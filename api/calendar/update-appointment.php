<?php
declare(strict_types=1);
/**
 * api/calendar/update-appointment.php
 * POST { id, status?, attended? } — randevu durumu güncelle (takvim görünümünden)
 * Admin auth gerekli
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$businessId = (int)($_SESSION['business_id'] ?? 0);
if (!$businessId) { wb_err('İşletme bulunamadı', 403, 'no_business'); }

$data = wb_body();

$appointmentId = $data['id'] ?? $data['appointmentId'] ?? null;
if (!$appointmentId) { wb_err('Missing appointment id', 400, 'missing_param'); }

$status   = $data['status']   ?? null;
$attended = array_key_exists('attended', $data) ? $data['attended'] : null;

$fields = [];
$params = [];

if ($status !== null) {
    $allowedStatuses = ['pending', 'approved', 'cancelled', 'no_show', 'cancellation_requested'];
    if (!in_array($status, $allowedStatuses, true)) {
        wb_err('Invalid status value', 400, 'invalid_status');
    }
    $fields[] = "status = ?";
    $params[] = $status;
    if ($status === 'no_show') { $fields[] = "attended = 0"; }
}

if ($attended !== null) {
    $fields[] = "attended = ?";
    $params[] = $attended ? 1 : 0;
}

if (!$fields) { wb_ok(['updated' => false, 'message' => 'Nothing to update']); }

$params[] = $appointmentId;
$params[] = $businessId;

$pdo->prepare("UPDATE appointments SET " . implode(', ', $fields) . " WHERE id = ? AND business_id = ?")
    ->execute($params);

// ── Kullanıcı Bildirimi ─────────────────────────────────────────
if ($status !== null && in_array($status, ['approved', 'cancelled', 'rejected'], true)) {
    try {
        $apptRow = $pdo->prepare("
            SELECT a.customer_user_id, a.customer_phone, a.start_at,
                   b.name AS business_name, s.name AS service_name
            FROM appointments a
            LEFT JOIN businesses b ON b.id = a.business_id
            LEFT JOIN services   s ON s.id = a.service_id
            WHERE a.id = ? AND a.business_id = ? LIMIT 1
        ");
        $apptRow->execute([$appointmentId, $businessId]);
        $row = $apptRow->fetch();

        if ($row) {
            $custUserId = $row['customer_user_id'] ?? null;
            if (!$custUserId && !empty($row['customer_phone'])) {
                $phoneStmt = $pdo->prepare("SELECT user_id FROM customers WHERE phone = ? LIMIT 1");
                $phoneStmt->execute([$row['customer_phone']]);
                $phoneRow = $phoneStmt->fetch();
                if ($phoneRow) $custUserId = (int)$phoneRow['user_id'];
            }

            if ($custUserId) {
                $dt      = new DateTimeImmutable($row['start_at'], new DateTimeZone('Europe/Istanbul'));
                $bizName = $row['business_name'] ?? 'İşletme';
                $svcName = $row['service_name'] ?? '';
                $dateFmt = $dt->format('d.m.Y H:i');

                $title   = $status === 'approved' ? '✅ Randevunuz Onaylandı' : '❌ Randevunuz İptal Edildi';
                $type    = match($status) { 'approved' => 'appt_approved', 'rejected' => 'appt_rejected', default => 'appt_cancelled' };
                $message = "{$bizName} — {$dateFmt}" . ($svcName ? " · {$svcName}" : '');

                $pdo->prepare("INSERT INTO user_notifications (user_id, appointment_id, type, title, message, business_name) VALUES (?, ?, ?, ?, ?, ?)")
                    ->execute([(int)$custUserId, (int)$appointmentId, $type, $title, $message, $bizName]);
            }
        }
    } catch (Throwable $e) {
        error_log('[update-appointment user_notif] ' . $e->getMessage());
    }
}

wb_ok(['updated' => true, 'id' => (string)$appointmentId, 'status' => $status, 'attended' => $attended]);