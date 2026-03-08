<?php
// api/_email_templates.php
// ─────────────────────────────────────────────────────────────────────
// Webey Email HTML Şablonları
// Kullanım:
//   require_once __DIR__ . '/_email_templates.php';
//   [$subject, $html] = wbEmailApptConfirm([...]);
// ─────────────────────────────────────────────────────────────────────
declare(strict_types=1);

/** Ortak HTML wrapper */
function _wbEmailWrap(string $title, string $content, string $preheader = ''): string {
    $cfg       = require __DIR__ . '/_email_config.php';
    $brand     = $cfg['brand_color'];
    $siteUrl   = rtrim($cfg['site_url'], '/');
    $year      = date('Y');

    return <<<HTML
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8"/>
<meta name="viewport" content="width=device-width, initial-scale=1.0"/>
<meta http-equiv="X-UA-Compatible" content="IE=edge"/>
<title>{$title}</title>
<!--[if mso]><style>td,th,p,a,span,div{font-family:Arial,sans-serif!important}</style><![endif]-->
</head>
<body style="margin:0;padding:0;background:#f4f6f8;font-family:'Inter',Arial,sans-serif;">
<!-- Preheader -->
<div style="display:none;max-height:0;overflow:hidden;color:transparent;">{$preheader}&nbsp;&zwnj;</div>

<table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="background:#f4f6f8;">
  <tr><td align="center" style="padding:32px 16px;">

    <table width="100%" cellpadding="0" cellspacing="0" role="presentation" style="max-width:560px;">

      <!-- LOGO -->
      <tr><td align="center" style="padding-bottom:24px;">
        <a href="{$siteUrl}" style="text-decoration:none;">
          <span style="font-size:26px;font-weight:900;color:{$brand};letter-spacing:-1px;">Webey</span>
        </a>
      </td></tr>

      <!-- KART -->
      <tr><td style="background:#ffffff;border-radius:20px;padding:40px 36px;box-shadow:0 4px 24px rgba(0,0,0,.07);">
        {$content}
      </td></tr>

      <!-- FOOTER -->
      <tr><td style="padding:24px 0;text-align:center;color:#9ca3af;font-size:12px;line-height:1.6;">
        <p style="margin:0 0 6px;">Bu emaili <a href="{$siteUrl}" style="color:{$brand};text-decoration:none;">Webey</a> üzerinden gerçekleşen bir işlem tetikledi.</p>
        <p style="margin:0;">© {$year} Webey · Tüm hakları saklıdır.</p>
      </td></tr>

    </table>
  </td></tr>
</table>
</body>
</html>
HTML;
}

/** Ortak randevu detay bloğu */
function _wbApptBlock(array $d): string {
    $cfg   = require __DIR__ . '/_email_config.php';
    $brand = $cfg['brand_color'];

    $rows = '';
    $map = [
        'İşletme'  => $d['bizName']    ?? '',
        'Hizmet'   => $d['service']    ?? '',
        'Personel' => $d['staffName']  ?? '',
        'Tarih'    => $d['dateLabel']  ?? '',
        'Saat'     => $d['timeLabel']  ?? '',
        'Adres'    => $d['address']    ?? '',
    ];
    foreach ($map as $label => $val) {
        if (!$val) continue;
        $rows .= <<<ROW
<tr>
  <td style="padding:10px 0;color:#6b7280;font-size:13.5px;white-space:nowrap;width:90px;">{$label}</td>
  <td style="padding:10px 0 10px 12px;color:#111827;font-size:13.5px;font-weight:600;">{$val}</td>
</tr>
<tr><td colspan="2" style="border-top:1px solid #f3f4f6;"></td></tr>
ROW;
    }

    return <<<HTML
<table width="100%" cellpadding="0" cellspacing="0" role="presentation"
       style="background:#f9fafb;border-radius:12px;padding:8px 20px;margin:20px 0;">
  <tbody>{$rows}</tbody>
</table>
HTML;
}

/** Büyük renkli başlık ikonu */
function _wbIcon(string $emoji, string $color = '#19a0b6'): string {
    return <<<HTML
<div style="text-align:center;margin-bottom:24px;">
  <div style="display:inline-block;width:64px;height:64px;border-radius:50%;
              background:{$color}1a;line-height:64px;font-size:32px;text-align:center;">
    {$emoji}
  </div>
</div>
HTML;
}

/** CTA butonu */
function _wbBtn(string $text, string $url, string $color = '#19a0b6'): string {
    return <<<HTML
<div style="text-align:center;margin:28px 0 8px;">
  <a href="{$url}" style="display:inline-block;padding:14px 32px;background:{$color};color:#fff;
     border-radius:12px;text-decoration:none;font-size:15px;font-weight:700;letter-spacing:-.2px;">
    {$text}
  </a>
</div>
HTML;
}

// ═══════════════════════════════════════════════════════════
//  MÜŞTERİ EMAİLLERİ
// ═══════════════════════════════════════════════════════════

/**
 * Müşteriye: Randevu alındı (pending/approved)
 * @param array $d [custName, bizName, service, staffName, dateLabel, timeLabel, address, siteUrl, apptId]
 * @return [subject, html]
 */
function wbEmailApptConfirm(array $d): array {
    $cfg     = require __DIR__ . '/_email_config.php';
    $siteUrl = rtrim($d['siteUrl'] ?? $cfg['site_url'], '/');
    $name    = htmlspecialchars($d['custName'] ?? 'Müşteri', ENT_QUOTES);
    $status  = ($d['status'] ?? 'pending') === 'approved' ? 'onaylandı' : 'alındı';
    $isPending = ($d['status'] ?? 'pending') === 'pending';

    $statusBadge = $isPending
        ? '<span style="background:#fef3c7;color:#92400e;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;">Onay Bekliyor</span>'
        : '<span style="background:#dcfce7;color:#166534;padding:3px 10px;border-radius:6px;font-size:12px;font-weight:700;">✓ Onaylandı</span>';

    $content  = _wbIcon('📅', '#19a0b6');
    $content .= "<h2 style='margin:0 0 6px;font-size:22px;color:#111827;text-align:center;'>Randevunuz {$status}!</h2>";
    $content .= "<p style='text-align:center;margin:0 0 4px;'>{$statusBadge}</p>";
    $content .= "<p style='color:#6b7280;font-size:14.5px;margin:16px 0 0;'>Merhaba <strong style='color:#111827;'>{$name}</strong>,</p>";
    $content .= "<p style='color:#374151;font-size:14.5px;line-height:1.6;'>Randevunuz başarıyla " . ($isPending ? 'sisteme kaydedildi. İşletme en kısa sürede onaylayacak.' : 'onaylandı. Belirlenen saatte hazır olun!') . "</p>";
    $content .= _wbApptBlock($d);

    if ($isPending) {
        $content .= "<p style='color:#6b7280;font-size:13px;text-align:center;'>Durum değişikliklerinde size email göndereceğiz.</p>";
    }

    $content .= _wbBtn('Randevularımı Gör', $siteUrl . '/user-profile.html#appointments');

    $subject = "Randevunuz " . ($isPending ? 'alındı' : 'onaylandı') . " – " . ($d['bizName'] ?? 'Webey');
    return [$subject, _wbEmailWrap($subject, $content, 'Randevunuz ' . $status . ': ' . ($d['bizName'] ?? ''))];
}

/**
 * Müşteriye: Randevu onaylandı
 */
function wbEmailApptApproved(array $d): array {
    $cfg     = require __DIR__ . '/_email_config.php';
    $siteUrl = rtrim($d['siteUrl'] ?? $cfg['site_url'], '/');
    $name    = htmlspecialchars($d['custName'] ?? 'Müşteri', ENT_QUOTES);
    $biz     = htmlspecialchars($d['bizName']  ?? 'İşletme', ENT_QUOTES);

    $content  = _wbIcon('✅', '#16a34a');
    $content .= "<h2 style='margin:0 0 16px;font-size:22px;color:#111827;text-align:center;'>Randevunuz Onaylandı! 🎉</h2>";
    $content .= "<p style='color:#374151;font-size:14.5px;line-height:1.6;'>Merhaba <strong>{$name}</strong>, <strong>{$biz}</strong> randevunuzu onayladı!</p>";
    $content .= _wbApptBlock($d);
    $content .= "<p style='color:#6b7280;font-size:13px;text-align:center;margin-top:8px;'>Randevunuzu iptal etmeniz gerekirse profilinizden yapabilirsiniz.</p>";
    $content .= _wbBtn('Randevularımı Gör', $siteUrl . '/user-profile.html#appointments', '#16a34a');

    $subject = "Randevunuz onaylandı – {$biz}";
    return [$subject, _wbEmailWrap($subject, $content, "Harika haber! {$biz} randevunuzu onayladı.")];
}

/**
 * Müşteriye: Randevu iptal edildi / reddedildi
 */
function wbEmailApptCancelled(array $d): array {
    $cfg     = require __DIR__ . '/_email_config.php';
    $siteUrl = rtrim($d['siteUrl'] ?? $cfg['site_url'], '/');
    $name    = htmlspecialchars($d['custName'] ?? 'Müşteri', ENT_QUOTES);
    $biz     = htmlspecialchars($d['bizName']  ?? 'İşletme', ENT_QUOTES);
    $reason  = !empty($d['reason']) ? '<p style="background:#fef2f2;border-left:4px solid #ef4444;padding:12px 16px;border-radius:0 8px 8px 0;margin:16px 0;font-size:13.5px;color:#7f1d1d;">' . htmlspecialchars($d['reason'], ENT_QUOTES) . '</p>' : '';

    $content  = _wbIcon('❌', '#ef4444');
    $content .= "<h2 style='margin:0 0 16px;font-size:22px;color:#111827;text-align:center;'>Randevunuz İptal Edildi</h2>";
    $content .= "<p style='color:#374151;font-size:14.5px;line-height:1.6;'>Merhaba <strong>{$name}</strong>, maalesef <strong>{$biz}</strong> randevunuz iptal edildi.</p>";
    $content .= $reason;
    $content .= _wbApptBlock($d);
    $content .= "<p style='color:#6b7280;font-size:13.5px;line-height:1.6;'>Yeni bir randevu almak ister misiniz?</p>";
    $content .= _wbBtn('Yeni Randevu Al', $siteUrl . '/profile.html?id=' . ($d['bizId'] ?? ''), '#ef4444');

    $subject = "Randevunuz iptal edildi – {$biz}";
    return [$subject, _wbEmailWrap($subject, $content, "Randevunuz iptal edildi.")];
}

/**
 * Müşteriye: İptal talebin alındı
 */
function wbEmailCancelRequested(array $d): array {
    $cfg     = require __DIR__ . '/_email_config.php';
    $siteUrl = rtrim($d['siteUrl'] ?? $cfg['site_url'], '/');
    $name    = htmlspecialchars($d['custName'] ?? 'Müşteri', ENT_QUOTES);

    $content  = _wbIcon('🕐', '#f59e0b');
    $content .= "<h2 style='margin:0 0 16px;font-size:22px;color:#111827;text-align:center;'>İptal Talebiniz Alındı</h2>";
    $content .= "<p style='color:#374151;font-size:14.5px;line-height:1.6;'>Merhaba <strong>{$name}</strong>, iptal talebiniz işletmeye iletildi. Onaylandığında size bilgi vereceğiz.</p>";
    $content .= _wbApptBlock($d);
    $content .= _wbBtn('Randevularımı Gör', $siteUrl . '/user-profile.html#appointments', '#f59e0b');

    $subject = 'İptal talebiniz alındı – Webey';
    return [$subject, _wbEmailWrap($subject, $content, 'İptal talebiniz işleme alındı.')];
}

// ═══════════════════════════════════════════════════════════
//  İŞLETME EMAİLLERİ
// ═══════════════════════════════════════════════════════════

/**
 * İşletmeye: Yeni randevu bildirimi
 */
function wbEmailNewApptBiz(array $d): array {
    $cfg     = require __DIR__ . '/_email_config.php';
    $siteUrl = rtrim($d['siteUrl'] ?? $cfg['site_url'], '/');
    $cust    = htmlspecialchars($d['custName']  ?? 'Müşteri', ENT_QUOTES);
    $phone   = htmlspecialchars($d['custPhone'] ?? '', ENT_QUOTES);

    $content  = _wbIcon('🔔', '#19a0b6');
    $content .= "<h2 style='margin:0 0 16px;font-size:22px;color:#111827;text-align:center;'>Yeni Randevu Talebi!</h2>";
    $content .= "<p style='color:#374151;font-size:14.5px;line-height:1.6;'><strong>{$cust}</strong>" . ($phone ? " (☎ {$phone})" : '') . " adlı müşteri randevu talep etti.</p>";
    $content .= _wbApptBlock($d);
    $content .= _wbBtn('Randevuyu Onayla', $siteUrl . '/calendar.html', '#19a0b6');
    $content .= "<p style='color:#9ca3af;font-size:12px;text-align:center;'>Takvim sayfasından randevuyu onaylayabilir veya reddedebilirsiniz.</p>";

    $subject = "Yeni randevu talebi – {$cust}";
    return [$subject, _wbEmailWrap($subject, $content, "Yeni randevu: {$cust}")];
}

/**
 * İşletmeye: Müşteri iptal talebi
 */
function wbEmailCancelRequestBiz(array $d): array {
    $cfg     = require __DIR__ . '/_email_config.php';
    $siteUrl = rtrim($d['siteUrl'] ?? $cfg['site_url'], '/');
    $cust    = htmlspecialchars($d['custName'] ?? 'Müşteri', ENT_QUOTES);

    $content  = _wbIcon('⚠️', '#f59e0b');
    $content .= "<h2 style='margin:0 0 16px;font-size:22px;color:#111827;text-align:center;'>İptal Talebi Geldi</h2>";
    $content .= "<p style='color:#374151;font-size:14.5px;line-height:1.6;'><strong>{$cust}</strong> adlı müşteri randevusunu iptal etmek istiyor. Takvimden onaylayabilir veya reddedebilirsiniz.</p>";
    $content .= _wbApptBlock($d);
    $content .= _wbBtn('Takvime Git', $siteUrl . '/calendar.html', '#f59e0b');

    $subject = "İptal talebi – {$cust}";
    return [$subject, _wbEmailWrap($subject, $content, "İptal talebi: {$cust}")];
}

// ═══════════════════════════════════════════════════════════
//  YARDIMCI: DB'den randevu bilgilerini email veri yapısına çevir
// ═══════════════════════════════════════════════════════════

/**
 * Appointments tablosundan gelen ham satırı email veri yapısına çevir
 * @param array $row DB'den gelen satır
 * @param PDO   $pdo
 * @return array
 */
function wbApptToEmailData(array $row, PDO $pdo): array {
    $cfg     = require __DIR__ . '/_email_config.php';
    $siteUrl = $cfg['site_url'];

    // Tarih / saat formatlama
    $startAt = $row['start_at'] ?? '';
    try {
        $dt        = new DateTime($startAt);
        $trMonths  = ['', 'Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
        $trDays    = ['Pazar','Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi'];
        $dateLabel = $trDays[(int)$dt->format('w')] . ', ' . (int)$dt->format('j') . ' ' . $trMonths[(int)$dt->format('n')] . ' ' . $dt->format('Y');
        $timeLabel = $dt->format('H:i');
    } catch (\Throwable $e) {
        $dateLabel = $startAt;
        $timeLabel = '';
    }

    // İşletme + personel + hizmet adları
    $bizName   = $row['business_name'] ?? '';
    $staffName = $row['staff_name']    ?? '';
    $service   = $row['service_name']  ?? $row['notes'] ?? '';
    $address   = '';
    $bizId     = (int)($row['business_id'] ?? 0);

    // DB'den işletme bilgisi tamamla
    if ($bizId && (!$bizName || !$address)) {
        try {
            $bStmt = $pdo->prepare("SELECT b.name, b.address_line, b.city, b.district, u.email AS owner_email
                FROM businesses b LEFT JOIN users u ON u.id = b.owner_id WHERE b.id = ? LIMIT 1");
            $bStmt->execute([$bizId]);
            $bRow = $bStmt->fetch();
            if ($bRow) {
                if (!$bizName) $bizName = $bRow['name'] ?? '';
                $addressParts = array_filter([$bRow['address_line'] ?? '', $bRow['district'] ?? '', $bRow['city'] ?? '']);
                $address = implode(', ', $addressParts);
                if (empty($row['owner_email'])) $row['owner_email'] = $bRow['owner_email'] ?? '';
            }
        } catch (\Throwable $e) {}
    }

    // Hizmet adını DB'den al
    $serviceId = (int)($row['service_id'] ?? 0);
    if ($serviceId && !$service) {
        try {
            $sStmt = $pdo->prepare("SELECT name FROM services WHERE id = ? LIMIT 1");
            $sStmt->execute([$serviceId]);
            $service = $sStmt->fetchColumn() ?: '';
        } catch (\Throwable $e) {}
    }

    // Personel adını DB'den al
    $staffId = (int)($row['staff_id'] ?? 0);
    if ($staffId && !$staffName) {
        try {
            $stStmt = $pdo->prepare("SELECT name FROM staff WHERE id = ? LIMIT 1");
            $stStmt->execute([$staffId]);
            $staffName = $stStmt->fetchColumn() ?: '';
        } catch (\Throwable $e) {}
    }

    return [
        'custName'   => $row['customer_name']  ?? '',
        'custEmail'  => $row['customer_email'] ?? '',
        'custPhone'  => $row['customer_phone'] ?? '',
        'bizId'      => $bizId,
        'bizName'    => $bizName,
        'ownerEmail' => $row['owner_email']    ?? '',
        'service'    => $service,
        'staffName'  => $staffName,
        'dateLabel'  => $dateLabel,
        'timeLabel'  => $timeLabel,
        'address'    => $address,
        'status'     => $row['status'] ?? 'pending',
        'siteUrl'    => $siteUrl,
    ];
}