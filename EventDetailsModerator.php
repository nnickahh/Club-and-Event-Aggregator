<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $currentPage = 'events';

    if (!isset($_GET['id'])) {
        header("Location: ModeratorEvents.php");
        exit();
    }
    $eventID = (int)$_GET['id'];
    $isEditing = isset($_GET['edit']) && $_GET['edit'] === '1';
    $message = '';
    $msgType = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE events SET status = 'approved' WHERE eventID = ?");
                $stmt->bind_param("i", $eventID);
                $stmt->execute();
                // Notify subscribed students
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
                    $clubName = $clubRow ? $clubRow['clubName'] : 'A club';
                    $clubID = $clubRow ? (int)($clubRow['clubID'] ?? 0) : 0;
                    $clubStmt->close();
                    $subStmt = $conn->prepare("SELECT studentID FROM club_notify WHERE adminID = ?");
                    $subStmt->bind_param("s", $eRow['adminID']);
                    $subStmt->execute();
                    $subResult = $subStmt->get_result();
                    $msg = $clubName . ' posted a new event: ' . $eRow['eventTitle'];
                    $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID, clubID) VALUES (?, ?, ?, ?)");
                    while ($sub = $subResult->fetch_assoc()) {
                        $notifStmt->bind_param("ssii", $sub['studentID'], $msg, $eventID, $clubID);
                        $notifStmt->execute();
                    }
                    $notifStmt->close();
                    $subStmt->close();
                }
                $message = 'Event has been approved successfully.';
                $msgType = 'success';
            } elseif ($action === 'decline') {
                $stmt = $conn->prepare("UPDATE events SET status = 'declined' WHERE eventID = ?");
                $stmt->bind_param("i", $eventID);
                $stmt->execute();
                $message = 'Event has been declined.';
                $msgType = 'success';
            } elseif ($action === 'update') {
                $title = $_POST['eventTitle'] ?? '';
                $date = $_POST['eventDate'] ?? '';
                $endDate = !empty(trim($_POST['eventEndDate'] ?? '')) ? trim($_POST['eventEndDate']) : null;
                $time = $_POST['eventTime'] ?? '';
                $endTime = !empty(trim($_POST['eventEndTime'] ?? '')) ? trim($_POST['eventEndTime']) : null;
                $venue = $_POST['venue'] ?? '';
                $capacity = $_POST['capacity'] ?? 0;
                $description = $_POST['description'] ?? '';
                $stmt = $conn->prepare("UPDATE events SET eventTitle=?, eventDate=?, eventEndDate=?, eventTime=?, eventEndTime=?, venue=?, capacity=?, description=? WHERE eventID=?");
                $stmt->bind_param("ssssssisi", $title, $date, $endDate, $time, $endTime, $venue, $capacity, $description, $eventID);
                $stmt->execute();
                $message = 'Event has been updated successfully.';
                $msgType = 'success';
                $isEditing = false;
            } elseif ($action === 'delete') {
                // Notify registered students
                $regStmt = $conn->prepare("SELECT studentID FROM registrations WHERE eventID = ?");
                $regStmt->bind_param("i", $eventID);
                $regStmt->execute();
                $regStudents = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $regStmt->close();

                $eStmt = $conn->prepare("SELECT eventTitle FROM events WHERE eventID = ?");
                $eStmt->bind_param("i", $eventID);
                $eStmt->execute();
                $eRow = $eStmt->get_result()->fetch_assoc();
                $eStmt->close();

                if ($eRow && !empty($regStudents)) {
                    $msg = $eRow['eventTitle'] . ' has been deleted by a moderator. If you have made any payment, please contact the club.';
                    $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
                    foreach ($regStudents as $s) {
                        $nStmt->bind_param("ssi", $s['studentID'], $msg, $eventID);
                        $nStmt->execute();
                    }
                    $nStmt->close();
                }

                // Clean up related records
                $conn->query("DELETE FROM registrations WHERE eventID = $eventID");
                $conn->query("DELETE FROM waiting_list WHERE eventID = $eventID");

                $stmt = $conn->prepare("DELETE FROM events WHERE eventID = ?");
                $stmt->bind_param("i", $eventID);
                $stmt->execute();
                $_SESSION['flash_message'] = 'Event has been deleted successfully.';
                header("Location: ModeratorEvents.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $message = 'Database error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    $event = null;
    try {
        $stmt = $conn->prepare("SELECT e.*, a.clubName AS club_name, a.name AS admin_name, a.clubEmail AS club_email FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.eventID = ?");
        $stmt->bind_param("i", $eventID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
        } else {
            die("Event not found.");
        }
    } catch (mysqli_sql_exception $e) {
        die("Database error: " . $e->getMessage());
    }

    $pendingClubs = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'pending'");
    if ($r) { $pendingClubs = $r->fetch_assoc()['c']; }

    $pendingEvents = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
    if ($r) { $pendingEvents = $r->fetch_assoc()['c']; }

    $today = date('Y-m-d');
    $eventStatus = $event['status'] ?? 'pending';

    // Fetch waiting list
    $waitStmt = $conn->prepare("SELECT w.waitID, w.studentID, w.registered_at, s.name, s.email FROM waiting_list w JOIN students s ON w.studentID = s.studentID WHERE w.eventID = ? ORDER BY w.registered_at ASC");
    $waitStmt->bind_param("i", $eventID);
    $waitStmt->execute();
    $waitlist = $waitStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $waitStmt->close();

    // Fetch registered count
    $regCntStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM registrations WHERE eventID = ?");
    $regCntStmt->bind_param("i", $eventID);
    $regCntStmt->execute();
    $regCount = (int)$regCntStmt->get_result()->fetch_assoc()['cnt'];
    $regCntStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['eventTitle']); ?> - Details</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>

    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">
        <a href="ModeratorEvents.php" class="back-link">&larr; Back to Events</a>

        <?php if ($message): ?>
            <div class="msg-banner" style="background:<?php echo $msgType === 'success' ? 'var(--green-bg)' : 'var(--red-light)'; ?>;color:<?php echo $msgType === 'success' ? 'var(--green)' : 'var(--red)'; ?>;border:1px solid <?php echo $msgType === 'success' ? 'rgba(45,125,70,0.2)' : 'rgba(237,28,36,0.2)'; ?>;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <article class="event-detail-card">
            <?php if (!empty($event['eventImage'])): ?>
                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" class="img-event-detail">
            <?php endif; ?>
            <a href="ClubDetailsModerator.php?id=<?php echo (int)$event['adminID']; ?>" class="no-deco"><span class="tag tag-club"><?php echo htmlspecialchars($event['club_name'] ?? 'Unknown Club'); ?></span></a>

            <?php if ($eventStatus === 'pending'): ?>
                <span class="mod-pending-badge mt-10"><span class="mod-badge-dot"></span>Pending review</span>
            <?php elseif ($eventStatus === 'declined'): ?>
                <span class="mod-status-tag declined mt-10">Declined</span>
            <?php else: ?>
                <span class="mod-status-tag approved mt-10">
                    <?php
                        $p = getEventPeriod($event['eventDate'], $event['eventEndDate'] ?? null, $today);
                        if ($p === 'ongoing') echo 'Ongoing';
                        elseif ($p === 'upcoming') echo 'Upcoming';
                        else echo 'Completed';
                    ?>
                </span>
            <?php endif; ?>

            <?php if ($isEditing): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update">

                    <div class="mod-form-group">
                        <label>Event Title</label>
                        <input type="text" name="eventTitle" value="<?php echo htmlspecialchars($event['eventTitle']); ?>" required>
                    </div>

                    <div class="mod-form-group">
                        <label>Date</label>
                        <input type="date" name="eventDate" value="<?php echo htmlspecialchars($event['eventDate']); ?>" required>
                    </div>
                    <div class="mod-form-group">
                        <label>End Date</label>
                        <input type="date" name="eventEndDate" value="<?php echo htmlspecialchars($event['eventEndDate'] ?? ''); ?>">
                        <small class="text-xs-muted-alt">Leave blank for single-day event.</small>
                    </div>

                    <div class="mod-form-group">
                        <label>Start Time</label>
                        <input type="time" name="eventTime" value="<?php echo htmlspecialchars($event['eventTime']); ?>" required>
                    </div>
                    <div class="mod-form-group">
                        <label>End Time</label>
                        <input type="time" name="eventEndTime" value="<?php echo htmlspecialchars($event['eventEndTime'] ?? ''); ?>">
                    </div>

                    <div class="mod-form-group">
                        <label>Venue</label>
                        <input type="text" name="venue" value="<?php echo htmlspecialchars($event['venue']); ?>" required>
                    </div>

                    <div class="mod-form-group">
                        <label>Capacity</label>
                        <input type="number" name="capacity" value="<?php echo htmlspecialchars($event['capacity']); ?>" required>
                    </div>

                    <div class="mod-form-group">
                        <label>Description</label>
                        <textarea name="description" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>

                    <div class="mod-detail-actions">
                        <button type="submit" class="btn-save">Save Changes</button>
                        <a href="EventDetailsModerator.php?id=<?php echo $eventID; ?>" class="btn-decline no-deco">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <h1 class="event-detail-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h1>

                <div class="event-meta event-meta-lg">
                    <p><strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?></p>
                    <p><strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></p>
                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                    <p><strong>Capacity:</strong> <?php echo htmlspecialchars($event['capacity']); ?> seats</p>
                    <p><strong>Fee:</strong> <?php $fee = floatval($event['fee'] ?? 0); echo $fee > 0 ? 'RM' . number_format($fee, 2) : 'Free'; ?></p>
                    <p><strong>Club:</strong> <?php echo htmlspecialchars($event['club_name'] ?? 'Unknown'); ?></p>
                    <p><strong>Club Admin:</strong> <?php echo htmlspecialchars($event['admin_name'] ?? 'Unknown'); ?></p>
                    <?php $fee = floatval($event['fee'] ?? 0); ?>
                    <?php if ($fee > 0 && !empty($event['payment_methods'])): ?>
                        <p><strong>Payment Methods:</strong>
                            <?php
                            $methods = explode(',', $event['payment_methods']);
                            $labels = ['cash'=>'Cash', 'tng'=>'TNG (Touch \'n Go)', 'bank_in'=>'Bank In'];
                            $methodTags = [];
                            foreach ($methods as $m) {
                                $methodTags[] = $labels[trim($m)] ?? trim($m);
                            }
                            echo implode(' &middot; ', $methodTags);
                            ?>
                        </p>
                        <?php if (in_array('tng', $methods) && (!empty($event['tng_phone']) || !empty($event['tng_qr']))): ?>
                            <div class="payment-box" style="margin-top:8px;">
                                <strong>TNG Details</strong><br>
                                <?php if (!empty($event['tng_phone'])): ?>Phone: <?php echo htmlspecialchars($event['tng_phone']); ?><br><?php endif; ?>
                                <?php if (!empty($event['tng_qr'])): ?><img src="<?php echo htmlspecialchars($event['tng_qr']); ?>" class="img-tng-qr"><?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <?php if (in_array('bank_in', $methods) && !empty($event['bank_details'])): ?>
                            <?php $bankData = json_decode($event['bank_details'], true); ?>
                            <?php if ($bankData): ?>
                            <div class="payment-box" style="margin-top:8px;">
                                <strong>Bank In Details</strong><br>
                                <?php if (!empty($bankData['bank_name'])): ?>Bank: <?php echo htmlspecialchars($bankData['bank_name']); ?><br><?php endif; ?>
                                <?php if (!empty($bankData['bank_account'])): ?>Account: <?php echo htmlspecialchars($bankData['bank_account']); ?><br><?php endif; ?>
                                <?php if (!empty($bankData['bank_holder'])): ?>Holder: <?php echo htmlspecialchars($bankData['bank_holder']); ?><?php endif; ?>
                            </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>

                <hr class="divider-light">

                <h3>About This Event</h3>
                <p class="event-description">
                    <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                </p>

                <hr class="divider-light">

                <h3>Registrations</h3>
                <p style="font-size:13px;color:var(--ink-2);margin-bottom:12px;">
                    <strong><?php echo $regCount; ?></strong> registered
                    &middot;
                    <strong><?php echo count($waitlist); ?></strong> on waiting list
                </p>

                <?php if (!empty($waitlist)): ?>
                <h4 style="font-size:14px;margin:16px 0 8px;color:#856404;">Waiting List</h4>
                <div class="table-responsive" style="margin-bottom:16px;">
                    <table class="part-table">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Joined</th>
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
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>

                <hr class="divider-light">

                <div class="mod-detail-actions">
                    <?php if ($eventStatus === 'pending'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn-approve">Approve Event</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to decline this event?');">
                            <input type="hidden" name="action" value="decline">
                            <button type="submit" class="btn-decline">Decline Event</button>
                        </form>
                    <?php elseif ($eventStatus === 'approved'): ?>
                        <a href="EventDetailsModerator.php?id=<?php echo $eventID; ?>&edit=1" class="btn-edit">Edit Event</a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this event? This cannot be undone.');">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-delete">Delete Event</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    </main>
</body>
</html>
