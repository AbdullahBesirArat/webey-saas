// js/appointments.js
// Randevu veri katmanı — Firebase YOK, PHP API tabanlı
// v6.0 — api/appointments/ + api/calendar/ + api/settings/ endpoint'leri kullanılır

import { api } from './api-client.js';

/* ========= Sabitler & yardımcılar ========= */
export const TZ = 'Europe/Istanbul';
const pad = (n) => String(n).padStart(2, '0');
const BLOCKING_STATES = new Set(['pending', 'confirmed', 'approved']);

/** Date -> "YYYY-MM-DD" */
export const toDayStr = (d) =>
  `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}`;

/** "10:30" -> 630 */
export const timeToMin = (t) => {
  if (typeof t === 'number' && Number.isFinite(t)) return t;
  const [h = '0', m = '0'] = String(t || '').split(':');
  return Number(h) * 60 + Number(m);
};

/** 630 -> "10:30" */
export const minToTime = (m) =>
  `${pad(Math.floor(m / 60))}:${pad(m % 60)}`;

/* ========= Çalışma saatleri (PHP'den gelen format) ========= */
// PHP API: { mon:{closed,open,close}, tue:{...}, ... }
// mon/tue/wed/thu/fri/sat/sun

const EN_KEYS = ['sun', 'mon', 'tue', 'wed', 'thu', 'fri', 'sat'];
const TR_NAMES = ['Pazar', 'Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi'];

function dayIndexFromDayStr(dayStr) {
  const [y, m, d] = String(dayStr || '').split('-').map(Number);
  return new Date(y || 1970, (m || 1) - 1, d || 1).getDay();
}

/** PHP settings hours formatını iç formata çevir */
function phpHoursToInternal(phpHours) {
  const out = {};
  for (let i = 0; i < 7; i++) {
    const key = EN_KEYS[i];
    const h = phpHours?.[key];
    if (!h || h.closed || !h.open || !h.close) {
      out[i] = { open: false, ranges: [] };
    } else {
      out[i] = {
        open: true,
        ranges: [{ startMin: timeToMin(h.open), endMin: timeToMin(h.close) }]
      };
    }
  }
  return out;
}

/**
 * İşletme + personel saatlerini kesiştirir.
 * @returns {{open:boolean, ranges:Array<{startMin,endMin}>}}
 */
export function mergeDayWindow(bizHoursInternal, staffHoursInternal, dayStr) {
  const idx = dayIndexFromDayStr(dayStr);
  const b = bizHoursInternal?.[idx];
  const s = staffHoursInternal?.[idx];

  if (!b?.open || !s?.open || !b.ranges?.length || !s.ranges?.length) {
    return { open: false, ranges: [] };
  }

  const out = [];
  for (const br of b.ranges) {
    for (const sr of s.ranges) {
      const start = Math.max(br.startMin, sr.startMin);
      const end   = Math.min(br.endMin,   sr.endMin);
      if (end > start) out.push({ startMin: start, endMin: end });
    }
  }
  return out.length ? { open: true, ranges: out } : { open: false, ranges: [] };
}

/* ========= Slot üretimi ========= */
export const overlaps = (aS, aE, bS, bE) => aS < bE && aE > bS;

/**
 * @param {{dayWindow, durationMin, bufferMin?, granularity?, booked?}} params
 * @returns {Array<{startMin, endMin, label}>}
 */
export function generateSlots({ dayWindow, durationMin, bufferMin = 0, granularity = 15, booked = [] }) {
  if (!dayWindow?.open || !dayWindow?.ranges?.length) return [];
  if (!Number.isFinite(durationMin) || durationMin <= 0) return [];

  const slots = [];
  for (const r of dayWindow.ranges) {
    let start = r.startMin - (r.startMin % granularity);
    if (start < r.startMin) start += granularity;

    for (; start + durationMin <= r.endMin; start += granularity) {
      const end = start + durationMin;
      const collide = booked.some((b) =>
        overlaps(start - bufferMin, end + bufferMin, b.startMin, b.endMin)
      );
      if (!collide) {
        slots.push({ startMin: start, endMin: end, label: minToTime(start) });
      }
    }
  }
  return slots;
}

/* ========= Dolu slotları getir (PHP API) ========= */
/**
 * @param {{businessId, staffId, dayStr}} params
 * @returns {Promise<Array<{startMin, endMin}>>}
 */
export async function getBookedRanges({ businessId, staffId, dayStr }) {
  if (!businessId || !staffId || !dayStr) return [];
  try {
    const res = await api.get(
      `/api/appointments/booked-map.php?date=${dayStr}&staffId=${staffId}&businessId=${businessId}`
    );
    if (!res.ok) return [];

    // booked-map döner: { "10:00": true, "10:15": true, ... }
    // Bunu sürekli aralıklara çevir
    const bookedMap = res.data?.booked || {};
    const times = Object.keys(bookedMap).sort();
    if (!times.length) return [];

    // 15'er dakikalık slotları birleştir
    const ranges = [];
    let start = null, prev = null;
    for (const t of times) {
      const m = timeToMin(t);
      if (start === null) { start = m; prev = m; }
      else if (m === prev + 15) { prev = m; }
      else {
        ranges.push({ startMin: start, endMin: prev + 15 });
        start = m; prev = m;
      }
    }
    if (start !== null) ranges.push({ startMin: start, endMin: prev + 15 });
    return ranges;
  } catch { return []; }
}

/* ========= İşletme + Personel saatlerini çek ========= */
/**
 * PHP API'dan business hours ve staff hours getirir (iç formata çevrilmiş)
 */
export async function fetchWorkingHours({ businessId, staffId }) {
  try {
    // Business hours: settings/load.php
    const bRes = await api.get('/api/settings/load.php');
    const phpBizHours = bRes.ok ? (bRes.data?.hours || {}) : {};
    const bizHours = phpHoursToInternal(phpBizHours);

    // Staff hours: staff/save.php GET — staff_hours tablosundan
    let staffHours = { ...bizHours }; // fallback: iş saatleri
    if (staffId) {
      const sRes = await api.get(`/api/staff/hours.php?staffId=${staffId}`);
      if (sRes.ok && sRes.data?.hours) {
        staffHours = phpHoursToInternal(sRes.data.hours);
      }
    }
    return { bizHours, staffHours };
  } catch {
    return { bizHours: {}, staffHours: {} };
  }
}

/* ========= Çakışma kontrolü ========= */
export async function ensureWithinWorkingHours({ businessId, staffId, dayStr, startMin, endMin }) {
  const { bizHours, staffHours } = await fetchWorkingHours({ businessId, staffId });
  const dayWindow = mergeDayWindow(bizHours, staffHours, dayStr);
  if (!dayWindow.open || !dayWindow.ranges?.length) {
    throw new Error('Bu personel bu gün çalışmıyor.');
  }
  const allowed = dayWindow.ranges.some(r => startMin >= r.startMin && endMin <= r.endMin);
  if (!allowed) throw new Error('Seçilen saat personelin çalışma saatleri dışında.');
  return dayWindow;
}

/* ========= Randevu oluştur ========= */
/**
 * @returns {Promise<{ok:true, id:string}>}
 */
export async function bookAppointment({ businessId, staffId, serviceId, dayStr, startMin, durationMin, customer = {}, status = 'pending', source = 'web', notes = '' }) {
  if (!businessId || !staffId || !serviceId || !dayStr) throw new Error('Eksik parametre.');
  if (!Number.isFinite(startMin) || !Number.isFinite(durationMin)) throw new Error('Başlangıç veya süre geçersiz.');

  const startAt = `${dayStr}T${minToTime(startMin)}:00`;

  const res = await api.post('/api/appointments/book.php', {
    staffId,
    serviceId,
    startAt,
    customer: {
      name:  customer.name  || '',
      phone: customer.phone || customer.phoneE164 || '',
      email: customer.email || '',
    },
    notes: notes || '',
  });

  if (!res.ok) throw new Error(res.error || 'Randevu alınamadı.');
  return { ok: true, id: String(res.data?.id || '') };
}

/* ========= Durum güncelle ========= */
export async function updateAppointmentStatus({ businessId, apptId, appointmentId, id, status, attended, noShow } = {}) {
  const aid = apptId || appointmentId || id;
  if (!aid) throw new Error('Geçersiz parametre.');

  const body = { id: aid };
  if (status)                          body.status   = status;
  if (typeof attended === 'boolean')   body.attended = attended;
  if (typeof noShow   === 'boolean' && noShow) body.status = 'no_show';

  const res = await api.post('/api/calendar/update-appointment.php', body);
  if (!res.ok) throw new Error(res.error || 'Güncelleme başarısız.');
  return { ok: true };
}

/* ========= İptal ========= */
export async function cancelAppointment({ businessId, apptId, reason = 'user_cancel' }) {
  const res = await api.post('/api/appointments/cancel.php', {
    id: apptId,
    reason,
  });
  if (!res.ok) throw new Error(res.error || 'İptal başarısız.');
  return { ok: true };
}

/* ========= Yeniden planla ========= */
export async function rescheduleAppointment({ businessId, apptId, newDayStr, newStartMin }) {
  if (!apptId || !newDayStr || !Number.isFinite(newStartMin)) throw new Error('Eksik parametre.');
  const newStartAt = `${newDayStr}T${minToTime(newStartMin)}:00`;
  const res = await api.post('/api/appointments/reschedule.php', {
    id: apptId,
    startAt: newStartAt,
  });
  if (!res.ok) throw new Error(res.error || 'Yeniden planlama başarısız.');
  return { ok: true };
}

/* ========= Kullanıcının randevuları ========= */
export async function fetchUserAppointments({ kind = 'upcoming', pageSize = 25 } = {}) {
  const res = await api.get('/api/user/appointments.php');
  if (!res.ok) return { items: [], nextCursor: null };

  const now = new Date();
  let items = (res.data || []).map(r => ({
    id:           r.id,
    businessId:   r.businessId,
    businessName: r.businessName,
    logo:         r.logo,
    startAt:      r.startAt,
    endAt:        r.endAt,
    status:       r.status,
    services:     r.services || [],
    address:      r.address || {},
    // Ek: mevcut kod uyumu
    startMin:     timeToMin(r.startAt?.split('T')[1]?.slice(0,5) || '00:00'),
    endMin:       timeToMin(r.endAt?.split('T')[1]?.slice(0,5) || '00:00'),
    day:          r.startAt?.split('T')[0] || '',
  }));

  if (kind === 'upcoming') {
    items = items.filter(x => new Date(x.startAt) >= now);
    items.sort((a, b) => new Date(a.startAt) - new Date(b.startAt));
  } else {
    items = items.filter(x => new Date(x.startAt) < now);
    items.sort((a, b) => new Date(b.startAt) - new Date(a.startAt));
  }

  return { items: items.slice(0, pageSize), nextCursor: null };
}

/* ========= Polling tabanlı "canlı" dinleme (Firestore onSnapshot yerine) ========= */
export function watchAppointments({ businessId, staffId, dayStr }, cb, intervalMs = 30000) {
  if (!businessId || !staffId || !dayStr) throw new Error('businessId/staffId/dayStr gerekli.');

  let stopped = false;
  const poll = async () => {
    if (stopped) return;
    try {
      const res = await api.get(
        `/api/calendar/appointments.php?start=${dayStr} 00:00:00&end=${dayStr} 23:59:59`
      );
      if (res.ok) cb(res.data?.appointments || []);
    } catch {}
    if (!stopped) setTimeout(poll, intervalMs);
  };
  poll();
  return () => { stopped = true; }; // unsubscribe
}

export function watchUserUpcoming(opts = {}, cb) {
  let stopped = false;
  const poll = async () => {
    if (stopped) return;
    try {
      const { items } = await fetchUserAppointments({ kind: 'upcoming', ...opts });
      cb(items);
    } catch {}
    if (!stopped) setTimeout(poll, 30000);
  };
  poll();
  return () => { stopped = true; };
}

export function watchUserPast(opts = {}, cb) {
  let stopped = false;
  const poll = async () => {
    if (stopped) return;
    try {
      const { items } = await fetchUserAppointments({ kind: 'past', ...opts });
      cb(items);
    } catch {}
    if (!stopped) setTimeout(poll, 60000);
  };
  poll();
  return () => { stopped = true; };
}

/* ========= Debug ========= */
export const AppointmentsAPI = {
  toDayStr, timeToMin, minToTime,
  mergeDayWindow, getBookedRanges, generateSlots,
  bookAppointment, updateAppointmentStatus,
  cancelAppointment, rescheduleAppointment,
  watchAppointments, fetchUserAppointments,
  watchUserUpcoming, watchUserPast,
  ensureWithinWorkingHours, fetchWorkingHours,
};
try { window.__AppointmentsAPI__ = AppointmentsAPI; } catch {}