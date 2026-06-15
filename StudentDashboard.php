<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    $currentDate = date('Y-m-d');

    // Fetch ongoing events (today)
    $ongoingStmt = $conn->prepare("SELECT e.*, a.clubName FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.eventDate = ? AND e.status = 'approved' ORDER BY e.eventTime ASC LIMIT 4");
    $ongoingStmt->bind_param("s", $currentDate);
    $ongoingStmt->execute();
    $ongoingResult = $ongoingStmt->get_result();
    $ongoingStmt->close();

    // Fetch upcoming events (future dates)
    $upcomingStmt = $conn->prepare("SELECT e.*, a.clubName FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.eventDate > ? AND e.status = 'approved' ORDER BY e.eventDate ASC LIMIT 4");
    $upcomingStmt->bind_param("s", $currentDate);
    $upcomingStmt->execute();
    $upcomingResult = $upcomingStmt->get_result();
    $upcomingStmt->close();

    $studentID = $_SESSION['student_id'];
    $rsvpCount = $conn->query("SELECT COUNT(*) AS c FROM registrations WHERE studentID = '$studentID'")->fetch_assoc()['c'] ?? 0;
    $clubCount = $conn->query("SELECT COUNT(DISTINCT adminID) AS c FROM club_members WHERE studentID = '$studentID'")->fetch_assoc()['c'] ?? 0;

    $colors = ['', 'green', 'blue', 'amber', 'purple'];

    function clubColor($clubName, $colors) {
        return $colors[abs(crc32($clubName ?? '')) % count($colors)];
    }

    function renderEventCard($row, $colors) {
        $color = clubColor($row['clubName'] ?? '', $colors);
        $eventDate = strtotime($row['eventDate']);
        $eventTime = strtotime($row['eventTime']);
        $eventEndTime = !empty($row['eventEndTime']) ? strtotime($row['eventEndTime']) : null;
        ob_start();
        ?>
        <article class="event-card"
                 data-title="<?php echo strtolower(htmlspecialchars($row['eventTitle'])); ?>"
                 data-club="<?php echo strtolower(htmlspecialchars($row['clubName'])); ?>"
                 data-venue="<?php echo strtolower(htmlspecialchars($row['venue'])); ?>"
                 data-date="<?php echo $row['eventDate']; ?>">
            <div class="card-stripe" data-color="<?php echo $color; ?>"></div>
            <?php if (!empty($row['eventImage'])): ?>
                <img src="<?php echo htmlspecialchars($row['eventImage']); ?>" alt="Event image" style="width:100%;height:160px;object-fit:cover;display:block;">
            <?php endif; ?>
            <div class="card-body">
                <span class="tag" data-color="<?php echo $color; ?>"><?php echo htmlspecialchars($row['clubName']); ?></span>
                <h3><?php echo htmlspecialchars($row['eventTitle']); ?></h3>
                <div class="event-meta">
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                        <?php echo date('d M Y, l', $eventDate); ?>
                    </div>
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                        <?php echo date('h:iA', $eventTime); ?><?php if ($eventEndTime): ?> — <?php echo date('h:iA', $eventEndTime); ?><?php endif; ?>
                    </div>
                    <div class="meta-row">
                        <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                        <?php echo htmlspecialchars($row['venue']); ?>
                    </div>
                </div>
                <div class="card-divider"></div>
                <a href="DetailedEvent.php?id=<?php echo (int)$row['eventID']; ?>" class="btn-primary">
                    Details →
                </a>
            </div>
        </article>
        <?php
        return ob_get_clean();
    }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Home</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'StudentNavbar.php'; ?>

    <div class="dash-hero">
        <div class="dash-hero-inner">
            <div>
                <p class="dash-greeting">Welcome back, <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?> 👋</p>
                <h1 class="dash-title">Discover <br><em>campus</em> events</h1>
                <p class="dash-sub">Browse upcoming activities from your clubs and faculties. <br>RSVP to reserve your spot.</p>
            </div>
            <div class="dash-stats">
                <a href="MyEvent.php?tab=events" class="stat-pill" style="text-decoration:none;color:inherit;">
                    <div class="num"><?php echo $rsvpCount; ?></div>
                    <div class="lbl">My Events</div>
                </a>
                <a href="MyEvent.php?tab=clubs" class="stat-pill" style="text-decoration:none;color:inherit;">
                    <div class="num"><?php echo $clubCount; ?></div>
                    <div class="lbl">My Clubs</div>
                </a>
            </div>
        </div>
    </div>

    <main class="container">

        <div style="display:flex;gap:12px;flex-wrap:wrap;margin-bottom:32px;">
            <a href="StudentEvents.php" class="btn-primary btn-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;width:auto;padding:12px 24px;">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                Browse Events
            </a>
            <a href="Clubs.php" class="btn-primary btn-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;width:auto;padding:12px 24px;background:var(--blue);">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                Explore Clubs
            </a>
            <a href="Calendar.php" class="btn-primary btn-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;width:auto;padding:12px 24px;background:var(--purple);">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                Calendar
            </a>
            <a href="MyEvent.php" class="btn-primary btn-action-btn" style="text-decoration:none;display:inline-flex;align-items:center;gap:8px;width:auto;padding:12px 24px;background:var(--green);">
                <svg viewBox="0 0 24 24" style="width:16px;height:16px;stroke:white;fill:none;stroke-width:2;"><path d="M22 11.08V12a10 10 0 11-5.93-9.14"/><polyline points="22,4 12,14.01 9,11.01"/></svg>
                My Activities
            </a>
        </div>

        <?php if ($ongoingResult->num_rows > 0): ?>
            <p class="section-label" style="color:#b91c1c;">Ongoing Events</p>
            <section class="event-grid">
                <?php while ($row = $ongoingResult->fetch_assoc()): ?>
                    <?php echo renderEventCard($row, $colors); ?>
                <?php endwhile; ?>
            </section>
        <?php endif; ?>

        <p class="section-label" style="margin-top:<?php echo $ongoingResult->num_rows > 0 ? '32px' : '0'; ?>">Upcoming Events</p>

        <section class="event-grid">
            <?php
            $hasUpcoming = false;
            while ($row = $upcomingResult->fetch_assoc()) {
                $hasUpcoming = true;
                echo renderEventCard($row, $colors);
            }
            if (!$hasUpcoming) {
                echo "
                <div class='event-empty-box'>
                    <div class='empty-icon'>
                        <svg viewBox='0 0 24 24' aria-hidden='true'><rect x='3' y='4' width='18' height='18' rx='2'/><line x1='3' y1='9' x2='21' y2='9'/><line x1='8' y1='2' x2='8' y2='6'/><line x1='16' y1='2' x2='16' y2='6'/></svg>
                    </div>
                    <p>No upcoming events available</p>
                    <p class='empty-subtext'>Check back later for new activities!</p>
                </div>";
            }
            ?>
        </section>

    </main>
</body>
</html>
