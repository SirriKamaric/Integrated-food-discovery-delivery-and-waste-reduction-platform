<?php
require_once '../session_check.php';

// Check if user is customer
if ($_SESSION['user_type'] !== 'customer') {
    header("Location: ../unauthorized.php");
    exit();
}

require_once '../db_config.php';
$conn = new mysqli($servername, $username, $password, $dbname);
if ($conn->connect_error) die("Connection failed: " . $conn->connect_error);

$success_message = '';
$error_message = '';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_address']) || isset($_POST['edit_address'])) {
        $address_line1 = $conn->real_escape_string($_POST['address_line1']);
        $address_line2 = $conn->real_escape_string($_POST['address_line2']);
        $city = $conn->real_escape_string($_POST['city']);
        $state = $conn->real_escape_string($_POST['state']);
        $postal_code = $conn->real_escape_string($_POST['postal_code']);
        $phone_number = $conn->real_escape_string($_POST['phone_number']);
        $is_default = isset($_POST['is_default']) ? 1 : 0;
        $user_id = $_SESSION['user_id'];

        if ($is_default) {
            // Reset all other addresses to non-default
            $conn->query("UPDATE customer_addresses SET is_default = 0 WHERE user_id = $user_id");
        }

        if (isset($_POST['add_address'])) {
            $sql = "INSERT INTO customer_addresses (user_id, address_line1, address_line2, city, state, postal_code, phone_number, is_default) 
                    VALUES ($user_id, '$address_line1', '$address_line2', '$city', '$state', '$postal_code', '$phone_number', $is_default)";
            if ($conn->query($sql)) {
                $success_message = "Address added successfully!";
            } else {
                $error_message = "Error adding address: " . $conn->error;
            }
        } else {
            $address_id = intval($_POST['address_id']);
            $sql = "UPDATE customer_addresses SET 
                    address_line1 = '$address_line1',
                    address_line2 = '$address_line2',
                    city = '$city',
                    state = '$state',
                    postal_code = '$postal_code',
                    phone_number = '$phone_number',
                    is_default = $is_default
                    WHERE address_id = $address_id AND user_id = $user_id";
            if ($conn->query($sql)) {
                $success_message = "Address updated successfully!";
            } else {
                $error_message = "Error updating address: " . $conn->error;
            }
        }
    } elseif (isset($_POST['delete_address'])) {
        $address_id = intval($_POST['address_id']);
        $user_id = $_SESSION['user_id'];
        
        if ($conn->query("DELETE FROM customer_addresses WHERE address_id = $address_id AND user_id = $user_id")) {
            $success_message = "Address deleted successfully!";
        } else {
            $error_message = "Error deleting address: " . $conn->error;
        }
    }
}

// Fetch user's addresses
$user_id = $_SESSION['user_id'];
$addresses = [];
$result = $conn->query("SELECT * FROM customer_addresses WHERE user_id = $user_id ORDER BY is_default DESC, created_at DESC");
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $addresses[] = $row;
    }
}

$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Delivery Addresses | FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        body { background: #f8fafc; }
        .address-card { 
            border-radius: 12px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.1);
            transition: transform 0.2s;
        }
        .address-card:hover {
            transform: translateY(-5px);
        }
        .default-badge {
            position: absolute;
            top: 10px;
            right: 10px;
        }
    </style>
</head>
<body>
<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>My Delivery Addresses</h2>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addAddressModal">
            <i class="fas fa-plus me-2"></i>Add New Address
        </button>
    </div>

    <?php if ($success_message): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if ($error_message): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="row">
        <?php if (empty($addresses)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center">
                    You haven't added any delivery addresses yet. Add your first address to start ordering!
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($addresses as $address): ?>
                <div class="col-md-6 mb-4">
                    <div class="card address-card h-100">
                        <div class="card-body">
                            <?php if ($address['is_default']): ?>
                                <span class="badge bg-primary default-badge">Default Address</span>
                            <?php endif; ?>
                            
                            <h5 class="card-title"><?php echo htmlspecialchars($address['address_line1']); ?></h5>
                            <?php if ($address['address_line2']): ?>
                                <p class="card-text"><?php echo htmlspecialchars($address['address_line2']); ?></p>
                            <?php endif; ?>
                            <p class="card-text">
                                <?php echo htmlspecialchars($address['city'] . ', ' . $address['state'] . ' ' . $address['postal_code']); ?>
                            </p>
                            <p class="card-text">
                                <i class="fas fa-phone me-2"></i><?php echo htmlspecialchars($address['phone_number']); ?>
                            </p>
                            
                            <div class="d-flex justify-content-end mt-3">
                                <button type="button" class="btn btn-outline-primary btn-sm me-2" 
                                        data-bs-toggle="modal" 
                                        data-bs-target="#editAddressModal<?php echo $address['address_id']; ?>">
                                    <i class="fas fa-edit me-1"></i>Edit
                                </button>
                                <form method="post" class="d-inline" onsubmit="return confirm('Are you sure you want to delete this address?');">
                                    <input type="hidden" name="address_id" value="<?php echo $address['address_id']; ?>">
                                    <button type="submit" name="delete_address" class="btn btn-outline-danger btn-sm">
                                        <i class="fas fa-trash me-1"></i>Delete
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Edit Address Modal -->
                <div class="modal fade" id="editAddressModal<?php echo $address['address_id']; ?>" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">Edit Address</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <form method="post">
                                <div class="modal-body">
                                    <input type="hidden" name="address_id" value="<?php echo $address['address_id']; ?>">
                                    <div class="mb-3">
                                        <label class="form-label">Address Line 1</label>
                                        <input type="text" class="form-control" name="address_line1" required
                                               value="<?php echo htmlspecialchars($address['address_line1']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Address Line 2 (Optional)</label>
                                        <input type="text" class="form-control" name="address_line2"
                                               value="<?php echo htmlspecialchars($address['address_line2']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">City</label>
                                        <input type="text" class="form-control" name="city" required
                                               value="<?php echo htmlspecialchars($address['city']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">State</label>
                                        <input type="text" class="form-control" name="state" required
                                               value="<?php echo htmlspecialchars($address['state']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Postal Code</label>
                                        <input type="text" class="form-control" name="postal_code" required
                                               value="<?php echo htmlspecialchars($address['postal_code']); ?>">
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label">Phone Number</label>
                                        <input type="tel" class="form-control" name="phone_number" required
                                               value="<?php echo htmlspecialchars($address['phone_number']); ?>">
                                    </div>
                                    <div class="form-check">
                                        <input type="checkbox" class="form-check-input" name="is_default" 
                                               id="isDefault<?php echo $address['address_id']; ?>"
                                               <?php echo $address['is_default'] ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="isDefault<?php echo $address['address_id']; ?>">
                                            Set as default address
                                        </label>
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
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Address Modal -->
<div class="modal fade" id="addAddressModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Address</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Address Line 1</label>
                        <input type="text" class="form-control" name="address_line1" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Address Line 2 (Optional)</label>
                        <input type="text" class="form-control" name="address_line2">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">City</label>
                        <input type="text" class="form-control" name="city" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">State</label>
                        <input type="text" class="form-control" name="state" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Postal Code</label>
                        <input type="text" class="form-control" name="postal_code" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Phone Number</label>
                        <input type="tel" class="form-control" name="phone_number" required>
                    </div>
                    <div class="form-check">
                        <input type="checkbox" class="form-check-input" name="is_default" id="isDefaultNew">
                        <label class="form-check-label" for="isDefaultNew">
                            Set as default address
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="add_address" class="btn btn-primary">Add Address</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 