<?php
// api/superadmin/promo/list.php — Tüm promosyon kodlarını listele
declare(strict_types=1);

require_once __DIR__ . '/../../_bootstrap.php';
require_once __DIR__ . '/../_bootstrap.php';

wb_method('GET');
wb_auth_superadmin();

try {
    $rows = $pdo->query("
        SELECT p.*,
               u.email AS created_by_email,
               (SELECT COUNT(*) FROM promo_code_uses WHERE promo_id = p.id) AS actual_uses,
               (SELECT MAX(pcu.used_at) FROM promo_code_uses pcu WHERE pcu.promo_id = p.id) AS last_used_at,
               (SELECT uu.email FROM promo_code_uses pcu2
                JOIN users uu ON uu.id = pcu2.user_id
                WHERE pcu2.promo_id = p.id ORDER BY pcu2.used_at DESC LIMIT 1) AS last_used_by_email
        FROM promo_codes p
        LEFT JOIN users u ON u.id = p.created_by
        ORDER BY p.created_at DESC
    ")->fetchAll(PDO::FETCH_ASSOC);

    $codes = array_map(fn($r) => [
        'id'                 => (int)$r['id'],
        'code'               => $r['code'],
        'plan'               => $r['plan'],
        'discount_type'      => $r['discount_type'],
        'discount_value'     => (float)$r['discount_value'],
        'max_uses'           => $r['max_uses'] !== null ? (int)$r['max_uses'] : null,
        'used_count'         => (int)$r['actual_uses'],
        'expires_at'         => $r['expires_at'],
        'is_active'          => (bool)$r['is_active'],
        'note'               => $r['note'],
        'created_by'         => $r['created_by_email'],
        'created_at'         => $r['created_at'],
        'updated_at'         => $r['updated_at'],
        'last_used_at'       => $r['last_used_at'],
        'last_used_by_email' => $r['last_used_by_email'],
        'is_expired'         => $r['expires_at'] && strtotime($r['expires_at']) < time(),
        'is_exhausted'       => $r['max_uses'] !== null && (int)$r['actual_uses'] >= (int)$r['max_uses'],
    ], $rows);

    wb_ok(['codes' => $codes]);

} catch (Throwable $e) {
    error_log('[promo/list] ' . $e->getMessage());
    wb_err('Sunucu hatası', 500, 'internal_error');
}