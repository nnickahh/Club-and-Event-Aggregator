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
        if ($ev['eventDate'] == $currentDate) {
            $ongoingEvents[] = $ev;
        } elseif ($ev['eventDate'] > $currentDate) {
            $upcomingEvents[] = $ev;
        } else {
            $completedEvents[] = $ev;
        }
    }

    // Fetch member count and list from club_members
    $memCountStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM club_members WHERE adminID = ?");
    $memCountStmt->bind_param("s", $adminID);
    $memCountStmt->execute();
    $memberCount = $memCountStmt->get_result()->fetch_assoc()['cnt'];
    $memCountStmt->close();

    $membersStmt = $conn->prepare("
        SELECT cm.studentID, cm.role, cm.joined_at, s.name, s.email
        FROM club_members cm
        JOIN students s ON cm.studentID = s.studentID
        WHERE cm.adminID = ?
        ORDER BY cm.joined_at ASC
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
        $notifMsg = "$studentName joined your club";
        $notifStmt = $conn->prepare("INSERT INTO notifications (adminID, message) VALUES (?, ?)");
        $notifStmt->bind_param("ss", $adminID, $notifMsg);
        $notifStmt->execute();
        $notifStmt->close();
        header("Location: ClubsDetails.php?id=" . $clubID);
        exit();
    }

    // Handle quit club
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['quit_club'])) {
        $quitStmt = $conn->prepare("DELETE FROM club_members WHERE studentID = ? AND adminID = ?");
        $quitStmt->bind_param("ss", $studentID, $adminID);
        $quitStmt->execute();
        $quitStmt->close();
        header("Location: ClubsDetails.php?id=" . $clubID);
        exit();
    }

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
        <div class="profile-brand-card" style="margin-top:16px;">
            <div class="brand-identity-flex">
                <div class="avatar-uploader-wrapper" style="cursor:default;">
                    <?php if ($profilePic): ?>
                        <img src="<?php echo $profilePic; ?>" class="brand-avatar-img" alt="<?php echo $clubName; ?>" onclick="openClubViewer(this.src)" style="cursor:pointer;">
                    <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:32px;background:#fef2f2;">🏆</div>
                    <?php endif; ?>
                </div>
                <div class="brand-meta-details-wrapper">
                    <span class="badge-role"><?php echo $category; ?></span>
                    <div class="title-field-container">
                        <h1 style="font-size:26px;font-weight:700;margin:4px 0;color:var(--premium-ink-dark);"><?php echo $clubName; ?></h1>
                    </div>
                    <p class="meta-subline" style="margin:0;">📍 INTI International University</p>
                    <div style="margin-top:10px;">
                        <?php if ($isMember): ?>
                            <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                                <span style="display:inline-flex;align-items:center;gap:6px;padding:6px 16px;border-radius:20px;font-size:13px;font-weight:600;background:#f0fdf4;color:#16a34a;border:1px solid #86efac;">✓ Joined</span>
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Are you sure you want to quit this club?');">
                                    <button type="submit" name="quit_club" style="padding:6px 16px;border:1px solid #e2e8f0;border-radius:20px;font-size:12px;font-weight:500;background:#fff;color:#64748b;cursor:pointer;transition:all 0.2s;" onmouseover="this.style.borderColor='#dc2626';this.style.color='#dc2626'" onmouseout="this.style.borderColor='#e2e8f0';this.style.color='#64748b'">Quit Club</button>
                                </form>
                            </div>
                        <?php else: ?>
                            <form method="POST" style="display:inline;">
                                <button type="submit" name="join_club" style="padding:6px 20px;border:none;border-radius:20px;font-size:13px;font-weight:600;background:var(--red,#dc2626);color:#fff;cursor:pointer;transition:opacity 0.2s;" onmouseover="this.style.opacity=0.85" onmouseout="this.style.opacity=1">+ Join Club</button>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tabs -->
        <div class="tab-navigation-bar" style="margin-top:24px;">
            <button type="button" class="tab-trigger active" onclick="switchTab(event, 'overview-tab')">Overview</button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'events-tab')">Events <span class="tab-counter"><?php echo count($ongoingEvents) + count($upcomingEvents); ?></span></button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'members-tab')">Members <span class="tab-counter"><?php echo $memberCount; ?></span></button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'contacts-tab')">Contacts</button>
        </div>

        <!-- Overview tab -->
        <div id="overview-tab" class="tab-content-panel active">
            <div class="glass-editor-card">
                <div class="card-title-header">
                    <h3>About Our Club</h3>
                </div>
                <div class="form-group-modern">
                    <p style="font-size:15px;line-height:1.7;color:var(--ink-2,#475569);margin:0;white-space:pre-line;"><?php echo $description ? nl2br($description) : 'No description available.'; ?></p>
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
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="DetailedEvent.php?id=<?php echo (int)$ev['eventID']; ?>" class="action-pill-btn" style="text-decoration:none;">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($upcomingEvents)): ?>
                <div class="section-flex-header" style="<?php echo !empty($ongoingEvents) ? 'margin-top:24px;' : ''; ?>">
                    <h3>Upcoming Events</h3>
                </div>
                <div class="modern-events-list">
                    <?php foreach($upcomingEvents as $ev): ?>
                        <div class="event-strip-card">
                            <div class="date-badge-box">
                                <span class="day-num"><?php echo date('d', strtotime($ev['eventDate'])); ?></span>
                                <span class="month-txt"><?php echo date('M', strtotime($ev['eventDate'])); ?></span>
                            </div>
                            <div class="strip-main-info">
                                <h4><?php echo htmlspecialchars($ev['eventTitle']); ?></h4>
                                <p class="strip-meta">⏰ <?php echo date('h:iA', strtotime($ev['eventTime'])); ?><?php if (!empty($ev['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($ev['eventEndTime'])); ?><?php endif; ?> • 📍 <?php echo htmlspecialchars($ev['venue']); ?></p>
                            </div>
                            <div class="strip-actions">
                                <a href="DetailedEvent.php?id=<?php echo (int)$ev['eventID']; ?>" class="action-pill-btn" style="text-decoration:none;">View Details</a>
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
                <h3>Members (<?php echo $memberCount; ?>)</h3>
            </div>
            <?php if (!empty($members)): ?>
                <div class="table-wrapper" style="overflow-x:auto;">
                    <table class="members-table" style="width:100%;border-collapse:collapse;font-size:14px;">
                        <thead>
                            <tr style="background:#f8fafc;border-bottom:2px solid #e2e8f0;">
                                <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600;">No.</th>
                                <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600;">Student ID</th>
                                <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600;">Name</th>
                                <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600;">Email</th>
                                <th style="padding:12px 16px;text-align:left;color:#64748b;font-weight:600;">Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; foreach ($members as $m): ?>
                                <tr style="border-bottom:1px solid #e2e8f0;">
                                    <td style="padding:12px 16px;"><?php echo $i++; ?></td>
                                    <td style="padding:12px 16px;"><?php echo htmlspecialchars($m['studentID']); ?></td>
                                    <td style="padding:12px 16px;"><?php echo htmlspecialchars($m['name']); ?></td>
                                    <td style="padding:12px 16px;"><?php echo htmlspecialchars($m['email']); ?></td>
                                    <td style="padding:12px 16px;">
                                        <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:<?php echo $m['role'] === 'member' ? '#f0fdf4' : '#eff6ff'; ?>;color:<?php echo $m['role'] === 'member' ? '#16a34a' : '#2563eb'; ?>;">
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
                    <h4>No members yet</h4>
                    <p>Students who register for this club's events will appear here.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Contacts tab -->
        <div id="contacts-tab" class="tab-content-panel">
            <div class="glass-editor-card">
                <div class="card-title-header">
                    <h3>Contact Details</h3>
                </div>
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(280px,1fr));gap:20px;">
                    <?php if ($clubEmail): ?>
                    <div>
                        <label style="font-size:12px;font-weight:700;color:var(--ink-3,#94a3b8);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:4px;">Email</label>
                        <p style="font-size:15px;color:var(--ink,#1a1a2e);margin:0;"><?php echo $clubEmail; ?></p>
                    </div>
                    <?php endif; ?>
                    <?php if ($socialMedia): ?>
                    <div style="grid-column:1/-1;">
                        <label style="font-size:12px;font-weight:700;color:var(--ink-3,#94a3b8);text-transform:uppercase;letter-spacing:0.5px;display:block;margin-bottom:4px;">Social Media & Contacts</label>
                        <p style="font-size:15px;color:var(--ink,#1a1a2e);margin:0;white-space:pre-line;"><?php echo nl2br(htmlspecialchars($socialMedia)); ?></p>
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
