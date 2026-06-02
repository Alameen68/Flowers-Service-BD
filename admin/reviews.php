<?php
// Process actions BEFORE any output
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
check_admin();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    if (isset($_POST['approve_id'])) {
        $pdo->prepare("UPDATE reviews SET is_approved=1 WHERE id=?")->execute([(int)$_POST['approve_id']]);
    }
    if (isset($_POST['delete_id'])) {
        $pdo->prepare("DELETE FROM reviews WHERE id=?")->execute([(int)$_POST['delete_id']]);
    }
    header('Location: reviews.php'); exit;
}

$reviews = $pdo->query("SELECT * FROM reviews ORDER BY is_approved ASC, created_at DESC")->fetchAll();

require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="fas fa-star me-2"></i>Customer Reviews</h2>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-bordered table-hover align-middle">
                <thead class="table-dark">
                    <tr>
                        <th>Name</th>
                        <th>Location</th>
                        <th>Rating</th>
                        <th>Review</th>
                        <th>Date</th>
                        <th>Status</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($reviews as $r): ?>
                    <tr>
                        <td><?= htmlspecialchars($r['name']) ?></td>
                        <td><?= htmlspecialchars($r['location']) ?></td>
                        <td>
                            <?php for ($s=1;$s<=5;$s++): ?>
                                <i class="fas fa-star" style="color:<?= $s<=$r['rating']?'#fbbf24':'#ddd' ?>; font-size:12px;"></i>
                            <?php endfor; ?>
                        </td>
                        <td style="max-width:300px;"><?= htmlspecialchars(substr($r['review'],0,100)) ?>...</td>
                        <td><?= date('d M Y', strtotime($r['created_at'])) ?></td>
                        <td>
                            <?php if ($r['is_approved']): ?>
                                <span class="badge bg-success">Approved</span>
                            <?php else: ?>
                                <span class="badge bg-warning text-dark">Pending</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <?php if (!$r['is_approved']): ?>
                            <form method="POST" style="display:inline;">
                                <?= csrf_field() ?>
                                <input type="hidden" name="approve_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-success">
                                    <i class="fas fa-check"></i> Approve
                                </button>
                            </form>
                            <?php endif; ?>
                            <form method="POST" style="display:inline;" onsubmit="return confirm('Delete this review?')">
                                <?= csrf_field() ?>
                                <input type="hidden" name="delete_id" value="<?= $r['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php if (empty($reviews)): ?>
                    <tr><td colspan="7" class="text-center text-muted">No reviews yet.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
