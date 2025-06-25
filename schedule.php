<?php
require_once '../session_check.php';
if (!hasAccess('restaurant')) {
    header("Location: ../unauthorized.php");
    exit();
}

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

$restaurant_id = $_SESSION['user_id'];
$scheduled_donations = [];
$result = $conn->query("SELECT * FROM donation_schedules WHERE restaurant_id = $restaurant_id ORDER BY schedule_date ASC");
if ($result->num_rows > 0) {
    while($row = $result->fetch_assoc()) {
        $scheduled_donations[] = $row;
    }
}
$conn->close();
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Donation Schedule | FoodSave</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.css">
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
        
        .status-scheduled { background-color: #DBEAFE; color: #1E40AF; }
        .status-completed { background-color: #D1FAE5; color: #065F46; }
        .status-cancelled { background-color: #FEE2E2; color: #991B1B; }
        
        body.dark-mode .status-scheduled { background-color: #1E3A8A; color: #BFDBFE; }
        body.dark-mode .status-completed { background-color: #064E3B; color: #A7F3D0; }
        body.dark-mode .status-cancelled { background-color: #7F1D1D; color: #FECACA; }
        
        #calendar {
            background-color: white;
            border-radius: 10px;
            padding: 15px;
            box-shadow: 0 4px 6px rgba(0, 0, 0, 0.05);
        }
        
        body.dark-mode #calendar {
            background-color: var(--dark-card-bg);
        }
        
        .fc-event {
            cursor: pointer;
            border-radius: 6px;
            padding: 2px 4px;
            font-size: 0.85rem;
        }
        
        .fc-toolbar-title {
            font-size: 1.25rem;
        }
        
        .fc-button {
            padding: 0.3rem 0.6rem;
            font-size: 0.85rem;
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
            
            #calendar {
                padding: 10px;
            }
            
            .fc-toolbar-title {
                font-size: 1.1rem;
            }
            
            .fc-button {
                padding: 0.2rem 0.4rem;
                font-size: 0.75rem;
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
            
            .fc-toolbar {
                flex-direction: column;
                align-items: flex-start;
            }
            
            .fc-toolbar-chunk {
                margin-bottom: 10px;
            }
            
            .fc-header-toolbar .fc-toolbar-chunk:last-child {
                align-self: flex-end;
            }
            
            .card-header {
                padding: 12px 15px;
            }
        }
        
        @media (max-width: 575px) {
            .main-content {
                padding: 8px;
            }
            
            .fc-toolbar-title {
                font-size: 1rem;
            }
            
            .fc-button {
                padding: 0.15rem 0.3rem;
                font-size: 0.7rem;
            }
            
            .btn-sm {
                padding: 0.25rem 0.5rem;
                font-size: 0.8rem;
            }
            
            .badge-status {
                font-size: 0.65rem;
                padding: 3px 6px;
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
                    <a class="nav-link active" href="schedule.php">
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
                <h2 class="mb-0">Donation Schedule</h2>
            </div>
            <div class="d-flex align-items-center">
                <button id="themeToggleBtn" class="btn btn-secondary me-2 theme-toggle-btn" title="Toggle Theme"><i class="fas fa-moon"></i></button>
                <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                    <i class="fas fa-plus me-2"></i>Schedule Donation
                </button>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-lg-8">
                <div class="card mb-4">
                    <div class="card-body p-0">
                        <div id="calendar"></div>
                    </div>
                </div>
            </div>
            <div class="col-lg-4">
                <div class="card mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Upcoming Donations</h5>
                    </div>
                    <div class="card-body">
                        <?php foreach (array_slice($scheduled_donations, 0, 3) as $schedule): ?>
                        <div class="card mb-3">
                            <div class="card-body">
                                <div class="d-flex justify-content-between align-items-start mb-2">
                                    <h6 class="mb-0"><?php echo htmlspecialchars($schedule['donation_type']); ?></h6>
                                    <span class="badge-status 
                                        <?php 
                                        switch(strtolower($schedule['status'])) {
                                            case 'scheduled': echo 'status-scheduled'; break;
                                            case 'completed': echo 'status-completed'; break;
                                            case 'cancelled': echo 'status-cancelled'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst($schedule['status']); ?>
                                    </span>
                                </div>
                                <p class="text-muted small mb-1">
                                    <i class="fas fa-calendar-day me-2"></i>
                                    <?php echo date('M d, Y', strtotime($schedule['schedule_date'])); ?>
                                </p>
                                <p class="text-muted small mb-2">
                                    <i class="fas fa-clock me-2"></i>
                                    <?php echo date('h:i A', strtotime($schedule['schedule_time'])); ?>
                                </p>
                                <div class="d-flex justify-content-between">
                                    <small class="text-muted"><?php echo $schedule['quantity']; ?> meals</small>
                                    <button class="btn btn-sm btn-outline-primary">Details</button>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>

                        <?php if (empty($scheduled_donations)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                            <h5>No scheduled donations</h5>
                            <p class="text-muted">Schedule your first donation</p>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0">Quick Stats</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-flex justify-content-between mb-3">
                            <span>Scheduled:</span>
                            <strong><?php echo count(array_filter($scheduled_donations, fn($s) => $s['status'] === 'scheduled')); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between mb-3">
                            <span>Completed:</span>
                            <strong><?php echo count(array_filter($scheduled_donations, fn($s) => $s['status'] === 'completed')); ?></strong>
                        </div>
                        <div class="d-flex justify-content-between">
                            <span>Total Meals:</span>
                            <strong><?php echo array_sum(array_column($scheduled_donations, 'quantity')); ?></strong>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">All Scheduled Donations</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>Type</th>
                                <th>Date & Time</th>
                                <th>Quantity</th>
                                <th>NGO</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($scheduled_donations as $schedule): ?>
                            <tr>
                                <td>#<?php echo $schedule['id']; ?></td>
                                <td><?php echo htmlspecialchars($schedule['donation_type']); ?></td>
                                <td>
                                    <?php echo date('M d, Y', strtotime($schedule['schedule_date'])); ?>
                                    <small class="text-muted d-block"><?php echo date('h:i A', strtotime($schedule['schedule_time'])); ?></small>
                                </td>
                                <td><?php echo $schedule['quantity']; ?> meals</td>
                                <td><?php echo htmlspecialchars($schedule['ngo_name'] ?? 'Not assigned'); ?></td>
                                <td>
                                    <span class="badge-status 
                                        <?php 
                                        switch(strtolower($schedule['status'])) {
                                            case 'scheduled': echo 'status-scheduled'; break;
                                            case 'completed': echo 'status-completed'; break;
                                            case 'cancelled': echo 'status-cancelled'; break;
                                            default: echo 'bg-secondary';
                                        }
                                        ?>">
                                        <?php echo ucfirst($schedule['status']); ?>
                                    </span>
                                </td>
                                <td>
                                    <button class="btn btn-sm btn-outline-primary">Edit</button>
                                    <button class="btn btn-sm btn-outline-danger">Cancel</button>
                                </td>
                            </tr>
                            <?php endforeach; ?>

                            <?php if (empty($scheduled_donations)): ?>
                            <tr>
                                <td colspan="7" class="text-center py-5">
                                    <i class="fas fa-calendar-alt fa-3x text-muted mb-3"></i>
                                    <h4>No scheduled donations found</h4>
                                    <p class="text-muted">Schedule your first donation to get started</p>
                                    <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
                                        <i class="fas fa-plus me-2"></i>Schedule Donation
                                    </button>
                                </td>
                            </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <!-- Add Schedule Modal -->
    <div class="modal fade" id="addScheduleModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Schedule New Donation</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <form>
                        <div class="mb-3">
                            <label class="form-label">Donation Type</label>
                            <select class="form-select" required>
                                <option value="">Select Type</option>
                                <option>Daily Excess</option>
                                <option>Weekly Bulk</option>
                                <option>Event Leftovers</option>
                                <option>Seasonal</option>
                            </select>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Date</label>
                                <input type="date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Time</label>
                                <input type="time" class="form-control" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Estimated Quantity (meals)</label>
                            <input type="number" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Preferred NGO (optional)</label>
                            <select class="form-select">
                                <option value="">Any NGO</option>
                                <option>Food for All</option>
                                <option>Share the Meal</option>
                                <option>Hunger Free</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Special Instructions</label>
                            <textarea class="form-control" rows="3"></textarea>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary">Schedule</button>
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
        <a href="profile.php" class="mobile-nav-item">
            <i class="fas fa-store"></i>
            <span>Profile</span>
        </a>
    </div>

    <!-- Sidebar backdrop for mobile -->
    <div class="sidebar-backdrop" id="sidebarBackdrop"></div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/fullcalendar@5.11.3/main.min.js"></script>
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

            // Initialize FullCalendar
            var calendarEl = document.getElementById('calendar');
            var calendar = new FullCalendar.Calendar(calendarEl, {
                initialView: 'dayGridMonth',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: 'dayGridMonth,timeGridWeek,timeGridDay'
                },
                events: [
                    <?php foreach ($scheduled_donations as $schedule): ?>
                    {
                        title: 'Donation: <?php echo $schedule['quantity']; ?> meals',
                        start: '<?php echo $schedule['schedule_date'] . 'T' . $schedule['schedule_time']; ?>',
                        color: '<?php echo $schedule['status'] === 'completed' ? 'var(--success)' : ($schedule['status'] === 'cancelled' ? 'var(--danger)' : 'var(--primary)'); ?>'
                    },
                    <?php endforeach; ?>
                ],
                eventClick: function(info) {
                    alert('Event: ' + info.event.title);
                }
            });
            calendar.render();

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