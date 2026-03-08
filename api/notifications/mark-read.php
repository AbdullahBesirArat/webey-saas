<?php
declare(strict_types=1);
/**
 * api/notifications/mark-read.php
 * POST { id } — Bildirimi okundu işaretle
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$bid = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');

$in = wb_body();
$id = isset($in['id']) ? (int)$in['id'] : 0;
if (!$id) wb_err('id zorunlu', 400, 'missing_id');

try {
    $pdo->prepare('UPDATE notifications SET is_read = 1 WHERE id = ? AND business_id = ?')
        ->execute([$id, $bid]);
    wb_ok(['marked' => true, 'id' => (string)$id]);
} catch (Throwable $e) {
    error_log('[notifications/mark-read] ' . $e->getMessage());
    wb_err('İşlem tamamlanamadı', 500, 'internal_error');
}