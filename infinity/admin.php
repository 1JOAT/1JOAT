<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
session_start();

// Check if the user is logged in
if (!isset($_SESSION['id'])) {
    header("location: login.php");
    exit;
}

require 'includes/db.php'; 

// Check if the logged-in user is an admin
$user_id = $_SESSION['id'];
$sql = "SELECT is_admin FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($is_admin);
$stmt->fetch();
$stmt->close();

if (!$is_admin) {
    // User is not an admin, redirect to a suitable page
    header("location: homepage.php");
    exit;
}

// Handle password reset request
$password_reset_message = "";
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['reset_password'])) {
    $email = trim($_POST['email']);
    $new_password = trim($_POST['new_password']);
    $confirm_password = trim($_POST['confirm_password']);
    
    // Validate inputs
    if (empty($email) || empty($new_password) || empty($confirm_password)) {
        $password_reset_message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $password_reset_message = "Passwords do not match.";
    } else {
        // Hash the new password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        
        // Update the user's password in the database
        $sql = "UPDATE users SET password = ? WHERE email = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $hashed_password, $email);
        
        if ($stmt->execute()) {
            $password_reset_message = "Password successfully updated for user: " . $email;
        } else {
            $password_reset_message = "Error updating password. Please try again.";
        }
        
        $stmt->close();
    }
}

// Fetch pending transactions
$sql = "SELECT t.id, t.reference, t.amount, t.crypto_type, t.sender_address, u.email 
        FROM transactions t
        JOIN users u ON t.user_id = u.id
        WHERE t.status = 'pending_confirmation'";
$result = $conn->query($sql);

// Handle status update
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['update_status'])) {
    $transaction_id = $_POST['transaction_id'];
    $new_status = $_POST['new_status'];

    // Update the transaction status
    $sql = "UPDATE transactions SET status = ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("si", $new_status, $transaction_id);
    $stmt->execute();
    $stmt->close();

    // If the status is 'success', update the user's balance
    if ($new_status == 'success') {
        // Fetch the transaction amount
        $sql = "SELECT amount, user_id FROM transactions WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("i", $transaction_id);
        $stmt->execute();
        $stmt->bind_result($amount, $user_id);
        $stmt->fetch();
        $stmt->close();

        // Update the user's balance
        $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("di", $amount, $user_id);
        $stmt->execute();
        $stmt->close();
    }

    // Redirect back to the admin page to refresh the transaction list
    header("Location: admin.php");
    exit;
}

// Fetch active signal subscriptions with user details
$sql = "SELECT ss.id, ss.plan, ss.start_date, ss.end_date, u.username, u.email, u.phone_number
        FROM signal_subscriptions ss
        JOIN users u ON ss.user_id = u.id
        WHERE ss.end_date > NOW()";
$resultSubscriptions = $conn->query($sql);

// Fetch one-on-one class registrations with user details
$sql = "SELECT ooc.id, ooc.registration_date, u.username, u.email, u.phone_number
        FROM one_on_one_classes ooc
        JOIN users u ON ooc.user_id = u.id";
$resultClasses = $conn->query($sql);

// Fetch registered users per month
$sql = "SELECT MONTHNAME(created_at) as month, COUNT(*) as count 
        FROM users 
        GROUP BY MONTH(created_at), MONTHNAME(created_at)"; 
$resultRegisteredUsersPerMonth = $conn->query($sql);

// Fetch registered users for free classes per month
$sql = "SELECT MONTHNAME(registration_date) as month, COUNT(*) as count 
        FROM free_class_registrations 
        GROUP BY MONTH(registration_date), MONTHNAME(registration_date)";
$resultFreeClassRegistrationsPerMonth = $conn->query($sql);

// Fetch total registered users
$sql = "SELECT COUNT(*) as total_count FROM users";
$resultTotalCount = $conn->query($sql);
$totalCount = $resultTotalCount->fetch_assoc()['total_count'];

?>

<!DOCTYPE html>
<html>
<head>
    <title>Admin Panel - Infinity Forex Academy</title>
    <link rel="shortcut icon" href="images/fav.png" type="image/x-icon">
    <link rel="stylesheet" href="styles/admin.css">
    <link rel="stylesheet" href="styles/homepage.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.2/css/all.min.css">
        <style>
        body {
    font-family: 'Mukta', sans-serif;
    margin: 0;
    padding: 0;
    padding-bottom: 60px;
    padding: 60px 20px;
    color: #333;
    background-color: #f1f1f1;
}

@media screen and (min-width: 768px){
    body{
        margin-left: 270px;
    }
}

h2 {
    color: #104cba;
    margin-bottom: 15px;
}

p {
    font-size: 18px;
}

table {
    width: 100%;
    border-collapse: collapse;
    margin-bottom: 30px;
    background-color: #fff;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
    border-radius: 5px;
    overflow: hidden;
}

th, td {
    padding: 12px 15px;
    text-align: left;
    border-bottom: 1px solid #ddd;
}

th {
    background-color: #104cba;
    color: #fff;
    text-transform: uppercase;
    letter-spacing: 0.03em;
}

td {
    color: #333;
}

tr:hover {
    background-color: #f1f1f1;
}

/* Buttons and Forms */
button {
    background-color: #104cba;
    color: #fff;
    border: none;
    padding: 8px 15px;
    cursor: pointer;
    border-radius: 4px;
    font-size: 14px;
    transition: background-color 0.3s ease;
}

button:hover {
    background-color: #083b89;
}

select {
    padding: 8px;
    border-radius: 4px;
    border: 1px solid #ddd;
    margin-right: 10px;
}

/* Table styles */
th, td {
    border-bottom: 1px solid #e0e0e0;
}

tr:nth-child(even) {
    background-color: #f9f9f9;
}

tr:last-child td {
    border-bottom: none;
}

/* Card Layout for stats */
.stats-container {
    display: flex;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    flex: 1;
    background-color: #fff;
    padding: 20px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.05);
}

.stat-card p {
    font-size: 20px;
    color: #555;
}

.stat-card .stat-value {
    font-size: 36px;
    color: #104cba;
    margin-top: 10px;
}

/* Responsive layout */
@media (max-width: 768px) {
    .stats-container {
        flex-direction: column;
    }

    table, tr, td, th {
        font-size: 14px;
    }

    button {
        padding: 6px 10px;
    }
}

    </style>
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

<h1>Admin Dashboard</h1>

<!-- Password Reset Form -->
<h2>Reset User Password</h2>
<p><?php echo $password_reset_message; ?></p>
<form method="post" action="admin.php" class="stats-container">
    <label for="email">User Email:</label>
    <input type="email" name="email" id="email" required>
    
    <label for="new_password">New Password:</label>
    <input type="password" name="new_password" id="new_password" required>
    
    <label for="confirm_password">Confirm Password:</label>
    <input type="password" name="confirm_password" id="confirm_password" required>
    
    <button type="submit" name="reset_password">Reset Password</button>
</form>

<!-- Stats Cards -->
<div class="stats-container">
    <div class="stat-card">
        <p>Total Registered Users</p>
        <div class="stat-value"><?php echo $totalCount; ?></div>
    </div>
</div>

<h2>Registered Users per Month</h2>
<table>
    <tr>
        <th>Month</th>
        <th>Number of Registered Users</th>
    </tr>
    <?php
    if ($resultRegisteredUsersPerMonth->num_rows > 0) {
        while($row = $resultRegisteredUsersPerMonth->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row["month"] . "</td>";
            echo "<td>" . $row["count"] . "</td>";
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='2'>No registered users found.</td></tr>";
    }
    ?>
</table>

    <h2>Pending Cryptocurrency Transactions</h2>

    <table>
        <tr>
            <th>ID</th>
            <th>Reference</th>
            <th>User Email</th>
            <th>Amount</th>
            <th>Cryptocurrency</th>
            <th>Sender Address</th>
            <th>Action</th>
        </tr>
        <?php
        if ($result->num_rows > 0) {
            while($row = $result->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["id"] . "</td>";
                echo "<td>" . $row["reference"] . "</td>";
                echo "<td>" . $row["email"] . "</td>";
                echo "<td>$" . $row["amount"] . "</td>";
                echo "<td>" . $row["crypto_type"] . "</td>";
                echo "<td>" . $row["sender_address"] . "</td>";
                echo "<td>";
                echo "<form method='post' action='admin.php'>";
                echo "<input type='hidden' name='transaction_id' value='" . $row["id"] . "'>";
                echo "<select name='new_status'>";
                echo "<option value='success'>Approve</option>";
                echo "<option value='failed'>Reject</option>";
                echo "</select>";
                echo "<button type='submit' name='update_status'>Update</button>";
                echo "</form>";
                echo "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='7'>No pending transactions found.</td></tr>";
        }
        ?>
    </table>

    <h2>Active Signal Subscriptions</h2>
<table id="signalSubscriptionsTable">
    <tr>
        <th>ID</th>
        <th>User</th>
        <th>Email</th>
        <th>Phone Number</th> 
        <th>Plan</th>
        <th>Start Date</th>
        <th>End Date</th>
        <th>Action</th> 
    </tr>

    <?php
    if ($resultSubscriptions->num_rows > 0) {
        while($row = $resultSubscriptions->fetch_assoc()) {
            echo "<tr>";
            echo "<td>" . $row["id"] . "</td>";
            echo "<td>" . $row["username"] . "</td>";
            echo "<td>" . $row["email"] . "</td>";
            echo "<td>" . $row["phone_number"] . "</td>"; 
            echo "<td>" . $row["plan"] . "</td>";
            echo "<td>" . $row["start_date"] . "</td>";
            echo "<td>" . $row["end_date"] . "</td>";
            echo "<td><button class='hide-row'>Hide</button></td>"; // Add the "Hide" button
            echo "</tr>";
        }
    } else {
        echo "<tr><td colspan='8'>No active signal subscriptions found.</td></tr>"; // Update colspan
    }
    ?>
</table>



    <h2>One-on-One Class Registrations</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>User</th>
            <th>Email</th>
            <th>Phone Number</th>
            <th>Registration Date</th>
        </tr>
        <?php
        if ($resultClasses->num_rows > 0) {
            while($row = $resultClasses->fetch_assoc()) {
                echo "<tr>";
                echo "<td>" . $row["id"] . "</td>";
                echo "<td>" . $row["username"] . "</td>";
                echo "<td>" . $row["email"] . "</td>";
                echo "<td>" . $row["phone_number"] . "</td>";
                echo "<td>" . $row["registration_date"] . "</td>";
                echo "</tr>";
            }
        } else {
            echo "<tr><td colspan='5'>No one-on-one class registrations found.</td></tr>";
        }
        ?>
    </table>
    <nav class="bottom-navbar">
        <ul>
            <li><a href="homepage.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="resources.php"><i class="fas fa-folder"></i> Resources</a></li>
            <li><a href="topup.php"><i class="fas fa-plus-circle"></i> Top Up</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
   </nav>


   <script>
const hideButtons = document.querySelectorAll('.hide-row');

hideButtons.forEach(button => {
    button.addEventListener('click', () => {
        const row = button.parentNode.parentNode; 
        const rowId = row.cells[0].textContent; // Assuming the first cell contains the subscription ID

        // Store the hidden row ID in local storage
        let hiddenRows = localStorage.getItem('hiddenSignalRows');
        if (!hiddenRows) {
            hiddenRows = [];
        } else {
            hiddenRows = JSON.parse(hiddenRows);
        }
        hiddenRows.push(rowId);
        localStorage.setItem('hiddenSignalRows', JSON.stringify(hiddenRows));

        row.style.display = 'none'; 
    });
});

// On page load, hide rows that were previously hidden
window.addEventListener('load', () => {
    const hiddenRows = localStorage.getItem('hiddenSignalRows');
    if (hiddenRows) {
        const hiddenRowIds = JSON.parse(hiddenRows);
        const allRows = document.querySelectorAll('#signalSubscriptionsTable tr'); 
        allRows.forEach(row => {
            const rowId = row.cells[0].textContent;
            if (hiddenRowIds.includes(rowId)) {
                row.style.display = 'none';
            }
        });
    }
});
</script>
</body>
</html>