<?php
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

// Initialize search query variable
$search_query = '';
if (isset($_GET['search_query']) && !empty(trim($_GET['search_query']))) {
    $search_query = $conn->real_escape_string(trim($_GET['search_query']));
}

// Fetch restaurants from the 'restaurants' table, including logo_url
$restaurants = [];

if (!empty($search_query)) {
    // Modify SQL query to search for food items and join with restaurants
    $sql = "
        SELECT DISTINCT r.restaurant_id, r.restaurant_name, r.cuisine_type, r.address, r.rating, r.opening_time, r.closing_time, r.logo_url
        FROM restaurants r
        JOIN menu_items mi ON r.restaurant_id = mi.restaurant_id
        WHERE r.is_active = 1 AND mi.item_name LIKE ?
        ORDER BY r.restaurant_name
    ";
    $stmt = $conn->prepare($sql);
    $search_param = '%' . $search_query . '%';
    $stmt->bind_param("s", $search_param);
    $stmt->execute();
    $result = $stmt->get_result();
} else {
    // Original SQL query to fetch all active restaurants
    $sql = "SELECT restaurant_id, restaurant_name, cuisine_type, address, rating, opening_time, closing_time, logo_url FROM restaurants WHERE is_active = 1 ORDER BY restaurant_name";
    $result = $conn->query($sql);
}

if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $restaurants[] = $row;
    }
}

// Close statement if it was prepared
if (isset($stmt)) {
    $stmt->close();
}

$conn->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodSave | Restaurants</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="../css/main.css">
    <link rel="stylesheet" href="../css/styles.css">
    <link rel="stylesheet" href="../index.css">
    <style>
        .restaurant-card {
            transition: transform 0.2s ease-in-out;
        }
        .restaurant-card:hover {
            transform: translateY(-5px);
        }
        .restaurant-card img.card-img-top {
            height: 180px; /* Fixed height for consistent image size */
            object-fit: cover; /* Ensures image covers the area without distortion */
        }
        .navbar-brand img {
            height: 30px;
            width: auto;
            margin-right: 10px;
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-success sticky-top">
        <div class="container">
            <a class="navbar-brand fw-bold" href="../index.php">
                <img src="../images/logo.jpg" alt="FoodSave" height="30"> FoodSave
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
                        <a class="nav-link" href="../surplus.html">Surplus Deals</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="../donate.html">Donate Food</a>
                    </li>
                </ul>
                <div class="d-flex">
                    <a href="../login.php" class="btn btn-outline-light me-2">Login</a>
                    <a href="../register.php" class="btn btn-light text-success">Sign Up</a>
                </div>
            </div>
        </div>
    </nav>
    
<!-- Include header -->
<div class="container py-5">
    <div class="row mb-4">
        <div class="col-md-6">
            <h1 class="fw-bold mb-0">Restaurants Near You</h1>
            <p class="text-muted">Showing all available restaurants</p>
        </div>
        <div class="col-md-6">
            <div class="d-flex gap-3">
                <form action="list.php" method="GET" class="flex-grow-1">
                    <div class="input-group">
                        <input type="text" class="form-control" placeholder="Search food items..." name="search_query" value="<?php echo htmlspecialchars($search_query); ?>">
                        <button class="btn btn-success" type="submit"><i class="fas fa-search"></i></button>
                    </div>
                </form>
                <div class="dropdown">
                    <button class="btn btn-outline-secondary dropdown-toggle" type="button" data-bs-toggle="dropdown">
                        <i class="fas fa-sliders-h me-1"></i> Filters
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end p-3" style="width: 300px;">
                        <li>
                            <label class="form-label">Cuisine</label>
                            <select class="form-select mb-3">
                                <option>All Cuisines</option>
                                <option>Italian</option>
                                <option>Mexican</option>
                                <option>Asian</option>
                            </select>
                        </li>
                        <li>
                            <label class="form-label">Sort By</label>
                            <select class="form-select mb-3">
                                <option>Recommended</option>
                                <option>Rating</option>
                                <option>Distance</option>
                                <option>Most Surplus</option>
                            </select>
                        </li>
                        <li>
                            <div class="d-grid">
                                <button class="btn btn-success">Apply Filters</button>
                            </div>
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row g-4">
        <?php if (empty($restaurants)): ?>
            <div class="col-12">
                <div class="alert alert-info text-center" role="alert">
                    <?php echo !empty($search_query) ? 'No restaurants found with ' . htmlspecialchars($search_query) . ' on their menu.' : 'No restaurants found yet.'; ?>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($restaurants as $restaurant): ?>
                <div class="col-md-6 col-lg-4 col-xl-3">
                    <div class="card restaurant-card h-100 border-0 shadow-sm overflow-hidden">
                        <div class="position-relative">
                            <?php
                            $logo_url = htmlspecialchars($restaurant['logo_url'] ?? '');
                            $display_logo_src = '../images/placeholder_restaurant.jpg'; // Default placeholder
                            
                            if (!empty($logo_url)) {
                                $full_logo_server_path = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $logo_url;
                                if (file_exists($full_logo_server_path) && !is_dir($full_logo_server_path)) {
                                    $display_logo_src = '/food_delivery_system/' . $logo_url;
                                } else {
                                    // Fallback for older restaurant logos which might be in restaurant/uploads/logos/
                                    $old_logo_path = 'restaurant/' . $logo_url;
                                    $full_old_logo_server_path = $_SERVER['DOCUMENT_ROOT'] . '/food_delivery_system/' . $old_logo_path;
                                    if (file_exists($full_old_logo_server_path) && !is_dir($full_old_logo_server_path)) {
                                        $display_logo_src = '/food_delivery_system/' . $old_logo_path;
                                    }
                                }
                            }
                            ?>
                            <img src="<?php echo $display_logo_src; ?>" class="card-img-top" alt="Restaurant Logo">
                            <!-- This badge for surplus items will remain a placeholder until we integrate surplus food data -->
                            <span class="badge bg-success position-absolute top-0 end-0 m-2">5 surplus items</span>
                        </div>
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <h5 class="card-title fw-bold mb-0"><?php echo htmlspecialchars($restaurant['restaurant_name']); ?></h5>
                                <span class="badge bg-success bg-opacity-10 text-success"><?php echo htmlspecialchars(number_format($restaurant['rating'], 1) ?? 'N/A'); ?> ★</span>
                            </div>
                            <p class="text-muted small mb-2">
                                <?php echo htmlspecialchars($restaurant['cuisine_type'] ?? 'N/A'); ?> • 
                                <?php echo htmlspecialchars($restaurant['address'] ?? 'N/A'); ?>
                            </p>
                            <p class="small mb-3">
                                Opens: <?php echo htmlspecialchars($restaurant['opening_time'] ?? 'N/A'); ?> • 
                                Closes: <?php echo htmlspecialchars($restaurant['closing_time'] ?? 'N/A'); ?>
                            </p>
                            <div class="d-flex justify-content-between align-items-center">
                                <span class="text-success fw-bold">20% OFF surplus</span>
                                <a href="menu.php?restaurant_id=<?php echo htmlspecialchars($restaurant['restaurant_id']); ?>" class="btn btn-sm btn-outline-success">View Menu</a>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
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
                        <li class="mb-2"><a href="../index.php" class="text-white">Home</a></li>
                        <li class="mb-2"><a href="../About.html" class="text-white">About</a></li>
                        <li class="mb-2"><a href="list.php" class="text-white">Restaurants</a></li>
                        <li class="mb-2"><a href="../Contact.html" class="text-white">Contact</a></li>
                    </ul>
                </div>
                <div class="col-md-3 mb-4">
                    <h5 class="fw-bold mb-3">Legal</h5>
                    <ul class="list-unstyled">
                        <li class="mb-2"><a href="../Privacy.html" class="text-white">Privacy Policy</a></li>
                        <li class="mb-2"><a href="../Terms.html" class="text-white">Terms of Service</a></li>
                        <li class="mb-2"><a href="../Cookie-policy.html" class="text-white">Cookie Policy</a></li>
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
</body>
</html> 