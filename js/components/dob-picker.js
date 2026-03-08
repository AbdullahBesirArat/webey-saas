// components/dob-picker.js
// Basit, döngüsüz ve “buton hissiyatlı” DOB picker
// Yeni: format esnekliği, i18n ay adları, erişilebilirlik, focus-trap
// Not: type="date" alanları yalnızca ISO yyyy-MM-dd kabul eder → bu durumda otomatik ISO + valueAsDate kullanılır.

export function attachDOBPicker({
  input,
  years = { min: new Date().getFullYear() - 100, max: new Date().getFullYear() },
  locale = "tr",
  format = "yyyy-MM-dd",
} = {}) {
  if (!input) return;

  /* --- Guards --- */
  if (input.dataset.dobReady === "1") return; // aynı inputa tekrar bağlama
  input.dataset.dobReady = "1";

  if (typeof years?.min === "number" && typeof years?.max === "number" && years.min > years.max) {
    const t = years.min; years.min = years.max; years.max = t;
  }

  // Native picker’ı devre dışı bırak (kontrol bizde)
  input.readOnly = true;
  input.setAttribute("role", "button");
  input.setAttribute("aria-haspopup", "dialog");
  input.setAttribute("aria-expanded", "false");

  // Klavyeyle serbest yazmayı önle, Enter/Space açar
  input.addEventListener("keydown", (e) => {
    const ok = ["Enter", " ", "ArrowDown", "ArrowUp"].includes(e.key);
    if (!ok) e.preventDefault();
    if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); }
  });
  input.addEventListener("click", open);

  // type="date" ise zorunlu ISO kullan
  const isNativeDate = input.type === "date";
  const effectiveFormat = isNativeDate ? "yyyy-MM-dd" : format;

  /* --- i18n --- */
  const MONTHS = getMonthsShort(locale);

  /* --- parsing/formatting --- */
  const parser = buildTokenParser(effectiveFormat);
  const isoFallback = buildTokenParser("yyyy-MM-dd");

  function parseValue(v) {
    // type="date" için önce valueAsDate varsa onu kullan
    if (isNativeDate && input.valueAsDate instanceof Date && !isNaN(input.valueAsDate)) {
      const d = input.valueAsDate;
      return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate() };
    }
    const p = parser.tryParse(v) || isoFallback.tryParse(v);
    if (p) return p;
    const d = new Date(); // fallback: bugün
    return { y: d.getFullYear(), m: d.getMonth() + 1, d: d.getDate() };
  }
  function fmt({ y, m, d }) {
    const pad = (n) => String(n).padStart(2, "0");
    return effectiveFormat
      .replace("yyyy", String(y))
      .replace("MM", pad(m))
      .replace("dd", pad(d));
  }
  function daysIn(y, m) { return new Date(y, m, 0).getDate(); }

  let overlay, box, colDay, colMon, colYear;
  let lastFocused = null;
  let _pickerAddedNoScroll = false; // biz mi ekledik takip et

  let state = parseValue(input.value);
  state.m = clamp(state.m, 1, 12);
  state.y = clamp(state.y, years.min, years.max);
  state.d = clamp(state.d, 1, daysIn(state.y, state.m));

  function open() {
    if (overlay?.isConnected) return; // zaten açık
    lastFocused = document.activeElement;
    build();

    input.setAttribute("aria-expanded", "true");
    document.body.appendChild(overlay);
    _pickerAddedNoScroll = !document.body.classList.contains("no-scroll");
    if (_pickerAddedNoScroll) document.body.classList.add("no-scroll");

    // Double RAF: ilk RAF layout'u schedule eder, ikinci RAF layout tamamlandıktan sonra çalışır.
    // Bu olmadan clientHeight=0 olabiliyor → yanlış scrollTop → schedule döngüsü.
    requestAnimationFrame(() => {
      requestAnimationFrame(() => {
        // Bayrağı set et — schedule() bu sürede devreye girmesin
        [colDay, colMon, colYear].forEach(c => { if (c) { c._openScrolling = true; } });

        scrollToIndex(colDay,  state.d - 1, true);
        scrollToIndex(colMon,  state.m - 1, true);
        const yearsArr2 = getYearsDesc(years.min, years.max);
        const yIdx = yearsArr2.indexOf(String(state.y));
        scrollToIndex(colYear, Math.max(0, yIdx), true);

        // 400ms bekle: hem instant scroll yerleşsin hem olası stale timer'lar bitsin
        setTimeout(() => {
          [colDay, colMon, colYear].forEach(c => { if (c) c._openScrolling = false; });
          focusFirstFocusable(overlay);
        }, 400);
      });
    });
  }

  function close() {
    overlay?.remove();
    // Sadece biz ekledikse kaldır — aksi halde modal scroll kilidini bozmayalım
    if (_pickerAddedNoScroll) {
      document.body.classList.remove("no-scroll");
      _pickerAddedNoScroll = false;
    }
    input.setAttribute("aria-expanded", "false");
    if (lastFocused && lastFocused.focus) lastFocused.focus();
    overlay = box = colDay = colMon = colYear = null;
  }

  function build() {
    overlay = el("div", { class: "dobp-overlay", role: "dialog", "aria-modal": "true" });
    overlay.tabIndex = -1;
    // Inline z-index: CSS'den bağımsız olarak her zaman en üstte görünsün
    overlay.style.zIndex = "2147483647"; // max 32-bit int — her z-index'in üstünde

    overlay.addEventListener("click", (e) => { if (e.target === overlay) close(); });
    overlay.addEventListener("keydown", (e) => {
      if (e.key === "Escape") { e.preventDefault(); close(); return; }
      if (e.key === "Tab") { trapTabKey(overlay, e); }
    });

    box = el("div", { class: "dobp-box" });
    const headLabel = locale?.startsWith("tr") ? "Tarihi ayarla" : "Set date";
    const head = el("div", { class: "dobp-head", id: "dobp-label" }, txt(headLabel));
    box.setAttribute("aria-labelledby", "dobp-label");

    const wheel = el("div", { class: "dobp-wheel" });

    // Gün
    colDay = makeColumn(
      Array.from({ length: 31 }, (_, i) => String(i + 1)),
      state.d - 1,
      (idx) => { state.d = idx + 1; highlight(colDay, idx); },
      locale?.startsWith("tr") ? "Gün" : "Day"
    );

    // Ay
    colMon = makeColumn(
      MONTHS,
      state.m - 1,
      (idx) => {
        state.m = idx + 1; highlight(colMon, idx);
        const maxD = daysIn(state.y, state.m);
        if (state.d > maxD) { state.d = maxD; highlight(colDay, state.d - 1); scrollToIndex(colDay, state.d - 1); }
      },
      locale?.startsWith("tr") ? "Ay" : "Month"
    );

    // Yıl (desc)
    const yearsArr = getYearsDesc(years.min, years.max);
    const yearIdx = yearsArr.indexOf(String(state.y));
    colYear = makeColumn(
      yearsArr,
      Math.max(0, yearIdx),
      (idx) => {
        state.y = Number(yearsArr[idx]); highlight(colYear, idx);
        const maxD = daysIn(state.y, state.m);
        if (state.d > maxD) { state.d = maxD; highlight(colDay, state.d - 1); scrollToIndex(colDay, state.d - 1); }
      },
      locale?.startsWith("tr") ? "Yıl" : "Year"
    );

    wheel.append(colDay, colMon, colYear, el("div", { class: "dobp-mask", "aria-hidden": "true" }));

    // Actions
    const actions = el("div", { class: "dobp-actions" });
    const btnCancel = el("button", { class: "btn-ghost", type: "button" }, txt(locale?.startsWith("tr") ? "İptal" : "Cancel"));
    const btnOk = el("button", { class: "auth-btn", type: "button", style: "min-width:160px" }, txt(locale?.startsWith("tr") ? "Ayarla" : "Apply"));
    btnCancel.addEventListener("click", close);
    btnOk.addEventListener("click", () => {
      // String value
      const str = fmt(state);
      // Native date input güvenliği
      if (isNativeDate) {
        const d = new Date(state.y, state.m - 1, state.d);
        input.valueAsDate = d;             // tarayıcı tarafından garantili kabul
        input.value = isoYYYYMMDD(d);      // string değer de ISO olsun
      } else {
        input.value = str;
      }
      input.dispatchEvent(new Event("input", { bubbles: true }));
      input.dispatchEvent(new Event("change", { bubbles: true }));
      close();
    });
    actions.append(btnCancel, btnOk);

    box.append(head, wheel, actions);
    overlay.append(box);
  }

  /** Column factory (debounced snap, loop yok) */
  function makeColumn(items, activeIndex, onChange, ariaLabel) {
    const col = el("div", { class: "dobp-col", role: "listbox", "aria-label": ariaLabel || "" });
    const buttons = items.map((label, i) => {
      const b = el("button",
        { class: "dobp-opt", type: "button", role: "option", "aria-selected": i === activeIndex ? "true" : "false" },
        txt(label)
      );
      b.addEventListener("click", () => { highlight(col, i); scrollToIndex(col, i); onChange(i); });
      return b;
    });
    col.append(...buttons);

    // İlk highlight
    highlight(col, activeIndex);

    // Debounced snapping
    let t = null;
    let programmatic = false;
    const schedule = () => {
      // Açılış scroll'u veya programmatic scroll sırasında tetiklenme
      if (programmatic || col._openScrolling) return;
      clearTimeout(t);
      t = setTimeout(() => {
        const idx = nearestIndex(col);
        highlight(col, idx);
        onChange(idx);
        programmatic = true;
        scrollToIndex(col, idx);
        setTimeout(() => (programmatic = false), 120);
      }, 120);
    };

    col.addEventListener("scroll", schedule, { passive: true });
    col.addEventListener("wheel", schedule, { passive: true });
    col.addEventListener("touchend", schedule, { passive: true });

    // Klavye ile gezinme
    col.addEventListener("keydown", (e) => {
      const cur = currentIndex(col);
      if (e.key === "ArrowUp" || e.key === "ArrowDown") {
        e.preventDefault();
        const next = clamp(e.key === "ArrowUp" ? cur - 1 : cur + 1, 0, items.length - 1);
        highlight(col, next); onChange(next);
        programmatic = true; scrollToIndex(col, next); setTimeout(() => (programmatic = false), 120);
      } else if (e.key === "Home" || e.key === "End") {
        e.preventDefault();
        const next = e.key === "Home" ? 0 : items.length - 1;
        highlight(col, next); onChange(next);
        programmatic = true; scrollToIndex(col, next); setTimeout(() => (programmatic = false), 120);
      } else if (e.key === "PageUp" || e.key === "PageDown") {
        e.preventDefault();
        const step = Math.max(3, Math.floor(col.clientHeight / Math.max(1, getStride(col))));
        const next = clamp(e.key === "PageUp" ? cur - step : cur + step, 0, items.length - 1);
        highlight(col, next); onChange(next);
        programmatic = true; scrollToIndex(col, next); setTimeout(() => (programmatic = false), 120);
      }
    });

    col.tabIndex = 0;
    col.addEventListener("focus", () => col.classList.add("focus"));
    col.addEventListener("blur",  () => col.classList.remove("focus"));

    return col;
  }

  /* --- Snap & index helpers --- */
  function highlight(col, idx) {
    const btns = col.querySelectorAll(".dobp-opt");
    btns.forEach((b, i) => b.setAttribute("aria-selected", i === idx ? "true" : "false"));
    col.dataset.activeIndex = String(idx);
  }
  function currentIndex(col) {
    return clamp(+col.dataset.activeIndex || 0, 0, col.querySelectorAll(".dobp-opt").length - 1);
  }
  function nearestIndex(col) {
    const first = col.querySelector(".dobp-opt");
    if (!first) return 0;
    const stride = getStride(col);
    const center = col.scrollTop + (col.clientHeight / 2);
    const idx = Math.round((center - stride / 2) / stride);
    return clamp(idx, 0, col.querySelectorAll(".dobp-opt").length - 1);
  }
  function scrollToIndex(col, idx, instant = false) {
    const stride     = getStride(col);
    const paddingTop = parseFloat(getComputedStyle(col).paddingTop) || 0;
    const top = Math.max(0, Math.round(paddingTop + idx * stride - (col.clientHeight - stride) / 2));
    if (instant) col.scrollTop = top;
    else col.scrollTo({ top, behavior: "smooth" });
  }
  function getStride(col) {
    const opts = col.querySelectorAll(".dobp-opt");
    if (!opts.length) return 40;
    if (opts.length === 1) return opts[0].offsetHeight || 40;
    const top0 = opts[0].offsetTop, top1 = opts[1].offsetTop;
    const diff = Math.abs(top1 - top0);
    if (diff > 0) return diff;
    const cs = getComputedStyle(opts[0]);
    const h = opts[0].offsetHeight;
    const my = parseFloat(cs.marginTop) + parseFloat(cs.marginBottom);
    return h + my || h || 40;
  }

  /* --- Utils --- */
  function el(tag, attrs = {}, ...children) {
    const n = document.createElement(tag);
    for (const k in attrs) n.setAttribute(k, attrs[k]);
    children.forEach((c) => n.appendChild(c));
    return n;
  }
  function txt(s) { return document.createTextNode(s); }
  function clamp(v, a, b) { return Math.min(Math.max(v, a), b); }

  function getYearsDesc(min, max) {
    const out = [];
    for (let y = max; y >= min; y--) out.push(String(y));
    return out;
  }

  function getMonthsShort(loc) {
    try {
      const fmt = new Intl.DateTimeFormat(loc || "tr", { month: "short" });
      return Array.from({ length: 12 }, (_, i) =>
        fmt.format(new Date(2020, i, 1)).replace(/\.$/, "") // bazı dillerde nokta olur
      );
    } catch {
      return ["Oca","Şub","Mar","Nis","May","Haz","Tem","Ağu","Eyl","Eki","Kas","Ara"];
    }
  }

  // Basit token bazlı parser
  function buildTokenParser(fmt) {
    const esc = (s) => s.replace(/[-\/\\^$*+?.()|[\]{}]/g, "\\$&");
    const map = {
      "yyyy": "(?<yyyy>\\d{4})",
      "MM"  : "(?<MM>\\d{2})",
      "dd"  : "(?<dd>\\d{2})",
    };
    let reSrc = esc(fmt);
    reSrc = reSrc.replace(/yyyy|MM|dd/g, (t) => map[t]);
    const re = new RegExp("^" + reSrc + "$");

    function tryParse(v) {
      if (typeof v !== "string" || !v) return null;
      const m = re.exec(v.trim());
      if (!m?.groups) return null;
      const y = +m.groups.yyyy, M = +m.groups.MM, d = +m.groups.dd;
      if (!y || !M || !d) return null;
      return { y, m: M, d };
    }
    return { tryParse };
  }

  // Focus trap
  function getFocusable(container) {
    const sel = ["button","[href]","input","select","textarea","[tabindex]:not([tabindex='-1'])"].join(",");
    return Array.from(container.querySelectorAll(sel))
      .filter(el => !el.hasAttribute("disabled") && !el.getAttribute("aria-hidden"));
  }
  function focusFirstFocusable(container) {
    const list = getFocusable(container);
    const target = list.find(el => el.classList.contains("dobp-col")) || list[0];
    if (target) target.focus();
  }
  function trapTabKey(container, e) {
    const list = getFocusable(container);
    if (!list.length) return;
    const first = list[0], last = list[list.length - 1];
    if (e.shiftKey) {
      if (document.activeElement === first) { e.preventDefault(); last.focus(); }
    } else {
      if (document.activeElement === last) { e.preventDefault(); first.focus(); }
    }
  }

  // ISO helper
  function isoYYYYMMDD(d) {
    const pad = (n) => String(n).padStart(2, "0");
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
  }
}