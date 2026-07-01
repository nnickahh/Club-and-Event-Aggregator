<?php
    session_start();
    require_once 'db_connect.php';

    $role = $_SESSION['role'] ?? '';
    $eventID = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
    $redirect = $role === 'moderator' ? 'ModeratorEvents.php?tab=cancelled' : 'AdminDashboard.php';

    if (!$eventID || !in_array($role, ['admin', 'moderator'], true)) {
        header("Location: " . $redirect);
        exit();
    }

    try {
        if ($role === 'admin') {
            $adminID = $_SESSION['admin_id'] ?? '';
            $eventStmt = $conn->prepare("SELECT eventID, eventTitle FROM events WHERE eventID = ? AND adminID = ? AND (status IN ('cancelled','ended') OR (status = 'approved' AND COALESCE(eventEndDate, eventDate) < CURDATE()))");
            $eventStmt->bind_param("is", $eventID, $adminID);
        } else {
            $eventStmt = $conn->prepare("SELECT eventID, eventTitle FROM events WHERE eventID = ? AND (status IN ('cancelled','ended') OR (status = 'approved' AND COALESCE(eventEndDate, eventDate) < CURDATE()))");
            $eventStmt->bind_param("i", $eventID);
        }

        $eventStmt->execute();
        $event = $eventStmt->get_result()->fetch_assoc();
        $eventStmt->close();

        if (!$event) {
            $_SESSION['flash_message'] = 'Event not found or cannot be deleted.';
            header("Location: " . $redirect);
            exit();
        }

        $conn->begin_transaction();

        $tables = ['registrations', 'waiting_list', 'student_notifications', 'notifications', 'moderator_notifications', 'event_feedback', 'event_reminders_sent'];
        foreach ($tables as $table) {
            $stmt = $conn->prepare("DELETE FROM $table WHERE eventID = ?");
            $stmt->bind_param("i", $eventID);
            $stmt->execute();
            $stmt->close();
        }

        $deleteEvent = $conn->prepare("DELETE FROM events WHERE eventID = ?");
        $deleteEvent->bind_param("i", $eventID);
        $deleteEvent->execute();
        $deleted = $deleteEvent->affected_rows > 0;
        $deleteEvent->close();

        $conn->commit();

        $_SESSION['flash_message'] = $deleted ? 'Event record deleted successfully.' : 'Event record could not be deleted.';
    } catch (mysqli_sql_exception $e) {
        error_log('DeleteCancelledEvent DB error: ' . $e->getMessage());
        try { $conn->rollback(); } catch (Throwable $rollbackError) {}
        $_SESSION['flash_message'] = 'Database error while deleting the cancelled event.';
    }

    header("Location: " . $redirect);
    exit();
?>
