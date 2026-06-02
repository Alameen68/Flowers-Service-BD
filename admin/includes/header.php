<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once '../config/db.php';
require_once '../includes/functions.php';
require_once __DIR__ . '/../vapid_keys.php';

check_admin();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Panel - Flower Service BD</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        .sidebar { min-height: 100vh; background-color: #343a40; color: white; }
        .sidebar a { color: #cfd2d6; text-decoration: none; display: block; padding: 10px 15px; }
        .sidebar a:hover, .sidebar a.active { background-color: #495057; color: white; }
        .content { width: 100%; padding: 20px; }
    </style>
</head>
<body>

<div class="d-flex">
    <!-- Sidebar -->
    <div class="sidebar p-3" style="width: 250px; flex-shrink: 0;">
        <h4 class="text-center py-3 border-bottom">Admin Panel</h4>
        <ul class="list-unstyled mt-3">
            <li><a href="index.php"><i class="fas fa-tachometer-alt me-2"></i> Dashboard</a></li>
            <li><a href="categories.php"><i class="fas fa-list me-2"></i> Categories</a></li>
            <li><a href="products.php"><i class="fas fa-box me-2"></i> Products</a></li>
            <li><a href="carousel.php"><i class="fas fa-images me-2"></i> Carousel</a></li>
            <li><a href="orders.php"><i class="fas fa-shopping-cart me-2"></i> Orders</a></li>
            <li><a href="custom_requests.php"><i class="fas fa-palette me-2"></i> Custom Requests</a></li>
            <li><a href="messages.php"><i class="fas fa-envelope me-2"></i> Messages</a></li>
            <li><a href="reviews.php"><i class="fas fa-star me-2"></i> Reviews</a></li>
            <?php if (is_super_admin()): ?>
            <li><a href="manage_admins.php" style="color:#ff6b6b;"><i class="fas fa-user-shield me-2"></i> Manage Admins</a></li>
            <?php endif; ?>
            <li><a href="logout.php" class="text-danger mt-5"><i class="fas fa-sign-out-alt me-2"></i> Logout</a></li>
        </ul>
    </div>

    <!-- Main Content -->
    <div class="content bg-light">
        <nav class="navbar navbar-expand-lg navbar-light bg-white shadow-sm mb-4 rounded">
            <div class="container-fluid">
                <button class="btn btn-outline-secondary d-md-none" id="menu-toggle"><i class="fas fa-bars"></i></button>
                <h5 class="ms-3 mb-0">Dashboard</h5>
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <span class="nav-link">Welcome, <?= htmlspecialchars($_SESSION['user_name']) ?></span>
                    </li>
                </ul>
            </div>
        </nav>

<!-- ── Order Notification System ── -->
<div id="orderNotif" style="display:none; position:fixed; top:20px; right:20px; z-index:9999; min-width:300px; max-width:360px;">
    <div style="background:#fff; border-left:5px solid #d63384; border-radius:12px; box-shadow:0 8px 30px rgba(0,0,0,0.15); padding:16px 20px; animation: slideIn .4s ease;">
        <div style="display:flex; align-items:center; gap:12px;">
            <div style="font-size:28px;">🛍️</div>
            <div style="flex:1;">
                <div style="font-weight:700; color:#1a1a2e; font-size:14px;">New Order Received!</div>
                <div id="notifMsg" style="font-size:13px; color:#666; margin-top:2px;"></div>
            </div>
            <button onclick="document.getElementById('orderNotif').style.display='none'" style="background:none;border:none;font-size:18px;color:#aaa;cursor:pointer;line-height:1;">×</button>
        </div>
        <a href="orders.php" style="display:block; margin-top:10px; background:linear-gradient(135deg,#d63384,#9b1f5e); color:#fff; text-align:center; padding:8px; border-radius:8px; font-size:13px; font-weight:600; text-decoration:none;">
            View Orders
        </a>
    </div>
</div>

<style>
@keyframes slideIn {
    from { transform: translateX(120%); opacity:0; }
    to   { transform: translateX(0);    opacity:1; }
}
#orderNotif .badge-pulse {
    display:inline-block;
    width:10px; height:10px;
    background:#d63384;
    border-radius:50%;
    animation: pulse 1s infinite;
}
@keyframes pulse {
    0%,100% { transform:scale(1); opacity:1; }
    50%      { transform:scale(1.4); opacity:.6; }
}
</style>

<script>
// Generate notification sound using Web Audio API (no file needed)
function playNotifSound() {
    try {
        var audio = new Audio('/assets/sounds/notification.mp3');
        audio.volume = 0.8;
        audio.play().catch(function() {
            // Fallback to Web Audio API beep
            var ctx = new (window.AudioContext || window.webkitAudioContext)();
            var notes = [523, 659, 784, 1047];
            notes.forEach(function(freq, i) {
                var osc = ctx.createOscillator();
                var gain = ctx.createGain();
                osc.connect(gain); gain.connect(ctx.destination);
                osc.frequency.value = freq; osc.type = 'sine';
                gain.gain.setValueAtTime(0.3, ctx.currentTime + i * 0.15);
                gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + i * 0.15 + 0.3);
                osc.start(ctx.currentTime + i * 0.15);
                osc.stop(ctx.currentTime + i * 0.15 + 0.3);
            });
        });
    } catch(e) {}
}

function checkNewOrders() {
    fetch('check_new_orders.php')
        .then(r => r.json())
        .then(data => {
            if (data.count > 0) {
                var msg = data.count + ' new order' + (data.count > 1 ? 's' : '');
                if (data.latest) {
                    msg = 'From: <strong>' + data.latest.customer_name + '</strong> — Tk. ' + parseFloat(data.latest.total_amount).toLocaleString();
                }
                document.getElementById('notifMsg').innerHTML = msg;
                document.getElementById('orderNotif').style.display = 'block';
                playNotifSound();

                // Auto-hide after 10 seconds
                setTimeout(function() {
                    document.getElementById('orderNotif').style.display = 'none';
                }, 10000);
            }
        })
        .catch(function() {});
}

// Check every 30 seconds
setInterval(checkNewOrders, 30000);
</script>

<!-- ── Web Push Subscription ── -->
<script>
(function() {
    if (!('serviceWorker' in navigator) || !('PushManager' in window)) return;

    var VAPID_PUBLIC = '<?= VAPID_PUBLIC_KEY ?>';

    function urlBase64ToUint8Array(b) {
        var pad = '='.repeat((4 - b.length % 4) % 4);
        var base64 = (b + pad).replace(/-/g, '+').replace(/_/g, '/');
        var raw = atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) arr[i] = raw.charCodeAt(i);
        return arr;
    }

    navigator.serviceWorker.register('/sw.js', { scope: '/' })
        .then(function(reg) {
            // Request notification permission
            return Notification.requestPermission().then(function(perm) {
                if (perm !== 'granted') return null;
                return reg.pushManager.getSubscription().then(function(existing) {
                    if (existing) return existing;
                    return reg.pushManager.subscribe({
                        userVisibleOnly: true,
                        applicationServerKey: urlBase64ToUint8Array(VAPID_PUBLIC)
                    });
                });
            });
        })
        .then(function(sub) {
            if (!sub) return;
            return fetch('save_subscription.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(sub)
            });
        })
        .catch(function(e) { console.warn('Push setup:', e); });
})();
</script>

