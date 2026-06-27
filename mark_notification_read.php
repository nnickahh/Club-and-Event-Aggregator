<?php
    session_start();
    require_once 'db_connect.php';

    $type = $_GET['type'] ?? '';
    $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $redirect = $_GET['redirect'] ?? '';

    if ($id > 0) {
        if ($type === 'student' && isset($_SESSION['student_id'])) {
            $studentID = $_SESSION['student_id'];
            $stmt = $conn->prepare("UPDATE student_notifications SET is_read = 1 WHERE id = ? AND studentID = ? AND is_read = 0");
            $stmt->bind_param("is", $id, $studentID);
            $stmt->execute();
            $stmt->close();
        } elseif ($type === 'admin' && isset($_SESSION['admin_id'])) {
            $adminID = $_SESSION['admin_id'];
            $stmt = $conn->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND adminID = ? AND is_read = 0");
            $stmt->bind_param("is", $id, $adminID);
            $stmt->execute();
            $stmt->close();
        } elseif ($type === 'moderator' && isset($_SESSION['role']) && $_SESSION['role'] === 'moderator') {
            $stmt = $conn->prepare("UPDATE moderator_notifications SET is_read = 1 WHERE id = ? AND is_read = 0");
            $stmt->bind_param("i", $id);
            $stmt->execute();
            $stmt->close();
        }
    }

    if ($redirect === '' || preg_match('/^https?:\/\//i', $redirect)) {
        if (isset($_SESSION['role']) && $_SESSION['role'] === 'admin') {
            $redirect = 'AdminDashboard.php';
        } elseif (isset($_SESSION['role']) && $_SESSION['role'] === 'moderator') {
            $redirect = 'ModeratorDashboard.php';
        } else {
            $redirect = 'StudentDashboard.php';
        }
    }

    header("Location: " . $redirect);
    exit();
?>
