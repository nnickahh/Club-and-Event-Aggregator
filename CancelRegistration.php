<?php
    session_start();
    require_once 'db_connect.php';
    require_once 'waitlist_helpers.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['student_id'])) {
        $studentID = $_SESSION['student_id'];
        $eventID = $_POST['eventID'];

        $stmt = $conn->prepare("DELETE FROM registrations WHERE studentID = ? AND eventID = ?");
        $stmt->bind_param("si", $studentID, $eventID);

        if ($stmt->execute()) {
            $removed = $stmt->affected_rows > 0;
            $stmt->close();
            if ($removed) {
                promoteNextWaitlistedStudent($conn, (int)$eventID);
            }
            header("Location: DetailedEvent.php?id=" . $eventID . "&cancelled=1");
        } else {
            $stmt->close();
            header("Location: DetailedEvent.php?id=" . $eventID . "&cancelled=0");
        }
        exit();
    }
?>
