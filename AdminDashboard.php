<!--AdminDashboard.php-->
<?php
    session_start();
    require_once 'db_connect.php';

    // Security Check: Redirect to login if user is not authorized as an admin
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }
    // Flash message for popup
    $flashMessage = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    session_write_close();

    $adminID = $_SESSION['admin_id'];
    $clubName = isset($_SESSION['club_name']) ? $_SESSION['club_name'] : "Club Admin";

    $query = "SELECT * FROM events WHERE adminID = ? ORDER BY eventDate ASC";
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $adminID); 
    $stmt->execute();
    $result = $stmt->get_result();

    $ongoingEvents = [];
    $upcomingEvents = [];
    $pendingEvents = [];
    $completedEvents = [];
    $cancelledEvents = [];
    $currentDate = date('Y-m-d');

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $status = $row['status'] ?? 'pending';
            if ($status === 'pending') {
                $pendingEvents[] = $row;
            } elseif ($status === 'approved' && $row['eventDate'] == $currentDate) {
                $ongoingEvents[] = $row;
            } elseif ($status === 'approved' && $row['eventDate'] > $currentDate) {
                $upcomingEvents[] = $row;
            } elseif (($status === 'approved' && $row['eventDate'] < $currentDate) || $status === 'ended') {
                $completedEvents[] = $row;
            } elseif ($status === 'cancelled') {
                $cancelledEvents[] = $row;
            }
        }
    }
    $stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($clubName); ?></title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'AdminNavbar.php'; ?>

    <main class="container">
        
        <header class="dashboard-header">
            <div>
                <h1>Welcome Back, <?php echo htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin User'); ?></h1>
                <p class="subtitle">Managing: <strong><?php echo htmlspecialchars($clubName); ?></strong></p>
            </div>
            <a href="CreateEvent.php" class="btn-primary" style="text-decoration: none; display: inline-block;">Create New Event</a>
        </header>

        <!-- ONGOING EVENTS SECTION -->
        <h3 class="section-title">Ongoing Events</h3>
        <section class="event-grid">
            <?php if (!empty($ongoingEvents)): ?>
                <?php foreach($ongoingEvents as $event): ?>
                    <article class="event-card">
                        <div>
                            <span class="mod-status-tag ongoing">Ongoing</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:12px;">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Capacity Limit:</strong> <?php echo htmlspecialchars($event['capacity']); ?> seats max<br>
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                                <strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?><br>
                                <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                            </div>

                            <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>

                        <div class="action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <a href="DeleteEvent.php?id=<?php echo $event['eventID']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Ongoing Events</p>
                    <p class="empty-subtext">Events happening today will appear here.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- UPCOMING EVENTS SECTION -->
        <h3 class="section-title">Upcoming Events</h3>
        <section class="event-grid">
            <?php if (!empty($upcomingEvents)): ?>
                <?php foreach($upcomingEvents as $event): ?>
                    <article class="event-card">
                        <div>
                            <span class="mod-status-tag upcoming">Upcoming</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:12px;">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Capacity Limit:</strong> <?php echo htmlspecialchars($event['capacity']); ?> seats max<br>
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                                <strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?><br>
                                <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                            </div>

                            <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>

                        <div class="action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <a href="DeleteEvent.php?id=<?php echo $event['eventID']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Upcoming Events Found</p>
                    <p class="empty-subtext">Click the 'Create New Event' button to get started.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- PENDING EVENTS SECTION -->
        <h3 class="section-title">Pending Events</h3>
        <p style="font-size:13px;color:var(--ink-3,#888);margin:-10px 0 16px 0;">Events with a pending status are not visible to students until approved by a moderator.</p>
        <section class="event-grid">
            <?php if (!empty($pendingEvents)): ?>
                <?php foreach($pendingEvents as $event): ?>
                    <article class="event-card">
                        <div>
                            <span class="mod-status-tag pending">Pending Approval</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:12px;">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                                <strong>Time:</strong> <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?><br>
                                <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                            </div>

                            <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>

                        <div class="action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                            <a href="DeleteEvent.php?id=<?php echo $event['eventID']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Pending Events</p>
                    <p class="empty-subtext">All your events have been reviewed.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- COMPLETED EVENTS SECTION -->
        <h3 class="section-title">Completed Events</h3>
        <section class="event-grid">
            <?php if (!empty($completedEvents)): ?>
                <?php foreach($completedEvents as $event): ?>
                    <article class="event-card event-card-completed"> 
                        <div>
                            <span class="tag">Completed</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:12px;opacity:0.7;">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                                <strong>Status:</strong> Archived / Completed
                            </div>
                        </div>

                        <div class="action-buttons">
                            <a href="ExportReport.php?id=<?php echo $event['eventID']; ?>" class="btn-outline btn-full-width">Generate Report</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Completed Events</p>
                    <p class="empty-subtext">Your event history will appear here once events have passed.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- CANCELLED EVENTS SECTION -->
        <h3 class="section-title">Cancelled Events</h3>
        <section class="event-grid">
            <?php if (!empty($cancelledEvents)): ?>
                <?php foreach($cancelledEvents as $event): ?>
                    <article class="event-card event-card-completed"> 
                        <div>
                            <span class="tag" style="background:#fef2f2;color:#dc2626;">Cancelled</span>
                            <?php if (!empty($event['eventImage'])): ?>
                                <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;max-height:180px;object-fit:cover;border-radius:6px;margin-bottom:12px;opacity:0.7;">
                            <?php endif; ?>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                                <strong>Status:</strong> Cancelled
                            </div>
                        </div>

                        <div class="action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="action-pill-btn">Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Cancelled Events</p>
                    <p class="empty-subtext">Events that are cancelled will appear here.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>

    <?php if ($flashMessage): ?>
    <div id="flashOverlay" style="position:fixed;inset:0;background:rgba(0,0,0,0.4);display:flex;align-items:center;justify-content:center;z-index:1000;">
        <div style="background:#fff;border-radius:12px;padding:32px 40px;max-width:420px;text-align:center;box-shadow:0 8px 32px rgba(0,0,0,0.15);">
            <?php
                $isError = stripos($flashMessage, 'deleted') !== false || stripos($flashMessage, 'cancelled') !== false;
            ?>
            <div style="font-size:48px;margin-bottom:12px;"><?php echo $isError ? '🗑️' : '🎉'; ?></div>
            <h3 style="margin:0 0 8px;font-size:18px;"><?php echo $isError ? 'Done!' : 'Event Submitted!'; ?></h3>
            <p style="margin:0 0 20px;font-size:14px;color:#666;line-height:1.5;"><?php echo htmlspecialchars($flashMessage); ?></p>
            <button onclick="document.getElementById('flashOverlay').remove()" style="background:var(--red);color:#fff;border:none;padding:10px 28px;border-radius:8px;font-size:14px;font-weight:600;cursor:pointer;">OK</button>
        </div>
    </div>
    <?php endif; ?>

</body>
</html>
