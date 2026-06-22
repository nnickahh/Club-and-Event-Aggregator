<?php
    session_start();
    require_once 'db_connect.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register']) && isset($_SESSION['student_id'])) {

        $studentID = $_SESSION['student_id'];
        $eventID = $_POST['event_id'];
        $paymentMethod = $_POST['payment_method'] ?? '';

        // 1. Check if the student is already registered
        $checkStmt = $conn->prepare("SELECT * FROM registrations WHERE studentID = ? AND eventID = ?");
        $checkStmt->bind_param("si", $studentID, $eventID);
        $checkStmt->execute();
        $result = $checkStmt->get_result();

        if ($result->num_rows > 0) {
            header("Location: MyEvent.php?status=already_registered");
            exit();
        }

        // Check if on waiting list already
        $waitCheck = $conn->prepare("SELECT * FROM waiting_list WHERE studentID = ? AND eventID = ?");
        $waitCheck->bind_param("si", $studentID, $eventID);
        $waitCheck->execute();
        if ($waitCheck->get_result()->num_rows > 0) {
            header("Location: DetailedEvent.php?id=" . $eventID . "&on_waitlist=1");
            exit();
        }
        $waitCheck->close();

        // 2. Check capacity
        $capStmt = $conn->prepare("SELECT e.capacity, (SELECT COUNT(*) FROM registrations WHERE eventID = ?) AS regCount FROM events e WHERE e.eventID = ?");
        $capStmt->bind_param("ii", $eventID, $eventID);
        $capStmt->execute();
        $capResult = $capStmt->get_result()->fetch_assoc();
        $capStmt->close();

        $isFull = $capResult && $capResult['regCount'] >= $capResult['capacity'];

        if ($isFull) {
            // Insert into waiting list
            $waitIns = $conn->prepare("INSERT IGNORE INTO waiting_list (studentID, eventID, payment_method) VALUES (?, ?, ?)");
            $waitIns->bind_param("sis", $studentID, $eventID, $paymentMethod);
            $waitIns->execute();
            $waitIns->close();

            // Notify admin
            $evStmt = $conn->prepare("SELECT adminID, eventTitle FROM events WHERE eventID = ?");
            $evStmt->bind_param("i", $eventID);
            $evStmt->execute();
            $evRow = $evStmt->get_result()->fetch_assoc();
            $evStmt->close();

            if ($evRow) {
                $nameStmt = $conn->prepare("SELECT name FROM students WHERE studentID = ?");
                $nameStmt->bind_param("s", $studentID);
                $nameStmt->execute();
                $studentName = ($nameRow = $nameStmt->get_result()->fetch_assoc()) ? $nameRow['name'] : $studentID;
                $nameStmt->close();

                $notifStmt = $conn->prepare("INSERT INTO notifications (adminID, message, eventID) VALUES (?, ?, ?)");
                $notifMsg = "$studentName joined the waiting list for {$evRow['eventTitle']}";
                $notifStmt->bind_param("ssi", $evRow['adminID'], $notifMsg, $eventID);
                $notifStmt->execute();
                $notifStmt->close();
            }

            header("Location: DetailedEvent.php?id=" . $eventID . "&waitlisted=1");
            exit();
        }

        // 3. Insert new registration
        $insertStmt = $conn->prepare("INSERT INTO registrations (studentID, eventID, payment_method) VALUES (?, ?, ?)");
        $insertStmt->bind_param("sis", $studentID, $eventID, $paymentMethod);

        if ($insertStmt->execute()) {
            $evStmt = $conn->prepare("SELECT adminID, eventTitle FROM events WHERE eventID = ?");
            $evStmt->bind_param("i", $eventID);
            $evStmt->execute();
            $evResult = $evStmt->get_result();
            if ($evRow = $evResult->fetch_assoc()) {
                $adminID = $evRow['adminID'];
                $eventTitle = $evRow['eventTitle'];
                $nameStmt = $conn->prepare("SELECT name FROM students WHERE studentID = ?");
                $nameStmt->bind_param("s", $studentID);
                $nameStmt->execute();
                $nameResult = $nameStmt->get_result();
                $studentName = ($nameRow = $nameResult->fetch_assoc()) ? $nameRow['name'] : $studentID;
                $nameStmt->close();
                $notifStmt = $conn->prepare("INSERT INTO notifications (adminID, message, eventID) VALUES (?, ?, ?)");
                $notifMsg = "$studentName registered for $eventTitle";
                $notifStmt->bind_param("ssi", $adminID, $notifMsg, $eventID);
                $notifStmt->execute();
                $notifStmt->close();
                $memStmt = $conn->prepare("INSERT IGNORE INTO club_members (studentID, adminID) VALUES (?, ?)");
                $memStmt->bind_param("ss", $studentID, $adminID);
                $memStmt->execute();
                $memStmt->close();
            }
            $evStmt->close();
            header("Location: DetailedEvent.php?id=" . $eventID . "&registered=1");
        } else {
            header("Location: StudentDashboard.php?status=error");
        }
        exit();
    } else {
        header("Location: StudentDashboard.php");
        exit();
    }
?>