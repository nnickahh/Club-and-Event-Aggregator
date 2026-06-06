<?php
    // GetEvents.php
    require_once 'db_connect.php';

    // Fetch events using your exact SQL column names
    $query = "SELECT eventTitle, eventDate FROM events";
    $result = $conn->query($query);

    $events = [];

    if ($result) {
        while($row = $result->fetch_assoc()) {
            $events[] = $row;
        }
    }

    // Tell the browser this is JSON data, not a webpage
    header('Content-Type: application/json');
    echo json_encode($events);
?>