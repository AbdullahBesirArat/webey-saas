<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
wb_method('POST');

$userId = $user['user_id'];
$in     = wb_body();
$bizId  = (int)($in['business_id'] ?? 0);

if ($bizId <= 0) {
    wb_err('Geçersiz business_id', 400, 'invalid_param');
}

try {
    $check = $pdo->prepare("SELECT id FROM businesses WHERE id = ? AND status = 'active' LIMIT 1");
    $check->execute([$bizId]);
    if (!$check->fetchColumn()) {
        wb_err('İşletme bulunamadı', 404, 'not_found');
    }

    $exists = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND business_id = ? LIMIT 1");
    $exists->execute([$userId, $bizId]);
    $favId = $exists->fetchColumn();

    if ($favId) {
        $pdo->prepare("DELETE FROM favorites WHERE id = ?")->execute([$favId]);
        wb_ok(['favorited' => false]);
    } else {
        $pdo->prepare("INSERT INTO favorites (user_id, business_id) VALUES (?, ?)")->execute([$userId, $bizId]);
        wb_ok(['favorited' => true]);
    }

} catch (Throwable $e) {
    error_log('[user/favorites/toggle.php] ' . $e->getMessage());
    wb_err('İşlem başarısız.', 500, 'internal_error');
}