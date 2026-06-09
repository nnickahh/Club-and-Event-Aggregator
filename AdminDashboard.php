<!--AdminDashboard.php-->
<?php
    session_start();
    require_once 'db_connect.php';

    // Security Check: Redirect to login if user is not authorized as an admin
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminLogin.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $clubName = isset($_SESSION['club_name']) ? $_SESSION['club_name'] : "Club Admin";

    // FIXED QUERY: Reads safely from the events table only, avoiding missing table errors.
    $query = "SELECT * FROM events WHERE adminID = ? ORDER BY eventDate ASC";
              
    $stmt = $conn->prepare($query);
    $stmt->bind_param("s", $adminID); 
    $stmt->execute();
    $result = $stmt->get_result();

    // Sort events into arrays based on current calendar date
    $upcomingEvents = [];
    $completedEvents = [];
    $currentDate = date('Y-m-d');

    if ($result && $result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            if ($row['eventDate'] >= $currentDate) {
                $upcomingEvents[] = $row;
            } else {
                $completedEvents[] = $row;
            }
        }
    }
    $stmt->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - <?php echo htmlspecialchars($clubName); ?></title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <!-- Dynamic Admin Navbar Replacement incorporating the Profile Settings dropdown hooks -->
    <?php include 'AdminNavbar.php'; ?>

    <main class="dashboard-container">
        
        <header class="dashboard-header">
            <div>
                <h1>Welcome Back, <?php echo htmlspecialchars(isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Admin User'); ?></h1>
                <p class="subtitle">Managing: <strong><?php echo htmlspecialchars($clubName); ?></strong></p>
            </div>
            <a href="CreateEvent.php" class="btn-primary" style="text-decoration: none; display: inline-block;">Create New Event</a>
        </header>

        <!-- ONGOING & UPCOMING EVENTS SECTION -->
        <h3 class="section-title">Upcoming Events</h3>
        <section class="event-grid">
            <?php if (!empty($upcomingEvents)): ?>
                <?php foreach($upcomingEvents as $event): ?>
                    <article class="event-card">
                        <div>
                            <span class="tag tag-upcoming">Upcoming</span>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <!-- Safely displays max capacity set by the admin -->
                                <strong>Capacity Limit:</strong> <?php echo htmlspecialchars($event['capacity']); ?> seats max<br>
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                                <strong>Time:</strong> <?php echo htmlspecialchars($event['eventTime']); ?><br>
                                <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?>
                            </div>

                            <p class="event-desc"><?php echo htmlspecialchars($event['description']); ?></p>
                        </div>

                        <div class="action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="btn-outline">Edit</a>
                            <a href="DeleteEvent.php?id=<?php echo $event['eventID']; ?>" class="btn-danger" onclick="return confirm('Are you sure you want to delete this event?');">Delete</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Upcoming Events Found</p>
                    <p class="empty-subtext">Click the 'Create New Event' button to get started.</p>
                </div>
            <?php endif; ?>
        </section>

        <!-- COMPLETED EVENTS SECTION -->
        <h3 class="section-title">Completed Events</h3>
        <section class="event-grid">
            <?php if (!empty($completedEvents)): ?>
                <?php foreach($completedEvents as $event): ?>
                    <article class="event-card event-card-completed"> 
                        <div>
                            <span class="tag">Completed</span>
                            <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                            
                            <div class="event-meta">
                                <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                                <strong>Status:</strong> Archived / Completed
                            </div>
                        </div>

                        <div class="action-buttons">
                            <a href="ExportReport.php?id=<?php echo $event['eventID']; ?>" class="btn-outline btn-full-width">Generate Report</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Completed Events</p>
                    <p class="empty-subtext">Your event history will appear here once events have passed.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>