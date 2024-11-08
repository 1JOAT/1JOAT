<?php
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

require 'includes/db.php';

$userId = $_SESSION['user_id'];
$cost = 10000;

// Fetch user's current points balance
$stmt = $conn->prepare("SELECT points FROM users WHERE id = ?");
$stmt->bind_param("i", $userId);
$stmt->execute();
$result = $stmt->get_result();
$user = $result->fetch_assoc();
$stmt->close();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if ($user['points'] < $cost) {
        echo json_encode(['error' => 'Insufficient points']);
        exit();
    }

    // Deduct points from user's account
    $stmt = $conn->prepare("UPDATE users SET points = points - ? WHERE id = ?");
    $stmt->bind_param("ii", $cost, $userId);
    $stmt->execute();
    $stmt->close();

    $prizes = [
        ['name' => 'lite', 'points' => 5000, 'weight' => 55],
        ['name' => 'Medium Prize', 'points' => 10000, 'weight' => 30],
        ['name' => 'Large Prize', 'points' => 20000, 'weight' => 2],
        ['name' => 'Large Prize', 'points' => 50000, 'weight' => 1],
        ['name' => 'Large Prize', 'points' => 100000, 'weight' => 1],
        ['name' => 'Elite', 'points' => 500000, 'weight' => 1],
        ['name' => 'No Prize', 'points' => 0, 'weight' => 10]
    ];

    function weightedRandom($items) {
        $totalWeight = array_reduce($items, function($carry, $item) {
            return $carry + $item['weight'];
        }, 0);

        $random = mt_rand(0, $totalWeight - 1);

        foreach ($items as $item) {
            $random -= $item['weight'];
            if ($random < 0) {
                return $item;
            }
        }
    }

    $randomPrize = weightedRandom($prizes);

    // Add points to user's account
    $stmt = $conn->prepare("UPDATE users SET points = points + ? WHERE id = ?");
    $stmt->bind_param("ii", $randomPrize['points'], $userId);
    $stmt->execute();
    $stmt->close();

    // Store the result in the database
    $stmt = $conn->prepare("INSERT INTO spin_results (user_id, prize_name, points_won) VALUES (?, ?, ?)");
    $stmt->bind_param("isi", $userId, $randomPrize['name'], $randomPrize['points']);
    $stmt->execute();
    $stmt->close();

    // Return the result to the client along with prize details
    echo json_encode([
        'prizes' => $prizes,
        'result' => $randomPrize
    ]);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Spin and Win - Echoflux</title>
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="css/nav.css">
    <link rel="stylesheet" href="css/spin.css">
    <link rel="shortcut icon" href="images/favnew.png" type="image/x-icon">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11/dist/sweetalert2.min.css">
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
                <li><a href="spin.php">Win points</a></li>
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
        <section class="spin-section">
            <div class="loading-spinner"></div>

            <h2>Win points</h2>
            <p><strong>Your current points:</strong> <?= htmlspecialchars($user['points']) ?> ✨</p>
            <p>Pay <strong>10000</strong> points to take a chance and win more points!! 🎉</p>

            <div id="wheel">
                <button id="spin-button">Win Now🏅</button>
            </div><br><hr><br><br><br>
            <div id="prizes-list">
                <h3>Available Points to be won;<hr>0 points <br> 5000 points <br> 10000 points <br> 20000 points <br> 50000 points <br> 100000 points <br> 500000 points </h3>
            </div>
        </section>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
    <script>
        document.getElementById('spin-button').addEventListener('click', function() {
            // Show loading spinner
            document.querySelector('.loading-spinner').style.display = 'block';

            fetch('spin.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                }
            })
            .then(response => response.json())
            .then(data => {
                // Hide loading spinner
                document.querySelector('.loading-spinner').style.display = 'none';

                if (data.error) {
                    Swal.fire({
                        title: 'Oops...',
                        text: data.error,
                        icon: 'error',
                        confirmButtonText: 'OK'
                    });
                } else {
                    // Simulate spinning animation with a delay
                    setTimeout(() => {
                        if (data.result.points > 0) {
                            Swal.fire({
                                title: 'Congratulations!',
                                text: `You won ${data.result.name}: ${data.result.points} points!`,
                                icon: 'success',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload(); // Refresh the page to update points
                            });
                        } else {
                            Swal.fire({
                                title: 'Sorry!',
                                text: 'You did not win this time. Better luck next time!',
                                icon: 'error',
                                confirmButtonText: 'OK'
                            }).then(() => {
                                location.reload(); // Refresh the page to update points
                            });
                        }
                    }, 100); // Simulate a 2-second spin animation
                }
            });
        });
    </script>
    <script src="js/nav.js"></script>
</body>
</html>
