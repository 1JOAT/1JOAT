<?php
require 'includes/db.php'; // Include your database connection file

// Paystack secret key (replace with your actual key)
$paystack_secret_key = 'YOUR_PAYSTACK_SECRET_KEY';

// Retrieve the raw POST data from Paystack
$input = @file_get_contents("php://input");

// Log the raw data for debugging 
error_log("Received Paystack webhook data: " . $input);

// Decode the JSON data
$event = json_decode($input);

// Verify the webhook signature 
$signature = (isset($_SERVER['HTTP_X_PAYSTACK_SIGNATURE'])) ? $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] : '';

if (!$signature || ($signature !== hash_hmac('sha512', $input, $paystack_secret_key))) {
    // Invalid signature
    error_log("Invalid Paystack webhook signature.");
    http_response_code(400); // Bad Request
    exit();
}

// Check if the event is a successful charge.event
if ('charge.success' == $event->event) {
    // Get the transaction reference from the webhook data
    $reference = $event->data->reference;

    // Retrieve the transaction details from your database
    $sql = "SELECT user_id, amount FROM transactions WHERE reference = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $reference);
    $stmt->execute();
    $stmt->bind_result($user_id, $amount);
    $stmt->fetch();
    $stmt->close();

    // Update the transaction status to 'success'
    $sql = "UPDATE transactions SET status = 'success' WHERE reference = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $reference);
    if (!$stmt->execute()) {
        // Log the error for debugging
        error_log("Error updating transaction status: " . $stmt->error);
    }
    $stmt->close();

    // Update the user's balance
    $sql = "UPDATE users SET balance = balance + ? WHERE id = ?";
    $stmt = $conn->prepare($sql);
    $stmt->bind_param("di", $amount, $user_id);
    if (!$stmt->execute()) {
        // Log the error for debugging
        error_log("Error updating user balance: " . $stmt->error);
    }
    $stmt->close();

}

http_response_code(200); // Send a 200 OK response to Paystack
?>