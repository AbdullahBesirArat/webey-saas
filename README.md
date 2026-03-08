# Webey — Geliştirici Dokümantasyonu

> Son güncelleme: Mart 2026  
> PHP 8.2 · MariaDB 10.4 · Session tabanlı auth (cookie)

---

## İçindekiler

1. [Proje Yapısı](#proje-yapısı)
2. [Auth Mimarisi](#auth-mimarisi)
3. [API Endpoint Listesi](#api-endpoint-listesi)
4. [Response Formatı](#response-formatı)
5. [Frontend JS Mimarisi](#frontend-js-mimarisi)
6. [Service Worker & Cache](#service-worker--cache)
7. [Veritabanı](#veritabanı)
8. [Kurulum](#kurulum)

---

## Proje Yapısı

```
webey/
├── api/
│   ├── _bootstrap.php          # Session + header başlatma
│   ├── wb_response.php         # Standart response helper (wb_ok, wb_err)
│   ├── _logout_helper.php      # Paylaşılan logout mantığı
│   ├── _mailer.php             # Email gönderme
│   ├── session/
│   │   └── me.php              # ★ Birleşik oturum endpoint'i
│   ├── auth/                   # Admin / işletme sahibi auth
│   ├── user/                   # Müşteri auth + profil
│   ├── appointments/           # Randevu CRUD
│   ├── calendar/               # Takvim (admin görünümü)
│   ├── billing/                # Abonelik & ödeme
│   ├── business/               # İşletme profili
│   ├── public/                 # Auth gerektirmeyen public endpoint'ler
│   ├── services/               # Hizmet yönetimi
│   ├── staff/                  # Personel yönetimi
│   └── reviews/                # Değerlendirme sistemi
├── js/
│   ├── api-client.js           # ★ Merkezi fetch wrapper + session state
│   ├── auth.js                 # Müşteri kayıt/giriş UI
│   └── ...
├── css/
├── service-worker.js           # PWA cache stratejileri
├── database/
│   ├── schema.sql              # Tam şema
│   └── webey_migration_001.sql # Index + updated_at + appointment_logs
└── db.php                      # PDO bağlantısı
```

---

## Auth Mimarisi

İki ayrı kullanıcı tipi vardır, her biri ayrı session key seti kullanır:

### Admin / İşletme Sahibi
```
Session keys: user_id, admin_id, email, business_id
Giriş:  POST /api/auth/login.php       { email, password }
Kayıt:  POST /api/auth/register.php    { email, password }
Çıkış:  POST /api/auth/logout.php
```

### Müşteri (end-user)
```
Session keys: user_id, user_role='user', user_phone
Giriş:  POST /api/user/login.php       { phone, password }
Kayıt:  POST /api/user/register.php    { phone, password, firstName, ... }
Çıkış:  POST /api/user/logout.php
```

### Birleşik Oturum Kontrolü
```
GET /api/session/me.php
→ { ok: true, data: { role: 'admin', ... } }  — işletme sahibi
→ { ok: true, data: { role: 'user',  ... } }  — müşteri
→ { ok: false, code: 'unauthenticated' }       — giriş yok
```
`api/auth/me.php` ve `api/user/me.php` artık bu endpoint'e yönlendiren birer shim'dir.  
`api-client.js → refreshSession()` tek istekle her iki rolü kontrol eder.

---

## API Endpoint Listesi

Tüm endpoint'ler `/api/` prefix'iyle çağrılır. Response formatı standart `{ ok, data/error }`.

### 🔐 Oturum
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET/POST | `session/me.php` | — | Birleşik oturum kontrolü (admin veya user) |
| GET | `auth/me.php` | admin | **[shim]** → session/me.php |
| GET | `user/me.php` | user | **[shim]** → session/me.php |

### 👤 Admin Auth
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| POST | `auth/login.php` | — | Email + şifre ile giriş |
| POST | `auth/register.php` | — | Yeni admin kaydı, doğrulama emaili gönderir |
| POST | `auth/logout.php` | — | Çıkış, session temizle |
| POST | `auth/forgot-password.php` | — | Şifre sıfırlama emaili |
| POST | `auth/reset-password.php` | — | Token ile yeni şifre belirleme |
| POST | `auth/verify-email.php` | — | Email token doğrulama |
| GET | `auth/check-email-status.php` | admin | Email doğrulama durumu |
| POST | `auth/check-email.php` | — | Email kullanımda mı? |
| POST | `auth/google-login.php` | — | Google OAuth ile giriş/kayıt |
| GET | `auth/getUser.php` | admin | Mevcut admin detayı |

### 📱 Müşteri Auth
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| POST | `user/login.php` | — | Telefon + şifre ile giriş |
| POST | `user/register.php` | — | Yeni müşteri kaydı |
| POST | `user/logout.php` | — | Çıkış |
| POST | `user/check-phone.php` | — | Telefon kullanımda mı? |
| POST | `user/completeSignup.php` | user | Kayıt tamamlama |
| GET | `user/getProfile.php` | user | Müşteri profili |
| POST | `user/update-profile.php` | user | Profil güncelle |
| POST | `user/profile/update.php` | user | Profil güncelle (alan bazlı: name/phone/email/address/password) |

### 📅 Randevular (Müşteri)
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET | `user/appointments.php` | user | Müşterinin randevuları |
| POST | `user/appointments/cancel.php` | user | Randevu iptali |
| GET | `user/appointments/next.php` | user | Bir sonraki randevu |

### ❤️ Favoriler
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET | `user/favorites/list.php` | user | Favori işletmeler |
| GET | `user/favorites/check.php` | user | İşletme favori mi? `?ids=1,2,3` |
| POST | `user/favorites/toggle.php` | user | Favori ekle/kaldır |

### 📆 Takvim (Admin)
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET | `calendar/appointments.php` | admin | Takvim randevuları |
| GET | `calendar/bootstrap.php` | admin | Takvim başlangıç verisi |
| POST | `calendar/block-time.php` | admin | Blok zaman ekle |
| POST | `calendar/approve-cancellation.php` | admin | İptal isteğini onayla |
| POST | `calendar/reject-cancellation.php` | admin | İptal isteğini reddet |
| GET | `calendar/cancellation-requests.php` | admin | Bekleyen iptal istekleri |
| GET | `calendar/pending-notifications.php` | admin | Bildirimler |
| GET | `calendar/customer-history.php` | admin | Müşteri geçmişi |

### 🗓️ Randevu İşlemleri (Genel)
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| POST | `appointments/book.php` | — | Yeni randevu oluştur |
| GET | `appointments/booked-map.php` | — | Dolu zaman slotları |
| POST | `appointments/check-conflict.php` | — | Çakışma kontrolü |
| GET | `appointments/status.php` | — | Randevu durumu |
| GET | `appointments/cancellation-status.php` | — | İptal durumu |
| POST | `appointments/cancel.php` | — | İptal isteği gönder |
| POST | `appointments/reschedule.php` | — | Yeniden zamanla |
| POST/PUT | `appointments/setStatus.php` | admin | Durum güncelle |
| GET | `appointments/counters.php` | admin | Randevu sayıları |

### 🏢 İşletme & Admin
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET/POST | `business/profile.php` | admin | İşletme profili |
| POST | `admin/completeOnboarding.php` | admin | Onboarding tamamla |
| POST | `admin/updateAbout.php` | admin | Hakkında güncelle |
| GET/POST | `admin/status.php` | admin | İşletme durumu |
| GET | `settings/load.php` | admin | Ayarları yükle |
| POST | `settings/save.php` | admin | Ayarları kaydet |
| POST | `settings/upload-image.php` | admin | Fotoğraf yükle |
| POST | `settings/delete-image.php` | admin | Fotoğraf sil |

### 👥 Personel & Hizmet
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET | `staff/list.php` | admin | Personel listesi |
| POST | `staff/save.php` | admin | Personel ekle/güncelle |
| POST | `staff/delete.php` | admin | Personel sil |
| GET/POST | `staff/hours.php` | admin | Personel çalışma saatleri |
| POST | `staff/save-services.php` | admin | Personele hizmet ata |
| POST | `staff/upload-photo.php` | admin | Personel fotoğrafı |
| POST | `staff/remove-photo.php` | admin | Personel fotoğrafı sil |
| GET | `services/list.php` | admin | Hizmet listesi |
| POST | `services/save.php` | admin | Hizmet ekle/güncelle |
| POST | `services/delete.php` | admin | Hizmet sil |

### 🌐 Public (Auth Yok)
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET | `public/salons.php` | — | Aktif işletme listesi `?city=&district=` |
| GET | `public/businesses.php` | — | İşletme filtrele/listele |
| GET | `public/suggest.php` | — | Arama önerileri |
| GET | `public/business.php` | — | Tek işletme detayı |

### ⭐ Değerlendirmeler
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET | `reviews/list.php` | — | İşletme değerlendirmeleri |
| GET | `reviews/can-review.php` | user | Değerlendirme yapabilir mi? |
| POST | `reviews/submit.php` | user | Değerlendirme gönder |

### 💳 Faturalama
| Method | Endpoint | Auth | Açıklama |
|--------|----------|------|----------|
| GET | `billing/cards.php` | admin | Kayıtlı kartlar |
| POST | `billing/add-card.php` | admin | Kart ekle |
| POST | `billing/remove-card.php` | admin | Kart sil |
| POST | `billing/subscribe.php` | admin | Abonelik başlat |
| POST | `billing/cancel.php` | admin | Abonelik iptal |
| GET | `billing/invoices.php` | admin | Faturalar |

---

## Response Formatı

Tüm endpoint'ler `wb_response.php` üzerinden standart JSON döner:

```json
// Başarı
{ "ok": true, "data": { ... } }

// Hata
{ "ok": false, "error": "Kullanıcıya gösterilecek mesaj", "code": "snake_case_key" }
```

HTTP durum kodları: `200` başarı, `400` validasyon, `401` auth, `403` yetki, `404` bulunamadı, `409` çakışma, `422` unprocessable, `500` sunucu hatası.

---

## Frontend JS Mimarisi

### `api-client.js` — Tek Fetch Noktası

```js
import { api, getSession, onAuthChange, refreshSession } from './api-client.js';

// GET / POST
const res = await api.get('/api/public/salons.php');
const res = await api.post('/api/user/login.php', { phone, password });

// Oturum dinle
onAuthChange(session => {
    if (!session) { /* giriş yok */ return; }
    if (session.type === 'admin') { /* admin UI */ }
    if (session.type === 'user')  { /* müşteri UI */ }
});
```

### Diğer Dosyalardaki Yerel apiGet/apiPost

`calendar.js`, `settings.js`, `staff.js`, `profile.js`, `admin-profile.js` ve `user-profile.js` şu an kendi yerel `apiGet`/`apiPost` tanımlarını kullanıyor. Bu dosyalar gelecekte `api-client.js`'e migrate edilebilir.

### `auth.js` — Müşteri Auth UI

4 adımlı kayıt akışı (telefon → şifre → kimlik → adres) ve giriş formu.  
Artık `api-client.js`'in `api` nesnesi üzerinden istek atıyor.  
`initGoogleAuth()` kaldırıldı — Google auth `index.html` inline script'inden yönetiliyor.

```js
import { initAuth, normPhone } from './auth.js';
```

---

## Service Worker & Cache

`service-worker.js` 3 ayrı cache bucket kullanır:

| Cache | İçerik | Strateji |
|-------|---------|----------|
| `webey-static-v2` | CSS, JS, fontlar | Stale-while-revalidate |
| `webey-pages-v2` | HTML sayfalar | Network-first |
| `webey-images-v2` | Görseller | Cache-first, 3 gün TTL |

**Cache versiyonunu güncellemek:** `service-worker.js` dosyasında `CACHE_VERSION = 'v2'` satırını artır (örn. `'v3'`). Deploy sonrası kullanıcıların tarayıcısı eski cache'i otomatik temizler.

**API istekleri hiçbir zaman cache'e alınmaz.** Çevrimdışıyken API çağrılarına `{ ok: false, error: 'Çevrimdışısınız...' }` döner.

---

## Veritabanı

### Tablolar
| Tablo | Açıklama |
|-------|----------|
| `users` | Tüm kullanıcılar (admin + müşteri), email ile unique |
| `admin_users` | users tablosuna 1:1, işletme sahipleri |
| `businesses` | İşletme profilleri |
| `customers` | users tablosuna 1:1, müşteri profilleri |
| `appointments` | Randevular |
| `appointment_logs` | Randevu durum geçmişi |
| `services` | İşletme hizmetleri |
| `staff` | Personeller |
| `staff_hours` | Personel çalışma saatleri |
| `business_hours` | İşletme çalışma saatleri |
| `reviews` | Değerlendirmeler |
| `favorites` | Müşteri favorileri |
| `subscriptions` | Abonelikler |
| `invoices` | Faturalar |
| `payment_cards` | Kayıtlı ödeme kartları |

### Migration Uygulama

```bash
# Önce yedek al
mysqldump -u root -p webey_local > backup_$(date +%Y%m%d).sql

# Migration'ı uygula
mysql -u root -p webey_local < database/webey_migration_001.sql
```

`webey_migration_001.sql` içeriği:
- 11 yeni index (status, phone, city/district, onboarding vb.)
- 2 mükerrer index silindi
- `appointments.updated_at` kolonu eklendi
- `appointment_logs` tablosu oluşturuldu

---

## Kurulum

### Gereksinimler
- PHP 8.2+
- MariaDB 10.4+ / MySQL 8+
- Composer (opsiyonel, mailer için)

### Adımlar

```bash
# 1. db.php dosyasını yapılandır
cp db.php.example db.php
# DB_HOST, DB_NAME, DB_USER, DB_PASS değerlerini doldur

# 2. Şemayı kur
mysql -u root -p webey_local < database/schema.sql

# 3. Migration'ları uygula
mysql -u root -p webey_local < database/webey_migration_001.sql

# 4. Email ayarları
# api/_email_config.php dosyasını doldur (SMTP, site_url vb.)

# 5. Google OAuth
# api/auth/google-login.php içindeki CLIENT_ID'yi güncelle
# index.html ve auth.js'deki GOOGLE_CLIENT_ID'yi güncelle
```

### Ortam Değişkenleri (Önerilen)
`_email_config.php` ve `db.php`'deki hassas değerleri `$_ENV` üzerinden yönet:

```php
// db.php
$pdo = new PDO(
    'mysql:host=' . ($_ENV['DB_HOST'] ?? 'localhost') . ';dbname=' . ($_ENV['DB_NAME'] ?? 'webey_local'),
    $_ENV['DB_USER'] ?? 'root',
    $_ENV['DB_PASS'] ?? ''
);
```