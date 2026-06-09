<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);

    session_start();
    require_once 'db_connect.php';

    // Security Check: Redirect to login if user is not authorized as an admin
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $message = "";

    // Handle Form Processing when user clicks submit
    if (isset($_POST["submit"])) {
        $eventTitle  = trim($_POST['eventTitle']);
        $eventDate   = $_POST['eventDate'];
        $eventTime   = trim($_POST['eventTime']);
        $venue       = trim($_POST['venue']);
        $capacity    = intval($_POST['capacity']); // Added to safely grab capacity input
        $description = trim($_POST['description']);

        // Insert including the verified 'capacity' column details
        $stmt = $conn->prepare("INSERT INTO events (adminID, eventTitle, eventDate, eventTime, venue, capacity, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("sssssis", $adminID, $eventTitle, $eventDate, $eventTime, $venue, $capacity, $description);
            
            if ($stmt->execute()) {
                // Success! Redirect straight back to your clean dashboard grid
                header("Location: AdminDashboard.php");
                exit();
            } else {
                $message = "<div class='msg-error'>Error execution failure: Unable to save your event record.</div>";
            }
            $stmt->close();
        } else {
            $message = "<div class='msg-error'>Database pre-compilation query failure query error.</div>";
        }
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Create New Event</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
    
    <?php include 'AdminNavbar.php'; ?>

    <div class="login-body" style="min-height: calc(100vh - 80px); padding: 20px 0; box-sizing: border-box;">
        <div class="box" style="max-width: 500px; width: 100%;">
            <h2 class="h2">Create Event</h2>
            
            <?php echo $message; ?>

            <form action="CreateEvent.php" method="POST">
                
                <div class="form-group">
                    <label>Event Title / Name</label>
                    <input type="text" name="eventTitle" placeholder="e.g., INTI Badminton Championship" required>
                </div>

                <div class="form-group">
                    <label>Event Date</label>
                    <input type="date" name="eventDate" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Event Time Schedule</label>
                    <input type="text" name="eventTime" placeholder="e.g., 2:00 PM - 6:00 PM" required>
                </div>

                <div class="form-group">
                    <label>Venue Location</label>
                    <input type="text" name="venue" placeholder="e.g., Sports Hall Block B" required>
                </div>

                <div class="form-group">
                    <label>Max Capacity Limit</label>
                    <input type="number" name="capacity" min="1" placeholder="e.g., 50" required>
                </div>

                <div class="form-group">
                    <label>Event Description / Remarks</label>
                    <textarea name="description" placeholder="Provide event context guidelines or register rules..." required></textarea>
                </div>
                
                <button type="submit" name="submit" class="btn-primary" style="margin-top: 10px;">Publish Event</button>
                
                <div class="links center-links" style="margin-top: 20px;">
                    Changed your mind? <a href="AdminDashboard.php" class="link-primary">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>