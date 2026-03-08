<?php
declare(strict_types=1);
/**
 * api/superadmin/promo/toggle.php
 * POST { id, action: 'activate'|'deactivate'|'delete' }
 */

require_once __DIR__ . '/../_bootstrap.php';
wb_method('POST');

$body   = wb_body();
$id     = (int)($body['id'] ?? 0);
$action = $body['action'] ?? '';

if (!$id || !in_array($action, ['activate','deactivate','delete'])) {
    wb_err('id ve action zorunlu (activate|deactivate|delete)', 400, 'missing_param');
}

try {
    $check = $pdo->prepare("SELECT id, code FROM promo_codes WHERE id=? LIMIT 1");
    $check->execute([$id]);
    if (!$check->fetch()) { wb_err('Kod bulunamadı', 404, 'not_found'); }

    if ($action === 'delete') {
        $pdo->beginTransaction();

        $subIds = $pdo->prepare("SELECT DISTINCT subscription_id FROM promo_code_uses WHERE promo_id=? AND subscription_id IS NOT NULL");
        $subIds->execute([$id]);
        $sids = $subIds->fetchAll(\PDO::FETCH_COLUMN);

        $cancelledCount = 0;
        if (!empty($sids)) {
            $ph = implode(',', array_fill(0, count($sids), '?'));
            $cancelStmt = $pdo->prepare("UPDATE subscriptions SET status='cancelled', cancelled_at=NOW(), cancel_at_period_end=0 WHERE id IN ($ph) AND status IN ('active','trialing','past_due')");
            $cancelStmt->execute($sids);
            $cancelledCount = $cancelStmt->rowCount();
        }

        $pdo->prepare("DELETE FROM promo_codes WHERE id=?")->execute([$id]);
        $pdo->commit();

        wb_ok(['message' => 'Kod silindi', 'cancelled_subs' => $cancelledCount]);

    } else {
        $active = $action === 'activate' ? 1 : 0;
        $pdo->prepare("UPDATE promo_codes SET is_active=? WHERE id=?")->execute([$active, $id]);
        wb_ok(['message' => $active ? 'Kod aktifleştirildi' : 'Kod devre dışı bırakıldı', 'is_active' => (bool)$active]);
    }

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[promo/toggle] ' . $e->getMessage());
    wb_err('İşlem başarısız', 500, 'internal_error');
}