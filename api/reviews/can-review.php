<?php
declare(strict_types=1);
/**
 * api/reviews/can-review.php
 * GET ?business_id=123
 * AUTH: user — Tamamlanmış yorumsuz randevular
 */

require_once __DIR__ . '/../user/_bootstrap.php';
wb_method('GET');

$userId = $user['user_id'];
$bizId  = (int)($_GET['business_id'] ?? 0);
if ($bizId <= 0) wb_err('Geçersiz business_id', 400, 'invalid_business_id');

try {
    $phones = [];
    $sp = preg_replace('/\D/', '', $_SESSION['user_phone'] ?? '');
    if ($sp) $phones[] = substr($sp, -10);
    $cRow = $pdo->prepare('SELECT phone FROM customers WHERE user_id = ? LIMIT 1');
    $cRow->execute([$userId]);
    $cp = preg_replace('/\D/', '', $cRow->fetchColumn() ?: '');
    if ($cp) { $t = substr($cp, -10); if (!in_array($t, $phones, true)) $phones[] = $t; }

    if (!$phones) {
        wb_ok(['eligible' => [], 'already_reviewed' => []]);
    }

    $ph = implode(',', array_fill(0, count($phones), '?'));
    $apptStmt = $pdo->prepare("
        SELECT a.id, a.start_at, a.staff_id, a.customer_name,
               s.name AS service_name, st.name AS staff_name, st.photo_opt AS staff_photo
        FROM appointments a
        LEFT JOIN services s  ON s.id  = a.service_id
        LEFT JOIN staff    st ON st.id = a.staff_id
        WHERE a.business_id = ?
          AND (a.status IN ('completed','approved') OR a.attended = 1)
          AND a.end_at <= NOW()
          AND RIGHT(REPLACE(REPLACE(REPLACE(COALESCE(a.customer_phone,''),'+',''),' ',''),'-',''), 10) IN ($ph)
        ORDER BY a.start_at DESC LIMIT 20
    ");
    $apptStmt->execute(array_merge([$bizId], $phones));
    $appts = $apptStmt->fetchAll();

    if (!$appts) { wb_ok(['eligible' => [], 'already_reviewed' => []]); }

    $apptIds  = array_column($appts, 'id');
    $plh      = implode(',', array_fill(0, count($apptIds), '?'));
    $revStmt  = $pdo->prepare("SELECT appointment_id FROM reviews WHERE appointment_id IN ($plh)");
    $revStmt->execute($apptIds);
    $reviewed = array_flip(array_column($revStmt->fetchAll(), 'appointment_id'));

    $eligible = []; $already_reviewed = [];
    foreach ($appts as $a) {
        $entry = ['appointment_id' => (int)$a['id'], 'start_at' => $a['start_at'], 'service_name' => $a['service_name'], 'customer_name' => $a['customer_name'], 'staff_id' => $a['staff_id'] ? (int)$a['staff_id'] : null, 'staff_name' => $a['staff_name'] ?? null, 'staff_photo' => $a['staff_photo'] ?? null];
        if (isset($reviewed[$a['id']])) { $already_reviewed[] = $entry; } else { $eligible[] = $entry; }
    }

    wb_ok(['eligible' => $eligible, 'already_reviewed' => $already_reviewed]);

} catch (Throwable $e) {
    error_log('[reviews/can-review] ' . $e->getMessage());
    wb_err('Kontrol yapılamadı', 500, 'internal_error');
}