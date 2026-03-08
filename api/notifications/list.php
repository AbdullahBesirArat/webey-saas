<?php
declare(strict_types=1);
/**
 * api/notifications/list.php
 * GET ?limit=20&offset=0 — Admin bildirim listesi
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('GET');

$bid    = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');
$limit  = min((int)($_GET['limit']  ?? 20), 100);
$offset = (int)($_GET['offset'] ?? 0);

try {
    $stmt = $pdo->prepare("
        SELECT id, type, customer_name, customer_phone, service_name, staff_name,
               appointment_start, result, is_read, created_at, appointment_id
        FROM notifications
        WHERE business_id = ?
        ORDER BY created_at DESC
        LIMIT ? OFFSET ?
    ");
    $stmt->execute([$bid, $limit, $offset]);

    $items = array_map(fn($r) => array_merge($r, [
        'id'         => (string)$r['id'],
        'is_read'    => (bool)$r['is_read'],
        'appointment_id' => $r['appointment_id'] ? (string)$r['appointment_id'] : null,
    ]), $stmt->fetchAll());

    $cStmt = $pdo->prepare('SELECT COUNT(*) FROM notifications WHERE business_id = ?');
    $cStmt->execute([$bid]);
    $total = (int)$cStmt->fetchColumn();

    wb_ok(['items' => $items, 'total' => $total]);

} catch (Throwable $e) {
    error_log('[notifications/list] ' . $e->getMessage());
    wb_err('Bildirimler yüklenemedi', 500, 'internal_error');
}