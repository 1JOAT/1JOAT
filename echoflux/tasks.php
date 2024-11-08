<?php
session_start();
// error_reporting(E_ALL);
// ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'includes/db.php';

$userId = $_SESSION['user_id'];

// Fetch tasks from the database
$stmt = $conn->prepare("SELECT id, title, description, points, link, completed FROM tasks");
$stmt->execute();
$result = $stmt->get_result();
$tasks = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch completed tasks for the user
$stmt = $conn->prepare("SELECT task_id FROM user_tasks WHERE user_id = ? AND completed = 1");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$completedTasks = $result->fetch_all(MYSQLI_ASSOC);
$completedTaskIds = array_column($completedTasks, 'task_id');
$stmt->close();

// Handle task link visit
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['visit_task'])) {
    $taskId = $_POST['task_id'];
    $_SESSION['visited_tasks'][$taskId] = true;
    header('Location: ' . $_POST['task_link']);
    exit();
}

// Handle task completion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['complete_task'])) {
    $taskId = $_POST['task_id'];

    if (!isset($_SESSION['visited_tasks'][$taskId])) {
        $_SESSION['message'] = "You must visit the task link first before marking it as completed.";
        header('Location: tasks.php');
        exit();
    }

    // Check if the task is already completed by the user
    $stmt = $conn->prepare("SELECT completed FROM user_tasks WHERE user_id = ? AND task_id = ?");
    $stmt->bind_param("ii", $userId, $taskId);
    $stmt->execute();
    $stmt->bind_result($completed);
    $stmt->fetch();
    $stmt->close();

    if (!$completed) {
        // Mark task as completed and credit points
        $stmt = $conn->prepare("INSERT INTO user_tasks (user_id, task_id, completed) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $userId, $taskId);
        if (!$stmt->execute()) {
            die("Error inserting user_tasks: " . $stmt->error);
        }
        $stmt->close();

        // Fetch task points
        $stmt = $conn->prepare("SELECT points FROM tasks WHERE id = ?");
        $stmt->bind_param("i", $taskId);
        if (!$stmt->execute()) {
            die("Error fetching task points: " . $stmt->error);
        }
        $stmt->bind_result($points);
        $stmt->fetch();
        $stmt->close();

        // Update user points
        $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
        $stmt->bind_param("ii", $points, $userId);
        if (!$stmt->execute()) {
            die("Error updating user points: " . $stmt->error);
        }
        $stmt->close();

        $_SESSION['message'] = "Task completed successfully!";
    } else {
        $_SESSION['message'] = "You have already completed this task.";
    }

    header('Location: tasks.php');
    exit();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks - Echoflux</title>
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/tasks.css">
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
        <section class="tasks-section">
            <h2>Available Tasks</h2><br>
            <?php if (isset($_SESSION['message'])): ?>
                <div class="message"><?= htmlspecialchars($_SESSION['message']) ?></div><br>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <?php foreach ($tasks as $task): ?>
                <div class="task-card">
                    <h3><?= htmlspecialchars($task['title']) ?></h3>
                    <p><?= htmlspecialchars($task['description']) ?></p>
                    <p>Points: <?= htmlspecialchars($task['points']) ?></p><br>
                    <?php if (in_array($task['id'], $completedTaskIds)): ?>
                        <p>Status: Completed</p>
                    <?php else: ?>
                        <form action="tasks.php" method="post">
                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                            <input type="hidden" name="task_link" value="<?= htmlspecialchars($task['link']) ?>">
                            <button type="submit" name="visit_task" class="button">Go to Task</button>
                        </form><br>
                        <form action="tasks.php" method="post">
                            <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                            <button type="submit" name="complete_task" class="button">Mark as Completed</button>
                        </form>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </section>
    </div>
    <script src="js/nav.js"></script>
</body>
</html>
