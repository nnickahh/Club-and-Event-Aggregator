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
    $clubName = $_SESSION['club_name'] ?? '';
    $message = "";
    $showSuccessPopup = false;

    function prepareUploadDirectory($relativeDir) {
        $relativeDir = trim($relativeDir, '/');
        $absoluteDir = __DIR__ . '/' . $relativeDir;

        if (!is_dir($absoluteDir) && !@mkdir($absoluteDir, 0775, true)) {
            return false;
        }

        return is_writable($absoluteDir) ? $absoluteDir . '/' : false;
    }

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

        if ($eventEndDate !== null && $eventEndDate < $eventDate) {
            $message = "<p class='msg-error'>End date is before the event date. Please choose a correct date.</p>";
        }

        $isSingleDayEvent = $eventEndDate === null || $eventEndDate === $eventDate;
        if (empty($message) && $isSingleDayEvent && $eventEndTime !== null && $eventEndTime <= $eventTime) {
            $message = "<p class='msg-error'>End time is before the event time. Please choose a correct time.</p>";
        }

        // Handle image upload
        if (empty($message) && isset($_FILES['eventImage']) && $_FILES['eventImage']['error'] === UPLOAD_ERR_OK) {
            $fileName = time() . '_' . basename($_FILES['eventImage']['name']);
            $uploadDir = prepareUploadDirectory('uploads/events');
            $relativePath = 'uploads/events/' . $fileName;
            $targetPath = $uploadDir ? $uploadDir . $fileName : '';
            $imageFileType = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));

            // Validate file type
            $allowedTypes = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            if (!in_array($imageFileType, $allowedTypes)) {
                $message = "<p class='msg-error'>Only JPG, JPEG, PNG, GIF & WEBP files are allowed.</p>";
            } elseif ($_FILES['eventImage']['size'] > 5 * 1024 * 1024) {
                $message = "<p class='msg-error'>File size must be less than 5MB.</p>";
            } elseif (!$uploadDir) {
                $message = "<p class='msg-error'>Upload folder is not writable. Please check the uploads/events folder permission.</p>";
            } elseif (move_uploaded_file($_FILES['eventImage']['tmp_name'], $targetPath)) {
                $eventImage = $relativePath;
            } else {
                $message = "<p class='msg-error'>Failed to upload image.</p>";
            }
        }

        // Payment fields
        $paymentMethods = !empty($_POST['payment_methods']) ? implode(',', $_POST['payment_methods']) : null;
        $tngPhone = null;
        $tngQr = null;
        $bankDetails = null;

        if (empty($message) && $paymentMethods && strpos($paymentMethods, 'tng') !== false) {
            $tngPhone = !empty(trim($_POST['tng_phone'])) ? trim($_POST['tng_phone']) : null;
            if ($tngPhone === null) {
                $message = "<p class='msg-error'>TNG phone number is required.</p>";
            } elseif (!ctype_digit($tngPhone)) {
                $message = "<p class='msg-error'>Only numbers allowed.</p>";
            }
            if (isset($_FILES['tng_qr']) && $_FILES['tng_qr']['error'] === UPLOAD_ERR_OK) {
                $qrName = time() . '_qr_' . basename($_FILES['tng_qr']['name']);
                $qrDir = prepareUploadDirectory('uploads/payments');
                $relativeQrPath = 'uploads/payments/' . $qrName;
                $qrPath = $qrDir ? $qrDir . $qrName : '';
                $qrType = strtolower(pathinfo($relativeQrPath, PATHINFO_EXTENSION));
                if (!in_array($qrType, ['jpg','jpeg','png','gif','webp'])) {
                    $message = "<p class='msg-error'>Only JPG, JPEG, PNG, GIF & WEBP QR files are allowed.</p>";
                } elseif (!$qrDir) {
                    $message = "<p class='msg-error'>Upload folder is not writable. Please check the uploads/payments folder permission.</p>";
                } elseif (move_uploaded_file($_FILES['tng_qr']['tmp_name'], $qrPath)) {
                    $tngQr = $relativeQrPath;
                } else {
                    $message = "<p class='msg-error'>Failed to upload Touch 'n Go QR image.</p>";
                }
            }
        }

        if (empty($message) && $paymentMethods && strpos($paymentMethods, 'bank_in') !== false) {
            $bankData = [];
            if (empty(trim($_POST['bank_name']))) {
                $message = "<p class='msg-error'>Bank name is required.</p>";
            } else {
                $bankData['bank_name'] = trim($_POST['bank_name']);
            }
            if (empty($message) && empty(trim($_POST['bank_account']))) {
                $message = "<p class='msg-error'>Bank account number is required.</p>";
            } elseif (empty($message)) {
                $bankAccount = trim($_POST['bank_account']);
                if (!ctype_digit($bankAccount)) {
                    $message = "<p class='msg-error'>Only numbers allowed.</p>";
                } else {
                    $bankData['bank_account'] = $bankAccount;
                }
            }
            if (empty($message) && empty(trim($_POST['bank_holder']))) {
                $message = "<p class='msg-error'>Account holder name is required.</p>";
            } elseif (empty($message)) {
                $bankData['bank_holder'] = trim($_POST['bank_holder']);
            }
            if (!empty($bankData)) $bankDetails = json_encode($bankData);
        }

        if (empty($message) && empty($clubName)) {
            $clubStmt = $conn->prepare("SELECT clubName FROM admins WHERE adminID = ?");
            $clubStmt->bind_param("s", $adminID);
            $clubStmt->execute();
            $clubResult = $clubStmt->get_result();
            if ($clubRow = $clubResult->fetch_assoc()) {
                $clubName = $clubRow['clubName'];
            }
            $clubStmt->close();
        }

        if (empty($message)) {
            $stmt = $conn->prepare("INSERT INTO events (adminID, clubName, eventTitle, eventDate, eventEndDate, eventTime, eventEndTime, venue, capacity, description, eventImage, status, payment_methods, tng_phone, tng_qr, bank_details, fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");

            if ($stmt) {
                $stmt->bind_param("ssssssssissssssd", $adminID, $clubName, $eventTitle, $eventDate, $eventEndDate, $eventTime, $eventEndTime, $venue, $capacity, $description, $eventImage, $paymentMethods, $tngPhone, $tngQr, $bankDetails, $fee);

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
                    <input type="date" name="eventDate" id="eventDateInput" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>End Date :</label>
                    <input type="date" name="eventEndDate" id="eventEndDateInput" min="<?php echo date('Y-m-d'); ?>" placeholder="Leave blank if the event is only one day.">
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
                            <label class="form-label-sm">Phone Number</label>
                            <input type="text" name="tng_phone" placeholder="e.g., 0123456789" inputmode="numeric" pattern="[0-9]*" oninvalid="this.setCustomValidity(this.value ? 'Only numbers allowed' : 'Please fill out this field')" oninput="this.setCustomValidity(''); this.value=this.value.replace(/[^0-9]/g,'')" class="form-input">
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
                            <input type="text" name="bank_name" placeholder="e.g., CIMB Bank" oninvalid="this.setCustomValidity('Please fill out this field')" oninput="this.setCustomValidity('')" class="form-input">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label class="form-label-sm">Account Number</label>
                            <input type="text" name="bank_account" placeholder="e.g., 1234567890" inputmode="numeric" pattern="[0-9]*" oninvalid="this.setCustomValidity(this.value ? 'Only numbers allowed' : 'Please fill out this field')" oninput="this.setCustomValidity(''); this.value=this.value.replace(/[^0-9]/g,'')" class="form-input">
                        </div>
                        <div>
                            <label class="form-label-sm">Account Holder Name</label>
                            <input type="text" name="bank_holder" placeholder="e.g., John Doe" oninvalid="this.setCustomValidity('Please fill out this field')" oninput="this.setCustomValidity('')" class="form-input">
                        </div>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-primary mt-16 w-100" onclick="return confirm('Are you sure you want to submit this event for moderator approval?')">Submit for Approval</button>

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
    const eventDateInput = document.getElementById('eventDateInput');
    const eventEndDateInput = document.getElementById('eventEndDateInput');
    const eventTimeInput = document.querySelector('input[name="eventTime"]');
    const eventEndTimeInput = document.querySelector('input[name="eventEndTime"]');

    function validateEventDateRange() {
        if (eventDateInput && eventEndDateInput && eventEndDateInput.value && eventDateInput.value && eventEndDateInput.value < eventDateInput.value) {
            eventEndDateInput.setCustomValidity('End date is before the event date. Please choose a correct date.');
        } else if (eventEndDateInput) {
            eventEndDateInput.setCustomValidity('');
        }
    }

    function validateEventTimeRange() {
        if (!eventTimeInput || !eventEndTimeInput || !eventDateInput || !eventEndDateInput) return;
        const singleDay = !eventEndDateInput.value || eventEndDateInput.value === eventDateInput.value;
        if (singleDay && eventEndTimeInput.value && eventTimeInput.value && eventEndTimeInput.value <= eventTimeInput.value) {
            eventEndTimeInput.setCustomValidity('End time is before the event time. Please choose a correct time.');
        } else {
            eventEndTimeInput.setCustomValidity('');
        }
    }

    if (eventDateInput && eventEndDateInput) {
        eventDateInput.addEventListener('change', validateEventDateRange);
        eventEndDateInput.addEventListener('change', validateEventDateRange);
        eventDateInput.addEventListener('change', validateEventTimeRange);
        eventEndDateInput.addEventListener('change', validateEventTimeRange);
    }
    if (eventTimeInput && eventEndTimeInput) {
        eventTimeInput.addEventListener('change', validateEventTimeRange);
        eventEndTimeInput.addEventListener('change', validateEventTimeRange);
    }

    function togglePaymentFields() {
        const tng = document.querySelector('input[name="payment_methods[]"][value="tng"]');
        const bank = document.querySelector('input[name="payment_methods[]"][value="bank_in"]');
        const tngPhone = document.querySelector('input[name="tng_phone"]');
        const bankName = document.querySelector('input[name="bank_name"]');
        const bankAccount = document.querySelector('input[name="bank_account"]');
        const bankHolder = document.querySelector('input[name="bank_holder"]');
        document.getElementById('tngFields').style.display = tng && tng.checked ? 'block' : 'none';
        document.getElementById('bankFields').style.display = bank && bank.checked ? 'block' : 'none';
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
    togglePaymentFields();
    </script>
    <?php endif; ?>

</body>
</html>
