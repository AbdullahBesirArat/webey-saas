<?php
declare(strict_types=1);
/**
 * api/public/businesses.php
 * GET ?status=active&city=...&district=...&q=...&limit=500
 * kuafor.js için — images, services, businessLocation, workingHours döner
 */

require_once __DIR__ . '/../_public_bootstrap.php';
header('Cache-Control: public, max-age=30');
wb_method('GET');

$limit    = min((int)($_GET['limit'] ?? 100), 800);
$status   = $_GET['status'] ?? '';
$city     = trim($_GET['city'] ?? '');
$district = trim($_GET['district'] ?? '');
$q        = trim($_GET['q'] ?? '');

$where  = ['b.onboarding_completed = 1'];
$params = [];
if ($status)   { $where[] = 'b.status = ?';   $params[] = $status; }
if ($city)     { $where[] = 'b.city = ?';     $params[] = $city; }
if ($district) { $where[] = 'b.district = ?'; $params[] = $district; }
if ($q)        { $where[] = '(b.name LIKE ? OR b.type LIKE ?)'; $params[] = "%$q%"; $params[] = "%$q%"; }

$params[] = $limit;

try {
    $stmt = $pdo->prepare('
        SELECT b.id, b.name, b.type, b.status,
               b.city, b.district, b.neighborhood, b.address_line,
               b.phone, b.images_json,
               ROUND(COALESCE(AVG(rv.rating), 0), 1) AS avg_rating,
               COUNT(rv.id) AS review_count
        FROM businesses b
        LEFT JOIN reviews rv ON rv.business_id = b.id AND rv.is_visible = 1
        WHERE ' . implode(' AND ', $where) . '
        GROUP BY b.id
        ORDER BY b.id DESC LIMIT ?
    ');
    $stmt->execute($params);
    $rows = $stmt->fetchAll();
} catch (Throwable $e) {
    error_log('[businesses.php] ' . $e->getMessage());
    wb_err('Veriler alınamadı', 500, 'internal_error');
}

if (!$rows) {
    wb_ok([]);
}

$ids = array_column($rows, 'id');
$ph  = implode(',', array_fill(0, count($ids), '?'));

// ── Hizmetler (min fiyat + isim) ─────────────────────────────────────────────
$servicesByBiz = [];
try {
    $s = $pdo->prepare("SELECT business_id, name, price FROM services WHERE business_id IN ($ph) AND price > 0 ORDER BY price ASC");
    $s->execute($ids);
    foreach ($s->fetchAll() as $r) {
        $bid = (string)$r['business_id'];
        if (!isset($servicesByBiz[$bid])) $servicesByBiz[$bid] = [];
        $servicesByBiz[$bid][] = ['name' => $r['name'], 'price' => (float)$r['price']];
    }
} catch (Throwable $ignored) {}

// ── Çalışma saatleri ─────────────────────────────────────────────────────────
// kuafor.js getDay() indeksi (0=Pazar…6=Cumartesi) kullanır
$dayToIdx = ['sun'=>0,'mon'=>1,'tue'=>2,'wed'=>3,'thu'=>4,'fri'=>5,'sat'=>6];
$hoursByBiz = [];
try {
    $h = $pdo->prepare("SELECT business_id, day, is_open, open_time, close_time FROM business_hours WHERE business_id IN ($ph)");
    $h->execute($ids);
    foreach ($h->fetchAll() as $r) {
        $bid = (string)$r['business_id'];
        $idx = $dayToIdx[$r['day']] ?? null;
        if ($idx === null) continue;
        if (!(bool)$r['is_open'] || !$r['open_time']) {
            $hoursByBiz[$bid][$idx] = ['open' => false, 'slots' => []];
        } else {
            $from = substr($r['open_time'],  0, 5);
            $to   = substr($r['close_time'] ?? '00:00', 0, 5);
            $hoursByBiz[$bid][$idx] = ['open' => true, 'slots' => [['from' => $from, 'to' => $to]]];
        }
    }
} catch (Throwable $ignored) {}

// ── Kapak görseli ─────────────────────────────────────────────────────────────
function bizCoverUrl(?string $json, int $bid): ?string {
    if (!$json) return null;
    $img = json_decode($json, true);
    if (!is_array($img)) return null;
    foreach (['cover', 'salon', 'model'] as $k) {
        // Önce optimize versiyonu dene (cover_opt, salon_opt, model_opt)
        $optKey = $k . '_opt';
        $vOpt = $img[$optKey] ?? null;
        if (is_array($vOpt) && count($vOpt) && is_string($vOpt[0]) && $vOpt[0] !== '') {
            return $vOpt[0];
        }
        // Yoksa orijinale düş
        $v = $img[$k] ?? null;
        if (is_string($v) && $v !== '') return $v;
        if (is_array($v) && count($v)) {
            $f = $v[0];
            $u = is_string($f) ? $f : ($f['url'] ?? $f['src'] ?? null);
            if ($u) return $u;
        }
    }
    return null;
}

function bizImagesObj(?string $json, int $bid): array {
    $images = ['cover' => [], 'salon' => [], 'model' => []];
    if (!$json) return $images;
    $raw = json_decode($json, true);
    if (!is_array($raw)) return $images;
    foreach (['cover', 'salon', 'model'] as $k) {
        $arr = $raw[$k] ?? [];
        if (is_string($arr) && $arr !== '') { $images[$k][] = $arr; continue; }
        if (!is_array($arr)) continue;
        foreach ($arr as $u) { if ($u) $images[$k][] = $u; }
    }
    return $images;
}

// ── Response ─────────────────────────────────────────────────────────────────
$out = [];
foreach ($rows as $r) {
    $bid      = (string)$r['id'];
    $bidInt   = (int)$r['id'];
    $images   = bizImagesObj($r['images_json'], $bidInt);
    $coverUrl = bizCoverUrl($r['images_json'], $bidInt);
    $svcs     = $servicesByBiz[$bid] ?? [];
    $hours    = $hoursByBiz[$bid]   ?? (object)[];

    $out[] = [
        'id'               => $bid,
        'uid'              => $bid,         // kuafor.js row.uid
        'businessId'       => $bid,
        'name'             => (string)($r['name'] ?? ''),
        'category'         => (string)($r['type'] ?? ''),
        'status'           => (string)($r['status'] ?? ''),
        'phone'            => (string)($r['phone'] ?? ''),
        // Görsel alanları — kuafor.js row.images.cover || row.logoUrl okur
        'coverUrl'         => $coverUrl,
        'logoUrl'          => $coverUrl,
        'images'           => $images,      // kuafor.js: row.images.cover[0]
        // Fiyat — kuafor.js minPrice(row.services) çağırır, dizi bekler
        'services'         => $svcs,        // [{name, price}]
        'minPrice'         => $svcs ? min(array_column($svcs, 'price')) : null,
        // Konum — kuafor.js row.businessLocation.district vs okur
        'city'             => (string)($r['city'] ?? ''),
        'district'         => (string)($r['district'] ?? ''),
        'neighborhood'     => (string)($r['neighborhood'] ?? ''),
        'address'          => (string)($r['address_line'] ?? ''),
        'businessLocation' => [              // kuafor.js: row.businessLocation
            'city'         => $r['city']         ?? '',
            'district'     => $r['district']     ?? '',
            'neighborhood' => $r['neighborhood'] ?? '',
            'province'     => $r['city']         ?? '',
            'addressLine'  => $r['address_line'] ?? '',
        ],
        // Saatler — kuafor.js: isOpenNow(row.workingHours)
        'workingHours'     => $hours ?: (object)[],
        'hours'            => $hours ?: (object)[],
        // Puan & yorum sayısı — kuafor.js: row.avg_rating, row.review_count
        'avg_rating'       => (float)($r['avg_rating']   ?? 0),
        'review_count'     => (int)($r['review_count']   ?? 0),
    ];
}

wb_ok($out);