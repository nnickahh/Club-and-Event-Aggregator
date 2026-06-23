<?php
    $modNotifCount = 0;
    $modNotifications = [];
    if (isset($_SESSION['moderator_id']) || isset($_SESSION['role']) && $_SESSION['role'] === 'moderator') {
        require_once 'db_connect.php';
        $nStmt = $conn->prepare("SELECT id, message, eventID, clubID, created_at FROM moderator_notifications WHERE is_read = 0 ORDER BY created_at DESC LIMIT 10");
        $nStmt->execute();
        $nResult = $nStmt->get_result();
        $modNotifications = $nResult->fetch_all(MYSQLI_ASSOC);
        $modNotifCount = count($modNotifications);
        $nStmt->close();
    }
?>
<nav class="navbar">
    <a href="ModeratorDashboard.php" class="logo">
        <img src="Image/inti-logo.png" alt="INTI Logo">
    </a>
    <div class="nav-links">
        <a href="ModeratorDashboard.php" class="<?php echo $currentPage === 'home' ? 'active' : ''; ?>">Home</a>
        <a href="ModeratorEvents.php" class="<?php echo $currentPage === 'events' ? 'active' : ''; ?>">
            Events
            <?php if (isset($pendingEvents) && $pendingEvents > 0): ?>
                <span class="nav-badge"><?php echo $pendingEvents; ?></span>
            <?php endif; ?>
        </a>
        <a href="ModeratorClubs.php" class="<?php echo $currentPage === 'clubs' ? 'active' : ''; ?>">
            Clubs
            <?php if (isset($pendingClubs) && $pendingClubs > 0): ?>
                <span class="nav-badge"><?php echo $pendingClubs; ?></span>
            <?php endif; ?>
        </a>
        <div class="profile-dropdown">
            <span class="profile-name">
                🔔<?php if ($modNotifCount > 0): ?><sup class="notif-sup"><?php echo $modNotifCount; ?></sup><?php endif; ?>
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Moderator'); ?>
            </span>
            <div class="dropdown-content notif-dropdown">
                <?php if (!empty($modNotifications)): ?>
                    <?php foreach ($modNotifications as $n): ?>
                        <a href="<?php
                            if (!empty($n['eventID'])) { echo 'EventDetailsModerator.php?id=' . (int)$n['eventID']; }
                            elseif (!empty($n['clubID'])) { echo 'ModeratorClubs.php'; }
                            else { echo 'ModeratorDashboard.php'; }
                        ?>" class="notif-link">
                            <?php echo htmlspecialchars($n['message']); ?>
                            <br><small class="notif-time"><?php echo date('d M h:i A', strtotime($n['created_at'])); ?></small>
                        </a>
                    <?php endforeach; ?>
                    <a href="clear_notifications.php" class="clear-notif">Clear All</a>
                <?php else: ?>
                    <a class="notif-empty">No new notifications</a>
                <?php endif; ?>
                <hr class="notif-sep">
                <a href="ProfileSettings.php">Profile</a>
                <a href="LogOut.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>