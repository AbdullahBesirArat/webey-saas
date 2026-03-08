<?php
declare(strict_types=1);
/**
 * api/appointments/check-conflict.php
 * POST JSON: { uid, startISO, endISO }
 * PUBLIC — profile.js hasUserConflict() tarafından kullanılır
 * Döner: { ok:true, data: { hasConflict, bizName, businessId } }
 */

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('POST');

$data = wb_body();
$uid      = trim($data['uid']      ?? '');
$startISO = trim($data['startISO'] ?? '');
$endISO   = trim($data['endISO']   ?? '');

// uid veya tarih eksikse çakışma yok say
if (!$uid || !$startISO || !$endISO) {
    wb_ok(['hasConflict' => false, 'bizName' => '', 'businessId' => '']);
}

try {
    new DateTime($startISO);
    new DateTime($endISO);
} catch (Throwable) {
    wb_ok(['hasConflict' => false, 'bizName' => '', 'businessId' => '']);
}

// Not: appointments.user_uid kolonu şemada YOK — çakışma kontrolü devre dışı.
// Kolon eklendiğinde aşağıdaki sorgu aktifleştirilmeli.
wb_ok(['hasConflict' => false, 'bizName' => '', 'businessId' => '']);