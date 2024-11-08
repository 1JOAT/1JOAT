<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check if the user is an admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: login.php');
    exit();
}

require 'includes/db.php';

// Fetch the total number of registered users
$stmt = $conn->prepare("SELECT COUNT(*) FROM users");
$stmt->execute();
$stmt->bind_result($totalUsers);
$stmt->fetch();
$stmt->close();

// Fetch the number of active users (who checked in in the past 24 hours)
$stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE last_checkin_date >= NOW() - INTERVAL 1 DAY");
$stmt->execute();
$stmt->bind_result($activeUsers);
$stmt->fetch();
$stmt->close();

// Handle adding a new notification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['message']) && !empty($_POST['message'])) {
    $message = $conn->real_escape_string($_POST['message']);
    $sql = "INSERT INTO notifications (message) VALUES ('$message')";
    
    if ($conn->query($sql) === TRUE) {
        echo "Notification added successfully!";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }
}

// Handle deleting a notification
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['id'])) {
    $id = intval($_POST['id']);
    $sql = "DELETE FROM notifications WHERE id = $id";
    
    if ($conn->query($sql) === TRUE) {
        echo "Notification deleted successfully!";
    } else {
        echo "Error: " . $sql . "<br>" . $conn->error;
    }

    header('Location: admin_dashboard.php');
    exit();
}

// Fetch all withdrawal requests with user details
$stmt = $conn->prepare("
    SELECT w.id, w.user_id, w.amount, w.method, w.status, w.created_at, w.account_number, w.bank_name, w.crypto_address, u.firstname, u.lastname
    FROM withdrawals w
    JOIN users u ON w.user_id = u.id
    WHERE w.is_processed = 0
    ORDER BY w.created_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$withdrawals = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle status update for withdrawals
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $withdrawalId = $_POST['withdrawal_id'];
    $newStatus = $_POST['status'];

    $stmt = $conn->prepare("UPDATE withdrawals SET status = ?, is_processed = 1 WHERE id = ?");
    $stmt->bind_param("si", $newStatus, $withdrawalId);
    $stmt->execute();
    $stmt->close();

    // If status is set to 'Failed', add the amount back to the user's balance
    if ($newStatus == 'Failed') {
        $stmt = $conn->prepare("SELECT user_id, amount FROM withdrawals WHERE id = ?");
        $stmt->bind_param("i", $withdrawalId);
        $stmt->execute();
        $result = $stmt->get_result();
        $withdrawal = $result->fetch_assoc();
        $stmt->close();

        if ($withdrawal) {
            $userId = $withdrawal['user_id'];
            $amount = $withdrawal['amount'];

            $stmt = $conn->prepare("UPDATE users SET balance = balance + ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $userId);
            $stmt->execute();
            $stmt->close();
        }
    }

    header('Location: admin_dashboard.php');
    exit();
}

// Fetch users with the highest successful withdrawals
$stmt = $conn->prepare("
    SELECT u.id, u.firstname, u.lastname, SUM(w.amount) as total_withdrawals
    FROM users u
    JOIN withdrawals w ON u.id = w.user_id
    WHERE w.status = 'Successful'
    GROUP BY u.id
    ORDER BY total_withdrawals DESC
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
$topWithdrawalsUsers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch users with the highest number of referrals
$stmt = $conn->prepare("
    SELECT u.id, u.firstname, u.lastname, COUNT(r.referrer_id) as total_referrals
    FROM users u
    JOIN referrals r ON u.id = r.referrer_id
    GROUP BY u.id
    ORDER BY total_referrals DESC
    LIMIT 10
");
$stmt->execute();
$result = $stmt->get_result();
$topReferralsUsers = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch user skills with user details
$stmt = $conn->prepare("
    SELECT us.user_id, u.firstname, u.lastname, u.email, u.phone_number, s.name AS skill_name, s.cost, us.registered_at
    FROM user_skills us
    JOIN users u ON us.user_id = u.id
    JOIN skills s ON us.skill_id = s.id
    ORDER BY us.registered_at DESC
");
$stmt->execute();
$result = $stmt->get_result();
$userSkills = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch tasks for admin management
$stmt = $conn->prepare("SELECT id, title, description, points, link FROM tasks");
$stmt->execute();
$result = $stmt->get_result();
$tasks = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Handle task update or deletion
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_task'])) {
    $taskId = $_POST['task_id'];
    $title = $_POST['title'];
    $description = $_POST['description'];
    $points = $_POST['points'];
    $link = $_POST['link'];

    $stmt = $conn->prepare("UPDATE tasks SET title = ?, description = ?, points = ?, link = ? WHERE id = ?");
    $stmt->bind_param("ssisi", $title, $description, $points, $link, $taskId);
    $stmt->execute();
    $stmt->close();

    header('Location: admin_dashboard.php');
    exit();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_task'])) {
    $taskId = $_POST['task_id'];

    $stmt = $conn->prepare("DELETE FROM tasks WHERE id = ?");
    $stmt->bind_param("i", $taskId);
    $stmt->execute();
    $stmt->close();

    header('Location: admin_dashboard.php');
    exit();
}

// Handle adding a new task
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['add_task'])) {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $points = $_POST['points'];
    $link = $_POST['link'];

    $stmt = $conn->prepare("INSERT INTO tasks (title, description, points, link) VALUES (?, ?, ?, ?)");
    $stmt->bind_param("ssis", $title, $description, $points, $link);
    $stmt->execute();
    $stmt->close();

    header('Location: admin_dashboard.php');
    exit();
}

 


?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard</title>
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/admindashboard.css">
</head>
<body>
    <div class="container">


        <!-- Total Registered Users Section -->
        <div class="dashboard">
            <div class="card">
                <h2>Total Registered Users</h2>
                <p><?= htmlspecialchars($totalUsers) ?></p>
            </div>
        </div><br>

        <!-- Active Users Section -->
        <div class="dashboard">
            <div class="card">
                <h2>Active Users (Last 24 Hours)</h2>
                <p><?= htmlspecialchars($activeUsers) ?></p>
            </div>
        </div><br>

        <!-- Withdrawal Requests Section -->
        <div class="dashboard">
            <div class="card">
                <h2>Withdrawal Requests</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>User ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Amount (₦)</th>
                            <th>Method</th>
                            <th>Account Number / Crypto Address</th>
                            <th>Bank Name</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($withdrawals as $withdrawal): ?>
                            <tr>
                                <td><?= htmlspecialchars($withdrawal['id']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['user_id']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['firstname']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['lastname']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['amount']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['method']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['method'] == 'crypto_currency' ? $withdrawal['crypto_address'] : $withdrawal['account_number']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['bank_name']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['status']) ?></td>
                                <td><?= htmlspecialchars($withdrawal['created_at']) ?></td>
                                <td>
                                    <form action="admin_dashboard.php" method="post">
                                        <input type="hidden" name="withdrawal_id" value="<?= htmlspecialchars($withdrawal['id']) ?>">
                                        <select name="status">
                                            <option value="Pending" <?= $withdrawal['status'] == 'Pending' ? 'selected' : '' ?>>Pending</option>
                                            <option value="Successful" <?= $withdrawal['status'] == 'Successful' ? 'selected' : '' ?>>Successful</option>
                                            <option value="Failed" <?= $withdrawal['status'] == 'Failed' ? 'selected' : '' ?>>Failed</option>
                                        </select>
                                        <button type="submit" name="update_status">Update</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><br>

        <!-- User Skills Section -->
        <div class="dashboard">
            <div class="card">
                <h2>User Skills</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Email</th>
                            <th>Phone Number</th>
                            <th>Skill Name</th>
                            <th>Skill Cost</th>
                            <th>Registered At</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($userSkills as $userSkill): ?>
                            <tr>
                                <td><?= htmlspecialchars($userSkill['user_id']) ?></td>
                                <td><?= htmlspecialchars($userSkill['firstname']) ?></td>
                                <td><?= htmlspecialchars($userSkill['lastname']) ?></td>
                                <td><?= htmlspecialchars($userSkill['email']) ?></td>
                                <td><?= htmlspecialchars($userSkill['phone_number']) ?></td>
                                <td><?= htmlspecialchars($userSkill['skill_name']) ?></td>
                                <td><?= htmlspecialchars($userSkill['cost']) ?></td>
                                <td><?= htmlspecialchars($userSkill['registered_at']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><br>
        
              



        <!-- Task Management Section -->
        <div class="dashboard">
            <div class="card">
                <h2>Task Management</h2>
                <table>
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Title</th>
                            <th>Description</th>
                            <th>Points</th>
                            <th>Link</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($tasks as $task): ?>
                            <tr>
                                <td><?= htmlspecialchars($task['id']) ?></td>
                                <td><?= htmlspecialchars($task['title']) ?></td>
                                <td><?= htmlspecialchars($task['description']) ?></td>
                                <td><?= htmlspecialchars($task['points']) ?></td>
                                <td><?= htmlspecialchars($task['link']) ?></td>
                                <td>
                                    <form action="admin_dashboard.php" method="post">
                                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                        <input type="text" name="title" placeholder="Title" value="<?= htmlspecialchars($task['title']) ?>" required>
                                        <input type="text" name="description" placeholder="Description" value="<?= htmlspecialchars($task['description']) ?>" required>
                                        <input type="number" name="points" placeholder="Points" value="<?= htmlspecialchars($task['points']) ?>" required>
                                        <input type="url" name="link" placeholder="Link" value="<?= htmlspecialchars($task['link']) ?>" required>
                                        <button type="submit" name="update_task">Update</button>
                                    </form>
                                    <form action="admin_dashboard.php" method="post">
                                        <input type="hidden" name="task_id" value="<?= htmlspecialchars($task['id']) ?>">
                                        <button type="submit" name="delete_task">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><br>

        <!-- Add New Task Section -->
        <div class="dashboard">
            <div class="card">
                <h2>Add New Task</h2>
                <form action="admin_dashboard.php" method="post">
                    <input type="text" name="title" placeholder="Title" required>
                    <input type="text" name="description" placeholder="Description" required>
                    <input type="number" name="points" placeholder="Points" required>
                    <input type="url" name="link" placeholder="Link" required>
                    <button type="submit" name="add_task">Add Task</button>
                </form>
            </div>
        </div><br>

        <!-- Top Users with Highest Withdrawals Section -->
        <div class="dashboard">
            <div class="card">
                <h2>Top Users with Highest Withdrawals</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Total Withdrawals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topWithdrawalsUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['firstname']) ?></td>
                                <td><?= htmlspecialchars($user['lastname']) ?></td>
                                <td><?= htmlspecialchars($user['total_withdrawals']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><br>

        <!-- Top Users with Highest Referrals Section -->
        <div class="dashboard">
            <div class="card">
                <h2>Top Users with Highest Referrals</h2>
                <table>
                    <thead>
                        <tr>
                            <th>User ID</th>
                            <th>First Name</th>
                            <th>Last Name</th>
                            <th>Total Referrals</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($topReferralsUsers as $user): ?>
                            <tr>
                                <td><?= htmlspecialchars($user['id']) ?></td>
                                <td><?= htmlspecialchars($user['firstname']) ?></td>
                                <td><?= htmlspecialchars($user['lastname']) ?></td>
                                <td><?= htmlspecialchars($user['total_referrals']) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div><br>



</body>
</html>
<?php
// Close the connection here, at the very end
$conn->close();
?>