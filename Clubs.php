<!--Clubs.php-->
<!--User explore about all the clubs that registered-->
<?php
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
    
    session_start();
    require_once 'db_connect.php';

    if (!isset($_SESSION['student_id']) || $_SESSION['role'] !== 'student') {
        header("Location: StudentLogin.php");
        exit();
    }
    session_write_close();

    $studentID = $_SESSION['student_id'];

    // Get club names the student is a member of
    $memberClubNames = [];
    try {
        $memberStmt = $conn->prepare("SELECT a.clubName FROM club_members cm JOIN admins a ON cm.adminID = a.adminID WHERE cm.studentID = ?");
        $memberStmt->bind_param("s", $studentID);
        $memberStmt->execute();
        $memberResult = $memberStmt->get_result();
        while ($m = $memberResult->fetch_assoc()) {
            $memberClubNames[] = strtolower(trim($m['clubName']));
        }
        $memberStmt->close();
    } catch (mysqli_sql_exception $e) {
        error_log('Clubs.php membership query error: ' . $e->getMessage());
    }

    try {
        $query = "
            SELECT c.*
            FROM clubs c
            INNER JOIN (
                SELECT adminID, MAX(clubID) AS latestClubID
                FROM clubs
                WHERE adminID IS NOT NULL AND adminID <> ''
                GROUP BY adminID
            ) latest ON latest.latestClubID = c.clubID
            ORDER BY c.clubName ASC
        ";
        $result = $conn->query($query);
    } catch (mysqli_sql_exception $e) {
        error_log('Clubs.php DB query error: ' . $e->getMessage());
        $result = false;
    }

    // Colour palette — cycles per card
    $colors  = ['', 'green', 'blue', 'amber', 'purple'];
    // Emoji fallback per colour slot
    $emojis  = ['🏆', '🌱', '💻', '🎨', '🎤'];
    $colorIndex = 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Campus Clubs</title>
    <link rel="stylesheet" type="text/css" href="Style.css">
</head>
<body>
    <?php include 'StudentNavbar.php'; ?>

    <main class="container">
        <h2 class="clubs-title">Explore Campus Clubs</h2>

        <!-- Toolbar: search + filter -->
        <div class="search-toolbar">
            <div class="search-wrap">
                <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
                <input type="text" id="clubSearch" class="search-bar" placeholder="Search clubs by name or category...">
            </div>
            <div class="reg-filter-group">
                <button class="filter-chip active" data-memberfilter="all">All</button>
                <button class="filter-chip" data-memberfilter="member">Member</button>
                <button class="filter-chip" data-memberfilter="nonmember">Non-member</button>
            </div>
        </div>

        <p class="section-label" id="clubCount">
            <?php
                $total = ($result) ? $result->num_rows : 0;
                echo $total . ' club' . ($total !== 1 ? 's' : '') . ' found';
            ?>
        </p>

        <section class="club-grid" id="clubGrid">
            <?php
                if ($result && $result->num_rows > 0) {
                    while ($row = $result->fetch_assoc()) {
                        $color = $colors[$colorIndex % count($colors)];
                        $emoji = $emojis[$colorIndex % count($emojis)];
                        $colorIndex++;
                        $desc = htmlspecialchars($row['description'] ?? 'No description available.');
                        $name = htmlspecialchars($row['clubName']);
                        // Category — use a 'category' column if it exists, else fall back to 'Club'
                        $cat  = htmlspecialchars($row['category'] ?? 'Club');
                        // Member count if column exists
                        $members = isset($row['memberCount']) ? (int)$row['memberCount'] : null;
            ?>
                <?php $isMember = in_array(strtolower(trim($name)), $memberClubNames); ?>
                <article class="club-card"
                        data-name="<?php echo strtolower($name); ?>"
                        data-cat="<?php echo strtolower($cat); ?>"
                        data-member="<?php echo $isMember ? '1' : '0'; ?>">

                    <div class="club-stripe<?php echo $color ? ' ' . $color : ''; ?>"></div>

                    <div class="club-avatar-wrap">
                        <?php if (!empty($row['profilePic'])): ?>
                            <img src="<?php echo htmlspecialchars($row['profilePic']); ?>" class="club-avatar-img" alt="<?php echo $name; ?>">
                        <?php else: ?>
                            <div class="club-avatar<?php echo $color ? ' ' . $color : ''; ?>"><?php echo $emoji; ?></div>
                        <?php endif; ?>
                        <div class="club-avatar-info">
                            <h3><?php echo $name; ?></h3>
                            <span class="club-category<?php echo $color ? ' ' . $color : ''; ?>"><?php echo $cat; ?></span>
                        </div>
                    </div>

                    <div class="club-body">
                        <p class="club-desc-text"><?php echo $desc; ?></p>

                        <?php if ($members !== null): ?>
                        <div class="club-meta-row">
                            <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/></svg>
                            <?php echo $members; ?> members
                        </div>
                        <?php endif; ?>

                        <a href="ClubsDetails.php?id=<?php echo (int)$row['clubID']; ?>" class="btn-club-view">View Club →</a>
                    </div>
                </article>
            <?php
                    }
                } else {
                    echo "
                    <div class='clubs-empty'>
                        <h3>No Clubs Registered Yet</h3>
                        <p>There are currently no active clubs listed on our portal. Check back later!</p>
                    </div>";
                }
            ?>
        </section>
    </main>

    <script>
        const searchInput = document.getElementById('clubSearch');
        const clubCount   = document.getElementById('clubCount');
        const cards       = document.querySelectorAll('.club-card');

        function applyClubFilters() {
            const q = searchInput.value.toLowerCase().trim();
            const memberFilter = document.querySelector('.reg-filter-group .filter-chip.active');
            const mVal = memberFilter ? memberFilter.dataset.memberfilter : 'all';
            let visible = 0;
            cards.forEach(card => {
                let show = true;
                if (q && !card.dataset.name.includes(q) && !card.dataset.cat.includes(q)) show = false;
                if (mVal === 'member' && card.dataset.member !== '1') show = false;
                if (mVal === 'nonmember' && card.dataset.member !== '0') show = false;
                card.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            clubCount.textContent = visible + ' club' + (visible !== 1 ? 's' : '') + ' found';
        }

        searchInput.addEventListener('input', applyClubFilters);

        document.querySelectorAll('.reg-filter-group .filter-chip').forEach(btn => {
            btn.addEventListener('click', function () {
                document.querySelectorAll('.reg-filter-group .filter-chip').forEach(b => b.classList.remove('active'));
                this.classList.add('active');
                applyClubFilters();
            });
        });
    </script>
</body>
</html>
