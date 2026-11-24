<?php
$currentPage = basename($_SERVER['PHP_SELF']);
$userRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
$roleLabel = getRoleLabel($userRole);
$logoHref = ($userRole === 'accountant') ? 'reports.php' : 'index.php';
?>
<header class="admin-navbar">
    <a href="<?= $logoHref ?>" class="logo">
        <img src="../assets/img/logo.png" alt="CyberFix Logo">
        CyberFix Admin
    </a>
    <nav class="admin-nav-links">
        <?php if (in_array($userRole, ['superadmin', 'operator', 'engineer'], true)): ?>
            <a href="index.php" class="<?= $currentPage === 'index.php' ? 'active' : '' ?>">Active Orders</a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['superadmin', 'operator'], true)): ?>
            <a href="clients.php" class="<?= $currentPage === 'clients.php' ? 'active' : '' ?>">Clients</a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['superadmin', 'operator', 'engineer'], true)): ?>
            <a href="devices.php" class="<?= $currentPage === 'devices.php' ? 'active' : '' ?>">Devices</a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['superadmin', 'operator'], true)): ?>
            <a href="services.php" class="<?= $currentPage === 'services.php' ? 'active' : '' ?>">Services</a>
        <?php endif; ?>

        <?php if ($userRole === 'superadmin'): ?>
            <a href="employees.php" class="<?= $currentPage === 'employees.php' ? 'active' : '' ?>">Staff</a>
        <?php endif; ?>

        <?php if (in_array($userRole, ['superadmin', 'accountant'], true)): ?>
            <a href="reports.php" class="<?= $currentPage === 'reports.php' ? 'active' : '' ?>">Reports</a>
        <?php endif; ?>
    </nav>
    <div style="display: flex; align-items: center; gap: 12px;">
        <span class="admin-badge-user">
            <?= htmlspecialchars($_SESSION['admin_name'] ?? 'User') ?> (<?= htmlspecialchars($roleLabel) ?>)
        </span>
        <a href="index.php?action=logout" class="btn-danger btn-sm">Logout</a>
    </div>
</header>