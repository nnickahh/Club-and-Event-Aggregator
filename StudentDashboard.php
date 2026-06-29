<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    $currentDate = date('Y-m-d');
    $studentID = $_SESSION['student_id'];
    $dashboardFeedbackMessage = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_dashboard_feedback'])) {
        $feedbackEventID = (int)($_POST['event_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $weekStart = date('Y-m-d', strtotime('-7 days'));

        if ($feedbackEventID <= 0 || $rating < 1 || $rating > 5) {
            $dashboardFeedbackMessage = 'Please select a rating from 1 to 5 stars.';
        } else {
            $recentCheck = $conn->prepare("
                SELECT e.eventID
                FROM events e
                JOIN registrations r ON e.eventID = r.eventID
                WHERE e.eventID = ?
                  AND r.studentID = ?
                  AND COALESCE(e.eventEndDate, e.eventDate) >= ?
                  AND ((e.status = 'approved' AND COALESCE(e.eventEndDate, e.eventDate) < ?) OR e.status = 'ended')
                LIMIT 1
            ");
            $recentCheck->bind_param("isss", $feedbackEventID, $studentID, $weekStart, $currentDate);
            $recentCheck->execute();
            $canReview = $recentCheck->get_result()->num_rows > 0;
            $recentCheck->close();

            if ($canReview) {
                $existingStmt = $conn->prepare("SELECT feedbackID FROM event_feedback WHERE eventID = ? AND studentID = ? LIMIT 1");
                $existingStmt->bind_param("is", $feedbackEventID, $studentID);
                $existingStmt->execute();
                $existing = $existingStmt->get_result()->fetch_assoc();
                $existingStmt->close();

                if ($existing) {
                    $updateStmt = $conn->prepare("UPDATE event_feedback SET rating = ?, `comment` = ? WHERE feedbackID = ?");
                    $updateStmt->bind_param("isi", $rating, $comment, $existing['feedbackID']);
                    $updateStmt->execute();
                    $updateStmt->close();
                    $reviewAction = 'updated a review';
                } else {
                    $insertStmt = $conn->prepare("INSERT INTO event_feedback (eventID, studentID, rating, `comment`) VALUES (?, ?, ?, ?)");
                    $insertStmt->bind_param("isis", $feedbackEventID, $studentID, $rating, $comment);
                    $insertStmt->execute();
                    $insertStmt->close();
                    $reviewAction = 'left a review';
                }

                $reviewInfoStmt = $conn->prepare("
                    SELECT e.eventTitle, e.adminID, s.name AS studentName
                    FROM events e
                    JOIN students s ON s.studentID = ?
                    WHERE e.eventID = ?
                    LIMIT 1
                ");
                $reviewInfoStmt->bind_param("si", $studentID, $feedbackEventID);
                $reviewInfoStmt->execute();
                $reviewInfo = $reviewInfoStmt->get_result()->fetch_assoc();
                $reviewInfoStmt->close();

                if ($reviewInfo && !empty($reviewInfo['adminID'])) {
                    $studentName = $reviewInfo['studentName'] ?: $studentID;
                    $eventTitle = $reviewInfo['eventTitle'] ?: 'your event';
                    $adminMsg = $studentName . ' ' . $reviewAction . ' for ' . $eventTitle . '.';
                    $adminNotifStmt = $conn->prepare("INSERT INTO notifications (adminID, message, eventID) VALUES (?, ?, ?)");
                    $adminNotifStmt->bind_param("ssi", $reviewInfo['adminID'], $adminMsg, $feedbackEventID);
                    $adminNotifStmt->execute();
                    $adminNotifStmt->close();
                }

                header("Location: StudentDashboard.php?feedback=1#recent-past-events");
                exit();
            } else {
                $dashboardFeedbackMessage = 'Review is only available for joined events from the last 7 days.';
            }
        }
    }

    // Fetch ongoing events (today or multi-day range covering today)
    $ongoingStmt = $conn->prepare("SELECT e.*, a.clubName, c.clubID FROM events e LEFT JOIN admins a ON e.adminID = a.adminID LEFT JOIN clubs c ON c.clubID = (SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1) WHERE ? BETWEEN e.eventDate AND COALESCE(e.eventEndDate, e.eventDate) AND e.status = 'approved' ORDER BY e.eventTime ASC LIMIT 4");
    $ongoingStmt->bind_param("s", $currentDate);
    $ongoingStmt->execute();
    $ongoingResult = $ongoingStmt->get_result();
    $ongoingStmt->close();

    // Fetch upcoming events (future dates)
    $upcomingStmt = $conn->prepare("SELECT e.*, a.clubName, c.clubID FROM events e LEFT JOIN admins a ON e.adminID = a.adminID LEFT JOIN clubs c ON c.clubID = (SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1) WHERE e.eventDate > ? AND e.status = 'approved' ORDER BY e.eventDate ASC LIMIT 4");
    $upcomingStmt->bind_param("s", $currentDate);
    $upcomingStmt->execute();
    $upcomingResult = $upcomingStmt->get_result();
    $upcomingStmt->close();

    $rsvpCount = $conn->query("SELECT COUNT(*) AS c FROM registrations WHERE studentID = '$studentID'")->fetch_assoc()['c'] ?? 0;
    $clubCount = $conn->query("SELECT COUNT(DISTINCT adminID) AS c FROM club_members WHERE studentID = '$studentID'")->fetch_assoc()['c'] ?? 0;

    $weekStart = date('Y-m-d', strtotime('-7 days'));
    $recentPastStmt = $conn->prepare("
        SELECT e.*, a.clubName, c.clubID, r.attendance_status, f.feedbackID, f.rating, f.`comment` AS feedbackComment
        FROM events e
        JOIN registrations r ON e.eventID = r.eventID
        LEFT JOIN admins a ON e.adminID = a.adminID
        LEFT JOIN clubs c ON c.clubID = (
            SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1
        )
        LEFT JOIN event_feedback f ON f.eventID = e.eventID AND f.studentID = r.studentID
        WHERE r.studentID = ?
          AND COALESCE(e.eventEndDate, e.eventDate) >= ?
          AND ((e.status = 'approved' AND COALESCE(e.eventEndDate, e.eventDate) < ?) OR e.status = 'ended')
        ORDER BY COALESCE(e.eventEndDate, e.eventDate) DESC
        LIMIT 3
    ");
    $recentPastStmt->bind_param("sss", $studentID, $weekStart, $currentDate);
    $recentPastStmt->execute();
    $recentPastResult = $recentPastStmt->get_result();
    $recentPastStmt->close();

    $colors = ['', 'green', 'blue', 'amber', 'purple'];

    $announcementResult = $conn->query("
        SELECT an.announcementID, an.title, an.content, an.created_at, a.clubName, e.eventID, e.eventTitle
        FROM announcements an
        LEFT JOIN admins a ON an.adminID = a.adminID
        LEFT JOIN events e ON an.eventID = e.eventID
        WHERE DATE(an.created_at) = CURDATE()
        ORDER BY an.created_at DESC
        LIMIT 3
    ");
    $announcements = $announcementResult ? $announcementResult->fetch_all(MYSQLI_ASSOC) : [];
    $announcementReadKey = !empty($announcements) ? 'student_announcements_read_' . (int)$announcements[0]['announcementID'] : '';

    function clubColor($clubName, $colors) {
        return $colors[abs(crc32($clubName ?? '')) % count($colors)];
    }

    function renderEventCard($row, $colors) {
        $color = clubColor($row['clubName'] ?? '', $colors);
        $eventDate = strtotime($row['eventDate']);
        $eventTime = strtotime($row['eventTime']);
        $eventEndTime = !empty($row['eventEndTime']) ? strtotime($row['eventEndTime']) : null;
        ob_start();
        ?>
        <article class="event-card student-dashboard-event-card"
                 data-title="<?php echo strtolower(htmlspecialchars($row['eventTitle'])); ?>"
                 data-club="<?php echo strtolower(htmlspecialchars($row['clubName'])); ?>"
                 data-venue="<?php echo strtolower(htmlspecialchars($row['venue'])); ?>"
                 data-date="<?php echo $row['eventDate']; ?>">
            <div class="card-stripe" data-color="<?php echo $color; ?>"></div>
            <?php if (!empty($row['eventImage'])): ?>
                <img src="<?php echo htmlspecialchars($row['eventImage']); ?>" alt="Event image" class="img-event-card">
            <?php endif; ?>
            <div class="card-body">
                <a href="ClubsDetails.php?id=<?php echo (int)($row['clubID'] ?? 0); ?>" class="no-deco"><span class="tag" data-color="<?php echo $color; ?>"><?php echo htmlspecialchars($row['clubName']); ?></span></a>
                <h3><?php echo htmlspecialchars($row['eventTitle']); ?></h3>
                <div class="event-meta">
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                        <?php echo formatDateRange($row['eventDate'], $row['eventEndDate'] ?? null); ?>
                    </div>
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo date('h:iA', $eventTime); ?><?php if ($eventEndTime): ?> — <?php echo date('h:iA', $eventEndTime); ?><?php endif; ?>
                    </div>
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?php echo htmlspecialchars($row['venue']); ?>
                    </div>
                </div>
                <div class="card-divider"></div>
                <a href="DetailedEvent.php?id=<?php echo (int)$row['eventID']; ?>" class="btn-primary">
                    Details →
                </a>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }

    function renderRecentPastEventCard($row, $colors) {
        $color = clubColor($row['clubName'] ?? '', $colors);
        ob_start();
        ?>
        <article class="event-card student-dashboard-event-card recent-past-review-card">
            <div class="card-stripe" data-color="<?php echo $color; ?>"></div>
            <div class="card-body">
                <a href="ClubsDetails.php?id=<?php echo (int)($row['clubID'] ?? 0); ?>" class="no-deco"><span class="tag" data-color="<?php echo $color; ?>"><?php echo htmlspecialchars($row['clubName']); ?></span></a>
                <h3><?php echo htmlspecialchars($row['eventTitle']); ?></h3>
                <div class="event-meta">
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                        <?php echo formatDateRange($row['eventDate'], $row['eventEndDate'] ?? null); ?>
                    </div>
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?php echo htmlspecialchars($row['venue']); ?>
                    </div>
                </div>
                <div class="feedback-panel dashboard-feedback-panel">
                    <form method="POST" class="feedback-form">
                        <p class="feedback-form-title">Review this event</p>
                        <input type="hidden" name="event_id" value="<?php echo (int)$row['eventID']; ?>">
                        <div class="rating-row" aria-label="Star rating">
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <input type="radio" id="dash-rating-<?php echo (int)$row['eventID']; ?>-<?php echo $star; ?>" name="rating" value="<?php echo $star; ?>" <?php echo (int)($row['rating'] ?? 0) === $star ? 'checked' : ''; ?> required>
                                <label for="dash-rating-<?php echo (int)$row['eventID']; ?>-<?php echo $star; ?>">★</label>
                            <?php endfor; ?>
                        </div>
                        <textarea name="comment" class="feedback-comment" rows="2" placeholder="Quick review..."><?php echo htmlspecialchars($row['feedbackComment'] ?? ''); ?></textarea>
                        <button type="submit" name="submit_dashboard_feedback" class="btn-sm btn-sm-outline feedback-submit"><?php echo !empty($row['feedbackID']) ? 'Update Review' : 'Submit Review'; ?></button>
                    </form>
                    <?php if (($row['attendance_status'] ?? 'absent') === 'present'): ?>
                        <a href="Certificate.php?id=<?php echo (int)$row['eventID']; ?>" class="btn-sm btn-sm-outline certificate-btn">Download Certificate</a>
                    <?php endif; ?>
                </div>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Home</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'StudentNavbar.php'; ?>

    <div class="dash-hero student-dashboard-hero">
        <div class="dash-hero-inner">
            <div>
                <p class="dash-greeting student-dash-greeting-lg">Welcome back, <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?> 👋</p>
                <h1 class="dash-title">Discover <br><em>campus</em> events</h1>
                <p class="dash-sub">Browse upcoming activities from your clubs and campus communities. <br>Register to reserve your spot!</p>
            </div>
            <div class="dash-stats">
                <a href="MyEvent.php?tab=events" class="stat-pill no-deco stat-link-inherit">
                    <div class="num"><?php echo $rsvpCount; ?></div>
                    <div class="lbl">My Events</div>
                </a>
                <a href="MyEvent.php?tab=clubs" class="stat-pill no-deco stat-link-inherit">
                    <div class="num"><?php echo $clubCount; ?></div>
                    <div class="lbl">My Clubs</div>
                </a>
            </div>
        </div>
    </div>

    <main class="container">

        <?php if (!empty($announcements)): ?>
            <section class="student-announcement-banner" aria-label="Latest announcements">
                <div class="student-announcement-heading">
                    <span>Campus Alerts</span>
                    <strong>Latest Announcements</strong>
                </div>
                <div class="student-announcement-list">
                    <?php foreach ($announcements as $announcement): ?>
                        <article>
                            <div>
                                <span class="announcement-event-chip"><?php echo !empty($announcement['eventTitle']) ? htmlspecialchars($announcement['eventTitle']) : 'General announcement'; ?></span>
                                <h3><?php echo htmlspecialchars($announcement['title']); ?></h3>
                                <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                            </div>
                            <small>
                                <?php echo htmlspecialchars($announcement['clubName'] ?? 'Campus Admin'); ?> ·
                                <?php echo date('d M Y', strtotime($announcement['created_at'])); ?>
                            </small>
                        </article>
                    <?php endforeach; ?>
                </div>
            </section>
        <?php endif; ?>

        <div class="flex-center-gap12">
            <a href="StudentEvents.php" class="btn-primary btn-action-btn btn-action-wide">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                Browse Events
            </a>
            <a href="Clubs.php" class="btn-primary btn-action-btn btn-action-wide" style="background:var(--blue);">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                Explore Clubs
            </a>
            <a href="Calendar.php" class="btn-primary btn-action-btn btn-action-wide" style="background:var(--purple);">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                Calendar
            </a>
            <a href="MyEvent.php" class="btn-primary btn-action-btn btn-action-wide" style="background:var(--green);">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
                My Activities
            </a>
        </div>

        <?php if (isset($_GET['feedback']) && $_GET['feedback'] === '1'): ?>
            <div class="msg-banner feedback-success-banner">Review saved successfully.</div>
        <?php elseif ($dashboardFeedbackMessage): ?>
            <div class="msg-banner feedback-error-banner"><?php echo htmlspecialchars($dashboardFeedbackMessage); ?></div>
        <?php endif; ?>

        <?php if ($ongoingResult->num_rows > 0): ?>
            <p class="section-label text-danger">Ongoing Events</p>
            <section class="event-grid">
                <?php while ($row = $ongoingResult->fetch_assoc()): ?>
                    <?php echo renderEventCard($row, $colors); ?>
                <?php endwhile; ?>
            </section>
        <?php endif; ?>

        <p class="section-label <?php echo $ongoingResult->num_rows > 0 ? 'mt-32' : ''; ?>">Upcoming Events</p>

        <section class="event-grid">
            <?php
            $hasUpcoming = false;
            while ($row = $upcomingResult->fetch_assoc()) {
                $hasUpcoming = true;
                echo renderEventCard($row, $colors);
            }
            if (!$hasUpcoming) {
                echo "
                <div class='event-empty-box'>
                    <div class='empty-icon'>
                        <svg viewBox='0 0 24 24' aria-hidden='true'><rect x='3' y='4' width='18' height='18' rx='2'/><line x1='3' y1='9' x2='21' y2='9'/><line x1='8' y1='2' x2='8' y2='6'/><line x1='16' y1='2' x2='16' y2='6'/></svg>
                    </div>
                    <p>No upcoming events available</p>
                    <p class='empty-subtext'>Check back later for new activities!</p>
                </div>";
            }
            ?>
        </section>

        <?php if ($recentPastResult && $recentPastResult->num_rows > 0): ?>
            <p class="section-label mt-32" id="recent-past-events">Recent Past Events</p>
            <section class="event-grid">
                <?php while ($row = $recentPastResult->fetch_assoc()): ?>
                    <?php echo renderRecentPastEventCard($row, $colors); ?>
                <?php endwhile; ?>
            </section>
        <?php endif; ?>

    </main>
    <?php if (!empty($announcements)): ?>
        <div class="announcement-popup-overlay" id="announcementPopup" data-read-key="<?php echo htmlspecialchars($announcementReadKey); ?>">
            <div class="announcement-popup-box" role="dialog" aria-modal="true" aria-labelledby="announcementPopupTitle">
                <div class="announcement-popup-head">
                    <span>Campus Alert</span>
                    <h3 id="announcementPopupTitle">Latest Announcements</h3>
                </div>
                <div class="announcement-popup-list">
                    <?php foreach ($announcements as $announcement): ?>
                        <article>
                            <span class="announcement-event-chip"><?php echo !empty($announcement['eventTitle']) ? htmlspecialchars($announcement['eventTitle']) : 'General announcement'; ?></span>
                            <h4><?php echo htmlspecialchars($announcement['title']); ?></h4>
                            <p><?php echo nl2br(htmlspecialchars($announcement['content'])); ?></p>
                            <small>
                                <?php echo htmlspecialchars($announcement['clubName'] ?? 'Campus Admin'); ?> ·
                                <?php echo date('d M Y', strtotime($announcement['created_at'])); ?>
                            </small>
                        </article>
                    <?php endforeach; ?>
                </div>
                <button type="button" class="btn-primary-sm announcement-read-btn" onclick="markAnnouncementsRead()">I have read</button>
            </div>
        </div>
    <?php endif; ?>
    <?php if (isset($_GET['feedback']) && $_GET['feedback'] === '1'): ?>
        <script>
            alert('Thank you! Your review has been submitted.');
        </script>
    <?php endif; ?>
    <script>
        const announcementPopup = document.getElementById('announcementPopup');
        if (announcementPopup) {
            const readKey = announcementPopup.dataset.readKey;
            if (readKey && localStorage.getItem(readKey) !== '1') {
                announcementPopup.classList.add('open');
                document.body.classList.add('modal-open');
            }
        }

        function markAnnouncementsRead() {
            const popup = document.getElementById('announcementPopup');
            if (!popup) return;
            const readKey = popup.dataset.readKey;
            if (readKey) localStorage.setItem(readKey, '1');
            popup.classList.remove('open');
            document.body.classList.remove('modal-open');
        }
    </script>
</body>
</html>
