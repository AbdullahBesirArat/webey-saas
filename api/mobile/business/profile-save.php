<?php
declare(strict_types=1);
/**
 * api/mobile/business/profile-save.php
 * POST — Token sahibi işletmenin profil bilgilerini günceller.
 *
 * Body (JSON):
 *   name         : string  (zorunlu, maks 100)
 *   owner_name   : string  (opsiyonel, maks 100)
 *   phone        : string  (opsiyonel, maks 20)
 *   city         : string  (opsiyonel, maks 80)
 *   district     : string  (opsiyonel, maks 80)
 *   address_line : string  (opsiyonel, maks 300)
 *   about        : string  (opsiyonel, maks 5000)
 *   map_url      : string  (opsiyonel, maks 500)
 *   latitude     : float   (opsiyonel, -90..90)
 *   longitude    : float   (opsiyonel, -180..180)
 *   building_no  : string  (opsiyonel, maks 20)
 *   neighborhood : string  (opsiyonel, maks 100)
 *
 * Yanıt:
 *   business : object
 *
 * Faz 8B — Bearer token zorunlu, business/admin tipi.
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../_auth.php';
require_once __DIR__ . '/_helpers.php';

wb_method('POST');

$auth       = mobile_auth($pdo, ['business', 'admin']);
$ctx        = mobile_business_context($pdo, $auth);
$businessId = (int)$ctx['business_id'];

$in = wb_body();

// ── Input sanitize ────────────────────────────────────────────────────────────
$name         = mb_substr(trim((string)($in['name']         ?? '')), 0, 100);
$ownerName    = mb_substr(trim((string)($in['owner_name']   ?? '')), 0, 100);
$phone        = mb_substr(trim((string)($in['phone']        ?? '')), 0, 20);
$city         = mb_substr(trim((string)($in['city']         ?? '')), 0, 80);
$district     = mb_substr(trim((string)($in['district']     ?? '')), 0, 80);
$addressLine  = mb_substr(trim((string)($in['address_line'] ?? '')), 0, 300);
$about        = mb_substr(trim((string)($in['about']        ?? '')), 0, 5000);
$mapUrl       = mb_substr(trim((string)($in['map_url']      ?? '')), 0, 500);
$buildingNo   = mb_substr(trim((string)($in['building_no']  ?? '')), 0, 20);
$neighborhood = mb_substr(trim((string)($in['neighborhood'] ?? '')), 0, 100);

$latRaw  = $in['latitude']  ?? null;
$lngRaw  = $in['longitude'] ?? null;
$latitude  = ($latRaw  !== null && $latRaw  !== '') ? (float)$latRaw  : null;
$longitude = ($lngRaw !== null && $lngRaw !== '') ? (float)$lngRaw : null;

// ── Doğrulama ─────────────────────────────────────────────────────────────────
if ($name === '') {
    wb_err('name zorunludur.', 422, 'missing_name');
}
if ($phone !== '' && !preg_match('/^\+?[0-9()\s\-]{7,20}$/', $phone)) {
    wb_err('Geçerli bir telefon numarası girin.', 422, 'invalid_phone');
}
if ($latitude !== null && ($latitude < -90.0 || $latitude > 90.0)) {
    wb_err('latitude -90 ile 90 arasında olmalı.', 422, 'invalid_latitude');
}
if ($longitude !== null && ($longitude < -180.0 || $longitude > 180.0)) {
    wb_err('longitude -180 ile 180 arasında olmalı.', 422, 'invalid_longitude');
}

try {
    $pdo->prepare("
        UPDATE businesses
        SET name         = ?,
            owner_name   = NULLIF(?, ''),
            phone        = NULLIF(?, ''),
            city         = NULLIF(?, ''),
            district     = NULLIF(?, ''),
            address_line = NULLIF(?, ''),
            about        = NULLIF(?, ''),
            map_url      = NULLIF(?, ''),
            latitude     = ?,
            longitude    = ?,
            building_no  = NULLIF(?, ''),
            neighborhood = NULLIF(?, '')
        WHERE id = ?
    ")->execute([
        $name,
        $ownerName, $phone, $city, $district,
        $addressLine, $about, $mapUrl,
        $latitude, $longitude,
        $buildingNo, $neighborhood,
        $businessId,
    ]);

    // ── Güncel satırı döndür ──────────────────────────────────────────────────
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
    $row    = $stmt->fetch() ?: [];
    $images = mobile_images($row['images_json'] ?? null);

    wb_ok(['business' => [
        'id'                   => (string)($row['id'] ?? $businessId),
        'name'                 => (string)($row['name'] ?? $name),
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
        'latitude'             => ($row['latitude']  ?? null) !== null ? (float)$row['latitude']  : null,
        'longitude'            => ($row['longitude'] ?? null) !== null ? (float)$row['longitude'] : null,
        'building_no'          => $row['building_no']   ?? null,
        'neighborhood'         => $row['neighborhood']  ?? null,
        'cover_image_url'      => $images['cover_image_url'],
        'logo_url'             => $images['logo_url'],
        'onboarding_step'      => (int)($row['onboarding_step']      ?? 1),
        'onboarding_completed' => (bool)($row['onboarding_completed'] ?? false),
    ]]);

} catch (Throwable $e) {
    error_log('[mobile/business/profile-save.php] ' . $e->getMessage());
    wb_err('Profil kaydedilemedi.', 500, 'internal_error');
}
