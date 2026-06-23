<?php
    session_start();
    require_once 'db_connect.php';

    if (isset($_SESSION['student_id'])) {
        $sid = $_SESSION['student_id'];
        $upd = $conn->prepare("UPDATE student_notifications SET is_read = 1 WHERE studentID = ? AND is_read = 0");
        $upd->bind_param("s", $sid);
        $upd->execute();
        $upd->close();
    } elseif (isset($_SESSION['admin_id'])) {
        $aid = $_SESSION['admin_id'];
        $upd = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE adminID = ? AND is_read = 0");
        $upd->bind_param("s", $aid);
        $upd->execute();
        $upd->close();
    }

    $redirect = $_SERVER['HTTP_REFERER'] ?? 'StudentDashboard.php';
    header("Location: " . $redirect);
    exit();
?>