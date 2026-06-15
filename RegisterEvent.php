<?php
    session_start();
    require_once 'db_connect.php';

    // Check if the student is logged in and the form was submitted
    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register']) && isset($_SESSION['student_id'])) {
        
        $studentID = $_SESSION['student_id'];
        $eventID = $_POST['event_id'];

        // 1. Check if the student is already registered for this event
        $checkStmt = $conn->prepare("SELECT * FROM registrations WHERE studentID = ? AND eventID = ?");
        $checkStmt->bind_param("si", $studentID, $eventID);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            // Already registered, send back to schedule with a message
            header("Location: MyEvent.php?status=already_registered");
            exit();
        } else {
            // 2. Insert new registration into the database
            $insertStmt = $conn->prepare("INSERT INTO registrations (studentID, eventID) VALUES (?, ?)");
            $insertStmt->bind_param("si", $studentID, $eventID);
            
                if ($insertStmt->execute()) {
                // Add student to club_members
                $evStmt = $conn->prepare("SELECT adminID FROM events WHERE eventID = ?");
                $evStmt->bind_param("i", $eventID);
                $evStmt->execute();
                $evResult = $evStmt->get_result();
                if ($evRow = $evResult->fetch_assoc()) {
                    $adminID = $evRow['adminID'];
                    $memStmt = $conn->prepare("INSERT IGNORE INTO club_members (studentID, adminID) VALUES (?, ?)");
                    $memStmt->bind_param("ss", $studentID, $adminID);
                    $memStmt->execute();
                    $memStmt->close();
                    // Notify admin
                    $nameStmt = $conn->prepare("SELECT name FROM students WHERE studentID = ?");
                    $nameStmt->bind_param("s", $studentID);
                    $nameStmt->execute();
                    $nameResult = $nameStmt->get_result();
                    $studentName = ($nameRow = $nameResult->fetch_assoc()) ? $nameRow['name'] : $studentID;
                    $nameStmt->close();
                    $notifMsg = "$studentName registered for an event and joined your club";
                    $notifStmt = $conn->prepare("INSERT INTO notifications (adminID, message) VALUES (?, ?)");
                    $notifStmt->bind_param("ss", $adminID, $notifMsg);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
                $evStmt->close();
                // Success: Redirect to their personal schedule
                header("Location: MyEvent.php?status=success");
            } else {
                // Error: Redirect with error message
                header("Location: StudentDashboard.php?status=error");
            }
            exit();
        }
    } else {
        // If someone tries to access this file directly, send them back
        header("Location: StudentDashboard.php");
        exit();
    }
?>