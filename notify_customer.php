<?php
require_once '../session_check.php';
if (!hasAccess('restaurant')) {
    header("Location: ../unauthorized.php");
    exit();
}

require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

// Fetch restaurant details for sidebar and customer fetching
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

$message = '';
$customers = [];

if ($restaurant_id) { // Only fetch customers if a valid restaurant_id is found
    // Get restaurant's customers who have placed orders
    $sql_customers = "
        SELECT DISTINCT u.user_id, CONCAT(up.first_name, ' ', up.last_name) AS full_name, u.email
        FROM orders o
        JOIN users u ON o.customer_id = u.user_id
        JOIN user_profiles up ON u.user_id = up.user_id -- Join to get first_name and last_name
        WHERE o.restaurant_id = ?
        ORDER BY full_name ASC
    ";
    
    $stmt_customers = $conn->prepare($sql_customers);
    if ($stmt_customers === false) {
        $message = '<div class="alert alert-danger">Error preparing customer query: ' . $conn->error . '</div>';
    } else {
        $stmt_customers->bind_param("i", $restaurant_id);
        if (!$stmt_customers->execute()) {
            $message = '<div class="alert alert-danger">Error executing customer query: ' . $stmt_customers->error . '</div>';
        } else {
            $result_customers = $stmt_customers->get_result();

            if ($result_customers->num_rows > 0) {
                while($row = $result_customers->fetch_assoc()) {
                    $customers[] = $row;
                }
            } else {
                $message = '<div class="alert alert-info">No customers found who have placed orders with your restaurant yet.</div>';
            }
        }
        $stmt_customers->close();
    }
} else {
    $message = '<div class="alert alert-danger">Error: Restaurant ID not found for the logged-in user. Please log in again.</div>';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected_customers = $_POST['customers'] ?? [];
    $notification_title = trim($_POST['title'] ?? '');
    $notification_message = trim($_POST['message'] ?? '');
    
    if ($restaurant_id === null) {
        $message = '<div class="alert alert-danger">Error: Restaurant ID not found. Please log in again.</div>';
    } else if (!empty($selected_customers) && !empty($notification_title) && !empty($notification_message)) {
        $conn->begin_transaction();
        $success_all = true;
        foreach ($selected_customers as $customer_id) {
            $stmt = $conn->prepare("INSERT INTO notifications (user_id, restaurant_id, title, message, created_at) VALUES (?, ?, ?, ?, NOW())");
            if ($stmt === false) {
                $success_all = false;
                $message = '<div class="alert alert-danger">Database error preparing insert statement: ' . $conn->error . '</div>';
                break; 
            }
            $stmt->bind_param("iiss", $customer_id, $restaurant_id, $notification_title, $notification_message);
            if (!$stmt->execute()) {
                $success_all = false;
                // Optionally log the error: error_log("Failed to insert notification for user {$customer_id}: " . $stmt->error);
            }
            $stmt->close();
        }
        
        if ($success_all) {
            $conn->commit();
            $message = '<div class="alert alert-success">Notifications sent successfully to selected customers!</div>';
        } else {
            $conn->rollback();
            $message = '<div class="alert alert-danger">Error sending one or more notifications. All changes rolled back.</div>';
        }
    } else {
        $message = '<div class="alert alert-warning">Please select at least one customer and fill in the title and message fields.</div>';
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notify Customers | FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #3B82F6;
            --primary-dark: #2563EB;
            --accent: #F59E0B;
            --dark: #1F2937;
            --light: #F9FAFB;
        }
        .sidebar {
            background-color: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
        }
        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            border-radius: 5px;
        }
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }
        .sidebar .nav-link i {
            margin-right: 10px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background-color: var(--light);
            min-height: 100vh;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
        }
        .customer-list {
            max-height: 300px;
            overflow-y: auto;
        }
        .customer-item {
            padding: 10px;
            border-bottom: 1px solid #eee;
        }
        .customer-item:last-child {
            border-bottom: none;
        }
    </style>
</head>
<body>
    <div class="d-flex">
        <div class="sidebar p-3">
            <div class="text-center mb-4">
                <img src="<?php echo htmlspecialchars(str_starts_with($restaurant_logo_url_sidebar, 'http') ? $restaurant_logo_url_sidebar : '../' . $restaurant_logo_url_sidebar); ?>" 
                     alt="Restaurant Logo" class="img-fluid rounded-circle mb-2" style="width: 80px; height: 80px; object-fit: cover;">
                <h5 class="text-white"><?php echo $restaurant_name_sidebar; ?></h5>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item">
                    <a class="nav-link" href="dashboard.php">
                        <i class="fas fa-tachometer-alt"></i> Dashboard
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="menu.php">
                        <i class="fas fa-utensils"></i> Menu Management
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="orders.php">
                        <i class="fas fa-clipboard-list"></i> Orders
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="donations.php">
                        <i class="fas fa-donate"></i> Donations
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="schedule.php">
                        <i class="fas fa-calendar-alt"></i> Donation Schedule
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="profile.php">
                        <i class="fas fa-store"></i> Restaurant Profile
                    </a>
                </li>
                <li class="nav-item mt-3">
                    <a class="nav-link active" href="notify_customer.php">
                        <i class="fas fa-bell"></i> Notify Customers
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link" href="../logout.php">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </li>
            </ul>
        </div>

        <div class="main-content">
            <div class="d-flex justify-content-between align-items-center mb-4">
                <h2>Notify Customers</h2>
                <a href="dashboard.php" class="btn btn-outline-primary">
                    <i class="fas fa-arrow-left me-2"></i>Back to Dashboard
                </a>
            </div>

            <?php 
                echo $message; // Display general messages
            ?>

            <div class="card">
                <div class="card-body">
                    <form method="POST" action="">
                        <div class="mb-4">
                            <label class="form-label">Select Customers</label>
                            <div class="customer-list border rounded p-3">
                                <?php if (empty($customers)): ?>
                                    <p class="text-muted">No customers found.</p>
                                <?php else: ?>
                                    <div class="form-check mb-2">
                                        <input class="form-check-input" type="checkbox" id="selectAll">
                                        <label class="form-check-label" for="selectAll">
                                            Select All Customers
                                        </label>
                                    </div>
                                    <?php foreach ($customers as $customer): ?>
                                        <div class="customer-item">
                                            <div class="form-check">
                                                <input class="form-check-input customer-checkbox" type="checkbox" 
                                                       name="customers[]" value="<?php echo $customer['user_id']; ?>" 
                                                       id="customer<?php echo $customer['user_id']; ?>">
                                                <label class="form-check-label" for="customer<?php echo $customer['user_id']; ?>">
                                                    <?php echo htmlspecialchars($customer['full_name']); ?>
                                                    <small class="text-muted d-block"><?php echo htmlspecialchars($customer['email']); ?></small>
                                                </label>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="title" class="form-label">Notification Title</label>
                            <input type="text" class="form-control" id="title" name="title" required>
                        </div>

                        <div class="mb-3">
                            <label for="message" class="form-label">Message</label>
                            <textarea class="form-control" id="message" name="message" rows="4" required></textarea>
                        </div>

                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane me-2"></i>Send Notifications
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const selectAllCheckbox = document.getElementById('selectAll');
            const customerCheckboxes = document.querySelectorAll('.customer-checkbox');

            if (selectAllCheckbox) {
                selectAllCheckbox.addEventListener('change', function() {
                    customerCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            customerCheckboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    if (selectAllCheckbox) {
                        if (!this.checked) {
                            selectAllCheckbox.checked = false;
                        } else {
                            const allChecked = Array.from(customerCheckboxes).every(cb => cb.checked);
                            selectAllCheckbox.checked = allChecked;
                        }
                    }
                });
            });
        });
    </script>
</body>
</html> 