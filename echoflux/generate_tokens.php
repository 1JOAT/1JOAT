<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require 'includes/db.php';
require 'includes/function.php';

function generateUniqueToken($conn) {
    do {
        // generate a random token
        $token = bin2hex(random_bytes(10));
        $query = "SELECT id FROM registration_tokens WHERE token = ?";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        $stmt->store_result(); 
    } while ($stmt->num_rows > 0); // ensures the token is unique

    return $token;
}

function generateTokens($conn, $quantity) {
    $tokens = []; // Array to hold generated tokens
    for ($i = 0; $i < $quantity; $i++) { 
        $token = generateUniqueToken($conn);
        $query = "INSERT INTO registration_tokens (token, status) VALUES (?, 'completed')";
        $stmt = $conn->prepare($query);
        $stmt->bind_param("s", $token);
        $stmt->execute();
        
        $tokens[] = $token; // Store the generated token
    }
    return $tokens; // Return the array of generated tokens
}
?>
