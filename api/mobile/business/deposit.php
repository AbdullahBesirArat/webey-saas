<?php
declare(strict_types=1);
/**
 * api/mobile/business/deposit.php
 * GET — Token sahibi işletmenin kapora politikasını döner.
 *
 * Yanıt:
 *   policy : object
 *     - rate_pct         : int          — kapora oranı (%)
 *     - per_service      : bool         — hizmet bazında mı?
 *     - cancel_policy    : string       — esnek|siki|yok
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

wb_method('GET');

$auth       = mobile_auth($pdo, ['business', 'admin']);
$ctx        = mobile_business_context($pdo, $auth);
$businessId = (int)$ctx['business_id'];

try {
    $stmt = $pdo->prepare(
        'SELECT rate_pct, per_service, cancel_policy FROM deposit_policies WHERE business_id = ? LIMIT 1'
    );
    $stmt->execute([$businessId]);
    $row = $stmt->fetch();

    wb_ok(['policy' => [
        'rate_pct'      => $row ? (int)$row['rate_pct']        : 25,
        'per_service'   => $row ? (bool)$row['per_service']     : false,
        'cancel_policy' => $row ? (string)$row['cancel_policy'] : 'esnek',
    ]]);

} catch (Throwable $e) {
    error_log('[mobile/business/deposit.php] ' . $e->getMessage());
    wb_err('Kapora politikası alınamadı.', 500, 'internal_error');
}
