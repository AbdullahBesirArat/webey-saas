<?php
// api/billing/cron_sub_reminders.php
// ─────────────────────────────────────────────────────────────────────
// Abonelik Bitiş Hatırlatma Cron Job'u
//
// Crontab (her 30 dakikada bir):
//   */30 * * * * php /var/www/html/api/billing/cron_sub_reminders.php >> /var/log/webey_sub_reminders.log 2>&1
//
// 3 tetikleme noktası:
//   expiry_3d  → bitiş 71-73 saat arası (≈3 gün kala)
//   expiry_1d  → bitiş 23-25 saat arası (≈1 gün kala)
//   expired    → bitiş son 60 dakika içinde geçmiş
//
// Her subscription × remind_type × channel kombinasyonu
// subscription_reminders tablosunda UNIQUE key ile korunur — 2 kez gitmez.
// ─────────────────────────────────────────────────────────────────────
declare(strict_types=1);

if (PHP_SAPI !== 'cli' && ($_SERVER['REMOTE_ADDR'] ?? '') !== '127.0.0.1') {
    http_response_code(403); exit('Forbidden');
}

require __DIR__ . '/../../db.php';
require __DIR__ . '/../_mailer.php';
require __DIR__ . '/../_email_templates.php';
require __DIR__ . '/../_sms.php';

$now    = new DateTimeImmutable('now', new DateTimeZone('Europe/Istanbul'));
$counts = ['notification' => 0, 'email' => 0, 'sms' => 0];
$errors = 0;

echo "[" . $now->format('Y-m-d H:i:s') . "] Abonelik hatırlatma cron başladı\n";

// ─────────────────────────────────────────────────────────────────────
// YARDIMCILAR
// ─────────────────────────────────────────────────────────────────────

/** Daha önce gönderilmediyse subscription_reminders'a ekle → true döner */
function shouldSend(PDO $pdo, int $subId, string $remindType, string $channel): bool {
    try {
        $pdo->prepare("
            INSERT INTO subscription_reminders (subscription_id, remind_type, channel, status, sent_at)
            VALUES (?, ?, ?, 'sent', NOW())
        ")->execute([$subId, $remindType, $channel]);
        return true;
    } catch (Throwable) {
        return false; // UNIQUE ihlali = zaten gönderilmiş
    }
}

/** notifications tablosuna işletme bildirimi ekle */
function insertSubNotification(
    PDO $pdo, int $bizId, string $type,
    string $planLabel, string $endDate, string $remindType
): void {
    // customer_name alanını başlık, service_name alanını açıklama olarak kullanıyoruz
    $title = match($remindType) {
        'expiry_3d' => "Aboneliğiniz 3 gün içinde bitiyor",
        'expiry_1d' => "Aboneliğiniz yarın bitiyor!",
        'expired'   => "Aboneliğiniz sona erdi",
    };
    $desc = match($remindType) {
        'expiry_3d' => "{$planLabel} — {$endDate} tarihinde bitiyor. Yayında kalmak için plan yenileyin.",
        'expiry_1d' => "{$planLabel} — Yarın ({$endDate}) bitiyor! Hemen plan yenileyin.",
        'expired'   => "{$planLabel} aboneliğiniz sona erdi. Yeni plan almadan dükkanınız yayında görünmez.",
    };
    $pdo->prepare("
        INSERT INTO notifications
            (business_id, type, customer_name, service_name, result, is_read, is_deleted, created_at)
        VALUES (?, ?, ?, ?, 'info', 0, 0, NOW())
    ")->execute([$bizId, $type, $title, $desc]);
}

/** Email kuyruğuna ekle */
function queueSubEmail(PDO $pdo, string $toEmail, string $toName, string $subject, string $html): void {
    $pdo->prepare("
        INSERT INTO email_queue (to_email, to_name, subject, body_html, status, created_at)
        VALUES (?, ?, ?, ?, 'pending', NOW())
    ")->execute([$toEmail, $toName, $subject, $html]);
}

/** Abonelik hatırlatma emaili HTML */
function buildSubEmail(string $bizName, string $ownerName, string $planLabel, string $endDate, string $remindType, string $planUrl): string {
    [$headline, $bodyHtml, $btnText, $color] = match($remindType) {
        'expiry_3d' => [
            '📅 Aboneliğiniz 3 Gün İçinde Bitiyor',
            "İşletmenizin Webey aboneliği <strong>3 gün sonra ({$endDate})</strong> sona erecek.<br><br>
             Müşterilerinize kesintisiz hizmet verebilmek için planınızı şimdiden yenilemenizi öneririz.",
            '🔄 Planı Yenile',
            '#0ea5b3',
        ],
        'expiry_1d' => [
            '⏳ Aboneliğiniz Yarın Bitiyor',
            "İşletmenizin Webey aboneliği <strong>yarın ({$endDate})</strong> sona erecek.<br><br>
             Dükkanınızın yayında kalmaya devam etmesi için planınızı hemen yenilemenizi öneririz.",
            '🔄 Planı Yenile',
            '#f59e0b',
        ],
        default => [
            '⚠️ Aboneliğiniz Sona Erdi',
            "İşletmenizin Webey üzerindeki aboneliği <strong>{$endDate}</strong> tarihinde sona erdi.<br><br>
             Dükkanınızın müşterilerinize <strong>görünmeye devam etmesi</strong> ve yeni randevular alabilmesi için
             bir plan seçmeniz gerekmektedir.",
            '🚀 Hemen Plan Al',
            '#ef4444',
        ],
    };

    return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head><meta charset="UTF-8"/><meta name="viewport" content="width=device-width,initial-scale=1"/>
<title>Webey — Abonelik Bildirimi</title></head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:Inter,Arial,sans-serif;">
<table width="100%" cellpadding="0" cellspacing="0" style="background:#f4f6f8;">
<tr><td align="center" style="padding:32px 16px;">
<table width="100%" cellpadding="0" cellspacing="0" style="max-width:540px;">

  <!-- Logo -->
  <tr><td align="center" style="padding-bottom:20px;">
    <span style="font-size:26px;font-weight:900;color:#0ea5b3;letter-spacing:-1px;">webey</span>
  </td></tr>

  <!-- Kart -->
  <tr><td style="background:#fff;border-radius:20px;overflow:hidden;box-shadow:0 4px 24px rgba(0,0,0,.08);">

    <!-- Üst bant -->
    <div style="background:{$color};padding:28px 32px;text-align:center;">
      <h1 style="color:#fff;margin:0;font-size:21px;font-weight:700;">{$headline}</h1>
    </div>

    <!-- İçerik -->
    <div style="padding:32px 36px;">
      <p style="color:#374151;font-size:15px;margin:0 0 14px;">
        Merhaba <strong>{$ownerName}</strong>,
      </p>
      <p style="color:#4b5563;font-size:14px;line-height:1.7;margin:0 0 20px;">{$bodyHtml}</p>

      <!-- Kart -->
      <div style="background:#f8f9ff;border-radius:12px;padding:16px 20px;border-left:4px solid {$color};margin-bottom:24px;">
        <p style="margin:0;color:#111;font-size:14px;line-height:1.8;">
          🏪 <strong>{$bizName}</strong><br>
          📦 Plan: <strong>{$planLabel}</strong><br>
          📅 Bitiş: <strong>{$endDate}</strong>
        </p>
      </div>

      <!-- CTA -->
      <div style="text-align:center;">
        <a href="{$planUrl}" style="display:inline-block;padding:14px 36px;background:{$color};
           color:#fff;border-radius:10px;text-decoration:none;font-size:15px;font-weight:700;">
          {$btnText}
        </a>
      </div>

      <p style="color:#9ca3af;font-size:12px;text-align:center;margin:24px 0 0;">
        Sorularınız için <a href="mailto:destek@webey.com.tr" style="color:#0ea5b3;text-decoration:none;">destek@webey.com.tr</a>
      </p>
    </div>

  </td></tr>

  <!-- Footer -->
  <tr><td style="padding:20px 0;text-align:center;color:#9ca3af;font-size:12px;">
    © 2026 Webey · Tüm hakları saklıdır.
  </td></tr>

</table>
</td></tr>
</table>
</body></html>
HTML;
}

/** SMS metinleri (kısa + TR karakter) */
function smsSubText(string $bizName, string $endDate, string $remindType): string {
    return match($remindType) {
        'expiry_3d' => "Webey: {$bizName} aboneliginiz {$endDate} tarihinde bitiyor. Yayinda kalmak icin plan yenileyin: webey.com.tr",
        'expiry_1d' => "Webey: {$bizName} aboneliginiz YARIN ({$endDate}) bitiyor! Hemen plan alin: webey.com.tr",
        default     => "Webey: {$bizName} aboneliginiz sona erdi. Dukkaninizin yayinda kalmasi icin plan alin: webey.com.tr",
    };
}

// ─────────────────────────────────────────────────────────────────────
// PLAN LABELLERİ
// ─────────────────────────────────────────────────────────────────────
$PLAN_LABELS = [
    'monthly_1' => '1 Aylık Plan',
    'monthly_3' => '3 Aylık Plan',
    'monthly_6' => '6 Aylık Plan',
    'yearly_1'  => '1 Yıllık Plan',
    'yearly_2'  => '2 Yıllık Plan',
];

$cfg     = require __DIR__ . '/../_email_config.php';
$planUrl = rtrim($cfg['site_url'] ?? 'https://webey.com.tr', '/') . '/admin-profile.html#billing';

// ─────────────────────────────────────────────────────────────────────
// 3 PENCERE: 3 gün kala / 1 gün kala / süresi dolmuş
// ─────────────────────────────────────────────────────────────────────
$windows = [
    'expiry_3d' => "s.end_date BETWEEN DATE_ADD(NOW(), INTERVAL 71 HOUR) AND DATE_ADD(NOW(), INTERVAL 73 HOUR)",
    'expiry_1d' => "s.end_date BETWEEN DATE_ADD(NOW(), INTERVAL 23 HOUR) AND DATE_ADD(NOW(), INTERVAL 25 HOUR)",
    'expired'   => "s.end_date BETWEEN DATE_SUB(NOW(), INTERVAL 60 MINUTE) AND NOW()",
];

foreach ($windows as $remindType => $whereClause) {
    $stmt = $pdo->query("
        SELECT
            s.id          AS sub_id,
            s.plan,
            s.end_date,
            u.id          AS user_id,
            u.email       AS user_email,
            u.name        AS user_name,
            b.id          AS biz_id,
            b.name        AS biz_name,
            b.phone       AS biz_phone
        FROM subscriptions s
        JOIN users u ON u.id = s.user_id
        LEFT JOIN businesses b ON b.owner_id = u.id
        WHERE s.status IN ('active','trialing')
          AND {$whereClause}
        LIMIT 200
    ");
    $subs = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $label = match($remindType) { 'expiry_3d'=>'3 gün kala', 'expiry_1d'=>'1 gün kala', default=>'süresi doldu' };
    echo "  [{$label}]: " . count($subs) . " abonelik\n";

    foreach ($subs as $sub) {
        $subId     = (int)$sub['sub_id'];
        $bizId     = (int)($sub['biz_id'] ?? 0);
        $planLabel = $PLAN_LABELS[$sub['plan']] ?? $sub['plan'];
        $endDate   = (new DateTimeImmutable($sub['end_date']))->format('d.m.Y');
        $ownerName = $sub['user_name'] ?: ($sub['biz_name'] ?: 'İşletme Sahibi');
        $bizName   = $sub['biz_name'] ?: 'İşletmeniz';

        $notifType = match($remindType) {
            'expiry_3d' => 'subscription_expiry_3d',
            'expiry_1d' => 'subscription_expiry_1d',
            default     => 'subscription_expired',
        };

        // ── 1. BİLDİRİM PANELİ ──────────────────────────────────
        if ($bizId && shouldSend($pdo, $subId, $remindType, 'notification')) {
            try {
                insertSubNotification($pdo, $bizId, $notifType, $planLabel, $endDate, $remindType);
                $counts['notification']++;
                echo "    [panel] sub#{$subId} biz#{$bizId}\n";
            } catch (Throwable $e) {
                $errors++;
                error_log("[cron_sub][notif] sub#{$subId} " . $e->getMessage());
            }
        }

        // ── 2. EMAIL ─────────────────────────────────────────────
        if (!empty($sub['user_email']) && shouldSend($pdo, $subId, $remindType, 'email')) {
            try {
                $subject = match($remindType) {
                    'expiry_3d' => "📅 {$bizName} — Aboneliğiniz 3 Gün İçinde Bitiyor",
                    'expiry_1d' => "⏳ {$bizName} — Aboneliğiniz Yarın Bitiyor!",
                    default     => "⚠️ {$bizName} — Aboneliğiniz Sona Erdi",
                };
                $html = buildSubEmail($bizName, $ownerName, $planLabel, $endDate, $remindType, $planUrl);
                queueSubEmail($pdo, $sub['user_email'], $ownerName, $subject, $html);
                $counts['email']++;
                echo "    [email] sub#{$subId} → {$sub['user_email']}\n";
            } catch (Throwable $e) {
                $errors++;
                error_log("[cron_sub][email] sub#{$subId} " . $e->getMessage());
            }
        }

        // ── 3. SMS ───────────────────────────────────────────────
        $phone = trim($sub['biz_phone'] ?? '');
        if ($phone && shouldSend($pdo, $subId, $remindType, 'sms')) {
            try {
                $smsText = smsSubText($bizName, $endDate, $remindType);
                queueSms($pdo, $phone, $smsText, 'sub_reminder', null, null);
                $counts['sms']++;
                echo "    [sms]   sub#{$subId} → {$phone}\n";
            } catch (Throwable $e) {
                $errors++;
                error_log("[cron_sub][sms] sub#{$subId} " . $e->getMessage());
            }
        }
    }
}

// ─────────────────────────────────────────────────────────────────────
// Biten abonelikleri 'expired' yap (60 dk geçmişten daha eski)
// ─────────────────────────────────────────────────────────────────────
try {
    $expired = $pdo->exec("
        UPDATE subscriptions
        SET status = 'expired'
        WHERE status IN ('active','trialing')
          AND end_date < DATE_SUB(NOW(), INTERVAL 60 MINUTE)
    ");
    if ($expired > 0) echo "  [expire] {$expired} abonelik 'expired' yapıldı\n";
} catch (Throwable $e) {
    error_log("[cron_sub][expire] " . $e->getMessage());
}

echo "[" . date('H:i:s') . "] Bitti — " .
     "panel:{$counts['notification']} email:{$counts['email']} sms:{$counts['sms']} hata:{$errors}\n\n";