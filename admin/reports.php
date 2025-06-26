<?php
require_once '../session_check.php';
require_once '../db_config.php';

if (!in_array($_SESSION['user_type'], ['admin', 'super_admin'])) {
    header("Location: ../unauthorized.php");
    exit();
}

// Get report data
$conn = new mysqli($servername, $username, $password, $dbname);
$stats = [
    'total_donations' => 0,
    'total_meals' => 0,
    'top_restaurants' => [],
    'top_ngos' => [],
    'monthly_data' => []
];

if (!$conn->connect_error) {
    // Get total donations
    $result = $conn->query("SELECT COUNT(*) as total FROM donations");
    if ($result->num_rows > 0) {
        $stats['total_donations'] = $result->fetch_assoc()['total'];
    }

    // Get total meals donated
    $result = $conn->query("SELECT SUM(quantity) as total FROM donations");
    if ($result->num_rows > 0) {
        $stats['total_meals'] = $result->fetch_assoc()['total'] ?? 0;
    }

    // Get top restaurants
    $result = $conn->query("SELECT r.restaurant_name, COUNT(d.donation_id) as donations 
                          FROM donations d
                          JOIN restaurants r ON d.user_id = r.user_id
                          GROUP BY d.user_id
                          ORDER BY donations DESC
                          LIMIT 5");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $stats['top_restaurants'][] = $row;
        }
    }

    // Get top NGOs
    $result = $conn->query("SELECT n.ngo_name, COUNT(d.donation_id) as distributions 
                          FROM donations d
                          JOIN ngo n ON d.ngo_id = n.user_id
                          WHERE d.status = 'distributed'
                          GROUP BY d.ngo_id
                          ORDER BY distributions DESC
                          LIMIT 5");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $stats['top_ngos'][] = $row;
        }
    }

    // Get monthly data
    $result = $conn->query("SELECT 
                          DATE_FORMAT(created_at, '%Y-%m') as month,
                          COUNT(*) as donations,
                          SUM(quantity) as meals
                          FROM donations
                          GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                          ORDER BY month DESC
                          LIMIT 12");
    if ($result->num_rows > 0) {
        while($row = $result->fetch_assoc()) {
            $stats['monthly_data'][] = $row;
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
    <title>Reports | FoodSave Admin</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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

        .btn-outline-success {
            color: var(--success);
            border-color: var(--success);
        }

        .btn-outline-success:hover {
            background-color: var(--success);
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

        /* Chart Styles */
        .chart-container {
            position: relative;
            height: 100%;
            min-height: 200px;
        }

        .chart-legend {
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .legend-item {
            display: flex;
            align-items: center;
            margin-bottom: 0.75rem;
        }

        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 4px;
            margin-right: 0.75rem;
        }

        /* Stat Cards */
        .stat-card {
            display: flex;
            align-items: center;
            padding: 1rem;
            border-radius: 0.5rem;
            background: white;
        }

        .stat-icon {
            width: 48px;
            height: 48px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 1rem;
            font-size: 1.25rem;
        }

        .stat-icon.primary {
            background-color: var(--primary-light);
            color: var(--primary);
        }

        .stat-icon.success {
            background-color: var(--success-light);
            color: var(--success);
        }

        .stat-icon.warning {
            background-color: var(--warning-light);
            color: var(--warning);
        }

        .stat-icon.danger {
            background-color: var(--danger-light);
            color: var(--danger);
        }

        .stat-info h3 {
            margin-bottom: 0.25rem;
            font-weight: 700;
            color: var(--dark);
            font-size: 1.5rem;
        }

        .stat-info h6 {
            color: var(--secondary);
            font-size: 0.8rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            margin-bottom: 0.5rem;
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
            .row > div {
                margin-bottom: 1rem;
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
            <h2 class="page-title">System Reports</h2>
            <div class="btn-group">
                <button class="btn btn-outline-primary">
                    <i class="fas fa-file-pdf me-1"></i>Export PDF
                </button>
                <button class="btn btn-outline-success">
                    <i class="fas fa-file-excel me-1"></i>Export Excel
                </button>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-chart-pie"></i> Donation Overview</h5>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="stat-icon primary">
                                        <i class="fas fa-donate"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>Total Donations</h6>
                                        <h3><?php echo $stats['total_donations']; ?></h3>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="stat-card">
                                    <div class="stat-icon success">
                                        <i class="fas fa-utensils"></i>
                                    </div>
                                    <div class="stat-info">
                                        <h6>Total Meals</h6>
                                        <h3><?php echo $stats['total_meals']; ?></h3>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-calendar-alt"></i> Monthly Donations</h5>
                        <div class="chart-container">
                            <canvas id="monthlyChart" height="120"></canvas>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-trophy"></i> Top Restaurants</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Restaurant</th>
                                        <th>Donations</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['top_restaurants'] as $restaurant): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($restaurant['restaurant_name']); ?></td>
                                        <td><?php echo $restaurant['donations']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary action-btn">
                                                <i class="fas fa-eye me-1"></i>View
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
            <div class="col-md-6">
                <div class="card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="fas fa-award"></i> Top NGOs</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>NGO</th>
                                        <th>Distributions</th>
                                        <th>Action</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($stats['top_ngos'] as $ngo): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($ngo['ngo_name']); ?></td>
                                        <td><?php echo $ngo['distributions']; ?></td>
                                        <td>
                                            <button class="btn btn-sm btn-outline-primary action-btn">
                                                <i class="fas fa-eye me-1"></i>View
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
        </div>

        <div class="card">
            <div class="card-body">
                <h5 class="card-title"><i class="fas fa-chart-doughnut"></i> Donation Status Distribution</h5>
                <div class="row">
                    <div class="col-md-8">
                        <div class="chart-container">
                            <canvas id="statusChart" height="250"></canvas>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div id="statusLegend" class="chart-legend"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Monthly Donations Chart
        const monthlyCtx = document.getElementById('monthlyChart').getContext('2d');
        const monthlyChart = new Chart(monthlyCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode(array_map(function($m) { return date('M Y', strtotime($m['month'])); }, $stats['monthly_data'])); ?>,
                datasets: [{
                    label: 'Donations',
                    data: <?php echo json_encode(array_column($stats['monthly_data'], 'donations')); ?>,
                    borderColor: '#10B981',
                    backgroundColor: 'rgba(16, 185, 129, 0.1)',
                    tension: 0.3,
                    fill: true,
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        mode: 'index',
                        intersect: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        grid: {
                            drawBorder: false,
                            color: 'rgba(0, 0, 0, 0.05)'
                        },
                        ticks: {
                            stepSize: 5
                        }
                    },
                    x: {
                        grid: {
                            display: false,
                            drawBorder: false
                        }
                    }
                }
            }
        });

        // Status Distribution Chart
        const statusCtx = document.getElementById('statusChart').getContext('2d');
        const statusChart = new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: ['Available', 'Reserved', 'Claimed', 'Distributed'],
                datasets: [{
                    data: [25, 15, 30, 45],
                    backgroundColor: [
                        '#10B981',
                        '#F59E0B',
                        '#3B82F6',
                        '#8B5CF6'
                    ],
                    borderWidth: 0,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                cutout: '70%'
            }
        });

        // Custom legend for status chart
        const legendItems = statusChart.data.labels.map((label, i) => {
            return `<div class="legend-item">
                <div class="legend-color" style="background-color: ${statusChart.data.datasets[0].backgroundColor[i]}"></div>
                <span>${label}</span>
            </div>`;
        });
        document.getElementById('statusLegend').innerHTML = legendItems.join('');
    </script>
</body>
</html>