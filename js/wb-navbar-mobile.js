/**
 * wb-navbar-mobile.js — Mobil Navbar Scroll Collapse
 * ─────────────────────────────────────────────────────
 * Tüm sayfalarda ortak kullanılır.
 * 60px scroll geçilince:  expanded bölüm gizlenir, compact logo belirir.
 * Scroll başa dönünce:    expanded geri açılır.
 *
 * Sayfa özelleştirmeleri için 'wb:navbarCollapse' event'ini dinleyin:
 *   document.addEventListener('wb:navbarCollapse', function(e) {
 *     // e.detail.collapsed → true/false
 *     updateMyPageLayout();
 *   });
 *
 * CSS değişkeni: --mob-navbar-h (navbar'ın anlık yüksekliği px olarak)
 */
(function () {
  'use strict';

  function initNavScroll() {
    if (window.innerWidth > 767) return;

    var expanded    = document.getElementById('mobExpanded');
    var compactLogo = document.querySelector('.mob-compact-logo');
    var collapsed   = false;

    /* ── CSS değişkeni: sayfalar padding-top için kullanabilir ── */
    function publishHeight() {
      var nb = document.getElementById('mobileNavbar');
      if (!nb) return;
      var h = nb.getBoundingClientRect().height;
      document.documentElement.style.setProperty('--mob-navbar-h', h + 'px');
    }

    /* ── Collapse / Expand animasyonu ── */
    function setCollapsed(yes) {
      if (yes === collapsed) return;
      collapsed = yes;

      if (expanded) {
        expanded.style.transition = 'max-height .32s ease, opacity .25s ease';
        expanded.style.overflow   = 'hidden';
        expanded.style.maxHeight  = yes ? '0'     : '200px';
        expanded.style.opacity    = yes ? '0'     : '1';
      }
      if (compactLogo) {
        compactLogo.style.transition = 'max-width .30s ease, opacity .25s ease';
        compactLogo.style.maxWidth   = yes ? '80px' : '0';
        compactLogo.style.opacity    = yes ? '1'    : '0';
      }

      /* Animasyon bittikten sonra yüksekliği güncelle + event fırlat */
      setTimeout(function () {
        publishHeight();
        try {
          document.dispatchEvent(new CustomEvent('wb:navbarCollapse', {
            detail: { collapsed: yes }
          }));
        } catch (_) {}
      }, 330);
    }

    /* İlk durum */
    setCollapsed(false);
    publishHeight();
    setTimeout(publishHeight, 500);
    setTimeout(publishHeight, 1200);

    /* ── capture phase scroll: bubble olmayan scroll'ları da yakala ── */
    function onAnyScroll(e) {
      var el = e.target;
      var sy = 0;
      if (el === document || el === window) {
        sy = Math.max(
          window.scrollY || 0,
          window.pageYOffset || 0,
          document.documentElement.scrollTop || 0,
          document.body.scrollTop || 0
        );
      } else if (el && el.scrollTop !== undefined) {
        sy = el.scrollTop;
      }
      setCollapsed(sy > 60);
    }
    document.addEventListener('scroll', onAnyScroll, { passive: true, capture: true });
    window.addEventListener('scroll', onAnyScroll, { passive: true });

    /* ── IntersectionObserver: scrollSentinel varsa kullan ── */
    var sentinel = document.getElementById('scrollSentinel');
    if (sentinel && 'IntersectionObserver' in window) {
      var io = new IntersectionObserver(function (entries) {
        setCollapsed(!entries[0].isIntersecting);
      }, { threshold: 0 });
      io.observe(sentinel);
    }

    /* ── Yedek: 200ms polling ── */
    setInterval(function () {
      var sy = Math.max(
        window.scrollY || 0,
        document.documentElement.scrollTop || 0,
        document.body.scrollTop || 0
      );
      var panels = document.querySelectorAll('.list-page, main, #mainContent');
      panels.forEach(function (p) { if (p.scrollTop > 0) sy = Math.max(sy, p.scrollTop); });
      setCollapsed(sy > 60);
    }, 200);

    /* ── Resize: desktop'a geçince sıfırla ── */
    window.addEventListener('resize', function () {
      if (window.innerWidth > 767) {
        if (expanded)    { expanded.style.maxHeight = ''; expanded.style.opacity = ''; }
        if (compactLogo) { compactLogo.style.maxWidth = ''; compactLogo.style.opacity = ''; }
        collapsed = false;
        document.documentElement.style.removeProperty('--mob-navbar-h');
      } else {
        publishHeight();
      }
    }, { passive: true });
  }

  /* ── Auto-init ── */
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNavScroll);
  } else {
    initNavScroll();
  }

  /* ── Harici erişim (sayfalar gerekirse manuel tetikleyebilir) ── */
  window.wbNavbarMobile = { init: initNavScroll };

})();