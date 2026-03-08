/**
 * wb-bottom-nav.js — Webey Mobil Alt Navigasyon
 * ─────────────────────────────────────────────────────────────────
 * "Randevular" ve "Profilim" butonlarına tıklanınca:
 *   • Giriş yapılmamışsa  → auth modal açılır (sayfa içinde)
 *   • Giriş yapılmışsa    → user-profile.html'e gidilir
 *
 * Auth modalleri kuafor.html ile birebir aynı HTML/CSS/JS kullanır.
 * auth.js lazy import edilir, sadece ihtiyaç olduğunda yüklenir.
 */
(function () {
  'use strict';

  /* ── Sayfa algılama ── */
  const path = location.pathname.split('/').pop() || 'index.html';
  const page = (
    document.documentElement.dataset.page ||
    document.body.dataset.page ||
    path
  );

  const isActive = (id) => {
    const map = {
      home:         ['index.html', '', '/'],
      explore:      ['kuafor.html'],
      appointments: ['appointments.html', 'user-profile.html'],
      profile:      ['user-profile.html'],
    };
    return (map[id] || []).some(p => page.includes(p));
  };

  /* ── Nav yapısı ── */
  const items = [
    { id: 'home',         href: 'index.html',                      icon: 'fa-solid fa-house',           label: 'Ana Sayfa',  auth: false },
    { id: 'explore',      href: 'kuafor.html',                     icon: 'fa-solid fa-magnifying-glass', label: 'Keşfet',     auth: false },
    { id: 'appointments', href: 'user-profile.html#appointments',  icon: 'fa-regular fa-calendar-check', label: 'Randevular', auth: true, cta: true, badge: 0 },
    { id: 'profile',      href: 'user-profile.html',               icon: 'fa-regular fa-circle-user',    label: 'Profilim',   auth: true },
  ];

  /* ── HTML Oluştur ── */
  const nav = document.createElement('nav');
  nav.className   = 'wb-bottom-nav';
  nav.id          = 'wbBottomNav';
  nav.setAttribute('role', 'navigation');
  nav.setAttribute('aria-label', 'Alt Navigasyon');

  const inner = document.createElement('div');
  inner.className = 'wb-bn-inner';

  items.forEach(item => {
    const a = document.createElement('a');
    a.href      = item.href;
    a.className = 'wb-bn-item' + (item.cta ? ' wb-bn-cta' : '');
    a.setAttribute('aria-label', item.label);
    if (item.auth) a.dataset.authNav = '1';  // işaret: session kontrolü

    if (isActive(item.id)) {
      a.classList.add('is-active');
      a.setAttribute('aria-current', 'page');
    }

    if (item.cta) {
      a.innerHTML = `
        <div class="wb-bn-icon-wrap">
          <i class="${item.icon} wb-bn-icon" aria-hidden="true"></i>
        </div>
        <span class="wb-bn-label">${item.label}</span>`;
    } else {
      a.innerHTML = `
        <i class="${item.icon} wb-bn-icon" aria-hidden="true"></i>
        <span class="wb-bn-label">${item.label}</span>
        ${item.badge != null ? `<span class="wb-bn-badge" data-badge="${item.id}"></span>` : ''}`;
    }

    inner.appendChild(a);
  });

  nav.appendChild(inner);
  document.body.appendChild(nav);

  /* ════════════════════════════════════════════════════════════
     MODAL YÖNETİMİ
  ════════════════════════════════════════════════════════════ */

  function openModal(id) {
    const m = document.getElementById(id);
    if (!m) return;
    m.removeAttribute('hidden');
    m.classList.add('active');
    m.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    setTimeout(() => m.querySelector('input:not([disabled]):not([tabindex="-1"])')?.focus(), 60);
  }

  function closeTopModal() {
    const open = Array.from(document.querySelectorAll('.modal-overlay.active'));
    if (!open.length) return;
    const top = open[open.length - 1];
    top.classList.remove('active');
    top.setAttribute('aria-hidden', 'true');
    top.setAttribute('hidden', '');
    if (!document.querySelector('.modal-overlay.active')) {
      document.body.classList.remove('no-scroll');
    }
  }

  function injectAuthModalsIfMissing() {
    if (document.getElementById('authModal')) return;

    const host = document.createElement('div');
    host.id = 'wb-auth-modals';
    host.innerHTML = `
      <!-- ── 1. GİRİŞ / KAYIT ── -->
      <div id="authModal" class="modal-overlay" role="dialog" aria-modal="true"
           aria-labelledby="wbAuthTitle" aria-hidden="true" hidden>
        <div class="modal-box">
          <div class="auth-modal-header">
            <div class="auth-header-spacer"></div>
            <div class="auth-steps" aria-hidden="true"></div>
            <button class="modal-close" type="button" aria-label="Kapat">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="auth-container">
            <h2 id="wbAuthTitle" class="auth-title">Hoş Geldin</h2>
            <p class="auth-subtitle">Randevu almak için giriş yap veya kayıt ol.</p>
            <div class="auth-tabs" role="tablist">
              <button class="auth-tab active" data-tab="login"  type="button" role="tab" aria-selected="true">Giriş Yap</button>
              <button class="auth-tab"        data-tab="signup" type="button" role="tab" aria-selected="false">Kayıt Ol</button>
            </div>
            <form id="loginForm" class="auth-form active" data-form="login" autocomplete="off" role="tabpanel">
              <div class="phone-row">
                <div class="cc-box" aria-hidden="true"><span class="flag">🇹🇷</span><span class="cc">+90</span></div>
                <input class="auth-input phone-input" type="tel" name="phone" placeholder="5xx xxx xx xx"
                       inputmode="numeric" maxlength="13" autocomplete="tel-national"
                       data-phone required aria-label="Telefon numarası" />
              </div>
              <div class="password-wrap">
                <input class="auth-input" type="password" name="password" placeholder="Şifre"
                       autocomplete="current-password" aria-label="Şifre" required minlength="8" />
                <button type="button" class="toggle-eye" aria-label="Şifreyi göster/gizle">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div class="auth-link-row">
                <a id="forgotLink" href="#" class="auth-link">Şifremi unuttum</a>
              </div>
              <div id="loginError" class="error" aria-live="assertive"></div>
              <button type="submit" class="auth-btn" id="btnLogin">Giriş Yap</button>
            </form>
            <form id="signupForm" class="auth-form" data-form="signup" autocomplete="one-time-code" role="tabpanel">
              <div class="phone-row">
                <div class="cc-box" aria-hidden="true"><span class="flag">🇹🇷</span><span class="cc">+90</span></div>
                <input class="auth-input phone-input" type="tel" name="phone" placeholder="5xx xxx xx xx"
                       inputmode="numeric" maxlength="13" autocomplete="tel-national"
                       data-phone required aria-label="Telefon numarası" />
              </div>
              <p class="phone-hint">Türkiye numarası · 10 hane · 5xx ile başlamalı</p>
              <div id="signupError" class="error" aria-live="assertive"></div>
              <button type="submit" class="auth-btn" id="btnSendOtp">Devam Et →</button>
            </form>
          </div>
        </div>
      </div>

      <!-- ── 2. OTP ── -->
      <div id="otpModal" class="modal-overlay" role="dialog" aria-modal="true"
           aria-labelledby="otpTitle" aria-hidden="true" hidden>
        <div class="modal-box">
          <div class="auth-modal-header">
            <button class="modal-back" type="button" id="btnBackOtp" aria-label="Geri">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="auth-steps" aria-hidden="true">
              <div class="auth-step-dot done"></div>
              <div class="auth-step-dot active"></div>
              <div class="auth-step-dot"></div>
            </div>
            <button class="modal-close" type="button" aria-label="Kapat">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="auth-container">
            <h2 id="otpTitle" class="auth-title">SMS Kodu</h2>
            <p class="auth-subtitle">Telefonuna gönderilen 6 haneli kodu gir.</p>
            <form id="otpForm" class="auth-form active" data-form="otp" autocomplete="one-time-code">
              <input class="auth-input otp-input" type="text" inputmode="numeric"
                     pattern="[0-9]{6}" maxlength="6" name="code" placeholder="- - - - - -"
                     required aria-label="SMS kodu"
                     style="text-align:center;font-size:22px;letter-spacing:10px;font-weight:700" />
              <div id="otpError" class="error" aria-live="assertive"></div>
              <button type="submit" class="auth-btn" id="btnVerifyOtp">Doğrula</button>
              <button type="button" class="resend-otp" id="btnResendOtp"
                      style="display:block;width:100%;margin-top:10px;background:none;border:none;
                             color:#6366f1;font-size:13.5px;font-weight:600;cursor:pointer;padding:8px;">
                Kodu Tekrar Gönder
              </button>
            </form>
          </div>
        </div>
      </div>

      <!-- ── 3. ŞİFRE ── -->
      <div id="passModal" class="modal-overlay" role="dialog" aria-modal="true"
           aria-labelledby="passTitle" aria-hidden="true" hidden>
        <div class="modal-box">
          <div class="auth-modal-header">
            <button class="modal-back" type="button" id="btnBackPass" aria-label="Geri">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="auth-steps" aria-hidden="true">
              <div class="auth-step-dot active"></div>
              <div class="auth-step-dot"></div>
              <div class="auth-step-dot"></div>
            </div>
            <button class="modal-close" type="button" aria-label="Kapat">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="auth-container">
            <h2 id="passTitle" class="auth-title">Şifre Oluştur</h2>
            <p class="auth-subtitle">En az 8 karakter — harf ve rakam karıştır.</p>
            <form id="passForm" class="auth-form active" data-form="pass" autocomplete="new-password">
              <div class="password-wrap">
                <input class="auth-input" type="password" name="password"
                       placeholder="Şifre" aria-label="Şifre" required minlength="8" />
                <button type="button" class="toggle-eye" aria-label="Şifreyi göster/gizle">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div class="pass-strength" aria-hidden="true">
                <div class="pass-strength-bar" id="passStrBar"></div>
              </div>
              <span id="passStrLabel" class="pass-strength-label"></span>
              <span id="passStrTips" class="pass-strength-tips"></span>
              <div class="password-wrap">
                <input class="auth-input" type="password" name="confirm"
                       placeholder="Şifre (tekrar)" aria-label="Şifre tekrar" required minlength="8" />
                <button type="button" class="toggle-eye" aria-label="Şifreyi göster/gizle">
                  <i class="fa-regular fa-eye"></i>
                </button>
              </div>
              <div id="passError" class="error" aria-live="assertive"></div>
              <button type="submit" class="auth-btn" id="btnPassNext" disabled>Devam Et →</button>
            </form>
          </div>
        </div>
      </div>

      <!-- ── 4. KİMLİK ── -->
      <div id="nameModal" class="modal-overlay" role="dialog" aria-modal="true"
           aria-labelledby="nameTitle" aria-hidden="true" hidden>
        <div class="modal-box">
          <div class="auth-modal-header">
            <button class="modal-back" type="button" id="btnBackName" aria-label="Geri">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="auth-steps" aria-hidden="true">
              <div class="auth-step-dot done"></div>
              <div class="auth-step-dot active"></div>
              <div class="auth-step-dot"></div>
            </div>
            <button class="modal-close" type="button" aria-label="Kapat">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="auth-container">
            <h2 id="nameTitle" class="auth-title">Kimlik Bilgileri</h2>
            <p class="auth-subtitle">Randevu aldığında salon bu bilgileri görecek — doğru gir.</p>
            <form id="nameForm" class="auth-form active" data-form="name">
              <div class="field-row-2">
                <input class="auth-input" type="text" name="firstName" placeholder="Ad"
                       autocomplete="given-name" required />
                <input class="auth-input" type="text" name="lastName" placeholder="Soyad"
                       autocomplete="family-name" required />
              </div>
              <button type="button" id="dobTriggerBtn" class="dob-trigger placeholder"
                      aria-label="Doğum tarihi seç" aria-haspopup="dialog">
                <span id="dobDisplay">Doğum Tarihi</span>
                <i class="fas fa-calendar-alt dob-icon"></i>
              </button>
              <input type="text" name="birthday" id="birthdayInput" tabindex="-1"
                     aria-hidden="true"
                     style="position:absolute;opacity:0;pointer-events:none;width:1px;height:1px" />
              <div id="nameError" class="error" aria-live="assertive"></div>
              <button type="submit" class="auth-btn" id="btnNameNext" disabled>Devam Et →</button>
            </form>
          </div>
        </div>
      </div>

      <!-- ── 5. ADRES ── -->
      <div id="addressModal" class="modal-overlay" role="dialog" aria-modal="true"
           aria-labelledby="addressTitle" aria-hidden="true" hidden>
        <div class="modal-box">
          <div class="auth-modal-header">
            <button class="modal-back" type="button" id="btnBackAddress" aria-label="Geri">
              <i class="fas fa-arrow-left"></i>
            </button>
            <div class="auth-steps" aria-hidden="true">
              <div class="auth-step-dot done"></div>
              <div class="auth-step-dot done"></div>
              <div class="auth-step-dot active"></div>
            </div>
            <button class="modal-close" type="button" aria-label="Kapat">
              <i class="fas fa-times"></i>
            </button>
          </div>
          <div class="auth-container">
            <h2 id="addressTitle" class="auth-title">Konumun</h2>
            <p class="auth-subtitle">Yakınındaki salonları önermek için şehir bilgine ihtiyacımız var.</p>
            <form id="addressForm" class="auth-form active" data-form="address" autocomplete="off">
              <div class="field-row-3">
                <select class="auth-input" id="citySelect" required aria-label="Şehir"></select>
                <select class="auth-input" id="districtSelect" required aria-label="İlçe"></select>
                <select class="auth-input" id="neighborhoodSelect" required aria-label="Mahalle"></select>
              </div>
              <div id="addressError" class="error" aria-live="assertive"></div>
              <button type="submit" class="auth-btn" id="btnFinish" disabled>Kaydı Tamamla ✓</button>
            </form>
          </div>
        </div>
      </div>

      <div id="recaptcha-container" class="sr-only"></div>
      <div id="toast" class="toast" role="status" aria-live="polite" aria-atomic="true"></div>
    `;
    document.body.appendChild(host);

    /* Modal backdrop tıklama + close butonları */
    host.querySelectorAll('.modal-overlay').forEach(m => {
      m.addEventListener('click', (e) => { if (e.target === m) closeTopModal(); });
      m.querySelector('.modal-close')?.addEventListener('click', closeTopModal);
    });

    /* AppModals: auth.js bu interface'i kullanıyor */
    if (!window.AppModals) {
      window.AppModals = {
        openModal,
        closeModal: closeTopModal,
      };
    }
  }

  /* ── Kayıt/giriş tamamlandığında → user-profile.html'e yönlendir ── */
  document.addEventListener('user:loggedin', () => {
    /* Eğer modal açıksa (biz açtıysak) yönlendir */
    if (document.querySelector('#wb-auth-modals')) {
      setTimeout(() => { window.location.href = 'user-profile.html'; }, 700);
    }
  });

  /* ── Session kontrolü → modal veya navigation ── */
  async function handleAuthNav(href) {
    try {
      const res  = await fetch('/api/session/me.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok && json.data) {
        window.location.href = href;
        return;
      }
    } catch { /* sessiz — giriş yapılmamış say */ }

    /* Giriş yapılmamış → modal inject et ve aç */
    injectAuthModalsIfMissing();

    if (!window.__wbBnAuthLoaded) {
      window.__wbBnAuthLoaded = true;
      try {
        await import('./auth.js');
        document.dispatchEvent(new Event('auth:ready'));
      } catch (e) {
        console.warn('wb-bottom-nav: auth.js yüklenemedi', e);
      }
    }

    openModal('authModal');
  }

  /* ── Click handler ── */
  nav.addEventListener('click', (e) => {
    const a = e.target.closest('[data-auth-nav]');
    if (!a) return;
    e.preventDefault();
    handleAuthNav(a.href);
  });

  /* ── Badge: pending randevu ── */
  function updateBadge(id, count) {
    const badge = nav.querySelector(`[data-badge="${id}"]`);
    if (!badge) return;
    if (count > 0) {
      badge.textContent = count > 99 ? '99+' : String(count);
      badge.classList.add('visible');
    } else {
      badge.classList.remove('visible');
    }
  }

  async function checkPendingAppts() {
    try {
      const res  = await fetch('/api/user/appointments/next.php', { credentials: 'same-origin' });
      const json = await res.json();
      if (json.ok && json.data) updateBadge('appointments', 1);
    } catch { /* sessiz */ }
  }

  if (!page.includes('user-profile')) checkPendingAppts();

})();