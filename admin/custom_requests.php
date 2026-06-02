<?php
require_once 'includes/header.php';

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $id = $_POST['id'];
    $status = $_POST['status'];
    
    $stmt = $pdo->prepare("UPDATE custom_bouquet_requests SET status = ? WHERE id = ?");
    $stmt->execute([$status, $id]);
    
    $message = "Status updated successfully!";
}

// Handle delete
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['delete_request'])) {
    $id = $_POST['id'];
    $stmt = $pdo->prepare("DELETE FROM custom_bouquet_requests WHERE id = ?");
    $stmt->execute([$id]);
    
    $message = "Request deleted successfully!";
}

// Fetch all custom requests
$requests = $pdo->query("SELECT * FROM custom_bouquet_requests ORDER BY created_at DESC")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Custom Bouquet Requests</h2>
        <span class="badge bg-primary fs-6"><?= count($requests) ?> Total Requests</span>
    </div>

    <?php if (isset($message)): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Phone</th>
                            <th>Details</th>
                            <th>Image</th>
                            <th>Status</th>
                            <th>Date</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($requests as $req): ?>
                        <tr>
                            <td><?= $req['id'] ?></td>
                            <td><?= htmlspecialchars($req['customer_name']) ?></td>
                            <td>
                                <a href="tel:<?= $req['customer_phone'] ?>" class="text-decoration-none">
                                    <i class="fas fa-phone text-success"></i> <?= htmlspecialchars($req['customer_phone']) ?>
                                </a>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-info" data-bs-toggle="modal" data-bs-target="#detailsModal<?= $req['id'] ?>">
                                    <i class="fas fa-eye"></i> View
                                </button>
                            </td>
                            <td>
                                <?php if ($req['reference_image']): ?>
                                    <a href="../<?= $req['reference_image'] ?>" target="_blank">
                                        <img src="../<?= $req['reference_image'] ?>" width="50" height="50" style="object-fit: cover; border-radius: 5px;">
                                    </a>
                                <?php else: ?>
                                    <span class="text-muted">No image</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <form method="POST" class="d-inline">
                                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                    <select name="status" class="form-select form-select-sm" onchange="this.form.submit()">
                                        <option value="pending" <?= $req['status'] == 'pending' ? 'selected' : '' ?>>Pending</option>
                                        <option value="contacted" <?= $req['status'] == 'contacted' ? 'selected' : '' ?>>Contacted</option>
                                        <option value="confirmed" <?= $req['status'] == 'confirmed' ? 'selected' : '' ?>>Confirmed</option>
                                        <option value="completed" <?= $req['status'] == 'completed' ? 'selected' : '' ?>>Completed</option>
                                        <option value="cancelled" <?= $req['status'] == 'cancelled' ? 'selected' : '' ?>>Cancelled</option>
                                    </select>
                                    <input type="hidden" name="update_status" value="1">
                                </form>
                            </td>
                            <td><?= date('M d, Y', strtotime($req['created_at'])) ?></td>
                            <td>
                                <a href="https://wa.me/88<?= $req['customer_phone'] ?>?text=Hello <?= urlencode($req['customer_name']) ?>, regarding your custom bouquet request..." 
                                   target="_blank" 
                                   class="btn btn-sm btn-success">
                                    <i class="fab fa-whatsapp"></i>
                                </a>
                                <form method="POST" class="d-inline" onsubmit="return confirm('Delete this request?')">
                                    <input type="hidden" name="id" value="<?= $req['id'] ?>">
                                    <button type="submit" name="delete_request" class="btn btn-sm btn-danger">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                        
                        <!-- Details Modal -->
                        <div class="modal fade" id="detailsModal<?= $req['id'] ?>" tabindex="-1">
                            <div class="modal-dialog modal-lg">
                                <div class="modal-content">
                                    <div class="modal-header">
                                        <h5 class="modal-title">Request Details - #<?= $req['id'] ?></h5>
                                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                                    </div>
                                    <div class="modal-body">
                                        <div class="row">
                                            <div class="col-md-6">
                                                <p><strong>Customer:</strong> <?= htmlspecialchars($req['customer_name']) ?></p>
                                                <p><strong>Phone:</strong> <?= htmlspecialchars($req['customer_phone']) ?></p>
                                                <p><strong>Status:</strong> <span class="badge bg-info"><?= ucfirst($req['status']) ?></span></p>
                                                <p><strong>Date:</strong> <?= date('F d, Y h:i A', strtotime($req['created_at'])) ?></p>
                                            </div>
                                            <div class="col-md-6">
                                                <?php if ($req['reference_image']): ?>
                                                    <p><strong>Reference Image:</strong></p>
                                                    <img src="../<?= $req['reference_image'] ?>" class="img-fluid rounded" alt="Reference">
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        <hr>
                                        <p><strong>Bouquet Details:</strong></p>
                                        <div class="bg-light p-3 rounded">
                                            <?= nl2br(htmlspecialchars($req['bouquet_details'])) ?>
                                        </div>
                                    </div>
                                    <div class="modal-footer">
                                        <a href="https://wa.me/88<?= $req['customer_phone'] ?>" target="_blank" class="btn btn-success">
                                            <i class="fab fa-whatsapp me-2"></i>Contact via WhatsApp
                                        </a>
                                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
