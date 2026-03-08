<?php
declare(strict_types=1);
/**
 * api/settings/upload-image.php
 * POST (multipart/form-data) — İşletme görseli yükle
 *
 * Beklenen form alanları:
 *   file  — görsel dosya (jpeg/png/webp/gif, max 5MB)
 *   kind  — "cover" | "salon" | "model"
 *
 * NOT: CSRF token X-CSRF-Token header'ı olarak gönderilmeli
 *      (wb-api-shim.js'deki apiUpload bunu otomatik ekler)
 */

require_once __DIR__ . '/../admin/_bootstrap.php';
wb_method('POST');

$bid = $user['business_id'];
if (!$bid) wb_err('İşletme bulunamadı', 404, 'business_not_found');

$kind    = trim((string)($_POST['kind'] ?? ''));
$allowed = ['cover', 'salon', 'model'];
if (!in_array($kind, $allowed, true)) {
    wb_err('Geçersiz kind değeri. Olası değerler: ' . implode(', ', $allowed), 400, 'invalid_kind');
}

$file = $_FILES['file'] ?? null;
if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
    $errMap = [
        UPLOAD_ERR_INI_SIZE   => 'Dosya sunucu limitini aşıyor',
        UPLOAD_ERR_FORM_SIZE  => 'Dosya form limitini aşıyor',
        UPLOAD_ERR_PARTIAL    => 'Dosya kısmen yüklendi',
        UPLOAD_ERR_NO_FILE    => 'Dosya seçilmedi',
        UPLOAD_ERR_NO_TMP_DIR => 'Geçici dizin bulunamadı',
        UPLOAD_ERR_CANT_WRITE => 'Dosya yazılamadı',
        UPLOAD_ERR_EXTENSION  => 'PHP uzantısı yüklemeyi engelledi',
    ];
    $msg = $errMap[$file['error'] ?? -1] ?? 'Yükleme hatası';
    wb_err($msg, 400, 'upload_error');
}

$allowedMimes = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
$mime         = mime_content_type($file['tmp_name']);
if (!in_array($mime, $allowedMimes, true)) {
    wb_err('Desteklenmeyen dosya tipi. İzin verilenler: JPEG, PNG, WebP, GIF', 415, 'unsupported_mime');
}

if ($file['size'] > 5 * 1024 * 1024) {
    wb_err('Dosya 5MB sınırını aşıyor', 413, 'file_too_large');
}

try {
    $webeyRoot = realpath(__DIR__ . '/../..');
    $uploadDir = $webeyRoot . '/uploads/biz/' . $bid . '/';
    if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);

    $ext      = match($mime) {
        'image/png'  => 'png',
        'image/webp' => 'webp',
        'image/gif'  => 'gif',
        default      => 'jpg',
    };
    $filename = $kind . '_' . uniqid() . '.' . $ext;
    $target   = $uploadDir . $filename;

    if (!move_uploaded_file($file['tmp_name'], $target)) {
        wb_err('Dosya kaydedilemedi', 500, 'save_error');
    }

    $url    = 'uploads/biz/' . $bid . '/' . $filename;
    $optUrl = null;

    // ── GD ile thumbnail optimize ─────────────────────────────────────────────
    // cover  → max 700×467 WebP q72
    // salon/model → max 960×720 WebP q78
    if (function_exists('imagecreatefromjpeg') && $mime !== 'image/gif') {
        try {
            $src = match($mime) {
                'image/png'  => imagecreatefrompng($target),
                'image/webp' => function_exists('imagecreatefromwebp') ? imagecreatefromwebp($target) : false,
                default      => imagecreatefromjpeg($target),
            };

            if ($src) {
                $srcW = imagesx($src);
                $srcH = imagesy($src);
                [$maxW, $maxH, $quality] = $kind === 'cover' ? [700, 467, 72] : [960, 720, 78];

                $ratio = min($maxW / $srcW, $maxH / $srcH, 1.0);
                $newW  = max(1, (int)floor($srcW * $ratio));
                $newH  = max(1, (int)floor($srcH * $ratio));

                $dst = imagecreatetruecolor($newW, $newH);
                imagealphablending($dst, false);
                imagesavealpha($dst, true);
                imagefilledrectangle($dst, 0, 0, $newW, $newH, imagecolorallocatealpha($dst, 255, 255, 255, 0));
                imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $srcW, $srcH);

                $optFilename = 'opt_' . pathinfo($filename, PATHINFO_FILENAME) . '.webp';
                $optTarget   = $uploadDir . $optFilename;

                if (function_exists('imagewebp') && imagewebp($dst, $optTarget, $quality)) {
                    $optUrl = 'uploads/biz/' . $bid . '/' . $optFilename;
                } elseif (imagejpeg($dst, $optTarget . '.jpg', $quality)) {
                    $optUrl = 'uploads/biz/' . $bid . '/' . $optFilename . '.jpg';
                }

                imagedestroy($src);
                imagedestroy($dst);
            }
        } catch (Throwable $gdErr) {
            error_log('[settings/upload-image] GD hata: ' . $gdErr->getMessage());
            $optUrl = null;
        }
    }

    // ── DB: images_json güncelle ──────────────────────────────────────────────
    $stmt = $pdo->prepare('SELECT images_json FROM businesses WHERE id = ?');
    $stmt->execute([$bid]);
    $row    = $stmt->fetch();
    $images = json_decode($row['images_json'] ?? 'null', true)
        ?: ['cover' => [], 'salon' => [], 'model' => []];

    $optKey = $kind . '_opt';

    if ($kind === 'cover') {
        // Eski cover dosyalarını sil
        $toDelete = array_merge(
            is_array($images['cover']     ?? null) ? $images['cover']     : [],
            is_array($images['cover_opt'] ?? null) ? $images['cover_opt'] : []
        );
        foreach ($toDelete as $oldUrl) {
            $clean   = ltrim(preg_replace('#^(/[^/]+)?/uploads/#', 'uploads/', (string)$oldUrl), '/');
            $oldPath = $webeyRoot . '/' . $clean;
            if ($oldPath !== $target && file_exists($oldPath)) @unlink($oldPath);
        }
        $images['cover']     = [$url];
        $images['cover_opt'] = $optUrl ? [$optUrl] : [];
    } else {
        if (!is_array($images[$kind]))   $images[$kind]   = [];
        if (!is_array($images[$optKey])) $images[$optKey] = [];
        $images[$kind][]   = $url;
        if ($optUrl) $images[$optKey][] = $optUrl;
    }

    $pdo->prepare('UPDATE businesses SET images_json = ?, updated_at = NOW() WHERE id = ?')
        ->execute([json_encode($images), $bid]);

    wb_ok([
        'url'    => $url,
        'optUrl' => $optUrl ?? $url,
        'kind'   => $kind,
        'images' => $images,
    ]);

} catch (Throwable $e) {
    error_log('[settings/upload-image] ' . $e->getMessage());
    wb_err('Görsel yüklenemedi', 500, 'internal_error');
}