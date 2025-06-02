<?php
require_once '../session_check.php';

// Check if user is customer
if ($_SESSION['user_type'] !== 'customer') {
    header("Location: ../unauthorized.php");
    exit();
}

$userName = isset($_SESSION['user_name']) ? htmlspecialchars($_SESSION['user_name']) : 'Customer';
$user_id = $_SESSION['user_id'];

// Database connection
require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Handle food search
$search_results = [];

// Read and sanitize inputs from GET
$search_term = $conn->real_escape_string(isset($_GET['food_search']) ? $_GET['food_search'] : '');
$price_min_input = isset($_GET['price_min']) ? $_GET['price_min'] : '';
$price_max_input = isset($_GET['price_max']) ? $_GET['price_max'] : '';
$restaurant_filter_input = isset($_GET['restaurant']) ? $_GET['restaurant'] : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'name';
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$items_per_page = 12;

$restaurant_filter = '';
$total_items = 0;
$total_pages = 1;

// Only proceed with search query if a search term is provided or filters are active
if (!empty($search_term) || ($price_min_input !== '' && is_numeric($price_min_input)) || ($price_max_input !== '' && is_numeric($price_max_input)) || !empty($restaurant_filter_input)) {

    // Validate and set restaurant filter - only if input is non-empty
    if ($restaurant_filter_input !== '') {
         $restaurant_filter = $conn->real_escape_string($restaurant_filter_input);
    }

    // Build the base search query
    $search_query = "SELECT r.restaurant_id, r.restaurant_name, r.address, r.latitude, r.longitude, 
                    f.food_id, f.name as food_name, f.price, f.description, f.image_url
                    FROM restaurants r
                    JOIN foods f ON r.restaurant_id = f.restaurant_id
                    WHERE 1=1"; // Start with a true condition to easily append filters
    
    // Add search term condition if present
    if (!empty($search_term)) {
        $search_query .= " AND (LOWER(f.name) LIKE '%" . strtolower($search_term) . "%' 
                           OR LOWER(f.description) LIKE '%" . strtolower($search_term) . "%'
                           OR LOWER(r.restaurant_name) LIKE '%" . strtolower($search_term) . "%')";
    }

    // Add price range filter - only if inputs are non-empty numeric strings
    if (($price_min_input !== '' && is_numeric($price_min_input)) || ($price_max_input !== '' && is_numeric($price_max_input))) {
        if ($price_min_input !== '' && is_numeric($price_min_input)) {
            $search_query .= " AND f.price >= " . floatval($price_min_input);
        }
        if ($price_max_input !== '' && is_numeric($price_max_input)) {
            $search_query .= " AND f.price <= " . floatval($price_max_input);
        }
    }
    
    // Add restaurant filter if set
    if ($restaurant_filter !== '') {
        $search_query .= " AND r.restaurant_name LIKE '%$restaurant_filter%'";
    }
    
    // Add sorting
    switch ($sort) {
        case 'price_low':
            $search_query .= " ORDER BY f.price ASC";
            break;
        case 'price_high':
            $search_query .= " ORDER BY f.price DESC";
            break;
        case 'name':
        default:
            $search_query .= " ORDER BY f.name ASC";
            break;
    }
    
    // Add pagination
    $offset = ($page - 1) * $items_per_page;
    $search_query .= " LIMIT $offset, $items_per_page";
    
    $search_result = $conn->query($search_query);
    if ($search_result) {
        while ($row = $search_result->fetch_assoc()) {
            $search_results[] = $row;
        }
    }
    
    // Build the base count query
    $count_query = "SELECT COUNT(*) as total FROM restaurants r
                    JOIN foods f ON r.restaurant_id = f.restaurant_id
                    WHERE 1=1"; // Start with a true condition

    // Add search term condition to count query if present
    if (!empty($search_term)) {
        $count_query .= " AND (LOWER(f.name) LIKE '%" . strtolower($search_term) . "%' 
                           OR LOWER(f.description) LIKE '%" . strtolower($search_term) . "%'
                           OR LOWER(r.restaurant_name) LIKE '%" . strtolower($search_term) . "%')";
    }

    // Add price range filter to count query - same logic as main query
    if (($price_min_input !== '' && is_numeric($price_min_input)) || ($price_max_input !== '' && is_numeric($price_max_input))) {
         if ($price_min_input !== '' && is_numeric($price_min_input)) {
            $count_query .= " AND f.price >= " . floatval($price_min_input);
        }
        if ($price_max_input !== '' && is_numeric($price_max_input)) {
            $count_query .= " AND f.price <= " . floatval($price_max_input);
        }
    }

    // Add restaurant filter to count query
    if ($restaurant_filter !== '') {
        $count_query .= " AND r.restaurant_name LIKE '%$restaurant_filter%'";
    }
    
    $total_result = $conn->query($count_query);
    if ($total_result) {
         $total_items = $total_result->fetch_assoc()['total'];
         $total_pages = ceil($total_items / $items_per_page);
    }
}

// Get unique restaurants for filter
$restaurants = [];
$restaurants_query = "SELECT DISTINCT restaurant_name FROM restaurants ORDER BY restaurant_name";
$restaurants_result = $conn->query($restaurants_query);
while ($rest = $restaurants_result->fetch_assoc()) {
    $restaurants[] = $rest['restaurant_name'];
}

// Fetch Recent Orders (e.g., last 5)
$recent_orders = [];
$orders_query = "SELECT o.order_id, o.order_date, o.total_amount, o.status, r.restaurant_name
                 FROM orders o
                 JOIN restaurants r ON o.restaurant_id = r.restaurant_id
                 WHERE o.customer_id = ?
                 ORDER BY o.order_date DESC
                 LIMIT 5";
$stmt = $conn->prepare($orders_query);
$stmt->bind_param('i', $user_id);
$stmt->execute();
$orders_result = $stmt->get_result();
while ($order = $orders_result->fetch_assoc()) {
    $recent_orders[] = $order;
}
$stmt->close();

// Fetch Nearby Restaurants (e.g., all for simplicity)
$nearby_restaurants = [];
$restaurants_query = "SELECT restaurant_id, restaurant_name, address, latitude, longitude FROM restaurants LIMIT 6";
$restaurants_result = $conn->query($restaurants_query);
if ($restaurants_result) {
    while ($rest = $restaurants_result->fetch_assoc()) {
        $nearby_restaurants[] = $rest;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Customer Dashboard | FoodSave</title>
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
            box-shadow: 4px 0 15px rgba(0, 0, 0, 0.1);
            z-index: 1000;
        }
        
        .sidebar-header {
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.85);
            padding: 12px 20px;
            margin: 0 10px;
            border-radius: 8px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.15);
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
            padding: 30px;
            min-height: 100vh;
        }
        
        .card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            overflow: hidden;
        }
        
        .card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.1);
        }
        
        .card-header {
            background-color: white;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
            padding: 18px 25px;
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
        
        .search-container {
            position: relative;
            margin-bottom: 30px;
        }
        
        .search-input {
            padding: 15px 20px;
            border-radius: 50px;
            border: none;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
            padding-right: 50px;
            font-size: 1rem;
            transition: all 0.3s ease;
        }
        
        .search-input:focus {
            box-shadow: 0 4px 20px rgba(79, 70, 229, 0.2);
        }
        
        .search-btn {
            position: absolute;
            right: 5px;
            top: 5px;
            border: none;
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .search-btn:hover {
            transform: scale(1.05);
        }
        
        .food-card {
            transition: all 0.3s ease;
            border: 1px solid rgba(0, 0, 0, 0.05);
        }
        
        .food-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
        }
        
        .food-img {
            height: 180px;
            object-fit: cover;
            width: 100%;
        }
        
        .restaurant-card {
            transition: all 0.3s ease;
        }
        
        .restaurant-card:hover {
            transform: translateY(-5px);
        }
        
        .map-container {
            height: 300px;
            border-radius: 12px;
            overflow: hidden;
            margin-top: 15px;
        }
        
        .donate-btn {
            background: linear-gradient(135deg, #10B981, #059669);
            border: none;
            padding: 10px 20px;
            border-radius: 50px;
            color: white;
            font-weight: 600;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
        }
        
        .donate-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(16, 185, 129, 0.3);
            color: white;
        }
        
        .donate-btn i {
            margin-right: 8px;
        }
        
        .order-track {
            position: relative;
            padding-left: 30px;
            margin-bottom: 20px;
        }
        
        .order-track::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background-color: #E5E7EB;
        }
        
        .track-step {
            position: relative;
            margin-bottom: 15px;
        }
        
        .track-step::before {
            content: '';
            position: absolute;
            left: -30px;
            top: 4px;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background-color: #E5E7EB;
            z-index: 1;
        }
        
        .track-step.active::before {
            background-color: var(--primary);
            box-shadow: 0 0 0 4px rgba(79, 70, 229, 0.2);
        }
        
        .track-step.completed::before {
            background-color: var(--success);
        }
        
        .track-label {
            font-weight: 500;
            color: var(--dark);
        }
        
        .track-date {
            font-size: 0.8rem;
            color: #6B7280;
        }
        
        .user-avatar {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: var(--primary);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            text-transform: uppercase;
        }
        
        @media (max-width: 992px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            
            .main-content {
                margin-left: 0;
            }
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
                    <?php echo substr($userName, 0, 1); ?>
                </div>
                <div>
                    <h6 class="mb-0 text-white"><?php echo $userName; ?></h6>
                    <small class="text-white-50">Customer</small>
                </div>
            </div>
            
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="orders.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="mb-0">Welcome back, <?php echo $userName; ?>!</h2>
            <a href="donate.php" class="donate-btn">
                <i class="fas fa-gift"></i> Donate Food
            </a>
        </div>
        
        <!-- Food Search Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Find Food Near You</h5>
                <small class="text-muted">Search by food name, description, or restaurant</small>
            </div>
            <div class="card-body">
                <form method="GET" action="dashboard.php" class="mb-4">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="search-container">
                                <input type="text" class="form-control search-input" name="food_search" 
                                       placeholder="Search for food or restaurant..." 
                                       value="<?php echo htmlspecialchars($search_term); ?>">
                                <button type="submit" class="search-btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <select class="form-select" name="restaurant">
                                <option value="">All Restaurants</option>
                                <?php foreach ($restaurants as $rest): ?>
                                    <option value="<?php echo htmlspecialchars($rest); ?>" 
                                            <?php echo $restaurant_filter_input === $rest ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($rest); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="price_min" 
                                   placeholder="Min Price" value="<?php echo htmlspecialchars($price_min_input); ?>">
                        </div>
                        <div class="col-md-2">
                            <input type="number" class="form-control" name="price_max" 
                                   placeholder="Max Price" value="<?php echo htmlspecialchars($price_max_input); ?>">
                        </div>
                    </div>
                    <div class="row mt-3">
                        <div class="col-md-6">
                            <select class="form-select" name="sort" onchange="this.form.submit()">
                                <option value="name" <?php echo $sort === 'name' ? 'selected' : ''; ?>>Sort by Name</option>
                                <option value="price_low" <?php echo $sort === 'price_low' ? 'selected' : ''; ?>>Price: Low to High</option>
                                <option value="price_high" <?php echo $sort === 'price_high' ? 'selected' : ''; ?>>Price: High to Low</option>
                            </select>
                        </div>
                    </div>
                </form>
                
                <?php if (!empty($search_results)): ?>
                    <div class="row mt-4">
                        <?php foreach ($search_results as $item): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card food-card h-100">
                                    <?php if (!empty($item['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($item['image_url']); ?>" class="food-img" alt="<?php echo htmlspecialchars($item['food_name']); ?>">
                                    <?php else: ?>
                                        <div class="food-img bg-light d-flex align-items-center justify-content-center">
                                            <i class="fas fa-utensils fa-3x text-muted"></i>
                                        </div>
                                    <?php endif; ?>
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($item['food_name']); ?></h5>
                                        <p class="card-text text-muted"><?php echo htmlspecialchars($item['description']); ?></p>
                                        <div class="d-flex justify-content-between align-items-center">
                                            <span class="fw-bold text-primary">XAF <?php echo number_format($item['price'], 0); ?></span>
                                            <small class="text-muted"><?php echo htmlspecialchars($item['restaurant_name']); ?></small>
                                        </div>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <div class="d-flex justify-content-between">
                                            <a href="#" class="btn btn-sm btn-outline-primary" data-bs-toggle="modal" data-bs-target="#locationModal" 
                                               data-restaurant="<?php echo htmlspecialchars($item['restaurant_name']); ?>"
                                               data-address="<?php echo htmlspecialchars($item['address']); ?>"
                                               data-lat="<?php echo $item['latitude']; ?>"
                                               data-lng="<?php echo $item['longitude']; ?>">
                                                <i class="fas fa-map-marker-alt me-1"></i> View Location
                                            </a>
                                            <a href="order.php?food_id=<?php echo $item['food_id']; ?>" class="btn btn-sm btn-primary">
                                                <i class="fas fa-shopping-cart me-1"></i> Order Now
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Search results pages" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                                    <li class="page-item <?php echo $page === $i ? 'active' : ''; ?>">
                                        <a class="page-link" href="?food_search=<?php echo urlencode($search_term); ?>&page=<?php echo $i; ?>&sort=<?php echo urlencode($sort); ?>&price_min=<?php echo urlencode($price_min_input); ?>&price_max=<?php echo urlencode($price_max_input); ?>&restaurant=<?php echo urlencode($restaurant_filter_input); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                    
                <?php elseif (isset($_GET['food_search'])): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h5>No results found for "<?php echo htmlspecialchars($_GET['food_search']); ?>"</h5>
                        <p class="text-muted">Try adjusting your search criteria or filters</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Recent Orders Section -->
        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Recent Orders</h5>
                <a href="orders.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($recent_orders)): ?>
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
                            <tbody>
                                <?php foreach ($recent_orders as $order): ?>
                                    <tr>
                                        <td><strong>#<?php echo $order['order_id']; ?></strong></td>
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
                                        <td>
                                            <a href="order_tracking.php?order_id=<?php echo $order['order_id']; ?>" class="btn btn-sm btn-outline-primary">Track</a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-box-open fa-3x text-muted mb-3"></i>
                        <h5>No recent orders</h5>
                        <p class="text-muted">You haven't placed any orders yet</p>
                        <a href="restaurants.php" class="btn btn-primary">Browse Restaurants</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Nearby Restaurants Section -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Nearby Restaurants</h5>
                <a href="restaurants.php" class="btn btn-sm btn-outline-primary">View All</a>
            </div>
            <div class="card-body">
                <?php if (!empty($nearby_restaurants)): ?>
                    <div class="row">
                        <?php foreach ($nearby_restaurants as $rest): ?>
                            <div class="col-md-4 mb-4">
                                <div class="card restaurant-card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title"><?php echo htmlspecialchars($rest['restaurant_name']); ?></h5>
                                        <p class="card-text text-muted">
                                            <i class="fas fa-map-marker-alt text-danger me-1"></i>
                                            <?php echo htmlspecialchars($rest['address']); ?>
                                        </p>
                                        <div class="map-container" id="mini-map-<?php echo $rest['restaurant_id']; ?>"></div>
                                    </div>
                                    <div class="card-footer bg-white">
                                        <a href="restaurant_menu.php?rid=<?php echo $rest['restaurant_id']; ?>" class="btn btn-primary btn-sm w-100">
                                            <i class="fas fa-utensils me-1"></i> View Menu
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4">
                        <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                        <h5>No restaurants available</h5>
                        <p class="text-muted">Check back later for nearby restaurants</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Location Modal -->
    <div class="modal fade" id="locationModal" tabindex="-1" aria-labelledby="locationModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="locationModalLabel">Restaurant Location</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <h6 id="restaurantName"></h6>
                    <p class="text-muted" id="restaurantAddress"></p>
                    <div class="map-container" id="largeMap" style="height: 400px;"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <a href="#" id="directionsBtn" class="btn btn-primary" target="_blank">
                        <i class="fas fa-directions me-1"></i> Get Directions
                    </a>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.3/dist/leaflet.js"></script>
    <script>
        // Initialize mini maps for restaurants
        document.addEventListener('DOMContentLoaded', function() {
            <?php foreach ($nearby_restaurants as $rest): ?>
                if (document.getElementById('mini-map-<?php echo $rest['restaurant_id']; ?>')) {
                    const miniMap = L.map('mini-map-<?php echo $rest['restaurant_id']; ?>').setView([
                        <?php echo $rest['latitude'] ?: '4.0511'; ?>, 
                        <?php echo $rest['longitude'] ?: '9.7679'; ?>
                    ], 15);
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(miniMap);
                    
                    L.marker([
                        <?php echo $rest['latitude'] ?: '4.0511'; ?>, 
                        <?php echo $rest['longitude'] ?: '9.7679'; ?>
                    ]).addTo(miniMap)
                      .bindPopup('<?php echo addslashes($rest['restaurant_name']); ?>');
                }
            <?php endforeach; ?>
            
            // Setup location modal
            const locationModal = document.getElementById('locationModal');
            if (locationModal) {
                locationModal.addEventListener('show.bs.modal', function(event) {
                    const button = event.relatedTarget;
                    const restaurant = button.getAttribute('data-restaurant');
                    const address = button.getAttribute('data-address');
                    const lat = parseFloat(button.getAttribute('data-lat'));
                    const lng = parseFloat(button.getAttribute('data-lng'));
                    
                    document.getElementById('restaurantName').textContent = restaurant;
                    document.getElementById('restaurantAddress').textContent = address;
                    
                    // Initialize or update large map
                    if (window.largeMap) {
                        window.largeMap.remove();
                    }
                    
                    window.largeMap = L.map('largeMap').setView([lat || 4.0511, lng || 9.7679], 15);
                    
                    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
                    }).addTo(window.largeMap);
                    
                    L.marker([lat || 4.0511, lng || 9.7679]).addTo(window.largeMap)
                      .bindPopup(restaurant);
                      
                    // Update directions link
                    document.getElementById('directionsBtn').href = 
                        `https://www.google.com/maps/dir/?api=1&destination=${lat},${lng}`;
                });
            }
        });
    </script>
</body>
</html>