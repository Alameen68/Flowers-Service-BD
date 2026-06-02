<?php
require_once 'includes/header.php';

// Inline image compression function
function compress_category_image($source, $destination, $max_size_kb = 200) {
    $info = getimagesize($source);
    if ($info === false) return false;
    
    $mime = $info['mime'];
    
    // Create image from source
    switch ($mime) {
        case 'image/jpeg':
            $image = imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $image = imagecreatefrompng($source);
            break;
        case 'image/gif':
            $image = imagecreatefromgif($source);
            break;
        default:
            return false;
    }
    
    if (!$image) return false;
    
    // Get original dimensions
    $width = imagesx($image);
    $height = imagesy($image);
    
    // Resize if too large (maintain aspect ratio, min 400x400)
    $max_dimension = 800;
    $min_dimension = 400;
    
    if ($width > $max_dimension || $height > $max_dimension) {
        $ratio = min($max_dimension / $width, $max_dimension / $height);
        $new_width = max($min_dimension, (int)($width * $ratio));
        $new_height = max($min_dimension, (int)($height * $ratio));
        
        $resized = imagecreatetruecolor($new_width, $new_height);
        imagecopyresampled($resized, $image, 0, 0, 0, 0, $new_width, $new_height, $width, $height);
        imagedestroy($image);
        $image = $resized;
    }
    
    // Try different quality levels
    $quality = 90;
    $max_size_bytes = $max_size_kb * 1024;
    
    while ($quality >= 70) {
        ob_start();
        imagejpeg($image, null, $quality);
        $image_data = ob_get_clean();
        
        if (strlen($image_data) <= $max_size_bytes || $quality <= 70) {
            file_put_contents($destination, $image_data);
            imagedestroy($image);
            return true;
        }
        
        $quality -= 5;
    }
    
    imagedestroy($image);
    return false;
}

// Safe image MIME check
function is_valid_image($file) {
    if ($file['error'] !== 0) return false;
    $allowed = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    return in_array($mime, $allowed);
}

// Handle Add/Delete/Edit Logic
$message = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();
    if (isset($_POST['add_category'])) {
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        // Image Upload
        $image = '';
        if (isset($_FILES['image']) && is_valid_image($_FILES['image'])) {
            $target_dir = "../assets/images/categories/";
            $image_name = time() . '_' . basename($_FILES["image"]["name"]);
            $temp_path = $_FILES["image"]["tmp_name"];
            $final_path = $target_dir . $image_name;
            
            // Compress the image to 200KB
            if (compress_category_image($temp_path, $final_path, 200)) {
                $image = "assets/images/categories/" . $image_name;
            }
        }

        $stmt = $pdo->prepare("INSERT INTO categories (name, description, image) VALUES (?, ?, ?)");
        $stmt->execute([$name, $description, $image]);
        $message = "Category added successfully!";
    }
    
    if (isset($_POST['update_category'])) {
        $id = $_POST['id'];
        $name = trim($_POST['name']);
        $description = trim($_POST['description']);
        
        // Check if new image is uploaded
        if (isset($_FILES['image']) && is_valid_image($_FILES['image'])) {
            $target_dir = "../assets/images/categories/";
            $image_name = time() . '_' . basename($_FILES["image"]["name"]);
            $temp_path = $_FILES["image"]["tmp_name"];
            $final_path = $target_dir . $image_name;
            
            // Compress the image to 200KB
            if (compress_category_image($temp_path, $final_path, 200)) {
                $image = "assets/images/categories/" . $image_name;
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ?, image = ? WHERE id = ?");
                $stmt->execute([$name, $description, $image, $id]);
            } else {
                // Upload failed, update without image
                $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
                $stmt->execute([$name, $description, $id]);
            }
        } else {
            $stmt = $pdo->prepare("UPDATE categories SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([$name, $description, $id]);
        }
        $message = "Category updated successfully!";
    }
    
    if (isset($_POST['delete_category'])) {
        $id = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM categories WHERE id = ?");
        $stmt->execute([$id]);
        $message = "Category deleted successfully!";
    }
}

$categories = $pdo->query("SELECT * FROM categories ORDER BY id DESC")->fetchAll();
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Categories</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addCategoryModal"><i class="fas fa-plus"></i> Add Category</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Image</th>
                            <th>Name</th>
                            <th>Description</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                        <tr>
                            <td><?= $cat['id'] ?></td>
                            <td>
                                <?php if($cat['image']): ?>
                                    <img src="../<?= $cat['image'] ?>" width="50" height="50" style="object-fit: cover;">
                                <?php else: ?>
                                    <span class="text-muted">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($cat['name']) ?></td>
                            <td><?= htmlspecialchars($cat['description']??'') ?></td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editCategoryModal" onclick="editCategory(<?= htmlspecialchars(json_encode($cat)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Are you sure?');" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $cat['id'] ?>">
                                    <button type="submit" name="delete_category" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Category Name</label>
                        <input type="text" name="name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Image</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Image will be automatically compressed to max 200KB for better quality</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_category" class="btn btn-primary">Save Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="mb-3">
                        <label>Category Name</label>
                        <input type="text" name="name" id="edit_name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Description</label>
                        <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <label>Image</label>
                        <div id="current_image_preview" class="mb-2"></div>
                        <input type="file" name="image" class="form-control" accept="image/*">
                        <small class="text-muted">Leave blank to keep current image. Max size after compression: 200KB</small>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_category" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editCategory(category) {
    document.getElementById('edit_id').value = category.id;
    document.getElementById('edit_name').value = category.name;
    document.getElementById('edit_description').value = category.description;
    
    if (category.image) {
        document.getElementById('current_image_preview').innerHTML = '<img src="../' + category.image + '" width="80" height="80" style="object-fit: cover;">';
    } else {
        document.getElementById('current_image_preview').innerHTML = '<span class="text-muted">No image</span>';
    }
}
</script>

<?php require_once 'includes/footer.php'; ?>
