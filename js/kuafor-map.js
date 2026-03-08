/**
 * js/kuafor-map.js
 * ──────────────────────────────────────────────────────────────────
 * kuafor.html'e ekstra özellikler:
 *   1. Sort chipleri (En Yeni / Puan / Fiyat)
 *   2. "Şu an açık" toggle
 *   3. Leaflet.js harita görünümü
 *   4. "Daha Fazla Göster" (Load More) butonu
 *
 * Mevcut kuafor.js'e dokunmaz — window.ALL_SALONS ve window.renderList
 * fonksiyonlarına hook eder. kuafor.js'den SONRA yüklenmelidir.
 *
 * HTML'e ekle (kuafor.js'den sonra):
 *   <script src="js/kuafor-map.js" defer></script>
 * ──────────────────────────────────────────────────────────────────
 */

(function () {
  'use strict';

  /* ── Ayarlar ── */
  const PAGE_SIZE   = 18;    // Her sayfada kaç salon
  const MAP_CENTER  = [41.015, 28.979]; // İstanbul
  const MAP_ZOOM    = 12;
  const LEAFLET_CSS = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
  const LEAFLET_JS  = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';

  /* ── State ── */
  let currentSort    = 'newest';
  let openNowActive  = false;
  let currentPage    = 1;
  let mapInitialized = false;
  let leafletMap     = null;
  let markers        = [];

  /* ── DOM Referansları ── */
  const chipBtns    = document.querySelectorAll('.wb-chip[data-sort]');
  const toggle      = document.getElementById('openNowToggle');
  const toggleLabel = document.getElementById('openNowLabel');
  const mapViewBtn  = document.getElementById('mapViewBtn');
  const closeMapBtn = document.getElementById('closeMapBtn');
  const mapPanel    = document.getElementById('mapPanel');
  const loadMoreWrap = document.getElementById('loadMoreWrap');
  const loadMoreBtn  = document.getElementById('loadMoreBtn');
  const grid         = document.getElementById('salonGrid');

  /* ── Sort chipleri ── */
  chipBtns.forEach(btn => {
    btn.addEventListener('click', () => {
      chipBtns.forEach(b => b.classList.remove('wb-chip--active'));
      btn.classList.add('wb-chip--active');
      currentSort = btn.dataset.sort;
      currentPage = 1;
      applyEnhancements();
    });
  });

  /* ── Şu an açık toggle ── */
  function setToggle(active) {
    openNowActive = active;
    toggle?.setAttribute('aria-checked', String(active));
    if (toggleLabel) toggleLabel.style.color = active ? '#0ea5b3' : '#374151';
  }

  toggle?.addEventListener('click', () => {
    setToggle(!openNowActive);
    currentPage = 1;
    applyEnhancements();
  });

  toggle?.addEventListener('keydown', e => {
    if (e.key === 'Enter' || e.key === ' ') {
      e.preventDefault();
      toggle.click();
    }
  });

  /* ── Açık mı kontrolü (client-side) ── */
  function isOpenNow(salon) {
    const hours = salon.hours || salon.business_hours;
    if (!hours || !Array.isArray(hours)) return true; // Bilgi yoksa göster
    const now  = new Date();
    const dow  = now.getDay(); // 0=Pazar
    const nowMin = now.getHours() * 60 + now.getMinutes();
    const todayHours = hours.find(h => h.day_of_week === dow);
    if (!todayHours || !todayHours.is_open) return false;
    const [oh, om] = (todayHours.open_time  || '00:00').split(':').map(Number);
    const [ch, cm] = (todayHours.close_time || '23:59').split(':').map(Number);
    return nowMin >= oh * 60 + om && nowMin <= ch * 60 + cm;
  }

  /* ── Sıralama fonksiyonu ── */
  function sortSalons(list) {
    const sorted = [...list];
    switch (currentSort) {
      case 'rating':
        sorted.sort((a, b) => (b.avg_rating || b.avgRating || 0) - (a.avg_rating || a.avgRating || 0));
        break;
      case 'price_asc':
        sorted.sort((a, b) => {
          const pa = a.min_price ?? a.minPrice ?? Infinity;
          const pb = b.min_price ?? b.minPrice ?? Infinity;
          return pa - pb;
        });
        break;
      case 'price_desc':
        sorted.sort((a, b) => {
          const pa = a.min_price ?? a.minPrice ?? 0;
          const pb = b.min_price ?? b.minPrice ?? 0;
          return pb - pa;
        });
        break;
      default: // newest — orijinal sıra
        break;
    }
    return sorted;
  }

  /* ── Tüm geliştirmeleri uygula ── */
  function applyEnhancements() {
    const salons = window.ALL_SALONS;
    if (!Array.isArray(salons)) return;

    // Filtrele
    let filtered = openNowActive ? salons.filter(isOpenNow) : salons.slice();

    // Sırala
    filtered = sortSalons(filtered);

    // Sayfala
    const total     = filtered.length;
    const pageItems = filtered.slice(0, currentPage * PAGE_SIZE);

    // Grid'i yeniden çiz
    renderCustom(pageItems);

    // Load More göster/gizle
    if (loadMoreWrap) {
      loadMoreWrap.style.display = (total > currentPage * PAGE_SIZE) ? 'block' : 'none';
    }
    if (loadMoreBtn) {
      const remaining = total - currentPage * PAGE_SIZE;
      loadMoreBtn.textContent = `Daha Fazla Göster (${remaining} kaldı)`;
    }
  }

  /* ── Kartları render et (kuafor.js'deki renderList yerine pagination için) ── */
  function renderCustom(salons) {
    if (!grid) return;
    if (!salons.length) {
      grid.innerHTML = '';
      document.getElementById('emptyState').style.display = 'block';
      return;
    }
    document.getElementById('emptyState').style.display = 'none';

    grid.innerHTML = salons.map(s => {
      const name     = s.name || s.businessName || '';
      const cover    = s.coverUrl || s.cover || '';
      const city     = s.loc?.city || s.businessLocation?.province || '';
      const district = s.loc?.district || s.businessLocation?.district || '';
      const rating   = +(s.avg_rating || s.avgRating || 0);
      const reviews  = +(s.review_count || s.reviewCount || 0);
      const minPrice = s.min_price || s.minPrice;
      const id       = s.id || s.businessId || '';
      const slug     = s.slug || id;

      return `<a class="card" href="profile.html?id=${id}" style="text-decoration:none;display:block;cursor:pointer;">
        <div class="img" style="background:#f3f4f6;${cover ? `background-image:url('${cover}');background-size:cover;background-position:center;` : ''}"></div>
        <div class="card-body" style="padding:12px 14px;">
          <div style="font-size:15px;font-weight:700;color:#111827;margin-bottom:4px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${name}</div>
          <div style="font-size:12px;color:#9ca3af;margin-bottom:6px;">${[district,city].filter(Boolean).join(', ')}</div>
          <div style="display:flex;align-items:center;justify-content:space-between;">
            ${rating > 0 ? `<span style="font-size:12px;color:#f59e0b;font-weight:600;">⭐ ${rating.toFixed(1)} <span style="color:#9ca3af;font-weight:400;">(${reviews})</span></span>` : '<span></span>'}
            ${minPrice ? `<span style="font-size:11px;font-weight:800;color:#0ea5b3;">₺${minPrice}<span style="font-size:10px;font-weight:500;color:#9ca3af;">'dan başlayan fiyatlar</span></span>` : ''}
          </div>
        </div>
      </a>`;
    }).join('');
  }

  /* ── Load More ── */
  loadMoreBtn?.addEventListener('click', () => {
    currentPage++;
    applyEnhancements();
    // Smooth scroll to new items
    const cards = grid?.querySelectorAll('.card');
    if (cards?.length) {
      const newStart = (currentPage - 1) * PAGE_SIZE;
      cards[newStart]?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
  });

  /* ── Leaflet Harita ── */
  function loadLeaflet(cb) {
    if (window.L) { cb(); return; }
    // CSS
    if (!document.getElementById('leaflet-css')) {
      const link = document.createElement('link');
      link.id   = 'leaflet-css';
      link.rel  = 'stylesheet';
      link.href = LEAFLET_CSS;
      document.head.appendChild(link);
    }
    // JS
    const script   = document.createElement('script');
    script.src     = LEAFLET_JS;
    script.onload  = cb;
    document.head.appendChild(script);
  }

  function initMap() {
    if (mapInitialized) return;
    mapInitialized = true;
    leafletMap = L.map('leafletMap').setView(MAP_CENTER, MAP_ZOOM);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
      attribution: '© <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
      maxZoom: 19,
    }).addTo(leafletMap);
  }

  /* ── Google Maps URL'inden koordinat çıkar ── */
  function extractCoordsFromMapUrl(url) {
    if (!url) return null;
    // Format: .../@lat,lng,zoom  veya  .../@lat,lng,
    let m = url.match(/@(-?\d+\.?\d*),(-?\d+\.?\d*)/);
    if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
    // Format: ?q=lat,lng veya &q=lat,lng
    m = url.match(/[?&]q=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
    if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
    // Format: ll=lat,lng
    m = url.match(/ll=(-?\d+\.?\d*),(-?\d+\.?\d*)/);
    if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };
    return null;
  }

  function populateMap() {
    if (!leafletMap || !window.ALL_SALONS) return;

    // Eski markerları temizle
    markers.forEach(m => m.remove());
    markers = [];

    const salons = window.ALL_SALONS;
    const bounds = [];
    let mapUrlCount = 0;

    salons.forEach(s => {
      let lat = parseFloat(s.loc?.latitude || s.latitude || s.lat || 0);
      let lng = parseFloat(s.loc?.longitude || s.longitude || s.lng || 0);
      const hasMapUrl = !!(s.map_url || s.mapUrl);

      // lat/lng yoksa Google Maps URL'inden çıkarmaya çalış
      if ((!lat || !lng) && hasMapUrl) {
        const coords = extractCoordsFromMapUrl(s.map_url || s.mapUrl);
        if (coords) {
          lat = coords.lat;
          lng = coords.lng;
          mapUrlCount++;
        }
      }

      if (!lat || !lng) return;

      const rating   = +(s.avg_rating || s.avgRating || 0);
      const minPrice = s.min_price || s.minPrice;
      const name     = s.name || '';
      const city     = s.loc?.city || '';
      const district = s.loc?.district || '';
      const id       = s.id || '';
      const mapUrl   = s.map_url || s.mapUrl || null;

      const popup = L.popup({ className: 'wb-map-popup' }).setContent(`
        <div class="wb-popup-inner">
          <div class="wb-popup-name">${name}</div>
          <div class="wb-popup-loc">${[district,city].filter(Boolean).join(', ')}</div>
          ${rating > 0 ? `<div class="wb-popup-rating">⭐ ${rating.toFixed(1)} (${+(s.review_count||0)} yorum)</div>` : ''}
          ${minPrice ? `<div class="wb-popup-price">₺${minPrice}+'dan</div>` : ''}
          ${mapUrl ? `<a href="${mapUrl}" target="_blank" rel="noopener" class="wb-popup-gmaps-link">
            <svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/></svg>
            Google Maps'te Gör
          </a>` : ''}
          <a href="profile.html?id=${id}" class="wb-popup-btn">Randevu Al</a>
        </div>
      `);

      // Özel ikon — map_url olanlar mor pin
      const pinColor = hasMapUrl ? '#7c3aed' : '#0ea5b3';
      const icon = L.divIcon({
        html: `<div class="wb-gmap-pin${hasMapUrl ? ' has-url' : ''}" style="background:${pinColor}">✂️${rating > 0 ? ' ' + rating.toFixed(1) : ''}</div>`,
        className: '',
        iconAnchor: [24, 14],
      });

      const marker = L.marker([lat, lng], { icon }).addTo(leafletMap).bindPopup(popup);
      markers.push(marker);
      bounds.push([lat, lng]);
    });

    if (bounds.length > 0) {
      leafletMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 14 });
    } else {
      // Hiç koordinat yoksa boş uyarı göster
      const empty = document.createElement('div');
      empty.className = 'wb-map-empty';
      empty.innerHTML = `<div class="wb-map-empty-icon">📍</div><div class="wb-map-empty-title">Haritada gösterilecek salon yok</div><div class="wb-map-empty-sub">Salonlar konum bilgisi ekledikçe burada görünecek</div>`;
      document.getElementById('mapPanel')?.appendChild(empty);
    }
  }

  function showMap() {
    if (!mapPanel) return;
    mapPanel.style.display = 'block';
    mapViewBtn?.classList.add('active');
    document.getElementById('mainContent').style.display = 'none';
    document.getElementById('loadMoreWrap') && (document.getElementById('loadMoreWrap').style.display = 'none');

    loadLeaflet(() => {
      initMap();
      setTimeout(() => {
        leafletMap.invalidateSize();
        populateMap();
      }, 100);
    });
  }

  function hideMap() {
    if (mapPanel) mapPanel.style.display = 'none';
    mapViewBtn?.classList.remove('active');
    document.getElementById('mainContent').style.display = 'block';
    applyEnhancements(); // Load more durumunu güncelle
    // Banner'in görünmesi için sayfayı en üste al
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  mapViewBtn?.addEventListener('click',  showMap);
  closeMapBtn?.addEventListener('click', hideMap);

  /* ── kuafor.js'den sonra yüklenince hook kur ── */
  function hookIntoKuafor() {
    // ALL_SALONS değiştiğinde sort/filtre/paginationu uygula
    let lastLen = -1;
    const interval = setInterval(() => {
      const salons = window.ALL_SALONS;
      if (Array.isArray(salons) && salons.length !== lastLen) {
        lastLen = salons.length;
        currentPage = 1;
        applyEnhancements();
      }
    }, 300);

    // 10sn sonra interval'ı kapat (sayfa yüklendi)
    setTimeout(() => clearInterval(interval), 10_000);
  }

  // DOM hazır olduğunda başlat
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', hookIntoKuafor);
  } else {
    hookIntoKuafor();
  }

  // Global erişim (debug için)
  window.WbEnhanced = { applyEnhancements, showMap, hideMap };

})();