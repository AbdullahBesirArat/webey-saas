<?php
declare(strict_types=1);
/**
 * api/user/notifications/list.php
 * GET ?limit=50&offset=0&unread_only=0 — müşteri bildirimleri
 */

require_once __DIR__ . '/../../_bootstrap.php';
wb_method('GET');

$userId     = $user['user_id'];
$limit      = min((int)($_GET['limit']  ?? 50), 200);
$offset     = max((int)($_GET['offset'] ?? 0), 0);
$unreadOnly = !empty($_GET['unread_only']) && $_GET['unread_only'] !== '0';

try {
    $cntStmt = $pdo->prepare("SELECT COUNT(*) AS unread FROM user_notifications WHERE user_id = ? AND is_read = 0");
    $cntStmt->execute([$userId]);
    $unreadCount = (int)$cntStmt->fetchColumn();

    $where  = 'WHERE user_id = ?';
    $params = [$userId];
    if ($unreadOnly) { $where .= ' AND is_read = 0'; }

    $stmt = $pdo->prepare("
        SELECT id, appointment_id, type, title, message, business_name, is_read, read_at, created_at
        FROM user_notifications {$where}
        ORDER BY created_at DESC LIMIT ? OFFSET ?
    ");
    $params[] = $limit;
    $params[] = $offset;
    $stmt->execute($params);

    $notifications = array_map(fn($r) => [
        'id'           => (int)$r['id'],
        'appointmentId'=> $r['appointment_id'] ? (string)$r['appointment_id'] : null,
        'type'         => $r['type'],
        'title'        => $r['title'],
        'message'      => $r['message'],
        'businessName' => $r['business_name'],
        'isRead'       => (bool)$r['is_read'],
        'readAt'       => $r['read_at'],
        'createdAt'    => $r['created_at'],
    ], $stmt->fetchAll());

    wb_ok(['notifications' => $notifications, 'unreadCount' => $unreadCount]);

} catch (Throwable $e) {
    error_log('[user/notifications/list] ' . $e->getMessage());
    wb_err('Bildirimler alınamadı', 500, 'internal_error');
}