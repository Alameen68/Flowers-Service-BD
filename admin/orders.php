<?php
// Process POST actions BEFORE any output
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
check_admin();

// Handle bulk delete by date — super admin only
if (isset($_POST['delete_by_date']) && isset($_POST['delete_date']) && is_super_admin()) {
    $delete_date = $_POST['delete_date'];
    // Validate date format
    $d = DateTime::createFromFormat('Y-m-d', $delete_date);
    if (!$d || $d->format('Y-m-d') !== $delete_date) {
        header("Location: orders.php"); exit;
    }
    
    // Delete order items first (foreign key constraint)
    $stmt = $pdo->prepare("DELETE oi FROM order_items oi 
                          INNER JOIN orders o ON oi.order_id = o.id 
                          WHERE DATE(o.created_at) = ?");
    $stmt->execute([$delete_date]);
    
    // Then delete orders
    $stmt = $pdo->prepare("DELETE FROM orders WHERE DATE(created_at) = ?");
    $stmt->execute([$delete_date]);
    
    $deleted_count = $stmt->rowCount();
    $_SESSION['success'] = "Successfully deleted $deleted_count order(s) from " . date('F d, Y', strtotime($delete_date));
    header("Location: orders.php");
    exit;
}

// Handle Filters
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// Build query
$where_conditions = [];
$params = [];

if ($status_filter) {
    $where_conditions[] = "status = ?";
    $params[] = $status_filter;
}

if ($date_filter) {
    $where_conditions[] = "DATE(created_at) = ?";
    $params[] = $date_filter;
}

$where_clause = !empty($where_conditions) ? "WHERE " . implode(" AND ", $where_conditions) : "";

// Get orders with first product image
$sql = "SELECT o.*, 
    (SELECT p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = o.id AND p.image != '' LIMIT 1) as product_image
    FROM orders o $where_clause ORDER BY o.created_at DESC";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$orders = $stmt->fetchAll();

// Calculate summary statistics
$total_orders = count($orders);
$total_revenue = 0;
$status_counts = ['pending' => 0, 'confirmed' => 0, 'delivered' => 0, 'cancelled' => 0];

foreach ($orders as $order) {
    $total_revenue += $order['total_amount'];
    if (isset($status_counts[$order['status']])) {
        $status_counts[$order['status']]++;
    }
}

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Orders</h2>
    </div>

    <!-- Success Message -->
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <i class="fas fa-check-circle me-2"></i><?= $_SESSION['success'] ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <!-- Date Filter -->
    <div class="card shadow-sm mb-4">
        <div class="card-body">
            <form method="GET" class="row g-3 align-items-end">
                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter by Date</label>
                    <input type="date" name="date" class="form-control" value="<?= htmlspecialchars($date_filter) ?>">
                </div>
                <div class="col-md-3">
                    <label class="form-label fw-bold">Filter by Status</label>
                    <select name="status" class="form-select">
                        <option value="">All Status</option>
                        <option value="pending" <?= $status_filter == 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="confirmed" <?= $status_filter == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                        <option value="delivered" <?= $status_filter == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                        <option value="cancelled" <?= $status_filter == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                    </select>
                </div>
                <div class="col-md-6">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-filter me-2"></i>Apply Filter
                    </button>
                    <a href="orders.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times me-2"></i>Clear
                    </a>
                    
                    <!-- Delete All Orders by Date Button — Super Admin only -->
                    <?php if ($date_filter && $total_orders > 0 && is_super_admin()): ?>
                        <button type="button" class="btn btn-danger ms-3" data-bs-toggle="modal" data-bs-target="#deleteModal">
                            <i class="fas fa-trash me-2"></i>Delete All Orders (<?= date('M d, Y', strtotime($date_filter)) ?>)
                        </button>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Summary Cards — Super Admin only -->
    <?php if (($date_filter || $status_filter) && is_super_admin()): ?>
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Total Orders</h6>
                            <h3 class="mb-0 fw-bold"><?= $total_orders ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Total Revenue</h6>
                            <h3 class="mb-0 fw-bold">Tk. <?= number_format($total_revenue, 2) ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-dollar-sign"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Pending</h6>
                            <h3 class="mb-0 fw-bold"><?= $status_counts['pending'] ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card shadow-sm border-0 bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h6 class="mb-1">Delivered</h6>
                            <h3 class="mb-0 fw-bold"><?= $status_counts['delivered'] ?></h3>
                        </div>
                        <div class="fs-1 opacity-50">
                            <i class="fas fa-check-circle"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Status Filter Buttons -->
    <div class="mb-3">
        <div class="btn-group">
            <a href="orders.php<?= $date_filter ? '?date=' . urlencode($date_filter) : '' ?>" class="btn btn-outline-secondary <?= !$status_filter ? 'active' : '' ?>">All</a>
            <a href="orders.php?status=pending<?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="btn btn-outline-warning <?= $status_filter == 'pending' ? 'active' : '' ?>">Pending</a>
            <a href="orders.php?status=confirmed<?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="btn btn-outline-primary <?= $status_filter == 'confirmed' ? 'active' : '' ?>">Confirmed</a>
            <a href="orders.php?status=delivered<?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="btn btn-outline-success <?= $status_filter == 'delivered' ? 'active' : '' ?>">Delivered</a>
            <a href="orders.php?status=cancelled<?= $date_filter ? '&date=' . urlencode($date_filter) : '' ?>" class="btn btn-outline-danger <?= $status_filter == 'cancelled' ? 'active' : '' ?>">Cancelled</a>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Note</th>
                            <th>Order Time</th>
                            <th>Delivery Date</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Transaction ID</th>
                            <th>Status</th>
                            <th>Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($orders) > 0): ?>
                            <?php foreach ($orders as $order): ?>
                            <tr>
                                <td>#<?= $order['id'] ?></td>
                                <td>
                                    <?php if (!empty($order['product_image'])): ?>
                                        <img src="../<?= htmlspecialchars($order['product_image']) ?>" width="50" height="50" style="object-fit:cover; border-radius:6px;">
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= htmlspecialchars($order['customer_name']) ?></td>
                                <td><?= htmlspecialchars($order['customer_phone']) ?></td>
                                <td>
                                    <?php if (!empty($order['note'])): ?>
                                        <small class="text-muted"><?= htmlspecialchars(substr($order['note'], 0, 50)) ?><?= strlen($order['note']) > 50 ? '...' : '' ?></small>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td><?= date('d M Y, h:i A', strtotime($order['created_at'])) ?></td>
                                <td><?= $order['delivery_date'] ?></td>
                                <td>Tk. <?= number_format($order['total_amount'], 2) ?></td>
                                <td><?= ucfirst(htmlspecialchars($order['payment_method'])) ?></td>
                                <td>
                                    <?php if (!empty($order['transaction_id'])): ?>
                                        <span class="badge bg-success"><?= htmlspecialchars($order['transaction_id']) ?></span>
                                    <?php else: ?>
                                        <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <span class="badge bg-<?= $order['status'] == 'pending' ? 'warning' : ($order['status'] == 'confirmed' ? 'primary' : ($order['status'] == 'delivered' ? 'success' : 'danger')) ?>">
                                        <?= ucfirst($order['status']) ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="order_details.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-info text-white"><i class="fas fa-eye"></i> View</a>
                                    <a href="invoice.php?id=<?= $order['id'] ?>" target="_blank" class="btn btn-sm btn-dark ms-1"><i class="fas fa-print"></i> Invoice</a>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="12" class="text-center">No orders found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal — Super Admin only -->
<?php if (is_super_admin()): ?>
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title" id="deleteModalLabel">
                    <i class="fas fa-exclamation-triangle me-2"></i>Confirm Delete
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-3">Are you sure you want to delete <strong>ALL <?= $total_orders ?> order(s)</strong> from:</p>
                <div class="alert alert-warning">
                    <i class="fas fa-calendar me-2"></i>
                    <strong><?= $date_filter ? date('F d, Y', strtotime($date_filter)) : '' ?></strong>
                </div>
                <p class="text-danger mb-0">
                    <i class="fas fa-exclamation-circle me-2"></i>
                    <strong>Warning:</strong> This action cannot be undone! All order items and details will be permanently deleted.
                </p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="fas fa-times me-2"></i>Cancel
                </button>
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="delete_date" value="<?= htmlspecialchars($date_filter) ?>">
                    <button type="submit" name="delete_by_date" class="btn btn-danger">
                        <i class="fas fa-trash me-2"></i>Yes, Delete All
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
<?php endif; // end super admin modal ?>

<?php require_once 'includes/footer.php'; ?>
