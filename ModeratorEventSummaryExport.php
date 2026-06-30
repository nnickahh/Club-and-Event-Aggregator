<?php
session_start();
require_once 'db_connect.php';

if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
    header("Location: ModeratorLogin.php");
    exit();
}

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

$clubName = 'All Clubs';
if ($clubFilter !== 'all') {
    $clubStmt = $conn->prepare("SELECT clubName FROM admins WHERE adminID = ? LIMIT 1");
    $clubStmt->bind_param("s", $clubFilter);
    $clubStmt->execute();
    $clubResult = $clubStmt->get_result()->fetch_assoc();
    if ($clubResult) {
        $clubName = $clubResult['clubName'];
    }
    $clubStmt->close();
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

$filename = 'moderator_event_summary_' . date('Ymd_His') . '.csv';
header('Content-Type: text/csv; charset=utf-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');

$output = fopen('php://output', 'w');
fputcsv($output, ['Moderator Event Summary Report']);
fputcsv($output, ['Period', $periodLabel]);
fputcsv($output, ['Date Range', date('d M Y', strtotime($startDate)) . ' - ' . date('d M Y', strtotime($endDate))]);
fputcsv($output, ['Club', $clubName]);
fputcsv($output, ['Generated At', date('d M Y h:i A')]);
fputcsv($output, []);
fputcsv($output, ['Overview']);
fputcsv($output, ['Total Events', 'Completed', 'Upcoming', 'Ongoing', 'Cancelled', 'Participants', 'Attended', 'Attendance Rate']);
fputcsv($output, [
    $totals['events'],
    $totals['completed'],
    $totals['upcoming'],
    $totals['ongoing'],
    $totals['cancelled'],
    $totals['participants'],
    $totals['attended'],
    $attendanceRate . '%'
]);
fputcsv($output, []);
fputcsv($output, ['Club Summary']);
fputcsv($output, ['Club', 'Total', 'Ongoing', 'Upcoming', 'Completed', 'Cancelled', 'Participants', 'Attendance']);
foreach ($summaryRows as $row) {
    $participants = (int)$row['participants'];
    $attended = (int)$row['attended'];
    $rate = $participants > 0 ? round(($attended / $participants) * 100) : 0;
    fputcsv($output, [
        $row['clubName'],
        (int)$row['totalEvents'],
        (int)$row['ongoingEvents'],
        (int)$row['upcomingEvents'],
        (int)$row['completedEvents'],
        (int)$row['cancelledEvents'],
        $participants,
        $rate . '%'
    ]);
}
fputcsv($output, []);
fputcsv($output, ['Event Details']);
fputcsv($output, ['Event', 'Club', 'Date', 'Status', 'Participants', 'Attended']);
foreach ($eventRows as $event) {
    fputcsv($output, [
        $event['eventTitle'],
        $event['clubName'] ?? 'Unknown Club',
        formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null),
        ucfirst($event['status']),
        (int)$event['participants'],
        (int)$event['attended']
    ]);
}
fclose($output);
exit();
