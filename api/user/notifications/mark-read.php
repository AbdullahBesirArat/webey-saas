<?php
declare(strict_types=1);
/**
 * api/user/notifications/mark-read.php
 * POST { ids: [1,2,3] }  veya  POST { all: true }
 */

require_once __DIR__ . '/../../_bootstrap.php';
wb_method('POST');

$userId = $user['user_id'];
$body   = wb_body();

try {
    if (!empty($body['all'])) {
        $pdo->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND is_read = 0")
            ->execute([$userId]);
    } elseif (!empty($body['ids']) && is_array($body['ids'])) {
        $ids = array_map('intval', $body['ids']);
        $ph  = implode(',', array_fill(0, count($ids), '?'));
        $pdo->prepare("UPDATE user_notifications SET is_read = 1, read_at = NOW() WHERE user_id = ? AND id IN ($ph) AND is_read = 0")
            ->execute(array_merge([$userId], $ids));
    } else {
        wb_err('ids veya all gerekli', 400, 'missing_param');
    }

    wb_ok(['updated' => true]);

} catch (Throwable $e) {
    error_log('[user/notifications/mark-read] ' . $e->getMessage());
    wb_err('İşlem başarısız', 500, 'internal_error');
}