<?php
/**
 * api/auth/google-login.php — Google ile Giriş / Kayıt
 * POST /api/auth/google-login.php
 * Body: { "credential": "<Google JWT>" }
 */
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

wb_method('POST');

$data       = wb_body();
$credential = trim((string)($data['credential'] ?? ''));

if (!$credential) {
    wb_err('Google token eksik', 400, 'missing_token');
}

// Google token doğrulama
$GOOGLE_CLIENT_ID = '279602177241-o5qmpgshp4g13jlrunnkav6vdu4hiejv.apps.googleusercontent.com';

$ctx      = stream_context_create(['http' => ['timeout' => 5]]);
$response = @file_get_contents(
    'https://oauth2.googleapis.com/tokeninfo?id_token=' . urlencode($credential),
    false,
    $ctx
);

if ($response === false) {
    wb_err('Google doğrulaması başarısız, tekrar deneyin', 502, 'google_unreachable');
}

$payload = json_decode($response, true);

if (
    empty($payload['email_verified']) || $payload['email_verified'] !== 'true' ||
    empty($payload['email']) ||
    ($payload['aud'] ?? '') !== $GOOGLE_CLIENT_ID
) {
    wb_err('Geçersiz Google token', 401, 'invalid_token');
}

$googleId  = $payload['sub'];
$email     = strtolower(trim($payload['email']));
$firstName = $payload['given_name']  ?? '';
$lastName  = $payload['family_name'] ?? '';
$avatar    = $payload['picture']     ?? '';
$fullName  = trim("$firstName $lastName") ?: 'Kullanıcı';

try {
    // Önce Google ID ile ara
    $stmt = $pdo->prepare("SELECT * FROM users WHERE google_id = ? LIMIT 1");
    $stmt->execute([$googleId]);
    $user = $stmt->fetch();

    if (!$user) {
        // Email ile ara (mevcut hesabı bağla)
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ? LIMIT 1");
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            $pdo->prepare("UPDATE users SET google_id = ?, avatar_url = COALESCE(NULLIF(avatar_url,''), ?) WHERE id = ?")
                ->execute([$googleId, $avatar, $user['id']]);
        } else {
            // Yeni kullanıcı oluştur
            $pdo->prepare("
                INSERT INTO users (google_id, email, full_name, avatar_url, email_verified_at, created_at)
                VALUES (?, ?, ?, ?, NOW(), NOW())
            ")->execute([$googleId, $email, $fullName, $avatar]);
            $user = ['id' => $pdo->lastInsertId(), 'full_name' => $fullName, 'email' => $email];
        }
    }

    // Oturumu başlat
    if (session_status() === PHP_SESSION_NONE) session_start();
    session_regenerate_id(true);
    $_SESSION['user_id']   = (int)$user['id'];
    $_SESSION['user_name'] = $user['full_name'] ?? $fullName;
    $_SESSION['login_via'] = 'google';

    wb_ok([
        'user' => [
            'id'    => (int)$user['id'],
            'name'  => $user['full_name'] ?? $fullName,
            'email' => $email,
        ]
    ]);

} catch (Throwable $e) {
    error_log('[google-login] ' . $e->getMessage());
    wb_err('Sunucu hatası, tekrar deneyin', 500, 'internal_error');
}