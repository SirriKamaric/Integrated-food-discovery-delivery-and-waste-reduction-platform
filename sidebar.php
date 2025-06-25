<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start(); // Start the session only if it hasn't started already
}
?>

<div class="sidebar p-3">
    <div class="text-center mb-4">
        <h4>FoodSave</h4>
        <p class="text-muted small">NGO Panel</p>
    </div>
    
    <ul class="nav flex-column">
        <li class="nav-item">
            <a class="nav-link" href="dashboard.php">
                <i class="fas fa-tachometer-alt"></i> Dashboard
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="donations.php">
                <i class="fas fa-donate"></i> Donations
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="requests.php">
                <i class="fas fa-hand-holding-heart"></i> Requests
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="distribution.php">
                <i class="fas fa-people-carry"></i> Distributions
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="beneficiaries.php">
                <i class="fas fa-users"></i> Beneficiaries
            </a>
        </li>
        <li class="nav-item">
            <a class="nav-link" href="ngo_profile.php">
                <i class="fas fa-building"></i> NGO Profile
            </a>
        </li>
        <li class="nav-item mt-3">
            <a class="nav-link" href="../logout.php">
                <i class="fas fa-sign-out-alt"></i> Logout
            </a>
        </li>
    </ul>
</div>