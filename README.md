# Food Delivery & Donation System

A modern web-based platform for food delivery, restaurant management, and food donation, supporting customers, restaurants, NGOs, and administrators. Built with PHP, MySQL, Bootstrap, and JavaScript.

---

## Table of Contents

- [Features](#features)
- [Project Structure](#project-structure)
- [Installation & Setup](#installation--setup)
- [Database](#database)
- [Usage Guide](#usage-guide)
- [File Uploads](#file-uploads)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)
- [Acknowledgments](#acknowledgments)

---

## Features

### Customer
- Register, login, and manage profile
- Browse restaurants and menus
- Add items to cart, checkout, and track orders
- Donate surplus food to NGOs
- Mark favorite restaurants
- View order and donation history

### Restaurant
- Register and manage restaurant profile
- Add, edit, and remove menu items
- Manage incoming orders and donations
- View analytics and order history

### NGO
- Register and manage NGO profile
- View and accept food donations from customers/restaurants
- Manage beneficiaries and distributions
- Track donation and distribution history
- Receive notifications

### Admin
- Manage users, restaurants, NGOs, and donations
- Approve or deactivate restaurants/NGOs
- View system analytics and reports

---

## Project Structure

```
food_delivery_system/
│
├── admin/           # Admin dashboard and management
├── customer/        # Customer dashboard, orders, donations, etc.
├── restaurant/      # Restaurant dashboard, menu, orders, etc.
├── ngo/             # NGO dashboard, donations, distributions, etc.
├── database/        # SQL schema and seed files
├── includes/        # Shared HTML includes (header, footer)
├── uploads/         # Uploaded images (profile, menu, etc.)
│   ├── menu_items/
│   ├── customer_profiles/
│   ├── ngo_profiles/
│   └── menu/
├── images/          # Static images for the site
├── styles.css       # Main CSS
├── index.php        # Landing page
├── db_config.php    # Database connection config
├── session_check.php# Session and authentication checks
├── README.md        # This file
└── ...              # Other HTML, PHP, and config files
```

---

## Installation & Setup

### Prerequisites

- PHP 8.0 or higher
- MySQL 5.7 or higher (MariaDB supported)
- Apache/Nginx web server
- Composer (optional, for future dependency management)

### Steps

1. **Clone the repository**
   ```bash
   git clone [repository-url]
   cd food_delivery_system
   ```

2. **Database Setup**
   - Create a database named `food_delivery_system`.
   - Import the schema:
     ```bash
     mysql -u root -p food_delivery_system < database/food_delivery_system.sql
     ```

3. **Configure Database Connection**
   - Edit `db_config.php` with your MySQL credentials:
     ```php
     $servername = "localhost";
     $username = "root";
     $password = "";
     $dbname = "food_delivery_system";
     ```

4. **Set File Permissions**
   - Ensure the `uploads/` directory and its subfolders are writable by the web server for image uploads.

5. **Web Server Configuration**
   - Point your web server root to the project directory.
   - Enable `mod_rewrite` if using Apache for clean URLs (optional).

---

## Database

The main schema is in `database/food_delivery_system.sql`. Key tables include:

- `users` — All users (customers, restaurants, NGOs, admins)
- `restaurants` — Restaurant profiles
- `menu_items` — Menu items for restaurants
- `orders`, `order_items` — Customer orders
- `ngo` — NGO profiles
- `donations`, `customer_food_donations` — Food donations
- `distributions` — NGO food distributions
- `beneficiaries` — NGO beneficiaries
- `notifications` — System notifications
- `cart`, `cart_items` — Shopping cart
- `reviews`, `rewards`, etc.

**See the SQL file for full details.**

---

## Usage Guide

### User Roles

- **Customer:** Register/login, browse restaurants, order food, donate surplus food, manage favorites.
- **Restaurant:** Register/login, manage menu, view and fulfill orders, accept donations.
- **NGO:** Register/login, manage profile, view/accept donations, distribute food, manage beneficiaries.
- **Admin:** Login, manage all users, restaurants, NGOs, donations, and view reports.

### Main Entry Points

- `index.php` — Landing page for all users
- `/customer/` — Customer dashboard and features
- `/restaurant/` — Restaurant dashboard and features
- `/ngo/` — NGO dashboard and features
- `/admin/` — Admin dashboard and management

### File Uploads

- Profile pictures and menu images are stored in `uploads/` subfolders.
- Only image files (JPG, PNG, GIF) are allowed for uploads.

---

## Security

- Passwords are hashed before storage.
- Session management and access control for all roles.
- Input validation and sanitization throughout.
- File upload validation.
- Protection against SQL injection and XSS.

---

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/YourFeature`)
3. Commit your changes (`git commit -m 'Add your feature'`)
4. Push to the branch (`git push origin feature/YourFeature`)
5. Open a Pull Request

---

## License

This project is licensed under the MIT License.

---

## Acknowledgments

- Bootstrap (UI framework)
- Font Awesome (icons)
- All contributors

---

**For questions or support, please open an issue or contact the maintainer.** 