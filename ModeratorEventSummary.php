<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }

    $currentPage = 'summary';
    $today = date('Y-m-d');
    $period = $_GET['period'] ?? 'this_month';
    $clubFilter = $_GET['club'] ?? 'all';
    $customStart = $_GET['start_date'] ?? '';
    $customEnd = $_GET['end_date'] ?? '';

    switch ($period) {
        case 'last_3_months':
            $startDate = date('Y-m-01', strtotime('-2 months'));
            $endDate = date('Y-m-t');
            $periodLabel = 'Last 3 Months';
            break;
        case 'this_year':
            $startDate = date('Y-01-01');
            $endDate = date('Y-12-31');
            $periodLabel = 'This Year';
            break;
        case 'last_12_months':
            $startDate = date('Y-m-d', strtotime('-12 months'));
            $endDate = $today;
            $periodLabel = 'Last 12 Months';
            break;
        case 'custom':
            $startDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $customStart) ? $customStart : date('Y-m-01');
            $endDate = preg_match('/^\d{4}-\d{2}-\d{2}$/', $customEnd) ? $customEnd : date('Y-m-t');
            if ($endDate < $startDate) {
                $endDate = $startDate;
            }
            $periodLabel = 'Custom Range';
            break;
        case 'this_month':
        default:
            $period = 'this_month';
            $startDate = date('Y-m-01');
            $endDate = date('Y-m-t');
            $periodLabel = 'This Month';
            break;
    }

    $pendingClubs = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'pending'");
    if ($r) { $pendingClubs = (int)$r->fetch_assoc()['c']; }

    $pendingEvents = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
    if ($r) { $pendingEvents = (int)$r->fetch_assoc()['c']; }

    $clubOptions = [];
    $clubResult = $conn->query("
        SELECT DISTINCT a.adminID, a.clubName
        FROM events e
        LEFT JOIN admins a ON e.adminID = a.adminID
        WHERE a.adminID IS NOT NULL
        ORDER BY a.clubName ASC
    ");
    if ($clubResult) {
        $clubOptions = $clubResult->fetch_all(MYSQLI_ASSOC);
    }

    $where = ["e.eventDate <= ?", "COALESCE(e.eventEndDate, e.eventDate) >= ?"];
    $types = "ss";
    $params = [$endDate, $startDate];
    if ($clubFilter !== 'all') {
        $where[] = "e.adminID = ?";
        $types .= "s";
        $params[] = $clubFilter;
    }
    $whereSql = implode(' AND ', $where);

    $summarySql = "
        SELECT
            COALESCE(a.adminID, e.adminID, '') AS adminID,
            COALESCE(a.clubName, e.clubName, 'Unknown Club') AS clubName,
            COUNT(e.eventID) AS totalEvents,
            SUM(CASE WHEN e.status = 'pending' THEN 1 ELSE 0 END) AS pendingEvents,
            SUM(CASE WHEN e.status = 'cancelled' THEN 1 ELSE 0 END) AS cancelledEvents,
            SUM(CASE WHEN e.status = 'approved' AND ? BETWEEN e.eventDate AND COALESCE(e.eventEndDate, e.eventDate) THEN 1 ELSE 0 END) AS ongoingEvents,
            SUM(CASE WHEN e.status = 'approved' AND e.eventDate > ? THEN 1 ELSE 0 END) AS upcomingEvents,
            SUM(CASE WHEN e.status = 'ended' OR (e.status = 'approved' AND COALESCE(e.eventEndDate, e.eventDate) < ?) THEN 1 ELSE 0 END) AS completedEvents,
            SUM(COALESCE(rc.participantCount, 0)) AS participants,
            SUM(COALESCE(rc.presentCount, 0)) AS attended
        FROM events e
        LEFT JOIN admins a ON e.adminID = a.adminID
        LEFT JOIN (
            SELECT eventID,
                   COUNT(*) AS participantCount,
                   SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) AS presentCount
            FROM registrations
            GROUP BY eventID
        ) rc ON e.eventID = rc.eventID
        WHERE $whereSql
        GROUP BY COALESCE(a.adminID, e.adminID, ''), COALESCE(a.clubName, e.clubName, 'Unknown Club')
        ORDER BY totalEvents DESC, clubName ASC
    ";
    $summaryTypes = "sss" . $types;
    $summaryParams = array_merge([$today, $today, $today], $params);
    $summaryStmt = $conn->prepare($summarySql);
    $summaryStmt->bind_param($summaryTypes, ...$summaryParams);
    $summaryStmt->execute();
    $summaryRows = $summaryStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $summaryStmt->close();

    $detailSql = "
        SELECT e.eventID, e.eventTitle, e.eventDate, e.eventEndDate, e.status,
               a.clubName,
               COALESCE(rc.participantCount, 0) AS participants,
               COALESCE(rc.presentCount, 0) AS attended
        FROM events e
        LEFT JOIN admins a ON e.adminID = a.adminID
        LEFT JOIN (
            SELECT eventID,
                   COUNT(*) AS participantCount,
                   SUM(CASE WHEN attendance_status = 'present' THEN 1 ELSE 0 END) AS presentCount
            FROM registrations
            GROUP BY eventID
        ) rc ON e.eventID = rc.eventID
        WHERE $whereSql
        ORDER BY e.eventDate DESC, e.eventTitle ASC
        LIMIT 100
    ";
    $detailStmt = $conn->prepare($detailSql);
    $detailStmt->bind_param($types, ...$params);
    $detailStmt->execute();
    $eventRows = $detailStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $detailStmt->close();

    $totals = [
        'events' => 0,
        'completed' => 0,
        'upcoming' => 0,
        'ongoing' => 0,
        'cancelled' => 0,
        'participants' => 0,
        'attended' => 0,
    ];
    foreach ($summaryRows as $row) {
        $totals['events'] += (int)$row['totalEvents'];
        $totals['completed'] += (int)$row['completedEvents'];
        $totals['upcoming'] += (int)$row['upcomingEvents'];
        $totals['ongoing'] += (int)$row['ongoingEvents'];
        $totals['cancelled'] += (int)$row['cancelledEvents'];
        $totals['participants'] += (int)$row['participants'];
        $totals['attended'] += (int)$row['attended'];
    }
    $attendanceRate = $totals['participants'] > 0 ? round(($totals['attended'] / $totals['participants']) * 100) : 0;
    $exportUrl = 'ModeratorEventSummaryExport.php?' . http_build_query([
        'period' => $period,
        'club' => $clubFilter,
        'start_date' => $startDate,
        'end_date' => $endDate
    ]);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator - Event Summary</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">
        <div class="mod-page-header summary-hero">
            <div>
                <span class="summary-kicker">Moderator Report</span>
                <h2 class="mod-title">Event Summary</h2>
                <p class="mod-sub">Review event activity by club and date range.</p>
            </div>
            <a href="<?php echo htmlspecialchars($exportUrl); ?>" class="summary-export-btn">
                <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7,10 12,15 17,10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                Export Report
            </a>
        </div>

        <form method="GET" class="summary-filter-panel">
            <div>
                <label>Period</label>
                <select name="period" id="summaryPeriod" class="form-select">
                    <option value="this_month" <?php echo $period === 'this_month' ? 'selected' : ''; ?>>This Month</option>
                    <option value="last_3_months" <?php echo $period === 'last_3_months' ? 'selected' : ''; ?>>Last 3 Months</option>
                    <option value="this_year" <?php echo $period === 'this_year' ? 'selected' : ''; ?>>This Year</option>
                    <option value="last_12_months" <?php echo $period === 'last_12_months' ? 'selected' : ''; ?>>Last 12 Months</option>
                    <option value="custom" <?php echo $period === 'custom' ? 'selected' : ''; ?>>Custom Range</option>
                </select>
            </div>
            <div>
                <label>Club</label>
                <select name="club" class="form-select">
                    <option value="all">All Clubs</option>
                    <?php foreach ($clubOptions as $club): ?>
                        <option value="<?php echo htmlspecialchars($club['adminID']); ?>" <?php echo $clubFilter === $club['adminID'] ? 'selected' : ''; ?>>
                            <?php echo htmlspecialchars($club['clubName']); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="summary-custom-date">
                <label>Start Date</label>
                <input type="date" name="start_date" class="form-input" value="<?php echo htmlspecialchars($startDate); ?>">
            </div>
            <div class="summary-custom-date">
                <label>End Date</label>
                <input type="date" name="end_date" class="form-input" value="<?php echo htmlspecialchars($endDate); ?>">
            </div>
            <button type="submit" class="btn-primary-sm">Apply Filter</button>
        </form>

        <p class="section-label summary-range-pill">Showing <?php echo htmlspecialchars($periodLabel); ?> · <?php echo date('d M Y', strtotime($startDate)); ?> - <?php echo date('d M Y', strtotime($endDate)); ?></p>

        <section class="summary-card-grid">
            <div class="summary-card summary-card-red"><span>Total Events</span><strong><?php echo $totals['events']; ?></strong></div>
            <div class="summary-card summary-card-green"><span>Completed</span><strong><?php echo $totals['completed']; ?></strong></div>
            <div class="summary-card summary-card-blue"><span>Upcoming / Ongoing</span><strong><?php echo $totals['upcoming'] + $totals['ongoing']; ?></strong></div>
            <div class="summary-card summary-card-amber"><span>Participants</span><strong><?php echo $totals['participants']; ?></strong></div>
            <div class="summary-card summary-card-purple"><span>Attendance Rate</span><strong><?php echo $attendanceRate; ?>%</strong></div>
        </section>

        <section class="summary-table-card">
            <h3>Club Summary</h3>
            <div class="table-responsive">
                <table class="part-table summary-table">
                    <thead>
                        <tr>
                            <th>Club</th>
                            <th>Total</th>
                            <th>Ongoing</th>
                            <th>Upcoming</th>
                            <th>Completed</th>
                            <th>Cancelled</th>
                            <th>Participants</th>
                            <th>Attendance</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($summaryRows as $row): ?>
                            <?php
                                $participants = (int)$row['participants'];
                                $attended = (int)$row['attended'];
                                $rate = $participants > 0 ? round(($attended / $participants) * 100) : 0;
                            ?>
                            <tr>
                                <td><?php echo htmlspecialchars($row['clubName']); ?></td>
                                <td><?php echo (int)$row['totalEvents']; ?></td>
                                <td><?php echo (int)$row['ongoingEvents']; ?></td>
                                <td><?php echo (int)$row['upcomingEvents']; ?></td>
                                <td><?php echo (int)$row['completedEvents']; ?></td>
                                <td><?php echo (int)$row['cancelledEvents']; ?></td>
                                <td><?php echo $participants; ?></td>
                                <td><?php echo $rate; ?>%</td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($summaryRows)): ?>
                            <tr><td colspan="8" class="text-center text-xs-muted">No events found for this filter.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>

        <section class="summary-table-card">
            <h3>Event Details</h3>
            <div class="table-responsive">
                <table class="part-table summary-table">
                    <thead>
                        <tr>
                            <th>Event</th>
                            <th>Club</th>
                            <th>Date</th>
                            <th>Status</th>
                            <th>Participants</th>
                            <th>Attended</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($eventRows as $event): ?>
                            <tr>
                                <td><a href="EventDetailsModerator.php?id=<?php echo (int)$event['eventID']; ?>"><?php echo htmlspecialchars($event['eventTitle']); ?></a></td>
                                <td><?php echo htmlspecialchars($event['clubName'] ?? 'Unknown Club'); ?></td>
                                <td><?php echo formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($event['status'])); ?></td>
                                <td><?php echo (int)$event['participants']; ?></td>
                                <td><?php echo (int)$event['attended']; ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if (empty($eventRows)): ?>
                            <tr><td colspan="6" class="text-center text-xs-muted">No event details available.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </section>
    </main>

    <script>
        const summaryPeriod = document.getElementById('summaryPeriod');
        const customDateFields = document.querySelectorAll('.summary-custom-date');
        function syncCustomDateFields() {
            const show = summaryPeriod.value === 'custom';
            customDateFields.forEach(field => field.style.display = show ? '' : 'none');
        }
        summaryPeriod.addEventListener('change', syncCustomDateFields);
        syncCustomDateFields();
    </script>
</body>
</html>
