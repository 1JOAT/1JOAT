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

    require_once 'includes/db.php';



    // Fetch the user's details from the database
    $sql = "SELECT first_name, last_name, username, phone_number, email FROM users WHERE id = ?";

    // Fetch active signal subscriptions
    $sqli = "SELECT plan, start_date, end_date FROM signal_subscriptions WHERE user_id = ? AND end_date > NOW()";

    if($stmt = $conn->prepare($sql)){
        // Bind the session user ID to the query
        $stmt->bind_param("i", $param_id);
        $param_id = $_SESSION["id"];
        
        // Execute the query
        if($stmt->execute()){
            // Store the result
            $stmt->store_result();

            // Check if the user exists in the database
            if($stmt->num_rows == 1){
                // Bind the result to variables
                $stmt->bind_result($first_name, $last_name, $username, $phone_number, $email);
                $stmt->fetch();
            } else {
                // If user not found, display an error
                echo "Error: User not found.";
                exit;
            }
        } else {
            echo "Error: Could not retrieve profile.";
            exit;
        }

        $stmt->close();
    }
    if($stmt = $conn->prepare($sqli)) {
        $stmt->bind_param("i", $param_id);
        $param_id = $_SESSION["id"];

        if($stmt->execute()) {
            $result = $stmt->get_result();
            $subscriptions = $result->fetch_all(MYSQLI_ASSOC); 
        } else {
            echo "Error: Could not retrieve subscriptions.";
            exit;
        }
        $stmt->close();

    }

    // Close connection
    $conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title> Profile - Infinity  Forex Academy </title>
    <link rel="stylesheet" href="styles/homepage.css">
    <link rel="stylesheet" href="styles/profile.css">
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
                <!-- <li><a href="announcements.php"><i class="fas fa-bullhorn"></i> Announcements</a></li> -->
                <li><a href="topup.php"><i class="fas fa-plus-circle"></i> Top Up</a></li>
                <li><a href="profile.php"><i class="fas fa-user-circle"></i> Profile</a></li>
                <li class="logout"><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
            </ul>
    </nav>


    <div class="profile-container">
    <h2>Your Profile</h2>
    <table>
        <tr>
            <th>First Name</th>
            <td><?php echo htmlspecialchars($first_name); ?></td>
        </tr>
        <tr>
            <th>Last Name</th>
            <td><?php echo htmlspecialchars($last_name); ?></td>
        </tr>
        <tr>
            <th>Username</th>
            <td><?php echo htmlspecialchars($username); ?></td>
        </tr>
        <tr>
            <th>Phone Number</th>
            <td><?php echo htmlspecialchars($phone_number); ?></td>
        </tr>
        <tr>
            <th>Email</th>
            <td><?php echo htmlspecialchars($email); ?></td>
        </tr>
    </table>
    <div class="support-message">
    <p>Need to update your details? Kindly email our support at <a href="mailto:support@infinityfa.com.ng">support@infinityfa.com.ng</a> for assistance.</p>
    </div>


    <h2>Active Signal Subscriptions</h2>
    <?php if (!empty($subscriptions)): ?>
        <table>
            <tr>
                <th>Plan</th>
                <th>Start Date</th>
                <th>End Date</th>
            </tr>
            <?php foreach ($subscriptions as $subscription): ?>
                <tr>
                    <td><?php echo htmlspecialchars($subscription['plan']); ?></td>
                    <td><?php echo htmlspecialchars($subscription['start_date']); ?></td>
                    <td><?php echo htmlspecialchars($subscription['end_date']); ?></td>
                </tr>
            <?php endforeach; ?>
        </table>


    <?php else: ?>
        <p>You have no active signal subscriptions.</p>
    <?php endif; ?>



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