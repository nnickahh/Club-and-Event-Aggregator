<?php
    function promoteNextWaitlistedStudent(mysqli $conn, int $eventID): bool {
        $eventStmt = $conn->prepare("
            SELECT e.eventTitle, e.capacity, e.adminID,
                   (SELECT COUNT(*) FROM registrations r WHERE r.eventID = e.eventID) AS registeredCount
            FROM events e
            WHERE e.eventID = ?
            LIMIT 1
        ");
        $eventStmt->bind_param("i", $eventID);
        $eventStmt->execute();
        $event = $eventStmt->get_result()->fetch_assoc();
        $eventStmt->close();

        if (!$event || (int)$event['registeredCount'] >= (int)$event['capacity']) {
            return false;
        }

        $waitStmt = $conn->prepare("SELECT * FROM waiting_list WHERE eventID = ? ORDER BY registered_at ASC LIMIT 1");
        $waitStmt->bind_param("i", $eventID);
        $waitStmt->execute();
        $nextInLine = $waitStmt->get_result()->fetch_assoc();
        $waitStmt->close();

        if (!$nextInLine) {
            return false;
        }

        $studentID = $nextInLine['studentID'];
        $paymentMethod = 'cash';
        $paymentStatus = 'unpaid';
        $paymentReceipt = null;

        $insertStmt = $conn->prepare("INSERT IGNORE INTO registrations (studentID, eventID, payment_method, payment_status, payment_receipt) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->bind_param("sisss", $studentID, $eventID, $paymentMethod, $paymentStatus, $paymentReceipt);
        $insertStmt->execute();
        $promoted = $insertStmt->affected_rows > 0;
        $insertStmt->close();

        if (!$promoted) {
            return false;
        }

        $deleteStmt = $conn->prepare("DELETE FROM waiting_list WHERE waitID = ?");
        $deleteStmt->bind_param("i", $nextInLine['waitID']);
        $deleteStmt->execute();
        $deleteStmt->close();

        if (!empty($event['adminID'])) {
            $memberStmt = $conn->prepare("INSERT IGNORE INTO club_members (studentID, adminID) VALUES (?, ?)");
            $memberStmt->bind_param("ss", $studentID, $event['adminID']);
            $memberStmt->execute();
            $memberStmt->close();
        }

        $message = "Good news! A space is available for {$event['eventTitle']}. You have successfully joined the event from the waiting list.";
        $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
        $notifStmt->bind_param("ssi", $studentID, $message, $eventID);
        $notifStmt->execute();
        $notifStmt->close();

        return true;
    }
?>
