<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
wb_method('GET');

$userId = $user['user_id'];

try {
    $stmt = $pdo->prepare("
        SELECT b.id, b.name, b.type, b.city, b.district, b.address_line,
               b.images_json, b.about, f.created_at AS favorited_at
        FROM favorites f
        JOIN businesses b ON b.id = f.business_id
        WHERE f.user_id = ? AND b.status = 'active'
        ORDER BY f.created_at DESC
    ");
    $stmt->execute([$userId]);

    $favorites = array_map(function($row) {
        $cover = null;
        if (!empty($row['images_json'])) {
            $imgs  = json_decode($row['images_json'], true);
            $cover = $imgs['cover'][0] ?? $imgs['salon'][0] ?? null;
        }
        return [
            'id'           => (int)$row['id'],
            'name'         => $row['name'],
            'type'         => $row['type'],
            'city'         => $row['city'],
            'district'     => $row['district'],
            'address_line' => $row['address_line'],
            'about'        => $row['about'],
            'cover'        => $cover,
            'favorited_at' => $row['favorited_at'],
        ];
    }, $stmt->fetchAll());

    wb_ok(['favorites' => $favorites]);

} catch (Throwable $e) {
    error_log('[user/favorites/list.php] ' . $e->getMessage());
    wb_err('Favoriler alınamadı.', 500, 'internal_error');
}