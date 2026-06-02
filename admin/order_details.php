<?php
require_once 'includes/header.php';

if (!isset($_GET['id'])) {
    header('Location: orders.php');
    exit;
}

// Cast to int immediately — prevents SQL injection AND XSS in JS
$id = (int)$_GET['id'];

// Handle Status Update
if (isset($_POST['update_status'])) {
    verify_csrf();
    $allowed_statuses = ['pending','confirmed','processing','shipped','delivered','cancelled'];
    $status = in_array($_POST['status'], $allowed_statuses) ? $_POST['status'] : 'pending';
    $pdo->prepare("UPDATE orders SET status = ? WHERE id = ?")->execute([$status, $id]);
    echo '<script>window.location.href="order_details.php?id=' . $id . '";</script>';
    exit;
}

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    header('Location: orders.php');
    exit;
}

// Fetch Order Items
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
?>

<div class="container-fluid">
    <div class="row mb-4">
        <div class="col-md-6">
            <h2 class="mb-0">Order #<?= $order['id'] ?> Details</h2>
        </div>
        <div class="col-md-6 text-end">
            <a href="orders.php" class="btn btn-secondary">Back to Orders</a>
        </div>
    </div>

    <div class="row">
        <!-- Order Info -->
        <div class="col-md-8">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0">Order Items</h5>
                </div>
                <div class="card-body">
                    <table class="table">
                        <thead>
                            <tr>
                                <th>Product</th>
                                <th>Price</th>
                                <th>Qty</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($items as $item): ?>
                            <tr>
                                <td>
                                    <?= htmlspecialchars($item['name']) ?>
                                </td>
                                <td>Tk. <?= number_format($item['price'], 2) ?></td>
                                <td><?= $item['quantity'] ?></td>
                                <td>Tk. <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total Amount:</th>
                                <th class="text-primary fs-5">Tk. <?= number_format($order['total_amount'], 2) ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>

        <!-- Customer & Status -->
        <div class="col-md-4">
            <div class="card shadow-sm mb-4">
                <div class="card-header bg-secondary text-white">
                    <h5 class="mb-0">Customer Info</h5>
                </div>
                <div class="card-body">
                    <p><strong>Name:</strong> <?= htmlspecialchars($order['customer_name']) ?></p>
                    <p><strong>Phone:</strong> <?= htmlspecialchars($order['customer_phone']) ?></p>
                    <p><strong>Address:</strong><br><?= nl2br(htmlspecialchars($order['customer_address'])) ?></p>
                    <p><strong>Delivery Date:</strong> <?= $order['delivery_date'] ?></p>
                    <p><strong>Payment Method:</strong> <?= htmlspecialchars($order['payment_method']) ?></p>
                    <?php if (!empty($order['note'])): ?>
                    <p><strong>Order Note:</strong><br>
                        <span class="text-muted"><?= nl2br(htmlspecialchars($order['note'])) ?></span>
                    </p>
                    <?php endif; ?>
                    <?php if (!empty($order['transaction_id'])): ?>
                    <p><strong>Transaction ID:</strong>
                        <span class="badge bg-success fs-6"><?= htmlspecialchars($order['transaction_id']) ?></span>
                    </p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white">
                    <h5 class="mb-0">Order Status</h5>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label>Current Status</label>
                            <select name="status" class="form-select">
                                <option value="pending" <?= $order['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="confirmed" <?= $order['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                <option value="processing" <?= $order['status'] == 'processing' ? 'selected' : '' ?>>Processing</option>
                                <option value="shipped" <?= $order['status'] == 'shipped' ? 'selected' : '' ?>>Shipped</option>
                                <option value="delivered" <?= $order['status'] == 'delivered' ? 'selected' : '' ?>>Delivered</option>
                                <option value="cancelled" <?= $order['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                            </select>
                        </div>
                        <button type="submit" name="update_status" class="btn btn-success w-100">Update Status</button>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
