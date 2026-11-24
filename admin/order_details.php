<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

checkAccess(['Superadmin', 'Operator', 'Engineer']);

$currentRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
$currentEmployeeId = (int)($_SESSION['employee_id'] ?? 0);
$isSuperadmin = ($currentRole === 'superadmin');

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : (int)($_POST['order_id'] ?? 0);

if ($orderId <= 0) {
    header('Location: index.php');
    exit;
}

function recalculateOrderTotals(PDO $pdo, int $orderId, int $deviceId, ?int $initialServiceId): void {
    $baseCost = 0.0;
    $baseHours = 0.0;

    if ($initialServiceId) {
        $initStmt = $pdo->prepare("SELECT base_price, estimated_hours FROM Services WHERE service_id = :sid");
        $initStmt->execute(['sid' => $initialServiceId]);
        $initSrv = $initStmt->fetch();
        if ($initSrv) {
            $baseCost += (float)$initSrv['base_price'];
            $baseHours += (float)$initSrv['estimated_hours'];
        }
    }

    $appStmt = $pdo->prepare("
        SELECT 
            COALESCE(SUM(s.base_price), 0) AS sum_price,
            COALESCE(SUM(s.estimated_hours), 0) AS sum_hours
        FROM Applied_Services a
        JOIN Services s ON a.service_id = s.service_id
        WHERE a.device_id = :did
          AND a.result_status != 'Failed_Needs_Another_Service'
    ");
    $appStmt->execute(['did' => $deviceId]);
    $totals = $appStmt->fetch();

    $finalCost = $baseCost + (float)($totals['sum_price'] ?? 0);
    $finalHours = $baseHours + (float)($totals['sum_hours'] ?? 0);

    $upd = $pdo->prepare("UPDATE Orders SET total_cost = :cost, estimated_hours = :hours WHERE order_id = :oid");
    $upd->execute([
        'cost'  => $finalCost,
        'hours' => $finalHours,
        'oid'   => $orderId
    ]);
}

$orderStmt = $pdo->prepare("
    SELECT 
        o.*,
        c.name AS client_name,
        c.phone_number,
        c.email,
        d.brand,
        d.model,
        d.serial_number,
        dt.device_kind,
        dt.service_category,
        s.service_name AS initial_service_name,
        s.base_price AS initial_service_price,
        CONCAT(e.first_name, ' ', e.last_name) AS lead_engineer_name
    FROM Orders o
    JOIN Clients c ON o.client_id = c.client_id
    JOIN Devices d ON o.device_id = d.device_id
    LEFT JOIN Device_Types dt ON d.type_id = dt.type_id
    LEFT JOIN Services s ON o.initial_service_id = s.service_id
    LEFT JOIN Employees e ON o.lead_employee_id = e.employee_id
    WHERE o.order_id = :oid
");
$orderStmt->execute(['oid' => $orderId]);
$order = $orderStmt->fetch();

if (!$order) {
    header('Location: index.php');
    exit;
}

$deviceId = (int)$order['device_id'];
$initialServiceId = $order['initial_service_id'] ? (int)$order['initial_service_id'] : null;

$message = '';
$isError = false;

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'issue_added') $message = 'Device issue recorded!';
    if ($_GET['msg'] === 'issue_updated') $message = 'Issue status updated!';
    if ($_GET['msg'] === 'issue_deleted') $message = 'Issue deleted!';
    if ($_GET['msg'] === 'service_added') $message = 'Service applied and order total recalculated!';
    if ($_GET['msg'] === 'service_updated') $message = 'Service status updated and total recalculated!';
    if ($_GET['msg'] === 'service_deleted') $message = 'Applied service removed and total recalculated!';
    if ($_GET['msg'] === 'order_updated') $message = 'Order status updated!';
    if ($_GET['msg'] === 'error_delete') {
        $message = 'Cannot delete item: it has linked services.';
        $isError = true;
    }
    if ($_GET['msg'] === 'no_access') {
        $message = 'Access denied for this action.';
        $isError = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $newStatus = trim($_POST['order_status']);
    $completionSql = ($newStatus === 'Completed') ? ", completion_date = NOW()" : "";

    $stmt = $pdo->prepare("UPDATE Orders SET order_status = :st $completionSql WHERE order_id = :oid");
    $stmt->execute(['st' => $newStatus, 'oid' => $orderId]);

    header("Location: order_details.php?order_id={$orderId}&msg=order_updated");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_issue'])) {
    $issueTypeId = (int)$_POST['issue_type_id'];
    $desc = trim($_POST['issue_description']);
    $assignedEmp = !empty($_POST['employee_id']) ? (int)$_POST['employee_id'] : ($currentEmployeeId ?: null);

    $stmt = $pdo->prepare("
        INSERT INTO Device_Issues (device_id, issue_type_id, registration_date, issue_description, employee_id, status)
        VALUES (:did, :itid, NOW(), :desc, :eid, 'Diagnosing')
    ");
    $stmt->execute([
        'did'  => $deviceId,
        'itid' => $issueTypeId,
        'desc' => $desc,
        'eid'  => $assignedEmp
    ]);

    header("Location: order_details.php?order_id={$orderId}&msg=issue_added");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_issue_status'])) {
    $issueId = (int)$_POST['issue_id'];
    $status = trim($_POST['status']);
    $resSql = in_array($status, ['Resolved', 'Unresolvable'], true) ? ", resolution_date = NOW()" : ", resolution_date = NULL";

    $stmt = $pdo->prepare("UPDATE Device_Issues SET status = :st $resSql WHERE issue_id = :iid AND device_id = :did");
    $stmt->execute(['st' => $status, 'iid' => $issueId, 'did' => $deviceId]);

    header("Location: order_details.php?order_id={$orderId}&msg=issue_updated");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_issue'])) {
    if (!$isSuperadmin) {
        header("Location: order_details.php?order_id={$orderId}&msg=no_access");
        exit;
    }
    $issueId = (int)$_POST['issue_id'];
    try {
        $stmt = $pdo->prepare("DELETE FROM Device_Issues WHERE issue_id = :iid AND device_id = :did");
        $stmt->execute(['iid' => $issueId, 'did' => $deviceId]);
        header("Location: order_details.php?order_id={$orderId}&msg=issue_deleted");
        exit;
    } catch (PDOException $e) {
        header("Location: order_details.php?order_id={$orderId}&msg=error_delete");
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_applied_service'])) {
    $issueId = (int)$_POST['issue_id'];
    $serviceId = (int)$_POST['service_id'];
    $resStatus = trim($_POST['result_status']);
    $compSql = ($resStatus === 'Successful') ? "NOW()" : "NULL";

    $stmt = $pdo->prepare("
        INSERT INTO Applied_Services (device_id, issue_id, service_id, start_date, result_status, completion_date)
        VALUES (:did, :iid, :sid, NOW(), :rst, $compSql)
    ");
    $stmt->execute([
        'did' => $deviceId,
        'iid' => $issueId,
        'sid' => $serviceId,
        'rst' => $resStatus
    ]);

    recalculateOrderTotals($pdo, $orderId, $deviceId, $initialServiceId);

    header("Location: order_details.php?order_id={$orderId}&msg=service_added");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_applied_status'])) {
    $appId = (int)$_POST['applied_service_id'];
    $resStatus = trim($_POST['result_status']);
    $compSql = ($resStatus === 'Successful') ? ", completion_date = NOW()" : ", completion_date = NULL";

    $stmt = $pdo->prepare("UPDATE Applied_Services SET result_status = :rst $compSql WHERE applied_service_id = :aid AND device_id = :did");
    $stmt->execute(['rst' => $resStatus, 'aid' => $appId, 'did' => $deviceId]);

    recalculateOrderTotals($pdo, $orderId, $deviceId, $initialServiceId);

    header("Location: order_details.php?order_id={$orderId}&msg=service_updated");
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_applied_service'])) {
    if (!$isSuperadmin) {
        header("Location: order_details.php?order_id={$orderId}&msg=no_access");
        exit;
    }
    $appId = (int)$_POST['applied_service_id'];
    $stmt = $pdo->prepare("DELETE FROM Applied_Services WHERE applied_service_id = :aid AND device_id = :did");
    $stmt->execute(['aid' => $appId, 'did' => $deviceId]);

    recalculateOrderTotals($pdo, $orderId, $deviceId, $initialServiceId);

    header("Location: order_details.php?order_id={$orderId}&msg=service_deleted");
    exit;
}

$issueTypes = $pdo->query("SELECT issue_type_id, type_name FROM Issue_Types ORDER BY type_name ASC")->fetchAll();
$engineers = $pdo->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) AS full_name FROM Employees WHERE staff_type = 'Engineer' AND work_status = 'Active'")->fetchAll();
$catalogServices = $pdo->query("SELECT service_id, service_name, base_price, estimated_hours FROM Services ORDER BY service_name ASC")->fetchAll();

$issuesStmt = $pdo->prepare("
    SELECT 
        di.*,
        it.type_name,
        CONCAT(e.first_name, ' ', e.last_name) AS engineer_name
    FROM Device_Issues di
    JOIN Issue_Types it ON di.issue_type_id = it.issue_type_id
    LEFT JOIN Employees e ON di.employee_id = e.employee_id
    WHERE di.device_id = :did
    ORDER BY di.registration_date DESC
");
$issuesStmt->execute(['did' => $deviceId]);
$issues = $issuesStmt->fetchAll();

$appliedStmt = $pdo->prepare("
    SELECT 
        a.*,
        s.service_name,
        s.base_price,
        s.estimated_hours,
        it.type_name AS issue_category,
        di.issue_description
    FROM Applied_Services a
    JOIN Services s ON a.service_id = s.service_id
    JOIN Device_Issues di ON a.issue_id = di.issue_id
    JOIN Issue_Types it ON di.issue_type_id = it.issue_type_id
    WHERE a.device_id = :did
    ORDER BY a.start_date DESC
");
$appliedStmt->execute(['did' => $deviceId]);
$appliedServices = $appliedStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix Admin - Order #<?= $order['order_id'] ?> Details</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=2">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body>
<div class="container min-page-height">
    <?php include '../includes/admin_header.php'; ?>

    <?php if ($message): ?>
        <div class="alert-message <?= $isError ? 'alert-danger' : 'alert-success' ?>" style="<?= $isError ? 'background-color: #f8d7da; color: #721c24; border: 1px solid #f5c6cb;' : '' ?>">
            <?= htmlspecialchars($message) ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
        <div>
            <h1 class="page-title" style="margin-top: 0;">Order #<?= $order['order_id'] ?> — Diagnostics & Repair</h1>
            <p class="page-subtitle" style="margin-bottom: 0;">
                Device: <strong><?= htmlspecialchars($order['brand'] . ' ' . $order['model']) ?></strong> (SN: <?= htmlspecialchars($order['serial_number']) ?>) • Client: <strong><?= htmlspecialchars($order['client_name']) ?></strong>
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <a href="index.php" class="btn-secondary">Back to Orders</a>
            <a href="print_receipt.php?order_id=<?= $order['order_id'] ?>" target="_blank" class="btn-primary">Print Receipt</a>
        </div>
    </div>

    <div class="admin-stats">
        <div class="stat-card">
            <h3>$<?= number_format($order['total_cost'], 2) ?></h3>
            <p>Calculated Total Cost</p>
        </div>
        <div class="stat-card">
            <h3><?= number_format($order['estimated_hours'], 1) ?> hrs</h3>
            <p>Estimated Work Time</p>
        </div>
        <div class="stat-card">
            <h3><?= str_replace('_', ' ', $order['order_status']) ?></h3>
            <p>Current Order Status</p>
        </div>
    </div>

    <div class="admin-section-box">
        <h3>Order Status & General Info</h3>
        <div class="form-grid" style="margin-bottom: 18px;">
            <div>
                <small class="sub-text">Client Phone / Email</small><br>
                <strong><?= htmlspecialchars($order['phone_number']) ?></strong> / <?= htmlspecialchars($order['email']) ?>
            </div>
            <div>
                <small class="sub-text">Lead Engineer</small><br>
                <strong><?= htmlspecialchars($order['lead_engineer_name'] ?? 'Unassigned') ?></strong>
            </div>
            <div>
                <small class="sub-text">Initial Service</small><br>
                <strong><?= htmlspecialchars($order['initial_service_name'] ?? 'Diagnostics Only') ?></strong>
                <?php if ($order['initial_service_price']): ?>
                    ($<?= number_format($order['initial_service_price'], 2) ?>)
                <?php endif; ?>
            </div>
        </div>

        <form action="order_details.php?order_id=<?= $orderId ?>" method="POST" style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="order_id" value="<?= $orderId ?>">
            <div class="form-group" style="min-width: 240px;">
                <label>Change Order Status</label>
                <select name="order_status">
                    <option value="Accepted" <?= $order['order_status'] === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                    <option value="Diagnostics" <?= $order['order_status'] === 'Diagnostics' ? 'selected' : '' ?>>Diagnostics</option>
                    <option value="In_Progress" <?= $order['order_status'] === 'In_Progress' ? 'selected' : '' ?>>In Progress</option>
                    <option value="Ready" <?= $order['order_status'] === 'Ready' ? 'selected' : '' ?>>Ready</option>
                    <option value="Completed" <?= $order['order_status'] === 'Completed' ? 'selected' : '' ?>>Completed (Close Order)</option>
                </select>
            </div>
            <button type="submit" name="update_order_status" class="btn-primary">Update Status</button>
        </form>
    </div>

    <div class="admin-section-box">
        <h3>1. Detected Device Issues (Diagnostics)</h3>
        <div class="table-responsive" style="margin-bottom: 22px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Description</th>
                        <th>Engineer</th>
                        <th>Date</th>
                        <th>Status</th>
                        <?php if ($isSuperadmin): ?>
                            <th>Delete</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($issues) > 0): ?>
                        <?php foreach ($issues as $iss): ?>
                            <tr>
                                <td><strong>#<?= $iss['issue_id'] ?></strong></td>
                                <td><?= htmlspecialchars($iss['type_name']) ?></td>
                                <td><?= htmlspecialchars($iss['issue_description']) ?></td>
                                <td><?= htmlspecialchars($iss['engineer_name'] ?? '-') ?></td>
                                <td><?= date('Y-m-d H:i', strtotime($iss['registration_date'])) ?></td>
                                <td>
                                    <form action="order_details.php?order_id=<?= $orderId ?>" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                        <input type="hidden" name="issue_id" value="<?= $iss['issue_id'] ?>">
                                        <select name="status" style="padding: 5px 8px; border-radius: 6px; border: 1px solid #d4cefa; font-size: 12.5px;">
                                            <option value="Diagnosing" <?= $iss['status'] === 'Diagnosing' ? 'selected' : '' ?>>Diagnosing</option>
                                            <option value="In_Progress" <?= $iss['status'] === 'In_Progress' ? 'selected' : '' ?>>In Progress</option>
                                            <option value="Resolved" <?= $iss['status'] === 'Resolved' ? 'selected' : '' ?>>Resolved</option>
                                            <option value="Unresolvable" <?= $iss['status'] === 'Unresolvable' ? 'selected' : '' ?>>Unresolvable</option>
                                        </select>
                                        <button type="submit" name="update_issue_status" class="btn-primary btn-sm">Save</button>
                                    </form>
                                </td>
                                <?php if ($isSuperadmin): ?>
                                <td>
                                    <form action="order_details.php?order_id=<?= $orderId ?>" method="POST" onsubmit="return confirm('Delete this issue?');">
                                        <input type="hidden" name="issue_id" value="<?= $iss['issue_id'] ?>">
                                        <button type="submit" name="delete_issue" class="btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $isSuperadmin ? 7 : 6 ?>" style="text-align: center; color: #837a9c;">No issues recorded yet. Add one below.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <form action="order_details.php?order_id=<?= $orderId ?>" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Issue Category</label>
                    <select name="issue_type_id" required>
                        <?php foreach ($issueTypes as $it): ?>
                            <option value="<?= $it['issue_type_id'] ?>"><?= htmlspecialchars($it['type_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Responsible Engineer</label>
                    <select name="employee_id">
                        <?php foreach ($engineers as $eng): ?>
                            <option value="<?= $eng['employee_id'] ?>" <?= ($eng['employee_id'] == $currentEmployeeId || $eng['employee_id'] == $order['lead_employee_id']) ? 'selected' : '' ?>>
                                <?= htmlspecialchars($eng['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Problem Description</label>
                    <input type="text" name="issue_description" required placeholder="e.g. Dry thermal paste and dust blockage in cooling system">
                </div>
            </div>
            <button type="submit" name="add_issue" class="btn-primary mt-20">Add Detected Issue</button>
        </form>
    </div>

    <div class="admin-section-box">
        <h3>2. Applied Repair Services (Auto-calculates Total Cost)</h3>
        <div class="table-responsive" style="margin-bottom: 22px;">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Target Issue</th>
                        <th>Applied Service</th>
                        <th>Time</th>
                        <th>Price ($)</th>
                        <th>Result Status</th>
                        <?php if ($isSuperadmin): ?>
                            <th>Delete</th>
                        <?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($appliedServices) > 0): ?>
                        <?php foreach ($appliedServices as $app): ?>
                            <tr>
                                <td><strong>#<?= $app['applied_service_id'] ?></strong></td>
                                <td>
                                    <strong>#<?= $app['issue_id'] ?> <?= htmlspecialchars($app['issue_category']) ?></strong><br>
                                    <small class="sub-text"><?= htmlspecialchars($app['issue_description']) ?></small>
                                </td>
                                <td><?= htmlspecialchars($app['service_name']) ?></td>
                                <td><?= htmlspecialchars($app['estimated_hours']) ?> hrs</td>
                                <td>
                                    <strong>$<?= number_format($app['base_price'], 2) ?></strong>
                                    <?php if ($app['result_status'] === 'Failed_Needs_Another_Service'): ?>
                                        <br><small style="color: #dc3545;">(Not billed)</small>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <form action="order_details.php?order_id=<?= $orderId ?>" method="POST" style="display: flex; gap: 6px; align-items: center;">
                                        <input type="hidden" name="applied_service_id" value="<?= $app['applied_service_id'] ?>">
                                        <select name="result_status" style="padding: 5px 8px; border-radius: 6px; border: 1px solid #d4cefa; font-size: 12.5px;">
                                            <option value="In_Testing" <?= $app['result_status'] === 'In_Testing' ? 'selected' : '' ?>>In Testing</option>
                                            <option value="Successful" <?= $app['result_status'] === 'Successful' ? 'selected' : '' ?>>Successful</option>
                                            <option value="Failed_Needs_Another_Service" <?= $app['result_status'] === 'Failed_Needs_Another_Service' ? 'selected' : '' ?>>Failed (Not Billed)</option>
                                        </select>
                                        <button type="submit" name="update_applied_status" class="btn-primary btn-sm">Save</button>
                                    </form>
                                </td>
                                <?php if ($isSuperadmin): ?>
                                <td>
                                    <form action="order_details.php?order_id=<?= $orderId ?>" method="POST" onsubmit="return confirm('Remove this service and recalculate total?');">
                                        <input type="hidden" name="applied_service_id" value="<?= $app['applied_service_id'] ?>">
                                        <button type="submit" name="delete_applied_service" class="btn-danger btn-sm">Delete</button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="<?= $isSuperadmin ? 7 : 6 ?>" style="text-align: center; color: #837a9c;">No repair services applied yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>

        <?php if (count($issues) > 0): ?>
            <form action="order_details.php?order_id=<?= $orderId ?>" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>For Detected Issue</label>
                        <select name="issue_id" required>
                            <?php foreach ($issues as $iss): ?>
                                <option value="<?= $iss['issue_id'] ?>">
                                    #<?= $iss['issue_id'] ?> — <?= htmlspecialchars($iss['type_name']) ?> (<?= htmlspecialchars(mb_strimwidth($iss['issue_description'], 0, 40, '...')) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Service from Catalog</label>
                        <select name="service_id" required>
                            <?php foreach ($catalogServices as $cs): ?>
                                <option value="<?= $cs['service_id'] ?>">
                                    <?= htmlspecialchars($cs['service_name']) ?> ($<?= number_format($cs['base_price'], 2) ?>, <?= $cs['estimated_hours'] ?> hrs)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Initial Result Status</label>
                        <select name="result_status">
                            <option value="In_Testing">In Testing</option>
                            <option value="Successful">Successful</option>
                            <option value="Failed_Needs_Another_Service">Failed (Needs Another Service)</option>
                        </select>
                    </div>
                </div>
                <button type="submit" name="add_applied_service" class="btn-primary mt-20">Apply Service & Recalculate Total</button>
            </form>
        <?php else: ?>
            <p style="color: #837a9c; font-size: 13.5px;">Add at least one detected issue above before applying repair services.</p>
        <?php endif; ?>
    </div>
</div>

<footer class="admin-footer">
    <div class="container">
        <p>&copy; 2026 CyberFix Technical Service Management. All rights reserved.</p>
    </div>
</footer>
</body>
</html>