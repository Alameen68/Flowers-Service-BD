<?php
require_once 'includes/header.php';
check_admin();
$product_id = (int)($_GET['product_id'] ?? 0);
$stmt = $pdo->prepare("SELECT category_id FROM product_categories WHERE product_id = ?");
$stmt->execute([$product_id]);
$ids = $stmt->fetchAll(PDO::FETCH_COLUMN);
header('Content-Type: application/json');
echo json_encode(array_map('intval', $ids));
exit;
