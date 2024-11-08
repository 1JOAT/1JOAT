<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);
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

// Paystack API key (test mode)
// $paystack_secret_key = 'sk_test_a176111a6b9e685a747abf0a1c24e5f5263664e3'; 

$user_id = $_SESSION['id']; 

// Fetch user details (email, balance)
$sql = "SELECT email, balance FROM users WHERE id = ?";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id); 
$stmt->execute();
$stmt->bind_result($email, $current_balance);
$stmt->fetch();
$stmt->close();

// Fetch user's deposit history
$sql = "SELECT amount, payment_method, status, reference, created_at FROM transactions WHERE user_id = ? ORDER BY created_at DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$stmt->bind_result($amount, $payment_method, $status, $reference, $created_at);

// Store deposit history in an array
$deposit_history = [];
while ($stmt->fetch()) {
    $deposit_history[] = [
        'amount' => $amount,
        'payment_method' => $payment_method,
        'status' => $status,
        'reference' => $reference,
        'created_at' => $created_at
    ];
}
$stmt->close();

// Array to store cryptocurrency addresses
$crypto_addresses = [
    'bitcoin' => 'bc1qcd4kk40tcy4h503axnskemqnn3ylwu7z7pxj2u',
    'ethereum' => '0xDa3ccD06DC62178eDb75E23f4c1A4D31e21B90b4',
    'bnb' => '0xDa3ccD06DC62178eDb75E23f4c1A4D31e21B90b4',
    'litecoin' => 'ltc1qyelsufe564g20xuxdkdclmueh70hkgnaqy33vf',
    'usdt_trc20' => 'TCHoXpRiU4Lf8WiqNPc8RzE3jaWgqsfJBh',
    'solana' => 'DfotdLikz3ZzUyKwDLgVGJPixQAqCsrqD9HmZbsPxSzH',
    'xrp' => 'rQp1FQiUvjsWVUr4XFqnSxUS9pzB7Cziy2',
    'ton' => 'UQBa_IP8DD3cbSJTQygq9QMLp2aejopQny7NY-lVwMY__t3_',
    'dogecoin' => 'DGhgkD9kxLKEeMjTEz5rKfycDaQQrgW9St'
];

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    if (isset($_POST['confirm_payment'])) {
        $sender_address = filter_input(INPUT_POST, 'sender_address', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
        $reference = filter_input(INPUT_POST, 'reference', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        // Update the transaction with the sender's address and mark it as pending confirmation
        $sql = "UPDATE transactions SET sender_address = ?, status = 'pending_confirmation' WHERE reference = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $sender_address, $reference);
        if ($stmt->execute()) {
            echo '<div class="review-message">Your payment is under review. We will confirm shortly.</div>';
        } else {
            echo '<div class="error-message">Error: ' . $stmt->error . '</div>';
        }
        $stmt->close();

    } else { 
        // Handle initial top-up form submission
        $topup_amount = filter_input(INPUT_POST, 'amount', FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
        $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

        if (!is_numeric($topup_amount) || $topup_amount <= 0) {
            echo '<div class="error-message">Invalid Topup amount, please enter a valid number.</div>';
            exit;
        }

        $amount_in_kobo = $topup_amount * 100 * 1600; 
        $reference = uniqid('ref_');

        $crypto_type = ($payment_method == 'crypto') ? $_POST["crypto_type"] : null;
        $sql = "INSERT INTO transactions (user_id, amount, payment_method, status, reference, crypto_type) 
                VALUES (?, ?, ?, 'pending', ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("idsss", $user_id, $topup_amount, $payment_method, $reference, $crypto_type);

        if (!$stmt->execute()) {
            error_log("Error inserting transaction: " . $stmt->error);
            echo '<div class="error-message">An error occurred durin payment processing. Please try again later.</div>';
            exit;

        }
        $stmt->close();

        if ($payment_method == 'paystack') {
            $callback_url = 'verify_payment.php'; 

            $url = "https://api.paystack.co/transaction/initialize";
            $fields = [
                'email' => $email, 
                'amount' => $amount_in_kobo,
                'reference' => $reference,
                'callback_url' => $callback_url
            ];

            $fields_string = http_build_query($fields);
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $fields_string);

            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization:   Bearer $paystack_secret_key",

                "Cache-Control: no-cache",
            ]);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // For development only
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);

            if(curl_errno($ch)) {
                file_put_contents('paystack_log.txt', "cURL Error: " . curl_error($ch) . PHP_EOL, FILE_APPEND);
            }

            curl_close($ch);

            file_put_contents('paystack_log.txt', "Paystack Response: " . $response . PHP_EOL, FILE_APPEND); 
            $paystackResponse = json_decode($response, true); 

            if (!$paystackResponse || !$paystackResponse['status']) {
                $errorMessage = $paystackResponse['message'] ?? 'Unknown error from Paystack';
                file_put_contents('paystack_log.txt', "Error initializing Paystack payment: " . $errorMessage . PHP_EOL, FILE_APPEND); 
                echo '<div class="error-message">An error occurred while initializing the payment. Please try again later.</div>';

            } else {
                header("Location: " . $paystackResponse['data']['authorization_url']);
            }
        } elseif ($payment_method == 'crypto') {
            $crypto_address = $crypto_addresses[$crypto_type] ?? 'Address not found for this cryptocurrency';

            // Display the address to the user
            echo '<div class="crypto-payment-section">';
            echo '<h2>Send Cryptocurrency</h2>';
            echo '<p>Please send $' . $topup_amount . ' worth of ' . ucfirst($crypto_type) . ' to the following address:</p>';
            echo '<p><strong class="crypto-address">' . $crypto_address . '</strong></p>';
            echo '<p>Once you have sent the payment, please confirm below:</p>';

            // Confirmation form for sender's address
            echo '<form method="post" action="topup.php">';
            echo '<label for="sender_address">Enter the address you sent from:</label>';
            echo '<input type="text" id="sender_address" name="sender_address" class="crypto-input" required>';
            echo '<input type="hidden" name="crypto_type" value="' . $crypto_type . '">';
            echo '<input type="hidden" name="amount" value="' . $topup_amount . '">';
            echo '<input type="hidden" name="reference" value="' . $reference . '">';
            echo '<button type="submit" name="confirm_payment" class="crypto-button">I have sent $' . $topup_amount . '  to this address</button>';
            echo '</form>';
            echo '</div>';


            

        } elseif ($payment_method == 'monnify') {
            // ... (your Monnify integration code) 
        } else {
            echo '<div class="error-message">Invalid payment method selected.</div>';
            exit;
        }
    } 
} 

?>

<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    
    <title>Top Up - Infinity Forex Academy</title>
    <script src="https://js.paystack.co/v1/inline.js"></script> 
    <link rel="stylesheet" href="styles/homepage.css">
    <link rel="stylesheet" href="styles/topup.css">
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

    <h2 class="welcome"><span> Current Balance:  $<?php echo $current_balance; ?><br><br> <h6 style="margin: 0px;">1$ = NGN 1600</h6></span> </h2>

    <div class="top-up-section">
        <h2>Top Up Your Account</h2>
        <form method="post" action="topup.php"> 
            <label for="amount">Top-up Amount ($):</label>
            <input type="number" id="amount" name="amount" min="1" required>

            <label for="payment_method">Payment Method:</label>
            <select id="payment_method" name="payment_method" onchange="toggleCryptoOptions(this.value)">
                <option value="xxx">Select an option...</option>
                <option value="crypto">Cryptocurrency</option>
            </select>

            <div id="crypto_options" style="display:none;"> 
                <label for="crypto_type">Cryptocurrency:</label>
                <select id="crypto_type" name="crypto_type">
                    <option value="bitcoin">Bitcoin</option>
                    <option value="dogecoin">Dogecoin</option>
                    <option value="usdt_trc20">USDT (TRC20)</option>
                    <option value="bnb">BNB (BEP 20)</option>
                    <option value="litecoin">Litecoin</option>
                    <option value="solana">Solana</option>
                    <option value="xrp">XRP</option>
                    <option value="ton">TON</option>
                </select>
            </div>

            <button type="submit">Top Up</button>
        </form>
    </div>

    <div class="deposit-history-section">
        <h2>Your Deposit History</h2>
        
        <div class="table-container">
            <?php if (!empty($deposit_history)) : ?>
                <table>
                    <thead>
                        <tr>
                            <th>Amount ($)</th>
                            <th>Payment Method</th>
                            <th>Status</th>
                            <th>Reference</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($deposit_history as $deposit) : ?>
                            <tr>
                                <td><?php echo $deposit['amount']; ?></td>
                                <td><?php echo ucfirst($deposit['payment_method']); ?></td>
                                <td><span><?php echo ucfirst($deposit['status']); ?></span></td>
                                <td><?php echo $deposit['reference']; ?></td>
                                <td><?php echo date('Y-m-d H:i:s', strtotime($deposit['created_at'])); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else : ?>
                <p class="no-deposit-message">No deposit history available.</p>
            <?php endif; ?>
        </div>
    </div>



    <nav class="bottom-navbar">
        <ul>
            <li><a href="homepage.php"><i class="fas fa-home"></i> Home</a></li>
            <li><a href="resources.php"><i class="fas fa-folder"></i> Resources</a></li>
            <li><a href="topup.php"><i class="fas fa-plus-circle"></i> Top Up</a></li>
            <li><a href="logout.php"><i class="fas fa-sign-out-alt"></i> Logout</a></li>
        </ul>
   </nav>

    <script>
        function toggleCryptoOptions(paymentMethod) {
            if (paymentMethod === 'crypto') {
                document.getElementById('crypto_options').style.display = 'block';
            } else {
                document.getElementById('crypto_options').style.display = 'none';
            }
        }
    </script>
    <script src=""></script>
</body>
</html>
