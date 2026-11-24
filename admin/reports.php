<?php
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../config/db.php';

checkAccess(['Superadmin', 'Accountant']);

$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo   = trim($_GET['date_to'] ?? '');

$orderWhere = [];
$orderParams = [];

if ($dateFrom !== '') {
    $orderWhere[] = "DATE(o.order_date) >= :date_from";
    $orderParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $orderWhere[] = "DATE(o.order_date) <= :date_to";
    $orderParams['date_to'] = $dateTo;
}

$orderWhereSql = count($orderWhere) > 0 ? 'WHERE ' . implode(' AND ', $orderWhere) : '';

// 1. Фінансові показники за період
$finStmt = $pdo->prepare("
    SELECT 
        COALESCE(SUM(CASE WHEN o.payment_status = 'Paid' THEN o.total_cost ELSE 0 END), 0) AS paid_rev,
        COALESCE(SUM(CASE WHEN o.payment_status = 'Unpaid' THEN o.total_cost ELSE 0 END), 0) AS unpaid_rev,
        SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS closed_cnt,
        COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_cost ELSE 0 END), 0) AS completed_rev
    FROM Orders o
    $orderWhereSql
");
$finStmt->execute($orderParams);
$finData = $finStmt->fetch();

$paidRevenue    = (float)($finData['paid_rev'] ?? 0);
$unpaidRevenue  = (float)($finData['unpaid_rev'] ?? 0);
$completedCount = (int)($finData['closed_cnt'] ?? 0);
$completedRev   = (float)($finData['completed_rev'] ?? 0);
$avgOrder       = $completedCount > 0 ? ($completedRev / $completedCount) : 0;

// Фонд оплати праці активних співробітників
$payrollStmt = $pdo->query("
    SELECT COALESCE(SUM(CASE WHEN work_status = 'Active' THEN salary ELSE 0 END), 0) AS active_payroll
    FROM Employees
");
$activePayroll = (float)($payrollStmt->fetchColumn() ?? 0);

// 2. Діаграма №1: Статуси замовлень
$stStmt = $pdo->prepare("
    SELECT o.order_status, COUNT(*) AS cnt
    FROM Orders o
    $orderWhereSql
    GROUP BY o.order_status
");
$stStmt->execute($orderParams);
$statusRows = $stStmt->fetchAll();

$statusLabels = [];
$statusCounts = [];
foreach ($statusRows as $sr) {
    $statusLabels[] = str_replace('_', ' ', $sr['order_status']);
    $statusCounts[] = (int)$sr['cnt'];
}

// 3. Діаграма №2: Дохід по категоріях пристроїв
$catStmt = $pdo->prepare("
    SELECT 
        COALESCE(dt.device_kind, 'Other') AS device_kind,
        COUNT(o.order_id) AS orders_count,
        COALESCE(SUM(o.total_cost), 0) AS category_sum
    FROM Orders o
    JOIN Devices d ON o.device_id = d.device_id
    LEFT JOIN Device_Types dt ON d.type_id = dt.type_id
    $orderWhereSql
    GROUP BY dt.device_kind
    ORDER BY category_sum DESC
");
$catStmt->execute($orderParams);
$byCategory = $catStmt->fetchAll();

$catLabels = [];
$catSums   = [];
foreach ($byCategory as $cat) {
    $catLabels[] = $cat['device_kind'];
    $catSums[]   = (float)$cat['category_sum'];
}

// 4. Діаграма №3 та детальний зарплатний реєстр персоналу
$engJoinCond = "e.employee_id = o.lead_employee_id";
if ($dateFrom !== '') {
    $engJoinCond .= " AND DATE(o.order_date) >= :date_from";
}
if ($dateTo !== '') {
    $engJoinCond .= " AND DATE(o.order_date) <= :date_to";
}

$payrollLedgerStmt = $pdo->prepare("
    SELECT 
        e.employee_id,
        CONCAT(e.first_name, ' ', e.last_name) AS full_name,
        e.staff_type,
        e.position,
        e.work_status,
        COALESCE(e.salary, 0) AS base_salary,
        COUNT(o.order_id) AS total_orders,
        SUM(CASE WHEN o.order_status != 'Completed' AND o.order_id IS NOT NULL THEN 1 ELSE 0 END) AS active_orders,
        SUM(CASE WHEN o.order_status = 'Completed' THEN 1 ELSE 0 END) AS completed_orders,
        COALESCE(SUM(CASE WHEN o.order_status = 'Completed' THEN o.total_cost ELSE 0 END), 0) AS earned_total
    FROM Employees e
    LEFT JOIN Orders o ON $engJoinCond
    GROUP BY e.employee_id
    ORDER BY e.staff_type ASC, e.employee_id ASC
");
$payrollLedgerStmt->execute($orderParams);
$staffLedger = $payrollLedgerStmt->fetchAll();

$engLabels    = [];
$engActive    = [];
$engCompleted = [];
foreach ($staffLedger as $st) {
    if ($st['staff_type'] === 'Engineer') {
        $engLabels[]    = $st['full_name'];
        $engActive[]    = (int)$st['active_orders'];
        $engCompleted[] = (int)$st['completed_orders'];
    }
}

// 5. ТОП виконаних послуг (Applied_Services)
$appWhere = [];
$appParams = [];
if ($dateFrom !== '') {
    $appWhere[] = "DATE(a.start_date) >= :date_from";
    $appParams['date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $appWhere[] = "DATE(a.start_date) <= :date_to";
    $appParams['date_to'] = $dateTo;
}
$appWhereSql = count($appWhere) > 0 ? 'WHERE ' . implode(' AND ', $appWhere) : '';

$topSrvStmt = $pdo->prepare("
    SELECT 
        s.service_name,
        COUNT(a.applied_service_id) AS times_used,
        SUM(CASE WHEN a.result_status = 'Successful' THEN 1 ELSE 0 END) AS success_cnt,
        COALESCE(SUM(CASE WHEN a.result_status != 'Failed_Needs_Another_Service' THEN s.base_price ELSE 0 END), 0) AS total_billed
    FROM Applied_Services a
    JOIN Services s ON a.service_id = s.service_id
    $appWhereSql
    GROUP BY s.service_id
    ORDER BY times_used DESC, total_billed DESC
    LIMIT 10
");
$topSrvStmt->execute($appParams);
$topServices = $topSrvStmt->fetchAll();

// 6. Знеособлений фінансовий реєстр замовлень (тільки перегляд, без імені клієнта та моделі пристрою)
$payOrdersStmt = $pdo->prepare("
    SELECT 
        o.order_id,
        o.order_date,
        o.order_status,
        o.total_cost,
        o.payment_status,
        o.payment_method,
        c.client_type
    FROM Orders o
    JOIN Clients c ON o.client_id = c.client_id
    $orderWhereSql
    ORDER BY o.order_id DESC
    LIMIT 15
");
$payOrdersStmt->execute($orderParams);
$paymentOrders = $payOrdersStmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix Admin - Accounting & Reports</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=3">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
    <div class="container min-page-height">
        <?php include '../includes/admin_header.php'; ?>

        <h1 class="page-title">Accounting, Payroll & Financial Analytics</h1>
        <p class="page-subtitle">Read-only financial ledger, employee payroll summary, and service revenue statistics</p>

        <div class="admin-section-box">
            <h3>Filter Financial Period</h3>
            <form action="reports.php" method="GET" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group">
                    <label>Date From</label>
                    <input type="date" name="date_from" value="<?= htmlspecialchars($dateFrom) ?>">
                </div>
                <div class="form-group">
                    <label>Date To</label>
                    <input type="date" name="date_to" value="<?= htmlspecialchars($dateTo) ?>">
                </div>
                <div style="display: flex; gap: 10px;">
                    <button type="submit" class="btn-primary">Filter Period</button>
                    <a href="reports.php" class="btn-secondary">All Time</a>
                </div>
            </form>
        </div>

        <div class="admin-stats">
            <div class="stat-card">
                <h3 style="color: #28a745;">$<?= number_format($paidRevenue, 2) ?></h3>
                <p>Paid Revenue (Collected)</p>
            </div>
            <div class="stat-card">
                <h3 style="color: #e67e22;">$<?= number_format($unpaidRevenue, 2) ?></h3>
                <p>Unpaid / Awaiting Payment</p>
            </div>
            <div class="stat-card">
                <h3>$<?= number_format($activePayroll, 2) ?></h3>
                <p>Monthly Base Payroll (Active)</p>
            </div>
            <div class="stat-card">
                <h3>$<?= number_format($avgOrder, 2) ?></h3>
                <p>Average Check (<?= $completedCount ?> closed)</p>
            </div>
        </div>

        <!-- 1. ЗНЕОСОБЛЕНИЙ ФІНАНСОВИЙ РЕЄСТР ОПЛАТ (ТІЛЬКИ ПЕРЕГЛЯД) -->
        <div class="admin-section-box">
            <h3>Order Financial Registry (Read-Only)</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Order ID</th>
                            <th>Order Date</th>
                            <th>Client Segment</th>
                            <th>Order Status</th>
                            <th>Amount ($)</th>
                            <th>Payment Status</th>
                            <th>Payment Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($paymentOrders) > 0): ?>
                            <?php foreach ($paymentOrders as $po): ?>
                                <tr>
                                    <td><strong>#<?= $po['order_id'] ?></strong></td>
                                    <td><?= date('Y-m-d', strtotime($po['order_date'])) ?></td>
                                    <td><?= htmlspecialchars($po['client_type']) ?></td>
                                    <td>
                                        <span class="status-badge status-<?= htmlspecialchars($po['order_status']) ?>">
                                            <?= htmlspecialchars(str_replace('_', ' ', $po['order_status'])) ?>
                                        </span>
                                    </td>
                                    <td><strong>$<?= number_format($po['total_cost'], 2) ?></strong></td>
                                    <td>
                                        <span class="status-badge <?= $po['payment_status'] === 'Paid' ? 'status-Completed' : 'status-Accepted' ?>">
                                            <?= htmlspecialchars($po['payment_status']) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?= $po['payment_method'] ? htmlspecialchars(str_replace('_', ' ', $po['payment_method'])) : '<span class="sub-text">—</span>' ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="7" style="text-align: center; color: #837a9c;">No orders found in this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- 2. ДЕТАЛЬНИЙ ЗАРПЛАТНИЙ РЕЄСТР ПРАЦІВНИКІВ -->
        <div class="admin-section-box">
            <h3>Staff Payroll & Performance Ledger</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Employee</th>
                            <th>Category / Position</th>
                            <th>Work Status</th>
                            <th>Base Salary ($)</th>
                            <th>Closed Orders</th>
                            <th>5% Repair Bonus ($)</th>
                            <th>Total Payout ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        $totalBaseSum = 0;
                        $totalBonusSum = 0;
                        $totalPayoutSum = 0;
                        ?>
                        <?php foreach ($staffLedger as $st): ?>
                            <?php
                            $baseSal = (float)$st['base_salary'];
                            $bonus   = ($st['staff_type'] === 'Engineer') ? round((float)$st['earned_total'] * 0.05, 2) : 0.0;
                            $payout  = ($st['work_status'] === 'Active') ? ($baseSal + $bonus) : 0.0;

                            if ($st['work_status'] === 'Active') {
                                $totalBaseSum   += $baseSal;
                                $totalBonusSum  += $bonus;
                                $totalPayoutSum += $payout;
                            }
                            ?>
                            <tr>
                                <td><strong>#<?= $st['employee_id'] ?></strong></td>
                                <td><strong><?= htmlspecialchars($st['full_name']) ?></strong></td>
                                <td><?= htmlspecialchars($st['staff_type']) ?> <br><small class="sub-text"><?= htmlspecialchars($st['position']) ?></small></td>
                                <td>
                                    <span class="status-badge <?= $st['work_status'] === 'Active' ? 'status-Ready' : 'status-Accepted' ?>">
                                        <?= htmlspecialchars(str_replace('_', ' ', $st['work_status'])) ?>
                                    </span>
                                </td>
                                <td>$<?= number_format($baseSal, 2) ?></td>
                                <td><?= $st['staff_type'] === 'Engineer' ? $st['completed_orders'] . ' ($' . number_format($st['earned_total'], 2) . ')' : '-' ?></td>
                                <td>$<?= number_format($bonus, 2) ?></td>
                                <td><strong>$<?= number_format($payout, 2) ?></strong></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr style="background-color: #f4f2ff; font-weight: 700;">
                            <td colspan="4" style="text-align: right;">Total Active Payroll Summary:</td>
                            <td>$<?= number_format($totalBaseSum, 2) ?></td>
                            <td>-</td>
                            <td>$<?= number_format($totalBonusSum, 2) ?></td>
                            <td style="color: #705DFB; font-size: 15px;">$<?= number_format($totalPayoutSum, 2) ?></td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <!-- 3. ГРАФІКИ АНАЛІТИКИ -->
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;">
            <div class="admin-section-box" style="margin-bottom: 0;">
                <h3>Orders by Status</h3>
                <div style="max-width: 260px; margin: 10px auto;">
                    <canvas id="orderStatusChart"></canvas>
                </div>
            </div>

            <div class="admin-section-box" style="margin-bottom: 0;">
                <h3>Revenue by Device Type ($)</h3>
                <div style="height: 250px; display: flex; align-items: center;">
                    <canvas id="categoryRevenueChart"></canvas>
                </div>
            </div>

            <div class="admin-section-box" style="margin-bottom: 0;">
                <h3>Engineer Workload (Orders)</h3>
                <div style="height: 250px; display: flex; align-items: center;">
                    <canvas id="engineerWorkloadChart"></canvas>
                </div>
            </div>
        </div>

        <!-- 4. ТОП ПОСЛУГ -->
        <div class="admin-section-box">
            <h3>TOP Applied Repair Services</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>Service Name</th>
                            <th>Times Applied</th>
                            <th>Successful</th>
                            <th>Total Billed ($)</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($topServices) > 0): ?>
                            <?php foreach ($topServices as $ts): ?>
                                <tr>
                                    <td><strong><?= htmlspecialchars($ts['service_name']) ?></strong></td>
                                    <td><?= $ts['times_used'] ?></td>
                                    <td><?= $ts['success_cnt'] ?></td>
                                    <td><strong>$<?= number_format($ts['total_billed'], 2) ?></strong></td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="4" style="text-align: center; color: #837a9c;">No applied services recorded in this period.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <footer class="admin-footer">
        <div class="container">
            <p>&copy; 2026 CyberFix Technical Service Management. All rights reserved.</p>
        </div>
    </footer>

    <script>
    new Chart(document.getElementById('orderStatusChart'), {
        type: 'doughnut',
        data: {
            labels: <?= json_encode($statusLabels) ?>,
            datasets: [{
                data: <?= json_encode($statusCounts) ?>,
                backgroundColor: ['#705DFB', '#9E90FF', '#4BC0C0', '#FFCE56', '#28A745'],
                borderWidth: 2,
                borderColor: '#ffffff'
            }]
        },
        options: {
            responsive: true,
            plugins: { legend: { position: 'bottom' } }
        }
    });

    new Chart(document.getElementById('categoryRevenueChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($catLabels) ?>,
            datasets: [{
                label: 'Revenue ($)',
                data: <?= json_encode($catSums) ?>,
                backgroundColor: '#705DFB',
                borderRadius: 6
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: { legend: { display: false } },
            scales: { y: { beginAtZero: true } }
        }
    });

    new Chart(document.getElementById('engineerWorkloadChart'), {
        type: 'bar',
        data: {
            labels: <?= json_encode($engLabels) ?>,
            datasets: [
                { label: 'Completed', data: <?= json_encode($engCompleted) ?>, backgroundColor: '#28A745', borderRadius: 4 },
                { label: 'Active', data: <?= json_encode($engActive) ?>, backgroundColor: '#9E90FF', borderRadius: 4 }
            ]
        },
        options: {
            indexAxis: 'y',
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                x: { stacked: true, beginAtZero: true, ticks: { stepSize: 1 } },
                y: { stacked: true }
            },
            plugins: { legend: { position: 'bottom' } }
        }
    });
    </script>
</body>
</html>