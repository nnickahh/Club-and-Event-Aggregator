<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $currentPage = 'events';

    if (!isset($_GET['id'])) {
        header("Location: ModeratorEvents.php");
        exit();
    }
    $eventID = (int)$_GET['id'];
    $isEditing = isset($_GET['edit']) && $_GET['edit'] === '1';
    $message = '';
    $msgType = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
        $action = $_POST['action'];

        try {
            if ($action === 'approve') {
                $stmt = $conn->prepare("UPDATE events SET status = 'approved' WHERE eventID = ?");
                $stmt->bind_param("i", $eventID);
                $stmt->execute();
                $message = 'Event has been approved successfully.';
                $msgType = 'success';
            } elseif ($action === 'decline') {
                $stmt = $conn->prepare("UPDATE events SET status = 'declined' WHERE eventID = ?");
                $stmt->bind_param("i", $eventID);
                $stmt->execute();
                $message = 'Event has been declined.';
                $msgType = 'success';
            } elseif ($action === 'update') {
                $title = $_POST['eventTitle'] ?? '';
                $date = $_POST['eventDate'] ?? '';
                $time = $_POST['eventTime'] ?? '';
                $endTime = !empty(trim($_POST['eventEndTime'] ?? '')) ? trim($_POST['eventEndTime']) : null;
                $venue = $_POST['venue'] ?? '';
                $capacity = $_POST['capacity'] ?? 0;
                $description = $_POST['description'] ?? '';
                $stmt = $conn->prepare("UPDATE events SET eventTitle=?, eventDate=?, eventTime=?, eventEndTime=?, venue=?, capacity=?, description=? WHERE eventID=?");
                $stmt->bind_param("sssssisi", $title, $date, $time, $endTime, $venue, $capacity, $description, $eventID);
                $stmt->execute();
                $message = 'Event has been updated successfully.';
                $msgType = 'success';
                $isEditing = false;
            } elseif ($action === 'delete') {
                $stmt = $conn->prepare("DELETE FROM events WHERE eventID = ?");
                $stmt->bind_param("i", $eventID);
                $stmt->execute();
                header("Location: ModeratorEvents.php");
                exit();
            }
        } catch (mysqli_sql_exception $e) {
            $message = 'Database error: ' . $e->getMessage();
            $msgType = 'error';
        }
    }

    $event = null;
    try {
        $stmt = $conn->prepare("SELECT e.*, a.clubName AS club_name, a.name AS admin_name, a.clubEmail AS club_email FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.eventID = ?");
        $stmt->bind_param("i", $eventID);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
        } else {
            die("Event not found.");
        }
    } catch (mysqli_sql_exception $e) {
        die("Database error: " . $e->getMessage());
    }

    $pendingClubs = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'pending'");
    if ($r) { $pendingClubs = $r->fetch_assoc()['c']; }

    $pendingEvents = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
    if ($r) { $pendingEvents = $r->fetch_assoc()['c']; }

    $today = date('Y-m-d');
    $eventStatus = $event['status'] ?? 'pending';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($event['eventTitle']); ?> - Details</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>

    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">
        <a href="ModeratorEvents.php" class="back-link">&larr; Back to Events</a>

        <?php if ($message): ?>
            <div style="padding:14px 18px;border-radius:var(--radius-md);margin-bottom:20px;font-weight:500;font-size:14px;background:<?php echo $msgType === 'success' ? 'var(--green-bg)' : 'var(--red-light)'; ?>;color:<?php echo $msgType === 'success' ? 'var(--green)' : 'var(--red)'; ?>;border:1px solid <?php echo $msgType === 'success' ? 'rgba(45,125,70,0.2)' : 'rgba(237,28,36,0.2)'; ?>;">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <article class="event-detail-card">
            <?php if (!empty($event['eventImage'])): ?>
                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;max-height:320px;object-fit:cover;border-radius:8px;margin-bottom:20px;">
            <?php endif; ?>
            <span class="tag tag-club"><?php echo htmlspecialchars($event['club_name'] ?? 'Unknown Club'); ?></span>

            <?php if ($eventStatus === 'pending'): ?>
                <span class="mod-pending-badge" style="display:inline-flex;margin-top:10px;"><span class="mod-badge-dot"></span>Pending review</span>
            <?php elseif ($eventStatus === 'declined'): ?>
                <span class="mod-status-tag declined" style="display:inline-flex;margin-top:10px;">Declined</span>
            <?php else: ?>
                <span class="mod-status-tag approved" style="display:inline-flex;margin-top:10px;">
                    <?php
                        if ($event['eventDate'] === $today) echo 'Ongoing';
                        elseif ($event['eventDate'] > $today) echo 'Upcoming';
                        else echo 'Completed';
                    ?>
                </span>
            <?php endif; ?>

            <?php if ($isEditing): ?>
                <form method="POST">
                    <input type="hidden" name="action" value="update">

                    <div class="mod-form-group">
                        <label>Event Title</label>
                        <input type="text" name="eventTitle" value="<?php echo htmlspecialchars($event['eventTitle']); ?>" required>
                    </div>

                    <div class="mod-form-group">
                        <label>Date</label>
                        <input type="date" name="eventDate" value="<?php echo htmlspecialchars($event['eventDate']); ?>" required>
                    </div>

                    <div class="mod-form-group">
                        <label>Start Time</label>
                        <input type="time" name="eventTime" value="<?php echo htmlspecialchars($event['eventTime']); ?>" required>
                    </div>
                    <div class="mod-form-group">
                        <label>End Time</label>
                        <input type="time" name="eventEndTime" value="<?php echo htmlspecialchars($event['eventEndTime'] ?? ''); ?>">
                    </div>

                    <div class="mod-form-group">
                        <label>Venue</label>
                        <input type="text" name="venue" value="<?php echo htmlspecialchars($event['venue']); ?>" required>
                    </div>

                    <div class="mod-form-group">
                        <label>Capacity</label>
                        <input type="number" name="capacity" value="<?php echo htmlspecialchars($event['capacity']); ?>" required>
                    </div>

                    <div class="mod-form-group">
                        <label>Description</label>
                        <textarea name="description" required><?php echo htmlspecialchars($event['description']); ?></textarea>
                    </div>

                    <div class="mod-detail-actions">
                        <button type="submit" class="btn-save">Save Changes</button>
                        <a href="EventDetailsModerator.php?id=<?php echo $eventID; ?>" class="btn-decline" style="text-align:center;text-decoration:none;">Cancel</a>
                    </div>
                </form>
            <?php else: ?>
                <h1 class="event-detail-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h1>

                <div class="event-meta event-meta-lg">
                    <p><strong>Date:</strong> <?php echo date('d F Y', strtotime($event['eventDate'])); ?></p>
                    <p><strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></p>
                    <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
                    <p><strong>Capacity:</strong> <?php echo htmlspecialchars($event['capacity']); ?> seats</p>
                    <p><strong>Club:</strong> <?php echo htmlspecialchars($event['club_name'] ?? 'Unknown'); ?></p>
                    <p><strong>Club Admin:</strong> <?php echo htmlspecialchars($event['admin_name'] ?? 'Unknown'); ?></p>
                </div>

                <hr class="divider-light">

                <h3>About This Event</h3>
                <p class="event-description">
                    <?php echo nl2br(htmlspecialchars($event['description'])); ?>
                </p>

                <hr class="divider-light">

                <div class="mod-detail-actions">
                    <?php if ($eventStatus === 'pending'): ?>
                        <form method="POST">
                            <input type="hidden" name="action" value="approve">
                            <button type="submit" class="btn-approve">Approve Event</button>
                        </form>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to decline this event?');">
                            <input type="hidden" name="action" value="decline">
                            <button type="submit" class="btn-decline">Decline Event</button>
                        </form>
                    <?php elseif ($eventStatus === 'approved'): ?>
                        <a href="EventDetailsModerator.php?id=<?php echo $eventID; ?>&edit=1" class="btn-edit">Edit Event</a>
                        <form method="POST" onsubmit="return confirm('Are you sure you want to delete this event? This cannot be undone.');">
                            <input type="hidden" name="action" value="delete">
                            <button type="submit" class="btn-delete">Delete Event</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </article>
    </main>
</body>
</html>
