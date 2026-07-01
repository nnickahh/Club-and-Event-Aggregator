<?php
    session_start();
    require_once 'db_connect.php';

    $type = $_GET['type'] ?? '';
    $id = (int)($_GET['id'] ?? 0);
    $success = false;

    if ($id > 0 && $type) {
        if ($type === 'student' && isset($_SESSION['student_id'])) {
            $stmt = $conn->prepare("DELETE FROM student_notifications WHERE id = ? AND studentID = ?");
            $stmt->bind_param("is", $id, $_SESSION['student_id']);
            $success = $stmt->execute();
            $stmt->close();
        } elseif ($type === 'admin' && isset($_SESSION['admin_id'])) {
            $stmt = $conn->prepare("DELETE FROM notifications WHERE id = ? AND adminID = ?");
            $stmt->bind_param("is", $id, $_SESSION['admin_id']);
            $success = $stmt->execute();
            $stmt->close();
        } elseif ($type === 'moderator' && isset($_SESSION['role']) && $_SESSION['role'] === 'moderator') {
            $stmt = $conn->prepare("DELETE FROM moderator_notifications WHERE id = ?");
            $stmt->bind_param("i", $id);
            $success = $stmt->execute();
            $stmt->close();
        }
    }

    header('Content-Type: application/json');
    echo json_encode(['success' => $success]);
    exit();
