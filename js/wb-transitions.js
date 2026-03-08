/**
 * wb-transitions.js — Webey Sayfa Geçiş Animasyonları
 * - Progress bar (üst şerit)
 * - Link tıklayınca sayfa çıkış animasyonu
 * - Scroll reveal (IntersectionObserver)
 */
(function () {
  'use strict';

  /* ══════════════════════════════════════
     PROGRESS BAR
  ══════════════════════════════════════ */
  const bar = document.createElement('div');
  bar.id = 'wb-progress';
  document.body.prepend(bar);

  let _progTimer = null;
  let _progVal   = 0;

  function progStart() {
    _progVal = 0;
    bar.style.opacity = '1';
    bar.classList.remove('wb-done');
    bar.style.width = '0%';
    _progTimer && clearInterval(_progTimer);
    // Hızla %80'e kadar, sonra yavaşla
    _progTimer = setInterval(() => {
      if (_progVal < 80)      _progVal += 8;
      else if (_progVal < 95) _progVal += 1;
      bar.style.width = _progVal + '%';
    }, 80);
  }

  function progDone() {
    clearInterval(_progTimer);
    bar.style.width  = '100%';
    bar.classList.add('wb-done');
  }

  // Sayfa tam yüklenince progress done
  if (document.readyState === 'complete') {
    progDone();
  } else {
    progStart();
    window.addEventListener('load', progDone, { once: true });
  }

  /* ══════════════════════════════════════
     SAYFA ÇIKIŞ ANİMASYONU
  ══════════════════════════════════════ */
  let _leaving = false;

  function shouldAnimate(href) {
    if (!href) return false;
    if (href.startsWith('#'))     return false;
    if (href.startsWith('tel:'))  return false;
    if (href.startsWith('mailto:')) return false;
    if (href.startsWith('http') && !href.includes(location.hostname)) return false;
    return true;
  }

  document.addEventListener('click', e => {
    const a = e.target.closest('a[href]');
    if (!a || _leaving)    return;
    if (e.ctrlKey || e.metaKey || e.shiftKey || e.altKey) return;
    if (a.target === '_blank') return;

    const href = a.getAttribute('href');
    if (!shouldAnimate(href)) return;

    e.preventDefault();
    _leaving = true;

    // Progress başlat
    progStart();

    // Çıkış animasyonu
    document.body.classList.add('wb-leaving');

    setTimeout(() => {
      window.location.href = href;
    }, 200);
  });

  /* ══════════════════════════════════════
     BFCACHE DÜZELTME
     Geri/ileri tuşuyla gelince tarayıcı sayfayı
     bfcache'den restore eder. Bu durumda body'de
     'wb-leaving' class'ı kalır ve sayfa görünmez olur.
     pageshow + event.persisted ile yakalayıp temizliyoruz.
  ══════════════════════════════════════ */
  window.addEventListener('pageshow', function (e) {
    if (e.persisted) {
      // Sayfa bfcache'den geri yüklendi — animasyon class'larını temizle
      document.body.classList.remove('wb-leaving');
      document.body.style.opacity  = '';
      document.body.style.transform = '';
      _leaving = false;

      // Progress bar'ı da tamamlandı olarak işaretle
      progDone();
    }
  });

  /* ══════════════════════════════════════
     SCROLL REVEAL
  ══════════════════════════════════════ */
  if ('IntersectionObserver' in window) {
    const observer = new IntersectionObserver((entries) => {
      entries.forEach(entry => {
        if (entry.isIntersecting) {
          entry.target.classList.add('is-visible');
          observer.unobserve(entry.target);
        }
      });
    }, {
      threshold: 0.12,
      rootMargin: '0px 0px -40px 0px'
    });

    // DOM hazır olunca observe et
    function initReveal() {
      document.querySelectorAll('.wb-reveal').forEach(el => observer.observe(el));
    }

    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', initReveal);
    } else {
      initReveal();
    }

    // Dinamik eklenen elementler için MutationObserver
    const mutObs = new MutationObserver(mutations => {
      mutations.forEach(m => {
        m.addedNodes.forEach(node => {
          if (node.nodeType !== 1) return;
          if (node.classList?.contains('wb-reveal')) observer.observe(node);
          node.querySelectorAll?.('.wb-reveal').forEach(el => observer.observe(el));
        });
      });
    });

    mutObs.observe(document.body, { childList: true, subtree: true });
  } else {
    // Fallback: hepsini direkt göster
    document.querySelectorAll('.wb-reveal').forEach(el => el.classList.add('is-visible'));
  }

})();