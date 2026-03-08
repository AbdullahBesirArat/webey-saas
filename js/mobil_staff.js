/* ===========================================================
   STAFF — MOBIL Davranışları (≤1024px) • v2
   - Desktop JS’e dokunmaz; sadece mobil/tablet UX katmanı
   - Tek kolon akışta yukarı/aşağı kaydırma, sticky üstlerle uyum
   - html.is-mobile sınıfı → mobil_staff.css ile senkron
   - Rail geç yüklenmesini bekleyip aktif sayfayı işaretleme
   =========================================================== */
(function initMobileStaff(){
  const mq = window.matchMedia('(max-width: 1024px)');

  /* === Mobil sınıfı === */
  function applyMobileClass() {
    document.documentElement.classList.toggle('is-mobile', mq.matches);
  }
  applyMobileClass();

  // Yeni tarayıcılar
  if (mq.addEventListener) {
    mq.addEventListener('change', applyMobileClass);
  } else if (mq.addListener) {
    // Eski Safari / Chrome için fallback
    mq.addListener(applyMobileClass);
  }

  function isMobile(){
    return mq.matches;
  }

  /* === Kaydırma yardımcıları === */
  function getScroller(){
    const el = document.querySelector('.container');
    const docEl = document.scrollingElement || document.documentElement;
    if (!el) return docEl;
    return (el.scrollHeight > el.clientHeight + 2) ? el : docEl;
  }

  function smoothScrollTop(){
    if (!isMobile()) return;
    const s = getScroller();
    try {
      s.scrollTo({ top: 0, behavior: 'smooth' });
    } catch {
      s.scrollTop = 0;
    }
  }

  function smoothScrollBottom(){
    if (!isMobile()) return;
    const s = getScroller();
    const go = () => {
      const y = Math.max(0, s.scrollHeight - s.clientHeight + 1);
      try {
        s.scrollTo({ top: y, behavior: 'smooth' });
      } catch {
        s.scrollTop = y;
      }
    };
    // İçerik yeniden layout olsun diye hafif gecikmeli
    requestAnimationFrame(() =>
      requestAnimationFrame(() =>
        setTimeout(go, 30)
      )
    );
  }

  /* Sekme tıklamalarında başa dön (mobilde) */
  document.querySelectorAll('.tabs .tab[role="tab"]').forEach(tab=>{
    tab.addEventListener('click', ()=>{
      setTimeout(smoothScrollTop, 0);
    });
  });

  /* Personel seçimi sonrası EN ALTA kaydır (panel görünür olsun) */
  const staffList = document.getElementById('staffList');
  staffList?.addEventListener('click', (e)=>{
    const item = e.target.closest('.staff-item');
    if (!item) return;
    setTimeout(smoothScrollBottom, 60);
  });

  /* Modallar açıldığında küçük bir kaydırma toparlama */
  ['openModal2','btnAddSmall','btnRemoveSmall'].forEach(id=>{
    const btn = document.getElementById(id);
    if (!btn) return;
    btn.addEventListener('click', ()=>{
      setTimeout(smoothScrollTop, 120);
    });
  });

  /* Ekran yönü/resize: yapışkan başlık altındaki scroll’u toparla */
  window.addEventListener('orientationchange', ()=>{
    setTimeout(smoothScrollTop, 100);
  });
  window.addEventListener('resize', ()=>{
    if (isMobile()) setTimeout(smoothScrollTop, 100);
  }, { passive:true });

  /* Skip-link (#main) — odak ve konum */
  const skip = document.querySelector('.skip-link');
  if (skip){
    skip.addEventListener('click', ()=>{
      const main = document.getElementById('main');
      if (!main) return;
      // varsayılan anchor çalışsın ama odak da taşıyalım
      setTimeout(()=>{
        main.setAttribute('tabindex','-1');
        try {
          main.focus({ preventScroll:true });
        } catch {
          main.focus();
        }
        smoothScrollTop();
      }, 0);
    });
  }

  /* === Mobil alt rail: aktif sayfa işaretleme fix’i ===
     loadRail() asenkron geldiği için burada da işaretliyoruz. */
  function markRailActive(root){
    if (!root) return;
    const btns = root.querySelectorAll('.rail__btn, nav.rail a, .rail a');
    if (!btns.length) return;

    let found = false;
    btns.forEach(a=>{
      const href = (a.getAttribute('href') || '').split(/[?#]/)[0];
      const pageAttr = a.dataset ? a.dataset.page : null;
      const isMe = /(^|\/)staff\.html$/i.test(href) || pageAttr === 'staff';

      // Tüm rail butonlarından aria-current’ı kaldır
      a.removeAttribute('aria-current');

      if (isMe){
        a.setAttribute('aria-current','page');
        found = true;
      }
    });

    // Fallback: data-page="staff" yakala (href boşsa vs.)
    if (!found){
      const alt = root.querySelector('[data-page="staff"]');
      if (alt){
        alt.setAttribute('aria-current','page');
      }
    }
  }

  // 1) Hali hazırda rail varsa
  markRailActive(document);

  // 2) #rail-mount içine sonradan HTML enjekte edilirse yakala
  const railMount = document.getElementById('rail-mount');
  if (railMount){
    const mo = new MutationObserver(()=>{
      markRailActive(railMount);
    });
    mo.observe(railMount, { childList:true, subtree:true });

    // Sayfa görünüm değiştiğinde de tekrar dene
    document.addEventListener('visibilitychange', ()=>{
      if (document.visibilityState === 'visible'){
        markRailActive(railMount);
      }
    });
  }

  /* Focus görünürlüğü (klavye erişilebilirliği) */
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'Tab'){
      document.documentElement.classList.add('show-focus');
    }
  }, { once:true });
})();
