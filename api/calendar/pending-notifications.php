<?php
declare(strict_types=1);
/**
 * api/calendar/pending-notifications.php
 * GET ?since=UNIX_TIMESTAMP — Son sorgudan bu yana gelen yeni randevular
 * wb-notifications.js tarafından tüm admin sayfalarında 30sn poll edilir
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('GET');

$bid   = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');
$since = (int)($_GET['since'] ?? (time() - 60));

try {
    $sinceStr = date('Y-m-d H:i:s', $since);

    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.customer_name, a.customer_phone,
               a.start_at, a.end_at, a.created_at,
               s.name AS service_name, st.name AS staff_name
        FROM appointments a
        LEFT JOIN services s  ON s.id  = a.service_id
        LEFT JOIN staff    st ON st.id = a.staff_id
        WHERE a.business_id = ? AND a.created_at > ? AND a.status = 'pending'
        ORDER BY a.created_at DESC
        LIMIT 20
    ");
    $stmt->execute([$bid, $sinceStr]);
    $rows = $stmt->fetchAll();

    $items = array_map(function($r) use ($pdo, $bid) {
        $start = new DateTime($r['start_at']);
        $stats = ['total' => 0, 'attended' => 0, 'no_show' => 0, 'cancelled' => 0, 'top_service' => null, 'first_visit' => null, 'is_new' => true];

        if (!empty($r['customer_phone'])) {
            $phone10 = substr(preg_replace('/\D/', '', $r['customer_phone']), -10);
            $st = $pdo->prepare("SELECT COUNT(*) AS total, SUM(CASE WHEN status IN ('completed','approved') OR attended=1 THEN 1 ELSE 0 END) AS attended, SUM(CASE WHEN status='no_show' THEN 1 ELSE 0 END) AS no_show_count, SUM(CASE WHEN status IN ('cancelled','rejected','declined') THEN 1 ELSE 0 END) AS cancelled_count, MIN(start_at) AS first_visit FROM appointments WHERE business_id=? AND RIGHT(REPLACE(REPLACE(COALESCE(customer_phone,''),'+',''),' ',''),10)=? AND id!=?");
            $st->execute([$bid, $phone10, (int)$r['id']]);
            $stat = $st->fetch();
            if ($stat) {
                $total = (int)($stat['total'] ?? 0);
                $stats = array_merge($stats, ['total' => $total, 'attended' => (int)($stat['attended'] ?? 0), 'no_show' => (int)($stat['no_show_count'] ?? 0), 'cancelled' => (int)($stat['cancelled_count'] ?? 0), 'first_visit' => $stat['first_visit'] ? date('d.m.Y', strtotime($stat['first_visit'])) : null, 'is_new' => $total === 0]);
            }
            $topSvc = $pdo->prepare("SELECT COALESCE(s.name,'Genel') AS svc_name, COUNT(*) AS cnt FROM appointments a LEFT JOIN services s ON s.id=a.service_id WHERE a.business_id=? AND RIGHT(REPLACE(REPLACE(COALESCE(a.customer_phone,''),'+',''),' ',''),10)=? AND a.status NOT IN ('cancelled','rejected','declined') GROUP BY svc_name ORDER BY cnt DESC LIMIT 1");
            $topSvc->execute([$bid, $phone10]);
            $ts = $topSvc->fetch();
            if ($ts) $stats['top_service'] = $ts['svc_name'];
        }

        return ['id' => (string)$r['id'], 'status' => $r['status'], 'customerName' => $r['customer_name'], 'customerPhone' => $r['customer_phone'], 'serviceName' => $r['service_name'] ?? 'Hizmet', 'staffName' => $r['staff_name'] ?? null, 'startAt' => $r['start_at'], 'startFmt' => $start->format('d.m.Y H:i'), 'createdAt' => $r['created_at'], 'customerStats' => $stats];
    }, $rows);

    wb_ok(['items' => $items, 'ts' => time()]);

} catch (Throwable $e) {
    error_log('[calendar/pending-notifications] ' . $e->getMessage());
    wb_err('Bildirimler yüklenemedi', 500, 'internal_error');
}