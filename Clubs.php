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

    try {
        $query = "SELECT * FROM clubs ORDER BY clubName ASC";
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

        <!-- Search bar (reuses existing search-wrap style) -->
        <div class="search-wrap" style="margin-bottom:22px;">
            <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/></svg>
            <input type="text" id="clubSearch" class="search-bar" placeholder="Search clubs by name or category...">
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
                <article class="club-card"
                        data-name="<?php echo strtolower($name); ?>"
                        data-cat="<?php echo strtolower($cat); ?>">

                    <div class="club-stripe<?php echo $color ? ' ' . $color : ''; ?>"></div>

                    <div class="club-avatar-wrap">
                        <div class="club-avatar<?php echo $color ? ' ' . $color : ''; ?>"><?php echo $emoji; ?></div>
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

        searchInput.addEventListener('input', function () {
            const q = this.value.toLowerCase().trim();
            let visible = 0;
            cards.forEach(card => {
                const match = !q
                    || card.dataset.name.includes(q)
                    || card.dataset.cat.includes(q);
                card.style.display = match ? '' : 'none';
                if (match) visible++;
            });
            clubCount.textContent = visible + ' club' + (visible !== 1 ? 's' : '') + ' found';
        });
    </script>
</body>
</html>