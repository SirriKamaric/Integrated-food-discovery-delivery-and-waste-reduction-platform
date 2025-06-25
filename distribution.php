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

// Get all distributions (filtered by NGO if user is ngo_admin)
$distributions = [];
$query = "SELECT d.distribution_id, n.ngo_name, r.restaurant_name, 
         d.distribution_date, d.status
         FROM distributions d
         LEFT JOIN ngo n ON d.ngo_id = n.user_id
         LEFT JOIN restaurants r ON d.restaurant_id = r.user_id";

if ($_SESSION['user_type'] === 'ngo_admin') {
    $query .= " WHERE d.ngo_id = " . $_SESSION['user_id'];
}

$query .= " ORDER BY d.distribution_date DESC";

$result = $conn->query($query);
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $distributions[] = $row;
    }
}
$conn->close();

// After PHP logic and before HTML
$userName = htmlspecialchars($ngo['full_name'] ?? 'NGO Admin');
$userEmail = htmlspecialchars($ngo['email'] ?? 'N/A');
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Food Distributions | FoodSave</title>
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
                    <a class="nav-link" href="donations.php">
                        <i class="fas fa-hand-holding-heart"></i> Donations
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link" href="requests.php">
                        <i class="fas fa-clipboard-list"></i> Requests
                    </a>
                </li>
                <li class="nav-item mb-2">
                    <a class="nav-link active" href="distribution.php">
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
                <span class="header-welcome">Food Distributions</span>
            </div>
            <div class="d-flex align-items-center header-right">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button id="notificationBtn" class="btn btn-info me-2 notification-bell position-relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count"></span>
                </button>
                <a href="#" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDistributionModal">
                    <i class="fas fa-plus me-2"></i> New Distribution
                </a>
            </div>
        </div>

        <div class="card">
            <div class="card-body">
                <?php if (empty($distributions)): ?>
                    <div class="empty-state">
                        <i class="fas fa-truck"></i>
                        <h3>No Distributions Recorded</h3>
                        <p class="text-muted">There are currently no distribution records in the system.</p>
                        <button class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addDistributionModal">
                            <i class="fas fa-plus me-2"></i>Add First Distribution
                        </button>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>ID</th>
                                    <th>NGO</th>
                                    <th>Restaurant</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($distributions as $dist): ?>
                                <tr>
                                    <td><?php echo $dist['distribution_id']; ?></td>
                                    <td><?php echo htmlspecialchars($dist['ngo_name']); ?></td>
                                    <td><?php echo htmlspecialchars($dist['restaurant_name']); ?></td>
                                    <td><?php echo date('M d, Y', strtotime($dist['distribution_date'])); ?></td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $dist['status'] === 'completed' ? 'success' : 
                                                 ($dist['status'] === 'scheduled' ? 'primary' : 'warning'); ?>">
                                            <?php echo ucfirst($dist['status']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-wrap">
                                            <button class="btn btn-sm btn-outline-primary me-2 mb-2 action-btn">
                                                <i class="fas fa-eye me-1"></i>View
                                            </button>
                                            <button class="btn btn-sm btn-outline-secondary mb-2 action-btn">
                                                <i class="fas fa-edit me-1"></i>Edit
                                            </button>
                                        </div>
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
        <a href="donations.php" class="mobile-nav-item">
            <i class="fas fa-hand-holding-heart"></i>
            <span>Donations</span>
        </a>
        <a href="requests.php" class="mobile-nav-item">
            <i class="fas fa-clipboard-list"></i>
            <span>Requests</span>
        </a>
        <a href="distribution.php" class="mobile-nav-item active">
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