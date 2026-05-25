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

// ── Input sanitization — sadece body'de gelen alanları işle ──────────────────
$colMaxLen = [
    'first_name'   => 100,
    'last_name'    => 100,
    'phone'        => 30,
    'city'         => 80,
    'district'     => 80,
    'neighborhood' => 80,
];

// Telefon format kontrolü: sadece body'de phone varsa
if (array_key_exists('phone', $in)) {
    $phoneRaw = mb_substr(trim((string)($in['phone'] ?? '')), 0, 30);
    if ($phoneRaw !== '' && !preg_match('/^[\+\d\s\-\(\)]{7,20}$/', $phoneRaw)) {
        wb_err('Telefon numarası geçersiz biçimde.', 422, 'invalid_phone');
    }
}

$fields = [];
foreach ($colMaxLen as $col => $max) {
    if (!array_key_exists($col, $in)) continue;
    $val = mb_substr(trim((string)($in[$col] ?? '')), 0, $max);
    $fields[$col] = $val !== '' ? $val : null;
}

try {
    // ── customers satırının var olup olmadığını kontrol et ────────────────────
    $checkStmt = $pdo->prepare("SELECT id FROM customers WHERE user_id = ? LIMIT 1");
    $checkStmt->execute([$userId]);
    $existingId = $checkStmt->fetchColumn();

    if ($fields !== []) {
        if ($existingId !== false) {
            // Satır var → sadece gönderilen alanları UPDATE et
            $setClauses = implode(', ', array_map(fn($col) => "$col = ?", array_keys($fields)));
            $params = array_values($fields);
            $params[] = $userId;
            $pdo->prepare("UPDATE customers SET $setClauses WHERE user_id = ?")->execute($params);
        } else {
            // Satır yok → INSERT
            $cols         = implode(', ', array_keys($fields));
            $placeholders = implode(', ', array_fill(0, count($fields), '?'));
            $params       = [$userId, ...array_values($fields)];
            $pdo->prepare("
                INSERT INTO customers (user_id, $cols)
                VALUES (?, $placeholders)
            ")->execute($params);
        }
    }
    // body boşsa ($fields === []) mevcut profil korunur, hiçbir yazma yapılmaz

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
