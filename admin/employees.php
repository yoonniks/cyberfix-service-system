<?php
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../config/db.php';

checkAccess(['Superadmin']);

function isStrongPassword(string $password): bool {
    if (strlen($password) < 8) {
        return false;
    }
    if (!preg_match('/[A-Z]/', $password)) {
        return false;
    }
    if (!preg_match('/[a-z]/', $password)) {
        return false;
    }
    if (!preg_match('/[0-9]/', $password)) {
        return false;
    }
    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return false;
    }
    return true;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_employee'])) {
    $empId = (int)$_POST['employee_id'];
    if ($empId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Employees WHERE employee_id = :id");
            $stmt->execute(['id' => $empId]);
            setFlash('success', 'Employee deleted successfully!');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete employee: linked to existing orders or device issues!');
        }
    }
    header('Location: employees.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_employee'])) {
    $firstName  = trim($_POST['first_name']);
    $lastName   = trim($_POST['last_name']);
    $middleName = trim($_POST['middle_name']);
    $staffType  = $_POST['staff_type'];
    $position   = trim($_POST['position']);
    $salary     = (float)($_POST['salary'] ?? 0);
    $phone      = trim($_POST['phone_number']);
    $workStatus = $_POST['work_status'];
    $birthDate  = $_POST['birth_date'];
    $hireDate   = $_POST['hire_date'];

    $username   = trim($_POST['username'] ?? '');
    $password   = trim($_POST['password'] ?? '');
    $systemRole = $_POST['system_role'] ?? 'Operator';

    if ($username !== '' && $password !== '' && !isStrongPassword($password)) {
        setFlash('error', 'Password is too weak! Use at least 8 characters, uppercase & lowercase letters, a number, and a special symbol.');
        header('Location: employees.php');
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            INSERT INTO Employees (first_name, last_name, middle_name, staff_type, position, salary, phone_number, work_status, birth_date, hire_date)
            VALUES (:fn, :ln, :mn, :st, :pos, :sal, :ph, :ws, :bd, :hd)
        ");
        $stmt->execute([
            'fn' => $firstName, 'ln' => $lastName, 'mn' => $middleName,
            'st' => $staffType, 'pos' => $position, 'sal' => $salary, 'ph' => $phone,
            'ws' => $workStatus, 'bd' => $birthDate, 'hd' => $hireDate
        ]);
        $newEmpId = (int)$pdo->lastInsertId();

        if ($username !== '' && $password !== '') {
            $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
            $admStmt = $pdo->prepare("
                INSERT INTO Admins (employee_id, username, password, role, created_at)
                VALUES (:eid, :user, :pass, :role, NOW())
            ");
            $admStmt->execute([
                'eid'  => $newEmpId,
                'user' => $username,
                'pass' => $hashedPassword,
                'role' => $systemRole
            ]);
        }

        $pdo->commit();
        setFlash('success', "Employee '{$firstName} {$lastName}' added successfully!");
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'Error adding employee: login username might already exist.');
    }
    header('Location: employees.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_employee'])) {
    $empId      = (int)$_POST['employee_id'];
    $firstName  = trim($_POST['first_name']);
    $lastName   = trim($_POST['last_name']);
    $middleName = trim($_POST['middle_name']);
    $staffType  = $_POST['staff_type'];
    $position   = trim($_POST['position']);
    $salary     = (float)($_POST['salary'] ?? 0);
    $phone      = trim($_POST['phone_number']);
    $workStatus = $_POST['work_status'];

    $username      = trim($_POST['username'] ?? '');
    $newPassword   = trim($_POST['password'] ?? '');
    $systemRole    = $_POST['system_role'] ?? 'Operator';
    $removeAccount = isset($_POST['remove_account']);

    if (!$removeAccount && $username !== '' && $newPassword !== '' && !isStrongPassword($newPassword)) {
        setFlash('error', 'New password is too weak! Use at least 8 characters, uppercase & lowercase letters, a number, and a special symbol.');
        header('Location: employees.php?edit_id=' . $empId);
        exit;
    }

    try {
        $pdo->beginTransaction();

        $stmt = $pdo->prepare("
            UPDATE Employees
            SET first_name = :fn, last_name = :ln, middle_name = :mn,
                staff_type = :st, position = :pos, salary = :sal, phone_number = :ph, work_status = :ws
            WHERE employee_id = :id
        ");
        $stmt->execute([
            'fn' => $firstName, 'ln' => $lastName, 'mn' => $middleName,
            'st' => $staffType, 'pos' => $position, 'sal' => $salary, 'ph' => $phone,
            'ws' => $workStatus, 'id' => $empId
        ]);

        $checkAdm = $pdo->prepare("SELECT admin_id FROM Admins WHERE employee_id = :eid");
        $checkAdm->execute(['eid' => $empId]);
        $existingAdm = $checkAdm->fetch();

        if ($removeAccount && $existingAdm) {
            $delAdm = $pdo->prepare("DELETE FROM Admins WHERE employee_id = :eid");
            $delAdm->execute(['eid' => $empId]);
        } elseif ($username !== '') {
            if ($existingAdm) {
                if ($newPassword !== '') {
                    $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                    $updAdm = $pdo->prepare("UPDATE Admins SET username = :user, password = :pass, role = :role WHERE employee_id = :eid");
                    $updAdm->execute(['user' => $username, 'pass' => $hashedPassword, 'role' => $systemRole, 'eid' => $empId]);
                } else {
                    $updAdm = $pdo->prepare("UPDATE Admins SET username = :user, role = :role WHERE employee_id = :eid");
                    $updAdm->execute(['user' => $username, 'role' => $systemRole, 'eid' => $empId]);
                }
            } elseif ($newPassword !== '') {
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                $insAdm = $pdo->prepare("INSERT INTO Admins (employee_id, username, password, role, created_at) VALUES (:eid, :user, :pass, :role, NOW())");
                $insAdm->execute(['eid' => $empId, 'user' => $username, 'pass' => $hashedPassword, 'role' => $systemRole]);
            }
        }

        $pdo->commit();
        setFlash('success', "Employee #{$empId} updated successfully!");
    } catch (PDOException $e) {
        $pdo->rollBack();
        setFlash('error', 'Error updating employee: login username might already be taken.');
    }
    header('Location: employees.php');
    exit;
}

// GET-фільтри та пошук
$searchQ      = trim($_GET['q'] ?? '');
$filterStaff  = trim($_GET['staff_type'] ?? '');
$filterStatus = trim($_GET['work_status'] ?? '');

$whereClauses = [];
$params = [];

if ($searchQ !== '') {
    $whereClauses[] = "(CONCAT(e.first_name, ' ', e.last_name) LIKE :q_name OR e.position LIKE :q_pos OR e.phone_number LIKE :q_phone OR a.username LIKE :q_user)";
    $params['q_name']  = '%' . $searchQ . '%';
    $params['q_pos']   = '%' . $searchQ . '%';
    $params['q_phone'] = '%' . $searchQ . '%';
    $params['q_user']  = '%' . $searchQ . '%';
}

if (in_array($filterStaff, ['Engineer', 'Operator', 'Admin', 'Support', 'Accountant'], true)) {
    $whereClauses[] = "e.staff_type = :stype";
    $params['stype'] = $filterStaff;
}

if (in_array($filterStatus, ['Active', 'On_Leave', 'Sick_Leave'], true)) {
    $whereClauses[] = "e.work_status = :wstatus";
    $params['wstatus'] = $filterStatus;
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Пагінація (по 5 записів)
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Employees e
    LEFT JOIN Admins a ON e.employee_id = a.employee_id
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$empStmt = $pdo->prepare("
    SELECT e.*, a.username, a.role AS system_role
    FROM Employees e
    LEFT JOIN Admins a ON e.employee_id = a.employee_id
    $whereSql
    ORDER BY e.employee_id ASC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $empStmt->bindValue(':' . $key, $val);
}
$empStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$empStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$empStmt->execute();
$employees = $empStmt->fetchAll();

// Дані для модального вікна Details
$viewEmp = null;
if (isset($_GET['view_id'])) {
    $viewId = (int)$_GET['view_id'];
    $stmt = $pdo->prepare("
        SELECT e.*, a.username, a.role AS system_role, a.created_at AS account_created
        FROM Employees e
        LEFT JOIN Admins a ON e.employee_id = a.employee_id
        WHERE e.employee_id = :id
    ");
    $stmt->execute(['id' => $viewId]);
    $viewEmp = $stmt->fetch();
}

// Дані для модального вікна Edit
$editEmp = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("
        SELECT e.*, a.username, a.role AS system_role
        FROM Employees e
        LEFT JOIN Admins a ON e.employee_id = a.employee_id
        WHERE e.employee_id = :id
    ");
    $stmt->execute(['id' => $editId]);
    $editEmp = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix Admin - Staff</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=3">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body>
<div class="container min-page-height">
    <?php include '../includes/admin_header.php'; ?>

    <h1 class="page-title">Staff Directory</h1>
    <p class="page-subtitle">Manage employees, positions, work statuses, and system login accounts</p>

    <?php renderFlash(); ?>

    <!-- 1. БЛОК ФІЛЬТРІВ -->
    <div class="admin-section-box">
        <h3>Filter Staff</h3>
        <form action="employees.php" method="GET" id="filterForm">
            <input type="hidden" name="q" id="filterHiddenQ" value="<?= htmlspecialchars($searchQ) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Staff Category</label>
                    <select name="staff_type" id="filterStaffType">
                        <option value="">-- All Categories --</option>
                        <option value="Engineer" <?= $filterStaff === 'Engineer' ? 'selected' : '' ?>>Engineer</option>
                        <option value="Operator" <?= $filterStaff === 'Operator' ? 'selected' : '' ?>>Operator</option>
                        <option value="Accountant" <?= $filterStaff === 'Accountant' ? 'selected' : '' ?>>Accountant</option>
                        <option value="Admin" <?= $filterStaff === 'Admin' ? 'selected' : '' ?>>Admin</option>
                        <option value="Support" <?= $filterStaff === 'Support' ? 'selected' : '' ?>>Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Work Status</label>
                    <select name="work_status" id="filterWorkStatus">
                        <option value="">-- All Statuses --</option>
                        <option value="Active" <?= $filterStatus === 'Active' ? 'selected' : '' ?>>Active</option>
                        <option value="On_Leave" <?= $filterStatus === 'On_Leave' ? 'selected' : '' ?>>On Leave</option>
                        <option value="Sick_Leave" <?= $filterStatus === 'Sick_Leave' ? 'selected' : '' ?>>Sick Leave</option>
                    </select>
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn-primary">Apply Filters</button>
                <a href="employees.php" class="btn-secondary">Reset All</a>
            </div>
        </form>
    </div>

    <!-- 2. ОКРЕМИЙ БЛОК ПОШУКУ (миттєвий AJAX-пошук без перезавантаження) -->
    <div class="admin-section-box">
        <h3>Instant Staff Search</h3>
        <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 260px; margin-bottom: 0;">
                <label>Search by Name, Position, Phone or Login</label>
                <input type="text" id="liveSearchInput" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Start typing to filter results instantly..." autocomplete="off">
            </div>
            <button type="button" id="clearSearchBtn" class="btn-secondary" style="<?= $searchQ === '' ? 'display: none;' : '' ?>">Clear Search</button>
        </div>
    </div>

    <!-- 3. БЛОК РЕЗУЛЬТАТІВ (Оновлюється динамічно через AJAX) -->
    <div class="admin-section-box" id="staffResultsContainer">
        <h3>All Employees (Found: <?= $totalRows ?>)</h3>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Full Name</th>
                        <th>Category</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($employees) > 0): ?>
                        <?php foreach ($employees as $emp): ?>
                            <tr>
                                <td><strong>#<?= $emp['employee_id'] ?></strong></td>
                                <td><?= htmlspecialchars($emp['first_name'] . ' ' . $emp['last_name']) ?></td>
                                <td><?= htmlspecialchars($emp['staff_type']) ?></td>
                                <td><?= htmlspecialchars($emp['position']) ?></td>
                                <td>
                                    <span class="status-badge <?= $emp['work_status'] === 'Active' ? 'status-Ready' : 'status-Accepted' ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $emp['work_status'])) ?>
                                    </span>
                                </td>
                                <td>
                                    <div style="display: flex; gap: 6px;">
                                        <a href="employees.php?view_id=<?= $emp['employee_id'] ?>&page=<?= $page ?>&q=<?= urlencode($searchQ) ?>&staff_type=<?= urlencode($filterStaff) ?>&work_status=<?= urlencode($filterStatus) ?>" class="btn-secondary btn-sm">Details</a>
                                        <a href="employees.php?edit_id=<?= $emp['employee_id'] ?>" class="btn-primary btn-sm">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6" style="text-align: center; color: #837a9c;">No employees match your criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php renderPagination($page, $totalPages, $_GET); ?>
    </div>

    <!-- 4. ФОРМА ДОДАВАННЯ НОВОГО ПРАЦІВНИКА -->
    <div class="admin-section-box">
        <h3>Add New Employee</h3>
        <form action="employees.php" method="POST" id="addEmployeeForm">
            <div class="form-grid">
                <div class="form-group">
                    <label>First Name</label>
                    <input type="text" name="first_name" required placeholder="e.g. Ivan">
                </div>
                <div class="form-group">
                    <label>Last Name</label>
                    <input type="text" name="last_name" required placeholder="e.g. Bondar">
                </div>
                <div class="form-group">
                    <label>Middle Name</label>
                    <input type="text" name="middle_name" placeholder="e.g. Petrovych">
                </div>
                <div class="form-group">
                    <label>Staff Category</label>
                    <select name="staff_type" required>
                        <option value="Engineer">Engineer</option>
                        <option value="Operator">Operator</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Admin">Admin</option>
                        <option value="Support">Support</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Position</label>
                    <input type="text" name="position" required placeholder="e.g. Hardware Technician">
                </div>
                <div class="form-group">
                    <label>Monthly Salary ($)</label>
                    <input type="number" step="0.01" min="0" name="salary" required placeholder="e.g. 1200.00">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="phone_number" required placeholder="e.g. +380971234567">
                </div>
                <div class="form-group">
                    <label>Work Status</label>
                    <select name="work_status">
                        <option value="Active">Active</option>
                        <option value="On_Leave">On Leave</option>
                        <option value="Sick_Leave">Sick Leave</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Birth Date</label>
                    <input type="date" name="birth_date" required>
                </div>
                <div class="form-group">
                    <label>Hire Date</label>
                    <input type="date" name="hire_date" required>
                </div>
            </div>

            <hr style="border: 0; border-top: 1px solid #e3deff; margin: 22px 0;">
            <h4 style="color: #705DFB; margin-bottom: 12px;">System Login Access (Optional — leave empty for Support staff)</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Login Username</label>
                    <input type="text" name="username" id="add_username" placeholder="e.g. ivan.eng">
                </div>
                <div class="form-group">
                    <label>Strong Password</label>
                    <input type="password" name="password" id="add_password" placeholder="Min 8 chars, A-Z, a-z, 0-9, !@#">
                    <small id="add_pass_hint" style="display: block; margin-top: 5px; font-size: 12px; color: #837a9c;">
                        Must contain: 8+ chars, uppercase, lowercase, digit, special symbol.
                    </small>
                </div>
                <div class="form-group">
                    <label>System Access Role</label>
                    <select name="system_role">
                        <option value="Engineer">Engineer</option>
                        <option value="Operator">Operator</option>
                        <option value="Accountant">Accountant</option>
                        <option value="Superadmin">Superadmin</option>
                    </select>
                </div>
            </div>

            <button type="submit" name="add_employee" class="btn-primary mt-20">Add Employee</button>
        </form>
    </div>
</div>

<!-- МОДАЛЬНЕ ВІКНО DETAILS -->
<?php if ($viewEmp): ?>
    <div class="modal-overlay active">
        <div class="modal-content" style="max-width: 560px;">
            <div class="modal-header">
                <h3>Employee Details #<?= $viewEmp['employee_id'] ?></h3>
                <a href="employees.php?page=<?= $page ?>&q=<?= urlencode($searchQ) ?>&staff_type=<?= urlencode($filterStaff) ?>&work_status=<?= urlencode($filterStatus) ?>" class="close-btn">&times;</a>
            </div>
            <div style="padding: 0 28px 10px 28px; overflow-y: auto;">
                <div class="form-grid" style="gap: 14px;">
                    <div>
                        <small style="color: #837a9c; display: block;">Full Name</small>
                        <strong><?= htmlspecialchars(trim($viewEmp['last_name'] . ' ' . $viewEmp['first_name'] . ' ' . ($viewEmp['middle_name'] ?? ''))) ?></strong>
                    </div>
                    <div>
                        <small style="color: #837a9c; display: block;">Staff Category & Position</small>
                        <strong><?= htmlspecialchars($viewEmp['staff_type']) ?></strong> — <?= htmlspecialchars($viewEmp['position']) ?>
                    </div>
                    <div>
                        <small style="color: #837a9c; display: block;">Monthly Salary</small>
                        <strong style="color: #28a745; font-size: 16px;">$<?= number_format((float)($viewEmp['salary'] ?? 0), 2) ?></strong>
                    </div>
                    <div>
                        <small style="color: #837a9c; display: block;">Work Status</small>
                        <span class="status-badge <?= $viewEmp['work_status'] === 'Active' ? 'status-Ready' : 'status-Accepted' ?>">
                            <?= htmlspecialchars(str_replace('_', ' ', $viewEmp['work_status'])) ?>
                        </span>
                    </div>
                    <div>
                        <small style="color: #837a9c; display: block;">Phone Number</small>
                        <strong><?= htmlspecialchars($viewEmp['phone_number']) ?></strong>
                    </div>
                    <div>
                        <small style="color: #837a9c; display: block;">Birth Date</small>
                        <strong><?= htmlspecialchars($viewEmp['birth_date']) ?></strong>
                    </div>
                    <div>
                        <small style="color: #837a9c; display: block;">Hire Date</small>
                        <strong><?= htmlspecialchars($viewEmp['hire_date']) ?></strong>
                    </div>
                    <div>
                        <small style="color: #837a9c; display: block;">System Login & Role</small>
                        <?php if ($viewEmp['username']): ?>
                            <code><?= htmlspecialchars($viewEmp['username']) ?></code> (<?= htmlspecialchars($viewEmp['system_role']) ?>)
                        <?php else: ?>
                            <span style="color: #837a9c;">No system login</span>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="btn-group mt-20" style="justify-content: flex-end;">
                    <a href="employees.php?edit_id=<?= $viewEmp['employee_id'] ?>" class="btn-primary">Edit Employee</a>
                    <a href="employees.php?page=<?= $page ?>&q=<?= urlencode($searchQ) ?>&staff_type=<?= urlencode($filterStaff) ?>&work_status=<?= urlencode($filterStatus) ?>" class="btn-secondary">Close</a>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<!-- МОДАЛЬНЕ ВІКНО EDIT -->
<?php if ($editEmp): ?>
    <div class="modal-overlay active">
        <div class="modal-content" style="max-width: 620px;">
            <div class="modal-header">
                <h3>Edit Employee #<?= $editEmp['employee_id'] ?></h3>
                <a href="employees.php" class="close-btn">&times;</a>
            </div>
            <form action="employees.php" method="POST" id="editEmployeeForm">
                <input type="hidden" name="employee_id" value="<?= $editEmp['employee_id'] ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>First Name</label>
                        <input type="text" name="first_name" value="<?= htmlspecialchars($editEmp['first_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Last Name</label>
                        <input type="text" name="last_name" value="<?= htmlspecialchars($editEmp['last_name']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Middle Name</label>
                        <input type="text" name="middle_name" value="<?= htmlspecialchars($editEmp['middle_name'] ?? '') ?>">
                    </div>
                    <div class="form-group">
                        <label>Staff Category</label>
                        <select name="staff_type">
                            <option value="Engineer" <?= $editEmp['staff_type'] === 'Engineer' ? 'selected' : '' ?>>Engineer</option>
                            <option value="Operator" <?= $editEmp['staff_type'] === 'Operator' ? 'selected' : '' ?>>Operator</option>
                            <option value="Accountant" <?= $editEmp['staff_type'] === 'Accountant' ? 'selected' : '' ?>>Accountant</option>
                            <option value="Admin" <?= $editEmp['staff_type'] === 'Admin' ? 'selected' : '' ?>>Admin</option>
                            <option value="Support" <?= $editEmp['staff_type'] === 'Support' ? 'selected' : '' ?>>Support</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Position</label>
                        <input type="text" name="position" value="<?= htmlspecialchars($editEmp['position']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Monthly Salary ($)</label>
                        <input type="number" step="0.01" min="0" name="salary" value="<?= htmlspecialchars($editEmp['salary'] ?? '0.00') ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" value="<?= htmlspecialchars($editEmp['phone_number']) ?>" required>
                    </div>
                    <div class="form-group">
                        <label>Work Status</label>
                        <select name="work_status">
                            <option value="Active" <?= $editEmp['work_status'] === 'Active' ? 'selected' : '' ?>>Active</option>
                            <option value="On_Leave" <?= $editEmp['work_status'] === 'On_Leave' ? 'selected' : '' ?>>On Leave</option>
                            <option value="Sick_Leave" <?= $editEmp['work_status'] === 'Sick_Leave' ? 'selected' : '' ?>>Sick Leave</option>
                        </select>
                    </div>
                </div>

                <hr style="border: 0; border-top: 1px solid #e3deff; margin: 18px 0;">
                <h4 style="color: #705DFB; margin-bottom: 10px;">System Account Settings</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Login Username</label>
                        <input type="text" name="username" value="<?= htmlspecialchars($editEmp['username'] ?? '') ?>" placeholder="Leave empty if no login">
                    </div>
                    <div class="form-group">
                        <label>New Strong Password</label>
                        <input type="password" name="password" id="edit_password" placeholder="<?= $editEmp['username'] ? 'Keep current password' : 'Min 8 chars, A-Z, a-z, 0-9, !@#' ?>">
                        <small id="edit_pass_hint" style="display: block; margin-top: 5px; font-size: 12px; color: #837a9c;">
                            8+ chars, uppercase, lowercase, digit, special symbol.
                        </small>
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>System Access Role</label>
                        <select name="system_role">
                            <option value="Engineer" <?= ($editEmp['system_role'] ?? '') === 'Engineer' ? 'selected' : '' ?>>Engineer</option>
                            <option value="Operator" <?= ($editEmp['system_role'] ?? '') === 'Operator' ? 'selected' : '' ?>>Operator</option>
                            <option value="Accountant" <?= ($editEmp['system_role'] ?? '') === 'Accountant' ? 'selected' : '' ?>>Accountant</option>
                            <option value="Superadmin" <?= ($editEmp['system_role'] ?? '') === 'Superadmin' ? 'selected' : '' ?>>Superadmin</option>
                        </select>
                    </div>
                </div>

                <?php if ($editEmp['username']): ?>
                <div class="checkbox-group" style="margin-top: 12px;">
                    <input type="checkbox" name="remove_account" value="1" id="rem_acc">
                    <label for="rem_acc" style="color: #dc3545;">Revoke system login access for this employee</label>
                </div>
                <?php endif; ?>

                <div class="btn-group mt-20" style="justify-content: space-between; align-items: center;">
                    <button type="submit" name="delete_employee" class="btn-danger" onclick="return confirm('Delete this employee?');">Delete Employee</button>
                    <div style="display: flex; gap: 10px;">
                        <a href="employees.php" class="btn-secondary">Cancel</a>
                        <button type="submit" name="update_employee" class="btn-primary">Save Changes</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
<?php endif; ?>

<footer class="admin-footer">
    <div class="container">
        <p>&copy; 2026 CyberFix Technical Service Management. All rights reserved.</p>
    </div>
</footer>

<script>
// 1. Миттєвий пошук без перезавантаження сторінки (AJAX fetch)
const searchInput = document.getElementById('liveSearchInput');
const clearBtn = document.getElementById('clearSearchBtn');
const resultsContainer = document.getElementById('staffResultsContainer');
const filterHiddenQ = document.getElementById('filterHiddenQ');
const filterStaffSelect = document.getElementById('filterStaffType');
const filterStatusSelect = document.getElementById('filterWorkStatus');

let activeController = null;
let debounceTimer = null;

function fetchStaffResults(queryVal) {
    if (activeController) {
        activeController.abort();
    }
    activeController = new AbortController();

    const staffVal = filterStaffSelect ? filterStaffSelect.value : '';
    const statusVal = filterStatusSelect ? filterStatusSelect.value : '';

    if (filterHiddenQ) filterHiddenQ.value = queryVal;
    if (clearBtn) clearBtn.style.display = queryVal.length > 0 ? 'inline-block' : 'none';

    const params = new URLSearchParams();
    if (queryVal !== '') params.set('q', queryVal);
    if (staffVal !== '') params.set('staff_type', staffVal);
    if (statusVal !== '') params.set('work_status', statusVal);

    const newUrl = 'employees.php' + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);

    fetch(newUrl, { signal: activeController.signal })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBox = doc.getElementById('staffResultsContainer');
            if (newBox && resultsContainer) {
                resultsContainer.innerHTML = newBox.innerHTML;
            }
        })
        .catch(err => {
            if (err.name !== 'AbortError') {
                console.error(err);
            }
        });
}

if (searchInput) {
    searchInput.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        const val = searchInput.value;
        debounceTimer = setTimeout(() => {
            fetchStaffResults(val);
        }, 200);
    });
}

if (clearBtn && searchInput) {
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        fetchStaffResults('');
    });
}

// 2. Перевірка надійності пароля на клієнті
function checkPasswordStrength(pwd) {
    return pwd.length >= 8 &&
        /[A-Z]/.test(pwd) &&
        /[a-z]/.test(pwd) &&
        /[0-9]/.test(pwd) &&
        /[^A-Za-z0-9]/.test(pwd);
}

function attachPasswordValidator(inputId, hintId, formId) {
    const input = document.getElementById(inputId);
    const hint = document.getElementById(hintId);
    const form = document.getElementById(formId);
    if (!input || !hint || !form) return;

    input.addEventListener('input', function () {
        const val = input.value;
        if (val === '') {
            hint.style.color = '#837a9c';
            hint.textContent = 'Must contain: 8+ chars, uppercase, lowercase, digit, special symbol.';
            return;
        }
        if (checkPasswordStrength(val)) {
            hint.style.color = '#28a745';
            hint.textContent = 'Strong password ✓';
        } else {
            hint.style.color = '#dc3545';
            hint.textContent = 'Weak password: needs 8+ chars, A-Z, a-z, 0-9 and symbol (!@#$...).';
        }
    });

    form.addEventListener('submit', function (e) {
        const val = input.value;
        if (val !== '' && !checkPasswordStrength(val)) {
            e.preventDefault();
            alert('Please enter a strong password (at least 8 characters, uppercase & lowercase letters, a digit, and a special character).');
            input.focus();
        }
    });
}

attachPasswordValidator('add_password', 'add_pass_hint', 'addEmployeeForm');
attachPasswordValidator('edit_password', 'edit_pass_hint', 'editEmployeeForm');
</script>
</body>
</html>