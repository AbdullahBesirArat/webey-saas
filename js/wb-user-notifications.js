/**
 * wb-user-notifications.js
 * Webey — Müşteri (user) Bildirim Sistemi
 *
 * - Giriş yapmış kullanıcıya tüm sayfalarda sol-alt toast bildirim gösterir
 * - Yeni bildirimler için poll eder (30sn)
 * - window.wbUserNotif.openPanel() ile user-profile'daki panel açılabilir
 */
(function () {
  'use strict';

  const POLL_INTERVAL = 30000; // 30sn
  const SESSION_CHECK = 60000; // 60sn
  const API_LIST      = '/api/user/notifications/list.php';
  const API_MARK_READ = '/api/user/notifications/mark-read.php';

  let _userId      = null;
  let _pollTimer   = null;
  let _sessTimer   = null;
  let _seenIds     = new Set();
  let _initialized = false;
  let _unreadCount = 0;
  let _csrfToken   = null;

  /* ── Helpers ── */
  function escHtml(s = '') {
    return String(s)
      .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;').replace(/'/g, '&#39;');
  }

  function fmtTime(iso) {
    if (!iso) return '';
    const d = new Date(String(iso).replace(' ', 'T'));
    if (isNaN(d)) return '';
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60)   return 'Az önce';
    if (diff < 3600) return Math.floor(diff / 60) + ' dk önce';
    if (diff < 86400) return Math.floor(diff / 3600) + ' sa önce';
    return d.toLocaleDateString('tr-TR', { day: '2-digit', month: 'short' });
  }

  function iconFor(type) {
    const map = {
      appt_approved:  '✅',
      appt_cancelled: '❌',
      appt_rejected:  '🚫',
      appt_reminder:  '🔔',
      info:           'ℹ️',
    };
    return map[type] || '🔔';
  }

  function colorFor(type) {
    if (type === 'appt_approved') return '#10b981';
    if (type === 'appt_cancelled' || type === 'appt_rejected') return '#ef4444';
    return '#6366f1';
  }

  /* ── Toast Container ── */
  function getToastContainer() {
    let el = document.getElementById('wbUserToastContainer');
    if (!el) {
      el = document.createElement('div');
      el.id = 'wbUserToastContainer';
      el.setAttribute('aria-live', 'polite');
      el.setAttribute('aria-atomic', 'false');
      document.body.appendChild(el);
      injectStyles();
    }
    return el;
  }

  function injectStyles() {
    if (document.getElementById('wbUserNotifStyles')) return;
    const style = document.createElement('style');
    style.id = 'wbUserNotifStyles';
    style.textContent = `
      /* ── Toast Container ── */
      #wbUserToastContainer {
        position: fixed;
        bottom: 80px;
        left: 16px;
        z-index: 90;
        display: flex;
        flex-direction: column-reverse;
        gap: 10px;
        max-width: min(340px, calc(100vw - 32px));
        pointer-events: none;
      }

      /* ── Tek Toast ── */
      .wb-user-toast {
        pointer-events: all;
        background: #1e2027;
        color: #f1f5f9;
        border-radius: 14px;
        padding: 14px 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,.45);
        display: flex;
        align-items: flex-start;
        gap: 12px;
        animation: wbToastIn .35s cubic-bezier(.34,1.56,.64,1) forwards;
        border-left: 4px solid var(--wb-toast-color, #6366f1);
        min-width: 260px;
      }
      .wb-user-toast.leaving {
        animation: wbToastOut .28s ease forwards;
      }
      @keyframes wbToastIn {
        from { opacity: 0; transform: translateY(20px) scale(.92); }
        to   { opacity: 1; transform: translateY(0)   scale(1); }
      }
      @keyframes wbToastOut {
        to { opacity: 0; transform: translateY(10px) scale(.95); }
      }
      .wb-toast-icon {
        font-size: 22px;
        line-height: 1;
        flex-shrink: 0;
        margin-top: 1px;
      }
      .wb-toast-body {
        flex: 1;
        min-width: 0;
      }
      .wb-toast-title {
        font-weight: 700;
        font-size: 13.5px;
        line-height: 1.3;
        margin-bottom: 3px;
        color: #f8fafc;
      }
      .wb-toast-msg {
        font-size: 12px;
        color: #94a3b8;
        line-height: 1.4;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
      }
      .wb-toast-time {
        font-size: 10.5px;
        color: #64748b;
        margin-top: 4px;
      }
      .wb-toast-close {
        flex-shrink: 0;
        background: none;
        border: none;
        color: #64748b;
        cursor: pointer;
        font-size: 15px;
        line-height: 1;
        padding: 2px 4px;
        border-radius: 6px;
        transition: color .15s;
      }
      .wb-toast-close:hover { color: #f1f5f9; }

      /* ── Profile sayfası bildirim panel ── */
      .wb-user-notif-list { list-style: none; padding: 0; margin: 0; }
      .wb-user-notif-item {
        display: flex;
        align-items: flex-start;
        gap: 12px;
        padding: 14px 0;
        border-bottom: 1px solid #f1f5f9;
        position: relative;
        cursor: default;
      }
      .wb-user-notif-item:last-child { border-bottom: none; }
      .wb-user-notif-item.unread { background: #f8faff; border-radius: 10px; padding: 14px 12px; margin-bottom: 4px; }
      .wb-user-notif-dot {
        position: absolute;
        top: 18px; right: 0;
        width: 8px; height: 8px;
        border-radius: 50%;
        background: #6366f1;
        display: none;
      }
      .wb-user-notif-item.unread .wb-user-notif-dot { display: block; }
      .wb-notif-icon-wrap {
        width: 40px; height: 40px;
        border-radius: 50%;
        background: #f3f4f6;
        display: flex; align-items: center; justify-content: center;
        font-size: 18px;
        flex-shrink: 0;
      }
      .wb-notif-content { flex: 1; min-width: 0; }
      .wb-notif-title {
        font-size: 14px;
        font-weight: 700;
        color: #111827;
        margin-bottom: 3px;
      }
      .wb-notif-msg {
        font-size: 12.5px;
        color: #6b7280;
        line-height: 1.45;
      }
      .wb-notif-time {
        font-size: 11px;
        color: #9ca3af;
        margin-top: 4px;
      }
      .wb-notif-empty {
        text-align: center;
        padding: 50px 20px;
        color: #9ca3af;
      }
      .wb-notif-empty-icon { font-size: 40px; margin-bottom: 12px; }
      .wb-notif-empty-txt  { font-size: 14px; }
      .wb-notif-mark-all {
        background: none;
        border: none;
        color: var(--brand, #0ea5b3);
        font-size: 13px;
        font-weight: 600;
        cursor: pointer;
        padding: 0;
        text-decoration: underline;
      }
      .wb-notif-mark-all:hover { opacity: .75; }
      .wb-notif-badge {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        min-width: 20px;
        height: 20px;
        border-radius: 10px;
        background: #ef4444;
        color: #fff;
        font-size: 11px;
        font-weight: 700;
        padding: 0 5px;
        margin-left: 7px;
      }
      .wb-notif-badge.hidden { display: none; }
    `;
    document.head.appendChild(style);
  }

  /* ── Toast göster ── */
  function showToast(notif) {
    injectStyles();
    const container = getToastContainer();
    const color = colorFor(notif.type);
    const icon  = iconFor(notif.type);

    const toast = document.createElement('div');
    toast.className = 'wb-user-toast';
    toast.style.setProperty('--wb-toast-color', color);
    toast.innerHTML = `
      <div class="wb-toast-icon">${icon}</div>
      <div class="wb-toast-body">
        <div class="wb-toast-title">${escHtml(notif.title)}</div>
        ${notif.message ? `<div class="wb-toast-msg">${escHtml(notif.message)}</div>` : ''}
        <div class="wb-toast-time">${fmtTime(notif.createdAt)}</div>
      </div>
      <button class="wb-toast-close" title="Kapat">✕</button>
    `;

    const close = toast.querySelector('.wb-toast-close');
    const dismiss = () => {
      toast.classList.add('leaving');
      toast.addEventListener('animationend', () => toast.remove(), { once: true });
    };
    close.addEventListener('click', dismiss);

    // Profile sayfasındaysa tıklayınca Bildirimler sekmesine git
    toast.addEventListener('click', (e) => {
      if (e.target === close) return;
      if (window.wbUserNotif?.openTab) {
        window.wbUserNotif.openTab();
        dismiss();
      }
    });

    container.appendChild(toast);

    // Ses (varsa)
    const sound = document.getElementById('wbUserNotifSound');
    if (sound) { try { sound.play()?.catch(() => {}); } catch {} }

    // 6sn sonra otomatik kapat
    setTimeout(dismiss, 6000);
  }

  /* ── Unread badge güncelle ── */
  function updateBadges(count) {
    _unreadCount = count;
    // user-profile menu badge
    document.querySelectorAll('.wb-notif-menu-badge').forEach(el => {
      el.textContent = count > 99 ? '99+' : String(count);
      el.classList.toggle('hidden', count === 0);
    });
  }

  /* ── Bildirim panelini doldur (user-profile.html) ── */
  function renderPanel(notifications) {
    const container = document.getElementById('wbUserNotifPanel');
    if (!container) return;

    if (!notifications.length) {
      container.innerHTML = `
        <div class="wb-notif-empty">
          <div class="wb-notif-empty-icon">🔔</div>
          <div class="wb-notif-empty-txt">Henüz bildiriminiz yok</div>
        </div>`;
      return;
    }

    const ul = document.createElement('ul');
    ul.className = 'wb-user-notif-list';

    notifications.forEach(n => {
      const li = document.createElement('li');
      li.className = 'wb-user-notif-item' + (n.isRead ? '' : ' unread');
      li.dataset.id = n.id;
      li.innerHTML = `
        <div class="wb-user-notif-dot"></div>
        <div class="wb-notif-icon-wrap" style="background:${colorFor(n.type)}22">${iconFor(n.type)}</div>
        <div class="wb-notif-content">
          <div class="wb-notif-title">${escHtml(n.title)}</div>
          ${n.message ? `<div class="wb-notif-msg">${escHtml(n.message)}</div>` : ''}
          <div class="wb-notif-time">${fmtTime(n.createdAt)}</div>
        </div>
      `;
      // Tıklayınca okundu işaretle
      if (!n.isRead) {
        li.addEventListener('click', async () => {
          li.classList.remove('unread');
          li.querySelector('.wb-user-notif-dot')?.remove();
          try {
            await fetch(API_MARK_READ, {
              method: 'POST', credentials: 'same-origin',
              headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken || window.__csrfToken || '' },
              body: JSON.stringify({ ids: [n.id] }),
            });
          } catch {}
          _unreadCount = Math.max(0, _unreadCount - 1);
          updateBadges(_unreadCount);
        });
      }
      ul.appendChild(li);
    });

    container.innerHTML = '';
    container.appendChild(ul);
  }

  /* ── API ── */
  async function fetchNotifications(unreadOnly = false) {
    try {
      const url = API_LIST + (unreadOnly ? '?unread_only=1' : '?limit=50');
      const res = await fetch(url, { credentials: 'same-origin' });
      if (res.status === 401) { _userId = null; stopPolling(); return null; }
      const json = await res.json();
      if (!json.ok) return null;
      return json.data;
    } catch { return null; }
  }

  /* ── Poll ── */
  async function poll() {
    if (!_userId) return;

    const data = await fetchNotifications(false);
    if (!data) return;

    updateBadges(data.unreadCount);

    // Yeni okunmamışları toast ile göster
    data.notifications
      .filter(n => !n.isRead && !_seenIds.has(n.id))
      .forEach(n => {
        _seenIds.add(n.id);
        showToast(n);
      });

    // Zaten görülmüş olanları seenIds'e ekle (toast gösterme)
    data.notifications.forEach(n => _seenIds.add(n.id));

    // user-profile paneli açıksa güncelle
    const panel = document.getElementById('wbUserNotifPanel');
    if (panel && panel.closest('#page-notifications')?.classList.contains('show')) {
      renderPanel(data.notifications);
    }
  }

  /* ── Profil sekmesi için tam yükle ── */
  async function loadPanelData() {
    const panel = document.getElementById('wbUserNotifPanel');
    if (!panel) return;

    panel.innerHTML = '<div style="text-align:center;padding:40px;color:#9ca3af">Yükleniyor…</div>';
    const data = await fetchNotifications(false);
    if (!data) {
      panel.innerHTML = '<div style="text-align:center;padding:40px;color:#ef4444">Bildirimler yüklenemedi.</div>';
      return;
    }
    updateBadges(data.unreadCount);
    renderPanel(data.notifications);
  }

  function startPolling() {
    stopPolling();
    poll();
    _pollTimer = setInterval(poll, POLL_INTERVAL);
  }

  function stopPolling() {
    clearInterval(_pollTimer);
    _pollTimer = null;
  }

  /* ── Session ── */
  async function checkSession() {
    try {
      const res = await fetch('/api/session/me.php', { credentials: 'same-origin' });
      if (!res.ok) { _userId = null; stopPolling(); return; }
      const json = await res.json();
      // CSRF token'ı session/me.php'den al ve sakla
      if (json?.ok && json.data?.csrf_token) {
        _csrfToken = json.data.csrf_token;
        window.__csrfToken = _csrfToken;
      }
      const isUser = json.ok && json.data?.role === 'user';

      if (isUser && !_userId) {
        _userId = json.data.userId || json.data.id || true;
        if (!_initialized) {
          _initialized = true;
          // Ses elementi
          if (!document.getElementById('wbUserNotifSound')) {
            const audio = document.createElement('audio');
            audio.id = 'wbUserNotifSound';
            audio.src = '/sounds/notificationSound1.mp3';
            audio.preload = 'auto';
            document.body.appendChild(audio);
          }
          injectStyles();
        }
        // İlk poll: mevcut bildirimleri seenIds'e ekle (tekrar toast gösterme)
        const initial = await fetchNotifications(false);
        if (initial) {
          updateBadges(initial.unreadCount);
          initial.notifications.forEach(n => _seenIds.add(n.id));
        }
        startPolling();
      } else if (!isUser) {
        _userId = null;
        stopPolling();
      }
    } catch {}
  }

  /* ── Tab visibility ── */
  document.addEventListener('visibilitychange', () => {
    if (!document.hidden && _userId) poll();
  });

  /* ── Init ── */
  function init() {
    checkSession();
    _sessTimer = setInterval(checkSession, SESSION_CHECK);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', init);
  } else {
    init();
  }

  /* ── Global API ── */
  window.wbUserNotif = {
    loadPanelData,
    updateBadges: () => updateBadges(_unreadCount),
    openTab: () => {
      // user-profile.html'de Bildirimler sekmesini aç
      const btn = document.querySelector('[data-goto="notifications"]');
      if (btn) btn.click();
    },
    markAllRead: async () => {
      try {
        await fetch(API_MARK_READ, {
          method: 'POST', credentials: 'same-origin',
          headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': _csrfToken || window.__csrfToken || '' },
          body: JSON.stringify({ all: true }),
        });
        document.querySelectorAll('.wb-user-notif-item.unread').forEach(el => {
          el.classList.remove('unread');
          el.querySelector('.wb-user-notif-dot')?.remove();
        });
        updateBadges(0);
      } catch {}
    },
  };

})();