<?php
require_once '../session_check.php';
require_once '../db_config.php';

if (!in_array($_SESSION['user_type'], ['ngo', 'ngo_admin'])) {
    header("Location: ../unauthorized.php");
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add this after DB connection and before fetching donations
$claim_success = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['claim_donation_id'])) {
    $claim_id = intval($_POST['claim_donation_id']);
    $user_id = $_SESSION['user_id'];
    
    // First get the NGO ID for this user
    $ngo_query = "SELECT ngo_id FROM ngo WHERE user_id = ?";
    $stmt_ngo = $conn->prepare($ngo_query);
    $stmt_ngo->bind_param("i", $user_id);
    $stmt_ngo->execute();
    $ngo_result = $stmt_ngo->get_result();
    
    if ($ngo_result->num_rows > 0) {
        $ngo_data = $ngo_result->fetch_assoc();
        $ngo_id = $ngo_data['ngo_id'];
        
        // Check if the claim is for a surplus food donation or customer food donation
        $stmt_check_type = $conn->prepare("SELECT 1 FROM surplus_food WHERE surplus_id = ? AND status = 'available'");
        $stmt_check_type->bind_param("i", $claim_id);
        $stmt_check_type->execute();
        $result_check_type = $stmt_check_type->get_result();

        if ($result_check_type->num_rows > 0) {
            // It's a surplus food donation from a restaurant
            $stmt = $conn->prepare("UPDATE surplus_food SET status = 'reserved', ngo_id = ? WHERE surplus_id = ? AND status = 'available'");
            $stmt->bind_param("ii", $ngo_id, $claim_id);
        } else {
            // Assume it's a customer food donation
            $stmt = $conn->prepare("UPDATE customer_food_donations SET status = 'scheduled', ngo_id = ? WHERE food_donation_id = ? AND status = 'pending'");
            $stmt->bind_param("ii", $ngo_id, $claim_id);
        }
        
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            $claim_success = 'Donation claimed successfully!';
        } else {
            $claim_success = 'Error claiming donation or donation not available.';
        }
        $stmt->close();
        $stmt_check_type->close();
    } else {
        $claim_success = 'NGO not found.';
    }
    $stmt_ngo->close();
}

// Fetch NGO profile data for sidebar/profile
$ngo = [];
$query = "SELECT u.user_id, u.full_name, u.email, n.ngo_name, n.address, n.phone, n.registration_number, n.profile_picture_url
          FROM users u
          LEFT JOIN ngo n ON u.user_id = n.user_id
          WHERE u.user_id = ?";
$stmt = $conn->prepare($query);
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
if ($result->num_rows > 0) {
    $ngo = $result->fetch_assoc();
} else {
    $ngo = [
        'user_id' => $_SESSION['user_id'],
        'full_name' => 'NGO Admin',
        'email' => 'N/A',
        'ngo_name' => 'N/A',
        'address' => 'N/A',
        'phone' => 'N/A',
        'registration_number' => 'N/A',
        'profile_picture_url' => ''
    ];
}
$stmt->close();

$userName = htmlspecialchars($ngo['full_name'] ?? 'NGO Admin');
$userEmail = htmlspecialchars($ngo['email'] ?? 'N/A');

// Get all available food donations from restaurants and customers
$donations = [];
$query = "
    SELECT 
        sf.surplus_id AS donation_id,
        'Restaurant' AS source_type,
        r.restaurant_name AS source_name,
        sf.item_name AS food_item,
        sf.quantity,
        sf.expiry_date AS date,
        sf.status,
        sf.pickup_address, -- Assuming pickup_address in surplus_food
        NULL AS contact_phone -- Placeholder for consistent columns
    FROM surplus_food sf
    JOIN restaurants r ON sf.restaurant_id = r.restaurant_id
    WHERE sf.status = 'available'

    UNION ALL

    SELECT
        cfd.food_donation_id AS donation_id,
        'Customer' AS source_type,
        CONCAT(u.first_name, ' ', u.last_name) AS source_name, -- Assuming users table has first_name, last_name
        cfd.food_name AS food_item,
        cfd.quantity,
        cfd.expiry_date AS date,
        cfd.status,
        cfd.pickup_address,
        cfd.contact_phone
    FROM customer_food_donations cfd
    JOIN users u ON cfd.user_id = u.user_id
    WHERE cfd.status = 'pending'

    ORDER BY date ASC
";

$result = $conn->query($query);
if ($result && $result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $donations[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Available Donations | FoodSave</title>
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

        .empty-state {
            text-align: center;
            padding: 40px 20px;
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 20px;
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
            
            .table td, .table th {
                white-space: nowrap;
            }
            
            .action-btn {
                margin-bottom: 5px;
                width: 100%;
            }
            
            .empty-state i {
                font-size: 3rem;
            }
        }
        
        @media (max-width: 575px) {
            .main-content {
                padding: 8px;
            }
            
            .table td, .table th {
                padding: 0.5rem;
            }
            
            .empty-state {
                padding: 20px 10px;
            }
        }
        
        /* Mobile bottom navigation */
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
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="sidebar-header text-center">
            <?php
            $profile_pic_url_sidebar = htmlspecialchars($ngo['profile_picture_url'] ?? '');
            $display_profile_pic_src_sidebar = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=random';
            if (!empty($profile_pic_url_sidebar)) {
                $full_server_path_sidebar = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $profile_pic_url_sidebar;
                $full_server_path_sidebar = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_server_path_sidebar);
                if (file_exists($full_server_path_sidebar) && !is_dir($full_server_path_sidebar)) {
                    $display_profile_pic_src_sidebar = '/food_delivery_system/' . $profile_pic_url_sidebar;
                    $display_profile_pic_src_sidebar = str_replace('\\', '/', $display_profile_pic_src_sidebar);
                }
            }
            ?>
            <img src="<?php echo $display_profile_pic_src_sidebar; ?>" 
                 alt="Profile Picture" class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
            <h5 class="text-white mb-0"><?php echo htmlspecialchars($ngo['ngo_name'] ?? 'NGO'); ?></h5>
            <small class="text-white-50">NGO Panel</small>
        </div>
        <div class="px-3 py-4">
            <ul class="nav flex-column">
                <li class="nav-item mb-2">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="donations.php">
                        <i class="fas fa-hand-holding-heart"></i> Donations
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="requests.php">
                        <i class="fas fa-clipboard-list"></i> Requests
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="distribution.php">
                        <i class="fas fa-truck"></i> Distributions
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="beneficiaries.php">
                        <i class="fas fa-users"></i> Beneficiaries
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="ngo_profile.php">
                        <i class="fas fa-building"></i> NGO Profile
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
                <span class="header-welcome">Available Donations</span>
            </div>
            <div class="d-flex align-items-center header-right">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button id="notificationBtn" class="btn btn-info me-2 notification-bell position-relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count"></span>
                </button>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (!empty($claim_success)): ?>
                    <div class="alert alert-success"><?php echo $claim_success; ?></div>
                <?php endif; ?>
                <?php if (empty($donations)): ?>
                    <div class="empty-state">
                        <i class="fas fa-database"></i>
                        <h3>No Donations Available</h3>
                        <p class="text-muted">There are currently no food donations available.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th scope="col">Donation ID</th>
                                    <th scope="col">Source</th>
                                    <th scope="col">Source Name</th>
                                    <th scope="col">Food Item</th>
                                    <th scope="col">Quantity</th>
                                    <th scope="col">Expiry Date</th>
                                    <th scope="col">Status</th>
                                    <th scope="col">Pickup Address</th>
                                    <th scope="col">Contact</th>
                                    <th scope="col">Action</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($donations as $donation): ?>
                                <tr>
                                    <td data-label="Donation ID">#<?php echo htmlspecialchars($donation['donation_id']); ?></td>
                                    <td data-label="Source"><?php echo htmlspecialchars($donation['source_type']); ?></td>
                                    <td data-label="Source Name"><?php echo htmlspecialchars($donation['source_name']); ?></td>
                                    <td data-label="Food Item"><?php echo htmlspecialchars($donation['food_item']); ?></td>
                                    <td data-label="Quantity"><?php echo htmlspecialchars($donation['quantity']); ?></td>
                                    <td data-label="Expiry Date"><?php echo htmlspecialchars($donation['date']); ?></td>
                                    <td data-label="Status">
                                        <span class="badge bg-<?php 
                                            echo $donation['status'] === 'available' ? 'success' : 
                                                 ($donation['status'] === 'pending' ? 'warning' : 'primary'); ?>">
                                            <?php echo htmlspecialchars($donation['status']); ?>
                                        </span>
                                    </td>
                                    <td data-label="Pickup Address"><?php echo htmlspecialchars($donation['pickup_address']); ?></td>
                                    <td data-label="Contact"><?php echo htmlspecialchars($donation['contact_phone'] ?? 'N/A'); ?></td>
                                    <td data-label="Action">
                                        <?php if ($donation['status'] === 'available' || $donation['status'] === 'pending'): ?>
                                            <form method="POST" style="display:inline;">
                                                <input type="hidden" name="claim_donation_id" value="<?php echo htmlspecialchars($donation['donation_id']); ?>">
                                                <button type="submit" class="btn btn-sm btn-primary">Claim</button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted">N/A</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Mobile Bottom Navigation -->
    <div class="mobile-navbar">
        <a href="dashboard.php" class="mobile-nav-item">
            <i class="fas fa-home"></i>
            <span>Dashboard</span>
        </a>
        <a href="donations.php" class="mobile-nav-item active">
            <i class="fas fa-hand-holding-heart"></i>
            <span>Donations</span>
        </a>
        <a href="requests.php" class="mobile-nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Requests</span>
        </a>
        <a href="distribution.php" class="mobile-nav-item">
            <i class="fas fa-truck"></i>
            <span>Distributions</span>
        </a>
        <a href="ngo_profile.php" class="mobile-nav-item">
            <i class="fas fa-building"></i>
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
                fetch('../ngo/fetch_notifications.php')
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
                fetch('../ngo/mark_notification_read.php', {
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
        });
    </script>
</body>
</html>