<?php
require_once '../session_check.php';

// Check if user is customer
if ($_SESSION['user_type'] !== 'customer') {
    header("Location: ../unauthorized.php");
    exit();
}

$user_id = $_SESSION['user_id'];

// Database connection
require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Get order ID from URL
$order_id = isset($_GET['order_id']) ? intval($_GET['order_id']) : 0;

$order = null;
$order_items = [];
$cancellable = false;
$message = '';

if ($order_id > 0) {
    // Fetch order details
    $order_query = "SELECT o.*, r.restaurant_name 
                    FROM orders o
                    JOIN restaurants r ON o.restaurant_id = r.restaurant_id
                    WHERE o.order_id = ? AND o.customer_id = ?";
    $stmt = $conn->prepare($order_query);
    $stmt->bind_param('ii', $order_id, $user_id);
    $stmt->execute();
    $order_result = $stmt->get_result();

    if ($order_result->num_rows > 0) {
        $order = $order_result->fetch_assoc();
        
        // Check if order is cancellable (adjust statuses based on your logic in cancel_order.php)
        if ($order['status'] === 'processing' || $order['status'] === 'preparing') { // Example statuses
            $cancellable = true;
        } else {
            $message = 'This order cannot be cancelled as its status is \'' . ucfirst($order['status']) . '\'.';
        }
        
        // Fetch order items
        $items_query = "SELECT f.name, f.price, oi.quantity 
                        FROM order_items oi
                        JOIN foods f ON oi.item_id = f.food_id
                        WHERE oi.order_id = ?";
        $stmt_items = $conn->prepare($items_query);
        $stmt_items->bind_param('i', $order_id);
        $stmt_items->execute();
        $items_result = $stmt_items->get_result();
        while ($item = $items_result->fetch_assoc()) {
            $order_items[] = $item;
        }
        $stmt_items->close();
        
    } else {
        $message = 'Order not found or does not belong to your account.';
    }
    $stmt->close();
} else {
    $message = 'Invalid order ID.';
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Confirm Cancellation | FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f7fa;
            padding: 30px;
        }
        .confirm-container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 30px;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
    </style>
</head>
<body>
    <div class="confirm-container">
        <h2 class="mb-4">Confirm Order Cancellation</h2>
        
        <?php if ($order): ?>
            <p><strong>Order ID:</strong> <?php echo $order['order_id']; ?></p>
            <p><strong>Restaurant:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
            <p><strong>Current Status:</strong> <span class="badge bg-info"><?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?></span></p>
            
            <?php if (!empty($order_items)): ?>
                <h5 class="mt-4">Order Items:</h5>
                <ul class="list-group mb-4">
                    <?php foreach ($order_items as $item): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center">
                            <?php echo htmlspecialchars($item['name']); ?> x <?php echo $item['quantity']; ?>
                            <span>XAF <?php echo number_format($item['price'] * $item['quantity'], 0); ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
            
            <?php if ($cancellable): ?>
                <div class="alert alert-warning" role="alert">
                    <i class="fas fa-exclamation-triangle me-2"></i> Are you sure you want to cancel this order?
                </div>
                <form action="cancel_order.php" method="POST" class="d-grid gap-2">
                    <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                    <button type="submit" class="btn btn-danger btn-lg">Confirm Cancellation</button>
                    <a href="order_tracking.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-secondary btn-lg">Back to Order Details</a>
                </form>
            <?php else: ?>
                <div class="alert alert-info" role="alert">
                    <i class="fas fa-info-circle me-2"></i> <?php echo $message; ?>
                </div>
                <a href="order_tracking.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-primary btn-lg d-grid gap-2">Back to Order Details</a>
            <?php endif; ?>
            
        <?php else: ?>
            <div class="alert alert-danger" role="alert">
                <i class="fas fa-times-circle me-2"></i> <?php echo $message; ?>
            </div>
            <a href="orders.php" class="btn btn-primary">Back to My Orders</a>
        <?php endif; ?>
        
    </div>
</body>
</html> 