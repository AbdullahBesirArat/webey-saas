<?php
declare(strict_types=1);
/**
 * api/billing/cron_expire.php
 * ─────────────────────────────────────────────────────────
 * Günlük cron job: Süresi dolan abonelikleri expire eder,
 * sahiplerinin dükkanını "suspended" durumuna alır.
 *
 * Cron örneği (sunucuda):
 *   0 2 * * * php /var/www/html/webey/api/billing/cron_expire.php >> /var/log/webey_cron.log 2>&1
 *
 * Manuel test:
 *   php api/billing/cron_expire.php
 */
declare(strict_types=1);

// CLI'dan çalıştırılıyorsa güvenli; web'den erişimi engelle
require_once __DIR__ . '/../wb_response.php';

if (PHP_SAPI !== 'cli') {
    // Web'den gizli token ile de çağrılabilsin (cron hosting için)
    $secret = getenv('CRON_SECRET') ?: 'webey_cron_2026';
    if (($secret !== 'disable') && (($_GET['secret'] ?? '') !== $secret)) {
        wb_err('Forbidden', 403, 'forbidden');
    }
    header('Content-Type: application/json; charset=utf-8');
}

require_once __DIR__ . '/../db.php';

$now  = date('Y-m-d H:i:s');
$log  = [];
$log[] = "[{$now}] Cron başladı";

/* ── 1. Süresi dolan aktif abonelikleri expire et ── */
try {
    $stmt = $pdo->prepare("
        UPDATE subscriptions
        SET status = 'expired', updated_at = NOW()
        WHERE status = 'active'
          AND end_date < NOW()
    ");
    $stmt->execute();
    $expiredCount = $stmt->rowCount();
    $log[] = "Expire edilen abonelik: {$expiredCount}";
} catch (Throwable $e) {
    $log[] = "HATA (expire): " . $e->getMessage();
    $expiredCount = 0;
}

/* ── 2. cancel_at_period_end = 1 olanları dönem sonunda iptal et ── */
try {
    $stmt = $pdo->prepare("
        UPDATE subscriptions
        SET status = 'cancelled', cancelled_at = NOW(), updated_at = NOW()
        WHERE status = 'active'
          AND cancel_at_period_end = 1
          AND end_date < NOW()
    ");
    $stmt->execute();
    $cancelledCount = $stmt->rowCount();
    $log[] = "Dönem sonu iptal: {$cancelledCount}";
} catch (Throwable $e) {
    $log[] = "HATA (cancel): " . $e->getMessage();
    $cancelledCount = 0;
}

/* ── 3. Aboneliği biten kullanıcıların dükkanını suspend et ── */
// Koşul: aboneliği yok (veya expired/cancelled) VE deneme süresi de bitti
try {
    $stmt = $pdo->prepare("
        UPDATE businesses b
        JOIN users u ON u.id = b.owner_id
        SET b.status = 'suspended', b.updated_at = NOW()
        WHERE b.status = 'active'
          AND b.onboarding_completed = 1
          AND NOT EXISTS (
              SELECT 1 FROM subscriptions s
              WHERE s.user_id = b.owner_id
                AND s.status = 'active'
                AND s.end_date > NOW()
          )
          AND u.created_at < DATE_SUB(NOW(), INTERVAL 30 DAY)
    ");
    $stmt->execute();
    $suspendedCount = $stmt->rowCount();
    $log[] = "Suspend edilen dükkan: {$suspendedCount}";
} catch (Throwable $e) {
    $log[] = "HATA (suspend): " . $e->getMessage();
    $suspendedCount = 0;
}

/* ── 4. Aboneliği yenilenen dükkanları tekrar aktif et ── */
try {
    $stmt = $pdo->prepare("
        UPDATE businesses b
        SET b.status = 'active', b.updated_at = NOW()
        WHERE b.status = 'suspended'
          AND b.onboarding_completed = 1
          AND EXISTS (
              SELECT 1 FROM subscriptions s
              WHERE s.user_id = b.owner_id
                AND s.status = 'active'
                AND s.end_date > NOW()
          )
    ");
    $stmt->execute();
    $reactivatedCount = $stmt->rowCount();
    $log[] = "Yeniden aktif edilen dükkan: {$reactivatedCount}";
} catch (Throwable $e) {
    $log[] = "HATA (reactivate): " . $e->getMessage();
    $reactivatedCount = 0;
}

/* ── Özet ── */
$summary = [
    'ok'          => true,
    'ran_at'      => $now,
    'expired'     => $expiredCount,
    'cancelled'   => $cancelledCount,
    'suspended'   => $suspendedCount,
    'reactivated' => $reactivatedCount,
    'log'         => $log,
];

$log[] = "Tamamlandı: " . json_encode(array_slice($summary, 0, -1));

// CLI: düz metin, Web: JSON (wb_ok)
if (PHP_SAPI === 'cli') {
    foreach ($log as $line) echo $line . PHP_EOL;
} else {
    wb_ok([
        'ran_at'      => $now,
        'expired'     => $expiredCount,
        'cancelled'   => $cancelledCount,
        'suspended'   => $suspendedCount,
        'reactivated' => $reactivatedCount,
        'log'         => $log,
    ]);
}