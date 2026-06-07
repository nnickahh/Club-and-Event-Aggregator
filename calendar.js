// calendar.js
let currentDate = new Date();
let currentView = 'month';

const monthNames   = ["January","February","March","April","May","June",
                      "July","August","September","October","November","December"];
const dayNamesShort = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
const colorPalette  = ['red','green','blue','amber','purple'];

// ─── Fetch ─────────────────────────────────────────────────────────────────
async function fetchEvents() {
    try { return await (await fetch('get_events.php')).json(); }
    catch(e) { console.error(e); return []; }
}

// ─── MONTH VIEW ────────────────────────────────────────────────────────────
async function renderMonthView() {
    const month = currentDate.getMonth();
    const year  = currentDate.getFullYear();
    document.getElementById('monthYearDisplay').innerText = `${monthNames[month]} ${year}`;

    const events = await fetchEvents();
    const grid = document.getElementById('mainCalendar');
    grid.className = 'calendar-grid';
    grid.innerHTML = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']
        .map(d => `<div class="day-name">${d}</div>`).join('');

    const firstDay    = new Date(year, month, 1).getDay();
    const emptyBefore = firstDay === 0 ? 6 : firstDay - 1;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today       = new Date();

    for (let i = 0; i < emptyBefore; i++)
        grid.innerHTML += `<div class="calendar-day empty-day"></div>`;

    let ci = 0;
    for (let day = 1; day <= daysInMonth; day++) {
        const ds      = `${year}-${pad(month+1)}-${pad(day)}`;
        const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
        let evHtml = '';
        events.forEach(ev => {
            if (ev.eventDate === ds) {
                const c = colorPalette[ci++ % colorPalette.length];
                evHtml += `<div class="calendar-event-pill" data-color="${c}" title="${ev.eventTitle}">${ev.eventTitle}</div>`;
            }
        });
        grid.innerHTML += `
            <div class="calendar-day${isToday ? ' today-highlight' : ''}">
                <span class="day-number">${day}</span>
                <div class="event-list-container">${evHtml}</div>
            </div>`;
    }
}

// ─── WEEK VIEW ─────────────────────────────────────────────────────────────
async function renderWeekView() {
    const events    = await fetchEvents();
    const weekStart = getWeekStart(currentDate);
    const weekEnd   = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);

    // Header date range
    const fmt = d => `${d.getDate()} ${monthNames[d.getMonth()].slice(0,3)}`;
    document.getElementById('monthYearDisplay').innerText =
        `${fmt(weekStart)} – ${fmt(weekEnd)} ${weekEnd.getFullYear()}`;

    const grid     = document.getElementById('mainCalendar');
    grid.className = 'calendar-grid week-time-grid';

    const todayStr = toDs(new Date());
    const days     = Array.from({length:7}, (_, i) => {
        const d = new Date(weekStart);
        d.setDate(weekStart.getDate() + i);
        return d;
    });

    const wStart  = toDs(weekStart);
    const wEnd    = toDs(weekEnd);
    const wEvents = events.filter(e => e.eventDate >= wStart && e.eventDate <= wEnd);

    // ── Hours: 12 AM (0) → 11 PM (23) — full day, 24 rows ──
    const START_H  = 0;   // 12 AM
    const END_H    = 23;  // 11 PM
    const HOURS    = END_H - START_H + 1; // 24
    const HOUR_H   = 52;  // px per row — compact

    function ampm(h) {
        if (h === 0)  return '12 AM';
        if (h === 12) return '12 PM';
        return h < 12 ? `${h} AM` : `${h - 12} PM`;
    }

    // Gutter — 24 labels
    const gutterHTML = Array.from({length: HOURS}, (_, i) =>
        `<div class="wtg-hour-label" style="height:${HOUR_H}px">${ampm(START_H + i)}</div>`
    ).join('');

    // Header columns
    const headHTML = days.map((d, i) => {
        const isToday = toDs(d) === todayStr;
        return `
        <div class="wtg-col-head${isToday ? ' wtg-today' : ''}">
            <span class="wtg-day-name">${dayNamesShort[i]}</span>
            <span class="wtg-day-num${isToday ? ' wtg-day-num--today' : ''}">${d.getDate()}/${d.getMonth()+1}</span>
        </div>`;
    }).join('');

    // Day columns
    const colsHTML = days.map(d => {
        const ds      = toDs(d);
        const isToday = ds === todayStr;
        const dayEvs  = wEvents.filter(e => e.eventDate === ds);

        const rowsHTML = Array.from({length: HOURS}, () =>
            `<div class="wtg-hour-row" style="height:${HOUR_H}px"></div>`
        ).join('');

        let ci = 0;
        const evHTML = dayEvs.map(ev => {
            const col  = colorPalette[ci++ % colorPalette.length];
            let topPx  = 0;
            if (ev.eventTime) {
                const [h, m] = ev.eventTime.split(':').map(Number);
                topPx = ((h - START_H) + m / 60) * HOUR_H;
                topPx = Math.max(0, topPx);
            }
            const tLabel = ev.eventTime ? ampm(parseInt(ev.eventTime)) : '';
            return `
            <div class="wtg-event" data-color="${col}" style="top:${topPx}px;height:${HOUR_H - 4}px;">
                <div class="wtg-event-inner">
                    <div class="wtg-event-title">${esc(ev.eventTitle)}</div>
                    ${tLabel ? `<div class="wtg-event-time">${tLabel}</div>` : ''}
                    ${ev.venue ? `<div class="wtg-event-venue">
                        <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        ${esc(ev.venue)}</div>` : ''}
                    ${ev.clubName ? `<div class="wtg-event-club">${esc(ev.clubName)}</div>` : ''}
                </div>
            </div>`;
        }).join('');

        return `
        <div class="wtg-day-col${isToday ? ' wtg-col-today' : ''}">
            ${rowsHTML}
            <div class="wtg-events-layer">${evHTML}</div>
            ${isToday ? '<div class="wtg-now-line" id="nowLine"></div>' : ''}
        </div>`;
    }).join('');

    // Assemble — header gutter div MUST match the CSS --wtg-gutter column
    grid.innerHTML = `
    <div class="wtg-wrapper">
        <div class="wtg-header">
            <div class="wtg-time-gutter-head"></div>
            ${headHTML}
        </div>
        <div class="wtg-body" id="wtgBody">
            <div class="wtg-time-gutter">${gutterHTML}</div>
            ${colsHTML}
        </div>
    </div>`;

    positionNowLine();

    // Scroll to current hour (or 8 AM if overnight)
    const body = document.getElementById('wtgBody');
    if (body) {
        const h = new Date().getHours();
        body.scrollTop = Math.max(0, (h - 1)) * HOUR_H;
    }
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function getWeekStart(date) {
    const d   = new Date(date);
    const day = d.getDay();
    d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
    d.setHours(0,0,0,0);
    return d;
}
function toDs(d) {
    return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`;
}
function pad(n) { return String(n).padStart(2,'0'); }
function esc(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function positionNowLine() {
    const line = document.getElementById('nowLine');
    if (!line) return;
    const now   = new Date();
    const HOUR_H = 52;
    const mins  = now.getHours() * 60 + now.getMinutes(); // from midnight (START_H=0)
    line.style.top = `${(mins / 60) * HOUR_H}px`;
}

// ─── Dispatcher ────────────────────────────────────────────────────────────
async function renderCalendar() {
    currentView === 'week' ? await renderWeekView() : await renderMonthView();
}
function changeMonth(offset) {
    if (currentView === 'week') currentDate.setDate(currentDate.getDate() + offset * 7);
    else currentDate.setMonth(currentDate.getMonth() + offset);
    renderCalendar();
}
function switchView(v) {
    currentView = v;
    document.getElementById('btnMonth').classList.toggle('active', v === 'month');
    document.getElementById('btnWeek').classList.toggle('active', v === 'week');
    renderCalendar();
}

renderCalendar();
setInterval(positionNowLine, 60000);