<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        http_response_code(401);
        echo json_encode([]);
        exit();
    }
    session_write_close();

    $studentID = $_SESSION['student_id'];

    $stmt = $conn->prepare("
        SELECT e.* FROM events e
        JOIN registrations r ON e.eventID = r.eventID
        WHERE r.studentID = ? AND e.status = 'approved'
        ORDER BY e.eventDate ASC
    ");
    $stmt->bind_param("s", $studentID);
    $stmt->execute();
    $result = $stmt->get_result();
    $events = [];
    while ($row = $result->fetch_assoc()) {
        $events[] = $row;
    }
    $stmt->close();

    header('Content-Type: application/json');
    echo json_encode($events);
