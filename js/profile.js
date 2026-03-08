/* ===================== [profile.js] PART 1/3 =====================
   profile.js — Profil sayfası (public) — KANONİK (YENİ)
   - Firebase SDK frontend'den kaldırıldı
   - Tüm veri işlemleri PHP backend /api üzerinden geçiyor
   - Randevu şeması: businesses/{bizId}/appointments (sunucuda)
   - 2025-12-07 – PHP proxy uyumlu sürüm
   - 2026-01-21 – DOM-ready + saat overlay slot düzeltmeleri
   ========================================================= */

"use strict";

/* =========================================================
   DOM Ready helper
   ========================================================= */
function onReady(fn) {
  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", fn, { once: true });
  } else {
    fn();
  }
}

/* =========================================================
   Backend API helper'ları
   ========================================================= */

const API_BASE = "/api";


// ── API Wrapper — window.WbApi üzerinden, wb_response data sarması otomatik açılır ──
async function apiGet(path, params) {
  const res = await window.WbApi.get(path, params);
  if (!res) return null;
  return (res && res.ok === true && res.data !== undefined) ? res.data : res;
}
async function apiPost(path, body) {
  const res = await window.WbApi.post(path, body);
  if (!res) return null;
  return (res && res.ok === true && res.data !== undefined) ? res.data : res;
}
// ─────────────────────────────────────────────────────────────────────


/* =========================================================
   Mini utils
   ========================================================= */

const $ = (s) => document.querySelector(s);
const TL = (v) => "₺" + Number(v || 0).toLocaleString("tr-TR");
const pad = (n) => n.toString().padStart(2, "0");
const fmtDate = (d) =>
  `${pad(d.getDate())} ${
    ["Oca", "Şub", "Mar", "Nis", "May", "Haz", "Tem", "Ağu", "Eyl", "Eki", "Kas", "Ara"][d.getMonth()]
  } ${d.getFullYear()}`;
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;
const escapeHTML = (s = "") =>
  String(s).replace(/[&<>"']/g, (m) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  }[m]));
const fmtPhoneTR = (p = "") => {
  const d = (p || "").replace(/\D/g, "");
  const nat = d.startsWith("90") ? d.slice(2) : d.slice(-10);
  return nat.length === 10
    ? `(${nat.slice(0, 3)}) ${nat.slice(3, 6)}-${nat.slice(6, 8)}-${nat.slice(8)}`
    : "-";
};

function parseHHMM(s = "") {
  const m = /^(\d{1,2}):?(\d{2})$/.exec(String(s).trim());
  if (!m) return { h: 0, m: 0, min: 0 };
  const h = Math.max(0, Math.min(23, Number(m[1])));
  const mm = Math.max(0, Math.min(59, Number(m[2])));
  return { h, m: mm, min: h * 60 + mm };
}

function addMinutesToTimeStr(timeStr = "00:00", add = 0) {
  const { h, m } = parseHHMM(timeStr);
  const total = h * 60 + m + Number(add || 0);
  const nh = ((Math.floor(total / 60) % 24) + 24) % 24;
  const nm = ((total % 60) + 60) % 60;
  return `${pad(nh)}:${pad(nm)}`;
}

function showToast(msg) {
  const wrap = document.getElementById("toastWrap");
  if (!wrap) return;
  const t = document.createElement("div");
  t.className = "toast show";
  t.innerHTML = `<span class="dot"></span>${escapeHTML(msg)}`;
  wrap.appendChild(t);
  setTimeout(() => {
    try {
      wrap.removeChild(t);
    } catch (_) {}
  }, 2400);
}

/* ==== Review modal içi inline hata helper'ları (çakışma vb.) ==== */
function showReviewError(msg) {
  const modal = document.getElementById("reviewOv");
  if (!modal) {
    showToast(msg);
    return;
  }
  let box = document.getElementById("reviewError");
  if (!box) {
    const btn = document.getElementById("confirmBook");
    if (!btn || !btn.parentElement) {
      showToast(msg);
      return;
    }
    box = document.createElement("div");
    box.id = "reviewError";
    box.className = "inline-error";
    box.style.margin = "8px 0 0";
    box.style.padding = "8px 10px";
    box.style.borderRadius = "8px";
    box.style.background = "#ffe5e5";
    box.style.color = "#b3261e";
    box.style.fontSize = "13px";
    box.style.fontWeight = "600";
    box.style.textAlign = "left";
    box.style.display = "none";
    box.style.border = "1px solid rgba(179,38,30,0.25)";
    btn.parentElement.insertBefore(box, btn);
  }
  box.textContent = msg;
  box.style.display = "block";
}
function clearReviewError() {
  const box = document.getElementById("reviewError");
  if (box) box.style.display = "none";
}

/* Kullanıcı randevu listesi URL'i */
const APPOINTMENTS_URL = "user-profile.html#appointments";

/* Kalıcı ayarlar (localStorage) */
const Persist = {
  get(k, def = null) {
    try {
      return JSON.parse(localStorage.getItem(k));
    } catch {
      return def;
    }
  },
  set(k, v) {
    try {
      localStorage.setItem(k, JSON.stringify(v));
    } catch {}
  },
  del(k) {
    try {
      localStorage.removeItem(k);
    } catch {}
  },
};

/* ---- Randevu cache temizleme (SEPET/SAAT/PERSONEL) ---- */
function clearBookingCache({ keepDate = true } = {}) {
  try {
    Persist.del("profile_cart");
    Persist.del("profile_selected_time");
    Persist.del("profile_staff");
    if (!keepDate) Persist.del("profile_selected_date");
  } catch {}
  // RAM state reset
  cart = [];
  selectedTime = "";
  selectedStaff = null;
}

/* =========================================================
   TZ helpers (Europe/Istanbul)
   ========================================================= */

const TZ = "Europe/Istanbul";

function nowInTZ(tz = TZ) {
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: tz,
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
    hour: "2-digit",
    minute: "2-digit",
    second: "2-digit",
    hour12: false,
  }).formatToParts(new Date());
  const get = (t) => Number(parts.find((p) => p.type === t)?.value || 0);
  const Y = get("year"),
    M = get("month"),
    D = get("day");
  const h = get("hour"),
    m = get("minute");
  return {
    Y,
    M,
    D,
    h,
    m,
    ymd: `${Y}-${String(M).padStart(2, "0")}-${String(D).padStart(2, "0")}`,
  };
}
function isTodayTZ(d, tz = TZ) {
  return ymd(d) === nowInTZ(tz).ymd;
}

/* ---- Randevu günü string & dakika helper'ları ---- */
const apptDayStr = (d) => ymd(d);
const apptTimeToMin = (timeStr) => parseHHMM(timeStr).min;

/* =========================================================
   Rezervasyon penceresi: bugün + 9 gün
   ========================================================= */

const BOOK_WINDOW_DAYS = 10;

function todayTZDate(tz = TZ) {
  const { Y, M, D } = nowInTZ(tz);
  return new Date(Y, M - 1, D); // 00:00
}

function bookingWindow(tz = TZ) {
  const start = todayTZDate(tz);
  const end = new Date(start);
  end.setDate(start.getDate() + (BOOK_WINDOW_DAYS - 1));
  return { start, end };
}
function clampToBookingWindow(d) {
  const { start, end } = bookingWindow();
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  if (x < start) return start;
  if (x > end) return end;
  return x;
}
function inBookingWindow(d) {
  const { start, end } = bookingWindow();
  const x = new Date(d.getFullYear(), d.getMonth(), d.getDate());
  return x >= start && x <= end;
}

/* =========================================================
   Booksy tarzı SHOPBAR yardımcıları
   ========================================================= */

let currentLogoSrc = "img/berber1.jpeg";

function updateShopbarFromDOM() {
  const sbNameEl = document.getElementById("sbName");
  const sbAddrEl = document.getElementById("sbAddr");
  const sbLogoEl = document.getElementById("sbLogo");
  if (sbNameEl)
    sbNameEl.textContent =
      (document.getElementById("bizName")?.textContent || "İşletmeniz").trim();
  if (sbAddrEl)
    sbAddrEl.textContent =
      (document.getElementById("infoAddr")?.textContent || "—").trim();
  try {
    if (sbLogoEl && currentLogoSrc) sbLogoEl.src = currentLogoSrc;
  } catch {}
}

function initScrollShopbar() {
  const mainNav = document.getElementById("mainNavbar");
  const shopNav = document.getElementById("shopNavbar");
  if (!mainNav || !shopNav) return;

  const bookBtn = document.getElementById("sbBookBtn");
  if (bookBtn && !bookBtn._bound) {
    bookBtn._bound = true;
    bookBtn.addEventListener("click", () => {
      const list = window.__lastServicesList || [];
      if (list.length) {
        renderServicePicker(list);
        window.showOv?.("svcOv");
      } else {
        document.getElementById("openAllServices")?.click();
      }
    });
  }

  let lastY = window.scrollY;
  const HIDE_AFTER = 80;
  const onScroll = () => {
    const y = window.scrollY;
    const goingDown = y > lastY;
    if (goingDown && y > HIDE_AFTER) mainNav.classList.add("nav--hidden");
    else mainNav.classList.remove("nav--hidden");
    const showShop = mainNav.classList.contains("nav--hidden");
    shopNav.classList.toggle("show", showShop);
    shopNav.setAttribute("aria-hidden", showShop ? "false" : "true");
    lastY = y;
  };
  window.addEventListener("scroll", onScroll, { passive: true });
  updateShopbarFromDOM();
  onScroll();
}

/* =========================================================
   Marka uyumlu LS anahtarları + migrasyon
   ========================================================= */

const PENDING_KEY_NEW = "webey_pending_appts";
const PENDING_KEY_OLD = "arat_pending_appts";
const CAL_EVT_KEY_NEW = "webey_calendar_events";
const CAL_EVT_KEY_OLD = "arat_calendar_events";

function migrateArrayKey(oldKey, newKey) {
  try {
    const oldVal = JSON.parse(localStorage.getItem(oldKey) || "null");
    const newVal = JSON.parse(localStorage.getItem(newKey) || "null");
    if (oldVal && !newVal) {
      localStorage.setItem(newKey, JSON.stringify(oldVal));
      localStorage.removeItem(oldKey);
    }
  } catch {}
}
migrateArrayKey(PENDING_KEY_OLD, PENDING_KEY_NEW);
migrateArrayKey(CAL_EVT_KEY_OLD, CAL_EVT_KEY_NEW);

/* =========================================================
   Staff-service cache & eşleştirme yardımcıları
   ========================================================= */

const _staffSvcCache = new Map(); // staffId -> [{id,name,...}, ...]

/* Servis kimliği: serviceId > id > code > slug > key */
const svcId = (s) =>
  String(s?.serviceId ?? s?.id ?? s?.code ?? s?.slug ?? s?.key ?? "")
    .trim()
    .toLowerCase();

const svcName = (s) => String(s?.name ?? "").trim().toLowerCase();
const svcCode = (s) => String(s?.code ?? "").trim().toLowerCase();

const slugifyName = (name = "") =>
  name
    .toString()
    .trim()
    .toLowerCase()
    .replace(/ç/g, "c")
    .replace(/ğ/g, "g")
    .replace(/ı/g, "i")
    .replace(/ö/g, "o")
    .replace(/ş/g, "s")
    .replace(/ü/g, "u")
    .replace(/[^a-z0-9]+/g, "-")
    .replace(/^-+|-+$/g, "");

const svcEquals = (a, b) => {
  const aId = svcId(a),
    bId = svcId(b);
  if (aId && bId && aId === bId) return true;

  const aCode = svcCode(a),
    bCode = svcCode(b);
  if (aCode && bCode && aCode === bCode) return true;

  const an = svcName(a),
    bn = svcName(b);
  if (an && bn && an === bn) return true;

  const as = slugifyName(an),
    bs = slugifyName(bn);
  return !!as && as === bs;
};

async function getStaffServicesCached(bizId, staffId) {
  if (_staffSvcCache.has(staffId)) return _staffSvcCache.get(staffId);
  const list = await fetchStaffServices(bizId, staffId);
  _staffSvcCache.set(staffId, list);
  return list;
}

async function eligibleStaffForCart(bizId, staffList, cartItems) {
  if (!Array.isArray(cartItems) || !cartItems.length) return staffList.slice();
  const out = [];
  for (const s of staffList) {
    const svcs = await getStaffServicesCached(bizId, s.id || s.uid);
    const ok = cartItems.every((w) => svcs.some((v) => svcEquals(w, v)));
    if (ok) out.push(s);
  }
  return out;
}

/* =========================================================
   Auth (PHP backend üzerinden)
   ========================================================= */

let currentUid = null;
let currentUser = null;

async function loadAuthStatus() {
  try {
    const data = await apiGet("/api/auth/getUser.php");
    if (data && data.uid) {
      currentUid = data.uid;
      currentUser = data;
    } else {
      currentUid = null;
      currentUser = null;
    }
  } catch (e) {
    currentUid = null;
    currentUser = null;
  }
}

/* Giriş / kayıt sonrası auth durumunu güncelle — sayfa yenilemeden çalışsın */
let _pendingBookingAfterLogin = false;

// wb-api-shim 401 aldığında bu event'i fırlatır — authModal'ı aç
window.addEventListener('wb:needsLogin', () => {
  _pendingBookingAfterLogin = true;
  openAuthModal();
});

document.addEventListener("user:loggedin", async () => {
  await loadAuthStatus();
  if (_pendingBookingAfterLogin && currentUid) {
    _pendingBookingAfterLogin = false;
    setTimeout(() => persistBookingAndGo(), 400); // kısa bekleme → randevuya otomatik devam
  }
});
document.addEventListener("auth:userChanged", () => loadAuthStatus());

/* ---- Auth modal ---- */
function openAuthModal() {
  const el = document.getElementById("authModal");
  if (!el) return;
  try {
    el.removeAttribute("hidden");
  } catch {}
  el.classList.add("active");
}
function closeAuthModal() {
  const el = document.getElementById("authModal");
  if (!el) return;
  el.classList.remove("active");
  try {
    el.setAttribute("hidden", "");
  } catch {}
}
function isAuthOpen() {
  const el = document.getElementById("authModal");
  if (!el) return false;
  return el.classList.contains("active") || !el.hasAttribute("hidden");
}

/* =========================================================
   Modal helpers
   ========================================================= */

window.showOv = (id) => {
  document.querySelectorAll(".modal-ov.show").forEach((ov) => {
    if (ov.id !== id) ov.classList.remove("show");
  });
  document.getElementById(id)?.classList.add("show");
};
window.closeOv = (id) => {
  if (id === "timeOv") releaseSlotLock();
  document.getElementById(id)?.classList.remove("show");
};

/* DOM-ready'de çağrılacak */
function attachModalBasics() {
  document.querySelectorAll(".modal-ov").forEach((ov) => {
    if (ov._basicBound) return;
    ov._basicBound = true;
    ov.addEventListener("click", (e) => {
      if (e.target === ov) ov.classList.remove("show");
    });
  });

  if (!document._profileEscBound) {
    document._profileEscBound = true;
    document.addEventListener("keydown", (e) => {
      if (e.key !== "Escape") return;
      const open = [...document.querySelectorAll(".modal-ov.show")].pop();
      if (open) open.classList.remove("show");
      if (isAuthOpen()) closeAuthModal();
    });
  }

  const authModal = document.getElementById("authModal");
  if (authModal && !authModal._bound) {
    authModal._bound = true;
    authModal.addEventListener("click", (e) => {
      if (e.target.closest(".modal-close")) {
        e.preventDefault();
        closeAuthModal();
      }
    });
  }
}

/* =========================================================
   Galeri + Lightbox  (DOM-ready'de init)
   ========================================================= */

function initSliderAndLightbox() {
  const slides = [...document.querySelectorAll("[data-slide]")];
  const dots = [...document.querySelectorAll(".dotbar .dot")];
  const next = document.getElementById("nextSlide");
  const prev = document.getElementById("prevSlide");

  if (slides.length && !document._profileSlideBound) {
    document._profileSlideBound = true;
    let i = 0;
    const show = (n) => {
      i = (n + slides.length) % slides.length;
      slides.forEach((im, k) => (im.style.display = k === i ? "block" : "none"));
      dots.forEach((d, k) => d.classList.toggle("active", k === i));
    };
    next?.addEventListener("click", () => show(i + 1));
    prev?.addEventListener("click", () => show(i - 1));
    dots.forEach((d, k) => d.addEventListener("click", () => show(k)));
    show(0);

    const lbImg = document.getElementById("lbImg");
    const lbPrev = document.getElementById("lbPrev");
    const lbNext = document.getElementById("lbNext");
    const imgClose = document.getElementById("imgClose");
    let list = [];
    let idx = 0;

    function rescan() {
      list = [...document.querySelectorAll("[data-enlarge]")];
      list.forEach((el, ii) => {
        if (!el._lbBound) {
          el.addEventListener("click", () => open(ii));
          el._lbBound = true;
        }
      });
    }
    function open(ii) {
      idx = (ii + list.length) % list.length;
      if (lbImg) {
        const el = list[idx];
        // data-orig varsa orijinal tam kalite göster, yoksa src kullan
        const fullSrc = el?.dataset?.orig || el?.src || "";
        lbImg.src = "";           // sıfırla (eski resim flash'ı önle)
        lbImg.src = fullSrc;
        lbImg.style.width = "100%";
        lbImg.style.height = "100%";
        lbImg.style.objectFit = "contain";
      }
      showOv("imgOv");
    }
    const step = (d) => open(idx + d);
    lbPrev?.addEventListener("click", () => step(-1));
    lbNext?.addEventListener("click", () => step(1));
    imgClose?.addEventListener("click", () => closeOv("imgOv"));

    if (!document._profileArrowBound) {
      document._profileArrowBound = true;
      document.addEventListener("keydown", (e) => {
        if (document.getElementById("imgOv")?.classList.contains("show")) {
          if (e.key === "ArrowLeft") lbPrev?.click();
          if (e.key === "ArrowRight") lbNext?.click();
          return;
        }
        if (e.key === "ArrowLeft") prev?.click();
        if (e.key === "ArrowRight") next?.click();
      });
    }

    window._lbRescan = rescan;
    rescan();
  }
}

/* =========================================================
   "Tüm haftayı göster" toggle (DOM-ready'de init)
   ========================================================= */

function initWeekToggle() {
  const scope = document.getElementById("hoursCard") || document;
  const blk = document.getElementById("weekBlock");
  if (blk) blk.toggleAttribute("hidden", !blk.classList.contains("show"));

  if (scope._weekToggleBound) return;
  scope._weekToggleBound = true;

  scope.addEventListener("click", (e) => {
    const btn = e.target.closest("#toggleWeek, [data-toggle='week']");
    if (!btn) return;
    e.preventDefault();
    const block = document.getElementById("weekBlock");
    if (!block) return;
    const open = block.classList.toggle("show");
    block.toggleAttribute("hidden", !open);
    btn.setAttribute("aria-expanded", open);
    const t =
      document.getElementById("toggleText") ||
      btn.querySelector("[data-txt]") ||
      btn;
    if (t) t.textContent = open ? "Gizle" : "Tüm haftayı göster";
    btn.querySelector(".chev")?.classList.toggle("rot", open);
  });
}

/* =========================================================
   Client Status Bar (dinamik)
   ========================================================= */

let _statusBarEl = null;
let _statusPollTimer = null;

function cleanupStatusWatchers() {
  if (_statusPollTimer) {
    clearInterval(_statusPollTimer);
    _statusPollTimer = null;
  }
}

function removeStatusBar() {
  if (_statusBarEl?.parentNode) {
    _statusBarEl.parentNode.removeChild(_statusBarEl);
  }
  _statusBarEl = null;
  cleanupStatusWatchers();
}

function showClientStatus({ type = "pending", text = "", ctaText = "", ctaHref = "#" } = {}) {
  if (!_statusBarEl) {
    _statusBarEl = document.createElement("div");
    _statusBarEl.className = "client-status";
    _statusBarEl.setAttribute("role", "status");
    _statusBarEl.setAttribute("aria-live", "polite");
    _statusBarEl.innerHTML = `
      <span class="icon" aria-hidden="true">⏳</span>
      <span class="text"></span>
      <a class="cta" href="#" target="_self" rel="noopener" style="display:none"></a>
      <button class="close" type="button" aria-label="Kapat">✕</button>
    `;
    document.body.appendChild(_statusBarEl);
    _statusBarEl.addEventListener("click", (e) => {
      if (e.target.closest(".close")) {
        e.preventDefault();
        removeStatusBar();
      }
    });
  }
  _statusBarEl.classList.remove("pending", "ok", "err");
  _statusBarEl.classList.add(type);
  const icon = _statusBarEl.querySelector(".icon");
  if (icon) icon.textContent = type === "ok" ? "✔" : type === "err" ? "✕" : "⏳";
  const txt = _statusBarEl.querySelector(".text");
  if (txt) txt.textContent = text || "";
  const cta = _statusBarEl.querySelector(".cta");
  if (ctaText) {
    cta.textContent = ctaText;
    cta.href = ctaHref || "#";
    cta.style.display = "inline-flex";
  } else if (cta) {
    cta.style.display = "none";
  }
}
function updateClientStatus(type, text, ctaText = "", ctaHref = "#") {
  if (_statusBarEl) showClientStatus({ type, text, ctaText, ctaHref });
}

function rememberPending(bizId, rid) {
  try {
    const oldArr = JSON.parse(localStorage.getItem(PENDING_KEY_OLD) || "[]");
    const newArr = JSON.parse(localStorage.getItem(PENDING_KEY_NEW) || "[]");
    const arr = [...oldArr, ...newArr];
    const dedup = [];
    const set = new Set();
    for (const x of arr) {
      const key = `${x.bizId}|${x.rid}`;
      if (!set.has(key)) {
        set.add(key);
        dedup.push(x);
      }
    }
    if (!dedup.find((x) => x.bizId === bizId && x.rid === rid)) dedup.push({ bizId, rid, ts: Date.now() });
    localStorage.setItem(PENDING_KEY_NEW, JSON.stringify(dedup));
    localStorage.removeItem(PENDING_KEY_OLD);
  } catch {}
}
function forgetPending(bizId, rid) {
  try {
    const arr = JSON.parse(localStorage.getItem(PENDING_KEY_NEW) || "[]").filter((x) => !(x.bizId === bizId && x.rid === rid));
    localStorage.setItem(PENDING_KEY_NEW, JSON.stringify(arr));
    localStorage.removeItem(PENDING_KEY_OLD);
  } catch {}
}

/* ---- Pending randevular için backend üzerinden status poll ---- */
async function resumePendingWatchersForCurrentBiz() {
  try {
    const merged = [
      ...(JSON.parse(localStorage.getItem(PENDING_KEY_OLD) || "[]")),
      ...(JSON.parse(localStorage.getItem(PENDING_KEY_NEW) || "[]")),
    ];
    const mine = (merged || []).filter((x) => x.bizId === currentBizId);
    let hasAnyStillPending = false;

    for (const it of mine) {
      attachStatusWatchers(it.bizId, it.rid);
      try {
        const res = await apiGet("/api/appointments/status.php", {
          businessId: it.bizId,
          id: it.rid,
        });
        const r = res.appointment || res.data || {};
        const st = String(r.status || "").toLowerCase();
        if (!r || !st || st !== "pending") {
          forgetPending(it.bizId, it.rid);
        } else {
          hasAnyStillPending = true;
        }
      } catch {
        forgetPending(it.bizId, it.rid);
      }
    }

    if (hasAnyStillPending) {
      showClientStatus({
        type: "pending",
        text: "Randevu talebiniz iletildi. Onay bekleniyor.",
        ctaText: "Randevumu görüntüle",
        ctaHref: APPOINTMENTS_URL,
      });
    } else {
      removeStatusBar();
    }
  } catch {}
}

/* =========================================================
   Booking State
   ========================================================= */

let selectedDate = new Date();
let selectedTime = ""; // "HH:MM"
let selectedStaff = null; // { id, name }
let currentBizId = "";
let currentHours = null;
let currentBooked = {};
let currentOwner = null;
let staffCount = 0;

/* ==== Staff hazır bekleme ==== */
let staffReady = false;
let _staffReadyWaiters = [];
function waitStaffReady() {
  return staffReady ? Promise.resolve() : new Promise((r) => _staffReadyWaiters.push(r));
}

/* DOM ref cache (DOM-ready'de set edilir) */
let timeSlotWrap = null;
let dayRail = null;

// ── Slot Kilitleme Durumu ────────────────────────────────
const SLOT_LOCK_TTL = 120; // saniye (lock.php ile eşleşmeli)
let slotLock = { token: null, expiresAt: null, timerId: null };

function _lockBannerEl()  { return document.getElementById("slotLockBanner"); }
function _lockSecEl()     { return document.getElementById("slotLockSec"); }
function _lockArcEl()     { return document.getElementById("slotLockArc"); }

function _ensureGlobalLockBar() {
  if (document.getElementById("wbGlobalLockBar")) return;
  const bar = document.createElement("div");
  bar.id = "wbGlobalLockBar";
  bar.setAttribute("aria-live", "polite");
  bar.setAttribute("role", "status");
  bar.style.cssText = [
    "display:none",
    "position:fixed",
    "top:0","left:0","right:0",
    "z-index:2147483647",
    "background:linear-gradient(90deg,#fff7ed 0%,#fff3e0 100%)",
    "border-bottom:2px solid #fed7aa",
    "padding:8px 16px",
    "box-shadow:0 2px 12px rgba(251,146,60,.25)",
    "font-family:inherit",
  ].join(";");
  bar.innerHTML = `
    <div style="display:flex;align-items:center;gap:10px;max-width:600px;margin:0 auto">
      <span style="font-size:18px;line-height:1" aria-hidden="true">🔒</span>
      <div style="flex:1;min-width:0">
        <div style="font-weight:700;color:#92400e;font-size:13px;line-height:1.3">Bu saat sizin için ayrıldı</div>
        <div style="color:#b45309;font-size:12px;line-height:1.3">
          Randevunuzu <strong><span id="wbGlobalLockSec">120</span> saniye</strong> içinde onaylayın
        </div>
      </div>
      <svg id="wbGlobalLockRing" width="40" height="40" viewBox="0 0 40 40"
           style="flex-shrink:0;transform:rotate(-90deg)" aria-hidden="true">
        <circle cx="20" cy="20" r="16" fill="none" stroke="#fed7aa" stroke-width="4.5"/>
        <circle id="wbGlobalLockArc" cx="20" cy="20" r="16" fill="none" stroke="#f97316"
          stroke-width="4.5" stroke-dasharray="100.5" stroke-dashoffset="0"
          stroke-linecap="round" style="transition:stroke-dashoffset .9s linear,stroke .3s"/>
      </svg>
      <div id="wbGlobalLockPct"
           style="font-size:15px;font-weight:800;color:#f97316;min-width:38px;text-align:right;line-height:1">
        120s
      </div>
    </div>
  `;
  document.body.appendChild(bar);
}

function startLockCountdown(expiresAt) {
  stopLockCountdown();
  _ensureGlobalLockBar();

  const banner      = _lockBannerEl();
  const secEl       = _lockSecEl();
  const arcEl       = _lockArcEl();
  const gBar        = document.getElementById("wbGlobalLockBar");
  const gSecEl      = document.getElementById("wbGlobalLockSec");
  const gArcEl      = document.getElementById("wbGlobalLockArc");
  const gPctEl      = document.getElementById("wbGlobalLockPct");
  const CIRC_LOCAL  = 94.2;   // r=15
  const CIRC_GLOBAL = 100.5;  // r=16

  if (banner) banner.style.display = "block";
  if (gBar)   gBar.style.display   = "block";

  function tick() {
    const remaining = Math.max(0, Math.round((expiresAt - Date.now()) / 1000));
    const pct       = remaining / SLOT_LOCK_TTL;

    // — timeOv içindeki yerel banner —
    if (secEl) secEl.textContent = remaining;
    if (arcEl) {
      arcEl.style.strokeDashoffset = String(CIRC_LOCAL * (1 - pct));
      arcEl.style.stroke = pct > 0.4 ? "#f97316" : pct > 0.15 ? "#ef4444" : "#dc2626";
    }

    // — tüm modallarda görünen global bar —
    const color = pct > 0.4 ? "#f97316" : pct > 0.15 ? "#ef4444" : "#dc2626";
    if (gSecEl) gSecEl.textContent = remaining;
    if (gPctEl) { gPctEl.textContent = remaining + "s"; gPctEl.style.color = color; }
    if (gArcEl) {
      gArcEl.style.strokeDashoffset = String(CIRC_GLOBAL * (1 - pct));
      gArcEl.style.stroke = color;
    }
    if (gBar && pct <= 0.25) {
      gBar.style.background = pct <= 0.15
        ? "linear-gradient(90deg,#fff1f2 0%,#ffe4e6 100%)"
        : "linear-gradient(90deg,#fff7ed 0%,#fef3c7 100%)";
      gBar.style.borderBottomColor = color;
    }

    if (remaining <= 0) {
      stopLockCountdown();
      slotLock.token = null;
      showToast("⏳ Süreniz doldu! Lütfen saati yeniden seçin.");
      selectedTime = null;
      document.querySelectorAll("#timeOv .slot").forEach(x => x.classList.remove("active"));
      const done = document.getElementById("timeDone");
      if (done) done.disabled = true;
      // Açık olan tüm booking modallarını kapat, saat seçimine geri dön
      ["staffOv","staffServicesOv","confirmOv"].forEach(id => {
        document.getElementById(id)?.classList.remove("show");
      });
      if (typeof closeAuthModal === "function") { try { closeAuthModal(); } catch {} }
      ["authModal","otpModal","passModal","nameModal","addressModal"].forEach(id => {
        const el = document.getElementById(id);
        if (el) { el.classList.remove("active"); el.setAttribute("hidden",""); el.setAttribute("aria-hidden","true"); }
      });
      setTimeout(() => window.showOv?.("timeOv"), 200);
    }
  }

  tick();
  slotLock.timerId = setInterval(tick, 1000);
}

function stopLockCountdown() {
  if (slotLock.timerId) { clearInterval(slotLock.timerId); slotLock.timerId = null; }
  const banner = _lockBannerEl();
  if (banner) banner.style.display = "none";
  const gBar = document.getElementById("wbGlobalLockBar");
  if (gBar) {
    gBar.style.display = "none";
    gBar.style.background = "linear-gradient(90deg,#fff7ed 0%,#fff3e0 100%)";
    gBar.style.borderBottomColor = "#fed7aa";
  }
}

async function acquireSlotLock(bizId, staffId, dayStr, startMin, durationMin) {
  // Önceki kilidi serbest bırak
  if (slotLock.token) {
    const prevToken = slotLock.token;
    slotLock.token = null;
    stopLockCountdown();
    try { await apiPost("/api/appointments/unlock.php", { token: prevToken }); } catch {}
  }
  try {
    const res = await apiPost("/api/appointments/lock.php", {
      businessId: bizId,
      staffId: staffId ?? "any",
      dayStr,
      startMin,
      durationMin,
      token: slotLock.token ?? ""
    });
    // apiPost artık wb_ok sarmasını açıp data içeriğini döndürüyor
    // res = { token:'...', expiresAt:'...', expiresInSec:120 }
    if (res && (res.token || res.lock_token)) {
      slotLock.token = res.token ?? res.lock_token ?? null;
      const rawExpires = res.expiresAt ?? res.expires_at ?? null;
      const ttlSec     = Number(res.expiresInSec ?? res.expires_in_sec ?? SLOT_LOCK_TTL);
      slotLock.expiresAt = rawExpires
        ? new Date(rawExpires).getTime()
        : Date.now() + ttlSec * 1000;
      if (!Number.isFinite(slotLock.expiresAt) || isNaN(slotLock.expiresAt)) {
        slotLock.expiresAt = Date.now() + SLOT_LOCK_TTL * 1000;
      }
      startLockCountdown(slotLock.expiresAt);
    }
    // lock alınamadıysa (conflict/locked) kullanıcıya bildir
    if (res && res.ok === false) {
      const code = res.code || '';
      if (code === 'locked' || code === 'conflict') {
        showToast('🚫 Bu saat şu an başka biri tarafından seçildi. Lütfen farklı saat deneyin.');
        selectedTime = null;
        document.querySelectorAll('#timeOv .slot').forEach(x => x.classList.remove('active'));
      }
    }
  } catch (err) {
    // Ağ hatası vb. — kilit olmadan devam et
  }
}

async function releaseSlotLock() {
  stopLockCountdown();
  if (!slotLock.token) return;
  const token = slotLock.token;
  slotLock.token = null;
  slotLock.expiresAt = null;
  try { await apiPost("/api/appointments/unlock.php", { token }); } catch {}
}
// ───────────────────────────────────────────────────────────────────────────

function cacheProfileDomRefs() {
  timeSlotWrap = document.getElementById("timeSlotWrap");
  dayRail = document.getElementById("dayRail");
}

/* Periyot filtresi İPTAL */
let periodFilter = "all";

/* =========================================================
   Çalışma Saatleri Yardımcıları
   ========================================================= */

const TR_DAYS = ["Pazar", "Pazartesi", "Salı", "Çarşamba", "Perşembe", "Cuma", "Cumartesi"];
const EN_SHORT = ["sun", "mon", "tue", "wed", "thu", "fri", "sat"];

function m2t(min) {
  const h = Math.floor((min || 0) / 60);
  const m = (min || 0) % 60;
  return `${pad(h)}:${pad(m)}`;
}

function normalizeWorkingHours(src) {
  if (!src || typeof src !== "object") return null;
  const out = {};
  TR_DAYS.forEach((day, idx) => {
    const h =
      src[day] ??
      src[day?.toLowerCase?.()] ??
      src[idx] ??
      src[String(idx)] ??
      src[EN_SHORT[idx]] ??
      src[EN_SHORT[idx]?.toUpperCase?.()] ??
      null;
    if (!h) {
      out[idx] = [];
      return;
    }
    if (Array.isArray(h)) {
      out[idx] = h;
      return;
    }
    if (typeof h === "string") {
      out[idx] = [h];
      return;
    }
    if ("ranges" in h && Array.isArray(h.ranges)) {
      const segs =
        h.open === false
          ? []
          : h.ranges
              .map((r) => `${m2t(r.startMin)} - ${m2t(r.endMin)}`)
              .filter(Boolean);
      out[idx] = segs;
      return;
    }
    if ("closed" in h && h.closed === true) {
      out[idx] = [];
      return;
    }
    if ("open" in h && h.open === false) {
      out[idx] = [];
      return;
    }
    const openS = h.open || h.from || h.start || h.openFrom;
    const closeS = h.close || h.to || h.end || h.openTo;
    const segs = [];
    if (openS && closeS) segs.push(`${openS} - ${closeS}`);
    const o2 = h.open2 || h.openFrom2,
      c2 = h.close2 || h.openTo2;
    if (o2 && c2) segs.push(`${o2} - ${c2}`);
    out[idx] = segs;
  });
  return out;
}

function openSegmentsForDate(hours, dateObj) {
  if (!hours) return [];
  const norm = normalizeWorkingHours(hours);
  if (!norm) return [];
  const segStrs = norm[dateObj.getDay()] || [];
  const segs = [];
  for (const s of segStrs) {
    const m = /^\s*(\d{1,2}:\d{2})\s*-\s*(\d{1,2}:\d{2})\s*$/.exec(s);
    if (!m) continue;
    const a = parseHHMM(m[1]).min;
    const b = parseHHMM(m[2]).min;
    if (a < b) segs.push([a, b]);
  }
  return segs;
}

function generateOpenHours(hours, dateObj) {
  const segs = openSegmentsForDate(hours, dateObj);
  const hoursOut = [];
  for (const [a, b] of segs) {
    const startH = Math.ceil(a / 60);
    const endH = Math.floor((b - 1) / 60);
    for (let h = startH; h <= endH; h++) hoursOut.push(h);
  }
  return [...new Set(hoursOut)];
}
/* ===================== [profile.js] PART 2/3 ===================== */
/* =========================================================
   Doluluk Okuma (PHP backend üzerinden)
   ========================================================= */

const BLOCKING_STATES = new Set(["pending", "confirmed", "approved"]);

/**
 * GET /api/appointments/booked-map.php
 *  ?businessId=...&date=YYYY-MM-DD&staffId=...
 * Response:
 *  { success:true, map:{ "10":[0,15,30], "11":[0] } }
 */
async function fetchBookedMap(bizId, dateObj, staffId = null, lockToken = null) {
  if (!bizId) return {};
  try {
    const res = await apiGet("/api/appointments/booked-map.php", {
      businessId: bizId,
      date: apptDayStr(dateObj),
      staffId: staffId || "",
      // Kullanıcının kendi slotunu meşgul görmemesi için token gönder
      ...(lockToken ? { lockToken } : {}),
    });
    const raw = res.map || res.data || {};
    const out = {};
    Object.keys(raw).forEach((hStr) => {
      const h = Number(hStr);
      if (!Number.isFinite(h)) return;
      const arr = raw[hStr];
      const set = new Set(Array.isArray(arr) ? arr.map((v) => Number(v) || 0) : []);
      out[h] = set;
    });
    return out;
  } catch (e) {
    console.warn("[fetchBookedMap] api error:", e);
    return {};
  }
}

/**
 * GET /api/appointments/counters.php
 *  ?businessId=...&date=YYYY-MM-DD
 * Response:
 *  { success:true, counters:{ "10": { "0":1,"15":2,"30":1,"45":0 }, ... } }
 */
async function fetchBookedCounters(bizId, dateObj) {
  if (!bizId) return {};
  try {
    const res = await apiGet("/api/appointments/counters.php", {
      businessId: bizId,
      date: apptDayStr(dateObj),
    });
    const raw = res.counters || res.data || {};
    const map = {};
    Object.keys(raw).forEach((hStr) => {
      const h = Number(hStr);
      if (!Number.isFinite(h)) return;
      const row = raw[hStr] || {};
      map[h] = {
        0: Number(row[0] ?? row["0"] ?? 0),
        15: Number(row[15] ?? row["15"] ?? 0),
        30: Number(row[30] ?? row["30"] ?? 0),
        45: Number(row[45] ?? row["45"] ?? 0),
      };
    });
    return map;
  } catch (e) {
    console.warn("[fetchBookedCounters] api error:", e);
    return {};
  }
}

function hasBusinessCapacity(counterMap, startH, startM, blocks) {
  const quarters = [0, 15, 30, 45];
  const cap = Math.max(staffCount, 1);
  for (let i = 0; i < blocks; i++) {
    const t = startH * 60 + startM + i * 15;
    const hh = Math.floor(t / 60);
    const mm = quarters[Math.floor((t % 60) / 15)];
    const used = counterMap[hh]?.[mm] || 0;
    if (used >= cap) return false;
  }
  return true;
}

/* ===== yardımcı: bloklar boş mu? ===== */
function isWithinWorkingHours(startMin, durationMin) {
  const segs = openSegmentsForDate(currentHours, selectedDate);
  const endMin = startMin + durationMin;
  return segs.some(([a, b]) => startMin >= a && endMin <= b);
}
function isBlocksFree(map, startH, startM, blocks) {
  const quarters = [0, 15, 30, 45];
  for (let i = 0; i < blocks; i++) {
    const t = startH * 60 + startM + i * 15;
    const hh = Math.floor(t / 60);
    const mm = quarters[Math.floor((t % 60) / 15)];
    if ((map[hh] || new Set()).has(mm)) return false;
  }
  return true;
}

/* =========================================================
   Saat Grid & Tarih Şeridi
   ========================================================= */

const isToday = (d) => {
  const t = new Date();
  return d.getFullYear() === t.getFullYear() && d.getMonth() === t.getMonth() && d.getDate() === t.getDate();
};

/* ---- Tarih filtresi: #dateFrom, #dateTo, #dateApply ---- */
function attachDateFilterControls() {
  const from = $("#dateFrom");
  const to = $("#dateTo");
  const apply = $("#dateApply");

  const { start, end } = bookingWindow();
  const startStr = ymd(start),
    endStr = ymd(end);

  if (from) {
    from.value = startStr;
    from.min = startStr;
    from.max = endStr;
    from.disabled = true;
  }
  if (to) {
    to.value = endStr;
    to.min = startStr;
    to.max = endStr;
    to.disabled = true;
  }

  selectedDate = clampToBookingWindow(selectedDate);

  function applyRange() {
    buildDayRail(start, BOOK_WINDOW_DAYS);
    setDateLabel();
    refreshHourGrid();
  }

  apply?.addEventListener("click", (e) => {
    e.preventDefault();
    applyRange();
  });
  applyRange();
}

function buildDayRail(baseDate = todayTZDate(), days = BOOK_WINDOW_DAYS) {
  if (!dayRail) return;
  dayRail.innerHTML = "";

  const { start } = bookingWindow();
  baseDate = start;
  days = BOOK_WINDOW_DAYS;

  for (let i = 0; i < days; i++) {
    const d = new Date(baseDate);
    d.setDate(baseDate.getDate() + i);
    const btn = document.createElement("button");
    btn.className = "day-pill" + (ymd(d) === ymd(selectedDate) ? " active" : "");
    btn.dataset.date = ymd(d);
    btn.innerHTML = `<div style="font-weight:800">${
      ["Paz", "Pts", "Sal", "Çar", "Per", "Cum", "Cts"][d.getDay()]
    }</div>
                     <div>${pad(d.getDate())}/${pad(d.getMonth() + 1)}</div>`;
    btn.addEventListener("click", async () => {
      selectedDate = d;
      selectedTime = "";
      Persist.set("profile_selected_date", ymd(selectedDate));
      Persist.del("profile_selected_time");
      document.querySelectorAll(".day-pill").forEach((x) => x.classList.toggle("active", x === btn));
      setDateLabel();
      await refreshHourGrid();
    });
    dayRail.appendChild(btn);
  }
}

/* ---- Doluluk seviyesi yardımcıları ---- */
function occupancyLevelForHour(counterMap, h) {
  const cap = Math.max(staffCount, 1);
  const q = counterMap[h] || { 0: 0, 15: 0, 30: 0, 45: 0 };
  const ratios = [q[0], q[15], q[30], q[45]].map((v) => Math.min(1, (v || 0) / cap));
  const maxr = Math.max(...ratios);
  if (maxr === 0) return 0;
  if (maxr <= 0.25) return 1;
  if (maxr <= 0.5) return 2;
  if (maxr <= 0.75) return 3;
  return 4;
}
function titleForHour(counterMap, h) {
  const cap = Math.max(staffCount, 1);
  const q = counterMap[h] || { 0: 0, 15: 0, 30: 0, 45: 0 };
  const parts = [0, 15, 30, 45].map((m) => `${pad(h)}:${pad(m)} • ${Math.min(q[m] || 0, cap)}/${cap}`);
  return "Doluluk (çeyrek bazında):\n" + parts.join("\n");
}

/* === Saat filtresi (sabah/öğlen/akşam) KALDIRILDI === */
function removePeriodChips() {
  const periodsWrap = document.querySelector("#timeOv .periods");
  if (periodsWrap) periodsWrap.innerHTML = "";
  periodFilter = "all";
  Persist.set("profile_period", "all");
}

/* ==== Staff hazır olmadan grid çizme & TZ kontrolleri ==== */
async function refreshHourGrid() {
  if (!timeSlotWrap) return;
  if (currentBizId && !staffReady) {
    timeSlotWrap.innerHTML = `<div class="muted" style="padding:8px 2px">Personel yükleniyor…</div>`;
    waitStaffReady().then(() => {
      try {
        refreshHourGrid();
      } catch {}
    });
    return;
  }

  const counterMap = currentBizId ? await fetchBookedCounters(currentBizId, selectedDate) : {};
  currentBooked = counterMap;

  const allHours = generateOpenHours(currentHours, selectedDate);
  timeSlotWrap.innerHTML = "";
  const hours = allHours;

  if (!hours.length) {
    timeSlotWrap.innerHTML = `<div class="muted" style="padding:8px 2px">Seçili gün kapalı.</div>`;
    return;
  }

  const nowTZ = nowInTZ();
  hours.forEach((h) => {
    const btn = document.createElement("button");
    btn.type = "button";
    btn.className = "slot";
    btn.textContent = `${pad(h)}:00`;

    const allFull =
      (counterMap[h]?.[0] || 0) >= Math.max(staffCount, 1) &&
      (counterMap[h]?.[15] || 0) >= Math.max(staffCount, 1) &&
      (counterMap[h]?.[30] || 0) >= Math.max(staffCount, 1) &&
      (counterMap[h]?.[45] || 0) >= Math.max(staffCount, 1);

    const lvl = occupancyLevelForHour(counterMap, h);
    btn.dataset.lvl = String(lvl);
    btn.classList.add(`lvl-${lvl}`);
    btn.title = titleForHour(counterMap, h);
    if (allFull) {
      btn.disabled = true;
      btn.title = "Bu saatte tüm personeller dolu";
    }

    if (isTodayTZ(selectedDate)) {
      if (h < nowTZ.h) btn.disabled = true;
      if (h === nowTZ.h && nowTZ.m > 45) btn.disabled = true;
    }

    btn.addEventListener("click", () => openMinutePicker(h));
    timeSlotWrap.appendChild(btn);
  });
}

function openMinutePicker(hour) {
  if (!timeSlotWrap) return;
  const quarters = [0, 15, 30, 45];
  timeSlotWrap.innerHTML = "";

  const totalMin = cartTotalMin() || 30;
  const blocks = Math.max(1, Math.ceil(totalMin / 15));

  const title = document.getElementById("timeTtl");
  if (title)
    title.innerHTML = `<span id="timeDateLabel">${fmtDate(selectedDate)}</span> • Dakika seç`;

  document.getElementById("minBack")?.remove();
  const back = document.createElement("button");
  back.id = "minBack";
  back.type = "button";
  back.className = "btn-mini";
  back.style.margin = "0 0 10px 2px";
  back.textContent = "← Saatlere dön";
  back.addEventListener("click", () => {
    const t = document.getElementById("timeTtl");
    if (t) t.innerHTML = `<span id="timeDateLabel">${fmtDate(selectedDate)}</span> • Saat seç`;
    back.remove();
    refreshHourGrid();
  });
  timeSlotWrap.parentElement?.prepend(back);

  const nowTZ = nowInTZ();
  const cap = Math.max(staffCount, 1);
  quarters.forEach((m) => {
    const b = document.createElement("button");
    b.type = "button";
    b.className = "slot";
    b.textContent = `${pad(hour)}:${pad(m)}`;

    const startMin = hour * 60 + m;
    const within = isWithinWorkingHours(startMin, totalMin);
    let disabled = !within;

    if (!disabled) {
      disabled = !hasBusinessCapacity(currentBooked, hour, m, blocks);
    }

    if (isTodayTZ(selectedDate)) {
      const nowMinTZ = nowTZ.h * 60 + nowTZ.m;
      if (startMin <= nowMinTZ) disabled = true;
    }

    const used = currentBooked[hour]?.[m] || 0;
    const ratio = Math.min(1, used / cap);
    const lvl = ratio === 0 ? 0 : ratio <= 0.25 ? 1 : ratio <= 0.5 ? 2 : ratio <= 0.75 ? 3 : 4;

    b.dataset.lvl = String(lvl);
    b.classList.add(`lvl-${lvl}`);
    b.title = `Doluluk: ${Math.round(ratio * 100)}% (${Math.min(used, cap)}/${cap})`;

    b.disabled = disabled;
    if (disabled) b.title = (b.title ? b.title + " • " : "") + "Dolu veya uygun değil";

    if (selectedTime === `${pad(hour)}:${pad(m)}`) b.classList.add("active");

    b.addEventListener("click", () => {
      if (b.disabled) return;
      selectedTime = `${pad(hour)}:${pad(m)}`;
      Persist.set("profile_selected_time", selectedTime);
      document.querySelectorAll("#timeOv .slot").forEach((x) => x.classList.remove("active"));
      b.classList.add("active");
      const done = document.getElementById("timeDone");
      if (done) done.disabled = false;
      $("#timeDone")?.focus();
      // Slot kilitle (120 sn)
      const _sMin = hour * 60 + m;
      const _dur  = cartTotalMin() || 30;
      const _day  = apptDayStr(selectedDate);
      acquireSlotLock(currentBizId, selectedStaff?.id ?? null, _day, _sMin, _dur);
    });

    timeSlotWrap.appendChild(b);
  });
}

/* Tarih etiketi */
function setDateLabel() {
  const lbl = document.getElementById("timeDateLabel");
  if (lbl) lbl.textContent = fmtDate(selectedDate);
}

/* =========================================================
   Sepet & Servis Seçimi
   ========================================================= */

let cart = []; // {serviceId?, name, price, duration}
function cartTotal() {
  return cart.reduce((s, i) => s + Number(i.price || 0), 0);
}
function cartTotalMin() {
  return cart.reduce((m, i) => m + (i.duration || i.min || 30), 0);
}

/**
 * Hizmet seçici
 * mode:
 *   - "new"  : yeni randevu akışı (hizmet → saat seçimi)
 *   - "edit" : inceleme ekranından "Başka hizmet ekle" ile geldiğinde
 */
function renderServicePicker(list = [], opts = {}) {
  const { mode = "new" } = opts;

  const container = $("#svcList");
  const totalEl = $("#svcTotal");
  const btnNext = $("#svcContinue");
  if (!container) return;

  let tempSel = [...cart];

  const btnStyle = (pressed = false) =>
    `border:0;border-radius:10px;padding:8px 12px;font-weight:800;cursor:pointer;` +
    (pressed ? `background:#0aa36b;color:#fff;` : `background:#111;color:#fff;`);

  const render = (q = "") => {
    const qLower = (q || "").toLowerCase();
    const f = list
      .filter((s) => (s.name || "").toLowerCase().includes(qLower))
      .map((s) => {
        const duration = Number(s.duration ?? s.min ?? s.durationMin ?? 30);
        const price = Number(s.price ?? 0);
        const key = `${s.name || "Hizmet"}:${duration}:${price}`;
        const _sid = s.serviceId || s.id || s.code || s.slug || s.key || "";
        return { ...s, duration, price, key, _sid };
      });

    container.innerHTML = f
      .map(
        (s) => `
      <div class="svc-item" style="display:flex;justify-content:space-between;gap:10px;align-items:center">
        <div>
          <div style="font-weight:700">${escapeHTML(s.name || "Hizmet")}</div>
          <div class="meta">${escapeHTML(String(s.duration || 30))}dk</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="font-weight:800">${TL(s.price ?? 0)}</div>
          <button class="btn-select"
                  data-key='${escapeHTML(s.key)}'
                  data-sid='${escapeHTML(String(s._sid || ""))}'
                  aria-pressed="${tempSel.some((x) => `${x.name}:${x.duration}:${x.price}` === s.key)}"
                  style="${btnStyle(tempSel.some((x) => `${x.name}:${x.duration}:${x.price}` === s.key))}">
            ${tempSel.some((x) => `${x.name}:${x.duration}:${x.price}` === s.key) ? "Seçildi" : "Seç"}
          </button>
        </div>
      </div>`
      )
      .join("");

    container.querySelectorAll(".btn-select").forEach((btn) => {
      btn.addEventListener("click", () => {
        const key = btn.dataset.key;
        const sid = btn.dataset.sid || "";
        const [nm, minStr, priceStr] = key.split(":");
        const duration = Number(minStr);
        const price = Number(priceStr);
        const idx = tempSel.findIndex((x) => `${x.name}:${x.duration}:${x.price}` === key);
        const pressed = idx === -1;
        if (pressed) {
          tempSel.push({
            serviceId: sid || undefined,
            name: nm,
            duration,
            price,
          });
        } else {
          tempSel.splice(idx, 1);
        }
        btn.setAttribute("aria-pressed", String(pressed));
        btn.textContent = pressed ? "Seçildi" : "Seç";
        btn.setAttribute("style", btnStyle(pressed));
        updateFooter();
      });
    });

    updateFooter();
  };

  const updateFooter = () => {
    const tot = tempSel.reduce((s, i) => s + Number(i.price || 0), 0);
    if (totalEl) totalEl.textContent = TL(tot);
    if (btnNext) btnNext.disabled = tempSel.length === 0;
  };

  let searchDebounce;
  $("#svcSearch")?.addEventListener("input", (e) => {
    clearTimeout(searchDebounce);
    const val = e.target.value;
    searchDebounce = setTimeout(() => render(val), 150);
  });
  btnNext?.addEventListener("click", async () => {
    cart = [...tempSel];
    Persist.set("profile_cart", cart);
    try {
      const elig = await eligibleStaffForCart(currentBizId, window.__staffList || [], cart);
      if (!elig.length) {
        showToast("Bu hizmet(ler)i yapabilen personel bulunamadı. Lütfen farklı hizmet seçin.");
        return;
      }
      if (selectedStaff?.id) {
        const ok = elig.some((s) => (s.id || s.uid) === selectedStaff.id);
        if (!ok) {
          selectedStaff = null;
          Persist.del("profile_staff");
          showToast("Seçili personel bu hizmet(ler) için uygun değil. Personel seçimi sıfırlandı.");
        }
      }
    } catch {}

    closeOv("svcOv");

    if (mode === "edit" && selectedTime) {
      buildReviewFromCurrent();
      showOv("reviewOv");
      return;
    }

    buildDayRail(todayTZDate(), BOOK_WINDOW_DAYS);
    setDateLabel();
    await refreshHourGrid();
    showOv("timeOv");
  });

  render();
}

/* Hizmet satırından direkt randevu */
function bindOpenBookButtons() {
  document.querySelectorAll(".open-book").forEach((b) => {
    if (b._bound) return;
    b._bound = true;

    b.addEventListener("click", async () => {
      const svc = {
        serviceId: b.dataset.sid || undefined,
        name: b.dataset.name,
        price: Number(b.dataset.price || 0),
        duration: Number(b.dataset.duration || b.dataset.min || 30),
      };
      cart = [svc];
      Persist.set("profile_cart", cart);

      if (currentBizId) {
        try {
          const elig = await eligibleStaffForCart(currentBizId, window.__staffList || [], cart);
          if (selectedStaff?.id) {
            const ok = elig.some((s) => (s.id || s.uid) === selectedStaff.id);
            if (!ok) {
              selectedStaff = null;
              Persist.del("profile_staff");
              showToast("Seçili personel bu hizmet için uygun değil. Personel seçimi sıfırlandı.");
            }
          }
        } catch {}
      }
      buildDayRail(todayTZDate(), BOOK_WINDOW_DAYS);
      setDateLabel();
      await refreshHourGrid();
      showOv("timeOv");
    });
  });
}

/* =========================================================
   Zaman adımı butonları (DOM-ready'de bağlanacak)
   ========================================================= */

function bindTimeDone() {
  const btn = document.getElementById("timeDone");
  if (!btn || btn._bound) return;
  btn._bound = true;

  btn.addEventListener("click", async () => {
    if (!selectedTime) {
      showToast("Lütfen bir saat seçin");
      return;
    }
    if (selectedStaff?.id || selectedStaff?.name) {
      buildReviewFromCurrent();
      showOv("reviewOv");
      return;
    }
    renderStaffPicker(window.__staffList || []);
    showOv("staffOv");
  });
}
/* ===================== [profile.js] PART 3/3 ===================== */
/* =========================================================
   Personeller
   ========================================================= */

function initials(name = "") {
  const parts = name.trim().split(/\s+/).slice(0, 2);
  return parts.map((p) => p[0]?.toUpperCase() || "").join("");
}

function ensureStaffServicesModal() {
  if (document.getElementById("staffServicesOv")) return;
  const ov = document.createElement("div");
  ov.className = "modal-ov";
  ov.id = "staffServicesOv";
  ov.innerHTML = `
    <div class="modal" role="dialog" aria-modal="true" aria-labelledby="staffSvcTtl">
      <div class="hd">
        <div class="ttl" id="staffSvcTtl">Personel</div>
        <button class="x" type="button" aria-label="Kapat">✕</button>
      </div>
      <div class="ct" id="staffSvcBody"><div class="muted">Yükleniyor…</div></div>
    </div>`;
  document.body.appendChild(ov);
  ov.addEventListener("click", (e) => {
    if (e.target === ov) ov.classList.remove("show");
  });
  ov.querySelector(".x")?.addEventListener("click", () => ov.classList.remove("show"));
}

function renderStaff(list = []) {
  const wrap = $("#staffWrap");
  if (!wrap) return;
  if (!Array.isArray(list) || list.length === 0) {
    wrap.innerHTML = `<div class="muted small">Henüz personel eklenmemiş.</div>`;
    return;
  }
  wrap.innerHTML = `
    <div class="staff-list">
      ${list
        .map((s) => {
          const id = s.id || s.uid || "";
          const name = s.name || "Personel";
          const purl = s.photoOpt || s.photoUrl || s.photoURL || "";
          const init = initials(name);
          const avatar = purl
            ? `<img src="${escapeHTML(purl)}" alt="${escapeHTML(name)}">`
            : `<span class="initial">${escapeHTML(init || "?")}</span>`;
          return `
          <div class="staff" role="button" tabindex="0"
               data-staff-id="${escapeHTML(id)}"
               data-staff-name="${escapeHTML(name)}"
               data-staff-photo="${escapeHTML(purl)}">
            <div class="avatar">${avatar}</div>
            <div class="sname">${escapeHTML(name)}</div>
          </div>`;
        })
        .join("")}
    </div>`;
  if (!wrap._bound) {
    wrap._bound = true;
    wrap.addEventListener("click", (e) => {
      const el = e.target.closest("[data-staff-id]");
      if (!el) return;
      openStaffServicesFor(el.dataset.staffId, el.dataset.staffName, el.dataset.staffPhoto || "");
    });
    wrap.addEventListener("keydown", (e) => {
      if (e.key !== "Enter" && e.key !== " ") return;
      const el = e.target.closest("[data-staff-id]");
      if (!el) return;
      e.preventDefault();
      openStaffServicesFor(el.dataset.staffId, el.dataset.staffName, el.dataset.staffPhoto || "");
    });
  }
}

/* ---------- PERSONEL HİZMETLERİ (frontend tabanlı) ---------- */
async function fetchStaffServices(bizId, staffId) {
  const all = (Array.isArray(window.__lastServicesList) ? window.__lastServicesList : []) || [];
  const staff = (window.__staffList || []).find((s) => (s.id || s.uid) === staffId);
  if (!staff) return all;

  // Backend'den serviceIds veya services alani gelip gelmedigini kontrol et.
  // Bu alanlar tanimli ise (bos dizi bile olsa) admin'in bilincli atamasidir.
  // Tanimli degilse -> tum hizmetleri goster (geriye donuk uyumluluk).
  const hasExplicitConfig =
    Object.prototype.hasOwnProperty.call(staff, "serviceIds") ||
    Object.prototype.hasOwnProperty.call(staff, "services");

  const linked = [];

  if (Array.isArray(staff.services)) {
    for (const it of staff.services) {
      if (!it) continue;
      if (typeof it === "string") linked.push({ id: it });
      else if (typeof it === "object") linked.push(it);
    }
  }

  if (Array.isArray(staff.serviceIds)) {
    for (const id of staff.serviceIds) {
      if (id) linked.push({ id: String(id) });
    }
  }

  // Hizmet atanmamissa:
  //   Backend serviceIds/services dondurduyse  -> bos liste (personele hizmet yok)
  //   Backend bu alani hic dondurmediyse       -> tum hizmetler (eski uyumluluk)
  if (!linked.length) return hasExplicitConfig ? [] : all;

  const out = [];

  linked.forEach((meta) => {
    const stubs = all.filter((svc) =>
      svcEquals({ ...svc, serviceId: svc.serviceId || svc.id }, { ...meta, serviceId: meta.serviceId || meta.id })
    );
    const match = stubs[0] || null;
    if (!match) return;

    const duration = Number(meta.duration ?? meta.min ?? match.duration ?? match.durationMin ?? match.min ?? 30);
    const price = Number(meta.price ?? match.price ?? 0);
    out.push({
      id: match.id || match.serviceId || meta.id,
      serviceId: match.serviceId || match.id || meta.id,
      name: meta.name || match.name || "Hizmet",
      price,
      duration,
      category: meta.category || match.category || "",
    });
  });

  // Eslesen hizmet bulunamadiysa:
  //   Explicit config varsa -> bos dondur (atanan ID'ler katalogda yok)
  //   Yoksa -> tum hizmetler (guvenli fallback)
  return out.length ? out : (hasExplicitConfig ? [] : all);
}

async function openStaffServicesFor(staffId, staffName, staffPhoto) {
  if (!currentBizId || !staffId) return;
  ensureStaffServicesModal();

  const ttl  = document.getElementById('staffSvcTtl');
  const body = document.getElementById('staffSvcBody');

  if (ttl) ttl.innerHTML = `<span style="opacity:.5">Yükleniyor…</span>`;
  if (body) body.innerHTML = `<div style="padding:32px;text-align:center;color:#94a3b8">Yükleniyor…</div>`;
  showOv('staffServicesOv');

  // Paralel: hizmetler + çalışan yorumları
  const [svcs, revData] = await Promise.all([
    fetchStaffServices(currentBizId, staffId).catch(() => []),
    apiGet(`/api/staff/reviews.php`, { staff_id: staffId, business_id: currentBizId, limit: 5 }),
  ]);

  const staffData = revData || null;
  const stats     = staffData?.stats || { avg_rating: 0, total: 0, breakdown: {} };
  const reviews   = staffData?.reviews || [];
  const totalAppt = staffData?.total_appointments || 0;
  const photo     = staffData?.staff?.photo || staffPhoto || '';
  const photoOrig = staffData?.staff?.photo_orig || staffPhoto || '';
  const position  = staffData?.staff?.position || '';
  const color     = staffData?.staff?.color || '#6b7280';

  // ── Başlık ───────────────────────────────────────────────────────────
  if (ttl) {
    const avatarHtml = photo
      ? `<img src="${escapeHTML(photo)}" style="width:28px;height:28px;border-radius:50%;object-fit:cover;margin-right:8px;vertical-align:middle">`
      : `<span style="display:inline-flex;align-items:center;justify-content:center;width:28px;height:28px;border-radius:50%;background:${escapeHTML(color)};color:#fff;font-size:12px;font-weight:800;margin-right:8px;vertical-align:middle">${escapeHTML(initials(staffName || 'P'))}</span>`;
    ttl.innerHTML = `${avatarHtml}${escapeHTML(staffName || 'Personel')}`;
  }

  if (!body) { showOv('staffServicesOv'); return; }

  // ── Yıldız render yardımcısı ─────────────────────────────────────────
  const stars = (n) => {
    const full = Math.round(n);
    return Array.from({length:5}, (_,i) =>
      `<i class="${i < full ? 'fas' : 'fa-regular'} fa-star" style="color:#f59e0b;font-size:12px"></i>`
    ).join('');
  };

  // ── Hero bölümü ───────────────────────────────────────────────────────
  const avatarBlock = photo
    ? `<img src="${escapeHTML(photo)}"
           data-orig="${escapeHTML(photoOrig)}"
           alt="${escapeHTML(staffName)}"
           class="staff-modal-avatar staff-avatar-zoomable"
           title="Büyütmek için tıkla">`
    : `<div class="staff-modal-avatar-init" style="background:${escapeHTML(color)}">${escapeHTML(initials(staffName || 'P'))}</div>`;

  const avgHtml = stats.total > 0
    ? `<div class="staff-stat-pill">${stars(stats.avg_rating)} <span>${Number(stats.avg_rating).toFixed(1)}</span></div>
       <div class="staff-stat-pill" style="color:#64748b">(${stats.total} yorum)</div>`
    : `<div class="staff-stat-pill" style="color:#94a3b8">Henüz yorum yok</div>`;

  const apptHtml = totalAppt > 0
    ? `<div class="staff-stat-pill" style="color:#64748b">· ${totalAppt} randevu</div>`
    : '';

  let html = `
    <div class="staff-modal-hero">
      ${avatarBlock}
      <div class="staff-modal-info">
        <div class="staff-modal-name">${escapeHTML(staffName || 'Personel')}</div>
        ${position ? `<div class="staff-modal-pos">${escapeHTML(position)}</div>` : ''}
        <div class="staff-modal-stats">${avgHtml}${apptHtml}</div>
      </div>
    </div>`;

  // ── Puan dağılımı (5'ten fazla yorum varsa) ─────────────────────────
  if (stats.total >= 3) {
    html += `<div class="staff-modal-section-title">Puan Dağılımı</div>`;
    [5,4,3,2,1].forEach(n => {
      const cnt   = stats.breakdown?.[n] || 0;
      const pct   = stats.total > 0 ? Math.round((cnt / stats.total) * 100) : 0;
      html += `
        <div class="staff-rating-bar">
          <div class="bar-label">${n}★</div>
          <div class="bar-track"><div class="bar-fill" style="width:${pct}%"></div></div>
          <div class="bar-count">${cnt}</div>
        </div>`;
    });
    html += '<div style="margin-bottom:18px"></div>';
  }

  // ── Hizmetler ─────────────────────────────────────────────────────────
  html += `<div class="staff-modal-services">
    <div class="staff-modal-section-title">Yapabildiği Hizmetler (${svcs.length})</div>`;

  if (!svcs.length) {
    html += `<div style="color:#94a3b8;font-size:13px">Bu personele hizmet atanmamış.</div>`;
  } else {
    html += `<div class="staff-svc-chips">`;
    svcs.forEach(s => {
      const dur = Number(s.duration || s.duration_min || 30);
      html += `
        <div class="staff-svc-chip" data-pick-svc='${escapeHTML(JSON.stringify(s))}'>
          <div>
            <div class="chip-name">${escapeHTML(s.name)}</div>
            <div class="chip-meta">${dur} dk</div>
          </div>
          <div class="chip-price">${TL(s.price || 0)}</div>
        </div>`;
    });
    html += `</div>`;
  }
  html += `</div>`;

  // ── Yorumlar ──────────────────────────────────────────────────────────
  html += `<div class="staff-modal-reviews">
    <div class="staff-modal-section-title">Yorumlar</div>`;

  if (!reviews.length) {
    html += `<div class="staff-rev-empty">Bu çalışan için henüz yorum yazılmamış.</div>`;
  } else {
    reviews.forEach(r => {
      const d   = r.created_at ? new Date(r.created_at).toLocaleDateString('tr-TR', { day:'2-digit', month:'short', year:'numeric' }) : '';
      const svc = r.service_name ? `<div class="staff-rev-svc">${escapeHTML(r.service_name)}</div>` : '';
      html += `
        <div class="staff-rev-item">
          <div class="staff-rev-header">
            <span class="staff-rev-name">${escapeHTML(r.reviewer_name || 'Müşteri')}</span>
            <span class="staff-rev-stars">${stars(r.rating)}</span>
            <span class="staff-rev-date">${d}</span>
          </div>
          ${svc}
          ${r.comment ? `<div class="staff-rev-text">${escapeHTML(r.comment)}</div>` : ''}
        </div>`;
    });

    if (staffData?.has_more) {
      html += `<button class="staff-load-more-btn" id="staffRevLoadMore" data-page="2" data-staff-id="${staffId}">Daha fazla yorum gör</button>`;
    }
  }
  html += `</div>`;

  body.innerHTML = html;

  // ── Avatar tıklanınca orijinal fotoğrafı lightbox'ta aç ──────────────
  const avatarEl = body.querySelector('.staff-avatar-zoomable');
  if (avatarEl && photoOrig) {
    avatarEl.addEventListener('click', () => {
      const lbImg   = document.getElementById('lbImg');
      const lbPrev  = document.getElementById('lbPrev');
      const lbNext  = document.getElementById('lbNext');
      if (!lbImg) return;
      lbImg.src = '';                       // flash önle
      lbImg.src = photoOrig;
      lbImg.style.objectFit = 'contain';
      // Galeriye ait değil → prev/next gizle
      if (lbPrev) lbPrev.style.display = 'none';
      if (lbNext) lbNext.style.display = 'none';
      showOv('imgOv');
      // Lightbox kapanınca prev/next'i geri getir
      const restore = () => {
        if (lbPrev) lbPrev.style.display = '';
        if (lbNext) lbNext.style.display = '';
        document.getElementById('imgOv')?.removeEventListener('click', restore);
        document.getElementById('imgClose')?.removeEventListener('click', restore);
      };
      document.getElementById('imgClose')?.addEventListener('click', restore, { once: true });
      document.getElementById('imgOv')?.addEventListener('click', function handler(e) {
        if (e.target === this) { restore(); }
      }, { once: true });
    });
  }
  body.querySelectorAll('[data-pick-svc]').forEach(el => {
    el.addEventListener('click', async () => {
      try {
        const svc = JSON.parse(el.getAttribute('data-pick-svc'));
        cart = [{
          serviceId: svc.id || undefined,
          name:      svc.name,
          price:     Number(svc.price || 0),
          duration:  Number(svc.duration || svc.duration_min || 30),
        }];
        Persist.set('profile_cart', cart);
        selectedStaff = { id: staffId, name: staffName || 'Personel' };
        Persist.set('profile_staff', selectedStaff);
        closeOv('staffServicesOv');
        buildDayRail(todayTZDate(), BOOK_WINDOW_DAYS);
        setDateLabel();
        await refreshHourGrid();
        showOv('timeOv');
      } catch {}
    });
  });

  // ── Daha fazla yorum ──────────────────────────────────────────────────
  body.querySelector('#staffRevLoadMore')?.addEventListener('click', async (e) => {
    const btn  = e.currentTarget;
    const page = +(btn.dataset.page || 2);
    btn.textContent = 'Yükleniyor…';
    try {
      const j2 = await apiGet(`/api/staff/reviews.php`, { staff_id: staffId, business_id: currentBizId, page, limit: 5 });
      if (j2?.reviews) {
        const stars2 = (n) => Array.from({length:5},(_,i)=>`<i class="${i<Math.round(n)?'fas':'fa-regular'} fa-star" style="color:#f59e0b;font-size:12px"></i>`).join('');
        const container = body.querySelector('.staff-modal-reviews');
        btn.remove();
        j2.reviews.forEach(rv => {
          const d2   = rv.created_at ? new Date(rv.created_at).toLocaleDateString('tr-TR',{day:'2-digit',month:'short',year:'numeric'}) : '';
          const svc2 = rv.service_name ? `<div class="staff-rev-svc">${escapeHTML(rv.service_name)}</div>` : '';
          const div  = document.createElement('div');
          div.className = 'staff-rev-item';
          div.innerHTML = `
            <div class="staff-rev-header">
              <span class="staff-rev-name">${escapeHTML(rv.reviewer_name||'Müşteri')}</span>
              <span class="staff-rev-stars">${stars2(rv.rating)}</span>
              <span class="staff-rev-date">${d2}</span>
            </div>${svc2}
            ${rv.comment?`<div class="staff-rev-text">${escapeHTML(rv.comment)}</div>`:''}`;
          container.appendChild(div);
        });
        if (j2.has_more) {
          const nb = document.createElement('button');
          nb.className = 'staff-load-more-btn';
          nb.id = 'staffRevLoadMore';
          nb.dataset.page = page + 1;
          nb.dataset.staffId = staffId;
          nb.textContent = 'Daha fazla yorum gör';
          container.appendChild(nb);
          // re-bind
          nb.addEventListener('click', e => body.querySelector('#staffRevLoadMore')?.dispatchEvent(new MouseEvent('click')));
        }
      }
    } catch { btn.textContent = 'Daha fazla yorum gör'; }
  });
}

/* ---- Uygun personel seçici ---- */
async function renderStaffPicker(list = []) {
  const box = document.getElementById("staffPickList");
  if (!box) return;

  if (!Array.isArray(list) || list.length === 0) {
    box.innerHTML = `<div class="muted">Henüz personel eklenmemiş.</div>`;
    return;
  }

  const uniq = [];
  const seen = new Set();
  list.forEach((s) => {
    const key = (s.id || s.uid || s.name || Math.random()) + "";
    if (!seen.has(key)) {
      seen.add(key);
      uniq.push(s);
    }
  });

  let candidates = [];
  try {
    candidates = await eligibleStaffForCart(currentBizId, uniq, cart);
  } catch {
    candidates = uniq.slice();
  }

  if (!candidates.length) {
    box.innerHTML = `
      <div class="svc-item">
        <div>
          <div style="font-weight:700">Bu hizmet(ler) için uygun personel bulunamadı.</div>
          <div class="meta">Farklı hizmet seçebilirsiniz.</div>
        </div>
        <button class="btn-mini" id="backToServices">Hizmet seçimine dön</button>
      </div>`;
    box.querySelector("#backToServices")?.addEventListener("click", () => {
      renderServicePicker(window.__lastServicesList || []);
      showOv("svcOv");
    });
    return;
  }

  const totalMin = cartTotalMin() || 30;
  const [sh, sm] = (selectedTime || "00:00").split(":").map(Number);
  const blocks = Math.max(1, Math.ceil(totalMin / 15));

  const results = await Promise.all(
    candidates.map(async (s) => {
      let free = true;
      try {
        const m = await fetchBookedMap(currentBizId, selectedDate, s.id || s.uid || null, slotLock.token);
        free = isBlocksFree(m, sh, sm, blocks);
      } catch {
        free = true;
      }
      return { s, free };
    })
  );

  const busyCount = results.filter((r) => !r.free).length;
  const infoText = busyCount ? `Bu saatte ${busyCount} uygun olmayan personel var.` : `Seçtiğiniz saatte tüm uygun personeller uygun görünüyor.`;

  box.innerHTML = `
    <div class="small" style="margin:0 0 10px;color:var(--muted)">${infoText}</div>
    <div class="svc-item">
      <div>Farketmez <div class="meta">Uygun personele atanır</div></div>
      <button class="btn-mini" data-pick="__any__">Seç</button>
    </div>
    ${results
      .map(
        ({ s, free }) => `
      <div class="svc-item" ${free ? "" : 'aria-disabled="true"'}>
        <div>
          <div style="font-weight:700">${escapeHTML(s.name || "Personel")}</div>
          <div class="meta">
            ${free ? '<span class="badge green">Uygun</span>' : '<span class="badge red">Bu saatte dolu</span>'}
          </div>
        </div>
        <button class="btn-mini" ${free ? "" : "disabled"}
          data-pick="${escapeHTML(s.id || s.uid || s.name || "")}"
          data-name="${escapeHTML(s.name || "Personel")}">
          Seç
        </button>
      </div>
    `
      )
      .join("")}
  `;
  box.querySelectorAll("[data-pick]").forEach((btn) => {
    btn.onclick = () => {
      const id = btn.getAttribute("data-pick");
      if (id === "__any__") {
        selectedStaff = null;
        Persist.del("profile_staff");
      } else {
        selectedStaff = { id, name: btn.getAttribute("data-name") || "Personel" };
        Persist.set("profile_staff", selectedStaff);
      }
      closeOv("staffOv");
      buildReviewFromCurrent();
      showOv("reviewOv");
    };
  });
}

/* =========================================================
   İnceleme (Review)
   ========================================================= */

function buildReviewFromCurrent() {
  clearReviewError();
  const rev = document.getElementById("reviewBody");
  if (rev) {
    const total = cartTotal();
    const totalMin = cartTotalMin();
    const startStr = selectedTime || "Saat seçilmedi";
    const endStr = selectedTime ? addMinutesToTimeStr(selectedTime, totalMin) : "";
    const staffStr = selectedStaff?.name ? ` • ${escapeHTML(selectedStaff.name)} (personel)` : "";
    rev.innerHTML = `
      <div style="text-align:center;font-weight:800">
        ${fmtDate(selectedDate)}${staffStr}<br>
        <span class="muted" style="font-weight:700">
          ${startStr}${endStr ? ` – ${endStr}` : ""} • ${totalMin}dk toplam
        </span><br>
        <span class="small" id="revBizName"></span>
      </div>
      <div class="sumbox" style="margin-top:16px">
        ${cart
          .map(
            (i) => `
          <div style="display:flex;justify-content:space-between;font-weight:700;padding:8px 0">
            <span>${escapeHTML(i.name)}<br><span class="small">${Number(i.duration || i.min || 30)}dk</span></span>
            <span>${TL(i.price || 0)}</span>
          </div>`
          )
          .join("")}
        <div class="sumrow"><span style="font-weight:700">Toplam:</span><span style="font-weight:800">${TL(total)}</span></div>
        <div style="margin-top:8px;display:flex;justify-content:flex-start">
          <button id="openAdd" class="btn-outline" style="border:1px solid var(--border);border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer;background:#fff">Başka hizmet ekle</button>
        </div>
      </div>
    `;
    const revTotalEl = document.getElementById("reviewTotal");
    if (revTotalEl) revTotalEl.textContent = TL(total);
    const rb = document.getElementById("revBizName");
    if (rb) rb.textContent = document.getElementById("bizName")?.textContent || "";
    document.getElementById("openAdd")?.addEventListener("click", () => {
      renderServicePicker(window.__lastServicesList || [], { mode: "edit" });
      showOv("svcOv");
    });
  }
}

/* =========================================================
   Çakışma kontrolleri (PHP üzerinden)
   ========================================================= */

async function hasUserConflict(uid, start, end) {
  if (!uid) return { hasConflict: false, bizName: "", businessId: "" };
  try {
    const res = await apiPost("/api/appointments/check-conflict.php", {
      uid,
      startISO: start.toISOString(),
      endISO: end.toISOString(),
    });
    return {
      hasConflict: !!res.hasConflict,
      bizName: res.bizName || "",
      businessId: res.businessId || "",
    };
  } catch (e) {
    console.warn("[hasUserConflict] cross-biz query error:", e);
    return { hasConflict: false, bizName: "", businessId: "" };
  }
}

/* Çalışma saatini kontrol */
async function isBusinessFree(start, totalMin) {
  const within = isWithinWorkingHours(start.getHours() * 60 + start.getMinutes(), totalMin);
  return within;
}

/* ===== Durum izleme (PHP poll) ===== */
function attachStatusWatchers(bizId, rid) {
  cleanupStatusWatchers();
  if (!bizId || !rid) return;

  const checkOnce = async () => {
    try {
      const res = await apiGet("/api/appointments/status.php", {
        businessId: bizId,
        id: rid,
      });
      const r = res.appointment || res.data || {};
      if (!r) {
        forgetPending(bizId, rid);
        removeStatusBar();
        cleanupStatusWatchers();
        return;
      }
      const st = String(r.status || "").toLowerCase();
      if (st === "approved" || st === "confirmed") {
        updateClientStatus("ok", "Randevunuz onaylandı.", "Randevumu görüntüle", APPOINTMENTS_URL);
        const start = r.startISO ? new Date(r.startISO) : null;
        const end = r.endISO ? new Date(r.endISO) : null;
        openSuccessModal({
          start,
          end,
          totalMin: r.durationMin || r.totalMin,
          staffName: r.staffName || "",
        });
        forgetPending(bizId, rid);
        cleanupStatusWatchers();
        setTimeout(removeStatusBar, 3500);
        return;
      }
      if (st === "rejected" || st === "cancelled" || st === "canceled" || st === "declined") {
        updateClientStatus("err", st === "rejected" ? "Randevunuz reddedildi." : "Randevunuz iptal edildi.", "Randevularım", APPOINTMENTS_URL);
        forgetPending(bizId, rid);
        cleanupStatusWatchers();
        setTimeout(removeStatusBar, 3500);
        return;
      }
    } catch (e) {
      console.warn("[status watcher] status api error", e);
    }
  };

  checkOnce();
  _statusPollTimer = setInterval(checkOnce, 15000);
}

/* =========================================================
   Kaydetme
   ========================================================= */

function openSuccessModal({ start, end, totalMin, staffName }) {
  const whenEl = document.getElementById("confirmWhen");
  const ttlEl = document.getElementById("confirmTtl");
  if (ttlEl) ttlEl.textContent = "Randevu onaylandı";
  if (start && end) {
    const timeStr = `${fmtDate(start)} • ${pad(start.getHours())}:${pad(start.getMinutes())} – ${pad(end.getHours())}:${pad(end.getMinutes())} • ${totalMin}dk`;
    const staffStr = staffName ? ` • ${staffName}` : "";
    if (whenEl) whenEl.textContent = timeStr + staffStr;
  }
  ["svcOv", "reviewOv", "timeOv", "staffOv"].forEach((id) => document.getElementById(id)?.classList.remove("show"));
  showOv("confirmOv");
  const go = document.getElementById("goAppointments");
  if (go && !go._bound) {
    go._bound = true;
    go.addEventListener("click", () => {
      location.href = APPOINTMENTS_URL;
    });
  }
}

/* ---- Kullanıcı tipi: admin/staff randevu alamaz ---- */
function isCurrentUserStaffOrOwner() {
  if (!currentUid) return false;
  if (currentOwner && (currentOwner.uid === currentUid || currentOwner.id === currentUid)) return true;
  return (window.__staffList || []).some((s) => (s.uid || s.id) === currentUid);
}

/* ---- Kullanıcı JSON'u randevuya göm (yalın) ---- */
async function buildCustomerPayload(uid) {
  if (!uid) return { uid: "" };
  if (currentUser && currentUser.uid === uid) {
    const name = (currentUser.name || currentUser.displayName || "").trim();
    return {
      uid,
      name,
      phoneE164: currentUser.phoneE164 || currentUser.phone || "",
      email: currentUser.email || "",
    };
  }

  try {
    const res = await apiGet("/api/user/getProfile.php", { uid });
    return {
      uid,
      name: (res.name || "").trim(),
      phoneE164: res.phoneE164 || res.phone || "",
      email: res.email || "",
    };
  } catch (e) {
    console.warn("[buildCustomerPayload] user profile api error:", e);
    return { uid };
  }
}

/* ---- SON DAKİKA YARIŞ KONTROLÜ ---- */
async function finalAvailabilityGuard(start, totalMin) {
  const [hh, mm] = [start.getHours(), start.getMinutes()];
  const blocks = Math.max(1, Math.ceil((totalMin || 30) / 15));

  if (selectedStaff?.id) {
    try {
      const map = await fetchBookedMap(currentBizId, selectedDate, selectedStaff.id, slotLock.token);
      if (!isBlocksFree(map, hh, mm, blocks)) {
        showToast("Seçtiğiniz personel bu saatte dolu. Lütfen başka saat/personel seçin.");
        return false;
      }
    } catch {}
  } else {
    try {
      const counterMap = await fetchBookedCounters(currentBizId, selectedDate);
      if (!hasBusinessCapacity(counterMap, hh, mm, blocks)) {
        showToast("Bu saat doldu. Lütfen başka bir saat seçin.");
        return false;
      }
    } catch {}
  }
  return true;
}

let _saving = false;

async function persistBookingAndGo() {
  if (_saving) return;
  clearReviewError();
  if (!cart.length) {
    showToast("Lütfen en az bir hizmet seçin");
    return;
  }
  if (!selectedTime) {
    showToast("Lütfen bir saat seçin");
    return;
  }
  if (!currentUid) {
    _pendingBookingAfterLogin = true;
    showToast("Lütfen giriş yapın veya kayıt olun");
    openAuthModal();
    return;
  }
  if (!currentBizId) {
    showToast("İşletme bilgisi eksik");
    return;
  }
  if (isCurrentUserStaffOrOwner()) {
    showToast("İşletme/staff hesabı ile randevu alınamaz. Lütfen kullanıcı hesabı ile giriş yapın.");
    return;
  }

  if (!inBookingWindow(selectedDate)) {
    showToast("Bu tarih için randevu alınamaz. Sadece bugün ve sonraki 9 gün.");
    return;
  }

  const [hh, mm] = selectedTime.split(":").map(Number);
  const start = new Date(selectedDate.getFullYear(), selectedDate.getMonth(), selectedDate.getDate(), hh, mm || 0, 0, 0);
  const totalMin = cartTotalMin();
  const end = new Date(start.getTime() + totalMin * 60000);

  if (!(await isBusinessFree(start, totalMin))) {
    showToast("Seçilen saat çalışma saatleri dışında");
    return;
  }

  const conflict = await hasUserConflict(currentUid, start, end);
  if (conflict?.hasConflict) {
    let msg;
    if (conflict.businessId && conflict.businessId === currentBizId) {
      msg =
        "Bu saatte bu salonda zaten bir randevunuz bulunuyor. Aynı saate birden fazla randevu almak istiyorsanız salon ile telefon numarası üzerinden iletişime geçebilirsiniz.";
    } else {
      const name = conflict.bizName || "başka bir salon";
      msg = `"${name}" adlı salonda bu saatte bir randevunuz bulunuyor. Aynı saate birden fazla randevu almak istiyorsanız salon ile telefon numarası üzerinden iletişime geçebilirsiniz.`;
    }
    showReviewError(msg);
    return;
  }

  if (!(await finalAvailabilityGuard(start, totalMin))) return;

  const primaryServiceId =
    cart[0]?.serviceId ||
    cart[0]?.id ||
    (cart[0]?.name ? cart[0].name.toLowerCase().replace(/\s+/g, "-") : "general");

  const customer = await buildCustomerPayload(currentUid);

  const dayStr = apptDayStr(selectedDate);
  const startMin = apptTimeToMin(selectedTime);
  const staffId = selectedStaff?.id || "any";

  const btn = document.getElementById("confirmBook");
  try {
    _saving = true;
    if (btn) btn.disabled = true;

    const res = await apiPost("/api/appointments/book.php", {
      businessId: currentBizId,
      staffId,
      serviceId: primaryServiceId,
      dayStr,
      startMin,
      durationMin: totalMin,
      customer: {
        uid: customer.uid,
        name: customer.name,
        phoneE164: customer.phoneE164,
      },
      status: "pending",
      source: "web",
      lockToken: slotLock.token ?? "",
      notes: cart.length > 1 ? `Çoklu hizmet: ${cart.map((s) => s.name).join(", ")}` : "",
    });

    const id = res.id || res.rid;

    // Modalı bilgilerle aç (beklemede durumu)
    const ttlEl = document.getElementById("confirmTtl");
    const whenEl = document.getElementById("confirmWhen");
    if (ttlEl) ttlEl.textContent = "Randevu talebiniz alındı";
    if (whenEl) {
      const timeStr = selectedTime || "—";
      const endStr = selectedTime ? addMinutesToTimeStr(selectedTime, totalMin) : "";
      const staffStr = selectedStaff?.name ? ` • ${escapeHTML(selectedStaff.name)}` : "";
      whenEl.textContent = `${fmtDate(selectedDate)} • ${timeStr}${endStr ? ` – ${endStr}` : ""} • ${totalMin}dk${staffStr}`;
    }
    ["svcOv", "reviewOv", "timeOv", "staffOv"].forEach((id2) => document.getElementById(id2)?.classList.remove("show"));
    showOv("confirmOv");

    showClientStatus({
      type: "pending",
      text: "Randevu talebiniz iletildi. Onay bekleniyor.",
      ctaText: "Randevumu görüntüle",
      ctaHref: APPOINTMENTS_URL,
    });
    rememberPending(currentBizId, id);
    attachStatusWatchers(currentBizId, id);

    // Kilit başarıyla kullanıldı, temizle
    slotLock.token = null;
    stopLockCountdown();
    clearBookingCache();

    try {
      const merged = [
        ...(JSON.parse(localStorage.getItem(CAL_EVT_KEY_OLD) || "[]")),
        ...(JSON.parse(localStorage.getItem(CAL_EVT_KEY_NEW) || "[]")),
      ];
      merged.push({
        businessId: currentBizId,
        rid: id,
        startISO: start.toISOString(),
        endISO: end.toISOString(),
        createdAt: Date.now(),
      });
      localStorage.setItem(CAL_EVT_KEY_NEW, JSON.stringify(merged));
      localStorage.removeItem(CAL_EVT_KEY_OLD);
    } catch {}
  } catch (e) {
    console.error("[booking] error:", e);
    showToast("Randevu talebiniz kaydedilemedi");
  } finally {
    if (btn) btn.disabled = false;
    _saving = false;
  }
}

/* DOM-ready'de bağlanır */
function bindConfirmBook() {
  const btn = document.getElementById("confirmBook");
  if (!btn || btn._bound) return;
  btn._bound = true;
  btn.addEventListener("click", persistBookingAndGo);
}

/* =========================================================
   Saatler (sidebar)
   ========================================================= */

function renderHours(hours) {
  const weekEl = $("#weekBlock"),
    todayEl = $("#todayTimes"),
    note = $("#hoursNote");
  let norm = hours ? normalizeWorkingHours(hours) : null;
  if (!norm) {
    if (note) note.style.display = "block";
    if (todayEl) todayEl.textContent = "—";
    if (weekEl) {
      weekEl.innerHTML = "";
      weekEl.setAttribute("hidden", "");
    }
    return;
  }
  if (note) note.style.display = "none";

  const tzNow = nowInTZ();
  const t = new Date(tzNow.Y, tzNow.M - 1, tzNow.D).getDay();

  if (todayEl) todayEl.textContent = norm[t]?.length ? norm[t].join(" • ") : "Kapalı";
  if (weekEl) {
    weekEl.innerHTML = "";
    for (let i = 0; i < 7; i++) {
      const row = document.createElement("div");
      row.className = "row";
      const left = document.createElement("span");
      left.textContent = TR_DAYS[i];
      const right = document.createElement("span");
      right.className = "muted";
      right.textContent = norm[i]?.length ? norm[i].join("  •  ") : "Kapalı";
      if (i === t) {
        left.style.fontWeight = "800";
        right.style.fontWeight = "800";
        right.style.color = "#111";
      }
      row.append(left, right);
      weekEl.appendChild(row);
    }
    weekEl.toggleAttribute("hidden", !weekEl.classList.contains("show"));
  }
}

/* =========================================================
   Adres/Görseller/Hizmetler
   ========================================================= */

// normalizeImagePairs: [{opt, orig}] çiftleri döner
// opt  → küçük görselde (lazy load)
// orig → lightbox/tam görünümde
function normalizeImagePairs(images = {}) {
  const pairs = [];
  const addPairs = (optKey, origKey) => {
    const opts  = [].concat(images[optKey]  || []).filter(Boolean);
    const origs = [].concat(images[origKey] || []).filter(Boolean);
    const len = Math.max(opts.length, origs.length);
    for (let i = 0; i < len; i++) {
      const orig = origs[i] || opts[i];
      const opt  = opts[i]  || orig;
      if (orig || opt) pairs.push({ opt, orig });
    }
  };
  addPairs("cover_opt", "cover");
  addPairs("salon_opt", "salon");
  addPairs("model_opt", "model");
  [].concat(images.works || []).filter(Boolean).forEach(u => pairs.push({ opt: u, orig: u }));
  // Tekrar edenlerı çıkar (orig bazında)
  const seen = new Set();
  return pairs.filter(p => { if (seen.has(p.orig)) return false; seen.add(p.orig); return true; });
}
// Geriye uyumluluk: sadece opt URL listesi
function normalizeImageList(images = {}) {
  return normalizeImagePairs(images).map(p => p.opt);
}

function buildMapsLinks(loc = {}, bizName = "", addrText = "", mode = "search") {
  const direct = loc.googleMapsUrl || loc.googleMapUrl || loc.mapUrl || loc.mapLink || loc.gmapsUrl;
  const hasLL = Number.isFinite(loc.lat) && Number.isFinite(loc.lng);
  const fullAddr =
    addrText ||
    [loc.street && (loc.building ? `${loc.street} ${loc.building}` : loc.street), loc.neighborhood, loc.district, loc.province]
      .filter(Boolean)
      .join(", ");
  const queryQ = hasLL ? `${loc.lat},${loc.lng}` : (bizName ? `${bizName}, ` : "") + fullAddr;
  const openHref =
    direct ||
    (mode === "directions"
      ? `https://www.google.com/maps/dir/?api=1&destination=${encodeURIComponent(queryQ)}`
      : `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(queryQ)}`);
  const embedSrc = `https://www.google.com/maps?hl=tr&q=${encodeURIComponent(queryQ)}&z=15&output=embed`;
  return { openHref, embedSrc };
}

function renderAddress(loc = {}) {
  const subTop = [loc.district, loc.province].filter(Boolean).join(" • ") || "—";
  const elAddrTop = $("#bizAddrTop");
  if (elAddrTop) elAddrTop.textContent = subTop;

  const line1 = [loc.street && (loc.building ? `${loc.street} ${loc.building}` : loc.street), loc.neighborhood].filter(Boolean).join(", ");
  const line2 = [loc.district, loc.province].filter(Boolean).join(" • ");
  const fullAddr = [line1, line2].filter(Boolean).join(" • ") || "—";
  const infoAddrEl = $("#infoAddr");
  if (infoAddrEl) infoAddrEl.textContent = fullAddr;

  const gmFrame = document.getElementById("gmFrame");
  const openBtn = document.getElementById("openMapBtn");
  const mapTitle = document.getElementById("mapTitle");
  const mapSub = document.getElementById("mapSub");
  const mapLogo = document.getElementById("mapLogo");

  const bizName = $("#infoName")?.textContent || $("#bizName")?.textContent || "İşletme";
  if (mapTitle) mapTitle.textContent = bizName;
  if (mapSub) mapSub.textContent = fullAddr.replace(" • ", ", ");
  if (mapLogo && currentLogoSrc) mapLogo.src = currentLogoSrc;

  const { openHref, embedSrc } = buildMapsLinks({ ...loc, lat: loc.lat, lng: loc.lng }, bizName, fullAddr.replace(" • ", ", "), "search");

  if (gmFrame) {
    gmFrame.src = embedSrc;
    gmFrame.loading = "lazy";
    gmFrame.referrerPolicy = "no-referrer-when-downgrade";
    gmFrame.style.border = "0";
    gmFrame.style.width = "100%";
    gmFrame.style.height = "100%";
    gmFrame.style.pointerEvents = "none";
  }
  if (openBtn) {
    openBtn.href = openHref;
    openBtn.style.display = "inline-flex";
  }

  const box = document.getElementById("mapBox");
  if (box) {
    box.addEventListener("click", (e) => {
      if (e.target.closest(".mapcard") || e.target.closest("#openMapBtn")) return;
      if (openHref) window.open(openHref, "_blank", "noopener");
    });
    box.setAttribute("role", "link");
    box.setAttribute("tabindex", "0");
    box.setAttribute("aria-label", "Google Haritalar’da aç");
    box.addEventListener("keydown", (e) => {
      if ((e.key === "Enter" || e.key === " ") && openHref) {
        e.preventDefault();
        window.open(openHref, "_blank", "noopener");
      }
    });
  }
  updateShopbarFromDOM();
}

function setImages(imgs = {}) {
  const pairs = normalizeImagePairs(imgs);
  const g1 = $("#gal1");
  const g2 = $("#gal2");
  const g3 = $("#gal3");

  // src = optimize (hızlı yüklensin), data-orig = orijinal tam kalite (lightbox için)
  if (g1 && pairs[0]) {
    g1.src = pairs[0].opt;
    g1.dataset.orig = pairs[0].orig;
    g1.style.display = "block";
  }
  if (g2 && pairs[1]) {
    g2.src = pairs[1].opt;
    g2.dataset.orig = pairs[1].orig;
    g2.style.display = "none";
  }
  if (g3 && pairs[2]) {
    g3.src = pairs[2].opt;
    g3.dataset.orig = pairs[2].orig;
    g3.style.display = "none";
  }

  currentLogoSrc = pairs[1]?.opt || pairs[0]?.opt || "img/berber1.jpeg";
  try {
    const sbLogo = document.getElementById("sbLogo");
    if (sbLogo && currentLogoSrc) sbLogo.src = currentLogoSrc;
  } catch {}
}

function renderServices(list = []) {
  const box = $("#svcRows");
  if (!box) return;
  if (!list.length) {
    box.innerHTML = '<div class="muted">Henüz hizmet eklenmemiş.</div>';
    return;
  }
  const first = list.slice(0, 3).map((s) => ({
    ...s,
    _sid: s.serviceId || s.id || s.code || s.slug || s.key || "",
  }));
  box.innerHTML = first
    .map(
      (s) => `
    <div class="service-row">
      <div>
        <div class="name">${escapeHTML(s.name || "Hizmet")}</div>
        <div class="meta">${escapeHTML(String(s.min ?? s.duration ?? s.durationMin ?? 30))}dk</div>
      </div>
      <div class="price">${TL(s.price ?? 0)}</div>
      <button class="book open-book"
              data-sid="${escapeHTML(String(s._sid || ""))}"
              data-name="${escapeHTML(s.name || "Hizmet")}"
              data-price="${Number(s.price || 0)}"
              data-duration="${Number(s.min ?? s.duration ?? s.durationMin ?? 30)}">Randevu al</button>
    </div>`
    )
    .join("");
  const allBtn = document.getElementById("openAllServices");
  if (allBtn) {
    allBtn.onclick = () => {
      const s = document.getElementById("svcSearch");
      if (s) s.value = "";
      renderServicePicker(list);
      showOv("svcOv");
    };
  }
  bindOpenBookButtons();
}

/* =========================================================
   Personel + Kaynakları ekrana bas
   ========================================================= */

function paintDisplay(disp) {
  const elBizName = $("#bizName");
  if (elBizName) elBizName.textContent = disp.name;
  const infoNameEl = $("#infoName");
  if (infoNameEl) infoNameEl.textContent = disp.name;
  try {
    document.title = `${disp.name} – Webey`;
  } catch {}

  const phoneText = $("#phoneText");
  if (phoneText) phoneText.textContent = disp.phoneRaw ? fmtPhoneTR(disp.phoneRaw) : "-";
  const callBtn = $("#callBtn");
  if (callBtn) setTimeout(() => callBtn.setAttribute("href", disp.phoneRaw ? `tel:${disp.phoneRaw}` : "#"), 0);

  const aboutText = $("#aboutText");
  if (aboutText) aboutText.textContent = disp.about || "—";

  setImages(disp.images || {});
  renderAddress({
    province: disp.loc?.province || disp.loc?.city || "",
    district: disp.loc?.district || "",
    neighborhood: disp.loc?.neighborhood || "",
    street: disp.loc?.street || disp.loc?.addressLine || "",
    building: disp.loc?.building || "",
    lat: disp.loc?.lat,
    lng: disp.loc?.lng,
    googleMapsUrl: disp.loc?.googleMapsUrl || disp.loc?.mapUrl || disp.loc?.mapLink || "",
  });

  renderServices(disp.services || []);
  renderHours(disp.hours);

  currentOwner = disp.owner || null;

  // Staff list & kapasite
  window.__staffList = Array.isArray(disp.staff) ? disp.staff : [];
  staffCount = Math.max(
    (window.__staffList || []).filter((s) => s.active !== false && s.showInCalendar !== false).length || 0,
    1
  );
  renderStaff(window.__staffList);

  // "personel hazır" sinyali
  staffReady = true;
  try {
    _staffReadyWaiters.forEach((fn) => fn());
  } catch {}
  _staffReadyWaiters = [];

  // "Çalışmalarımız" alanı — TÜM fotoğraflar
  const pics = normalizeImageList(disp.images || {});
  const worksGrid = $("#worksGrid");
  if (worksGrid) {
    if (pics.length) {
      // opt → ekranda küçük görüntü, data-orig → lightbox tam kalite
      const imgPairs = normalizeImagePairs(disp.images || {});
      worksGrid.innerHTML = imgPairs
        .map(p => `<img data-enlarge loading="lazy" decoding="async" src="${p.opt}" data-orig="${p.orig}" alt="Çalışma görseli">`)
        .join("");
      if (!document.getElementById("worksFixStyle")) {
        const st = document.createElement("style");
        st.id = "worksFixStyle";
        st.textContent = `.grid-works img{width:100%;height:auto;aspect-ratio:1/1;object-fit:cover;}`;
        document.head.appendChild(st);
      }
      window._lbRescan?.();
    } else {
      worksGrid.innerHTML = '<div class="muted" style="padding:12px 4px;font-size:14px">Henüz görsel eklenmemiş.</div>';
    }
  }

  currentHours = disp.hours || null;

  // Schema.org
  (function setStructuredData() {
    try {
      const addr = {
        "@type": "PostalAddress",
        streetAddress: [disp.loc?.street, disp.loc?.building].filter(Boolean).join(" "),
        addressLocality: disp.loc?.district || "",
        addressRegion: disp.loc?.province || "",
        addressCountry: "TR",
      };
      const sd = {
        "@context": "https://schema.org",
        "@type": "HairSalon",
        name: disp.name || "İşletme",
        description: (disp.about || "").slice(0, 200),
        telephone: disp.phoneRaw || undefined,
        image: [currentLogoSrc].filter(Boolean),
        address: addr,
        geo:
          Number.isFinite(disp.loc?.lat) && Number.isFinite(disp.loc?.lng)
            ? { "@type": "GeoCoordinates", latitude: disp.loc.lat, longitude: disp.loc.lng }
            : undefined,
        url: location.href,
        hasMap: (Number.isFinite(disp.loc?.lat) && Number.isFinite(disp.loc?.lng))
          ? `https://www.google.com/maps?q=${disp.loc.lat},${disp.loc.lng}`
          : undefined,
        priceRange: disp.price_range || "₺₺",
        currenciesAccepted: "TRY",
        paymentAccepted: "Cash, Credit Card",
        // Çalışma saatleri
        openingHoursSpecification: (() => {
          try {
            const days = ["Sunday","Monday","Tuesday","Wednesday","Thursday","Friday","Saturday"];
            const norm = disp.hours || {};
            const specs = [];
            Object.entries(norm).forEach(([dayIdx, seg]) => {
              if (!seg || seg.closed) return;
              const opens = seg.start || seg.opens || "09:00";
              const closes = seg.end || seg.closes || "18:00";
              specs.push({
                "@type": "OpeningHoursSpecification",
                dayOfWeek: `https://schema.org/${days[Number(dayIdx)] || "Monday"}`,
                opens,
                closes
              });
            });
            return specs.length ? specs : undefined;
          } catch { return undefined; }
        })(),
        // Yıldız puanı (varsa)
        aggregateRating: (disp.rating_avg && disp.rating_count)
          ? {
              "@type": "AggregateRating",
              ratingValue: parseFloat(disp.rating_avg).toFixed(1),
              reviewCount: parseInt(disp.rating_count),
              bestRating: "5",
              worstRating: "1"
            }
          : undefined,
      };
      const el = document.getElementById("ldJson");
      if (el) el.textContent = JSON.stringify(sd, null, 2);
    } catch {}
  })();

  window.__lastServicesList = disp.services || [];
  updateShopbarFromDOM();
}

/* =========================================================
   Yükleme (business/profile.php üzerinden)
   ========================================================= */

async function load() {
  await loadAuthStatus();

  const storedYmd = Persist.get("profile_selected_date");
  if (storedYmd) {
    const p = storedYmd.split("-").map(Number);
    if (p.length === 3) selectedDate = new Date(p[0], p[1] - 1, p[2]);
  }
  const storedTime = Persist.get("profile_selected_time");
  if (storedTime) selectedTime = storedTime;
  const storedCart = Persist.get("profile_cart", []);
  if (Array.isArray(storedCart)) cart = storedCart;
  const storedStaff = Persist.get("profile_staff", null);
  if (storedStaff && storedStaff.id) selectedStaff = storedStaff;

  const params = new URLSearchParams(location.search);
  let queryBizId = params.get("id") || params.get("biz") || params.get("bid") || "";
  let queryUid = params.get("uid") || "";
  const slugParam = params.get("n") || "";

  const qDate = params.get("date");
  const qTime = params.get("time");
  if (qDate && /^\d{4}-\d{2}-\d{2}$/.test(qDate)) {
    const [Y, M, D] = qDate.split("-").map(Number);
    selectedDate = new Date(Y, M - 1, D);
    Persist.set("profile_selected_date", ymd(selectedDate));
  }
  if (qTime && /^\d{1,2}:\d{2}$/.test(qTime)) {
    selectedTime = qTime;
    Persist.set("profile_selected_time", selectedTime);
  }

  selectedDate = clampToBookingWindow(selectedDate);

  let slugFromPath = "";
  try {
    const parts = location.pathname.split("/").filter(Boolean);
    const pIdx = parts.indexOf("profile");
    if (pIdx >= 0 && parts[pIdx + 1]) slugFromPath = decodeURIComponent(parts[pIdx + 1]);
    else {
      const last = parts[parts.length - 1] || "";
      if (last && !/\.[a-z0-9]+$/i.test(last) && last.toLowerCase() !== "profile") slugFromPath = decodeURIComponent(last);
    }
  } catch {}

  currentBizId = queryBizId || queryUid || "";

  let disp = null;

  try {
    const res = await apiGet("/api/business/profile.php", {
      id: currentBizId || "",
      slug: slugFromPath || slugParam || "",
    });

    currentBizId = res.businessId || res.id || res.uid || queryBizId || queryUid || "";

    disp = {
      name: res.name || "İşletmeniz",
      about: res.about || "",
      phoneRaw: res.phoneE164 || res.phone || "",
      loc: res.loc || res.location || {},
      images: res.images || {},
      services: Array.isArray(res.services) ? res.services : [],
      hours: res.hours || null,
      staff: Array.isArray(res.staff) ? res.staff : [],
      owner: res.owner || null,
    };
  } catch (e) {
    console.warn("[profile] business/profile.php error:", e);
    showToast("Profil yüklenemedi");
    const nm = slugFromPath || slugParam || "İşletmeniz";
    disp = {
      name: nm,
      about: "",
      phoneRaw: "",
      loc: {},
      images: {},
      services: [],
      hours: null,
      staff: [],
      owner: null,
    };
  }

  paintDisplay(disp);
  attachDateFilterControls();
  setDateLabel();
  resumePendingWatchersForCurrentBiz();
  initScrollShopbar();
}

/* =========================================================
   TARİH MODALİ FAILSAFE (DOM-ready init)
   ========================================================= */

function bindDateOverlayFailsafe() {
  const ov = document.getElementById("sb-date-overlay");
  if (!ov || ov.__bound) return;
  ov.__bound = true;

  if (!ov.hasAttribute("aria-hidden")) ov.setAttribute("aria-hidden", "true");

  const input = document.getElementById("qWhen");
  const okBtn = ov.querySelector(".actions .ok");
  const clrBtn = ov.querySelector(".actions .clear");
  const close = ov.querySelector(".sb-date-close");
  const grid = ov.querySelector(".grid");
  const monthEl = ov.querySelector(".sb-date-head .month");
  const actions = ov.querySelector(".actions");
  const trMonths = ["Ocak", "Şubat", "Mart", "Nisan", "Mayıs", "Haziran", "Temmuz", "Ağustos", "Eylül", "Ekim", "Kasım", "Aralık"];

  let qSelectedTime = "";
  let timeGridEl = null;

  const resetTimeSelection = () => {
    qSelectedTime = "";
    if (!timeGridEl) return;
    timeGridEl.querySelectorAll(".sb-time-pill").forEach((x) => {
      x.classList.remove("active");
      x.style.background = "#fff";
      x.style.color = "#111";
      x.style.borderColor = "var(--border, #ddd)";
    });
  };

  const highlightTime = (t) => {
    if (!timeGridEl) return;
    qSelectedTime = t;
    timeGridEl.querySelectorAll(".sb-time-pill").forEach((x) => {
      const isActive = x.dataset.time === t;
      x.classList.toggle("active", isActive);
      if (isActive) {
        x.style.background = "#0aa36b";
        x.style.color = "#fff";
        x.style.borderColor = "#0aa36b";
      } else {
        x.style.background = "#fff";
        x.style.color = "#111";
        x.style.borderColor = "var(--border, #ddd)";
      }
    });
  };

  if (actions) {
    const timeRow = document.createElement("div");
    timeRow.className = "sb-time-row";
    timeRow.innerHTML = `
      <div class="sb-time-title" style="font-weight:600;margin:12px 0 6px;">Saat seç</div>
      <div class="sb-time-grid" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
    `;
    actions.parentNode.insertBefore(timeRow, actions);
    timeGridEl = timeRow.querySelector(".sb-time-grid");

    const makeTimeBtn = (t) => {
      const b = document.createElement("button");
      b.type = "button";
      b.className = "sb-time-pill";
      b.dataset.time = t;
      b.textContent = t;
      b.style.border = "1px solid var(--border, #ddd)";
      b.style.borderRadius = "999px";
      b.style.padding = "4px 10px";
      b.style.fontSize = "12px";
      b.style.cursor = "pointer";
      b.style.background = "#fff";
      b.style.color = "#111";
      b.addEventListener("click", () => highlightTime(t));
      return b;
    };

    // ✅ Güncel slotlar: 09:00–15:00 ve 16:00–21:00 (15 dk adım, 15:00–16:00 arası yok)
    const slots = [];
    const ranges = [
      { from: parseHHMM("09:00").min, to: parseHHMM("15:00").min },
      { from: parseHHMM("16:00").min, to: parseHHMM("21:00").min },
    ];
    for (const r of ranges) {
      for (let t = r.from; t <= r.to; t += 15) {
        const hh = Math.floor(t / 60);
        const mm = t % 60;
        slots.push(`${pad(hh)}:${pad(mm)}`);
      }
    }
    slots.forEach((t) => timeGridEl.appendChild(makeTimeBtn(t)));
  }

  const show = () => {
    if (input && input.value) {
      const parts = input.value.trim().split(/\s+/);
      if (parts[1] && /^\d{1,2}:\d{2}$/.test(parts[1])) {
        highlightTime(parts[1]);
      }
    }
    ov.setAttribute("aria-hidden", "false");
  };
  const hide = () => ov.setAttribute("aria-hidden", "true");

  input?.addEventListener("click", show);
  input?.addEventListener("focus", show);
  close?.addEventListener("click", hide);
  ov.addEventListener("click", (e) => {
    if (e.target === ov) hide();
  });
  document.addEventListener("keydown", (e) => {
    if (e.key === "Escape" && ov.getAttribute("aria-hidden") === "false") hide();
  });

  const readSelectedYMD = () => {
    const sel = grid?.querySelector('[aria-selected="true"], .selected, .active');
    if (!sel) return "";
    let ymdStr = sel.dataset?.date || sel.dataset?.ymd || "";
    if (ymdStr) return ymdStr;

    const label = (monthEl?.textContent || "").trim();
    const [monName, yearStr] = label.split(/\s+/);
    const m = trMonths.findIndex((mn) => mn.toLowerCase() === (monName || "").toLowerCase());
    const d = new Date(Number(yearStr || 0), m < 0 ? 0 : m, Number(sel.textContent || 1));
    const YY = d.getFullYear();
    const MM = String(d.getMonth() + 1).padStart(2, "0");
    const DD = String(d.getDate()).padStart(2, "0");
    return `${YY}-${MM}-${DD}`;
  };

  okBtn?.addEventListener("click", () => {
    const val = readSelectedYMD();
    if (!val) return;

    const d = new Date(val);
    if (!inBookingWindow(d)) {
      showToast("Sadece bugün ve sonraki 9 güne randevu alınabilir.");
      return;
    }

    let out = val;
    if (qSelectedTime) out = `${val} ${qSelectedTime}`;

    if (input) {
      input.value = out;
      input.dispatchEvent(new Event("change"));
    }

    try {
      const svcVal = (document.getElementById("qSvc")?.value || "").trim();
      const locVal = (document.getElementById("qLoc")?.value || "").trim();

      const url = new URL("kuafor.html", window.location.origin);

      if (svcVal) url.searchParams.set("svc", svcVal);
      if (locVal) url.searchParams.set("loc", locVal);
      url.searchParams.set("date", val);
      if (qSelectedTime) url.searchParams.set("time", qSelectedTime);

      hide();
      window.location.href = url.toString();
    } catch (e) {
      console.warn("[date overlay] redirect error", e);
      hide();
    }
  });

  clrBtn?.addEventListener("click", () => {
    if (input) {
      input.value = "";
      input.dispatchEvent(new Event("change"));
    }
    resetTimeSelection();
    hide();
  });
}

/* =========================================================
   DOM-ready init (tek yerden)
   ========================================================= */

onReady(() => {
  cacheProfileDomRefs();
  attachModalBasics();
  initSliderAndLightbox();
  initWeekToggle();
  removePeriodChips();
  bindTimeDone();
  bindConfirmBook();
  bindDateOverlayFailsafe();

  // Sticky ve yan kart butonu
  const btnOpen = document.getElementById("btnOpenBook");
  if (btnOpen && !btnOpen._bound) {
    btnOpen._bound = true;
    btnOpen.addEventListener("click", () => {
      renderServicePicker(window.__lastServicesList || []);
      showOv("svcOv");
    });
  }

  load();
});
/* ════════════════════════════════════════════════
   FAVORİ TOGGLE — profile.js eki
   ════════════════════════════════════════════════ */

(function initFavToggle() {
  // DOM-ready olana kadar bekle
  function run() {
    const btn  = document.getElementById('favToggleBtn');
    const icon = document.getElementById('favIcon');
    if (!btn || !icon) return;

    let _bizId = null;
    let _favorited = false;
    let _loading = false;

    // currentBizId yüklenince biz ID'yi al (load() async olduğu için polling)
    function waitForBizId(cb, tries = 0) {
      if (typeof currentBizId !== 'undefined' && currentBizId) {
        cb(currentBizId);
      } else if (tries < 40) {
        setTimeout(() => waitForBizId(cb, tries + 1), 150);
      }
    }

    function setFavState(favorited) {
      _favorited = favorited;
      icon.className = favorited ? 'fas fa-heart' : 'fa-regular fa-heart';
      btn.setAttribute('aria-label', favorited ? 'Favorilerden çıkar' : 'Favorilere ekle');
      btn.title = favorited ? 'Favorilerden çıkar' : 'Favorilere ekle';
      btn.classList.toggle('fav-toggle-btn--active', favorited);
    }

    async function checkFavStatus(bizId) {
      try {
        const json = await apiGet(`/api/user/favorites/check.php`, { business_id: bizId });
        if (json?.favorited !== undefined) setFavState(!!json.favorited);
      } catch {}
      btn.style.display = '';
    }

    async function toggleFav() {
      if (_loading || !_bizId) return;
      _loading = true;
      btn.disabled = true;

      // Optimistic update
      setFavState(!_favorited);

      try {
        const json = await apiPost('/api/user/favorites/toggle.php', { business_id: +_bizId });

        if (!json) {
          // 401 → auth modal (WbApi handles redirect for booking pages; here open modal)
          setFavState(!_favorited); // geri al
          document.querySelector('.open-auth')?.click();
        } else if (json?.favorited !== undefined) {
          setFavState(!!json.favorited);
          // Başarı animasyonu
          icon.style.transform = 'scale(1.4)';
          setTimeout(() => { icon.style.transform = ''; }, 250);
        } else {
          setFavState(!_favorited); // geri al
        }
      } catch {
        setFavState(!_favorited);
      }

      _loading = false;
      btn.disabled = false;
    }

    // Başlangıçta gizli
    btn.style.display = 'none';

    waitForBizId(async (bizId) => {
      _bizId = bizId;
      await checkFavStatus(bizId);
    });

    btn.addEventListener('click', toggleFav);
    // Icon geçiş animasyonu
    icon.style.transition = 'transform .2s ease';
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', run);
  } else {
    run();
  }
})();

/* ════════════════════════════════════════════════
   YORUM & PUAN SİSTEMİ — profile.js eki
   ════════════════════════════════════════════════ */

/* ─── Yardımcılar ─── */
const esc = s => String(s || '').replace(/[&<>"']/g, m => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[m]));

function starsHtml(rating, max = 5) {
  let h = '';
  for (let i = 1; i <= max; i++) {
    if (rating >= i)       h += '<i class="fas fa-star rev-star rev-star--full" aria-hidden="true"></i>';
    else if (rating >= i - 0.5) h += '<i class="fas fa-star-half-stroke rev-star rev-star--half" aria-hidden="true"></i>';
    else                   h += '<i class="far fa-star rev-star rev-star--empty" aria-hidden="true"></i>';
  }
  return h;
}

function relativeDate(dateStr) {
  const d = new Date(dateStr);
  if (isNaN(d)) return '';
  const diff = (Date.now() - d.getTime()) / 1000;
  if (diff < 60)     return 'Az önce';
  if (diff < 3600)   return `${Math.floor(diff/60)} dk önce`;
  if (diff < 86400)  return `${Math.floor(diff/3600)} sa önce`;
  if (diff < 604800) return `${Math.floor(diff/86400)} gün önce`;
  return d.toLocaleDateString('tr-TR', { day:'2-digit', month:'long', year:'numeric' });
}

/* ─── State ─── */
let _reviewPage = 1;
let _reviewHasMore = false;
let _reviewLoading = false;
let _selectedRating = 0;
let _eligibleAppointments = []; // yorum yapılabilecek randevular
let _selectedApptId = null;
let _selectedReviewTarget = 'business'; // 'business' | 'staff'
let _selectedReviewStaffId = null;

/* ─── DOM refs ─── */
const reviewsSection  = () => document.getElementById('reviewsSection');
const reviewsList     = () => document.getElementById('reviewsList');
const reviewsEmpty    = () => document.getElementById('reviewsEmpty');
const reviewsLoading  = () => document.getElementById('reviewsLoading');
const reviewsLoadMore = () => document.getElementById('reviewsLoadMore');
const reviewsSummary  = () => document.getElementById('reviewsSummary');
const rsAvg           = () => document.getElementById('rsAvg');
const rsStars         = () => document.getElementById('rsStars');
const rsCount         = () => document.getElementById('rsCount');
const rsBars          = () => document.getElementById('rsBars');
const bizRatingRow    = () => document.getElementById('bizRatingRow');
const bizStars        = () => document.getElementById('bizStars');
const bizRatingAvg    = () => document.getElementById('bizRatingAvg');
const bizRatingCount  = () => document.getElementById('bizRatingCount');
const btnWriteReview  = () => document.getElementById('btnWriteReview');

/* ─── Ortalama puan → biz header'a yaz ─── */
function paintBizRating(avg, total) {
  const row = bizRatingRow();
  if (!row) return;
  if (!total) { row.style.display = 'none'; return; }

  const stars = bizStars();
  const avgEl = bizRatingAvg();
  const cntEl = bizRatingCount();

  if (stars) stars.innerHTML = starsHtml(avg);
  if (avgEl) avgEl.textContent = avg.toFixed(1);
  if (cntEl) {
    cntEl.textContent = `(${total} yorum)`;
    cntEl.onclick = () => {
      reviewsSection()?.scrollIntoView({ behavior: 'smooth', block: 'start' });
    };
  }
  row.style.display = 'flex';
}

/* ─── İstatistik özeti ─── */
function paintReviewStats(stats) {
  const summary = reviewsSummary();
  if (!summary) return;

  if (!stats.total) { summary.style.display = 'none'; return; }
  summary.style.display = 'flex';

  const avgEl   = rsAvg();
  const starsEl = rsStars();
  const cntEl   = rsCount();
  const barsEl  = rsBars();

  if (avgEl)   avgEl.textContent = stats.avg_rating.toFixed(1);
  if (starsEl) starsEl.innerHTML = starsHtml(stats.avg_rating);
  if (cntEl)   cntEl.textContent = `${stats.total} değerlendirme`;

  if (barsEl && stats.breakdown) {
    barsEl.innerHTML = [5, 4, 3, 2, 1].map(n => {
      const count = stats.breakdown[n] || 0;
      const pct   = stats.total ? Math.round((count / stats.total) * 100) : 0;
      return `
        <div class="rs-bar-row">
          <span class="rs-bar-label">${n}</span>
          <i class="fas fa-star rs-bar-star" aria-hidden="true"></i>
          <div class="rs-bar-track"><div class="rs-bar-fill" style="width:${pct}%"></div></div>
          <span class="rs-bar-pct">${count}</span>
        </div>`;
    }).join('');
  }

  // Global puan satırını da güncelle
  paintBizRating(stats.avg_rating, stats.total);
}

/* ─── Yorum kartı ─── */
function buildReviewCard(r) {
  const stars     = starsHtml(r.rating);
  const date      = relativeDate(r.created_at);
  const comment   = r.comment ? `<p class="rev-comment">${esc(r.comment)}</p>` : '';
  const replyHtml = r.reply ? `
    <div class="rev-reply">
      <div class="rev-reply-label"><i class="fas fa-store" aria-hidden="true"></i> İşletme yanıtı</div>
      <p class="rev-reply-text">${esc(r.reply)}</p>
    </div>` : '';

  return `
    <div class="rev-card ${r.is_mine ? 'rev-card--mine' : ''}" data-review-id="${r.id}">
      <div class="rev-top">
        <div class="rev-avatar" aria-hidden="true">${esc(r.reviewer_name[0] || '?')}</div>
        <div class="rev-meta">
          <div class="rev-name">${esc(r.reviewer_name)}${r.is_mine ? ' <span class="rev-mine-badge">Sen</span>' : ''}</div>
          <div class="rev-date">${esc(date)}</div>
        </div>
        <div class="rev-stars-wrap" aria-label="${r.rating} yıldız">${stars}</div>
      </div>
      ${comment}
      ${replyHtml}
    </div>`;
}

/* ─── Yorum listesini yükle ─── */
async function loadReviews(append = false) {
  if (_reviewLoading) return;
  _reviewLoading = true;

  const listEl    = reviewsList();
  const loadingEl = reviewsLoading();
  const emptyEl   = reviewsEmpty();
  const moreBtn   = reviewsLoadMore();

  if (loadingEl) loadingEl.style.display = 'flex';
  if (moreBtn)   moreBtn.style.display   = 'none';

  try {
    const json = await apiGet(`/api/reviews/list.php`, { business_id: currentBizId, page: _reviewPage, limit: 5 });
    if (!json) throw new Error('Bağlantı hatası');

    const { stats, reviews, has_more } = json;

    if (_reviewPage === 1) {
      paintReviewStats(stats);
    }

    _reviewHasMore = !!has_more;

    if (!append) listEl.innerHTML = '';

    if (!reviews.length && _reviewPage === 1) {
      if (emptyEl) emptyEl.style.display = 'block';
    } else {
      if (emptyEl) emptyEl.style.display = 'none';
      listEl.innerHTML += reviews.map(buildReviewCard).join('');
    }

    if (moreBtn) moreBtn.style.display = _reviewHasMore ? 'block' : 'none';
  } catch (e) {
    console.warn('[reviews] load error', e);
  } finally {
    if (loadingEl) loadingEl.style.display = 'none';
    _reviewLoading = false;
  }
}

/* ─── Yorum yapabilir mi? ─── */
async function checkCanReview() {
  const btn = btnWriteReview();
  if (!btn) return;
  if (!currentBizId) return;

  try {
    const json = await apiGet(`/api/reviews/can-review.php`, { business_id: currentBizId });
    if (!json) return;

    _eligibleAppointments = json?.eligible || [];

    if (_eligibleAppointments.length > 0) {
      btn.style.display = 'inline-flex';
    }
  } catch {}
}

/* ─── Yorum yazma modali ─── */
function openWriteReview() {
  if (!_eligibleAppointments.length) {
    document.querySelector('.open-auth')?.click();
    return;
  }

  _selectedRating = 0;
  _selectedReviewTarget = 'business';
  _selectedReviewStaffId = null;

  // Form reset
  const textarea   = document.getElementById('rwComment');
  const charCount  = document.getElementById('rwCharCount');
  const errorEl    = document.getElementById('rwError');
  const submitBtn  = document.getElementById('rwSubmit');
  const bizNameEl  = document.getElementById('rwBizName');

  if (textarea)  textarea.value = '';
  if (charCount) charCount.textContent = '0';
  if (errorEl)  { errorEl.style.display = 'none'; errorEl.textContent = ''; }
  if (submitBtn) submitBtn.disabled = true;
  if (bizNameEl) bizNameEl.textContent = document.getElementById('bizName')?.textContent || '';

  // ── Randevu bilgisi & seçici ──────────────────────────────────────────
  function setActiveAppt(appt) {
    _selectedApptId = appt.appointment_id;
    _selectedReviewStaffId = appt.staff_id || null;

    const card     = document.getElementById('rwApptCard');
    const infoEl   = document.getElementById('rwApptInfo');
    const staffEl  = document.getElementById('rwApptStaff');

    if (card) card.style.display = 'flex';

    if (infoEl && appt.start_at) {
      const d = new Date(appt.start_at);
      const label = d.toLocaleDateString('tr-TR', { day:'2-digit', month:'long', year:'numeric' });
      const time  = d.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' });
      const svc   = appt.service_name || 'Randevu';
      infoEl.textContent = `${svc} · ${label}, ${time}`;
    }

    if (staffEl) {
      if (appt.staff_name) {
        staffEl.textContent = `✂️ ${appt.staff_name}`;
        staffEl.style.display = 'block';
      } else {
        staffEl.style.display = 'none';
      }
    }

    // Hedef seçimini güncelle
    const targetSection  = document.getElementById('rwTargetSection');
    const targetStaffBtn = document.getElementById('rwTargetStaff');
    const staffNameSpan  = document.getElementById('rwTargetStaffName');

    if (appt.staff_name && targetSection && targetStaffBtn) {
      targetSection.style.display = 'block';
      if (staffNameSpan) staffNameSpan.textContent = appt.staff_name;
    } else if (targetSection) {
      targetSection.style.display = 'none';
    }

    // Seçili option'ı işaretle
    document.querySelectorAll('.rw-appt-opt').forEach(o => {
      o.classList.toggle('active', +o.dataset.apptId === appt.appointment_id);
    });
  }

  // Çoklu randevu seçici
  const apptSelector = document.getElementById('rwApptSelector');
  const apptListEl   = document.getElementById('rwApptList');

  if (_eligibleAppointments.length > 1 && apptSelector && apptListEl) {
    apptSelector.style.display = 'block';
    apptListEl.innerHTML = _eligibleAppointments.map(a => {
      const d   = new Date(a.start_at);
      const lbl = d.toLocaleDateString('tr-TR', { day:'2-digit', month:'short', year:'numeric' });
      const t   = d.toLocaleTimeString('tr-TR', { hour:'2-digit', minute:'2-digit' });
      const svc = a.service_name || 'Randevu';
      const staff = a.staff_name ? ` · ✂️ ${escapeHTML(a.staff_name)}` : '';
      return `
        <div class="rw-appt-opt" data-appt-id="${a.appointment_id}">
          <div>
            <div class="rw-opt-date">${escapeHTML(svc)}${staff}</div>
            <div class="rw-opt-svc">${escapeHTML(lbl)}, ${escapeHTML(t)}</div>
          </div>
        </div>`;
    }).join('');

    apptListEl.querySelectorAll('.rw-appt-opt').forEach(btn => {
      btn.addEventListener('click', () => {
        const appt = _eligibleAppointments.find(a => a.appointment_id === +btn.dataset.apptId);
        if (appt) setActiveAppt(appt);
      });
    });
  } else {
    if (apptSelector) apptSelector.style.display = 'none';
  }

  setActiveAppt(_eligibleAppointments[0]);
  paintStarPicker(0);
  showOv('writeReviewOv');
}

/* ─── Yıldız seçici ─── */
function paintStarPicker(val) {
  document.querySelectorAll('#rwStarsPick .rw-star').forEach(btn => {
    const v = +btn.dataset.val;
    const icon = btn.querySelector('i');
    if (!icon) return;
    icon.className = v <= val ? 'fas fa-star' : 'fa-regular fa-star';
    btn.classList.toggle('rw-star--active', v <= val);
  });

  const labels = ['', 'Berbat', 'Kötü', 'İdare eder', 'İyi', 'Mükemmel!'];
  const labelEl = document.getElementById('rwStarLabel');
  if (labelEl) labelEl.textContent = val ? labels[val] : 'Puan seçin';

  const submitBtn = document.getElementById('rwSubmit');
  if (submitBtn) submitBtn.disabled = val < 1;
}

/* ─── Yorum gönder ─── */
async function submitReview() {
  if (!_selectedApptId || !_selectedRating) return;

  const submitBtn = document.getElementById('rwSubmit');
  const errorEl   = document.getElementById('rwError');
  const comment   = document.getElementById('rwComment')?.value?.trim() || '';

  if (submitBtn) { submitBtn.disabled = true; submitBtn.textContent = 'Gönderiliyor…'; }
  if (errorEl)   errorEl.style.display = 'none';

  try {
    const json = await apiPost('/api/reviews/submit.php', {
      appointment_id: _selectedApptId,
      rating: _selectedRating,
      comment,
      staff_id:      _selectedReviewTarget === 'staff' ? _selectedReviewStaffId : null,
      review_target: _selectedReviewTarget,
    });

    if (!json || !json.ok) {
      throw new Error(json?.message || json?.error || 'Bir hata oluştu');
    }

    // Başarı
    closeOv('writeReviewOv');
    showToast?.('Yorumunuz için teşekkürler! 🌟', true);

    // Puanı güncelle
    const { avg_rating, total } = json.data;
    paintBizRating(avg_rating, total);

    // Yorumları baştan yükle
    _reviewPage = 1;
    await loadReviews(false);

    // Yorum butonu gizle (zaten yorum yaptı)
    _eligibleAppointments = _eligibleAppointments.filter(a => a.appointment_id !== _selectedApptId);
    if (_eligibleAppointments.length === 0) {
      const btn = btnWriteReview();
      if (btn) btn.style.display = 'none';
    }

  } catch (e) {
    if (errorEl) { errorEl.textContent = e.message; errorEl.style.display = 'block'; }
    if (submitBtn) { submitBtn.disabled = false; submitBtn.textContent = 'Gönder'; }
  }
}

/* ─── Event binders ─── */
function bindReviewEvents() {
  // Yorum yaz butonu
  document.getElementById('btnWriteReview')?.addEventListener('click', openWriteReview);

  // Hedef seçimi (işletme / çalışan)
  document.querySelectorAll('.rw-target-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.rw-target-btn').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      _selectedReviewTarget = btn.dataset.target || 'business';
    });
  });

  // Yıldız seçici hover + tıklama
  document.querySelectorAll('#rwStarsPick .rw-star').forEach(btn => {
    btn.addEventListener('mouseenter', () => paintStarPicker(+btn.dataset.val));
    btn.addEventListener('mouseleave', () => paintStarPicker(_selectedRating));
    btn.addEventListener('click', () => {
      _selectedRating = +btn.dataset.val;
      paintStarPicker(_selectedRating);
    });
  });

  // Textarea karakter sayacı
  document.getElementById('rwComment')?.addEventListener('input', function() {
    const el = document.getElementById('rwCharCount');
    if (el) el.textContent = this.value.length;
  });

  // Gönder
  document.getElementById('rwSubmit')?.addEventListener('click', submitReview);

  // Daha fazla yükle
  document.getElementById('reviewsLoadMore')?.addEventListener('click', () => {
    _reviewPage++;
    loadReviews(true);
  });
}

/* ─── Başlat ─── */
async function initReviews() {
  // currentBizId yüklenene kadar bekle
  function waitBizId(cb, tries = 0) {
    if (typeof currentBizId !== 'undefined' && currentBizId) cb();
    else if (tries < 40) setTimeout(() => waitBizId(cb, tries + 1), 150);
  }

  waitBizId(async () => {
    bindReviewEvents();
    await loadReviews();
    await checkCanReview();
  });
}

// DOM ready
if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initReviews);
} else {
  initReviews();
}

// Sayfa kapanırken aktif slot kilidini serbest bırak
window.addEventListener("beforeunload", () => {
  if (slotLock.token) {
    // sendBeacon: sayfa kapanırken fetch güvenilir değil
    navigator.sendBeacon("/api/appointments/unlock.php", JSON.stringify({ token: slotLock.token }));
  }
});