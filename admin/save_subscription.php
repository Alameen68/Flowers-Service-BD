<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once '../config/db.php';
require_once '../includes/functions.php';
header('Content-Type: application/json');
if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], ['admin','super_admin'])) {
    http_response_code(401); echo json_encode(['ok'=>false]); exit;
}

// Auto-create table
$pdo->exec("CREATE TABLE IF NOT EXISTS push_subscriptions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    endpoint TEXT NOT NULL,
    p256dh VARCHAR(512) NOT NULL,
    auth VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uq_endpoint (endpoint(191))
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$input = json_decode(file_get_contents('php://input'), true);

if (empty($input['endpoint']) || empty($input['keys']['p256dh']) || empty($input['keys']['auth'])) {
    echo json_encode(['ok' => false, 'error' => 'Invalid data']); exit;
}

$pdo->prepare("INSERT INTO push_subscriptions (user_id, endpoint, p256dh, auth)
    VALUES (?, ?, ?, ?)
    ON DUPLICATE KEY UPDATE p256dh=VALUES(p256dh), auth=VALUES(auth), user_id=VALUES(user_id)")
    ->execute([
        $_SESSION['user_id'],
        $input['endpoint'],
        $input['keys']['p256dh'],
        $input['keys']['auth']
    ]);

echo json_encode(['ok' => true]);
