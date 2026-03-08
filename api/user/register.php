<?php
declare(strict_types=1);
/**
 * api/user/register.php — Müşteri (end-user) kaydı
 * POST JSON: { phone, password, firstName?, lastName?, birthday?, city?, district?, neighborhood? }
 */

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('POST');

$in = wb_body();

// ── Zorunlu alanlar ──
$phone    = preg_replace('/\D+/', '', (string)($in['phone'] ?? ''));
$password = (string)($in['password'] ?? '');

if (str_starts_with($phone, '90') && strlen($phone) === 12) $phone = substr($phone, 2);
if (str_starts_with($phone, '0')) $phone = substr($phone, 1);

wb_validate(['phone' => $phone, 'password' => $password], [
    'phone'    => ['required', 'regex:/^5\d{9}$/'],
    'password' => ['required', 'min:8'],
]);

// Telefonu sahte email olarak sakla (users tablosu email bazlı)
$email = $phone . '@phone.user';

// ── Opsiyonel profil bilgileri ──
$firstName    = trim((string)($in['firstName']    ?? ''));
$lastName     = trim((string)($in['lastName']     ?? ''));
$birthday     = trim((string)($in['birthday']     ?? ''));
$city         = trim((string)($in['city']         ?? ''));
$district     = trim((string)($in['district']     ?? ''));
$neighborhood = trim((string)($in['neighborhood'] ?? ''));
$smsOk        = (bool)($in['smsOk']   ?? true);
$emailOk      = (bool)($in['emailOk'] ?? false);

try {
    // Var mı?
    $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $chk->execute([$email]);
    if ($chk->fetchColumn()) {
        wb_err('Bu telefon numarası zaten kayıtlı. Giriş yapın.', 409, 'phone_exists');
    }

    $pdo->beginTransaction();

    // users kaydı
    $hash = password_hash($password, PASSWORD_BCRYPT, ['cost' => 11]);
    $pdo->prepare("INSERT INTO users (email, password_hash, role, created_at) VALUES (?,?,'user', NOW())")
        ->execute([$email, $hash]);
    $userId = (int)$pdo->lastInsertId();

    // customers profil kaydı
    $pdo->prepare("
        INSERT INTO customers (user_id, first_name, last_name, phone, birthday, city, district, neighborhood, sms_ok, email_ok)
        VALUES (?,?,?,?,?,?,?,?,?,?)
    ")->execute([
        $userId,
        $firstName ?: null,
        $lastName  ?: null,
        $phone,
        ($birthday && preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthday)) ? $birthday : null,
        $city ?: null,
        $district ?: null,
        $neighborhood ?: null,
        $smsOk  ? 1 : 0,
        $emailOk ? 1 : 0,
    ]);

    $pdo->commit();

    // Oturumu aç
    session_regenerate_id(true);
    $_SESSION['user_id']    = $userId;
    $_SESSION['user_role']  = 'user';
    $_SESSION['user_phone'] = $phone;
    unset($_SESSION['admin_id'], $_SESSION['business_id']);

    wb_ok([
        'userId' => (string)$userId,
        'phone'  => $phone,
        'mode'   => 'created',
    ], 201);

} catch (Throwable $e) {
    if (isset($pdo) && $pdo->inTransaction()) $pdo->rollBack();
    error_log('[user/register.php] ' . $e->getMessage());
    wb_err('Kayıt başarısız. Lütfen tekrar deneyin.', 500);
}