<?php
declare(strict_types=1);
/**
 * api/superadmin/promo/delete.php
 * POST { id } — kodu sil, ilgili abonelikleri anında iptal et
 */

require_once __DIR__ . '/../_bootstrap.php';
wb_method('POST');

$body = wb_body();
$id   = (int)($body['id'] ?? 0);
if (!$id) { wb_err('id zorunlu', 400, 'missing_param'); }

try {
    $check = $pdo->prepare("SELECT id, code FROM promo_codes WHERE id = ? LIMIT 1");
    $check->execute([$id]);
    $promo = $check->fetch();
    if (!$promo) { wb_err('Promosyon kodu bulunamadı', 404, 'not_found'); }

    $pdo->beginTransaction();

    $subStmt = $pdo->prepare("
        SELECT DISTINCT pcu.subscription_id FROM promo_code_uses pcu
        WHERE pcu.promo_id = ? AND pcu.subscription_id IS NOT NULL
    ");
    $subStmt->execute([$id]);
    $subIds = $subStmt->fetchAll(\PDO::FETCH_COLUMN);

    $cancelledCount = 0;
    $affectedUsers  = [];

    if (!empty($subIds)) {
        $ph = implode(',', array_fill(0, count($subIds), '?'));
        $userStmt = $pdo->prepare("
            SELECT s.user_id, s.plan, u.email FROM subscriptions s
            JOIN users u ON u.id = s.user_id
            WHERE s.id IN ($ph) AND s.status IN ('active','trialing','past_due')
        ");
        $userStmt->execute($subIds);
        $affectedUsers = $userStmt->fetchAll();

        $cancelStmt = $pdo->prepare("
            UPDATE subscriptions
            SET status='cancelled', cancelled_at=NOW(), end_date=NOW(), cancel_at_period_end=0
            WHERE id IN ($ph) AND status IN ('active','trialing','past_due')
        ");
        $cancelStmt->execute($subIds);
        $cancelledCount = $cancelStmt->rowCount();
    }

    $pdo->prepare("DELETE FROM promo_code_uses WHERE promo_id = ?")->execute([$id]);
    $pdo->prepare("DELETE FROM promo_codes WHERE id = ?")->execute([$id]);
    $pdo->commit();

    wb_ok(['message' => 'Promosyon kodu silindi', 'cancelled_subs' => $cancelledCount, 'affected_users' => $affectedUsers]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[promo/delete] ' . $e->getMessage());
    wb_err('İşlem başarısız', 500, 'internal_error');
}