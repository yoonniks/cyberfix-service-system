<?php
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../config/db.php';

if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    $_SESSION = [];
    session_destroy();
    header('Location: ../login.php');
    exit;
}

checkAccess(['Superadmin', 'Operator', 'Engineer']);

$currentRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
$isSuperadmin = ($currentRole === 'superadmin');
$canManageOrders = in_array($currentRole, ['superadmin', 'operator'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_order'])) {
    if (!$canManageOrders) {
        setFlash('error', 'Access denied: you do not have permission to create orders.');
        header('Location: index.php');
        exit;
    }

    $clientId = !empty($_POST['existing_client_id']) ? (int)$_POST['existing_client_id'] : null;

    if (!$clientId) {
        $cName  = trim($_POST['client_name']);
        $cPhone = trim($_POST['client_phone']);
        $cEmail = trim($_POST['client_email']);

        $stmtClient = $pdo->prepare("INSERT INTO Clients (client_type, name, phone_number, email, registration_date) VALUES ('Individual', :name, :phone, :email, NOW())");
        $stmtClient->execute(['name' => $cName, 'phone' => $cPhone, 'email' => $cEmail]);
        $clientId = $pdo->lastInsertId();
    }

    $typeId = (int)$_POST['type_id'];
    $brand  = trim($_POST['brand']);
    $model  = trim($_POST['model']);
    $serial = trim($_POST['serial_number']);

    $stmtDevice = $pdo->prepare("INSERT INTO Devices (client_id, type_id, brand, model, serial_number, first_service_date) VALUES (:client_id, :type_id, :brand, :model, :serial, NOW())");
    $stmtDevice->execute([
        'client_id' => $clientId,
        'type_id'   => $typeId,
        'brand'     => $brand,
        'model'     => $model,
        'serial'    => $serial
    ]);
    $deviceId = $pdo->lastInsertId();

    $serviceId  = !empty($_POST['initial_service_id']) ? (int)$_POST['initial_service_id'] : null;
    $employeeId = (int)$_POST['lead_employee_id'];
    $totalCost  = (float)$_POST['total_cost'];

    $estHours = 1.0;
    if ($serviceId) {
        $sStmt = $pdo->prepare("SELECT estimated_hours FROM Services WHERE service_id = :id");
        $sStmt->execute(['id' => $serviceId]);
        $srv = $sStmt->fetch();
        if ($srv) { $estHours = (float)$srv['estimated_hours']; }
    }

    $stmtOrder = $pdo->prepare("
        INSERT INTO Orders (client_id, device_id, initial_service_id, lead_employee_id, order_date, order_status, estimated_hours, total_cost, payment_status, payment_method)
        VALUES (:client_id, :device_id, :service_id, :employee_id, NOW(), 'Accepted', :hours, :cost, 'Unpaid', NULL)
    ");
    $stmtOrder->execute([
        'client_id'   => $clientId,
        'device_id'   => $deviceId,
        'service_id'  => $serviceId,
        'employee_id' => $employeeId,
        'hours'       => $estHours,
        'cost'        => $totalCost
    ]);

    setFlash('success', 'New order created successfully!');
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order'])) {
    $orderId = (int)$_POST['order_id'];
    $status  = trim($_POST['order_status']);
    $completionSql = ($status === 'Completed') ? ", completion_date = NOW()" : "";

    if ($currentRole === 'engineer') {
        $stmtUpdate = $pdo->prepare("
            UPDATE Orders 
            SET order_status = :status
                $completionSql
            WHERE order_id = :order_id
        ");
        $stmtUpdate->execute([
            'status'   => $status,
            'order_id' => $orderId
        ]);
    } else {
        $employeeId    = (int)$_POST['lead_employee_id'];
        $totalCost     = (float)$_POST['total_cost'];
        $paymentStatus = in_array($_POST['payment_status'] ?? '', ['Unpaid', 'Paid'], true) ? $_POST['payment_status'] : 'Unpaid';
        $rawMethod     = trim($_POST['payment_method'] ?? '');
        $paymentMethod = ($paymentStatus === 'Paid' && in_array($rawMethod, ['Cash', 'Card', 'Bank_Transfer'], true)) ? $rawMethod : null;

        $stmtUpdate = $pdo->prepare("
            UPDATE Orders 
            SET order_status = :status,
                lead_employee_id = :employee_id,
                total_cost = :cost,
                payment_status = :pay_status,
                payment_method = :pay_method
                $completionSql
            WHERE order_id = :order_id
        ");
        $stmtUpdate->execute([
            'status'      => $status,
            'employee_id' => $employeeId,
            'cost'        => $totalCost,
            'pay_status'  => $paymentStatus,
            'pay_method'  => $paymentMethod,
            'order_id'    => $orderId
        ]);
    }

    setFlash('success', "Order #{$orderId} updated successfully!");
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_order'])) {
    if (!$isSuperadmin) {
        setFlash('error', 'Access denied: only Superadmin can delete orders.');
        header('Location: index.php');
        exit;
    }

    $orderId = (int)$_POST['order_id'];

    if ($orderId > 0) {
        try {
            $deleteStmt = $pdo->prepare("DELETE FROM Orders WHERE order_id = :id");
            $deleteStmt->execute(['id' => $orderId]);
            setFlash('success', "Order #{$orderId} deleted successfully!");
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete order: it has linked records in the system!');
        }
    }
    header('Location: index.php');
    exit;
}

// GET-фільтри та пошук
$searchQ        = trim($_GET['q'] ?? '');
$filterStatus   = trim($_GET['status'] ?? 'active_only');
$filterEngineer = (int)($_GET['engineer_id'] ?? 0);
$dateFrom       = trim($_GET['date_from'] ?? '');
$dateTo         = trim($_GET['date_to'] ?? '');

$whereClauses = [];
$params = [];

if ($filterStatus === 'active_only') {
    $whereClauses[] = "o.order_status != 'Completed'";
} elseif ($filterStatus !== '' && $filterStatus !== 'all') {
    $whereClauses[] = "o.order_status = :status";
    $params['status'] = $filterStatus;
}

if ($filterEngineer > 0) {
    $whereClauses[] = "o.lead_employee_id = :engineer_id";
    $params['engineer_id'] = $filterEngineer;
}

if ($dateFrom !== '') {
    $whereClauses[] = "DATE(o.order_date) >= :date_from";
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $whereClauses[] = "DATE(o.order_date) <= :date_to";
    $params['date_to'] = $dateTo;
}

if ($searchQ !== '') {
    $whereClauses[] = "(CAST(o.order_id AS CHAR) = :q_exact OR c.name LIKE :q_name OR c.phone_number LIKE :q_phone OR CONCAT(d.brand, ' ', d.model) LIKE :q_dev)";
    $params['q_exact'] = ltrim($searchQ, '#');
    $params['q_name']  = '%' . $searchQ . '%';
    $params['q_phone'] = '%' . $searchQ . '%';
    $params['q_dev']   = '%' . $searchQ . '%';
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Пагінація (по 5 записів)
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Orders o
    JOIN Clients c ON o.client_id = c.client_id
    JOIN Devices d ON o.device_id = d.device_id
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$ordersStmt = $pdo->prepare("
    SELECT 
        o.order_id,
        c.name AS client_name,
        c.phone_number,
        dt.device_kind,
        d.brand,
        d.model,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.lead_employee_id,
        CONCAT(e.first_name, ' ', e.last_name) AS engineer_name,
        o.total_cost,
        o.order_date,
        s.service_name AS initial_service
    FROM Orders o
    JOIN Clients c ON o.client_id = c.client_id
    JOIN Devices d ON o.device_id = d.device_id
    LEFT JOIN Device_Types dt ON d.type_id = dt.type_id
    LEFT JOIN Employees e ON o.lead_employee_id = e.employee_id
    LEFT JOIN Services s ON o.initial_service_id = s.service_id
    $whereSql
    ORDER BY o.order_date DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $ordersStmt->bindValue(':' . $key, $val);
}
$ordersStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$ordersStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$ordersStmt->execute();
$active_orders = $ordersStmt->fetchAll();

$clients      = $pdo->query("SELECT client_id, name, phone_number, email FROM Clients ORDER BY name ASC")->fetchAll();
$engineers    = $pdo->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) AS full_name FROM Employees WHERE staff_type = 'Engineer' AND work_status = 'Active' ORDER BY first_name ASC")->fetchAll();
$allEngineers = $pdo->query("SELECT employee_id, CONCAT(first_name, ' ', last_name) AS full_name FROM Employees WHERE staff_type = 'Engineer' ORDER BY first_name ASC")->fetchAll();
$services     = $pdo->query("SELECT service_id, service_name, base_price FROM Services ORDER BY service_name ASC")->fetchAll();
$devTypes     = $pdo->query("SELECT type_id, CONCAT(device_kind, ' (', service_category, ')') AS category_info FROM Device_Types")->fetchAll();

$total_active = $pdo->query("SELECT COUNT(*) as cnt FROM Orders WHERE order_status != 'Completed'")->fetch()['cnt'];
$in_progress  = $pdo->query("SELECT COUNT(*) as cnt FROM Orders WHERE order_status = 'In_Progress'")->fetch()['cnt'];
$ready_count  = $pdo->query("SELECT COUNT(*) as cnt FROM Orders WHERE order_status = 'Ready'")->fetch()['cnt'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix Admin - Orders</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=3">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body>

<div class="container min-page-height">
    <?php include '../includes/admin_header.php'; ?>

    <?php renderFlash(); ?>

    <h1 class="page-title">Service Management Dashboard</h1>
    <p class="page-subtitle">Overview of technical repairs, order search, and registrations</p>

    <div class="admin-stats">
        <div class="stat-card">
            <h3><?= $total_active ?></h3>
            <p>Active Orders</p>
        </div>
        <div class="stat-card">
            <h3><?= $in_progress ?></h3>
            <p>In Progress</p>
        </div>
        <div class="stat-card">
            <h3><?= $ready_count ?></h3>
            <p>Ready for Pickup</p>
        </div>
    </div>

    <!-- 1. БЛОК ФІЛЬТРІВ -->
    <div class="admin-section-box">
        <h3>Filter Orders</h3>
        <form action="index.php" method="GET" id="filterForm">
            <input type="hidden" name="q" id="filterHiddenQ" value="<?= htmlspecialchars($searchQ) ?>">
            <div class="form-grid">
                <div class="form-group">
                    <label>Order Status</label>
                    <select name="status" id="filterStatus">
                        <option value="active_only" <?= $filterStatus === 'active_only' ? 'selected' : '' ?>>All Active (Not Completed)</option>
                        <option value="all" <?= $filterStatus === 'all' ? 'selected' : '' ?>>All Orders (Including Closed)</option>
                        <option value="Accepted" <?= $filterStatus === 'Accepted' ? 'selected' : '' ?>>Accepted</option>
                        <option value="Diagnostics" <?= $filterStatus === 'Diagnostics' ? 'selected' : '' ?>>Diagnostics</option>
                        <option value="In_Progress" <?= $filterStatus === 'In_Progress' ? 'selected' : '' ?>>In Progress</option>
                        <option value="Ready" <?= $filterStatus === 'Ready' ? 'selected' : '' ?>>Ready</option>
                        <option value="Completed" <?= $filterStatus === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Lead Engineer</label>
                    <select name="engineer_id" id="filterEngineer">
                        <option value="0">-- All Engineers --</option>
                        <?php foreach ($allEngineers as $eng): ?>
                            <option value="<?= $eng['employee_id'] ?>" <?= $filterEngineer === (int)$eng['employee_id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($eng['full_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" id="filterDateFrom" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="form-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" id="filterDateTo" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
            </div>
            <div style="display: flex; gap: 10px; margin-top: 15px;">
                <button type="submit" class="btn-primary">Apply Filters</button>
                <a href="index.php" class="btn-secondary">Reset All</a>
            </div>
        </form>
    </div>

    <!-- 2. МИТТЄВИЙ ПОШУК (AJAX) -->
    <div class="admin-section-box">
        <h3>Instant Order Search</h3>
        <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
            <div class="form-group" style="flex: 1; min-width: 260px; margin-bottom: 0;">
                <label>Search by Order ID, Client Name, Phone or Device</label>
                <input type="text" id="liveSearchInput" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Start typing #ID, client name, phone or model..." autocomplete="off">
            </div>
            <button type="button" id="clearSearchBtn" class="btn-secondary" style="<?= $searchQ === '' ? 'display: none;' : '' ?>">Clear Search</button>
        </div>
    </div>

    <!-- 3. ТАБЛИЦЯ РЕЗУЛЬТАТІВ -->
    <div class="admin-section-box" id="ordersResultsContainer">
        <h3>Service Orders (Found: <?= $totalRows ?>)</h3>
        <div class="table-responsive">
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Client</th>
                        <th>Phone</th>
                        <th>Device</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Engineer</th>
                        <th>Total & Payment</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($active_orders) > 0): ?>
                        <?php foreach ($active_orders as $row): ?>
                            <tr>
                                <td><strong>#<?= $row['order_id'] ?></strong></td>
                                <td><?= htmlspecialchars($row['client_name']) ?></td>
                                <td><?= htmlspecialchars($row['phone_number']) ?></td>
                                <td><?= htmlspecialchars($row['brand'] . ' ' . $row['model']) ?></td>
                                <td><?= date('Y-m-d', strtotime($row['order_date'])) ?></td>
                                <td>
                                    <span class="status-badge status-<?= $row['order_status'] ?>">
                                        <?= str_replace('_', ' ', $row['order_status']) ?>
                                    </span>
                                </td>
                                <td><?= htmlspecialchars($row['engineer_name'] ?? 'Unassigned') ?></td>
                                <td>
                                    <strong>$<?= number_format($row['total_cost'], 2) ?></strong><br>
                                    <span class="status-badge <?= ($row['payment_status'] ?? 'Unpaid') === 'Paid' ? 'status-Completed' : 'status-Accepted' ?>" style="font-size: 11px; padding: 2px 7px; margin-top: 3px; display: inline-block;">
                                        <?= htmlspecialchars($row['payment_status'] ?? 'Unpaid') ?>
                                        <?= !empty($row['payment_method']) ? ' (' . str_replace('_', ' ', $row['payment_method']) . ')' : '' ?>
                                    </span>
                                </td>
                                <td style="white-space: nowrap;">
                                    <a href="order_details.php?order_id=<?= $row['order_id'] ?>" class="btn-primary btn-sm">Details</a>
                                    <button class="btn-secondary btn-sm" onclick="openEditOrderModal(
                                        '<?= $row['order_id'] ?>',
                                        '<?= $row['order_status'] ?>',
                                        '<?= $row['lead_employee_id'] ?>',
                                        '<?= $row['total_cost'] ?>',
                                        '<?= htmlspecialchars($row['payment_status'] ?? 'Unpaid', ENT_QUOTES) ?>',
                                        '<?= htmlspecialchars($row['payment_method'] ?? '', ENT_QUOTES) ?>'
                                    )">Edit</button>
                                    <a href="print_receipt.php?order_id=<?= $row['order_id'] ?>" target="_blank" class="btn-secondary btn-sm">Receipt</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" style="text-align: center; color: #837a9c;">No orders match your filter criteria.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
        <?php renderPagination($page, $totalPages, $_GET); ?>
    </div>

    <?php if ($canManageOrders): ?>
    <div class="admin-section-box">
        <h3>Register New Order (Fast Checkout)</h3>
        <form action="index.php" method="POST">
            <div class="form-grid">
                <div class="form-group">
                    <label>Existing Client</label>
                    <select id="existing_client_select" name="existing_client_id" onchange="autoFillClientData()">
                        <option value="">-- New Client (fill fields below) --</option>
                        <?php foreach ($clients as $c): ?>
                            <option value="<?= $c['client_id'] ?>" 
                                    data-name="<?= htmlspecialchars($c['name']) ?>" 
                                    data-phone="<?= htmlspecialchars($c['phone_number']) ?>" 
                                    data-email="<?= htmlspecialchars($c['email']) ?>">
                                <?= htmlspecialchars($c['name']) ?> (<?= htmlspecialchars($c['phone_number']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Client Name / Company</label>
                    <input type="text" id="client_name" name="client_name" placeholder="e.g. John Doe" required>
                </div>

                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" id="client_phone" name="client_phone" placeholder="e.g. +380971234567" required>
                </div>

                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" id="client_email" name="client_email" placeholder="e.g. client@example.com">
                </div>

                <div class="form-group">
                    <label>Device Category</label>
                    <select name="type_id" required>
                        <?php foreach ($devTypes as $dt): ?>
                            <option value="<?= $dt['type_id'] ?>"><?= htmlspecialchars($dt['category_info']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Brand</label>
                    <input type="text" name="brand" placeholder="e.g. ASUS / Apple" required>
                </div>

                <div class="form-group">
                    <label>Model</label>
                    <input type="text" name="model" placeholder="e.g. ROG Strix G15" required>
                </div>

                <div class="form-group">
                    <label>Serial Number</label>
                    <input type="text" name="serial_number" placeholder="e.g. SN-9812471">
                </div>

                <div class="form-group">
                    <label>Initial Service</label>
                    <select id="initial_service_select" name="initial_service_id" onchange="autoFillServicePrice()">
                        <option value="" data-price="0.00">-- Diagnostics Only --</option>
                        <?php foreach ($services as $s): ?>
                            <option value="<?= $s['service_id'] ?>" data-price="<?= $s['base_price'] ?>">
                                <?= htmlspecialchars($s['service_name']) ?> ($<?= number_format($s['base_price'], 2) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Assigned Engineer</label>
                    <select name="lead_employee_id" required>
                        <?php foreach ($engineers as $eng): ?>
                            <option value="<?= $eng['employee_id'] ?>"><?= htmlspecialchars($eng['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group">
                    <label>Total Cost ($)</label>
                    <input type="number" step="0.01" id="total_cost_input" name="total_cost" placeholder="0.00" required>
                </div>
            </div>

            <button type="submit" name="add_order" class="btn-primary mt-20">Create Order</button>
        </form>
    </div>
    <?php endif; ?>
</div>

<!-- МОДАЛЬНЕ ВІКНО РЕДАГУВАННЯ ЗАМОВЛЕННЯ -->
<div id="editOrderModal" class="modal-overlay">
    <div class="modal-content">
        <div class="modal-header">
            <h3 id="modal_order_title">Edit Order</h3>
            <button class="close-btn" onclick="closeEditOrderModal()">&times;</button>
        </div>

        <form action="index.php" method="POST" id="editOrderForm">
            <input type="hidden" id="edit_order_id" name="order_id">

            <div class="form-grid">
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Order Status</label>
                    <select id="edit_order_status" name="order_status">
                        <option value="Accepted">Accepted</option>
                        <option value="Diagnostics">Diagnostics</option>
                        <option value="In_Progress">In Progress</option>
                        <option value="Ready">Ready</option>
                        <option value="Completed">Completed (Close Order)</option>
                    </select>
                </div>

                <?php if ($canManageOrders): ?>
                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Assigned Engineer</label>
                    <select id="edit_lead_employee_id" name="lead_employee_id">
                        <?php foreach ($engineers as $eng): ?>
                            <option value="<?= $eng['employee_id'] ?>"><?= htmlspecialchars($eng['full_name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <div class="form-group" style="grid-column: 1 / -1;">
                    <label>Total Price ($)</label>
                    <input type="number" step="0.01" id="edit_total_cost" name="total_cost" required>
                </div>

                <div class="form-group">
                    <label>Payment Status</label>
                    <select id="edit_payment_status" name="payment_status" onchange="togglePaymentMethodField()">
                        <option value="Unpaid">Unpaid</option>
                        <option value="Paid">Paid</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <select id="edit_payment_method" name="payment_method">
                        <option value="">-- Not Paid Yet --</option>
                        <option value="Cash">Cash</option>
                        <option value="Card">Card</option>
                        <option value="Bank_Transfer">Bank Transfer</option>
                    </select>
                </div>
                <?php endif; ?>
            </div>

            <div class="btn-group mt-20" style="justify-content: space-between; align-items: center;">
                <?php if ($isSuperadmin): ?>
                <button type="submit" name="delete_order" class="btn-danger" onclick="return confirmDeleteOrder();">Delete Order</button>
                <?php else: ?>
                <span></span>
                <?php endif; ?>

                <div style="display: flex; gap: 10px;">
                    <button type="button" class="btn-secondary" onclick="closeEditOrderModal()">Cancel</button>
                    <button type="submit" name="update_order" class="btn-primary">Save Changes</button>
                </div>
            </div>
        </form>
    </div>
</div>

<footer class="admin-footer">
    <div class="container">
        <p>&copy; 2026 CyberFix Technical Service Management. All rights reserved.</p>
    </div>
</footer>

<script>
// 1. Миттєвий AJAX-пошук замовлень
const searchInput = document.getElementById('liveSearchInput');
const clearBtn = document.getElementById('clearSearchBtn');
const resultsContainer = document.getElementById('ordersResultsContainer');
const filterHiddenQ = document.getElementById('filterHiddenQ');

let activeController = null;
let debounceTimer = null;

function fetchOrdersResults(queryVal) {
    if (activeController) {
        activeController.abort();
    }
    activeController = new AbortController();

    const statusVal   = document.getElementById('filterStatus')?.value || '';
    const engineerVal = document.getElementById('filterEngineer')?.value || '';
    const dateFromVal = document.getElementById('filterDateFrom')?.value || '';
    const dateToVal   = document.getElementById('filterDateTo')?.value || '';

    if (filterHiddenQ) filterHiddenQ.value = queryVal;
    if (clearBtn) clearBtn.style.display = queryVal.length > 0 ? 'inline-block' : 'none';

    const params = new URLSearchParams();
    if (queryVal !== '') params.set('q', queryVal);
    if (statusVal !== '') params.set('status', statusVal);
    if (engineerVal !== '' && engineerVal !== '0') params.set('engineer_id', engineerVal);
    if (dateFromVal !== '') params.set('date_from', dateFromVal);
    if (dateToVal !== '') params.set('date_to', dateToVal);

    const newUrl = 'index.php' + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);

    fetch(newUrl, { signal: activeController.signal })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBox = doc.getElementById('ordersResultsContainer');
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
            fetchOrdersResults(val);
        }, 200);
    });
}

if (clearBtn && searchInput) {
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        fetchOrdersResults('');
    });
}

// 2. Автозаповнення клієнта та ціни послуги
function autoFillClientData() {
    const select = document.getElementById('existing_client_select');
    const selectedOption = select.options[select.selectedIndex];

    const nameInput = document.getElementById('client_name');
    const phoneInput = document.getElementById('client_phone');
    const emailInput = document.getElementById('client_email');

    if (select.value !== "") {
        nameInput.value = selectedOption.getAttribute('data-name') || '';
        phoneInput.value = selectedOption.getAttribute('data-phone') || '';
        emailInput.value = selectedOption.getAttribute('data-email') || '';

        nameInput.readOnly = true;
        phoneInput.readOnly = true;
        emailInput.readOnly = true;
    } else {
        nameInput.value = '';
        phoneInput.value = '';
        emailInput.value = '';

        nameInput.readOnly = false;
        phoneInput.readOnly = false;
        emailInput.readOnly = false;
    }
}

function autoFillServicePrice() {
    const select = document.getElementById('initial_service_select');
    const selectedOption = select.options[select.selectedIndex];
    const priceInput = document.getElementById('total_cost_input');
    const price = selectedOption.getAttribute('data-price');

    if (price !== null && price !== "") {
        priceInput.value = parseFloat(price).toFixed(2);
    }
}

// 3. Модальне вікно редагування замовлення та оплати
function togglePaymentMethodField() {
    const payStatus = document.getElementById('edit_payment_status');
    const payMethod = document.getElementById('edit_payment_method');
    if (!payStatus || !payMethod) return;

    if (payStatus.value === 'Unpaid') {
        payMethod.value = '';
        payMethod.disabled = true;
    } else {
        payMethod.disabled = false;
        if (payMethod.value === '') {
            payMethod.value = 'Card';
        }
    }
}

function openEditOrderModal(orderId, status, employeeId, totalCost, paymentStatus, paymentMethod) {
    document.getElementById('modal_order_title').innerText = 'Edit Order #' + orderId;
    document.getElementById('edit_order_id').value = orderId;
    document.getElementById('edit_order_status').value = status;

    const empField = document.getElementById('edit_lead_employee_id');
    const costField = document.getElementById('edit_total_cost');
    const payStatusField = document.getElementById('edit_payment_status');
    const payMethodField = document.getElementById('edit_payment_method');

    if (empField) empField.value = employeeId;
    if (costField) costField.value = totalCost;
    if (payStatusField) {
        payStatusField.value = paymentStatus || 'Unpaid';
    }
    if (payMethodField) {
        payMethodField.value = paymentMethod || '';
    }
    togglePaymentMethodField();

    document.getElementById('editOrderModal').classList.add('active');
}

function closeEditOrderModal() {
    document.getElementById('editOrderModal').classList.remove('active');
}

function confirmDeleteOrder() {
    const orderId = document.getElementById('edit_order_id').value;
    return confirm(`Are you sure you want to delete order #${orderId}? This action cannot be undone!`);
}
</script>

</body>
</html>