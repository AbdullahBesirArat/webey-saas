<?php
// api/auth/verify-email.php — Email doğrulama token kontrolü
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

wb_method('POST');
wb_csrf_verify(false);

$data  = wb_body();
$token = trim((string)($data['token'] ?? ''));

if (!$token || strlen($token) < 32) {
    wb_err('Geçersiz token', 400, 'invalid_token');
}

try {
    $stmt = $pdo->prepare("
        SELECT id, email, email_verified_at
        FROM users
        WHERE email_verify_token = ?
          AND email_verify_sent_at > DATE_SUB(NOW(), INTERVAL 24 HOUR)
        LIMIT 1
    ");
    $stmt->execute([$token]);
    $user = $stmt->fetch();

    if (!$user) {
        wb_err('Bu doğrulama linki geçersiz veya süresi dolmuş', 410, 'token_expired');
    }

    if ($user['email_verified_at']) {
        wb_ok(['already' => true]);
    }

    $pdo->prepare("
        UPDATE users
        SET email_verified_at    = NOW(),
            email_verify_token   = NULL,
            email_verify_sent_at = NULL
        WHERE id = ?
    ")->execute([(int)$user['id']]);

    // Aktif session varsa güncelle
    if (isset($_SESSION['user_id']) && (int)$_SESSION['user_id'] === (int)$user['id']) {
        $_SESSION['email_verified'] = true;
    }

    wb_ok(['already' => false]);

} catch (Throwable $e) {
    error_log('[verify-email] ' . $e->getMessage());
    wb_err('Sunucu hatası, lütfen tekrar deneyin', 500, 'internal_error');
}