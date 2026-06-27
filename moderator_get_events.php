<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        http_response_code(401);
        header('Content-Type: application/json');
        echo json_encode([]);
        exit();
    }
    session_write_close();

    $stmt = $conn->prepare("
        SELECT e.*, COALESCE(e.clubName, a.clubName) AS clubName
        FROM events e
        LEFT JOIN admins a ON e.adminID = a.adminID
        WHERE e.status IN ('approved', 'ended')
        ORDER BY e.eventDate ASC
    ");
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
