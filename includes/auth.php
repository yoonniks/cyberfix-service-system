<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function getRoleLabel(string $role): string {
    $r = strtolower(trim($role));
    if ($r === 'superadmin') {
        return 'Superadmin';
    }
    if ($r === 'operator') {
        return 'Operator';
    }
    if ($r === 'engineer') {
        return 'Engineer';
    }
    if ($r === 'accountant') {
        return 'Accountant';
    }
    return ucfirst($role);
}

function checkAccess(array $allowedRoles): void {
    if (!isset($_SESSION['admin_id'])) {
        header('Location: ../login.php');
        exit;
    }

    $currentRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
    $normalizedAllowed = array_map('strtolower', $allowedRoles);

    if (!in_array($currentRole, $normalizedAllowed, true)) {
        if ($currentRole === 'accountant') {
            header('Location: reports.php');
        } else {
            header('Location: index.php?msg=no_access');
        }
        exit;
    }
}