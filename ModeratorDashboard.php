<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $currentPage = 'home';

    // Fetch stats
    $totalEvents = 0;
    $pendingClubs = 0;
    $pendingEvents = 0;
    $activeClubs = 0;
    $recentActivity = [];

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
        if ($result) { $recentActivity = $result->fetch_all(MYSQLI_ASSOC); }
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
                <h2 class="mod-title">Dashboard</h2>
                <p class="mod-sub">Overview of campus activity and pending items.</p>
            </div>
        </div>

        <div class="mod-stats-grid">
            <div class="mod-stat-card">
                <a href="ModeratorEvents.php" class="stat-link">
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
                <a href="ModeratorEvents.php?tab=pending" class="stat-link">
                    <div class="mod-stat-icon amber">
                        <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12,6 12,12 16,14"/></svg>
                    </div>
                    <div>
                        <div class="mod-stat-num"><?php echo $pendingEvents; ?></div>
                        <div class="mod-stat-lbl">Pending Events</div>
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
                        <div class="mod-stat-lbl">Pending Clubs</div>
                    </div>
                </a>
            </div>
            <div class="mod-stat-card">
                <div class="mod-stat-icon green">
                    <svg viewBox="0 0 24 24"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
                </div>
                <div>
                    <div class="mod-stat-num"><?php echo $activeClubs; ?></div>
                    <div class="mod-stat-lbl">Active Clubs</div>
                </div>
            </div>
        </div>

        <div class="section-label">Recent Activity</div>
        <div class="mod-activity-list">
            <?php if (!empty($recentActivity)): ?>
                <?php foreach ($recentActivity as $act): ?>
                    <div class="mod-activity-item">
                        <span class="mod-activity-dot"></span>
                        <span>
                            <span class="act-title"><?php echo htmlspecialchars($act['eventTitle']); ?></span>
                            <span class="act-club">by <a href="ClubDetailsModerator.php?id=<?php echo (int)$act['adminID']; ?>" class="text-muted-link-inherit"><?php echo htmlspecialchars($act['club_name'] ?? 'Unknown'); ?></a></span>
                            <span class="act-status">-
                                <?php
                                    $s = $act['status'] ?? 'approved';
                                    if ($s === 'pending') echo '<span style="color:var(--amber);font-weight:600;">Pending</span>';
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
        </div>

    </main>
</body>
</html>
