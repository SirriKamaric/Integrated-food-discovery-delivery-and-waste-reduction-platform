<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FoodSave | Reduce Waste, Save Money</title>
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Custom CSS -->
    <link rel="stylesheet" href="css/main.css">
    <link rel="stylesheet" href="css/styles.css">
    <link rel="stylesheet" href="index.css">
    
    
        <style>
            :root {
                --primary: #2e7d32;
                --primary-dark: #1b5e20;
                --primary-light: #81c784;
                --white: #ffffff;
                --dark: #263238;
                --light: #f5f5f5;
            }
    
            /* Reset default margins and padding */
            body, html {
                margin: 0;
                padding: 0;
                width: 100%;
                overflow-x: hidden;
            }
    


           


            /* Hero Section - Full Viewport Coverage */
            .hero-section {
                position: relative;
                width: 100%;
                height: 100vh;
                overflow: hidden;
                
            }
    
            /* Background Slideshow */
            .hero-slideshow {
                position: absolute;
                width: 100%;
                height: 100%;
                z-index: 1;
                display: flex;
            }
    
            .hero-slide {
                min-width: 100%;
                height: 100%;
                background-size: cover;
                background-position: center;
                transition: transform 1.5s ease-in-out;
                position: relative;
            }
    
            .hero-slide::after {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.4); /* Darker overlay for better text visibility */
                z-index: 2;
            }
    
            /* Navbar Styling - Fixed and Solid */
            .navbar {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                z-index: 1000;
                background-color: white;
                padding: 0.75rem 0;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
    
            .navbar-brand {
                display: flex;
                align-items: center;
                padding: 0;
            }
    
            .navbar-brand img {
                height: 30px;
                width: auto;
                margin-right: 10px;
            }
    
            .navbar-dark .navbar-nav .nav-link {
                color: rgba(255,255,255,0.9);
                font-weight: 500;
                padding: 0.5rem 1rem;
            }
    
            .navbar-dark .navbar-nav .nav-link:hover {
                color: white;
            }
    
            .navbar-toggler {
                border: none;
                padding: 0.5rem;
            }
    
            /* Hero Content */
            .hero-content {
                position: relative;
                z-index: 3;
                height: 100%;
                display: flex;
                align-items: center;
                padding-top: 70px; /* Space for fixed navbar */
            }

            .hero-section {
                margin-top: 0px;
                padding-top: 0px;
            }
    
            .hero-text {
                max-width: 600px;
                text-shadow: 2px 2px 8px rgba(0,0,0,0.7);
            }
    
            .hero-title {
                font-size: 3.5rem;
                font-weight: 800;
                line-height: 1.2;
                margin-bottom: 1.5rem;
                color: #fff;
            }
    
            .hero-subtitle {
                font-size: 1.5rem;
                margin-bottom: 2rem;
                color: #fff;
                font-weight: 500;
            }
    
            .btn-hero {
                padding: 0.8rem 2rem;
                font-size: 1.1rem;
                font-weight: 600;
                border-radius: 50px;
                transition: all 0.3s ease;
            }
    
            .btn-hero-primary {
                background-color: var(--primary);
                border-color: var(--primary);
            }
    
            .btn-hero-primary:hover {
                background-color: var(--primary-dark);
                border-color: var(--primary-dark);
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
    
            .btn-hero-outline {
                border: 2px solid white;
                color: white;
                background-color: rgba(255,255,255,0.1);
            }
    
            .btn-hero-outline:hover {
                background-color: white;
                color: var(--dark);
                transform: translateY(-2px);
                box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            }
    
            /* Navigation Arrows */
            .slide-nav {
                position: absolute;
                top: 50%;
                transform: translateY(-50%);
                z-index: 4;
                width: 100%;
                display: flex;
                justify-content: space-between;
                padding: 0 2rem;
            }
    
            .slide-arrow {
                background-color: rgba(255,255,255,0.2);
                color: white;
                border: none;
                width: 50px;
                height: 50px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                cursor: pointer;
                transition: all 0.3s ease;
                font-size: 1.5rem;
            }
    
            .slide-arrow:hover {
                background-color: rgba(255,255,255,0.4);
                transform: scale(1.1);
            }
    
            /* Slide Indicators */
            .slide-indicators {
                position: absolute;
                bottom: 2rem;
                left: 50%;
                transform: translateX(-50%);
                z-index: 4;
                display: flex;
                gap: 0.5rem;
            }
    
            .slide-indicator {
                width: 12px;
                height: 12px;
                border-radius: 50%;
                background-color: rgba(255,255,255,0.5);
                cursor: pointer;
                transition: all 0.3s ease;
            }
    
            .slide-indicator.active {
                background-color: white;
                transform: scale(1.2);
            }
    
            /* Responsive Adjustments */
            @media (max-width: 992px) {
                .hero-title {
                    font-size: 2.8rem;
                }
                .hero-subtitle {
                    font-size: 1.3rem;
                }
                .slide-arrow {
                    width: 40px;
                    height: 40px;
                    font-size: 1.2rem;
                }
            }
    
            @media (max-width: 768px) {
                .hero-section {
                    height: 100vh;
                }
                .hero-title {
                    font-size: 2.2rem;
                }
                .hero-subtitle {
                    font-size: 1.1rem;
                }
                .btn-hero {
                    padding: 0.7rem 1.5rem;
                    font-size: 1rem;
                }
                .slide-arrow {
                    width: 35px;
                    height: 35px;
                    font-size: 1rem;
                }
                .hero-content {
                    padding-top: 60px;
                }
            }
    
            @media (max-width: 576px) {
                .hero-title {
                    font-size: 1.8rem;
                }
                .hero-subtitle {
                    font-size: 1rem;
                }
                .hero-buttons {
                    flex-direction: column;
                    gap: 1rem;
                }
                .btn-hero {
                    width: 100%;
                }
                .slide-nav {
                    padding: 0 1rem;
                }
                .navbar-brand img {
                    height: 25px;
                }
            }
        </style>
    </head>
    <body>
        <!-- Navbar - Fixed at Top -->
        <nav class="navbar navbar-expand-lg navbar-dark">
            <div class="container">
                <a class="navbar-brand fw-bold" href="/">
                    <img src="images/logo.jpg" alt="FoodSave"> FoodSave
                </a>
                <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                    <span class="navbar-toggler-icon"></span>
                </button>
                <div class="collapse navbar-collapse" id="navbarNav">
                    <ul class="navbar-nav me-auto">
                        <li class="nav-item">
                            <a class="nav-link" href="list.html">Restaurants</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="surplus.html">Surplus Deals</a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="donate.html">Donate Food</a>
                        </li>
                    </ul>
                    <div class="d-flex">
                        <a href="login.php" class="btn btn-outline-light me-2">Login</a>
                        <a href="register.php" class="btn btn-light text-success">Sign Up</a>
                    </div>
                </div>
            </div>
        </nav>
    
        <!-- Hero Section - Full Viewport -->
        <section class="hero-section">
            <!-- Background Slideshow -->
            <div class="hero-slideshow" id="heroSlideshow">
                <div class="hero-slide" style="background-image: url('images/ndole.jpg');"></div>
                <div class="hero-slide" style="background-image: url('images/achu.jpg');"></div>
                <div class="hero-slide" style="background-image: url('images/eru.jpg');"></div>
                <div class="hero-slide" style="background-image: url('images/plantain.jpg');"></div>
                <div class="hero-slide" style="background-image: url('images/snales.jpg');"></div>
            </div>
            
            <!-- Navigation Arrows -->
            <div class="slide-nav">
                <button class="slide-arrow" id="prevSlide"><i class="fas fa-chevron-left"></i></button>
                <button class="slide-arrow" id="nextSlide"><i class="fas fa-chevron-right"></i></button>
            </div>
            
            <!-- Slide Indicators -->
            <div class="slide-indicators" id="slideIndicators">
                <div class="slide-indicator active"></div>
                <div class="slide-indicator"></div>
                <div class="slide-indicator"></div>
                <div class="slide-indicator"></div>
            </div>
            
            <!-- Hero Content -->
            <div class="container hero-content">
                <div class="hero-text">
                    <h1 class="hero-title">Reduce Food Waste, Save Money</h1>
                    <p class="hero-subtitle">Discover amazing meals from local restaurants at discounted prices while helping reduce food waste in your community.</p>
                    <div class="d-flex gap-3 hero-buttons">
                        <a href="register.php" class="btn btn-hero btn-hero-primary">Get Started</a>
                        <a href="list.html" class="btn btn-hero btn-hero-outline">Browse Restaurants</a>
                    </div>
                </div>
            </div>
        </section>

            <!-- How It Works Section -->
            <section class="py-5 bg-light">
                <div class="container py-5">
                    <div class="text-center mb-5">
                        <h2 class="fw-bold">How It Works</h2>
                        <p class="lead text-muted">Join the movement against food waste in 3 simple steps</p>
                    </div>
                    <div class="row g-4">
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center p-4">
                                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="fas fa-search fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold">1. Discover</h5>
                                    <p class="text-muted">Browse restaurants and surplus deals near you</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center p-4">
                                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="fas fa-utensils fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold">2. Order</h5>
                                    <p class="text-muted">Get delicious meals at discounted prices</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card border-0 shadow-sm h-100">
                                <div class="card-body text-center p-4">
                                    <div class="bg-success bg-opacity-10 text-success rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width: 80px; height: 80px;">
                                        <i class="fas fa-leaf fa-2x"></i>
                                    </div>
                                    <h5 class="fw-bold">3. Make an Impact</h5>
                                    <p class="text-muted">Earn rewards while reducing food waste</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Featured Restaurants -->
            <section class="py-5">
                <div class="container py-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="fw-bold mb-0">Featured Restaurants</h2>
                        <a href="list.html" class="btn btn-outline-success">View All</a>
                    </div>
                    <div class="row g-4">
                        <!-- Restaurant Card 1 -->
                        <div class="col-md-6 col-lg-3">
                            <div class="card restaurant-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p11.jpg" class="card-img-top" alt="Restaurant">
                                    <span class="badge bg-success position-absolute top-0 end-0 m-2">20% OFF</span>
                                </div>
                                <div class="card-body">
                                    <div class="d-flex justify-content-between align-items-start mb-2">
                                        <h5 class="card-title fw-bold mb-0">Green Bites</h5>
                                        <span class="badge bg-success bg-opacity-10 text-success">4.8 ★</span>
                                    </div>
                                    <p class="text-muted small mb-2">Healthy • Vegetarian • $</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <span class="text-success fw-bold">3 surplus items</span>
                                        <a href="menu.html" class="btn btn-sm btn-outline-success">View Menu</a>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Repeat for 3 more restaurants -->
                    </div>
                </div>
            </section>

            <!-- Surplus Deals Section -->
            <section class="py-5 bg-success bg-opacity-10">
                <div class="container py-5">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <h2 class="fw-bold mb-0">Today's Surplus Deals</h2>
                        <a href="surplus.html" class="btn btn-success">View All Deals</a>
                    </div>
                    <div class="row g-4">
                        <!-- Food Item 1 -->
                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p2.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p10.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p9.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p7.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p6.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p5.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p4.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p2.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="col-md-6 col-lg-3">
                            <div class="card food-card h-100 border-0 shadow-sm overflow-hidden">
                                <div class="position-relative">
                                    <img src="images/p1.jpg" class="card-img-top" alt="Food">
                                    <span class="badge bg-danger position-absolute top-0 end-0 m-2">40% OFF</span>
                                </div>
                                <div class="card-body">
                                    <h5 class="card-title fw-bold">Avocado Toast</h5>
                                    <p class="text-muted small mb-2">Green Bites • Expires in 2 hours</p>
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <span class="text-decoration-line-through text-muted me-2">$8.99</span>
                                            <span class="fw-bold text-success">$5.39</span>
                                        </div>
                                        <button class="btn btn-sm btn-success">Add to Cart</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <!-- Repeat for 3 more food items -->
                    </div>
                </div>
            </section>


    <!-- Footer -->
    <footer class="bg-dark text-white pt-5 pb-4">
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
                        <li class="mb-2"><a href="index.html" class="text-white">Home</a></li>
                        <li class="mb-2"><a href="About.html" class="text-white">About</a></li>
                        <li class="mb-2"><a href="list.html" class="text-white">Restaurants</a></li>
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

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const slideshow = document.getElementById('heroSlideshow');
            const prevBtn = document.getElementById('prevSlide');
            const nextBtn = document.getElementById('nextSlide');
            const indicators = document.querySelectorAll('.slide-indicator');
            const navbar = document.getElementById('mainNavbar');
            let currentSlide = 0;
            const totalSlides = 4;
            let slideInterval;
            
            // Initialize slideshow
            function startSlideShow() {
                slideInterval = setInterval(() => {
                    goToSlide((currentSlide + 1) % totalSlides);
                }, 4000);
            }
            
            // Go to specific slide
            function goToSlide(n) {
                currentSlide = (n + totalSlides) % totalSlides;
                slideshow.style.transform = `translateX(-${currentSlide * 100}%)`;
                updateIndicators();
            }
            
            // Update active indicator
            function updateIndicators() {
                indicators.forEach((indicator, index) => {
                    indicator.classList.toggle('active', index === currentSlide);
                });
            }
            
            // Event listeners for navigation
            prevBtn.addEventListener('click', () => {
                clearInterval(slideInterval);
                goToSlide(currentSlide - 1);
                startSlideShow();
            });
            
            nextBtn.addEventListener('click', () => {
                clearInterval(slideInterval);
                goToSlide(currentSlide + 1);
                startSlideShow();
            });
            
            // Click on indicators
            indicators.forEach((indicator, index) => {
                indicator.addEventListener('click', () => {
                    clearInterval(slideInterval);
                    goToSlide(index);
                    startSlideShow();
                });
            });
            
            // Navbar scroll effect
            window.addEventListener('scroll', () => {
                if (window.scrollY > 50) {
                    navbar.classList.add('scrolled');
                } else {
                    navbar.classList.remove('scrolled');
                }
            });
            
            // Pause on hover
            slideshow.addEventListener('mouseenter', () => {
                clearInterval(slideInterval);
            });
            
            slideshow.addEventListener('mouseleave', startSlideShow);
            
            // Start the slideshow
            startSlideShow();
        });
    </script>
</body>
</html>