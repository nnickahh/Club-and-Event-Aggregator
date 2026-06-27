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

    <nav class="navbar">
        <a href="StudentDashboard.php" class="logo">
            <img src="Image/inti-logo.png" alt="INTI Logo">
        </a>
        <div class="nav-links">
            <a href="StudentDashboard.php">Events Feed</a>
            <a href="Clubs.php">Clubs</a>
            <a href="Calendar.php">Calendar</a>
            <a href="MyEvent.php" class="active">My Schedule</a>
            <div class="profile-dropdown">
                <span class="profile-name">👤 <?php echo htmlspecialchars($_SESSION['full_name']); ?></span>
                <div class="dropdown-content">
                    <a href="ProfileSettings.php">Settings</a>
                    <hr>
                    <a href="Logout.php" class="logout-link">Log Out</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container">
        <h2>My Upcoming Schedule</h2>
        
        <section class="event-grid">
            <?php
                if ($result && $result->num_rows > 0) {
                    while($row = $result->fetch_assoc()) {
                        ?>
                        <article class="event-card" style="width: 100%; display: flex; justify-content: space-between; align-items: center; margin-bottom: 15px;">
                            <div>
                                <span class="tag" style="background: #ffeb3b; color: #333;">Event Confirmed</span>
                                <h3 style="margin-top: 10px;"><?php echo htmlspecialchars($row['eventTitle']); ?></h3>
                                <div class="event-meta" style="margin-bottom: 0;">
                                    <?php echo date('d F Y', strtotime($row['eventDate'])); ?> | 
                                    <?php echo date('h:i A', strtotime($row['eventTime'])); ?> | 
                                    <?php echo htmlspecialchars($row['venue']); ?>
                                </div>
                            </div>
                            
                            <form action="CancelRegistration.php" method="POST" onsubmit="return confirm('Are you sure you want to cancel this registration?');">
                                <input type="hidden" name="eventID" value="<?php echo $row['eventID']; ?>">
                                <button type="submit" class="btn-primary" style="width: auto; background-color: #fff; color: #ff4d4d; border: 1px solid #ff4d4d;">Cancel</button>
                            </form>
                        </article>
                        <?php
                    }
                } else {
                    echo "<div style='text-align: center; padding: 40px; background: white; border: 1px dashed #ccc; width: 100%;'>
                            <p>You haven't registered for any events yet.</p>
                            <a href='StudentDashboard.php' style='color: #1f3cf4;'>Browse Events</a>
                        </div>";
                }
            ?>
        </section>
    </main>

</body>
</html>