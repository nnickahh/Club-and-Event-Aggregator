<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $currentPage = 'clubs';

    if (!isset($_GET['id'])) {
        header("Location: ModeratorClubs.php");
        exit();
    }
    $adminID = $_GET['id'];
    $message = '';
    $msgType = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE admins SET status = 'active' WHERE adminID = ?");
                $stmt->bind_param("s", $adminID);
                $stmt->execute();
                $message = 'Club has been approved successfully.';
                $msgType = 'success';
            } elseif ($action === 'decline') {
                $stmt = $conn->prepare("UPDATE admins SET status = 'declined' WHERE adminID = ?");
                $stmt->bind_param("s", $adminID);
                $stmt->execute();
                $message = 'Club registration has been declined.';
                $msgType = 'success';
            } elseif ($action === 'update') {
                $clubNameUpd = trim($_POST['clubName'] ?? '');
                $clubEmailUpd = trim($_POST['clubEmail'] ?? '');
                $nameUpd = trim($_POST['name'] ?? '');
                $descriptionUpd = trim($_POST['description'] ?? '');
                $socialMediaUpd = trim($_POST['socialMedia'] ?? '');

                $stmt = $conn->prepare("UPDATE admins SET clubName=?, clubEmail=?, name=? WHERE adminID=?");
                $stmt->bind_param("ssss", $clubNameUpd, $clubEmailUpd, $nameUpd, $adminID);
                $stmt->execute();

                // Upsert clubs table
                $checkClubs = $conn->prepare("SELECT clubID FROM clubs WHERE adminID = ?");
                $checkClubs->bind_param("s", $adminID);
                $checkClubs->execute();
                $hasClubRecord = $checkClubs->get_result()->num_rows > 0;
                $checkClubs->close();

                if ($hasClubRecord) {
                    $updClubs = $conn->prepare("UPDATE clubs SET clubName=?, clubEmail=?, description=?, socialMedia=? WHERE adminID=?");
                    $updClubs->bind_param("sssss", $clubNameUpd, $clubEmailUpd, $descriptionUpd, $socialMediaUpd, $adminID);
                    $updClubs->execute();
                    $updClubs->close();
                } else {
                    $insClubs = $conn->prepare("INSERT INTO clubs (clubName, clubEmail, description, socialMedia, adminID) VALUES (?, ?, ?, ?, ?)");
                    $insClubs->bind_param("sssss", $clubNameUpd, $clubEmailUpd, $descriptionUpd, $socialMediaUpd, $adminID);
                    $insClubs->execute();
                    $insClubs->close();
                }

                $message = 'Club details updated successfully.';
                $msgType = 'success';
            } elseif ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM admins WHERE adminID = ?");
                $stmt->bind_param("s", $adminID);
                $stmt->execute();
                header("Location: ModeratorClubs.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $message = 'Database error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    // Fetch club data from admins + clubs tables
    $club = null;
    $clubPic = '';
    $description = '';
    $socialMedia = '';
    try {
        $stmt = $conn->prepare("SELECT a.*, c.profilePic AS clubPic, c.description, c.socialMedia FROM admins a LEFT JOIN clubs c ON a.adminID = c.adminID WHERE a.adminID = ?");
        $stmt->bind_param("s", $adminID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $club = $result->fetch_assoc();
            $clubPic = !empty($club['clubPic']) ? htmlspecialchars($club['clubPic']) : '';
            $description = htmlspecialchars($club['description'] ?? '');
            $socialMedia = htmlspecialchars($club['socialMedia'] ?? '');
        } else {
            die("Club not found.");
        }
    } catch (mysqli_sql_exception $e) {
        die("Database error: " . $e->getMessage());
    }

    $currentDate = date('Y-m-d');
    $eventStmt = $conn->prepare("SELECT * FROM events WHERE adminID = ? ORDER BY eventDate ASC");
    $eventStmt->bind_param("s", $adminID);
    $eventStmt->execute();
    $allEvents = $eventStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $eventStmt->close();

    $ongoingEvents = [];
    $upcomingEvents = [];
    foreach ($allEvents as $ev) {
        if ($ev['status'] === 'approved' && $ev['eventDate'] == $currentDate) {
            $ongoingEvents[] = $ev;
        } elseif ($ev['status'] === 'approved' && $ev['eventDate'] > $currentDate) {
            $upcomingEvents[] = $ev;
        }
    }

    $pendingClubs = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'pending'");
    if ($r) { $pendingClubs = $r->fetch_assoc()['c']; }

    $pendingEvtCount = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
    if ($r) { $pendingEvtCount = $r->fetch_assoc()['c']; }

    $clubStatus = $club['status'] ?? 'pending';
    $clubName   = htmlspecialchars($club['clubName']);
    $clubEmail  = htmlspecialchars($club['clubEmail']);
    $adminName  = htmlspecialchars($club['name']);
    $adminIDVal = htmlspecialchars($club['adminID']);

    // Fetch members
    $membersResult = null;
    $memberCount = 0;
    try {
        $membersStmt = $conn->prepare("
            SELECT cm.studentID, cm.role, cm.joined_at, s.name, s.email
            FROM club_members cm JOIN students s ON cm.studentID = s.studentID
            WHERE cm.adminID = ? ORDER BY cm.joined_at ASC
        ");
        $membersStmt->bind_param("s", $adminID);
        $membersStmt->execute();
        $membersResult = $membersStmt->get_result();
        $memberCount = $membersResult->num_rows;
        $membersStmt->close();
        $membersResult->data_seek(0);
    } catch (mysqli_sql_exception $e) {
        $memberCount = 0;
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $clubName; ?> - Club Details</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>

    <?php include 'ModeratorNavBar.php'; ?>

    <main class="profile-hero-container">
        <a href="ModeratorClubs.php" class="back-link">&larr; Back to Clubs</a>

        <?php if ($message): ?>
            <div style="padding:14px 18px;border-radius:var(--radius-md);margin-bottom:20px;font-weight:500;font-size:14px;background:<?php echo $msgType === 'success' ? 'var(--green-bg)' : 'var(--red-light)'; ?>;color:<?php echo $msgType === 'success' ? 'var(--green)' : 'var(--red)'; ?>;border:1px solid <?php echo $msgType === 'success' ? 'rgba(45,125,70,0.2)' : 'rgba(237,28,36,0.2)'; ?>;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form action="ClubDetailsModerator.php?id=<?php echo urlencode($adminIDVal); ?>" method="POST" id="mainProfileForm" enctype="multipart/form-data">
            <input type="hidden" name="action" value="update">

        <div class="profile-brand-card">
            <div class="brand-identity-flex">
                <div class="avatar-uploader-wrapper">
                    <?php if ($clubPic): ?>
                        <img src="<?php echo $clubPic; ?>" class="brand-avatar-img" alt="<?php echo $clubName; ?>" onclick="openViewer(this.src)" style="cursor:pointer;">
                    <?php else: ?>
                        <div style="width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:32px;background:#fef2f2;">🏆</div>
                    <?php endif; ?>
                </div>
                <div class="brand-meta-details-wrapper">
                    <?php if ($clubStatus === 'pending'): ?>
                        <span class="badge-role" style="background:var(--amber-bg,#fef3c7);color:var(--amber,#d97706);">Pending Review</span>
                    <?php elseif ($clubStatus === 'declined'): ?>
                        <span class="badge-role" style="background:var(--red-light);color:var(--red);">Declined</span>
                    <?php else: ?>
                        <span class="badge-role" style="background:#ecfdf5;color:#16a34a;">Active</span>
                    <?php endif; ?>
                    <div class="title-field-container">
                        <input type="text" name="clubName" id="clubNameInput" class="editable-field club-title-input" value="<?php echo $clubName; ?>" required readonly>
                    </div>
                    <p class="meta-subline">📍 INTI International University</p>
                </div>
            </div>

            <?php if ($clubStatus === 'pending'): ?>
                <div class="action-header-buttons">
                    <button type="button" class="btn-modern-secondary hide-on-edit" onclick="enableEditingMode()">✏️ Edit Club</button>
                    <button type="button" class="btn-modern-cancel show-on-edit" onclick="disableEditingMode()">Cancel</button>
                    <button type="submit" class="btn-modern-primary show-on-edit">💾 Save Changes</button>
                    <form method="POST" onsubmit="return confirm('Are you sure you want to decline this club registration?');" style="display:inline;">
                        <input type="hidden" name="action" value="decline">
                        <button type="submit" style="padding:8px 20px;background:#fff;color:var(--red);border:1.5px solid var(--red);border-radius:var(--radius-md);font-size:13px;font-weight:600;cursor:pointer;">Decline Club</button>
                    </form>
                </div>
            <?php else: ?>
                <div class="action-header-buttons">
                    <button type="button" class="btn-modern-secondary hide-on-edit" onclick="enableEditingMode()">✏️ Edit Club</button>
                    <button type="button" class="btn-modern-cancel show-on-edit" onclick="disableEditingMode()">Cancel</button>
                    <button type="submit" class="btn-modern-primary show-on-edit">💾 Save Changes</button>
                    <form method="POST" onsubmit="return confirm('Delete this entire club and all its data? This cannot be undone.');" style="display:inline;">
                        <input type="hidden" name="action" value="delete">
                        <button type="submit" style="padding:8px 20px;background:#fff;color:var(--red);border:1.5px solid var(--red);border-radius:var(--radius-md);font-size:13px;font-weight:600;cursor:pointer;">🗑️ Delete Club</button>
                    </form>
                </div>
            <?php endif; ?>
        </div>

        <div class="tab-navigation-bar">
            <button type="button" class="tab-trigger active" onclick="switchTab(event, 'overview-tab')">Overview</button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'events-tab')">Events <span class="tab-counter"><?php echo count($ongoingEvents) + count($upcomingEvents); ?></span></button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'members-tab')">Members <span class="tab-counter"><?php echo $memberCount; ?></span></button>
            <button type="button" class="tab-trigger" onclick="switchTab(event, 'contacts-tab')">Contacts</button>
        </div>

        <div id="overview-tab" class="tab-content-panel active">
            <div class="glass-editor-card">
                <div class="card-title-header">
                    <h3>About This Club</h3>
                </div>
                <div class="form-group-modern">
                    <textarea name="description" id="descriptionInput" class="editable-field" placeholder="No description available." readonly><?php echo $description; ?></textarea>
                </div>
            </div>
        </div>

        <div id="contacts-tab" class="tab-content-panel">
            <div class="glass-editor-card">
                <div class="card-title-header">
                    <h3>Contact Details</h3>
                </div>
                <div class="form-input-grid">
                    <div class="form-group-modern">
                        <label>Official Contact Email</label>
                        <input type="email" name="clubEmail" id="clubEmailInput" class="editable-field text-input" required value="<?php echo $clubEmail; ?>" readonly>
                    </div>
                    <div class="form-group-modern">
                        <label>Admin Name</label>
                        <input type="text" name="name" id="adminNameInput" class="editable-field text-input" value="<?php echo $adminName; ?>" readonly>
                    </div>
                    <div class="form-group-modern">
                        <label>Admin ID</label>
                        <input type="text" value="<?php echo $adminIDVal; ?>" readonly style="background:#f8fafc;">
                    </div>
                    <div class="form-group-modern">
                        <label>Social Media & Other Contacts</label>
                        <textarea name="socialMedia" id="socialMediaInput" class="editable-field" placeholder="Instagram: @yourclub&#10;Facebook: /yourclub&#10;Website: https://..." readonly><?php echo htmlspecialchars($socialMedia ?? ''); ?></textarea>
                    </div>
                </div>
            </div>
        </div>

        </form>

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
                                <a href="EventDetailsModerator.php?id=<?php echo (int)$ev['eventID']; ?>" class="action-pill-btn" style="text-decoration:none;">View Details</a>
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
                                <a href="EventDetailsModerator.php?id=<?php echo (int)$ev['eventID']; ?>" class="action-pill-btn" style="text-decoration:none;">View Details</a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <?php if (empty($ongoingEvents) && empty($upcomingEvents)): ?>
                <div class="empty-state-canvas">
                    <div class="empty-icon">📅</div>
                    <h4>No events yet</h4>
                    <p>This club has not created any approved events.</p>
                </div>
            <?php endif; ?>
        </div>

        <div id="members-tab" class="tab-content-panel">
            <div class="section-flex-header">
                <h3>Club Members (<?php echo $memberCount; ?>)</h3>
            </div>
            <?php if ($memberCount > 0): ?>
                <div class="table-card-container">
                    <table class="premium-modern-table">
                        <thead>
                            <tr>
                                <th>No.</th>
                                <th>Student ID</th>
                                <th>Full Name</th>
                                <th>Official Email Address</th>
                                <th>Role</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php $i = 1; $membersResult->data_seek(0); while($memb = $membersResult->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $i++; ?></td>
                                    <td><span class="id-tag"><?php echo htmlspecialchars($memb['studentID']); ?></span></td>
                                    <td class="user-cell-name"><strong><?php echo htmlspecialchars($memb['name']); ?></strong></td>
                                    <td><a href="mailto:<?php echo htmlspecialchars($memb['email']); ?>" class="email-link"><?php echo htmlspecialchars($memb['email']); ?></a></td>
                                    <td>
                                        <span style="display:inline-block;padding:3px 10px;border-radius:12px;font-size:12px;font-weight:600;background:<?php echo $memb['role'] === 'member' ? '#f0fdf4' : '#eff6ff'; ?>;color:<?php echo $memb['role'] === 'member' ? '#16a34a' : '#2563eb'; ?>;">
                                            <?php echo htmlspecialchars($memb['role']); ?>
                                        </span>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
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
    </main>

    <div id="clubViewer" class="avatar-viewer-modal" onclick="closeViewer()">
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

        function enableEditingMode() {
            document.body.classList.add('is-editing-mode');
            document.getElementById('clubNameInput').removeAttribute('readonly');
            document.getElementById('descriptionInput').removeAttribute('readonly');
            document.getElementById('clubEmailInput').removeAttribute('readonly');
            document.getElementById('adminNameInput').removeAttribute('readonly');
            document.getElementById('socialMediaInput').removeAttribute('readonly');
        }

        function disableEditingMode() {
            document.body.classList.remove('is-editing-mode');
            document.getElementById('clubNameInput').setAttribute('readonly', 'true');
            document.getElementById('descriptionInput').setAttribute('readonly', 'true');
            document.getElementById('clubEmailInput').setAttribute('readonly', 'true');
            document.getElementById('adminNameInput').setAttribute('readonly', 'true');
            document.getElementById('socialMediaInput').setAttribute('readonly', 'true');
        }

        function openViewer(src) {
            document.getElementById('clubViewerImage').src = src;
            document.getElementById('clubViewer').classList.add('active');
        }
        function closeViewer() {
            document.getElementById('clubViewer').classList.remove('active');
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') closeViewer();
        });
    </script>
</body>
</html>
