<?php
    session_start();
    require_once 'db_connect.php';

    function prepareUploadDirectory($relativeDir) {
        $relativeDir = trim($relativeDir, '/');
        $absoluteDir = __DIR__ . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true)) {
            return false;
        }

        return is_writable($absoluteDir) ? $absoluteDir . '/' : false;
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['register']) && isset($_SESSION['student_id'])) {

        $studentID = $_SESSION['student_id'];
        $eventID = (int)$_POST['event_id'];
        $paymentMethod = $_POST['payment_method'] ?? '';
        $paymentStatus = 'unpaid';
        $paymentReceipt = null;

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

        // 2. Check event details and capacity
        $capStmt = $conn->prepare("SELECT e.capacity, e.fee, e.payment_methods, (SELECT COUNT(*) FROM registrations WHERE eventID = ?) AS regCount FROM events e WHERE e.eventID = ?");
        $capStmt->bind_param("ii", $eventID, $eventID);
        $capStmt->execute();
        $capResult = $capStmt->get_result()->fetch_assoc();
        $capStmt->close();

        if (!$capResult) {
            header("Location: StudentDashboard.php?status=error");
            exit();
        }

        $isFull = $capResult && $capResult['regCount'] >= $capResult['capacity'];

        $fee = floatval($capResult['fee'] ?? 0);
        $allowedMethods = array_filter(array_map('trim', explode(',', $capResult['payment_methods'] ?? '')));
        if (!$isFull && $fee > 0 && !empty($allowedMethods) && !in_array($paymentMethod, $allowedMethods, true)) {
            header("Location: DetailedEvent.php?id=" . $eventID . "&payment_error=1");
            exit();
        }

        $receiptUploaded = isset($_FILES['payment_receipt']) && $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_NO_FILE;
        if (!$isFull && $fee > 0 && in_array($paymentMethod, ['tng', 'bank_in'], true) && $receiptUploaded && !isset($_POST['payment_paid'])) {
            header("Location: DetailedEvent.php?id=" . $eventID . "&paid_required=1");
            exit();
        }

        if (!$isFull && $fee > 0 && in_array($paymentMethod, ['tng', 'bank_in'], true) && isset($_POST['payment_paid'])) {
            if (!isset($_FILES['payment_receipt']) || $_FILES['payment_receipt']['error'] !== UPLOAD_ERR_OK) {
                header("Location: DetailedEvent.php?id=" . $eventID . "&receipt_required=1");
                exit();
            }

            $receiptName = time() . '_receipt_' . basename($_FILES['payment_receipt']['name']);
            $receiptDir = prepareUploadDirectory('uploads/receipts');
            $relativeReceiptPath = 'uploads/receipts/' . $receiptName;
            $receiptPath = $receiptDir ? $receiptDir . $receiptName : '';
            $receiptType = strtolower(pathinfo($relativeReceiptPath, PATHINFO_EXTENSION));
            $allowedReceiptTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

            if (!in_array($receiptType, $allowedReceiptTypes, true) || $_FILES['payment_receipt']['size'] > 5 * 1024 * 1024 || !$receiptDir || !move_uploaded_file($_FILES['payment_receipt']['tmp_name'], $receiptPath)) {
                header("Location: DetailedEvent.php?id=" . $eventID . "&receipt_error=1");
                exit();
            }

            $paymentStatus = 'paid';
            $paymentReceipt = $relativeReceiptPath;
        }

        if ($isFull) {
            // Insert into waiting list
            $waitIns = $conn->prepare("INSERT IGNORE INTO waiting_list (studentID, eventID, payment_method, payment_status, payment_receipt) VALUES (?, ?, ?, ?, ?)");
            $waitIns->bind_param("sisss", $studentID, $eventID, $paymentMethod, $paymentStatus, $paymentReceipt);
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
        $insertStmt = $conn->prepare("INSERT INTO registrations (studentID, eventID, payment_method, payment_status, payment_receipt) VALUES (?, ?, ?, ?, ?)");
        $insertStmt->bind_param("sisss", $studentID, $eventID, $paymentMethod, $paymentStatus, $paymentReceipt);

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
