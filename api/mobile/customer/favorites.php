<?php
declare(strict_types=1);
/**
 * api/mobile/customer/favorites.php
 * GET — Token sahibi müşterinin favori salonlarını listeler.
 *
 * Tablo: customer_favorites (canlı şema)
 *   id bigint, customer_user_id int unsigned, business_id int, created_at datetime
 *   UNIQUE KEY uq_customer_business (customer_user_id, business_id)
 *
 * Yanıt: SalonSummary.fromJson() uyumlu items dizisi.
 *
 * Faz 8A — Bearer token zorunlu, customer tipi.
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../_auth.php';

wb_method('GET');

$session = mobile_auth($pdo, 'customer');
$userId  = $session['user_id'];

// ── customer_favorites tablosunun var olup olmadığını kontrol et ──────────────
// Tablo yoksa boş liste döndür; hata vermez.
try {
    $pdo->query("SELECT 1 FROM customer_favorites LIMIT 1");
} catch (Throwable) {
    wb_ok(['items' => []]);
}


try {
    // ── Favori salonları çek, sadece aktif işletmeler ─────────────────────────
    $stmt = $pdo->prepare("
        SELECT b.id, b.slug, b.name, b.about, b.city, b.district,
               b.address_line, b.images_json, b.min_price, b.max_price, b.type
        FROM customer_favorites cf
        INNER JOIN businesses b
            ON b.id = cf.business_id
           AND b.status = 'active'
        WHERE cf.customer_user_id = ?
        ORDER BY cf.created_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll();

    // ── Açık mı? business_hours'dan toplu kontrol ─────────────────────────────
    $ids = array_map(static fn(array $row): int => (int)$row['id'], $rows);
    $openNowByBusiness = [];
    if ($ids !== []) {
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $today   = mobile_day_key();
        $nowTime = date('H:i:s');
        $hoursStmt = $pdo->prepare("
            SELECT business_id
            FROM business_hours
            WHERE business_id IN ($placeholders)
              AND day = ?
              AND is_open = 1
              AND open_time <= ?
              AND close_time >= ?
        ");
        $hoursStmt->execute(array_merge($ids, [$today, $nowTime, $nowTime]));
        foreach ($hoursStmt->fetchAll() as $row) {
            $openNowByBusiness[(int)$row['business_id']] = true;
        }
    }

    // ── Satırları SalonSummary.fromJson() uyumlu formata dönüştür ─────────────
    $items = [];
    foreach ($rows as $row) {
        $images   = mobile_images($row['images_json'] ?? null);
        $minPrice = $row['min_price'] !== null ? (int)$row['min_price'] : null;
        $maxPrice = $row['max_price'] !== null ? (int)$row['max_price'] : null;
        $bizId    = (int)$row['id'];
        $isOpen   = !empty($openNowByBusiness[$bizId]);
        $badges   = $isOpen ? ['Açık'] : [];

        $items[] = [
            'id'                  => (string)$row['id'],
            'slug'                => (string)($row['slug'] ?? ''),
            'name'                => (string)($row['name'] ?? ''),
            'description'         => $row['about']        ?? null,
            'city'                => $row['city']          ?? null,
            'district'            => $row['district']      ?? null,
            'address'             => $row['address_line']  ?? null,
            'cover_image_url'     => $images['cover_image_url'],
            'logo_url'            => $images['logo_url'],
            'rating'              => null,
            'review_count'        => 0,
            'price_level'         => $minPrice !== null
                ? ['min' => $minPrice, 'max' => $maxPrice]
                : null,
            'deposit_required'    => false,
            'deposit_amount'      => null,
            'is_open_now'         => $isOpen,
            'next_available_text' => null,
            'badges'              => $badges,
            'category_slugs'      => mobile_category_slugs_from_type($row['type'] ?? null),
        ];
    }

    wb_ok(['items' => $items]);

} catch (Throwable $e) {
    error_log('[mobile/customer/favorites.php] ' . $e->getMessage());
    wb_err('Favoriler alınamadı', 500, 'internal_error');
}
