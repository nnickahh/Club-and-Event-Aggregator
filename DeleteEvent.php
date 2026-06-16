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
    $stmt = $conn->prepare("SELECT eventID FROM events WHERE eventID = ? AND adminID = ?");
    $stmt->bind_param("is", $eventID, $adminID);
    $stmt->execute();
    $result = $stmt->get_result();
    $stmt->close();

    if ($result->num_rows === 0) {
        header("Location: AdminDashboard.php");
        exit();
    }

    // Delete related registrations first
    $conn->query("DELETE FROM registrations WHERE eventID = $eventID");

    // Delete the event
    $stmt = $conn->prepare("DELETE FROM events WHERE eventID = ? AND adminID = ?");
    $stmt->bind_param("is", $eventID, $adminID);
    $stmt->execute();
    $stmt->close();

    $_SESSION['flash_message'] = 'Event has been deleted successfully.';
    header("Location: AdminDashboard.php");
    exit();
