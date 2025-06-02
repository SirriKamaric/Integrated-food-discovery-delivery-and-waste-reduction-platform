<?php
require_once '../session_check.php';
if ($_SESSION['user_type'] !== 'customer') {
    header("Location: ../unauthorized.php");
    exit();
}
require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$restaurant_id = isset($_GET['rid']) ? intval($_GET['rid']) : 0;
if ($restaurant_id <= 0) {
    die('Invalid restaurant ID.');
}
// Fetch restaurant info
$rest_result = $conn->query("SELECT restaurant_name, address FROM restaurants WHERE restaurant_id = $restaurant_id");
if (!$rest_result || $rest_result->num_rows == 0) {
    die('Restaurant not found.');
}
$restaurant = $rest_result->fetch_assoc();
// Fetch menu items
$menu_result = $conn->query("SELECT * FROM menu_items WHERE restaurant_id = $restaurant_id");
$menu_items = [];
if ($menu_result) {
    while ($row = $menu_result->fetch_assoc()) {
        $menu_items[] = $row;
    }
}

$order_success = false;
$order_error = '';

// Initialize cart if it doesn't exist
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// Handle add to cart submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_to_cart'])) {
    $item_id = intval($_POST['add_to_cart']);
    $quantity = max(1, intval($_POST['quantity']));
    // Check if item exists and belongs to this restaurant
    $item_check_result = $conn->query("SELECT item_id FROM menu_items WHERE item_id = $item_id AND restaurant_id = $restaurant_id");
    if ($item_check_result && $item_check_result->num_rows > 0) {
        // Add or update item in cart session
        if (isset($_SESSION['cart'][$item_id])) {
            $_SESSION['cart'][$item_id]['quantity'] += $quantity;
        } else {
            $_SESSION['cart'][$item_id] = ['quantity' => $quantity, 'restaurant_id' => $restaurant_id];
        }
        // Optional: Add a success message or visual feedback
        // For now, just stay on the page or redirect
        header("Location: restaurant_menu.php?rid=$restaurant_id"); // Redirect to prevent resubmission
        exit();

    } else {
        // Handle item not found or not belonging to restaurant
        // You might want to add an error message display here
        $order_error = "Failed to add item to cart: Item not found or invalid.";
    }
}

// Handle order submission (This logic will be moved/changed for the cart system)
// if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['order_item_id'])) {
//    // ... (old order logic)
// }

$conn->close();

$cart_item_count = 0;
if (isset($_SESSION['cart'])) {
    foreach($_SESSION['cart'] as $item) {
        $cart_item_count += $item['quantity'];
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($restaurant['restaurant_name']); ?> | Menu</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; }
        .menu-card { border-radius: 12px; box-shadow: 0 4px 20px rgba(0,0,0,0.05); }
        .menu-img { height: 180px; object-fit: cover; border-radius: 12px 12px 0 0; }
        .cart-icon-badge {
            position: absolute;
            top: 10px;
            right: 10px;
            background-color: red;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.8em;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <a href="orders.php" class="btn btn-link mb-3"><i class="fas fa-arrow-left me-1"></i>Back to Orders</a>
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2 class="mb-0"><?php echo htmlspecialchars($restaurant['restaurant_name']); ?></h2>
        <a href="cart.php" class="btn btn-primary position-relative">
            <i class="fas fa-shopping-cart"></i> Cart
             <?php if ($cart_item_count > 0): ?>
                <span class="cart-icon-badge"><?php echo $cart_item_count; ?></span>
             <?php endif; ?>
        </a>
    </div>
    <?php if ($order_success): ?>
        <div class="alert alert-success">Order placed successfully!</div>
    <?php elseif ($order_error): ?>
        <div class="alert alert-danger"><?php echo $order_error; ?></div>
    <?php endif; ?>
    <div class="row">
        <?php if (count($menu_items) > 0): ?>
            <?php foreach ($menu_items as $item): ?>
                <div class="col-md-4 mb-4">
                    <div class="card menu-card h-100">
                        <?php
                        $img_path = isset($item['image_url']) ? $item['image_url'] : '';
                        $img_full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $img_path;
                        $img_src = (!empty($img_path) && file_exists($img_full_path)) ? "/$img_path" : 'https://via.placeholder.com/400x250?text=No+Image';
                        ?>
                        <img src="<?php echo $img_src; ?>" class="menu-img card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?>">
                        <div class="card-body d-flex flex-column">
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                            <span class="badge bg-primary mb-2"><?php echo number_format($item['price'], 0, '.', ','); ?> XAF</span>
                            <p class="card-text flex-grow-1"><?php echo htmlspecialchars($item['description']); ?></p>
                            <form method="post" class="mt-auto">
                                <div class="input-group mb-2">
                                    <input type="number" name="quantity" value="1" min="1" class="form-control" style="max-width:80px;" required>
                                    <span class="input-group-text">Qty</span>
                                </div>
                                <input type="hidden" name="add_to_cart" value="<?php echo $item['item_id']; ?>">
                                <button type="submit" class="btn btn-success w-100"><i class="fas fa-cart-plus me-2"></i> Add to Cart</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center text-muted">No menu items available for this restaurant.</div>
        <?php endif; ?>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 