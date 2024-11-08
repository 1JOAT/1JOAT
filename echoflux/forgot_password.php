<?php 

    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

    session_start();
    require 'includes/db.php';

    if ($_SERVER["REQUEST_METHOD"] == "POST") {
        $username = filter_input(INPUT_POST, "username", FILTER_SANITIZE_STRING);
        $security_answer = filter_input(INPUT_POST, "security_answer", FILTER_SANITIZE_STRING);

        // Check if username exists in the database
        $stmt = $conn->prepare("SELECT id, security_answer FROM users WHERE username = ?");
        $stmt->bind_param("s", $username);
        $stmt->execute();
        $stmt->store_result();

        if ($stmt->num_rows === 1) {
            $stmt->bind_result($user_id, $hashed_security_answer);
            $stmt->fetch();
            $stmt->close();

            // Verify the security answer
            if (password_verify($security_answer, $hashed_security_answer)) {
                // Store user ID in session
                $_SESSION['user_id'] = $user_id;
                header('Location: reset_password.php'); // Redirect to password reset page
            } else {
                $_SESSION['message'] = "Invalid security answer.";
                header('Location: forgot_password.php');
            }
        } else {
            $_SESSION['message'] = "Invalid username.";
            header('Location: forgot_password.php');
        }

        $conn->close();
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Your Website</title>
    <link rel="stylesheet" href="css/signup.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
      <style>
       body{
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
    <div class="container">
        <form action="forgot_password.php" method="post" autocomplete="off">
            <h2>Forgot Password</h2>
            <?php if (isset($_SESSION['message'])): ?>
                <div class='message'><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <div class="username-div">
                <label for="username">Username:</label>
                <input type="text" placeholder="johndoe" name="username" required>
            </div>
            <div class="security-answer-div">
                <label for="security_answer">Security Answer:</label>
                <input type="text" placeholder="Your answer" name="security_answer" required>
            </div>
            <div class="button-div">
                <button type="submit">Verify</button>
            </div>
            <h3>Remembered your password? <a href="login.php">Login</a></h3>
        </form>
    </div>

    <script src="js/nav.js"></script>
</body>
</html>
