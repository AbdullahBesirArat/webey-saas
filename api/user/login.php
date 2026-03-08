<?php
declare(strict_types=1);
/**
 * api/user/login.php — Müşteri (end-user) girişi
 * ─────────────────────────────────────────────────
 * POST JSON: { phone, password }
 * Döner:     { ok, data: { userId, phone, firstName, lastName } }
 */

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('POST');

// ── Brute-force koruması: IP başına 5 dakikada 10 deneme ──────────────────────
$ip       = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
$window   = 300;  // 5 dakika
$maxTries = 10;

try {
    $pdo->prepare('DELETE FROM login_attempts WHERE ip = ? AND attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)')
        ->execute([$ip, $window]);
    $triesStmt = $pdo->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip = ?');
    $triesStmt->execute([$ip]);
    if ((int)$triesStmt->fetchColumn() >= $maxTries) {
        wb_err('Çok fazla başarısız deneme. 5 dakika sonra tekrar dene.', 429, 'rate_limited');
    }
} catch (Throwable) { /* login_attempts tablosu yoksa devam et */ }
// ══════════════════════════════════════════════

$in    = wb_body();
$phone = preg_replace('/\D+/', '', (string)($in['phone'] ?? ''));
$pass  = (string)($in['password'] ?? '');

// Başındaki 0 veya 90'ı at → 5xxxxxxxxx formatına getir
if (str_starts_with($phone, '90') && strlen($phone) === 12) $phone = substr($phone, 2);
if (str_starts_with($phone, '0')) $phone = substr($phone, 1);

wb_validate(['phone' => $phone, 'password' => $pass], [
    'phone'    => ['required', 'regex:/^5\d{9}$/'],
    'password' => ['required'],
]);

// Müşteri telefon numarasını sahte email olarak saklıyor
$email = $phone . '@phone.user';

try {
    $stmt = $pdo->prepare("
        SELECT id, password_hash
        FROM   users
        WHERE  email = ? AND role = 'user'
        LIMIT  1
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password_hash'])) {
        try {
            $pdo->prepare('INSERT INTO login_attempts (ip, attempted_at) VALUES (?, NOW())')
                ->execute([$ip]);
        } catch (Throwable) {}
        wb_err('Telefon veya şifre hatalı', 401);
    }

    $userId = (int)$user['id'];

    // Profil bilgisi
    $pStmt = $pdo->prepare('
        SELECT first_name, last_name, city
        FROM   customers
        WHERE  user_id = ?
        LIMIT  1
    ');
    $pStmt->execute([$userId]);
    $profile = $pStmt->fetch();

    // Son giriş zamanını güncelle
    $pdo->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?')
        ->execute([$userId]);

    // Session
    session_regenerate_id(true);
    $_SESSION['user_id']    = $userId;
    $_SESSION['user_role']  = 'user';
    $_SESSION['user_phone'] = $phone;
    unset($_SESSION['admin_id'], $_SESSION['business_id']); // Admin session temizle

    wb_ok([
        'userId'    => (string)$userId,
        'phone'     => $phone,
        'firstName' => $profile['first_name'] ?? '',
        'lastName'  => $profile['last_name']  ?? '',
    ]);

} catch (Throwable $e) {
    error_log('[user/login.php] ' . $e->getMessage());
    wb_err('Giriş yapılamadı. Lütfen tekrar deneyin.', 500);
}