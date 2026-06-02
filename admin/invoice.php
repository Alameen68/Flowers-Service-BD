<?php
require_once '../config/db.php';
require_once '../includes/functions.php';

if (!isset($_GET['id'])) {
    redirect('../admin/orders.php');
}

$id = $_GET['id'];

// Fetch Order
$stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
$stmt->execute([$id]);
$order = $stmt->fetch();

if (!$order) {
    redirect('../admin/orders.php');
}

// Fetch Order Items
$stmt = $pdo->prepare("SELECT oi.*, p.name, p.image FROM order_items oi LEFT JOIN products p ON oi.product_id = p.id WHERE oi.order_id = ?");
$stmt->execute([$id]);
$items = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Invoice #<?= $order['id'] ?> - Flower Shop</title>
    <style>
        body {
            font-family: 'Courier New', Courier, monospace;
            font-size: 11pt;
            line-height: 1.4;
            color: #000;
        }
        .invoice-container {
            max-width: 210mm;
            margin: 0 auto;
            padding: 15mm;
        }
        .invoice-header {
            border-bottom: 2px solid #000;
            padding-bottom: 10px;
            margin-bottom: 15px;
        }
        .invoice-title {
            font-size: 18pt;
            font-weight: bold;
            text-align: center;
            margin-bottom: 5px;
        }
        .invoice-subtitle {
            text-align: center;
            font-size: 10pt;
        }
        .invoice-info {
            display: table;
            width: 100%;
            margin-bottom: 15px;
        }
        .invoice-info-left,
        .invoice-info-right {
            display: table-cell;
            vertical-align: top;
            width: 50%;
        }
        .invoice-info-right {
            text-align: right;
        }
        .section-title {
            font-weight: bold;
            margin-bottom: 5px;
            text-decoration: underline;
        }
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }
        .items-table th,
        .items-table td {
            border: 1px solid #000;
            padding: 6px 8px;
            text-align: left;
        }
        .items-table th {
            background-color: #f0f0f0 !important;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            font-weight: bold;
        }
        .text-center {
            text-align: center !important;
        }
        .text-end {
            text-align: right !important;
        }
        .total-section {
            border: 1px solid #000;
            padding: 10px;
            margin-bottom: 15px;
        }
        .total-row {
            display: table;
            width: 100%;
            margin-bottom: 3px;
        }
        .total-label,
        .total-value {
            display: table-cell;
        }
        .total-label {
            width: 70%;
            text-align: right;
            padding-right: 10px;
        }
        .total-value {
            width: 30%;
            text-align: right;
            font-weight: bold;
        }
        .grand-total {
            border-top: 2px solid #000;
            padding-top: 5px;
            margin-top: 5px;
            font-size: 12pt;
        }
        .footer-section {
            border-top: 1px solid #000;
            padding-top: 10px;
            margin-top: 10px;
            font-size: 9pt;
        }
        .note-section {
            border: 1px dashed #000;
            padding: 8px;
            margin-bottom: 10px;
            font-size: 10pt;
        }
        @media print {
            .no-print {
                display: none !important;
            }
            body {
                margin: 0;
                padding: 0;
            }
            .invoice-container {
                padding: 10mm;
                max-width: 100%;
            }
            @page {
                size: A4;
                margin: 10mm;
            }
        }
    </style>
</head>
<body>
    <div class="invoice-container">
        <!-- Print Button -->
        <div style="display: flex; justify-content: flex-end; margin-bottom: 10px;" class="no-print">
            <button onclick="window.print()" style="padding: 5px 15px;">Print Invoice</button>
            <a href="../admin/orders.php" style="padding: 5px 15px; margin-left: 5px; text-decoration: none; color: #000;">Back to Orders</a>
        </div>

        <!-- Invoice Header -->
        <div class="invoice-header">
            <div class="invoice-title">INVOICE</div>
            <div class="invoice-subtitle">Flower Service BD</div>
            <div class="invoice-subtitle">Contact: 01610279148</div>
        </div>

        <!-- Invoice Details -->
        <div class="invoice-info">
            <div class="invoice-info-left">
                <div class="section-title">Bill To:</div>
                <div><strong><?= htmlspecialchars($order['customer_name']) ?></strong></div>
                <div><?= htmlspecialchars($order['customer_phone']) ?></div>
                <div style="white-space: pre-line;"><?= htmlspecialchars($order['customer_address']) ?></div>
            </div>
            <div class="invoice-info-right">
                <div><strong>Invoice #:</strong> <?= str_pad($order['id'], 6, '0', STR_PAD_LEFT) ?></div>
                <div><strong>Date:</strong> <?= date('d-M-Y', strtotime($order['created_at'])) ?></div>
                <div><strong>Delivery Date:</strong> <?= date('d-M-Y', strtotime($order['delivery_date'])) ?></div>
                <div><strong>Payment:</strong> <?= strtoupper(htmlspecialchars($order['payment_method'])) ?></div>
                <?php if (!empty($order['transaction_id'])): ?>
                <div><strong>Transaction ID:</strong> <?= htmlspecialchars($order['transaction_id']) ?></div>
                <?php endif; ?>
                <div><strong>Status:</strong> <?= ucfirst($order['status']) ?></div>
            </div>
        </div>

        <?php if (!empty($order['note'])): ?>
        <div class="note-section">
            <strong>Order Note:</strong><br>
            <span style="white-space: pre-line;"><?= htmlspecialchars($order['note']) ?></span>
        </div>
        <?php endif; ?>

        <!-- Order Items Table -->
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 5%;">#</th>
                    <th style="width: 45%;">Product Name</th>
                    <th style="width: 15%;" class="text-center">Qty</th>
                    <th style="width: 17%;" class="text-end">Unit Price</th>
                    <th style="width: 18%;" class="text-end">Total</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $item_number = 1;
                foreach ($items as $item): 
                ?>
                <tr>
                    <td><?= $item_number++ ?></td>
                    <td><?= htmlspecialchars($item['name']) ?></td>
                    <td class="text-center"><?= $item['quantity'] ?></td>
                    <td class="text-end">Tk. <?= number_format($item['price'], 2) ?></td>
                    <td class="text-end">Tk. <?= number_format($item['price'] * $item['quantity'], 2) ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <!-- Total Section -->
        <div class="total-section">
            <div class="total-row">
                <div class="total-label">Subtotal:</div>
                <div class="total-value">Tk. <?= number_format($order['total_amount'], 2) ?></div>
            </div>
            <div class="total-row">
                <div class="total-label">Delivery Charge:</div>
                <div class="total-value">Tk. 0.00</div>
            </div>
            <div class="total-row grand-total">
                <div class="total-label">Grand Total:</div>
                <div class="total-value">Tk. <?= number_format($order['total_amount'], 2) ?></div>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer-section">
            <div style="text-align: center;">
                <strong>Thank you for your business!</strong><br>
                For any queries, please contact us at 01610279148
            </div>
        </div>
    </div>
</body>
</html>
