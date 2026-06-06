<?php
    session_start();
    require_once 'db_connect.php';

    // 1. Security Check: Ensure only active Admins can access this page
    if (!isset($_SESSION['admin_id']) || $_SESSION['role'] !== 'admin') {
        header("Location: AdminDashboard.php");
        exit();
    }

    $adminID = $_SESSION['admin_id'];
    $clubName = $_SESSION['full_name']; // You can also fetch the specific clubName from DB if needed

    // 2. Fetch Events specific to this Club Admin
    // (Assuming your events table uses adminID or club_id as the foreign key)
    $query = "SELECT * FROM events WHERE club_id = ? ORDER BY eventDate ASC";
    $stmt = $conn->prepare($query);
    
    // If your foreign key is named differently in the events table, change 'club_id' above
    $stmt->bind_param("s", $adminID); 
    $stmt->execute();
    $result = $stmt->get_result();

    // 3. Sort events into arrays based on date for your Upcoming/Completed wireframe layout
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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Dashboard - <?php echo htmlspecialchars($clubName); ?></title>
    <link rel="stylesheet" type="text/css" href="Style.css">
    <style>
        /* Extra Admin-Specific Styles that integrate with your Master CSS */
        .admin-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 30px;
        }
        
        .section-title {
            border-bottom: 2px solid #ED1C24;
            padding-bottom: 10px;
            margin-top: 40px;
            color: #333;
        }

        .action-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }

        /* Override button sizes for the admin cards */
        .action-buttons .btn-primary, 
        .action-buttons .btn-outline {
            margin-top: 0;
            padding: 8px 12px;
            font-size: 13px;
        }
    </style>
</head>
<body>
    <nav class="navbar">
        <a href="AdminDashboard.php" class="logo">
            <img src="Image/inti-logo.png" alt="INTI Logo">
        </a>
        <div class="nav-links">
            <a href="AdminDashboard.php" class="active">Home</a>
            <a href="CreateEvent.php">New Event</a>
            <a href="ClubProfile.php">Club Profile</a>
            <a href="ExportReport.php">Export Report</a>

            <div class="profile-dropdown">
                <span class="profile-name">⚙️ <?php echo htmlspecialchars($clubName); ?></span>
                <div class="dropdown-content">
                    <a href="Logout.php" class="logout-link">Log Out</a>
                </div>
            </div>
        </div>
    </nav>

    <main class="container">
        
        <div class="admin-header">
            <h2>Welcome, <?php echo htmlspecialchars($clubName); ?> Admin</h2>
            <a href="CreateEvent.php" class="btn-primary" style="width: auto; padding: 10px 20px;">+ Create New Event</a>
        </div>

        <h3 class="section-title">Upcoming & Ongoing Events</h3>
        <section class="event-grid">
            <?php if (!empty($upcomingEvents)): ?>
                <?php foreach($upcomingEvents as $event): ?>
                    <article class="event-card">
                        <span class="tag tag-club">Upcoming</span>
                        <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                        
                        <div class="event-meta">
                            <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                            <strong>Time:</strong> <?php echo date('h:i A', strtotime($event['eventTime'])); ?><br>
                            <strong>Venue:</strong> <?php echo htmlspecialchars($event['venue']); ?><br>
                            <strong>Capacity:</strong> <?php echo htmlspecialchars($event['capacity'] ?? 'N/A'); ?>
                        </div>

                        <div class="dashed-line"></div>
                        
                        <div class="action-buttons">
                            <a href="EditEvent.php?id=<?php echo $event['eventID']; ?>" class="btn-outline" style="flex: 1; text-align: center;">Edit</a>
                            <form action="DeleteEvent.php" method="POST" style="flex: 1;" onsubmit="return confirm('Are you sure you want to permanently delete this event?');">
                                <input type="hidden" name="event_id" value="<?php echo $event['eventID']; ?>">
                                <button type="submit" class="btn-primary" style="background-color: #333; border-color: #333;">Delete</button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>No Upcoming Events</p>
                    <p class="empty-subtext">Click the 'Create New Event' button to get started.</p>
                </div>
            <?php endif; ?>
        </section>

        <h3 class="section-title">Completed Events</h3>
        <section class="event-grid">
            <?php if (!empty($completedEvents)): ?>
                <?php foreach($completedEvents as $event): ?>
                    <article class="event-card" style="opacity: 0.7;"> <span class="tag">Completed</span>
                        <h3><?php echo htmlspecialchars($event['eventTitle']); ?></h3>
                        
                        <div class="event-meta">
                            <strong>Date:</strong> <?php echo date('d M Y', strtotime($event['eventDate'])); ?><br>
                            <strong>Attendees:</strong> View Report
                        </div>

                        <div class="action-buttons">
                            <a href="ExportReport.php?id=<?php echo $event['eventID']; ?>" class="btn-outline" style="width: 100%; text-align: center;">Generate Report</a>
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