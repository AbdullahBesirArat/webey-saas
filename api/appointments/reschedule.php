<?php
declare(strict_types=1);
require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$bid = $user['business_id'];
if (!$bid) { wb_err('İşletme bulunamadı', 404, 'business_not_found'); }

$in             = wb_body();
$appointmentId  = $in['id'] ?? null;
$newStartAt     = $in['startAt'] ?? null;

if (!$appointmentId || !$newStartAt) {
    wb_err('id ve startAt zorunlu', 400, 'missing_param');
}

try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("
        SELECT id, staff_id, service_id, status
        FROM appointments
        WHERE id = ? AND business_id = ?
        LIMIT 1 FOR UPDATE
    ");
    $stmt->execute([$appointmentId, $bid]);
    $appt = $stmt->fetch();

    if (!$appt) {
        $pdo->rollBack();
        wb_err('Randevu bulunamadı', 404, 'not_found');
    }
    if (in_array($appt['status'], ['cancelled', 'no_show'], true)) {
        $pdo->rollBack();
        wb_err('İptal edilmiş randevu yeniden planlanamaz', 409, 'invalid_status');
    }

    $duration = 30;
    if (!empty($appt['service_id'])) {
        $svcStmt = $pdo->prepare("SELECT duration_min FROM services WHERE id = ? AND business_id = ? LIMIT 1");
        $svcStmt->execute([$appt['service_id'], $bid]);
        $svc = $svcStmt->fetch();
        if ($svc) $duration = (int)$svc['duration_min'];
    }

    $startDT   = new DateTime($newStartAt);
    $endDT     = (clone $startDT)->modify("+{$duration} minutes");
    $startAtDb = $startDT->format('Y-m-d H:i:s');
    $endAtDb   = $endDT->format('Y-m-d H:i:s');

    $cfStmt = $pdo->prepare("
        SELECT id FROM appointments
        WHERE business_id = ? AND staff_id = ? AND id != ?
          AND status NOT IN ('cancelled','no_show')
          AND start_at < ? AND end_at > ?
        FOR UPDATE
    ");
    $cfStmt->execute([$bid, $appt['staff_id'], $appointmentId, $endAtDb, $startAtDb]);
    if ($cfStmt->fetch()) {
        $pdo->rollBack();
        wb_err('Seçilen saat dolu', 409, 'time_conflict');
    }

    $pdo->prepare("
        UPDATE appointments SET start_at = ?, end_at = ?, updated_at = NOW()
        WHERE id = ? AND business_id = ?
    ")->execute([$startAtDb, $endAtDb, $appointmentId, $bid]);

    $pdo->commit();

    wb_ok([
        'id'      => (string)$appointmentId,
        'startAt' => $startAtDb,
        'endAt'   => $endAtDb,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[appointments/reschedule.php] ' . $e->getMessage());
    wb_err('Randevu güncellenemedi. Lütfen tekrar deneyin.', 500, 'internal_error');
}