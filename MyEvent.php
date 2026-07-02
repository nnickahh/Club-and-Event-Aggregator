<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    function formatClubPosition($role) {
        $role = preg_replace('/\s+/', ' ', str_replace('-', ' ', strtolower(trim($role ?? 'member'))));
        return ucwords($role ?: 'member');
    }

    $studentID = $_SESSION['student_id'];
    $feedbackMessage = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback'])) {
        $feedbackEventID = (int)($_POST['event_id'] ?? 0);
        $rating = (int)($_POST['rating'] ?? 0);
        $comment = trim($_POST['comment'] ?? '');
        $today = date('Y-m-d');

        if ($feedbackEventID <= 0 || $rating < 1 || $rating > 5) {
            $feedbackMessage = 'Please select a rating from 1 to 5 stars.';
        } else {
            $pastCheck = $conn->prepare("
                SELECT e.eventID
                FROM events e
                JOIN registrations r ON e.eventID = r.eventID
                WHERE e.eventID = ?
                  AND r.studentID = ?
                  AND (COALESCE(e.eventEndDate, e.eventDate) < ? OR e.status = 'ended')
                LIMIT 1
            ");
            $pastCheck->bind_param("iss", $feedbackEventID, $studentID, $today);
            $pastCheck->execute();
            $canFeedback = $pastCheck->get_result()->num_rows > 0;
            $pastCheck->close();

            if ($canFeedback) {
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

                header("Location: MyEvent.php?tab=history&feedback=1");
                exit();
            } else {
                $feedbackMessage = 'Feedback can only be submitted for past events you joined.';
            }
        }
    }

    // Registered events — separate active (ongoing/upcoming) from completed
    $today = date('Y-m-d');
    $activeStmt = $conn->prepare("SELECT e.*, a.clubName, c.clubID FROM events e JOIN registrations r ON e.eventID = r.eventID LEFT JOIN admins a ON e.adminID = a.adminID LEFT JOIN clubs c ON c.clubID = (SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1) WHERE r.studentID = ? AND e.status = 'approved' AND NOW() <= CONCAT(COALESCE(e.eventEndDate, e.eventDate), ' ', COALESCE(e.eventEndTime, '23:59:59')) ORDER BY e.eventDate ASC");
    $activeStmt->bind_param("s", $studentID);
    $activeStmt->execute();
    $activeEvents = $activeStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $activeStmt->close();

    $pastStmt = $conn->prepare("
        SELECT e.*, a.clubName, c.clubID, r.attendance_status, f.feedbackID, f.rating, f.`comment` AS feedbackComment,
               COALESCE(pc.participantCount, 0) AS participantCount
        FROM events e
        JOIN registrations r ON e.eventID = r.eventID
        LEFT JOIN admins a ON e.adminID = a.adminID
        LEFT JOIN clubs c ON c.clubID = (
            SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1
        )
        LEFT JOIN event_feedback f ON f.eventID = e.eventID AND f.studentID = r.studentID
        LEFT JOIN (
            SELECT eventID, COUNT(*) AS participantCount
            FROM registrations
            GROUP BY eventID
        ) pc ON pc.eventID = e.eventID
        WHERE r.studentID = ?
          AND ((e.status = 'approved' AND NOW() > CONCAT(COALESCE(e.eventEndDate, e.eventDate), ' ', COALESCE(e.eventEndTime, '23:59:59'))) OR e.status = 'ended')
        ORDER BY e.eventDate DESC
    ");
    $pastStmt->bind_param("s", $studentID);
    $pastStmt->execute();
    $pastEvents = $pastStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $pastStmt->close();

    // Club memberships
    $clubsStmt = $conn->prepare("SELECT a.clubName, a.clubEmail, cm.role, cm.joined_at, c.clubID, c.profilePic FROM club_members cm JOIN admins a ON cm.adminID = a.adminID LEFT JOIN clubs c ON c.clubID = (SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1) WHERE cm.studentID = ? ORDER BY a.clubName ASC");
    $clubsStmt->bind_param("s", $studentID);
    $clubsStmt->execute();
    $clubsResult = $clubsStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $clubsStmt->close();

    $validTabs = ['events', 'history', 'clubs'];
    $requestedTab = $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_feedback']) ? 'history' : ($_GET['tab'] ?? 'events');
    $activeTab = in_array($requestedTab, $validTabs, true) ? $requestedTab : 'events';
    $showFeedbackSaved = isset($_GET['feedback']) && $_GET['feedback'] === '1';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Activities</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <h2 class="clubs-title">My Activities</h2>

        <div class="tab-bar">
            <a href="MyEvent.php?tab=events" class="<?php echo $activeTab === 'events' ? 'active' : ''; ?>">My Events</a>
            <a href="MyEvent.php?tab=history" class="<?php echo $activeTab === 'history' ? 'active' : ''; ?>">Past Events</a>
            <a href="MyEvent.php?tab=clubs" class="<?php echo $activeTab === 'clubs' ? 'active' : ''; ?>">My Clubs</a>
        </div>

        <?php if ($showFeedbackSaved): ?>
            <div class="msg-banner feedback-success-banner">Feedback saved successfully.</div>
        <?php elseif ($feedbackMessage): ?>
            <div class="msg-banner feedback-error-banner"><?php echo htmlspecialchars($feedbackMessage); ?></div>
        <?php endif; ?>

        <?php if ($activeTab === 'events'): ?>
            <?php if (!empty($activeEvents)): ?>
                <?php foreach ($activeEvents as $row): ?>
                    <div class="horizontal-card active-event-card">
                        <div class="card-body">
                            <div class="active-event-head">
                                <span class="tag tag-confirmed">Event Confirmed</span>
                                <?php if (!empty($row['clubName'])): ?>
                                    <a href="ClubsDetails.php?id=<?php echo (int)($row['clubID'] ?? 0); ?>" class="active-event-club"><?php echo htmlspecialchars($row['clubName']); ?></a>
                                <?php endif; ?>
                            </div>
                            <h4><?php echo htmlspecialchars($row['eventTitle']); ?></h4>
                            <div class="active-event-meta">
                                <span>📅 <?php echo formatDateRange($row['eventDate'], $row['eventEndDate'] ?? null); ?></span>
                                <span>⏰ <?php echo date('h:iA', strtotime($row['eventTime'])); ?><?php if (!empty($row['eventEndTime'])): ?> - <?php echo date('h:iA', strtotime($row['eventEndTime'])); ?><?php endif; ?></span>
                                <span>📍 <?php echo htmlspecialchars($row['venue']); ?></span>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="DetailedEvent.php?id=<?php echo (int)$row['eventID']; ?>" class="btn-sm btn-sm-outline">View Details</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No upcoming events. Register for an event to see it here.</p>
                </div>
            <?php endif; ?>

        <?php endif; ?>

        <?php if ($activeTab === 'history'): ?>
            <?php if (!empty($pastEvents)): ?>
                <div class="past-events-list">
                    <?php foreach ($pastEvents as $row): ?>
                        <?php
                            $isPresent = ($row['attendance_status'] ?? 'absent') === 'present';
                            $hasFeedback = !empty($row['feedbackID']);
                        ?>
                        <article class="past-card">
                            <div class="past-card-body">
                                <div class="past-card-top">
                                    <div>
                                        <h4><?php echo htmlspecialchars($row['eventTitle']); ?> <span class="completed-pill">Completed</span></h4>
                                        <?php if (!empty($row['clubName'])): ?>
                                            <a href="ClubsDetails.php?id=<?php echo (int)($row['clubID'] ?? 0); ?>" class="past-club-link"><?php echo htmlspecialchars($row['clubName']); ?></a>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <div class="past-card-meta">
                                    <span><?php echo formatDateRange($row['eventDate'], $row['eventEndDate'] ?? null); ?> • <?php echo date('h:iA', strtotime($row['eventTime'])); ?><?php if (!empty($row['eventEndTime'])): ?> - <?php echo date('h:iA', strtotime($row['eventEndTime'])); ?><?php endif; ?></span>
                                    <span><?php echo htmlspecialchars($row['venue']); ?></span>
                                    <span class="<?php echo $isPresent ? 'att-present' : 'att-absent'; ?>"><?php echo $isPresent ? 'Present' : 'Absent'; ?></span>
                                </div>

                                <div class="past-card-feedback">
                                    <?php if ($hasFeedback): ?>
                                        <div class="past-feedback-current">
                                            <span class="feedback-stars"><?php echo str_repeat('★', (int)$row['rating']) . str_repeat('☆', 5 - (int)$row['rating']); ?></span>
                                            <?php if (!empty($row['feedbackComment'])): ?>
                                                <p><?php echo nl2br(htmlspecialchars($row['feedbackComment'])); ?></p>
                                            <?php endif; ?>
                                        </div>
                                    <?php endif; ?>
                                    <form method="POST">
                                        <input type="hidden" name="event_id" value="<?php echo (int)$row['eventID']; ?>">
                                        <div class="past-feedback-inputs">
                                            <div class="rating-row" aria-label="Star rating">
                                                <?php for ($star = 5; $star >= 1; $star--): ?>
                                                    <input type="radio" id="rating-<?php echo (int)$row['eventID']; ?>-<?php echo $star; ?>" name="rating" value="<?php echo $star; ?>" <?php echo (int)($row['rating'] ?? 0) === $star ? 'checked' : ''; ?> required>
                                                    <label for="rating-<?php echo (int)$row['eventID']; ?>-<?php echo $star; ?>" title="<?php echo ['Poor', 'Fair', 'Average', 'Good', 'Excellent'][$star - 1]; ?>">★</label>
                                                <?php endfor; ?>
                                            </div>
                                            <textarea name="comment" rows="2" placeholder="Leave a comment..."><?php echo htmlspecialchars($row['feedbackComment'] ?? ''); ?></textarea>
                                            <button type="submit" name="submit_feedback" class="btn-sm btn-sm-outline"><?php echo $hasFeedback ? 'Update' : 'Review'; ?></button>
                                        </div>
                                    </form>
                                </div>
                            </div>

                            <div class="past-card-side">
                                <a href="DetailedEvent.php?id=<?php echo (int)$row['eventID']; ?>" class="past-details-btn">Details</a>
                                <?php if ($isPresent): ?>
                                    <a href="Certificate.php?id=<?php echo (int)$row['eventID']; ?>" class="past-cert-card ready">Certificate</a>
                                <?php else: ?>
                                    <div class="past-cert-card locked">Certificate Locked</div>
                                <?php endif; ?>
                            </div>
                        </article>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No past events yet.</p>
                    <p class="empty-subtext">Events you joined will appear here after they have ended.</p>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($activeTab === 'clubs'): ?>
            <?php if (!empty($clubsResult)): ?>
                <?php foreach ($clubsResult as $club):
                    $clubName = htmlspecialchars($club['clubName']); ?>
                    <div class="horizontal-card">
                        <?php if (!empty($club['profilePic'])): ?>
                            <img src="<?php echo htmlspecialchars($club['profilePic']); ?>" class="club-avatar-sm" alt="<?php echo $clubName; ?>">
                        <?php else: ?>
                            <div class="club-icon bg-red-light">🏛️</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h4><?php echo htmlspecialchars($club['clubName']); ?></h4>
                            <div class="card-meta"><?php echo htmlspecialchars($club['clubEmail'] ?? ''); ?> · Joined <?php echo date('d M Y', strtotime($club['joined_at'])); ?></div>
                        </div>
                        <span class="club-role-badge"><?php echo htmlspecialchars(formatClubPosition($club['role'] ?? 'member')); ?></span>
                        <div class="card-actions">
                            <a href="ClubsDetails.php?id=<?php echo (int)$club['clubID']; ?>" class="btn-sm btn-sm-outline">Details →</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>You haven't joined any clubs yet.</p>
                    <a href="Clubs.php">Explore Clubs</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
