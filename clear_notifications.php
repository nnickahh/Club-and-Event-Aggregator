<?php
    session_start();
    require_once 'db_connect.php';

    if (isset($_SESSION['student_id'])) {
        $sid = $_SESSION['student_id'];
        $upd = $conn->prepare("DELETE FROM student_notifications WHERE studentID = ?");
        $upd->bind_param("s", $sid);
        $upd->execute();
        $upd->close();
    } elseif (isset($_SESSION['admin_id'])) {
        $aid = $_SESSION['admin_id'];
        $upd = $conn->prepare("DELETE FROM notifications WHERE adminID = ?");
        $upd->bind_param("s", $aid);
        $upd->execute();
        $upd->close();
    } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'moderator') {
        $upd = $conn->prepare("DELETE FROM moderator_notifications");
        $upd->execute();
        $upd->close();
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'StudentDashboard.php';
    header("Location: " . $redirect);
    exit();
?>
