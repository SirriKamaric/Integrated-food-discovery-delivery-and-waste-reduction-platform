<?php
require_once '../session_check.php';
if (!hasAccess('restaurant')) {
    header("Location: ../unauthorized.php");
    exit();
}

require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Fetch restaurant details for sidebar
$restaurant_name_sidebar = 'FoodSave'; // Default name
$restaurant_logo_url_sidebar = 'https://via.placeholder.com/50'; // Default logo

$stmt_sidebar = $conn->prepare("SELECT restaurant_name, logo_url FROM restaurants WHERE user_id = ?");
$stmt_sidebar->bind_param("i", $_SESSION['user_id']);
$stmt_sidebar->execute();
$result_sidebar = $stmt_sidebar->get_result();
if ($result_sidebar->num_rows > 0) {
    $row_sidebar = $result_sidebar->fetch_assoc();
    $restaurant_name_sidebar = htmlspecialchars($row_sidebar['restaurant_name']);
    $restaurant_logo_url_sidebar = htmlspecialchars($row_sidebar['logo_url']);
}
$stmt_sidebar->close();

$restaurant_id = $_SESSION['user_id'];
$donations = [];
$result = $conn->query("SELECT d.*, n.ngo_name as ngo_name, u.full_name as donor_name 
                       FROM donations d
                       LEFT JOIN ngo n ON d.ngo_id = n.user_id
                       LEFT JOIN users u ON d.donor_id = u.user_id
                       WHERE d.user_id = $restaurant_id
                       ORDER BY d.created_at DESC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $donations[] = $row;
    }
}

// Add this at the top after DB connection
$add_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_donation'])) {
    $food_items = htmlspecialchars(trim($_POST['food_items']));
    $quantity = intval($_POST['quantity']);
    $pickup_datetime = $_POST['pickup_datetime'];
    $user_id = $_SESSION['user_id'];
    
    // First, get a default NGO ID (you might want to create a system NGO for this purpose)
    $default_ngo_query = "SELECT ngo_id FROM ngo WHERE user_id = 1 LIMIT 1"; // Assuming user_id 1 is a system NGO
    $default_ngo_result = $conn->query($default_ngo_query);
    $default_ngo = $default_ngo_result->fetch_assoc();
    $default_ngo_id = $default_ngo ? $default_ngo['ngo_id'] : 1; // Fallback to 1 if no system NGO exists
    
    $stmt = $conn->prepare("INSERT INTO donations (user_id, food_items, quantity, pickup_datetime, status, ngo_id) VALUES (?, ?, ?, ?, 'available', ?)");
    $stmt->bind_param("isisi", $user_id, $food_items, $quantity, $pickup_datetime, $default_ngo_id);
    
    if ($stmt->execute()) {
        $message = "Donation added successfully!";
        $message_type = "success";
    } else {
        $message = "Error adding donation: " . $conn->error;
        $message_type = "danger";
    }
    $stmt->close();
}

// Fetch restaurant profile data for sidebar/profile
$restaurant = [];
$query = "SELECT u.user_id, u.full_name, u.email, r.restaurant_name, r.address, r.phone
          FROM users u
          LEFT JOIN restaurants r ON u.user_id = r.user_id
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $restaurant = $result->fetch_assoc();
} else {
    $restaurant = [
        'user_id' => $_SESSION['user_id'],
        'full_name' => 'Restaurant Admin',
        'email' => 'N/A',
        'restaurant_name' => 'N/A',
        'address' => 'N/A',
        'phone' => 'N/A'
    ];
}
$stmt->close();

$userName = htmlspecialchars($restaurant['full_name'] ?? 'Restaurant Admin');
$userEmail = htmlspecialchars($restaurant['email'] ?? 'N/A');

// Get all donations for this restaurant
$donations = [];
$query = "SELECT d.donation_id, d.food_items, d.quantity, d.pickup_datetime, d.status, d.created_at, n.ngo_name
         FROM donations d
         LEFT JOIN ngo n ON d.ngo_id = n.ngo_id
         WHERE d.user_id = ?
         ORDER BY d.pickup_datetime DESC";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $donations[] = $row;
    }
}
$stmt->close();

// Close the database connection after all operations are complete
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Donations Management | FoodSave</title>
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
        
        .donation-status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .status-available {
            background-color: #D1FAE5;
            color: #065F46;
        }
        
        .status-reserved {
            background-color: #FEF3C7;
            color: #92400E;
        }
        
        .status-claimed {
            background-color: #DBEAFE;
            color: #1E40AF;
        }
        
        .status-distributed {
            background-color: #E0E7FF;
            color: #4338CA;
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
            
            .stat-card .card-body {
                flex-direction: column;
                text-align: center;
            }
            
            .stat-card .icon-container {
                margin-right: 0;
                margin-bottom: 10px;
            }
            
            .header-welcome {
                font-size: 1.2rem;
            }
            
            .card-header {
                padding: 12px 15px;
            }
            
            .stats-row .col-md-3 {
                margin-bottom: 15px;
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
            
            .donation-status-badge {
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
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-clipboard-list"></i> Orders
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="donations.php">
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
                <span class="header-welcome">Donations Management</span>
            </div>
            <div class="d-flex align-items-center header-right">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button id="notificationBtn" class="btn btn-info me-2 notification-bell position-relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count"></span>
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDonationModal">
                    <i class="fas fa-plus me-2"></i>Add Donation
                </button>
            </div>
        </div>

        <div class="row mb-4 stats-row">
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="icon-container">
                            <i class="fas fa-check-circle card-icon"></i>
                        </div>
                        <div>
                            <h5 class="card-title"><?php echo count(array_filter($donations, fn($d) => $d['status'] === 'available')); ?></h5>
                            <p class="card-text text-muted">Available</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="icon-container">
                            <i class="fas fa-clock card-icon"></i>
                        </div>
                        <div>
                            <h5 class="card-title"><?php echo count(array_filter($donations, fn($d) => $d['status'] === 'reserved')); ?></h5>
                            <p class="card-text text-muted">Reserved</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="icon-container">
                            <i class="fas fa-check card-icon"></i>
                        </div>
                        <div>
                            <h5 class="card-title"><?php echo count(array_filter($donations, fn($d) => $d['status'] === 'claimed')); ?></h5>
                            <p class="card-text text-muted">Claimed</p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 col-6">
                <div class="card stat-card">
                    <div class="card-body">
                        <div class="icon-container">
                            <i class="fas fa-box-open card-icon"></i>
                        </div>
                        <div>
                            <h5 class="card-title"><?php echo count(array_filter($donations, fn($d) => $d['status'] === 'distributed')); ?></h5>
                            <p class="card-text text-muted">Distributed</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <div class="d-flex justify-content-between align-items-center">
                    <h5 class="mb-0">All Donations</h5>
                    <div class="btn-group">
                        <button class="btn btn-sm btn-outline-primary">Export</button>
                        <button class="btn btn-sm btn-outline-secondary">Filter</button>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Food Description</th>
                                <th>Quantity</th>
                                <th>Pickup Time</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($donations as $donation): ?>
                            <tr>
                                <td><strong>#<?php echo $donation['donation_id']; ?></strong></td>
                                <td><?php echo htmlspecialchars($donation['food_items'] ?? 'Not specified'); ?></td>
                                <td><?php echo $donation['quantity']; ?> meals</td>
                                <td><?php echo isset($donation['pickup_datetime']) ? date('M d, Y H:i', strtotime($donation['pickup_datetime'])) : 'Flexible'; ?></td>
                                <td>
                                    <span class="donation-status-badge 
                                        <?php 
                                        switch(strtolower($donation['status'])) {
                                            case 'available': echo 'status-available'; break;
                                            case 'reserved': echo 'status-reserved'; break;
                                            case 'claimed': echo 'status-claimed'; break;
                                            case 'distributed': echo 'status-distributed'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst($donation['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex">
                                        <button class="btn btn-sm btn-outline-primary me-2" data-bs-toggle="modal" 
                                                data-bs-target="#donationDetailsModal<?php echo $donation['donation_id']; ?>">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($donation['status'] === 'available'): ?>
                                            <button class="btn btn-sm btn-outline-danger" onclick="deleteDonation(<?php echo $donation['donation_id']; ?>)">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
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
        <a href="orders.php" class="mobile-nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Orders</span>
        </a>
        <a href="donations.php" class="mobile-nav-item active">
            <i class="fas fa-donate"></i>
            <span>Donations</span>
        </a>
        <a href="profile.php" class="mobile-nav-item">
            <i class="fas fa-store"></i>
            <span>Profile</span>
        </a>
    </div>

    <!-- Add Donation Modal -->
    <div class="modal fade" id="addDonationModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Food Donation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if (!empty($add_success)): ?>
                        <div class="alert alert-success"> <?php echo $add_success; ?> </div>
                    <?php endif; ?>
                    <form method="POST">
                        <input type="hidden" name="submit_donation" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Food Items</label>
                                <input type="text" class="form-control" name="food_items" placeholder="e.g., Pasta, Salad, Bread" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Quantity (meals)</label>
                                <input type="number" class="form-control" name="quantity" required>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Pickup Date and Time</label>
                                <input type="datetime-local" class="form-control" name="pickup_datetime" required>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">Add Donation</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Donation Details Modals -->
    <?php foreach ($donations as $donation): ?>
    <div class="modal fade" id="donationDetailsModal<?php echo $donation['donation_id']; ?>" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Donation #<?php echo $donation['donation_id']; ?></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <h6>Food Information</h6>
                        <p class="mb-1"><strong>Items:</strong> <?php echo htmlspecialchars($donation['food_items']); ?></p>
                        <p class="mb-1"><strong>Quantity:</strong> <?php echo $donation['quantity']; ?> meals</p>
                    </div>
                    
                    <div class="mb-3">
                        <h6>Status Information</h6>
                        <p class="mb-1"><strong>Status:</strong> 
                            <span class="donation-status-badge 
                                <?php 
                                switch(strtolower($donation['status'])) {
                                    case 'available': echo 'status-available'; break;
                                    case 'reserved': echo 'status-reserved'; break;
                                    case 'claimed': echo 'status-claimed'; break;
                                    case 'distributed': echo 'status-distributed'; break;
                                    default: echo 'bg-secondary';
                                }
                                ?>">
                                <?php echo ucfirst($donation['status']); ?>
                            </span>
                        </p>
                        <p class="mb-1"><strong>Pickup Time:</strong> <?php echo isset($donation['pickup_datetime']) ? date('M d, Y H:i', strtotime($donation['pickup_datetime'])) : 'Flexible'; ?></p>
                        <p class="mb-1"><strong>Created:</strong> <?php echo isset($donation['created_at']) ? date('M d, Y', strtotime($donation['created_at'])) : 'N/A'; ?></p>
                    </div>

                    <div class="mb-3">
                        <h6>Donation Details</h6>
                        <p class="mb-1"><strong>NGO:</strong> <?php echo htmlspecialchars($donation['ngo_name'] ?? 'Not assigned'); ?></p>
                    </div>

                    <?php if ($donation['status'] === 'available'): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-circle me-2"></i>
                        This donation needs to be claimed by an NGO before expiry
                    </div>
                    <?php elseif ($donation['status'] === 'reserved'): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle me-2"></i>
                        This donation has been reserved by an NGO for pickup
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <?php if ($donation['status'] === 'available'): ?>
                    <button type="button" class="btn btn-primary">Edit</button>
                    <?php elseif ($donation['status'] === 'reserved'): ?>
                    <button type="button" class="btn btn-success">Mark as Claimed</button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
    <?php endforeach; ?>

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

            // Delete donation function
            window.deleteDonation = function(donationId) {
                if (confirm('Are you sure you want to delete this donation?')) {
                    fetch('delete_donation.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `donation_id=${donationId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert('Donation deleted successfully');
                            location.reload();
                        } else {
                            alert('Error deleting donation: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while deleting the donation');
                    });
                }
            };
        });
    </script>
</body>
</html>