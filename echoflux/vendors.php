<?php
session_start();
require 'includes/db.php';



// Fetch all vendors
$stmt = $conn->prepare("SELECT id, username, whatsapp_link FROM vendors");
$stmt->execute();
$result = $stmt->get_result();
$vendors = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="description" content="Find authorized vendors to purchase your EchoFlux tokens. Securely buy tokens and start enjoying all the benefits EchoFlux has to offer.">

    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vendors - Echoflux</title>
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="css/vendors.css">
    <link rel="stylesheet" href="css/nav.css">
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
        <h2>Verified Vendors List</h2>
        <table>
            <thead>
                <tr>
                    <th>Username</th>
                    <th>WhatsApp Link</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($vendors as $vendor): ?>
                    <tr>
                        <td><?= htmlspecialchars($vendor['username']) ?></td>
                        <td><a href="<?= htmlspecialchars($vendor['whatsapp_link']) ?>" target="_blank">Chat on WhatsApp</a></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <script src="js/nav.js"></script>
</body>
</html>
