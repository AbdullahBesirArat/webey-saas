<?php
declare(strict_types=1);
/**
 * api/reviews/list.php
 * GET ?business_id=123&page=1&limit=10
 * PUBLIC — giriş gerekmez
 */

require_once __DIR__ . '/../_public_bootstrap.php';
header('Cache-Control: public, max-age=60'); // public cache override
wb_method('GET');

$bizId  = (int)($_GET['business_id'] ?? 0);
if ($bizId <= 0) wb_err('Geçersiz business_id', 400, 'invalid_business_id');

$page   = max(1, (int)($_GET['page']  ?? 1));
$limit  = min(20, max(5, (int)($_GET['limit'] ?? 10)));
$offset = ($page - 1) * $limit;

$currentUserId = !empty($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null;

try {
    $statStmt = $pdo->prepare("SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total, SUM(rating=5) AS r5, SUM(rating=4) AS r4, SUM(rating=3) AS r3, SUM(rating=2) AS r2, SUM(rating=1) AS r1 FROM reviews WHERE business_id = ? AND is_visible = 1");
    $statStmt->execute([$bizId]);
    $stats = $statStmt->fetch();

    $revStmt = $pdo->prepare("
        SELECT r.id, r.rating, r.comment, r.reply, r.reply_at, r.created_at, r.user_id, r.appointment_id,
               COALESCE(CONCAT(c.first_name,' ',LEFT(c.last_name,1),'.'), 'Müşteri') AS reviewer_name
        FROM reviews r
        LEFT JOIN customers c ON c.user_id = r.user_id
        WHERE r.business_id = ? AND r.is_visible = 1
        ORDER BY r.created_at DESC
        LIMIT ? OFFSET ?
    ");
    $revStmt->execute([$bizId, $limit, $offset]);
    $rows = $revStmt->fetchAll();

    $reviews = array_map(fn($r) => [
        'id'             => (int)$r['id'],
        'rating'         => (int)$r['rating'],
        'comment'        => $r['comment'],
        'reply'          => $r['reply'],
        'reply_at'       => $r['reply_at'],
        'created_at'     => $r['created_at'],
        'reviewer_name'  => $r['reviewer_name'],
        'is_mine'        => $currentUserId && (int)$r['user_id'] === $currentUserId,
        'appointment_id' => (int)$r['appointment_id'],
    ], $rows);

    wb_ok([
        'stats' => [
            'avg_rating' => (float)($stats['avg_rating'] ?? 0),
            'total'      => (int)($stats['total'] ?? 0),
            'breakdown'  => [5 => (int)($stats['r5']??0), 4 => (int)($stats['r4']??0), 3 => (int)($stats['r3']??0), 2 => (int)($stats['r2']??0), 1 => (int)($stats['r1']??0)],
        ],
        'reviews'  => $reviews,
        'page'     => $page,
        'has_more' => count($rows) === $limit,
    ]);

} catch (Throwable $e) {
    error_log('[reviews/list] ' . $e->getMessage());
    wb_err('Yorumlar yüklenemedi', 500, 'internal_error');
}