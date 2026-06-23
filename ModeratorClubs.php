<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $currentPage = 'clubs';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
    $clubs = [];
    $counts = ['pending' => 0, 'approved' => 0];
    $msg = '';
    $msgType = '';

    // Handle inline Accept / Decline
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['club_action'], $_POST['adminID'])) {
        $adminID = $_POST['adminID'];
        $action  = $_POST['club_action'];

        try {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE admins SET status = 'active' WHERE adminID = ? AND status = 'pending'");
                $stmt->bind_param("s", $adminID);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    // Upsert into clubs table
                    $info = $conn->prepare("SELECT * FROM admins WHERE adminID = ?");
                    $info->bind_param("s", $adminID);
                    $info->execute();
                    $row = $info->get_result()->fetch_assoc();
                    $info->close();
                    if ($row) {
                        $chk = $conn->prepare("SELECT clubID FROM clubs WHERE adminID = ?");
                        $chk->bind_param("s", $adminID);
                        $chk->execute();
                        if ($chk->get_result()->num_rows === 0) {
                            $ins = $conn->prepare("INSERT INTO clubs (clubName, clubEmail, adminID) VALUES (?, ?, ?)");
                            $ins->bind_param("sss", $row['clubName'], $row['clubEmail'], $adminID);
                            $ins->execute();
                            $ins->close();
                        }
                        $chk->close();
                    }
                    $msg = 'Club approved successfully.';
                    $msgType = 'success';
                }
            } elseif ($action === 'decline') {
                $stmt = $conn->prepare("UPDATE admins SET status = 'declined' WHERE adminID = ? AND status = 'pending'");
                $stmt->bind_param("s", $adminID);
                $stmt->execute();
                if ($stmt->affected_rows > 0) {
                    $msg = 'Club registration declined.';
                    $msgType = 'success';
                }
            }
        } catch (mysqli_sql_exception $e) {
            $msg = 'Database error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'pending'");
        $counts['pending'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'active'");
        $counts['approved'] = $r ? $r->fetch_assoc()['c'] : 0;

        if ($tab === 'pending') {
            $result = $conn->query("SELECT * FROM admins WHERE status = 'pending' ORDER BY created_at DESC");
        } else {
            $result = $conn->query("SELECT * FROM admins WHERE status = 'active' ORDER BY created_at DESC");
        }
        if ($result) { $clubs = $result->fetch_all(MYSQLI_ASSOC); }
    } catch (mysqli_sql_exception $e) {
        error_log('ModeratorClubs DB error: ' . $e->getMessage());
    }

    $pendingClubs = $counts['pending'];

    $pendingEvents = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
    if ($r) { $pendingEvents = $r->fetch_assoc()['c']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator - Clubs</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">

        <?php if ($msg): ?>
            <div class="msg-banner" style="background:<?php echo $msgType === 'success' ? 'var(--green-bg)' : 'var(--red-light)'; ?>;color:<?php echo $msgType === 'success' ? 'var(--green)' : 'var(--red)'; ?>;border:1px solid <?php echo $msgType === 'success' ? 'rgba(45,125,70,0.2)' : 'rgba(237,28,36,0.2)'; ?>;">
                <?php echo htmlspecialchars($msg); ?>
            </div>
        <?php endif; ?>

        <div class="mod-page-header">
            <div>
                <h2 class="mod-title">Clubs</h2>
                <p class="mod-sub">Manage club registrations across campus.</p>
            </div>
        </div>

        <div class="mod-tab-nav">
            <a href="ModeratorClubs.php?tab=pending" class="mod-tab-link <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                Pending <?php if ($counts['pending'] > 0): ?><span class="tab-count"><?php echo $counts['pending']; ?></span><?php endif; ?>
            </a>
            <a href="ModeratorClubs.php?tab=approved" class="mod-tab-link <?php echo $tab === 'approved' ? 'active' : ''; ?>">
                Clubs <?php if ($counts['approved'] > 0): ?><span class="tab-count"><?php echo $counts['approved']; ?></span><?php endif; ?>
            </a>
        </div>

        <section class="event-grid">
            <?php if (!empty($clubs)): ?>
                <?php foreach ($clubs as $club): ?>
                    <article class="event-card mod-card">
                        <div class="card-stripe" data-color="<?php echo $tab === 'pending' ? 'amber' : 'green'; ?>"></div>
                        <div class="card-body">

                            <?php if ($tab === 'pending'): ?>
                                <span class="mod-pending-badge">
                                    <span class="mod-badge-dot"></span>
                                    Pending review
                                </span>
                            <?php else: ?>
                                <span class="mod-status-tag approved">Approved</span>
                            <?php endif; ?>

                            <h3><?php echo htmlspecialchars($club['clubName']); ?></h3>

                            <div class="event-meta">
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                                    <span><strong>Admin ID:</strong> <?php echo htmlspecialchars($club['adminID']); ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <span><strong>Admin:</strong> <?php echo htmlspecialchars($club['name']); ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <span><strong>Email:</strong> <?php echo htmlspecialchars($club['clubEmail']); ?></span>
                                </div>
                            </div>

                            <div class="card-divider"></div>

                            <div class="mod-card-actions">
                                <?php if ($tab === 'pending'): ?>
                                    <form method="POST" class="flex-inline">
                                        <input type="hidden" name="adminID" value="<?php echo htmlspecialchars($club['adminID']); ?>">
                                        <input type="hidden" name="club_action" value="approve">
                                        <button type="submit" class="btn-mod-accept" onclick="return confirm('Approve this club registration?')">Accept</button>
                                    </form>
                                    <form method="POST" class="flex-inline">
                                        <input type="hidden" name="adminID" value="<?php echo htmlspecialchars($club['adminID']); ?>">
                                        <input type="hidden" name="club_action" value="decline">
                                        <button type="submit" class="btn-mod-decline" onclick="return confirm('Decline this club registration?')">Decline</button>
                                    </form>
                                    <button type="button" class="btn-mod-details" onclick="openRegModal('<?php echo htmlspecialchars($club['adminID'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($club['clubName'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($club['name'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($club['clubEmail'], ENT_QUOTES); ?>', '<?php echo htmlspecialchars($club['created_at'] ?? '', ENT_QUOTES); ?>')">View</button>
                                <?php else: ?>
                                    <a href="ClubDetailsModerator.php?id=<?php echo urlencode($club['adminID']); ?>" class="btn-mod-details">Details</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    </div>
                    <p>No <?php echo $tab === 'pending' ? 'pending' : 'approved'; ?> clubs</p>
                    <p class="empty-subtext">There are no clubs in this category right now.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <!-- Registration Details Modal -->
    <div id="regModal" class="modal-overlay" onclick="closeRegModal(event)">
        <div class="modal-box" onclick="event.stopPropagation()">
            <button type="button" class="modal-close" onclick="closeRegModal()">&times;</button>
            <h3>Registration Details</h3>
            <hr class="notif-sep">
            <table class="reg-detail-table">
                <tr><td class="reg-label">Club Name</td><td id="regClubName"></td></tr>
                <tr><td class="reg-label">Admin Name</td><td id="regAdminName"></td></tr>
                <tr><td class="reg-label">Admin ID</td><td id="regAdminID"></td></tr>
                <tr><td class="reg-label">Club Email</td><td id="regClubEmail"></td></tr>
                <tr><td class="reg-label">Submitted</td><td id="regDate"></td></tr>
            </table>
            <hr class="notif-sep">
            <div class="modal-actions">
                <form method="POST" class="flex-inline">
                    <input type="hidden" name="adminID" id="modalApproveID" value="">
                    <input type="hidden" name="club_action" value="approve">
                    <button type="submit" class="btn-mod-accept" onclick="return confirm('Approve this club registration?')">Accept</button>
                </form>
                <form method="POST" class="flex-inline">
                    <input type="hidden" name="adminID" id="modalDeclineID" value="">
                    <input type="hidden" name="club_action" value="decline">
                    <button type="submit" class="btn-mod-decline" onclick="return confirm('Decline this club registration?')">Decline</button>
                </form>
                <button type="button" class="btn-mod-details" onclick="closeRegModal()">Cancel</button>
            </div>
        </div>
    </div>

    <script>
        function openRegModal(adminID, clubName, adminName, clubEmail, created) {
            document.getElementById('regClubName').textContent = clubName;
            document.getElementById('regAdminName').textContent = adminName;
            document.getElementById('regAdminID').textContent = adminID;
            document.getElementById('regClubEmail').textContent = clubEmail;
            document.getElementById('regDate').textContent = created ? new Date(created).toLocaleString('en-MY', { day:'numeric', month:'short', year:'numeric', hour:'2-digit', minute:'2-digit' }) : '';
            document.getElementById('modalApproveID').value = adminID;
            document.getElementById('modalDeclineID').value = adminID;
            document.getElementById('regModal').classList.add('active');
        }
        function closeRegModal(e) {
            if (!e || e.target === document.getElementById('regModal')) {
                document.getElementById('regModal').classList.remove('active');
            }
        }
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') document.getElementById('regModal').classList.remove('active');
        });
    </script>
</body>
</html>
