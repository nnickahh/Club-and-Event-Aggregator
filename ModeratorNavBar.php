<?php
    $modNotifCount = 0;
    $modNotifications = [];
    if (isset($_SESSION['moderator_id']) || isset($_SESSION['role']) && $_SESSION['role'] === 'moderator') {
        require_once 'db_connect.php';
        $countResult = $conn->query("SELECT COUNT(*) AS cnt FROM moderator_notifications WHERE is_read = 0");
        $modNotifCount = $countResult ? (int)$countResult->fetch_assoc()['cnt'] : 0;

        $nStmt = $conn->prepare("SELECT id, message, eventID, clubID, is_read, created_at FROM moderator_notifications ORDER BY created_at DESC LIMIT 10");
        $nStmt->execute();
        $nResult = $nStmt->get_result();
        $modNotifications = $nResult->fetch_all(MYSQLI_ASSOC);
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
        <a href="ModeratorCalendar.php" class="<?php echo $currentPage === 'calendar' ? 'active' : ''; ?>">Calendar</a>
        <a href="ModeratorEventSummary.php" class="<?php echo $currentPage === 'summary' ? 'active' : ''; ?>">Summary</a>
        <div class="profile-dropdown">
            <span class="profile-name">
                🔔<?php if ($modNotifCount > 0): ?><sup class="notif-sup"><?php echo $modNotifCount; ?></sup><?php endif; ?>
                👤 <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'Moderator'); ?>
            </span>
            <div class="dropdown-content notif-dropdown">
                <?php if (!empty($modNotifications)): ?>
                    <?php foreach ($modNotifications as $n): ?>
                        <?php
                            if (!empty($n['eventID'])) { $notifTarget = 'EventDetailsModerator.php?id=' . (int)$n['eventID']; }
                            elseif (!empty($n['clubID']) || stripos($n['message'], 'New club registration') !== false) { $notifTarget = 'ModeratorClubs.php?tab=pending'; }
                            else { $notifTarget = 'ModeratorDashboard.php'; }
                            $notifHref = 'mark_notification_read.php?type=moderator&id=' . (int)$n['id'] . '&redirect=' . urlencode($notifTarget);
                        ?>
                        <div class="notif-link <?php echo empty($n['is_read']) ? 'notif-link-unread' : ''; ?>" onclick="window.location.href='<?php echo htmlspecialchars($notifHref); ?>'">
                            <?php if (empty($n['is_read'])): ?><span class="notif-dot notif-dot-red"></span><?php endif; ?>
                            <span class="notif-message-text"><?php echo htmlspecialchars($n['message']); ?></span>
                            <br><small class="notif-time"><?php echo date('d M h:i A', strtotime($n['created_at'])); ?></small>
                            <span class="notif-dismiss" onclick="event.stopPropagation(); dismissNotification(<?php echo (int)$n['id']; ?>, 'moderator');">&times;</span>
                        </div>
                    <?php endforeach; ?>
                    <a href="clear_notifications.php" class="clear-notif" onclick="return confirm('Remove all notifications from the list?');">Clear All</a>
                <?php else: ?>
                    <a class="notif-empty">No notifications</a>
                <?php endif; ?>
                <hr class="notif-sep">
                <a href="ModeratorProfile.php">Profile</a>
                <a href="LogOut.php">Sign Out</a>
            </div>
        </div>
    </div>
<script>
function dismissNotification(id, type) {
    fetch('dismiss_notification.php?type=' + type + '&id=' + id)
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) location.reload();
        });
}
</script>
</nav>
