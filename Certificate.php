<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
    header("Location: StudentLogin.php");
    exit();
}

$studentID = $_SESSION['student_id'];
$eventID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($eventID <= 0) {
    die("Invalid certificate request.");
}

$stmt = $conn->prepare("
    SELECT e.eventID, e.eventTitle, e.eventDate, e.eventEndDate, e.venue,
           a.clubName, s.studentID, s.name AS studentName, r.attendance_status
    FROM registrations r
    JOIN events e ON r.eventID = e.eventID
    JOIN students s ON r.studentID = s.studentID
    LEFT JOIN admins a ON e.adminID = a.adminID
    WHERE r.eventID = ?
      AND r.studentID = ?
      AND r.attendance_status = 'present'
      AND ((e.status = 'approved' AND COALESCE(e.eventEndDate, e.eventDate) < CURDATE()) OR e.status = 'ended')
    LIMIT 1
");
$stmt->bind_param("is", $eventID, $studentID);
$stmt->execute();
$certificate = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$certificate) {
    die("Certificate is only available after your attendance has been marked as attended.");
}

$eventDate = formatDateRange($certificate['eventDate'], $certificate['eventEndDate'] ?? null);
$issuedDate = date('d M Y');
$certificateNo = 'CERT-' . (int)$certificate['eventID'] . '-' . preg_replace('/[^A-Za-z0-9]/', '', $certificate['studentID']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Participation Certificate</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body class="certificate-page">
    <main class="certificate-shell">
        <div class="certificate-actions no-print">
            <a href="MyEvent.php?tab=history" class="btn-sm btn-sm-outline">Back</a>
            <button type="button" class="btn-primary-sm" onclick="window.print()">Download / Print Certificate</button>
        </div>

        <section class="certificate-card">
            <div class="certificate-border">
                <div class="certificate-topline">
                    <span>INTI Campus Event System</span>
                    <span><?php echo htmlspecialchars($certificateNo); ?></span>
                </div>
                <p class="certificate-kicker">Certificate of Participation</p>
                <h1><?php echo htmlspecialchars($certificate['studentName']); ?></h1>
                <p class="certificate-text">has successfully participated in</p>
                <h2><?php echo htmlspecialchars($certificate['eventTitle']); ?></h2>
                <p class="certificate-meta">
                    Organized by <?php echo htmlspecialchars($certificate['clubName'] ?? 'Campus Club'); ?><br>
                    <?php echo htmlspecialchars($eventDate); ?> · <?php echo htmlspecialchars($certificate['venue']); ?>
                </p>
                <div class="certificate-footer">
                    <div>
                        <span>Issued Date</span>
                        <strong><?php echo htmlspecialchars($issuedDate); ?></strong>
                    </div>
                    <div class="certificate-signature">
                        <strong><?php echo htmlspecialchars($certificate['clubName'] ?? 'Campus Club'); ?></strong>
                        <span>Authorized Organizer</span>
                    </div>
                    <div>
                        <span>Certificate No.</span>
                        <strong><?php echo htmlspecialchars($certificateNo); ?></strong>
                    </div>
                </div>
            </div>
        </section>
    </main>
</body>
</html>
