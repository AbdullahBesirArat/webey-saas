<?php
declare(strict_types=1);
/**
 * api/mobile/customer/profile-save.php
 * POST — Token sahibi müşterinin profil bilgisini günceller.
 *
 * Body (JSON):
 *   first_name    : string|null
 *   last_name     : string|null
 *   phone         : string|null
 *   city          : string|null
 *   district      : string|null
 *   neighborhood  : string|null
 *
 * Yanıt: profile.php ile aynı format (CustomerProfile.fromJson uyumlu).
 *
 * Faz 8A — Bearer token zorunlu, customer tipi.
 */

require_once __DIR__ . '/../_bootstrap.php';
require_once __DIR__ . '/../_auth.php';

wb_method('POST');

$session = mobile_auth($pdo, 'customer');
$userId  = $session['user_id'];

$in = wb_body();

// ── Input sanitization ────────────────────────────────────────────────────────
$firstName    = mb_substr(trim((string)($in['first_name']   ?? '')), 0, 100);
$lastName     = mb_substr(trim((string)($in['last_name']    ?? '')), 0, 100);
$phone        = mb_substr(trim((string)($in['phone']        ?? '')), 0, 30);
$city         = mb_substr(trim((string)($in['city']         ?? '')), 0, 80);
$district     = mb_substr(trim((string)($in['district']     ?? '')), 0, 80);
$neighborhood = mb_substr(trim((string)($in['neighborhood'] ?? '')), 0, 80);

// Telefon format kontrolü: rakam, boşluk, parantez, tire, artı içerebilir
if ($phone !== '' && !preg_match('/^[\+\d\s\-\(\)]{7,20}$/', $phone)) {
    wb_err('Telefon numarası geçersiz biçimde.', 422, 'invalid_phone');
}

try {
    // ── customers satırının var olup olmadığını kontrol et ────────────────────
    $checkStmt = $pdo->prepare("SELECT id FROM customers WHERE user_id = ? LIMIT 1");
    $checkStmt->execute([$userId]);
    $existingId = $checkStmt->fetchColumn();

    if ($existingId !== false) {
        // Satır var → UPDATE
        $pdo->prepare("
            UPDATE customers
            SET first_name    = ?,
                last_name     = ?,
                phone         = ?,
                city          = ?,
                district      = ?,
                neighborhood  = ?
            WHERE user_id = ?
        ")->execute([
            $firstName    !== '' ? $firstName    : null,
            $lastName     !== '' ? $lastName     : null,
            $phone        !== '' ? $phone        : null,
            $city         !== '' ? $city         : null,
            $district     !== '' ? $district     : null,
            $neighborhood !== '' ? $neighborhood : null,
            $userId,
        ]);
    } else {
        // Satır yok → INSERT
        $pdo->prepare("
            INSERT INTO customers
                (user_id, first_name, last_name, phone, city, district, neighborhood)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ")->execute([
            $userId,
            $firstName    !== '' ? $firstName    : null,
            $lastName     !== '' ? $lastName     : null,
            $phone        !== '' ? $phone        : null,
            $city         !== '' ? $city         : null,
            $district     !== '' ? $district     : null,
            $neighborhood !== '' ? $neighborhood : null,
        ]);
    }

    // ── Güncel profil datasını çek (profile.php ile aynı sorgu) ───────────────
    $stmt = $pdo->prepare("
        SELECT
            u.id,
            u.email,
            u.name          AS display_name,
            u.avatar_url,
            u.created_at,
            u.last_login_at,
            c.first_name,
            c.last_name,
            c.phone,
            c.birthday,
            c.city,
            c.district,
            c.neighborhood,
            c.sms_ok,
            c.email_ok
        FROM users u
        LEFT JOIN customers c ON c.user_id = u.id
        WHERE u.id = ? AND u.role = 'user'
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row) {
        wb_err('Kullanıcı bulunamadı', 404, 'user_not_found');
    }

    // ── İsim birleştirme ──────────────────────────────────────────────────────
    $fn       = trim((string)($row['first_name'] ?? ''));
    $ln       = trim((string)($row['last_name']  ?? ''));
    $fullName = trim("$fn $ln");
    if ($fullName === '') {
        $fullName = trim((string)($row['display_name'] ?? ''));
    }

    // ── Randevu istatistikleri ────────────────────────────────────────────────
    $statsStmt = $pdo->prepare("
        SELECT
            COUNT(*) AS total,
            SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) AS completed,
            SUM(CASE
                    WHEN status IN ('cancelled','cancellation_requested','rejected','declined')
                    THEN 1 ELSE 0
                END) AS cancelled
        FROM appointments
        WHERE customer_user_id = ?
    ");
    $statsStmt->execute([$userId]);
    $stats = $statsStmt->fetch() ?: [];

    wb_ok([
        'profile' => [
            'id'            => (string)$row['id'],
            'email'         => (string)($row['email'] ?? ''),
            'full_name'     => $fullName !== '' ? $fullName : null,
            'first_name'    => $fn !== '' ? $fn : null,
            'last_name'     => $ln !== '' ? $ln : null,
            'phone'         => $row['phone']        ?? null,
            'birthday'      => $row['birthday']     ?? null,
            'city'          => $row['city']         ?? null,
            'district'      => $row['district']     ?? null,
            'neighborhood'  => $row['neighborhood'] ?? null,
            'avatar_url'    => $row['avatar_url']   ?? null,
            'sms_ok'        => (bool)($row['sms_ok']   ?? true),
            'email_ok'      => (bool)($row['email_ok'] ?? false),
            'created_at'    => $row['created_at']    ?? null,
            'last_login_at' => $row['last_login_at'] ?? null,
            'stats'         => [
                'appointments_count' => (int)($stats['total']     ?? 0),
                'completed_count'    => (int)($stats['completed'] ?? 0),
                'cancelled_count'    => (int)($stats['cancelled'] ?? 0),
            ],
        ],
    ]);

} catch (Throwable $e) {
    error_log('[mobile/customer/profile-save.php] ' . $e->getMessage());
    wb_err('Profil güncellenemedi', 500, 'internal_error');
}
