<?php
    session_start();
    require_once 'db_connect.php';
 
    // Security Check: Redirect to login if not a student (Matches Role-Based Architecture)
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
 
    // Fetch Events using your exact column names
    $query = "SELECT * FROM events ORDER BY eventDate ASC";
    $result = $conn->query($query);
 
    // Count total events for the stat pill
    $totalEvents = ($result) ? $result->num_rows : 0;
 
    // Color palette — cycles through cards so each gets a distinct accent
    $colors = ['', 'green', 'blue', 'amber', 'purple'];
    $colorIndex = 0;
?>
 
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Student Main Dashboard</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
 
    <?php include 'StudentNavbar.php'; ?>
 
    <!-- HERO BANNER -->
    <div class="dash-hero">
        <div class="dash-hero-inner">
            <div>
                <p class="dash-greeting">Welcome back, <?php echo htmlspecialchars($_SESSION['student_name'] ?? 'Student'); ?> 👋</p>
                <h1 class="dash-title">Discover <br><em>campus</em> events</h1>
                <p class="dash-sub">Browse upcoming activities from your clubs and faculties. <br>RSVP to reserve your spot.</p>
            </div>
            <div class="dash-stats">
                <div class="stat-pill accent">
                    <div class="num"><?php echo $totalEvents; ?></div>
                    <div class="lbl">Upcoming</div>
                </div>
                <div class="stat-pill">
                    <div class="num"><?php echo isset($_SESSION['rsvp_count']) ? $_SESSION['rsvp_count'] : '—'; ?></div>
                    <div class="lbl">My Events</div>
                </div>
                <div class="stat-pill">
                    <div class="num"><?php echo isset($_SESSION['club_count']) ? $_SESSION['club_count'] : '—'; ?></div>
                    <div class="lbl">Clubs</div>
                </div>
            </div>
        </div>
    </div>
 
    <main class="container">
 
        <!-- SEARCH TOOLBAR -->
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
        </div>
 
        <p class="section-label" id="resultCount"><?php echo $totalEvents; ?> event<?php echo $totalEvents !== 1 ? 's' : ''; ?> found</p>
 
        <!-- EVENT GRID -->
        <section class="event-grid" id="eventGrid">
            <?php
            if ($result && $result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $color = $colors[$colorIndex % count($colors)];
                    $colorIndex++;
                    $eventDate = strtotime($row['eventDate']);
                    $eventTime = strtotime($row['eventTime']);
            ?>
            <article class="event-card"
                     data-title="<?php echo strtolower(htmlspecialchars($row['eventTitle'])); ?>"
                     data-club="<?php echo strtolower(htmlspecialchars($row['clubName'])); ?>"
                     data-venue="<?php echo strtolower(htmlspecialchars($row['venue'])); ?>"
                     data-date="<?php echo $row['eventDate']; ?>">
 
                <div class="card-stripe" data-color="<?php echo $color; ?>"></div>
 
                <div class="card-body">
                    <span class="tag" data-color="<?php echo $color; ?>">
                        <?php echo htmlspecialchars($row['clubName']); ?>
                    </span>
 
                    <h3><?php echo htmlspecialchars($row['eventTitle']); ?></h3>
 
                    <div class="event-meta">
                        <div class="meta-row">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                            <?php echo date('d M Y, l', $eventDate); ?>
                        </div>
                        <div class="meta-row">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                            <?php echo date('h:i A', $eventTime); ?>
                        </div>
                        <div class="meta-row">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                            <?php echo htmlspecialchars($row['venue']); ?>
                        </div>
                    </div>
 
                    <div class="card-divider"></div>
 
                    <a href="DetailedEvent.php?id=<?php echo (int)$row['eventID']; ?>" class="btn-primary">
                        View Details &amp; RSVP →
                    </a>
                </div>
            </article>
            <?php
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
        // Live search filter
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
 
        // Filter chips
        document.querySelectorAll('.filter-chip').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.filter-chip').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
 
                const filter = this.dataset.filter;
                const now    = new Date();
                const weekMs = 7 * 24 * 60 * 60 * 1000;
                let visible  = 0;
 
                cards.forEach(card => {
                    let show = true;
                    if (filter === 'week') {
                        const d = new Date(card.dataset.date);
                        show = d >= now && d <= new Date(now.getTime() + weekMs);
                    }
                    card.style.display = show ? '' : 'none';
                    if (show) visible++;
                });
                updateCount(visible);
                searchBar.value = '';
            });
        });
    </script>
</body>
</html>