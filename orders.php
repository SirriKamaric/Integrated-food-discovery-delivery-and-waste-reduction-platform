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

// Fetch customer profile data
$customer_profile = [];
$profile_query = "SELECT first_name, last_name, email, profile_picture_url FROM users WHERE user_id = ?";
$stmt = $conn->prepare($profile_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
if ($profile_result->num_rows > 0) {
    $customer_profile = $profile_result->fetch_assoc();
    $userName = htmlspecialchars($customer_profile['first_name'] . ' ' . $customer_profile['last_name']);
    $userEmail = htmlspecialchars($customer_profile['email']);
} else {
    $userName = 'Customer';
    $userEmail = '';
}
$stmt->close();

// Fetch orders
$orders_query = "SELECT o.order_id, o.order_date, o.total_amount, o.status, r.restaurant_name 
                 FROM orders o
                 JOIN restaurants r ON o.restaurant_id = r.restaurant_id
                 WHERE o.customer_id = ?
                 ORDER BY o.order_date DESC";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
$stmt->close();

// Fetch restaurants
$restaurants_query = "SELECT restaurant_id, restaurant_name, address, logo_url FROM restaurants LIMIT 6";
$restaurants_result = $conn->query($restaurants_query);

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Orders | FoodSave</title>
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

        body.dark-mode .search-input {
            background-color: var(--dark-card-bg);
            color: var(--dark-text-color);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }

        body.dark-mode .search-input:focus {
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.3);
        }

        body.dark-mode .bg-white {
            background-color: var(--dark-card-bg) !important;
        }

        body.dark-mode .modal-content {
            background-color: var(--dark-card-bg);
            color: var(--dark-text-color);
        }

        body.dark-mode .modal-header, body.dark-mode .modal-footer {
            border-color: var(--dark-border-color);
        }

        body.dark-mode .modal-title {
            color: var(--dark-text-color);
        }

        body.dark-mode .btn-close {
            filter: invert(1);
        }

        body.dark-mode .leaflet-tile-pane {
            filter: brightness(0.6) invert(1) grayscale(1);
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
            background: rgba(0,0,0,0.1); /* Slightly darker for contrast */
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

        .badge-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .badge-processing { background-color: var(--info); }
        .badge-preparing { background-color: var(--warning); color: white; }
        .badge-delivered { background-color: var(--success); color: white; }
        .badge-cancelled { background-color: var(--danger); color: white; }

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
            
            .donate-btn {
                width: 100%;
                margin-top: 10px;
                justify-content: center;
            }
            
            .header-welcome {
                font-size: 1.2rem;
            }
            
            .card-header {
                padding: 12px 15px;
            }
            
            .food-img {
                height: 140px;
            }
            
            .search-container {
                margin-bottom: 15px;
            }
            
            .search-input {
                padding: 10px 15px;
                padding-right: 45px;
            }
            
            .search-btn {
                width: 36px;
                height: 36px;
            }
        }
        
        @media (max-width: 575px) {
            .main-content {
                padding: 8px;
            }
            
            .header-welcome {
                font-size: 1.1rem;
            }
            
            .food-img {
                height: 120px;
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
            
            .badge-status {
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
        
        /* Improved form controls for mobile */
        .form-control, .form-select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            padding: 10px 15px;
        }
        
        /* Touch-friendly buttons */
        .btn {
            padding: 8px 16px;
            touch-action: manipulation;
        }
        
        /* Improved tap targets */
        a, button, [role="button"], input, label, select, textarea {
            touch-action: manipulation;
            -webkit-tap-highlight-color: transparent;
        }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header">
            <h4 class="mb-0 text-center">FoodSave</h4>
            <p class="text-center text-white-50 mb-0 small">Customer Panel</p>
        </div>
        <div class="px-3 py-4">
            <div class="d-flex align-items-center mb-4 px-2">
                <div class="user-avatar me-3">
                    <?php
                    $customer_profile_pic_url = htmlspecialchars($customer_profile['profile_picture_url'] ?? '');
                    $display_customer_profile_pic_src = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=random';

                    if (!empty($customer_profile_pic_url)) {
                        $full_server_path = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $customer_profile_pic_url;
                        $full_server_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_server_path);

                        if (file_exists($full_server_path) && !is_dir($full_server_path)) {
                            $display_customer_profile_pic_src = '/food_delivery_system/' . $customer_profile_pic_url;
                            $display_customer_profile_pic_src = str_replace('\\', '/', $display_customer_profile_pic_src);
                        }
                    }
                    ?>
                    <img src="<?php echo $display_customer_profile_pic_src; ?>" alt="Profile Picture" class="rounded-circle" style="width: 40px; height: 40px; object-fit: cover;">
                </div>
                <div>
                    <h6 class="mb-0 text-white"><?php echo $userName; ?></h6>
                    <small class="text-white-50">Customer</small>
                </div>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="orders.php">
                        <i class="fas fa-clipboard-list"></i> My Orders
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="donations.php">
                        <i class="fas fa-hand-holding-heart"></i> My Donations
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="favorites.php">
                        <i class="fas fa-heart"></i> Favorites
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-user"></i> My Profile
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
                <span class="header-welcome">My Orders</span>
            </div>
            <div class="d-flex align-items-center header-right">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button id="notificationBtn" class="btn btn-info me-2 notification-bell position-relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count"></span>
                </button>
                <a href="donate.php" class="donate-btn btn btn-success">
                    <i class="fas fa-hand-holding-heart me-2"></i> Donate Food
                </a>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Order History</h5>
                <div class="d-flex">
                    <a href="orders.php" class="btn btn-sm btn-outline-primary me-2"><i class="fas fa-filter me-1"></i> Filter</a>
                    <button id="exportOrdersBtn" class="btn btn-sm btn-outline-success"><i class="fas fa-download me-1"></i> Export</button>
                </div>
            </div>
            <div class="card-body">
                <?php if ($orders_result && $orders_result->num_rows > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead>
                                <tr>
                                    <th>Order ID</th>
                                    <th>Date</th>
                                    <th>Restaurant</th>
                                    <th>Amount</th>
                                    <th>Status</th>
                                    <th>Action</th>
                                </tr>
                            </thead>
                            <tbody id="ordersTableBody">
                                <?php while ($order = $orders_result->fetch_assoc()): ?>
                                    <tr>
                                        <td><strong>#<?php echo htmlspecialchars($order['order_id']); ?></strong></td>
                                        <td><?php echo date('M d, Y', strtotime($order['order_date'])); ?></td>
                                        <td><?php echo htmlspecialchars($order['restaurant_name']); ?></td>
                                        <td>XAF <?php echo number_format($order['total_amount'], 0, ',', ' '); ?></td>
                                        <td>
                                            <?php 
                                            $badge_class = '';
                                            if ($order['status'] == 'preparing') $badge_class = 'badge-preparing';
                                            elseif ($order['status'] == 'delivered') $badge_class = 'badge-delivered';
                                            elseif ($order['status'] == 'cancelled') $badge_class = 'badge-cancelled';
                                            elseif ($order['status'] == 'processing') $badge_class = 'badge-processing';
                                            ?>
                                            <span class="badge-status <?php echo $badge_class; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td><a href="order_tracking.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-primary">Track</a></td>
                                    </tr>
                                <?php endwhile; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5>No orders found</h5>
                        <p class="text-muted">You haven't placed any orders yet.</p>
                        <a href="dashboard.php" class="btn btn-primary">Browse Restaurants</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div id="browse-restaurants" class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Browse Restaurants</h5>
                <a href="../list.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <div class="row row-cols-1 row-cols-md-2 row-cols-lg-3 g-4">
                    <?php if ($restaurants_result && $restaurants_result->num_rows > 0): ?>
                        <?php while ($rest = $restaurants_result->fetch_assoc()): ?>
                            <div class="col">
                                <div class="card h-100 shadow-sm">
                                    <?php
                                    $restaurant_logo = htmlspecialchars($rest['logo_url']);
                                    $display_logo_src = '../images/restaurant_placeholder.jpg';
                                    if (!empty($restaurant_logo)) {
                                        $web_path_candidate = '../' . $restaurant_logo;
                                        $server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $restaurant_logo;
                                        if (file_exists($server_path_candidate) && !is_dir($server_path_candidate)) {
                                            $display_logo_src = $web_path_candidate;
                                        } else {
                                            $old_logo_path_candidate = '../restaurant_logos/' . $restaurant_logo;
                                            $server_path_old_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/restaurant_logos/' . $restaurant_logo;
                                            if (file_exists($server_path_old_candidate) && !is_dir($server_path_old_candidate)) {
                                                $display_logo_src = $old_logo_path_candidate;
                                            }
                                        }
                                    }
                                    ?>
                                    <img src="<?php echo $display_logo_src; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($rest['restaurant_name']); ?>" style="height: 160px; object-fit: cover;">
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title"><?php echo htmlspecialchars($rest['restaurant_name']); ?></h5>
                                        <p class="card-text text-muted small"><i class="fas fa-map-marker-alt me-2"></i><?php echo htmlspecialchars($rest['address']); ?></p>
                                        <div class="mt-auto">
                                            <a href="restaurant_menu.php?rid=<?php echo $rest['restaurant_id']; ?>" class="btn btn-outline-primary w-100">
                                                <i class="fas fa-utensils me-1"></i> View Menu
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <div class="col-12 text-center text-muted py-4">
                            <i class="fas fa-store-alt fa-3x mb-3"></i>
                            <h5>No restaurants available</h5>
                            <p>Check back later for available restaurants</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="row mt-4">
            <div class="col-md-6 mb-4 mb-md-0">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Order Status Legend</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge badge-processing me-3">Processing</span>
                            <span class="small">Your order is being processed</span>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge badge-preparing me-3">Preparing</span>
                            <span class="small">Your order is being prepared</span>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge bg-primary me-3">On the way</span>
                            <span class="small">Your order is being delivered</span>
                        </div>
                        <div class="d-flex align-items-center mb-3">
                            <span class="badge badge-delivered me-3">Delivered</span>
                            <span class="small">Order successfully delivered</span>
                        </div>
                        <div class="d-flex align-items-center">
                            <span class="badge badge-cancelled me-3">Cancelled</span>
                            <span class="small">Order was cancelled</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card h-100">
                    <div class="card-header">
                        <h5 class="mb-0">Need Help?</h5>
                    </div>
                    <div class="card-body d-flex flex-column">
                        <p class="mb-4">If you have any issues with your orders, please contact our support team.</p>
                        <div class="mt-auto">
                            <button class="btn btn-primary w-100">
                                <i class="fas fa-headset me-2"></i> Contact Support
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-navbar">
        <a href="dashboard.php" class="mobile-nav-item">
            <i class="fas fa-home"></i>
            <span>Home</span>
        </a>
        <a href="orders.php" class="mobile-nav-item active">
            <i class="fas fa-clipboard-list"></i>
            <span>Orders</span>
        </a>
        <a href="donations.php" class="mobile-nav-item">
            <i class="fas fa-hand-holding-heart"></i>
            <span>Donations</span>
        </a>
        <a href="favorites.php" class="mobile-nav-item">
            <i class="fas fa-heart"></i>
            <span>Favorites</span>
        </a>
        <a href="profile.php" class="mobile-nav-item">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
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
        let map = null; // Declare map variable outside to retain its state (if needed for any modals, etc.)

        document.addEventListener('DOMContentLoaded', function() {
            // Theme Toggle Functionality (copied from dashboard.php)
            const themeToggleBtn = document.getElementById('themeToggleBtn');
            const body = document.body;
            const currentTheme = localStorage.getItem('theme');

            if (currentTheme) {
                body.classList.add(currentTheme);
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
                    // No map to invalidate on this page currently
                });
            }

            // Notification Button Functionality (copied from dashboard.php)
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
                fetch('../customer/fetch_notifications.php') 
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
                fetch('../customer/mark_notification_read.php', {
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

            // Mobile menu toggle functionality (copied from dashboard.php)
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

            // Export Orders to CSV (copied from dashboard.php)
            document.getElementById('exportOrdersBtn').addEventListener('click', function() {
                let csv = [];
                let rows = document.querySelectorAll("#ordersTableBody tr"); // Note: Changed ID to ordersTableBody

                // Get header row
                let header = [];
                document.querySelectorAll(".table thead th").forEach(th => {
                    header.push(th.innerText);
                });
                csv.push(header.join(','));

                for (let i = 0; i < rows.length; i++) {
                    let row = [], cols = rows[i].querySelectorAll("td, th");
                    for (let j = 0; j < cols.length; j++) {
                        let data = cols[j].innerText.replace(/\n/g, ' ').replace(/,/g, '').trim();
                        row.push(data);
                    }
                    csv.push(row.join(','));
                }

                // Download CSV
                let csvFile = new Blob([csv.join('\n')], {type: 'text/csv'});
                let downloadLink = document.createElement('a');
                downloadLink.download = 'my_orders.csv'; // Changed filename
                downloadLink.href = window.URL.createObjectURL(csvFile);
                downloadLink.style.display = 'none';
                document.body.appendChild(downloadLink);
                downloadLink.click();
                document.body.removeChild(downloadLink);
            });
        });
    </script>
</body>
</html>