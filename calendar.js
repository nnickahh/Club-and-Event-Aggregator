// calendar.js

// 1. Setup our starting date (Defaults to current computer date)
let currentDate = new Date();

// Array of month names for our display
const monthNames = ["January", "February", "March", "April", "May", "June",
  "July", "August", "September", "October", "November", "December"
];

// 2. The main function to build the calendar
async function renderCalendar() {
    const month = currentDate.getMonth();
    const year = currentDate.getFullYear();

    // Fetch the event data from our PHP helper
    // This connects the frontend to your SQL 'events' table
    let dbEvents = [];
    try {
        const response = await fetch('get_events.php');
        dbEvents = await response.json();
    } catch (error) {
        console.error("Error fetching events:", error);
    }

    // Update the text in the middle of the arrows
    document.getElementById('monthYearDisplay').innerText = `${monthNames[month]} ${year}`;

    const calendarGrid = document.getElementById('mainCalendar');

    // Headers
    const dayHeaders = `
        <div class="day-name">Mon</div>
        <div class="day-name">Tue</div>
        <div class="day-name">Wed</div>
        <div class="day-name">Thu</div>
        <div class="day-name">Fri</div>
        <div class="day-name">Sat</div>
        <div class="day-name">Sun</div>
    `;
    calendarGrid.innerHTML = dayHeaders;

    let firstDayOfWeek = new Date(year, month, 1).getDay();
    let emptyBoxesBefore = (firstDayOfWeek === 0) ? 6 : firstDayOfWeek - 1;
    let daysInMonth = new Date(year, month + 1, 0).getDate();

    // Get the exact real-world date right now
    const realToday = new Date();

    // Add empty grey boxes
    for (let i = 0; i < emptyBoxesBefore; i++) {
        calendarGrid.innerHTML += `<div class="calendar-day empty-day"></div>`;
    }

    // Add the numbered days
    for (let day = 1; day <= daysInMonth; day++) {
        
        // Check if this box is today
        let highlightClass = "";
        if (day === realToday.getDate() && 
            month === realToday.getMonth() && 
            year === realToday.getFullYear()) {
            
            highlightClass = " today-highlight";
        }

        // Create a date string to match your SQL format (YYYY-MM-DD)
        const currentDayString = `${year}-${String(month + 1).padStart(2, '0')}-${String(day).padStart(2, '0')}`;

        // Filter events that happen on this specific day
        let dailyEventsHtml = "";
        dbEvents.forEach(event => {
            if (event.eventDate === currentDayString) {
                // We use a small dot or text to show the event exists
                dailyEventsHtml += `<div class="calendar-event-pill" title="${event.eventTitle}">${event.eventTitle}</div>`;
            }
        });
        
        calendarGrid.innerHTML += `
            <div class="calendar-day${highlightClass}">
                <span class="day-number">${day}</span>
                <div class="event-list-container">
                    ${dailyEventsHtml}
                </div>
            </div>`;
    }
}

// 3. The function attached to your < and > buttons
function changeMonth(offset) {
    currentDate.setMonth(currentDate.getMonth() + offset);
    renderCalendar(); // Re-draw the grid with new data
}

// 4. The Week/Month toggle
function switchView(viewType) {
    const calendar = document.getElementById('mainCalendar');
    const btnMonth = document.getElementById('btnMonth');
    const btnWeek = document.getElementById('btnWeek');

    if (viewType === 'week') {
        calendar.classList.add('week-mode');
        btnWeek.classList.add('active');
        btnMonth.classList.remove('active');
    } else {
        calendar.classList.remove('week-mode');
        btnMonth.classList.add('active');
        btnWeek.classList.remove('active');
    }
}

// 5. Initial Load
renderCalendar();