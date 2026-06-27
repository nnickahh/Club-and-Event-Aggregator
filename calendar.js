// calendar.js
let currentDate = new Date();
let currentView = 'month';
const calendarEventsEndpoint = window.CALENDAR_EVENTS_ENDPOINT || 'get_events.php';
const calendarEventDetailPrefix = window.CALENDAR_EVENT_DETAIL_PREFIX || 'DetailedEvent.php?id=';
const calendarColorByEvent = Boolean(window.CALENDAR_COLOR_BY_EVENT);

const monthNames    = ["January","February","March","April","May","June",
                       "July","August","September","October","November","December"];
const dayNamesShort = ["Mon","Tue","Wed","Thu","Fri","Sat","Sun"];
const colorPalette  = ['red','green','blue','amber','purple','teal','pink','orange','slate'];
function clubColor(clubName) {
    let h = 0;
    const s = (clubName || '').toLowerCase();
    for (let i = 0; i < s.length; i++) h = ((h << 5) - h) + s.charCodeAt(i);
    return colorPalette[Math.abs(h) % colorPalette.length];
}
function calendarEventColor(ev, indexInDay) {
    return calendarColorByEvent ? colorPalette[indexInDay % colorPalette.length] : clubColor(ev.clubName);
}

// ─── Fetch ─────────────────────────────────────────────────────────────────
async function fetchEvents() {
    try { return await (await fetch(calendarEventsEndpoint)).json(); }
    catch(e) { console.error(e); return []; }
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function pad(n) { return String(n).padStart(2,'0'); }
function toDs(d) { return `${d.getFullYear()}-${pad(d.getMonth()+1)}-${pad(d.getDate())}`; }
function esc(s) {
    if (!s) return '';
    return s.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
function eventOnDate(ev, dateStr) {
    if (!ev.eventEndDate || ev.eventEndDate === ev.eventDate) return ev.eventDate === dateStr;
    return dateStr >= ev.eventDate && dateStr <= ev.eventEndDate;
}
function eventStartMinutes(ev) {
    if (!ev.eventTime) return 0;
    const [h, m] = ev.eventTime.split(':').map(Number);
    return (h * 60) + (m || 0);
}
function eventEndMinutes(ev) {
    if (!ev.eventEndTime) return eventStartMinutes(ev) + 60;
    const [h, m] = ev.eventEndTime.split(':').map(Number);
    const end = (h * 60) + (m || 0);
    const start = eventStartMinutes(ev);
    return end > start ? end : start + 60;
}
function eventsOverlap(a, b) {
    return eventStartMinutes(a) < eventEndMinutes(b) && eventStartMinutes(b) < eventEndMinutes(a);
}
// Convert hour integer to 12-hour AM/PM label
function ampm(h) {
    if (h === 0)  return '12 AM';
    if (h === 12) return '12 PM';
    return h < 12 ? `${h} AM` : `${h - 12} PM`;
}
function getWeekStart(date) {
    const d = new Date(date);
    const day = d.getDay();
    d.setDate(d.getDate() + (day === 0 ? -6 : 1 - day));
    d.setHours(0,0,0,0);
    return d;
}
function positionNowLine() {
    const line = document.getElementById('nowLine');
    if (!line) return;
    const now = new Date();
    const HOUR_H = 56, START_H = 0;
    line.style.top = `${((now.getHours() - START_H) + now.getMinutes()/60) * HOUR_H}px`;
}

// ─── MONTH VIEW ────────────────────────────────────────────────────────────
async function renderMonthView() {
    const month = currentDate.getMonth();
    const year  = currentDate.getFullYear();
    document.getElementById('monthYearDisplay').innerText = `${monthNames[month]} ${year}`;

    const events = await fetchEvents();
    const grid   = document.getElementById('mainCalendar');
    grid.className = 'calendar-grid';
    grid.innerHTML = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun']
        .map(d => `<div class="day-name">${d}</div>`).join('');

    const firstDay    = new Date(year, month, 1).getDay();
    const emptyBefore = firstDay === 0 ? 6 : firstDay - 1;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const today       = new Date();

    for (let i = 0; i < emptyBefore; i++)
        grid.innerHTML += `<div class="calendar-day empty-day"></div>`;

    for (let day = 1; day <= daysInMonth; day++) {
        const ds      = `${year}-${pad(month+1)}-${pad(day)}`;
        const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
        let evHtml = '';
        const dayEvents = events.filter(ev => eventOnDate(ev, ds));
        dayEvents.forEach((ev, eventIndex) => {
            if (eventOnDate(ev, ds)) {
                const c = calendarEventColor(ev, eventIndex);
                evHtml += `<a href="${calendarEventDetailPrefix}${ev.eventID}" style="text-decoration:none;color:inherit;display:block;"><div class="calendar-event-pill" data-color="${c}" title="${ev.eventTitle}">${ev.eventTitle}</div></a>`;
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

    const fmt = d => `${d.getDate()} ${monthNames[d.getMonth()].slice(0,3)}`;
    document.getElementById('monthYearDisplay').innerText =
        `${fmt(weekStart)} \u2013 ${fmt(weekEnd)} ${weekEnd.getFullYear()}`;

    const grid     = document.getElementById('mainCalendar');
    grid.className = '';

    const todayStr = toDs(new Date());
    const days     = Array.from({length: 7}, (_, i) => {
        const d = new Date(weekStart);
        d.setDate(weekStart.getDate() + i);
        return d;
    });

    const wStart  = toDs(weekStart);
    const wEnd    = toDs(weekEnd);
    const wEvents = events.filter(e => e.eventDate <= wEnd && (e.eventEndDate || e.eventDate) >= wStart);

    // 12 AM (0) → 11 PM (23) — 24 rows exactly
    const START_H = 0;
    const END_H   = 23;
    const HOURS   = 24;          // rows: 0,1,...,23
    const HOUR_H  = 56;          // px per row
    const TOTAL_H = HOURS * HOUR_H; // 1344px — exact content height, no overflow

    // Time gutter: 24 labels
    const gutterHTML = Array.from({length: HOURS}, (_, i) =>
        `<div class="wtg-hour-label" style="height:${HOUR_H}px">${ampm(i)}</div>`
    ).join('');

    // Column headers
    const headHTML = days.map((d, i) => {
        const isToday = toDs(d) === todayStr;
        return `
        <div class="wtg-col-head${isToday ? ' wtg-today' : ''}">
            <span class="wtg-day-name">${dayNamesShort[i]}</span>
            <span class="wtg-day-num${isToday ? ' wtg-day-num--today' : ''}">${d.getDate()}/${d.getMonth()+1}</span>
        </div>`;
    }).join('');

    // Day columns — exactly HOURS rows, explicit pixel height on wrapper
    const colsHTML = days.map(d => {
        const ds      = toDs(d);
        const isToday = ds === todayStr;
        const dayEvs  = wEvents
            .filter(e => eventOnDate(e, ds))
            .sort((a, b) => eventStartMinutes(a) - eventStartMinutes(b) || String(a.eventTitle || '').localeCompare(String(b.eventTitle || '')));

        // Exactly HOURS rows — no more, no less
        const rowsHTML = Array.from({length: HOURS}, () =>
            `<div class="wtg-hour-row" style="height:${HOUR_H}px"></div>`
        ).join('');

        const evHTML = dayEvs.map((ev, eventIndex) => {
            const col = calendarEventColor(ev, eventIndex);
            const overlapSlot = calendarColorByEvent
                ? Math.min(4, dayEvs.slice(0, eventIndex).filter(other => eventsOverlap(other, ev)).length)
                : 0;
            const sideOffset = 4 + (overlapSlot * 7);
            let topPx = 0;
            let evHeight = HOUR_H - 4;
            if (ev.eventTime) {
                const [h, m] = ev.eventTime.split(':').map(Number);
                topPx = Math.max(0, (h + m/60) * HOUR_H);
                if (ev.eventEndTime) {
                    const [eh, em] = ev.eventEndTime.split(':').map(Number);
                    const endDec = eh + em/60;
                    const startDec = h + m/60;
                    evHeight = Math.max(HOUR_H - 4, (endDec - startDec) * HOUR_H);
                }
            }
            const startLabel = ev.eventTime ? ampm(parseInt(ev.eventTime)) : '';
            const endLabel = ev.eventEndTime ? ampm(parseInt(ev.eventEndTime)) : '';
            const tLabel = startLabel + (endLabel ? ' — ' + endLabel : '');
            return `
            <div class="wtg-event" data-color="${col}" style="top:${topPx}px; height:${evHeight}px; left:${sideOffset}px; right:4px; z-index:${2 + eventIndex}; cursor:pointer;" onclick="window.location.href='${calendarEventDetailPrefix}${ev.eventID}'">
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
        <div class="wtg-day-col${isToday ? ' wtg-col-today' : ''}" style="height:${TOTAL_H}px">
            ${rowsHTML}
            <div class="wtg-events-layer">${evHTML}</div>
            ${isToday ? '<div class="wtg-now-line" id="nowLine"></div>' : ''}
        </div>`;
    }).join('');

    grid.innerHTML = `
    <div class="wtg-wrapper">
        <div class="wtg-header">
            <div class="wtg-time-gutter-head"></div>
            ${headHTML}
        </div>
        <div class="wtg-body" id="wtgBody" style="max-height:640px; height:${TOTAL_H}px">
            <div class="wtg-time-gutter" style="height:${TOTAL_H}px">${gutterHTML}</div>
            ${colsHTML}
        </div>
    </div>`;

    positionNowLine();

    // Scroll to current hour
    const body = document.getElementById('wtgBody');
    if (body) {
        const h = new Date().getHours();
        body.scrollTop = Math.max(0, h - 1) * HOUR_H;
    }
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
