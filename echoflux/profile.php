<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'includes/db.php';

$userId = $_SESSION['user_id'];

$stmt = $conn->prepare("SELECT firstname, lastname, balance, username, email, phone_number FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($firstname, $lastname, $balance, $username, $email, $phone_number);
$stmt->fetch();
$stmt->close();
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Echoflux</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/profile.css">
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">
                <a href="homepage.php"><img src="images/logo2.png" alt="Echoflux Logo"></a>
            </div>
            <ul class="nav-links">
                <li><a href="homepage.php">Home 🏠</a></li>
                <li><a href="tasks.php">Tasks</a></li>
                <li><a href="spin.php">Win points</a></li>
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
        <section class="profile-section">
            <div class="profile-card">
                <h2><?= htmlspecialchars($username) ?>'s Profile</h2><hr>
                <div class="profile-info">
                    <p><strong>Name:</strong> <?= htmlspecialchars($firstname) ?> <?= htmlspecialchars($lastname) ?></p>
                    <p><strong>Username:</strong> <?= htmlspecialchars($username) ?></p>
                    <p><strong>Email:</strong> <?= htmlspecialchars($email) ?></p>
                    <p><strong>Phone Number:</strong> <?= htmlspecialchars($phone_number) ?></p>
                    <p><strong>Balance:</strong> &#8358;<?= htmlspecialchars($balance) ?></p>
                </div>
                <h6>Kindly reach out to <a href="contact.php">support</a> if you need to change your details.</h4>
            </div>
        </section>
    </div>

    <script src="js/nav.js"></script>
</body>
</html>
