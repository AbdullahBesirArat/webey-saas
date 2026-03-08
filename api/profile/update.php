<?php
// api/profile/update.php — Admin profil güncelleme (email, telefon, şifre)
declare(strict_types=1);

require_once __DIR__ . '/../_bootstrap.php';

wb_method('POST');
wb_csrf_verify(false);

$sess   = wb_auth();
$userId = $sess['user_id'];
$data   = wb_body();
$action = (string)($data['action'] ?? '');

try {
    switch ($action) {

        case 'update_email':
            $email = trim(strtolower((string)($data['email'] ?? '')));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                wb_err('Geçersiz e-posta adresi', 422, 'invalid_email');
            }
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ?");
            $chk->execute([$email, $userId]);
            if ($chk->fetch()) {
                wb_err('Bu e-posta zaten kullanımda', 409, 'email_taken');
            }
            $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$email, $userId]);
            $_SESSION['email'] = $email;
            wb_ok(['email' => $email]);
            break;

        case 'update_phone':
            $phone = preg_replace('/\D+/', '', (string)($data['phone'] ?? ''));
            if ($phone && !preg_match('/^5\d{9}$/', $phone)) {
                wb_err('Geçersiz numara (5xxxxxxxxx formatında olmalı)', 422, 'invalid_phone');
            }
            $pdo->prepare("UPDATE businesses SET phone = ? WHERE owner_id = ?")->execute([$phone ?: null, $userId]);
            wb_ok(['phone' => $phone]);
            break;

        case 'change_password':
            $curPw = (string)($data['currentPassword'] ?? '');
            $newPw = (string)($data['newPassword']     ?? '');
            if (mb_strlen($newPw) < 8) {
                wb_err('Yeni şifre en az 8 karakter olmalı', 422, 'password_too_short');
            }
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($curPw, $row['password_hash'])) {
                wb_err('Mevcut şifre hatalı', 403, 'wrong_password');
            }
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 11]), $userId]);
            wb_ok(['changed' => true]);
            break;

        default:
            wb_err('Geçersiz action', 400, 'invalid_action');
    }
} catch (Throwable $e) {
    error_log('[profile/update] ' . $e->getMessage());
    wb_err('Sunucu hatası', 500, 'internal_error');
}