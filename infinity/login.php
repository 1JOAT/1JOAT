<?php
// Check if session is already active before starting a new one
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require 'includes/db.php';

$email_err = $password_err = $login_err = $email = $password = "";

// Process form data when submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Check if email is empty
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter your email.";
    } else {
        $email = trim($_POST["email"]);
    }
    
    // Check if password is empty
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter your password.";
    } else {
        $password = trim($_POST["password"]);
    }
    
    // Validate credentials
    if (empty($email_err) && empty($password_err)) {
        // Prepare a select statement
        $sql = "SELECT id, email, password FROM users WHERE email = ?";
        
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = $email;
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                $stmt->store_result();
                
                // Check if email exists, if yes then verify password
                if ($stmt->num_rows == 1) {
                    $stmt->bind_result($id, $email, $hashed_password);
                    if ($stmt->fetch()) {
                        if (password_verify($password, $hashed_password)) {
                            // Password is correct, so start a new session
                            if (session_status() === PHP_SESSION_NONE) {
                                session_start();
                            }

                            // Store data in session variables
                            $_SESSION["id"] = $id;
                            $_SESSION["email"] = $email;

                            // Redirect user to homepage
                            header("Location: homepage.php");
                            exit;
                        } else {
                            // Password is not valid, display a generic error message
                            $login_err = "Invalid email or password.";
                        }
                    }
                } else {
                    // Email doesn't exist, display a generic error message
                    $login_err = "Invalid email or password.";
                }
            } else {
                echo "Oops! Something went wrong. Please try again later.";
            }

            // Close statement
            $stmt->close();
        }
    }

    // Close connection
    $conn->close();
}
?>


<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Infinity Forex Academy</title>
    <link rel="shortcut icon" href="images/fav.png" type="image/x-icon">
    <link rel="stylesheet" href="styles/signup.css">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/nav.css">
    <meta name="description" content="Login to your account on Infinity Forex Academy to access your dashboard.">
    
    <meta property="og:image" content="https://www.infinityfa.com.ng/images/fav.png"> 
        <script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "Organization",
              "name": "Infinity Forex Academy",
              "url": "https://www.infinityfa.com.ng",
              "logo": "https://www.infinityfa.com.ng/images/fav.png",
              "contactPoint": [
                {
                  "@type": "ContactPoint",
                  "email": "support@infinityfa.com.ng",
                  "contactType": "Customer Service"
                }
              ],
              "sameAs": [
                "https://www.facebook.com/infinityforexacademy",
                "https://twitter.com/infinityfa"
              ]
            }
          </script>

</head>
<body>
    <header>
        <nav class="navbar" aria-label="Main navigation">
            <div class="logo">
                <a href="index.html" aria-label="Home"><img loading="lazy" src="images/fav.png" alt="Infinity Forex Logo"></a>
            </div>
            <div class="name">
                <p><span>Infinity</span> Forex Academy</p>
            </div>
            <ul class="nav-links">
                <li><a href="index.html" aria-label="Home">Home 🏠</a></li>
                <li><a href="signup.php" aria-label="Signup">Signup 🚀</a></li>
                <li><a href="login.php" aria-label="Login">Login 🔑</a></li>
                <li><a href="contact.html" aria-label="Contact Us">Contact us 📞</a></li>
                <li><a href="blog.html" aria-label="Blog">Blog 📝</a></li>
            </ul>
            <div class="burger" aria-label="Menu">
                <div class="line1"></div>
                <div class="line2"></div>
                <div class="line3"></div>
            </div>
        </nav>
    </header>
    <div class="container">
        <h2>Login</h2>
        <p>Please fill in your credentials to login.</p>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <p style="color: red;"><?php echo $login_err; ?></p>
            <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo $email; ?>">
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label>Password</label>
                <input type="password" name="password" class="form-control">
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Login">
            </div>
            <p>Don't have an account? <a href="signup.php">Sign up here</a>.</p>
            <p>Forgot Password? Write to <a href="contact.html">support</a> to change your password.</p>
        </form>
    </div>
    <script src="js/nav.js"></script>
</body>
</html>
