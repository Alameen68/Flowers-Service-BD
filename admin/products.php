<?php
require_once 'includes/header.php';

// ============================================
// SEO-FRIENDLY FILENAME GENERATOR
// ============================================
function seo_filename($product_name, $suffix = '') {
    $slug = strtolower(trim($product_name));
    $slug = preg_replace('/[^a-z0-9\s-]/', '', $slug);
    $slug = preg_replace('/[\s-]+/', '-', $slug);
    $slug = trim($slug, '-');
    $slug = substr($slug, 0, 50);
    return time() . '-' . $slug . ($suffix ? '-' . $suffix : '') . '.jpg';
}

// ============================================
// SAFE IMAGE UPLOAD HELPER
// ============================================
function is_valid_image_upload($file) {
    if ($file['error'] !== 0) return false;
    $allowed_mime = ['image/jpeg','image/png','image/gif','image/webp'];
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime  = finfo_file($finfo, $file['tmp_name']);
    finfo_close($finfo);
    return in_array($mime, $allowed_mime);
}

// ============================================
// INLINE IMAGE COMPRESSION FUNCTION
// ============================================
function compress_product_image($temp_file, $destination, $max_kb = 50) {
    $max_bytes = $max_kb * 1024;

    if (!extension_loaded('gd')) {
        move_uploaded_file($temp_file, $destination);
        return false;
    }

    $info = @getimagesize($temp_file);
    if (!$info) {
        move_uploaded_file($temp_file, $destination);
        return false;
    }

    $width  = $info[0];
    $height = $info[1];
    $mime   = $info['mime'];

    $source = null;
    if ($mime == 'image/jpeg')      $source = @imagecreatefromjpeg($temp_file);
    elseif ($mime == 'image/png')   $source = @imagecreatefrompng($temp_file);
    elseif ($mime == 'image/gif')   $source = @imagecreatefromgif($temp_file);
    elseif ($mime == 'image/webp')  $source = @imagecreatefromwebp($temp_file);

    if (!$source) {
        move_uploaded_file($temp_file, $destination);
        return false;
    }

    // Progressive attempts: resize down + reduce quality until under 50KB
    // Max dimension 800px keeps quality sharp at small file size
    $max_dim = 800;
    $scale = 1.0;
    if ($width > $max_dim || $height > $max_dim) {
        $scale = min($max_dim / $width, $max_dim / $height);
    }

    $attempts = [
        [$scale,       85],
        [$scale,       75],
        [$scale,       65],
        [$scale * 0.8, 80],
        [$scale * 0.8, 70],
        [$scale * 0.6, 75],
        [$scale * 0.6, 65],
        [$scale * 0.5, 70],
        [$scale * 0.4, 65],
    ];

    foreach ($attempts as [$s, $quality]) {
        $new_w = max(200, (int)($width * $s));
        $new_h = max(200, (int)($height * $s));

        $canvas = imagecreatetruecolor($new_w, $new_h);
        $white  = imagecolorallocate($canvas, 255, 255, 255);
        imagefill($canvas, 0, 0, $white);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $new_w, $new_h, $width, $height);

        ob_start();
        imagejpeg($canvas, null, $quality);
        $data = ob_get_clean();
        imagedestroy($canvas);

        if (strlen($data) <= $max_bytes) {
            file_put_contents($destination, $data);
            imagedestroy($source);
            return true;
        }
    }

    // Absolute fallback
    $final = imagecreatetruecolor(400, 400);
    $white = imagecolorallocate($final, 255, 255, 255);
    imagefill($final, 0, 0, $white);
    imagecopyresampled($final, $source, 0, 0, 0, 0, 400, 400, $width, $height);
    imagejpeg($final, $destination, 60);
    imagedestroy($final);
    imagedestroy($source);
    return true;
}

// Handle Actions
$message = '';

// Auto-create pivot table
$pdo->exec("CREATE TABLE IF NOT EXISTS `product_categories` (
    `product_id` int(11) NOT NULL,
    `category_id` int(11) NOT NULL,
    PRIMARY KEY (`product_id`, `category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Helper: sync pivot table
function sync_product_categories($pdo, $product_id, $category_ids) {
    $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$product_id]);
    if (!empty($category_ids)) {
        $stmt = $pdo->prepare("INSERT INTO product_categories (product_id, category_id) VALUES (?, ?)");
        foreach ($category_ids as $cid) {
            $stmt->execute([$product_id, (int)$cid]);
        }
        // Keep first category in products.category_id for backward compat
        $pdo->prepare("UPDATE products SET category_id = ? WHERE id = ?")->execute([(int)$category_ids[0], $product_id]);
    }
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    verify_csrf();
    if (isset($_POST['add_product'])) {
        $name        = trim($_POST['name']);
        $category_ids = isset($_POST['category_ids']) ? $_POST['category_ids'] : [];
        $regular_price = $_POST['regular_price'];
        $sell_price    = $_POST['sell_price'];
        $description   = trim($_POST['description']);

        $image = '';
        if (isset($_FILES['image']) && is_valid_image_upload($_FILES['image'])) {
            $target_dir = "../assets/images/products/";
            $image_name = seo_filename($name);
            $final_path = $target_dir . $image_name;
            compress_product_image($_FILES["image"]["tmp_name"], $final_path);
            if (file_exists($final_path)) $image = "assets/images/products/" . $image_name;
        }

        $image2 = '';
        if (isset($_FILES['image2']) && is_valid_image_upload($_FILES['image2'])) {
            $target_dir = "../assets/images/products/";
            $image_name2 = seo_filename($name, '2');
            $final_path2 = $target_dir . $image_name2;
            compress_product_image($_FILES["image2"]["tmp_name"], $final_path2);
            if (file_exists($final_path2)) $image2 = "assets/images/products/" . $image_name2;
        }

        $primary_cat = !empty($category_ids) ? (int)$category_ids[0] : null;
        $stmt = $pdo->prepare("INSERT INTO products (name, category_id, price, regular_price, sell_price, description, image, image2) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$name, $primary_cat, $sell_price, $regular_price, $sell_price, $description, $image, $image2]);
        $new_id = $pdo->lastInsertId();

        // Generate unique slug — fully parameterized, no raw interpolation
        $base_slug = make_slug($name);
        if (empty($base_slug)) $base_slug = 'product';
        $slug = $base_slug;
        $counter = 2;
        $slug_check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
        do {
            $slug_check->execute([$slug, $new_id]);
            if ($slug_check->fetchColumn() == 0) break;
            $slug = $base_slug . '-' . $counter++;
        } while (true);
        $pdo->prepare("UPDATE products SET slug = ? WHERE id = ?")->execute([$slug, $new_id]);

        sync_product_categories($pdo, $new_id, $category_ids);
        $message = "Product added successfully!";
    }

    if (isset($_POST['update_product'])) {
        $id           = $_POST['id'];
        $name         = trim($_POST['name']);
        $category_ids = isset($_POST['category_ids']) ? $_POST['category_ids'] : [];
        $regular_price = $_POST['regular_price'];
        $sell_price    = $_POST['sell_price'];
        $description   = trim($_POST['description']);

        // Handle images
        $image_set  = false;
        $image2_set = false;
        $image = $image2 = '';

        if (isset($_FILES['image']) && is_valid_image_upload($_FILES['image'])) {
            $target_dir = "../assets/images/products/";
            $image_name = seo_filename($name);
            $final_path = $target_dir . $image_name;
            compress_product_image($_FILES["image"]["tmp_name"], $final_path);
            if (file_exists($final_path)) { $image = "assets/images/products/" . $image_name; $image_set = true; }
        }

        if (isset($_FILES['image2']) && is_valid_image_upload($_FILES['image2'])) {
            $target_dir = "../assets/images/products/";
            $image_name2 = seo_filename($name, '2');
            $final_path2 = $target_dir . $image_name2;
            compress_product_image($_FILES["image2"]["tmp_name"], $final_path2);
            if (file_exists($final_path2)) { $image2 = "assets/images/products/" . $image_name2; $image2_set = true; }
        }

        $primary_cat = !empty($category_ids) ? (int)$category_ids[0] : null;
        $sql = "UPDATE products SET name=?, category_id=?, regular_price=?, sell_price=?, price=?, description=?";
        $params = [$name, $primary_cat, $regular_price, $sell_price, $sell_price, $description];
        if ($image_set)  { $sql .= ", image=?";  $params[] = $image; }
        if ($image2_set) { $sql .= ", image2=?"; $params[] = $image2; }

        // Regenerate slug if name changed — fully parameterized
        $old = $pdo->prepare("SELECT name, slug FROM products WHERE id = ?");
        $old->execute([$id]);
        $old = $old->fetch();
        if ($old && $old['name'] !== $name) {
            $base_slug = make_slug($name);
            if (empty($base_slug)) $base_slug = 'product';
            $slug = $base_slug; $counter = 2;
            $slug_check = $pdo->prepare("SELECT COUNT(*) FROM products WHERE slug = ? AND id != ?");
            do {
                $slug_check->execute([$slug, $id]);
                if ($slug_check->fetchColumn() == 0) break;
                $slug = $base_slug . '-' . $counter++;
            } while (true);
            $sql .= ", slug=?";
            $params[] = $slug;
        }

        $sql .= " WHERE id=?";
        $params[] = $id;
        $pdo->prepare($sql)->execute($params);
        sync_product_categories($pdo, $id, $category_ids);
        $message = "Product updated successfully!";
    }

    if (isset($_POST['delete_product'])) {
        $id = $_POST['id'];
        $pdo->prepare("DELETE FROM product_categories WHERE product_id = ?")->execute([$id]);
        $pdo->prepare("DELETE FROM products WHERE id = ?")->execute([$id]);
        $message = "Product deleted successfully!";
    }
}

// Fetch products with all their categories
$products = $pdo->query("SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ', ') as category_names
    FROM products p
    LEFT JOIN product_categories pc ON p.id = pc.product_id
    LEFT JOIN categories c ON pc.category_id = c.id
    GROUP BY p.id ORDER BY p.id DESC")->fetchAll();

// Also seed pivot from existing category_id for old products
$pdo->exec("INSERT IGNORE INTO product_categories (product_id, category_id)
    SELECT id, category_id FROM products WHERE category_id IS NOT NULL");

$categories = $pdo->query("SELECT * FROM categories ORDER BY name ASC")->fetchAll();

// Helper: get selected category ids for a product
function get_product_category_ids($pdo, $product_id) {
    $stmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
    $stmt->execute([$product_id]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}
?>

<div class="container-fluid">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h2>Manage Products</h2>
        <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addProductModal"><i class="fas fa-plus"></i> Add Product</button>
    </div>

    <?php if ($message): ?>
        <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= $message ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
        </div>
    <?php endif; ?>

    <?php if (extension_loaded('gd')): ?>
        <div class="alert alert-success mb-4">
            <strong>✓ Image Compression Active:</strong> All uploaded images will be optimized to max 200KB for best quality
        </div>
    <?php else: ?>
        <div class="alert alert-warning mb-4">
            <strong>⚠ GD Extension Disabled:</strong> Enable GD in php.ini to compress images
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
                            <th>Category</th>
                            <th>Regular Price</th>
                            <th>Sell Price</th>
                            <th>Discount</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $prod): 
                            $discount = 0;
                            if ($prod['regular_price'] > 0 && $prod['sell_price'] > 0) {
                                $discount = round((($prod['regular_price'] - $prod['sell_price']) / $prod['regular_price']) * 100);
                            }
                        ?>
                        <tr>
                            <td><?= $prod['id'] ?></td>
                            <td>
                                <?php if($prod['image']): ?>
                                    <img src="../<?= $prod['image'] ?>" width="50" height="50" style="object-fit: cover;">
                                <?php else: ?>
                                    <span class="text-muted">No Image</span>
                                <?php endif; ?>
                            </td>
                            <td><?= htmlspecialchars($prod['name']) ?></td>
                            <td><?= htmlspecialchars($prod['category_names'] ?? 'Uncategorized') ?></td>
                            <td>Tk. <?= number_format($prod['regular_price'], 2) ?></td>
                            <td><strong>Tk. <?= number_format($prod['sell_price'], 2) ?></strong></td>
                            <td>
                                <?php if ($discount > 0): ?>
                                    <span class="badge bg-danger"><?= $discount ?>% OFF</span>
                                <?php else: ?>
                                    <span class="text-muted">-</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editProductModal" onclick="editProduct(<?= htmlspecialchars(json_encode($prod)) ?>)">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <form method="POST" onsubmit="return confirm('Are you sure?');" class="d-inline">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="id" value="<?= $prod['id'] ?>">
                                    <button type="submit" name="delete_product" class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
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

<!-- Add Product Modal -->
<div class="modal fade" id="addProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add New Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Product Name</label>
                            <input type="text" name="name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Categories <small class="text-muted">(hold Ctrl/Cmd to select multiple)</small></label>
                            <select name="category_ids[]" class="form-select" multiple size="5" required>
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Regular Price (Tk.)</label>
                            <input type="number" step="0.01" name="regular_price" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Sell Price (Tk.)</label>
                            <input type="number" step="0.01" name="sell_price" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label>Description</label>
                            <textarea name="description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label>Product Image 1</label>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <small class="text-muted">First product image (optimized to max 200KB)</small>
                        </div>
                        <div class="col-12 mb-3">
                            <label>Product Image 2 (Optional)</label>
                            <input type="file" name="image2" class="form-control" accept="image/*">
                            <small class="text-muted">Second product image (optimized to max 200KB)</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="add_product" class="btn btn-primary">Save Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Product Modal -->
<div class="modal fade" id="editProductModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Product</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <div class="modal-body">
                    <input type="hidden" name="id" id="edit_id">
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label>Product Name</label>
                            <input type="text" name="name" id="edit_name" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Categories <small class="text-muted">(hold Ctrl/Cmd to select multiple)</small></label>
                            <select name="category_ids[]" id="edit_category_ids" class="form-select" multiple size="5">
                                <?php foreach ($categories as $cat): ?>
                                    <option value="<?= $cat['id'] ?>"><?= htmlspecialchars($cat['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Regular Price (Tk.)</label>
                            <input type="number" step="0.01" name="regular_price" id="edit_regular_price" class="form-control" required>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label>Sell Price (Tk.)</label>
                            <input type="number" step="0.01" name="sell_price" id="edit_sell_price" class="form-control" required>
                        </div>
                        <div class="col-12 mb-3">
                            <label>Description</label>
                            <textarea name="description" id="edit_description" class="form-control" rows="3"></textarea>
                        </div>
                        <div class="col-12 mb-3">
                            <label>Product Image 1</label>
                            <div id="current_image_preview" class="mb-2"></div>
                            <input type="file" name="image" class="form-control" accept="image/*">
                            <small class="text-muted">Leave blank to keep current image. Max 200KB</small>
                        </div>
                        <div class="col-12 mb-3">
                            <label>Product Image 2 (Optional)</label>
                            <div id="current_image2_preview" class="mb-2"></div>
                            <input type="file" name="image2" class="form-control" accept="image/*">
                            <small class="text-muted">Leave blank to keep current image. Max 200KB</small>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="submit" name="update_product" class="btn btn-primary">Update Product</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editProduct(product) {
    document.getElementById('edit_id').value = product.id;
    document.getElementById('edit_name').value = product.name;
    document.getElementById('edit_regular_price').value = product.regular_price;
    document.getElementById('edit_sell_price').value = product.sell_price;
    document.getElementById('edit_description').value = product.description;

    // Load and pre-select categories for this product
    fetch('get_product_categories.php?product_id=' + product.id)
        .then(r => r.json())
        .then(ids => {
            var sel = document.getElementById('edit_category_ids');
            for (var i = 0; i < sel.options.length; i++) {
                sel.options[i].selected = ids.includes(parseInt(sel.options[i].value));
            }
        });

    // Image previews
    document.getElementById('current_image_preview').innerHTML = product.image
        ? '<img src="../' + product.image + '" width="100" height="100" style="object-fit:cover;border-radius:5px;">'
        : '<span class="text-muted">No image</span>';

    document.getElementById('current_image2_preview').innerHTML = product.image2
        ? '<img src="../' + product.image2 + '" width="100" height="100" style="object-fit:cover;border-radius:5px;">'
        : '<span class="text-muted">No second image</span>';
}
</script>

<?php require_once 'includes/footer.php'; ?>
