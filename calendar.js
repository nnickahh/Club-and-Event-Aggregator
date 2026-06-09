// calendar.js

let currentDate = new Date();
let currentView = 'month';

const monthNames = ["January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December"
];

const dayNamesShort = ["Mon", "Tue", "Wed", "Thu", "Fri", "Sat", "Sun"];
const colorPalette  = ['red', 'green', 'blue', 'amber', 'purple'];

// ─── Fetch Events ──────────────────────────────────────────────────────────
async function fetchEvents() {
    try {
        const response = await fetch('get_events.php');
        return await response.json();
    } catch (error) {
        console.error("Error fetching events:", error);
        return [];
    }
}

// ─── MONTH VIEW ────────────────────────────────────────────────────────────
async function renderMonthView() {
    const month = currentDate.getMonth();
    const year  = currentDate.getFullYear();

    document.getElementById('monthYearDisplay').innerText = `${monthNames[month]} ${year}`;

    const dbEvents = await fetchEvents();
    const calendarGrid = document.getElementById('mainCalendar');

    calendarGrid.className = 'calendar-grid';
    calendarGrid.innerHTML = `
        <div class="day-name">Mon</div>
        <div class="day-name">Tue</div>
        <div class="day-name">Wed</div>
        <div class="day-name">Thu</div>
        <div class="day-name">Fri</div>
        <div class="day-name">Sat</div>
        <div class="day-name">Sun</div>
    `;

    const firstDayOfWeek = new Date(year, month, 1).getDay();
    const emptyBoxesBefore = (firstDayOfWeek === 0) ? 6 : firstDayOfWeek - 1;
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const realToday = new Date();

    for (let i = 0; i < emptyBoxesBefore; i++) {
        calendarGrid.innerHTML += `<div class="calendar-day empty-day"></div>`;
    }

    let colorIndex = 0;
    for (let day = 1; day <= daysInMonth; day++) {
        let highlightClass = "";
        if (day === realToday.getDate() &&
            month === realToday.getMonth() &&
            year === realToday.getFullYear()) {
            highlightClass = " today-highlight";
        }

        const currentDayString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

        let dailyEventsHtml = "";
        dbEvents.forEach(event => {
            if (event.eventDate === currentDayString) {
                const color = colorPalette[colorIndex % colorPalette.length];
                colorIndex++;
                dailyEventsHtml += `<div class="calendar-event-pill" data-color="${color}" title="${event.eventTitle}">${event.eventTitle}</div>`;
            }
        });

        calendarGrid.innerHTML += `
            <div class="calendar-day${highlightClass}">
                <span class="day-number">${day}</span>
                <div class="event-list-container">${dailyEventsHtml}</div>
            </div>`;
    }
}

// ─── WEEK VIEW ─────────────────────────────────────────────────────────────
async function renderWeekView() {
    const dbEvents = await fetchEvents();

    const weekStart = getWeekStart(currentDate);
    const weekEnd   = new Date(weekStart);
    weekEnd.setDate(weekStart.getDate() + 6);

    const fmt = (d) => `${d.getDate()} ${monthNames[d.getMonth()].slice(0, 3)}`;
    document.getElementById('monthYearDisplay').innerText =
        `${fmt(weekStart)} – ${fmt(weekEnd)} ${weekEnd.getFullYear()}`;

    const calendarGrid = document.getElementById('mainCalendar');
    calendarGrid.className = 'calendar-grid week-time-grid';

    const realToday = new Date();
    const todayStr  = toDateString(realToday);

    const days = [];
    for (let i = 0; i < 7; i++) {
        const d = new Date(weekStart);
        d.setDate(weekStart.getDate() + i);
        days.push(d);
    }

    const weekStartStr = toDateString(weekStart);
    const weekEndStr   = toDateString(weekEnd);
    const weekEvents   = dbEvents.filter(e => e.eventDate >= weekStartStr && e.eventDate <= weekEndStr);

    // ── Full 24 hours ──
    const START_HOUR  = 0;   // midnight
    const END_HOUR    = 24;  // midnight next day
    const HOURS       = END_HOUR - START_HOUR; // 24
    const HOUR_HEIGHT = 56;  // px per hour — compact but readable

    // Hour label formatter — 24h style
    function hourLabel(h) {
        return `${String(h).padStart(2, '0')}:00`;
    }

    let html = `
    <div class="wtg-wrapper">
      <div class="wtg-header">
        <div class="wtg-time-gutter-head"></div>
        ${days.map((d, i) => {
            const isToday = toDateString(d) === todayStr;
            // Date shown as D/M e.g. "1/6"
            const dateLabel = `${d.getDate()}/${d.getMonth() + 1}`;
            return `
            <div class="wtg-col-head ${isToday ? 'wtg-today' : ''}">
                <span class="wtg-day-name">${dayNamesShort[i]}</span>
                <span class="wtg-day-num ${isToday ? 'wtg-day-num--today' : ''}">${dateLabel}</span>
            </div>`;
        }).join('')}
      </div>

      <div class="wtg-body" id="wtgBody">
        <div class="wtg-time-gutter">
          ${Array.from({length: HOURS}, (_, i) => {
              const h = START_HOUR + i;
              return `<div class="wtg-hour-label" style="height:${HOUR_HEIGHT}px">${hourLabel(h)}</div>`;
          }).join('')}
        </div>

        ${days.map((d) => {
            const dateStr  = toDateString(d);
            const isToday  = dateStr === todayStr;
            const dayEvents = weekEvents.filter(e => e.eventDate === dateStr);

            const gridLines = Array.from({length: HOURS}, () =>
                `<div class="wtg-hour-row" style="height:${HOUR_HEIGHT}px"></div>`
            ).join('');

            let colorIndex = 0;
            const eventBlocks = dayEvents.map(event => {
                const color = colorPalette[colorIndex % colorPalette.length];
                colorIndex++;

                let topPx    = 0;
                let heightPx = HOUR_HEIGHT;
                if (event.eventTime) {
                    const parts    = event.eventTime.split(':');
                    const h        = parseInt(parts[0], 10);
                    const m        = parseInt(parts[1] || '0', 10);
                    const totalMins = h * 60 + m; // from midnight
                    topPx = (totalMins / 60) * HOUR_HEIGHT;
                }

                const timeLabel = event.eventTime ? formatTime24(event.eventTime) : '';

                return `
                <div class="wtg-event" data-color="${color}" style="top:${topPx}px; height:${heightPx - 4}px;">
                    <div class="wtg-event-inner">
                        <div class="wtg-event-title">${escapeHtml(event.eventTitle)}</div>
                        ${timeLabel ? `<div class="wtg-event-time">${timeLabel}</div>` : ''}
                        ${event.venue ? `<div class="wtg-event-venue">
                            <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            ${escapeHtml(event.venue)}
                        </div>` : ''}
                        ${event.clubName ? `<div class="wtg-event-club">${escapeHtml(event.clubName)}</div>` : ''}
                    </div>
                </div>`;
            }).join('');

            return `
            <div class="wtg-day-col ${isToday ? 'wtg-col-today' : ''}">
                ${gridLines}
                <div class="wtg-events-layer">${eventBlocks}</div>
                ${isToday ? '<div class="wtg-now-line" id="nowLine"></div>' : ''}
            </div>`;
        }).join('')}
      </div>
    </div>`;

    calendarGrid.innerHTML = html;

    positionNowLine();

    // Scroll to current hour (or 8 AM default)
    const body = document.getElementById('wtgBody');
    if (body) {
        const scrollHour = realToday.getHours() > 0 ? realToday.getHours() - 1 : 8;
        body.scrollTop = scrollHour * HOUR_HEIGHT;
    }
}

// ─── Helpers ───────────────────────────────────────────────────────────────
function getWeekStart(date) {
    const d   = new Date(date);
    const day = d.getDay();
    const diff = (day === 0) ? -6 : 1 - day;
    d.setDate(d.getDate() + diff);
    d.setHours(0, 0, 0, 0);
    return d;
}

function toDateString(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
}

// 24-hour format: "14:30"
function formatTime24(timeStr) {
    const parts = timeStr.split(':');
    const h = String(parseInt(parts[0], 10)).padStart(2, '0');
    const m = parts[1] ? parts[1].substring(0, 2) : '00';
    return `${h}:${m}`;
}

function escapeHtml(str) {
    if (!str) return '';
    return str.replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function positionNowLine() {
    const line = document.getElementById('nowLine');
    if (!line) return;
    const now         = new Date();
    const HOUR_HEIGHT = 56;
    const mins        = now.getHours() * 60 + now.getMinutes(); // from midnight
    line.style.top    = `${(mins / 60) * HOUR_HEIGHT}px`;
}

// ─── Render dispatcher ─────────────────────────────────────────────────────
async function renderCalendar() {
    if (currentView === 'week') {
        await renderWeekView();
    } else {
        await renderMonthView();
    }
}

// ─── Navigation ───────────────────────────────────────────────────────────
function changeMonth(offset) {
    if (currentView === 'week') {
        currentDate.setDate(currentDate.getDate() + offset * 7);
    } else {
        currentDate.setMonth(currentDate.getMonth() + offset);
    }
    renderCalendar();
}

// ─── View toggle ──────────────────────────────────────────────────────────
function switchView(viewType) {
    currentView = viewType;
    document.getElementById('btnMonth').classList.toggle('active', viewType === 'month');
    document.getElementById('btnWeek').classList.toggle('active', viewType === 'week');
    renderCalendar();
}

// ─── Boot ─────────────────────────────────────────────────────────────────
renderCalendar();
setInterval(positionNowLine, 60000);