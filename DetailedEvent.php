<?php
    session_start();
    require_once 'db_connect.php';

    // 1. Security Check: Only logged-in students
    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }

    // 2. Get the specific Event ID from the URL (e.g., DetailedEvent.php?id=5)
    if (isset($_GET['id'])) {
        $eventID = $_GET['id'];

        // 3. Fetch specific event details using Prepared Statement
        $stmt = $conn->prepare("SELECT * FROM events WHERE eventID = ?");
        $stmt->bind_param("i", $eventID);
        $stmt->execute();
        $result = $stmt->get_result();

        if ($result->num_rows > 0) {
            $event = $result->fetch_assoc();
        } else {
            // If the ID doesn't exist in the database
            die("Event not found.");
        }
    } else {
        // If someone tries to access the page without an ID
        header("Location: StudentDashboard.php");
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title><?php echo htmlspecialchars($event['eventTitle']); ?> - Details</title>
    <link rel="stylesheet" href="Style.css">
</head>
<body>

    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <a href="StudentDashboard.php" class="back-link">&larr; Back to Feed</a>
        
        <article class="event-detail-card">
            <span class="tag tag-club"><?php echo htmlspecialchars($event['clubName']); ?></span>
            <h1 class="event-detail-title"><?php echo htmlspecialchars($event['eventTitle']); ?></h1>
            
            <div class="event-meta event-meta-lg">
                <p><strong>Date:</strong> <?php echo date('d F Y', strtotime($event['eventDate'])); ?></p>
                <p><strong>Time:</strong> <?php echo date('h:i A', strtotime($event['eventTime'])); ?></p>
                <p><strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?></p>
            </div>

            <hr class="divider-light">
            
            <h3>About This Event</h3>
            <p class="event-description">
                <?php echo nl2br(htmlspecialchars($event['description'])); ?>
            </p>

            <form action="RegisterEvent.php" method="POST">
                <input type="hidden" name="event_id" value="<?php echo $event['eventID']; ?>">
                <button type="submit" name="register" class="btn-primary btn-register">
                    Confirm RSVP & Register
                </button>
            </form>
        </article>
    </main>

</body>
</html>