/* =========================================================
   appointments-ui.js
   - Profil: servis/personel/tarih-saat seçimi + book
   - Randevularım (appointments.html): listele, iptal et, yeniden planla
   - appointments.js veri katmanını kullanır
   ========================================================= */

import { getSession, onAuthChange } from "./api-client.js";
import {
  // Profil akışı için:
  mergeDayWindow,
  getBookedRanges,
  generateSlots,
  bookAppointment,
  timeToMin,
  minToTime,
  // Randevularım sayfası için:
  watchUserUpcoming,
  watchUserPast,
  fetchUserAppointments,
  cancelAppointment,
} from "./appointments.js";

const TZ = "Europe/Istanbul";
const isApptPage =
  !!document.querySelector("#apptRoot") || window.__APPOINTMENTS_PAGE__ === true;

/* ------------------ Mini utils ------------------ */
const $ = (s) => document.querySelector(s);
const pad = (n) => String(n).padStart(2, "0");
const TL = (v) => "₺" + Number(v || 0).toLocaleString("tr-TR");
const escapeHTML = (s = "") =>
  String(s).replace(/[&<>"']/g, (m) => ({ "&":"&amp;","<":"&lt;",">":"&gt;",'"':"&quot;","'":"&#39;" }[m]));
const ymd = (d) => `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
const fmtMonth = ["Oca","Şub","Mar","Nis","May","Haz","Tem","Ağu","Eyl","Eki","Kas","Ara"];
const fmtDate = (d) => `${pad(d.getDate())} ${fmtMonth[d.getMonth()]} ${d.getFullYear()}`;
function showToast(msg){
  const wrap = document.getElementById("toastWrap");
  if (!wrap) { console.log("[toast]", msg); return; }
  const t = document.createElement("div");
  t.className = "toast show";
  t.innerHTML = `<span class="dot"></span>${escapeHTML(msg)}`;
  wrap.appendChild(t);
  setTimeout(()=>{ try{wrap.removeChild(t);}catch{} }, 2000);
}
function nowInTZ(tz = TZ){
  const parts = new Intl.DateTimeFormat("en-CA", {
    timeZone: tz, year: "numeric", month: "2-digit", day: "2-digit",
    hour: "2-digit", minute: "2-digit", second: "2-digit", hour12: false
  }).formatToParts(new Date());
  const get = (t) => Number(parts.find(p => p.type === t)?.value || 0);
  const Y = get("year"), M = get("month"), D = get("day");
  const h = get("hour"), m = get("minute");
  return { Y, M, D, h, m, ymd: `${Y}-${pad(M)}-${pad(D)}` };
}
function inPastTodayTZ(dateObj, hh, mm){
  const now = nowInTZ();
  const dStr = ymd(dateObj);
  if (dStr !== now.ymd) return false;
  if (hh < now.h) return true;
  if (hh === now.h && mm <= now.m) return true;
  return false;
}
function addMinutesToHHMM(hhmm = "00:00", addMin = 0) {
  const [h,m] = (hhmm || "00:00").split(":").map(n=>+n||0);
  const t = h*60 + m + (addMin||0);
  const nh = ((Math.floor(t/60)%24)+24)%24; const nm = ((t%60)+60)%60;
  return `${pad(nh)}:${pad(nm)}`;
}
function showOv(id){
  if (typeof window.showOv === "function") { window.showOv(id); return; }
  document.getElementById(id)?.classList.add("show");
}
function closeOv(id){
  if (typeof window.closeOv === "function") { window.closeOv(id); return; }
  document.getElementById(id)?.classList.remove("show");
}

/* =========================================================
   BÖLÜM A — PROFİLDE RANDEVU ALMA AKIŞI
   ========================================================= */

/* ------------------ Dahili durum ------------------ */
const state = {
  businessId: "",
  businessHoursTR: null,          // işletme saatleri (TR format)
  staff: [],                      // {id,name,workingHoursTR,bookable,active,photoURL?}
  services: [],                   // {id,name,durationMin,price,active}
  cart: [],                       // {serviceId?, name, duration, price}
  selectedDate: new Date(),
  selectedTime: "",               // "HH:MM"
  selectedStaff: null,            // { id, name } | null
  bufferMin: 0,                   // slot buffer
  granularity: 15,                // slot adımı
};

export function getState(){ return JSON.parse(JSON.stringify(state)); }
export function setBusinessHours(hoursTR){ state.businessHoursTR = hoursTR || null; refreshDayAndSlots(); }
export function setServices(list=[]){ state.services = normalizeServices(list); wireServiceTriggers(); }
export function setStaff(list=[]){ state.staff = normalizeStaff(list); }
export function openServicePicker(){ renderServicePicker(state.services); showOv("svcOv"); }
export function openTimePicker(){ buildDayRail(state.selectedDate, 14); setDateLabel(); refreshHourGrid(); showOv("timeOv"); }
export function openStaffPicker(){ renderStaffPicker(state.staff); showOv("staffOv"); }

export function initAppointmentsUI({
  businessId,
  businessHoursTR = null,
  services = [],
  staff = [],
  bufferMin = 0,
  granularity = 15,
} = {}) {
  state.businessId = String(businessId||"").trim();
  state.businessHoursTR = businessHoursTR || null;
  state.services = normalizeServices(services);
  state.staff = normalizeStaff(staff);
  state.bufferMin = Number(bufferMin||0);
  state.granularity = Number(granularity||15);

  // Query string ile gelen tarih/saat (opsiyonel)
  try{
    const p = new URLSearchParams(location.search);
    const qDate = p.get("date"); const qTime = p.get("time");
    if (qDate && /^\d{4}-\d{2}-\d{2}$/.test(qDate)) {
      const [Y,M,D] = qDate.split("-").map(Number); state.selectedDate = new Date(Y, M-1, D);
    }
    if (qTime && /^\d{1,2}:\d{2}$/.test(qTime)) state.selectedTime = qTime;
  }catch{}

  // UI bağla
  wireServiceUI();
  wireTimeUI();
  wireStaffUI();
  wireReviewUI();

  // Kısa yollar
  wireServiceTriggers();

  // İlk çizimler
  setDateLabel();
  buildDayRail(state.selectedDate, 14);
  refreshHourGrid();

  // Onay modalındaki "Randevularım" kısayolu
  $("#goAppointments")?.addEventListener("click", ()=>{ location.href = "appointments.html"; });
}

/* ------------------ Servis seçimi ------------------ */
function normalizeServices(arr){
  return (Array.isArray(arr)?arr:[])
    .map(s=>({
      id: s.id || s.serviceId || s.slug || s.key || (s.name ? s.name.toLowerCase().replace(/\s+/g,"-") : ""),
      name: s.name || "Hizmet",
      duration: Number(s.durationMin ?? s.min ?? s.duration ?? 30) || 30,
      price: Number(s.price ?? 0) || 0,
      active: (s.active !== false)
    }))
    .filter(s=>s.active !== false);
}
function serviceKey(s){ return `${s.name}:${s.duration}:${s.price}`; }

function wireServiceUI(){
  const search = $("#svcSearch");
  const next = $("#svcContinue");
  const totalEl = $("#svcTotal");

  // Arama debounce
  let timer = null;
  search?.addEventListener("input", (e)=>{
    const q = e.target.value || "";
    if (timer) clearTimeout(timer);
    timer = setTimeout(()=> renderServicePicker(state.services, q), 120);
  });

  next?.addEventListener("click", ()=>{
    if (!state.cart.length) { showToast("Lütfen en az bir hizmet seçin"); return; }
    buildDayRail(state.selectedDate, 14);
    setDateLabel();
    refreshHourGrid();
    showOv("timeOv");
  });

  // Toplam ilk değer
  if (totalEl) totalEl.textContent = TL(0);
}
function wireServiceTriggers(){
  // "Tüm hizmetleri aç" butonu
  const btn = document.getElementById("openAllServices");
  if (btn && !btn._bound){
    btn._bound = true;
    btn.addEventListener("click", ()=>{ renderServicePicker(state.services); showOv("svcOv"); });
  }
  // Her bir satırdaki "Randevu al" kısayolu
  document.querySelectorAll(".open-book").forEach(el=>{
    if (el._bound) return; el._bound = true;
    el.addEventListener("click", ()=>{
      const svc = {
        id: el.dataset.sid || "",
        name: el.dataset.name || "Hizmet",
        price: Number(el.dataset.price || 0),
        duration: Number(el.dataset.duration || el.dataset.min || 30)
      };
      state.cart = [svc];
      renderServicePicker(state.services); // sepet görünür olsun
      showOv("timeOv");
      buildDayRail(state.selectedDate, 14);
      setDateLabel();
      refreshHourGrid();
    });
  });
}

function renderServicePicker(list=[], query=""){
  const box = $("#svcList");
  const totalEl = $("#svcTotal");
  const arr = (list||[]).filter(s => s.active !== false)
    .filter(s => (s.name||"").toLowerCase().includes(String(query||"").toLowerCase()));

  const btnStyle = (pressed=false) =>
    `border:0;border-radius:10px;padding:8px 12px;font-weight:800;cursor:pointer;` +
    (pressed ? `background:#0aa36b;color:#fff;` : `background:#111;color:#fff;`);

  box && (box.innerHTML = arr.map(s=>{
    const skey = serviceKey(s);
    const on = state.cart.some(x => serviceKey(x) === skey);
    return `
      <div class="svc-item" style="display:flex;justify-content:space-between;gap:10px;align-items:center">
        <div>
          <div style="font-weight:700">${escapeHTML(s.name||"Hizmet")}</div>
          <div class="meta">${Number(s.duration||30)}dk</div>
        </div>
        <div style="display:flex;align-items:center;gap:10px">
          <div style="font-weight:800">${TL(s.price||0)}</div>
          <button class="btn-select" data-key="${escapeHTML(skey)}" data-sid="${escapeHTML(s.id||"")}"
                  aria-pressed="${on}" style="${btnStyle(on)}">
            ${on ? "Seçildi" : "Seç"}
          </button>
        </div>
      </div>`;
  }).join(""));

  box?.querySelectorAll(".btn-select").forEach(btn=>{
    btn.addEventListener("click", ()=>{
      const key = btn.getAttribute("data-key");
      const sid = btn.getAttribute("data-sid") || "";
      const [name, durStr, priceStr] = key.split(":");
      const duration = Number(durStr||30);
      const price = Number(priceStr||0);
      const idx = state.cart.findIndex(x => serviceKey(x) === key);
      const pressed = idx === -1;

      if (pressed) state.cart.push({ serviceId: sid || undefined, id:sid||undefined, name, duration, price });
      else state.cart.splice(idx,1);

      btn.setAttribute("aria-pressed", String(pressed));
      btn.textContent = pressed ? "Seçildi" : "Seç";
      btn.setAttribute("style", btnStyle(pressed));
      updateServiceFooter();
    });
  });

  updateServiceFooter();

  function updateServiceFooter(){
    const tot = state.cart.reduce((s,i)=> s + Number(i.price||0), 0);
    totalEl && (totalEl.textContent = TL(tot));
    const next = $("#svcContinue"); if (next) next.disabled = state.cart.length === 0;
  }
}

/* ------------------ Tarih / Saat (slot) ------------------ */
function wireTimeUI(){
  const done = $("#timeDone");
  done?.addEventListener("click", ()=>{
    if (!state.selectedTime) { showToast("Lütfen bir saat seçin"); return; }
    if (state.selectedStaff?.id) { buildReview(); showOv("reviewOv"); return; }
    renderStaffPicker(state.staff); showOv("staffOv");
  });
}
export function setDateLabel(){
  const lbl = $("#timeDateLabel"); if (lbl) lbl.textContent = fmtDate(state.selectedDate);
}
export function buildDayRail(baseDate = new Date(), days = 14){
  const rail = $("#dayRail"); if (!rail) return;
  rail.innerHTML = "";
  for (let i=0;i<days;i++){
    const d = new Date(baseDate); d.setDate(baseDate.getDate() + i);
    const btn = document.createElement("button");
    btn.className = "day-pill" + (ymd(d)===ymd(state.selectedDate) ? " active" : "");
    btn.dataset.date = ymd(d);
    btn.innerHTML = `
      <div style="font-weight:800">${["Paz","Pts","Sal","Çar","Per","Cum","Cts"][d.getDay()]}</div>
      <div>${pad(d.getDate())}/${pad(d.getMonth()+1)}</div>
    `;
    btn.addEventListener("click", ()=>{
      state.selectedDate = d; state.selectedTime = "";
      document.querySelectorAll(".day-pill").forEach(x=>x.classList.toggle("active", x===btn));
      setDateLabel(); refreshHourGrid();
    });
    rail.appendChild(btn);
  }
}
function personHoursFor(staffId){
  const s = (state.staff||[]).find(x => (x.id||x.uid) === staffId);
  return s?.workingHoursTR || null;
}
function effectiveDayWindow(){
  const staffH = state.selectedStaff?.id ? personHoursFor(state.selectedStaff.id) : null;
  return mergeDayWindow(state.businessHoursTR, staffH, ymd(state.selectedDate));
}
function openMinutePicker(hour){
  const wrap = $("#timeSlotWrap"); if (!wrap) return;
  wrap.innerHTML = "";

  const quarters = [0,15,30,45];
  const totalMin = Math.max(15, state.cart.reduce((m,i)=> m + (i.duration||30), 0) || 30);

  // Başlık güncelle
  const t = $("#timeTtl");
  if (t) t.innerHTML = `<span id="timeDateLabel">${fmtDate(state.selectedDate)}</span> • Dakika seç`;

  // Geri butonu
  $("#minBack")?.remove();
  const back = document.createElement("button");
  back.id = "minBack"; back.type = "button"; back.className = "btn-mini"; back.style.margin = "0 0 10px 2px";
  back.textContent = "← Saatlere dön";
  back.addEventListener("click", ()=>{
    const tt = $("#timeTtl");
    if (tt) tt.innerHTML = `<span id="timeDateLabel">${fmtDate(state.selectedDate)}</span> • Saat seç`;
    back.remove(); refreshHourGrid();
  });
  wrap.parentElement?.prepend(back);

  quarters.forEach((m)=>{
    const startMin = hour*60 + m;
    const within = checkWithinWorking(startMin, totalMin);
    let disabled = !within || inPastTodayTZ(state.selectedDate, hour, m);

    const btn = document.createElement("button");
    btn.type="button"; btn.className="slot"; btn.textContent = `${pad(hour)}:${pad(m)}`;
    btn.disabled = disabled;
    if (state.selectedTime === `${pad(hour)}:${pad(m)}`) btn.classList.add("active");

    btn.addEventListener("click", ()=>{
      if (btn.disabled) return;
      state.selectedTime = `${pad(hour)}:${pad(m)}`;
      document.querySelectorAll("#timeOv .slot").forEach(x=>x.classList.remove("active"));
      btn.classList.add("active");
      $("#timeDone")?.focus();
    });
    wrap.appendChild(btn);
  });
}
function checkWithinWorking(startMin, durationMin){
  const dw = effectiveDayWindow();
  if (!dw?.open || !dw?.ranges?.length) return false;
  const endMin = startMin + durationMin;
  return dw.ranges.some(r => startMin >= r.startMin && endMin <= r.endMin);
}
export async function refreshHourGrid(){
  const wrap = $("#timeSlotWrap"); if (!wrap) return;

  const dw = effectiveDayWindow();
  if (!dw?.open || !dw.ranges?.length){
    wrap.innerHTML = `<div class="muted" style="padding:8px 2px">Seçili gün kapalı.</div>`;
    return;
  }

  // Seçili personele göre booked ranges
  let booked = [];
  try{
    if (state.selectedStaff?.id && state.businessId){
      booked = await getBookedRanges({
        businessId: state.businessId,
        staffId: state.selectedStaff.id,
        dayStr: ymd(state.selectedDate)
      });
    } else {
      booked = []; // personel seçilmediyse saatler "aday" olarak gösterilir
    }
  }catch{ booked = []; }

  const totalMin = Math.max(15, state.cart.reduce((m,i)=> m + (i.duration||30), 0) || 30);
  const slots = generateSlots({
    dayWindow: dw,
    durationMin: totalMin,
    bufferMin: state.bufferMin,
    granularity: state.granularity,
    booked
  });

  if (!slots.length){
    wrap.innerHTML = `<div class="muted" style="padding:8px 2px">Uygun saat bulunamadı.</div>`;
    return;
  }

  // Başlık
  const t = $("#timeTtl");
  if (t) t.innerHTML = `<span id="timeDateLabel">${fmtDate(state.selectedDate)}</span> • Saat seç`;

  wrap.innerHTML = "";
  const now = nowInTZ();

  slots.forEach(s=>{
    const hhmm = minToTime(s.startMin);
    const [hh,mm] = hhmm.split(":").map(Number);

    const btn = document.createElement("button");
    btn.type="button"; btn.className="slot"; btn.textContent = hhmm;

    // Bugün geçmiş saat engeli
    const todayStr = ymd(state.selectedDate);
    if (todayStr === now.ymd && (hh < now.h || (hh === now.h && mm <= now.m))) {
      btn.disabled = true;
    }

    btn.addEventListener("click", ()=> openMinutePicker(hh));
    wrap.appendChild(btn);
  });
}

/* ------------------ Personel seçimi ------------------ */
function normalizeStaff(arr){
  return (Array.isArray(arr)?arr:[]).map(s=>({
    id: s.id || s.uid || "",
    uid: s.uid || s.id || "",
    name: s.name || "Personel",
    bookable: (s.bookable !== false),
    active: (s.active !== false),
    workingHoursTR: s.workingHoursTR || s.workingHours || null,
    photoURL: s.photoURL || s.photoUrl || ""
  })).filter(s => s.active !== false);
}
function initials(name=""){ const p = (name||"").trim().split(/\s+/).slice(0,2); return p.map(x=>x[0]?.toUpperCase()||"").join(""); }
function wireStaffUI(){ /* seçici modal içerik renderStaffPicker'da */ }
function renderStaffPicker(list=[]){
  const box = $("#staffPickList"); if (!box) return;
  const avail = (list||[]).filter(s => s.bookable !== false && s.active !== false);

  if (!avail.length){
    box.innerHTML = `
      <div class="svc-item">
        <div>
          <div style="font-weight:700">Henüz personel eklenmemiş.</div>
          <div class="meta">Yine de saat seçebilir ve uygun personel bulunursa atanır.</div>
        </div>
        <button class="btn-mini" data-any>Farketmez</button>
      </div>`;
    box.querySelector("[data-any]")?.addEventListener("click", ()=>{
      state.selectedStaff = null; closeOv("staffOv"); buildReview(); showOv("reviewOv");
    });
    return;
  }

  box.innerHTML = `
    <div class="svc-item" style="justify-content:space-between">
      <div>Farketmez <div class="meta">Uygun personele atanır</div></div>
      <button class="btn-mini" data-any>Seç</button>
    </div>
    ${avail.map(s=>`
      <div class="svc-item" style="justify-content:space-between">
        <div style="display:flex;align-items:center;gap:10px">
          <div class="avatar" style="width:32px;height:32px;border-radius:50%;overflow:hidden;display:grid;place-items:center;background:#eee">
            ${s.photoURL ? `<img src="${escapeHTML(s.photoURL)}" alt="" style="width:100%;height:100%;object-fit:cover">`
                          : `<span class="initial" style="font-weight:800">${escapeHTML(initials(s.name))}</span>`}
          </div>
          <div><div style="font-weight:700">${escapeHTML(s.name)}</div><div class="meta">Uygun</div></div>
        </div>
        <button class="btn-mini" data-pick="${escapeHTML(s.id||s.uid)}" data-name="${escapeHTML(s.name)}">Seç</button>
      </div>
    `).join("")}
  `;

  box.querySelector("[data-any]")?.addEventListener("click", ()=>{
    state.selectedStaff = null; closeOv("staffOv"); buildReview(); showOv("reviewOv");
  });
  box.querySelectorAll("[data-pick]").forEach(btn=>{
    btn.addEventListener("click", ()=>{
      state.selectedStaff = { id: btn.getAttribute("data-pick"), name: btn.getAttribute("data-name") || "Personel" };
      closeOv("staffOv"); buildReview(); showOv("reviewOv");
    });
  });
}

/* ------------------ Özet / Kaydet ------------------ */
function wireReviewUI(){
  $("#confirmBook")?.addEventListener("click", saveBooking);
}
function cartTotal(){ return state.cart.reduce((s,i)=> s + Number(i.price||0), 0); }
function cartTotalMin(){ return Math.max(15, state.cart.reduce((m,i)=> m + (Number(i.duration||30)), 0) || 30); }
function buildReview(){
  const box = $("#reviewBody"); if (!box) return;
  const total = cartTotal(); const totalMin = cartTotalMin();

  const staffStr = state.selectedStaff?.name ? ` • ${escapeHTML(state.selectedStaff.name)}` : "";
  const start = state.selectedTime || "Saat seçilmedi";
  const end = state.selectedTime ? addMinutesToHHMM(state.selectedTime, totalMin) : "";

  box.innerHTML = `
    <div style="text-align:center;font-weight:800">
      ${fmtDate(state.selectedDate)}${staffStr}<br>
      <span class="muted" style="font-weight:700">
        ${escapeHTML(start)}${end ? ` – ${escapeHTML(end)}` : ""} • ${totalMin}dk toplam
      </span>
    </div>
    <div class="sumbox" style="margin-top:16px">
      ${state.cart.map(i=>`
        <div style="display:flex;justify-content:space-between;font-weight:700;padding:8px 0">
          <span>${escapeHTML(i.name)}<br><span class="small">${Number(i.duration||30)}dk</span></span>
          <span>${TL(i.price||0)}</span>
        </div>
      `).join("")}
      <div class="sumrow"><span style="font-weight:700">Toplam:</span><span style="font-weight:800">${TL(total)}</span></div>
      <div style="margin-top:8px;display:flex;justify-content:flex-start">
        <button id="openAddFromReview" class="btn-outline" style="border:1px solid var(--border);border-radius:10px;padding:8px 12px;font-weight:700;cursor:pointer;background:#fff">Başka hizmet ekle</button>
      </div>
    </div>
  `;

  $("#reviewTotal") && ($("#reviewTotal").textContent = TL(total));
  $("#openAddFromReview")?.addEventListener("click", ()=>{ renderServicePicker(state.services); showOv("svcOv"); });
}

let _saving = false;
async function saveBooking(){
  if (_saving) return;

  if (!state.businessId) { showToast("İşletme bilgisi eksik"); return; }
  if (!state.cart.length) { showToast("Lütfen hizmet seçin"); return; }
  if (!state.selectedTime) { showToast("Lütfen saat seçin"); return; }

  const totalMin = cartTotalMin();
  const [hh,mm] = state.selectedTime.split(":").map(Number);
  const startMin = hh*60 + (mm||0);
  const endMin = startMin + totalMin;

  if (!checkWithinWorking(startMin, totalMin)) {
    showToast("Seçilen saat çalışma saatleri dışında"); return;
  }

  // Çakışma kontrol (seçili personele göre)
  try{
    if (state.selectedStaff?.id){
      const booked = await getBookedRanges({
        businessId: state.businessId,
        staffId: state.selectedStaff.id,
        dayStr: ymd(state.selectedDate)
      });
      const collide = booked.some(b => !(endMin <= b.startMin || startMin >= b.endMin));
      if (collide) { showToast("Seçili personel bu saatte dolu"); return; }
    }
  }catch{}

  const firstSvc = state.cart[0] || {};
  const dayStr = ymd(state.selectedDate);

  const btn = $("#confirmBook");
  try{
    _saving = true; btn && (btn.disabled = true);

    const res = await bookAppointment({
      businessId: state.businessId,
      staffId: state.selectedStaff?.id || "any",
      serviceId: firstSvc.serviceId || firstSvc.id || (firstSvc.name || "service"),
      dayStr,
      startMin,
      durationMin: totalMin,
      customer: {},             // appointments.js tarafı auth.currentUser varsa doldurur
      status: "pending",
      source: "web",
      notes: state.cart.length > 1 ? `Çoklu hizmet: ${state.cart.map(s=>s.name).join(", ")}` : ""
    });

    if (res?.ok){
      const whenEl = $("#confirmWhen");
      if (whenEl) {
        const end = addMinutesToHHMM(state.selectedTime, totalMin);
        whenEl.textContent = `${fmtDate(state.selectedDate)} • ${state.selectedTime} – ${end} • ${totalMin}dk`;
      }
      closeOv("reviewOv"); closeOv("timeOv"); closeOv("staffOv"); closeOv("svcOv");
      showOv("confirmOv");
    } else {
      showToast("Randevu oluşturulamadı");
    }
  }catch(e){
    console.error(e);
    showToast(String(e?.message || "Randevu başarısız"));
  }finally{
    _saving = false; btn && (btn.disabled = false);
  }
}
function refreshDayAndSlots(){ buildDayRail(state.selectedDate, 14); setDateLabel(); refreshHourGrid(); }

/* =========================================================
   BÖLÜM B — RANDEVULARIM (appointments.html)
   ========================================================= */

if (isApptPage) {
  initAppointmentsPage();
}

function initAppointmentsPage(){
  // Sekmeler
  const tabs = $("#apptTabs");
  tabs?.addEventListener("click", (e)=>{
    const b = e.target.closest(".tab"); if (!b) return;
    tabs.querySelectorAll(".tab").forEach(t=>t.classList.remove("active"));
    b.classList.add("active");
    const k = b.dataset.tab;
    $("#apptPanelUpcoming")?.classList.toggle("active", k==="upcoming");
    $("#apptPanelUpcoming")?.toggleAttribute("hidden", k!=="upcoming");
    $("#apptPanelPast")?.classList.toggle("active", k==="past");
    $("#apptPanelPast")?.toggleAttribute("hidden", k!=="past");
  });

  // Modal kapatıcıları
  document.querySelectorAll(".modal-ov [data-close]")?.forEach(x=>{
    x.addEventListener("click", ()=> x.closest(".modal-ov")?.classList.remove("show"));
  });

  // Cancel & Resched butonları için delegasyon
  $("#apptListUpc")?.addEventListener("click", handleListActions);
  $("#apptListPast")?.addEventListener("click", handleListActions);

  // Cancel modal onay
  $("#btnApptCancelYes")?.addEventListener("click", onConfirmCancel);

  // Reschedule modal kaydet
  $("#btnApptReschedApply")?.addEventListener("click", onApplyResched);

  // Reschedule tarih min = bugün
  const d = $("#reschedDate");
  if (d) {
    const n = new Date();
    d.min = `${n.getFullYear()}-${pad(n.getMonth()+1)}-${pad(n.getDate())}`;
  }

  // Auth hazır olduğunda abonelikleri başlat
  const startWatchers = () => {
    // Yükleniyorları göster
    setLoading("upc", true);
    setLoading("past", true);

    try {
      // Canlı akış
      window.__unsubUpc?.(); window.__unsubPast?.();
      window.__unsubUpc = watchUserUpcoming({}, (items)=> renderUpcoming(items));
      window.__unsubPast = watchUserPast({}, (items)=> renderPast(items));
    } catch (e) {
      console.error("watch error", e);
      // Fallback: tek seferlik fetch
      refreshListsOnce();
    }
  };

  if (true) {
    onAuthChange((session) => {
      if (session) startWatchers();
      else {
        // Giriş yoksa boş göster
        renderUpcoming([]); renderPast([]);
      }
    });
  } else {
    // Plugin yoksa yine dene
    refreshListsOnce();
  }
}

async function refreshListsOnce(){
  try{
    setLoading("upc", true);
    const upc = await fetchUserAppointments({kind:"upcoming", pageSize:50});
    renderUpcoming(upc.items||[]);
  }catch(e){ console.error(e); }
  try{
    setLoading("past", true);
    const past = await fetchUserAppointments({kind:"past", pageSize:50});
    renderPast(past.items||[]);
  }catch(e){ console.error(e); }
}

function setLoading(kind, on){
  if (kind === "upc") {
    $("#apptLoadingUpc")?.toggleAttribute("hidden", !on);
    if (on) { $("#apptEmptyUpc")?.setAttribute("hidden",""); $("#apptListUpc") && ($("#apptListUpc").innerHTML=""); }
  } else {
    $("#apptLoadingPast")?.toggleAttribute("hidden", !on);
    if (on) { $("#apptEmptyPast")?.setAttribute("hidden",""); $("#apptListPast") && ($("#apptListPast").innerHTML=""); }
  }
}

function renderUpcoming(list){
  setLoading("upc", false);
  const root = $("#apptListUpc"); const empty = $("#apptEmptyUpc");
  if (!root) return;
  if (!Array.isArray(list) || !list.length){
    empty?.removeAttribute("hidden");
    root.innerHTML = "";
    return;
  }
  empty?.setAttribute("hidden","");
  root.innerHTML = list.map(a => apptCardHTML(a, "upcoming")).join("");
}
function renderPast(list){
  setLoading("past", false);
  const root = $("#apptListPast"); const empty = $("#apptEmptyPast");
  if (!root) return;
  if (!Array.isArray(list) || !list.length){
    empty?.removeAttribute("hidden");
    root.innerHTML = "";
    return;
  }
  empty?.setAttribute("hidden","");
  root.innerHTML = list.map(a => apptCardHTML(a, "past")).join("");
}

function apptCardHTML(a, kind){
  // a: { id, businessId, staffId, startAt(Timestamp), startMin,endMin, day, status, business? }
  const ts = a.startAt?.toDate ? a.startAt.toDate() : (a.startAt || new Date());
  const dayStr = a.day || ymd(ts);
  const start = a.startMin ?? timeToMin(`${pad(ts.getHours())}:${pad(ts.getMinutes())}`);
  const end = a.endMin ?? (start + 30);
  const hhmm = minToTime(start);
  const hhmmEnd = minToTime(end);
  const dur = end - start;

  const bName = a.business?.name || "İşletme";
  const bAddr = a.business?.address || "";
  const photo = a.business?.photo || "";
  const stf = a.staffId && a.staffId !== "any" ? ` • Personel: ${escapeHTML(a.staffId)}` : " • Personel: Belirlenecek";

  const status = a.status || "pending";
  const badge = status === "confirmed" ? "badge-ok"
              : status === "cancelled" ? "badge-cancel"
              : status === "noshow" ? "badge-warn"
              : "badge-pending";

  return `
    <article class="appt-item" data-appt='${escapeHTML(JSON.stringify({
      id:a.id, businessId:a.businessId, staffId:a.staffId, day:dayStr,
      startMin:start, endMin:end, duration: dur, status
    }))}'>
      <div class="row">
        <div class="left">
          ${photo ? `<img class="thumb" src="${escapeHTML(photo)}" alt="" loading="lazy">` : `<div class="thumb ph"></div>`}
          <div class="info">
            <div class="ttl">${escapeHTML(bName)} <span class="badge ${badge}">${escapeHTML(status)}</span></div>
            <div class="sub">${fmtDate(new Date(dayStr))} • ${hhmm}–${hhmmEnd} (${dur}dk)${stf}</div>
            ${bAddr ? `<div class="muted small">${escapeHTML(bAddr)}</div>`:""}
          </div>
        </div>
        <div class="right">
          ${kind==="upcoming" && status!=="cancelled" ? `
            <button class="btn-mini" data-resched>Yeniden planla</button>
            <button class="btn-mini danger" data-cancel>İptal et</button>
          ` : ``}
        </div>
      </div>
    </article>
  `;
}

/* ---------- Liste olayları ---------- */
let _selectedAppt = null;

function handleListActions(e){
  const btn = e.target.closest("[data-cancel],[data-resched]");
  if (!btn) return;
  const card = e.target.closest(".appt-item");
  if (!card) return;
  try {
    _selectedAppt = JSON.parse(card.getAttribute("data-appt") || "{}");
  } catch { _selectedAppt = null; }

  if (btn.hasAttribute("data-cancel")) {
    $("#cancelReason") && ($("#cancelReason").value = "");
    showOv("apptCancelModal");
  } else if (btn.hasAttribute("data-resched")) {
    // Varsayılan tarih/saat alanlarını doldur
    const d = $("#reschedDate");
    const t = $("#reschedTime");
    if (d) d.value = _selectedAppt?.day || "";
    if (t) t.value = minToTime(_selectedAppt?.startMin || 0);
    showOv("apptReschedModal");
  }
}

/* ---------- İptal onayı ---------- */
let _cancelBusy = false;
async function onConfirmCancel(){
  if (_cancelBusy || !_selectedAppt?.id) return;
  const btn = $("#btnApptCancelYes");
  const reason = $("#cancelReason")?.value || "user_cancel";
  try{
    _cancelBusy = true; btn && (btn.disabled = true);
    await cancelAppointment({
      businessId: _selectedAppt.businessId,
      apptId: _selectedAppt.id,
      reason
    });
    closeOv("apptCancelModal");
    showToast("Randevu iptal edildi");
  }catch(e){
    console.error(e);
    showToast(e?.message || "İptal başarısız");
  }finally{
    _cancelBusy = false; btn && (btn.disabled = false);
  }
}

/* ---------- Yeniden planla ---------- */
let _reschedBusy = false;
async function onApplyResched(){
  if (_reschedBusy || !_selectedAppt?.id) return;

  const dEl = $("#reschedDate");
  const tEl = $("#reschedTime");
  const dayStr = dEl?.value || "";
  const timeStr = tEl?.value || "";
  if (!/^\d{4}-\d{2}-\d{2}$/.test(dayStr)) { showToast("Geçerli bir tarih seçin"); return; }
  if (!/^\d{1,2}:\d{2}$/.test(timeStr)) { showToast("Geçerli bir saat seçin"); return; }

  const startMin = timeToMin(timeStr);
  const durationMin = Math.max(15, Number(_selectedAppt.duration || (_selectedAppt.endMin - _selectedAppt.startMin) || 30));

  const btn = $("#btnApptReschedApply");
  try{
    _reschedBusy = true; btn && (btn.disabled = true);

    // Önce yeni slotu rezerve etmeyi dene:
    await bookAppointment({
      businessId: _selectedAppt.businessId,
      staffId: _selectedAppt.staffId || "any",
      serviceId: _selectedAppt.serviceId || "service",
      dayStr,
      startMin,
      durationMin,
      status: "pending",
      source: "web",
      notes: `reschedule-from:${_selectedAppt.id}`
    });

    // Eskiyi iptal et
    await cancelAppointment({
      businessId: _selectedAppt.businessId,
      apptId: _selectedAppt.id,
      reason: "user_reschedule"
    });

    closeOv("apptReschedModal");
    showToast("Randevu yeniden planlandı");
  }catch(e){
    console.error(e);
    // BookTransaction çakışma hatası vs.
    showToast(e?.message || "Yeniden planlama başarısız");
  }finally{
    _reschedBusy = false; btn && (btn.disabled = false);
  }
}

/* =========================================================
   Global (opsiyonel kolay erişim)
   ========================================================= */
try {
  window.AppointmentsUI = {
    // Profil
    init: initAppointmentsUI,
    state: getState,
    setBusinessHours,
    setServices,
    setStaff,
    openServicePicker,
    openTimePicker,
    openStaffPicker,
    refreshHourGrid,
    buildDayRail,
    setDateLabel,
    // Randevularım
    refreshListsOnce
  };
} catch {}