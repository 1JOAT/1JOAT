<?php 

    // error_reporting(E_ALL);
    // ini_set('display_errors', 1);

    session_start();
    require 'includes/db.php';

    if (!isset($_SESSION['user_id'])) {
        $_SESSION['message'] = "Session expired or invalid.";
        header('Location: forgot_password.php');
        exit();
    }

    if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['new_password'])) {
        $user_id = $_SESSION['user_id'];
        $new_password = $_POST['new_password'];

        // Validate new password
        if (strlen($new_password) < 8 || !preg_match('/\d/', $new_password)) {
            $_SESSION['message'] = "Password must be at least 8 characters long and contain at least one number.";
            header('Location: reset_password.php'); // Redirect back
            exit();
        }

        // Update the user's password
        $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
        $stmt = $conn->prepare("UPDATE users SET password = ? WHERE id = ?");
        $stmt->bind_param("si", $hashed_password, $user_id); 
        $stmt->execute();
        $stmt->close();

        // Clear session data
        unset($_SESSION['user_id']);
        unset($_SESSION['security_question']);

        $_SESSION['message'] = "Password has been reset successfully.";
        header('Location: login.php');
        exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Echoflux</title>
    <link rel="stylesheet" href="css/signup.css">
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
      <style>
       body{
            font-family: 'Gill Sans', 'Gill Sans MT', Calibri, 'Trebuchet MS', sans-serif;
        }
  </style>
    
</head>
<body>
    <div class="container">
        <form action="reset_password.php" method="post" autocomplete="off">
            <h2>Reset Password</h2>
            <?php if (isset($_SESSION['message'])): ?>
                <div class='message'><?= htmlspecialchars($_SESSION['message']) ?></div>
                <?php unset($_SESSION['message']); ?>
            <?php endif; ?>
            <div class="password-div">
                <label for="new_password">New Password:</label>
                <input type="password" placeholder="Min 8 characters, must contain a number" name="new_password" required>
            </div>
            <div class="button-div">
                <button type="submit">Reset Password</button>
            </div>
        </form>
    </div>
</body>
</html>
