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
        $capacity    = intval($_POST['capacity']); 
        $description = trim($_POST['description']);

        $stmt = $conn->prepare("INSERT INTO events (adminID, eventTitle, eventDate, eventTime, venue, capacity, description) VALUES (?, ?, ?, ?, ?, ?, ?)");
        
        if ($stmt) {
            $stmt->bind_param("sssssis", $adminID, $eventTitle, $eventDate, $eventTime, $venue, $capacity, $description);
            
            if ($stmt->execute()) {
                header("Location: AdminDashboard.php");
                exit();
            } else {
                $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Error creating event. Please try again.</p>";
            }
            $stmt->close();
        } else {
            $message = "<p class='msg-error' style='color:#ED1C24; font-weight:600;'>Database preparation failed.</p>";
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

    <div class="container">
        <div class="profile-box">
            <h2>Create New Event</h2>
            
            <?php echo $message; ?>

            <form action="CreateEvent.php" method="POST">
                
                <div class="form-group">
                    <label>Event Title :</label>
                    <input type="text" name="eventTitle" placeholder="e.g., Annual General Meeting" required>
                </div>

                <div class="form-group">
                    <label>Event Date :</label>
                    <input type="date" name="eventDate" min="<?php echo date('Y-m-d'); ?>" required>
                </div>

                <div class="form-group">
                    <label>Event Time Schedule :</label>
                    <input type="text" name="eventTime" placeholder="e.g., 2:00 PM - 6:00 PM" required>
                </div>

                <div class="form-group">
                    <label>Venue Location :</label>
                    <input type="text" name="venue" placeholder="e.g., Sports Hall Block B" required>
                </div>

                <div class="form-group">
                    <label>Max Capacity Limit :</label>
                    <input type="number" name="capacity" min="1" placeholder="e.g., 50" required>
                </div>

                <div class="form-group">
                    <label>Event Description / Remarks :</label>
                    <textarea name="description" placeholder="Provide event context guidelines or register rules..." required style="width: 100%; min-height: 100px; padding: 10px; border: 1px solid var(--border, rgba(0,0,0,0.15)); border-radius: 6px; box-sizing: border-box; font-family: inherit; resize: vertical;"></textarea>
                </div>
                
                <button type="submit" name="submit" class="btn-primary" style="margin-top: 15px; width: 100%;">Publish Event</button>
                
                <div style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--ink-3, #888);">
                    Changed your mind? <a href="AdminDashboard.php" style="color: var(--red, #ED1C24); text-decoration: none; font-weight: 600;">Back to Dashboard</a>
                </div>
            </form>
        </div>
    </div>

</body>
</html>