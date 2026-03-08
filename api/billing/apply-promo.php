<?php
declare(strict_types=1);
/**
 * api/billing/apply-promo.php
 * POST { code, plan } → kodu doğrula, indirim bilgisi döndür
 * Gerçek kayıt subscribe.php'de yapılır
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$body   = wb_body();
$code   = strtoupper(trim($body['code'] ?? ''));
$plan   = trim($body['plan'] ?? '');
$userId = $user['user_id'];

$PLAN_PRICES = [
    'monthly_1' => ['price' => 1150,  'label' => '1 Aylık Plan'],
    'monthly_3' => ['price' => 2865,  'label' => '3 Aylık Plan'],
    'monthly_6' => ['price' => 4620,  'label' => '6 Aylık Plan'],
    'yearly_1'  => ['price' => 6900,  'label' => '1 Yıllık Plan'],
    'yearly_2'  => ['price' => 11040, 'label' => '2 Yıllık Plan'],
];

if (!$code) { wb_err('Kod girin', 400, 'missing_code'); }
if (!isset($PLAN_PRICES[$plan])) { wb_err('Geçersiz plan', 400, 'invalid_plan'); }

try {
    $promo = $pdo->prepare("SELECT * FROM promo_codes WHERE code=? AND is_active=1 LIMIT 1");
    $promo->execute([$code]);
    $p = $promo->fetch();

    if (!$p) { wb_err('Geçersiz veya aktif olmayan promosyon kodu', 400, 'invalid_code'); }

    if ($p['expires_at'] && strtotime($p['expires_at']) < time()) {
        wb_err('Bu promosyon kodu süresi dolmuş', 400, 'expired_code');
    }
    if ($p['max_uses'] !== null && (int)$p['used_count'] >= (int)$p['max_uses']) {
        wb_err('Bu promosyon kodu kullanım limitine ulaştı', 400, 'limit_reached');
    }
    if ($p['plan'] !== null && $p['plan'] !== $plan) {
        $planLabel = $PLAN_PRICES[$p['plan']]['label'] ?? $p['plan'];
        wb_err("Bu kod sadece '{$planLabel}' için geçerli", 400, 'plan_mismatch');
    }

    $used = $pdo->prepare("SELECT id FROM promo_code_uses WHERE promo_id=? AND user_id=? LIMIT 1");
    $used->execute([$p['id'], $userId]);
    if ($used->fetch()) { wb_err('Bu kodu daha önce kullandınız', 409, 'already_used'); }

    $originalPrice = $PLAN_PRICES[$plan]['price'];
    $finalPrice = match($p['discount_type']) {
        'free'    => 0,
        'percent' => max(0, round($originalPrice * (1 - $p['discount_value'] / 100))),
        'fixed'   => max(0, $originalPrice - $p['discount_value']),
        default   => $originalPrice,
    };
    $discountLabel = match($p['discount_type']) {
        'free'    => 'Ücretsiz',
        'percent' => '%' . (int)$p['discount_value'] . ' indirim',
        'fixed'   => '₺' . number_format($p['discount_value'], 0, ',', '.') . ' indirim',
        default   => 'İndirim',
    };

    wb_ok([
        'promo_id'       => (int)$p['id'],
        'code'           => $p['code'],
        'discount_type'  => $p['discount_type'],
        'discount_value' => (float)$p['discount_value'],
        'discount_label' => $discountLabel,
        'original_price' => $originalPrice,
        'final_price'    => (float)$finalPrice,
        'is_free'        => $finalPrice == 0,
    ]);

} catch (Throwable $e) {
    error_log('[billing/apply-promo.php] ' . $e->getMessage());
    wb_err('Kod doğrulanamadı', 500, 'internal_error');
}