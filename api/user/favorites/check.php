<?php
declare(strict_types=1);
/**
 * api/user/favorites/check.php
 * GET ?business_id=X  veya  GET ?ids=1,2,3
 * Soft-auth: giriş yoksa false döner, hata vermez.
 */

require_once __DIR__ . '/../../_public_bootstrap.php';
wb_method('GET');

// Giriş yapmamışsa her zaman false döner
if (empty($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'user') {
    if (!empty($_GET['ids'])) {
        $map = [];
        foreach (explode(',', $_GET['ids']) as $id) {
            $i = (int)trim($id);
            if ($i > 0) $map[$i] = false;
        }
        wb_ok(['map' => $map]);
    }
    wb_ok(['favorited' => false]);
}

$userId = (int)$_SESSION['user_id'];

try {
    if (!empty($_GET['ids'])) {
        $ids = array_filter(array_map('intval', explode(',', $_GET['ids'])));
        $ids = array_slice(array_unique($ids), 0, 50);

        if (empty($ids)) { wb_ok(['map' => (object)[]]); }

        $ph   = implode(',', array_fill(0, count($ids), '?'));
        $stmt = $pdo->prepare("SELECT business_id FROM favorites WHERE user_id = ? AND business_id IN ($ph)");
        $stmt->execute(array_merge([$userId], $ids));
        $favored = array_flip($stmt->fetchAll(\PDO::FETCH_COLUMN));

        $map = [];
        foreach ($ids as $id) { $map[$id] = isset($favored[$id]); }
        wb_ok(['map' => $map]);
    }

    $bizId = (int)($_GET['business_id'] ?? 0);
    if ($bizId <= 0) { wb_err('Geçersiz business_id', 400, 'invalid_param'); }

    $stmt = $pdo->prepare("SELECT id FROM favorites WHERE user_id = ? AND business_id = ? LIMIT 1");
    $stmt->execute([$userId, $bizId]);
    wb_ok(['favorited' => (bool)$stmt->fetchColumn()]);

} catch (Throwable $e) {
    error_log('[user/favorites/check.php] ' . $e->getMessage());
    wb_err('Sunucu hatası.', 500, 'internal_error');
}