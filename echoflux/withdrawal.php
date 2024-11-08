<?php
session_start();

error_reporting(E_ALL);
ini_set('display_errors', 1);

if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'includes/db.php';

$userId = $_SESSION['user_id'];

// Check if a withdrawal was just made
if (isset($_SESSION['withdrawal_made']) && $_SESSION['withdrawal_made'] === true) {
    unset($_SESSION['withdrawal_made']); // Remove the flag
    header('Location: withdrawal.php'); // Redirect to prevent form resubmission
    exit();
}

// Fetch user's current balance
$stmt = $conn->prepare("SELECT balance FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$stmt->bind_result($balance);
$stmt->fetch();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $amount = $_POST['amount'];
    $method = $_POST['method']; // e.g., bank transfer, PayPal, etc.
    $cryptoAddress = isset($_POST['crypto_address']) ? $_POST['crypto_address'] : '';
    $accountNumber = isset($_POST['account_number']) ? $_POST['account_number'] : '';
    $bankName = isset($_POST['bank_name']) ? $_POST['bank_name'] : '';

    // Validate amount
    if ($amount <= 0) {
        $error = "Invalid withdrawal amount.";
    } elseif ($amount < 1000) {
        $error = "The amount must be at least 1000 Naira to withdraw.";
    } elseif ($amount > $balance) {
        $error = "Insufficient balance.";
    } else {
        // Deduct the amount from the user's balance
        $stmt = $conn->prepare("UPDATE users SET balance = balance - ? WHERE id = ?");
        $stmt->bind_param("di", $amount, $userId);
        $stmt->execute();
        $stmt->close();

        // Log the withdrawal
        $status = 'Pending'; // Initial status
        $stmt = $conn->prepare("INSERT INTO withdrawals (user_id, amount, method, status, account_number, bank_name, crypto_address) VALUES (?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("idsssss", $userId, $amount, $method, $status, $accountNumber, $bankName, $cryptoAddress);
        $stmt->execute();
        $stmt->close();

        // Log the admin notification (alternative to email)
        $notification = "A new withdrawal request has been submitted by user ID $userId for the amount of $amount Naira via $method.";
        $logFile = 'admin_notifications.log';
        if (file_exists($logFile) && is_writable($logFile)) {
            file_put_contents($logFile, date('Y-m-d H:i:s') . ' - ' . $notification . PHP_EOL, FILE_APPEND);
        } else {
            // Handle the error, e.g., log it to another file or display a message
            error_log("Unable to write to admin notifications log file.", 3, "error_log.txt");
        }
        
        $_SESSION['withdrawal_made'] = true; // Set the flag
        $success = "Withdrawal request submitted successfully.";
    }
}

// Fetch user's withdrawal history
$stmt = $conn->prepare("SELECT amount, method, status, created_at FROM withdrawals WHERE user_id = ? ORDER BY created_at DESC");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$withdrawals = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal - Echoflux</title>
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/withdrawal.css">

</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">
                <a href="homepage.php"><img src="images/logo2.png" alt="Echoflux Logo"></a>
            </div>
            <ul class="nav-links">
                <li><a href="homepage.php">Home </a></li>
                <li><a href="tasks.php">Tasks</a></li>
                <li><a href="spin.php">Spin and Win</a></li>
                <li><a href="digital.php">Digital Skills</a></li>
                <li><a href="sport.php">Sports Prediction</a></li>
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
       <section class="withdrawal-section">
            <h2>Request Withdrawal</h2>
            <p>Your current balance: ₦<?= htmlspecialchars($balance) ?></p>
            <?php if (isset($error)) echo "<p class='error'>$error</p>"; ?>
            <?php if (isset($success)) echo "<p class='success'>$success</p>"; ?>
            
            <form action="withdrawal.php" method="post">
                <label for="amount">Amount (in Naira):</label>
                <input type="number" id="amount" name="amount" required>
                <label for="method">Method:</label>
                <select id="method" name="method" required>
                    <option value="">Select Method</option>
                    <option value="crypto_currency">Crypto</option>
                    <option value="bank_transfer">Bank Transfer</option>
                </select>
                <div id="bankDetails" style="display: none;">
                    <label for="account_number">Account Number:</label>
                    <input type="text" id="account_number" name="account_number"><br>
                    <label for="bank_name">Bank Name:</label>
                    <select id="bank_name" name="bank_name">
                        <option value="">Select Bank</option>
                        <option value="wema">GTB</option>
                        <option value="opay">Opay</option>
                        <option value="moniepoint">Moniepoint</option>
                        <option value="Palmpay">Palmpay</option>
                        <option value="Kuda Mfb">Kuda Mfb</option>
                        <option value="UBA">UBA</option>
                        <option value="Polaris">Polaris</option>
                        <option value="Firstbank">Firstbank</option>
                        <option value="Access">Access</option>
                        <option value="Zenith">Zenith</option>
                        <option value="Smartcash">Smartcash</option>

                        <!-- Add more options as needed -->
                    </select>
                </div>
                <div id="cryptoDetails" style="display: none;">
                    <label for="crypto_address">TRX trc20 Address:</label>
                    <input type="text" id="crypto_address" name="crypto_address" placeholder="TRX TRC20 address only">
                </div>
                <button type="submit">Request Withdrawal</button>
            </form>

        </section>
        <section class="withdrawal-history">
            <h2>Withdrawal History</h2>
            <table>
                <thead>
                    <tr>
                        <th>Amount (₦)</th>
                        <th>Method</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($withdrawals as $withdrawal): ?>
                        <tr>
                            <td><?= htmlspecialchars($withdrawal['amount']) ?></td>
                            <td><?= htmlspecialchars($withdrawal['method']) ?></td>
                            <td><?= htmlspecialchars($withdrawal['status']) ?></td>
                            <td><?= htmlspecialchars($withdrawal['created_at']) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </section>
    </div>
    <script>
        document.addEventListener('DOMContentLoaded', (event) => {
            document.getElementById('method').addEventListener('change', function() {
                if (this.value === 'bank_transfer') {
                    document.getElementById('bankDetails').style.display = 'block';
                    document.getElementById('cryptoDetails').style.display = 'none';
                } else if (this.value === 'crypto_currency') {
                    document.getElementById('cryptoDetails').style.display = 'block';
                    document.getElementById('bankDetails').style.display = 'none';
                } else {
                    document.getElementById('bankDetails').style.display = 'none';
                    document.getElementById('cryptoDetails').style.display = 'none';
                }
            });
        });
    </script>
    <script src="js/nav.js"></script>
</body>
</html>
