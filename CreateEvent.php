<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    session_start();
    require_once 'db_connect.php';

    // Security Check: Redirect to login if user is not authorized as an admin
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }
    session_write_close();

    $adminID = $_SESSION['admin_id'];
    $message = "";
    $showSuccessPopup = false;

    // Handle Form Processing when user clicks submit
    if (isset($_POST["submit"])) {
        $eventTitle  = trim($_POST['eventTitle']);
        $eventDate   = $_POST['eventDate'];
        $eventEndDate = !empty(trim($_POST['eventEndDate'])) ? trim($_POST['eventEndDate']) : null;
        $eventTime   = trim($_POST['eventTime']);
        $eventEndTime = !empty(trim($_POST['eventEndTime'])) ? trim($_POST['eventEndTime']) : null;
        $venue       = trim($_POST['venue']);
        $capacity    = intval($_POST['capacity']);
        $description = trim($_POST['description']);
        $fee        = !empty(trim($_POST['fee'])) ? floatval($_POST['fee']) : 0.00;
        $eventImage  = null;

        // Handle image upload
        if (isset($_FILES['eventImage']) && $_FILES['eventImage']['error'] === UPLOAD_ERR_OK) {
            $uploadDir = 'uploads/events/';
            $fileName = time() . '_' . basename($_FILES['eventImage']['name']);
            $targetPath = $uploadDir . $fileName;
            $imageFileType = strtolower(pathinfo($targetPath, PATHINFO_EXTENSION));

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($imageFileType, $allowedTypes)) {
                $message = "<p class='msg-error'>Only JPG, JPEG, PNG, GIF & WEBP files are allowed.</p>";
            } elseif ($_FILES['eventImage']['size'] > 5 * 1024 * 1024) {
                $message = "<p class='msg-error'>File size must be less than 5MB.</p>";
            } elseif (move_uploaded_file($_FILES['eventImage']['tmp_name'], $targetPath)) {
                $eventImage = $targetPath;
            } else {
                $message = "<p class='msg-error'>Failed to upload image.</p>";
            }
        }

        // Payment fields
        $paymentMethods = !empty($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : null;
        $tngPhone = null;
        $tngQr = null;
        $bankDetails = null;

        if ($paymentMethods && strpos($paymentMethods, 'tng') !== false) {
            $tngPhone = !empty(trim($_POST['tng_phone'])) ? trim($_POST['tng_phone']) : null;
            if (isset($_FILES['tng_qr']) && $_FILES['tng_qr']['error'] === UPLOAD_ERR_OK) {
                $qrDir = 'uploads/payments/';
                if (!is_dir($qrDir)) mkdir($qrDir, 0777, true);
                $qrName = time() . '_qr_' . basename($_FILES['tng_qr']['name']);
                $qrPath = $qrDir . $qrName;
                $qrType = strtolower(pathinfo($qrPath, PATHINFO_EXTENSION));
                if (in_array($qrType, ['jpg','jpeg','png','gif','webp']) && move_uploaded_file($_FILES['tng_qr']['tmp_name'], $qrPath)) {
                    $tngQr = $qrPath;
                }
            }
        }

        if ($paymentMethods && strpos($paymentMethods, 'bank_in') !== false) {
            $bankData = [];
            if (!empty(trim($_POST['bank_name']))) $bankData['bank_name'] = trim($_POST['bank_name']);
            if (!empty(trim($_POST['bank_account']))) $bankData['bank_account'] = trim($_POST['bank_account']);
            if (!empty(trim($_POST['bank_holder']))) $bankData['bank_holder'] = trim($_POST['bank_holder']);
            if (!empty($bankData)) $bankDetails = json_encode($bankData);
        }

        if (empty($message)) {
            $stmt = $conn->prepare("INSERT INTO events (adminID, eventTitle, eventDate, eventEndDate, eventTime, eventEndTime, venue, capacity, description, eventImage, status, payment_methods, tng_phone, tng_qr, bank_details, fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");

            if ($stmt) {
                $stmt->bind_param("sssssssissssssd", $adminID, $eventTitle, $eventDate, $eventEndDate, $eventTime, $eventEndTime, $venue, $capacity, $description, $eventImage, $paymentMethods, $tngPhone, $tngQr, $bankDetails, $fee);

                if ($stmt->execute()) {
                    // Notify moderators
                    $newEventId = $stmt->insert_id;
                    $modMsg = "New event submitted for review: " . $eventTitle;
                    $modStmt = $conn->prepare("INSERT INTO moderator_notifications (message, eventID) VALUES (?, ?)");
                    $modStmt->bind_param("si", $modMsg, $newEventId);
                    $modStmt->execute();
                    $modStmt->close();
                    $showSuccessPopup = true;
                } else {
                    $message = "<p class='msg-error'>Error creating event. Please try again.</p>";
                }
                $stmt->close();
            } else {
                $message = "<p class='msg-error'>Database preparation failed.</p>";
            }
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Event</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'AdminNavbar.php'; ?>

    <div class="container">
        <div class="profile-box">
            <h2>Create New Event</h2>

            <?php echo $message; ?>

            <form action="CreateEvent.php" method="POST" enctype="multipart/form-data">

                <div class="form-group">
                    <label>Event Title :</label>
                    <input type="text" name="eventTitle" placeholder="e.g., Annual General Meeting" required>
                </div>

                <div class="form-group">
                    <label>Event Date :</label>
                    <input type="date" name="eventDate" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>End Date :</label>
                    <input type="date" name="eventEndDate" min="<?php echo date('Y-m-d'); ?>" placeholder="Leave blank if the event is only one day.">
                </div>

                <div class="form-group">
                    <label>Event Time Schedule :</label>
                    <div class="flex-time-row">
                        <input type="time" name="eventTime" required class="form-input-flex">
                        <span class="flex-time-sep">—</span>
                        <input type="time" name="eventEndTime" class="form-input-flex">
                    </div>
                </div>

                <div class="form-group">
                    <label>Venue Location :</label>
                    <input type="text" name="venue" placeholder="e.g., Sports Hall Block B" required>
                </div>

                <div class="form-group">
                    <label>Max Capacity Limit :</label>
                    <input type="number" name="capacity" min="1" placeholder="e.g., 50" required>
                </div>

                <div class="form-group">
                    <label>Event Description / Remarks :</label>
                    <textarea name="description" placeholder="Provide event context guidelines or register rules..." required class="form-textarea-lg"></textarea>
                </div>

                <div class="form-group">
                    <label>Event Fee (optional) :</label>
                    <input type="number" name="fee" min="0" step="0.01" placeholder="0.00 (free)" class="form-input-lg">
                </div>

                <div class="form-group">
                    <label>Event Poster (optional) :</label>
                    <input type="file" name="eventImage" accept="image/jpeg,image/png,image/gif,image/webp" class="form-file-input">
                </div>

                <div class="form-group">
                    <label>Payment Methods (optional) :</label>
                    <div class="flex-payment-checkboxes">
                        <label>
                            <input type="checkbox" name="payment_methods[]" value="cash" onchange="togglePaymentFields()" class="form-checkbox"> Cash
                        </label>
                        <label>
                            <input type="checkbox" name="payment_methods[]" value="tng" onchange="togglePaymentFields()" class="form-checkbox"> TNG (Touch 'n Go)
                        </label>
                        <label>
                            <input type="checkbox" name="payment_methods[]" value="bank_in" onchange="togglePaymentFields()" class="form-checkbox"> Bank In
                        </label>
                    </div>

                    <div id="tngFields" class="payment-section">
                        <p class="payment-section-title">TNG Payment Details</p>
                        <div style="margin-bottom:10px;">
                            <label class="form-label-sm">Phone Number (optional)</label>
                            <input type="text" name="tng_phone" placeholder="e.g., 012-3456789" class="form-input">
                        </div>
                        <div>
                            <label class="form-label-sm">QR Code Image (optional)</label>
                            <input type="file" name="tng_qr" accept="image/*" class="form-file-input-sm">
                        </div>
                    </div>

                    <div id="bankFields" class="payment-section">
                        <p class="payment-section-title">Bank In Details</p>
                        <div style="margin-bottom:10px;">
                            <label class="form-label-sm">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="e.g., CIMB Bank" class="form-input">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label class="form-label-sm">Account Number</label>
                            <input type="text" name="bank_account" placeholder="e.g., 1234-567-890" class="form-input">
                        </div>
                        <div>
                            <label class="form-label-sm">Account Holder Name</label>
                            <input type="text" name="bank_holder" placeholder="e.g., John Doe" class="form-input">
                        </div>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-primary mt-16 w-100">Submit for Approval</button>

                <div class="text-center text-sm-muted" style="margin-top:20px;">
                    Changed your mind? <a href="AdminDashboard.php" class="text-muted-link">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($showSuccessPopup): ?>
    <div class="logout-modal-overlay">
        <div class="logout-modal-box modal-content-center">
            <div class="modal-icon-success">✓</div>
            <h3 class="modal-title">Event Submitted!</h3>
            <p class="modal-text">Your event has been submitted for review.<br>A moderator will review it before it becomes visible to students.</p>
            <button onclick="window.location.href='AdminDashboard.php'" class="btn-primary modal-btn">OK</button>
        </div>
    </div>
    <?php else: ?>
    <script>
    function togglePaymentFields() {
        const tng = document.querySelector('input[name="payment_methods[]"][value="tng"]');
        const bank = document.querySelector('input[name="payment_methods[]"][value="bank_in"]');
        document.getElementById('tngFields').style.display = tng && tng.checked ? 'block' : 'none';
        document.getElementById('bankFields').style.display = bank && bank.checked ? 'block' : 'none';
    }
    </script>
    <?php endif; ?>

</body>
</html>
