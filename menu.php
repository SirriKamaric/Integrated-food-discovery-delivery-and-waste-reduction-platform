<?php
session_start(); // Start the session at the very beginning

// Database configuration
$servername = "localhost";
$username = "root";
$password = "";
$dbname = "food_delivery_system";

// Create connection
$conn = new mysqli($servername, $username, $password, $dbname);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

$restaurantName = "Unknown Restaurant";
$restaurantId = null;
$categorizedMenuItems = [];

// Check if user is logged in
$isLoggedIn = isset($_SESSION['user_id']);

if (isset($_GET['restaurant_id'])) {
    $restaurantId = intval($_GET['restaurant_id']);

    // Fetch restaurant details
    $stmt = $conn->prepare("SELECT restaurant_name FROM restaurants WHERE restaurant_id = ?");
    $stmt->bind_param("i", $restaurantId);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows > 0) {
        $row = $result->fetch_assoc();
        $restaurantName = htmlspecialchars($row['restaurant_name']);
    }
    $stmt->close();

    // Fetch menu items and their categories for this restaurant
    $sqlMenuItems = "
        SELECT
            mi.item_id,
            mi.item_name,
            mi.description,
            mi.price,
            mi.image_url,
            mc.category_name
        FROM menu_items mi
        LEFT JOIN menu_categories mc ON mi.category_id = mc.category_id
        WHERE mi.restaurant_id = ?
        ORDER BY mc.category_name, mi.item_name
    ";
    $stmtMenuItems = $conn->prepare($sqlMenuItems);
    $stmtMenuItems->bind_param("i", $restaurantId);
    $stmtMenuItems->execute();
    $resultMenuItems = $stmtMenuItems->get_result();

    if ($resultMenuItems->num_rows > 0) {
        while($item = $resultMenuItems->fetch_assoc()) {
            $category = $item['category_name'] ?? 'Uncategorized';
            $categorizedMenuItems[$category][] = $item;
        }
    }
    $stmtMenuItems->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $restaurantName; ?> | Menu</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="index.css">
    <style>
        .navbar-brand img {
            height: 30px;
            width: auto;
            margin-right: 10px;
        }
        .menu-category-title {
            border-bottom: 2px solid var(--bs-success);
            padding-bottom: 0.5rem;
            margin-bottom: 1.5rem;
        }
        .menu-item-card img {
            height: 180px;
            object-fit: cover;
        }
        .menu-item-card {
            transition: transform 0.2s ease-in-out;
        }
        .menu-item-card:hover {
            transform: translateY(-5px);
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="index.php">
                <img src="images/logo.jpg" alt="FoodSave" height="30"> FoodSave
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="list.php">Restaurants</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="surplus.html">Surplus Deals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="donate.html">Donate Food</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="customer/cart.php"><i class="fas fa-shopping-cart"></i> Cart</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                    <?php if ($isLoggedIn): ?>
                        <!-- You might want a logout button here or a user profile link -->
                        <a href="logout.php" class="btn btn-light text-success">Logout</a>
                    <?php else: ?>
                        <a href="register.php" class="btn btn-light text-success">Sign Up</a>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
    
    <div class="container py-5">
        <div class="row">
            <div class="col-12">
                <h1 class="fw-bold mb-4">Menu for <?php echo $restaurantName; ?></h1>
                <?php if ($restaurantId === null): ?>
                    <div class="alert alert-warning" role="alert">
                        No restaurant selected. Please go back to the <a href="list.php">restaurants list</a>.
                    </div>
                <?php elseif (empty($categorizedMenuItems)): ?>
                    <div class="alert alert-info" role="alert">
                        No menu items found for <?php echo $restaurantName; ?> yet. Check back later!
                    </div>
                <?php else: ?>
                    <?php foreach ($categorizedMenuItems as $categoryName => $menuItems): ?>
                        <h3 class="menu-category-title mt-5"><?php echo htmlspecialchars($categoryName); ?></h3>
                        <div class="row g-4">
                            <?php foreach ($menuItems as $item): ?>
                                <div class="col-md-4 col-lg-3">
                                    <div class="card h-100 shadow-sm menu-item-card">
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
                                        <img src="<?php echo $img_src; ?>" class="card-img-top" alt="<?php echo htmlspecialchars($item['item_name']); ?> Image">
                                        <div class="card-body">
                                            <h5 class="card-title fw-bold"><?php echo htmlspecialchars($item['item_name']); ?></h5>
                                            <p class="card-text text-muted small"><?php echo htmlspecialchars($item['description']); ?></p>
                                            <p class="fw-bold text-success">XAF <?php echo htmlspecialchars(number_format($item['price'], 2)); ?></p>
                                            <?php if ($isLoggedIn): ?>
                                                <button class="btn btn-success add-to-cart-btn" data-item-id="<?php echo $item['item_id']; ?>" data-restaurant-id="<?php echo $restaurantId; ?>">Add to Cart</button>
                                            <?php else: ?>
                                                <a href="login.php" class="btn btn-success">Login to Order</a>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-4 mt-5">
        <div class="container">
            <div class="row">
                <div class="col-md-4 mb-4">
                    <h5 class="fw-bold mb-3">FoodSave</h5>
                    <p>Reducing food waste while helping you discover amazing meals at great prices.</p>
                    <div class="social-icons">
                        <a href="#" class="text-white me-3"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-twitter"></i></a>
                        <a href="#" class="text-white me-3"><i class="fab fa-instagram"></i></a>
                    </div>
                </div>
                <div class="col-md-2 mb-4">
                    <h5 class="fw-bold mb-3">Quick Links</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="index.php" class="text-white">Home</a></li>
                        <li class="mb-2"><a href="About.html" class="text-white">About</a></li>
                        <li class="mb-2"><a href="list.php" class="text-white">Restaurants</a></li>
                        <li class="mb-2"><a href="Contact.html" class="text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="fw-bold mb-3">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="Privacy.html" class="text-white">Privacy Policy</a></li>
                        <li class="mb-2"><a href="Terms.html" class="text-white">Terms of Service</a></li>
                        <li class="mb-2"><a href="Cookie-policy.html" class="text-white">Cookie Policy</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="fw-bold mb-3">Newsletter</h5>
                    <p>Subscribe to get updates on surplus deals.</p>
                    <div class="input-group mb-3">
                        <input type="email" class="form-control" placeholder="Your email">
                        <button class="btn btn-success" type="button">Subscribe</button>
                    </div>
                </div>
            </div>
            <hr class="my-4 bg-secondary">
            <div class="text-center">
                <p class="mb-0">&copy; 2025 FoodSave. All rights reserved.</p>
            </div>
        </div>
    </footer>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.8/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const addToCartButtons = document.querySelectorAll('.add-to-cart-btn');

            addToCartButtons.forEach(button => {
                button.addEventListener('click', function() {
                    const itemId = this.dataset.itemId;
                    const restaurantId = this.dataset.restaurantId;

                    if (!itemId || !restaurantId) {
                        alert('Error: Missing item or restaurant ID.');
                        return;
                    }

                    fetch('add_to_cart.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: `item_id=${itemId}&restaurant_id=${restaurantId}`
                    })
                    .then(response => response.json())
                    .then(data => {
                        if (data.success) {
                            alert(data.message); // Or a more sophisticated notification
                        } else {
                            alert('Failed to add item to cart: ' + data.message);
                        }
                    })
                    .catch(error => {
                        console.error('Error:', error);
                        alert('An error occurred while adding the item to the cart.');
                    });
                });
            });
        });
    </script>
</body>
</html> 