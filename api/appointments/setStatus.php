<?php
declare(strict_types=1);
/**
 * api/appointments/setStatus.php
 * POST { id, status?, attended? } — randevu durumu güncelle
 * Admin auth gerekli
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$businessId = (int)($_SESSION['business_id'] ?? 0);
if (!$businessId) { wb_err('İşletme bulunamadı', 403, 'no_business'); }

$data = wb_body();

$appointmentId = $data['id'] ?? $data['appointmentId'] ?? null;
if (!$appointmentId) { wb_err('Missing appointment id', 400, 'missing_param'); }

$status   = $data['status']   ?? null;
$attended = array_key_exists('attended', $data) ? $data['attended'] : null;

$fields = [];
$params = [];

if ($status !== null) {
    $allowedStatuses = ['pending', 'approved', 'cancelled', 'no_show', 'rejected', 'declined'];
    if (!in_array($status, $allowedStatuses, true)) {
        wb_err('Invalid status value', 400, 'invalid_status');
    }
    $fields[] = "status = ?";
    $params[] = $status;
    if ($status === 'no_show') {
        $fields[] = "attended = 0";
    }
}

if ($attended !== null) {
    $fields[] = "attended = ?";
    $params[] = $attended ? 1 : 0;
}

if (!$fields) {
    wb_ok(['updated' => false, 'message' => 'Nothing to update']);
}

try {
    $params[] = $appointmentId;
    $params[] = $businessId;

    $sql = "UPDATE appointments SET " . implode(', ', $fields) . " WHERE id = ? AND business_id = ?";
    $pdo->prepare($sql)->execute($params);

    // ── Email Bildirimleri ──────────────────────────────────────────────
    if ($status !== null && in_array($status, ['approved', 'cancelled', 'rejected'], true)) {
        try {
            require_once __DIR__ . '/../_mailer.php';
            require_once __DIR__ . '/../_email_templates.php';

            $apptFull = $pdo->prepare("
                SELECT a.*, b.name AS business_name, b.address_line, b.city, b.district,
                       s.name AS service_name, st.name AS staff_name,
                       u.email AS owner_email
                FROM appointments a
                LEFT JOIN businesses b ON b.id = a.business_id
                LEFT JOIN services   s ON s.id = a.service_id
                LEFT JOIN staff     st ON st.id = a.staff_id
                LEFT JOIN users      u ON u.id = b.owner_id
                WHERE a.id = ? LIMIT 1
            ");
            $apptFull->execute([$appointmentId]);
            $row = $apptFull->fetch();

            if ($row) {
                $emailData = wbApptToEmailData($row, $pdo);
                $custEmail = $row['customer_email'] ?? '';
                $custName  = $row['customer_name']  ?? 'Müşteri';

                if ($custEmail && filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
                    if ($status === 'approved') {
                        [$subj, $html] = wbEmailApptApproved($emailData);
                        wbMail($custEmail, $custName, $subj, $html);
                    } elseif (in_array($status, ['cancelled', 'rejected'], true)) {
                        [$subj, $html] = wbEmailApptCancelled($emailData);
                        wbMail($custEmail, $custName, $subj, $html);
                    }
                }

                // ── SMS Bildirimi (Onay / Red → müşteriye) ───────────
                try {
                    require_once __DIR__ . '/../_sms.php';
                    $custPhone = $row['customer_phone'] ?? '';
                    if (!empty($custPhone)) {
                        $dt      = new DateTimeImmutable($row['start_at'], new DateTimeZone('Europe/Istanbul'));
                        $bizName = $row['business_name'] ?? 'İşletme';
                        if ($status === 'approved') {
                            queueSms(
                                $pdo,
                                $custPhone,
                                smsApptApproved($bizName, $dt->format('d.m.Y'), $dt->format('H:i')),
                                'approved',
                                (int)$appointmentId
                            );
                        } elseif (in_array($status, ['cancelled', 'rejected'], true)) {
                            queueSms(
                                $pdo,
                                $custPhone,
                                smsApptRejected($bizName),
                                'rejected',
                                (int)$appointmentId
                            );
                        }
                    }
                } catch (Throwable $smsEx) {
                    error_log('[setStatus.php sms] ' . $smsEx->getMessage());
                }
                // ── SMS sonu ─────────────────────────────────────────

                // ── Web Push (müşteriye: onay/red bildirimi) ──────────
                try {
                    require_once __DIR__ . '/../_push.php';
                    if ($row['customer_user_id'] ?? null) {
                        $pushTitle = $status === 'approved' ? '✅ Randevunuz Onaylandı' : '❌ Randevunuz Reddedildi';
                        $pushBody  = ($row['business_name'] ?? 'İşletme') . ' — ' . date('d.m.Y H:i', strtotime($row['start_at']));
                        sendPushToUser($pdo, (int)$row['customer_user_id'], $pushTitle, $pushBody, '/appointments.html', 'appt-status');
                    }
                } catch (Throwable $pushEx) {
                    error_log('[setStatus.php push] ' . $pushEx->getMessage());
                }
                // ── Push sonu ─────────────────────────────────────────

                // ── User Notification (DB — kullanıcı bildirim tablosu) ──
                try {
                    $custUserId = $row['customer_user_id'] ?? null;

                    // customer_user_id NULL ise telefon ile kullanıcıyı bul (eski randevular)
                    if (!$custUserId && !empty($row['customer_phone'])) {
                        $phoneStmt = $pdo->prepare("SELECT user_id FROM customers WHERE phone = ? LIMIT 1");
                        $phoneStmt->execute([$row['customer_phone']]);
                        $phoneRow = $phoneStmt->fetch();
                        if ($phoneRow) $custUserId = (int)$phoneRow['user_id'];
                    }

                    if ($custUserId) {
                        $bizName  = $row['business_name'] ?? 'İşletme';
                        $dt       = new DateTimeImmutable($row['start_at'], new DateTimeZone('Europe/Istanbul'));
                        $dateFmt  = $dt->format('d.m.Y H:i');
                        $svcName  = $row['service_name'] ?? '';

                        if ($status === 'approved') {
                            $title   = '✅ Randevunuz Onaylandı';
                            $message = "{$bizName} — {$dateFmt}" . ($svcName ? " · {$svcName}" : '');
                            $type    = 'appt_approved';
                        } else {
                            $title   = '❌ Randevunuz İptal Edildi';
                            $message = "{$bizName} — {$dateFmt}" . ($svcName ? " · {$svcName}" : '');
                            $type    = ($status === 'rejected') ? 'appt_rejected' : 'appt_cancelled';
                        }

                        $pdo->prepare("
                            INSERT INTO user_notifications (user_id, appointment_id, type, title, message, business_name)
                            VALUES (?, ?, ?, ?, ?, ?)
                        ")->execute([
                            (int)$custUserId,
                            (int)$appointmentId,
                            $type,
                            $title,
                            $message,
                            $bizName,
                        ]);
                    }
                } catch (Throwable $unEx) {
                    error_log('[setStatus user_notif] ' . $unEx->getMessage());
                }
                // ── User Notification sonu ──────────────────────────────
            }
        } catch (Throwable $mailEx) {
            error_log('[setStatus mail] ' . $mailEx->getMessage());
        }
    }

    wb_ok(['updated' => true, 'data' => ['id' => (string)$appointmentId, 'status' => $status, 'attended' => $attended]]);

} catch (Throwable $e) {
    error_log('[appointments/setStatus.php] ' . $e->getMessage());
    wb_err('Randevu güncellenemedi', 500, 'internal_error');
}