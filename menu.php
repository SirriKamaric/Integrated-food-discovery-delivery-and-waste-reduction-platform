<?php
require_once '../session_check.php';
if (!hasAccess('restaurant')) {
    header("Location: ../unauthorized.php");
    exit();
}

// Initialize all variables at the start
$cat_add_success = false;
$cat_add_error = '';
$add_success = false;
$add_error = '';
$edit_success = false;
$edit_error = '';
$delete_success = false;
$delete_error = '';
$menu_items = []; // Initialize as empty array
$categories = [];

require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

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

$user_id = $_SESSION['user_id'];
$restaurant_id = null;
$result = $conn->query("SELECT restaurant_id FROM restaurants WHERE user_id = $user_id");
if ($result && $result->num_rows > 0) {
    $row = $result->fetch_assoc();
    $restaurant_id = $row['restaurant_id'];
}

if ($restaurant_id === null) {
    $placeholder_name = 'My Restaurant';
    $placeholder_address = 'Please update your address';
    $placeholder_phone = '0000000000';
    $conn->query("INSERT INTO restaurants (user_id, restaurant_name, address, phone_number) VALUES ($user_id, '$placeholder_name', '$placeholder_address', '$placeholder_phone')");
    $result = $conn->query("SELECT restaurant_id FROM restaurants WHERE user_id = $user_id");
    if ($result && $result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $restaurant_id = $row['restaurant_id'];
    }
}

// Fetch categories
if ($restaurant_id !== null) {
    $cat_result = $conn->query("SELECT * FROM menu_categories WHERE restaurant_id = $restaurant_id");
    if ($cat_result) {
        while($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// Auto-sync categories if none exist
if ($restaurant_id !== null && empty($categories)) {
    $conn2 = new mysqli($servername, $username, $password, $dbname);
    $global_cats = $conn2->query("SELECT DISTINCT category_name FROM menu_categories");
    if ($global_cats && $global_cats->num_rows > 0) {
        while ($row = $global_cats->fetch_assoc()) {
            $cat_name = $conn2->real_escape_string($row['category_name']);
            $conn2->query("INSERT INTO menu_categories (restaurant_id, category_name) VALUES ($restaurant_id, '$cat_name')");
        }
    }
    $conn2->close();
    
    // Re-fetch categories
    $categories = [];
    $cat_result = $conn->query("SELECT * FROM menu_categories WHERE restaurant_id = $restaurant_id");
    if ($cat_result) {
        while($row = $cat_result->fetch_assoc()) {
            $categories[] = $row;
        }
    }
}

// Handle add menu item form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_menu_item'])) {
    $item_name = $conn->real_escape_string($_POST['item_name']);
    $price = floatval($_POST['price']);
    $description = $conn->real_escape_string($_POST['description']);
    $category_id = intval($_POST['category_id']);
    $image_url = '';
    // Handle image upload
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        error_log("DEBUG: Image file received for new item.");
        $img_name = basename($_FILES['image']['name']);
        $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($img_ext, $allowed_exts)) {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/uploads/menu_items/'; // Unified upload directory
            error_log("DEBUG: Target directory for new item: " . $target_dir);
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
                error_log("DEBUG: Created directory: " . $target_dir);
            }
            if (!is_writable($target_dir)) {
                error_log("ERROR: Target directory is not writable: " . $target_dir);
            }
            $target_file = $target_dir . uniqid() . '_' . $img_name;
            error_log("DEBUG: Target file path for new item: " . $target_file);
            if (move_uploaded_file($_FILES['image']['tmp_name'], $target_file)) {
                $image_url = htmlspecialchars('uploads/menu_items/' . basename($target_file)); // Store simplified URL in DB
                error_log("DEBUG: Image moved successfully. Storing URL: " . $image_url);
            } else {
                error_log("ERROR: Failed to move uploaded file for new item. Error: " . $_FILES['image']['error']);
            }
        } else {
            error_log("ERROR: Invalid image extension for new item: " . $img_ext);
        }
    } else if (isset($_FILES['image']['error']) && $_FILES['image']['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("ERROR: Image upload error for new item: " . $_FILES['image']['error']);
    }
    $stmt = $conn->prepare("INSERT INTO menu_items (restaurant_id, category_id, item_name, price, description, image_url) VALUES (?, ?, ?, ?, ?, ?)");
    $stmt->bind_param('iisdss', $restaurant_id, $category_id, $item_name, $price, $description, $image_url);
    if ($stmt->execute()) {
        $add_success = true;
    } else {
        $add_error = 'Failed to add menu item: ' . $conn->error;
    }
    $stmt->close();
}

// Handle add category form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category_restaurant'])) {
    $new_category = trim($conn->real_escape_string($_POST['new_category_name'] ?? ''));
    if ($new_category !== '') {
        if ($restaurant_id !== null) {
            $exists = $conn->query("SELECT 1 FROM menu_categories WHERE restaurant_id = $restaurant_id AND category_name = '$new_category'");
            if ($exists && $exists->num_rows > 0) {
                $cat_add_error = 'Category already exists.';
            } else {
                if ($conn->query("INSERT INTO menu_categories (restaurant_id, category_name) VALUES ($restaurant_id, '$new_category')")) {
                    $cat_add_success = true;
                    // Refresh categories
                    $categories = [];
                    $cat_result = $conn->query("SELECT * FROM menu_categories WHERE restaurant_id = $restaurant_id");
                    if ($cat_result) {
                        while($row = $cat_result->fetch_assoc()) {
                            $categories[] = $row;
                        }
                    }
                } else {
                    $cat_add_error = 'Failed to add category: ' . $conn->error;
                }
            }
        } else {
            $cat_add_error = 'Restaurant ID not found. Please contact admin.';
        }
    } else {
        $cat_add_error = 'Category name cannot be empty.';
    }
}

// Handle delete menu item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_menu_item'])) {
    $delete_id = intval($_POST['delete_item_id']);
    // Get image path to delete file
    $img_result = $conn->query("SELECT image_url FROM menu_items WHERE item_id = $delete_id AND restaurant_id = $restaurant_id");
    if ($img_result && $img_result->num_rows > 0) {
        $img_row = $img_result->fetch_assoc();
        $img_path = $img_row['image_url'];
        if (!empty($img_path)) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . '/' . $img_path;
            if (file_exists($full_path)) unlink($full_path);
        }
    }
    if ($conn->query("DELETE FROM menu_items WHERE item_id = $delete_id AND restaurant_id = $restaurant_id")) {
        $delete_success = true;
    } else {
        $delete_error = 'Failed to delete menu item.';
    }
}

// Handle edit menu item
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_menu_item'])) {
    $edit_id = intval($_POST['edit_item_id']);
    $edit_name = $conn->real_escape_string($_POST['edit_item_name']);
    $edit_price = floatval($_POST['edit_price']);
    $edit_desc = $conn->real_escape_string($_POST['edit_description']);
    $edit_cat = intval($_POST['edit_category_id']);
    $existing_image_url = $_POST['existing_image_url'];
    $new_image_url = $existing_image_url;
    if (isset($_FILES['edit_image']) && $_FILES['edit_image']['error'] === UPLOAD_ERR_OK) {
        error_log("DEBUG: Image file received for edit item.");
        $img_name = basename($_FILES['edit_image']['name']);
        $img_ext = strtolower(pathinfo($img_name, PATHINFO_EXTENSION));
        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        if (in_array($img_ext, $allowed_exts)) {
            $target_dir = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/uploads/menu_items/'; // Unified upload directory
            error_log("DEBUG: Target directory for edit item: " . $target_dir);
            if (!is_dir($target_dir)) {
                mkdir($target_dir, 0777, true);
                error_log("DEBUG: Created directory for edit: " . $target_dir);
            }
            if (!is_writable($target_dir)) {
                error_log("ERROR: Target directory is not writable for edit: " . $target_dir);
            }
            $target_file = $target_dir . uniqid() . '_' . $img_name;
            error_log("DEBUG: Target file path for edit item: " . $target_file);
            if (move_uploaded_file($_FILES['edit_image']['tmp_name'], $target_file)) {
                $new_image_url = htmlspecialchars('uploads/menu_items/' . basename($target_file)); // Store simplified URL in DB
                error_log("DEBUG: Image moved successfully for edit. Storing URL: " . $new_image_url);
                // Delete old image (only if it was a custom upload, not placeholder)
                if (!empty($existing_image_url) && !str_contains($existing_image_url, 'placeholder')) {
                    $old_path_full = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $existing_image_url;
                    error_log("DEBUG: Attempting to delete old image: " . $old_path_full);
                    if (file_exists($old_path_full)) {
                        if (unlink($old_path_full)) {
                            error_log("DEBUG: Old image deleted successfully.");
                        } else {
                            error_log("ERROR: Failed to delete old image: " . $old_path_full);
                        }
                    } else {
                        error_log("DEBUG: Old image file not found for deletion: " . $old_path_full);
                    }
                }
            } else {
                error_log("ERROR: Failed to move uploaded file for edit item. Error: " . $_FILES['edit_image']['error']);
            }
        } else {
            error_log("ERROR: Invalid image extension for edit item: " . $img_ext);
        }
    } else if (isset($_FILES['edit_image']['error']) && $_FILES['edit_image']['error'] !== UPLOAD_ERR_NO_FILE) {
        error_log("ERROR: Image upload error for edit item: " . $_FILES['edit_image']['error']);
    }
    $stmt = $conn->prepare("UPDATE menu_items SET item_name=?, price=?, description=?, category_id=?, image_url=? WHERE item_id=? AND restaurant_id=?");
    $stmt->bind_param('sdsdsii', $edit_name, $edit_price, $edit_desc, $edit_cat, $new_image_url, $edit_id, $restaurant_id);
    if ($stmt->execute()) {
        $edit_success = true;
    } else {
        $edit_error = 'Failed to update menu item: ' . $conn->error;
    }
    $stmt->close();
}

// Fetch menu items - ensure this always returns an array
if ($restaurant_id !== null) {
    $result = $conn->query("SELECT * FROM menu_items WHERE restaurant_id = $restaurant_id");
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $menu_items[] = $row;
        }
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Menu Management | FoodSave</title>
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
        
        .badge-status {
            font-size: 0.75rem;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 50px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }
        
        .badge-processing { background-color: var(--info); }
        .badge-pending { background-color: var(--warning); color: white; }
        .badge-completed { background-color: var(--success); color: white; }
        .badge-cancelled { background-color: var(--danger); color: white; }
        
        .stat-card .card-body {
            display: flex;
            align-items: center;
        }
        
        .stat-card .icon-container {
            background-color: rgba(79, 70, 229, 0.1);
            width: 60px;
            height: 60px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 15px;
        }
        
        .card-icon {
            font-size: 1.5rem;
            color: var(--primary);
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
        
        /* Menu Item Specific Styles */
        .menu-item-card {
            transition: all 0.3s cubic-bezier(0.25, 0.8, 0.25, 1);
        }

        .menu-item-img {
            height: 200px;
            object-fit: cover;
            transition: all 0.3s ease;
        }

        .menu-item-card:hover .menu-item-img {
            transform: scale(1.03);
        }

        .badge-price {
            background-color: var(--primary);
            color: white;
            font-weight: 600;
            padding: 5px 10px;
            border-radius: 20px;
        }

        .empty-state {
            text-align: center;
            padding: 50px 20px;
            background-color: white;
            border-radius: 12px;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.05);
        }

        .empty-state i {
            font-size: 4rem;
            color: var(--primary);
            margin-bottom: 20px;
            opacity: 0.7;
        }

        .empty-state h4 {
            font-weight: 600;
            color: var(--dark);
            margin-bottom: 10px;
        }

        .empty-state p {
            color: var(--gray);
            margin-bottom: 25px;
        }

        .category-card {
            background-color: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
        }

        .page-title {
            font-weight: 700;
            color: var(--dark);
            margin-bottom: 20px;
            position: relative;
            padding-bottom: 10px;
        }

        .page-title:after {
            content: '';
            position: absolute;
            left: 0;
            bottom: 0;
            width: 50px;
            height: 4px;
            background: linear-gradient(90deg, var(--primary), var(--primary-light));
            border-radius: 2px;
        }

        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0.4); }
            70% { box-shadow: 0 0 0 10px rgba(79, 70, 229, 0); }
            100% { box-shadow: 0 0 0 0 rgba(79, 70, 229, 0); }
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
            
            .menu-item-img {
                height: 150px;
            }
            
            .empty-state {
                padding: 30px 15px;
            }
            
            .empty-state i {
                font-size: 3rem;
            }
            
            .category-card {
                padding: 15px;
            }
        }
        
        @media (max-width: 575px) {
            .main-content {
                padding: 8px;
            }
            
            .menu-item-img {
                height: 120px;
            }
            
            .empty-state {
                padding: 20px 10px;
            }
            
            .empty-state i {
                font-size: 2.5rem;
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

        /* Dark mode specific overrides */
        body.dark-mode .empty-state,
        body.dark-mode .category-card {
            background-color: var(--dark-card-bg);
        }

        body.dark-mode .empty-state h4,
        body.dark-mode .empty-state i {
            color: var(--dark-text-color);
        }

        body.dark-mode .empty-state p {
            color: var(--dark-muted-text-color);
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
                    <a class="nav-link active" href="menu.php">
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
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div class="d-flex align-items-center">
                <button class="menu-toggle-btn" id="menuToggleBtn" aria-label="Open menu">
                    <i class="fas fa-bars"></i>
                </button>
                <h2 class="page-title mb-0">Menu Management</h2>
            </div>
            <div class="d-flex align-items-center">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary pulse" data-bs-toggle="modal" data-bs-target="#addMenuItemModal">
                    <i class="fas fa-plus me-2"></i>Add Item
                </button>
            </div>
        </div>

        <!-- Category Add Form -->
        <div class="category-card">
            <form method="post" class="d-flex align-items-end gap-3">
                <div class="flex-grow-1">
                    <label class="form-label mb-2">Add New Category</label>
                    <input type="text" class="form-control" name="new_category_name" placeholder="e.g. Breakfast, Main Course, Desserts" required>
                </div>
                <button type="submit" class="btn btn-success" name="add_category_restaurant" value="1">
                    <i class="fas fa-plus me-1"></i> Add
                </button>
            </form>
            <?php if ($cat_add_success): ?>
                <div class="alert alert-success mt-3">Category added successfully!</div>
            <?php elseif ($cat_add_error): ?>
                <div class="alert alert-danger mt-3"><?php echo $cat_add_error; ?></div>
            <?php endif; ?>
        </div>

        <div class="row">
            <?php foreach ($menu_items as $item): ?>
            <div class="col-lg-4 col-md-6 mb-4 d-flex align-items-stretch">
                <div class="card menu-item-card h-100 w-100 d-flex flex-column">
                    <?php
                    $image_url_from_db = htmlspecialchars($item['image_url']);
                    $img_src = '/food_delivery_system/images/placeholder_food.jpg'; // Default placeholder (absolute path)

                    if (!empty($image_url_from_db)) {
                        $web_path_candidate = '';
                        $server_path_candidate = '';

                        // Prioritize new standardized path (e.g., 'uploads/menu_items/filename.jpg')
                        if (str_starts_with($image_url_from_db, 'uploads/menu_items/')) {
                            $web_path_candidate = '/food_delivery_system/' . $image_url_from_db;
                            $server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $image_url_from_db;
                        }
                        // Fallback to old path (e.g., 'uploads/menu/filename.jpg' - relative to restaurant/ directory)
                        else if (str_starts_with($image_url_from_db, 'uploads/menu/')) {
                            $web_path_candidate = '/food_delivery_system/restaurant/' . $image_url_from_db;
                            $server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/restaurant/' . $image_url_from_db;
                        }
                        // Handle other potential root-relative paths or full URLs (less likely for stored images, but for robustness)
                        else if (str_starts_with($image_url_from_db, '/') || str_starts_with($image_url_from_db, 'http')) {
                             $web_path_candidate = $image_url_from_db;
                             // If it's a root-relative path (e.g., /myimage.jpg), it should be relative to DOCUMENT_ROOT
                             $server_path_candidate = str_starts_with($image_url_from_db, '/') ? $_SERVER['DOCUMENT_ROOT'] . $image_url_from_db : '';
                        }
                        // Catch-all for any other relative paths (assume relative to food_delivery_system/ for safety)
                        else {
                            $web_path_candidate = '/food_delivery_system/' . $image_url_from_db;
                            $server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $image_url_from_db;
                        }

                        // Normalize server path for file_exists check (Windows compatibility)
                        $server_path_candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $server_path_candidate);

                        // Check if the file exists on the server and is not a directory
                        if (!empty($server_path_candidate) && file_exists($server_path_candidate) && !is_dir($server_path_candidate)) {
                            $img_src = str_replace('\\', '/', $web_path_candidate); // Ensure web path uses forward slashes
                        }
                    }
                    ?>
                    <img src="<?php echo $img_src; ?>"
                         class="card-img-top menu-item-img"
                         alt="<?php echo htmlspecialchars($item['item_name']); ?>"
                         style="object-fit:cover; width:100%;">
                    <div class="card-body d-flex flex-column flex-grow-1">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-1"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                            <span class="badge-price"><?php echo number_format($item['price'], 0, '.', ','); ?> XAF</span>
                        </div>
                        <p class="card-text flex-grow-1"><?php echo htmlspecialchars($item['description']); ?></p>
                    </div>
                    <div class="card-footer bg-white border-0 d-flex justify-content-between pt-0">
                        <button class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#editMenuItemModal<?php echo $item['item_id']; ?>">
                            <i class="fas fa-edit me-1"></i> Edit
                        </button>
                        <button class="btn btn-outline-danger btn-sm" data-bs-toggle="modal" data-bs-target="#deleteMenuItemModal<?php echo $item['item_id']; ?>">
                            <i class="fas fa-trash me-1"></i> Delete
                        </button>
                    </div>
                </div>

                <!-- Edit Modal -->
                <div class="modal fade" id="editMenuItemModal<?php echo $item['item_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Menu Item</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post" enctype="multipart/form-data">
                                <input type="hidden" name="edit_menu_item" value="1">
                                <input type="hidden" name="edit_item_id" value="<?php echo $item['item_id']; ?>">
                                <input type="hidden" name="existing_image_url" value="<?php echo htmlspecialchars($item['image_url']); ?>">
                                <div class="modal-body">
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Item Name</label>
                                            <input type="text" class="form-control" name="edit_item_name" value="<?php echo htmlspecialchars($item['item_name']); ?>" required>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Price</label>
                                            <div class="input-group">
                                                <span class="input-group-text">XAF</span>
                                                <input type="number" step="1" min="0" class="form-control" name="edit_price" value="<?php echo htmlspecialchars($item['price']); ?>" required>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Description</label>
                                        <textarea class="form-control" name="edit_description" rows="3"><?php echo htmlspecialchars($item['description']); ?></textarea>
                                    </div>
                                    <div class="row">
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Category</label>
                                            <select class="form-select" name="edit_category_id" required>
                                                <?php foreach ($categories as $cat): ?>
                                                    <option value="<?php echo $cat['category_id']; ?>" <?php if ($cat['category_id'] == $item['category_id']) echo 'selected'; ?>><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6 mb-3">
                                            <label class="form-label">Image</label>
                                            <input type="file" class="form-control" name="edit_image" accept="image/*">
                                            <small class="text-muted">Leave blank to keep current image</small>
                                            <div class="mt-2 text-center">
                                                <?php
                                                $modal_image_url_from_db = htmlspecialchars($item['image_url']);
                                                $modal_img_src = '/food_delivery_system/images/placeholder_food.jpg'; // Default placeholder (absolute path)

                                                if (!empty($modal_image_url_from_db)) {
                                                    $modal_web_path_candidate = '';
                                                    $modal_server_path_candidate = '';

                                                    // Prioritize new standardized path (e.g., 'uploads/menu_items/filename.jpg')
                                                    if (str_starts_with($modal_image_url_from_db, 'uploads/menu_items/')) {
                                                        $modal_web_path_candidate = '/food_delivery_system/' . $modal_image_url_from_db;
                                                        $modal_server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/' . $modal_image_url_from_db;
                                                    }
                                                    // Fallback to old path (e.g., 'uploads/menu/filename.jpg' - relative to restaurant/ directory)
                                                    else if (str_starts_with($modal_image_url_from_db, 'uploads/menu/')) {
                                                        $modal_web_path_candidate = '/food_delivery_system/restaurant/' . $modal_image_url_from_db;
                                                        $modal_server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/restaurant/' . $modal_image_url_from_db;
                                                    }
                                                    // Handle other potential root-relative paths or full URLs
                                                    else if (str_starts_with($modal_image_url_from_db, '/') || str_starts_with($modal_image_url_from_db, 'http')) {
                                                         $modal_web_path_candidate = $modal_image_url_from_db;
                                                         $modal_server_path_candidate = str_starts_with($modal_image_url_from_db, '/') ? $_SERVER['DOCUMENT_ROOT'] . $modal_image_url_from_db : '';
                                                    }
                                                    // Catch-all for any other relative paths (assume relative to food_delivery_system/ for safety)
                                                    else {
                                                        $modal_web_path_candidate = '/food_delivery_system/' . $modal_image_url_from_db;
                                                        $modal_server_path_candidate = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $modal_image_url_from_db;
                                                    }

                                                    // Normalize server path for file_exists check (Windows compatibility)
                                                    $modal_server_path_candidate = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $modal_server_path_candidate);

                                                    // Check if the file exists on the server and is not a directory
                                                    if (!empty($modal_server_path_candidate) && file_exists($modal_server_path_candidate) && !is_dir($modal_server_path_candidate)) {
                                                        $modal_img_src = str_replace('\\', '/', $modal_web_path_candidate); // Ensure web path uses forward slashes
                                                    }
                                                }
                                                ?>
                                                <img src="<?php echo $modal_img_src; ?>" alt="Current Image" class="img-thumbnail" style="max-height: 120px;">
                                            </div>
                                        </div>
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

                <!-- Delete Modal -->
                <div class="modal fade" id="deleteMenuItemModal<?php echo $item['item_id']; ?>" tabindex="-1" aria-hidden="true">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header bg-danger text-white">
                                <h5 class="modal-title">Delete Menu Item</h5>
                                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                            </div>
                            <form method="post">
                                <input type="hidden" name="delete_menu_item" value="1">
                                <input type="hidden" name="delete_item_id" value="<?php echo $item['item_id']; ?>">
                                <div class="modal-body">
                                    <p>Are you sure you want to delete <strong><?php echo htmlspecialchars($item['item_name']); ?></strong>?</p>
                                    <?php if (!empty($item['image_url'])): ?>
                                        <p class="text-muted small">Note: The associated image will also be permanently deleted.</p>
                                    <?php endif; ?>
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                    <button type="submit" class="btn btn-danger">Delete Item</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>

            <?php if (empty($menu_items)): ?>
            <div class="col-12">
                <div class="empty-state">
                    <i class="fas fa-utensils"></i>
                    <h4>Your menu is empty</h4>
                    <p>Start by adding your first menu item to showcase your delicious offerings</p>
                    <button class="btn btn-primary pulse" data-bs-toggle="modal" data-bs-target="#addMenuItemModal">
                        <i class="fas fa-plus me-2"></i>Add Your First Item
                    </button>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Menu Item Modal -->
    <div class="modal fade" id="addMenuItemModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Menu Item</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <?php if ($add_success): ?>
                        <div class="alert alert-success">Menu item added successfully!</div>
                    <?php elseif ($add_error): ?>
                        <div class="alert alert-danger"><?php echo $add_error; ?></div>
                    <?php endif; ?>
                    <?php if ($edit_success): ?>
                        <div class="alert alert-success">Menu item updated successfully!</div>
                    <?php elseif ($edit_error): ?>
                        <div class="alert alert-danger"><?php echo $edit_error; ?></div>
                    <?php endif; ?>
                    <?php if ($delete_success): ?>
                        <div class="alert alert-success">Menu item deleted successfully!</div>
                    <?php elseif ($delete_error): ?>
                        <div class="alert alert-danger"><?php echo $delete_error; ?></div>
                    <?php endif; ?>
                    <form method="post" enctype="multipart/form-data">
                        <input type="hidden" name="add_menu_item" value="1">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Name <span class="text-danger">*</span></label>
                                <input type="text" class="form-control" name="item_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Price <span class="text-danger">*</span></label>
                                <div class="input-group">
                                    <span class="input-group-text">XAF</span>
                                    <input type="number" step="1" min="0" class="form-control" name="price" required>
                                </div>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Description</label>
                            <textarea class="form-control" name="description" rows="3" placeholder="Brief description of the item..."></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Category <span class="text-danger">*</span></label>
                                <select class="form-select" name="category_id" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo $cat['category_id']; ?>"><?php echo htmlspecialchars($cat['category_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Item Image</label>
                                <input type="file" class="form-control" name="image" accept="image/*">
                                <small class="text-muted">Recommended size: 800x600px (JPG, PNG, WEBP)</small>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Save Item
                            </button>
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
        <a href="menu.php" class="mobile-nav-item active">
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
        <a href="profile.php" class="mobile-nav-item">
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

            // Show success/error messages from form submissions
            const urlParams = new URLSearchParams(window.location.search);
            if (urlParams.has('success')) {
                const successMessage = urlParams.get('success');
                if (successMessage) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-success';
                    alertDiv.textContent = successMessage;
                    document.querySelector('.main-content').prepend(alertDiv);
                    
                    // Remove the success parameter from URL
                    urlParams.delete('success');
                    window.history.replaceState({}, '', `${window.location.pathname}?${urlParams.toString()}`);
                }
            }
            
            if (urlParams.has('error')) {
                const errorMessage = urlParams.get('error');
                if (errorMessage) {
                    const alertDiv = document.createElement('div');
                    alertDiv.className = 'alert alert-danger';
                    alertDiv.textContent = errorMessage;
                    document.querySelector('.main-content').prepend(alertDiv);
                    
                    // Remove the error parameter from URL
                    urlParams.delete('error');
                    window.history.replaceState({}, '', `${window.location.pathname}?${urlParams.toString()}`);
                }
            }
        });
    </script>
</body>
</html>