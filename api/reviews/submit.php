<?php
declare(strict_types=1);
/**
 * api/reviews/submit.php
 * POST { appointment_id, rating, comment, staff_id?, review_target? }
 * AUTH: user
 */

require_once __DIR__ . '/../user/_bootstrap.php';
wb_method('POST');

$userId = $user['user_id'];
$in     = wb_body();

$appointmentId = (int)($in['appointment_id'] ?? 0);
$rating        = (int)($in['rating']         ?? 0);
$comment       = trim((string)($in['comment'] ?? ''));
$staffId       = isset($in['staff_id']) && $in['staff_id'] ? (int)$in['staff_id'] : null;
$reviewTarget  = in_array($in['review_target'] ?? '', ['staff','business'], true) ? $in['review_target'] : 'business';

if ($appointmentId <= 0) wb_err('Geçersiz appointment_id', 400, 'invalid_appointment_id');
if ($rating < 1 || $rating > 5) wb_err('1-5 arası puan gerekli', 400, 'invalid_rating');
if (mb_strlen($comment) > 1000) wb_err('Yorum 1000 karakteri geçemez', 400, 'comment_too_long');

try {
    $phones = [];
    $sp = preg_replace('/\D/', '', $_SESSION['user_phone'] ?? '');
    if ($sp) $phones[] = substr($sp, -10);
    $cRow = $pdo->prepare('SELECT phone FROM customers WHERE user_id = ? LIMIT 1');
    $cRow->execute([$userId]);
    $cp = preg_replace('/\D/', '', $cRow->fetchColumn() ?: '');
    if ($cp) { $t = substr($cp, -10); if (!in_array($t, $phones, true)) $phones[] = $t; }

    if (!$phones) wb_err('Telefon bilgisi bulunamadı', 403, 'no_phone');

    $ph = implode(',', array_fill(0, count($phones), '?'));
    $apptStmt = $pdo->prepare("SELECT a.id, a.business_id FROM appointments a WHERE a.id=? AND (a.status IN ('completed','approved') OR a.attended=1) AND RIGHT(REPLACE(REPLACE(REPLACE(COALESCE(a.customer_phone,''),'+',''),' ',''),'-',''), 10) IN ($ph) AND a.end_at<=NOW() LIMIT 1");
    $apptStmt->execute(array_merge([$appointmentId], $phones));
    $appt = $apptStmt->fetch();

    if (!$appt) wb_err('Bu randevu için yorum yapılamaz', 403, 'not_eligible');

    $bizId = (int)$appt['business_id'];

    $existsStmt = $pdo->prepare('SELECT id FROM reviews WHERE appointment_id = ? LIMIT 1');
    $existsStmt->execute([$appointmentId]);
    if ($existsStmt->fetchColumn()) wb_err('Bu randevu için zaten yorum yapılmış', 409, 'already_reviewed');

    try {
        $pdo->prepare('INSERT INTO reviews (business_id, user_id, appointment_id, rating, comment, staff_id, review_target) VALUES (?,?,?,?,?,?,?)')
            ->execute([$bizId, $userId, $appointmentId, $rating, $comment ?: null, $staffId, $reviewTarget]);
    } catch (Throwable) {
        $pdo->prepare('INSERT INTO reviews (business_id, user_id, appointment_id, rating, comment) VALUES (?,?,?,?,?)')
            ->execute([$bizId, $userId, $appointmentId, $rating, $comment ?: null]);
    }
    $reviewId = (int)$pdo->lastInsertId();

    $avgStmt = $pdo->prepare('SELECT ROUND(AVG(rating),1) AS avg_rating, COUNT(*) AS total FROM reviews WHERE business_id = ? AND is_visible = 1');
    $avgStmt->execute([$bizId]);
    $stats = $avgStmt->fetch();

    wb_ok(['review_id' => $reviewId, 'avg_rating' => (float)($stats['avg_rating']??0), 'total' => (int)($stats['total']??0)]);

} catch (Throwable $e) {
    error_log('[reviews/submit] ' . $e->getMessage());
    wb_err('Yorum gönderilemedi', 500, 'internal_error');
}