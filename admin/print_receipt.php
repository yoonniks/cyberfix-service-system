<?php
require_once '../includes/auth.php';
require_once '../config/db.php';

checkAccess(['Superadmin', 'Operator', 'Engineer']);

$orderId = isset($_GET['order_id']) ? (int)$_GET['order_id'] : 0;

$stmt = $pdo->prepare("
    SELECT 
        o.order_id,
        o.device_id,
        o.order_date,
        o.completion_date,
        o.order_status,
        o.payment_status,
        o.payment_method,
        o.estimated_hours,
        o.total_cost,
        c.name AS client_name,
        c.phone_number,
        c.email,
        c.client_type,
        d.brand,
        d.model,
        d.serial_number,
        dt.device_kind,
        dt.service_category,
        s.service_name,
        s.description AS service_desc,
        s.base_price AS initial_price,
        s.estimated_hours AS initial_hours,
        CONCAT(e.first_name, ' ', e.last_name) AS engineer_name
    FROM Orders o
    JOIN Clients c ON o.client_id = c.client_id
    JOIN Devices d ON o.device_id = d.device_id
    LEFT JOIN Device_Types dt ON d.type_id = dt.type_id
    LEFT JOIN Services s ON o.initial_service_id = s.service_id
    LEFT JOIN Employees e ON o.lead_employee_id = e.employee_id
    WHERE o.order_id = :id
");
$stmt->execute(['id' => $orderId]);
$order = $stmt->fetch();

if (!$order) {
    die('Order not found.');
}

$appStmt = $pdo->prepare("
    SELECT 
        s.service_name,
        s.description,
        s.base_price,
        s.estimated_hours,
        a.result_status,
        it.type_name AS issue_type
    FROM Applied_Services a
    JOIN Services s ON a.service_id = s.service_id
    JOIN Device_Issues di ON a.issue_id = di.issue_id
    JOIN Issue_Types it ON di.issue_type_id = it.issue_type_id
    WHERE a.device_id = :did
      AND a.result_status != 'Failed_Needs_Another_Service'
    ORDER BY a.start_date ASC
");
$appStmt->execute(['did' => $order['device_id']]);
$appliedList = $appStmt->fetchAll();

$paymentStatus = $order['payment_status'] ?? 'Unpaid';
$paymentClass = ($paymentStatus === 'Paid') ? 'receipt-payment-paid' : 'receipt-payment-unpaid';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Receipt #<?= $order['order_id'] ?> - CyberFix</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=4">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body class="receipt-page">

<div class="receipt-actions">
    <a href="index.php" class="btn-secondary">Back to Orders</a>
    <button onclick="window.print()" class="btn-primary">Print Receipt</button>
</div>

<div class="receipt-box">
    <div class="receipt-header">
        <div class="receipt-brand">
            <img src="../assets/img/logo.png" alt="CyberFix">
            <div>
                <h1>CyberFix Service</h1>
                <small class="receipt-brand-sub">Technical Repair & Maintenance</small>
            </div>
        </div>
        <div class="receipt-meta">
            <strong>Receipt #<?= $order['order_id'] ?></strong><br>
            Created: <?= date('Y-m-d H:i', strtotime($order['order_date'])) ?><br>
            Status: <?= str_replace('_', ' ', $order['order_status']) ?>
        </div>
    </div>

    <div class="receipt-section-title">Client Information</div>
    <div class="receipt-grid">
        <div><span>Full Name / Company</span><strong><?= htmlspecialchars($order['client_name']) ?></strong></div>
        <div><span>Client Type</span><?= htmlspecialchars($order['client_type']) ?></div>
        <div><span>Phone Number</span><?= htmlspecialchars($order['phone_number']) ?></div>
        <div><span>Email</span><?= htmlspecialchars($order['email'] ?: '-') ?></div>
    </div>

    <div class="receipt-section-title">Device Information</div>
    <div class="receipt-grid">
        <div><span>Device</span><strong><?= htmlspecialchars($order['brand'] . ' ' . $order['model']) ?></strong></div>
        <div><span>Category</span><?= htmlspecialchars($order['device_kind'] ?? '-') ?> (<?= htmlspecialchars($order['service_category'] ?? '-') ?>)</div>
        <div><span>Serial Number</span><code><?= htmlspecialchars($order['serial_number'] ?: 'N/A') ?></code></div>
        <div><span>Assigned Engineer</span><?= htmlspecialchars($order['engineer_name'] ?? 'Unassigned') ?></div>
    </div>

    <div class="receipt-section-title">Services Performed</div>
    <table class="receipt-table">
        <thead>
            <tr>
                <th>Service & Issue</th>
                <th>Est. Time</th>
                <th class="text-right">Amount</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($order['service_name']): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($order['service_name']) ?></strong><br>
                    <small class="receipt-desc-text"><?= htmlspecialchars($order['service_desc'] ?? 'Initial service') ?></small>
                </td>
                <td><?= htmlspecialchars($order['initial_hours']) ?> hrs</td>
                <td class="text-right"><strong>$<?= number_format($order['initial_price'], 2) ?></strong></td>
            </tr>
            <?php endif; ?>

            <?php foreach ($appliedList as $item): ?>
            <tr>
                <td>
                    <strong><?= htmlspecialchars($item['service_name']) ?></strong> <small class="receipt-issue-tag">(Issue: <?= htmlspecialchars($item['issue_type']) ?>)</small><br>
                    <small class="receipt-desc-text"><?= htmlspecialchars($item['description']) ?></small>
                </td>
                <td><?= htmlspecialchars($item['estimated_hours']) ?> hrs</td>
                <td class="text-right"><strong>$<?= number_format($item['base_price'], 2) ?></strong></td>
            </tr>
            <?php endforeach; ?>

            <?php if (!$order['service_name'] && count($appliedList) === 0): ?>
            <tr>
                <td>
                    <strong>Technical Diagnostics</strong><br>
                    <small class="receipt-desc-text">Full hardware inspection and testing</small>
                </td>
                <td><?= htmlspecialchars($order['estimated_hours']) ?> hrs</td>
                <td class="text-right"><strong>$<?= number_format($order['total_cost'], 2) ?></strong></td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>

    <div class="receipt-total-row">
        Total: $<?= number_format($order['total_cost'], 2) ?>
        <div class="receipt-payment-info <?= $paymentClass ?>">
            Payment Status: <?= htmlspecialchars($paymentStatus) ?>
            <?= !empty($order['payment_method']) ? ' (' . htmlspecialchars(str_replace('_', ' ', $order['payment_method'])) . ')' : '' ?>
        </div>
    </div>

    <div class="receipt-signatures">
        <div class="receipt-sig-line">Service Representative</div>
        <div class="receipt-sig-line">Client Signature</div>
    </div>
</div>

</body>
</html>