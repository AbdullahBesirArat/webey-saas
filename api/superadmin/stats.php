<?php
declare(strict_types=1);
/**
 * api/superadmin/stats.php
 * GET — Platform geneli istatistikler
 */

require_once __DIR__ . '/_bootstrap.php';
wb_method('GET');

function intval_vals(mixed $v): mixed {
    if (is_array($v)) return array_map('intval_vals', $v);
    if (is_numeric($v)) return $v + 0;
    return $v;
}

try {
    $users         = $pdo->query("SELECT COUNT(*) AS total, SUM(role='admin') AS admins, SUM(role='user') AS users, SUM(role='superadmin') AS superadmins, SUM(DATE(created_at)=CURDATE()) AS new_today, SUM(created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)) AS new_week, SUM(created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) AS new_month FROM users")->fetch(PDO::FETCH_ASSOC);
    $businesses    = $pdo->query("SELECT COUNT(*) AS total, SUM(status='active') AS active, SUM(status='suspended') AS suspended, SUM(status='pending') AS pending, SUM(DATE(created_at)=CURDATE()) AS new_today, SUM(created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)) AS new_week, SUM(created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) AS new_month FROM businesses")->fetch(PDO::FETCH_ASSOC);
    $appointments  = $pdo->query("SELECT COUNT(*) AS total, SUM(DATE(created_at)=CURDATE()) AS today, SUM(created_at>=DATE_SUB(NOW(),INTERVAL 7 DAY)) AS this_week, SUM(created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY)) AS this_month, SUM(status='approved') AS approved, SUM(status='pending') AS pending, SUM(status IN ('cancelled','rejected','declined')) AS cancelled FROM appointments")->fetch(PDO::FETCH_ASSOC);
    $subscriptions = $pdo->query("SELECT COUNT(*) AS total, SUM(status='active') AS active, SUM(status='trialing') AS trialing, SUM(status='cancelled') AS cancelled FROM subscriptions")->fetch(PDO::FETCH_ASSOC);
    $promos        = $pdo->query("SELECT COUNT(*) AS total, SUM(is_active=1) AS active, SUM(is_active=0) AS inactive, SUM(used_count) AS total_uses FROM promo_codes")->fetch(PDO::FETCH_ASSOC);

    $trendRows = $pdo->query("SELECT DATE(created_at) AS day, COUNT(*) AS cnt FROM users WHERE created_at>=DATE_SUB(NOW(),INTERVAL 30 DAY) GROUP BY DATE(created_at) ORDER BY day ASC")->fetchAll(PDO::FETCH_ASSOC);
    $trendMap  = array_column($trendRows, 'cnt', 'day');
    $trend = [];
    for ($i = 29; $i >= 0; $i--) {
        $day     = date('Y-m-d', strtotime("-{$i} days"));
        $trend[] = ['day' => $day, 'count' => isset($trendMap[$day]) ? (int)$trendMap[$day] : 0];
    }

    $recentAdmins = $pdo->query("SELECT u.id, u.email, u.name, u.created_at, u.last_login_at, b.name AS biz_name, b.status AS biz_status, s.status AS sub_status, s.plan AS sub_plan, s.end_date AS sub_end FROM users u LEFT JOIN businesses b ON b.owner_id=u.id LEFT JOIN subscriptions s ON s.user_id=u.id AND s.status IN ('active','trialing') WHERE u.role='admin' ORDER BY u.created_at DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);

    wb_ok([
        'users'         => array_map('intval_vals', $users),
        'businesses'    => array_map('intval_vals', $businesses),
        'appointments'  => array_map('intval_vals', $appointments),
        'subscriptions' => array_map('intval_vals', $subscriptions),
        'promos'        => array_map('intval_vals', $promos),
        'user_trend'    => $trend,
        'recent_admins' => $recentAdmins,
    ]);

} catch (Throwable $e) {
    error_log('[superadmin/stats] ' . $e->getMessage());
    wb_err('İstatistikler yüklenemedi', 500, 'internal_error');
}