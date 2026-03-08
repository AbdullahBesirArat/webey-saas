<?php
declare(strict_types=1);
/**
 * api/calendar/bootstrap.php
 * GET — Takvim başlangıç verisi (user, business, hours, staff, catalog)
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('GET');

$bid = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');
$uid = $user['user_id'];

try {
    // User
    $uStmt = $pdo->prepare('SELECT id, email FROM users WHERE id = ? LIMIT 1');
    $uStmt->execute([$uid]);
    $uRow = $uStmt->fetch();
    if (!$uRow) wb_err('Kullanıcı bulunamadı', 401, 'unauthorized');

    // Business
    $bStmt = $pdo->prepare('SELECT id, name, owner_name FROM businesses WHERE id = ? LIMIT 1');
    $bStmt->execute([$bid]);
    $bRow = $bStmt->fetch();
    if (!$bRow) wb_err('İşletme bulunamadı', 404, 'business_not_found');

    // Working hours
    $hStmt = $pdo->prepare("
        SELECT day, is_open, open_time, close_time
        FROM business_hours
        WHERE business_id = ?
        ORDER BY FIELD(day,'mon','tue','wed','thu','fri','sat','sun')
    ");
    $hStmt->execute([$bid]);
    $defaultHours = [];
    foreach ($hStmt->fetchAll() as $h) {
        $isOpen = (bool)$h['is_open'];
        $from   = ($isOpen && $h['open_time'])  ? substr($h['open_time'],  0, 5) : null;
        $to     = ($isOpen && $h['close_time']) ? substr($h['close_time'], 0, 5) : null;
        $defaultHours[$h['day']] = ['open' => $isOpen, 'from' => $from, 'to' => $to, 'start' => $from, 'end' => $to];
    }

    // Staff + per-staff hours
    try {
        $sStmt = $pdo->prepare('SELECT id, name, color, photo_url, photo_opt FROM staff WHERE business_id = ? ORDER BY name');
        $sStmt->execute([$bid]);
    } catch (Throwable) {
        $sStmt = $pdo->prepare('SELECT id, name, color FROM staff WHERE business_id = ? ORDER BY name');
        $sStmt->execute([$bid]);
    }
    $staffRows = $sStmt->fetchAll();

    $staff = [];
    foreach ($staffRows as $s) {
        $sid  = (int)$s['id'];
        $shS  = $pdo->prepare("
            SELECT day, is_open, open_time, close_time
            FROM staff_hours
            WHERE staff_id = ? AND business_id = ?
            ORDER BY FIELD(day,'mon','tue','wed','thu','fri','sat','sun')
        ");
        $shS->execute([$sid, $bid]);
        $shRows = $shS->fetchAll();

        if (!empty($shRows)) {
            $hoursOverride = [];
            foreach ($shRows as $h) {
                $isOpen = (bool)$h['is_open'];
                $from   = ($isOpen && $h['open_time'])  ? substr($h['open_time'],  0, 5) : null;
                $to     = ($isOpen && $h['close_time']) ? substr($h['close_time'], 0, 5) : null;
                $hoursOverride[$h['day']] = ['open' => $isOpen, 'from' => $from, 'to' => $to, 'start' => $from, 'end' => $to];
            }
        } else {
            $hoursOverride = $defaultHours;
        }

        $staff[] = [
            'id'            => (string)$sid,
            'name'          => $s['name'],
            'color'         => $s['color'] ?? null,
            'photoUrl'      => $s['photo_url'] ?? null,
            'photoOpt'      => $s['photo_opt'] ?? null,
            'hoursOverride' => $hoursOverride,
        ];
    }

    // Services catalog
    $svStmt = $pdo->prepare('SELECT id, name, duration_min, price FROM services WHERE business_id = ? ORDER BY name');
    $svStmt->execute([$bid]);
    $catalog = array_map(fn($sv) => [
        'id'          => (string)$sv['id'],
        'name'        => $sv['name'],
        'durationMin' => (int)$sv['duration_min'],
        'price'       => $sv['price'] !== null ? (float)$sv['price'] : null,
    ], $svStmt->fetchAll());

    $ownerName = $bRow['owner_name'] ?: $uRow['email'];

    wb_ok([
        'user'     => ['uid' => (string)$uRow['id'], 'name' => $ownerName, 'email' => $uRow['email']],
        'business' => ['id' => (string)$bRow['id'], 'name' => $bRow['name'], 'defaultHours' => $defaultHours],
        'owner'    => ['uid' => (string)$uRow['id'], 'name' => $ownerName],
        'staff'    => $staff,
        'catalog'  => $catalog,
    ]);

} catch (Throwable $e) {
    error_log('[calendar/bootstrap] ' . $e->getMessage());
    wb_err('Takvim verisi yüklenemedi', 500, 'internal_error');
}