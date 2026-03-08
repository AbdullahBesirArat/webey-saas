<?php
declare(strict_types=1);
/**
 * api/billing/subscriptions.php — Abonelik listesi
 * GET — admin auth gerekli
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('GET');

$userId = $user['user_id'];

$PLAN_LABELS = [
    'monthly_1' => '1 Aylık Plan',
    'monthly_3' => '3 Aylık Plan',
    'monthly_6' => '6 Aylık Plan',
    'yearly_1'  => '1 Yıllık Plan',
    'yearly_2'  => '2 Yıllık Plan',
];

try {
    $userRow = $pdo->prepare("SELECT created_at FROM users WHERE id=? LIMIT 1");
    $userRow->execute([$userId]);
    $user = $userRow->fetch(PDO::FETCH_ASSOC);

    // Abonelikler + promo kodu (LEFT JOIN ile)
    $stmt = $pdo->prepare("
        SELECT s.id, s.plan, s.status, s.price, s.start_date, s.end_date,
               s.cancel_at_period_end, s.cancelled_at, s.created_at,
               pc.code AS promo_code,
               pc.discount_type,
               pc.discount_value
        FROM subscriptions s
        LEFT JOIN promo_code_uses pcu ON pcu.subscription_id = s.id
        LEFT JOIN promo_codes pc ON pc.id = pcu.promo_id
        WHERE s.user_id = ?
        ORDER BY s.created_at DESC
    ");
    $stmt->execute([$userId]);
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $subscriptions = [];

    // Deneme satırı
    if ($user) {
        $trialStart = new DateTime($user['created_at']);
        $trialEnd   = (clone $trialStart)->modify('+30 days');
        $now        = new DateTime();
        $trialStatus = $trialEnd > $now ? 'trialing' : 'expired';
        $subscriptions[] = [
            'id'         => null,
            'plan'       => 'trial',
            'planLabel'  => '1 Aylık Ücretsiz Deneme',
            'status'     => $trialStatus,
            'price'      => 0,
            'startDate'  => $trialStart->format('Y-m-d H:i:s'),
            'endDate'    => $trialEnd->format('Y-m-d H:i:s'),
            'isTrial'    => true,
            'promoCode'  => null,
        ];
    }

    foreach ($rows as $r) {
        // Ödeme yöntemi etiketi
        $payLabel = '—';
        if ($r['promo_code']) {
            $payLabel = $r['promo_code'];
        } elseif ((float)$r['price'] === 0.0) {
            $payLabel = 'Ücretsiz';
        } else {
            $payLabel = 'Kredi / Banka Kartı';
        }

        $subscriptions[] = [
            'id'          => (int)$r['id'],
            'plan'        => $r['plan'],
            'planLabel'   => $PLAN_LABELS[$r['plan']] ?? $r['plan'],
            'status'      => $r['status'],
            'price'       => (float)$r['price'],
            'startDate'   => $r['start_date'],
            'endDate'     => $r['end_date'],
            'cancelledAt' => $r['cancelled_at'],
            'isTrial'     => false,
            'promoCode'   => $r['promo_code'],
            'payLabel'    => $payLabel,
        ];
    }

    // Aktif abonelik
    $activeSub = null;
    foreach ($rows as $r) {
        if ($r['status'] === 'active' && strtotime($r['end_date']) > time()) {
            $payLabel = '—';
            if ($r['promo_code']) {
                $payLabel = $r['promo_code'];
            } elseif ((float)$r['price'] === 0.0) {
                $payLabel = 'Ücretsiz';
            } else {
                $payLabel = 'Kredi / Banka Kartı';
            }
            $activeSub = [
                'plan'      => $r['plan'],
                'planLabel' => $PLAN_LABELS[$r['plan']] ?? $r['plan'],
                'status'    => $r['status'],
                'endDate'   => $r['end_date'],
                'price'     => (float)$r['price'],
                'promoCode' => $r['promo_code'],
                'payLabel'  => $payLabel,
            ];
            break;
        }
    }

    wb_ok(['subscriptions' => $subscriptions, 'activeSub' => $activeSub]);
} catch (Throwable $e) {
    error_log('[billing/subscriptions] ' . $e->getMessage());
    wb_ok(['subscriptions' => [], 'activeSub' => null]);
}