<?php
declare(strict_types=1);
/**
 * api/staff/list.php
 * GET — Personel listesi (çalışma saatleri + servis ID'leri dahil)
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('GET');

$bid = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');

try {
    // photo_url/photo_opt kolonları yoksa graceful fallback
    try {
        $stmt = $pdo->prepare('SELECT id, name, position, phone, color, photo_url, photo_opt FROM staff WHERE business_id = ? ORDER BY id ASC');
        $stmt->execute([$bid]);
    } catch (PDOException) {
        $stmt = $pdo->prepare('SELECT id, name, position, phone, color FROM staff WHERE business_id = ? ORDER BY id ASC');
        $stmt->execute([$bid]);
    }
    $rows = $stmt->fetchAll();

    $staffList = [];
    foreach ($rows as $s) {
        $sid = (int)$s['id'];

        // Önce personele özel saatler, yoksa işletme saatleri
        $sh = $pdo->prepare('
            SELECT day, is_open, open_time, close_time FROM staff_hours
            WHERE staff_id = ? AND business_id = ?
            ORDER BY FIELD(day,"mon","tue","wed","thu","fri","sat","sun")
        ');
        $sh->execute([$sid, $bid]);
        $hourRows = $sh->fetchAll();

        if (empty($hourRows)) {
            $bh = $pdo->prepare('
                SELECT day, is_open, open_time, close_time FROM business_hours
                WHERE business_id = ?
                ORDER BY FIELD(day,"mon","tue","wed","thu","fri","sat","sun")
            ');
            $bh->execute([$bid]);
            $hourRows = $bh->fetchAll();
        }

        $hoursOverride = [];
        foreach ($hourRows as $h) {
            $isOpen = (bool)$h['is_open'];
            $from   = ($isOpen && $h['open_time'])  ? substr($h['open_time'],  0, 5) : null;
            $to     = ($isOpen && $h['close_time']) ? substr($h['close_time'], 0, 5) : null;
            $hoursOverride[$h['day']] = [
                'open'  => $isOpen,
                'start' => $from, 'end' => $to,
                'from'  => $from, 'to'  => $to,
            ];
        }

        $ss = $pdo->prepare('SELECT service_id FROM staff_services WHERE staff_id = ?');
        $ss->execute([$sid]);
        $serviceIds = array_column($ss->fetchAll(), 'service_id');

        $staffList[] = [
            'id'            => (string)$sid,
            'name'          => $s['name'],
            'position'      => $s['position'] ?? 'Personel',
            'phone'         => $s['phone']     ?? null,
            'color'         => $s['color']     ?? null,
            'hoursOverride' => $hoursOverride,
            'serviceIds'    => array_map('strval', $serviceIds),
            'photoUrl'      => $s['photo_url'] ?? null,
            'photoOpt'      => $s['photo_opt'] ?? null,
        ];
    }

    wb_ok(['staff' => $staffList]);

} catch (Throwable $e) {
    error_log('[staff/list] ' . $e->getMessage());
    wb_err('Personel listesi alınamadı', 500, 'internal_error');
}