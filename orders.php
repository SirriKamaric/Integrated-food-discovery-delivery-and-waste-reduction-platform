<?php
require_once '../session_check.php';
if (!hasAccess('restaurant')) {
    header("Location: ../unauthorized.php");
    exit();
}

require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch restaurant details for sidebar and order fetching
$restaurant_name_sidebar = 'FoodSave'; // Default name
$restaurant_logo_url_sidebar = 'https://via.placeholder.com/50'; // Default logo
$restaurant_id = null; // Initialize restaurant_id

$stmt_sidebar = $conn->prepare("SELECT restaurant_id, restaurant_name, logo_url FROM restaurants WHERE user_id = ?");
$stmt_sidebar->bind_param("i", $_SESSION['user_id']);
$stmt_sidebar->execute();
$result_sidebar = $stmt_sidebar->get_result();
if ($result_sidebar->num_rows > 0) {
    $row_sidebar = $result_sidebar->fetch_assoc();
    $restaurant_id = $row_sidebar['restaurant_id']; // Get the actual restaurant_id
    $restaurant_name_sidebar = htmlspecialchars($row_sidebar['restaurant_name']);
    $restaurant_logo_url_sidebar = htmlspecialchars($row_sidebar['logo_url']);
}
$stmt_sidebar->close();

$orders = [];
$update_message = '';
$update_message_type = '';

// Handle order status updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_status') {
    $order_id_to_update = intval($_POST['order_id']);
    $new_status = htmlspecialchars(trim($_POST['new_status']));

    // Validate status and ensure it's for this restaurant's order
    $stmt_check_order = $conn->prepare("SELECT order_id FROM orders WHERE order_id = ? AND restaurant_id = ?");
    $stmt_check_order->bind_param("ii", $order_id_to_update, $restaurant_id);
    $stmt_check_order->execute();
    $result_check_order = $stmt_check_order->get_result();

    if ($result_check_order->num_rows > 0) {
        $stmt_update = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
        $stmt_update->bind_param("si", $new_status, $order_id_to_update);
        if ($stmt_update->execute()) {
            $update_message = "Order #{$order_id_to_update} status updated to " . ucfirst($new_status) . ".";
            $update_message_type = "success";
        } else {
            $update_message = "Error updating order status: " . $stmt_update->error;
            $update_message_type = "danger";
        }
        $stmt_update->close();
    } else {
        $update_message = "Order not found or you do not have permission to update it.";
        $update_message_type = "danger";
    }
    $stmt_check_order->close();
}

if ($restaurant_id) { // Only fetch orders if a valid restaurant_id is found
    $sqlOrders = "
        SELECT
            o.order_id,
            CONCAT(up.first_name, ' ', up.last_name) AS customer_name, -- Corrected customer name from user_profiles
            o.total_amount,
            o.status,
            o.order_date,
            o.delivery_address_id,
            o.payment_method,
            o.payment_status,
            COUNT(oi.order_item_id) AS item_count
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        LEFT JOIN user_profiles up ON u.user_id = up.user_id -- Join to get customer name from user_profiles
        LEFT JOIN order_items oi ON o.order_id = oi.order_id
        WHERE o.restaurant_id = ?
        GROUP BY o.order_id, up.first_name, up.last_name, o.total_amount, o.status, o.order_date, o.delivery_address_id, o.payment_method, o.payment_status
        ORDER BY o.order_date DESC
    ";
    $stmtOrders = $conn->prepare($sqlOrders);
    $stmtOrders->bind_param("i", $restaurant_id);
    $stmtOrders->execute();
    $resultOrders = $stmtOrders->get_result();

    if ($resultOrders->num_rows > 0) {
        while($row = $resultOrders->fetch_assoc()) {
            $orders[] = $row;
        }
    }
    $stmtOrders->close();
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Orders Management | FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
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

            /* Dark Mode Variables */
            --dark-bg: #1a202c;
            --dark-card-bg: #2d3748;
            --dark-text-color: #e2e8f0;
            --dark-muted-text-color: #a0aec0;
            --dark-border-color: #4a5568;
        }
        
        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--light);
            color: var(--dark);
            overflow-x: hidden;
        }
        
        body.dark-mode {
            background-color: var(--dark-bg);
            color: var(--dark-text-color);
        }

        body.dark-mode .sidebar {
            background: linear-gradient(180deg, var(--dark-card-bg), var(--dark));
        }

        body.dark-mode .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.7);
        }

        body.dark-mode .sidebar .nav-link:hover, body.dark-mode .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
        }

        body.dark-mode .card {
            background-color: var(--dark-card-bg);
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .card-header {
            background-color: var(--dark-card-bg);
            border-bottom-color: var(--dark-border-color);
            color: var(--dark-text-color);
        }

        body.dark-mode .form-control,
        body.dark-mode .form-select {
            background-color: #4a5568;
            color: var(--dark-text-color);
            border-color: var(--dark-border-color);
        }

        body.dark-mode .form-control::placeholder {
            color: var(--dark-muted-text-color);
        }

        body.dark-mode .table {
            color: var(--dark-text-color);
        }

        body.dark-mode .table th {
            border-bottom-color: var(--dark-border-color);
        }

        body.dark-mode .table td {
            border-top-color: var(--dark-border-color);
        }

        body.dark-mode .text-muted {
            color: var(--dark-muted-text-color) !important;
        }

        body.dark-mode .btn-outline-primary {
            color: var(--primary-light);
            border-color: var(--primary-light);
        }

        body.dark-mode .btn-outline-primary:hover {
            background-color: var(--primary-light);
            color: var(--dark-bg);
        }

        .sidebar {
            background: linear-gradient(180deg, var(--primary), var(--primary-dark));
            color: white;
            height: 100vh;
            position: fixed;
            width: 280px;
            padding: 0;
            top: 0;
            left: 0;
            overflow-y: auto;
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.2);
            z-index: 1000;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            background: rgba(0,0,0,0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px 20px;
            margin: 0 10px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.2);
            color: white;
            transform: translateX(5px);
        }
        
        .sidebar .nav-link i {
            width: 24px;
            text-align: center;
            margin-right: 12px;
            font-size: 1.1rem;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
            transition: margin-left 0.3s ease;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
            margin-bottom: 20px;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 15px 20px;
            font-weight: 600;
        }
        
        .order-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-pending {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .status-processing {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-completed {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .status-cancelled {
            background-color: #FEE2E2;
            color: #991B1B;
        }
        
        .order-details {
            background-color: #F9FAFB;
            border-radius: 8px;
            padding: 15px;
        }
        
        .order-item {
            border-bottom: 1px solid #E5E7EB;
            padding-bottom: 10px;
            margin-bottom: 10px;
        }
        
        .order-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .notification-bell {
            position: relative;
        }

        .notification-count {
            position: absolute;
            top: -5px;
            right: -5px;
            background-color: var(--danger);
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 0.7em;
            line-height: 1;
            min-width: 18px;
            text-align: center;
        }
        
        /* Mobile-specific styles */
        @media (max-width: 1199px) {
            .sidebar {
                transform: translateX(-100%);
                z-index: 1050;
            }
            
            .sidebar.open {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
            
            .menu-toggle-btn {
                display: inline-flex !important;
                align-items: center;
                justify-content: center;
                background: none;
                border: none;
                font-size: 1.5rem;
                color: var(--primary);
                margin-right: 10px;
                cursor: pointer;
            }
            
            .sidebar-backdrop {
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background-color: rgba(0, 0, 0, 0.5);
                z-index: 1040;
                opacity: 0;
                visibility: hidden;
                transition: opacity 0.3s ease, visibility 0.3s ease;
            }
            
            .sidebar-backdrop.active {
                opacity: 1;
                visibility: visible;
            }
        }
        
        @media (max-width: 767px) {
            .main-content {
                padding: 10px;
            }
            
            .dashboard-header-row {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .header-left, .header-right {
                width: 100%;
            }
            
            .header-right {
                margin-top: 10px;
                justify-content: space-between;
            }
            
            .table-responsive {
                overflow-x: auto;
                -webkit-overflow-scrolling: touch;
            }
            
            .table th, .table td {
                white-space: nowrap;
            }
            
            .header-welcome {
                font-size: 1.2rem;
            }
            
            .card-header {
                padding: 12px 15px;
            }
        }
        
        @media (max-width: 575px) {
            .main-content {
                padding: 8px;
            }
            
            .header-welcome {
                font-size: 1.1rem;
            }
            
            .card-body {
                padding: 15px;
            }
            
            .card-footer {
                padding: 10px 15px;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .table th, .table td {
                padding: 0.5rem;
            }
            
            .order-status-badge {
                font-size: 0.65rem;
                padding: 3px 6px;
            }
        }
        
        /* Improved mobile navigation */
        .mobile-navbar {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            background-color: white;
            box-shadow: 0 -2px 10px rgba(0, 0, 0, 0.1);
            z-index: 1000;
            display: none;
        }
        
        .mobile-nav-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 8px 0;
            text-decoration: none;
            color: var(--dark);
            font-size: 0.8rem;
        }
        
        .mobile-nav-item i {
            font-size: 1.2rem;
            margin-bottom: 4px;
        }
        
        .mobile-nav-item.active {
            color: var(--primary);
        }
        
        @media (max-width: 767px) {
            .mobile-navbar {
                display: flex;
                justify-content: space-around;
            }
            
            body {
                padding-bottom: 60px; /* Space for mobile navbar */
            }
        }
        
        /* Theme toggle button styling */
        .theme-toggle-btn {
            background-color: transparent;
            border: none;
            color: var(--dark);
            font-size: 1.2rem;
            cursor: pointer;
            transition: color 0.3s ease;
        }

        body.dark-mode .theme-toggle-btn {
            color: var(--light);
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header text-center">
            <img src="<?php echo htmlspecialchars(str_starts_with($restaurant_logo_url_sidebar, 'http') ? $restaurant_logo_url_sidebar : '../' . $restaurant_logo_url_sidebar); ?>" 
                 alt="Restaurant Logo" class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
            <h5 class="text-white mb-0"><?php echo $restaurant_name_sidebar; ?></h5>
            <small class="text-white-50">Restaurant Panel</small>
        </div>
        <div class="px-3 py-4">
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="menu.php">
                        <i class="fas fa-utensils"></i> Menu Management
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="orders.php">
                        <i class="fas fa-clipboard-list"></i> Orders
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="donations.php">
                        <i class="fas fa-donate"></i> Donations
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="schedule.php">
                        <i class="fas fa-calendar-alt"></i> Donation Schedule
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-store"></i> Restaurant Profile
                    </a>
                </li>
                <li class="nav-item mt-4 pt-3 border-top border-white-10">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>
    </div>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4 dashboard-header-row">
            <div class="d-flex align-items-center header-left">
                <button class="menu-toggle-btn" id="menuToggleBtn" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="header-welcome">Orders Management</span>
            </div>
            <div class="d-flex align-items-center header-right">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button id="notificationBtn" class="btn btn-info me-2 notification-bell position-relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count"></span>
                </button>
                <button class="btn btn-outline-secondary me-2" data-bs-toggle="modal" data-bs-target="#filterModal">
                    <i class="fas fa-filter me-2"></i>Filter
                </button>
                <button class="btn btn-outline-secondary" id="exportBtn">
                    <i class="fas fa-download me-2"></i>Export
                </button>
            </div>
        </div>

        <?php if (!empty($update_message)): ?>
            <div class="alert alert-<?php echo $update_message_type; ?>" role="alert">
                <?php echo $update_message; ?>
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header">
                <ul class="nav nav-tabs card-header-tabs">
                    <li class="nav-item">
                        <a class="nav-link active" href="#">All Orders</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Pending</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Processing</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="#">Completed</a>
                    </li>
                </ul>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Order ID</th>
                                <th>Customer</th>
                                <th>Items</th>
                                <th>Total</th>
                                <th>Status</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                                <tr>
                                    <td colspan="7" class="text-center text-muted">No orders found for this restaurant.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <tr>
                                    <td><strong>#<?php echo $order['order_id']; ?></strong></td>
                                    <td><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                    <td><?php echo $order['item_count']; ?> items</td>
                                    <td>XAF <?php echo number_format($order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="order-status-badge 
                                            <?php 
                                            switch(strtolower($order['status'])) {
                                                case 'pending': echo 'status-pending'; break;
                                                case 'preparing': echo 'status-processing'; break; 
                                                case 'ready': echo 'status-processing'; break;
                                                case 'out_for_delivery': echo 'status-processing'; break;
                                                case 'delivered': echo 'status-completed'; break;
                                                case 'cancelled': echo 'status-cancelled'; break;
                                                case 'confirmed': echo 'status-processing'; break;
                                                default: echo 'bg-secondary';
                                            }
                                            ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                    <td>
                                        <div class="d-flex">
                                            <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" 
                                                    data-bs-target="#orderDetailsModal<?php echo $order['order_id']; ?>">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <nav>
                    <ul class="pagination justify-content-center">
                        <li class="page-item disabled"><a class="page-link" href="#">Previous</a></li>
                        <li class="page-item active"><a class="page-link" href="#">1</a></li>
                        <li class="page-item"><a class="page-link" href="#">2</a></li>
                        <li class="page-item"><a class="page-link" href="#">3</a></li>
                        <li class="page-item"><a class="page-link" href="#">Next</a></li>
                    </ul>
                </nav>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-navbar">
        <a href="dashboard.php" class="mobile-nav-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="menu.php" class="mobile-nav-item">
            <i class="fas fa-utensils"></i>
            <span>Menu</span>
        </a>
        <a href="orders.php" class="mobile-nav-item active">
            <i class="fas fa-clipboard-list"></i>
            <span>Orders</span>
        </a>
        <a href="donations.php" class="mobile-nav-item">
            <i class="fas fa-donate"></i>
            <span>Donations</span>
        </a>
        <a href="profile.php" class="mobile-nav-item">
            <i class="fas fa-store"></i>
            <span>Profile</span>
        </a>
    </div>

    <!-- Order Details Modals -->
    <?php foreach ($orders as $order): ?>
    <div class="modal fade" id="orderDetailsModal<?php echo $order['order_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Order #<?php echo $order['order_id']; ?> Details</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <h6>Order Information</h6>
                            <p class="mb-1"><strong>Date:</strong> <?php echo date('M d, Y H:i', strtotime($order['order_date'])); ?></p>
                            <p class="mb-1"><strong>Status:</strong> 
                                <span class="order-status-badge 
                                    <?php 
                                    switch(strtolower($order['status'])) {
                                        case 'pending': echo 'status-pending'; break;
                                        case 'preparing': echo 'status-processing'; break;
                                        case 'ready': echo 'status-processing'; break;
                                        case 'out_for_delivery': echo 'status-processing'; break;
                                        case 'delivered': echo 'status-completed'; break;
                                        case 'cancelled': echo 'status-cancelled'; break;
                                        case 'confirmed': echo 'status-processing'; break;
                                        default: echo 'bg-secondary';
                                    }
                                    ?>">
                                    <?php echo ucfirst($order['status']); ?>
                                </span>
                            </p>
                            <p class="mb-1"><strong>Total:</strong> XAF <?php echo number_format($order['total_amount'], 2); ?></p>
                        </div>
                        <div class="col-md-6">
                            <h6>Customer Information</h6>
                            <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                            <?php 
                                // Re-establish connection temporarily to fetch address details for the modal
                                $conn_modal = new mysqli($servername, $username, $password, $dbname);
                                if ($conn_modal->connect_error) die("Connection failed: " . $conn_modal->connect_error);
                                
                                $delivery_address_display = 'N/A';
                                if (isset($order['delivery_address_id'])) {
                                    $stmt_addr = $conn_modal->prepare("SELECT address_line1, address_line2, city, state, zip_code FROM addresses WHERE address_id = ?");
                                    $stmt_addr->bind_param("i", $order['delivery_address_id']);
                                    $stmt_addr->execute();
                                    $result_addr = $stmt_addr->get_result();
                                    if ($result_addr->num_rows > 0) {
                                        $addr = $result_addr->fetch_assoc();
                                        $delivery_address_display = htmlspecialchars($addr['address_line1']);
                                        if (!empty($addr['address_line2'])) {
                                            $delivery_address_display .= ', ' . htmlspecialchars($addr['address_line2']);
                                        }
                                        $delivery_address_display .= ', ' . htmlspecialchars($addr['city']) . ', ' . htmlspecialchars($addr['state']) . ' ' . htmlspecialchars($addr['zip_code']);
                                    }
                                    $stmt_addr->close();
                                }
                                $conn_modal->close();
                            ?>
                            <p class="mb-1"><strong>Delivery Address:</strong> <?php echo $delivery_address_display; ?></p>
                            <p class="mb-1"><strong>Payment Method:</strong> <?php echo htmlspecialchars($order['payment_method']); ?></p>
                            <p class="mb-1"><strong>Payment Status:</strong> <?php echo ucfirst(htmlspecialchars($order['payment_status'])); ?></p>
                        </div>
                    </div>
                    <hr>
                    <h6>Order Items</h6>
                    <div class="order-details">
                        <?php 
                            // Re-establish connection temporarily to fetch order items for the modal
                            $conn_items = new mysqli($servername, $username, $password, $dbname);
                            if ($conn_items->connect_error) die("Connection failed: " . $conn_items->connect_error);

                            $stmt_order_items = $conn_items->prepare("SELECT mi.item_name, oi.quantity, oi.price_at_time, mi.image_url FROM order_items oi JOIN menu_items mi ON oi.item_id = mi.item_id WHERE oi.order_id = ?");
                            $stmt_order_items->bind_param("i", $order['order_id']);
                            $stmt_order_items->execute();
                            $result_order_items = $stmt_order_items->get_result();

                            if ($result_order_items->num_rows > 0): 
                        ?>
                            <?php while($item = $result_order_items->fetch_assoc()): ?>
                                <div class="d-flex align-items-center order-item">
                                    <?php
                                        $img_path_db = htmlspecialchars($item['image_url']);
                                        $display_img_src = '../images/placeholder_food.jpg'; // Default placeholder (relative to restaurant/)

                                        if (!empty($img_path_db)) {
                                            // Prioritize new standardized path (e.g., 'uploads/menu_items/filename.jpg')
                                            $web_path_candidate = '../' . $img_path_db; // relative to restaurant/
                                            $server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $img_path_db;

                                            if (file_exists($server_path_candidate) && !is_dir($server_path_candidate)) {
                                                $display_img_src = $web_path_candidate;
                                            } else {
                                                // Fallback to old path (e.g., 'uploads/menu/filename.jpg' - relative to restaurant/ directory)
                                                $old_img_path_candidate = '../restaurant/' . $img_path_db; // relative to restaurant/
                                                $server_path_old_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/restaurant/' . $img_path_db;

                                                if (file_exists($server_path_old_candidate) && !is_dir($server_path_old_candidate)) {
                                                    $display_img_src = $old_img_path_candidate;
                                                }
                                            }
                                        }
                                    ?>
                                    <img src="<?php echo $display_img_src; ?>" alt="<?php echo htmlspecialchars($item['item_name']); ?>" class="me-3 rounded" style="width: 50px; height: 50px; object-fit: cover;">
                                    <div class="flex-grow-1">
                                        <p class="my-0"><strong><?php echo htmlspecialchars($item['item_name']); ?></strong></p>
                                        <small class="text-muted">XAF <?php echo number_format($item['price_at_time'], 2); ?> each</small>
                                    </div>
                                    <span class="fw-bold">x<?php echo $item['quantity']; ?></span>
                                    <span class="ms-3 fw-bold">XAF <?php echo number_format($item['price_at_time'] * $item['quantity'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <p class="text-center text-muted">No items found for this order.</p>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <!-- Add action buttons here, e.g., Mark as Processing, Mark as Ready for Pickup, etc. -->
                    <!-- Example: -->
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="new_status" value="preparing">
                        <button type="submit" class="btn btn-warning">Mark as Preparing</button>
                    </form>
                    <form method="post" style="display:inline-block;">
                        <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                        <input type="hidden" name="action" value="update_status">
                        <input type="hidden" name="new_status" value="delivered">
                        <button type="submit" class="btn btn-success">Mark as Delivered</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

    <!-- Filter Modal -->
    <div class="modal fade" id="filterModal" tabindex="-1" aria-labelledby="filterModalLabel" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header">
            <h5 class="modal-title" id="filterModalLabel">Filter Orders</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="filterForm">
            <div class="modal-body">
              <div class="mb-3">
                <label for="statusFilter" class="form-label">Status</label>
                <select class="form-select" id="statusFilter" name="status">
                  <option value="">All</option>
                  <option value="pending">Pending</option>
                  <option value="preparing">Preparing</option>
                  <option value="ready">Ready</option>
                  <option value="out_for_delivery">Out for Delivery</option>
                  <option value="delivered">Delivered</option>
                  <option value="cancelled">Cancelled</option>
                  <option value="confirmed">Confirmed</option>
                </select>
              </div>
              <div class="mb-3">
                <label for="dateFrom" class="form-label">From Date</label>
                <input type="date" class="form-control" id="dateFrom" name="date_from">
              </div>
              <div class="mb-3">
                <label for="dateTo" class="form-label">To Date</label>
                <input type="date" class="form-control" id="dateTo" name="date_to">
              </div>
            </div>
            <div class="modal-footer">
              <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
              <button type="submit" class="btn btn-primary">Apply Filter</button>
            </div>
          </form>
        </div>
      </div>
    </div>

    <!-- Notification Modal -->
    <div class="modal fade" id="notificationModal" tabindex="-1" aria-labelledby="notificationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="notificationModalLabel">Notifications</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body" id="notificationModalBody">
                    <p class="text-center text-muted">Loading notifications...</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Sidebar backdrop for mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Theme Toggle Functionality
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const body = document.body;
            const currentTheme = localStorage.getItem('theme');

            if (currentTheme) {
                body.classList.add(currentTheme);
                // Update icon based on theme
                if (currentTheme === 'dark-mode') {
                    themeToggleBtn.querySelector('i').classList.replace('fa-moon', 'fa-sun');
                } else {
                    themeToggleBtn.querySelector('i').classList.replace('fa-sun', 'fa-moon');
                }
            }

            if (themeToggleBtn) {
                themeToggleBtn.addEventListener('click', function() {
                    body.classList.toggle('dark-mode');
                    if (body.classList.contains('dark-mode')) {
                        localStorage.setItem('theme', 'dark-mode');
                        this.querySelector('i').classList.replace('fa-moon', 'fa-sun');
                    } else {
                        localStorage.removeItem('theme');
                        this.querySelector('i').classList.replace('fa-sun', 'fa-moon');
                    }
                });
            }

            // Notification Button Functionality
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationModal = new bootstrap.Modal(document.getElementById('notificationModal'));
            const notificationModalBody = document.getElementById('notificationModalBody');
            const notificationCountSpan = document.getElementById('notificationCount');

            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    notificationModal.show();
                    fetchNotifications();
                });
            }

            function fetchNotifications() {
                notificationModalBody.innerHTML = '<p class="text-center text-muted">Loading notifications...</p>';
                fetch('../restaurant/fetch_notifications.php')
                    .then(response => response.json())
                    .then(data => {
                        if (data.success && data.notifications.length > 0) {
                            notificationModalBody.innerHTML = '';
                            data.notifications.forEach(notification => {
                                const notificationDiv = document.createElement('div');
                                notificationDiv.classList.add('alert', notification.is_read ? 'alert-light' : 'alert-info', 'mb-2');
                                notificationDiv.setAttribute('role', 'alert');
                                notificationDiv.innerHTML = `
                                    <h6 class="alert-heading">${notification.title}</h6>
                                    <p class="mb-0">${notification.message}</p>
                                    <small class="text-muted">${new Date(notification.created_at).toLocaleString()}</small>
                                `;
                                notificationDiv.addEventListener('click', () => markAsRead(notification.notification_id, notificationDiv));
                                notificationModalBody.appendChild(notificationDiv);
                            });
                            notificationCountSpan.textContent = data.unread_count > 0 ? data.unread_count : '';
                            notificationCountSpan.style.display = data.unread_count > 0 ? '' : 'none';

                        } else if (data.success && data.notifications.length === 0) {
                            notificationModalBody.innerHTML = '<p class="text-center text-muted">No new notifications.</p>';
                            notificationCountSpan.textContent = '';
                            notificationCountSpan.style.display = 'none';
                        } else {
                            notificationModalBody.innerHTML = '<p class="text-center text-danger">Failed to load notifications: ' + (data.message || 'Unknown error') + '</p>';
                        }
                    })
                    .catch(error => {
                        console.error('Error fetching notifications:', error);
                        notificationModalBody.innerHTML = '<p class="text-center text-danger">An error occurred while fetching notifications.</p>';
                    });
            }

            function markAsRead(notificationId, notificationDiv) {
                fetch('../restaurant/mark_notification_read.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: `notification_id=${notificationId}`,
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        notificationDiv.classList.remove('alert-info');
                        notificationDiv.classList.add('alert-light');
                        fetchNotifications(); // Reload to update count
                    } else {
                        console.error('Failed to mark as read:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error marking as read:', error);
                });
            }

            // Initial fetch of notifications count when page loads
            fetchNotifications();

            // Mobile menu toggle functionality
            const sidebar = document.querySelector('.sidebar');
            const menuToggleBtn = document.getElementById('menuToggleBtn');
            const sidebarBackdrop = document.getElementById('sidebarBackdrop');
            
            function openSidebar() {
                sidebar.classList.add('open');
                sidebarBackdrop.classList.add('active');
                document.body.style.overflow = 'hidden';
            }
            
            function closeSidebar() {
                sidebar.classList.remove('open');
                sidebarBackdrop.classList.remove('active');
                document.body.style.overflow = '';
            }
            
            if (menuToggleBtn) {
                menuToggleBtn.addEventListener('click', openSidebar);
            }
            
            if (sidebarBackdrop) {
                sidebarBackdrop.addEventListener('click', closeSidebar);
            }
            
            // Close sidebar when clicking on a nav link (for mobile)
            const navLinks = document.querySelectorAll('.sidebar .nav-link');
            navLinks.forEach(link => {
                link.addEventListener('click', closeSidebar);
            });
            
            // Close sidebar on ESC key
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape') closeSidebar();
            });
            
            // Highlight current page in mobile navbar
            const currentPage = window.location.pathname.split('/').pop();
            const mobileNavItems = document.querySelectorAll('.mobile-nav-item');
            mobileNavItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage)) {
                    item.classList.add('active');
                } else {
                    item.classList.remove('active');
                }
            });

            // Export to CSV
            function downloadCSV(csv, filename) {
                var csvFile;
                var downloadLink;
                csvFile = new Blob([csv], {type: 'text/csv'});
                downloadLink = document.createElement('a');
                downloadLink.download = filename;
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = 'none';
                document.body.appendChild(downloadLink);
                downloadLink.click();
            }
            
            function exportTableToCSV(filename) {
                var csv = [];
                var rows = document.querySelectorAll('table.table tr');
                for (var i = 0; i < rows.length; i++) {
                    var row = [], cols = rows[i].querySelectorAll('th, td');
                    for (var j = 0; j < cols.length; j++)
                        row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
                    csv.push(row.join(','));
                }
                downloadCSV(csv.join('\n'), filename);
            }
            
            document.getElementById('exportBtn').addEventListener('click', function() {
                exportTableToCSV('orders_export.csv');
            });
            
            // Filter functionality (client-side)
            document.getElementById('filterForm').addEventListener('submit', function(e) {
                e.preventDefault();
                var status = document.getElementById('statusFilter').value.toLowerCase();
                var dateFrom = document.getElementById('dateFrom').value;
                var dateTo = document.getElementById('dateTo').value;
                var rows = document.querySelectorAll('table.table tbody tr');
                rows.forEach(function(row) {
                    var show = true;
                    var statusCell = row.querySelector('td:nth-child(5) span');
                    var dateCell = row.querySelector('td:nth-child(6)');
                    if (status && statusCell && statusCell.innerText.toLowerCase() !== status) {
                        show = false;
                    }
                    if (dateFrom) {
                        var rowDate = new Date(dateCell.innerText);
                        var fromDate = new Date(dateFrom);
                        if (rowDate < fromDate) show = false;
                    }
                    if (dateTo) {
                        var rowDate = new Date(dateCell.innerText);
                        var toDate = new Date(dateTo);
                        if (rowDate > toDate) show = false;
                    }
                    row.style.display = show ? '' : 'none';
                });
                var filterModal = bootstrap.Modal.getInstance(document.getElementById('filterModal'));
                filterModal.hide();
            });
        });
    </script>
</body>
</html>