<?php
require_once '../session_check.php';
require_once '../db_config.php';

// Check if user is admin
if (!in_array($_SESSION['user_type'], ['admin', 'super_admin'])) {
    header("Location: ../unauthorized.php");
    exit();
}

// Get all users from database
$conn = new mysqli($servername, $username, $password, $dbname);
$users = [];
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    $result = $conn->query("SELECT user_id, username, full_name, email, user_type, created_at FROM users ORDER BY created_at DESC");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $users[] = $row;
        }
    }
    $conn->close();
}

// Handle form submission for adding new user
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $conn = new mysqli($servername, $username, $password, $dbname);
    
    if ($_POST['action'] === 'add') {
        $username = trim($_POST['username']);
        $full_name = trim($_POST['full_name']);
        $email = trim($_POST['email']);
        $user_type = $_POST['user_type'];
        $password = password_hash($_POST['password'], PASSWORD_DEFAULT);
        
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, email, password, user_type) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $username, $full_name, $email, $password, $user_type);
        
        if ($stmt->execute()) {
            header("Location: users.php?success=1");
            exit();
        } else {
            $error = "Error adding user: " . $stmt->error;
        }
        $stmt->close();
    }
    
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Users | FoodSave Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary: #10B981;
            --primary-dark: #059669;
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
        .main-content {
            margin-left: 250px;
            padding: 20px;
            background-color: var(--light);
            min-height: 100vh;
        }
        .badge-customer {
            background-color: #3B82F6;
        }
        .badge-restaurant {
            background-color: #8B5CF6;
        }
        .badge-ngo {
            background-color: #EC4899;
        }
        .badge-admin {
            background-color: #10B981;
        }
    </style>
</head>
<body>
    <?php include 'sidebar.php'; ?>

    <div class="main-content">
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2>Manage Users</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="fas fa-plus me-2"></i>Add New User
            </button>
        </div>

        <?php if (isset($_GET['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            User added successfully!
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
        <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?php echo htmlspecialchars($error); ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Username</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>User Type</th>
                                <th>Joined</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                            <tr>
                                <td><?php echo $user['user_id']; ?></td>
                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td>
                                    <?php 
                                    $badgeClass = 'badge-';
                                    switch($user['user_type']) {
                                        case 'customer': $badgeClass .= 'customer'; break;
                                        case 'restaurant': $badgeClass .= 'restaurant'; break;
                                        case 'ngo': $badgeClass .= 'ngo'; break;
                                        case 'admin': $badgeClass .= 'admin'; break;
                                        default: $badgeClass .= 'secondary';
                                    }
                                    ?>
                                    <span class="badge <?php echo $badgeClass; ?>">
                                        <?php echo ucfirst($user['user_type']); ?>
                                    </span>
                                </td>
                                <td><?php echo date('M d, Y', strtotime($user['created_at'])); ?></td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-2" onclick="editUser(<?php echo $user['user_id']; ?>)">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger" onclick="deleteUser(<?php echo $user['user_id']; ?>)">Delete</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add User Modal -->
    <div class="modal fade" id="addUserModal" tabindex="-1" aria-labelledby="addUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addUserModalLabel">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="POST" action="">
                    <div class="modal-body">
                        <input type="hidden" name="action" value="add">
                        <div class="mb-3">
                            <label for="username" class="form-label">Username</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Full Name</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">Email</label>
                            <input type="email" class="form-control" id="email" name="email" required>
                        </div>
                        <div class="mb-3">
                            <label for="user_type" class="form-label">User Type</label>
                            <select class="form-select" id="user_type" name="user_type" required>
                                <option value="customer">Customer</option>
                                <option value="restaurant">Restaurant</option>
                                <option value="ngo">NGO</option>
                                <option value="admin">Admin</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Password</label>
                            <input type="password" class="form-control" id="password" name="password" required minlength="8">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function editUser(userId) {
            // TODO: Implement edit user functionality
            alert('Edit user ' + userId);
        }

        function deleteUser(userId) {
            if (confirm('Are you sure you want to delete this user?')) {
                // TODO: Implement delete user functionality
                alert('Delete user ' + userId);
            }
        }
    </script>
</body>
</html>