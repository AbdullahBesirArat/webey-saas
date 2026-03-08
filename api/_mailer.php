<?php
// api/_mailer.php
// ─────────────────────────────────────────────────────────────────────
// Webey Email Gönderim Yardımcısı
// Kullanım:
//   require_once __DIR__ . '/_mailer.php';
//   wbMail('to@example.com', 'Ad Soyad', 'Konu', $htmlBody);
// ─────────────────────────────────────────────────────────────────────
declare(strict_types=1);

/**
 * Email gönder.
 *
 * @param string $toEmail    Alıcı email
 * @param string $toName     Alıcı adı
 * @param string $subject    Konu
 * @param string $htmlBody   HTML içerik
 * @param string $textBody   Düz metin (opsiyonel, otomatik üretilir)
 * @return bool
 */
function wbMail(
    string $toEmail,
    string $toName,
    string $subject,
    string $htmlBody,
    string $textBody = ''
): bool {
    if (empty($toEmail) || !filter_var($toEmail, FILTER_VALIDATE_EMAIL)) {
        error_log('[wbMail] Geçersiz email: ' . $toEmail);
        return false;
    }

    $cfg = require __DIR__ . '/_email_config.php';

    // Debug modu: gerçekte gönderme, sadece logla
    if (!empty($cfg['debug'])) {
        error_log('[wbMail DEBUG] To: ' . $toEmail . ' | Subject: ' . $subject);
        return true;
    }

    // Düz metin otomatik üret (HTML taglarını kaldır)
    if (!$textBody) {
        $textBody = html_entity_decode(strip_tags(str_replace(['<br>', '<br/>', '<br />', '</p>', '</div>'], "\n", $htmlBody)), ENT_QUOTES, 'UTF-8');
        $textBody = preg_replace('/\n{3,}/', "\n\n", trim($textBody));
    }

    // PHPMailer varsa kullan, yoksa PHP'nin native mail() ile dene
    $vendorAutoload = __DIR__ . '/../../vendor/autoload.php';
    if (file_exists($vendorAutoload)) {
        return _wbMailPHPMailer($cfg, $toEmail, $toName, $subject, $htmlBody, $textBody);
    } else {
        return _wbMailNative($cfg, $toEmail, $toName, $subject, $htmlBody, $textBody);
    }
}

/**
 * PHPMailer ile gönder (önerilen)
 */
function _wbMailPHPMailer(array $cfg, string $to, string $toName, string $subject, string $html, string $text): bool {
    require_once __DIR__ . '/../../vendor/autoload.php';

    try {
        $mail = new PHPMailer\PHPMailer\PHPMailer(true);

        $mail->isSMTP();
        $mail->Host        = $cfg['host'];
        $mail->SMTPAuth    = true;
        $mail->Username    = $cfg['username'];
        $mail->Password    = $cfg['password'];
        $mail->SMTPSecure  = $cfg['encryption'] === 'ssl'
            ? PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS
            : PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port        = (int)$cfg['port'];
        $mail->CharSet     = 'UTF-8';
        $mail->SMTPDebug   = 0;

        $mail->setFrom($cfg['from_email'], $cfg['from_name']);
        $mail->addAddress($to, $toName);
        $mail->addReplyTo($cfg['from_email'], $cfg['from_name']);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = $html;
        $mail->AltBody = $text;

        $mail->send();
        return true;
    } catch (Throwable $e) {
        error_log('[wbMail PHPMailer] ' . $e->getMessage());
        return false;
    }
}

/**
 * PHP native mail() ile gönder (fallback)
 * Basit shared hosting / XAMPP için
 */
function _wbMailNative(array $cfg, string $to, string $toName, string $subject, string $html, string $text): bool {
    try {
        $fromEmail = $cfg['from_email'];
        $fromName  = mb_encode_mimeheader($cfg['from_name'], 'UTF-8', 'B');
        $toFmt     = $toName ? (mb_encode_mimeheader($toName, 'UTF-8', 'B') . ' <' . $to . '>') : $to;
        $subjectFmt = mb_encode_mimeheader($subject, 'UTF-8', 'B');

        $boundary = '----=_Part_' . md5(uniqid((string)rand(), true));

        $headers  = "From: {$fromName} <{$fromEmail}>\r\n";
        $headers .= "Reply-To: {$fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
        $headers .= "X-Mailer: Webey\r\n";

        $body  = "--{$boundary}\r\n";
        $body .= "Content-Type: text/plain; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($text)) . "\r\n";
        $body .= "--{$boundary}\r\n";
        $body .= "Content-Type: text/html; charset=UTF-8\r\n";
        $body .= "Content-Transfer-Encoding: base64\r\n\r\n";
        $body .= chunk_split(base64_encode($html)) . "\r\n";
        $body .= "--{$boundary}--";

        return mail($toFmt, $subjectFmt, $body, $headers);
    } catch (Throwable $e) {
        error_log('[wbMail native] ' . $e->getMessage());
        return false;
    }
}