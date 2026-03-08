<?php
declare(strict_types=1);
/**
 * api/public/salons.php — Aktif işletmeleri listele
 * GET ?city=&district=&sort=rating|newest|price_asc&min_rating=4&page=1&limit=18&q=&open_now=1
 */

require_once __DIR__ . '/../_public_bootstrap.php';
header('Cache-Control: public, max-age=60');
wb_method('GET');

// ── Parametreler ──────────────────────────────────────────────
$page      = max(1, (int)($_GET['page']   ?? 1));
$limit     = min(max(1, (int)($_GET['limit'] ?? 18)), 100);
$offset    = ($page - 1) * $limit;
$city      = trim($_GET['city']       ?? '');
$district  = trim($_GET['district']   ?? '');
$q         = trim($_GET['q']          ?? '');
$sort      = $_GET['sort']            ?? 'newest';
$minRating = round(max(0.0, min(5.0, (float)($_GET['min_rating'] ?? 0))), 1);
$maxPrice  = isset($_GET['max_price']) ? (int)$_GET['max_price'] : null;
$minPrice  = isset($_GET['min_price']) ? (int)$_GET['min_price'] : null;
$openNow   = !empty($_GET['open_now']);

$sortMap = [
    'newest'     => 'b.updated_at DESC',
    'rating'     => 'avg_rating DESC, review_count DESC',
    'price_asc'  => 'b.min_price ASC',
    'price_desc' => 'b.min_price DESC',
    'name'       => 'b.name ASC',
];
$orderBy = $sortMap[$sort] ?? $sortMap['newest'];

$where  = ["b.status = 'active'", "b.onboarding_completed = 1"];
$params = [];

if ($city !== '') {
    $where[]  = 'b.city = ?';
    $params[] = $city;
}
if ($district !== '') {
    $where[]  = 'b.district = ?';
    $params[] = $district;
}
if ($q !== '') {
    $where[]  = "(b.name LIKE ? OR b.about LIKE ? OR b.district LIKE ?)";
    $like     = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
if ($maxPrice !== null) {
    $where[]  = '(b.min_price IS NULL OR b.min_price <= ?)';
    $params[] = $maxPrice;
}
if ($minPrice !== null) {
    $where[]  = '(b.min_price >= ?)';
    $params[] = $minPrice;
}
if ($openNow) {
    $dayOfWeek = (int)date('w');
    $nowTime   = date('H:i:s');
    $where[]   = "EXISTS (SELECT 1 FROM business_hours bh WHERE bh.business_id = b.id AND bh.day_of_week = $dayOfWeek AND bh.is_open = 1 AND bh.open_time <= '$nowTime' AND bh.close_time >= '$nowTime')";
}

$whereSQL = implode(' AND ', $where);

try {
    // Toplam
    $cStmt = $pdo->prepare("SELECT COUNT(DISTINCT b.id) FROM businesses b WHERE $whereSQL");
    $cStmt->execute($params);
    $total = (int)$cStmt->fetchColumn();

    // HAVING: use parameterized binding to prevent injection
    $havingClause = $minRating > 0 ? 'HAVING avg_rating >= ?' : '';
    $havingParams = $minRating > 0 ? [$minRating] : [];

    $sql = "
        SELECT b.id, b.name, b.slug, b.city, b.district, b.address_line,
               b.images_json, b.about, b.min_price, b.max_price,
               b.latitude, b.longitude, b.map_url,
               ROUND(COALESCE(AVG(rv.rating),0),1) AS avg_rating,
               COUNT(rv.id) AS review_count
        FROM businesses b
        LEFT JOIN reviews rv ON rv.business_id = b.id AND rv.is_visible = 1
        WHERE $whereSQL
        GROUP BY b.id
        $havingClause
        ORDER BY $orderBy
        LIMIT ? OFFSET ?
    ";

    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge($params, $havingParams, [$limit, $offset]));
    $rows = $stmt->fetchAll();

    $items = [];
    foreach ($rows as $r) {
        $coverUrl = null;
        $images   = [];
        $gallery  = [];
        if ($r['images_json']) {
            $img      = json_decode($r['images_json'], true) ?? [];
            $coverOpt = is_array($img['cover_opt'] ?? null) ? ($img['cover_opt'][0] ?? null) : null;
            $coverOrig = is_array($img['cover'] ?? null) ? ($img['cover'][0] ?? null) : ($img['cover'] ?? null);
            $coverUrl = $coverOpt ?? $coverOrig;
            if ($coverOrig) $images['cover'] = $coverOrig;
            // Galeri: cover + salon + model resimleri birleştir
            if ($coverUrl) $gallery[] = $coverUrl;
            $salonOpts = is_array($img['salon_opt'] ?? null) ? $img['salon_opt'] : (is_array($img['salon'] ?? null) ? $img['salon'] : []);
            $modelOpts = is_array($img['model_opt'] ?? null) ? $img['model_opt'] : (is_array($img['model'] ?? null) ? $img['model'] : []);
            foreach ($salonOpts as $u) { if ($u && $u !== $coverUrl) $gallery[] = $u; }
            foreach ($modelOpts as $u) { if ($u && !in_array($u, $gallery)) $gallery[] = $u; }
        }
        $items[] = [
            'id'           => (string)$r['id'],
            'name'         => $r['name'],
            'slug'         => $r['slug'],
            'coverUrl'     => $coverUrl,
            'images'       => $images,
            'gallery'      => $gallery,
            'avg_rating'   => (float)$r['avg_rating'],
            'review_count' => (int)$r['review_count'],
            'min_price'    => $r['min_price'] !== null ? (int)$r['min_price'] : null,
            'max_price'    => $r['max_price'] !== null ? (int)$r['max_price'] : null,
            'loc'          => [
                'city'      => $r['city'],
                'district'  => $r['district'],
                'address'   => $r['address_line'],
                'latitude'  => $r['latitude']  !== null ? (float)$r['latitude']  : null,
                'longitude' => $r['longitude'] !== null ? (float)$r['longitude'] : null,
            ],
            'map_url'      => $r['map_url'] ?? null,
            'about'        => $r['about'],
        ];
    }

    header('Content-Type: application/json; charset=utf-8');
    wb_ok(['data' => $items, 'items' => $items, 'meta' => wb_paginate($total, $page, $limit)]);

} catch (Throwable $e) {
    error_log('[salons.php] ' . $e->getMessage());
    wb_err('Veriler alınamadı', 500, 'internal_error');
}