<?php
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../config/db.php';

checkAccess(['Superadmin', 'Operator']);

$isSuperadmin = (strtolower(trim($_SESSION['admin_role'] ?? '')) === 'superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_client'])) {
    if (!$isSuperadmin) {
        setFlash('error', 'Access denied: only Superadmin can delete clients.');
        header('Location: clients.php');
        exit;
    }

    $clientId = (int)$_POST['client_id'];

    if ($clientId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Clients WHERE client_id = :id");
            $stmt->execute(['id' => $clientId]);
            setFlash('success', 'Client deleted successfully!');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete client: they have registered devices or service orders!');
        }
    }
    header('Location: clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_client'])) {
    $type  = $_POST['client_type'];
    $name  = trim($_POST['name']);
    $phone = trim($_POST['phone_number']);
    $email = trim($_POST['email']);

    $stmt = $pdo->prepare("INSERT INTO Clients (client_type, name, phone_number, email, registration_date) VALUES (:type, :name, :phone, :email, NOW())");
    if ($stmt->execute(['type' => $type, 'name' => $name, 'phone' => $phone, 'email' => $email])) {
        setFlash('success', "Client '{$name}' added successfully!");
    }
    header('Location: clients.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_client'])) {
    $clientId = (int)$_POST['client_id'];
    $type     = $_POST['client_type'];
    $name     = trim($_POST['name']);
    $phone    = trim($_POST['phone_number']);
    $email    = trim($_POST['email']);

    $stmt = $pdo->prepare("UPDATE Clients SET client_type = :type, name = :name, phone_number = :phone, email = :email WHERE client_id = :id");
    if ($stmt->execute(['type' => $type, 'name' => $name, 'phone' => $phone, 'email' => $email, 'id' => $clientId])) {
        setFlash('success', "Client #{$clientId} updated successfully!");
    }
    header('Location: clients.php');
    exit;
}

// GET-фільтри та пошук
$searchQ    = trim($_GET['q'] ?? '');
$filterType = trim($_GET['client_type'] ?? '');
$dateFrom   = trim($_GET['date_from'] ?? '');
$dateTo     = trim($_GET['date_to'] ?? '');
$minOrders  = (int)($_GET['min_orders'] ?? 0);

$whereClauses = [];
$params = [];

if ($searchQ !== '') {
    $whereClauses[] = "(c.name LIKE :q_name OR c.phone_number LIKE :q_phone OR c.email LIKE :q_email)";
    $params['q_name']  = '%' . $searchQ . '%';
    $params['q_phone'] = '%' . $searchQ . '%';
    $params['q_email'] = '%' . $searchQ . '%';
}

if (in_array($filterType, ['Individual', 'Corporate'], true)) {
    $whereClauses[] = "c.client_type = :ctype";
    $params['ctype'] = $filterType;
}

if ($dateFrom !== '') {
    $whereClauses[] = "DATE(c.registration_date) >= :date_from";
    $params['date_from'] = $dateFrom;
}

if ($dateTo !== '') {
    $whereClauses[] = "DATE(c.registration_date) <= :date_to";
    $params['date_to'] = $dateTo;
}

$whereSql  = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';
$havingSql = $minOrders > 0 ? 'HAVING COUNT(o.order_id) >= :min_orders' : '';

// Пагінація (по 5 записів)
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("
    SELECT COUNT(*) FROM (
        SELECT c.client_id
        FROM Clients c
        LEFT JOIN Orders o ON c.client_id = o.client_id
        $whereSql
        GROUP BY c.client_id
        $havingSql
    ) AS sub
");
foreach ($params as $key => $val) {
    $countStmt->bindValue(':' . $key, $val);
}
if ($minOrders > 0) {
    $countStmt->bindValue(':min_orders', $minOrders, PDO::PARAM_INT);
}
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$clientsStmt = $pdo->prepare("
    SELECT c.*, COUNT(o.order_id) AS orders_count
    FROM Clients c
    LEFT JOIN Orders o ON c.client_id = o.client_id
    $whereSql
    GROUP BY c.client_id
    $havingSql
    ORDER BY c.client_id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $clientsStmt->bindValue(':' . $key, $val);
}
if ($minOrders > 0) {
    $clientsStmt->bindValue(':min_orders', $minOrders, PDO::PARAM_INT);
}
$clientsStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$clientsStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$clientsStmt->execute();
$clients = $clientsStmt->fetchAll();

$editClient = null;
if (isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM Clients WHERE client_id = :id");
    $stmt->execute(['id' => $editId]);
    $editClient = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix Admin - Clients</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=3">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body>
    <div class="container min-page-height">
        <?php include '../includes/admin_header.php'; ?>

        <h1 class="page-title">Clients Directory</h1>
        <p class="page-subtitle">Manage registered individual and corporate customers</p>

        <?php renderFlash(); ?>

        <!-- 1. БЛОК ФІЛЬТРІВ -->
        <div class="admin-section-box">
            <h3>Filter Clients</h3>
            <form action="clients.php" method="GET" id="filterForm">
                <input type="hidden" name="q" id="filterHiddenQ" value="<?= htmlspecialchars($searchQ) ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Client Type</label>
                        <select name="client_type" id="filterClientType">
                            <option value="">-- All Types --</option>
                            <option value="Individual" <?= $filterType === 'Individual' ? 'selected' : '' ?>>Individual</option>
                            <option value="Corporate" <?= $filterType === 'Corporate' ? 'selected' : '' ?>>Corporate</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Min Orders Count</label>
                        <input type="number" min="0" name="min_orders" id="filterMinOrders" value="<?= $minOrders > 0 ? $minOrders : '' ?>" placeholder="e.g. 1">
                    </div>
                    <div class="form-group">
                        <label>Registered From</label>
                        <input type="date" name="date_from" id="filterDateFrom" value="<?= htmlspecialchars($dateFrom) ?>">
                    </div>
                    <div class="form-group">
                        <label>Registered To</label>
                        <input type="date" name="date_to" id="filterDateTo" value="<?= htmlspecialchars($dateTo) ?>">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a href="clients.php" class="btn-secondary">Reset All</a>
                </div>
            </form>
        </div>

        <!-- 2. МИТТЄВИЙ ПОШУК (AJAX) -->
        <div class="admin-section-box">
            <h3>Instant Client Search</h3>
            <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 260px; margin-bottom: 0;">
                    <label>Search by Name, Phone or Email</label>
                    <input type="text" id="liveSearchInput" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Start typing name, +380... or email..." autocomplete="off">
                </div>
                <button type="button" id="clearSearchBtn" class="btn-secondary" style="<?= $searchQ === '' ? 'display: none;' : '' ?>">Clear Search</button>
            </div>
        </div>

        <!-- 3. ТАБЛИЦЯ РЕЗУЛЬТАТІВ -->
        <div class="admin-section-box" id="clientsResultsContainer">
            <h3>Registered Clients (Found: <?= $totalRows ?>)</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Type</th>
                            <th>Full Name / Company</th>
                            <th>Phone</th>
                            <th>Email</th>
                            <th>Orders</th>
                            <th>Registration Date</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($clients) > 0): ?>
                            <?php foreach ($clients as $cl): ?>
                                <tr>
                                    <td><strong>#<?= $cl['client_id'] ?></strong></td>
                                    <td><?= htmlspecialchars($cl['client_type']) ?></td>
                                    <td><?= htmlspecialchars($cl['name']) ?></td>
                                    <td><?= htmlspecialchars($cl['phone_number']) ?></td>
                                    <td><?= htmlspecialchars($cl['email'] ?: '-') ?></td>
                                    <td><span class="status-badge status-Ready"><?= (int)$cl['orders_count'] ?></span></td>
                                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($cl['registration_date']))) ?></td>
                                    <td>
                                        <a href="clients.php?edit_id=<?= $cl['client_id'] ?>" class="btn-primary btn-sm">Edit</a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="8" style="text-align: center; color: #837a9c;">No clients match your filter criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php renderPagination($page, $totalPages, $_GET); ?>
        </div>

        <div class="admin-section-box">
            <h3>Register New Client</h3>
            <form action="clients.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Client Type</label>
                        <select name="client_type">
                            <option value="Individual">Individual</option>
                            <option value="Corporate">Corporate</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Full Name or Company Name</label>
                        <input type="text" name="name" required placeholder="e.g. Olena Kovalenko">
                    </div>
                    <div class="form-group">
                        <label>Phone Number</label>
                        <input type="text" name="phone_number" required placeholder="e.g. +380971112233">
                    </div>
                    <div class="form-group">
                        <label>Email</label>
                        <input type="email" name="email" placeholder="e.g. olena@gmail.com">
                    </div>
                </div>
                <button type="submit" name="add_client" class="btn-primary mt-20">Add Client</button>
            </form>
        </div>
    </div>

    <?php if ($editClient): ?>
        <div class="modal-overlay active">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Client #<?= $editClient['client_id'] ?></h3>
                    <a href="clients.php" class="close-btn">&times;</a>
                </div>
                <form action="clients.php" method="POST">
                    <input type="hidden" name="client_id" value="<?= $editClient['client_id'] ?>">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Client Type</label>
                            <select name="client_type">
                                <option value="Individual" <?= $editClient['client_type'] === 'Individual' ? 'selected' : '' ?>>Individual</option>
                                <option value="Corporate" <?= $editClient['client_type'] === 'Corporate' ? 'selected' : '' ?>>Corporate</option>
                            </select>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Name</label>
                            <input type="text" name="name" value="<?= htmlspecialchars($editClient['name']) ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Phone Number</label>
                            <input type="text" name="phone_number" value="<?= htmlspecialchars($editClient['phone_number']) ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Email</label>
                            <input type="email" name="email" value="<?= htmlspecialchars($editClient['email']) ?>">
                        </div>
                    </div>
                    <div class="btn-group mt-20" style="justify-content: space-between; align-items: center;">
                        <?php if ($isSuperadmin): ?>
                        <button type="submit" name="delete_client" class="btn-danger" onclick="return confirm('Are you sure you want to delete this client?');">Delete Client</button>
                        <?php else: ?>
                        <span></span>
                        <?php endif; ?>

                        <div style="display: flex; gap: 10px;">
                            <a href="clients.php" class="btn-secondary">Cancel</a>
                            <button type="submit" name="update_client" class="btn-primary">Save Changes</button>
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
const searchInput = document.getElementById('liveSearchInput');
const clearBtn = document.getElementById('clearSearchBtn');
const resultsContainer = document.getElementById('clientsResultsContainer');
const filterHiddenQ = document.getElementById('filterHiddenQ');

let activeController = null;
let debounceTimer = null;

function fetchClientsResults(queryVal) {
    if (activeController) {
        activeController.abort();
    }
    activeController = new AbortController();

    const typeVal      = document.getElementById('filterClientType')?.value || '';
    const minOrdersVal = document.getElementById('filterMinOrders')?.value || '';
    const dateFromVal  = document.getElementById('filterDateFrom')?.value || '';
    const dateToVal    = document.getElementById('filterDateTo')?.value || '';

    if (filterHiddenQ) filterHiddenQ.value = queryVal;
    if (clearBtn) clearBtn.style.display = queryVal.length > 0 ? 'inline-block' : 'none';

    const params = new URLSearchParams();
    if (queryVal !== '') params.set('q', queryVal);
    if (typeVal !== '') params.set('client_type', typeVal);
    if (minOrdersVal !== '' && parseInt(minOrdersVal, 10) > 0) params.set('min_orders', minOrdersVal);
    if (dateFromVal !== '') params.set('date_from', dateFromVal);
    if (dateToVal !== '') params.set('date_to', dateToVal);

    const newUrl = 'clients.php' + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);

    fetch(newUrl, { signal: activeController.signal })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBox = doc.getElementById('clientsResultsContainer');
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
            fetchClientsResults(val);
        }, 200);
    });
}

if (clearBtn && searchInput) {
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        fetchClientsResults('');
    });
}
</script>
</body>
</html>