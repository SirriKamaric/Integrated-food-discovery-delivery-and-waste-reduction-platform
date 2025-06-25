<?php
require_once '../session_check.php';

if ($_SESSION['user_type'] !== 'customer') {
    header("Location: ../unauthorized.php");
    exit();
}

$user_id = $_SESSION['user_id'];

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

$message = '';
$message_type = '';

// Handle profile update (including picture upload)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {
    $first_name = htmlspecialchars(trim($_POST['first_name']));
    $last_name = htmlspecialchars(trim($_POST['last_name']));
    $phone_number = htmlspecialchars(trim($_POST['phone_number']));
    $date_of_birth = htmlspecialchars(trim($_POST['date_of_birth']));

    $update_fields = [];
    $bind_types = '';
    $bind_params = [];

    if (!empty($first_name)) { $update_fields[] = "first_name = ?"; $bind_types .= 's'; $bind_params[] = $first_name; }
    if (!empty($last_name)) { $update_fields[] = "last_name = ?"; $bind_types .= 's'; $bind_params[] = $last_name; }
    if (!empty($phone_number)) { $update_fields[] = "phone_number = ?"; $bind_types .= 's'; $bind_params[] = $phone_number; }
    if (!empty($date_of_birth)) { $update_fields[] = "date_of_birth = ?"; $bind_types .= 's'; $bind_params[] = $date_of_birth; }

    $profile_picture_url = $customer_profile['profile_picture_url'] ?? ''; // Keep existing if no new upload

    // Handle profile picture upload
    if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] === UPLOAD_ERR_OK) {
        $file_tmp_path = $_FILES['profile_picture']['tmp_name'];
        $file_name = $_FILES['profile_picture']['name'];
        $file_size = $_FILES['profile_picture']['size'];
        $file_type = $_FILES['profile_picture']['type'];
        $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));

        $allowed_exts = ['jpg', 'jpeg', 'png', 'gif'];
        if (in_array($file_ext, $allowed_exts)) {
            $new_file_name = uniqid() . '.' . $file_ext;
            $upload_dir = 'uploads/customer_profiles/';
            $upload_path_server = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $upload_dir;

            // Create directory if it doesn't exist
            if (!is_dir($upload_path_server)) {
                mkdir($upload_path_server, 0777, true);
            }

            $destination_path = $upload_path_server . $new_file_name;

            if (move_uploaded_file($file_tmp_path, $destination_path)) {
                $profile_picture_url = $upload_dir . $new_file_name;
                $update_fields[] = "profile_picture_url = ?";
                $bind_types .= 's';
                $bind_params[] = $profile_picture_url;
                $message = "Profile updated successfully!";
                $message_type = "success";
            } else {
                $message = "Failed to upload profile picture.";
                $message_type = "danger";
            }
        } else {
            $message = "Invalid file type. Only JPG, JPEG, PNG, GIF are allowed for profile pictures.";
            $message_type = "danger";
        }
    }

    if (!empty($update_fields)) {
        $update_query = "UPDATE users SET " . implode(", ", $update_fields) . " WHERE user_id = ?";
        $bind_params[] = $user_id;
        $bind_types .= 'i';

        $stmt = $conn->prepare($update_query);
        if ($stmt) {
            $stmt->bind_param($bind_types, ...$bind_params);
            if ($stmt->execute()) {
                // Update session variable if name changed
                if (in_array("first_name = ?", $update_fields) || in_array("last_name = ?", $update_fields)) {
                    $_SESSION['user_name'] = trim($first_name . ' ' . $last_name);
                }
                if (empty($message)) { // Don't overwrite image upload message
                    $message = "Profile updated successfully!";
                    $message_type = "success";
                }
                // Re-fetch profile data to get the latest (including new profile_picture_url)
                $profile_query_after_update = "SELECT * FROM users WHERE user_id = ?";
                $stmt_after = $conn->prepare($profile_query_after_update);
                $stmt_after->bind_param("i", $user_id);
                $stmt_after->execute();
                $profile_result_after = $stmt_after->get_result();
                $customer_profile = $profile_result_after->fetch_assoc(); // Update $customer_profile
                $stmt_after->close();

            } else {
                $message = "Error updating profile: " . $stmt->error;
                $message_type = "danger";
            }
            $stmt->close();
        } else {
            $message = "Error preparing statement: " . $conn->error;
            $message_type = "danger";
        }
    } else if (empty($message)) { // If no text fields updated, and no image upload errors
        $message = "No changes submitted.";
        $message_type = "info";
    }
}

// Handle address actions
if ($_SERVER['REQUEST_METHOD'] === 'POST' && (isset($_POST['add_address']) || isset($_POST['edit_address']) || isset($_POST['delete_address']) || isset($_POST['set_default_address']))) {
    
    if (isset($_POST['add_address']) || isset($_POST['edit_address'])) {
        $address_title = htmlspecialchars(trim($_POST['address_title']));
        $address_line1 = htmlspecialchars(trim($_POST['address_line1']));
        $address_line2 = htmlspecialchars(trim($_POST['address_line2']));
        $city = htmlspecialchars(trim($_POST['city']));
        $state = htmlspecialchars(trim($_POST['state']));
        $zip_code = htmlspecialchars(trim($_POST['zip_code']));
        $is_default = isset($_POST['is_default']) ? 1 : 0;

        if (empty($address_title) || empty($address_line1) || empty($city) || empty($state) || empty($zip_code)) {
            $message = "All required address fields must be filled.";
            $message_type = "danger";
        } else {
            $conn->begin_transaction();
            try {
                // If setting as default, clear existing defaults for this user
                if ($is_default) {
                    $stmt_clear_default = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
                    $stmt_clear_default->bind_param("i", $user_id);
                    $stmt_clear_default->execute();
                    $stmt_clear_default->close();
                }

                if (isset($_POST['add_address'])) {
                    $stmt = $conn->prepare("INSERT INTO addresses (user_id, address_title, address_line1, address_line2, city, state, zip_code, is_default) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->bind_param("issssssi", $user_id, $address_title, $address_line1, $address_line2, $city, $state, $zip_code, $is_default);
                    if ($stmt->execute()) {
                        $message = "Address added successfully!";
                        $message_type = "success";
                    } else {
                        throw new Exception("Error adding address: " . $stmt->error);
                    }
                } elseif (isset($_POST['edit_address'])) {
                    $address_id = intval($_POST['address_id']);
                    $stmt = $conn->prepare("UPDATE addresses SET address_title = ?, address_line1 = ?, address_line2 = ?, city = ?, state = ?, zip_code = ?, is_default = ? WHERE address_id = ? AND user_id = ?");
                    $stmt->bind_param("sssssiiii", $address_title, $address_line1, $address_line2, $city, $state, $zip_code, $is_default, $address_id, $user_id);
                    if ($stmt->execute()) {
                        $message = "Address updated successfully!";
                        $message_type = "success";
                    } else {
                        throw new Exception("Error updating address: " . $stmt->error);
                    }
                }
                $stmt->close();
                $conn->commit();
            } catch (Exception $e) {
                $conn->rollback();
                $message = $e->getMessage();
                $message_type = "danger";
            }
        }
    } elseif (isset($_POST['delete_address'])) {
        $address_id = intval($_POST['delete_address']);
        $stmt = $conn->prepare("DELETE FROM addresses WHERE address_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $address_id, $user_id);
        if ($stmt->execute()) {
            $message = "Address deleted successfully!";
            $message_type = "success";
        } else {
            $message = "Error deleting address: " . $stmt->error;
            $message_type = "danger";
        }
        $stmt->close();
    } elseif (isset($_POST['set_default_address'])) {
        $address_id = intval($_POST['set_default_address']);
        $conn->begin_transaction();
        try {
            // Clear existing default for this user
            $stmt_clear = $conn->prepare("UPDATE addresses SET is_default = 0 WHERE user_id = ?");
            $stmt_clear->bind_param("i", $user_id);
            $stmt_clear->execute();
            $stmt_clear->close();

            // Set new default
            $stmt_set = $conn->prepare("UPDATE addresses SET is_default = 1 WHERE address_id = ? AND user_id = ?");
            $stmt_set->bind_param("ii", $address_id, $user_id);
            if ($stmt_set->execute()) {
                $message = "Default address set successfully!";
                $message_type = "success";
            } else {
                throw new Exception("Error setting default address: " . $stmt_set->error);
            }
            $stmt_set->close();
            $conn->commit();
        } catch (Exception $e) {
            $conn->rollback();
            $message = $e->getMessage();
            $message_type = "danger";
        }
    }
}

// Get customer profile with prepared statement (initial fetch or re-fetch after update)
$profile_query = "SELECT user_id, first_name, last_name, email, phone_number, date_of_birth, created_at, profile_picture_url FROM users WHERE user_id = ?";
$stmt = $conn->prepare($profile_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$profile_result = $stmt->get_result();
$profile_data = $profile_result->fetch_assoc();
$stmt->close();

// Update $userName and $userEmail from fetched profile data for header display
$userName = htmlspecialchars($profile_data['first_name'] . ' ' . $profile_data['last_name']);
$userEmail = htmlspecialchars($profile_data['email']);

// Get customer addresses with prepared statement
$address_query = "SELECT * FROM addresses WHERE user_id = ? ORDER BY is_default DESC, address_title ASC";
$stmt = $conn->prepare($address_query);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$address_result = $stmt->get_result();
$stmt->close();

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile | FoodSave</title>
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
            background: linear-gradient(135deg, var(--primary), var(--accent));
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        
        .profile-pic {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            object-fit: cover;
            border: 3px solid white;
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
        
        .address-card {
            transition: all 0.3s;
        }
        
        .address-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 15px rgba(0, 0, 0, 0.1);
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
        
        .dashboard-header-row {
            display: flex;
            justify-content: space-between;
            align-items: center;
            width: 100%;
        }
        
        .header-left, .header-right {
            display: flex;
            align-items: center;
        }
        
        .header-welcome {
            font-size: 1.5rem;
            font-weight: 600;
            margin-left: 15px;
        }
        
        .menu-toggle-btn {
            display: none;
            background: none;
            border: none;
            font-size: 1.5rem;
            color: var(--primary);
            padding: 5px;
        }
        
        .donate-btn {
            white-space: nowrap;
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
                flex-direction: row;
                align-items: center;
            }
            
            .header-welcome {
                font-size: 1.2rem;
                margin-left: 10px;
            }
            
            .donate-btn span {
                display: none;
            }
            
            .donate-btn i {
                margin-right: 0;
            }
            
            .profile-header {
                padding: 20px;
                flex-direction: column;
                text-align: center;
            }
            
            .profile-pic {
                margin-bottom: 15px;
            }
            
            .account-info-icon {
                margin-right: 10px;
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
            
            .profile-header {
                padding: 15px;
            }
            
            .profile-pic {
                width: 80px;
                height: 80px;
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
            
            .account-info-icon {
                width: 30px;
                height: 30px;
                font-size: 0.8rem;
            }
        }
        
        /* Mobile Bottom Navigation */
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
                padding-bottom: 60px;
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
                    <a class="nav-link active" href="profile.php">
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
                <span class="header-welcome">My Profile</span>
            </div>
            <div class="d-flex align-items-center header-right">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button id="notificationBtn" class="btn btn-info me-2 notification-bell position-relative">
                    <i class="fas fa-bell"></i>
                    <span id="notificationCount" class="notification-count"></span>
                </button>
                <a href="donate.php" class="btn btn-success donate-btn">
                    <i class="fas fa-hand-holding-heart me-2"></i> <span class="d-none d-sm-inline">Donate Food</span>
                </a>
            </div>
        </div>

        <div class="profile-header">
            <div class="row align-items-center">
                <div class="col-md-2 text-center">
                    <?php
                    $profile_pic_url = htmlspecialchars($profile_data['profile_picture_url'] ?? '');
                    $display_profile_pic_src = 'https://ui-avatars.com/api/?name=' . urlencode($userName) . '&background=random';

                    if (!empty($profile_pic_url)) {
                        $full_server_path = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $profile_pic_url;
                        $full_server_path = str_replace(['/', '\\'], DIRECTORY_SEPARATOR, $full_server_path);

                        if (file_exists($full_server_path) && !is_dir($full_server_path)) {
                            $display_profile_pic_src = '/food_delivery_system/' . $profile_pic_url;
                            $display_profile_pic_src = str_replace('\\', '/', $display_profile_pic_src);
                        }
                    }
                    ?>
                    <img src="<?php echo $display_profile_pic_src; ?>" 
                         class="profile-pic" alt="Profile Picture">
                </div>
                <div>
                    <h6 class="mb-0 text-white"><?php echo $userName; ?></h6>
                    <small class="text-white-50">Customer</small>
                </div>
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
                                <h6 class="mb-0">Personal Info</h6>
                                <p class="text-muted small">Update your name, email, and phone</p>
                            </div>
                        </div>
                        <div class="d-flex mb-4">
                            <div class="account-info-icon">
                                <i class="fas fa-lock"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Password</h6>
                                <p class="text-muted small">Change your password</p>
                            </div>
                        </div>
                        <div class="d-flex mb-4">
                            <div class="account-info-icon">
                                <i class="fas fa-map-marker-alt"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Addresses</h6>
                                <p class="text-muted small">Manage your delivery addresses</p>
                            </div>
                        </div>
                        <div class="d-flex">
                            <div class="account-info-icon">
                                <i class="fas fa-bell"></i>
                            </div>
                            <div>
                                <h6 class="mb-0">Notifications</h6>
                                <p class="text-muted small">Configure notification preferences</p>
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
                            <i class="fas fa-shield-alt me-2"></i> Last login: 2 hours ago
                        </div>
                        <button class="btn btn-outline-primary w-100 mb-2">
                            <i class="fas fa-mobile-alt me-2"></i> Set up 2FA
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
                                <a class="nav-link active" href="#personal" data-bs-toggle="tab">Personal Info</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#addresses" data-bs-toggle="tab">Addresses</a>
                            </li>
                            <li class="nav-item">
                                <a class="nav-link" href="#preferences" data-bs-toggle="tab">Preferences</a>
                            </li>
                        </ul>
                    </div>
                    <div class="card-body">
                        <div class="tab-content">
                            <div class="tab-pane fade show active" id="personal">
                                <form method="POST" enctype="multipart/form-data">
                                    <div class="mb-3">
                                        <label for="profilePicture" class="form-label">Profile Picture</label>
                                        <input class="form-control" type="file" id="profilePicture" name="profile_picture" accept="image/*">
                                    </div>
                                    <div class="row mb-3">
                                        <div class="col-md-6">
                                            <label for="firstName" class="form-label">First Name</label>
                                            <input type="text" class="form-control" id="firstName" name="first_name" value="<?php echo htmlspecialchars($profile_data['first_name'] ?? ''); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="lastName" class="form-label">Last Name</label>
                                            <input type="text" class="form-control" id="lastName" name="last_name" value="<?php echo htmlspecialchars($profile_data['last_name'] ?? ''); ?>">
                                        </div>
                                    </div>
                                    <div class="mb-3">
                                        <label for="email" class="form-label">Email</label>
                                        <input type="email" class="form-control" id="email" value="<?php echo htmlspecialchars($profile_data['email'] ?? ''); ?>" disabled>
                                    </div>
                                    <div class="mb-3">
                                        <label for="phoneNumber" class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" id="phoneNumber" name="phone_number" value="<?php echo htmlspecialchars($profile_data['phone_number'] ?? ''); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label for="dateOfBirth" class="form-label">Date of Birth</label>
                                        <input type="date" class="form-control" id="dateOfBirth" name="date_of_birth" value="<?php echo htmlspecialchars($profile_data['date_of_birth'] ?? ''); ?>">
                                    </div>
                                    <button type="submit" name="update_profile" class="btn btn-primary">Update Profile</button>
                                </form>
                            </div>

                            <div class="tab-pane fade" id="addresses">
                                <div class="list-group-item d-flex align-items-center">
                                    <i class="fas fa-map-marker-alt fa-fw me-3"></i>
                                    <div>
                                        <h6 class="mb-0">Addresses</h6>
                                        <small class="text-muted">Manage your delivery addresses</small>
                                    </div>
                                    <button class="btn btn-sm btn-primary ms-auto" data-bs-toggle="modal" data-bs-target="#addAddressModal">+ Add New</button>
                                </div>

                                <!-- Addresses List -->
                                <div class="card-body">
                                    <?php if ($address_result->num_rows > 0): ?>
                                        <div class="row g-3">
                                            <?php while($address = $address_result->fetch_assoc()): ?>
                                                <div class="col-md-6">
                                                    <div class="card h-100 shadow-sm">
                                                        <div class="card-body d-flex flex-column">
                                                            <h6 class="card-title d-flex justify-content-between align-items-center">
                                                                <?php echo htmlspecialchars($address['address_title']); ?>
                                                                <?php if ($address['is_default']): ?>
                                                                    <span class="badge bg-success ms-2">Default</span>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <p class="card-text mb-1">
                                                                <?php echo htmlspecialchars($address['address_line1']); ?><br>
                                                                <?php if (!empty($address['address_line2'])): ?>
                                                                    <?php echo htmlspecialchars($address['address_line2']); ?><br>
                                                                <?php endif; ?>
                                                                <?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' ' . $address['zip_code']); ?>
                                                            </p>
                                                            <div class="mt-auto d-flex justify-content-end">
                                                                <button class="btn btn-sm btn-outline-primary me-2 edit-address-btn" 
                                                                        data-bs-toggle="modal" data-bs-target="#editAddressModal"
                                                                        data-id="<?php echo $address['address_id']; ?>"
                                                                        data-title="<?php echo htmlspecialchars($address['address_title']); ?>"
                                                                        data-line1="<?php echo htmlspecialchars($address['address_line1']); ?>"
                                                                        data-line2="<?php echo htmlspecialchars($address['address_line2']); ?>"
                                                                        data-city="<?php echo htmlspecialchars($address['city']); ?>"
                                                                        data-state="<?php echo htmlspecialchars($address['state']); ?>"
                                                                        data-zip="<?php echo htmlspecialchars($address['zip_code']); ?>"
                                                                        data-default="<?php echo $address['is_default']; ?>">
                                                                    Edit
                                                                </button>
                                                                <form method="post" onsubmit="return confirm('Are you sure you want to delete this address?');">
                                                                    <input type="hidden" name="delete_address" value="<?php echo $address['address_id']; ?>">
                                                                    <button type="submit" class="btn btn-sm btn-outline-danger me-2">Delete</button>
                                                                </form>
                                                                <?php if (!$address['is_default']): ?>
                                                                    <form method="post">
                                                                        <input type="hidden" name="set_default_address" value="<?php echo $address['address_id']; ?>">
                                                                        <button type="submit" class="btn btn-sm btn-outline-success">Set Default</button>
                                                                    </form>
                                                                <?php endif; ?>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endwhile; ?>
                                        </div>
                                    <?php else: ?>
                                        <div class="alert alert-info text-center" role="alert">
                                            No addresses saved. Add your first address to make ordering easier!
                                        </div>
                                    <?php endif; ?>
                                </div>

                            </div>

                            <div class="tab-pane fade" id="preferences">
                                <h5 class="mb-3">Dietary Preferences</h5>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="vegetarian">
                                    <label class="form-check-label" for="vegetarian">Vegetarian</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="vegan">
                                    <label class="form-check-label" for="vegan">Vegan</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="glutenFree">
                                    <label class="form-check-label" for="glutenFree">Gluten Free</label>
                                </div>
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="halal">
                                    <label class="form-check-label" for="halal">Halal</label>
                                </div>

                                <h5 class="mb-3">Notification Preferences</h5>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="orderUpdates" checked>
                                    <label class="form-check-label" for="orderUpdates">Order Updates</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="promotions" checked>
                                    <label class="form-check-label" for="promotions">Promotions & Offers</label>
                                </div>
                                <div class="form-check mb-2">
                                    <input class="form-check-input" type="checkbox" id="donationReminders" checked>
                                    <label class="form-check-label" for="donationReminders">Donation Reminders</label>
                                </div>
                                <div class="form-check mb-4">
                                    <input class="form-check-input" type="checkbox" id="newsletter" checked>
                                    <label class="form-check-label" for="newsletter">Newsletter</label>
                                </div>

                                <button type="submit" class="btn btn-primary">Save Preferences</button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5>Delete Account</h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle me-2"></i> This action cannot be undone. All your data will be permanently deleted.
                        </div>
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteAccountModal">
                            <i class="fas fa-trash-alt me-2"></i> Delete My Account
                        </button>
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
        <a href="orders.php" class="mobile-nav-item">
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
        <a href="profile.php" class="mobile-nav-item active">
            <i class="fas fa-user"></i>
            <span>Profile</span>
        </a>
    </div>

    <!-- Add Address Modal -->
    <div class="modal fade" id="addAddressModal" tabindex="-1" aria-labelledby="addAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addAddressModalLabel">Add New Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="addressTitle" class="form-label">Address Title (e.g., Home, Work)</label>
                            <input type="text" class="form-control" id="addressTitle" name="address_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="addressLine1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="addressLine1" name="address_line1" required>
                        </div>
                        <div class="mb-3">
                            <label for="addressLine2" class="form-label">Address Line 2 (Optional)</label>
                            <input type="text" class="form-control" id="addressLine2" name="address_line2" placeholder="Apt, suite, unit, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="city" class="form-label">City</label>
                                <input type="text" class="form-control" id="city" name="city" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="state" class="form-label">State</label>
                                <input type="text" class="form-control" id="state" name="state" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="zipCode" class="form-label">ZIP Code</label>
                            <input type="text" class="form-control" id="zipCode" name="zip_code" required>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isDefaultAdd" name="is_default">
                            <label class="form-check-label" for="isDefaultAdd">Set as default address</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_address" class="btn btn-primary">Save Address</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Edit Address Modal -->
    <div class="modal fade" id="editAddressModal" tabindex="-1" aria-labelledby="editAddressModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="editAddressModalLabel">Edit Address</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post">
                    <div class="modal-body">
                        <input type="hidden" name="address_id" id="editAddressId">
                        <div class="mb-3">
                            <label for="editAddressTitle" class="form-label">Address Title (e.g., Home, Work)</label>
                            <input type="text" class="form-control" id="editAddressTitle" name="address_title" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAddressLine1" class="form-label">Address Line 1</label>
                            <input type="text" class="form-control" id="editAddressLine1" name="address_line1" required>
                        </div>
                        <div class="mb-3">
                            <label for="editAddressLine2" class="form-label">Address Line 2 (Optional)</label>
                            <input type="text" class="form-control" id="editAddressLine2" name="address_line2" placeholder="Apt, suite, unit, etc.">
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="editCity" class="form-label">City</label>
                                <input type="text" class="form-control" id="editCity" name="city" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="editState" class="form-label">State</label>
                                <input type="text" class="form-control" id="editState" name="state" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="editZipCode" class="form-label">ZIP Code</label>
                            <input type="text" class="form-control" id="editZipCode" name="zip_code" required>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="isDefaultEdit" name="is_default">
                            <label class="form-check-label" for="isDefaultEdit">Set as default address</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="edit_address" class="btn btn-primary">Save Changes</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Account Modal -->
    <div class="modal fade" id="deleteAccountModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title">Confirm Account Deletion</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete your account? This will:</p>
                    <ul>
                        <li>Permanently delete your profile</li>
                        <li>Cancel any pending orders</li>
                        <li>Remove all your saved preferences</li>
                    </ul>
                    <div class="form-group mb-3">
                        <label for="deleteReason">Reason for leaving (optional)</label>
                        <select class="form-select" id="deleteReason">
                            <option value="">Select a reason</option>
                            <option>Found a better service</option>
                            <option>Privacy concerns</option>
                            <option>Too many notifications</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="deleteFeedback">Feedback (optional)</label>
                        <textarea class="form-control" id="deleteFeedback" rows="3" placeholder="What could we have done better?"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger">Delete My Account</button>
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
        let map = null; // Declare map variable outside to retain its state

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

            var editAddressModal = document.getElementById('editAddressModal');
            editAddressModal.addEventListener('show.bs.modal', function (event) {
                var button = event.relatedTarget; // Button that triggered the modal
                var id = button.getAttribute('data-id');
                var title = button.getAttribute('data-title');
                var line1 = button.getAttribute('data-line1');
                var line2 = button.getAttribute('data-line2');
                var city = button.getAttribute('data-city');
                var state = button.getAttribute('data-state');
                var zip = button.getAttribute('data-zip');
                var isDefault = button.getAttribute('data-default');

                var modalIdInput = editAddressModal.querySelector('#editAddressId');
                var modalTitleInput = editAddressModal.querySelector('#editAddressTitle');
                var modalLine1Input = editAddressModal.querySelector('#editAddressLine1');
                var modalLine2Input = editAddressModal.querySelector('#editAddressLine2');
                var modalCityInput = editAddressModal.querySelector('#editCity');
                var modalStateInput = editAddressModal.querySelector('#editState');
                var modalZipInput = editAddressModal.querySelector('#editZipCode');
                var modalIsDefaultCheckbox = editAddressModal.querySelector('#isDefaultEdit');

                modalIdInput.value = id;
                modalTitleInput.value = title;
                modalLine1Input.value = line1;
                modalLine2Input.value = line2;
                modalCityInput.value = city;
                modalStateInput.value = state;
                modalZipInput.value = zip;
                modalIsDefaultCheckbox.checked = (isDefault === '1');
            });

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