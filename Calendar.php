<?php
    session_start();
    require_once 'db_connect.php';

    // Security Check
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Calendar View</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
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

        <div class="calendar-grid" id="mainCalendar">
        </div>
    </main>

    <script src="calendar.js"></script>
</body>
</html>