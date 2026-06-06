<?php
    session_start();
    require_once 'db_connect.php';

    // Security Check: Only logged-in students
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }

    $currentStudent = $_SESSION['student_id'];

    // SQL JOIN: Get event details for events this specific student registered for
    $query = "SELECT e.* FROM events e 
            JOIN registrations r ON e.eventID = r.eventID 
            WHERE r.studentID = ? 
            ORDER BY e.eventDate ASC";

    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $currentStudent);
    $stmt->execute();
    $result = $stmt->get_result();
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Schedule</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <h2>My Upcoming Schedule</h2>
        
        <section class="event-grid">
            <?php
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        ?>
                        <article class="event-card my-event-card">
                            <div>
                                <span class="tag tag-confirmed">Event Confirmed</span>
                                <h3 class="event-card-title"><?php echo htmlspecialchars($row['eventTitle']); ?></h3>
                                <div class="event-meta event-meta-no-margin">
                                    <?php echo date('d F Y', strtotime($row['eventDate'])); ?> | 
                                    <?php echo date('h:i A', strtotime($row['eventTime'])); ?> | 
                                    <?php echo htmlspecialchars($row['venue']); ?>
                                </div>
                            </div>
                            
                            <form action="CancelRegistration.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this registration?');">
                                <input type="hidden" name="eventID" value="<?php echo $row['eventID']; ?>">
                                <button type="submit" class="btn-primary btn-cancel">Cancel</button>
                            </form>
                        </article>
                        <?php
                    }
                } else {
                    echo "<div class='event-empty-box'>
                            <p>You haven't registered for any events yet.</p>
                            <a href='StudentDashboard.php'>Browse Events</a>
                        </div>";
                }
            ?>
        </section>
    </main>

</body>
</html>