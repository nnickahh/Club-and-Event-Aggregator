<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $eventID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$eventID) {
        header("Location: AdminDashboard.php");
        exit();
    }

    // Verify event belongs to this admin
    $stmt = $conn->prepare("SELECT eventID, eventTitle, adminID FROM events WHERE eventID = ? AND adminID = ?");
    $stmt->bind_param("is", $eventID, $adminID);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        header("Location: AdminDashboard.php");
        exit();
    }

    // Notify registered students
    $regStmt = $conn->prepare("SELECT studentID FROM registrations WHERE eventID = ?");
    $regStmt->bind_param("i", $eventID);
    $regStmt->execute();
    $regStudents = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $regStmt->close();

    if (!empty($regStudents)) {
        $msg = $event['eventTitle'] . ' has been cancelled. If you have made any payment, please contact the club.';
        $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
        foreach ($regStudents as $s) {
            $nStmt->bind_param("ssi", $s['studentID'], $msg, $eventID);
            $nStmt->execute();
        }
        $nStmt->close();
    }

    // Notify club_notify subscribers
    $subStmt = $conn->prepare("SELECT studentID FROM club_notify WHERE adminID = ? AND studentID NOT IN (SELECT studentID FROM registrations WHERE eventID = ?)");
    $subStmt->bind_param("si", $adminID, $eventID);
    $subStmt->execute();
    $subStudents = $subStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $subStmt->close();

    if (!empty($subStudents)) {
        $subMsg = $event['eventTitle'] . ' has been cancelled.';
        $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
        foreach ($subStudents as $s) {
            $nStmt->bind_param("ssi", $s['studentID'], $subMsg, $eventID);
            $nStmt->execute();
        }
        $nStmt->close();
    }

    // Notify moderators
    $modMsg = $event['eventTitle'] . ' has been cancelled by the club admin.';
    $modStmt = $conn->prepare("INSERT INTO moderator_notifications (message, eventID) VALUES (?, ?)");
    $modStmt->bind_param("si", $modMsg, $eventID);
    $modStmt->execute();
    $modStmt->close();

    // Cancel the event instead of deleting it
    $stmt = $conn->prepare("UPDATE events SET status = 'cancelled' WHERE eventID = ? AND adminID = ?");
    $stmt->bind_param("is", $eventID, $adminID);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_message'] = 'Event has been cancelled.';
    header("Location: AdminDashboard.php");
    exit();
?>