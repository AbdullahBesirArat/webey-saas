<?php
declare(strict_types=1);
/**
 * api/billing/cancel.php — Aboneliği dönem sonunda iptal et
 * POST — admin auth gerekli
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$userId = $user['user_id'];

try {
    $stmt = $pdo->prepare("
        UPDATE subscriptions
        SET cancel_at_period_end=1, updated_at=NOW()
        WHERE user_id=? AND status='active'
    ");
    $stmt->execute([$userId]);
    wb_ok(['message' => 'Abonelik dönem sonunda iptal edilecek']);
} catch (Throwable $e) {
    error_log('[billing/cancel.php] ' . $e->getMessage());
    wb_err('İptal işlemi gerçekleştirilemedi', 500, 'internal_error');
}