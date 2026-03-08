<?php
declare(strict_types=1);
/**
 * api/user/appointments/next.php
 * GET — müşterinin bir sonraki yaklaşan randevusunu döner
 * { id, businessId, businessName, serviceTitle, startISO, endISO, status }
 */
require_once __DIR__ . '/../_bootstrap.php';   // api/user/_bootstrap.php (user auth)
wb_method('GET');
wb_csrf_verify(false);

$userId = $user['user_id'];
$phone  = $user['phone'] ?? '';

try {
    if (!$phone) {
        $cStmt = $pdo->prepare("SELECT phone FROM customers WHERE user_id = ? LIMIT 1");
        $cStmt->execute([$userId]);
        $phone = $cStmt->fetchColumn() ?: '';
    }
    $phoneNorm = substr(preg_replace('/\D/', '', $phone), -10);

    $row = null;

    $stmt = $pdo->prepare("
        SELECT a.id, a.business_id, a.start_at, a.end_at, a.status,
               s.name AS service_name,
               b.name AS business_name, b.city, b.district
        FROM appointments a
        LEFT JOIN businesses b ON b.id = a.business_id
        LEFT JOIN services   s ON s.id = a.service_id
        WHERE a.customer_user_id = ?
          AND a.start_at > NOW()
          AND a.status NOT IN ('cancelled','rejected','cancellation_requested')
        ORDER BY a.start_at ASC
        LIMIT 1
    ");
    $stmt->execute([$userId]);
    $row = $stmt->fetch();

    if (!$row && $phoneNorm) {
        $stmt2 = $pdo->prepare("
            SELECT a.id, a.business_id, a.start_at, a.end_at, a.status,
                   s.name AS service_name,
                   b.name AS business_name, b.city, b.district
            FROM appointments a
            LEFT JOIN businesses b ON b.id = a.business_id
            LEFT JOIN services   s ON s.id = a.service_id
            WHERE RIGHT(REPLACE(REPLACE(REPLACE(COALESCE(a.customer_phone,''),'+',''),' ',''),'-',''), 10) = ?
              AND a.start_at > NOW()
              AND a.status NOT IN ('cancelled','rejected','cancellation_requested')
            ORDER BY a.start_at ASC
            LIMIT 1
        ");
        $stmt2->execute([$phoneNorm]);
        $row = $stmt2->fetch();
    }

    if (!$row) { wb_ok([]); }   // wb_ok(null) yerine wb_ok([]) — strict_types uyumlu

    wb_ok([
        'id'           => (string)$row['id'],
        'businessId'   => (string)$row['business_id'],
        'businessName' => $row['business_name'] ?? 'İşletme',
        'serviceTitle' => $row['service_name']  ?? 'Randevu',
        'startISO'     => $row['start_at'],
        'endISO'       => $row['end_at'],
        'status'       => $row['status'],
    ]);

} catch (Throwable $e) {
    error_log('[user/appointments/next.php] ' . $e->getMessage());
    wb_ok([]);   // wb_ok(null) yerine wb_ok([])
}