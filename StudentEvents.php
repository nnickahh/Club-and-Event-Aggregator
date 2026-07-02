<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    $currentDate = date('Y-m-d');

    // Fetch clubs for filter dropdown (only those with approved events)
    $clubsResult = $conn->query("SELECT DISTINCT a.clubName FROM events e LEFT JOIN admins a ON e.adminID = a.adminID WHERE e.status = 'approved' AND a.clubName IS NOT NULL ORDER BY a.clubName ASC");

    $ongoingStmt = $conn->prepare("SELECT e.*, a.clubName, c.clubID FROM events e LEFT JOIN admins a ON e.adminID = a.adminID LEFT JOIN clubs c ON c.clubID = (SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1) WHERE e.status = 'approved' AND NOW() >= CONCAT(e.eventDate, ' ', COALESCE(e.eventTime, '00:00:00')) AND NOW() <= CONCAT(COALESCE(e.eventEndDate, e.eventDate), ' ', COALESCE(e.eventEndTime, '23:59:59')) ORDER BY e.eventTime ASC");
    $ongoingStmt->execute();
    $ongoingResult = $ongoingStmt->get_result();
    $ongoingStmt->close();

    $upcomingStmt = $conn->prepare("
        SELECT e.*, a.clubName, c.clubID
        FROM events e
        LEFT JOIN admins a ON e.adminID = a.adminID
        LEFT JOIN clubs c ON c.clubID = (
            SELECT c2.clubID FROM clubs c2 WHERE c2.adminID = a.adminID ORDER BY c2.clubID DESC LIMIT 1
        )
        WHERE e.eventDate > ?
          AND e.status = 'approved'
        ORDER BY e.eventDate ASC
    ");
    $upcomingStmt->bind_param("s", $currentDate);
    $upcomingStmt->execute();
    $upcomingResult = $upcomingStmt->get_result();
    $upcomingStmt->close();

    $totalEvents = $ongoingResult->num_rows + $upcomingResult->num_rows;

    // Get events the student is registered for
    $studentID = $_SESSION['student_id'];
    $regStmt = $conn->prepare("SELECT eventID FROM registrations WHERE studentID = ?");
    $regStmt->bind_param("s", $studentID);
    $regStmt->execute();
    $regResult = $regStmt->get_result();
    $registeredIDs = [];
    while ($r = $regResult->fetch_assoc()) {
        $registeredIDs[] = $r['eventID'];
    }
    $regStmt->close();

    $colors = ['', 'green', 'blue', 'amber', 'purple'];

    function clubColor($clubName, $colors) {
        return $colors[abs(crc32($clubName ?? '')) % count($colors)];
    }

    function renderEventCard($row, $colors, $isRegistered) {
        $color = clubColor($row['clubName'] ?? '', $colors);
        $eventTime = strtotime($row['eventTime']);
        $eventEndTime = !empty($row['eventEndTime']) ? strtotime($row['eventEndTime']) : null;
        $eventEndDate = $row['eventEndDate'] ?? null;
        ob_start();
        ?>
        <article class="event-card event-strip-card student-event-strip"
                 data-title="<?php echo strtolower(htmlspecialchars($row['eventTitle'])); ?>"
                 data-club="<?php echo strtolower(htmlspecialchars($row['clubName'])); ?>"
                 data-venue="<?php echo strtolower(htmlspecialchars($row['venue'])); ?>"
                 data-date="<?php echo $row['eventDate']; ?>"
                 data-registered="<?php echo $isRegistered ? '1' : '0'; ?>">
            <div class="date-badge-box">
                <?php if (!empty($eventEndDate) && $eventEndDate !== $row['eventDate']): ?>
                    <span class="day-num"><?php echo date('j', strtotime($row['eventDate'])) . '-' . date('j', strtotime($eventEndDate)); ?></span>
                    <span class="month-txt"><?php echo date('M', strtotime($row['eventDate'])); ?></span>
                <?php else: ?>
                    <span class="day-num"><?php echo date('d', strtotime($row['eventDate'])); ?></span>
                    <span class="month-txt"><?php echo date('M', strtotime($row['eventDate'])); ?></span>
                <?php endif; ?>
            </div>
            <div class="strip-main-info">
                <a href="ClubsDetails.php?id=<?php echo (int)($row['clubID'] ?? 0); ?>" class="student-strip-club tag" data-color="<?php echo $color; ?>">
                    <?php echo htmlspecialchars($row['clubName']); ?>
                </a>
                <h4><?php echo htmlspecialchars($row['eventTitle']); ?></h4>
                <p class="strip-meta">
                    <?php echo formatDateRange($row['eventDate'], $eventEndDate); ?> ·
                    <?php echo date('h:iA', $eventTime); ?><?php if ($eventEndTime): ?> — <?php echo date('h:iA', $eventEndTime); ?><?php endif; ?> ·
                    <?php echo htmlspecialchars($row['venue']); ?>
                </p>
            </div>
            <div class="strip-actions student-strip-actions">
                <?php if ($isRegistered): ?>
                    <span class="student-registered-pill">Registered</span>
                <?php endif; ?>
                <a href="DetailedEvent.php?id=<?php echo (int)$row['eventID']; ?>" class="action-pill-btn">Details</a>
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
    <title>Browse Events</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <h2 class="clubs-title">Explore Campus Events</h2>

        <div class="search-toolbar">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="searchBar" class="search-bar" placeholder="Search events by name, club, or venue...">
            </div>
            <button class="filter-chip active" data-filter="all">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                All events
            </button>
            <button class="filter-chip" data-filter="week">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                This week
            </button>
            <select id="clubFilter" class="club-filter-dropdown">
                <option value="">All</option>
                <?php while ($club = $clubsResult->fetch_assoc()): ?>
                    <option value="<?php echo strtolower(htmlspecialchars($club['clubName'])); ?>"><?php echo htmlspecialchars($club['clubName']); ?></option>
                <?php endwhile; ?>
            </select>
            <div class="reg-filter-group">
                <button class="filter-chip active" data-regfilter="all">All</button>
                <button class="filter-chip" data-regfilter="registered">Registered</button>
                <button class="filter-chip" data-regfilter="unregistered">Unregistered</button>
            </div>
        </div>

        <p class="section-label" id="resultCount"><?php echo $totalEvents; ?> event<?php echo $totalEvents !== 1 ? 's' : ''; ?> found</p>

        <?php if ($ongoingResult->num_rows > 0): ?>
            <p class="section-label text-danger mt-24">Ongoing Events</p>
            <section class="modern-events-list student-events-list ongoing-section">
                <?php while ($row = $ongoingResult->fetch_assoc()): ?>
                    <?php echo renderEventCard($row, $colors, in_array($row['eventID'], $registeredIDs)); ?>
                <?php endwhile; ?>
            </section>
        <?php endif; ?>

        <p class="section-label <?php echo $ongoingResult->num_rows > 0 ? 'mt-32' : 'mt-24'; ?>">Upcoming Events</p>

        <section class="modern-events-list student-events-list" id="eventGrid">
            <?php
            if ($upcomingResult->num_rows > 0) {
                while ($row = $upcomingResult->fetch_assoc()) {
                    echo renderEventCard($row, $colors, in_array($row['eventID'], $registeredIDs));
                }
            } else {
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

    <script>
        const searchBar   = document.getElementById('searchBar');
        const resultCount = document.getElementById('resultCount');
        const cards       = document.querySelectorAll('.event-card');

        function updateCount(visible) {
            resultCount.textContent = visible + ' event' + (visible !== 1 ? 's' : '') + ' found';
        }

        searchBar.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            let visible = 0;
            cards.forEach(card => {
                const matches = !q
                    || card.dataset.title.includes(q)
                    || card.dataset.club.includes(q)
                    || card.dataset.venue.includes(q);
                card.style.display = matches ? '' : 'none';
                if (matches) visible++;
            });
            updateCount(visible);
        });

        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                document.getElementById('clubFilter').value = '';
                applyFilters();
                searchBar.value = '';
            });
        });

        document.getElementById('clubFilter').addEventListener('change', function () {
            document.querySelectorAll('[data-filter]').forEach(b => b.classList.remove('active'));
            applyFilters();
        });

        document.querySelectorAll('.reg-filter-group .filter-chip').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.reg-filter-group .filter-chip').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                applyFilters();
            });
        });

        function applyFilters() {
            const activeChip = document.querySelector('[data-filter].active');
            const filter = activeChip ? activeChip.dataset.filter : 'all';
            const now    = new Date();
            const weekMs = 7 * 24 * 60 * 60 * 1000;
            const clubVal = document.getElementById('clubFilter').value;
            const regFilter = document.querySelector('.reg-filter-group .filter-chip.active');
            const regVal = regFilter ? regFilter.dataset.regfilter : 'all';
            let visible  = 0;

            cards.forEach(card => {
                let show = true;
                if (filter === 'week') {
                    const d = new Date(card.dataset.date);
                    show = d >= now && d <= new Date(now.getTime() + weekMs);
                }
                if (clubVal && card.dataset.club !== clubVal) show = false;
                if (regVal === 'registered' && card.dataset.registered !== '1') show = false;
                if (regVal === 'unregistered' && card.dataset.registered !== '0') show = false;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            updateCount(visible);
        }
    </script>
</body>
</html>
