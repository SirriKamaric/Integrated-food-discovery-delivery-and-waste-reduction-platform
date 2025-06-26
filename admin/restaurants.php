<?php
require_once '../session_check.php';
require_once '../db_config.php';

if (!in_array($_SESSION['user_type'], ['admin', 'super_admin'])) {
    header("Location: ../unauthorized.php");
    exit();
}

$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Add Restaurant logic
$add_success = false;
$add_error = '';
$edit_success = false;
$edit_error = '';
$delete_success = false;
$delete_error = '';

// Delete restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_restaurant'])) {
    $delete_user_id = intval($_POST['delete_user_id']);
    // Delete from users (cascade will remove restaurant, menu, etc.)
    if ($conn->query("DELETE FROM users WHERE user_id = $delete_user_id")) {
        $delete_success = true;
    } else {
        $delete_error = 'Failed to delete restaurant.';
    }
}

// Edit restaurant
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_restaurant'])) {
    $edit_user_id = intval($_POST['edit_user_id']);
    $edit_admin_name = $conn->real_escape_string($_POST['edit_admin_name']);
    $edit_admin_email = $conn->real_escape_string($_POST['edit_admin_email']);
    $edit_restaurant_name = $conn->real_escape_string($_POST['edit_restaurant_name']);
    $edit_restaurant_address = $conn->real_escape_string($_POST['edit_restaurant_address']);
    $edit_restaurant_phone = $conn->real_escape_string($_POST['edit_restaurant_phone']);
    // Update user
    $conn->query("UPDATE users SET full_name='$edit_admin_name', email='$edit_admin_email', username='$edit_admin_email' WHERE user_id=$edit_user_id");
    // Update restaurant
    $conn->query("UPDATE restaurants SET restaurant_name='$edit_restaurant_name', address='$edit_restaurant_address', phone_number='$edit_restaurant_phone' WHERE user_id=$edit_user_id");
    $edit_success = true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_restaurant'])) {
    $admin_name = $conn->real_escape_string($_POST['admin_name']);
    $admin_email = $conn->real_escape_string($_POST['admin_email']);
    $admin_password = password_hash($_POST['admin_password'], PASSWORD_DEFAULT);
    $restaurant_name = $conn->real_escape_string($_POST['restaurant_name']);
    $restaurant_address = $conn->real_escape_string($_POST['restaurant_address']);
    $restaurant_phone = $conn->real_escape_string($_POST['restaurant_phone']);
    // 1. Create user (restaurant admin)
    $user_sql = "INSERT INTO users (username, email, password, user_type, full_name) VALUES (?, ?, ?, 'restaurant', ?)";
    $user_stmt = $conn->prepare($user_sql);
    $user_stmt->bind_param('ssss', $admin_email, $admin_email, $admin_password, $admin_name);
    if ($user_stmt->execute()) {
        $user_id = $conn->insert_id;
        // 2. Create restaurant
        $rest_sql = "INSERT INTO restaurants (user_id, restaurant_name, address, phone_number) VALUES (?, ?, ?, ?)";
        $rest_stmt = $conn->prepare($rest_sql);
        $rest_stmt->bind_param('isss', $user_id, $restaurant_name, $restaurant_address, $restaurant_phone);
        if ($rest_stmt->execute()) {
            $restaurant_id = $conn->insert_id;
            // 3. Copy all categories for new restaurant
            $cat_result = $conn->query("SELECT DISTINCT category_name FROM menu_categories");
            if ($cat_result && $cat_result->num_rows > 0) {
                while ($row = $cat_result->fetch_assoc()) {
                    $category_name = $conn->real_escape_string($row['category_name']);
                    $conn->query("INSERT INTO menu_categories (restaurant_id, category_name) VALUES ($restaurant_id, '$category_name')");
                }
            }
            $add_success = true;
        } else {
            $add_error = 'Failed to create restaurant.';
        }
        $rest_stmt->close();
    } else {
        $add_error = 'Failed to create user.';
    }
    $user_stmt->close();
}

// Get all restaurants
$restaurants = [];
$result = $conn->query("SELECT u.user_id, u.full_name, u.email, r.restaurant_name, r.address, r.phone_number 
                      FROM users u 
                      LEFT JOIN restaurants r ON u.user_id = r.user_id 
                      WHERE u.user_type = 'restaurant_admin' OR u.user_type = 'restaurant'");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $restaurants[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Restaurants | FoodSave Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #059669;
            --primary-light: #A7F3D0;
            --accent: #F59E0B;
            --dark: #1F2937;
            --light: #F9FAFB;
            --gray: #E5E7EB;
            --text: #374151;
            --text-light: #6B7280;
            --white: #FFFFFF;
            --shadow-sm: 0 1px 2px rgba(0,0,0,0.05);
            --shadow-md: 0 4px 6px rgba(0,0,0,0.1);
            --shadow-lg: 0 10px 15px rgba(0,0,0,0.1);
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background-color: var(--light);
            color: var(--text);
        }

        .sidebar {
            background-color: var(--dark);
            color: white;
            height: 100vh;
            position: fixed;
            width: 250px;
            box-shadow: var(--shadow-md);
        }

        .sidebar .nav-link {
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 5px;
            border-radius: 5px;
            padding: 10px 15px;
            transition: all 0.3s ease;
        }

        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
        }

        .sidebar .nav-link i {
            margin-right: 10px;
            width: 20px;
            text-align: center;
        }

        .main-content {
            margin-left: 250px;
            padding: 30px;
            background-color: var(--light);
            min-height: 100vh;
        }

        .card {
            border: none;
            border-radius: 10px;
            box-shadow: var(--shadow-sm);
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            margin-bottom: 20px;
            background-color: var(--white);
        }

        .card:hover {
            box-shadow: var(--shadow-lg);
            transform: translateY(-2px);
        }

        .card-header, .card-footer {
            background-color: transparent;
            border-bottom: 1px solid var(--gray);
        }

        .table {
            color: var(--text);
        }

        .table th {
            font-weight: 600;
            color: var(--dark);
            border-bottom: 2px solid var(--primary-light);
            background-color: rgba(247, 250, 252, 0.8);
        }

        .table td {
            vertical-align: middle;
            border-top: 1px solid var(--gray);
        }

        .btn-primary {
            background-color: var(--primary);
            border-color: var(--primary);
        }

        .btn-primary:hover {
            background-color: var(--primary-dark);
            border-color: var(--primary-dark);
        }

        .btn-outline-primary {
            color: var(--primary);
            border-color: var(--primary);
        }

        .btn-outline-primary:hover {
            background-color: var(--primary);
            color: white;
        }

        .btn-outline-danger {
            color: #EF4444;
            border-color: #EF4444;
        }

        .btn-outline-danger:hover {
            background-color: #EF4444;
            color: white;
        }

        .form-control, .form-select {
            border: 1px solid var(--gray);
            padding: 10px 15px;
            border-radius: 8px;
        }

        .form-control:focus, .form-select:focus {
            border-color: var(--primary);
            box-shadow: 0 0 0 2px rgba(16, 185, 129, 0.2);
        }

        .modal-header {
            border-bottom: 1px solid var(--gray);
        }

        .modal-footer {
            border-top: 1px solid var(--gray);
        }

        .page-title {
            color: var(--dark);
            font-weight: 600;
            margin-bottom: 20px;
        }

        .action-btn {
            padding: 5px 12px;
            font-size: 0.85rem;
        }

        .section-title {
            font-size: 1rem;
            font-weight: 600;
            color: var(--primary-dark);
            margin-bottom: 15px;
            padding-bottom: 8px;
            border-bottom: 1px solid var(--primary-light);
        }

        /* Responsive adjustments */
        @media (max-width: 992px) {
            .sidebar {
                width: 220px;
            }
            .main-content {
                margin-left: 220px;
            }
        }

        @media (max-width: 768px) {
            .main-content {
                padding: 20px 15px;
            }
            .card-body {
                padding: 15px;
            }
            .modal-dialog {
                margin: 1rem auto;
            }
        }

        @media (max-width: 576px) {
            .sidebar {
                width: 100%;
                height: auto;
                position: relative;
            }
            .main-content {
                margin-left: 0;
            }
            .btn {
                padding: 8px 12px;
                font-size: 0.85rem;
            }
        }

        /* Table row hover effect */
        .table-hover tbody tr:hover {
            background-color: rgba(16, 185, 129, 0.05);
        }

        /* Address cell styling */
        .address-cell {
            max-width: 250px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        /* Modal styling */
        .modal-content {
            border-radius: 10px;
            border: none;
            box-shadow: var(--shadow-lg);
        }

        /* Input group styling */
        .input-group-text {
            background-color: var(--light);
            border-color: var(--gray);
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="page-title">Manage Restaurants</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addRestaurantModal">
                <i class="fas fa-plus me-2"></i>Add Restaurant
            </button>
        </div>

        <?php if ($add_success): ?>
            <div class="alert alert-success">Restaurant added successfully!</div>
        <?php elseif ($add_error): ?>
            <div class="alert alert-danger"><?php echo $add_error; ?></div>
        <?php endif; ?>
        <?php if ($edit_success): ?>
            <div class="alert alert-success">Restaurant updated successfully!</div>
        <?php elseif ($edit_error): ?>
            <div class="alert alert-danger"><?php echo $edit_error; ?></div>
        <?php endif; ?>
        <?php if ($delete_success): ?>
            <div class="alert alert-success">Restaurant deleted successfully!</div>
        <?php elseif ($delete_error): ?>
            <div class="alert alert-danger"><?php echo $delete_error; ?></div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Restaurant Name</th>
                                <th>Admin Name</th>
                                <th>Email</th>
                                <th>Address</th>
                                <th>Phone</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($restaurants as $restaurant): ?>
                            <tr>
                                <td><?php echo $restaurant['user_id']; ?></td>
                                <td><strong><?php echo htmlspecialchars($restaurant['restaurant_name']); ?></strong></td>
                                <td><?php echo htmlspecialchars($restaurant['full_name']); ?></td>
                                <td><a href="mailto:<?php echo htmlspecialchars($restaurant['email']); ?>" class="text-primary"><?php echo htmlspecialchars($restaurant['email']); ?></a></td>
                                <td class="address-cell" title="<?php echo htmlspecialchars($restaurant['address']); ?>"><?php echo htmlspecialchars($restaurant['address']); ?></td>
                                <td><?php echo htmlspecialchars($restaurant['phone_number']); ?></td>
                                <td>
                                    <!-- Edit Button -->
                                    <button class="btn btn-sm btn-outline-primary me-2 action-btn" data-bs-toggle="modal" data-bs-target="#editRestaurantModal<?php echo $restaurant['user_id']; ?>">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                    <!-- Delete Form -->
                                    <form method="post" style="display:inline;" onsubmit="return confirm('Are you sure you want to delete this restaurant?');">
                                        <input type="hidden" name="delete_restaurant" value="1">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $restaurant['user_id']; ?>">
                                        <button type="submit" class="btn btn-sm btn-outline-danger action-btn">
                                            <i class="fas fa-trash-alt me-1"></i>Delete
                                        </button>
                                    </form>
                                    <!-- Edit Modal -->
                                    <div class="modal fade" id="editRestaurantModal<?php echo $restaurant['user_id']; ?>" tabindex="-1" aria-labelledby="editRestaurantModalLabel<?php echo $restaurant['user_id']; ?>" aria-hidden="true">
                                        <div class="modal-dialog modal-lg">
                                            <div class="modal-content">
                                                <div class="modal-header">
                                                    <h5 class="modal-title" id="editRestaurantModalLabel<?php echo $restaurant['user_id']; ?>">Edit Restaurant</h5>
                                                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                                                </div>
                                                <form method="post">
                                                    <input type="hidden" name="edit_restaurant" value="1">
                                                    <input type="hidden" name="edit_user_id" value="<?php echo $restaurant['user_id']; ?>">
                                                    <div class="modal-body">
                                                        <div class="row">
                                                            <div class="col-md-6">
                                                                <h6 class="section-title">Admin Details</h6>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Full Name</label>
                                                                    <input type="text" class="form-control" name="edit_admin_name" value="<?php echo htmlspecialchars($restaurant['full_name']); ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Email</label>
                                                                    <input type="email" class="form-control" name="edit_admin_email" value="<?php echo htmlspecialchars($restaurant['email']); ?>" required>
                                                                </div>
                                                            </div>
                                                            <div class="col-md-6">
                                                                <h6 class="section-title">Restaurant Details</h6>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Restaurant Name</label>
                                                                    <input type="text" class="form-control" name="edit_restaurant_name" value="<?php echo htmlspecialchars($restaurant['restaurant_name']); ?>" required>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Address</label>
                                                                    <textarea class="form-control" name="edit_restaurant_address" rows="2" required><?php echo htmlspecialchars($restaurant['address']); ?></textarea>
                                                                </div>
                                                                <div class="mb-3">
                                                                    <label class="form-label">Phone</label>
                                                                    <input type="tel" class="form-control" name="edit_restaurant_phone" value="<?php echo htmlspecialchars($restaurant['phone_number']); ?>" required>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                    <div class="modal-footer">
                                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                        <button type="submit" class="btn btn-primary">
                                                            <i class="fas fa-save me-1"></i>Save Changes
                                                        </button>
                                                    </div>
                                                </form>
                                            </div>
                                        </div>
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

    <!-- Add Restaurant Modal -->
    <div class="modal fade" id="addRestaurantModal" tabindex="-1" aria-labelledby="addRestaurantModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addRestaurantModalLabel">Add New Restaurant</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form method="post">
                        <input type="hidden" name="add_restaurant" value="1">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="section-title">Admin Details</h6>
                                <div class="mb-3">
                                    <label for="restaurantAdminName" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="restaurantAdminName" name="admin_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="restaurantAdminEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="restaurantAdminEmail" name="admin_email" required>
                                </div>
                                <div class="mb-3">
                                    <label for="restaurantAdminPassword" class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="restaurantAdminPassword" name="admin_password" required minlength="8">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="section-title">Restaurant Details</h6>
                                <div class="mb-3">
                                    <label for="restaurantName" class="form-label">Restaurant Name</label>
                                    <input type="text" class="form-control" id="restaurantName" name="restaurant_name" required>
                                </div>
                                <div class="mb-3">
                                    <label for="restaurantAddress" class="form-label">Address</label>
                                    <textarea class="form-control" id="restaurantAddress" name="restaurant_address" rows="2" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="restaurantPhone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="restaurantPhone" name="restaurant_phone" required>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i>Save Restaurant
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('restaurantAdminPassword');
            const icon = this.querySelector('i');
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        });

        // Initialize tooltips
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl);
        });

        // Prevent modal from auto-closing
        document.addEventListener('DOMContentLoaded', function() {
            const editModals = document.querySelectorAll('[id^="editRestaurantModal"]');
            editModals.forEach(modal => {
                modal.addEventListener('hidden.bs.modal', function(event) {
                    event.preventDefault();
                });
            });
        });
    </script>
</body>
</html>