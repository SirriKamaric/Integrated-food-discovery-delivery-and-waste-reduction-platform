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

$message = '';
$message_type = '';

// Handle profile update POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    // ... [keep all your existing PHP code for handling profile updates] ...
}

// Fetch NGO profile data
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
$conn->close();

$userName = htmlspecialchars($ngo['full_name'] ?? 'NGO Admin');
$userEmail = htmlspecialchars($ngo['email'] ?? 'N/A');

// Get message from URL if redirected
if (isset($_GET['message']) && isset($_GET['type'])) {
    $message = htmlspecialchars($_GET['message']);
    $message_type = htmlspecialchars($_GET['type']);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>NGO Profile | FoodSave</title>
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
        
        .profile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            text-align: center;
            gap: 1rem;
            margin-bottom: 2rem;
            padding: 2rem;
            background: white;
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }

        .profile-avatar {
            position: relative;
            width: 120px;
            height: 120px;
            border-radius: 50%;
            overflow: hidden;
            background-color: var(--primary);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .profile-avatar img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }

        .avatar-placeholder {
            font-size: 3rem;
            color: white;
            font-weight: 600;
        }

        .profile-info {
            flex: 1;
        }

        .nav-pills .nav-link.active {
            background-color: var(--primary);
        }
        
        .nav-pills .nav-link {
            color: var(--dark);
        }
        
        .account-info-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: rgba(236, 72, 153, 0.1);
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
            color: var(--primary);
        }
        
        .default-badge {
            background-color: var(--accent);
            color: white;
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
            
            .profile-header {
                flex-direction: column;
                text-align: center;
            }
            
            .profile-info {
                margin-top: 1rem;
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
            
            .header-welcome {
                font-size: 1.2rem;
            }
            
            .card-header {
                padding: 12px 15px;
            }
            
            .nav-pills .nav-item {
                width: 100%;
                text-align: center;
            }
            
            .nav-pills .nav-link {
                padding: 0.5rem;
                font-size: 0.9rem;
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
            
            .profile-avatar {
                width: 80px;
                height: 80px;
            }
            
            .avatar-placeholder {
                font-size: 2rem;
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
            <?php
            $profile_pic_url_sidebar = htmlspecialchars($ngo['profile_picture_url'] ?? '');
            $display_profile_pic_src_sidebar = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=random';
            if (!empty($profile_pic_url_sidebar)) {
                $full_server_path_sidebar = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $profile_pic_url_sidebar;
                $full_server_path_sidebar = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_server_path_sidebar);
                if (file_exists($full_server_path_sidebar)) {
                    $display_profile_pic_src_sidebar = '/food_delivery_system/' . $profile_pic_url_sidebar;
                    $display_profile_pic_src_sidebar = str_replace('\\', '/', $display_profile_pic_src_sidebar);
                }
            }
            ?>
            <img src="<?php echo $display_profile_pic_src_sidebar; ?>" 
                 alt="Profile Picture" class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
            <h5 class="text-white mb-0"><?php echo $ngo['ngo_name'] ?? 'NGO'; ?></h5>
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
                    <a class="nav-link active" href="ngo_profile.php">
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
        <?php if (!empty($message)): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
            </div>
        <?php endif; ?>
        
        <div class="d-flex justify-content-between align-items-center mb-4 dashboard-header-row">
            <div class="d-flex align-items-center header-left">
                <button class="menu-toggle-btn" id="menuToggleBtn" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <span class="header-welcome">NGO Profile</span>
            </div>
            <div class="d-flex align-items-center header-right">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button id="notificationBtn" class="btn btn-info me-2 notification-bell position-relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count"></span>
                </button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </button>
            </div>
        </div>

        <div class="profile-header">
            <div class="profile-avatar">
                <?php 
                $profile_pic_url = $ngo['profile_picture_url'] ?? '';
                if (!empty($profile_pic_url)) {
                    $full_server_path = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $profile_pic_url;
                    $display_web_path = '/food_delivery_system/' . $profile_pic_url;
                    if (file_exists($full_server_path)) {
                        echo '<img src="' . htmlspecialchars($display_web_path) . '" 
                                 alt="Profile Picture" 
                                 class="rounded-circle"
                                 style="width: 120px; height: 120px; object-fit: cover;" />';
                    } else {
                        echo '<div class="avatar-placeholder">' . strtoupper(substr($ngo['full_name'], 0, 1)) . '</div>';
                    }
                } else {
                    echo '<div class="avatar-placeholder">' . strtoupper(substr($ngo['full_name'], 0, 1)) . '</div>';
                }
                ?>
            </div>
            <div class="profile-info">
                <h4><?php echo htmlspecialchars($ngo['full_name']); ?></h4>
                <p class="text-muted"><?php echo htmlspecialchars($ngo['email']); ?></p>
            </div>
        </div>

        <div class="row">
            <div class="col-md-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5>Account Information</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex mb-4">
                            <div class="account-info-icon">
                                <i class="fas fa-user"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Admin Info</h6>
                                <p class="text-muted small">Update NGO Admin name and email</p>
                            </div>
                        </div>
                        <div class="d-flex mb-4">
                            <div class="account-info-icon">
                                <i class="fas fa-building"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Organization Details</h6>
                                <p class="text-muted small">Update NGO name, registration, phone, and address</p>
                            </div>
                        </div>
                         <div class="d-flex">
                            <div class="account-info-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Notifications</h6>
                                <p class="text-muted small">View system notifications</p>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5>Account Security</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-shield-alt me-2"></i> Last login: <?php echo date('F j, Y, g:i a', strtotime($_SESSION['last_login'] ?? 'now')); ?>
                        </div>
                        <button class="btn btn-outline-primary w-100 mb-2" data-bs-toggle="modal" data-bs-target="#changePasswordModal">
                            <i class="fas fa-lock me-2"></i> Change Password
                        </button>
                        <button class="btn btn-outline-secondary w-100">
                            <i class="fas fa-list me-2"></i> View login activity
                        </button>
                    </div>
                </div>
            </div>

            <div class="col-md-8">
                <div class="card mb-4">
                    <div class="card-header">
                        <ul class="nav nav-pills">
                            <li class="nav-item">
                                <a class="nav-link active" href="#personal" data-bs-toggle="tab">Admin Info</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#organization" data-bs-toggle="tab">Organization Details</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#security" data-bs-toggle="tab">Security</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="personal">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="mb-3">
                                        <label for="profilePicture" class="form-label">Profile Picture</label>
                                        <input class="form-control" type="file" id="profilePicture" name="profile_picture" accept="image/*">
                                        <?php if (!empty($ngo['profile_picture_url'])): ?>
                                            <small class="form-text text-muted">Current: <a href="../<?php echo htmlspecialchars($ngo['profile_picture_url']); ?>" target="_blank">View Current Picture</a></small>
                                        <?php endif; ?>
                                    </div>
                                    <div class="mb-3">
                                        <label for="adminFullName" class="form-label">Full Name (Admin)</label>
                                        <input type="text" class="form-control" id="adminFullName" name="adminName" value="<?php echo htmlspecialchars($ngo['full_name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="adminEmail" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="adminEmail" value="<?php echo htmlspecialchars($ngo['email'] ?? ''); ?>" disabled>
                                        <small class="form-text text-muted">Email cannot be changed from this page.</small>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Admin Info</button>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="organization">
                                <form method="POST" enctype="multipart/form-data">
                                    <input type="hidden" name="update_profile" value="1">
                                    <div class="mb-3">
                                        <label for="ngoName" class="form-label">NGO Name</label>
                                        <input type="text" class="form-control" id="ngoName" name="ngoName" value="<?php echo htmlspecialchars($ngo['ngo_name'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="registrationNumber" class="form-label">Registration Number</label>
                                        <input type="text" class="form-control" id="registrationNumber" name="registrationNumber" value="<?php echo htmlspecialchars($ngo['registration_number'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="ngoPhone" class="form-label">Phone Number</label>
                                        <input type="text" class="form-control" id="ngoPhone" name="ngoPhone" value="<?php echo htmlspecialchars($ngo['phone'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="ngoAddress" class="form-label">Address</label>
                                        <input type="text" class="form-control" id="ngoAddress" name="ngoAddress" value="<?php echo htmlspecialchars($ngo['address'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" class="btn btn-primary">Update Organization Info</button>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="security">
                                <h5>Change Password</h5>
                                <form action="../change_password.php" method="POST">
                                    <div class="mb-3">
                                        <label for="currentPassword" class="form-label">Current Password</label>
                                        <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="newPassword" class="form-label">New Password</label>
                                        <input type="password" class="form-control" id="newPassword" name="new_password" required>
                                    </div>
                                    <div class="mb-3">
                                        <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
                                        <input type="password" class="form-control" id="confirmNewPassword" name="confirm_new_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Change Password</button>
                                </form>
                                <h5 class="mt-4">Two-Factor Authentication</h5>
                                <p class="text-muted">Enable 2FA for an extra layer of security.</p>
                                <button class="btn btn-outline-primary">Set up 2FA</button>
                            </div>
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
        <a href="distribution.php" class="mobile-nav-item">
            <i class="fas fa-truck"></i>
            <span>Distribute</span>
        </a>
        <a href="ngo_profile.php" class="mobile-nav-item active">
            <i class="fas fa-building"></i>
            <span>Profile</span>
        </a>
    </div>

    <!-- Edit Profile Modal -->
    <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editProfileModalLabel">Edit Profile</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form action="ngo_profile.php" method="POST" enctype="multipart/form-data">
                    <div class="modal-body">
                        <input type="hidden" name="update_profile" value="1">
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="adminName" class="form-label">Full Name</label>
                                <input type="text" class="form-control" id="adminName" name="adminName" value="<?php echo htmlspecialchars($ngo['full_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="adminEmail" class="form-label">Email</label>
                                <input type="email" class="form-control" id="adminEmail" value="<?php echo htmlspecialchars($ngo['email']); ?>" readonly>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ngoName" class="form-label">NGO Name</label>
                                <input type="text" class="form-control" id="ngoName" name="ngoName" value="<?php echo htmlspecialchars($ngo['ngo_name']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="registrationNumber" class="form-label">Registration Number</label>
                                <input type="text" class="form-control" id="registrationNumber" name="registrationNumber" value="<?php echo htmlspecialchars($ngo['registration_number']); ?>" required>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-6">
                                <label for="ngoPhone" class="form-label">Phone</label>
                                <input type="tel" class="form-control" id="ngoPhone" name="ngoPhone" value="<?php echo htmlspecialchars($ngo['phone']); ?>" required>
                            </div>
                            <div class="col-md-6">
                                <label for="ngoAddress" class="form-label">Address</label>
                                <input type="text" class="form-control" id="ngoAddress" name="ngoAddress" value="<?php echo htmlspecialchars($ngo['address']); ?>" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="profile_picture" class="form-label">Profile Picture</label>
                            <input type="file" class="form-control" id="profile_picture" name="profile_picture" accept="image/*">
                            <small class="text-muted">Allowed formats: JPG, JPEG, PNG, GIF</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Change Password Modal -->
    <div class="modal fade" id="changePasswordModal" tabindex="-1" aria-labelledby="changePasswordModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="changePasswordModalLabel">Change Password</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form action="../change_password.php" method="POST">
                        <div class="mb-3">
                            <label for="currentPassword" class="form-label">Current Password</label>
                            <input type="password" class="form-control" id="currentPassword" name="current_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="newPassword" class="form-label">New Password</label>
                            <input type="password" class="form-control" id="newPassword" name="new_password" required>
                        </div>
                        <div class="mb-3">
                            <label for="confirmNewPassword" class="form-label">Confirm New Password</label>
                            <input type="password" class="form-control" id="confirmNewPassword" name="confirm_new_password" required>
                        </div>
                        <button type="submit" class="btn btn-primary">Change Password</button>
                    </form>
                </div>
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
                if (currentTheme === 'dark-mode') {
                    themeToggleBtn.querySelector('i').classList.replace('fa-moon', 'fa-sun');
                } else {
                    themeToggleBtn.querySelector('i').classList.replace('fa-sun', 'fa-moon');
                }
            }

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

            // Notification Button Functionality
            const notificationBtn = document.getElementById('notificationBtn');
            const notificationModal = new bootstrap.Modal(document.getElementById('notificationModal'));
            const notificationModalBody = document.getElementById('notificationModalBody');
            const notificationCountSpan = document.getElementById('notificationCount');

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
                        fetchNotifications();
                    } else {
                        console.error('Failed to mark as read:', data.message);
                    }
                })
                .catch(error => {
                    console.error('Error marking as read:', error);
                });
            }

            if (notificationBtn) {
                notificationBtn.addEventListener('click', function() {
                    notificationModal.show();
                    fetchNotifications();
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