<?php
declare(strict_types=1);

/**
 * api/_helpers.php — Geriye Dönük Uyumluluk Katmanı
 * ──────────────────────────────────────────────────
 * Bu dosya artık yalnızca wb_response.php'yi yükler ve
 * eski fonksiyon adlarını yeni karşılıklarına eşler.
 *
 * YENİ KODDA bu dosyayı kullanma — direkt wb_response.php include et.
 */

require_once __DIR__ . '/wb_response.php';

// ── Eski adlar → yeni karşılıklar (silinecek shim'ler) ──

if (!function_exists('respond_json')) {
    /** @deprecated wb_ok() veya wb_err() kullan */
    function respond_json(array $data, int $status = 200): void {
        $isOk = ($data['ok'] ?? $data['success'] ?? true) === true;
        if ($isOk) {
            wb_ok($data, $status);
        } else {
            wb_err($data['error'] ?? 'Hata', $status);
        }
    }
}

if (!function_exists('read_json_body')) {
    /** @deprecated wb_body() kullan */
    function read_json_body(): array {
        return wb_body();
    }
}

if (!function_exists('require_method')) {
    /** @deprecated wb_method() kullan */
    function require_method(string $method): void {
        wb_method($method);
    }
}

if (!function_exists('require_auth')) {
    /** @deprecated wb_auth_admin() kullan */
    function require_auth(): array {
        return wb_auth_admin();
    }
}