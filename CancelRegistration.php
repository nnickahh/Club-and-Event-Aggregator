<?php
    // CancelRegistration.php
    session_start();
    require_once 'db_connect.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_SESSION['student_id'])) {
        $studentID = $_SESSION['student_id'];
        $eventID = $_POST['eventID'];

        $stmt = $conn->prepare("DELETE FROM registrations WHERE studentID = ? AND eventID = ?");
        $stmt->bind_param("si", $studentID, $eventID);
        
        if ($stmt->execute()) {
            header("Location: MyEvent.php?msg=cancelled");
        } else {
            header("Location: MyEvent.php?error=fail");
        }
        exit();
    }
?>