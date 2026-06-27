<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['admin_id']) || ($_SESSION['role'] ?? '') !== 'admin') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([]);
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    session_write_close();

    $stmt = $conn->prepare("
        SELECT e.*, COALESCE(e.clubName, a.clubName) AS clubName
        FROM events e
        LEFT JOIN admins a ON e.adminID = a.adminID
        WHERE e.adminID = ?
          AND e.status IN ('approved', 'ended')
        ORDER BY e.eventDate ASC
    ");
    $stmt->bind_param("s", $adminID);
    $stmt->execute();
    $result = $stmt->get_result();

    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($events);
?>
