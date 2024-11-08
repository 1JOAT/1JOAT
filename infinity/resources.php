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

function getUserFirstName($userId, $conn) {
    $stmt = $conn->prepare("SELECT first_name FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($firstName);
    $stmt->fetch();
    $stmt->close();
    return $firstName;
}

function getUserBalance($userId, $conn) {
    $stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $stmt->bind_result($balance);
    $stmt->fetch();
    $stmt->close();
    return $balance;
}

// Check if free class registration is open
$currentDate = date('Y-m-d');
$registrationEndDate = date('Y-m-28', strtotime('+1 month')); 
$registrationOpen = ($currentDate < $registrationEndDate);

// Fetch user's first name and balance 
$first_name = getUserFirstName($_SESSION['id'], $conn); 
$balance = getUserBalance($_SESSION['id'], $conn);

// Handle form submissions
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $userId = $_SESSION['id'];

    // Check for signal purchase
    if (isset($_POST['plan'])) {
        $plan = $_POST['plan'];

        // Plan prices and durations
        $plans = [
            'monthly' => ['price' => 10, 'duration' => '1 MONTH'],
            '6-months' => ['price' => 50, 'duration' => '6 MONTH'],
            'yearly' => ['price' => 100, 'duration' => '1 YEAR'],
            'lifetime' => ['price' => 200, 'duration' => '100 YEAR'] 
        ];

        if (isset($plans[$plan]) && $balance >= $plans[$plan]['price']) {
            // 2. Update user's balance
            $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->bind_param("ii", $plans[$plan]['price'], $userId);
            $stmt->execute();
            $stmt->close();

            // 3. Check for existing active subscription
            $stmt = $conn->prepare("SELECT id, end_date FROM signal_subscriptions 
                                    WHERE user_id = ? AND end_date > NOW()");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->store_result();

            if ($stmt->num_rows > 0) {
                // Existing subscription found, extend it
                $stmt->bind_result($subscriptionId, $currentEndDate);
                $stmt->fetch();

                // Calculate new end_date 
                $newEndDate = date('Y-m-d H:i:s', strtotime($currentEndDate . ' + ' . $plans[$plan]['duration']));

                $updateStmt = $conn->prepare("UPDATE signal_subscriptions SET end_date = ? WHERE id = ?");
                $updateStmt->bind_param("si", $newEndDate, $subscriptionId);
                $updateStmt->execute();
                $updateStmt->close();
            } else {
                // No existing subscription, create a new one
                $startDate = date('Y-m-d H:i:s'); 
                $endDate = date('Y-m-d H:i:s', strtotime($startDate . ' + ' . $plans[$plan]['duration']));

                $stmt = $conn->prepare("INSERT INTO signal_subscriptions (user_id, plan, start_date, end_date) 
                                        VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isss", $userId, $plan, $startDate, $endDate);
                $stmt->execute();
                $stmt->close();
            }

            // 4. Update balance display (optional)
            $balance -= $plans[$plan]['price']; 

            // 5. Set success message
            $status = 'success';
            $message = 'Signal subscription updated successfully!';
        } else {
            // 6. Set appropriate error message
            $status = 'error';
            $message = isset($plans[$plan]) ? 'Insufficient balance.' : 'Invalid signal plan.';
        }
    } 
    // Handle one-on-one class registration
    elseif (isset($_POST['register_class'])) {
        $userId = $_SESSION['id'];
        $classPrice = 99.99;

        if ($balance >= $classPrice) {
            // 2. Update user's balance
            $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
            $stmt->bind_param("di", $classPrice, $userId);
            $stmt->execute();
            $stmt->close();

            // 3. Record the one-on-one class registration
            $stmt = $conn->prepare("INSERT INTO one_on_one_classes (user_id) VALUES (?)");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $stmt->close();

            // 4. Update balance display (optional)
            $balance -= $classPrice; 

            // 5. Set success message
            $status = 'success';
            $message = 'Registered for one-on-one class successfully! You would be contacted via Email, Whatsapp and Telegram.';
        } else {
            // 6. Set error message
            $status = 'error';
            $message = 'Insufficient balance to register for one-on-one class.';
        }
    } 

    // Handle free class registration
    elseif (isset($_POST['register_free_class'])) {
        // Check if registration is open
        if ($registrationOpen) {
            $userId = $_SESSION['id'];

            // Check if the user is already registered for this month
            $stmt = $conn->prepare("SELECT COUNT(*) FROM free_class_registrations 
                                    WHERE user_id = ? AND MONTH(registration_date) = ?");
            $stmt->bind_param("ii", $userId, $currentMonth);
            $stmt->execute();
            $stmt->bind_result($isRegistered);
            $stmt->fetch();
            $stmt->close();

            if ($isRegistered == 0) {
                // Record the free class registration
                $stmt = $conn->prepare("INSERT INTO free_class_registrations (user_id) VALUES (?)");
                $stmt->bind_param("i", $userId);
                $stmt->execute();
                $stmt->close();

                $status = 'success';
                $message = 'Registered for free class successfully! <a href="https://chat.whatsapp.com/DjhbvB9sI3zEIQjbs6J3Av" target="_blank">Join the class here</a>.'; 
            } else {
                $status = 'error'; 
                $message = 'You are already registered for this month\'s free class.'; 
            }
        } else {
            $status = 'error';
            $message = 'Free class registration is currently closed. It will reopen on the 28th of next month.';
        }
    } else {
        // Handle the case where no valid action is recognized
        $status = 'error';
        $message = 'Invalid action. Please try again.';
    }

}

// Fetch one-on-one class registration status
$sql = "SELECT COUNT(*) FROM one_on_one_classes WHERE user_id = ?";
if($stmt = $conn->prepare($sql)) {
    $stmt->bind_param("i", $param_id);
    $param_id = $_SESSION["id"];

    if($stmt->execute()) {
        $stmt->bind_result($isRegisteredForClass);
        $stmt->fetch();
    } else {
        echo "Error: Could not retrieve registration status.";
        exit;
    }

    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Resources - Infinity Forex Academy</title>
    <link rel="stylesheet" href="styles/homepage.css">
    <link rel="stylesheet" href="styles/resources.css">
    <link rel="shortcut icon" href="images/fav.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
</head>
<body>

    <nav class="sidebar">
        <div class="logo">
            <img src="images/logo.png" alt="Infinity Forex Academy">
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

    <section class="welcome">
        <h2>Welcome to the resources section, <span><?php echo htmlspecialchars($first_name); ?>!</span></h2>
        <div class="balance-info">
            <strong class="balance"> Your Balance: <?php echo htmlspecialchars($balance); ?> USD </strong>
        </div>
        <p>Welcome to the Forex Academy Resources Section! Here, you can buy courses, register for one-on-one classes, subscribe to signals, and learn more about our services. If you need any help, our support team is always ready to assist you. Happy Trading!</p>
    </section><br>





    <?php if (isset($message)): ?>
        <div id="message" class="message <?php echo $status; ?>"><?php echo $message; ?></div>
    <?php endif; ?>

    <h2 class="h2">Free Classes</h2>
    <p class="p">Monthly classes, registration closes on the 28th of each month. After the conslusion of each classes, you would be added to the inner circle where you would be given daily signals.</p>

    <?php if ($registrationOpen): ?>
        <?php if ($isRegisteredForClass == 0): ?>
            <form class="form" method="post" action="">
                <button type="submit" name="register_free_class">Register Now</button>
            </form>
        <?php else: ?>
            <p>You are already registered for this month's free class.</p>
        <?php endif; ?>
    <?php else: ?>
        <p>Free class registration is currently closed. It will reopen on the 1st of next month.</p>
    <?php endif; ?>

    <hr>

    <h2 class="h2">VIP Signals</h2>

    <form method="post" class="signal-options-grid" action=""> 
        <div class="signal-option">
            <h3>Monthly - $10</h3>
            <button type="submit" name="plan" value="monthly">Buy Now</button>
        </div>

        <div class="signal-option">
            <h3>6 Months - $50</h3>
            <button type="submit" name="plan" value="6-months">Buy Now</button>
        </div>

        <div class="signal-option">
            <h3>Yearly - $100</h3>
            <button type="submit" name="plan" value="yearly">Buy Now</button>
        </div>

        <div class="signal-option">
            <h3>Lifetime - $200</h3>
            <button type="submit" name="plan" value="lifetime">Buy Now</button>
        </div>
    </form>
    <hr>

    <h2 class="h2">One-on-One Classes</h2>
    <p class="p"><strong> Price: $99.99</strong></p>
    <p class="p">Unlock personalized trading insights with our One-on-One subscription. Get tailored strategies and direct support to level up your trading journey. Registering for this class gives you 6 months vip signals to welcome you to the trading environment.You also get a professional certificate from the Academy.</p>
    <form class="form" method="post" action="">
        <button type="submit" name="register_class">Register Now</button>
    </form>





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

<?php
     $conn->close(); 
?>
