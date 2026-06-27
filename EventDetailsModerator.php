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
                $stmt = $conn->prepare("UPDATE events SET status = 'approved', decline_reason = NULL WHERE eventID = ?");
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

                    $adminMsg = $eRow['eventTitle'] . ' has been approved/re-approved by the moderator.';
                    $adminStmt = $conn->prepare("INSERT INTO notifications (adminID, message, eventID) VALUES (?, ?, ?)");
                    $adminStmt->bind_param("ssi", $eRow['adminID'], $adminMsg, $eventID);
                    $adminStmt->execute();
                    $adminStmt->close();
                }
                $message = 'Event has been approved successfully.';
                $msgType = 'success';
            } elseif ($action === 'decline') {
                $declineReason = trim($_POST['decline_reason'] ?? '');
                $eStmt = $conn->prepare("SELECT eventTitle, adminID FROM events WHERE eventID = ?");
                $eStmt->bind_param("i", $eventID);
                $eStmt->execute();
                $eRow = $eStmt->get_result()->fetch_assoc();
                $eStmt->close();
                if ($eRow) {
                    // Save decline reason and keep as pending (admin can resubmit)
                    $rStmt = $conn->prepare("UPDATE events SET status = 'pending', decline_reason = ? WHERE eventID = ?");
                    $rStmt->bind_param("si", $declineReason, $eventID);
                    $rStmt->execute();
                    $rStmt->close();
                    // Notify admin
                    $reasonText = $declineReason ? " Reason: " . $declineReason : '';
                    $adminMsg = $eRow['eventTitle'] . ' requires changes.' . $reasonText;
                    $aStmt = $conn->prepare("INSERT INTO notifications (adminID, message, eventID) VALUES (?, ?, ?)");
                    $aStmt->bind_param("ssi", $eRow['adminID'], $adminMsg, $eventID);
                    $aStmt->execute();
                    $aStmt->close();
                }
                $message = 'Event has been declined. The admin can resubmit after making changes.';
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
                $deleteReason = trim($_POST['delete_reason'] ?? '');

                // Notify registered and waitlisted students
                $regStmt = $conn->prepare("SELECT studentID FROM registrations WHERE eventID = ? UNION SELECT studentID FROM waiting_list WHERE eventID = ?");
                $regStmt->bind_param("ii", $eventID, $eventID);
                $regStmt->execute();
                $regStudents = $regStmt->get_result()->fetch_all(MYSQLI_ASSOC);
                $regStmt->close();

                $eStmt = $conn->prepare("SELECT eventTitle, adminID FROM events WHERE eventID = ?");
                $eStmt->bind_param("i", $eventID);
                $eStmt->execute();
                $eRow = $eStmt->get_result()->fetch_assoc();
                $eStmt->close();

                if ($eRow) {
                    $title = $eRow['eventTitle'];
                    $reasonText = $deleteReason !== '' ? ' Reason: ' . $deleteReason : '';
                    if (!empty($regStudents)) {
                        $msg = $title . ' has been deleted/cancelled by a moderator.' . $reasonText . ' If you have made any payment, please contact the club.';
                        $nStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message) VALUES (?, ?)");
                        foreach ($regStudents as $s) {
                            $nStmt->bind_param("ss", $s['studentID'], $msg);
                            $nStmt->execute();
                        }
                        $nStmt->close();
                    }
                    // Notify admin
                    $adminMsg = $title . ' has been deleted/cancelled by a moderator.' . $reasonText;
                    $adminStmt = $conn->prepare("INSERT INTO notifications (adminID, message) VALUES (?, ?)");
                    $adminStmt->bind_param("ss", $eRow['adminID'], $adminMsg);
                    $adminStmt->execute();
                    $adminStmt->close();

                    // Notify moderators
                    $modMsg = $title . ' has been deleted/cancelled.' . $reasonText;
                    $modStmt = $conn->prepare("INSERT INTO moderator_notifications (message) VALUES (?)");
                    $modStmt->bind_param("s", $modMsg);
                    $modStmt->execute();
                    $modStmt->close();
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
                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event poster" class="img-event-detail clickable-poster" onclick="openEventPosterViewer(this.src)">
            <?php endif; ?>
            <a href="ClubDetailsModerator.php?id=<?php echo urlencode($event['adminID']); ?>" class="no-deco"><span class="tag tag-club"><?php echo htmlspecialchars($event['club_name'] ?? 'Unknown Club'); ?></span></a>

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

                <?php if (!empty($event['decline_reason'])): ?>
                <div style="background:var(--red-light);padding:12px 16px;border-radius:8px;margin-top:12px;">
                    <strong style="color:var(--red);">Previously declined reason:</strong>
                    <p style="color:var(--red);margin:4px 0 0;font-size:13px;"><?php echo nl2br(htmlspecialchars($event['decline_reason'])); ?></p>
                </div>
                <?php endif; ?>

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
                        <button type="button" class="btn-decline" onclick="openDeclineModal()">Decline Event</button>
                    <?php elseif ($eventStatus === 'approved'): ?>
                        <a href="EventDetailsModerator.php?id=<?php echo $eventID; ?>&edit=1" class="btn-edit">Edit Event</a>
                        <button type="button" class="btn-delete" onclick="openDeleteModal()">Delete Event</button>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    </main>

    <div id="eventPosterViewer" class="avatar-viewer-modal" onclick="closeEventPosterViewer()">
        <span class="avatar-viewer-close">&times;</span>
        <img class="avatar-viewer-image" id="eventPosterViewerImage" alt="Full event poster">
    </div>

    <!-- Decline Reason Modal -->
    <div id="declineModal" class="modal-overlay" onclick="if(event.target===this)closeDeclineModal()">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button type="button" class="modal-close" onclick="closeDeclineModal()">&times;</button>
            <h3>Decline Event</h3>
            <p style="font-size:13px;color:var(--ink-2);margin-bottom:12px;">Provide a reason for declining this event. The club admin will be notified.</p>
            <form method="POST" id="declineForm">
                <input type="hidden" name="action" value="decline">
                <textarea name="decline_reason" class="form-textarea" placeholder="e.g. Insufficient details, conflicting date, inappropriate content..." rows="4" style="resize:vertical;width:100%;box-sizing:border-box;margin-bottom:16px;"></textarea>
                <div class="modal-actions" style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeDeclineModal()">Cancel</button>
                    <button type="submit" class="btn-decline">Decline Event</button>
                </div>
            </form>
        </div>
    </div>

    <!-- Delete Reason Modal -->
    <div id="deleteModal" class="modal-overlay" onclick="if(event.target===this)closeDeleteModal()">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button type="button" class="modal-close" onclick="closeDeleteModal()">&times;</button>
            <h3>Delete Event</h3>
            <p style="font-size:13px;color:var(--ink-2);margin-bottom:12px;">Write the reason for deleting/cancelling this event. The club admin and affected students will be notified.</p>
            <form method="POST" id="deleteForm">
                <input type="hidden" name="action" value="delete">
                <textarea name="delete_reason" class="form-textarea" placeholder="e.g. Event violates guidelines, duplicate event, unsuitable details..." rows="4" required style="resize:vertical;width:100%;box-sizing:border-box;margin-bottom:16px;"></textarea>
                <div class="modal-actions" style="display:flex;gap:10px;justify-content:flex-end;">
                    <button type="button" class="btn-secondary" onclick="closeDeleteModal()">Cancel</button>
                    <button type="submit" class="btn-delete" onclick="return confirm('Delete this event permanently? This cannot be undone.')">Delete Event</button>
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
        function openDeclineModal() {
            document.getElementById('declineModal').classList.add('active');
        }
        function closeDeclineModal() {
            document.getElementById('declineModal').classList.remove('active');
        }
        function openDeleteModal() {
            document.getElementById('deleteModal').classList.add('active');
        }
        function closeDeleteModal() {
            document.getElementById('deleteModal').classList.remove('active');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeEventPosterViewer();
                document.getElementById('declineModal').classList.remove('active');
                document.getElementById('deleteModal').classList.remove('active');
            }
        });
    </script>

</body>
</html>
