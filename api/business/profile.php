<?php
declare(strict_types=1);
/**
 * api/business/profile.php
 * GET ?id=123  veya  ?slug=salon-adi
 * PUBLIC — profile.js tarafından işletme profili sayfasını doldurmak için
 * Döner: name, about, phone, loc, images, services[], hours{}, staff[], owner
 */

require_once __DIR__ . '/../_public_bootstrap.php';
header('Cache-Control: public, max-age=30');
wb_method('GET');

$id   = (int)trim($_GET['id']   ?? '0');
$slug = trim($_GET['slug'] ?? '');

if (!$id && !$slug) {
    wb_err('id veya slug zorunlu', 400, 'missing_param');
}

try {
    // ================== URL NORMALIZE ==================
    // try bloğu içinde define edilen fonksiyon global scope'a kaydolur
    // ama PHP'de aynı isimli fonksiyon iki kez tanımlanamaz;
    // bu yüzden function_exists kontrolü yapıyoruz.
    if (!function_exists('normalizeImageUrl')) {
        function normalizeImageUrl($url, $bid) {
            if (!$url) return null;
            $url = trim($url);
            if (str_starts_with($url, 'uploads/')) return $url;
            if (preg_match('#/uploads/biz/(\d+)/(.+)$#', $url, $m)) return 'uploads/biz/' . $m[1] . '/' . $m[2];
            if (preg_match('#/uploads/(?:optimized|original)/(.+)$#', $url, $m)) return 'uploads/optimized/' . $m[1];
            if (!str_contains($url, '/')) return 'uploads/biz/' . $bid . '/' . $url;
            return $url;
        }
    }

    // ================== İŞLETME ==================
    if ($id) {
        $stmt = $pdo->prepare("
            SELECT b.*, u.email AS owner_email
            FROM businesses b
            LEFT JOIN users u ON u.id = b.owner_id
            WHERE b.id = ?
            LIMIT 1
        ");
        $stmt->execute([$id]);
    } else {
        $stmt = $pdo->prepare("
            SELECT b.*, u.email AS owner_email
            FROM businesses b
            LEFT JOIN users u ON u.id = b.owner_id
            WHERE b.slug = ?
            LIMIT 1
        ");
        $stmt->execute([$slug]);
    }

    $biz = $stmt->fetch();

    if (!$biz) {
        wb_err('İşletme bulunamadı', 404, 'not_found');
    }

    $bizId = (int)$biz['id'];

    // ── Abonelik / yayın durumu kontrolü ────────────────────────────────────
    require_once __DIR__ . '/../../api/_subscription_check.php';
    $ownerSub   = getSubscriptionStatus($pdo, (int)$biz['owner_id']);
    $isPublished = $ownerSub['active']; // false ise profil "yayında değil" gösterilecek
    // ──────────────────────────────────────────────────────────────────────────

    // ================== ÇALIŞMA SAATLERİ ==================
    $hStmt = $pdo->prepare("
        SELECT day, is_open, open_time, close_time
        FROM business_hours
        WHERE business_id = ?
        ORDER BY FIELD(day,'mon','tue','wed','thu','fri','sat','sun')
    ");
    $hStmt->execute([$bizId]);
    $hourRows = $hStmt->fetchAll();

    $hours = [];
    foreach ($hourRows as $h) {
        $isOpen = (bool)$h['is_open'];
        $openTime  = ($isOpen && $h['open_time'])  ? substr($h['open_time'],  0, 5) : null;
        $closeTime = ($isOpen && $h['close_time']) ? substr($h['close_time'], 0, 5) : null;
        $hours[$h['day']] = [
            'closed' => !$isOpen,
            'open'   => $openTime,   // zaman string'i: "09:00" veya null
            'close'  => $closeTime,
            'start'  => $openTime,
            'end'    => $closeTime,
            'from'   => $openTime,
            'to'     => $closeTime,
        ];
    }

    // ================== HİZMETLER ==================
    $sStmt = $pdo->prepare("
        SELECT id, name, duration_min, price
        FROM services
        WHERE business_id = ?
        ORDER BY id ASC
    ");
    $sStmt->execute([$bizId]);
    $serviceRows = $sStmt->fetchAll();

    $services = [];
    foreach ($serviceRows as $svc) {
        $services[] = [
            'id'          => (string)$svc['id'],
            'name'        => $svc['name'],
            'duration'    => (int)$svc['duration_min'],
            'duration_min'=> (int)$svc['duration_min'],
            'min'         => (int)$svc['duration_min'],
            'price'       => (float)$svc['price'],
        ];
    }

    // ================== PERSONEL ==================
    $stStmt = $pdo->prepare("
        SELECT id, name, position, color, phone,
               COALESCE(photo_url, '') AS photo_url,
               COALESCE(photo_opt, '') AS photo_opt
        FROM staff
        WHERE business_id = ?
        ORDER BY id ASC
    ");
    $stStmt->execute([$bizId]);
    $staffRows = $stStmt->fetchAll();

    $staff = [];
    foreach ($staffRows as $s) {
        $sid = (int)$s['id'];

        // Personel saatleri
        $shStmt = $pdo->prepare("
            SELECT day, is_open, open_time, close_time
            FROM staff_hours
            WHERE staff_id = ? AND business_id = ?
            ORDER BY FIELD(day,'mon','tue','wed','thu','fri','sat','sun')
        ");
        $shStmt->execute([$sid, $bizId]);
        $shRows = $shStmt->fetchAll();

        $staffHours = [];
        foreach ($shRows as $h) {
            $isOpen = (bool)$h['is_open'];
            $sOpenTime  = ($isOpen && $h['open_time'])  ? substr($h['open_time'],  0, 5) : null;
            $sCloseTime = ($isOpen && $h['close_time']) ? substr($h['close_time'], 0, 5) : null;
            $staffHours[$h['day']] = [
                'closed' => !$isOpen,
                'open'   => $sOpenTime,
                'close'  => $sCloseTime,
                'start'  => $sOpenTime,
                'end'    => $sCloseTime,
            ];
        }

        // Personel fotoğrafı (photo_opt varsa optimize, yoksa orijinal)
        $photoUrl = null;
        $photoOpt = null;
        try {
            if (!empty($s['photo_url'])) {
                $photoUrl = normalizeImageUrl($s['photo_url'], $bizId);
            }
            if (!empty($s['photo_opt'])) {
                $photoOpt = normalizeImageUrl($s['photo_opt'], $bizId);
            }
        } catch (Throwable $ignored) {}

        // Personele atanmış hizmet ID'leri (staff_services tablosu yoksa boş dizi)
        $staffServiceIds = [];
        try {
            $ssStmt = $pdo->prepare("
                SELECT service_id
                FROM staff_services
                WHERE staff_id = ?
                ORDER BY service_id ASC
            ");
            $ssStmt->execute([$sid]);
            $staffServiceIds = array_map(
                fn($row) => (string)$row['service_id'],
                $ssStmt->fetchAll()
            );
        } catch (Throwable $ignored) {
            // staff_services tablosu henüz yoksa serviceIds alanını null bırak
            // profile.js null görünce tüm hizmetleri gösterir (güvenli fallback)
            $staffServiceIds = null;
        }

        $staffEntry = [
            'id'            => (string)$sid,
            'name'          => $s['name'],
            'position'      => $s['position'] ?? 'Personel',
            'color'         => $s['color'] ?? null,
            'phone'         => $s['phone'] ?? null,
            'photoUrl'      => $photoUrl,
            'photoOpt'      => $photoOpt ?? $photoUrl,
            'hoursOverride' => $staffHours,
        ];
        // serviceIds sadece tablo varsa eklenir (null ise alanı hiç koyma)
        if ($staffServiceIds !== null) {
            $staffEntry['serviceIds'] = $staffServiceIds;
        }
        $staff[] = $staffEntry;
    }

    // ================== GÖRSELLER ==================

    $images = ['cover' => [], 'salon' => [], 'model' => [], 'cover_opt' => [], 'salon_opt' => [], 'model_opt' => []];
    $coverUrl = null;
    if (!empty($biz['images_json'])) {
        $rawImg = json_decode($biz['images_json'], true) ?? [];

        // Cover + cover_opt
        $rawCover = $rawImg['cover'] ?? null;
        if (is_array($rawCover)) {
            foreach ($rawCover as $u) { $n = normalizeImageUrl($u, $bizId); if ($n) $images['cover'][] = $n; }
        } elseif ($rawCover) {
            $n = normalizeImageUrl($rawCover, $bizId); if ($n) $images['cover'][] = $n;
        }
        foreach ($rawImg['cover_opt'] ?? [] as $u) { $n = normalizeImageUrl($u, $bizId); if ($n) $images['cover_opt'][] = $n; }

        // Salon & Model + opt versiyonları
        foreach (['salon','model'] as $k) {
            $arr = $rawImg[$k] ?? [];
            if (!is_array($arr)) continue;
            foreach ($arr as $u) { $n = normalizeImageUrl($u, $bizId); if ($n) $images[$k][] = $n; }
            foreach ($rawImg[$k . '_opt'] ?? [] as $u) { $n = normalizeImageUrl($u, $bizId); if ($n) $images[$k . '_opt'][] = $n; }
        }

        // coverUrl: optimize yoksa orijinal
        $coverUrl = $images['cover_opt'][0] ?? $images['cover'][0] ?? null;
    }
    if (!$coverUrl && !empty($biz['logo_url'])) {
        $coverUrl = normalizeImageUrl($biz['logo_url'], $bizId);
    }

    // ================== RESPONSE ==================
    // profile.js root level alanları doğrudan bekliyor (res.name, res.services vb.)
    // Bu yüzden hem data hem root level veriyoruz
    $payload = [
        'is_published' => $isPublished,
        'subscription' => ['active' => $ownerSub['active'], 'trialing' => $ownerSub['trialing'], 'plan' => $ownerSub['plan'], 'days_left' => $ownerSub['days_left']],
        'id'           => (string)$bizId,
        'businessId'   => (string)$bizId,
        'uid'          => (string)$bizId,
        'slug'         => $biz['slug'] ?? null,
        'name'         => $biz['name'] ?? '',
        'about'        => $biz['about'] ?? '',
        'phone'        => $biz['phone'] ?? '',
        'phoneE164'    => $biz['phone'] ?? '',
        'category'     => $biz['category'] ?? null,
        'status'       => $biz['status'] ?? null,
        'coverUrl'     => $coverUrl,
        'logoUrl'      => $biz['logo_url'] ?? $coverUrl,
        'images'       => $images,
        'services'     => $services,
        'hours'        => $hours,
        'staff'        => $staff,
        'loc'          => [
            'city'         => $biz['city']         ?? '',
            'district'     => $biz['district']     ?? '',
            'neighborhood' => $biz['neighborhood'] ?? '',
            'addressLine'  => $biz['address_line'] ?? '',
            'province'     => $biz['city']         ?? '',
            'mapUrl'       => $biz['map_url']      ?? null,
        ],
        'location'     => [
            'city'         => $biz['city']         ?? '',
            'district'     => $biz['district']     ?? '',
            'neighborhood' => $biz['neighborhood'] ?? '',
            'addressLine'  => $biz['address_line'] ?? '',
        ],
        'owner'        => $biz['owner_name'] ? [
            'name'  => $biz['owner_name'],
            'email' => $biz['owner_email'] ?? null,
        ] : null,
    ];
    // Backward compat: profile.js bazı yerlerde res.data.* bazı yerlerde res.* kullanıyor
    wb_ok($payload);

} catch (Throwable $e) {
    error_log('[business/profile.php] ' . $e->getMessage());
    wb_err('İşletme bilgileri alınamadı', 500, 'internal_error');
}