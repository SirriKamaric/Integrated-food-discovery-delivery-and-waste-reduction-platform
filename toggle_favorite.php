<?php
require_once '../session_check.php';
require_once '../db_config.php';

header('Content-Type: application/json');

// Check if user is customer and logged in
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    echo json_encode(['success' => false, 'message' => 'Unauthorized access.']);
    exit();
}

$user_id = $_SESSION['user_id'];

// Get POST data
$restaurant_id = isset($_POST['restaurant_id']) ? intval($_POST['restaurant_id']) : 0;
$action = isset($_POST['action']) ? $_POST['action'] : '';

if ($restaurant_id <= 0) {
    echo json_encode(['success' => false, 'message' => 'Invalid restaurant ID.']);
    exit();
}

// Database connection
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    echo json_encode(['success' => false, 'message' => 'Database connection failed: ' . $conn->connect_error]);
    exit();
}

$success = false;
$message = '';

if ($action === 'add') {
    // Check if it's already a favorite to prevent duplicate entries
    $stmt_check = $conn->prepare("SELECT COUNT(*) FROM customer_favorites WHERE customer_id = ? AND restaurant_id = ?");
    $stmt_check->bind_param("ii", $user_id, $restaurant_id);
    $stmt_check->execute();
    $stmt_check->bind_result($count);
    $stmt_check->fetch();
    $stmt_check->close();

    if ($count == 0) {
        $stmt = $conn->prepare("INSERT INTO customer_favorites (customer_id, restaurant_id, created_at) VALUES (?, ?, NOW())");
        $stmt->bind_param("ii", $user_id, $restaurant_id);
        if ($stmt->execute()) {
            $success = true;
            $message = 'Restaurant added to favorites.';
        } else {
            $message = 'Error adding to favorites: ' . $stmt->error;
        }
        $stmt->close();
    } else {
        $success = true; // Already a favorite, consider it a success
        $message = 'Restaurant is already in favorites.';
    }
} elseif ($action === 'remove') {
    $stmt = $conn->prepare("DELETE FROM customer_favorites WHERE customer_id = ? AND restaurant_id = ?");
    $stmt->bind_param("ii", $user_id, $restaurant_id);
    if ($stmt->execute()) {
        $success = true;
        $message = 'Restaurant removed from favorites.';
    } else {
        $message = 'Error removing from favorites: ' . $stmt->error;
    }
    $stmt->close();
} else {
    $message = 'Invalid action specified.';
}

$conn->close();

echo json_encode(['success' => $success, 'message' => $message]);
?> 