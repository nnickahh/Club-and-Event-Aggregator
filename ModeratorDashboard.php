<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }

    $moderatorID = $_SESSION['moderator_id'] ?? null;
    $moderatorName = $_SESSION['full_name'] ?? 'Moderator';
    $moderatorAnnouncementMessage = $_SESSION['moderator_announcement_flash'] ?? '';
    unset($_SESSION['moderator_announcement_flash']);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_moderator_announcement'])) {
        $announcementTitle = trim($_POST['moderator_announcement_title'] ?? '');
        $announcementContent = trim($_POST['moderator_announcement_content'] ?? '');

        if ($announcementTitle !== '' && $announcementContent !== '') {
            $role = 'moderator';
            $annStmt = $conn->prepare("INSERT INTO announcements (moderatorID, created_by_role, title, content) VALUES (?, ?, ?, ?)");
            $annStmt->bind_param("ssss", $moderatorID, $role, $announcementTitle, $announcementContent);
            $annStmt->execute();
            $annStmt->close();
            logModeratorActivity($conn, $moderatorID, $moderatorName, 'posted', 'announcement', null, $announcementTitle, $announcementContent);
            $_SESSION['moderator_announcement_flash'] = 'Announcement posted to admins and students.';
            header("Location: ModeratorDashboard.php#moderator-announcements");
            exit();
        }

        $moderatorAnnouncementMessage = 'Please fill in both announcement title and content.';
    }

    session_write_close();

    $currentPage = 'home';

    // Fetch stats
    $totalEvents = 0;
    $pendingClubs = 0;
    $pendingEvents = 0;
    $activeClubs = 0;
    $recentActivity = [];
    $pendingReviewQueue = [];
    $moderatorLogs = [];
    $moderatorAnnouncements = [];

    try {
        $result = $conn->query("SELECT COUNT(*) AS cnt FROM events");
        if ($result) { $totalEvents = $result->fetch_assoc()['cnt']; }

        $result = $conn->query("SELECT COUNT(*) AS cnt FROM admins WHERE status = 'pending'");
        if ($result) { $pendingClubs = $result->fetch_assoc()['cnt']; }

        $result = $conn->query("SELECT COUNT(*) AS cnt FROM events WHERE status = 'pending'");
        if ($result) { $pendingEvents = $result->fetch_assoc()['cnt']; }

        $result = $conn->query("SELECT COUNT(*) AS cnt FROM admins WHERE status = 'active'");
        if ($result) { $activeClubs = $result->fetch_assoc()['cnt']; }

        $result = $conn->query("SELECT e.*, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID ORDER BY e.created_at DESC LIMIT 10");
        if ($result) {
            $events = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($events as $event) {
                $event['activity_type'] = 'event';
                $recentActivity[] = $event;
            }
        }

	        $result = $conn->query("SELECT adminID, name, clubName, clubEmail, status, created_at FROM admins WHERE status = 'pending' ORDER BY created_at DESC LIMIT 10");
	        if ($result) {
	            $clubs = $result->fetch_all(MYSQLI_ASSOC);
            foreach ($clubs as $club) {
                $recentActivity[] = [
                    'activity_type' => 'club',
                    'eventID' => null,
                    'adminID' => $club['adminID'],
                    'eventTitle' => 'New club registration',
                    'club_name' => $club['clubName'],
                    'clubEmail' => $club['clubEmail'],
                    'applicant_name' => $club['name'],
                    'status' => $club['status'],
                    'created_at' => $club['created_at']
                ];
            }
        }

	        usort($recentActivity, function ($a, $b) {
	            return strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
	        });
	        $recentActivity = array_slice($recentActivity, 0, 10);

	        $eventQueue = $conn->query("SELECT e.eventID, e.eventTitle, e.eventDate, e.eventTime, e.venue, e.created_at, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.status = 'pending' ORDER BY e.created_at DESC LIMIT 6");
	        if ($eventQueue) {
	            foreach ($eventQueue->fetch_all(MYSQLI_ASSOC) as $item) {
	                $item['queue_type'] = 'event';
	                $pendingReviewQueue[] = $item;
	            }
	        }

	        $clubQueue = $conn->query("SELECT adminID, name, clubName, clubEmail, created_at FROM admins WHERE status = 'pending' ORDER BY created_at DESC LIMIT 6");
	        if ($clubQueue) {
	            foreach ($clubQueue->fetch_all(MYSQLI_ASSOC) as $item) {
	                $item['queue_type'] = 'club';
	                $pendingReviewQueue[] = $item;
	            }
	        }

	        usort($pendingReviewQueue, function ($a, $b) {
	            return strtotime($b['created_at'] ?? '1970-01-01') <=> strtotime($a['created_at'] ?? '1970-01-01');
	        });
	        $pendingReviewQueue = array_slice($pendingReviewQueue, 0, 8);

	        $logResult = $conn->query("SELECT * FROM moderator_activity_log ORDER BY created_at DESC LIMIT 8");
	        if ($logResult) {
	            $moderatorLogs = $logResult->fetch_all(MYSQLI_ASSOC);
	        }

	        if ($moderatorID) {
	            $modAnnStmt = $conn->prepare("SELECT announcementID, title, content, created_at FROM announcements WHERE moderatorID = ? AND DATE(created_at) = CURDATE() ORDER BY created_at DESC LIMIT 3");
	            $modAnnStmt->bind_param("s", $moderatorID);
	            $modAnnStmt->execute();
	            $moderatorAnnouncements = $modAnnStmt->get_result()->fetch_all(MYSQLI_ASSOC);
	            $modAnnStmt->close();
	        }
	    } catch (mysqli_sql_exception $e) {
	        error_log('ModeratorDashboard DB error: ' . $e->getMessage());
	    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator Dashboard</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">

        <div class="mod-page-header">
            <div>
                <h2 class="mod-title">Moderator Dashboard</h2>
                <p class="mod-sub">Overview of campus activity and pending items.</p>
            </div>
        </div>

        <?php if ($moderatorAnnouncementMessage): ?>
            <div class="msg-banner feedback-success-banner"><?php echo htmlspecialchars($moderatorAnnouncementMessage); ?></div>
        <?php endif; ?>

        <div class="moderator-dashboard-top">
            <section class="announcement-admin-panel moderator-announcement-panel" id="moderator-announcements">
                <div class="announcement-admin-copy">
                    <p class="section-label">General Announcement</p>
                    <h3>Broadcast to Admins and Students</h3>
                    <p>Post campus-wide notices that should appear as a popup for both club admins and students today.</p>
                </div>
                <form method="POST" class="announcement-admin-form">
                    <input type="text" name="moderator_announcement_title" class="form-input" maxlength="150" placeholder="Announcement title" required>
                    <textarea name="moderator_announcement_content" class="form-textarea" rows="5" placeholder="Write the announcement..." required></textarea>
                    <button type="submit" name="post_moderator_announcement" class="btn-primary-sm">Post General Announcement</button>
                </form>
                <?php if (!empty($moderatorAnnouncements)): ?>
                    <div class="announcement-admin-list">
                        <?php foreach ($moderatorAnnouncements as $announcement): ?>
                            <article>
                                <strong><?php echo htmlspecialchars($announcement['title']); ?></strong>
                                <span class="announcement-event-chip">Moderator announcement</span>
                                <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                                <small><?php echo date('d M Y h:i A', strtotime($announcement['created_at'])); ?></small>
                            </article>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <div class="moderator-stats-stack">
            <div class="mod-stats-grid moderator-stats-vertical">
                <div class="mod-stat-card">
                    <a href="ModeratorEvents.php?tab=pending" class="stat-link">
                        <div class="mod-stat-icon amber">
                            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                        </div>
                        <div>
                            <div class="mod-stat-num"><?php echo $pendingEvents; ?></div>
                            <div class="mod-stat-lbl">Event Approval Queue</div>
                        </div>
                    </a>
                </div>
                <div class="mod-stat-card">
                    <a href="ModeratorClubs.php?tab=pending" class="stat-link">
                        <div class="mod-stat-icon purple">
                            <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                        </div>
                        <div>
                            <div class="mod-stat-num"><?php echo $pendingClubs; ?></div>
                            <div class="mod-stat-lbl">Club Approval Queue</div>
                        </div>
                    </a>
                </div>
                <div class="mod-stat-card">
                    <a href="ModeratorEvents.php?tab=upcoming" class="stat-link">
                        <div class="mod-stat-icon blue">
                            <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                        </div>
                        <div>
                            <div class="mod-stat-num"><?php echo $totalEvents; ?></div>
                            <div class="mod-stat-lbl">Total Events</div>
                        </div>
                    </a>
                </div>
                <div class="mod-stat-card">
                    <a href="ModeratorClubs.php?tab=approved" class="stat-link">
                        <div class="mod-stat-icon green">
                            <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
                        </div>
                        <div>
                            <div class="mod-stat-num"><?php echo $activeClubs; ?></div>
                            <div class="mod-stat-lbl">Active Clubs</div>
                        </div>
                    </a>
                </div>
            </div>

		            <section class="mod-review-panel moderator-actions-panel">
		                <div class="mod-panel-head">
		                    <div>
		                        <p class="section-label">Activity Log</p>
	                        <h3>Recent Moderator Actions</h3>
	                    </div>
	                </div>
	                <?php if (!empty($moderatorLogs)): ?>
	                    <div class="mod-log-list">
	                        <?php foreach ($moderatorLogs as $log): ?>
	                            <article class="mod-log-item">
	                                <span class="mod-log-action"><?php echo htmlspecialchars(ucfirst($log['action_type'])); ?></span>
	                                <div>
	                                    <strong><?php echo htmlspecialchars($log['target_title'] ?? 'Untitled'); ?></strong>
	                                    <small><?php echo htmlspecialchars($log['moderatorName'] ?? 'Moderator'); ?> · <?php echo date('d M Y h:i A', strtotime($log['created_at'])); ?></small>
	                                    <?php if (!empty($log['details'])): ?>
	                                        <p><?php echo htmlspecialchars($log['details']); ?></p>
	                                    <?php endif; ?>
	                                </div>
	                            </article>
	                        <?php endforeach; ?>
	                    </div>
	                <?php else: ?>
	                    <div class="event-empty-box compact-empty-box">
	                        <p>No moderator actions logged yet.</p>
		                    </div>
		                <?php endif; ?>
		            </section>
            </div>
        </div>

	        <div class="section-label">Recent Activity</div>

        <div class="search-toolbar">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="dashboardActivitySearch" class="search-bar" placeholder="Search events or clubs...">
            </div>
            <div class="reg-filter-group">
	                <button type="button" class="filter-chip active" data-activityfilter="all">All</button>
	                <button type="button" class="filter-chip" data-activityfilter="events">Events</button>
	                <button type="button" class="filter-chip" data-activityfilter="clubs">Clubs</button>
	                <button type="button" class="filter-chip" data-activityfilter="pending">Pending</button>
	                <button type="button" class="filter-chip" data-activityfilter="approved">Approved</button>
	                <button type="button" class="filter-chip" data-activityfilter="declined">Declined</button>
            </div>
        </div>

        <p class="section-label" id="dashboardActivityCount">
            <?php echo count($recentActivity); ?> activit<?php echo count($recentActivity) === 1 ? 'y' : 'ies'; ?> found
        </p>

        <div class="mod-activity-list">
            <?php if (!empty($recentActivity)): ?>
                <?php foreach ($recentActivity as $act): ?>
                    <?php
                        $activityStatus = strtolower($act['status'] ?? 'approved');
                        $activityType = $act['activity_type'] ?? 'event';
                        $activityTarget = $activityType === 'club'
                            ? "ModeratorClubs.php?tab=pending"
                            : "EventDetailsModerator.php?id=" . (int)$act['eventID'];
                        $activitySearchTitle = strtolower(($act['eventTitle'] ?? '') . ' ' . ($act['applicant_name'] ?? '') . ' ' . ($act['clubEmail'] ?? ''));
                    ?>
                    <div class="mod-activity-item"
	                         onclick="window.location.href='<?php echo htmlspecialchars($activityTarget, ENT_QUOTES); ?>'"
	                         data-title="<?php echo htmlspecialchars($activitySearchTitle, ENT_QUOTES); ?>"
	                         data-club="<?php echo htmlspecialchars(strtolower($act['club_name'] ?? ''), ENT_QUOTES); ?>"
	                         data-type="<?php echo htmlspecialchars($activityType, ENT_QUOTES); ?>"
	                         data-status="<?php echo htmlspecialchars($activityStatus, ENT_QUOTES); ?>">
                        <span class="mod-activity-dot"></span>
                        <span>
                            <span class="act-title"><?php echo htmlspecialchars($act['eventTitle']); ?></span>
                            <?php if ($activityType === 'club'): ?>
                                <span class="act-club">for <a href="ModeratorClubs.php?tab=pending" class="text-muted-link-inherit" onclick="event.stopPropagation();"><?php echo htmlspecialchars($act['club_name'] ?? 'Unknown'); ?></a></span>
                            <?php else: ?>
                                <span class="act-club">by <a href="ClubDetailsModerator.php?id=<?php echo urlencode($act['adminID']); ?>" class="text-muted-link-inherit" onclick="event.stopPropagation();"><?php echo htmlspecialchars($act['club_name'] ?? 'Unknown'); ?></a></span>
                            <?php endif; ?>
                            <span class="act-status">-
                                <?php
                                    $s = $activityStatus;
                                    if ($s === 'pending' && $activityType === 'club') echo '<span style="color:var(--amber);font-weight:600;">Pending Club</span>';
                                    elseif ($s === 'pending') echo '<span style="color:var(--amber);font-weight:600;">Pending</span>';
                                    elseif ($s === 'declined') echo '<span style="color:var(--red);font-weight:600;">Declined</span>';
                                    else echo '<span style="color:var(--green);font-weight:600;">Approved</span>';
                                ?>
                            </span>
                        </span>
                        <span class="act-time"><?php echo date('d M Y', strtotime($act['created_at'])); ?></span>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                    </div>
                    <p>No recent activity yet.</p>
                </div>
            <?php endif; ?>
            <div class="event-empty-box" id="dashboardActivityNoResults" style="display:none;">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <p>No matching activity</p>
                <p class="empty-subtext">Try a different search or filter.</p>
            </div>
        </div>

    </main>
    <script>
        const dashboardActivitySearch = document.getElementById('dashboardActivitySearch');
        const dashboardActivityCount = document.getElementById('dashboardActivityCount');
        const dashboardActivityNoResults = document.getElementById('dashboardActivityNoResults');
        const dashboardActivityItems = document.querySelectorAll('.mod-activity-item');

        function applyDashboardActivityFilters() {
            const q = dashboardActivitySearch.value.toLowerCase().trim();
	            const activeFilter = document.querySelector('[data-activityfilter].active');
	            const filter = activeFilter ? activeFilter.dataset.activityfilter : 'all';
	            let visible = 0;
	
	            dashboardActivityItems.forEach(item => {
	                let show = true;
	                if (q && !item.dataset.title.includes(q) && !item.dataset.club.includes(q)) show = false;
	                if (filter === 'events' && item.dataset.type !== 'event') show = false;
	                if (filter === 'clubs' && item.dataset.type !== 'club') show = false;
	                if (['pending', 'approved', 'declined'].includes(filter) && item.dataset.status !== filter) show = false;
	                item.style.display = show ? '' : 'none';
	                if (show) visible++;
	            });

            dashboardActivityCount.textContent = visible + ' activit' + (visible === 1 ? 'y' : 'ies') + ' found';
            dashboardActivityNoResults.style.display = visible === 0 && dashboardActivityItems.length > 0 ? '' : 'none';
        }

        dashboardActivitySearch.addEventListener('input', applyDashboardActivityFilters);
	        document.querySelectorAll('[data-activityfilter]').forEach(btn => {
	            btn.addEventListener('click', function () {
	                document.querySelectorAll('[data-activityfilter]').forEach(b => b.classList.remove('active'));
	                this.classList.add('active');
	                applyDashboardActivityFilters();
	            });
        });
    </script>
</body>
</html>
