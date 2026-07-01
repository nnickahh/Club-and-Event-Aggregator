<?php
    session_start();
    require_once 'db_connect.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['student_id'])) {
        $studentID = $_SESSION['student_id'];
        $eventID = isset($_POST['eventID']) ? (int)$_POST['eventID'] : 0;

        if ($eventID) {
            $stmt = $conn->prepare("DELETE FROM waiting_list WHERE studentID = ? AND eventID = ?");
            $stmt->bind_param("si", $studentID, $eventID);
            $stmt->execute();
            $stmt->close();
        }
        header("Location: DetailedEvent.php?id=" . $eventID);
        exit();
    }
    header("Location: StudentDashboard.php");
    exit();
?>