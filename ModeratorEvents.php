<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $currentPage = 'events';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
    $today = date('Y-m-d');
    $events = [];
    $counts = ['pending' => 0, 'ongoing' => 0, 'upcoming' => 0, 'completed' => 0];

    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
        $counts['pending'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'approved' AND eventDate = '$today'");
        $counts['ongoing'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'approved' AND eventDate > '$today'");
        $counts['upcoming'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'approved' AND eventDate < '$today'");
        $counts['completed'] = $r ? $r->fetch_assoc()['c'] : 0;

        switch ($tab) {
            case 'pending':
                $q = "SELECT e.*, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.status = 'pending' ORDER BY e.created_at DESC";
                break;
            case 'ongoing':
                $q = "SELECT e.*, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.status = 'approved' AND e.eventDate = '$today' ORDER BY e.eventTime ASC";
                break;
            case 'upcoming':
                $q = "SELECT e.*, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.status = 'approved' AND e.eventDate > '$today' ORDER BY e.eventDate ASC";
                break;
            case 'completed':
                $q = "SELECT e.*, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.status = 'approved' AND e.eventDate < '$today' ORDER BY e.eventDate DESC";
                break;
            default:
                $q = "SELECT e.*, a.clubName AS club_name FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.status = 'pending' ORDER BY e.created_at DESC";
        }
        $result = $conn->query($q);
        if ($result) { $events = $result->fetch_all(MYSQLI_ASSOC); }
    } catch (mysqli_sql_exception $e) {
        error_log('ModeratorEvents DB error: ' . $e->getMessage());
    }

    $pendingClubs = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'pending'");
    if ($r) { $pendingClubs = $r->fetch_assoc()['c']; }

    $pendingEvents = $counts['pending'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator - Events</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">

        <div class="mod-page-header">
            <div>
                <h2 class="mod-title">Events</h2>
                <p class="mod-sub">Manage all events across campus.</p>
            </div>
        </div>

        <div class="mod-tab-nav">
            <a href="ModeratorEvents.php?tab=pending" class="mod-tab-link <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                Pending <?php if ($counts['pending'] > 0): ?><span class="tab-count"><?php echo $counts['pending']; ?></span><?php endif; ?>
            </a>
            <a href="ModeratorEvents.php?tab=ongoing" class="mod-tab-link <?php echo $tab === 'ongoing' ? 'active' : ''; ?>">
                Ongoing <?php if ($counts['ongoing'] > 0): ?><span class="tab-count"><?php echo $counts['ongoing']; ?></span><?php endif; ?>
            </a>
            <a href="ModeratorEvents.php?tab=upcoming" class="mod-tab-link <?php echo $tab === 'upcoming' ? 'active' : ''; ?>">
                Upcoming <?php if ($counts['upcoming'] > 0): ?><span class="tab-count"><?php echo $counts['upcoming']; ?></span><?php endif; ?>
            </a>
            <a href="ModeratorEvents.php?tab=completed" class="mod-tab-link <?php echo $tab === 'completed' ? 'active' : ''; ?>">
                Completed <?php if ($counts['completed'] > 0): ?><span class="tab-count"><?php echo $counts['completed']; ?></span><?php endif; ?>
            </a>
        </div>

        <section class="event-grid">
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event): ?>
                    <article class="event-card">
                        <div class="card-stripe" data-color="<?php echo $tab === 'completed' ? 'green' : ($tab === 'pending' ? 'amber' : ($tab === 'ongoing' ? 'blue' : 'purple')); ?>"></div>
                        <?php if (!empty($event['eventImage'])): ?>
                            <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" style="width:100%;height:160px;object-fit:cover;display:block;">
                        <?php endif; ?>
                        <div class="card-body">
                            <?php if ($tab === 'pending'): ?>
                                <span class="mod-pending-badge"><span class="mod-badge-dot"></span>Pending review</span>
                            <?php elseif ($tab === 'ongoing'): ?>
                                <span class="mod-status-tag approved">Ongoing</span>
                            <?php elseif ($tab === 'upcoming'): ?>
                                <span class="mod-status-tag approved">Upcoming</span>
                            <?php else: ?>
                                <span class="mod-status-tag approved">Completed</span>
                            <?php endif; ?>

                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>

                            <div class="event-meta">
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                                    <span><?php echo date('d M Y', strtotime($event['eventDate'])); ?> at <?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <span><?php echo htmlspecialchars($event['venue']); ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                    <span><?php echo htmlspecialchars($event['club_name'] ?? 'Unknown Club'); ?></span>
                                </div>
                            </div>

                            <div class="card-divider"></div>

                            <a href="EventDetailsModerator.php?id=<?php echo $event['eventID']; ?>" class="btn-mod-details">Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                    </div>
                    <p>No <?php echo $tab; ?> events</p>
                    <p class="empty-subtext">There are no events in this category right now.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
