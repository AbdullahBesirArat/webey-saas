<?php
// api/admin/customers.php
// GET ?search=&sort=visits|last_visit|no_show&order=desc&page=1
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wb_method('GET');

$sess  = wb_auth_admin();
$bizId = $sess['business_id'] ?? null;

if (!$bizId) {
    wb_err('İşletme bulunamadı', 404, 'business_not_found');
}

$search  = trim($_GET['search'] ?? '');
$sort    = in_array($_GET['sort'] ?? '', ['visits','last_visit','no_show','attended','cancelled'])
    ? $_GET['sort'] : 'visits';
$order   = ($_GET['order'] ?? 'desc') === 'asc' ? 'ASC' : 'DESC';
$page    = max(1, (int)($_GET['page'] ?? 1));
$limit   = 30;
$offset  = ($page - 1) * $limit;

$sortCol = match($sort) {
    'last_visit' => 'last_visit',
    'no_show'    => 'no_show_count',
    'attended'   => 'attended_count',
    'cancelled'  => 'cancelled_count',
    default      => 'total_visits',
};

try {
    $searchWhere = '';
    $params = [$bizId];
    if ($search !== '') {
        $searchWhere = "AND (customer_name LIKE ? OR RIGHT(REPLACE(REPLACE(COALESCE(customer_phone,''),'+',''),' ',''),10) LIKE ?)";
        $params[] = "%{$search}%";
        $params[] = "%{$search}%";
    }

    $countStmt = $pdo->prepare("
        SELECT COUNT(*) FROM (
            SELECT RIGHT(REPLACE(REPLACE(COALESCE(customer_phone,''),'+',''),' ',''),10) AS phone_key
            FROM appointments
            WHERE business_id = ? AND customer_name != '[DOLU]' $searchWhere
            GROUP BY phone_key
        ) t
    ");
    $countStmt->execute($params);
    $totalCount = (int)$countStmt->fetchColumn();

    $listStmt = $pdo->prepare("
        SELECT
            MAX(customer_name)  AS customer_name,
            RIGHT(REPLACE(REPLACE(COALESCE(customer_phone,''),'+',''),' ',''),10) AS phone_key,
            MAX(customer_phone) AS customer_phone,
            COUNT(*)            AS total_visits,
            SUM(CASE WHEN status IN ('completed','approved') OR attended = 1 THEN 1 ELSE 0 END) AS attended_count,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END)                                  AS no_show_count,
            SUM(CASE WHEN status IN ('cancelled','rejected','declined') THEN 1 ELSE 0 END)        AS cancelled_count,
            MIN(start_at)       AS first_visit,
            MAX(start_at)       AS last_visit
        FROM appointments
        WHERE business_id = ? AND customer_name != '[DOLU]' $searchWhere
        GROUP BY phone_key
        ORDER BY $sortCol $order
        LIMIT $limit OFFSET $offset
    ");
    $listStmt->execute($params);
    $rows = $listStmt->fetchAll();

    $customers = array_map(function($r) use ($pdo, $bizId) {
        $phone10    = $r['phone_key'];
        $topSvcStmt = $pdo->prepare("
            SELECT COALESCE(s.name,'Genel') AS svc_name, COUNT(*) AS cnt
            FROM appointments a
            LEFT JOIN services s ON s.id = a.service_id
            WHERE a.business_id = ?
              AND RIGHT(REPLACE(REPLACE(COALESCE(a.customer_phone,''),'+',''),' ',''),10) = ?
              AND a.status NOT IN ('cancelled','rejected','declined')
            GROUP BY svc_name ORDER BY cnt DESC LIMIT 1
        ");
        $topSvcStmt->execute([$bizId, $phone10]);
        $topSvc = $topSvcStmt->fetch();

        $total     = (int)$r['total_visits'];
        $attended  = (int)$r['attended_count'];
        $showRate  = $total > 0 ? round($attended / $total * 100) : 0;

        return [
            'name'        => $r['customer_name'],
            'phone'       => $r['customer_phone'],
            'phoneKey'    => $phone10,
            'totalVisits' => $total,
            'attended'    => $attended,
            'noShow'      => (int)$r['no_show_count'],
            'cancelled'   => (int)$r['cancelled_count'],
            'showRate'    => $showRate,
            'topService'  => $topSvc ? $topSvc['svc_name'] : null,
            'firstVisit'  => $r['first_visit'] ? date('d.m.Y', strtotime($r['first_visit'])) : null,
            'lastVisit'   => $r['last_visit']  ? date('d.m.Y', strtotime($r['last_visit']))  : null,
        ];
    }, $rows);

    wb_ok([
        'customers' => $customers,
        'total'     => $totalCount,
        'page'      => $page,
        'pageCount' => (int)ceil($totalCount / $limit),
    ]);

} catch (Throwable $e) {
    error_log('[customers] ' . $e->getMessage());
    wb_err('Sunucu hatası', 500, 'internal_error');
}