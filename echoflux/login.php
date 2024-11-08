<?php 
//   error_reporting(E_ALL);
//   ini_set('display_errors', 1);

  session_start();
  require 'includes/db.php';

  // Redirect to homepage if already logged in
  if (isset($_SESSION['user_id'])) {
    header('Location: homepage.php');
    exit();
  }

  if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $email = $conn->real_escape_string($_POST['email']);
    $password = $_POST['password'];

    $stmt = $conn->prepare("SELECT id, password FROM users WHERE email = ?");
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows === 1) {
      $stmt->bind_result($user_id, $hashed_password);
      $stmt->fetch();

      if (password_verify($password, $hashed_password)) {
        $_SESSION['user_id'] = $user_id;
        $_SESSION['message'] = "Login successful. Welcome!";
        header('Location: homepage.php');
        exit();
      } else {
        $_SESSION['message'] = "Invalid email or password.";
      }
    } else {
      $_SESSION['message'] = "Invalid email or password.";
    }
    $stmt->close();
    $conn->close();
  }
?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <meta name="description" content="Sign in to EchoFlux to access your account, complete tasks, earn money, and develop new digital skills. Stay connected and unlock the full potential of the EchoFlux platform.">

  <title>Login - Echoflux</title>
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
            <li><a href="vendors.php">Vendors 💵</a></li>
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
    <form action="login.php" method="post">
      <h2>Login | Echoflux</h2>
      <?php if (isset($_SESSION['message'])): ?>
        <div class='message'><?= htmlspecialchars($_SESSION['message']) ?></div>
        <?php unset($_SESSION['message']); ?>
      <?php endif; ?>
      <div class="email-div">
        <label for="email">Email:</label>
        <input type="email" name="email" placeholder="johndoe@example.com" required>
      </div>
      <div class="password-div">
          <label for="password" class="confirmpassword">Password:</label>
          <button type="button" id="togglePassword" class="toggle-password">Show</button>
          <input id="password" type="password" name="password" required>
      </div>
      <div class="button-div">
        <button type="submit">Login</button>
      </div>
      <h3>Don't have an account? <a href="signup.php">Sign up</a></h3>
      <h3>Forgot your password? <a href="forgot_password.php">Click here</a></h3>
    </form>
  </div>
  <script src="js/index.js"></script>
  <script src="js/nav.js"></script>
</body>
</html>
