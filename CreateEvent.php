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
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Only JPG, JPEG, PNG, GIF & WEBP files are allowed.</p>";
            } elseif ($_FILES['eventImage']['size'] > 5 * 1024 * 1024) {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>File size must be less than 5MB.</p>";
            } elseif (move_uploaded_file($_FILES['eventImage']['tmp_name'], $targetPath)) {
                $eventImage = $targetPath;
            } else {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Failed to upload image.</p>";
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
            $stmt = $conn->prepare("INSERT INTO events (adminID, eventTitle, eventDate, eventTime, eventEndTime, venue, capacity, description, eventImage, status, payment_methods, tng_phone, tng_qr, bank_details, fee) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, ?, ?)");

            if ($stmt) {
                $stmt->bind_param("ssssssissssssd", $adminID, $eventTitle, $eventDate, $eventTime, $eventEndTime, $venue, $capacity, $description, $eventImage, $paymentMethods, $tngPhone, $tngQr, $bankDetails, $fee);

                if ($stmt->execute()) {
                    $showSuccessPopup = true;
                } else {
                    $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Error creating event. Please try again.</p>";
                }
                $stmt->close();
            } else {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Database preparation failed.</p>";
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
                    <label>Event Time Schedule :</label>
                    <div style="display:flex;gap:10px;align-items:center;">
                        <input type="time" name="eventTime" required style="flex:1;">
                        <span style="font-weight:600;color:var(--ink-3,#888);">—</span>
                        <input type="time" name="eventEndTime" style="flex:1;">
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
                    <textarea name="description" placeholder="Provide event context guidelines or register rules..." required style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid var(--border, rgba(0,0,0,0.15)); border-radius: 6px; box-sizing: border-box; font-family: inherit; resize: vertical;"></textarea>
                </div>

                <div class="form-group">
                    <label>Event Fee (optional) :</label>
                    <input type="number" name="fee" min="0" step="0.01" placeholder="0.00 (free)" style="width:100%;padding:11px 14px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;font-size:14px;">
                </div>

                <div class="form-group">
                    <label>Event Poster (optional) :</label>
                    <input type="file" name="eventImage" accept="image/jpeg,image/png,image/gif,image/webp" style="width: 100%; padding: 8px 0;">
                </div>

                <div class="form-group">
                    <label>Payment Methods (optional) :</label>
                    <div style="margin-top:6px;text-align:left;">
                        <label style="display:block;cursor:pointer;font-size:14px;margin-bottom:8px;font-weight:400;">
                            <input type="checkbox" name="payment_methods[]" value="cash" onchange="togglePaymentFields()" style="width:auto;margin:0;vertical-align:middle;"> Cash
                        </label>
                        <label style="display:block;cursor:pointer;font-size:14px;margin-bottom:8px;font-weight:400;">
                            <input type="checkbox" name="payment_methods[]" value="tng" onchange="togglePaymentFields()" style="width:auto;margin:0;vertical-align:middle;"> TNG (Touch 'n Go)
                        </label>
                        <label style="display:block;cursor:pointer;font-size:14px;font-weight:400;">
                            <input type="checkbox" name="payment_methods[]" value="bank_in" onchange="togglePaymentFields()" style="width:auto;margin:0;vertical-align:middle;"> Bank In
                        </label>
                    </div>

                    <div id="tngFields" style="display:none;margin-top:12px;padding:14px;background:#f9f9f9;border-radius:8px;">
                        <p style="font-size:13px;font-weight:600;margin:0 0 10px;color:var(--ink-2);">TNG Payment Details</p>
                        <div style="margin-bottom:10px;">
                            <label style="font-size:12px;display:block;margin-bottom:4px;">Phone Number (optional)</label>
                            <input type="text" name="tng_phone" placeholder="e.g., 012-3456789" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;font-size:13px;">
                        </div>
                        <div>
                            <label style="font-size:12px;display:block;margin-bottom:4px;">QR Code Image (optional)</label>
                            <input type="file" name="tng_qr" accept="image/*" style="width:100%;padding:6px 0;font-size:13px;">
                        </div>
                    </div>

                    <div id="bankFields" style="display:none;margin-top:12px;padding:14px;background:#f9f9f9;border-radius:8px;">
                        <p style="font-size:13px;font-weight:600;margin:0 0 10px;color:var(--ink-2);">Bank In Details</p>
                        <div style="margin-bottom:10px;">
                            <label style="font-size:12px;display:block;margin-bottom:4px;">Bank Name</label>
                            <input type="text" name="bank_name" placeholder="e.g., CIMB Bank" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;font-size:13px;">
                        </div>
                        <div style="margin-bottom:10px;">
                            <label style="font-size:12px;display:block;margin-bottom:4px;">Account Number</label>
                            <input type="text" name="bank_account" placeholder="e.g., 1234-567-890" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;font-size:13px;">
                        </div>
                        <div>
                            <label style="font-size:12px;display:block;margin-bottom:4px;">Account Holder Name</label>
                            <input type="text" name="bank_holder" placeholder="e.g., John Doe" style="width:100%;padding:9px 12px;border:1px solid var(--border);border-radius:6px;box-sizing:border-box;font-size:13px;">
                        </div>
                    </div>
                </div>

                <button type="submit" name="submit" class="btn-primary" style="margin-top: 15px; width: 100%;">Submit for Approval</button>

                <div style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--ink-3, #888);">
                    Changed your mind? <a href="AdminDashboard.php" style="color: var(--red, #ED1C24); text-decoration: none; font-weight: 600;">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

    <?php if ($showSuccessPopup): ?>
    <div class="logout-modal-overlay" style="display:flex;">
        <div class="logout-modal-box" style="text-align:center;padding:40px 36px;">
            <div style="width:56px;height:56px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 16px;">✓</div>
            <h3 style="margin:0 0 8px;font-size:18px;">Event Submitted!</h3>
            <p style="font-size:14px;color:#666;margin:0 0 24px;">Your event has been submitted for review.<br>A moderator will review it before it becomes visible to students.</p>
            <button onclick="window.location.href='AdminDashboard.php'" class="btn-primary" style="padding:10px 32px;border:none;cursor:pointer;">OK</button>
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
