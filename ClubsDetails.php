<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    if (!isset($_GET['id'])) {
        header("Location: Clubs.php");
        exit();
    }
    $clubID = (int)$_GET['id'];

    // Fetch club details
    $stmt = $conn->prepare("SELECT * FROM clubs WHERE clubID = ?");
    $stmt->bind_param("i", $clubID);
    $stmt->execute();
    $club = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$club) {
        die("Club not found.");
    }

    $clubName    = htmlspecialchars($club['clubName']);
    $description = htmlspecialchars($club['description'] ?? '');
    $clubEmail   = htmlspecialchars($club['clubEmail'] ?? '');
    $profilePic  = !empty($club['profilePic']) ? htmlspecialchars($club['profilePic']) : '';
    $socialMedia = htmlspecialchars($club['socialMedia'] ?? '');
    $category    = htmlspecialchars($club['category'] ?? 'Club');

    $adminID = $club['adminID'];
    $currentDate = date('Y-m-d');

    // Fetch approved events
    $eventStmt = $conn->prepare("SELECT * FROM events WHERE adminID = ? AND status = 'approved' ORDER BY eventDate ASC");
    $eventStmt->bind_param("s", $adminID);
    $eventStmt->execute();
    $allEvents = $eventStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $eventStmt->close();

    $ongoingEvents = [];
    $upcomingEvents = [];
    $completedEvents = [];
    foreach ($allEvents as $ev) {
        $p = getEventPeriod($ev['eventDate'], $ev['eventEndDate'] ?? null, $currentDate);
        if ($p === 'ongoing') {
            $ongoingEvents[] = $ev;
        } elseif ($p === 'upcoming') {
            $upcomingEvents[] = $ev;
        } else {
            $completedEvents[] = $ev;
        }
    }

    // Fetch member count (only key positions visible to students)
    $memCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM club_members WHERE adminID = ? AND LOWER(role) IN ('president', 'secretary', 'treasurer')");
    $memCountStmt->bind_param("s", $adminID);
    $memCountStmt->execute();
    $memberCount = $memCountStmt->get_result()->fetch_assoc()['cnt'];
    $memCountStmt->close();

    $membersStmt = $conn->prepare("
        SELECT cm.studentID, cm.role, cm.joined_at, s.name, s.email
        FROM club_members cm
        JOIN students s ON cm.studentID = s.studentID
        WHERE cm.adminID = ? AND LOWER(cm.role) IN ('president', 'secretary', 'treasurer')
        ORDER BY FIELD(LOWER(cm.role), 'president', 'secretary', 'treasurer'), cm.joined_at ASC
    ");
    $membersStmt->bind_param("s", $adminID);
    $membersStmt->execute();
    $members = $membersStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $membersStmt->close();

    // Check if current student is a member of this club
    $studentID = $_SESSION['student_id'];
    $memberCheckStmt = $conn->prepare("SELECT * FROM club_members WHERE studentID = ? AND adminID = ?");
    $memberCheckStmt->bind_param("ss", $studentID, $adminID);
    $memberCheckStmt->execute();
    $isMember = $memberCheckStmt->get_result()->num_rows > 0;
    $memberCheckStmt->close();

    // Handle join club
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['join_club'])) {
        $joinStmt = $conn->prepare("INSERT IGNORE INTO club_members (studentID, adminID) VALUES (?, ?)");
        $joinStmt->bind_param("ss", $studentID, $adminID);
        $joinStmt->execute();
        $joinStmt->close();
        // Notify admin
        $nameStmt = $conn->prepare("SELECT name FROM students WHERE studentID = ?");
        $nameStmt->bind_param("s", $studentID);
        $nameStmt->execute();
        $nameResult = $nameStmt->get_result();
        $studentName = ($nameRow = $nameResult->fetch_assoc()) ? $nameRow['name'] : $studentID;
        $nameStmt->close();
        $notifStmt = $conn->prepare("INSERT INTO notifications (adminID, message, clubID) VALUES (?, ?, ?)");
        $notifMsg = "$studentName joined your club";
        $notifStmt->bind_param("ssi", $adminID, $notifMsg, $clubID);
        $notifStmt->execute();
        $notifStmt->close();
        // Auto-subscribe to club notifications
        $subStmt = $conn->prepare("INSERT IGNORE INTO club_notify (studentID, adminID) VALUES (?, ?)");
        $subStmt->bind_param("ss", $studentID, $adminID);
        $subStmt->execute();
        $subStmt->close();
        header("Location: ClubsDetails.php?id=" . $clubID);
        exit();
    }

    // Handle quit club
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quit_club'])) {
        $quitStmt = $conn->prepare("DELETE FROM club_members WHERE studentID = ? AND adminID = ?");
        $quitStmt->bind_param("ss", $studentID, $adminID);
        $quitStmt->execute();
        $quitStmt->close();
        // Auto-unsubscribe from club notifications
        $unsubStmt = $conn->prepare("DELETE FROM club_notify WHERE studentID = ? AND adminID = ?");
        $unsubStmt->bind_param("ss", $studentID, $adminID);
        $unsubStmt->execute();
        $unsubStmt->close();
        header("Location: ClubsDetails.php?id=" . $clubID);
        exit();
    }

    // Handle notify subscribe
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_sub'])) {
        $subStmt = $conn->prepare("INSERT IGNORE INTO club_notify (studentID, adminID) VALUES (?, ?)");
        $subStmt->bind_param("ss", $studentID, $adminID);
        $subStmt->execute();
        $subStmt->close();
        header("Location: ClubsDetails.php?id=" . $clubID);
        exit();
    }

    // Handle notify unsubscribe
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['notify_unsub'])) {
        $unsubStmt = $conn->prepare("DELETE FROM club_notify WHERE studentID = ? AND adminID = ?");
        $unsubStmt->bind_param("ss", $studentID, $adminID);
        $unsubStmt->execute();
        $unsubStmt->close();
        header("Location: ClubsDetails.php?id=" . $clubID);
        exit();
    }

    // Check if current student is subscribed to notifications for this club
    $notifyStmt = $conn->prepare("SELECT * FROM club_notify WHERE studentID = ? AND adminID = ?");
    $notifyStmt->bind_param("ss", $studentID, $adminID);
    $notifyStmt->execute();
    $isNotifying = $notifyStmt->get_result()->num_rows > 0;
    $notifyStmt->close();

    // Check if current student is registered for any of this club's events
    $regStmt = $conn->prepare("SELECT r.eventID FROM registrations r JOIN events e ON r.eventID = e.eventID WHERE e.adminID = ? AND r.studentID = ?");
    $regStmt->bind_param("ss", $adminID, $studentID);
    $regStmt->execute();
    $registeredEventIDs = array_column($regStmt->get_result()->fetch_all(MYSQLI_ASSOC), 'eventID');
    $regStmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $clubName; ?> - Club Details</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">

        <a href="Clubs.php" class="back-link">&larr; Back to Clubs</a>

        <!-- Profile brand card -->
        <div class="profile-brand-card mt-16">
            <div class="brand-identity-flex">
                <div class="avatar-uploader-wrapper cursor-default">
                    <?php if ($profilePic): ?>
                        <img src="<?php echo $profilePic; ?>" class="brand-avatar-img" alt="<?php echo $clubName; ?>" onclick="openClubViewer(this.src)" class="cursor-pointer">
                    <?php else: ?>
                        <div class="avatar-fallback">🏆</div>
                    <?php endif; ?>
                </div>
                <div class="brand-meta-details-wrapper">
                    <span class="badge-role"><?php echo $category; ?></span>
                    <div class="title-field-container">
                        <h1 class="club-detail-h1"><?php echo $clubName; ?></h1>
                    </div>
                    <p class="meta-subline club-meta-m0">📍 INTI International University</p>
                    <div class="mt-10">
                        <?php if ($isMember): ?>
                            <div class="flex-row">
                                <span class="badge-joined">✓ Joined</span>
                                <form method="POST" class="flex-inline" onsubmit="return confirm('Are you sure you want to quit this club?');">
                                    <button type="submit" name="quit_club" class="btn-outline-secondary-hover" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">Quit Club</button>
                                </form>
                                <?php if ($isNotifying): ?>
                                <span class="badge-notifying">🔔 Notifying</span>
                                <form method="POST" class="flex-inline">
                                    <button type="submit" name="notify_unsub" class="btn-outline-secondary">Unsubscribe</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="flex-inline">
                                    <button type="submit" name="notify_sub" class="btn-outline-secondary">🔔 Notify Me</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php else: ?>
                            <div class="flex-row">
                                <form method="POST" class="flex-inline">
                                    <button type="submit" name="join_club" class="btn-join" onmouseover="this.style.opacity=0.85" onmouseout="this.style.opacity=1">+ Join Club</button>
                                </form>
                                <?php if ($isNotifying): ?>
                                <span class="badge-notifying">🔔 Notifying</span>
                                <form method="POST" class="flex-inline">
                                    <button type="submit" name="notify_unsub" class="btn-outline-secondary">Unsubscribe</button>
                                </form>
                                <?php else: ?>
                                <form method="POST" class="flex-inline">
                                    <button type="submit" name="notify_sub" class="btn-outline-secondary">🔔 Notify Me</button>
                                </form>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-navigation-bar mt-24">
            <button type="button" class="tab-trigger active" onclick="switchTab(event, 'overview-tab')">Overview</button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'events-tab')">Events <span class="tab-counter"><?php echo count($ongoingEvents) + count($upcomingEvents); ?></span></button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'members-tab')">Committee <span class="tab-counter"><?php echo $memberCount; ?></span></button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'contacts-tab')">Contacts</button>
        </div>

        <!-- Overview tab -->
        <div id="overview-tab" class="tab-content-panel active">
            <div class="glass-editor-card">
                <div class="card-title-header">
                    <h3>About Our Club</h3>
                </div>
                <div class="form-group-modern">
                    <p class="club-detail-desc"><?php echo $description ? nl2br($description) : 'No description available.'; ?></p>
                </div>
            </div>
        </div>

        <!-- Events tab -->
        <div id="events-tab" class="tab-content-panel">
            <?php if (!empty($ongoingEvents)): ?>
                <div class="section-flex-header">
                    <h3>Ongoing Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($ongoingEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <?php if (!empty($ev['eventEndDate']) && $ev['eventEndDate'] !== $ev['eventDate']): ?>
                                    <span class="day-num"><?php echo date('j', strtotime($ev['eventDate'])) . '-' . date('j', strtotime($ev['eventEndDate'])); ?></span>
                                    <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                                <?php else: ?>
                                    <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                    <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="DetailedEvent.php?id=<?php echo (int)$ev['eventID']; ?>" class="action-pill-btn pill-btn-no-deco">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($upcomingEvents)): ?>
                <div class="section-flex-header <?php echo !empty($ongoingEvents) ? 'cond-mt' : ''; ?>">
                    <h3>Upcoming Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($upcomingEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <?php if (!empty($ev['eventEndDate']) && $ev['eventEndDate'] !== $ev['eventDate']): ?>
                                    <span class="day-num"><?php echo date('j', strtotime($ev['eventDate'])) . '-' . date('j', strtotime($ev['eventEndDate'])); ?></span>
                                    <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                                <?php else: ?>
                                    <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                    <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                                <?php endif; ?>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="DetailedEvent.php?id=<?php echo (int)$ev['eventID']; ?>" class="action-pill-btn pill-btn-no-deco">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($ongoingEvents) && empty($upcomingEvents)): ?>
                <div class="empty-state-canvas">
                    <div class="empty-icon">📅</div>
                    <h4>No upcoming events</h4>
                    <p>Check back later for new activities from this club.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Members tab -->
        <div id="members-tab" class="tab-content-panel">
            <div class="section-flex-header">
                <h3>Committee (<?php echo $memberCount; ?>)</h3>
            </div>
            <?php if (!empty($members)): ?>
                <div class="table-responsive">
                    <table class="members-table">
                        <thead>
                            <tr class="members-table-head">
                                <th>No.</th>
                                <th>Student ID</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($members as $m): ?>
                                <tr class="members-table-row">
                                    <td><?php echo $i++; ?></td>
                                    <td><?php echo htmlspecialchars($m['studentID']); ?></td>
                                    <td><?php echo htmlspecialchars($m['name']); ?></td>
                                    <td><?php echo htmlspecialchars($m['email']); ?></td>
                                    <td>
                                        <span class="badge-role-tag" style="background:<?php echo $m['role'] === 'member' ? '#f0fdf4' : '#eff6ff'; ?>;color:<?php echo $m['role'] === 'member' ? '#16a34a' : '#2563eb'; ?>;">
                                            <?php echo htmlspecialchars($m['role']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state-canvas">
                    <div class="empty-icon">👥</div>
                    <h4>No committee yet</h4>
                    <p>The club president, secretary, and treasurer will appear here once assigned.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contacts tab -->
        <div id="contacts-tab" class="tab-content-panel">
            <div class="glass-editor-card">
                <div class="card-title-header">
                    <h3>Contact Details</h3>
                </div>
                <div class="contact-grid">
                    <?php if ($clubEmail): ?>
                    <div>
                        <label class="contact-label">Email</label>
                        <p class="contact-value"><?php echo $clubEmail; ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($socialMedia): ?>
                    <div class="contact-full">
                        <label class="contact-label">Social Media & Contacts</label>
                        <p class="contact-value-pre"><?php echo nl2br(htmlspecialchars($socialMedia)); ?></p>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </main>

    <!-- Avatar viewer modal -->
    <div id="clubViewer" class="avatar-viewer-modal" onclick="closeClubViewer()">
        <span class="avatar-viewer-close">&times;</span>
        <img class="avatar-viewer-image" id="clubViewerImage" alt="Enlarged club logo">
    </div>

    <script>
        function switchTab(event, tabId) {
            document.querySelectorAll('.tab-content-panel').forEach(p => p.classList.remove('active'));
            document.querySelectorAll('.tab-trigger').forEach(t => t.classList.remove('active'));
            document.getElementById(tabId).classList.add('active');
            event.currentTarget.classList.add('active');
        }
        function openClubViewer(src) {
            document.getElementById('clubViewerImage').src = src;
            document.getElementById('clubViewer').classList.add('active');
        }
        function closeClubViewer() {
            document.getElementById('clubViewer').classList.remove('active');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeClubViewer();
        });
    </script>
</body>
</html>
