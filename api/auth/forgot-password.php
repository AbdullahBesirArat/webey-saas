<?php
// api/auth/forgot-password.php — Şifre sıfırlama emaili gönder
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../_mailer.php';
require_once __DIR__ . '/../_email_templates.php';
require_once __DIR__ . '/../_email_templates_auth.php';

wb_method('POST');
wb_csrf_verify(false);

$data  = wb_body();
$email = strtolower(trim((string)($data['email'] ?? '')));

if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    wb_err('Geçerli bir email adresi girin', 400, 'invalid_email');
}

// Hep aynı mesaj döndür (email varlığını sızdırma)
$genericMsg = 'Eğer bu adres kayıtlıysa şifre sıfırlama emaili gönderildi';

try {
    $stmt = $pdo->prepare("SELECT id, email, reset_token_expires FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user) {
        wb_ok(['message' => $genericMsg]);
    }

    // Rate limit: 5 dakikada bir
    if ($user['reset_token_expires']) {
        $expires = new DateTime($user['reset_token_expires']);
        $created = (clone $expires)->modify('-1 hour');
        $diff    = (new DateTime())->getTimestamp() - $created->getTimestamp();
        if ($diff < 300) {
            $wait = 300 - $diff;
            wb_err("Lütfen {$wait} saniye bekleyip tekrar deneyin", 429, 'rate_limited');
        }
    }

    $token   = bin2hex(random_bytes(32));
    $expires = date('Y-m-d H:i:s', time() + 3600);

    $pdo->prepare("UPDATE users SET reset_token = ?, reset_token_expires = ? WHERE id = ?")
        ->execute([$token, $expires, (int)$user['id']]);

    $cfg      = require __DIR__ . '/../_email_config.php';
    $resetUrl = rtrim($cfg['site_url'], '/') . '/sifre-sifirla.html?token=' . $token;
    [$subject, $html] = wbEmailPasswordReset(['name' => $user['email'], 'resetUrl' => $resetUrl]);
    wbMail($user['email'], $user['email'], $subject, $html);

    wb_ok(['message' => $genericMsg]);

} catch (Throwable $e) {
    error_log('[forgot-password] ' . $e->getMessage());
    // Güvenlik: hata detayı verme, generic mesaj dön
    wb_ok(['message' => $genericMsg]);
}