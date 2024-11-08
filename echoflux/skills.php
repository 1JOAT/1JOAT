<?php
session_start();

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'includes/db.php';

$userId = $_SESSION['user_id'];

// Fetch user points
$stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($userPoints);
$stmt->fetch();
$stmt->close();

// Handle skill registration
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['register_skill'])) {
    $skillId = $_POST['skill_id'];

    // Fetch skill cost
    $stmt = $conn->prepare("SELECT cost FROM skills WHERE id = ?");
    $stmt->bind_param("i", $skillId);
    $stmt->execute();
    $stmt->bind_result($skillCost);
    $stmt->fetch();
    $stmt->close();

    if ($userPoints >= $skillCost) {
        // Deduct points from user
        $newPoints = $userPoints - $skillCost;
        $stmt = $conn->prepare("UPDATE users SET points = ? WHERE id = ?");
        $stmt->bind_param("ii", $newPoints, $userId);
        $stmt->execute();
        $stmt->close();

        // Record user skill registration
        $stmt = $conn->prepare("INSERT INTO user_skills (user_id, skill_id) VALUES (?, ?)");
        $stmt->bind_param("ii", $userId, $skillId);
        $stmt->execute();
        $stmt->close();

        // Fetch user details
        $stmt = $conn->prepare("SELECT firstname, lastname, email, phone_number FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($firstname, $lastname, $email, $phone_number);
        $stmt->fetch();
        $stmt->close();

        // Log to admin dashboard
        $logMessage = "User $firstname $lastname ($email, $phone_number) registered for skill ID $skillId.";
        file_put_contents('admin_notification.log', $logMessage . PHP_EOL, FILE_APPEND);

        // Redirect to avoid resubmission
        header('Location: skills.php');
        exit();
    } else {
        $errorMessage = "Insufficient points to register for this skill.";
    }
}

// Fetch skills
$stmt = $conn->prepare("SELECT id, name, cost FROM skills");
$stmt->execute();
$result = $stmt->get_result();
$skills = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user's registered skills
$stmt = $conn->prepare("SELECT s.name, s.cost, us.registered_at FROM user_skills us JOIN skills s ON us.skill_id = s.id WHERE us.user_id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$userSkills = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Digital Skills - Echoflux</title>
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/skills.css">
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
                <li><a href="spin.php">Spin and Win</a></li>
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
        <h2 style="color: #0c11b4;">Echoflux digital 1.0 starts 1st september 2024</h2><br>
        <section class="user-points-section">
            <h2>Your Points Balance: <?= htmlspecialchars($userPoints) ?> Points</h2>
        </section>
        <h4>Want to Know more about this skills? click <a href="skills.html">here.</a></h4>
        <section class="skills-section">
            <h2>Available Skills</h2>
            <?php if (isset($errorMessage)): ?>
                <p class="error"><?= htmlspecialchars($errorMessage) ?></p>
            <?php endif; ?>
            <div class="skills-list">
                <?php foreach ($skills as $skill): ?>
                    <div class="skill-card">
                        <h3><?= htmlspecialchars($skill['name']) ?></h3>
                        <p>Cost: <?= htmlspecialchars($skill['cost']) ?> points</p>
                        <form action="skills.php" method="post">
                            <input type="hidden" name="skill_id" value="<?= htmlspecialchars($skill['id']) ?>">
                            <button type="submit" name="register_skill" class="button">Register</button>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="history-section">
            <h2>Your Registered Skills</h2>
            <div class="history-list">
                <?php foreach ($userSkills as $userSkill): ?>
                    <div class="history-card">
                        <h3><?= htmlspecialchars($userSkill['name']) ?></h3>
                        <p>Cost: <?= htmlspecialchars($userSkill['cost']) ?> points</p>
                        <p>Registered on: <?= htmlspecialchars($userSkill['registered_at']) ?></p>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>

    <script src="js/nav.js"></script>
</body>
</html>
