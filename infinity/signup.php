<?php

    // Check if session is already active before starting a new one
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }

  require 'includes/db.php';

   // Initialize variables to store error messages
    $first_name_err = $last_name_err = $username_err = $email_err = $password_err = $confirm_password_err = $terms_err = $phone_number_err = "";
    $first_name = $last_name = $username = $email = $password = $confirm_password = $phone_number = "";

    // Processing form data when the form is submitted
    
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Validate first name
    if (empty(trim($_POST["first_name"]))) {
        $first_name_err = "Please enter your first name.";
    } else {
        $first_name = trim($_POST["first_name"]);
    }

    // Validate last name
    if (empty(trim($_POST["last_name"]))) {
        $last_name_err = "Please enter your last name.";
    } else {
        $last_name = trim($_POST["last_name"]);
    }

    // Validate username
    if (empty(trim($_POST["username"]))) {
        $username_err = "Please enter a username.";
    } elseif (strlen(trim($_POST["username"])) < 4) {
        $username_err = "Username must be at least 4 characters.";
    }else {
        // Check if username already exists
        $sql = "SELECT id FROM users WHERE username = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_username);
            $param_username = trim($_POST["username"]);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $username_err = "This username is already taken.";
            } else {
                $username = trim($_POST["username"]);
            }
            $stmt->close();
        }
    }

    // Validate phone number
    if (empty(trim($_POST["phone_number"]))) {
        $phone_number_err = "Please enter your phone number.";
    } else {
        // Check if phone number already exists
        $sql = "SELECT id FROM users WHERE phone_number = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_phone_number);
            $param_phone_number = trim($_POST["phone_number"]);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $phone_number_err = "This phone number is already registered.";
            } else {
                $phone_number = trim($_POST["phone_number"]);
            }
            $stmt->close();
        }
    }

    // Validate email
    if (empty(trim($_POST["email"]))) {
        $email_err = "Please enter an email.";
    } elseif (!filter_var(trim($_POST["email"]), FILTER_VALIDATE_EMAIL)) {
        $email_err = "Please enter a valid email.";
    } else {
        // Check if email already exists
        $sql = "SELECT id FROM users WHERE email = ?";
        if ($stmt = $conn->prepare($sql)) {
            $stmt->bind_param("s", $param_email);
            $param_email = trim($_POST["email"]);
            $stmt->execute();
            $stmt->store_result();
            if ($stmt->num_rows > 0) {
                $email_err = "This email is already registered.";
            } else {
                $email = trim($_POST["email"]);
            }
            $stmt->close();
        }
    }

    // Validate password
    if (empty(trim($_POST["password"]))) {
        $password_err = "Please enter a password.";
    } elseif (strlen(trim($_POST["password"])) < 6) {
        $password_err = "Password must be at least 6 characters.";
    } else {
        $password = trim($_POST["password"]);
    }

    // Validate confirm password
    if (empty(trim($_POST["confirm_password"]))) {
        $confirm_password_err = "Please confirm the password.";
    } else {
        $confirm_password = trim($_POST["confirm_password"]);
        if (empty($password_err) && ($password != $confirm_password)) {
            $confirm_password_err = "Password did not match.";
        }
    }

    // Validate terms and conditions
    if (!isset($_POST['terms'])) {
        $terms_err = "You must agree to the terms and conditions.";
    }

    // Check for input errors before inserting into the database
    if (empty($first_name_err) && empty($last_name_err) && empty($username_err) && empty($email_err) && empty($password_err) && empty($confirm_password_err) && empty($terms_err) && empty($phone_number_err)) {
        // Prepare an insert statement
        $sql = "INSERT INTO users (first_name, last_name, username, phone_number, email, password) VALUES (?, ?, ?, ?, ?, ?)";
        
        if ($stmt = $conn->prepare($sql)) {
            // Bind variables to the prepared statement as parameters
            $stmt->bind_param("ssssss", $param_first_name, $param_last_name, $param_username, $param_phone_number, $param_email, $param_password);
            
            // Set parameters
            $param_first_name = $first_name;
            $param_last_name = $last_name;
            $param_username = $username;
            $param_phone_number = $phone_number;
            $param_email = $email;
            $param_password = password_hash($password, PASSWORD_DEFAULT); // Hash the password
            
            // Attempt to execute the prepared statement
            if ($stmt->execute()) {
                // Redirect to login page
                header("location: login.php");
                exit();
            } else {
                echo "Something went wrong. Please try again later.";
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

    <title>Sign Up - Infinity Forex Academy</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Mukta:wght@200;300;400;500;600;700;800&display=swap" rel="stylesheet">
    
    <link rel="shortcut icon" href="images/fav.png" type="image/x-icon">
    <link rel="stylesheet" href="styles/nav.css">
    <link rel="stylesheet" href="styles/style.css">
    <link rel="stylesheet" href="styles/signup.css">
    <meta name="description" content="Create a new account on Infinity Forex Academy to get started with our services.">
    
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


        <h2>Sign Up</h2>
        <p>Please fill this form to create an account.</p>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <div class="form-group <?php echo (!empty($first_name_err)) ? 'has-error' : ''; ?>">
                <label>First Name</label>
                <input type="text" name="first_name" class="form-control" value="<?php echo $first_name; ?>">
                <span class="help-block"><?php echo $first_name_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($last_name_err)) ? 'has-error' : ''; ?>">
                <label>Last Name</label>
                <input type="text" name="last_name" class="form-control" value="<?php echo $last_name; ?>">
                <span class="help-block"><?php echo $last_name_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($username_err)) ? 'has-error' : ''; ?>">
                <label>Username</label>
                <input type="text" name="username" class="form-control" value="<?php echo $username; ?>">
                <span class="help-block"><?php echo $username_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($phone_number_err)) ? 'has-error' : ''; ?>">
                <label>Phone Number</label>
                <input type="text" name="phone_number" class="form-control" value="<?php echo $phone_number; ?>">
                <span class="help-block"><?php echo $phone_number_err; ?></span>
            </div>  
            <div class="form-group <?php echo (!empty($email_err)) ? 'has-error' : ''; ?>">
                <label>Email</label>
                <input type="email" name="email" class="form-control" value="<?php echo $email; ?>">
                <span class="help-block"><?php echo $email_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($password_err)) ? 'has-error' : ''; ?>">
                <label>Password</label>
                <input required type="password" name="password" class="form-control" value="<?php echo $password; ?>">
                <span class="help-block"><?php echo $password_err; ?></span>
            </div>
            <div class="form-group <?php echo (!empty($confirm_password_err)) ? 'has-error' : ''; ?>">
                <label>Confirm Password</label>
                <input required type="password" name="confirm_password" class="form-control" value="<?php echo $confirm_password; ?>">
                <span class="help-block"><?php echo $confirm_password_err; ?></span>
            </div>
            <div class="form-group">
                <input type="checkbox" name="terms" value="agree" <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                <label>I agree to the <a href="terms.html" target="_blank">Terms and Conditions</a> and subscribe to the newsletter.</label>
                <span class="help-block"><?php echo $terms_err; ?></span>
            </div>
            <div class="form-group">
                <input type="submit" class="btn btn-primary" value="Sign Up">
            </div>
            <p>Already have an account? <a href="login.php">Login here</a>.</p>
        </form>
    </div>  
    
    <script src="js/nav.js"></script>
</body>
</html>