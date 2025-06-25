<?php
require_once '../session_check.php';
if (!hasAccess('restaurant')) {
    header("Location: ../unauthorized.php");
    exit();
}

require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$user_id = $_SESSION['user_id']; // This is the user_id from the users table
$message = '';

// Fetch restaurant details for sidebar
$restaurant_name_sidebar = 'FoodSave'; // Default name
$restaurant_logo_url_sidebar = 'https://via.placeholder.com/50'; // Default logo

$stmt_sidebar = $conn->prepare("SELECT restaurant_name, logo_url FROM restaurants WHERE user_id = ?");
$stmt_sidebar->bind_param("i", $user_id);
$stmt_sidebar->execute();
$result_sidebar = $stmt_sidebar->get_result();
if ($result_sidebar->num_rows > 0) {
    $row_sidebar = $result_sidebar->fetch_assoc();
    $restaurant_name_sidebar = htmlspecialchars($row_sidebar['restaurant_name']);
    $restaurant_logo_url_sidebar = htmlspecialchars($row_sidebar['logo_url']);
}
$stmt_sidebar->close();

// Fetch user's email (assuming it's in the users table)
$user_email = '';
$stmt_user = $conn->prepare("SELECT email FROM users WHERE user_id = ?");
$stmt_user->bind_param("i", $user_id);
$stmt_user->execute();
$result_user = $stmt_user->get_result();
if ($result_user->num_rows > 0) {
    $user_email = $result_user->fetch_assoc()['email'];
}
$stmt_user->close();

// Fetch restaurant details
$restaurant_data = [
    'restaurant_id' => null,
    'restaurant_name' => '',
    'description' => '',
    'cuisine_type' => '',
    'address' => '',
    'phone_number' => '',
    'opening_time' => '',
    'closing_time' => '',
    'rating' => null,
    'logo_url' => 'https://via.placeholder.com/150',
    'created_at' => '',
];

$stmt_restaurant = $conn->prepare("SELECT restaurant_id, restaurant_name, description, cuisine_type, address, phone_number, opening_time, closing_time, rating, logo_url, created_at FROM restaurants WHERE user_id = ?");
$stmt_restaurant->bind_param("i", $user_id);
$stmt_restaurant->execute();
$result_restaurant = $stmt_restaurant->get_result();
if ($result_restaurant->num_rows > 0) {
    $restaurant_data = array_merge($restaurant_data, $result_restaurant->fetch_assoc());
}
$stmt_restaurant->close();

// Handle profile update submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $r_name = trim($_POST['restaurant_name']);
    $desc = trim($_POST['description']);
    $cuisine = trim($_POST['cuisine_type']);
    $addr = trim($_POST['address']);
    $phone = trim($_POST['phone_number']);
    $open_time = trim($_POST['opening_time']);
    $close_time = trim($_POST['closing_time']);

    // Basic validation
    if (empty($r_name) || empty($cuisine) || empty($addr) || empty($phone) || empty($open_time) || empty($close_time)) {
        $message = '<div class="alert alert-danger">Please fill in all required fields.</div>';
    } else {
        // Handle logo upload if a file is provided
        $new_logo_url = $restaurant_data['logo_url']; // Default to existing logo
        if (isset($_FILES['logo']) && $_FILES['logo']['error'] === UPLOAD_ERR_OK) {
            $target_dir = "../images/"; // This is the actual server path to save the file
            $uploaded_file_name = basename($_FILES['logo']['name']);
            $target_file = $target_dir . $uploaded_file_name;
            $imageFileType = strtolower(pathinfo($target_file,PATHINFO_EXTENSION));
    
            // Check if image file is a actual image or fake image
            $check = getimagesize($_FILES['logo']['tmp_name']);
            if($check !== false) {
                // Check file size
                if ($_FILES['logo']['size'] < 500000) { // 500KB limit
                    // Allow certain file formats
                    if($imageFileType == "jpg" || $imageFileType == "png" || $imageFileType == "jpeg"
                    || $imageFileType == "gif" ) {
                        if (move_uploaded_file($_FILES['logo']['tmp_name'], $target_file)) {
                            // Store the URL relative to the document root, not the script's current directory
                            $new_logo_url = htmlspecialchars('images/' . $uploaded_file_name); 
                        } else {
                            $message = '<div class="alert alert-danger">Sorry, there was an error uploading your file.</div>';
                        }
                    } else {
                        $message = '<div class="alert alert-danger">Sorry, only JPG, JPEG, PNG & GIF files are allowed.</div>';
                    }
                } else {
                    $message = '<div class="alert alert-danger">Sorry, your file is too large.</div>';
                }
            } else {
                $message = '<div class="alert alert-danger">File is not an image.</div>';
            }
        }

        // Update restaurants table
        $update_sql = "UPDATE restaurants SET restaurant_name=?, description=?, cuisine_type=?, address=?, phone_number=?, opening_time=?, closing_time=?, logo_url=? WHERE user_id=?";
        $stmt_update = $conn->prepare($update_sql);
        $stmt_update->bind_param("ssssssssi", $r_name, $desc, $cuisine, $addr, $phone, $open_time, $close_time, $new_logo_url, $user_id);

        if ($stmt_update->execute()) {
            $message = '<div class="alert alert-success">Profile updated successfully!</div>';
            // Re-fetch data to display updated info immediately
            $stmt_restaurant_reget = $conn->prepare("SELECT restaurant_id, restaurant_name, description, cuisine_type, address, phone_number, opening_time, closing_time, rating, logo_url, created_at FROM restaurants WHERE user_id = ?");
            $stmt_restaurant_reget->bind_param("i", $user_id);
            $stmt_restaurant_reget->execute();
            $result_restaurant_reget = $stmt_restaurant_reget->get_result();
            if ($result_restaurant_reget->num_rows > 0) {
                $restaurant_data = array_merge($restaurant_data, $result_restaurant_reget->fetch_assoc());
            }
            $stmt_restaurant_reget->close();
        } else {
            $message = '<div class="alert alert-danger">Error updating profile: ' . $conn->error . '</div>';
        }
        $stmt_update->close();
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Restaurant Profile | FoodSave</title>
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
            background: linear-gradient(135deg, var(--primary), var(--primary-dark));
            color: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 20px;
        }

        body.dark-mode .profile-header {
            background: linear-gradient(135deg, var(--primary-dark), var(--dark));
        }

        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
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
            
            .profile-header {
                padding: 15px;
            }
            
            .profile-pic {
                width: 80px;
                height: 80px;
            }
            
            .card-header {
                padding: 12px 15px;
            }
        }
        
        @media (max-width: 575px) {
            .main-content {
                padding: 8px;
            }
            
            .profile-pic {
                width: 70px;
                height: 70px;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
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
                    <a class="nav-link active" href="profile.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <button class="menu-toggle-btn" id="menuToggleBtn" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h2 class="mb-0">Restaurant Profile</h2>
            </div>
            <div class="d-flex align-items-center">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
            </div>
        </div>

        <?php echo $message; // Display success/error messages ?>

        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center mb-3 mb-md-0">
                    <img src="<?php echo htmlspecialchars(str_starts_with($restaurant_data['logo_url'], 'http') ? $restaurant_data['logo_url'] : '../' . $restaurant_data['logo_url']); ?>" 
                         class="profile-pic" alt="Restaurant Logo">
                </div>
                <div class="col-md-10 text-center text-md-start">
                    <h3><?php echo htmlspecialchars($restaurant_data['restaurant_name']); ?></h3>
                    <p class="mb-1"><i class="fas fa-star me-2"></i> 
                        <?php echo isset($restaurant_data['rating']) ? number_format($restaurant_data['rating'], 1) . '/5.0' : 'No ratings yet'; ?>
                    </p>
                    <p class="mb-0"><i class="fas fa-calendar-alt me-2"></i> 
                        Member since <?php echo isset($restaurant_data['created_at']) ? date('M Y', strtotime($restaurant_data['created_at'])) : 'N/A'; ?>
                    </p>
                </div>
            </div>
        </div>

        <div class="card mb-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Restaurant Details</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#editProfileModal">
                    <i class="fas fa-edit me-2"></i>Edit Profile
                </button>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Restaurant Name:</strong> <?php echo htmlspecialchars($restaurant_data['restaurant_name']); ?><br>
                        <strong>Description:</strong> <?php echo htmlspecialchars($restaurant_data['description']); ?><br>
                        <strong>Cuisine Type:</strong> <?php echo htmlspecialchars($restaurant_data['cuisine_type']); ?><br>
                    </div>
                    <div class="col-md-6 mb-3">
                        <strong>Address:</strong> <?php echo htmlspecialchars($restaurant_data['address']); ?><br>
                        <strong>Phone Number:</strong> <?php echo htmlspecialchars($restaurant_data['phone_number']); ?><br>
                        <strong>Email:</strong> <?php echo htmlspecialchars($user_email); ?><br>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-6 mb-3">
                        <strong>Opening Time:</strong> <?php echo htmlspecialchars($restaurant_data['opening_time']); ?><br>
                        <strong>Closing Time:</strong> <?php echo htmlspecialchars($restaurant_data['closing_time']); ?><br>
                    </div>
                </div>
            </div>
        </div>

        <!-- Edit Profile Modal -->
        <div class="modal fade" id="editProfileModal" tabindex="-1" aria-labelledby="editProfileModalLabel" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editProfileModalLabel">Edit Restaurant Profile</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <form action="" method="POST" enctype="multipart/form-data">
                        <div class="modal-body">
                            <div class="mb-3">
                                <label for="restaurant_name" class="form-label">Restaurant Name</label>
                                <input type="text" class="form-control" id="restaurant_name" name="restaurant_name" value="<?php echo htmlspecialchars($restaurant_data['restaurant_name']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?php echo htmlspecialchars($restaurant_data['description']); ?></textarea>
                            </div>
                            <div class="mb-3">
                                <label for="cuisine_type" class="form-label">Cuisine Type</label>
                                <input type="text" class="form-control" id="cuisine_type" name="cuisine_type" value="<?php echo htmlspecialchars($restaurant_data['cuisine_type']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="address" class="form-label">Address</label>
                                <input type="text" class="form-control" id="address" name="address" value="<?php echo htmlspecialchars($restaurant_data['address']); ?>" required>
                            </div>
                            <div class="mb-3">
                                <label for="phone_number" class="form-label">Phone Number</label>
                                <input type="text" class="form-control" id="phone_number" name="phone_number" value="<?php echo htmlspecialchars($restaurant_data['phone_number']); ?>" required>
                            </div>
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label for="opening_time" class="form-label">Opening Time</label>
                                    <input type="time" class="form-control" id="opening_time" name="opening_time" value="<?php echo htmlspecialchars($restaurant_data['opening_time']); ?>" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label for="closing_time" class="form-label">Closing Time</label>
                                    <input type="time" class="form-control" id="closing_time" name="closing_time" value="<?php echo htmlspecialchars($restaurant_data['closing_time']); ?>" required>
                                </div>
                            </div>
                            <div class="mb-3">
                                <label for="logo" class="form-label">Restaurant Logo</label>
                                <input type="file" class="form-control" id="logo" name="logo" accept="image/*">
                                <?php if (!empty($restaurant_data['logo_url']) && $restaurant_data['logo_url'] !== 'https://via.placeholder.com/150'): ?>
                                    <small class="form-text text-muted">Current logo: <a href="<?php echo htmlspecialchars($restaurant_data['logo_url']); ?>" target="_blank">View Current Logo</a></small>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                            <button type="submit" class="btn btn-primary">Save Changes</button>
                        </div>
                    </form>
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
        <a href="donations.php" class="mobile-nav-item">
            <i class="fas fa-donate"></i>
            <span>Donations</span>
        </a>
        <a href="profile.php" class="mobile-nav-item active">
            <i class="fas fa-store"></i>
            <span>Profile</span>
        </a>
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