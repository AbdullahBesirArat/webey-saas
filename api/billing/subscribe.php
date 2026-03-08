<?php
declare(strict_types=1);
/**
 * api/billing/subscribe.php — Abonelik başlat
 * POST { plan, promo_code? } — admin auth gerekli
 * Ücretsiz / debug → direkt aktifleştirir
 * Ücretli → iyzico checkout başlatır
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
require_once __DIR__ . '/../_iyzico.php';
wb_method('POST');

$body      = wb_body();
$userId    = $user['user_id'];
$plan      = $body['plan'] ?? '';
$promoCode = strtoupper(trim($body['promo_code'] ?? ''));

$PLANS = [
    'monthly_1' => ['months' => 1,  'price' => 1150,  'label' => '1 Aylık Plan'],
    'monthly_3' => ['months' => 3,  'price' => 2865,  'label' => '3 Aylık Plan'],
    'monthly_6' => ['months' => 6,  'price' => 4620,  'label' => '6 Aylık Plan'],
    'yearly_1'  => ['months' => 12, 'price' => 6900,  'label' => '1 Yıllık Plan'],
    'yearly_2'  => ['months' => 24, 'price' => 11040, 'label' => '2 Yıllık Plan'],
];

if (!isset($PLANS[$plan])) { wb_err('Geçersiz plan', 400, 'invalid_plan'); }

$planInfo   = $PLANS[$plan];
$finalPrice = (float)$planInfo['price'];
$promoId    = null;
$isFree     = false;

// ── Promosyon kodu doğrula ───────────────────────────────────
if ($promoCode) {
    $promo = $pdo->prepare("
        SELECT * FROM promo_codes
        WHERE code=? AND is_active=1
          AND (expires_at IS NULL OR expires_at > NOW())
          AND (max_uses IS NULL OR used_count < max_uses)
        LIMIT 1
    ");
    $promo->execute([$promoCode]);
    $p = $promo->fetch();

    if (!$p) { wb_err('Geçersiz veya süresi dolmuş promosyon kodu', 400, 'invalid_code'); }
    if ($p['plan'] !== null && $p['plan'] !== $plan) {
        $pLabel = $PLANS[$p['plan']]['label'] ?? $p['plan'];
        wb_err("Bu kod sadece '{$pLabel}' için geçerli", 400, 'plan_mismatch');
    }
    $usedCheck = $pdo->prepare("SELECT id FROM promo_code_uses WHERE promo_id=? AND user_id=? LIMIT 1");
    $usedCheck->execute([$p['id'], $userId]);
    if ($usedCheck->fetch()) { wb_err('Bu kodu daha önce kullandınız', 409, 'already_used'); }

    $promoId    = (int)$p['id'];
    $finalPrice = match($p['discount_type']) {
        'free'    => 0,
        'percent' => max(0, round($planInfo['price'] * (1 - $p['discount_value'] / 100))),
        'fixed'   => max(0, $planInfo['price'] - $p['discount_value']),
        default   => $planInfo['price'],
    };
    $isFree = $finalPrice == 0;
}

$cfg = require __DIR__ . '/../_iyzico_config.php';

// ── Ücretsiz veya debug: direkt aktifleştir ──────────────────
if ($isFree || $cfg['debug']) {
    $startDate = new DateTime();
    $endDate   = (clone $startDate)->modify("+{$planInfo['months']} months");

    try {
        $pdo->beginTransaction();

        $pdo->prepare("UPDATE subscriptions SET status='cancelled', cancelled_at=NOW() WHERE user_id=? AND status IN ('active','trialing')")
            ->execute([$userId]);
        $pdo->prepare("INSERT INTO subscriptions (user_id,plan,status,price,start_date,end_date,created_at) VALUES (?,?,'active',?,?,?,NOW())")
            ->execute([$userId, $plan, $finalPrice, $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s')]);
        $subId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO invoices (subscription_id,user_id,plan_label,amount,status,created_at) VALUES (?,?,?,?,'paid',NOW())")
            ->execute([$subId, $userId, $planInfo['label'], $finalPrice]);
        $pdo->prepare("UPDATE businesses SET status='active', updated_at=NOW() WHERE owner_id=? AND status='suspended' AND onboarding_completed=1")
            ->execute([$userId]);

        if ($promoId) {
            $pdo->prepare("INSERT INTO promo_code_uses (promo_id,user_id,subscription_id,used_at) VALUES (?,?,?,NOW())")
                ->execute([$promoId, $userId, $subId]);
            $pdo->prepare("UPDATE promo_codes SET used_count=used_count+1 WHERE id=?")
                ->execute([$promoId]);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        error_log('[billing/subscribe.php free] ' . $e->getMessage());
        wb_err('Abonelik kaydedilemedi', 500, 'internal_error');
    }

    $msg = $isFree
        ? "🎉 Promosyon kodu uygulandı! {$planInfo['label']} ücretsiz aktifleştirildi."
        : 'Abonelik oluşturuldu (debug modu)';

    wb_ok(['free' => $isFree, 'debug' => $cfg['debug'] && !$isFree, 'message' => $msg, 'plan' => $plan, 'endDate' => $endDate->format('Y-m-d')]);
}

// ── Ücretli: iyzico checkout başlat ─────────────────────────
$userRow = $pdo->prepare("SELECT u.name, u.email, c.phone FROM users u LEFT JOIN customers c ON c.user_id=u.id WHERE u.id=? LIMIT 1");
$userRow->execute([$userId]);
$userInfo = $userRow->fetch() ?: [];

$iyzico = iyzicoInitCheckout(
    $userId, $plan, $finalPrice,
    $userInfo['name'] ?? 'Webey Kullanıcı',
    $userInfo['email'] ?? '',
    $userInfo['phone'] ?? ''
);

if (!$iyzico['ok']) {
    wb_err($iyzico['error'] ?? 'Ödeme başlatılamadı', 500, 'payment_error');
}

wb_ok([
    'requiresAction' => true,
    'checkoutToken'  => $iyzico['checkoutToken'],
    'checkoutUrl'    => $iyzico['checkoutUrl'],
    'final_price'    => $finalPrice,
]);