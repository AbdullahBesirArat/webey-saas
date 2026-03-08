<?php
declare(strict_types=1);
/**
 * api/staff/reviews.php
 * GET ?staff_id=X&business_id=Y&page=1&limit=6
 * PUBLIC — auth gerektirmez
 */

require_once __DIR__ . '/../_public_bootstrap.php';

// Bu endpoint için public cache uygundur (60 sn)
header('Cache-Control: public, max-age=60');

wb_method('GET');

$staffId = (int)($_GET['staff_id']    ?? 0);
$bizId   = (int)($_GET['business_id'] ?? 0);
$page    = max(1, (int)($_GET['page']  ?? 1));
$limit   = min(20, max(5, (int)($_GET['limit'] ?? 6)));
$offset  = ($page - 1) * $limit;

if ($staffId <= 0 || $bizId <= 0) wb_err('staff_id ve business_id zorunlu', 400, 'missing_params');

try {
    $staffStmt = $pdo->prepare('
        SELECT s.id, s.name, s.position, s.photo_url, s.photo_opt, s.color,
               COUNT(DISTINCT ss.service_id) AS service_count
        FROM staff s
        LEFT JOIN staff_services ss ON ss.staff_id = s.id
        WHERE s.id = ? AND s.business_id = ? AND s.is_active = 1
        GROUP BY s.id LIMIT 1
    ');
    $staffStmt->execute([$staffId, $bizId]);
    $staff = $staffStmt->fetch();
    if (!$staff) wb_err('Personel bulunamadı', 404, 'not_found');

    $svcStmt = $pdo->prepare('
        SELECT sv.id, sv.name, sv.price, sv.duration_min
        FROM staff_services ss
        JOIN services sv ON sv.id = ss.service_id
        WHERE ss.staff_id = ? ORDER BY sv.name
    ');
    $svcStmt->execute([$staffId]);
    $services = $svcStmt->fetchAll();

    $apptStmt = $pdo->prepare('
        SELECT COUNT(*) FROM appointments
        WHERE staff_id = ? AND business_id = ?
          AND (status IN ("completed","approved") OR attended = 1)
          AND end_at <= NOW()
    ');
    $apptStmt->execute([$staffId, $bizId]);
    $totalAppointments = (int)$apptStmt->fetchColumn();

    // Yorum istatistikleri — staff_id kolonu varsa direkt, yoksa appointment join
    $stats = null;
    try {
        $statStmt = $pdo->prepare('
            SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total,
                   SUM(rating=5) AS r5, SUM(rating=4) AS r4, SUM(rating=3) AS r3,
                   SUM(rating=2) AS r2, SUM(rating=1) AS r1
            FROM reviews WHERE staff_id = ? AND business_id = ? AND is_visible = 1
        ');
        $statStmt->execute([$staffId, $bizId]);
        $stats = $statStmt->fetch();
    } catch (Throwable) {}

    if (!$stats || (int)($stats['total'] ?? 0) === 0) {
        $statStmt = $pdo->prepare('
            SELECT ROUND(AVG(r.rating),1) AS avg_rating, COUNT(*) AS total,
                   SUM(r.rating=5) AS r5, SUM(r.rating=4) AS r4, SUM(r.rating=3) AS r3,
                   SUM(r.rating=2) AS r2, SUM(r.rating=1) AS r1
            FROM reviews r JOIN appointments a ON a.id = r.appointment_id
            WHERE a.staff_id = ? AND r.business_id = ? AND r.is_visible = 1
        ');
        $statStmt->execute([$staffId, $bizId]);
        $stats = $statStmt->fetch();
    }

    // Yorumlar
    $reviews = [];
    try {
        $revStmt = $pdo->prepare('
            SELECT r.id, r.rating, r.comment, r.created_at,
                   COALESCE(CONCAT(c.first_name," ",LEFT(c.last_name,1),"."), "Müşteri") AS reviewer_name,
                   sv.name AS service_name
            FROM reviews r
            LEFT JOIN customers c ON c.user_id = r.user_id
            LEFT JOIN appointments a ON a.id = r.appointment_id
            LEFT JOIN services sv ON sv.id = a.service_id
            WHERE r.staff_id = ? AND r.business_id = ? AND r.is_visible = 1
            ORDER BY r.created_at DESC LIMIT ? OFFSET ?
        ');
        $revStmt->execute([$staffId, $bizId, $limit, $offset]);
        $reviews = $revStmt->fetchAll();
    } catch (Throwable) {}

    if (empty($reviews)) {
        $revStmt = $pdo->prepare('
            SELECT r.id, r.rating, r.comment, r.created_at,
                   COALESCE(CONCAT(c.first_name," ",LEFT(c.last_name,1),"."), "Müşteri") AS reviewer_name,
                   sv.name AS service_name
            FROM reviews r JOIN appointments a ON a.id = r.appointment_id
            LEFT JOIN customers c ON c.user_id = r.user_id
            LEFT JOIN services sv ON sv.id = a.service_id
            WHERE a.staff_id = ? AND r.business_id = ? AND r.is_visible = 1
            ORDER BY r.created_at DESC LIMIT ? OFFSET ?
        ');
        $revStmt->execute([$staffId, $bizId, $limit, $offset]);
        $reviews = $revStmt->fetchAll();
    }

    wb_ok([
        'staff' => [
            'id'        => (int)$staff['id'],
            'name'      => $staff['name'],
            'position'  => $staff['position'],
            'photo'     => $staff['photo_opt'] ?? $staff['photo_url'] ?? null,
            'photo_orig'=> $staff['photo_url'] ?? $staff['photo_opt'] ?? null,
            'color'     => $staff['color'] ?? '#6b7280',
        ],
        'services' => array_map(fn($s) => [
            'id'          => (int)$s['id'],
            'name'        => $s['name'],
            'price'       => (float)($s['price'] ?? 0),
            'duration_min'=> (int)$s['duration_min'],
        ], $services),
        'total_appointments' => $totalAppointments,
        'stats' => [
            'avg_rating' => (float)($stats['avg_rating'] ?? 0),
            'total'      => (int)($stats['total']        ?? 0),
            'breakdown'  => [
                5 => (int)($stats['r5'] ?? 0), 4 => (int)($stats['r4'] ?? 0),
                3 => (int)($stats['r3'] ?? 0), 2 => (int)($stats['r2'] ?? 0),
                1 => (int)($stats['r1'] ?? 0),
            ],
        ],
        'reviews'  => array_map(fn($r) => [
            'id'            => (int)$r['id'],
            'rating'        => (int)$r['rating'],
            'comment'       => $r['comment'],
            'created_at'    => $r['created_at'],
            'reviewer_name' => $r['reviewer_name'],
            'service_name'  => $r['service_name'] ?? null,
        ], $reviews),
        'page'     => $page,
        'has_more' => count($reviews) === $limit,
    ]);

} catch (Throwable $e) {
    error_log('[staff/reviews] ' . $e->getMessage());
    wb_err('Veriler alınamadı', 500, 'internal_error');
}