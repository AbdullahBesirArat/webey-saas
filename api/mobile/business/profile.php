<?php
declare(strict_types=1);
/**
 * api/mobile/business/profile.php
 * GET — Token sahibi işletmenin profil bilgilerini döner.
 *
 * Yanıt:
 *   business : object  — profil alanları
 *
 * Faz 8B — Bearer token zorunlu, business/admin tipi.
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/_helpers.php';

wb_method('GET');

$auth       = mobile_auth($pdo, ['business', 'admin']);
$ctx        = mobile_business_context($pdo, $auth);
$businessId = (int)$ctx['business_id'];

try {
    $stmt = $pdo->prepare("
        SELECT id, name, slug, owner_name, phone, type, status,
               city, district, address_line, about,
               map_url, latitude, longitude, building_no, neighborhood,
               images_json, onboarding_step, onboarding_completed
        FROM businesses
        WHERE id = ?
        LIMIT 1
    ");
    $stmt->execute([$businessId]);
    $row = $stmt->fetch();

    if (!$row) {
        wb_err('İşletme bulunamadı.', 404, 'business_not_found');
    }

    $images = mobile_images($row['images_json'] ?? null);

    wb_ok(['business' => [
        'id'                   => (string)$row['id'],
        'name'                 => (string)($row['name'] ?? ''),
        'slug'                 => $row['slug']         ?? null,
        'owner_name'           => $row['owner_name']   ?? null,
        'phone'                => $row['phone']         ?? null,
        'type'                 => $row['type']          ?? null,
        'status'               => $row['status']        ?? null,
        'city'                 => $row['city']          ?? null,
        'district'             => $row['district']      ?? null,
        'address_line'         => $row['address_line']  ?? null,
        'about'                => $row['about']         ?? null,
        'map_url'              => $row['map_url']       ?? null,
        'latitude'             => $row['latitude']  !== null ? (float)$row['latitude']  : null,
        'longitude'            => $row['longitude'] !== null ? (float)$row['longitude'] : null,
        'building_no'          => $row['building_no']   ?? null,
        'neighborhood'         => $row['neighborhood']  ?? null,
        'cover_image_url'      => $images['cover_image_url'],
        'logo_url'             => $images['logo_url'],
        'onboarding_step'      => (int)($row['onboarding_step']      ?? 1),
        'onboarding_completed' => (bool)($row['onboarding_completed'] ?? false),
    ]]);

} catch (Throwable $e) {
    error_log('[mobile/business/profile.php] ' . $e->getMessage());
    wb_err('Profil bilgisi alınamadı.', 500, 'internal_error');
}
