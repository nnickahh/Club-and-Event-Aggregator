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
        $stmt = $conn->prepare("SELECT e.*, a.clubName AS club_name, c.clubID FROM events e LEFT JOIN admins a ON e.adminID = a.adminID LEFT JOIN clubs c ON c.adminID = a.adminID WHERE e.eventID = ? AND e.status = 'approved'");
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

    // Check if already registered
    $studentID = $_SESSION['student_id'];
    $checkStmt = $conn->prepare("SELECT * FROM registrations WHERE studentID = ? AND eventID = ?");
    $checkStmt->bind_param("si", $studentID, $eventID);
    $checkStmt->execute();
    $alreadyRegistered = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();

    $showPopup = isset($_GET['registered']) && $_GET['registered'] === '1';
    $showCancelPopup = isset($_GET['cancelled']) && $_GET['cancelled'] === '1';
    $showCancelError = isset($_GET['cancelled']) && $_GET['cancelled'] === '0';
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
                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;max-height:320px;object-fit:cover;border-radius:8px;margin-bottom:20px;">
            <?php endif; ?>
            <a href="ClubsDetails.php?id=<?php echo (int)($event['clubID'] ?? 0); ?>" style="text-decoration:none;"><span class="tag tag-club"><?php echo htmlspecialchars($event['club_name'] ?? $event['clubName'] ?? 'Club'); ?></span></a>
            <h1 class="event-detail-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h1>
            
            <div class="event-meta event-meta-lg">
                <p><strong>Date:</strong> <?php echo date('d F Y', strtotime($event['eventDate'])); ?></p>
                <p><strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                <p><strong>Fee:</strong> <?php echo $fee > 0 ? 'RM' . number_format($fee, 2) : 'Free'; ?></p>
            </div>

            <hr class="divider-light">
            
            <h3>About This Event</h3>
            <p class="event-description">
                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
            </p>

            <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                <hr class="divider-light">
                <h3>Payment Methods</h3>
                <?php
                $methods = explode(',', $event['payment_methods']);
                $labels = ['cash'=>'Cash', 'tng'=>'TNG (Touch \'n Go)', 'bank_in'=>'Bank In'];
                ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:12px;">
                    <?php foreach ($methods as $m): ?>
                        <span style="background:#f1f5f9;padding:6px 14px;border-radius:20px;font-size:13px;font-weight:500;"><?php echo $labels[trim($m)] ?? trim($m); ?></span>
                    <?php endforeach; ?>
                </div>
                <?php if (in_array('tng', $methods) && (!empty($event['tng_phone']) || !empty($event['tng_qr']))): ?>
                    <div style="padding:14px;background:#f0f9ff;border-radius:8px;font-size:13px;margin-bottom:12px;">
                        <strong style="color:#0369a1;">TNG Details</strong><br>
                        <?php if (!empty($event['tng_phone'])): ?>Phone: <?php echo htmlspecialchars($event['tng_phone']); ?><br><?php endif; ?>
                        <?php if (!empty($event['tng_qr'])): ?><img src="<?php echo htmlspecialchars($event['tng_qr']); ?>" style="max-width:160px;margin-top:6px;border-radius:6px;"><?php endif; ?>
                    </div>
                <?php endif; ?>
                <?php if (in_array('bank_in', $methods) && !empty($event['bank_details'])): ?>
                    <?php $bankData = json_decode($event['bank_details'], true); ?>
                    <?php if ($bankData): ?>
                    <div style="padding:14px;background:#f0f9ff;border-radius:8px;font-size:13px;margin-bottom:12px;">
                        <strong style="color:#0369a1;">Bank In Details</strong><br>
                        <?php if (!empty($bankData['bank_name'])): ?>Bank: <?php echo htmlspecialchars($bankData['bank_name']); ?><br><?php endif; ?>
                        <?php if (!empty($bankData['bank_account'])): ?>Account: <?php echo htmlspecialchars($bankData['bank_account']); ?><br><?php endif; ?>
                        <?php if (!empty($bankData['bank_holder'])): ?>Holder: <?php echo htmlspecialchars($bankData['bank_holder']); ?><?php endif; ?>
                    </div>
                    <?php endif; ?>
                <?php endif; ?>
                <?php endif; ?>
            <?php if ($alreadyRegistered): ?>
                <div style="background:#f0fdf4;border:1px solid #86efac;border-radius:8px;padding:16px;text-align:center;margin-top:24px;">
                    <span style="font-size:16px;font-weight:700;color:#16a34a;">✓ You are registered for this event</span>
                </div>
                <div style="text-align:center;margin-top:12px;">
                    <form action="CancelRegistration.php" method="POST" onsubmit="return confirm('Cancel your registration for this event?');">
                        <input type="hidden" name="eventID" value="<?php echo $event['eventID']; ?>">
                        <button type="submit" class="btn-primary btn-cancel" style="background:#dc2626;font-size:13px;padding:8px 18px;">Cancel Registration</button>
                    </form>
                </div>
            <?php else: ?>
                <form action="RegisterEvent.php" method="POST">
                    <input type="hidden" name="event_id" value="<?php echo $event['eventID']; ?>">
                    <div style="margin-bottom:16px;">
                        <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                        <label style="font-size:14px;font-weight:600;display:block;margin-bottom:6px;">Payment Method</label>
                        <select name="payment_method" style="width:100%;padding:10px 14px;border:1px solid var(--border);border-radius:var(--radius-md);font-size:14px;box-sizing:border-box;">
                            <option value="">Select payment method</option>
                            <?php
                            $methods = explode(',', $event['payment_methods']);
                            $labels = ['cash'=>'Cash', 'tng'=>'TNG (Touch \'n Go)', 'bank_in'=>'Bank In'];
                            foreach ($methods as $m): $m = trim($m);
                            ?>
                            <option value="<?php echo htmlspecialchars($m); ?>"><?php echo $labels[$m] ?? $m; ?></option>
                            <?php endforeach; ?>
                        </select>
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
    <div class="logout-modal-overlay" style="display:flex;">
        <div class="logout-modal-box" style="text-align:center;padding:40px 36px;">
            <div style="width:56px;height:56px;border-radius:50%;background:#fef2f2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 16px;">✕</div>
            <h3 style="margin:0 0 8px;font-size:18px;">Registration Cancelled</h3>
            <p style="font-size:14px;color:#666;margin:0 0 24px;">Your registration for this event has been cancelled.</p>
            <button onclick="window.location.href='DetailedEvent.php?id=<?php echo (int)$eventID; ?>'" class="btn-primary" style="padding:10px 32px;border:none;cursor:pointer;">OK</button>
        </div>
    </div>
    <?php elseif ($showCancelError): ?>
    <div class="logout-modal-overlay" style="display:flex;">
        <div class="logout-modal-box" style="text-align:center;padding:40px 36px;">
            <div style="width:56px;height:56px;border-radius:50%;background:#fef2f2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 16px;">!</div>
            <h3 style="margin:0 0 8px;font-size:18px;">Cancellation Failed</h3>
            <p style="font-size:14px;color:#666;margin:0 0 24px;">Something went wrong. Please try again.</p>
            <button onclick="window.location.href='DetailedEvent.php?id=<?php echo (int)$eventID; ?>'" class="btn-primary" style="padding:10px 32px;border:none;cursor:pointer;">OK</button>
        </div>
    </div>
    <?php endif; ?>
    <?php if ($showPopup): ?>
    <div class="logout-modal-overlay" style="display:flex;">
        <div class="logout-modal-box" style="text-align:center;padding:40px 36px;">
            <div style="width:56px;height:56px;border-radius:50%;background:#dcfce7;color:#16a34a;display:flex;align-items:center;justify-content:center;font-size:28px;font-weight:700;margin:0 auto 16px;">✓</div>
            <h3 style="margin:0 0 8px;font-size:18px;">Registered Successfully!</h3>
            <p style="font-size:14px;color:#666;margin:0 0 24px;">You have successfully registered for this event.</p>
            <button onclick="window.location.href='DetailedEvent.php?id=<?php echo (int)$eventID; ?>'" class="btn-primary" style="padding:10px 32px;border:none;cursor:pointer;">OK</button>
        </div>
    </div>
    <?php endif; ?>
</body>
</html>