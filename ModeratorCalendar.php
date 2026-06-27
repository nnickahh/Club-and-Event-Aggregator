<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }

    $currentPage = 'calendar';
    session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Calendar</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">
        <h2 class="clubs-title">Event Calendar</h2>

        <div class="calendar-header">
            <div class="month-controls">
                <button onclick="changeMonth(-1)" class="arrow-btn">&lt;</button>
                <span id="monthYearDisplay">Month Year</span>
                <button onclick="changeMonth(1)" class="arrow-btn">&gt;</button>
            </div>

            <div class="calendar-toggle">
                <button id="btnMonth" class="active" onclick="switchView('month')">Month</button>
                <button id="btnWeek" onclick="switchView('week')">Week</button>
            </div>
        </div>

        <div class="calendar-grid" id="mainCalendar"></div>
    </main>

    <script>
        window.CALENDAR_EVENTS_ENDPOINT = 'moderator_get_events.php';
        window.CALENDAR_EVENT_DETAIL_PREFIX = 'EventDetailsModerator.php?id=';
        window.CALENDAR_COLOR_BY_EVENT = true;
    </script>
    <script src="calendar.js"></script>
</body>
</html>
