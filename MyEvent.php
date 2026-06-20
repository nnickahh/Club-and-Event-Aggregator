<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    $studentID = $_SESSION['student_id'];

    // Registered events
    $eventsStmt = $conn->prepare("SELECT e.*, a.clubName, c.clubID FROM events e JOIN registrations r ON e.eventID = r.eventID LEFT JOIN admins a ON e.adminID = a.adminID LEFT JOIN clubs c ON c.adminID = a.adminID WHERE r.studentID = ? ORDER BY e.eventDate ASC");
    $eventsStmt->bind_param("s", $studentID);
    $eventsStmt->execute();
    $eventsResult = $eventsStmt->get_result();
    $eventsStmt->close();

    // Club memberships
    $clubsStmt = $conn->prepare("SELECT a.clubName, a.clubEmail, cm.role, cm.joined_at, c.clubID, c.profilePic FROM club_members cm JOIN admins a ON cm.adminID = a.adminID LEFT JOIN clubs c ON LOWER(TRIM(c.clubName)) = LOWER(TRIM(a.clubName)) WHERE cm.studentID = ? ORDER BY a.clubName ASC");
    $clubsStmt->bind_param("s", $studentID);
    $clubsStmt->execute();
    $clubsResult = $clubsStmt->get_result();
    $clubsStmt->close();

    $activeTab = isset($_GET['tab']) && $_GET['tab'] === 'clubs' ? 'clubs' : 'events';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>My Activities</title>
    <link rel="stylesheet" href="Style.css">
    <style>
        .tab-bar { display:flex; gap:0; margin-bottom:28px; border-bottom:2px solid var(--border); }
        .tab-bar a {
            padding:12px 24px; font-size:14px; font-weight:600; color:var(--ink-3);
            text-decoration:none; border-bottom:2px solid transparent; margin-bottom:-2px;
            transition:color 0.15s, border-color 0.15s;
        }
        .tab-bar a.active { color:var(--red); border-bottom-color:var(--red); }
        .tab-bar a:hover { color:var(--ink); }
        .horizontal-card {
            display:flex; align-items:center; gap:16px;
            padding:16px 20px; background:var(--surface);
            border:1px solid var(--border); border-radius:var(--radius-md);
            margin-bottom:12px;
        }
        .horizontal-card .card-body { flex:1; min-width:0; }
        .horizontal-card .card-body .tag { font-size:11px; display:inline-block; margin-bottom:4px; }
        .horizontal-card .card-body h4 { margin:0 0 2px; font-size:15px; }
        .horizontal-card .card-body .card-meta { font-size:12px; color:var(--ink-3); }
        .horizontal-card .card-actions { flex-shrink:0; }
        .btn-sm {
            padding:8px 16px; font-size:12px; font-weight:600;
            border-radius:var(--radius-md); cursor:pointer; white-space:nowrap;
            text-decoration:none; display:inline-block;
        }
        .btn-sm-outline {
            background:transparent; border:1px solid var(--border-md);
            color:var(--ink-2);
        }
        .btn-sm-outline:hover { border-color:var(--red); color:var(--red); }
        .club-icon {
            width:48px; height:48px; border-radius:50%;
            display:flex; align-items:center; justify-content:center;
            font-size:20px; flex-shrink:0;
        }
        .horizontal-card .tag { display:inline-block; }
        .club-role-badge {
            font-size:11px; font-weight:600; text-transform:uppercase;
            padding:4px 10px; border-radius:20px;
            background:var(--red-light); color:var(--red);
            flex-shrink:0;
        }
    </style>
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <h2 class="clubs-title">My Activities</h2>

        <div class="tab-bar">
            <a href="MyEvent.php?tab=events" class="<?php echo $activeTab === 'events' ? 'active' : ''; ?>">My Events</a>
            <a href="MyEvent.php?tab=clubs" class="<?php echo $activeTab === 'clubs' ? 'active' : ''; ?>">My Clubs</a>
        </div>

        <?php if ($activeTab === 'events'): ?>
            <?php if ($eventsResult && $eventsResult->num_rows > 0): ?>
                <?php while ($row = $eventsResult->fetch_assoc()): ?>
                    <div class="horizontal-card">
                        <div class="card-body">
                            <span class="tag tag-confirmed">Event Confirmed</span>
                            <?php if (!empty($row['clubName'])): ?>
                            <a href="ClubsDetails.php?id=<?php echo (int)($row['clubID'] ?? 0); ?>" class="no-deco"><span class="tag"><?php echo htmlspecialchars($row['clubName']); ?></span></a>
                            <?php endif; ?>
                            <h4><?php echo htmlspecialchars($row['eventTitle']); ?></h4>
                            <div class="card-meta">
                                <?php echo formatDateRange($row['eventDate'], $row['eventEndDate'] ?? null); ?> |
                                <?php echo date('h:iA', strtotime($row['eventTime'])); ?><?php if (!empty($row['eventEndTime'])): ?> — <?php echo date('h:iA', strtotime($row['eventEndTime'])); ?><?php endif; ?> |
                                <?php echo htmlspecialchars($row['venue']); ?>
                            </div>
                        </div>
                        <div class="card-actions">
                            <a href="DetailedEvent.php?id=<?php echo (int)$row['eventID']; ?>" class="btn-sm btn-sm-outline">Details →</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>You haven't registered for any events yet.</p>
                    <a href="StudentEvents.php">Browse Events</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>

        <?php if ($activeTab === 'clubs'): ?>
            <?php if ($clubsResult && $clubsResult->num_rows > 0): ?>
                <?php while ($club = $clubsResult->fetch_assoc()):
                    $clubName = htmlspecialchars($club['clubName']); ?>
                    <div class="horizontal-card">
                        <?php if (!empty($club['profilePic'])): ?>
                            <img src="<?php echo htmlspecialchars($club['profilePic']); ?>" style="width:48px;height:48px;border-radius:50%;object-fit:cover;flex-shrink:0;">
                        <?php else: ?>
                            <div class="club-icon" style="background:var(--red-light);">🏛️</div>
                        <?php endif; ?>
                        <div class="card-body">
                            <h4><?php echo htmlspecialchars($club['clubName']); ?></h4>
                            <div class="card-meta"><?php echo htmlspecialchars($club['clubEmail'] ?? ''); ?> · Joined <?php echo date('d M Y', strtotime($club['joined_at'])); ?></div>
                        </div>
                        <span class="club-role-badge"><?php echo htmlspecialchars(ucfirst($club['role'] ?? 'member')); ?></span>
                        <div class="card-actions">
                            <a href="ClubsDetails.php?id=<?php echo (int)$club['clubID']; ?>" class="btn-sm btn-sm-outline">Details →</a>
                        </div>
                    </div>
                <?php endwhile; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <p>You haven't joined any clubs yet.</p>
                    <a href="Clubs.php">Explore Clubs</a>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </main>
</body>
</html>
