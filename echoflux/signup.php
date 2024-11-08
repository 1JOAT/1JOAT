<?php
session_start(); // Ensure session is started

require 'includes/db.php';

// Function to generate a unique referral code
function generateUniqueReferralCode($conn) {
    do {
        $referral_code = strtoupper(bin2hex(random_bytes(4))); // Generate an 8-character unique code
        $stmt = $conn->prepare("SELECT COUNT(*) FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $referral_code);
        $stmt->execute();
        $stmt->bind_result($count);
        $stmt->fetch();
        $stmt->close();
    } while ($count > 0); // Ensure uniqueness

    return $referral_code;
}

// Function to validate password
function validatePassword($password) {
    return preg_match('/^(?=.*[A-Za-z])(?=.*\d)(?=.*[\W_])[A-Za-z\d\W_]{8,}$/', $password);
}

// Retrieve referral code from URL if available
$referral_code = '';
if (isset($_GET['ref']) && !empty($_GET['ref'])) {
    $referral_code = htmlspecialchars($_GET['ref']);
}

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $firstname = filter_input(INPUT_POST, "firstname", FILTER_SANITIZE_SPECIAL_CHARS);
    $lastname = filter_input(INPUT_POST, "lastname", FILTER_SANITIZE_SPECIAL_CHARS);
    $username = filter_input(INPUT_POST, "username", FILTER_SANITIZE_SPECIAL_CHARS);
    $email = filter_input(INPUT_POST, "email", FILTER_SANITIZE_EMAIL);
    $phone_number = substr(filter_input(INPUT_POST, "phone_number", FILTER_SANITIZE_SPECIAL_CHARS), 0, 20);
    $password = filter_input(INPUT_POST, "password", FILTER_SANITIZE_SPECIAL_CHARS);
    $confirmpassword = filter_input(INPUT_POST, "confirmpassword", FILTER_SANITIZE_SPECIAL_CHARS);
    $referral_code = filter_input(INPUT_POST, "referral_code", FILTER_SANITIZE_SPECIAL_CHARS);
    $security_question = filter_input(INPUT_POST, "security_question", FILTER_SANITIZE_SPECIAL_CHARS);
    $security_answer = filter_input(INPUT_POST, "security_answer", FILTER_SANITIZE_SPECIAL_CHARS);

    // Begin a transaction
    $conn->begin_transaction();

    try {
        if ($password !== $confirmpassword) {
            throw new Exception("Passwords do not match.");
        }

        if (!validatePassword($password)) {
            throw new Exception("Password must be at least 8 characters long and contain letters, numbers, and special characters.");
        }

        if (strlen($username) < 4) {
            throw new Exception("Username must be at least 4 characters long.");
        }

        $stmt = $conn->prepare("SELECT username FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            throw new Exception("Username already exists. Please choose a different username.");
        }

        $stmt = $conn->prepare("SELECT email FROM users WHERE email = ?");
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            throw new Exception("Email already exists. Please use a different email.");
        }

        $stmt = $conn->prepare("SELECT phone_number FROM users WHERE phone_number = ?");
        $stmt->bind_param("s", $phone_number);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows > 0) {
            throw new Exception("Phone number already exists. Please use a different phone number.");
        }

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        $hashed_security_answer = password_hash($security_answer, PASSWORD_DEFAULT);

        $unique_referral_code = generateUniqueReferralCode($conn);

        $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, phone_number, password, referral_code, security_question, security_answer) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->bind_param("sssssssss", $firstname, $lastname, $username, $email, $phone_number, $hashed_password, $unique_referral_code, $security_question, $hashed_security_answer);

        if ($stmt->execute()) {
            $user_id = $conn->insert_id; // Get the inserted user ID

            if (!empty($referral_code)) {
                $stmt = $conn->prepare("SELECT id, referral_count FROM users WHERE referral_code = ?");
                $stmt->bind_param("s", $referral_code);
                $stmt->execute();
                $stmt->store_result();

                if ($stmt->num_rows === 1) {
                    $stmt->bind_result($referrer_id, $referral_count);
                    $stmt->fetch();
                    $stmt->close();

                    if ($referral_count < 25) { // Max 25 referrals
                        $stmt = $conn->prepare("INSERT IGNORE INTO referrals (referrer_id, referred_id) VALUES (?, ?)");
                        $stmt->bind_param("ii", $referrer_id, $user_id);
                        $stmt->execute();
                        $stmt->close();

                        $points = 2500; // Points credited for successful referral
                        $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
                        $stmt->bind_param("ii", $points, $referrer_id);
                        $stmt->execute();
                        $stmt->close();

                        $stmt = $conn->prepare("UPDATE users SET referral_count = referral_count + 1 WHERE id = ?");
                        $stmt->bind_param("i", $referrer_id);
                        $stmt->execute();
                        $stmt->close();
                    }
                } else {
                    throw new Exception("Invalid referral code.");
                }
            }

            $conn->commit();

            $_SESSION['message'] = "Sign-up successful. Please log in.";
            header('Location: login.php');
            exit();
        } else {
            throw new Exception("Error: Could not sign up.");
        }
    } catch (Exception $e) {
        $conn->rollback();
        $_SESSION['message'] = $e->getMessage();
        header('Location: signup.php');
        exit();
    } finally {
        $stmt->close();
        $conn->close();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Signup - Echoflux</title>
    <link rel="stylesheet" href="css/signup.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/style.css">
    <link rel="icon" href="Images/favnew.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Dekko&display=swap" rel="stylesheet">
    <meta name="description" content="Join EchoFlux today! Sign up to start earning points through tasks, referrals, and more while enhancing your digital skills. Register now and be part of the EchoFlux community.">
    <style>
        body{
            padding: 60px 20px;
            font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif;
        }
    </style>
</head>
<body>
    <header>
        <nav class="navbar">
            <div class="logo">
                <a href="index.html"><img src="images/logo2.png" alt="Echoflux Logo"></a>
            </div>
            <ul class="nav-links">
                <li><a href="index.html">Home 🏠</a></li>
                <li><a href="signup.php">Signup 🚀</a></li>
                <li><a href="contact.php">Contact us 📞</a></li>
                <li><a href="blog.html">Blog 📝</a></li>
            </ul>
            <div class="burger">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </header>
    <div>
        <h2>What you should know before registering...</h2>
        <p>1. Input correct names matching your bank account as different name on account and different numbers would result to <strong>Failed withdrawal</strong>.</p>
        <p>2. Make sure to carefully input your Phone numbers, as they would be used to add the digital learners to a private group.</p>
        <p>3. Passwords must be at least 8 characters containing letters, numbers, and special characters.</p>
        <p>4. Multiple accounts would be banned permanently if noticed.</p>
        <p>5. Keep your security questions and answers safe from anybody, as that is the <strong>only</strong> way you can reset your password if you forget, and anybody who has this answers can reset your password.</p>
    </div>
    <div class="container">
        <form action="signup.php" method="post" autocomplete="off">
            <h2>Sign Up | Echoflux</h2>
            <?php if (isset($_SESSION['message'])): ?>
                <div class='message'><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <div class="name-div">
                <label for="firstname">Firstname:</label>
                <input type="text" placeholder="John" name="firstname" required>
                <label for="lastname">Lastname:</label>
                <input type="text" placeholder="Doe" name="lastname" required>
            </div>
            <div class="username">
                <label for="username">Username:</label>
                <input type="text" placeholder="johndoe" name="username" required>
            </div>
            <div class="email">
                <label for="email">Email:</label>
                <input type="email" placeholder="john@example.com" name="email" required>
            </div>
            <div class="phone">
                <label for="phone_number">Phone Number:</label>
                <input type="tel" placeholder="0123456789" name="phone_number" required>
            </div>
            <div class="password-div">
                <label for="password" class="confirmpassword">Password:</label>
                <button type="button" id="togglePassword" class="toggle-password">Show</button>
                <input type="password" placeholder="Min 8 characters, must contain a number and special character" name="password" id="password" required>
            </div>
            <div class="confirmpassword">
                <label for="confirmpassword">Confirm Password:</label>
                <button type="button" id="toggleConfirmPassword" class="toggle-password">Show</button>
                <input type="password" placeholder="Confirm your password" name="confirmpassword" required id="confirmPassword">
            </div>
            <div class="referral">
                <label for="referral_code">Referral Code (Optional):</label>
                <input type="text" placeholder="Referral Code" name="referral_code" value="<?= htmlspecialchars($referral_code) ?>">
            </div>
            <div class="security">
                <label for="security_question">Security Question:</label>
                <select name="security_question" required>
                    <option value="">Select a question</option>
                    <option value="What was the name of your first pet?">What was the name of your first pet?</option>
                    <option value="What is your mother's maiden name?">What is your mother's maiden name?</option>
                    <option value="What was the name of your elementary school?">What was the name of your elementary school?</option>
                    <option value="What is your favorite book?">What is your favorite book?</option>
                    <option value="What is your favorite movie?">What is your favorite movie?</option>
                </select>
                <label for="security_answer">Answer:</label>
                <input type="text" placeholder="Your answer" name="security_answer" required>
            </div>
            <div class="token">
                <input type="checkbox" required>
                <label for="token">By signing up, you agree to Echoflux <a href="terms.html">terms and privacy policy</a>.</label>
            </div>

            <div class="button-div">
                <button class="btn-primary" type="submit">Sign Up</button>
            </div>
            <h3>Already have an account? <a href="login.php">Login</a></h3>
        </form>
    </div>

    <script src="js/index.js"></script>
    <script src="js/nav.js"></script>
</body>
</html>
