<?php
declare(strict_types=1);
/**
 * api/appointments/book.php
 * POST JSON: { businessId, staffId, serviceId, dayStr, startMin, durationMin,
 *              customer:{uid,name,phoneE164}, status, source, notes }
 * PUBLIC — profile.js persistBookingAndGo() tarafından kullanılır
 * Döner: { ok:true, data:{ id, rid } }
 */

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('POST');

// ── IP Tabanlı Rate Limiting: 1 dakikada 10 randevu denemesi ─────────────────
$ip      = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
$rateKey = 'book:' . md5($ip);
$rateWindow = 60;
$rateMax    = 10;

try {
    $pdo->prepare('DELETE FROM api_rate_limits WHERE cache_key = ? AND expires_at < NOW()')
        ->execute([$rateKey]);
    $rStmt = $pdo->prepare('SELECT hits FROM api_rate_limits WHERE cache_key = ? LIMIT 1');
    $rStmt->execute([$rateKey]);
    $hits = (int)($rStmt->fetchColumn() ?: 0);
    if ($hits >= $rateMax) {
        wb_err('Çok fazla istek gönderildi. Lütfen 1 dakika bekleyin.', 429, 'rate_limited');
    }
    if ($hits === 0) {
        $pdo->prepare('INSERT INTO api_rate_limits (cache_key, hits, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL ? SECOND))')
            ->execute([$rateKey, $rateWindow]);
    } else {
        $pdo->prepare('UPDATE api_rate_limits SET hits = hits + 1 WHERE cache_key = ?')
            ->execute([$rateKey]);
    }
} catch (Throwable) { /* Tablo yoksa devam et */ }
// ─────────────────────────────────────────────────────────────────────────────


$data = wb_body();
if (!is_array($data)) { wb_err('Geçersiz JSON', 400); }

$businessId   = (int)($data['businessId']  ?? 0);
$staffIdRaw   = trim($data['staffId']      ?? 'any');
$serviceIdRaw = trim($data['serviceId']    ?? '');
$dayStr       = trim($data['dayStr']       ?? '');
$startMin     = (int)($data['startMin']    ?? -1);
$durationMin  = (int)($data['durationMin'] ?? 0);
$statusIn     = trim($data['status']       ?? 'pending');
$notes        = trim($data['notes']        ?? '');
// Müşterinin slot seçiminde aldığı kilit token'ı (opsiyonel ama önerilir)
$lockToken    = trim($data['lockToken']    ?? '');

$customer  = is_array($data['customer'] ?? null) ? $data['customer'] : [];
$custName  = trim($customer['name']      ?? '');
$custPhone = trim($customer['phoneE164'] ?? $customer['phone'] ?? '');
$custEmail = trim($customer['email'] ?? '');
if ($custEmail && !filter_var($custEmail, FILTER_VALIDATE_EMAIL)) $custEmail = '';

// Validasyon
if (!$businessId || !$dayStr || $startMin < 0 || $durationMin <= 0) {
    wb_err('businessId, dayStr, startMin, durationMin zorunlu', 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayStr)) {
    wb_err('dayStr YYYY-MM-DD formatında olmalı', 400);
}
if ($custName === '') {
    wb_err('Müşteri adı zorunlu', 400);
}

// ── Abonelik kontrolü: işletme sahibinin aboneliği aktif mi? ──────────────────
require_once __DIR__ . '/../_subscription_check.php';
$subStatus = getBusinessSubscriptionStatus($pdo, $businessId);
if (!$subStatus['active']) {
    wb_err('Bu işletme şu anda randevu kabul edemiyor.', 403, 'subscription_expired');
}
// ─────────────────────────────────────────────────────────────────────────────

// startMin → datetime
$startH   = (int)floor($startMin / 60);
$startM   = $startMin % 60;
$startStr = sprintf('%s %02d:%02d:00', $dayStr, $startH, $startM);
$endMin   = $startMin + $durationMin;
$endH     = (int)floor($endMin / 60);
$endM     = $endMin % 60;
$endStr   = sprintf('%s %02d:%02d:00', $dayStr, $endH, $endM);

// Personel
$staffId = null;
if ($staffIdRaw && $staffIdRaw !== 'any' && is_numeric($staffIdRaw)) {
    $staffId = (int)$staffIdRaw;
}

// Servis ID
$serviceId = null;
if (is_numeric($serviceIdRaw) && (int)$serviceIdRaw > 0) {
    $serviceId = (int)$serviceIdRaw;
} elseif ($serviceIdRaw !== '' && $serviceIdRaw !== 'general') {
    $svcStmt = $pdo->prepare("SELECT id FROM services WHERE business_id = ? AND (name = ? OR LOWER(REPLACE(name,' ','-')) = ?) LIMIT 1");
    $svcStmt->execute([$businessId, $serviceIdRaw, strtolower($serviceIdRaw)]);
    $svcRow = $svcStmt->fetch();
    if ($svcRow) $serviceId = (int)$svcRow['id'];
}

$finalStatus = in_array($statusIn, ['pending','confirmed','approved'], true) ? $statusIn : 'pending';

try {
    $pdo->beginTransaction();

    // Suresi dolan kilitleri temizle
    try { $pdo->prepare('DELETE FROM slot_locks WHERE expires_at < NOW()')->execute(); } catch (Throwable) {}

    // Kilit dogrulama
    $lockVerified = false;
    if ($lockToken !== '') {
        try {
            $lkStmt = $pdo->prepare('SELECT id FROM slot_locks WHERE lock_token = ? AND business_id = ? AND day_str = ? AND start_min = ? AND expires_at >= NOW() LIMIT 1');
            $lkStmt->execute([$lockToken, $businessId, $dayStr, $startMin]);
            $lockVerified = (bool)$lkStmt->fetch();
        } catch (Throwable) {}
    }

    // Personel bazlı çakışma (kilit doğrulanmadıysa)
    if (!$lockVerified && $staffId) {
        $cfStmt = $pdo->prepare("
            SELECT id FROM appointments
            WHERE business_id = ?
              AND staff_id = ?
              AND status NOT IN ('cancelled','no_show','rejected','declined')
              AND start_at < ?
              AND end_at   > ?
            LIMIT 1
        ");
        $cfStmt->execute([$businessId, $staffId, $endStr, $startStr]);
        if ($cfStmt->fetch()) {
            $pdo->rollBack();
            wb_err('Bu saat dolu', 409, 'time_conflict');
        }
    }

    // customer_user_id: önce gelen uid, yoksa session'dan al, yoksa telefon ile bul
    $customerUserId = null;
    if (!empty($customer['uid']) && is_numeric($customer['uid'])) {
        $customerUserId = (int)$customer['uid'];
    } elseif (!empty($_SESSION['user_id']) && ($_SESSION['user_role'] ?? '') === 'user') {
        $customerUserId = (int)$_SESSION['user_id'];
    } elseif ($custPhone) {
        try {
            $cuStmt = $pdo->prepare("SELECT user_id FROM customers WHERE phone = ? LIMIT 1");
            $cuStmt->execute([$custPhone]);
            $cuRow = $cuStmt->fetch();
            if ($cuRow) $customerUserId = (int)$cuRow['user_id'];
        } catch (Throwable) {}
    }

    // Schema'ya göre sabit kolon listesi
    $fields  = ['business_id','staff_id','service_id','customer_name','customer_phone','customer_email','customer_user_id','start_at','end_at','status','notes','created_at'];
    $values  = [$businessId, $staffId, $serviceId, $custName, $custPhone ?: null, $custEmail ?: null, $customerUserId, $startStr, $endStr, $finalStatus, $notes ?: null, date('Y-m-d H:i:s')];
    $holders = array_fill(0, count($fields), '?');

    $sql = 'INSERT INTO appointments (' . implode(',', $fields) . ') VALUES (' . implode(',', $holders) . ')';
    $pdo->prepare($sql)->execute($values);

    $newId = (string)$pdo->lastInsertId();

    // Kilit kaldir: randevu basariyla olusturuldu, lock artik gerekli degil
    if ($lockToken !== '') {
        try {
            $pdo->prepare('DELETE FROM slot_locks WHERE lock_token = ?')->execute([$lockToken]);
        } catch (Throwable) {}
    }

    // ── Bildirim kaydı ──────────────────────────────────────────────────────
    try {
        $svcName  = null;
        $stfName  = null;
        if ($serviceId) {
            $tmp = $pdo->prepare("SELECT name FROM services WHERE id=? LIMIT 1");
            $tmp->execute([$serviceId]);
            $svcName = $tmp->fetchColumn() ?: null;
        }
        if ($staffId) {
            $tmp = $pdo->prepare("SELECT name FROM staff WHERE id=? LIMIT 1");
            $tmp->execute([$staffId]);
            $stfName = $tmp->fetchColumn() ?: null;
        }
        $pdo->prepare("
            INSERT IGNORE INTO notifications
              (business_id,appointment_id,type,customer_name,customer_phone,
               service_name,staff_name,appointment_start,result,created_at)
            VALUES (?,?,'booking',?,?,?,?,?,'pending',NOW())
        ")->execute([$businessId, (int)$newId, $custName, $custPhone ?: null,
                     $svcName, $stfName, $startStr]);
    } catch (Throwable $nErr) {
        error_log('[book.php notification] ' . $nErr->getMessage());
    }
    // ────────────────────────────────────────────────────────────────────────

    $pdo->commit();

    // ── Email Bildirimleri ──────────────────────────────────────────────
    try {
        require_once __DIR__ . '/../_mailer.php';
        require_once __DIR__ . '/../_email_templates.php';
        $apptRow = [
            'business_id'    => $businessId,
            'staff_id'       => $staffId,
            'service_id'     => $serviceId,
            'customer_name'  => $custName,
            'customer_phone' => $custPhone,
            'customer_email' => $custEmail ?? '',
            'start_at'       => $startStr,
            'status'         => $finalStatus,
        ];
        $emailData = wbApptToEmailData($apptRow, $pdo);
        if (!empty($custEmail)) {
            [$subj, $html] = wbEmailApptConfirm($emailData);
            wbMail($custEmail, $custName, $subj, $html);
        }
        if (!empty($emailData['ownerEmail'])) {
            [$subj, $html] = wbEmailNewApptBiz($emailData);
            wbMail($emailData['ownerEmail'], $emailData['bizName'], $subj, $html);
        }
    } catch (Throwable $mailEx) {
        error_log('[book.php mail] ' . $mailEx->getMessage());
    }

    // ── SMS Bildirimi (Randevu alındı → müşteriye) ──────────────────
    try {
        require_once __DIR__ . '/../_sms.php';
        if (!empty($custPhone)) {
            $dt          = new DateTimeImmutable($startStr, new DateTimeZone('Europe/Istanbul'));
            $bizNameSms  = $emailData['bizName'] ?? 'İşletme';
            queueSms(
                $pdo,
                $custPhone,
                smsApptBooked($bizNameSms, $dt->format('d.m.Y'), $dt->format('H:i')),
                'booking',
                (int)$newId
            );
        }
    } catch (Throwable $smsEx) {
        error_log('[book.php sms] ' . $smsEx->getMessage());
    }
    // ── SMS sonu ────────────────────────────────────────────────────

    // ── Web Push (işletme sahibine anlık bildirim) ───────────────────
    try {
        require_once __DIR__ . '/../_push.php';
        $dt       = new DateTimeImmutable($startStr, new DateTimeZone('Europe/Istanbul'));
        sendPushToBusiness(
            $pdo,
            $businessId,
            '🔔 Yeni Randevu',
            $custName . ' — ' . $dt->format('d.m.Y H:i'),
            '/calendar.html',
            'new-booking'
        );
    } catch (Throwable $pushEx) {
        error_log('[book.php push] ' . $pushEx->getMessage());
    }
    // ── Push sonu ────────────────────────────────────────────────────

    wb_ok([
        'id'      => $newId,
        'rid'     => $newId,
        'status'  => $finalStatus,
        'startAt' => $startStr,
        'endAt'   => $endStr,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[book.php] ' . $e->getMessage());
    wb_err('Randevu oluşturulamadı. Lütfen tekrar deneyin.', 500);
}