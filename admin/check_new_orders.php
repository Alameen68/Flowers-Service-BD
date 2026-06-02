<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/db.php';
header('Content-Type: application/json');

// Only for logged-in admins
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','super_admin'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get last checked time from session (default: now - 60s on first load)
$last_checked = $_SESSION['last_order_check'] ?? (time() - 60);

// Find new orders since last check
$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE created_at > FROM_UNIXTIME(?)");
$stmt->execute([$last_checked]);
$new_count = (int)$stmt->fetchColumn();

// Get latest order info if any
$latest = null;
if ($new_count > 0) {
    $stmt2 = $pdo->prepare("SELECT id, customer_name, total_amount FROM orders WHERE created_at > FROM_UNIXTIME(?) ORDER BY id DESC LIMIT 1");
    $stmt2->execute([$last_checked]);
    $latest = $stmt2->fetch();
}

// Update last checked time
$_SESSION['last_order_check'] = time();

echo json_encode([
    'count'  => $new_count,
    'latest' => $latest
]);
