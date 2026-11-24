<?php
require_once '../includes/auth.php';
require_once '../includes/helpers.php';
require_once '../config/db.php';

checkAccess(['Superadmin', 'Operator']);

$isSuperadmin = (strtolower(trim($_SESSION['admin_role'] ?? '')) === 'superadmin');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_service'])) {
    if (!$isSuperadmin) {
        setFlash('error', 'Access denied: only Superadmin can modify the service catalog.');
        header('Location: services.php');
        exit;
    }

    $serviceId = (int)$_POST['service_id'];

    if ($serviceId > 0) {
        try {
            $stmt = $pdo->prepare("DELETE FROM Services WHERE service_id = :id");
            $stmt->execute(['id' => $serviceId]);
            setFlash('success', 'Service deleted successfully!');
        } catch (PDOException $e) {
            setFlash('error', 'Cannot delete service: it is linked to existing orders!');
        }
    }
    header('Location: services.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_service'])) {
    if (!$isSuperadmin) {
        setFlash('error', 'Access denied: only Superadmin can modify the service catalog.');
        header('Location: services.php');
        exit;
    }

    $name    = trim($_POST['service_name']);
    $desc    = trim($_POST['description']);
    $price   = (float)$_POST['base_price'];
    $hours   = (float)$_POST['estimated_hours'];
    $typeId  = (int)$_POST['type_id'];
    $visible = isset($_POST['is_visible_on_website']) ? 1 : 0;

    $stmt = $pdo->prepare("INSERT INTO Services (type_id, service_name, description, base_price, estimated_hours, is_visible_on_website) VALUES (:type_id, :name, :desc, :price, :hours, :visible)");
    if ($stmt->execute(['type_id' => $typeId, 'name' => $name, 'desc' => $desc, 'price' => $price, 'hours' => $hours, 'visible' => $visible])) {
        setFlash('success', "Service '{$name}' added successfully!");
    }
    header('Location: services.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_service'])) {
    if (!$isSuperadmin) {
        setFlash('error', 'Access denied: only Superadmin can modify the service catalog.');
        header('Location: services.php');
        exit;
    }

    $serviceId = (int)$_POST['service_id'];
    $name      = trim($_POST['service_name']);
    $desc      = trim($_POST['description']);
    $price     = (float)$_POST['base_price'];
    $hours     = (float)$_POST['estimated_hours'];
    $visible   = isset($_POST['is_visible_on_website']) ? 1 : 0;

    $stmt = $pdo->prepare("UPDATE Services SET service_name = :name, description = :desc, base_price = :price, estimated_hours = :hours, is_visible_on_website = :visible WHERE service_id = :id");
    if ($stmt->execute(['name' => $name, 'desc' => $desc, 'price' => $price, 'hours' => $hours, 'visible' => $visible, 'id' => $serviceId])) {
        setFlash('success', "Service #{$serviceId} updated successfully!");
    }
    header('Location: services.php');
    exit;
}

// GET-фільтри та пошук
$searchQ        = trim($_GET['q'] ?? '');
$filterCategory = trim($_GET['service_category'] ?? '');
$filterKind     = trim($_GET['device_kind'] ?? '');
$minPrice       = isset($_GET['min_price']) && $_GET['min_price'] !== '' ? (float)$_GET['min_price'] : null;
$maxPrice       = isset($_GET['max_price']) && $_GET['max_price'] !== '' ? (float)$_GET['max_price'] : null;

$whereClauses = [];
$params = [];

if ($searchQ !== '') {
    $whereClauses[] = "(s.service_name LIKE :q_name OR s.description LIKE :q_desc)";
    $params['q_name'] = '%' . $searchQ . '%';
    $params['q_desc'] = '%' . $searchQ . '%';
}

if (in_array($filterCategory, ['Hardware Repair', 'Preventive Care', 'Software Tuning', 'Data Recovery'], true)) {
    $whereClauses[] = "dt.service_category = :scat";
    $params['scat'] = $filterCategory;
}

if (in_array($filterKind, ['Laptop', 'Desktop PC', 'Workstation', 'Server'], true)) {
    $whereClauses[] = "dt.device_kind = :dkind";
    $params['dkind'] = $filterKind;
}

if ($minPrice !== null) {
    $whereClauses[] = "s.base_price >= :min_price";
    $params['min_price'] = $minPrice;
}

if ($maxPrice !== null) {
    $whereClauses[] = "s.base_price <= :max_price";
    $params['max_price'] = $maxPrice;
}

$whereSql = count($whereClauses) > 0 ? 'WHERE ' . implode(' AND ', $whereClauses) : '';

// Пагінація (по 5 записів)
$perPage = 5;
$page = max(1, (int)($_GET['page'] ?? 1));

$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM Services s
    JOIN Device_Types dt ON s.type_id = dt.type_id
    $whereSql
");
$countStmt->execute($params);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$servicesStmt = $pdo->prepare("
    SELECT s.*, dt.device_kind, dt.service_category
    FROM Services s
    JOIN Device_Types dt ON s.type_id = dt.type_id
    $whereSql
    ORDER BY s.service_id DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $val) {
    $servicesStmt->bindValue(':' . $key, $val);
}
$servicesStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$servicesStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$servicesStmt->execute();
$services = $servicesStmt->fetchAll();

$deviceTypes = $pdo->query("SELECT type_id, CONCAT(device_kind, ' (', service_category, ')') AS category_info FROM Device_Types")->fetchAll();

$editService = null;
if ($isSuperadmin && isset($_GET['edit_id'])) {
    $editId = (int)$_GET['edit_id'];
    $stmt = $pdo->prepare("SELECT * FROM Services WHERE service_id = :id");
    $stmt->execute(['id' => $editId]);
    $editService = $stmt->fetch();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>CyberFix Admin - Services</title>
    <link rel="stylesheet" href="../assets/css/style_admin.css?v=3">
    <link rel="icon" type="image/png" href="../assets/img/logo.png">
</head>
<body>
    <div class="container min-page-height">
        <?php include '../includes/admin_header.php'; ?>

        <h1 class="page-title">Service Catalog</h1>
        <p class="page-subtitle"><?= $isSuperadmin ? 'Configure public website services and internal procedures' : 'Search and view active service prices and procedures' ?></p>

        <?php renderFlash(); ?>

        <!-- 1. БЛОК ФІЛЬТРІВ -->
        <div class="admin-section-box">
            <h3>Filter Services</h3>
            <form action="services.php" method="GET" id="filterForm">
                <input type="hidden" name="q" id="filterHiddenQ" value="<?= htmlspecialchars($searchQ) ?>">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Service Category</label>
                        <select name="service_category" id="filterCategory">
                            <option value="">-- All Categories --</option>
                            <option value="Hardware Repair" <?= $filterCategory === 'Hardware Repair' ? 'selected' : '' ?>>Hardware Repair</option>
                            <option value="Preventive Care" <?= $filterCategory === 'Preventive Care' ? 'selected' : '' ?>>Preventive Care</option>
                            <option value="Software Tuning" <?= $filterCategory === 'Software Tuning' ? 'selected' : '' ?>>Software Tuning</option>
                            <option value="Data Recovery" <?= $filterCategory === 'Data Recovery' ? 'selected' : '' ?>>Data Recovery</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Device Type</label>
                        <select name="device_kind" id="filterDeviceKind">
                            <option value="">-- All Devices --</option>
                            <option value="Laptop" <?= $filterKind === 'Laptop' ? 'selected' : '' ?>>Laptop</option>
                            <option value="Desktop PC" <?= $filterKind === 'Desktop PC' ? 'selected' : '' ?>>Desktop PC</option>
                            <option value="Workstation" <?= $filterKind === 'Workstation' ? 'selected' : '' ?>>Workstation</option>
                            <option value="Server" <?= $filterKind === 'Server' ? 'selected' : '' ?>>Server</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Min Price ($)</label>
                        <input type="number" step="0.01" min="0" name="min_price" id="filterMinPrice" value="<?= $minPrice !== null ? htmlspecialchars((string)$minPrice) : '' ?>" placeholder="0.00">
                    </div>
                    <div class="form-group">
                        <label>Max Price ($)</label>
                        <input type="number" step="0.01" min="0" name="max_price" id="filterMaxPrice" value="<?= $maxPrice !== null ? htmlspecialchars((string)$maxPrice) : '' ?>" placeholder="500.00">
                    </div>
                </div>
                <div style="display: flex; gap: 10px; margin-top: 15px;">
                    <button type="submit" class="btn-primary">Apply Filters</button>
                    <a href="services.php" class="btn-secondary">Reset All</a>
                </div>
            </form>
        </div>

        <!-- 2. МИТТЄВИЙ ПОШУК (AJAX) -->
        <div class="admin-section-box">
            <h3>Instant Service Search</h3>
            <div style="display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap;">
                <div class="form-group" style="flex: 1; min-width: 260px; margin-bottom: 0;">
                    <label>Search by Service Name or Description</label>
                    <input type="text" id="liveSearchInput" name="q" value="<?= htmlspecialchars($searchQ) ?>" placeholder="Start typing Thermal Paste, Cleaning, SSD..." autocomplete="off">
                </div>
                <button type="button" id="clearSearchBtn" class="btn-secondary" style="<?= $searchQ === '' ? 'display: none;' : '' ?>">Clear Search</button>
            </div>
        </div>

        <!-- 3. ТАБЛИЦЯ РЕЗУЛЬТАТІВ -->
        <div class="admin-section-box" id="servicesResultsContainer">
            <h3>Standard & Custom Services (Found: <?= $totalRows ?>)</h3>
            <div class="table-responsive">
                <table class="admin-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Service Name & Description</th>
                            <th>Category</th>
                            <th>Price ($)</th>
                            <th>Est. Hours</th>
                            <th>Website Status</th>
                            <?php if ($isSuperadmin): ?>
                                <th>Action</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($services) > 0): ?>
                            <?php foreach ($services as $srv): ?>
                                <tr>
                                    <td><strong>#<?= $srv['service_id'] ?></strong></td>
                                    <td><?= htmlspecialchars($srv['service_name']) ?><br><small class="sub-text"><?= htmlspecialchars($srv['description']) ?></small></td>
                                    <td><?= htmlspecialchars($srv['device_kind']) ?> (<?= htmlspecialchars($srv['service_category']) ?>)</td>
                                    <td><strong>$<?= number_format($srv['base_price'], 2) ?></strong></td>
                                    <td><?= htmlspecialchars($srv['estimated_hours']) ?> hrs</td>
                                    <td>
                                        <span class="status-badge <?= $srv['is_visible_on_website'] ? 'status-Ready' : 'status-Diagnostics' ?>">
                                            <?= $srv['is_visible_on_website'] ? 'Public' : 'Internal Only' ?>
                                        </span>
                                    </td>
                                    <?php if ($isSuperadmin): ?>
                                    <td>
                                        <a href="services.php?edit_id=<?= $srv['service_id'] ?>" class="btn-primary btn-sm">Edit</a>
                                    </td>
                                    <?php endif; ?>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?= $isSuperadmin ? 7 : 6 ?>" style="text-align: center; color: #837a9c;">No services match your filter criteria.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
            <?php renderPagination($page, $totalPages, $_GET); ?>
        </div>

        <?php if ($isSuperadmin): ?>
        <div class="admin-section-box">
            <h3>Add New Service</h3>
            <form action="services.php" method="POST">
                <div class="form-grid">
                    <div class="form-group">
                        <label>Service Name</label>
                        <input type="text" name="service_name" required placeholder="e.g. Liquid Metal Replacement">
                    </div>
                    <div class="form-group">
                        <label>Category</label>
                        <select name="type_id" required>
                            <?php foreach ($deviceTypes as $dt): ?>
                                <option value="<?= $dt['type_id'] ?>"><?= htmlspecialchars($dt['category_info']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Price ($)</label>
                        <input type="number" step="0.01" name="base_price" required placeholder="e.g. 45.00">
                    </div>
                    <div class="form-group">
                        <label>Estimated Hours</label>
                        <input type="number" step="0.5" name="estimated_hours" required placeholder="e.g. 1.5">
                    </div>
                    <div class="form-group" style="grid-column: 1 / -1;">
                        <label>Description</label>
                        <textarea name="description" rows="2" required placeholder="Service procedure details..."></textarea>
                    </div>
                </div>
                <div class="checkbox-group" style="margin-top: 15px;">
                    <input type="checkbox" name="is_visible_on_website" value="1" id="vis_check" checked>
                    <label for="vis_check">Show on public website (is_visible_on_website)</label>
                </div>
                <button type="submit" name="add_service" class="btn-primary mt-20">Add Service</button>
            </form>
        </div>
        <?php endif; ?>
    </div>

    <?php if ($editService): ?>
        <div class="modal-overlay active">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Edit Service #<?= $editService['service_id'] ?></h3>
                    <a href="services.php" class="close-btn">&times;</a>
                </div>
                <form action="services.php" method="POST">
                    <input type="hidden" name="service_id" value="<?= $editService['service_id'] ?>">
                    <div class="form-grid">
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Service Name</label>
                            <input type="text" name="service_name" value="<?= htmlspecialchars($editService['service_name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Price ($)</label>
                            <input type="number" step="0.01" name="base_price" value="<?= $editService['base_price'] ?>" required>
                        </div>
                        <div class="form-group">
                            <label>Estimated Hours</label>
                            <input type="number" step="0.5" name="estimated_hours" value="<?= $editService['estimated_hours'] ?>" required>
                        </div>
                        <div class="form-group" style="grid-column: 1 / -1;">
                            <label>Description</label>
                            <textarea name="description" rows="2" required><?= htmlspecialchars($editService['description']) ?></textarea>
                        </div>
                    </div>
                    <div class="checkbox-group" style="margin-top: 15px;">
                        <input type="checkbox" name="is_visible_on_website" value="1" id="edit_vis" <?= $editService['is_visible_on_website'] ? 'checked' : '' ?>>
                        <label for="edit_vis">Show on public website</label>
                    </div>
                    <div class="btn-group mt-20" style="justify-content: space-between; align-items: center;">
                        <button type="submit" name="delete_service" class="btn-danger" onclick="return confirm('Are you sure you want to delete this service?');">Delete Service</button>

                        <div style="display: flex; gap: 10px;">
                            <a href="services.php" class="btn-secondary">Cancel</a>
                            <button type="submit" name="update_service" class="btn-primary">Save Changes</button>
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
const resultsContainer = document.getElementById('servicesResultsContainer');
const filterHiddenQ = document.getElementById('filterHiddenQ');

let activeController = null;
let debounceTimer = null;

function fetchServicesResults(queryVal) {
    if (activeController) {
        activeController.abort();
    }
    activeController = new AbortController();

    const catVal      = document.getElementById('filterCategory')?.value || '';
    const kindVal     = document.getElementById('filterDeviceKind')?.value || '';
    const minPriceVal = document.getElementById('filterMinPrice')?.value || '';
    const maxPriceVal = document.getElementById('filterMaxPrice')?.value || '';

    if (filterHiddenQ) filterHiddenQ.value = queryVal;
    if (clearBtn) clearBtn.style.display = queryVal.length > 0 ? 'inline-block' : 'none';

    const params = new URLSearchParams();
    if (queryVal !== '') params.set('q', queryVal);
    if (catVal !== '') params.set('service_category', catVal);
    if (kindVal !== '') params.set('device_kind', kindVal);
    if (minPriceVal !== '') params.set('min_price', minPriceVal);
    if (maxPriceVal !== '') params.set('max_price', maxPriceVal);

    const newUrl = 'services.php' + (params.toString() ? '?' + params.toString() : '');
    window.history.replaceState({}, '', newUrl);

    fetch(newUrl, { signal: activeController.signal })
        .then(res => res.text())
        .then(html => {
            const parser = new DOMParser();
            const doc = parser.parseFromString(html, 'text/html');
            const newBox = doc.getElementById('servicesResultsContainer');
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
            fetchServicesResults(val);
        }, 200);
    });
}

if (clearBtn && searchInput) {
    clearBtn.addEventListener('click', function () {
        searchInput.value = '';
        searchInput.focus();
        fetchServicesResults('');
    });
}
</script>
</body>
</html>