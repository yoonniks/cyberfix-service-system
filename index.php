<?php
require_once 'config/db.php';

$servicesStmt = $pdo->query("
    SELECT s.service_name, s.description, s.base_price, s.estimated_hours, dt.device_kind, dt.service_category
    FROM Services s
    JOIN Device_Types dt ON s.type_id = dt.type_id
    WHERE s.is_visible_on_website = 1
    ORDER BY s.base_price ASC
    LIMIT 6
");
$services = $servicesStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix - Computer Maintenance & Technical Service</title>
    <link rel="stylesheet" href="assets/css/style.css?v=2">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <div class="site-header">
        <header>
            <a href="index.php" class="logo">
                <img src="assets/img/logo.png" alt="CyberFix Logo">
                CyberFix
            </a>
            <nav>
                <a href="index.php" class="active">Services & Prices</a>
                <a href="status.php">Check Status</a>
            </nav>
            <a href="tel:+380971234567" class="header-phone">
                <svg width="18" height="18" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2c.27-.27.67-.36 1.02-.24 1.12.37 2.33.57 3.57.57.55 0 1 .45 1 1V20c0 .55-.45 1-1 1-9.39 0-17-7.61-17-17 0-.55.45-1 1-1h3.5c.55 0 1 .45 1 1 0 1.25.2 2.45.57 3.57.11.35.03.74-.25 1.02l-2.2 2.2z"/>
                </svg>
                <span>+380 97 123 4567</span>
            </a>
        </header>
    </div>

    <div class="container">
        <section class="hero">
            <div class="hero-text">
                <h1>Computer Maintenance & Technical Service</h1>
                <p>We provide full support, regular diagnostics, and top-level repair for your laptops and workstation PCs.</p>
                <a href="status.php" class="btn-primary">Check Order Status</a>
            </div>
            <div class="hero-image">
                <img src="assets/img/hero.png" alt="CyberFix Hardware Repair">
            </div>
        </section>
    </div>

    <div class="banner-section">
        <img src="assets/img/premium.png?v=3" alt="Premium Service Standards">
    </div>

    <div class="container">
        <section id="services-list">
            <h2 class="section-title">Maintenance & Care Packages</h2>
            <p class="section-subtitle">Full Technical Maintenance Services</p>

            <div class="services-grid">
                <?php foreach ($services as $service): ?>
                    <div class="service-card">
                        <div>
                            <div class="meta"><?= htmlspecialchars($service['device_kind']) ?> (<?= htmlspecialchars($service['service_category']) ?>)</div>
                            <h3><?= htmlspecialchars($service['service_name']) ?></h3>
                            <p><?= htmlspecialchars($service['description']) ?></p>
                        </div>
                        <div>
                            <div class="price">$<?= number_format($service['base_price'], 2) ?></div>
                            <small style="opacity: 0.85;">Est. Time: <?= htmlspecialchars($service['estimated_hours']) ?> hrs</small>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </section>

        <section class="workflow-section">
            <h2 class="section-title">How We Maintain Your Hardware</h2>
            <p class="section-subtitle">Our 5-Step Maintenance Workflow</p>

            <div class="workflow-container">
                <div class="workflow-steps">
                    <div class="workflow-step">
                        <div class="step-number">1</div>
                        <div class="step-content">
                            <h4>Device Acceptance</h4>
                            <p>Complete visual check and status registration.</p>
                        </div>
                    </div>
                    <div class="workflow-step">
                        <div class="step-number">2</div>
                        <div class="step-content">
                            <h4>Full System Diagnostics</h4>
                            <p>Computerized testing of motherboard, CPU, and RAM.</p>
                        </div>
                    </div>
                    <div class="workflow-step">
                        <div class="step-number">3</div>
                        <div class="step-content">
                            <h4>Professional Maintenance</h4>
                            <p>Cleaning, component replacement, and software updates.</p>
                        </div>
                    </div>
                    <div class="workflow-step">
                        <div class="step-number">4</div>
                        <div class="step-content">
                            <h4>Stress Testing</h4>
                            <p>2-hour stress test under heavy work conditions.</p>
                        </div>
                    </div>
                    <div class="workflow-step">
                        <div class="step-number">5</div>
                        <div class="step-content">
                            <h4>Premium Delivery</h4>
                            <p>Return of clean, fully working device with report.</p>
                        </div>
                    </div>
                </div>
                <div class="workflow-image">
                    <img src="assets/img/workflow.jpg" alt="How We Maintain Your Hardware Workflow">
                </div>
            </div>
        </section>
    </div>

    <footer>
        <div class="container">
            <div class="footer-top">
                <div class="footer-logo-block">
                    <a href="index.php" class="footer-logo">
                        <img src="assets/img/logo_wh.png" alt="CyberFix Logo White">
                        <span>CyberFix</span>
                    </a>
                    <div class="social-links">
                        <a href="https://t.me" target="_blank" class="social-icon" title="Telegram">
                            <svg viewBox="0 0 24 24"><path d="M9.78 18.65l.28-4.2 7.63-6.88c.34-.31-.07-.46-.52-.18L7.7 13.43l-4.07-1.28c-.88-.28-.89-.88.19-1.3L19.74 4.6c.73-.27 1.38.17 1.14 1.29l-2.7 12.72c-.19.92-.75 1.14-1.5.71l-4.55-3.35-2.2 2.12c-.25.24-.46.46-.95.46z"/></svg>
                        </a>
                        <a href="https://instagram.com" target="_blank" class="social-icon" title="Instagram">
                            <svg viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                        </a>
                    </div>
                </div>
                <div class="footer-desc">
                    CyberFix is a premium computer maintenance service center. We provide professional diagnostics, regular care, and high-end hardware repair.
                </div>
            </div>

            <div class="footer-grid">
                <div class="footer-col">
                    <h4>Quick Links</h4>
                    <a href="index.php">Services & Prices</a>
                    <a href="status.php">Check Status</a>
                </div>
                <div class="footer-col">
                    <h4>Our Services</h4>
                    <a href="index.php#services-list">PC Cleaning & Dust Protection</a>
                    <a href="index.php#services-list">Thermal Paste & Liquid Metal Service</a>
                    <a href="index.php#services-list">Hardware Diagnostics & Tuning</a>
                    <a href="index.php#services-list">Corporate Office Maintenance</a>
                </div>
                <div class="footer-col">
                    <h4>Contact Center</h4>
                    <p>+380 97 123 4567</p>
                    <p>support@cyberfix.com</p>
                    <p>12 Main Street, Lviv, Ukraine</p>
                    <p style="margin-top: 15px; font-weight: 600; color: #ffffff;">Working Hours</p>
                    <p>Mon-Fri 09:00 - 20:00</p>
                    <p>Sat 10:00 - 18:00</p>
                </div>
            </div>

            <div class="footer-bottom">
                <div>&copy; 2026 CyberFix. All rights reserved.</div>
                <div class="footer-legal-links">
                    <a href="#">Terms of Service</a>
                    <a href="#">Privacy Policy</a>
                    <a href="#">Warranty Policy</a>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>