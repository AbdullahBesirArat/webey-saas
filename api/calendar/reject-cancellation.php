<?php
declare(strict_types=1);
/**
 * api/calendar/reject-cancellation.php
 * POST { id } — İptal talebini reddet → randevu eski statüsüne döner
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$bid = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');

$in = wb_body();
$apptId = isset($in['id']) ? (int)$in['id'] : 0;
if (!$apptId) wb_err('id zorunlu', 400, 'missing_id');

try {
    $check = $pdo->prepare("SELECT id FROM appointments WHERE id = ? AND business_id = ? AND status = 'cancellation_requested'");
    $check->execute([$apptId, $bid]);
    if (!$check->fetch()) wb_err('Randevu bulunamadı veya iptal talebi durumunda değil', 404, 'not_found');

    // Önceki statüyü logdan bul
    $prevStatus = 'approved';
    try {
        $logChk = $pdo->prepare("SELECT prev_status FROM appointment_logs WHERE appointment_id = ? AND action = 'cancellation_requested' ORDER BY id DESC LIMIT 1");
        $logChk->execute([$apptId]);
        $logRow = $logChk->fetch();
        if ($logRow && in_array($logRow['prev_status'], ['approved','pending','confirmed'], true)) {
            $prevStatus = $logRow['prev_status'];
        }
    } catch (Throwable) {}

    $pdo->prepare('UPDATE appointments SET status = ? WHERE id = ? AND business_id = ?')
        ->execute([$prevStatus, $apptId, $bid]);

    // SMS
    try {
        require_once __DIR__ . '/../../api/_sms.php';
        $aRow = $pdo->prepare('SELECT customer_phone FROM appointments WHERE id=? LIMIT 1');
        $aRow->execute([$apptId]);
        $aData = $aRow->fetch();
        if (!empty($aData['customer_phone'])) {
            queueSms($pdo, $aData['customer_phone'], 'Webey: İptal talebiniz reddedildi. Randevunuz devam etmektedir.', 'cancellation_rejected', $apptId);
        }
    } catch (Throwable $smsEx) { error_log('[reject-cancellation sms] ' . $smsEx->getMessage()); }

    wb_ok(['id' => (string)$apptId, 'status' => $prevStatus, 'message' => 'İptal talebi reddedildi.']);

} catch (Throwable $e) {
    error_log('[calendar/reject-cancellation] ' . $e->getMessage());
    wb_err('İşlem tamamlanamadı', 500, 'internal_error');
}