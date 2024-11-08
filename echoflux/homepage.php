<?php

// error_reporting(E_ALL);
// ini_set('display_errors', 1);

session_start();

// Set session timeout duration in seconds
$sessionTimeoutDuration = 1800; // 30 minutes

// Check if the user is logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Check if the session has expired
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > $sessionTimeoutDuration) {
    // Session has expired, destroy session and redirect to login
    session_unset(); // Unset $_SESSION variables
    session_destroy(); // Destroy the session
    header('Location: login.php');
    exit();
}

// Update last activity time
$_SESSION['last_activity'] = time();

require 'includes/db.php';

$userId = $_SESSION['user_id'];

// Check if the user is an admin
$stmt = $conn->prepare("SELECT admin FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($isAdmin);
$stmt->fetch();
$stmt->close();

$_SESSION['admin'] = (bool)$isAdmin;

$today = date('Y-m-d');

if ($conn) {
    // Check if the user has already checked in today
    $stmt = $conn->prepare("SELECT last_checkin_date, streak_count, temporary_balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($lastCheckinDate, $streakCount, $temporaryBalance);
    $stmt->fetch();
    $stmt->close();

    if ($lastCheckinDate != $today) {
        // User is checking in for the first time today
        if ($lastCheckinDate == date('Y-m-d', strtotime('-1 day'))) {
            // Continued streak
            $streakCount++;
        } else {
            // Streak broken
            $streakCount = 1;
            $temporaryBalance = 0;
        }

        // Add points to temporary balance
        $temporaryBalance += 1400; // Assuming 1 point per day

        // Check if the streak is 30 days
        if ($streakCount == 30) {
            // Add temporary balance to total points and reset streak count
            $stmt = $conn->prepare("UPDATE users SET points = points + ?, temporary_balance = 0, streak_count = 1, last_checkin_date = ? WHERE id = ?");
            $stmt->bind_param("isi", $temporaryBalance, $today, $userId);
        } else {
            // Update last check-in date, streak count, and temporary balance
            $stmt = $conn->prepare("UPDATE users SET last_checkin_date = ?, streak_count = ?, temporary_balance = ? WHERE id = ?");
            $stmt->bind_param("siii", $today, $streakCount, $temporaryBalance, $userId);
        }
        $stmt->execute();
        $stmt->close();

    }

    // Fetch updated user data
    $stmt = $conn->prepare("SELECT firstname, lastname, referral_code, points, temporary_balance, balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($firstname, $lastname, $referral_code, $points, $temporaryBalance, $balance);
    $stmt->fetch();
    $stmt->close();


    // Fetch referrals id..
    $stmt = $conn->prepare("SELECT COUNT(*) FROM referrals WHERE referrer_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($referralCount);
    $stmt->fetch();
    $stmt->close();


} else {
    echo "Failed to connect to the database.";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Homepage - Echoflux</title>
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/homepage.css">
</head>
<body>
   <header>
        <nav class="navbar">
          <div class="logo">
            <a href="homepage.php"><img src="images/logo2.png" alt="Echoflux Logo"></a>
          </div>

          <ul class="nav-links">
            <li><a href="homepage.php">Home</a></li>
            <li><a href="tasks.php">Tasks</a></li>
            <li><a href="spin.php">Win Points</a></li>
            <li><a href="skills.php">Digital Skills</a></li>
            <li><a href="sportprediction.php">Sports Prediction</a></li>
            <li><a href="profile.php">Profile</a></li>
          </ul>
          <div class="burger">
            <div class="line1"></div>
            <div class="line2"></div>
            <div class="line3"></div>
          </div>
        </nav>
    </header>

    <div class="container">
        <section class="welcome-section">
            <div class="">
                <h2>Welcome, <?= htmlspecialchars($firstname) ?>!</h2>
                <p>You're doing great! Keep up the good work.</p>
                <blockquote>"Success is not final; failure is not fatal: It is the courage to continue that counts." - Winston S. Churchill</blockquote>
                <h3>Points: <?= htmlspecialchars($points) ?> </h3>
                <h3>Balance: &#8358;<?= htmlspecialchars($balance) ?> </h3>
                <h3>Temporary Point: <?= htmlspecialchars($temporaryBalance) ?> </h3>
                <h3>Streak Count: <?= htmlspecialchars($streakCount) ?> </h3>

                <a href="convert.php" class="button">Convert </a>
                <a href="withdrawal.php" class="button">Withdraw </a>

                <p><a href="logout.php" class="button">Logout </a></p>

            </div>
            <div class="dashboard-img">
                <img src="images/dashboard.avif" alt="Echoflux dashboard image">
            </div>
        </section><br>

        <section class="dashboard">
            <div class="card">
                <h3>Notifications</h3>
                <?php
                $sql = "SELECT message FROM notifications ORDER BY created_at DESC";
                $result = $conn->query($sql);

                if ($result->num_rows > 0) {
                    echo "<div class='notifications'>";
                    while ($row = $result->fetch_assoc()) {
                        echo "<div class='notification'>" . $row['message'] . "</div>";
                    }
                    echo "</div>";
                } else {
                    echo "<div class='no-notifications'>No notifications at this time.</div>";
                  }
            ?>
            </div>
        </section>

        <section class="dashboard">
            <div class="card">
                <h3>Affiliate</h3>
                <p>Your referral link: <a href="https://echoflux.com.ng/signup.php?ref=<?= urlencode($referral_code) ?>" target="_blank">https://echoflux.com.ng/signup.php?ref=<?= htmlspecialchars($referral_code) ?></a></p>
                <p>Referral Code: <?= htmlspecialchars($referral_code) ?></p>
                <p>Total referrals: <?= htmlspecialchars($referralCount) ?> </p>
            </div>
            <div class="card">
                <h3>Your Streak Progress</h3>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: <?= min(($streakCount / 30) * 100, 100) ?>%;">
                        <?= htmlspecialchars($streakCount) ?>/30
                    </div>
                </div>
                <p>Keep it up! You're doing great.</p>
            </div>
            <div class="card">
                <h3>Temporary Points Progress</h3>
                <div class="progress-bar">
                    <div class="progress-bar-fill" style="width: <?= min(($temporaryBalance / 42000) * 100, 100) ?>%;">
                        <?= htmlspecialchars($temporaryBalance) ?>/42000
                    </div>
                </div>
                <p>You're closer to your next milestone!</p>
            </div>

        </section><br>



        <section class="dashboard">
          <div class="card">
            <h3>Announcements</h3>
            <p>Stay updated with the latest news and updates from EchoFlux. Check out the <a target="_blank" href="#">telegram group</a>.</p>
          </div>
        </section>

    </div>
    <script src="js/nav.js"></script>
</body>
</html>
