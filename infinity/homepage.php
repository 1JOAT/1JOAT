<?php
session_start();

// Define the session timeout duration (10 hours in seconds)
$timeout_duration = 10 * 60 * 60; // 10 hours

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header("Location: login.php");
    exit;
}

// Check if the last activity timestamp is set
if (isset($_SESSION['LAST_ACTIVITY'])) {
    // Calculate the session inactivity duration
    $inactive_duration = time() - $_SESSION['LAST_ACTIVITY'];

    // If the inactivity duration exceeds the timeout duration, destroy the session
    if ($inactive_duration > $timeout_duration) {
        session_unset();
        session_destroy();
        header("Location: login.php");
        exit;
    }
}

// Update the last activity timestamp
$_SESSION['LAST_ACTIVITY'] = time();

require 'includes/db.php';

// Fetch user data from the database
$user_id = $_SESSION['id'];
$sql = "SELECT username, balance FROM users WHERE id = ?";
if ($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->bind_result($username, $balance);
    $stmt->fetch();
    $stmt->close();
}

// Fetch notifications from the database
$sql = "SELECT title, description, link, DATE_FORMAT(date, '%b %d, %Y') as formatted_date FROM notifications ORDER BY date DESC";
if ($stmt = $conn->prepare($sql)) {
    $stmt->execute();
    $stmt->bind_result($title, $description, $link, $formatted_date);
    $notifications = [];
    while ($stmt->fetch()) {
        $notifications[] = [
            'title' => $title,
            'description' => $description,
            'link' => $link,
            'date' => $formatted_date
        ];
    }
    $stmt->close();
}



?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <title>Home - Infinity Forex Academy</title>
    <link rel="stylesheet" href="styles/homepage.css">
    <link rel="shortcut icon" href="images/fav.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

    <nav class="sidebar">
        <div class="logo">
           <a href="admin.php"><img src="images/logo.png" alt="Infinity Forex Academy"></a> 
        </div>
        <ul>
            <li><a href="homepage.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="resources.php"><i class="fas fa-folder"></i> Resources</a></li>
            <li><a href="topup.php"><i class="fas fa-plus-circle"></i> Top Up</a></li>
            <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
            <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
    </nav>

    <div class="floating-icons" id="floating-icons">

        <a href="profile.php" class="floating-icon profile-icon">
            <i class="fas fa-user-circle"></i>
        </a>
    </div>

    <main>
            <!-- Welcome Section -->
            <section class="welcome">
                <h2>Welcome, <span><?php echo htmlspecialchars($username); ?>!</span></h2>
                <p>We’re glad to have you back. Ready to enhance your forex trading skills today?</p>
                <div class="balance-info">
                    <strong class="balance">  Your Balance: <?php echo htmlspecialchars($balance); ?> USD
                    </strong>
                </div>
            </section><br>

            <!-- Notifications Section -->
            <section class="notifications">
                <h3>Notifications</h3>
                <ul>
                    <?php foreach ($notifications as $notification): ?>
                        <li>
                            <strong><?php echo htmlspecialchars($notification['title']); ?></strong> 
                            <br>
                            <?php echo htmlspecialchars($notification['description']); ?>
                            <?php if ($notification['link']): ?>
                                <br><a href="<?php echo htmlspecialchars($notification['link']); ?>" class="btn">Read More</a>
                            <?php endif; ?>
                            <br><small><?php echo htmlspecialchars($notification['date']); ?></small>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>



            <section class="signals-subscription">
                <div class="container">
                    <h2>Subscribe to Our Trading Signals</h2>
                    <p>Join our community of traders and receive expert trading signals daily. Get access to <strong>premium signals</strong> for advanced, real-time insights, or unlock <strong>free signals</strong> after completing our trading classes.</p>

                    <div class="subscription-options">
                        <div class="signal-box premium-signals">
                            <h3>Premium Signals</h3>
                            <p>Unlock premium, real-time signals with deeper insights for better accuracy. Subscribe now for exclusive access to our most reliable trading advice.</p>
                            <a href="resources.php" class="btn">Subscribe to Premium</a>
                        </div>
                        <div class="signal-box free-signals">
                            <h3>Free Signals (After Classes)</h3>
                            <p>Access our free daily signals after completing any of our trading classes. Learn and trade with confidence.</p>
                            <a href="resources.php" class="btn">Join Our Classes</a>
                        </div>
                    </div>
                </div>
            </section>



            <section class="events">
                <h3>Upcoming Webinars</h3>
                <ul>
                    <li>
                        <strong>Forex for Beginners</strong> <br> 
                        November 15, 2024 | 4 PM UTC <br><br>
                        <!-- <a href="webinar.php" class="btn">Register</a> -->
                        
                    </li>
                    <li>
                        <strong>Trading During Market Volatility</strong> <br> 
                        December 20, 2024 | 6 PM UTC <br><br>
                        <!-- <a href="webinar.php" class="btn">Register</a> -->
                    </li>
                </ul>
            </section>

            <section class="one-on-one-classes">
                <div class="container">
                    <h2>One-on-One Classes</h2>
                    <p>Get personalized attention and direct mentoring with our one-on-one classes. Tailored to your skill level and pace, this is the ultimate way to master trading.</p>
                    <a href="resources.php" class="cta-btn">Book a One-on-One Class</a>
                </div>
            </section>

            <section class="upcoming-classes">
                <div class="container">
                    <h2>Upcoming Classes</h2>
                    <p>Join our next batch of classes to gain access to exclusive free signals and a deeper understanding of trading strategies.</p>
                    <a href="resources.php" class="cta-btn">See Upcoming Classes</a>
                </div>
            </section>




            <section class="featured-courses">
                <h3>Featured Courses</h3>
                <ul>
                    <li>
                        <strong>Beginners guide to forex trading.</strong> <br><br>
                        $5.99 <br><br>
                        <a href="#"  class="btn">Coming soon</a>
                    </li>
                    <!-- <li>
                        <strong>The Candlestick Bible.</strong> <br><br>
                        $0.00 <br><br>
                        <a href="courses.php" class="btn">Buy Now</a>
                    </li>
                    <li>
                        <strong>Fundamental terms in Forex.</strong> <br><br>
                        $0.00 <br><br>
                        <a href="courses.php" class="btn">Buy Now</a>
                    </li> -->
                    <!-- <li>
                        <strong>Holy Grail</strong> <br><br>
                        $49.99 <br><br>
                        <a href="courses.php" class="btn">Buy Now</a>
                    </li> -->
                    <!-- <li>
                        <strong>Trading Psychology by Mark Douhglas.</strong> <br><br>
                        $0.00<br><br>
                        <a href="courses.php" class="btn">Buy Now</a>
                    </li> -->
                    <!-- <li>
                        <strong>Prop Firm Risk Management</strong> <br><br>
                        $4.99 <br><br>
                        <a href="courses.php" class="btn">Buy Now</a>
                    </li> -->
x
                </ul>
            </section>

     </main>



    <nav class="bottom-navbar">
        <ul>
            <li><a href="homepage.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="resources.php"><i class="fas fa-folder"></i> Resources</a></li>
            <!-- <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Updates</a></li> -->
            <li><a href="topup.php"><i class="fas fa-plus-circle"></i> Top Up</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
   </nav>

   <script src="script.js"></script>

</body>
</html>