<?php
declare(strict_types=1);
/**
 * api/appointments/cancel.php — Müşteri iptal talebi
 * POST JSON: { id, reason? }
 * status → 'cancellation_requested', admin onaylar
 */

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('POST');

$data   = wb_body();
$apptId = (int)($data['id'] ?? 0);
$reason = trim($data['reason'] ?? 'user_cancel');

if (!$apptId) { wb_err('Eksik parametre: id zorunlu', 400, 'missing_param'); }

try {
    $stmt = $pdo->prepare("
        SELECT a.id, a.status, a.business_id, a.customer_phone, a.customer_name,
               a.start_at, a.end_at, s.name AS service_name
        FROM appointments a
        LEFT JOIN services s ON s.id = a.service_id
        WHERE a.id = ? LIMIT 1
    ");
    $stmt->execute([$apptId]);
    $appt = $stmt->fetch();

    if (!$appt) { wb_err('Randevu bulunamadı', 404, 'not_found'); }

    if (!in_array($appt['status'], ['pending','approved','confirmed'], true)) {
        wb_err('Bu randevu iptal edilemez (durum: ' . $appt['status'] . ')', 400, 'invalid_status');
    }

    // Oturumluysa ek doğrulama
    if (!empty($_SESSION['user_id'])) {
        try {
            $userCheck = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND customer_user_id = ?");
            $userCheck->execute([$apptId, (int)$_SESSION['user_id']]);
            if (!$userCheck->fetch() && !empty($_SESSION['user_phone'])) {
                $phoneNorm  = preg_replace('/[^\d]/', '', $_SESSION['user_phone']);
                $phoneCheck = $pdo->prepare("
                    SELECT id FROM appointments
                    WHERE id = ? AND RIGHT(REPLACE(REPLACE(REPLACE(COALESCE(customer_phone,''),'+',''),' ',''),'-',''), 10) = RIGHT(?, 10)
                ");
                $phoneCheck->execute([$apptId, $phoneNorm]);
                if (!$phoneCheck->fetch()) {
                    wb_err('Bu randevuya erişim yetkiniz yok', 403, 'forbidden');
                }
            }
        } catch (Throwable) {}
    }

    $pdo->prepare("UPDATE appointments SET status='cancellation_requested' WHERE id=?")
        ->execute([$apptId]);

    $businessId = (int)$appt['business_id'];

    // Log
    try {
        $pdo->prepare("
            INSERT INTO appointment_logs (appointment_id, action, new_status, created_at)
            VALUES (?, 'cancellation_requested', 'cancellation_requested', NOW())
        ")->execute([$apptId]);
    } catch (Throwable) {}

    // Bildirim kaydı
    try {
        $pdo->prepare("
            INSERT IGNORE INTO notifications
              (business_id, appointment_id, type, customer_name, customer_phone, service_name, appointment_start, result, created_at)
            VALUES (?, ?, 'cancellation', ?, ?, ?, ?, 'pending', NOW())
        ")->execute([
            $businessId, $apptId,
            $appt['customer_name'],
            $appt['customer_phone'] ?? null,
            $appt['service_name']   ?? null,
            $appt['start_at']       ?? null,
        ]);
    } catch (Throwable $nErr) { error_log('[appointments/cancel.php notification] ' . $nErr->getMessage()); }

    wb_ok([
        'id'         => (string)$apptId,
        'status'     => 'cancellation_requested',
        'businessId' => (string)$businessId,
        'message'    => 'İptal talebiniz alındı. İşletme onayladığında randevunuz iptal edilecektir.',
    ]);

} catch (Throwable $e) {
    error_log('[appointments/cancel.php] ' . $e->getMessage());
    wb_err('İşlem tamamlanamadı.', 500, 'internal_error');
}