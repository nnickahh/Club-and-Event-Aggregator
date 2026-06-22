<?php
    session_start();
    require_once 'db_connect.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['student_id'])) {
        $studentID = $_SESSION['student_id'];
        $eventID = $_POST['eventID'];

        $stmt = $conn->prepare("DELETE FROM registrations WHERE studentID = ? AND eventID = ?");
        $stmt->bind_param("si", $studentID, $eventID);

        if ($stmt->execute()) {
            // Auto-promote first person from waiting list
            $waitStmt = $conn->prepare("SELECT * FROM waiting_list WHERE eventID = ? ORDER BY registered_at ASC LIMIT 1");
            $waitStmt->bind_param("i", $eventID);
            $waitStmt->execute();
            $nextInLine = $waitStmt->get_result()->fetch_assoc();
            $waitStmt->close();

            if ($nextInLine) {
                $promoteSid = $nextInLine['studentID'];
                $promotePm = $nextInLine['payment_method'] ?? '';

                $ins = $conn->prepare("INSERT IGNORE INTO registrations (studentID, eventID, payment_method) VALUES (?, ?, ?)");
                $ins->bind_param("sis", $promoteSid, $eventID, $promotePm);
                $ins->execute();
                $ins->close();

                $del = $conn->prepare("DELETE FROM waiting_list WHERE waitID = ?");
                $del->bind_param("i", $nextInLine['waitID']);
                $del->execute();
                $del->close();

                // Notify promoted student
                $eStmt = $conn->prepare("SELECT eventTitle FROM events WHERE eventID = ?");
                $eStmt->bind_param("i", $eventID);
                $eStmt->execute();
                $eRow = $eStmt->get_result()->fetch_assoc();
                $eStmt->close();
                if ($eRow) {
                    $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
                    $msg = "A spot opened up for {$eRow['eventTitle']}! You have been moved from the waiting list to registered.";
                    $nStmt->bind_param("ssi", $promoteSid, $msg, $eventID);
                    $nStmt->execute();
                    $nStmt->close();
                }
            }

            header("Location: DetailedEvent.php?id=" . $eventID . "&cancelled=1");
        } else {
            header("Location: DetailedEvent.php?id=" . $eventID . "&cancelled=0");
        }
        exit();
    }
?>