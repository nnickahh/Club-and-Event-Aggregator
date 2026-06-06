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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Student Main Dashboard</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <h2>Discover Campus Events</h2>
        
        <input type="text" class="search-bar" placeholder="Search events by name, club, or venue...">

        <section class="event-grid">
            <?php
            // Check if there are any events in the table
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
            ?>
            <article class="event-card">
                <span class="tag"><?php echo htmlspecialchars($row['clubName']); ?></span>
                <h3><?php echo htmlspecialchars($row['eventTitle']); ?></h3>
                <div class="event-meta">
                    <strong>Date:</strong> <?php echo date('d M Y', strtotime($row['eventDate'])); ?><br>
                    <strong>Time:</strong> <?php echo date('h:i A', strtotime($row['eventTime'])); ?><br>
                    <strong>Venue:</strong> <?php echo htmlspecialchars($row['venue']); ?>
                </div>
                <a href="DetailedEvent.php?id=<?php echo $row['eventID']; ?>" class="btn-primary">View Details & RSVP</a>
            </article>
            <?php
                    }
                } else {
                    // Show this if the 'events' table is empty
                    echo "<div class='event-empty-box'>
                        <p>No Upcoming Events Available</p>
                        <p class='empty-subtext'>Check back later for new activities!</p>
                    </div>";
                }
            ?>
        </section>
    </main>
</body>
</html>