<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $eventID = isset($_GET['id']) ? (int)$_GET['id'] : 0;

    if (!$eventID) {
        header("Location: AdminDashboard.php");
        exit();
    }

    $stmt = $conn->prepare("SELECT * FROM events WHERE eventID = ? AND adminID = ?");
    $stmt->bind_param("is", $eventID, $adminID);
    $stmt->execute();
    $event = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$event) {
        die("Event not found.");
    }

    $partStmt = $conn->prepare("SELECT r.studentID, r.payment_method, r.payment_status, r.attendance_status, s.name, s.email FROM registrations r JOIN students s ON r.studentID = s.studentID WHERE r.eventID = ? ORDER BY s.name ASC");
    $partStmt->bind_param("i", $eventID);
    $partStmt->execute();
    $participants = $partStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $partStmt->close();

    $fee = floatval($event['fee'] ?? 0);
    $totalPaid = count(array_filter($participants, fn($p) => $p['payment_status'] === 'paid'));
    $totalPresent = count(array_filter($participants, fn($p) => $p['attendance_status'] === 'present'));
    $totalCollected = $totalPaid * $fee;

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="report_' . $event['eventTitle'] . '_' . date('Ymd') . '.csv"');

    $output = fopen('php://output', 'w');

    fputcsv($output, ['Event Report']);
    fputcsv($output, []);
    fputcsv($output, ['Title', $event['eventTitle']]);
    fputcsv($output, ['Date', formatDateRange($event['eventDate'], $event['eventEndDate'] ?? null)]);
    fputcsv($output, ['Time', date('h:iA', strtotime($event['eventTime'])) . (!empty($event['eventEndTime']) ? ' — ' . date('h:iA', strtotime($event['eventEndTime'])) : '')]);
    fputcsv($output, ['Venue', $event['venue']]);
    fputcsv($output, ['Fee', $fee > 0 ? 'RM' . number_format($fee, 2) : 'Free']);
    fputcsv($output, ['Capacity', $event['capacity']]);
    fputcsv($output, ['Status', $event['status'] ?? 'N/A']);
    fputcsv($output, []);
    fputcsv($output, ['Summary']);
    fputcsv($output, ['Total Registered', count($participants)]);
    fputcsv($output, ['Total Paid', $totalPaid]);
    fputcsv($output, ['Total Collected (RM)', number_format($totalCollected, 2)]);
    fputcsv($output, ['Total Present', $totalPresent]);
    fputcsv($output, []);
    fputcsv($output, ['Participants']);
    fputcsv($output, ['No.', 'Student ID', 'Name', 'Email', 'Payment Method', 'Payment Status', 'Attendance']);

    $i = 1;
    foreach ($participants as $p) {
        fputcsv($output, [
            $i++,
            $p['studentID'],
            $p['name'],
            $p['email'],
            $p['payment_method'] ?? '-',
            $p['payment_status'] ?? 'unpaid',
            $p['attendance_status'] ?? 'absent'
        ]);
    }

    fclose($output);
    exit();
