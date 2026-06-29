<?php
    $notifCount = 0;
    $notifications = [];
    if (isset($_SESSION['student_id'])) {
        require_once 'db_connect.php';
        $studentID = $_SESSION['student_id'];
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $reminderStmt = $conn->prepare("
            SELECT e.eventID, e.eventTitle, e.eventDate, e.eventTime, e.venue
            FROM registrations r
            JOIN events e ON r.eventID = e.eventID
            LEFT JOIN event_reminders_sent ers
              ON ers.studentID = r.studentID
             AND ers.eventID = e.eventID
             AND ers.reminder_date = ?
            WHERE r.studentID = ?
              AND e.status = 'approved'
              AND e.eventDate = ?
              AND ers.id IS NULL
        ");
        $reminderStmt->bind_param("sss", $today, $studentID, $tomorrow);
        $reminderStmt->execute();
        $reminderEvents = $reminderStmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $reminderStmt->close();

        if (!empty($reminderEvents)) {
            $sentStmt = $conn->prepare("INSERT IGNORE INTO event_reminders_sent (studentID, eventID, reminder_date) VALUES (?, ?, ?)");
            $notifStmt = $conn->prepare("INSERT INTO student_notifications (studentID, message, eventID) VALUES (?, ?, ?)");
            foreach ($reminderEvents as $eventReminder) {
                $eventID = (int)$eventReminder['eventID'];
                $sentStmt->bind_param("sis", $studentID, $eventID, $today);
                $sentStmt->execute();

                if ($sentStmt->affected_rows > 0) {
                    $eventTime = !empty($eventReminder['eventTime']) ? date('h:i A', strtotime($eventReminder['eventTime'])) : 'the scheduled time';
                    $eventVenue = !empty($eventReminder['venue']) ? ' at ' . $eventReminder['venue'] : '';
                    $message = 'Reminder: ' . $eventReminder['eventTitle'] . ' is tomorrow at ' . $eventTime . $eventVenue . '.';
                    $notifStmt->bind_param("ssi", $studentID, $message, $eventID);
                    $notifStmt->execute();
                }
            }
            $sentStmt->close();
            $notifStmt->close();
        }

        $countStmt = $conn->prepare("SELECT COUNT(*) AS cnt FROM student_notifications WHERE studentID = ? AND is_read = 0");
        $countStmt->bind_param("s", $studentID);
        $countStmt->execute();
        $countResult = $countStmt->get_result();
        $notifCount = $countResult ? (int)$countResult->fetch_assoc()['cnt'] : 0;
        $countStmt->close();

        $nStmt = $conn->prepare("SELECT id, message, eventID, clubID, is_read, created_at FROM student_notifications WHERE studentID = ? ORDER BY created_at DESC LIMIT 10");
        $nStmt->bind_param("s", $studentID);
        $nStmt->execute();
        $nResult = $nStmt->get_result();
        $notifications = $nResult->fetch_all(MYSQLI_ASSOC);
        $nStmt->close();
    }
?>
<nav class="navbar">
    <a href="
        <?php 
            if ($_SESSION['role'] === 'student') echo 'StudentDashboard.php';
            elseif ($_SESSION['role'] === 'admin') echo 'AdminDashboard.php';
            else echo 'ModeratorDashboard.php';
        ?>" class="logo">
        <img src="Image/inti-logo.png" alt="INTI Logo">
    </a>
    
    <div class="nav-links">
        <?php if ($_SESSION['role'] === 'student'): ?>
            <a href="StudentDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'StudentDashboard.php') ? 'active' : ''; ?>">HOME</a>
            <a href="StudentEvents.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'StudentEvents.php') ? 'active' : ''; ?>">EVENTS</a>
            <a href="Clubs.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'Clubs.php') ? 'active' : ''; ?>">CLUBS</a>
            <a href="Calendar.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'Calendar.php') ? 'active' : ''; ?>">CALENDAR</a>
            <a href="MyEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'MyEvent.php') ? 'active' : ''; ?>">MY ACTIVITIES</a>
        <?php elseif ($_SESSION['role'] === 'admin'): ?>
            <a href="AdminDashboard.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'AdminDashboard.php') ? 'active' : ''; ?>">Home</a>
            <a href="CreateEvent.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'CreateEvent.php') ? 'active' : ''; ?>">New Event</a>
            <a href="ClubProfile.php" class="<?php echo (basename($_SERVER['PHP_SELF']) == 'ClubProfile.php') ? 'active' : ''; ?>">Club Profile</a>
        <?php endif; ?>

        <div class="profile-dropdown">
            <span class="profile-name">
                🔔<?php if ($notifCount > 0): ?><sup class="notif-sup"><?php echo $notifCount; ?></sup><?php endif; ?>
                👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </span>
            <div class="dropdown-content notif-dropdown">
                <?php if (!empty($notifications)): ?>
                    <?php foreach ($notifications as $n): ?>
                        <?php
                            $notifTarget = '';
                            if (!empty($n['eventID'])) { $notifTarget = 'DetailedEvent.php?id=' . (int)$n['eventID']; }
                            elseif (!empty($n['clubID'])) {
                                $notifTarget = 'ClubsDetails.php?id=' . (int)$n['clubID'];
                                if (stripos($n['message'], 'You have been assigned as') !== false) {
                                    $notifTarget .= '#members-tab';
                                }
                            }
                            else { $notifTarget = 'Clubs.php'; }
                            $notifHref = 'mark_notification_read.php?type=student&id=' . (int)$n['id'] . '&redirect=' . urlencode($notifTarget);
                        ?>
                        <a href="<?php echo htmlspecialchars($notifHref); ?>" class="notif-link <?php echo empty($n['is_read']) ? 'notif-link-unread' : ''; ?>">
                            <?php if (empty($n['is_read'])): ?><span class="notif-dot notif-dot-blue"></span><?php endif; ?>
                            <span class="notif-message-text"><?php echo htmlspecialchars($n['message']); ?></span>
                            <br><small class="notif-time"><?php echo date('d M h:i A', strtotime($n['created_at'])); ?></small>
                        </a>
                    <?php endforeach; ?>
                    <a href="clear_notifications.php" class="clear-notif" onclick="return confirm('Remove all notifications from the list?');">Clear All</a>
                <?php else: ?>
                    <a class="notif-empty">No notifications</a>
                <?php endif; ?>
                <hr class="notif-sep">
                <a href="ProfileSettings.php">Profile</a>
                <a href="Logout.php">Sign Out</a>
            </div>
        </div>
    </div>
</nav>
