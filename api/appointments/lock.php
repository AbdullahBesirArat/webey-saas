<?php
// api/appointments/lock.php
// Geçici slot kilitleme — müşteri takvimde bir slot seçtiğinde çağrılır.
//
// POST JSON: {
//   businessId  : int,
//   staffId     : int|"any",
//   dayStr      : "YYYY-MM-DD",
//   startMin    : int,   -- günün başından itibaren dakika (ör. 600 = 10:00)
//   durationMin : int
// }
//
// Döner:
//   { ok:true,  token:"...", expiresAt:"...", expiresInSec:300 }  — kilit alındı
//   { ok:false, code:"conflict",  ... }                           — slot zaten dolu/kilitli
//   { ok:true,  renewed:true, ... }                               — aynı token yenilendi
//
// Kilit süresi: 5 dakika (LOCK_TTL_SEC sabiti)

declare(strict_types=1);

require_once __DIR__ . '/../_public_bootstrap.php';
wb_method('POST');

// ── Sabitler ─────────────────────────────────────────────────────────────────
const LOCK_TTL_SEC = 120; // 5 dakika

// ── IP Tabanlı Rate Limiting ──────────────────────────────────────────────────
$ip      = trim(explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0')[0]);
$rateKey = 'lock:' . md5($ip);
try {
    $pdo->prepare('DELETE FROM api_rate_limits WHERE cache_key = ? AND expires_at < NOW()')
        ->execute([$rateKey]);
    $rStmt = $pdo->prepare('SELECT hits FROM api_rate_limits WHERE cache_key = ? LIMIT 1');
    $rStmt->execute([$rateKey]);
    $hits = (int)($rStmt->fetchColumn() ?: 0);
    if ($hits >= 20) { // 1 dakikada 20 kilit denemesi
        wb_err('Çok fazla istek gönderildi. Lütfen bekleyin.', 429, 'rate_limited');
    }
    if ($hits === 0) {
        $pdo->prepare('INSERT INTO api_rate_limits (cache_key, hits, expires_at) VALUES (?, 1, DATE_ADD(NOW(), INTERVAL 60 SECOND))')
            ->execute([$rateKey]);
    } else {
        $pdo->prepare('UPDATE api_rate_limits SET hits = hits + 1 WHERE cache_key = ?')
            ->execute([$rateKey]);
    }
} catch (Throwable) { /* Tablo yoksa devam et */ }
// ─────────────────────────────────────────────────────────────────────────────

$data = wb_body();
if (!is_array($data)) { wb_err('Geçersiz JSON', 400); }

$businessId  = (int)($data['businessId']  ?? 0);
$staffIdRaw  = trim((string)($data['staffId'] ?? 'any'));
$dayStr      = trim($data['dayStr']       ?? '');
$startMin    = (int)($data['startMin']    ?? -1);
$durationMin = (int)($data['durationMin'] ?? 0);

// İstemcinin önceki token'ı varsa yenileme işlemi için kullanılır
$clientToken = trim($data['token'] ?? '');

// Validasyon
if (!$businessId || !$dayStr || $startMin < 0 || $durationMin <= 0) {
    wb_err('businessId, dayStr, startMin, durationMin zorunlu', 400);
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dayStr)) {
    wb_err('dayStr YYYY-MM-DD formatında olmalı', 400);
}

$staffId = ($staffIdRaw && $staffIdRaw !== 'any' && is_numeric($staffIdRaw))
         ? (int)$staffIdRaw
         : null;

$endMin = $startMin + $durationMin;

// ── Abonelik kontrolü ─────────────────────────────────────────────────────────
require_once __DIR__ . '/../_subscription_check.php';
$subStatus = getBusinessSubscriptionStatus($pdo, $businessId);
if (!$subStatus['active']) {
    wb_err('Bu işletme şu anda randevu kabul edemiyor.', 403, 'subscription_expired');
}
// ─────────────────────────────────────────────────────────────────────────────

// startMin / endMin → datetime string
$startH   = (int)floor($startMin / 60);
$startM   = $startMin % 60;
$endH     = (int)floor($endMin / 60);
$endM     = $endMin % 60;
$startStr = sprintf('%s %02d:%02d:00', $dayStr, $startH, $startM);
$endStr   = sprintf('%s %02d:%02d:00', $dayStr, $endH, $endM);

try {
    $pdo->beginTransaction();

    // ── 1. Süresi dolan kilitleri temizle ─────────────────────────────────────
    $pdo->prepare('DELETE FROM slot_locks WHERE expires_at < NOW()')
        ->execute();

    // ── 2. Gerçek randevularda çakışma var mı? ────────────────────────────────
    $apptSql = "SELECT id FROM appointments
                WHERE business_id = ?
                  AND status NOT IN ('cancelled','no_show','rejected','declined')
                  AND start_at < ?
                  AND end_at   > ?";
    $apptParams = [$businessId, $endStr, $startStr];

    if ($staffId) {
        $apptSql    .= ' AND staff_id = ?';
        $apptParams[] = $staffId;
    }

    $apptStmt = $pdo->prepare($apptSql);
    $apptStmt->execute($apptParams);
    if ($apptStmt->fetch()) {
        $pdo->rollBack();
        wb_err('Bu saat dolu.', 409, 'conflict');
    }

    // ── 3. Aktif kilit çakışması var mı? ─────────────────────────────────────
    //    Kilitleri dakika aralığı olarak kontrol et: start_min < endMin AND (start_min + duration_min) > startMin
    $lockSql = "SELECT id, lock_token FROM slot_locks
                WHERE business_id = ?
                  AND day_str = ?
                  AND start_min < ?
                  AND (start_min + duration_min) > ?
                  AND expires_at >= NOW()";
    $lockParams = [$businessId, $dayStr, $endMin, $startMin];

    if ($staffId) {
        // Sadece aynı personelin kilitleri — staff_id=NULL kilitler bu personeli bloke etmez
        $lockSql    .= ' AND staff_id = ?';
        $lockParams[] = $staffId;
    } else {
        // 'any' seçilmişse personelsiz kilitler
        $lockSql .= ' AND staff_id IS NULL';
    }

    $lockStmt = $pdo->prepare($lockSql);
    $lockStmt->execute($lockParams);
    $existingLock = $lockStmt->fetch();

    if ($existingLock) {
        // Aynı token ise kilidi yenile (müşteri sayfayı yenilediyse)
        if ($clientToken && $existingLock['lock_token'] === $clientToken) {
            $pdo->prepare('UPDATE slot_locks SET expires_at = DATE_ADD(NOW(), INTERVAL ? SECOND) WHERE lock_token = ?')
                ->execute([LOCK_TTL_SEC, $clientToken]);
            $pdo->commit();

            $expiresAt = (new DateTimeImmutable('now'))->modify('+' . LOCK_TTL_SEC . ' seconds');
            wb_ok([
                'renewed'      => true,
                'token'        => $clientToken,
                'expiresAt'    => $expiresAt->format('c'),
                'expiresInSec' => LOCK_TTL_SEC,
            ]);
        }

        $pdo->rollBack();
        wb_err('Bu saat şu an başka biri tarafından seçildi. Lütfen farklı bir saat deneyin.', 409, 'locked');
    }

    // ── 4. Yeni kilit oluştur ─────────────────────────────────────────────────
    $lockToken = bin2hex(random_bytes(24)); // 48 karakter hex token

    $pdo->prepare(
        'INSERT INTO slot_locks
            (business_id, staff_id, day_str, start_min, duration_min, lock_token, expires_at)
         VALUES (?, ?, ?, ?, ?, ?, DATE_ADD(NOW(), INTERVAL ? SECOND))'
    )->execute([
        $businessId,
        $staffId,
        $dayStr,
        $startMin,
        $durationMin,
        $lockToken,
        LOCK_TTL_SEC,
    ]);

    $pdo->commit();

    $expiresAt = (new DateTimeImmutable('now'))->modify('+' . LOCK_TTL_SEC . ' seconds');

    wb_ok([
        'token'        => $lockToken,
        'expiresAt'    => $expiresAt->format('c'),
        'expiresInSec' => LOCK_TTL_SEC,
    ]);

} catch (Throwable $e) {
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log('[lock.php] ' . $e->getMessage());
    wb_err('Slot kilitlenemedi. Lütfen tekrar deneyin.', 500);
}