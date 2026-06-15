<?php
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'moderator') {
        header("Location: ModeratorLogin.php");
        exit();
    }
    session_write_close();

    $currentPage = 'clubs';
    $tab = isset($_GET['tab']) ? $_GET['tab'] : 'pending';
    $clubs = [];
    $counts = ['pending' => 0, 'approved' => 0];

    try {
        $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'pending'");
        $counts['pending'] = $r ? $r->fetch_assoc()['c'] : 0;

        $r = $conn->query("SELECT COUNT(*) AS c FROM admins WHERE status = 'active'");
        $counts['approved'] = $r ? $r->fetch_assoc()['c'] : 0;

        if ($tab === 'pending') {
            $result = $conn->query("SELECT * FROM admins WHERE status = 'pending' ORDER BY created_at DESC");
        } else {
            $result = $conn->query("SELECT * FROM admins WHERE status = 'active' ORDER BY created_at DESC");
        }
        if ($result) { $clubs = $result->fetch_all(MYSQLI_ASSOC); }
    } catch (mysqli_sql_exception $e) {
        error_log('ModeratorClubs DB error: ' . $e->getMessage());
    }

    $pendingClubs = $counts['pending'];

    $pendingEvents = 0;
    $r = $conn->query("SELECT COUNT(*) AS c FROM events WHERE status = 'pending'");
    if ($r) { $pendingEvents = $r->fetch_assoc()['c']; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Moderator - Clubs</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>

    <?php include 'ModeratorNavBar.php'; ?>

    <main class="container">

        <div class="mod-page-header">
            <div>
                <h2 class="mod-title">Clubs</h2>
                <p class="mod-sub">Manage club registrations across campus.</p>
            </div>
        </div>

        <div class="mod-tab-nav">
            <a href="ModeratorClubs.php?tab=pending" class="mod-tab-link <?php echo $tab === 'pending' ? 'active' : ''; ?>">
                Pending <?php if ($counts['pending'] > 0): ?><span class="tab-count"><?php echo $counts['pending']; ?></span><?php endif; ?>
            </a>
            <a href="ModeratorClubs.php?tab=approved" class="mod-tab-link <?php echo $tab === 'approved' ? 'active' : ''; ?>">
                Clubs <?php if ($counts['approved'] > 0): ?><span class="tab-count"><?php echo $counts['approved']; ?></span><?php endif; ?>
            </a>
        </div>

        <section class="event-grid">
            <?php if (!empty($clubs)): ?>
                <?php foreach ($clubs as $club): ?>
                    <article class="event-card mod-card">
                        <div class="card-stripe" data-color="<?php echo $tab === 'pending' ? 'amber' : 'green'; ?>"></div>
                        <div class="card-body">

                            <?php if ($tab === 'pending'): ?>
                                <span class="mod-pending-badge">
                                    <span class="mod-badge-dot"></span>
                                    Pending review
                                </span>
                            <?php else: ?>
                                <span class="mod-status-tag approved">Approved</span>
                            <?php endif; ?>

                            <h3><?php echo htmlspecialchars($club['clubName']); ?></h3>

                            <div class="event-meta">
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="18" rx="2"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="16" y1="2" x2="16" y2="6"/></svg>
                                    <span><strong>Admin ID:</strong> <?php echo htmlspecialchars($club['adminID']); ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                                    <span><strong>Admin:</strong> <?php echo htmlspecialchars($club['name']); ?></span>
                                </div>
                                <div class="meta-row">
                                    <svg viewBox="0 0 24 24"><path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/></svg>
                                    <span><strong>Email:</strong> <?php echo htmlspecialchars($club['clubEmail']); ?></span>
                                </div>
                            </div>

                            <div class="card-divider"></div>

                            <a href="ClubDetailsModerator.php?id=<?php echo urlencode($club['adminID']); ?>" class="btn-mod-details">Details</a>
                        </div>
                    </article>
                <?php endforeach; ?>
            <?php else: ?>
                <div class="event-empty-box">
                    <div class="empty-icon">
                        <svg viewBox="0 0 24 24"><path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87M16 3.13a4 4 0 010 7.75"/></svg>
                    </div>
                    <p>No <?php echo $tab === 'pending' ? 'pending' : 'approved'; ?> clubs</p>
                    <p class="empty-subtext">There are no clubs in this category right now.</p>
                </div>
            <?php endif; ?>
        </section>

    </main>
</body>
</html>
