<?php
session_start();
require_once '../db_config.php';

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Check if user is logged in and is a customer
if (!isset($_SESSION['user_id']) || $_SESSION['user_type'] !== 'customer') {
    header('Location: ../login.php');
    exit();
}

// Check if order ID is provided
if (!isset($_GET['oid'])) {
    header('Location: orders.php');
    exit();
}

$order_id = intval($_GET['oid']);
$user_id = $_SESSION['user_id'];

// Fetch order details with restaurant and address information
$query = "SELECT o.*, r.restaurant_name, r.phone_number AS restaurant_phone,
          a.address_line1, a.address_line2, a.city, a.state, a.postal_code, a.phone_number AS delivery_phone
          FROM orders o
          JOIN restaurants r ON o.restaurant_id = r.restaurant_id
          LEFT JOIN customer_addresses a ON o.delivery_address_id = a.address_id
          WHERE o.order_id = ? AND o.customer_id = ?";

$stmt = $conn->prepare($query);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    header('Location: orders.php');
    exit();
}

$order = $result->fetch_assoc();

// Fetch order items
$items_query = "SELECT oi.*, mi.item_name, mi.image_url
                FROM order_items oi
                JOIN menu_items mi ON oi.item_id = mi.item_id
                WHERE oi.order_id = ?";
$items_stmt = $conn->prepare($items_query);
$items_stmt->bind_param('i', $order_id);
$items_stmt->execute();
$items_result = $items_stmt->get_result();
$order_items = [];
while ($item = $items_result->fetch_assoc()) {
    $order_items[] = $item;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Confirmation - Food Delivery System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .order-status {
            font-size: 1.2rem;
            font-weight: 500;
        }
        .status-preparing { color: #ffc107; }
        .status-processing { color: #0d6efd; }
        .status-ready { color: #198754; }
        .status-delivered { color: #6c757d; }
        .status-cancelled { color: #dc3545; }
    </style>
</head>
<body class="bg-light">
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-md-8">
                <div class="card shadow-sm">
                    <div class="card-body p-4">
                        <div class="text-center mb-4">
                            <i class="fas fa-check-circle text-success" style="font-size: 4rem;"></i>
                            <h2 class="mt-3">Order Confirmed!</h2>
                            <p class="text-muted">Thank you for your order. We'll notify you when it's ready.</p>
                        </div>

                        <div class="row mb-4">
                            <div class="col-md-6">
                                <h5 class="mb-3">Order Details</h5>
                                <p class="mb-1"><strong>Order ID:</strong> #<?php echo str_pad($order['order_id'], 8, '0', STR_PAD_LEFT); ?></p>
                                <p class="mb-1"><strong>Date:</strong> <?php echo date('F j, Y g:i A', strtotime($order['created_at'])); ?></p>
                                <p class="mb-1"><strong>Status:</strong> 
                                    <span class="order-status status-<?php echo strtolower($order['status']); ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </p>
                                <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucwords(str_replace('_', ' ', $order['payment_method'])); ?></p>
                                <p class="mb-0"><strong>Total Amount:</strong> XAF <?php echo number_format($order['total_amount'], 0, ',', ' '); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5 class="mb-3">Restaurant</h5>
                                <p class="mb-1"><strong><?php echo htmlspecialchars($order['restaurant_name']); ?></strong></p>
                                <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($order['restaurant_phone']); ?></p>
                            </div>
                        </div>

                        <div class="mb-4">
                            <h5 class="mb-3">Delivery Address</h5>
                            <p class="mb-1"><?php echo htmlspecialchars($order['address_line1']); ?></p>
                            <?php if ($order['address_line2']): ?>
                                <p class="mb-1"><?php echo htmlspecialchars($order['address_line2']); ?></p>
                            <?php endif; ?>
                            <p class="mb-1"><?php echo htmlspecialchars($order['city'] . ', ' . $order['state'] . ' ' . $order['postal_code']); ?></p>
                            <p class="mb-0"><i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($order['delivery_phone']); ?></p>
                        </div>

                        <div class="mb-4">
                            <h5 class="mb-3">Order Items</h5>
                            <div class="table-responsive">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Item</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-end">Price</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($order_items as $item): ?>
                                            <tr>
                                                <td>
                                                    <div class="d-flex align-items-center">
                                                        <?php if ($item['image_url']): ?>
                                                            <img src="<?php echo htmlspecialchars($item['image_url']); ?>" 
                                                                 alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                                                                 class="me-2" style="width: 40px; height: 40px; object-fit: cover;">
                                                        <?php endif; ?>
                                                        <?php echo htmlspecialchars($item['item_name']); ?>
                                                    </div>
                                                </td>
                                                <td class="text-center"><?php echo $item['quantity']; ?></td>
                                                <td class="text-end">XAF <?php echo number_format($item['price_at_time_of_order'], 0, ',', ' '); ?></td>
                                                <td class="text-end">XAF <?php echo number_format($item['quantity'] * $item['price_at_time_of_order'], 0, ',', ' '); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                    <tfoot>
                                        <tr>
                                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                                            <td class="text-end"><strong>XAF <?php echo number_format($order['total_amount'], 0, ',', ' '); ?></strong></td>
                                        </tr>
                                    </tfoot>
                                </table>
                            </div>
                        </div>

                        <div class="text-center">
                            <a href="orders.php" class="btn btn-primary me-2">
                                <i class="fas fa-list me-2"></i>View All Orders
                            </a>
                            <a href="restaurants.php" class="btn btn-outline-primary">
                                <i class="fas fa-utensils me-2"></i>Order More Food
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 