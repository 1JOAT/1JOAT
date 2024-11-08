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
$conversionRate = 0.1; // 10 point = 1 naira
$feePercentage = 0.09; // 9% fee

$points = 0; // Initialize points
$balance = 0; // Initialize balance

// Check if user has at least one skill
$hasSkill = false;
if ($conn) {
    $stmt = $conn->prepare("SELECT 1 FROM user_skills WHERE user_id = ?");
    $stmt->bind_param("i", $userId);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($result->num_rows > 0) {
        $hasSkill = true;
    }
    $stmt->close();
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $action = $_POST['action'];
    $amount = $_POST['amount'];

    if ($conn) {
        $stmt = $conn->prepare("SELECT points, balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($points, $balance);
        $stmt->fetch();
        $stmt->close();

        if (!$hasSkill && $action == 'points_to_naira') {
            $_SESSION['error'] = "You must have at least one skill to convert points to naira.";
        } else {
            if ($action == 'points_to_naira') {
                if ($points >= $amount) {
                    $nairaAmount = $amount * $conversionRate * (1 - $feePercentage); // Apply fee
                    $fee = $amount * $conversionRate * $feePercentage;
                    $stmt = $conn->prepare("UPDATE users SET points = points - ?, balance = balance + ? WHERE id = ?");
                    $stmt->bind_param("idd", $amount, $nairaAmount, $userId);
                    $stmt->execute();
                    $stmt->close();

                    // Log conversion history
                    $stmt = $conn->prepare("INSERT INTO conversion_history (user_id, action, amount, converted_amount, fee) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isddd", $userId, $action, $amount, $nairaAmount, $fee);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Insufficient points.";
                }
            } elseif ($action == 'naira_to_points') {
                if ($balance >= $amount * (1 + $feePercentage)) { // Check for total amount including fee
                    $pointsAmount = ($amount / $conversionRate) * (1 - $feePercentage); // Apply fee
                    $fee = ($amount / $conversionRate) * $feePercentage;
                    $stmt = $conn->prepare("UPDATE users SET balance = balance - ?, points = points + ? WHERE id = ?");
                    $stmt->bind_param("ddd", $amount, $pointsAmount, $userId);
                    $stmt->execute();
                    $stmt->close();

                    // Log conversion history
                    $stmt = $conn->prepare("INSERT INTO conversion_history (user_id, action, amount, converted_amount, fee) VALUES (?, ?, ?, ?, ?)");
                    $stmt->bind_param("isddd", $userId, $action, $amount, $pointsAmount, $fee);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    $_SESSION['error'] = "Insufficient balance.";
                }
            }
        }

        // Redirect to the same page to prevent form resubmission
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit();
    } else {
        echo "Failed to connect to the database.";
    }
} else {
    // Fetch user data when the page is first loaded
    if ($conn) {
        $stmt = $conn->prepare("SELECT points, balance FROM users WHERE id = ?");
        $stmt->bind_param("i", $userId);
        $stmt->execute();
        $stmt->bind_result($points, $balance);
        $stmt->fetch();
        $stmt->close();
    } else {
        echo "Failed to connect to the database.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Convert Points - Echoflux</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/convert.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <style>
        .loading-spinner {
            display: none;
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            z-index: 1000;
            background: rgba(255, 255, 255, 0.8);
            padding: 20px;
            border-radius: 10px;
        }
        .loading-spinner::after {
            content: '';
            display: block;
            width: 40px;
            height: 40px;
            border: 5px solid #ccc;
            border-top-color: #333;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
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
    <div class="loading-spinner"></div>
    <section class="convert-section">
        <h2>Convert Points</h2>
        <?php 
        if (isset($_SESSION['error'])) {
            echo "<p class='error'>" . htmlspecialchars($_SESSION['error']) . "</p>";
            unset($_SESSION['error']); // Clear the error message
        }
        ?>
        <form action="convert.php" method="post" onsubmit="showSpinner(); return confirmConversion(event)">
            <label for="action">Convert:</label>
            <select name="action" id="action">
                <option value="points_to_naira">Points to Naira</option>
                <option value="naira_to_points">Naira to Points</option>
            </select>
            <label for="amount">Amount:</label>
            <input type="number" name="amount" id="amount" required>
            <button type="submit">Convert</button>
        </form>
        <div class="conversion-info">
            <p>Your Points: <?= htmlspecialchars($points ?? 0) ?></p>
            <p>Your Balance: ₦<?= htmlspecialchars($balance ?? 0) ?></p>
            <p>Conversion Rate: 1 point = ₦<?= htmlspecialchars($conversionRate) ?></p>
            <p>Conversion Fee: 9%</p>
        </div>
    </section>

    <section class="conversion-history">
        <h2>Conversion History</h2>
        <?php
        if ($conn) {
            $stmt = $conn->prepare("SELECT action, amount, converted_amount, fee, created_at FROM conversion_history WHERE user_id = ? ORDER BY created_at DESC");
            $stmt->bind_param("i", $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                echo "<table>";
                echo "<tr><th>Action</th><th>Amount</th><th>Converted Amount</th><th>Fee</th><th>Date</th></tr>";
                while ($row = $result->fetch_assoc()) {
                    echo "<tr>";
                    echo "<td>" . htmlspecialchars($row['action']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['amount']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['converted_amount']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['fee']) . "</td>";
                    echo "<td>" . htmlspecialchars($row['created_at']) . "</td>";
                    echo "</tr>";
                }
                echo "</table>";
            } else {
                echo "<p>No transaction made.</p>";
            }
            $stmt->close();
        } else {
            echo "Failed to connect to the database.";
        }
        ?>
    </section>
</div>
<script src="js/nav.js"></script>
<script>
    function confirmConversion(event) {
        event.preventDefault(); // Prevent form submission

        const action = document.getElementById('action').value;
        const amount = parseFloat(document.getElementById('amount').value);
        const conversionRate = 0.1;
        const feePercentage = 0.09;

        let conversionDetails = '';
        let finalAmount = 0;
        let fee = 0;

        if (action === 'points_to_naira') {
            finalAmount = amount * conversionRate * (1 - feePercentage);
            fee = amount * conversionRate * feePercentage;
            conversionDetails = `You are converting ${amount} points to Naira. You will get approximately ₦${finalAmount.toFixed(2)} with a transaction fee of ₦${fee.toFixed(2)}.`;
        } else if (action === 'naira_to_points') {
            finalAmount = (amount / conversionRate) * (1 - feePercentage);
            fee = (amount / conversionRate) * feePercentage;
            conversionDetails = `You are converting ₦${amount} to points. You will get approximately ${finalAmount.toFixed(2)} points with a transaction fee of ${fee.toFixed(2)} points.`;
        }

        Swal.fire({
            title: 'Confirm Conversion',
            text: conversionDetails,
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#3085d6',
            cancelButtonColor: '#d33',
            confirmButtonText: 'Yes, convert it!'
        }).then((result) => {
            if (result.isConfirmed) {
                event.target.submit(); // Submit the form if confirmed
            }
        });
    }

    function showSpinner() {
        document.querySelector('.loading-spinner').style.display = 'block';
    }

    document.addEventListener('DOMContentLoaded', (event) => {
        document.querySelector('.loading-spinner').style.display = 'none';
    });
</script>
</body>
</html>
<?php
// Close the database connection after all operations are done
if ($conn) {
    $conn->close();
}
?>
