<?php
// api/billing/cron_reminders.php — Randevu Hatırlatma Cron Job'u
// ──────────────────────────────────────────────────────────────────────
// Crontab:  */15 * * * *  php /var/www/html/api/billing/cron_reminders.php >> /var/log/webey_reminders.log 2>&1
//
// Bu script:
//   1. 24 saat içindeki onaylanmış randevular için email + SMS kuyruğa ekler
//   2. 1 saat içindeki onaylanmış randevular için email + SMS kuyruğa ekler
//   3. appointment_reminders tablosuna kaydeder — aynı hatırlatma 2 kez gitmez
// ──────────────────────────────────────────────────────────────────────
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403);
    exit('Forbidden');
}

require __DIR__ . '/../../db.php';
require __DIR__ . '/../_mailer.php';
require __DIR__ . '/../_email_templates.php';
require __DIR__ . '/../_sms.php';

$now    = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));
$counts = ['email_24h'=>0,'sms_24h'=>0,'email_1h'=>0,'sms_1h'=>0];
$errors = 0;

echo "[" . $now->format('Y-m-d H:i:s') . "] Hatırlatma cron başladı\n";

/**
 * Bu randevu/kanal/zaman için daha önce hatırlatma gönderildi mi?
 * Gönderilmediyse appointment_reminders'a pending kaydı ekle ve TRUE döndür.
 */
function shouldSendReminder(PDO $pdo, int $apptId, string $channel, int $remindBefore): bool {
    $check = $pdo->prepare("SELECT id FROM appointment_reminders WHERE appointment_id=? AND channel=? AND remind_before=? LIMIT 1");
    $check->execute([$apptId, $channel, $remindBefore]);
    if ($check->fetch()) return false;

    $pdo->prepare("INSERT INTO appointment_reminders (appointment_id, channel, remind_before, status, created_at) VALUES (?,?,?,'pending',NOW())")
        ->execute([$apptId, $channel, $remindBefore]);
    return true;
}

function markReminderSent(PDO $pdo, int $apptId, string $channel, int $remindBefore): void {
    $pdo->prepare("UPDATE appointment_reminders SET status='sent', sent_at=NOW() WHERE appointment_id=? AND channel=? AND remind_before=?")
        ->execute([$apptId, $channel, $remindBefore]);
}

function fetchApptsInWindow(PDO $pdo, string $minInterval, string $maxInterval): array {
    $stmt = $pdo->prepare("
        SELECT a.id, a.start_at, a.customer_name, a.customer_email, a.customer_phone,
               b.name AS biz_name, b.address_line, b.city, b.district,
               s.name AS service_name, st.name AS staff_name
        FROM   appointments a
        LEFT JOIN businesses b  ON b.id  = a.business_id
        LEFT JOIN services   s  ON s.id  = a.service_id
        LEFT JOIN staff     st  ON st.id = a.staff_id
        WHERE  a.status IN ('approved','pending')
          AND  a.start_at BETWEEN DATE_ADD(NOW(), INTERVAL $minInterval)
                               AND DATE_ADD(NOW(), INTERVAL $maxInterval)
        LIMIT 200
    ");
    $stmt->execute();
    return $stmt->fetchAll(PDO::FETCH_ASSOC);
}

function buildReminderEmailHtml(array $d): string {
    $icsBtn    = $d['ics_url'] ? "<a href=\"{$d['ics_url']}\" style=\"display:inline-block;margin-top:14px;padding:10px 20px;background:#0ea5b3;color:#fff;border-radius:8px;text-decoration:none;font-size:14px;font-weight:600;\">📅 Takvime Ekle</a>" : '';
    $staffLine = $d['staff'] ? "<p style=\"margin:4px 0;color:#555;\">👤 Personel: <strong>{$d['staff']}</strong></p>" : '';
    return "<!DOCTYPE html><html><head><meta charset='utf-8'/></head><body style='font-family:Inter,sans-serif;background:#f5f5f5;padding:24px;'><div style='max-width:520px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;'><div style='background:linear-gradient(135deg,#0ea5b3,#0b6ef4);padding:28px 32px;text-align:center;'><h1 style='color:#fff;margin:0;font-size:22px;'>⏰ Randevu Hatırlatması</h1><p style='color:rgba(255,255,255,.8);margin:6px 0 0;font-size:14px;'>{$d['period']} sonra randevunuz var</p></div><div style='padding:28px 32px;'><p style='color:#333;font-size:16px;'>Merhaba <strong>{$d['customer_name']}</strong>,</p><div style='background:#f8f9ff;border-radius:12px;padding:18px;margin:16px 0;border-left:4px solid #0ea5b3;'><p style='margin:4px 0;color:#333;font-size:17px;font-weight:700;'>🏪 {$d['biz_name']}</p><p style='margin:4px 0;color:#555;'>✂️ Hizmet: <strong>{$d['service']}</strong></p>{$staffLine}<p style='margin:8px 0 4px;color:#555;'>📅 <strong>{$d['date']}</strong> saat <strong>{$d['time']}</strong></p><p style='margin:4px 0;color:#888;font-size:13px;'>📍 {$d['address']}</p></div>{$icsBtn}</div><div style='background:#f8f9fa;padding:16px 32px;text-align:center;'><p style='color:#aaa;font-size:12px;margin:0;'>© 2026 Webey</p></div></div></body></html>";
}

// ── 24 Saatlik hatırlatma ────────────────────────────────────────────
$appts24 = fetchApptsInWindow($pdo, '23 HOUR', '25 HOUR');
echo "  24h penceresi: " . count($appts24) . " randevu\n";

foreach ($appts24 as $appt) {
    $apptId = (int)$appt['id'];
    $dt     = new DateTimeImmutable($appt['start_at'], new DateTimeZone('Europe/Istanbul'));
    $date   = $dt->format('d.m.Y');
    $time   = $dt->format('H:i');
    $addr   = implode(', ', array_filter([$appt['address_line']??'',$appt['district']??'',$appt['city']??'']));

    // Email 24h
    if (!empty($appt['customer_email']) && shouldSendReminder($pdo, $apptId, 'email', 24)) {
        try {
            $icsUrl  = 'https://webey.com.tr/api/appointments/export-ics.php?token=' . md5($apptId . $appt['start_at']);
            $subject = '⏰ Yarın randevunuz var! — ' . ($appt['biz_name'] ?? 'Webey');
            $html    = buildReminderEmailHtml(['customer_name'=>$appt['customer_name'],'biz_name'=>$appt['biz_name']??'','service'=>$appt['service_name']??'','staff'=>$appt['staff_name']??'','date'=>$date,'time'=>$time,'address'=>$addr,'ics_url'=>$icsUrl,'period'=>'24 saat']);
            $pdo->prepare("INSERT INTO email_queue (to_email,to_name,subject,body_html,status,created_at) VALUES (?,?,?,?,'pending',NOW())")
                ->execute([$appt['customer_email'],$appt['customer_name'],$subject,$html]);
            markReminderSent($pdo, $apptId, 'email', 24);
            $counts['email_24h']++;
            echo "    [email 24h] #{$apptId}\n";
        } catch (Throwable $e) { $errors++; error_log('[cron_reminders][email 24h] #'.$apptId.' '.$e->getMessage()); }
    }

    // SMS 24h
    if (!empty($appt['customer_phone']) && shouldSendReminder($pdo, $apptId, 'sms', 24)) {
        try {
            queueSms($pdo, $appt['customer_phone'], smsReminder24h($appt['biz_name']??'Webey',$date,$time), 'reminder_24h', $apptId);
            markReminderSent($pdo, $apptId, 'sms', 24);
            $counts['sms_24h']++;
            echo "    [sms 24h]   #{$apptId} → {$appt['customer_phone']}\n";
        } catch (Throwable $e) { $errors++; error_log('[cron_reminders][sms 24h] #'.$apptId.' '.$e->getMessage()); }
    }
}

// ── 1 Saatlik hatırlatma ─────────────────────────────────────────────
$stmt1 = $pdo->prepare("
    SELECT a.id, a.start_at, a.customer_name, a.customer_email, a.customer_phone,
           b.name AS biz_name, b.address_line, b.city, b.district,
           s.name AS service_name, st.name AS staff_name
    FROM   appointments a
    LEFT JOIN businesses b  ON b.id  = a.business_id
    LEFT JOIN services   s  ON s.id  = a.service_id
    LEFT JOIN staff     st  ON st.id = a.staff_id
    WHERE  a.status IN ('approved','pending')
      AND  a.start_at BETWEEN DATE_ADD(NOW(), INTERVAL 50 MINUTE)
                           AND DATE_ADD(NOW(), INTERVAL 70 MINUTE)
    LIMIT 200
");
$stmt1->execute();
$appts1h = $stmt1->fetchAll(PDO::FETCH_ASSOC);
echo "  1h penceresi: " . count($appts1h) . " randevu\n";

foreach ($appts1h as $appt) {
    $apptId = (int)$appt['id'];
    $dt     = new DateTimeImmutable($appt['start_at'], new DateTimeZone('Europe/Istanbul'));
    $date   = $dt->format('d.m.Y');
    $time   = $dt->format('H:i');
    $addr   = implode(', ', array_filter([$appt['address_line']??'',$appt['district']??'',$appt['city']??'']));

    // Email 1h
    if (!empty($appt['customer_email']) && shouldSendReminder($pdo, $apptId, 'email', 1)) {
        try {
            $subject = '🕐 1 saate randevunuz! — ' . ($appt['biz_name'] ?? 'Webey');
            $html    = buildReminderEmailHtml(['customer_name'=>$appt['customer_name'],'biz_name'=>$appt['biz_name']??'','service'=>$appt['service_name']??'','staff'=>$appt['staff_name']??'','date'=>$date,'time'=>$time,'address'=>$addr,'ics_url'=>'','period'=>'1 saat']);
            $pdo->prepare("INSERT INTO email_queue (to_email,to_name,subject,body_html,status,created_at) VALUES (?,?,?,?,'pending',NOW())")
                ->execute([$appt['customer_email'],$appt['customer_name'],$subject,$html]);
            markReminderSent($pdo, $apptId, 'email', 1);
            $counts['email_1h']++;
            echo "    [email 1h] #{$apptId}\n";
        } catch (Throwable $e) { $errors++; error_log('[cron_reminders][email 1h] #'.$apptId.' '.$e->getMessage()); }
    }

    // SMS 1h
    if (!empty($appt['customer_phone']) && shouldSendReminder($pdo, $apptId, 'sms', 1)) {
        try {
            queueSms($pdo, $appt['customer_phone'], smsReminder1h($appt['biz_name']??'Webey',$time), 'reminder_1h', $apptId);
            markReminderSent($pdo, $apptId, 'sms', 1);
            $counts['sms_1h']++;
            echo "    [sms 1h]   #{$apptId} → {$appt['customer_phone']}\n";
        } catch (Throwable $e) { $errors++; error_log('[cron_reminders][sms 1h] #'.$apptId.' '.$e->getMessage()); }
    }
}

$end = date('H:i:s');
echo "[{$end}] Tamamlandı — email24:{$counts['email_24h']} sms24:{$counts['sms_24h']} email1h:{$counts['email_1h']} sms1h:{$counts['sms_1h']} hata:{$errors}\n\n";