<?php
require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/vapid_keys.php';

use Minishlink\WebPush\WebPush;
use Minishlink\WebPush\Subscription;

/**
 * Send push notification to all subscribed admin devices.
 * @param PDO    $pdo
 * @param int    $order_id
 * @param string $customer_name
 * @param float  $total
 */
function sendOrderPush($pdo, $order_id, $customer_name, $total) {
    // Ensure table exists
    try {
        $subs = $pdo->query("SELECT * FROM push_subscriptions")->fetchAll();
    } catch (Exception $e) { return; }

    if (empty($subs)) return;

    $webPush = new WebPush([
        'VAPID' => [
            'subject'    => VAPID_SUBJECT,
            'publicKey'  => VAPID_PUBLIC_KEY,
            'privateKey' => VAPID_PRIVATE_KEY,
        ],
    ]);

    $payload = json_encode([
        'title' => '🛍️ New Order #' . $order_id,
        'body'  => 'From: ' . $customer_name . ' — Tk. ' . number_format($total, 0),
        'icon'  => '/assets/images/LOGO/logo.png',
        'url'   => '/admin/orders.php',
        'sound' => '/assets/sounds/notification.mp3',
    ]);

    foreach ($subs as $sub) {
        $subscription = Subscription::create([
            'endpoint' => $sub['endpoint'],
            'keys'     => [
                'p256dh' => $sub['p256dh'],
                'auth'   => $sub['auth'],
            ],
        ]);
        $webPush->queueNotification($subscription, $payload);
    }

    // Send all queued notifications
    foreach ($webPush->flush() as $report) {
        if ($report->isSubscriptionExpired()) {
            // Clean up dead subscriptions
            $pdo->prepare("DELETE FROM push_subscriptions WHERE endpoint = ?")
                ->execute([$report->getEndpoint()]);
        }
    }
}
