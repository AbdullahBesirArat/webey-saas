<?php
// api/appointments/export-ics.php
// Randevuyu iCal (.ics) formatında indir → Google Calendar / Apple Calendar'a ekle
// GET ?token=<appointment_token>  ya da  GET ?id=<id> (oturum gerektirir)
declare(strict_types=1);

require_once __DIR__ . '/../wb_response.php';
require_once __DIR__ . '/../../db.php';

ini_set('display_errors', '0');
error_reporting(E_ALL);

if (session_status() === PHP_SESSION_NONE) {
    $isHttps = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
            || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https')
            || (($_SERVER['SERVER_PORT'] ?? '') === '443');
    ini_set('session.cookie_samesite',  'Lax');
    ini_set('session.cookie_httponly',  '1');
    ini_set('session.cookie_secure',    $isHttps ? '1' : '0');
    ini_set('session.use_strict_mode',  '1');
    ini_set('session.cookie_lifetime',  '0');
    ini_set('session.gc_maxlifetime',   '7200');
    session_start();
}

wb_method('GET');

// Auth: token veya oturum
$token = trim($_GET['token'] ?? '');
$id    = (int)($_GET['id']    ?? 0);

try {
    if ($token !== '') {
        // Token bazlı (e-postadaki linke tıklayan)
        $stmt = $pdo->prepare("
            SELECT a.*, b.name AS biz_name, b.address_line, b.city, b.district,
                   s.name AS service_name, st.name AS staff_name
            FROM appointments a
            LEFT JOIN businesses b  ON b.id = a.business_id
            LEFT JOIN services   s  ON s.id = a.service_id
            LEFT JOIN staff      st ON st.id = a.staff_id
            WHERE a.id = (
                SELECT id FROM appointments WHERE MD5(CONCAT(id, created_at)) = ? LIMIT 1
            )
        ");
        $stmt->execute([$token]);
    } elseif ($id > 0 && !empty($_SESSION['user_id'])) {
        $stmt = $pdo->prepare("
            SELECT a.*, b.name AS biz_name, b.address_line, b.city, b.district,
                   s.name AS service_name, st.name AS staff_name
            FROM appointments a
            LEFT JOIN businesses b  ON b.id = a.business_id
            LEFT JOIN services   s  ON s.id = a.service_id
            LEFT JOIN staff      st ON st.id = a.staff_id
            WHERE a.id = ? AND (a.customer_user_id = ? OR a.business_id IN (
                SELECT id FROM businesses WHERE owner_id = ?
            ))
        ");
        $stmt->execute([$id, (int)$_SESSION['user_id'], (int)$_SESSION['user_id']]);
    } else {
        wb_err('Yetkisiz erişim', 401, 'unauthorized');
    }

    $appt = $stmt->fetch();
    if (!$appt) {
        wb_err('Randevu bulunamadı', 404, 'not_found');
    }

    // ── ICS dosyası oluştur ───────────────────────────────
    $dtStamp   = gmdate('Ymd\THis\Z');
    $dtStart   = (new DateTimeImmutable($appt['start_at'], new DateTimeZone('Europe/Istanbul')))
                    ->format('Ymd\THis');
    $dtEnd     = (new DateTimeImmutable($appt['end_at'],   new DateTimeZone('Europe/Istanbul')))
                    ->format('Ymd\THis');
    $uid       = 'appt-' . $appt['id'] . '@webey.com.tr';
    $summary   = ($appt['service_name'] ?? 'Randevu') . ' — ' . ($appt['biz_name'] ?? '');
    $location  = implode(', ', array_filter([
        $appt['address_line'] ?? '', $appt['district'] ?? '', $appt['city'] ?? ''
    ]));
    $staff     = $appt['staff_name'] ? "\nPersonel: " . $appt['staff_name'] : '';
    $desc      = 'Webey randevunuz.' . $staff . '\nRandevu ID: #' . $appt['id'];

    // ICS metin uzunluk sınırı: 75 karakter
    $fold = fn(string $line): string => preg_replace('/(.{75})/u', "$1\r\n ", $line);

    $ics = "BEGIN:VCALENDAR\r\n"
         . "VERSION:2.0\r\n"
         . "PRODID:-//Webey//Webey Randevu//TR\r\n"
         . "CALSCALE:GREGORIAN\r\n"
         . "METHOD:PUBLISH\r\n"
         . "BEGIN:VEVENT\r\n"
         . "UID:{$uid}\r\n"
         . "DTSTAMP:{$dtStamp}\r\n"
         . "DTSTART;TZID=Europe/Istanbul:{$dtStart}\r\n"
         . "DTEND;TZID=Europe/Istanbul:{$dtEnd}\r\n"
         . $fold("SUMMARY:{$summary}") . "\r\n"
         . $fold("LOCATION:{$location}") . "\r\n"
         . $fold("DESCRIPTION:{$desc}") . "\r\n"
         . "STATUS:CONFIRMED\r\n"
         . "BEGIN:VALARM\r\n"
         . "TRIGGER:-PT1H\r\n"
         . "ACTION:DISPLAY\r\n"
         . "DESCRIPTION:Randevunuza 1 saat kaldı!\r\n"
         . "END:VALARM\r\n"
         . "BEGIN:VALARM\r\n"
         . "TRIGGER:-PT24H\r\n"
         . "ACTION:DISPLAY\r\n"
         . "DESCRIPTION:Yarın randevunuz var!\r\n"
         . "END:VALARM\r\n"
         . "END:VEVENT\r\n"
         . "END:VCALENDAR\r\n";

    $filename = 'webey-randevu-' . $appt['id'] . '.ics';
    header('Content-Type: text/calendar; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store');
    echo $ics;
    exit;

} catch (Throwable $e) {
    error_log('[export-ics.php] ' . $e->getMessage());
    wb_err('İCS dosyası oluşturulamadı', 500, 'internal_error');
}