<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    $currentPage = 'events';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
    $today = date('Y-m-d');
    $flashMessage = $_SESSION['flash_message'] ?? null;
    unset($_SESSION['flash_message']);
    session_write_close();
    $events = [];
    $counts = ['pending' => 0, 'ongoing' => 0, 'upcoming' => 0, 'completed' => 0, 'cancelled' => 0];

    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
        $counts['pending'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'approved' AND '$today' BETWEEN eventDate AND COALESCE(eventEndDate, eventDate)");
        $counts['ongoing'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'approved' AND eventDate > '$today'");
        $counts['upcoming'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE (status = 'approved' AND '$today' > COALESCE(eventEndDate, eventDate)) OR status = 'ended'");
        $counts['completed'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'cancelled'");
        $counts['cancelled'] = $r ? $r->fetch_assoc()['c'] : 0;

        $join = " FROM events e LEFT JOIN admins a ON e.adminID = a.adminID";
        switch ($tab) {
            case 'pending':
                $q = "SELECT e.*, a.clubName AS club_name" . $join . " WHERE e.status = 'pending' ORDER BY e.created_at DESC";
                break;
            case 'ongoing':
                $q = "SELECT e.*, a.clubName AS club_name" . $join . " WHERE e.status = 'approved' AND '$today' BETWEEN e.eventDate AND COALESCE(e.eventEndDate, e.eventDate) ORDER BY e.eventTime ASC";
                break;
            case 'upcoming':
                $q = "SELECT e.*, a.clubName AS club_name" . $join . " WHERE e.status = 'approved' AND e.eventDate > '$today' ORDER BY e.eventDate ASC";
                break;
            case 'completed':
                $q = "SELECT e.*, a.clubName AS club_name" . $join . " WHERE (e.status = 'approved' AND '$today' > COALESCE(e.eventEndDate, e.eventDate)) OR e.status = 'ended' ORDER BY e.eventDate DESC";
                break;
            case 'cancelled':
                $q = "SELECT e.*, a.clubName AS club_name" . $join . " WHERE e.status = 'cancelled' ORDER BY e.eventDate DESC";
                break;
            default:
                $q = "SELECT e.*, a.clubName AS club_name" . $join . " WHERE e.status = 'pending' ORDER BY e.created_at DESC";
        }
        $result = $conn->query($q);
        if ($result) { $events = $result->fetch_all(MYSQLI_ASSOC); }
    } catch (mysqli_sql_exception $e) {
        error_log('ModeratorEvents DB error: ' . $e->getMessage());
    }

    $eventClubNames = [];
    foreach ($events as $event) {
        $clubName = trim($event['club_name'] ?? '');
        if ($clubName !== '') {
            $eventClubNames[strtolower($clubName)] = $clubName;
        }
    }
    natcasesort($eventClubNames);

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

        <?php if ($flashMessage): ?>
        <div class="flash-overlay" id="flashPopup">
            <div class="flash-box">
                <div class="flash-icon">🎉</div>
                <div class="flash-title">Done!</div>
                <div class="flash-text"><?php echo htmlspecialchars($flashMessage); ?></div>
                <button class="flash-btn" onclick="document.getElementById('flashPopup').style.display='none'">OK</button>
            </div>
        </div>
        <?php endif; ?>

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
            <a href="ModeratorEvents.php?tab=cancelled" class="mod-tab-link <?php echo $tab === 'cancelled' ? 'active' : ''; ?>">
                Cancelled <?php if ($counts['cancelled'] > 0): ?><span class="tab-count"><?php echo $counts['cancelled']; ?></span><?php endif; ?>
            </a>
        </div>

        <div class="search-toolbar">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="modEventSearch" class="search-bar" placeholder="Search events by name, club, or venue...">
            </div>
            <button type="button" class="filter-chip active" data-eventfilter="all">
                <svg viewBox="0 0 24 24" aria-hidden="true"><line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/></svg>
                All events
            </button>
            <button type="button" class="filter-chip" data-eventfilter="week">
                <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="3" y1="9" x2="21" y2="9"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                This week
            </button>
            <select id="modEventClubFilter" class="club-filter-dropdown">
                <option value="">All clubs</option>
                <?php foreach ($eventClubNames as $clubValue => $clubLabel): ?>
                    <option value="<?php echo htmlspecialchars($clubValue, ENT_QUOTES); ?>"><?php echo htmlspecialchars($clubLabel); ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <p class="section-label" id="modEventResultCount">
            <?php echo count($events); ?> event<?php echo count($events) !== 1 ? 's' : ''; ?> found
        </p>

        <section class="event-grid">
            <?php if (!empty($events)): ?>
                <?php foreach ($events as $event): ?>
                    <article class="event-card mod-events-card"
                             data-title="<?php echo htmlspecialchars(strtolower($event['eventTitle'] ?? ''), ENT_QUOTES); ?>"
                             data-club="<?php echo htmlspecialchars(strtolower($event['club_name'] ?? ''), ENT_QUOTES); ?>"
                             data-venue="<?php echo htmlspecialchars(strtolower($event['venue'] ?? ''), ENT_QUOTES); ?>"
                             data-date="<?php echo htmlspecialchars($event['eventDate'] ?? '', ENT_QUOTES); ?>">
                        <div class="card-stripe" data-color="<?php echo $tab === 'completed' ? 'green' : ($tab === 'cancelled' ? 'red' : ($tab === 'pending' ? 'amber' : ($tab === 'ongoing' ? 'blue' : 'purple'))); ?>"></div>
                        <?php if (!empty($event['eventImage'])): ?>
                            <img src="<?php echo htmlspecialchars($event['eventImage']); ?>" alt="Event image" class="img-event-card">
                        <?php endif; ?>
                        <div class="card-body">
                            <?php if ($tab === 'pending'): ?>
                                <span class="mod-pending-badge"><span class="mod-badge-dot"></span>Pending review</span>
                            <?php elseif ($tab === 'ongoing'): ?>
                                <span class="mod-status-tag approved">Ongoing</span>
                            <?php elseif ($tab === 'upcoming'): ?>
                                <span class="mod-status-tag approved">Upcoming</span>
                            <?php elseif ($tab === 'cancelled'): ?>
                                <span class="mod-status-tag declined">Cancelled</span>
                            <?php else: ?>
                                <span class="mod-status-tag approved">Completed</span>
                            <?php endif; ?>

                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>

                            <div class="event-meta">
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                                    <span><?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/></svg>
                                    <span><?php echo date('h:iA', strtotime($event['eventTime'])); ?><?php if (!empty($event['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($event['eventEndTime'])); ?><?php endif; ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0118 0z"/><circle cx="12" cy="10" r="3"/></svg>
                                    <span><?php echo htmlspecialchars($event['venue']); ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                                    <a href="ClubDetailsModerator.php?id=<?php echo (int)$event['adminID']; ?>" class="no-deco stat-link-inherit"><span><?php echo htmlspecialchars($event['club_name'] ?? 'Unknown Club'); ?></span></a>
                                </div>
                            </div>

                            <div class="card-divider"></div>

                            <div class="mod-card-actions <?php echo $tab === 'cancelled' ? 'equal-action-buttons moderator-cancelled-actions' : 'mod-single-action-center'; ?>">
                                <a href="EventDetailsModerator.php?id=<?php echo $event['eventID']; ?>" class="btn-mod-details">Details</a>
                                <?php if ($tab === 'cancelled'): ?>
                                    <form method="POST" action="DeleteCancelledEvent.php" onsubmit="return confirm('Permanently delete this cancelled event record? This cannot be undone.');">
                                        <input type="hidden" name="event_id" value="<?php echo (int)$event['eventID']; ?>">
                                        <button type="submit" class="btn-outline-danger btn-mod-delete">Delete Record</button>
                                    </form>
                                <?php endif; ?>
                            </div>
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
            <div class="event-empty-box" id="modEventNoResults" style="display:none;">
                <div class="empty-icon">
                    <svg viewBox="0 0 24 24"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                </div>
                <p>No matching events</p>
                <p class="empty-subtext">Try a different search or filter.</p>
            </div>
        </section>

    </main>
    <script>
        const modEventSearch = document.getElementById('modEventSearch');
        const modEventClubFilter = document.getElementById('modEventClubFilter');
        const modEventCount = document.getElementById('modEventResultCount');
        const modEventNoResults = document.getElementById('modEventNoResults');
        const modEventCards = document.querySelectorAll('.event-card');

        function applyModeratorEventFilters() {
            const q = modEventSearch.value.toLowerCase().trim();
            const activeFilter = document.querySelector('[data-eventfilter].active');
            const filter = activeFilter ? activeFilter.dataset.eventfilter : 'all';
            const club = modEventClubFilter.value;
            const now = new Date();
            now.setHours(0, 0, 0, 0);
            const weekLimit = new Date(now.getTime() + (7 * 24 * 60 * 60 * 1000));
            let visible = 0;

            modEventCards.forEach(card => {
                let show = true;
                if (q && !card.dataset.title.includes(q) && !card.dataset.club.includes(q) && !card.dataset.venue.includes(q)) show = false;
                if (club && card.dataset.club !== club) show = false;
                if (filter === 'week') {
                    const eventDate = new Date(card.dataset.date);
                    show = show && eventDate >= now && eventDate <= weekLimit;
                }
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });

            modEventCount.textContent = visible + ' event' + (visible !== 1 ? 's' : '') + ' found';
            modEventNoResults.style.display = visible === 0 && modEventCards.length > 0 ? '' : 'none';
        }

        modEventSearch.addEventListener('input', applyModeratorEventFilters);
        modEventClubFilter.addEventListener('change', applyModeratorEventFilters);
        document.querySelectorAll('[data-eventfilter]').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('[data-eventfilter]').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                applyModeratorEventFilters();
            });
        });
    </script>
</body>
</html>
