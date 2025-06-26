<?php
require_once '../session_check.php';
require_once '../db_config.php';

if (!in_array($_SESSION['user_type'], ['admin', 'super_admin'])) {
    header("Location: ../unauthorized.php");
    exit();
}

// Get all NGOs
$conn = new mysqli($servername, $username, $password, $dbname);
$ngos = [];
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
} else {
    $result = $conn->query("SELECT u.user_id, u.full_name, u.email, n.ngo_name, n.address, n.phone, n.registration_number 
                          FROM users u 
                          LEFT JOIN ngo n ON u.user_id = n.user_id 
                          WHERE u.user_type = 'ngo_admin'");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $ngos[] = $row;
        }
    }
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage NGOs | FoodSave Admin</title>
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
            <h2 class="page-title">Manage NGOs</h2>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addNgoModal">
                <i class="fas fa-plus me-2"></i>Add NGO
            </button>
        </div>

        <div class="card">
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>NGO Name</th>
                                <th>Admin Name</th>
                                <th>Email</th>
                                <th>Reg. Number</th>
                                <th>Address</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($ngos as $ngo): ?>
                            <tr>
                                <td><?php echo $ngo['user_id']; ?></td>
                                <td>
                                    <strong><?php echo htmlspecialchars($ngo['ngo_name']); ?></strong>
                                </td>
                                <td><?php echo htmlspecialchars($ngo['full_name']); ?></td>
                                <td>
                                    <a href="mailto:<?php echo htmlspecialchars($ngo['email']); ?>" class="text-primary">
                                        <?php echo htmlspecialchars($ngo['email']); ?>
                                    </a>
                                </td>
                                <td><?php echo htmlspecialchars($ngo['registration_number']); ?></td>
                                <td class="address-cell" title="<?php echo htmlspecialchars($ngo['address']); ?>">
                                    <?php echo htmlspecialchars($ngo['address']); ?>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary me-2 action-btn">
                                        <i class="fas fa-edit me-1"></i>Edit
                                    </button>
                                    <button class="btn btn-sm btn-outline-danger action-btn">
                                        <i class="fas fa-trash-alt me-1"></i>Delete
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add NGO Modal -->
    <div class="modal fade" id="addNgoModal" tabindex="-1" aria-labelledby="addNgoModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="addNgoModalLabel">Add New NGO</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form id="addNgoForm">
                        <div class="row">
                            <div class="col-md-6">
                                <h6 class="section-title">Admin Details</h6>
                                <div class="mb-3">
                                    <label for="ngoAdminName" class="form-label">Full Name</label>
                                    <input type="text" class="form-control" id="ngoAdminName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="ngoAdminEmail" class="form-label">Email</label>
                                    <input type="email" class="form-control" id="ngoAdminEmail" required>
                                </div>
                                <div class="mb-3">
                                    <label for="ngoAdminPassword" class="form-label">Password</label>
                                    <div class="input-group">
                                        <input type="password" class="form-control" id="ngoAdminPassword" required minlength="8">
                                        <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                    </div>
                                    <small class="text-muted">Minimum 8 characters</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6 class="section-title">NGO Details</h6>
                                <div class="mb-3">
                                    <label for="ngoName" class="form-label">NGO Name</label>
                                    <input type="text" class="form-control" id="ngoName" required>
                                </div>
                                <div class="mb-3">
                                    <label for="ngoRegNumber" class="form-label">Registration Number</label>
                                    <input type="text" class="form-control" id="ngoRegNumber" required>
                                </div>
                                <div class="mb-3">
                                    <label for="ngoAddress" class="form-label">Address</label>
                                    <textarea class="form-control" id="ngoAddress" rows="2" required></textarea>
                                </div>
                                <div class="mb-3">
                                    <label for="ngoPhone" class="form-label">Phone</label>
                                    <input type="tel" class="form-control" id="ngoPhone" required>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i>Save NGO
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Toggle password visibility
        document.getElementById('togglePassword').addEventListener('click', function() {
            const passwordInput = document.getElementById('ngoAdminPassword');
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
    </script>
</body>
</html>