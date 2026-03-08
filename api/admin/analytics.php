<?php
// api/admin/analytics.php
// GET ?period=7|30|90  →  Dashboard verileri
declare(strict_types=1);

require_once __DIR__ . '/_bootstrap.php';

wb_method('GET');

$sess   = wb_auth_admin();
$bizId  = $sess['business_id'] ?? null;

if (!$bizId) {
    wb_err('İşletme bulunamadı', 404, 'business_not_found');
}

$period = in_array((int)($_GET['period'] ?? 30), [7, 30, 90]) ? (int)$_GET['period'] : 30;

try {
    /* 1. KPI */
    $kpiStmt = $pdo->prepare("
        SELECT
            SUM(CASE WHEN start_at >= NOW() - INTERVAL ? DAY
                      AND status NOT IN ('cancelled','rejected','declined','no_show')
                THEN 1 ELSE 0 END)                              AS appt_current,
            SUM(CASE WHEN start_at >= NOW() - INTERVAL ? DAY
                      AND start_at < NOW() - INTERVAL ? DAY
                      AND status NOT IN ('cancelled','rejected','declined','no_show')
                THEN 1 ELSE 0 END)                              AS appt_prev,
            SUM(CASE WHEN start_at >= NOW() - INTERVAL ? DAY
                      AND (status = 'completed' OR attended = 1)
                THEN 1 ELSE 0 END)                              AS completed_current,
            SUM(CASE WHEN start_at >= NOW() - INTERVAL ? DAY
                      AND status IN ('cancelled','rejected','declined')
                THEN 1 ELSE 0 END)                              AS cancelled_current,
            COUNT(DISTINCT CASE WHEN start_at >= NOW() - INTERVAL ? DAY
                      AND status NOT IN ('cancelled','rejected','declined','no_show')
                THEN RIGHT(REPLACE(REPLACE(COALESCE(customer_phone,''),'+',''),' ',''), 10)
                END)                                            AS unique_customers,
            SUM(CASE WHEN start_at >= NOW() AND start_at <= NOW() + INTERVAL 7 DAY
                      AND status NOT IN ('cancelled','rejected','declined')
                THEN 1 ELSE 0 END)                              AS upcoming_7d,
            SUM(CASE WHEN status = 'pending' AND start_at >= NOW()
                THEN 1 ELSE 0 END)                              AS pending_approval
        FROM appointments
        WHERE business_id = ?
    ");
    $kpiStmt->execute([$period, $period*2, $period, $period, $period, $period, $bizId]);
    $kpi = $kpiStmt->fetch();

    /* 2. GELİR TAHMİNİ */
    $revenueStmt = $pdo->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN a.start_at >= NOW() - INTERVAL ? DAY
                               AND a.status NOT IN ('cancelled','rejected','declined','no_show')
                          THEN s.price ELSE 0 END), 0) AS revenue_current,
            COALESCE(SUM(CASE WHEN a.start_at >= NOW() - INTERVAL ? DAY
                               AND a.start_at < NOW() - INTERVAL ? DAY
                               AND a.status NOT IN ('cancelled','rejected','declined','no_show')
                          THEN s.price ELSE 0 END), 0) AS revenue_prev
        FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id
        WHERE a.business_id = ?
    ");
    $revenueStmt->execute([$period, $period*2, $period, $bizId]);
    $revenue = $revenueStmt->fetch();

    /* 3. GÜNLÜK TREND */
    $trendStmt = $pdo->prepare("
        SELECT
            DATE(start_at)                                       AS day,
            COUNT(*)                                             AS total,
            SUM(status NOT IN ('cancelled','rejected','declined','no_show')) AS active
        FROM appointments
        WHERE business_id = ?
          AND start_at >= NOW() - INTERVAL ? DAY
          AND start_at <= NOW() + INTERVAL 1 DAY
        GROUP BY DATE(start_at)
        ORDER BY day ASC
    ");
    $trendStmt->execute([$bizId, $period]);
    $trendRows = $trendStmt->fetchAll();

    /* 4. EN ÇOK TERCİH EDİLEN HİZMETLER */
    $topServicesStmt = $pdo->prepare("
        SELECT
            COALESCE(s.name, a.notes, 'Genel') AS service_name,
            COUNT(*)                            AS count,
            COALESCE(SUM(s.price), 0)           AS total_revenue
        FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id
        WHERE a.business_id = ?
          AND a.start_at >= NOW() - INTERVAL ? DAY
          AND a.status NOT IN ('cancelled','rejected','declined','no_show')
        GROUP BY service_name
        ORDER BY count DESC
        LIMIT 8
    ");
    $topServicesStmt->execute([$bizId, $period]);
    $topServices = $topServicesStmt->fetchAll();

    /* 5. PERSONEL PERFORMANSI */
    $staffPerfStmt = $pdo->prepare("
        SELECT
            COALESCE(st.name, 'Atanmamış')      AS staff_name,
            COUNT(a.id)                          AS total,
            SUM(a.status NOT IN ('cancelled','rejected','declined','no_show')) AS active,
            COALESCE(SUM(s.price), 0)            AS revenue
        FROM appointments a
        LEFT JOIN staff    st ON st.id = a.staff_id
        LEFT JOIN services  s ON  s.id = a.service_id
        WHERE a.business_id = ?
          AND a.start_at >= NOW() - INTERVAL ? DAY
        GROUP BY a.staff_id, staff_name
        ORDER BY active DESC
        LIMIT 6
    ");
    $staffPerfStmt->execute([$bizId, $period]);
    $staffPerf = $staffPerfStmt->fetchAll();

    /* 6. EN AKTİF MÜŞTERİLER */
    $topCustStmt = $pdo->prepare("
        SELECT
            customer_name,
            RIGHT(REPLACE(REPLACE(COALESCE(customer_phone,''),'+',''),' ',''), 10) AS phone_tail,
            COUNT(*)                                                                AS visit_count,
            MAX(start_at)                                                           AS last_visit
        FROM appointments
        WHERE business_id = ?
          AND start_at >= NOW() - INTERVAL ? DAY
          AND status NOT IN ('cancelled','rejected','declined','no_show')
          AND customer_name != '[DOLU]'
        GROUP BY customer_name, phone_tail
        ORDER BY visit_count DESC
        LIMIT 6
    ");
    $topCustStmt->execute([$bizId, $period]);
    $topCustomers = $topCustStmt->fetchAll();

    /* 7. PUAN & YORUMLAR */
    $reviewStmt = $pdo->prepare("
        SELECT
            ROUND(AVG(rating), 1) AS avg_rating,
            COUNT(*)              AS total_reviews,
            SUM(rating = 5) AS r5, SUM(rating = 4) AS r4, SUM(rating = 3) AS r3,
            SUM(rating = 2) AS r2, SUM(rating = 1) AS r1
        FROM reviews
        WHERE business_id = ? AND is_visible = 1
    ");
    $reviewStmt->execute([$bizId]);
    $reviewStats = $reviewStmt->fetch();

    /* 8. DOLULUK ORANI */
    $monday = date('Y-m-d', strtotime('monday this week'));
    $hoursStmt = $pdo->prepare("
        SELECT SUM(TIMESTAMPDIFF(MINUTE, CONCAT(?, ' ', open_time), CONCAT(?, ' ', close_time))) / 60 AS total_work_hours
        FROM business_hours
        WHERE business_id = ? AND is_open = 1
    ");
    $hoursStmt->execute([$monday, $monday, $bizId]);
    $workHours = (float)($hoursStmt->fetchColumn() ?: 0);

    $usedStmt = $pdo->prepare("
        SELECT COALESCE(SUM(TIMESTAMPDIFF(MINUTE, start_at, end_at)), 0) AS used_min
        FROM appointments
        WHERE business_id = ?
          AND start_at >= DATE_FORMAT(NOW(), '%Y-%m-%d') - INTERVAL WEEKDAY(NOW()) DAY
          AND start_at < DATE_FORMAT(NOW(), '%Y-%m-%d') - INTERVAL WEEKDAY(NOW()) DAY + INTERVAL 7 DAY
          AND status NOT IN ('cancelled','rejected','declined','no_show')
    ");
    $usedStmt->execute([$bizId]);
    $usedMin        = (float)($usedStmt->fetchColumn() ?: 0);
    $occupancyRate  = $workHours > 0 ? min(100, round(($usedMin / 60) / $workHours * 100)) : 0;

    /* 9. SAATLİK YOĞUNLUK */
    $heatStmt = $pdo->prepare("
        SELECT DAYOFWEEK(start_at) - 1 AS dow, HOUR(start_at) AS hour, COUNT(*) AS cnt
        FROM appointments
        WHERE business_id = ?
          AND start_at >= NOW() - INTERVAL ? DAY
          AND status NOT IN ('cancelled','rejected','declined','no_show')
        GROUP BY dow, hour
        ORDER BY dow, hour
    ");
    $heatStmt->execute([$bizId, max($period, 30)]);
    $heatRows = $heatStmt->fetchAll();

    $heatmap = [];
    for ($d = 0; $d < 7; $d++) {
        for ($h = 0; $h < 24; $h++) $heatmap[$d][$h] = 0;
    }
    foreach ($heatRows as $hr) {
        $heatmap[(int)$hr['dow']][(int)$hr['hour']] = (int)$hr['cnt'];
    }

    /* TREND BOŞ GÜNLERİ DOLDUR */
    $trendMap = [];
    foreach ($trendRows as $r) $trendMap[$r['day']] = (int)$r['active'];
    $trendFull = [];
    for ($i = $period - 1; $i >= 0; $i--) {
        $day = date('Y-m-d', strtotime("-{$i} days"));
        $trendFull[] = ['day' => $day, 'count' => $trendMap[$day] ?? 0];
    }

    $calcChange = fn($cur, $prev) => $prev == 0 ? ($cur > 0 ? 100 : 0) : (int)round(($cur - $prev) / $prev * 100);

    wb_ok([
        'period' => $period,
        'kpi' => [
            'appointments'   => (int)($kpi['appt_current']      ?? 0),
            'appt_change'    => $calcChange($kpi['appt_current'] ?? 0, $kpi['appt_prev'] ?? 0),
            'completed'      => (int)($kpi['completed_current'] ?? 0),
            'cancelled'      => (int)($kpi['cancelled_current'] ?? 0),
            'customers'      => (int)($kpi['unique_customers']  ?? 0),
            'upcoming_7d'    => (int)($kpi['upcoming_7d']       ?? 0),
            'pending'        => (int)($kpi['pending_approval']  ?? 0),
            'revenue'        => (float)($revenue['revenue_current'] ?? 0),
            'rev_change'     => $calcChange($revenue['revenue_current'] ?? 0, $revenue['revenue_prev'] ?? 0),
            'occupancy_rate' => $occupancyRate,
        ],
        'trend'          => $trendFull,
        'top_services'   => array_map(fn($r) => [
            'name'    => $r['service_name'],
            'count'   => (int)$r['count'],
            'revenue' => (float)$r['total_revenue'],
        ], $topServices),
        'staff_perf'     => array_map(fn($r) => [
            'name'    => $r['staff_name'],
            'total'   => (int)$r['total'],
            'active'  => (int)$r['active'],
            'revenue' => (float)$r['revenue'],
        ], $staffPerf),
        'top_customers'  => array_map(fn($r) => [
            'name'       => $r['customer_name'],
            'visits'     => (int)$r['visit_count'],
            'last_visit' => $r['last_visit'],
        ], $topCustomers),
        'reviews' => [
            'avg'   => (float)($reviewStats['avg_rating']  ?? 0),
            'total' => (int)($reviewStats['total_reviews'] ?? 0),
            'r5'    => (int)($reviewStats['r5'] ?? 0),
            'r4'    => (int)($reviewStats['r4'] ?? 0),
            'r3'    => (int)($reviewStats['r3'] ?? 0),
            'r2'    => (int)($reviewStats['r2'] ?? 0),
            'r1'    => (int)($reviewStats['r1'] ?? 0),
        ],
        'heatmap' => $heatmap,
    ]);

} catch (Throwable $e) {
    error_log('[analytics] ' . $e->getMessage());
    wb_err('Sunucu hatası', 500, 'internal_error');
}