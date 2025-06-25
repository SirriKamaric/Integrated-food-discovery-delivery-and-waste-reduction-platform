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

// Fetch order details
$order = [];
$order_query = "SELECT o.*, r.restaurant_name, r.address, r.latitude, r.longitude 
                FROM orders o
                JOIN restaurants r ON o.restaurant_id = r.restaurant_id
                WHERE o.order_id = ? AND o.customer_id = ?";
$stmt = $conn->prepare($order_query);
$stmt->bind_param('ii', $order_id, $user_id);
$stmt->execute();
$order_result = $stmt->get_result();

if ($order_result->num_rows > 0) {
    $order = $order_result->fetch_assoc();
    
    // Get order items
    $items_query = "SELECT f.name, f.price, oi.quantity 
                    FROM order_items oi
                    JOIN foods f ON oi.item_id = f.food_id
                    WHERE oi.order_id = ?";
    $stmt_items = $conn->prepare($items_query);
    $stmt_items->bind_param('i', $order_id);
    $stmt_items->execute();
    $items_result = $stmt_items->get_result();
    $order_items = [];
    while ($item = $items_result->fetch_assoc()) {
        $order_items[] = $item;
    }
    $stmt_items->close();
} else {
    header("Location: orders.php?error=order_not_found");
    exit();
}

$stmt->close();
$conn->close();

// Determine current status and progress
$status_steps = [
    'processing' => ['Processing', 'Your order is being prepared by the restaurant'],
    'preparing' => ['Preparing', 'The chef is cooking your meal'],
    'on_the_way' => ['On the Way', 'Your food is out for delivery'],
    'delivered' => ['Delivered', 'Enjoy your meal!'],
    'cancelled' => ['Cancelled', 'Order was cancelled']
];

$current_status = $order['status'];
$status_index = array_search($current_status, array_keys($status_steps));
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Track Order | FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.3/dist/leaflet.css" />
    <style>
        :root {
            --primary: #4F46E5;
            --primary-dark: #4338CA;
            --accent: #F59E0B;
            --dark: #1F2937;
            --light: #F9FAFB;
            --success: #10B981;
            --danger: #EF4444;
            --warning: #F59E0B;
            --info: #0d6efd;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: #f5f7fa;
        }
        
        .tracking-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 30px;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
        }
        
        .tracking-header {
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding-bottom: 20px;
            margin-bottom: 30px;
        }
        
        .tracking-steps {
            position: relative;
            padding: 20px 0;
            margin-bottom: 30px;
        }
        
        .tracking-steps::before {
            content: '';
            position: absolute;
            left: 50px;
            top: 0;
            bottom: 0;
            width: 4px;
            background-color: #E5E7EB;
            transform: translateX(-50%);
        }
        
        .track-step {
            position: relative;
            padding-left: 80px;
            margin-bottom: 30px;
        }
        
        .track-step:last-child {
            margin-bottom: 0;
        }
        
        .step-icon {
            position: absolute;
            left: 30px;
            top: 0;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: #E5E7EB;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 18px;
            z-index: 2;
        }
        
        .step-icon.active {
            background-color: var(--primary);
            box-shadow: 0 0 0 6px rgba(79, 70, 229, 0.2);
        }
        
        .step-icon.completed {
            background-color: var(--success);
        }
        
        .step-content {
            padding: 15px;
            border-radius: 8px;
            background-color: #F9FAFB;
        }
        
        .step-title {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .step-description {
            color: #6B7280;
            font-size: 0.9rem;
        }
        
        .step-time {
            font-size: 0.8rem;
            color: #9CA3AF;
            margin-top: 5px;
        }
        
        .order-summary {
            border-radius: 12px;
            border: 1px solid rgba(0, 0, 0, 0.05);
            overflow: hidden;
        }
        
        .order-summary .table {
            margin-bottom: 0;
        }
        
        .order-summary .table th {
            background-color: #F9FAFB;
            border-bottom-width: 1px;
        }
        
        .map-container {
            height: 250px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 20px;
        }
        
        .delivery-person {
            display: flex;
            align-items: center;
            padding: 15px;
            background-color: #F9FAFB;
            border-radius: 8px;
            margin-top: 20px;
        }
        
        .delivery-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            margin-right: 15px;
            flex-shrink: 0;
        }
        
        .delivery-info h6 {
            margin-bottom: 5px;
        }
        
        .delivery-info p {
            color: #6B7280;
            font-size: 0.9rem;
            margin-bottom: 0;
        }
        
        .contact-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            margin-left: auto;
            flex-shrink: 0;
        }
        
        .status-badge {
            padding: 6px 12px;
            border-radius: 50px;
            font-weight: 600;
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .status-processing { background-color: var(--info); color: white; }
        .status-preparing { background-color: var(--warning); color: white; }
        .status-on_the_way { background-color: var(--primary); color: white; }
        .status-delivered { background-color: var(--success); color: white; }
        .status-cancelled { background-color: var(--danger); color: white; }
        
        @media (max-width: 768px) {
            .tracking-container {
                padding: 20px;
            }
            
            .track-step {
                padding-left: 60px;
            }
            
            .step-icon {
                left: 20px;
                width: 30px;
                height: 30px;
                font-size: 14px;
            }
            
            .tracking-steps::before {
                left: 35px;
            }
        }
    </style>
</head>
<body>
    <div class="container py-5">
        <div class="tracking-container">
            <div class="tracking-header">
                <div class="d-flex justify-content-between align-items-center mb-3">
                    <h2>Order #<?php echo $order['order_id']; ?></h2>
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $order['status'])); ?>
                    </span>
                </div>
                <div class="row">
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Order Date:</strong> <?php echo date('F j, Y \a\t g:i A', strtotime($order['order_date'])); ?></p>
                        <p class="mb-1"><strong>Restaurant:</strong> <?php echo htmlspecialchars($order['restaurant_name']); ?></p>
                    </div>
                    <div class="col-md-6">
                        <p class="mb-1"><strong>Total Amount:</strong> XAF <?php echo number_format($order['total_amount'], 0); ?></p>
                        <p class="mb-1"><strong>Payment Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                    </div>
                </div>
            </div>
            
            <h4 class="mb-4">Order Status</h4>
            <div class="tracking-steps">
                <?php foreach ($status_steps as $status => $step): ?>
                    <?php 
                    $current_index = array_search($status, array_keys($status_steps));
                    $is_completed = $current_index < $status_index;
                    $is_active = $current_index == $status_index;
                    ?>
                    <div class="track-step">
                        <div class="step-icon <?php echo $is_active ? 'active' : ''; ?> <?php echo $is_completed ? 'completed' : ''; ?>">
                            <?php if ($is_completed): ?>
                                <i class="fas fa-check"></i>
                            <?php else: ?>
                                <?php echo $current_index + 1; ?>
                            <?php endif; ?>
                        </div>
                        <div class="step-content">
                            <div class="step-title"><?php echo $step[0]; ?></div>
                            <div class="step-description"><?php echo $step[1]; ?></div>
                            <?php if ($is_active && $status !== 'cancelled'): ?>
                                <div class="step-time">Estimated time: <?php 
                                    if ($status === 'processing') echo '10-15 minutes';
                                    elseif ($status === 'preparing') echo '15-25 minutes';
                                    elseif ($status === 'on_the_way') echo '5-10 minutes';
                                    else echo 'Completed';
                                ?></div>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
            
            <?php if ($order['status'] === 'on_the_way'): ?>
                <div class="delivery-person">
                    <div class="delivery-avatar">
                        <?php echo substr('Delivery', 0, 1); ?>
                    </div>
                    <div class="delivery-info">
                        <h6 class="mb-0">Delivery in Progress</h6>
                        <p>Your food is on the way to your location</p>
                    </div>
                    <a href="#" class="contact-btn">
                        <i class="fas fa-phone"></i>
                    </a>
                </div>
                
                <div class="map-container" id="deliveryMap"></div>
            <?php endif; ?>
            
            <h4 class="mt-5 mb-4">Order Summary</h4>
            <div class="order-summary">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Quantity</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>XAF <?php echo number_format($item['price'], 0); ?></td>
                                <td>XAF <?php echo number_format($item['price'] * $item['quantity'], 0); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Subtotal:</strong></td>
                            <td>XAF <?php echo number_format($order['total_amount'] - 500, 0); ?></td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Delivery Fee:</strong></td>
                            <td>XAF 500</td>
                        </tr>
                        <tr>
                            <td colspan="3" class="text-end"><strong>Total:</strong></td>
                            <td>XAF <?php echo number_format($order['total_amount'], 0); ?></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            
            <div class="d-flex justify-content-between mt-4">
                <a href="orders.php" class="btn btn-outline-secondary">
                    <i class="fas fa-arrow-left me-2"></i> Back to Orders
                </a>
                <?php if ($order['status'] !== 'delivered' && $order['status'] !== 'cancelled'): ?>
                    <button class="btn btn-danger" onclick="window.location.href='confirm_cancel.php?order_id=<?php echo $order['order_id']; ?>'">
                        <i class="fas fa-times me-2"></i> Cancel Order
                    </button>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($order['status'] === 'on_the_way'): ?>
                // Initialize delivery map
                try {
                    const deliveryMap = L.map('deliveryMap').setView([
                        <?php echo $order['latitude'] ?? 3.8480; ?>, 
                        <?php echo $order['longitude'] ?? 11.5021; ?>
                    ], 15);
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(deliveryMap);
                    
                    // Restaurant marker
                    L.marker([
                        <?php echo $order['latitude'] ?? 3.8480; ?>, 
                        <?php echo $order['longitude'] ?? 11.5021; ?>
                    ]).addTo(deliveryMap)
                      .bindPopup('<?php echo addslashes($order['restaurant_name']); ?>')
                      .openPopup();
                      
                    // Simulate delivery movement (in a real app, this would come from GPS)
                    const deliveryMarker = L.marker([
                        <?php echo $order['latitude'] ?? 3.8480; ?>, 
                        <?php echo $order['longitude'] ?? 11.5021; ?>
                    ], {
                        icon: L.divIcon({
                            className: 'delivery-marker',
                            html: '<div class="delivery-marker-inner"><i class="fas fa-motorcycle"></i></div>',
                            iconSize: [30, 30]
                        })
                    }).addTo(deliveryMap);
                    
                    // Animate delivery (demo only)
                    if (window.location.href.indexOf('demo=1') > -1) {
                        let lat = <?php echo $order['latitude'] ?? 3.8480; ?>;
                        let lng = <?php echo $order['longitude'] ?? 11.5021; ?>;
                        
                        const interval = setInterval(() => {
                            lat += 0.001;
                            lng += 0.001;
                            deliveryMarker.setLatLng([lat, lng]);
                            
                            if (lat > 3.855) {
                                clearInterval(interval);
                            }
                        }, 1000);
                    }
                } catch (e) {
                    console.error('Error initializing delivery map:', e);
                    document.getElementById('deliveryMap').innerHTML = 
                        '<div class="alert alert-warning p-4 text-center">Delivery tracking unavailable</div>';
                }
            <?php endif; ?>
        });
    </script>
</body>
</html>