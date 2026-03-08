<?php
declare(strict_types=1);
/**
 * api/appointments/status.php — Randevu durumu
 * GET ?id=&businessId=
 * Soft-auth: giriş gerekmez
 */

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('GET');

$apptId     = (int)($_GET['id'] ?? 0);
$businessId = (int)($_GET['businessId'] ?? 0);

if (!$apptId || !$businessId) {
    wb_err('id ve businessId zorunlu', 400, 'missing_param');
}

try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.attended, a.start_at, a.end_at,
               a.customer_name, a.customer_phone,
               s.name AS service_name, st.name AS staff_name
        FROM appointments a
        LEFT JOIN services s  ON s.id  = a.service_id
        LEFT JOIN staff    st ON st.id = a.staff_id
        WHERE a.id = ? AND a.business_id = ?
        LIMIT 1
    ");
    $stmt->execute([$apptId, $businessId]);
    $appt = $stmt->fetch();

    if (!$appt) { wb_err('Randevu bulunamadı', 404, 'not_found'); }

    $startDT  = new DateTime($appt['start_at']);
    $endDT    = new DateTime($appt['end_at']);
    $diffMin  = (int)round(($endDT->getTimestamp() - $startDT->getTimestamp()) / 60);

    $data = [
        'id'            => (string)$appt['id'],
        'status'        => $appt['status'],
        'attended'      => (bool)$appt['attended'],
        'startAt'       => $appt['start_at'],
        'endAt'         => $appt['end_at'],
        'startISO'      => $startDT->format('c'),
        'endISO'        => $endDT->format('c'),
        'durationMin'   => $diffMin,
        'totalMin'      => $diffMin,
        'customerName'  => $appt['customer_name'],
        'customerPhone' => $appt['customer_phone'],
        'serviceName'   => $appt['service_name'],
        'staffName'     => $appt['staff_name'],
    ];

    // Backward compat: data içinde appointment anahtarı da var
    wb_ok(array_merge($data, ['appointment' => $data]));

} catch (Throwable $e) {
    error_log('[appointments/status.php] ' . $e->getMessage());
    wb_err('Sunucu hatası.', 500, 'internal_error');
}