<?php
// ── PROCESSING LOGIC (must run before any output) ──
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
check_admin();

// Auto-create table if not exists
$pdo->exec("CREATE TABLE IF NOT EXISTS `carousel_slides` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `image` varchar(255) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$msg = '';
$error = '';

// Handle delete — POST only with CSRF
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_id'])) {
    verify_csrf();
    $id = (int)$_POST['delete_id'];
    $row = $pdo->prepare("SELECT image FROM carousel_slides WHERE id = ?");
    $row->execute([$id]);
    $row = $row->fetch();
    if ($row && $row['image'] && file_exists('../' . $row['image'])) {
        unlink('../' . $row['image']);
    }
    $pdo->prepare("DELETE FROM carousel_slides WHERE id = ?")->execute([$id]);
    header('Location: carousel.php?msg=deleted');
    exit;
}

// Handle add
if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_FILES['image']['name'])) {
    verify_csrf();

    $allowed_mime = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $_FILES['image']['tmp_name']);
    finfo_close($finfo);

    $ext = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
    $allowed_ext = ['jpg','jpeg','png','webp','gif'];

    if (!in_array($mime, $allowed_mime) || !in_array($ext, $allowed_ext)) {
        $error = 'Invalid file. Only JPG, PNG, WEBP, GIF images are allowed.';
    } else {
        $filename = time() . '_' . preg_replace('/[^a-z0-9._]/i', '_', basename($_FILES['image']['name']));
        $dest = '../assets/images/careasel/' . $filename;

        if (move_uploaded_file($_FILES['image']['tmp_name'], $dest)) {
            $image_path = 'assets/images/careasel/' . $filename;
            $pdo->prepare("INSERT INTO carousel_slides (image) VALUES (?)")->execute([$image_path]);
            header('Location: carousel.php?msg=added');
            exit;
        } else {
            $error = 'Failed to upload image.';
        }
    }
}

if (isset($_GET['msg'])) {
    if ($_GET['msg'] === 'deleted') $msg = 'Banner deleted successfully.';
    if ($_GET['msg'] === 'added') $msg = 'Banner added successfully.';
}

$slides = $pdo->query("SELECT * FROM carousel_slides ORDER BY id DESC")->fetchAll();

// ── NOW INCLUDE HEADER (after all redirects) ──
require_once 'includes/header.php';
?>

<div class="container-fluid">
    <h2 class="mb-4"><i class="fas fa-images me-2"></i>Carousel Banners</h2>

    <?php if ($msg): ?><div class="alert alert-success alert-dismissible fade show">
        <?= $msg ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div><?php endif; ?>
    
    <?php if ($error): ?><div class="alert alert-danger alert-dismissible fade show">
        <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div><?php endif; ?>

    <div class="row g-4">
        <!-- Upload Form -->
        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-primary text-white fw-bold">
                    <i class="fas fa-upload me-2"></i>Upload New Banner
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= csrf_field() ?>
                        <div class="mb-3">
                            <label class="form-label">Banner Image <span class="text-danger">*</span></label>
                            <input type="file" name="image" class="form-control" accept="image/*" required>
                            <small class="text-muted">Recommended: 1920x420px (wide banner)</small>
                        </div>
                        <button type="submit" class="btn btn-primary w-100">
                            <i class="fas fa-plus me-1"></i> Add Banner
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- Banners List -->
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white fw-bold">
                    All Banners (<?= count($slides) ?>)
                </div>
                <div class="card-body p-0">
                    <?php if ($slides): ?>
                    <div class="row g-3 p-3">
                        <?php foreach ($slides as $s): ?>
                        <div class="col-md-6">
                            <div class="card border">
                                <img src="../<?= htmlspecialchars($s['image']) ?>" class="card-img-top" style="height:140px; object-fit:cover;">
                                <div class="card-body p-2 text-center">
                                    <form method="POST" onsubmit="return confirm('Delete this banner?')" style="display:inline;">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="delete_id" value="<?= $s['id'] ?>">
                                        <button type="submit" class="btn btn-sm btn-danger">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                        <div class="text-center py-5 text-muted">
                            <i class="fas fa-image fa-3x mb-3 d-block"></i>
                            No banners yet. Upload your first banner image.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
