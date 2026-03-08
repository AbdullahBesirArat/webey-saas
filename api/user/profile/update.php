<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
wb_method('POST');

$userId = $user['user_id'];
$in     = wb_body();
$action = $in['action'] ?? '';

try {
    switch ($action) {

        case 'update_name': {
            $fn = trim((string)($in['firstName'] ?? ''));
            $ln = trim((string)($in['lastName']  ?? ''));
            $bd = trim((string)($in['birthday']  ?? ''));
            $pdo->prepare("
                INSERT INTO customers (user_id, first_name, last_name, birthday)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    first_name = VALUES(first_name),
                    last_name  = VALUES(last_name),
                    birthday   = VALUES(birthday),
                    updated_at = NOW()
            ")->execute([
                $userId,
                $fn ?: null,
                $ln ?: null,
                ($bd && preg_match('/^\d{4}-\d{2}-\d{2}$/', $bd)) ? $bd : null,
            ]);
            wb_ok(['saved' => true]);
            break;
        }

        case 'update_address': {
            $city = trim((string)($in['city']         ?? ''));
            $dist = trim((string)($in['district']     ?? ''));
            $nbhd = trim((string)($in['neighborhood'] ?? ''));
            if (!$city || !$dist) {
                wb_err('İl ve ilçe zorunlu', 422, 'validation_error');
            }
            $pdo->prepare("
                INSERT INTO customers (user_id, city, district, neighborhood)
                VALUES (?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    city         = VALUES(city),
                    district     = VALUES(district),
                    neighborhood = VALUES(neighborhood),
                    updated_at   = NOW()
            ")->execute([$userId, $city, $dist, $nbhd ?: null]);
            wb_ok(['saved' => true]);
            break;
        }

        case 'update_email': {
            $email = strtolower(trim((string)($in['email'] ?? '')));
            if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                wb_err('Geçersiz e-posta', 422, 'validation_error');
            }
            $chk = $pdo->prepare("SELECT id FROM customers WHERE email = ? AND user_id != ? LIMIT 1");
            $chk->execute([$email, $userId]);
            if ($chk->fetchColumn()) {
                wb_err('Bu e-posta başka bir hesapta kayıtlı', 409, 'email_taken');
            }
            $pdo->prepare("
                INSERT INTO customers (user_id, email) VALUES (?,?)
                ON DUPLICATE KEY UPDATE email=VALUES(email), updated_at=NOW()
            ")->execute([$userId, $email]);
            wb_ok(['email' => $email]);
            break;
        }

        case 'update_phone': {
            $phone = preg_replace('/\D+/', '', (string)($in['phone'] ?? ''));
            if (!$phone || !preg_match('/^5\d{9}$/', $phone)) {
                wb_err('Geçersiz telefon (5xxxxxxxxx)', 422, 'validation_error');
            }
            $pseudoEmail = $phone . '@phone.user';
            $chk = $pdo->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
            $chk->execute([$pseudoEmail, $userId]);
            if ($chk->fetchColumn()) {
                wb_err('Bu numara başka bir hesapta kayıtlı', 409, 'phone_taken');
            }
            $pdo->prepare("UPDATE users SET email = ? WHERE id = ?")->execute([$pseudoEmail, $userId]);
            $pdo->prepare("
                INSERT INTO customers (user_id, phone) VALUES (?,?)
                ON DUPLICATE KEY UPDATE phone=VALUES(phone), updated_at=NOW()
            ")->execute([$userId, $phone]);
            $_SESSION['user_phone'] = $phone;
            wb_ok(['phone' => $phone]);
            break;
        }

        case 'change_password': {
            $cur = (string)($in['currentPassword'] ?? '');
            $new = (string)($in['newPassword']     ?? '');
            if (!$cur || !$new) {
                wb_err('Mevcut ve yeni şifre zorunlu', 422, 'validation_error');
            }
            if (mb_strlen($new) < 8) {
                wb_err('Yeni şifre en az 8 karakter olmalı', 422, 'validation_error');
            }
            $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = ? LIMIT 1");
            $stmt->execute([$userId]);
            $row = $stmt->fetch();
            if (!$row || !password_verify($cur, $row['password_hash'])) {
                wb_err('Mevcut şifre hatalı', 403, 'wrong_password');
            }
            $pdo->prepare("UPDATE users SET password_hash = ? WHERE id = ?")
                ->execute([password_hash($new, PASSWORD_DEFAULT), $userId]);
            wb_ok(['changed' => true]);
            break;
        }

        default:
            wb_err('Geçersiz action', 400, 'invalid_action');
    }

} catch (Throwable $e) {
    error_log('[user/profile/update.php] ' . $e->getMessage());
    wb_err('İşlem başarısız. Lütfen tekrar deneyin.', 500, 'internal_error');
}