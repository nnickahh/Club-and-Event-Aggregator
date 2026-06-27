<?php
    session_start();
    require_once 'db_connect.php';
    require_once 'waitlist_helpers.php';

    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $eventID = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    $editMessage = '';

    function prepareUploadDirectory($relativeDir) {
        $relativeDir = trim($relativeDir, '/');
        $absoluteDir = __DIR__ . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true)) {
            return false;
        }

        return is_writable($absoluteDir) ? $absoluteDir . '/' : false;
    }

    function collectEventPoster($currentImage) {
        if (!isset($_FILES['eventImage']) || $_FILES['eventImage']['error'] === UPLOAD_ERR_NO_FILE) {
            return [$currentImage, null];
        }

        if ($_FILES['eventImage']['error'] !== UPLOAD_ERR_OK) {
            return [$currentImage, 'Failed to upload poster. Please try again.'];
        }

        $fileName = time() . '_' . basename($_FILES['eventImage']['name']);
        $uploadDir = prepareUploadDirectory('uploads/events');
        $relativePath = 'uploads/events/' . $fileName;
        $targetPath = $uploadDir ? $uploadDir . $fileName : '';
        $imageFileType = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

        if (!in_array($imageFileType, $allowedTypes, true)) {
            return [$currentImage, 'Only JPG, JPEG, PNG, GIF & WEBP files are allowed.'];
        }
        if ($_FILES['eventImage']['size'] > 5 * 1024 * 1024) {
            return [$currentImage, 'Poster file size must be less than 5MB.'];
        }
        if (!$uploadDir) {
            return [$currentImage, 'Upload folder is not writable. Please check the uploads/events folder permission.'];
        }
        if (!move_uploaded_file($_FILES['eventImage']['tmp_name'], $targetPath)) {
            return [$currentImage, 'Failed to upload poster.'];
        }

        return [$relativePath, null];
    }

    function collectPaymentSettings($event) {
        $paymentMethods = !empty($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : null;
        $tngPhone = null;
        $tngQr = null;
        $bankDetails = null;

        if ($paymentMethods && strpos($paymentMethods, 'tng') !== false) {
            $tngPhone = !empty(trim($_POST['tng_phone'] ?? '')) ? trim($_POST['tng_phone']) : null;
            $tngQr = $event['tng_qr'] ?? null;
            if ($tngPhone === null) {
                return [null, null, null, null, 'TNG phone number is required.'];
            }
            if (!ctype_digit($tngPhone)) {
                return [null, null, null, null, 'Only numbers allowed.'];
            }

            if (isset($_FILES['tng_qr']) && $_FILES['tng_qr']['error'] === UPLOAD_ERR_OK) {
                $qrName = time() . '_qr_' . basename($_FILES['tng_qr']['name']);
                $qrDir = prepareUploadDirectory('uploads/payments');
                $relativeQrPath = 'uploads/payments/' . $qrName;
                $qrPath = $qrDir ? $qrDir . $qrName : '';
                $qrType = strtolower(pathinfo($relativeQrPath, PATHINFO_EXTENSION));

                if ($qrDir && in_array($qrType, ['jpg','jpeg','png','gif','webp'], true) && move_uploaded_file($_FILES['tng_qr']['tmp_name'], $qrPath)) {
                    $tngQr = $relativeQrPath;
                }
            }
        }

        if ($paymentMethods && strpos($paymentMethods, 'bank_in') !== false) {
            $bankData = [];
            if (empty(trim($_POST['bank_name'] ?? ''))) {
                return [null, null, null, null, 'Bank name is required.'];
            }
            $bankData['bank_name'] = trim($_POST['bank_name']);
            if (empty(trim($_POST['bank_account'] ?? ''))) {
                return [null, null, null, null, 'Bank account number is required.'];
            } else {
                $bankAccount = trim($_POST['bank_account']);
                if (!ctype_digit($bankAccount)) {
                    return [null, null, null, null, 'Only numbers allowed.'];
                }
                $bankData['bank_account'] = $bankAccount;
            }
            if (empty(trim($_POST['bank_holder'] ?? ''))) {
                return [null, null, null, null, 'Account holder name is required.'];
            }
            $bankData['bank_holder'] = trim($_POST['bank_holder']);
            if (!empty($bankData)) $bankDetails = json_encode($bankData);
        }

        return [$paymentMethods, $tngPhone, $tngQr, $bankDetails, null];
    }

    // Handle AJAX payment toggle (before rendering)
    if (isset($_POST['ajax_toggle_payment']) && $eventID) {
        $sid = $_POST['student_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? '';
        if ($sid && in_array($newStatus, ['unpaid','paid'])) {
            $upd = $conn->prepare("UPDATE registrations SET payment_status = ? WHERE eventID = ? AND studentID = ?");
            $upd->bind_param("sis", $newStatus, $eventID, $sid);
            $upd->execute();
            $upd->close();
        }
        exit();
    }

    // Handle AJAX attendance toggle
    if (isset($_POST['ajax_toggle_attendance']) && $eventID) {
        $sid = $_POST['student_id'] ?? '';
        $newStatus = $_POST['new_status'] ?? '';
        if ($sid && in_array($newStatus, ['absent','present'])) {
            $upd = $conn->prepare("UPDATE registrations SET attendance_status = ? WHERE eventID = ? AND studentID = ?");
            $upd->bind_param("sis", $newStatus, $eventID, $sid);
            $upd->execute();
            $upd->close();
        }
        exit();
    }

    // Handle add participant
    if (isset($_POST['add_participant']) && $eventID) {
        $newSid = trim($_POST['new_student_id'] ?? '');
        $newName = trim($_POST['new_name'] ?? '');
        $newEmail = trim($_POST['new_email'] ?? '');
        $paymentMethod = $_POST['payment_method'] ?? '';
        $paymentStatus = $_POST['payment_status'] ?? 'unpaid';
        if ($newSid && $newName && $newEmail) {
            // Check student exists
            $check = $conn->prepare("SELECT studentID FROM students WHERE studentID = ?");
            $check->bind_param("s", $newSid);
            $check->execute();
            $exists = $check->get_result()->fetch_assoc();
            $check->close();
            if ($exists) {
                // Update name/email in case they changed
                $upd = $conn->prepare("UPDATE students SET name = ?, email = ? WHERE studentID = ?");
                $upd->bind_param("sss", $newName, $newEmail, $newSid);
                $upd->execute();
                $upd->close();
            } else {
                // Insert new student with placeholder password (studentID as default)
                $defaultPass = password_hash($newSid, PASSWORD_DEFAULT);
                $ins = $conn->prepare("INSERT INTO students (studentID, name, email, password) VALUES (?, ?, ?, ?)");
                $ins->bind_param("ssss", $newSid, $newName, $newEmail, $defaultPass);
                $ins->execute();
                $ins->close();
            }
            // Check not already registered
            $dup = $conn->prepare("SELECT * FROM registrations WHERE eventID = ? AND studentID = ?");
            $dup->bind_param("is", $eventID, $newSid);
            $dup->execute();
            $already = $dup->get_result()->num_rows > 0;
            $dup->close();
            if (!$already) {
                $ins = $conn->prepare("INSERT INTO registrations (studentID, eventID, payment_method, payment_status) VALUES (?, ?, ?, ?)");
                $ins->bind_param("siss", $newSid, $eventID, $paymentMethod, $paymentStatus);
                $ins->execute();
                $ins->close();
            }
        }
        header("Location: EditEvent.php?id=" . $eventID);
        exit();
    }

    // Handle cancel event
    if (isset($_POST['cancel_event'])) {
        $cancelReason = trim($_POST['cancel_reason'] ?? '');
        $reasonText = $cancelReason !== '' ? ' Reason: ' . $cancelReason : '';

        // Notify registered students
        $regStmt = $conn->prepare("SELECT studentID FROM registrations WHERE eventID = ?");
        $regStmt->bind_param("i", $eventID);
        $regStmt->execute();
        $regStudents = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $regStmt->close();

        $eStmt = $conn->prepare("SELECT eventTitle, adminID FROM events WHERE eventID = ?");
        $eStmt->bind_param("i", $eventID);
        $eStmt->execute();
        $eRow = $eStmt->get_result()->fetch_assoc();
        $eStmt->close();

        if ($eRow) {
            $clubStmt = $conn->prepare("SELECT a.clubName, c.clubID FROM admins a LEFT JOIN clubs c ON a.adminID = c.adminID WHERE a.adminID = ?");
            $clubStmt->bind_param("s", $eRow['adminID']);
            $clubStmt->execute();
            $clubRow = $clubStmt->get_result()->fetch_assoc();
            $clubName = $clubRow ? $clubRow['clubName'] : 'Your club';
            $clubID = $clubRow ? (int)($clubRow['clubID'] ?? 0) : 0;
            $clubStmt->close();

            // Notify registered students
            $regMsg = $eRow['eventTitle'] . ' has been cancelled.' . $reasonText . ' If you have made any payment, please contact the club for a refund.';
            $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID, clubID) VALUES (?, ?, ?, ?)");
            foreach ($regStudents as $s) {
                $nStmt->bind_param("ssii", $s['studentID'], $regMsg, $eventID, $clubID);
                $nStmt->execute();
            }

            // Notify club_notify subscribers who are not already registered
            $subStmt = $conn->prepare("SELECT studentID FROM club_notify WHERE adminID = ? AND studentID NOT IN (SELECT studentID FROM registrations WHERE eventID = ?)");
            $subStmt->bind_param("si", $eRow['adminID'], $eventID);
            $subStmt->execute();
            $subStudents = $subStmt->get_result()->fetch_all(MYSQLI_ASSOC);
            $subStmt->close();

            $subMsg = $eRow['eventTitle'] . ' by ' . $clubName . ' has been cancelled.' . $reasonText;
            foreach ($subStudents as $s) {
                $nStmt->bind_param("ssii", $s['studentID'], $subMsg, $eventID, $clubID);
                $nStmt->execute();
            }
            $nStmt->close();

            // Notify admin
            $adminMsg = $eRow['eventTitle'] . ' has been cancelled.' . $reasonText;
            $aStmt = $conn->prepare("INSERT INTO notifications (adminID, message, eventID) VALUES (?, ?, ?)");
            $aStmt->bind_param("ssi", $adminID, $adminMsg, $eventID);
            $aStmt->execute();
            $aStmt->close();

            // Notify moderators
            $modMsg = $eRow['eventTitle'] . ' has been cancelled by the club admin.' . $reasonText;
            $modStmt = $conn->prepare("INSERT INTO moderator_notifications (message, eventID) VALUES (?, ?)");
            $modStmt->bind_param("si", $modMsg, $eventID);
            $modStmt->execute();
            $modStmt->close();
        }

        $upd = $conn->prepare("UPDATE events SET status = 'cancelled' WHERE eventID = ? AND adminID = ?");
        $upd->bind_param("is", $eventID, $adminID);
        $upd->execute();
        $upd->close();

        $_SESSION['flash_message'] = 'Event has been cancelled.';
        header("Location: AdminDashboard.php");
        exit();
    }

    if (!$eventID) {
        header("Location: AdminDashboard.php");
        exit();
    }

    // Fetch event
    $stmt = $conn->prepare("SELECT * FROM events WHERE eventID = ? AND adminID = ?");
    $stmt->bind_param("is", $eventID, $adminID);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        die("Event not found.");
    }

    $period = getEventPeriod($event['eventDate'], $event['eventEndDate'] ?? null, date('Y-m-d'));
    $isOngoing = $period === 'ongoing' && $event['status'] === 'approved';
    $isUpcoming = $period === 'upcoming' && $event['status'] === 'approved';
    $isPending = $event['status'] === 'pending';
    $canEdit = $isPending || $isUpcoming;

    // Handle end event
    if (isset($_POST['end_event'])) {
        $upd = $conn->prepare("UPDATE events SET status = 'ended' WHERE eventID = ? AND adminID = ?");
        $upd->bind_param("is", $eventID, $adminID);
        $upd->execute();
        $upd->close();
        header("Location: AdminDashboard.php");
        exit();
    }

    // Handle edit event form submission
    if (isset($_POST['save_event']) && $isPending) {
        $newTitle = trim($_POST['eventTitle'] ?? '');
        $newDate = $_POST['eventDate'] ?? '';
        $newEndDate = !empty(trim($_POST['eventEndDate'] ?? '')) ? trim($_POST['eventEndDate']) : null;
        $newTime = $_POST['eventTime'] ?? '';
        $newEndTime = trim($_POST['eventEndTime'] ?? '') ?: null;
        $newVenue = trim($_POST['venue'] ?? '');
        $newCapacity = intval($_POST['capacity'] ?? 0);
        $newDesc = trim($_POST['description'] ?? '');
        $newFee = !empty(trim($_POST['fee'] ?? '')) ? floatval($_POST['fee']) : 0.00;
        [$paymentMethods, $tngPhone, $tngQr, $bankDetails, $paymentError] = collectPaymentSettings($event);
        $newEventImage = $event['eventImage'] ?? null;
        $posterError = null;
        if (!$paymentError) {
            [$newEventImage, $posterError] = collectEventPoster($event['eventImage'] ?? null);
        }

        if ($paymentError || $posterError) {
            $editMessage = $paymentError ?: $posterError;
        } elseif ($newTitle && $newDate && $newTime && $newVenue && $newCapacity) {
            $upd = $conn->prepare("UPDATE events SET eventTitle=?, eventDate=?, eventEndDate=?, eventTime=?, eventEndTime=?, venue=?, capacity=?, description=?, eventImage=?, fee=?, payment_methods=?, tng_phone=?, tng_qr=?, bank_details=? WHERE eventID=? AND adminID=?");
            $upd->bind_param("ssssssissdssssis", $newTitle, $newDate, $newEndDate, $newTime, $newEndTime, $newVenue, $newCapacity, $newDesc, $newEventImage, $newFee, $paymentMethods, $tngPhone, $tngQr, $bankDetails, $eventID, $adminID);
            $upd->execute();
            $upd->close();
            $conn->query("UPDATE events SET decline_reason=NULL WHERE eventID=$eventID");
            // Refresh event data
            $event['eventTitle'] = $newTitle;
            $event['eventDate'] = $newDate;
            $event['eventEndDate'] = $newEndDate;
            $event['eventTime'] = $newTime;
            $event['eventEndTime'] = $newEndTime;
            $event['venue'] = $newVenue;
            $event['capacity'] = $newCapacity;
            $event['description'] = $newDesc;
            $event['eventImage'] = $newEventImage;
            $event['fee'] = $newFee;
            $event['payment_methods'] = $paymentMethods;
            $event['tng_phone'] = $tngPhone;
            $event['tng_qr'] = $tngQr;
            $event['bank_details'] = $bankDetails;
            $event['decline_reason'] = null;
            $fee = $newFee;
        }
    }

    // Handle submit for approval (upcoming events or declined-pending → re-pending for moderator review)
    if (isset($_POST['submit_for_approval']) && ($isUpcoming || ($isPending && !empty($event['decline_reason'])))) {
        $newTitle = trim($_POST['eventTitle'] ?? '');
        $newDate = $_POST['eventDate'] ?? '';
        $newEndDate = !empty(trim($_POST['eventEndDate'] ?? '')) ? trim($_POST['eventEndDate']) : null;
        $newTime = $_POST['eventTime'] ?? '';
        $newEndTime = trim($_POST['eventEndTime'] ?? '') ?: null;
        $newVenue = trim($_POST['venue'] ?? '');
        $newCapacity = intval($_POST['capacity'] ?? 0);
        $newDesc = trim($_POST['description'] ?? '');
        $newFee = !empty(trim($_POST['fee'] ?? '')) ? floatval($_POST['fee']) : 0.00;
        [$paymentMethods, $tngPhone, $tngQr, $bankDetails, $paymentError] = collectPaymentSettings($event);
        $newEventImage = $event['eventImage'] ?? null;
        $posterError = null;
        if (!$paymentError) {
            [$newEventImage, $posterError] = collectEventPoster($event['eventImage'] ?? null);
        }

        if ($paymentError || $posterError) {
            $editMessage = $paymentError ?: $posterError;
        } elseif ($newTitle && $newDate && $newTime && $newVenue && $newCapacity) {
            $upd = $conn->prepare("UPDATE events SET eventTitle=?, eventDate=?, eventEndDate=?, eventTime=?, eventEndTime=?, venue=?, capacity=?, description=?, eventImage=?, fee=?, payment_methods=?, tng_phone=?, tng_qr=?, bank_details=?, status='pending' WHERE eventID=? AND adminID=?");
            $upd->bind_param("ssssssissdssssis", $newTitle, $newDate, $newEndDate, $newTime, $newEndTime, $newVenue, $newCapacity, $newDesc, $newEventImage, $newFee, $paymentMethods, $tngPhone, $tngQr, $bankDetails, $eventID, $adminID);
            $upd->execute();
            $upd->close();
            $conn->query("UPDATE events SET decline_reason=NULL WHERE eventID=$eventID");
            // Notify moderators
            $modMsg = $newTitle . ' has been updated and needs re-approval.';
            $modStmt = $conn->prepare("INSERT INTO moderator_notifications (message, eventID) VALUES (?, ?)");
            $modStmt->bind_param("si", $modMsg, $eventID);
            $modStmt->execute();
            $modStmt->close();
            $_SESSION['flash_message'] = 'Event changes submitted for moderator approval.';
            header("Location: AdminDashboard.php");
            exit();
        }
    }

    // Handle remove registered participant
    if (isset($_POST['remove_registered']) && $eventID) {
        $sid = $_POST['student_id'] ?? '';
        if ($sid) {
            $del = $conn->prepare("DELETE FROM registrations WHERE eventID = ? AND studentID = ?");
            $del->bind_param("is", $eventID, $sid);
            $del->execute();
            $removed = $del->affected_rows > 0;
            $del->close();

            if ($removed) {
                $removeMsg = 'You have been removed from ' . ($event['eventTitle'] ?? 'this event') . '. Please contact the club if you think this is a mistake.';
                $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
                $nStmt->bind_param("ssi", $sid, $removeMsg, $eventID);
                $nStmt->execute();
                $nStmt->close();

                promoteNextWaitlistedStudent($conn, (int)$eventID);
            }
        }
        header("Location: EditEvent.php?id=" . $eventID);
        exit();
    }

    // Handle remove from waiting list
    if (isset($_POST['remove_waitlist']) && $eventID) {
        $wid = (int)($_POST['wait_id'] ?? 0);
        if ($wid) {
            $del = $conn->prepare("DELETE FROM waiting_list WHERE waitID = ?");
            $del->bind_param("i", $wid);
            $del->execute();
            $del->close();
        }
        header("Location: EditEvent.php?id=" . $eventID);
        exit();
    }

    // Handle promote from waiting list to registered
    if (isset($_POST['promote_waitlist']) && $eventID) {
        $wid = (int)($_POST['wait_id'] ?? 0);
        if ($wid) {
            $wStmt = $conn->prepare("SELECT * FROM waiting_list WHERE waitID = ?");
            $wStmt->bind_param("i", $wid);
            $wStmt->execute();
            $wRow = $wStmt->get_result()->fetch_assoc();
            $wStmt->close();
            if ($wRow) {
                $waitPaymentMethod = 'cash';
                $waitPaymentStatus = 'unpaid';
                $waitPaymentReceipt = null;
                $ins = $conn->prepare("INSERT IGNORE INTO registrations (studentID, eventID, payment_method, payment_status, payment_receipt) VALUES (?, ?, ?, ?, ?)");
                $ins->bind_param("sisss", $wRow['studentID'], $eventID, $waitPaymentMethod, $waitPaymentStatus, $waitPaymentReceipt);
                $ins->execute();
                $promoted = $ins->affected_rows > 0;
                $ins->close();
                $del = $conn->prepare("DELETE FROM waiting_list WHERE waitID = ?");
                $del->bind_param("i", $wid);
                $del->execute();
                $del->close();

                if ($promoted) {
                    $countStmt = $conn->prepare("SELECT COUNT(*) AS registeredCount FROM registrations WHERE eventID = ?");
                    $countStmt->bind_param("i", $eventID);
                    $countStmt->execute();
                    $registeredCount = (int)$countStmt->get_result()->fetch_assoc()['registeredCount'];
                    $countStmt->close();

                    if ($registeredCount > (int)$event['capacity']) {
                        $capStmt = $conn->prepare("UPDATE events SET capacity = ? WHERE eventID = ? AND adminID = ?");
                        $capStmt->bind_param("iis", $registeredCount, $eventID, $adminID);
                        $capStmt->execute();
                        $capStmt->close();
                    }

                    $memberStmt = $conn->prepare("INSERT IGNORE INTO club_members (studentID, adminID) VALUES (?, ?)");
                    $memberStmt->bind_param("ss", $wRow['studentID'], $adminID);
                    $memberStmt->execute();
                    $memberStmt->close();

                    $promoteMsg = "Good news! A space is available for {$event['eventTitle']}. You have successfully joined the event from the waiting list.";
                    $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
                    $notifStmt->bind_param("ssi", $wRow['studentID'], $promoteMsg, $eventID);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
            }
        }
        header("Location: EditEvent.php?id=" . $eventID);
        exit();
    }

    // Fetch participants with payment & attendance status
    $partStmt = $conn->prepare("SELECT r.studentID, r.payment_status, r.payment_receipt, r.attendance_status, r.payment_method, s.name, s.email FROM registrations r JOIN students s ON r.studentID = s.studentID WHERE r.eventID = ? ORDER BY s.name ASC");
    $partStmt->bind_param("i", $eventID);
    $partStmt->execute();
    $participants = $partStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $partStmt->close();

    // Fetch waiting list
    $waitStmt = $conn->prepare("SELECT w.waitID, w.studentID, w.payment_method, w.registered_at, s.name, s.email FROM waiting_list w JOIN students s ON w.studentID = s.studentID WHERE w.eventID = ? ORDER BY w.registered_at ASC");
    $waitStmt->bind_param("i", $eventID);
    $waitStmt->execute();
    $waitlist = $waitStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $waitStmt->close();

    $totalRegistered = count($participants);
    if ($totalRegistered > (int)$event['capacity']) {
        $syncCapacity = $totalRegistered;
        $capStmt = $conn->prepare("UPDATE events SET capacity = ? WHERE eventID = ? AND adminID = ?");
        $capStmt->bind_param("iis", $syncCapacity, $eventID, $adminID);
        $capStmt->execute();
        $capStmt->close();
        $event['capacity'] = $syncCapacity;
    }
    $totalPaid = count(array_filter($participants, fn($p) => $p['payment_status'] === 'paid'));
    $totalPresent = count(array_filter($participants, fn($p) => $p['attendance_status'] === 'present'));
    $fee = floatval($event['fee'] ?? 0);
    $totalCollected = $totalPaid * $fee;
    $potentialTotal = $event['capacity'] * $fee;
    $capacity = intval($event['capacity']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Edit Event - <?php echo htmlspecialchars($event['eventTitle']); ?></title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <?php include 'AdminNavbar.php'; ?>
    <main class="container">
        <a href="AdminDashboard.php" class="back-link">&larr; Back to Dashboard</a>
        <div class="flex-space-between">
            <h2 class="clubs-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h2>
            <?php if ($canEdit): ?>
            <div class="flex-row-nowrap">
                <button onclick="toggleEdit()" id="editBtn" class="action-pill-btn">Edit</button>
                <?php if ($isPending): ?>
                <form method="POST" action="DeleteEvent.php" class="flex-inline" onsubmit="return collectCancelReason(this);">
                    <input type="hidden" name="event_id" value="<?php echo (int)$eventID; ?>">
                    <input type="hidden" name="cancel_reason" value="">
                    <button type="submit" class="btn-red-inline">Cancel</button>
                </form>
                <?php endif; ?>
                <?php if ($isUpcoming): ?>
                <form method="POST" onsubmit="return collectCancelReason(this);">
                    <input type="hidden" name="cancel_reason" value="">
                    <button type="submit" name="cancel_event" class="btn-outline-danger">Cancel Event</button>
                </form>
                <?php endif; ?>
            </div>
            <?php elseif ($isOngoing): ?>
            <div class="flex-row-nowrap">
                <form method="POST" onsubmit="return confirm('End this event and move it to completed?');">
                    <button type="submit" name="end_event" class="btn-outline-sm">End Event</button>
                </form>
                <form method="POST" onsubmit="return collectCancelReason(this);">
                    <input type="hidden" name="cancel_reason" value="">
                    <button type="submit" name="cancel_event" class="btn-outline-danger">Cancel Event</button>
                </form>
            </div>
            <?php endif; ?>
        </div>
        <?php if ($editMessage): ?>
            <p class="msg-error"><?php echo htmlspecialchars($editMessage); ?></p>
        <?php endif; ?>

        <div class="edit-layout">
            <!-- Event Info -->
            <div class="edit-card">
                <h3>Event Details</h3>
                <div id="eventView">
                    <?php if (!empty($event['eventImage'])): ?>
                        <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event poster" class="edit-poster-preview clickable-poster" onclick="openEventPosterViewer(this.src)">
                    <?php endif; ?>
                    <p class="meta-line"><strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?></p>
                    <p class="meta-line"><strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></p>
                    <p class="meta-line"><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                    <p class="meta-line"><strong>Fee:</strong> <?php echo $fee > 0 ? 'RM' . number_format($fee, 2) : 'Free'; ?></p>
                    <p class="meta-line"><strong>Capacity:</strong> <?php echo (int)$event['capacity']; ?></p>
                    <p class="meta-line"><strong>Description:</strong> <?php echo nl2br(htmlspecialchars($event['description'])); ?></p>
                    <?php if (!empty($event['decline_reason'])): ?>
                        <p class="meta-line declined-note">
                            <strong>Declined reason:</strong>
                            <span><?php echo nl2br(htmlspecialchars($event['decline_reason'])); ?></span>
                        </p>
                    <?php endif; ?>
                    <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                        <p class="meta-line"><strong>Payment:</strong>
                            <?php
                            $methodLabels = ['cash'=>'Cash', 'tng'=>'TNG', 'bank_in'=>'Bank In'];
                            $ms = explode(',', $event['payment_methods']);
                            echo implode(', ', array_map(fn($m) => $methodLabels[trim($m)] ?? trim($m), $ms));
                            ?>
                        </p>
                    <?php endif; ?>
                </div>
                <?php if ($canEdit): ?>
                <div id="eventEdit" class="hidden">
                    <form method="POST" id="editForm" enctype="multipart/form-data">
                        <div class="mb-10">
                            <label class="form-label-sm">Event Title</label>
                            <input type="text" name="eventTitle" value="<?php echo htmlspecialchars($event['eventTitle']); ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Date</label>
                            <input type="date" name="eventDate" value="<?php echo $event['eventDate']; ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">End Date</label>
                            <input type="date" name="eventEndDate" value="<?php echo $event['eventEndDate'] ?? ''; ?>" class="form-input">
                            <small class="text-xs-muted-alt">Leave blank for single-day event.</small>
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Time</label>
                            <div class="flex-row">
                                <input type="time" name="eventTime" value="<?php echo $event['eventTime']; ?>" required class="form-input-flex">
                                <span>—</span>
                                <input type="time" name="eventEndTime" value="<?php echo $event['eventEndTime'] ?? ''; ?>" class="form-input-flex">
                            </div>
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Venue</label>
                            <input type="text" name="venue" value="<?php echo htmlspecialchars($event['venue']); ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Capacity</label>
                            <input type="number" name="capacity" min="1" value="<?php echo (int)$event['capacity']; ?>" required class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Fee (0 = free)</label>
                            <input type="number" name="fee" min="0" step="0.01" value="<?php echo number_format($fee, 2); ?>" class="form-input">
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Description</label>
                            <textarea name="description" required class="form-textarea"><?php echo htmlspecialchars($event['description']); ?></textarea>
                        </div>
                        <div class="mb-10">
                            <label class="form-label-sm">Event Poster</label>
                            <div class="edit-poster-upload">
                                <?php if (!empty($event['eventImage'])): ?>
                                    <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Current event poster" id="editPosterPreview">
                                    <small class="text-xs-muted-alt">Current poster shown above. Choose a new image only if you want to replace it.</small>
                                <?php else: ?>
                                    <img src="" alt="Selected event poster preview" id="editPosterPreview" class="hidden">
                                    <small class="text-xs-muted-alt">No poster uploaded yet. Choose an image to add one.</small>
                                <?php endif; ?>
                                <input type="file" name="eventImage" accept="image/jpeg,image/png,image/gif,image/webp" class="form-file-input-sm" onchange="previewEditPoster(this)">
                                <small class="text-xs-muted-alt">Allowed: JPG, JPEG, PNG, GIF, WEBP. Maximum 5MB.</small>
                            </div>
                        </div>
                        <?php
                            $selectedMethods = array_filter(array_map('trim', explode(',', $event['payment_methods'] ?? '')));
                            $bankData = !empty($event['bank_details']) ? json_decode($event['bank_details'], true) : [];
                            if (!is_array($bankData)) $bankData = [];
                        ?>
                        <div class="mb-10">
                            <label class="form-label-sm">Payment Methods</label>
                            <div class="flex-payment-checkboxes">
                                <label>
                                    <input type="checkbox" name="payment_methods[]" value="cash" onchange="toggleEditPaymentFields()" class="form-checkbox" <?php echo in_array('cash', $selectedMethods, true) ? 'checked' : ''; ?>> Cash
                                </label>
                                <label>
                                    <input type="checkbox" name="payment_methods[]" value="tng" onchange="toggleEditPaymentFields()" class="form-checkbox" <?php echo in_array('tng', $selectedMethods, true) ? 'checked' : ''; ?>> TNG (Touch 'n Go)
                                </label>
                                <label>
                                    <input type="checkbox" name="payment_methods[]" value="bank_in" onchange="toggleEditPaymentFields()" class="form-checkbox" <?php echo in_array('bank_in', $selectedMethods, true) ? 'checked' : ''; ?>> Bank In
                                </label>
                            </div>

                            <div id="editTngFields" class="payment-section">
                                <p class="payment-section-title">TNG Payment Details</p>
                                <div class="mb-10">
                                    <label class="form-label-sm">Phone Number</label>
                                    <input type="text" name="tng_phone" value="<?php echo htmlspecialchars($event['tng_phone'] ?? ''); ?>" placeholder="e.g., 0123456789" inputmode="numeric" pattern="[0-9]*" oninvalid="this.setCustomValidity(this.value ? 'Only numbers allowed' : 'Please fill out this field')" oninput="this.setCustomValidity(''); this.value=this.value.replace(/[^0-9]/g,'')" class="form-input">
                                </div>
                                <div>
                                    <label class="form-label-sm">QR Code Image (optional)</label>
                                    <?php if (!empty($event['tng_qr'])): ?>
                                        <p class="text-xs-muted-alt">Current QR uploaded. Choose a new file only if you want to replace it.</p>
                                    <?php endif; ?>
                                    <input type="file" name="tng_qr" accept="image/*" class="form-file-input-sm">
                                </div>
                            </div>

                            <div id="editBankFields" class="payment-section">
                                <p class="payment-section-title">Bank In Details</p>
                                <div class="mb-10">
                                    <label class="form-label-sm">Bank Name</label>
                                    <input type="text" name="bank_name" value="<?php echo htmlspecialchars($bankData['bank_name'] ?? ''); ?>" placeholder="e.g., CIMB Bank" oninvalid="this.setCustomValidity('Please fill out this field')" oninput="this.setCustomValidity('')" class="form-input">
                                </div>
                                <div class="mb-10">
                                    <label class="form-label-sm">Account Number</label>
                                    <input type="text" name="bank_account" value="<?php echo htmlspecialchars($bankData['bank_account'] ?? ''); ?>" placeholder="e.g., 1234567890" inputmode="numeric" pattern="[0-9]*" oninvalid="this.setCustomValidity(this.value ? 'Only numbers allowed' : 'Please fill out this field')" oninput="this.setCustomValidity(''); this.value=this.value.replace(/[^0-9]/g,'')" class="form-input">
                                </div>
                                <div>
                                    <label class="form-label-sm">Account Holder Name</label>
                                    <input type="text" name="bank_holder" value="<?php echo htmlspecialchars($bankData['bank_holder'] ?? ''); ?>" placeholder="e.g., John Doe" oninvalid="this.setCustomValidity('Please fill out this field')" oninput="this.setCustomValidity('')" class="form-input">
                                </div>
                            </div>
                        </div>
                        <div class="flex-end">
                            <button type="button" onclick="toggleEdit()" class="btn-secondary">Cancel</button>
                            <?php if ($isUpcoming || !empty($event['decline_reason'])): ?>
                            <button type="submit" name="submit_for_approval" class="btn-primary-sm">Submit for Approval</button>
                            <?php else: ?>
                            <button type="submit" name="save_event" class="btn-primary-sm">Save Changes</button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <!-- Stats -->
            <div class="edit-card">
                <h3>Registration &amp; Payment Stats</h3>
                <div class="stat-grid">
                    <div class="stat-box">
                        <div class="num"><?php echo $totalRegistered; ?> / <?php echo $capacity; ?></div>
                        <div class="lbl">Registered</div>
                    </div>
                    <?php if ($isOngoing): ?>
                    <div class="stat-box">
                        <div class="num"><?php echo $totalPresent; ?> / <?php echo $totalRegistered; ?></div>
                        <div class="lbl">Present</div>
                    </div>
                    <?php endif; ?>
                    <div class="stat-box">
                        <div class="num" id="feesCollectedStat" data-fee="<?php echo htmlspecialchars((string)$fee); ?>" data-potential="<?php echo htmlspecialchars((string)$potentialTotal); ?>"><?php echo $fee > 0 ? 'RM' . number_format($totalCollected, 2) . ' / RM' . number_format($potentialTotal, 2) : '—'; ?></div>
                        <div class="lbl">Fees Collected</div>
                    </div>
                    <div class="stat-box">
                        <div class="num" id="paymentDoneStat"><?php echo $fee > 0 ? $totalPaid . ' / ' . $totalRegistered : '—'; ?></div>
                        <div class="lbl">Payment Done</div>
                    </div>
                </div>
            </div>

            <!-- Participant List -->
            <div class="edit-card full-width">
                <h3>
                    <span>Participant List (<?php echo $totalRegistered; ?>)</span>
                    <?php if ($isOngoing): ?>
                        <button class="add-btn" onclick="document.getElementById('addModal').classList.add('open')" title="Add participant">+</button>
                    <?php endif; ?>
                </h3>
                <input type="text" id="partSearch" class="search-part" placeholder="Search by student ID or name...">
                <div class="table-responsive">
                    <table class="part-table" id="partTable">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Payment Method</th>
                                <th>Payment Status</th>
                                <?php if ($isOngoing): ?><th>Attendance</th><?php endif; ?>
                                <th>Remove</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($participants as $p): ?>
                            <tr>
                                <td><?php echo $i++; ?></td>
                                <td><?php echo htmlspecialchars($p['studentID']); ?></td>
                                <td><?php echo htmlspecialchars($p['name']); ?></td>
                                <td><?php echo htmlspecialchars($p['email']); ?></td>
                                <td><?php
                                    if ($fee == 0) {
                                        echo '—';
                                    } else {
                                        $pm = $p['payment_method'] ?? '';
                                        $methodLabels = ['cash'=>'Cash', 'tng'=>'TNG', 'bank_in'=>'Bank In'];
                                        echo $pm ? ($methodLabels[$pm] ?? $pm) : '<span class="text-xs-muted">—</span>';
                                    }
                                ?></td>
                                <td><?php if ($fee == 0): ?>—<?php else: ?>
                                    <?php
                                        $paymentMethod = $p['payment_method'] ?? '';
                                        $isPaid = ($p['payment_status'] ?? 'unpaid') === 'paid';
                                        $receipt = $p['payment_receipt'] ?? '';
                                    ?>
                                    <button class="toggle-btn <?php echo $isPaid ? 'on' : 'off'; ?>"
                                            data-sid="<?php echo htmlspecialchars($p['studentID']); ?>"
                                            data-eid="<?php echo $eventID; ?>"
                                            data-type="payment"
                                            onclick="toggleField(this)">
                                        <?php echo $isPaid ? '✅' : 'X'; ?>
                                    </button>
                                    <?php if ($isPaid && $receipt): ?>
                                        <a href="<?php echo htmlspecialchars($receipt); ?>" target="_blank" rel="noopener" class="receipt-link">(attachment)</a>
                                    <?php endif; ?>
                                <?php endif; ?></td>
                                <?php if ($isOngoing): ?>
                                <td>
                                    <button class="toggle-btn <?php echo $p['attendance_status'] === 'present' ? 'on' : 'off'; ?>"
                                            data-sid="<?php echo htmlspecialchars($p['studentID']); ?>"
                                            data-eid="<?php echo $eventID; ?>"
                                            data-type="attendance"
                                            onclick="toggleField(this)">
                                        <?php echo $p['attendance_status'] === 'present' ? '✅' : 'X'; ?>
                                    </button>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <button class="remove-btn" title="Remove participant"
                                            onclick="openRemoveReg('<?php echo htmlspecialchars($p['studentID']); ?>','<?php echo htmlspecialchars($p['name']); ?>')">Remove</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($participants)): ?>
                    <p class="text-center text-xs-muted empty-table-note">No participants yet.</p>
                <?php endif; ?>

                <!-- Remove Registered Student Modal -->
                <div class="modal-overlay" id="removeRegModal">
                    <div class="modal-box">
                        <h3>Remove Participant</h3>
                        <p class="modal-helper-text">Are you sure you want to remove <strong id="removeRegName"></strong> from this event?</p>
                        <form method="POST">
                            <input type="hidden" name="student_id" id="removeRegSid">
                            <div class="modal-actions">
                                <button type="button" class="btn-sm btn-sm-outline btn-pad-sm" onclick="document.getElementById('removeRegModal').classList.remove('open')">Cancel</button>
                                <button type="submit" name="remove_registered" class="btn-outline-danger btn-pad-sm">Remove</button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <!-- Waiting List -->
            <div class="edit-card full-width">
                <h3>
                    <span>Waiting List (<?php echo count($waitlist); ?>)</span>
                </h3>
                <div class="table-responsive">
                    <table class="part-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $wi = 1; foreach ($waitlist as $w): ?>
                            <tr>
                                <td><?php echo $wi++; ?></td>
                                <td><?php echo htmlspecialchars($w['studentID']); ?></td>
                                <td><?php echo htmlspecialchars($w['name']); ?></td>
                                <td><?php echo htmlspecialchars($w['email']); ?></td>
                                <td><?php echo date('d M Y h:iA', strtotime($w['registered_at'])); ?></td>
                                <td>
                                    <form method="POST" class="inline-form">
                                        <input type="hidden" name="wait_id" value="<?php echo (int)$w['waitID']; ?>">
                                        <button type="submit" name="promote_waitlist" class="promote-btn" title="Move to registered"><?php echo $isOngoing ? 'Add' : 'Move'; ?></button>
                                    </form>
                                    <form method="POST" class="inline-form" onsubmit="return confirm('Remove this student from the waiting list?');">
                                        <input type="hidden" name="wait_id" value="<?php echo (int)$w['waitID']; ?>">
                                        <button type="submit" name="remove_waitlist" class="remove-btn" title="Remove">Remove</button>
                                    </form>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php if (empty($waitlist)): ?>
                    <p class="text-center text-xs-muted empty-table-note">No one is on the waiting list.</p>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <div id="eventPosterViewer" class="avatar-viewer-modal" onclick="closeEventPosterViewer()">
        <span class="avatar-viewer-close">&times;</span>
        <img class="avatar-viewer-image" id="eventPosterViewerImage" alt="Full event poster">
    </div>

    <!-- Add Participant Modal -->
    <div class="modal-overlay" id="addModal">
        <div class="modal-box">
            <h3>Add Participant</h3>
            <form method="POST">
                <input type="text" name="new_student_id" placeholder="Student ID" required>
                <input type="text" name="new_name" placeholder="Full Name" required>
                <input type="email" name="new_email" placeholder="Email" required>
                <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                <select name="payment_method" class="form-select">
                    <option value="">Payment method (optional)</option>
                    <?php
                    $methodLabels = ['cash'=>'Cash', 'tng'=>'TNG (Touch \'n Go)', 'bank_in'=>'Bank In'];
                    foreach (explode(',', $event['payment_methods']) as $m):
                        $m = trim($m);
                    ?>
                    <option value="<?php echo htmlspecialchars($m); ?>"><?php echo $methodLabels[$m] ?? $m; ?></option>
                    <?php endforeach; ?>
                </select>
                <?php endif; ?>
                <?php if ($fee > 0): ?>
                <select name="payment_status" class="form-select">
                    <option value="unpaid">Payment: Unpaid</option>
                    <option value="paid">Payment: Paid</option>
                </select>
                <?php endif; ?>
                <div class="modal-actions">
                    <button type="button" class="btn-sm btn-sm-outline btn-pad-sm" onclick="document.getElementById('addModal').classList.remove('open')">Cancel</button>
                    <button type="submit" name="add_participant" class="btn-primary-sm btn-pad-sm">Add</button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function openEventPosterViewer(src) {
        document.getElementById('eventPosterViewerImage').src = src;
        document.getElementById('eventPosterViewer').classList.add('active');
    }

    function closeEventPosterViewer() {
        document.getElementById('eventPosterViewer').classList.remove('active');
    }

    function toggleEdit() {
        const view = document.getElementById('eventView');
        const edit = document.getElementById('eventEdit');
        const btn = document.getElementById('editBtn');
        if (!view || !edit) return;
        const isEditing = !edit.classList.contains('hidden');
        view.classList.toggle('hidden', !isEditing);
        edit.classList.toggle('hidden', isEditing);
        btn.textContent = isEditing ? 'Edit' : 'Cancel';
        toggleEditPaymentFields();
    }

    function previewEditPoster(input) {
        const preview = document.getElementById('editPosterPreview');
        const file = input.files && input.files[0] ? input.files[0] : null;
        if (!preview || !file) return;
        preview.src = URL.createObjectURL(file);
        preview.classList.remove('hidden');
        preview.style.display = 'block';
    }

    function toggleEditPaymentFields() {
        const tng = document.querySelector('#editForm input[name="payment_methods[]"][value="tng"]');
        const bank = document.querySelector('#editForm input[name="payment_methods[]"][value="bank_in"]');
        const tngFields = document.getElementById('editTngFields');
        const bankFields = document.getElementById('editBankFields');
        const tngPhone = document.querySelector('#editForm input[name="tng_phone"]');
        const bankName = document.querySelector('#editForm input[name="bank_name"]');
        const bankAccount = document.querySelector('#editForm input[name="bank_account"]');
        const bankHolder = document.querySelector('#editForm input[name="bank_holder"]');

        if (tngFields) tngFields.style.display = tng && tng.checked ? 'block' : 'none';
        if (bankFields) bankFields.style.display = bank && bank.checked ? 'block' : 'none';
        if (tngPhone) {
            tngPhone.required = !!(tng && tng.checked);
            if (!tngPhone.required) tngPhone.setCustomValidity('');
        }
        if (bankName) {
            bankName.required = !!(bank && bank.checked);
            if (!bankName.required) bankName.setCustomValidity('');
        }
        if (bankAccount) {
            bankAccount.required = !!(bank && bank.checked);
            if (!bankAccount.required) bankAccount.setCustomValidity('');
        }
        if (bankHolder) {
            bankHolder.required = !!(bank && bank.checked);
            if (!bankHolder.required) bankHolder.setCustomValidity('');
        }
    }

    document.getElementById('partSearch').addEventListener('input', function () {
        const q = this.value.toLowerCase().trim();
        document.querySelectorAll('#partTable tbody tr').forEach(row => {
            const text = row.cells[1].textContent.toLowerCase() + ' ' + row.cells[2].textContent.toLowerCase();
            row.style.display = !q || text.includes(q) ? '' : 'none';
        });
    });

    function toggleField(btn) {
        const sid = btn.dataset.sid;
        const eid = btn.dataset.eid;
        const type = btn.dataset.type;
        const current = btn.classList.contains('on') ? 
            (type === 'payment' ? 'paid' : 'present') : 
            (type === 'payment' ? 'unpaid' : 'absent');
        const newStatus = type === 'payment' ?
            (current === 'paid' ? 'unpaid' : 'paid') :
            (current === 'present' ? 'absent' : 'present');
        const ajaxField = type === 'payment' ? 'ajax_toggle_payment' : 'ajax_toggle_attendance';

        fetch('EditEvent.php?id=' + eid, {
            method: 'POST',
            headers: {'Content-Type':'application/x-www-form-urlencoded'},
            body: ajaxField + '=1&student_id=' + encodeURIComponent(sid) + '&new_status=' + newStatus
        }).then(() => {
            btn.className = 'toggle-btn ' + (newStatus === 'paid' || newStatus === 'present' ? 'on' : 'off');
            btn.textContent = (newStatus === 'paid' || newStatus === 'present') ? '✅' : 'X';
            if (type === 'payment') updatePaymentStats();
        });
    }

    function updatePaymentStats() {
        const feesCollectedStat = document.getElementById('feesCollectedStat');
        const paymentDoneStat = document.getElementById('paymentDoneStat');
        if (!feesCollectedStat || !paymentDoneStat) return;

        const paymentButtons = document.querySelectorAll('.toggle-btn[data-type="payment"]');
        const paidCount = document.querySelectorAll('.toggle-btn[data-type="payment"].on').length;
        const totalCount = paymentButtons.length;
        const fee = parseFloat(feesCollectedStat.dataset.fee || '0');
        const potential = parseFloat(feesCollectedStat.dataset.potential || '0');

        paymentDoneStat.textContent = paidCount + ' / ' + totalCount;
        feesCollectedStat.textContent = 'RM' + (paidCount * fee).toFixed(2) + ' / RM' + potential.toFixed(2);
    }

    function openRemoveReg(sid, name) {
        document.getElementById('removeRegSid').value = sid;
        document.getElementById('removeRegName').textContent = name;
        document.getElementById('removeRegModal').classList.add('open');
    }

    function collectCancelReason(form) {
        const reason = prompt('Please write the reason for cancelling this event. Students will be notified with this reason.');
        if (reason === null) return false;
        if (!reason.trim()) {
            alert('Cancellation reason is required.');
            return false;
        }
        const field = form.querySelector('input[name="cancel_reason"]');
        if (field) field.value = reason.trim();
        return confirm('Cancel this event and notify students?');
    }

    // Close modals on overlay click
    document.querySelectorAll('.modal-overlay').forEach(el => {
        el.addEventListener('click', function (e) {
            if (e.target === this) this.classList.remove('open');
        });
    });

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeEventPosterViewer();
        }
    });

    toggleEditPaymentFields();
    </script>
</body>
</html>
