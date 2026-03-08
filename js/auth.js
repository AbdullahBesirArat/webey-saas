/**
 * auth.js — Müşteri (end-user) auth flow
 * ──────────────────────────────────────────────────────────────
 * Telefon + şifre tabanlı kayıt/giriş.
 *
 * REFACTOR:
 *  - Yerel apiPost() kaldırıldı → merkezi api-client.js kullanılıyor
 *  - initGoogleAuth() / handleGoogleResponse() kaldırıldı →
 *    index.html inline script zaten aynı işi yapıyordu (çakışma kaldırıldı)
 *  - GOOGLE_CLIENT_ID sabiti kaldırıldı (index.html'den yönetiliyor)
 *  - normPhone() export edildi → user-profile.js artık bunu import edebilir
 *  - Türkçe karakter sorunları düzeltildi
 */

import { api } from './api-client.js';

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => Array.from(r.querySelectorAll(s));

const PASS_MIN = window.AUTH_PASS_MIN || 8;
const API_USER = '/api/user';

/* ── Yardımcılar ── */

export function normPhone(raw) {
    let p = (raw || '').replace(/\D/g, '');
    if (p.startsWith('90') && p.length === 12) p = p.slice(2);
    if (p.startsWith('0')) p = p.slice(1);
    return p;
}

function showToast(msg, ok = true) {
    const el = document.getElementById('toast');
    if (el) {
        el.textContent = msg;
        el.className = 'toast show ' + (ok ? 'success' : 'error');
        clearTimeout(el._wbt);
        el._wbt = setTimeout(() => el.className = 'toast', 2800);
        return;
    }
    const d = document.createElement('div');
    d.style.cssText = 'position:fixed;bottom:28px;left:50%;transform:translateX(-50%);'
        + 'padding:12px 22px;border-radius:12px;color:#fff;font-size:14px;z-index:99999;'
        + 'background:' + (ok ? '#111827' : '#dc2626') + ';font-family:Sora,sans-serif';
    d.textContent = msg;
    document.body.appendChild(d);
    setTimeout(() => d.remove(), 2800);
}

function setLoading(btn, on, lbl) {
    if (!btn) return;
    if (on) { btn._orig = btn.textContent; btn.disabled = true; btn.dataset.loading = '1'; if (lbl) btn.textContent = lbl; }
    else    { btn.disabled = false; btn.dataset.loading = ''; if (btn._orig !== undefined) btn.textContent = btn._orig; }
}

function setErr(sel, msg) { const el = $(sel); if (el) el.textContent = msg; }
function clrErr(sel)       { setErr(sel, ''); }

/* ── Modal ── */
function openM(id) {
    try { window.AppModals.openModal(id); return; } catch {}
    const m = document.getElementById(id);
    if (!m) return;
    m.removeAttribute('hidden');
    m.classList.add('active');
    m.setAttribute('aria-hidden', 'false');
    document.body.classList.add('no-scroll');
    setTimeout(() => m.querySelector('input:not([disabled]):not([tabindex="-1"])')?.focus(), 60);
}
function closeM(id) {
    try { window.AppModals.closeModal(id); return; } catch {}
    const m = document.getElementById(id);
    if (!m) return;
    m.classList.remove('active');
    m.setAttribute('aria-hidden', 'true');
    if (!$$('.modal-overlay.active').length) document.body.classList.remove('no-scroll');
}
function switchTab(key) {
    $$('.auth-tab').forEach(b => {
        const isThis = b.dataset.tab === key;
        b.classList.toggle('active', isThis);
        b.setAttribute('aria-selected', String(isThis));
    });
    document.getElementById('loginForm')?.classList.toggle('active',  key === 'login');
    document.getElementById('signupForm')?.classList.toggle('active', key === 'signup');
}

/* ── Şifre gücü ── */
function getStrength(p) {
    if (!p || p.length < 4) return 0;
    let s = 0;
    if (p.length >= 8)          s++;
    if (p.length >= 12)         s++;
    if (/[A-Z]/.test(p))        s++;
    if (/[0-9]/.test(p))        s++;
    if (/[^A-Za-z0-9]/.test(p)) s++;
    return s <= 1 ? 1 : s <= 3 ? 2 : 3;
}
function updateStrengthBar(val) {
    const bar   = document.getElementById('passStrBar');
    const label = document.getElementById('passStrLabel');
    const tips  = document.getElementById('passStrTips');
    const level = getStrength(val);
    if (bar) bar.className = 'pass-strength-bar ' + (['','weak','medium','strong'][level] || '');

    const LABELS = ['', 'Zayıf', 'Orta', 'Güçlü'];
    const TIPS = [
        '',
        // weak
        'Büyük harf veya rakam ekle',
        // medium
        'Özel karakter ekle (!@#) veya uzat',
        // strong
        '✓ Güvenli şifre',
    ];
    if (label) { label.textContent = val.length >= 4 ? LABELS[level] : ''; label.className = 'pass-strength-label ' + (LABELS[level] ? ['','weak','medium','strong'][level] : ''); }
    if (tips)  { tips.textContent  = val.length >= 4 ? TIPS[level]  : ''; }
}
function initEyes(container) {
    $$(container + ' .toggle-eye').forEach(btn => {
        if (btn.dataset.wbEyeBound) return; // çift bağlamayı önle
        btn.dataset.wbEyeBound = '1';
        btn.addEventListener('click', (e) => {
            e.stopPropagation(); // index.js delegated handler'ı tetikleme
            const inp = btn.closest('.password-wrap')?.querySelector('input');
            if (!inp) return;
            const show = inp.type === 'password';
            inp.type = show ? 'text' : 'password';
            btn.innerHTML = show ? '<i class="fa-regular fa-eye-slash"></i>' : '<i class="fa-regular fa-eye"></i>';
        });
    });
}

let _phone = '', _pass = '';

/* ── Kayıt taslağı (sessionStorage) ── */
const DRAFT_KEY = 'wb_signup_draft';

function saveDraft(step) {
    try {
        sessionStorage.setItem(DRAFT_KEY, JSON.stringify({ step, phone: _phone, ts: Date.now() }));
    } catch {}
}
function loadDraft() {
    try {
        const d = JSON.parse(sessionStorage.getItem(DRAFT_KEY) || 'null');
        if (!d) return null;
        // 30 dakikadan eski taslakları sil
        if (Date.now() - d.ts > 30 * 60 * 1000) { clearDraft(); return null; }
        return d;
    } catch { return null; }
}
function clearDraft() {
    try { sessionStorage.removeItem(DRAFT_KEY); } catch {}
}

/* Kayıt adımını devam ettir — window.AuthFlow.resumeSignup() */
function resumeSignup() {
    const draft = loadDraft();
    if (!draft) { openM('authModal'); switchTab('signup'); return; }
    _phone = draft.phone || '';
    switch (draft.step) {
        case 'pass':
            openM('passModal');
            document.querySelector('#passModal [name="password"]')?.focus();
            break;
        case 'name':
            openM('nameModal');
            initDOBPicker();
            break;
        case 'address':
            openM('nameModal'); // adrese gitmek için isim modalından geç
            initDOBPicker();
            break;
        default:
            openM('authModal');
            switchTab('signup');
    }
}
window.AuthFlow = { resumeSignup };

/* ── OTP State ── */
let _otpPhone = '';  // OTP gönderilen telefon
let _otpTimer = null;

function startOtpCountdown(btnResend, seconds = 60) {
    if (_otpTimer) clearInterval(_otpTimer);
    let remaining = seconds;
    if (btnResend) { btnResend.disabled = true; btnResend.textContent = `Tekrar Gönder (${remaining}s)`; }
    _otpTimer = setInterval(() => {
        remaining--;
        if (remaining <= 0) {
            clearInterval(_otpTimer);
            if (btnResend) { btnResend.disabled = false; btnResend.textContent = 'Kodu Tekrar Gönder'; }
        } else {
            if (btnResend) btnResend.textContent = `Tekrar Gönder (${remaining}s)`;
        }
    }, 1000);
}

async function sendOtp(phone, purpose = 'register') {
    try {
        const res = await api.post('/api/auth/send-otp.php', { phone, purpose });
        return res;
    } catch (e) {
        return { ok: false, error: e.message };
    }
}

/* ── Ana init ── */
export function initAuth() {
    if (initAuth._done) return;
    initAuth._done = true;

    initEyes('#loginForm');
    initEyes('#passForm');

    $$('.auth-tab').forEach(btn =>
        btn.addEventListener('click', () => switchTab(btn.dataset.tab === 'signup' ? 'signup' : 'login'))
    );
    $$('.modal-overlay .modal-close').forEach(btn => {
        const modal = btn.closest('.modal-overlay');
        if (modal) btn.addEventListener('click', () => closeM(modal.id));
    });
    $$('.modal-overlay').forEach(overlay =>
        overlay.addEventListener('click', e => { if (e.target === overlay) closeM(overlay.id); })
    );
    document.addEventListener('keydown', e => {
        if (e.key !== 'Escape') return;
        const open = $$('.modal-overlay.active');
        if (open.length) closeM(open[open.length - 1].id);
    });

    /* ADIM 1: telefon → OTP gönder */
    const signupForm = document.getElementById('signupForm');
    signupForm?.addEventListener('submit', async e => {
        e.preventDefault();
        const phoneRaw = signupForm.querySelector('[name="phone"]')?.value || '';
        const phone10  = normPhone(phoneRaw);
        if (phone10.length !== 10 || !phone10.startsWith('5')) {
            setErr('#signupError', '10 haneli TR numarası girin (5xxxxxxxxx)'); return;
        }
        clrErr('#signupError');
        const btn = signupForm.querySelector('button[type=submit]');
        setLoading(btn, true, 'Kod gönderiliyor...');
        try {
            // Telefon kayıtlı mı kontrol et
            try {
                const chk = await api.post(API_USER + '/check-phone.php', { phone: phone10 });
                if (chk.ok && chk.data?.available === false) {
                    switchTab('login');
                    const lp = document.querySelector('#loginForm [name="phone"]');
                    if (lp) lp.value = phoneRaw;
                    // Login tab'a geçince loginError'a yaz (signupError artık görünmüyor)
                    setTimeout(() => {
                        setErr('#loginError', '✓ Bu hesap zaten kayıtlı — giriş yapın.');
                    }, 50);
                    showToast('Bu numara zaten kayıtlı. Giriş yapın.', false);
                    return;
                }
            } catch {}

            // OTP gönder
            const otpRes = await sendOtp(phone10, 'register');
            if (!otpRes.ok) {
                setErr('#signupError', otpRes.error || 'SMS gönderilemedi, tekrar deneyin.');
                return;
            }

            _phone     = phone10;
            _otpPhone  = phone10;

            // Debug modunda kodu göster
            if (otpRes.debug_code) {
                showToast(`[DEBUG] Kodunuz: ${otpRes.debug_code}`, true);
            } else {
                showToast('Doğrulama kodu gönderildi!');
            }

            closeM('authModal');
            openM('otpModal');
            startOtpCountdown(document.getElementById('btnResendOtp'));
            document.querySelector('#otpModal input[name="code"], #otpModal .otp-input')?.focus();
        } finally { setLoading(btn, false); }
    });

    /* OTP doğrula */
    const otpForm = document.getElementById('otpForm');
    otpForm?.addEventListener('submit', async e => {
        e.preventDefault();
        const code = (otpForm.querySelector('input')?.value || '').trim();
        if (code.length !== 6) { setErr('#otpError', '6 haneli kodu girin'); return; }
        clrErr('#otpError');
        const btn = otpForm.querySelector('button[type=submit]');
        setLoading(btn, true, 'Doğrulanıyor...');
        try {
            const res = await api.post('/api/auth/verify-otp.php', { phone: _otpPhone, code, purpose: 'register' });
            if (!res.ok) { setErr('#otpError', res.error || 'Yanlış kod'); return; }
            // OTP doğrulandı — adımı kaydet (modal kapanırsa devam etsin)
            _phone = _otpPhone;
            saveDraft('pass');
            closeM('otpModal');
            openM('passModal');
            document.querySelector('#passModal [name="password"]')?.focus();
        } catch (err) {
            setErr('#otpError', err.message || 'Doğrulama başarısız');
        } finally { setLoading(btn, false); }
    });

    /* OTP tekrar gönder */
    document.getElementById('btnResendOtp')?.addEventListener('click', async () => {
        if (!_otpPhone) return;
        const res = await sendOtp(_otpPhone, 'register');
        if (res.ok) {
            showToast(res.debug_code ? `[DEBUG] Yeni kod: ${res.debug_code}` : 'Yeni kod gönderildi!');
            startOtpCountdown(document.getElementById('btnResendOtp'));
            setErr('#otpError', '');
        } else {
            showToast(res.error || 'Gönderilemedi', false);
        }
    });

    /* ADIM 2: şifre */
    const passForm = document.getElementById('passForm');
    if (passForm) {
        const nextBtn = document.getElementById('btnPassNext');
        function validatePass() {
            const p = passForm.querySelector('[name="password"]')?.value || '';
            const c = passForm.querySelector('[name="confirm"]')?.value  || '';
            updateStrengthBar(p);
            if (nextBtn) nextBtn.disabled = !(p.length >= PASS_MIN && (!c || p === c));
        }
        passForm.addEventListener('input', validatePass);
        validatePass();
        passForm.addEventListener('submit', e => {
            e.preventDefault();
            const p = passForm.querySelector('[name="password"]')?.value || '';
            const c = passForm.querySelector('[name="confirm"]')?.value  || '';
            if (p.length < PASS_MIN) { setErr('#passError', `Şifre en az ${PASS_MIN} karakter olmalı`); return; }
            if (c && p !== c)        { setErr('#passError', 'Şifreler eşleşmiyor'); return; }
            clrErr('#passError');
            _pass = p;
            saveDraft('name');
            closeM('passModal');
            openM('nameModal');
            initDOBPicker();
        });
        document.getElementById('btnBackPass')?.addEventListener('click', () => {
            closeM('passModal'); openM('authModal'); switchTab('signup');
        });
    }

    /* ADIM 3: kimlik */
    const nameForm = document.getElementById('nameForm');
    if (nameForm) {
        const nextBtn = document.getElementById('btnNameNext');
        function validateName() {
            const fn = (nameForm.querySelector('[name="firstName"]')?.value || '').trim();
            const ln = (nameForm.querySelector('[name="lastName"]')?.value  || '').trim();
            const bd = document.getElementById('birthdayInput')?.value || '';
            if (nextBtn) nextBtn.disabled = fn.length < 2 || ln.length < 2 || !bd;
        }
        nameForm.addEventListener('input', validateName);
        document.addEventListener('dob:selected', validateName);
        validateName();
        nameForm.addEventListener('submit', async e => {
            e.preventDefault();
            const fn = (nameForm.querySelector('[name="firstName"]')?.value || '').trim();
            const ln = (nameForm.querySelector('[name="lastName"]')?.value  || '').trim();
            const bd = document.getElementById('birthdayInput')?.value || '';
            if (fn.length < 2 || ln.length < 2) { setErr('#nameError', 'Ad ve soyad en az 2 karakter olmalı'); return; }
            if (!bd) { setErr('#nameError', 'Doğum tarihi seçiniz'); return; }
            clrErr('#nameError');
            saveDraft('address');
            closeM('nameModal');
            await prepareAddressModal();
            openM('addressModal');
        });
        document.getElementById('btnBackName')?.addEventListener('click', () => {
            closeM('nameModal'); openM('passModal');
        });
    }

    /* ADIM 4: adres */
    const addressForm = document.getElementById('addressForm');
    if (addressForm) {
        addressForm.addEventListener('submit', async e => {
            e.preventDefault();
            const fn           = (nameForm?.querySelector('[name="firstName"]')?.value || '').trim();
            const ln           = (nameForm?.querySelector('[name="lastName"]')?.value  || '').trim();
            const bd           = document.getElementById('birthdayInput')?.value       || '';
            const city         = document.getElementById('citySelect')?.value          || '';
            const district     = document.getElementById('districtSelect')?.value      || '';
            const neighborhood = document.getElementById('neighborhoodSelect')?.value  || '';
            if (!city || !district) { setErr('#addressError', 'İl ve ilçe zorunlu'); return; }
            const btn = document.getElementById('btnFinish');
            setLoading(btn, true, 'Kaydediliyor...');
            clrErr('#addressError');
            try {
                const res = await api.post(API_USER + '/register.php', {
                    phone: _phone, password: _pass,
                    firstName: fn, lastName: ln, birthday: toISOBirthday(bd),
                    city, district, neighborhood, smsOk: true, emailOk: false,
                });
                if (!res.ok) throw new Error(res.error);
                closeM('addressModal');
                _phone = ''; _pass = '';
                clearDraft();
                showToast('Kayıt tamamlandı, hoş geldin!');
                document.dispatchEvent(new Event('user:loggedin'));
                document.dispatchEvent(new Event('auth:userChanged'));
            } catch (err) {
                setErr('#addressError', err.message || 'Kayıt başarısız');
            } finally { setLoading(btn, false); }
        });
        document.getElementById('btnBackAddress')?.addEventListener('click', () => {
            closeM('addressModal'); openM('nameModal'); initDOBPicker();
        });
    }

    /* GİRİŞ */
    const loginForm = document.getElementById('loginForm');
    loginForm?.addEventListener('submit', async e => {
        e.preventDefault();
        const phoneRaw = loginForm.querySelector('[name="phone"]')?.value    || '';
        const phone10  = normPhone(phoneRaw);
        const pass     = loginForm.querySelector('[name="password"]')?.value || '';
        if (phone10.length !== 10) { setErr('#loginError', '10 haneli telefon girin'); return; }
        if (!pass)                  { setErr('#loginError', 'Şifre girin'); return; }
        const btn = loginForm.querySelector('button[type=submit]');
        setLoading(btn, true, 'Giriş yapılıyor...');
        clrErr('#loginError');
        try {
            const res = await api.post(API_USER + '/login.php', { phone: phone10, password: pass });
            if (!res.ok) throw new Error(res.error);
            closeM('authModal');
            showToast('Hoş geldin!');
            document.dispatchEvent(new Event('user:loggedin'));
            document.dispatchEvent(new Event('auth:userChanged'));
        } catch (err) {
            setErr('#loginError', err.message || 'Giriş başarısız');
        } finally { setLoading(btn, false); }
    });

    try { document.dispatchEvent(new Event('auth:ready')); } catch {}
}

/* ── DOB Picker ── */
let _dobInited = false;
async function initDOBPicker() {
    if (_dobInited) return;
    const hiddenInput = document.getElementById('birthdayInput');
    const triggerBtn  = document.getElementById('dobTriggerBtn');
    const display     = document.getElementById('dobDisplay');
    if (!hiddenInput) return;
    try {
        const { attachDOBPicker } = await import('./components/dob-picker.js');
        hiddenInput.style.cssText = 'pointer-events:auto;position:absolute;opacity:0;width:1px;height:1px';
        attachDOBPicker({ input: hiddenInput, years: { min: 1930, max: new Date().getFullYear() - 5 }, locale: 'tr', format: 'dd.MM.yyyy' });
        triggerBtn?.addEventListener('click', e => {
            e.preventDefault(); e.stopPropagation();
            hiddenInput.dispatchEvent(new MouseEvent('click', { bubbles: false, cancelable: true }));
        });
        function onDobChange() {
            const v = hiddenInput.value;
            if (v && display) { display.textContent = formatDobDisplay(v); triggerBtn?.classList.remove('placeholder'); }
            document.dispatchEvent(new Event('dob:selected'));
        }
        hiddenInput.addEventListener('change', onDobChange);
        hiddenInput.addEventListener('input',  onDobChange);
        _dobInited = true;
    } catch (err) {
        console.warn('DOB picker yüklenemedi', err);
        if (triggerBtn) triggerBtn.outerHTML = `<input type="date" name="birthday" id="birthdayInput" class="auth-input" style="margin-bottom:14px" max="${new Date().getFullYear() - 5}-12-31" />`;
    }
}

// dd.MM.yyyy veya yyyy-MM-dd → API icin yyyy-MM-dd formatina cevir
function toISOBirthday(v) {
    if (!v) return null;
    if (/^\d{4}-\d{2}-\d{2}$/.test(v)) return v; // zaten ISO
    if (/^\d{2}\.\d{2}\.\d{4}$/.test(v)) {
        const [d, m, y] = v.split('.');
        return `${y}-${m}-${d}`;
    }
    return null;
}

function formatDobDisplay(v) {
    if (!v) return 'Doğum Tarihi';
    const months = ['Ocak','Şubat','Mart','Nisan','Mayıs','Haziran','Temmuz','Ağustos','Eylül','Ekim','Kasım','Aralık'];
    const parts  = v.includes('.') ? v.split('.').map(Number) : v.split('-').map(Number).reverse();
    const [d, m, y] = parts;
    return `${d} ${months[m - 1] || m} ${y}`;
}

async function prepareAddressModal() {
    const city = document.getElementById('citySelect');
    const dSel = document.getElementById('districtSelect');
    const nSel = document.getElementById('neighborhoodSelect');
    const reset = (el, lbl) => { if (el) { el.innerHTML = `<option value="" disabled selected>${lbl}</option>`; el.disabled = true; } };
    reset(city, 'Şehir seçin'); reset(dSel, 'İlçe seçin'); reset(nSel, 'Mahalle seçin');
    try {
        const { attachTRLocationCombo } = await import('./components/select-combo.js');
        await attachTRLocationCombo({ citySelect: city, districtSelect: dSel, neighborhoodSelect: nSel });
    } catch {}
    function chkValid() { const btn = document.getElementById('btnFinish'); if (btn) btn.disabled = !(city?.value && dSel?.value); }
    city?.addEventListener('change', chkValid);
    dSel?.addEventListener('change', chkValid);
    chkValid();
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAuth);
} else {
    initAuth();
}