<?php
declare(strict_types=1);
/**
 * api/user/check-phone.php — Telefon müsaitliği kontrolü
 * POST JSON: { phone }
 * Döner: { ok, data: { available: bool } }
 */

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('POST');

$in    = wb_body();
$phone = preg_replace('/\D+/', '', (string)($in['phone'] ?? ''));
if (str_starts_with($phone, '90') && strlen($phone) === 12) $phone = substr($phone, 2);
if (str_starts_with($phone, '0')) $phone = substr($phone, 1);

wb_validate(['phone' => $phone], [
    'phone' => ['required', 'regex:/^5\d{9}$/'],
]);

$email = $phone . '@phone.user';
try {
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
    $stmt->execute([$email]);
    wb_ok(['available' => !(bool)$stmt->fetchColumn()]);
} catch (Throwable $e) {
    error_log('[user/check-phone.php] ' . $e->getMessage());
    wb_err('Sunucu hatası', 500, 'internal_error');
}