<?php
session_start();
require_once 'config/db.php';

$error = '';

if (isset($_SESSION['admin_id'])) {
    $role = strtolower(trim($_SESSION['admin_role'] ?? ''));
    if ($role === 'accountant') {
        header('Location: admin/reports.php');
    } else {
        header('Location: admin/index.php');
    }
    exit;
}

if (isset($_SESSION['admin_id'])) {
    header('Location: admin/index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = trim($_POST['password']);

    $stmt = $pdo->prepare("
        SELECT 
            a.admin_id,
            a.employee_id,
            a.password,
            a.role,
            CONCAT(e.first_name, ' ', e.last_name) AS full_name,
            e.work_status
        FROM Admins a
        JOIN Employees e ON a.employee_id = e.employee_id
        WHERE a.username = :user
    ");
    $stmt->execute(['user' => $username]);
    $admin = $stmt->fetch();

    if ($admin && password_verify($password, $admin['password'])) {
        if ($admin['work_status'] !== 'Active') {
            $error = 'Your employee account is not active.';
        } else {
            $_SESSION['admin_id'] = $admin['admin_id'];
            $_SESSION['employee_id'] = $admin['employee_id'];
            $_SESSION['admin_name'] = $admin['full_name'];
            $_SESSION['admin_role'] = $admin['role'];
            header('Location: admin/index.php');
            exit;
        }
    } else {
        $error = 'Wrong login or password!';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix - Staff Login</title>
    <link rel="stylesheet" href="assets/css/style_admin.css?v=2">
    <link rel="icon" type="image/png" href="assets/img/logo.png">
</head>
<body>
    <div class="container" style="display: flex; justify-content: center; align-items: center; min-height: 95vh;">
        <div style="background: #ffffff; border: 1px solid #e3deff; border-radius: 14px; width: 100%; max-width: 400px; padding: 40px 30px;">
            <div style="text-align: center; margin-bottom: 25px;">
                <img src="assets/img/logo.png" alt="CyberFix" style="height: 45px; margin-bottom: 10px;">
                <h2 style="color: #705DFB; text-align: center; margin-bottom: 6px;">CyberFix Control</h2>
                <p style="color: #837a9c; font-size: 13.5px;">Login for staff and admins</p>
            </div>

            <?php if ($error): ?>
                <div class="alert-message" style="background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; text-align: center; font-size: 13px; padding: 8px; margin-bottom: 15px;">
                    <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>

            <form action="login.php" method="POST" style="display: flex; flex-direction: column; gap: 15px;">
                <div class="form-group" style="text-align: left;">
                    <label>Login</label>
                    <input type="text" name="username" required placeholder="admin">
                </div>
                <div class="form-group" style="text-align: left;">
                    <label>Password</label>
                    <input type="password" name="password" required placeholder="••••••••">
                </div>
                <button type="submit" class="btn-primary" style="width: 100%; margin-top: 10px;">Sign In</button>
            </form>
        </div>
    </div>
</body>
</html>