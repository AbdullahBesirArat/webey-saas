<?php
declare(strict_types=1);
/**
 * api/calendar/approve-cancellation.php
 * POST { id } — İptal talebini onayla → status = 'cancelled'
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$bid = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');

$in = wb_body();
$apptId = isset($in['id']) ? (int)$in['id'] : 0;
if (!$apptId) wb_err('id zorunlu', 400, 'missing_id');

try {
    $check = $pdo->prepare("SELECT id, customer_name, customer_phone, customer_email FROM appointments WHERE id = ? AND business_id = ? AND status = 'cancellation_requested'");
    $check->execute([$apptId, $bid]);
    $appt = $check->fetch();

    if (!$appt) {
        $exists = $pdo->prepare('SELECT status FROM appointments WHERE id = ? AND business_id = ?');
        $exists->execute([$apptId, $bid]);
        $row = $exists->fetch();
        if ($row && $row['status'] === 'cancelled') {
            wb_ok(['id' => (string)$apptId, 'status' => 'cancelled', 'message' => 'Zaten iptal edilmiş.']);
        }
        wb_err('Randevu bulunamadı veya iptal talebi durumunda değil', 404, 'not_found');
    }

    $pdo->prepare("UPDATE appointments SET status = 'cancelled' WHERE id = ? AND business_id = ?")
        ->execute([$apptId, $bid]);

    // Email
    try {
        require_once __DIR__ . '/../../api/_mailer.php';
        require_once __DIR__ . '/../../api/_email_templates.php';
        $fStmt = $pdo->prepare("SELECT a.*, b.name AS business_name, u.email AS owner_email FROM appointments a LEFT JOIN businesses b ON b.id=a.business_id LEFT JOIN users u ON u.id=b.owner_id WHERE a.id=? LIMIT 1");
        $fStmt->execute([$apptId]);
        $row = $fStmt->fetch();
        if ($row && !empty($row['customer_email'])) {
            [$subj, $html] = wbEmailApptCancelled(wbApptToEmailData($row, $pdo));
            wbMail($row['customer_email'], $row['customer_name'] ?? 'Müşteri', $subj, $html);
        }
    } catch (Throwable $mailEx) { error_log('[approve-cancellation mail] ' . $mailEx->getMessage()); }

    // SMS
    try {
        require_once __DIR__ . '/../../api/_sms.php';
        if (!empty($appt['customer_phone'])) {
            queueSms($pdo, $appt['customer_phone'], 'Webey: İptal talebiniz onaylandı. Randevunuz iptal edilmiştir.', 'cancelled', $apptId);
        }
    } catch (Throwable $smsEx) { error_log('[approve-cancellation sms] ' . $smsEx->getMessage()); }

    wb_ok(['id' => (string)$apptId, 'status' => 'cancelled', 'message' => 'İptal onaylandı.']);

} catch (Throwable $e) {
    error_log('[calendar/approve-cancellation] ' . $e->getMessage());
    wb_err('İşlem tamamlanamadı', 500, 'internal_error');
}