<?php
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../config/db.php';

checkAccess(['Superadmin', 'Operator', 'Engineer']);

$currentRole = strtolower(trim($_SESSION['admin_role'] ?? ''));
$isSuperadmin = ($currentRole === 'superadmin');
$canManageDevices = in_array($currentRole, ['superadmin', 'operator'], true);

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_device'])) {
    if (!$isSuperadmin) {
        setFlash('error', 'Access denied: only Superadmin can delete devices.');
        header('Location: devices.php');
        exit;
    }

    $deviceId = (int)$_POST['device_id'];

    if ($deviceId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Devices WHERE device_id = :id");
            $stmt->execute(['id' => $deviceId]);
            setFlash('success', 'Device deleted successfully!');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete device: it is linked to service orders or recorded issues!');
        }
    }
    header('Location: devices.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_device'])) {
    if (!$canManageDevices) {
        setFlash('error', 'Access denied: you do not have permission to add devices.');
        header('Location: devices.php');
        exit;
    }

    $clientId = (int)$_POST['client_id'];
    $typeId   = (int)$_POST['type_id'];
    $brand    = trim($_POST['brand']);
    $model    = trim($_POST['model']);
    $serial   = trim($_POST['serial_number']);

    $stmt = $pdo->prepare("INSERT INTO Devices (client_id, type_id, brand, model, serial_number, first_service_date) VALUES (:client_id, :type_id, :brand, :model, :serial, NOW())");
    if ($stmt->execute(['client_id' => $clientId, 'type_id' => $typeId, 'brand' => $brand, 'model' => $model, 'serial' => $serial])) {
        setFlash('success', "Device '{$brand} {$model}' added successfully!");
    }
    header('Location: devices.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_device'])) {
    if (!$canManageDevices) {
        setFlash('error', 'Access denied: you do not have permission to edit devices.');
        header('Location: devices.php');
        exit;
    }

    $deviceId = (int)$_POST['device_id'];
    $brand    = trim($_POST['brand']);
    $model    = trim($_POST['model']);
    $serial   = trim($_POST['serial_number']);
    $typeId   = (int)$_POST['type_id'];

    $stmt = $pdo->prepare("UPDATE Devices SET brand = :brand, model = :model, serial_number = :serial, type_id = :type_id WHERE device_id = :id");
    if ($stmt->execute(['brand' => $brand, 'model' => $model, 'serial' => $serial, 'type_id' => $typeId, 'id' => $deviceId])) {
        setFlash('success', "Device #{$deviceId} updated successfully!");
    }
    header('Location: devices.php');
    exit;
}

// GET-фільтри та пошук
$searchQ     = trim($_GET['q'] ?? '');
$filterKind  = trim($_GET['device_kind'] ?? '');
$filterBrand = trim($_GET['brand'] ?? '');

$whereClauses = [];
$params = [];

if ($searchQ !== '') {
    $whereClauses[] = "(d.serial_number LIKE :q_sn OR d.model LIKE :q_model OR d.brand LIKE :q_brand OR c.name LIKE :q_owner)";
    $params['q_sn']    = '%' . $searchQ . '%';
    $params['q_model'] = '%' . $searchQ . '%';
    $params['q_brand'] = '%' . $searchQ . '%';
    $params['q_owner'] = '%' . $searchQ . '%';
}

if (in_array($filterKind, ['Laptop', 'Desktop PC', 'Workstation', 'Server'], true)) {
    $whereClauses[] = "dt.device_kind = :dkind";
    $params['dkind'] = $filterKind;
}

if ($filterBrand !== '') {
    $whereClauses[] = "d.brand = :brand";
    $params['brand'] = $filterBrand;
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Пагінація (по 5 записів)
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Devices d
    JOIN Clients c ON d.client_id = c.client_id
    JOIN Device_Types dt ON d.type_id = dt.type_id
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$devicesStmt = $pdo->prepare("
    SELECT d.*, c.name AS owner_name, dt.device_kind, dt.service_category
    FROM Devices d
    JOIN Clients c ON d.client_id = c.client_id
    JOIN Device_Types dt ON d.type_id = dt.type_id
    $whereSql
    ORDER BY d.device_id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $devicesStmt->bindValue(':' . $key, $val);
}
$devicesStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$devicesStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$devicesStmt->execute();
$devices = $devicesStmt->fetchAll();

$brandsList  = $pdo->query("SELECT DISTINCT brand FROM Devices ORDER BY brand ASC")->fetchAll(PDO::FETCH_COLUMN);
$clients     = $pdo->query("SELECT client_id, name FROM Clients ORDER BY name ASC")->fetchAll();
$deviceTypes = $pdo->query("SELECT type_id, CONCAT(device_kind, ' (', service_category, ')') AS category_info FROM Device_Types")->fetchAll();

$editDevice = null;
if ($canManageDevices && isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM Devices WHERE device_id = :id");
    $stmt->execute(['id' => $editId]);
    $editDevice = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix Admin - Devices</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=3">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body>
    <div class="container min-page-height">
        <?php include '../includes/admin_header.php'; ?>

        <h1 class="page-title">Hardware Devices</h1>
        <p class="page-subtitle">Track registered laptops, PCs, workstations, and servers</p>

        <?php renderFlash(); ?>

        <!-- 1. БЛОК ФІЛЬТРІВ -->
        <div class="admin-section-box">
            <h3>Filter Devices</h3>
            <form action="devices.php" method="GET" id="filterForm">
                <input type="hidden" name="q" id="filterHiddenQ" value="<?= htmlspecialchars($searchQ) ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Device Type</label>
                        <select name="device_kind" id="filterDeviceKind">
                            <option value="">-- All Types --</option>
                            <option value="Laptop" <?= $filterKind === 'Laptop' ? 'selected' : '' ?>>Laptop</option>
                            <option value="Desktop PC" <?= $filterKind === 'Desktop PC' ? 'selected' : '' ?>>Desktop PC</option>
                            <option value="Workstation" <?= $filterKind === 'Workstation' ? 'selected' : '' ?>>Workstation</option>
                            <option value="Server" <?= $filterKind === 'Server' ? 'selected' : '' ?>>Server</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <select name="brand" id="filterBrand">
                            <option value="">-- All Brands --</option>
                            <?php foreach ($brandsList as $br): ?>
                                <option value="<?= htmlspecialchars($br) ?>" <?= $filterBrand === $br ? 'selected' : '' ?>>
                                    <?= htmlspecialchars($br) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a href="devices.php" class="btn-secondary">Reset All</a>
                </div>
            </form>
        </div>

        <!-- 2. МИТТЄВИЙ ПОШУК (AJAX) -->
        <div class="admin-section-box">
            <h3>Instant Device Search</h3>
            <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 260px; margin-bottom: 0;">
                    <label>Search by Serial Number, Model, Brand or Owner</label>
                    <input type="text" id="liveSearchInput" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Start typing SN-..., ROG, Olena..." autocomplete="off">
                </div>
                <button type="button" id="clearSearchBtn" class="btn-secondary" style="<?= $searchQ === '' ? 'display: none;' : '' ?>">Clear Search</button>
            </div>
        </div>

        <!-- 3. ТАБЛИЦЯ РЕЗУЛЬТАТІВ -->
        <div class="admin-section-box" id="devicesResultsContainer">
            <h3>Registered Devices (Found: <?= $totalRows ?>)</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Brand & Model</th>
                            <th>Serial Number</th>
                            <th>Owner</th>
                            <th>Category</th>
                            <th>First Service</th>
                            <?php if ($canManageDevices): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($devices) > 0): ?>
                            <?php foreach ($devices as $dv): ?>
                                <tr>
                                    <td><strong>#<?= $dv['device_id'] ?></strong></td>
                                    <td><?= htmlspecialchars($dv['brand'] . ' ' . $dv['model']) ?></td>
                                    <td><code><?= htmlspecialchars($dv['serial_number']) ?></code></td>
                                    <td><?= htmlspecialchars($dv['owner_name']) ?></td>
                                    <td><?= htmlspecialchars($dv['device_kind']) ?> (<?= htmlspecialchars($dv['service_category']) ?>)</td>
                                    <td><?= htmlspecialchars(date('Y-m-d', strtotime($dv['first_service_date']))) ?></td>
                                    <?php if ($canManageDevices): ?>
                                    <td>
                                        <a href="devices.php?edit_id=<?= $dv['device_id'] ?>" class="btn-primary btn-sm">Edit</a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $canManageDevices ? 7 : 6 ?>" style="text-align: center; color: #837a9c;">No devices match your filter criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php renderPagination($page, $totalPages, $_GET); ?>
        </div>

        <?php if ($canManageDevices): ?>
        <div class="admin-section-box">
            <h3>Add New Device</h3>
            <form action="devices.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Owner Client</label>
                        <select name="client_id" required>
                            <?php foreach ($clients as $cl): ?>
                                <option value="<?= $cl['client_id'] ?>"><?= htmlspecialchars($cl['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Device Type</label>
                        <select name="type_id" required>
                            <?php foreach ($deviceTypes as $dt): ?>
                                <option value="<?= $dt['type_id'] ?>"><?= htmlspecialchars($dt['category_info']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Brand</label>
                        <input type="text" name="brand" required placeholder="e.g. ASUS">
                    </div>
                    <div class="form-group">
                        <label>Model</label>
                        <input type="text" name="model" required placeholder="e.g. Zephyrus G14">
                    </div>
                    <div class="form-group">
                        <label>Serial Number</label>
                        <input type="text" name="serial_number" required placeholder="e.g. SN-4412901">
                    </div>
                </div>
                <button type="submit" name="add_device" class="btn-primary mt-20">Add Device</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($editDevice): ?>
        <div class="modal-overlay active">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Device #<?= $editDevice['device_id'] ?></h3>
                    <a href="devices.php" class="close-btn">&times;</a>
                </div>
                <form action="devices.php" method="POST">
                    <input type="hidden" name="device_id" value="<?= $editDevice['device_id'] ?>">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Brand</label>
                            <input type="text" name="brand" value="<?= htmlspecialchars($editDevice['brand']) ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Model</label>
                            <input type="text" name="model" value="<?= htmlspecialchars($editDevice['model']) ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Serial Number</label>
                            <input type="text" name="serial_number" value="<?= htmlspecialchars($editDevice['serial_number']) ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Category</label>
                            <select name="type_id">
                                <?php foreach ($deviceTypes as $dt): ?>
                                    <option value="<?= $dt['type_id'] ?>" <?= $editDevice['type_id'] == $dt['type_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($dt['category_info']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="btn-group mt-20" style="justify-content: space-between; align-items: center;">
                        <?php if ($isSuperadmin): ?>
                        <button type="submit" name="delete_device" class="btn-danger" onclick="return confirm('Are you sure you want to delete this device?');">Delete Device</button>
                        <?php else: ?>
                        <span></span>
                        <?php endif; ?>

                        <div style="display: flex; gap: 10px;">
                            <a href="devices.php" class="btn-secondary">Cancel</a>
                            <button type="submit" name="update_device" class="btn-primary">Save Changes</button>
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
const resultsContainer = document.getElementById('devicesResultsContainer');
const filterHiddenQ = document.getElementById('filterHiddenQ');

let activeController = null;
let debounceTimer = null;

function fetchDevicesResults(queryVal) {
    if (activeController) {
        activeController.abort();
    }
    activeController = new AbortController();

    const kindVal  = document.getElementById('filterDeviceKind')?.value || '';
    const brandVal = document.getElementById('filterBrand')?.value || '';

    if (filterHiddenQ) filterHiddenQ.value = queryVal;
    if (clearBtn) clearBtn.style.display = queryVal.length > 0 ? 'inline-block' : 'none';

    const params = new URLSearchParams();
    if (queryVal !== '') params.set('q', queryVal);
    if (kindVal !== '') params.set('device_kind', kindVal);
    if (brandVal !== '') params.set('brand', brandVal);

    const newUrl = 'devices.php' + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);

    fetch(newUrl, { signal: activeController.signal })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBox = doc.getElementById('devicesResultsContainer');
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
            fetchDevicesResults(val);
        }, 200);
    });
}

if (clearBtn && searchInput) {
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        fetchDevicesResults('');
    });
}
</script>
</body>
</html>