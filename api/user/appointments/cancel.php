<?php
declare(strict_types=1);
require_once __DIR__ . '/../_bootstrap.php';
wb_method('POST');

$userId = $user['user_id'];
$phone  = $user['phone'] ?? '';

$in     = wb_body();
$apptId = (int)($in['appointmentId'] ?? $in['id'] ?? 0);

if (!$apptId) {
    wb_err('appointmentId zorunlu', 400, 'missing_param');
}

try {
    if (!$phone) {
        $cStmt = $pdo->prepare("SELECT phone FROM customers WHERE user_id = ? LIMIT 1");
        $cStmt->execute([$userId]);
        $phone = $cStmt->fetchColumn() ?: '';
    }
    $phoneNorm = substr(preg_replace('/\D/', '', $phone), -10);

    $appt = null;

    if ($phoneNorm) {
        try {
            $s = $pdo->prepare("
                SELECT id, status, start_at, business_id
                FROM appointments
                WHERE id = ?
                  AND RIGHT(REGEXP_REPLACE(COALESCE(customer_phone,''), '[^0-9]', ''), 10) = ?
                LIMIT 1
            ");
            $s->execute([$apptId, $phoneNorm]);
            $appt = $s->fetch() ?: null;
        } catch (Throwable) {
            // MySQL 5.7 fallback
            $s2 = $pdo->prepare("
                SELECT id, status, start_at, business_id
                FROM appointments
                WHERE id = ?
                  AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(
                        COALESCE(customer_phone,''), '+',''),'-',''),' ',''),'(',''),')',''), 10) = ?
                LIMIT 1
            ");
            $s2->execute([$apptId, $phoneNorm]);
            $appt = $s2->fetch() ?: null;
        }
    }

    if (!$appt) {
        wb_err('Randevu bulunamadı', 404, 'not_found');
    }

    $prevStatus = strtolower($appt['status'] ?? '');

    if (in_array($prevStatus, ['cancelled', 'rejected', 'cancellation_requested'], true)) {
        wb_err('Bu randevu zaten iptal edilmiş veya iptal talebi bekliyor', 409, 'already_cancelled');
    }

    if (strtotime($appt['start_at']) <= time()) {
        wb_err('Geçmiş randevu iptal edilemez', 409, 'past_appointment');
    }

    $pdo->prepare("UPDATE appointments SET status='cancellation_requested' WHERE id=?")
        ->execute([$apptId]);

    try {
        $hasLogs = (bool)$pdo->query("SHOW TABLES LIKE 'appointment_logs'")->fetch();
        if ($hasLogs) {
            $pdo->prepare("
                INSERT INTO appointment_logs (appointment_id, action, prev_status, new_status, created_at)
                VALUES (?, 'cancellation_requested', ?, 'cancellation_requested', NOW())
            ")->execute([$apptId, $prevStatus]);
        }
    } catch (Throwable) {}

    // Email bildirimleri
    try {
        require_once __DIR__ . '/../../../api/_mailer.php';
        require_once __DIR__ . '/../../../api/_email_templates.php';

        $apptFull = $pdo->prepare("
            SELECT a.*, b.name AS business_name, b.address_line, b.city, b.district,
                   s.name AS service_name, st.name AS staff_name,
                   u.email AS owner_email
            FROM appointments a
            LEFT JOIN businesses b ON b.id = a.business_id
            LEFT JOIN services   s ON s.id = a.service_id
            LEFT JOIN staff     st ON st.id = a.staff_id
            LEFT JOIN users      u ON u.id = b.owner_id
            WHERE a.id = ? LIMIT 1
        ");
        $apptFull->execute([$apptId]);
        $row = $apptFull->fetch();

        if ($row) {
            $emailData = wbApptToEmailData($row, $pdo);
            $custEmail = $row['customer_email'] ?? '';
            $custName  = $row['customer_name']  ?? 'Müşteri';

            if ($custEmail && filter_var($custEmail, FILTER_VALIDATE_EMAIL)) {
                [$subj, $html] = wbEmailCancelRequested($emailData);
                wbMail($custEmail, $custName, $subj, $html);
            }
            if (!empty($emailData['ownerEmail'])) {
                [$subj, $html] = wbEmailCancelRequestBiz($emailData);
                wbMail($emailData['ownerEmail'], $emailData['bizName'], $subj, $html);
            }
        }
    } catch (Throwable $mailEx) {
        error_log('[user/appointments/cancel.php mail] ' . $mailEx->getMessage());
    }

    // SMS bildirimi
    try {
        require_once __DIR__ . '/../../../api/_sms.php';
        $custPhone = $appt['customer_phone'] ?? '';
        if ($custPhone) {
            queueSms(
                $pdo,
                $custPhone,
                'Webey: İptal talebiniz işletmeye iletildi. Onay veya red bildirimi alacaksınız.',
                'cancellation_requested',
                $apptId
            );
        }
    } catch (Throwable $smsEx) {
        error_log('[user/appointments/cancel.php sms] ' . $smsEx->getMessage());
    }

    wb_ok([
        'status'  => 'cancellation_requested',
        'message' => 'İptal talebiniz işletmeye iletildi. Onaylandığında bilgilendirileceksiniz.',
    ]);

} catch (Throwable $e) {
    error_log('[user/appointments/cancel.php] ' . $e->getMessage());
    wb_err('İşlem tamamlanamadı. Lütfen tekrar deneyin.', 500, 'internal_error');
}