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
        $stmt = $conn->prepare("SELECT e.*, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.eventID = ? AND e.status = 'approved'");
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

    // Check if already registered
    $studentID = $_SESSION['student_id'];
    $checkStmt = $conn->prepare("SELECT * FROM registrations WHERE studentID = ? AND eventID = ?");
    $checkStmt->bind_param("si", $studentID, $eventID);
    $checkStmt->execute();
    $alreadyRegistered = $checkStmt->get_result()->num_rows > 0;
    $checkStmt->close();
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
            <span class="tag tag-club"><?php echo htmlspecialchars($event['club_name'] ?? $event['clubName'] ?? 'Club'); ?></span>
            <h1 class="event-detail-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h1>
            
            <div class="event-meta event-meta-lg">
                <p><strong>Date:</strong> <?php echo date('d F Y', strtotime($event['eventDate'])); ?></p>
                <p><strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
            </div>

            <hr class="divider-light">
            
            <h3>About This Event</h3>
            <p class="event-description">
                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
            </p>

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
                    <button type="submit" name="register" class="btn-primary btn-register">
                        Register Now
                    </button>
                </form>
            <?php endif; ?>
        </article>
    </main>

</body>
</html>