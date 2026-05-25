<?php
declare(strict_types=1);
/**
 * api/mobile/business/deposit-save.php
 * POST — Token sahibi işletmenin kapora politikasını günceller (upsert).
 *
 * Body (JSON — tüm alanlar opsiyonel, sadece gönderilenler güncellenir):
 *   rate_pct         : int    — 0-100
 *   per_service      : bool
 *   cancel_policy    : string — maks 20 karakter
 *
 * Yanıt: policy objesi (güncel değerler)
 *
 * Tablo: deposit_policies (canlı şema)
 *   id int, business_id int UNIQUE, rate_pct tinyint DEFAULT 25,
 *   per_service tinyint(1) DEFAULT 0, cancel_policy varchar(20) DEFAULT 'esnek',
 *   updated_at datetime ON UPDATE CURRENT_TIMESTAMP
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

// ── Input doğrulama ───────────────────────────────────────────────────────────
$ratePct = isset($in['rate_pct']) ? max(0, min(100, (int)$in['rate_pct'])) : null;

$cancelPolicy = isset($in['cancel_policy'])
    ? mb_substr(trim((string)$in['cancel_policy']), 0, 20)
    : null;

$perService = isset($in['per_service']) ? ((bool)$in['per_service'] ? 1 : 0) : null;

// ── Mevcut politikayı oku (varsayılan değerler için) ─────────────────────────
try {
    $stmt = $pdo->prepare(
        'SELECT rate_pct, per_service, cancel_policy FROM deposit_policies WHERE business_id = ? LIMIT 1'
    );
    $stmt->execute([$businessId]);
    $existing = $stmt->fetch();

    $finalRatePct    = $ratePct      ?? ($existing ? (int)$existing['rate_pct']        : 25);
    $finalPerService = $perService   ?? ($existing ? (int)$existing['per_service']      : 0);
    $finalPolicy     = ($cancelPolicy !== null && $cancelPolicy !== '')
                         ? $cancelPolicy
                         : ($existing ? (string)$existing['cancel_policy'] : 'esnek');

    // ── Upsert ───────────────────────────────────────────────────────────────
    $pdo->prepare("
        INSERT INTO deposit_policies (business_id, rate_pct, per_service, cancel_policy)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE
            rate_pct      = VALUES(rate_pct),
            per_service   = VALUES(per_service),
            cancel_policy = VALUES(cancel_policy),
            updated_at    = CURRENT_TIMESTAMP
    ")->execute([$businessId, $finalRatePct, $finalPerService, $finalPolicy]);

    wb_ok(['policy' => [
        'rate_pct'      => $finalRatePct,
        'per_service'   => (bool)$finalPerService,
        'cancel_policy' => $finalPolicy,
    ]]);

} catch (Throwable $e) {
    error_log('[mobile/business/deposit-save.php] ' . $e->getMessage());
    wb_err('Kapora politikası kaydedilemedi.', 500, 'internal_error');
}
