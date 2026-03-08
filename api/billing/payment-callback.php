<?php
declare(strict_types=1);
/**
 * api/billing/payment-callback.php
 * İyzico ödeme tamamlandıktan sonra çağrılır (POST redirect)
 * JSON API değil — kullanıcıyı fiyat.html'e redirect eder
 */

ini_set('display_errors', '0');
error_reporting(E_ALL);

require_once __DIR__ . '/../../db.php';
require_once __DIR__ . '/../_iyzico.php';

$cfg = require __DIR__ . '/../_iyzico_config.php';

$userId = (int)($_GET['userId'] ?? 0);
$plan   = preg_replace('/[^a-z0-9_]/', '', $_GET['plan'] ?? '');
$token  = trim($_POST['token'] ?? '');

$PLANS = [
    'monthly_1' => ['months' => 1,  'price' => 1150,  'label' => '1 Aylık Plan'],
    'monthly_3' => ['months' => 3,  'price' => 2865,  'label' => '3 Aylık Plan'],
    'monthly_6' => ['months' => 6,  'price' => 4620,  'label' => '6 Aylık Plan'],
    'yearly_1'  => ['months' => 12, 'price' => 6900,  'label' => '1 Yıllık Plan'],
    'yearly_2'  => ['months' => 24, 'price' => 11040, 'label' => '2 Yıllık Plan'],
];

if (!$userId || !isset($PLANS[$plan])) {
    header('Location: /fiyat.html?payment=error');
    exit;
}

// İyzico doğrulama
if (!$cfg['debug'] && $token) {
    $verifyPayload = [
        'locale'         => 'tr',
        'conversationId' => 'sub_' . $userId,
        'token'          => $token,
    ];
    $resp = _iyzicoPost($cfg, '/payment/iyzipos/checkoutform/auth/ecom/detail', $verifyPayload);

    if (($resp['status'] ?? '') !== 'success' || ($resp['paymentStatus'] ?? '') !== 'SUCCESS') {
        error_log('[payment-callback] Doğrulama başarısız: ' . json_encode($resp));
        header('Location: /fiyat.html?payment=failed');
        exit;
    }
    $paymentId = (string)($resp['paymentId'] ?? '');
} else {
    $paymentId = 'DEBUG_' . time();
}

// Aboneliği aktifleştir
$planInfo  = $PLANS[$plan];
$startDate = new DateTime();
$endDate   = (clone $startDate)->modify("+{$planInfo['months']} months");

try {
    $pdo->beginTransaction();

    $pdo->prepare("UPDATE subscriptions SET status='cancelled', cancelled_at=NOW() WHERE user_id=? AND status IN ('active','trialing')")
        ->execute([$userId]);

    $pdo->prepare("
        INSERT INTO subscriptions (user_id, plan, status, price, start_date, end_date, iyzico_subscription_id, created_at)
        VALUES (?, ?, 'active', ?, ?, ?, ?, NOW())
    ")->execute([$userId, $plan, $planInfo['price'], $startDate->format('Y-m-d H:i:s'), $endDate->format('Y-m-d H:i:s'), $paymentId]);

    $subId = $pdo->lastInsertId();

    $pdo->prepare("
        INSERT INTO invoices (subscription_id, user_id, plan_label, amount, status, iyzico_payment_id, paid_at, created_at)
        VALUES (?, ?, ?, ?, 'paid', ?, NOW(), NOW())
    ")->execute([$subId, $userId, $planInfo['label'], $planInfo['price'], $paymentId]);

    $pdo->prepare("UPDATE businesses SET status='active', updated_at=NOW() WHERE owner_id=? AND status='suspended' AND onboarding_completed=1")
        ->execute([$userId]);

    $pdo->commit();

    header('Location: /fiyat.html?payment=success&plan=' . $plan);
    exit;

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[payment-callback] DB hatası: ' . $e->getMessage());
    header('Location: /fiyat.html?payment=error');
    exit;
}