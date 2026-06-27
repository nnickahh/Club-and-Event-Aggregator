<?php
    session_start();
    require_once 'db_connect.php';

    // 1. Security Check: Only logged-in students
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    // 2. Get the specific Event ID from the URL (e.g., DetailedEvent.php?id=5)
    if (isset($_GET['id'])) {
        $eventID = $_GET['id'];

        // 3. Fetch specific event details using Prepared Statement
        $stmt = $conn->prepare("SELECT e.*, a.clubName AS club_name, c.clubID FROM events e LEFT JOIN admins a ON e.adminID = a.adminID LEFT JOIN clubs c ON c.clubID = (SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1) WHERE e.eventID = ?");
        $stmt->bind_param("i", $eventID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
        } else {
            die("Event not found.");
        }
    } else {
        header("Location: StudentEvents.php");
        exit();
    }

    $fee = floatval($event['fee'] ?? 0);
    $paymentMethods = array_filter(array_map('trim', explode(',', $event['payment_methods'] ?? '')));
    $paymentLabels = ['cash'=>'Cash', 'tng'=>'TNG (Touch \'n Go)', 'bank_in'=>'Bank'];
    $bankData = !empty($event['bank_details']) ? json_decode($event['bank_details'], true) : null;

    // Check if already registered
    $studentID = $_SESSION['student_id'];
    $checkStmt = $conn->prepare("SELECT * FROM registrations WHERE studentID = ? AND eventID = ?");
    $checkStmt->bind_param("si", $studentID, $eventID);
    $checkStmt->execute();
    $alreadyRegistered = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    // Check if on waiting list
    $waitCheck = $conn->prepare("SELECT * FROM waiting_list WHERE studentID = ? AND eventID = ?");
    $waitCheck->bind_param("si", $studentID, $eventID);
    $waitCheck->execute();
    $onWaitlist = $waitCheck->get_result()->num_rows > 0;
    $waitCheck->close();

    // Get registration count and capacity
    $regCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM registrations WHERE eventID = ?");
    $regCountStmt->bind_param("i", $eventID);
    $regCountStmt->execute();
    $regCount = (int)$regCountStmt->get_result()->fetch_assoc()['cnt'];
    $regCountStmt->close();
    $capacity = (int)$event['capacity'];
    $isFull = $regCount >= $capacity;

    $showPopup = isset($_GET['registered']) && $_GET['registered'] === '1';
    $showCancelPopup = isset($_GET['cancelled']) && $_GET['cancelled'] === '1';
    $showCancelError = isset($_GET['cancelled']) && $_GET['cancelled'] === '0';
    $showWaitlistPopup = isset($_GET['waitlisted']) && $_GET['waitlisted'] === '1';
    $showOnWaitlist = isset($_GET['on_waitlist']) && $_GET['on_waitlist'] === '1';
    $paymentError = isset($_GET['payment_error']);
    $paidRequired = isset($_GET['paid_required']);
    $receiptRequired = isset($_GET['receipt_required']);
    $receiptError = isset($_GET['receipt_error']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($event['eventTitle']); ?> - Details</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>

    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <a href="StudentEvents.php" class="back-link">&larr; Back to Events</a>
        
        <article class="event-detail-card">
            <?php if (!empty($event['eventImage'])): ?>
                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event poster" class="img-event-detail clickable-poster" onclick="openEventPosterViewer(this.src)">
            <?php endif; ?>
            <a href="ClubsDetails.php?id=<?php echo (int)($event['clubID'] ?? 0); ?>" class="no-deco"><span class="tag tag-club"><?php echo htmlspecialchars($event['club_name'] ?? $event['clubName'] ?? 'Club'); ?></span></a>
            <h1 class="event-detail-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h1>
            
            <?php if ($event['status'] === 'cancelled'): ?>
                <div class="msg-banner" style="background:#fee;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:12px 18px;margin-bottom:16px;font-size:14px;font-weight:600;">This event has been cancelled.</div>
            <?php endif; ?>
            
            <div class="event-meta event-meta-lg">
                <p><strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?></p>
                <p><strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                <p><strong>Fee:</strong> <?php echo $fee > 0 ? 'RM' . number_format($fee, 2) : 'Free'; ?></p>
                <p><strong>Capacity:</strong> <?php echo $regCount; ?> / <?php echo $capacity; ?> registered
                    <?php if ($isFull): ?><span style="color:#dc2626;font-weight:600;"> (Full)</span><?php endif; ?>
                </p>
            </div>

            <hr class="divider-light">
            
            <h3>About This Event</h3>
            <p class="event-description">
                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
            </p>

            <?php if ($event['status'] === 'cancelled'): ?>
                <div class="msg-banner" style="background:#fee;color:#b91c1c;border:1px solid #fecaca;border-radius:8px;padding:12px 18px;font-size:14px;font-weight:600;text-align:center;">Registration is closed for this cancelled event.</div>
            <?php elseif ($alreadyRegistered): ?>
                <div class="registered-banner">
                    <span class="registered-text">✓ You are registered for this event</span>
                </div>
                <div class="text-center mt-12">
                    <form action="CancelRegistration.php" method="POST" onsubmit="return confirm('Cancel your registration for this event?');">
                        <input type="hidden" name="eventID" value="<?php echo $event['eventID']; ?>">
                        <button type="submit" class="btn-primary btn-cancel btn-primary-sm">Cancel Registration</button>
                    </form>
                </div>
            <?php elseif ($onWaitlist): ?>
                <div class="registered-banner" style="background:#fff8e1;border-color:#ffe082;">
                    <span class="registered-text" style="color:#f57f17;">⏳ You are on the waiting list</span>
                </div>
            <?php elseif ($isFull): ?>
                <div class="text-center mt-16">
                    <p style="color:#dc2626;font-weight:600;margin-bottom:12px;">This event is full.</p>
                    <form action="RegisterEvent.php" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="event_id" value="<?php echo $event['eventID']; ?>">
                        <?php if ($fee > 0): ?>
                            <p class="payment-waitlist-note">Payment is only needed after you successfully get a spot in this event.</p>
                        <?php endif; ?>
                        <button type="submit" name="register" class="btn-primary btn-register" style="background:#f57f17;">Join Waiting List</button>
                    </form>
                </div>
            <?php else: ?>
                <form action="RegisterEvent.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="event_id" value="<?php echo $event['eventID']; ?>">
                    <div class="mb-16">
                        <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                        <?php if ($paymentError || $paidRequired || $receiptRequired || $receiptError): ?>
                            <p class="msg-error">
                                <?php
                                    if ($paidRequired) echo 'Please tick Paid before uploading a receipt.';
                                    elseif ($receiptRequired) echo 'Please upload a receipt image when marking TNG or Bank In as paid.';
                                    elseif ($receiptError) echo 'Receipt upload failed. Please upload a JPG, PNG, GIF, or WEBP image under 5MB.';
                                    else echo 'Please select a valid payment method.';
                                ?>
                            </p>
                        <?php endif; ?>
                        <label class="form-label-md">Payment Method</label>
                        <select name="payment_method" class="form-select payment-method-select">
                            <option value="">Select Payment Method</option>
                            <?php foreach ($paymentMethods as $m): ?>
                            <option value="<?php echo htmlspecialchars($m); ?>"><?php echo $paymentLabels[$m] ?? $m; ?></option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (in_array('tng', $paymentMethods, true) && (!empty($event['tng_phone']) || !empty($event['tng_qr']))): ?>
                        <div class="payment-box payment-detail-box" data-method="tng">
                            <strong>TNG Details</strong><br>
                            <?php if (!empty($event['tng_phone'])): ?><span class="payment-phone-number">Phone Number: <?php echo htmlspecialchars($event['tng_phone']); ?></span><br><?php endif; ?>
                            <?php if (!empty($event['tng_qr'])): ?><img src="<?php echo htmlspecialchars($event['tng_qr']); ?>" class="img-tng-qr" alt="TNG QR code"><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <?php if (in_array('bank_in', $paymentMethods, true) && $bankData): ?>
                        <div class="payment-box payment-detail-box" data-method="bank_in">
                            <strong>Bank Details</strong><br>
                            <?php if (!empty($bankData['bank_name'])): ?>Bank: <?php echo htmlspecialchars($bankData['bank_name']); ?><br><?php endif; ?>
                            <?php if (!empty($bankData['bank_account'])): ?>Account: <?php echo htmlspecialchars($bankData['bank_account']); ?><br><?php endif; ?>
                            <?php if (!empty($bankData['bank_holder'])): ?>Holder: <?php echo htmlspecialchars($bankData['bank_holder']); ?><?php endif; ?>
                        </div>
                        <?php endif; ?>
                        <div class="payment-proof-fields">
                            <label class="payment-paid-row">
                                <span>Paid</span>
                                <input type="checkbox" name="payment_paid" value="1">
                            </label>
                            <label class="form-label-md">Upload Receipt</label>
                            <input type="file" name="payment_receipt" accept="image/*" class="form-input" disabled>
                            <small class="text-xs-muted-alt">For TNG or Bank only. Cash payment will be confirmed manually by the admin.</small>
                        </div>
                        <?php endif; ?>
                    </div>
                    <button type="submit" name="register" class="btn-primary btn-register">
                        Register Now
                    </button>
                </form>
            <?php endif; ?>
        </article>
    </main>

    <?php if ($showCancelPopup): ?>
    <div class="logout-modal-overlay">
        <div class="logout-modal-box modal-content-center">
            <div class="modal-icon-error">✕</div>
            <h3 class="modal-title">Registration Cancelled</h3>
            <p class="modal-text">Your registration for this event has been cancelled.</p>
            <button onclick="window.location.href='DetailedEvent.php?id=<?php echo (int)$eventID; ?>'" class="btn-primary modal-btn">OK</button>
        </div>
    </div>
    <?php elseif ($showCancelError): ?>
    <div class="logout-modal-overlay">
        <div class="logout-modal-box modal-content-center">
            <div class="modal-icon-error">!</div>
            <h3 class="modal-title">Cancellation Failed</h3>
            <p class="modal-text">Something went wrong. Please try again.</p>
            <button onclick="window.location.href='DetailedEvent.php?id=<?php echo (int)$eventID; ?>'" class="btn-primary modal-btn">OK</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($showPopup): ?>
    <div class="logout-modal-overlay">
        <div class="logout-modal-box modal-content-center">
            <div class="modal-icon-success">✓</div>
            <h3 class="modal-title">Registered Successfully!</h3>
            <p class="modal-text">You have successfully registered for this event.</p>
            <button onclick="window.location.href='DetailedEvent.php?id=<?php echo (int)$eventID; ?>'" class="btn-primary modal-btn">OK</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($showWaitlistPopup): ?>
    <div class="logout-modal-overlay">
        <div class="logout-modal-box modal-content-center">
            <div class="modal-icon-waitlist" style="background:#f57f17;">⏳</div>
            <h3 class="modal-title">Added to Waiting List</h3>
            <p class="modal-text">The event is full. You have been added to the waiting list. You'll be notified if a spot opens up.</p>
            <button onclick="window.location.href='StudentDashboard.php'" class="btn-primary modal-btn">OK</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($showOnWaitlist): ?>
    <div class="logout-modal-overlay">
        <div class="logout-modal-box modal-content-center">
            <div class="modal-icon-waitlist" style="background:#f57f17;">⏳</div>
            <h3 class="modal-title">Already on Waiting List</h3>
            <p class="modal-text">You are already on the waiting list for this event.</p>
            <button onclick="window.location.href='DetailedEvent.php?id=<?php echo (int)$eventID; ?>'" class="btn-primary modal-btn">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <div id="eventPosterViewer" class="avatar-viewer-modal" onclick="closeEventPosterViewer()">
        <span class="avatar-viewer-close">&times;</span>
        <img class="avatar-viewer-image" id="eventPosterViewerImage" alt="Full event poster">
    </div>

    <script>
        function openEventPosterViewer(src) {
            document.getElementById('eventPosterViewerImage').src = src;
            document.getElementById('eventPosterViewer').classList.add('active');
        }

        function closeEventPosterViewer() {
            document.getElementById('eventPosterViewer').classList.remove('active');
        }

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventPosterViewer();
            }
        });

        document.querySelectorAll('.payment-method-select').forEach(select => {
            const form = select.closest('form');
            const proofFields = form ? form.querySelector('.payment-proof-fields') : null;
            const receiptInput = form ? form.querySelector('input[name="payment_receipt"]') : null;
            const paidCheckbox = form ? form.querySelector('input[name="payment_paid"]') : null;

            function updateReceiptInput() {
                if (!receiptInput || !paidCheckbox) return;
                receiptInput.disabled = !paidCheckbox.checked;
                if (!paidCheckbox.checked) receiptInput.value = '';
            }

            function updatePaymentDetails() {
                const selected = select.value;

                if (form) {
                    form.querySelectorAll('.payment-detail-box').forEach(box => {
                        box.style.display = box.dataset.method === selected ? 'block' : 'none';
                    });
                }

                if (proofFields) {
                    const needsProof = selected === 'tng' || selected === 'bank_in';
                    proofFields.style.display = needsProof ? 'block' : 'none';
                    if (!needsProof) {
                        proofFields.querySelector('input[name="payment_paid"]').checked = false;
                        if (receiptInput) receiptInput.value = '';
                    }
                    updateReceiptInput();
                }
            }

            if (paidCheckbox) paidCheckbox.addEventListener('change', updateReceiptInput);
            select.addEventListener('change', updatePaymentDetails);
            updatePaymentDetails();
        });
    </script>
</body>
</html>
