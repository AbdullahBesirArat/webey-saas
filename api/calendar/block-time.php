<?php
declare(strict_types=1);
/**
 * api/calendar/block-time.php
 * POST { staffId, date, startTime, endTime, note }
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$bid = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');

$in = wb_body();
$staffId   = isset($in['staffId']) ? (int)$in['staffId'] : 0;
$date      = trim($in['date']      ?? '');
$startTime = trim($in['startTime'] ?? '');
$endTime   = trim($in['endTime']   ?? '');
$note      = trim($in['note']      ?? 'Dolu');

if (!$staffId || !$date || !$startTime || !$endTime) {
    wb_err('staffId, date, startTime, endTime zorunlu', 400, 'missing_params');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) wb_err('Geçersiz tarih formatı', 400, 'invalid_date');
if (!preg_match('/^\d{2}:\d{2}$/', $startTime) || !preg_match('/^\d{2}:\d{2}$/', $endTime)) {
    wb_err('Geçersiz saat formatı (HH:MM bekleniyor)', 400, 'invalid_time');
}
if ($startTime >= $endTime) wb_err('Bitiş saati başlangıçtan sonra olmalı', 400, 'invalid_range');

try {
    $chk = $pdo->prepare('SELECT id FROM staff WHERE id = ? AND business_id = ? LIMIT 1');
    $chk->execute([$staffId, $bid]);
    if (!$chk->fetch()) wb_err('Personel bulunamadı', 403, 'forbidden');

    $pdo->prepare("
        INSERT INTO appointments (business_id, staff_id, start_at, end_at, status, customer_name, notes, created_at)
        VALUES (?, ?, ?, ?, 'blocked', '[DOLU]', ?, NOW())
    ")->execute([$bid, $staffId, "$date $startTime:00", "$date $endTime:00", $note]);

    $newId = $pdo->lastInsertId();
    wb_ok(['id' => (string)$newId, 'startAt' => "$date $startTime:00", 'endAt' => "$date $endTime:00", 'status' => 'blocked']);

} catch (Throwable $e) {
    error_log('[calendar/block-time] ' . $e->getMessage());
    wb_err('Zaman bloke edilemedi', 500, 'internal_error');
}