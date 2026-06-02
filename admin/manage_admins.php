<?php
require_once 'includes/header.php';

// Only super_admin can access this page
if (!is_super_admin()) {
    echo '<div class="container-fluid"><div class="alert alert-danger mt-4"><i class="fas fa-lock me-2"></i>Access denied. Super Admin only.</div></div>';
    require_once 'includes/footer.php';
    exit;
}

$message = $error = '';

// Create admin
if (isset($_POST['create_admin'])) {
    $name     = trim($_POST['name']);
    $email    = trim($_POST['email']);
    $password = $_POST['password'];
    $confirm  = $_POST['confirm_password'];

    if (empty($name) || empty($email) || empty($password)) {
        $error = 'Please fill in all fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Invalid email address.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $chk = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $chk->execute([$email]);
        if ($chk->fetch()) {
            $error = 'This email is already registered.';
        } else {
            $pdo->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, 'admin')")
                ->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT)]);
            $message = "Admin account created successfully.";
        }
    }
}

// Delete admin
if (isset($_POST['delete_admin'])) {
    $del_id = (int)$_POST['del_id'];
    // Prevent deleting self or other super admins
    $target = $pdo->prepare("SELECT role FROM users WHERE id = ?");
    $target->execute([$del_id]);
    $target = $target->fetch();
    if (!$target) {
        $error = 'User not found.';
    } elseif ($target['role'] === 'super_admin') {
        $error = 'Cannot delete a Super Admin account.';
    } elseif ($del_id === (int)$_SESSION['user_id']) {
        $error = 'You cannot delete your own account.';
    } else {
        $pdo->prepare("DELETE FROM users WHERE id = ? AND role = 'admin'")->execute([$del_id]);
        $message = "Admin account deleted.";
    }
}

$admins = $pdo->query("SELECT id, name, email, role, created_at FROM users WHERE role IN ('admin','super_admin') ORDER BY id ASC")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2><i class="fas fa-user-shield me-2"></i>Manage Admins</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createAdminModal">
            <i class="fas fa-plus me-1"></i> Create Admin
        </button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-danger alert-dismissible fade show">
            <?= $error ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <table class="table table-bordered table-hover">
                <thead class="table-dark">
                    <tr>
                        <th>ID</th>
                        <th>Name</th>
                        <th>Email</th>
                        <th>Role</th>
                        <th>Created</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($admins as $a): ?>
                    <tr>
                        <td><?= $a['id'] ?></td>
                        <td><?= htmlspecialchars($a['name']) ?></td>
                        <td><?= htmlspecialchars($a['email']) ?></td>
                        <td>
                            <?php if ($a['role'] === 'super_admin'): ?>
                                <span class="badge bg-danger">Super Admin</span>
                            <?php else: ?>
                                <span class="badge bg-primary">Admin</span>
                            <?php endif; ?>
                        </td>
                        <td><?= date('d M Y', strtotime($a['created_at'])) ?></td>
                        <td>
                            <?php if ($a['role'] !== 'super_admin' && $a['id'] !== (int)$_SESSION['user_id']): ?>
                            <form method="POST" onsubmit="return confirm('Delete this admin account?');" class="d-inline">
                                <input type="hidden" name="del_id" value="<?= $a['id'] ?>">
                                <button type="submit" name="delete_admin" class="btn btn-sm btn-danger">
                                    <i class="fas fa-trash"></i> Delete
                                </button>
                            </form>
                            <?php else: ?>
                                <span class="text-muted small">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Create Admin Modal -->
<div class="modal fade" id="createAdminModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Admin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Full Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Email Address</label>
                        <input type="email" name="email" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" name="create_admin" class="btn btn-primary">Create Admin</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
