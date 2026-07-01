<!--AdminDashboard.php-->
<?php
    session_start();
    require_once 'db_connect.php';

    // Security Check: Redirect to login if user is not authorized as an admin
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }
    // Flash message for popup
    $flashMessage = $_SESSION['flash_message'] ?? null;
    $announcementMessage = $_SESSION['announcement_flash'] ?? null;
    unset($_SESSION['flash_message']);
    unset($_SESSION['announcement_flash']);

    $adminID = $_SESSION['admin_id'];
    $clubName = isset($_SESSION['clubName']) ? $_SESSION['clubName'] : "Club Admin";

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_announcement'])) {
        $announcementTitle = trim($_POST['announcement_title'] ?? '');
        $announcementContent = trim($_POST['announcement_content'] ?? '');
        $announcementEventID = !empty($_POST['announcement_event_id']) ? (int)$_POST['announcement_event_id'] : null;

        if ($announcementTitle !== '' && $announcementContent !== '') {
            $annStmt = $conn->prepare("INSERT INTO announcements (adminID, eventID, title, content) VALUES (?, ?, ?, ?)");
            $annStmt->bind_param("siss", $adminID, $announcementEventID, $announcementTitle, $announcementContent);
            $annStmt->execute();
            $annStmt->close();
            $_SESSION['announcement_flash'] = 'Announcement posted to student dashboard.';
            header("Location: AdminDashboard.php#announcements");
            exit();
        }

        $announcementMessage = 'Please fill in both announcement title and content.';
    }

    session_write_close();

    $today = date('Y-m-d');
    $eventOptionStmt = $conn->prepare("
        SELECT eventID, eventTitle, eventDate, status
        FROM events
        WHERE adminID = ?
          AND eventDate >= ?
          AND status IN ('approved', 'cancelled')
        ORDER BY eventDate ASC
        LIMIT 30
    ");
    $eventOptionStmt->bind_param("ss", $adminID, $today);
    $eventOptionStmt->execute();
    $announcementEvents = $eventOptionStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $eventOptionStmt->close();

    $announcementStmt = $conn->prepare("
        SELECT an.announcementID, an.title, an.content, an.created_at, e.eventTitle
        FROM announcements an
        LEFT JOIN events e ON an.eventID = e.eventID
        WHERE an.adminID = ?
          AND DATE(an.created_at) = CURDATE()
        ORDER BY an.created_at DESC
        LIMIT 3
    ");
    $announcementStmt->bind_param("s", $adminID);
    $announcementStmt->execute();
    $adminAnnouncements = $announcementStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $announcementStmt->close();

    $modBroadcastResult = $conn->query("
        SELECT an.announcementID, an.title, an.content, an.created_at, m.name AS moderatorName, e.eventTitle
        FROM announcements an
        LEFT JOIN moderators m ON an.moderatorID = m.moderatorID
        LEFT JOIN events e ON an.eventID = e.eventID
        WHERE an.created_by_role = 'moderator'
          AND DATE(an.created_at) = CURDATE()
        ORDER BY an.created_at DESC
        LIMIT 3
    ");
    $moderatorBroadcasts = $modBroadcastResult ? $modBroadcastResult->fetch_all(MYSQLI_ASSOC) : [];
    $adminAnnouncementReadKey = !empty($moderatorBroadcasts) ? 'admin_moderator_announcements_read_' . (int)$moderatorBroadcasts[0]['announcementID'] : '';

    $query = "SELECT * FROM events WHERE adminID = ? ORDER BY eventDate ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $adminID); 
    $stmt->execute();
    $result = $stmt->get_result();

    $ongoingEvents = [];
    $upcomingEvents = [];
    $pendingEvents = [];
    $completedEvents = [];
    $cancelledEvents = [];
    $currentDate = date('Y-m-d');
    $weekEnd = date('Y-m-d', strtotime('sunday this week'));

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $status = $row['status'] ?? 'pending';
            if ($status === 'pending') {
                $pendingEvents[] = $row;
            } elseif ($status === 'approved') {
                $p = getEventPeriod($row['eventDate'], $row['eventEndDate'] ?? null, $currentDate);
                if ($p === 'ongoing') {
                    $ongoingEvents[] = $row;
                } elseif ($p === 'upcoming') {
                    if (($row['recurrence_type'] ?? '') === 'weekly' && !empty($row['recurrence_group_id'])) {
                        if ($row['eventDate'] > $weekEnd) {
                            continue;
                        }
                    }
                    $upcomingEvents[] = $row;
                } else {
                    $completedEvents[] = $row;
                }
            } elseif ($status === 'ended') {
                $completedEvents[] = $row;
            } elseif ($status === 'cancelled') {
                $cancelledEvents[] = $row;
            }
        }
    }
    $stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($clubName); ?></title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'AdminNavbar.php'; ?>

    <main class="container admin-dashboard-shell">
        <h2 class="clubs-title admin-dashboard-title">Admin Dashboard</h2>
        
        <header class="dashboard-header admin-management-hero">
            <div class="admin-hero-copy">
                <span class="admin-kicker">Club Management</span>
                <h1>Welcome Back, <?php echo htmlspecialchars($clubName); ?></h1>
                <p class="subtitle">Managed by: <strong><?php echo htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin User'); ?></strong></p>
            </div>
            <div class="admin-hero-actions">
                <a href="ClubSettings.php" class="btn-outline no-deco">Club Settings</a>
                <a href="CreateEvent.php" class="btn-primary no-deco">Create New Event</a>
            </div>
        </header>

        <?php if ($announcementMessage): ?>
            <div class="msg-banner feedback-success-banner"><?php echo htmlspecialchars($announcementMessage); ?></div>
        <?php endif; ?>

        <section class="announcement-admin-panel" id="announcements">
            <div class="announcement-admin-copy">
                <p class="section-label">Announcement</p>
                <h3>Broadcast to Student Dashboard</h3>
                <p>Post important venue changes, reminders, or event updates for students to see first.</p>
            </div>
            <form method="POST" class="announcement-admin-form">
                <select name="announcement_event_id" class="form-select">
                    <option value="">General announcement</option>
                    <?php foreach ($announcementEvents as $eventOption): ?>
                        <option value="<?php echo (int)$eventOption['eventID']; ?>">
                            <?php echo htmlspecialchars($eventOption['eventTitle']); ?> · <?php echo date('d M Y', strtotime($eventOption['eventDate'])); ?> · <?php echo htmlspecialchars(ucfirst($eventOption['status'] ?? 'pending')); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <input type="text" name="announcement_title" class="form-input" maxlength="150" placeholder="Announcement title" required>
                <textarea name="announcement_content" class="form-textarea" rows="3" placeholder="Write the update for students..." required></textarea>
                <button type="submit" name="post_announcement" class="btn-primary-sm">Post Announcement</button>
            </form>
            <?php if (!empty($adminAnnouncements)): ?>
                <div class="announcement-admin-list">
                    <?php foreach ($adminAnnouncements as $announcement): ?>
                        <article>
                            <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                            <span class="announcement-event-chip"><?php echo !empty($announcement['eventTitle']) ? htmlspecialchars($announcement['eventTitle']) : 'General announcement'; ?></span>
                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                            <small><?php echo date('d M Y h:i A', strtotime($announcement['created_at'])); ?></small>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </section>

        <section class="admin-status-strip" aria-label="Event status overview">
            <a href="#ongoing-events" class="admin-status-card status-ongoing">
                <span>Ongoing</span>
                <strong><?php echo count($ongoingEvents); ?></strong>
            </a>
            <a href="#upcoming-events" class="admin-status-card status-upcoming">
                <span>Upcoming</span>
                <strong><?php echo count($upcomingEvents); ?></strong>
            </a>
            <a href="#pending-events" class="admin-status-card status-pending">
                <span>Pending</span>
                <strong><?php echo count($pendingEvents); ?></strong>
            </a>
            <a href="#completed-events" class="admin-status-card status-completed">
                <span>Completed</span>
                <strong><?php echo count($completedEvents); ?></strong>
            </a>
            <a href="#cancelled-events" class="admin-status-card status-cancelled">
                <span>Cancelled</span>
                <strong><?php echo count($cancelledEvents); ?></strong>
            </a>
        </section>

        <!-- ONGOING EVENTS SECTION -->
        <h3 class="section-title" id="ongoing-events">Ongoing Events</h3>
        <section class="event-grid">
            <?php if (!empty($ongoingEvents)): ?>
                <?php foreach($ongoingEvents as $event): ?>
                    <article class="event-card">
                        <div>
                            <span class="mod-status-tag ongoing">Ongoing</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" class="img-event-card">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Capacity Limit:</strong> <?php echo htmlspecialchars($event['capacity']); ?> seats max<br>
                                <strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?><br>
                                <strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?><br>
                                <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                            </div>

                            <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>

                        <div class="action-buttons equal-action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <button type="button" class="btn-danger" onclick="openAdminCancelModal(<?php echo (int)$event['eventID']; ?>, <?php echo htmlspecialchars(json_encode($event['eventTitle']), ENT_QUOTES); ?>)">Delete</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Ongoing Events</p>
                    <p class="empty-subtext">Events happening today will appear here.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- UPCOMING EVENTS SECTION -->
        <h3 class="section-title" id="upcoming-events">Upcoming Events</h3>
        <section class="event-grid">
            <?php if (!empty($upcomingEvents)): ?>
                <?php foreach($upcomingEvents as $event): ?>
                    <article class="event-card">
                        <div>
                            <span class="mod-status-tag upcoming">Upcoming</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" class="img-event-card">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Capacity Limit:</strong> <?php echo htmlspecialchars($event['capacity']); ?> seats max<br>
                                <strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?><br>
                                <strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?><br>
                                <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                            </div>

                            <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>

                        <div class="action-buttons equal-action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <button type="button" class="btn-danger" onclick="openAdminCancelModal(<?php echo (int)$event['eventID']; ?>, <?php echo htmlspecialchars(json_encode($event['eventTitle']), ENT_QUOTES); ?>)">Delete</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Upcoming Events Found</p>
                    <p class="empty-subtext">Click the 'Create New Event' button to get started.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- PENDING EVENTS SECTION -->
        <h3 class="section-title" id="pending-events">Pending Events</h3>
        <p class="text-sm-muted" style="margin:-10px 0 16px 0;">Events with a pending status are not visible to students until approved by a moderator.</p>
        <section class="event-grid">
            <?php if (!empty($pendingEvents)): ?>
                <?php foreach($pendingEvents as $event): ?>
                    <article class="event-card">
                        <div>
                            <span class="mod-status-tag pending">Pending Approval</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" class="img-event-card">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?><br>
                                <strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?><br>
                                <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                            </div>

                            <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>

                        <div class="action-buttons equal-action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <button type="button" class="btn-danger" onclick="openAdminCancelModal(<?php echo (int)$event['eventID']; ?>, <?php echo htmlspecialchars(json_encode($event['eventTitle']), ENT_QUOTES); ?>)">Delete</button>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Pending Events</p>
                    <p class="empty-subtext">All your events have been reviewed.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- COMPLETED EVENTS SECTION -->
        <h3 class="section-title" id="completed-events">Completed Events</h3>
        <section class="event-grid">
            <?php if (!empty($completedEvents)): ?>
                <?php foreach($completedEvents as $event): ?>
                    <article class="event-card event-card-completed event-card-cancelled"> 
                        <div>
                            <span class="tag">Completed</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" class="img-event-card-dim">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?><br>
                                <strong>Status:</strong> Archived / Completed
                            </div>
                        </div>

                        <div class="action-buttons equal-action-buttons completed-report-actions">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <a href="ExportReport.php?id=<?php echo $event['eventID']; ?>" class="btn-danger">Generate Report</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Completed Events</p>
                    <p class="empty-subtext">Your event history will appear here once events have passed.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- CANCELLED EVENTS SECTION -->
        <h3 class="section-title" id="cancelled-events">Cancelled Events</h3>
        <section class="event-grid">
            <?php if (!empty($cancelledEvents)): ?>
                <?php foreach($cancelledEvents as $event): ?>
                    <article class="event-card event-card-completed"> 
                        <div>
                            <span class="tag" style="background:#fef2f2;color:#dc2626;">Cancelled</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" class="img-event-card-dim">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Date:</strong> <?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?><br>
                                <strong>Status:</strong> Cancelled
                            </div>
                        </div>

                        <div class="action-buttons equal-action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <form method="POST" action="DeleteCancelledEvent.php" onsubmit="return confirm('Permanently delete this cancelled event record? This cannot be undone.');">
                                <input type="hidden" name="event_id" value="<?php echo (int)$event['eventID']; ?>">
                                <button type="submit" class="btn-danger">Delete Record</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Cancelled Events</p>
                    <p class="empty-subtext">Events that are cancelled will appear here.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <?php if (!empty($moderatorBroadcasts)): ?>
    <div class="announcement-popup-overlay" id="adminAnnouncementPopup" data-read-key="<?php echo htmlspecialchars($adminAnnouncementReadKey); ?>">
        <div class="announcement-popup-box" role="dialog" aria-modal="true" aria-labelledby="adminAnnouncementPopupTitle">
            <div class="announcement-popup-head">
                <span>Moderator Notice</span>
                <h3 id="adminAnnouncementPopupTitle">Latest General Announcement</h3>
            </div>
            <div class="announcement-popup-list">
                <?php foreach ($moderatorBroadcasts as $announcement): ?>
                    <article>
                        <span class="announcement-event-chip"><?php echo !empty($announcement['eventTitle']) ? htmlspecialchars($announcement['eventTitle']) : 'General announcement'; ?></span>
                        <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                        <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                        <small>
                            <?php echo htmlspecialchars($announcement['moderatorName'] ?? 'Moderator'); ?> ·
                            <?php echo date('d M Y', strtotime($announcement['created_at'])); ?>
                        </small>
                    </article>
                <?php endforeach; ?>
            </div>
            <button type="button" class="btn-primary-sm announcement-read-btn" onclick="markAdminAnnouncementsRead()">I have read</button>
        </div>
    </div>
    <?php endif; ?>

    <div id="adminCancelModal" class="modal-overlay" onclick="closeAdminCancelModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button type="button" class="modal-close" onclick="closeAdminCancelModal()">&times;</button>
            <h3>Cancel Event</h3>
            <p class="text-sm-muted mb-16">Write the reason for cancelling <strong id="adminCancelEventTitle">this event</strong>. Students will be notified with this reason.</p>
            <form method="POST" action="DeleteEvent.php">
                <input type="hidden" name="event_id" id="adminCancelEventID" value="">
                <label for="adminCancelReason" class="form-label-md">Reason</label>
                <textarea name="cancel_reason" id="adminCancelReason" class="form-textarea-lg" rows="4" required placeholder="e.g. Venue unavailable, weather issue, insufficient participants..."></textarea>
                <div class="modal-actions">
                    <button type="button" class="btn-mod-details" onclick="closeAdminCancelModal()">Cancel</button>
                    <button type="submit" class="btn-danger">Confirm Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <?php if ($flashMessage): ?>
    <div id="flashOverlay" class="flash-overlay">
        <div class="flash-box">
            <?php
                $isError = stripos($flashMessage, 'deleted') !== false || stripos($flashMessage, 'cancelled') !== false;
            ?>
            <div class="flash-icon"><?php echo $isError ? '🗑️' : '🎉'; ?></div>
            <h3 class="flash-title"><?php echo $isError ? 'Done!' : 'Event Submitted!'; ?></h3>
            <p class="flash-text"><?php echo htmlspecialchars($flashMessage); ?></p>
            <button onclick="document.getElementById('flashOverlay').remove()" class="flash-btn">OK</button>
        </div>
    </div>
    <?php endif; ?>

    <script>
        function openAdminCancelModal(eventID, eventTitle) {
            document.getElementById('adminCancelEventID').value = eventID;
            document.getElementById('adminCancelEventTitle').textContent = eventTitle || 'this event';
            document.getElementById('adminCancelReason').value = '';
            document.getElementById('adminCancelModal').classList.add('active');
            document.getElementById('adminCancelReason').focus();
        }

        function closeAdminCancelModal(e) {
            if (!e || e.target === document.getElementById('adminCancelModal')) {
                document.getElementById('adminCancelModal').classList.remove('active');
            }
        }

        const adminAnnouncementPopup = document.getElementById('adminAnnouncementPopup');
        if (adminAnnouncementPopup) {
            const readKey = adminAnnouncementPopup.dataset.readKey;
            if (readKey && localStorage.getItem(readKey) !== '1') {
                adminAnnouncementPopup.classList.add('open');
                document.body.classList.add('modal-open');
            }
        }

        function markAdminAnnouncementsRead() {
            const popup = document.getElementById('adminAnnouncementPopup');
            if (!popup) return;
            const readKey = popup.dataset.readKey;
            if (readKey) localStorage.setItem(readKey, '1');
            popup.classList.remove('open');
            document.body.classList.remove('modal-open');
        }
    </script>

</body>
</html>
