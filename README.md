# CyberFix — Computer Service & Maintenance Management System

**CyberFix** is a web-based information system for a computer repair and technical maintenance center. It helps manage clients, hardware devices, diagnostics, service orders, staff, and financial reports.

## Main Features

### Client Part (Public)
- **Home Page (`index.php`):** Shows information about the service center, workflow steps, and a catalog of public services from the database.
- **Order Status Tracking (`status.php`):** Clients can enter their Order ID to check the real-time repair status without logging in.

### Admin Panel (Role-Based Access)
- **User Roles:** Three access levels (`Superadmin`, `Operator`, `Engineer`) with secure session login and `Bcrypt` password hashing.
- **Orders Management (`admin/index.php`):** Create new orders, edit statuses, assign engineers, manage payment status (`Paid` / `Unpaid`), and use instant live search and filters.
- **Diagnostics & Work Log (`admin/order_details.php`):** Record hardware issues, add repair services, and automatically calculate total hours and cost.
- **Directories:** Full CRUD management for **Clients** (`clients.php`), **Devices** (`devices.php`), **Services** (`services.php`), and **Staff** (`employees.php`).
- **Printable Receipts (`admin/print_receipt.php`):** Clean print-ready receipt for clients.
- **Analytics & Reports (`admin/reports.php`):** Financial statistics, engineer workload, top services, and interactive charts powered by **Chart.js**.

## Technologies Used
- **Backend:** PHP 8+ (PDO with Prepared Statements)
- **Database:** MySQL / MariaDB (10 normalized tables in 3NF)
- **Frontend:** HTML5, CSS3, Vanilla JavaScript
- **Charts:** Chart.js
- **Local Server:** XAMPP (Apache + MySQL)

## Project Structure
```text
cyberfix/
├── index.php               # Public home page
├── status.php              # Public order status tracker
├── login.php               # Staff login page
├── config/
│   ├── db.example.php      # Database configuration template
│   └── db.php              # Local DB credentials (ignored by Git)
├── includes/
│   ├── auth.php            # Authentication & role checks
│   ├── admin_header.php    # Shared admin navigation
│   └── helpers.php         # Flash messages & pagination
├── admin/
│   ├── index.php           # Orders dashboard
│   ├── order_details.php   # Diagnostics & applied services
│   ├── clients.php         # Clients directory
│   ├── devices.php         # Hardware devices registry
│   ├── services.php        # Service catalog
│   ├── employees.php       # Staff & admin accounts
│   ├── reports.php         # Analytics & charts
│   └── print_receipt.php   # Printable order receipt
├── assets/
│   ├── css/                # Client and admin stylesheets
│   └── img/                # Logos and UI images
└── database/
    └── cyberfix_db.sql     # Database structure and sample data 
```
### How to Run Locally (XAMPP)

1. Clone or download this repository into your XAMPP htdocs folder:
   C:\xampp\htdocs\cyberfix\

2. Start Apache and MySQL in the XAMPP Control Panel.

3. Create the database:
   - Open http://localhost/phpmyadmin in your browser.
   - Create a new database named cyberfix_db with utf8mb4_unicode_ci collation.
   - Import the SQL dump file (database/cyberfix_db.sql).

4. Configure database connection:
   - Copy config/db.example.php and rename it to config/db.php.
   - Open config/db.php and enter your MySQL username and password (for default XAMPP: user 'root', password '').

5. Open the application:
   - Public website: http://localhost/cyberfix/index.php
   - Admin login: http://localhost/cyberfix/login.php
