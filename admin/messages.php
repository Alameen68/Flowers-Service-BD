<?php
require_once 'includes/header.php';

$messages = $pdo->query("SELECT * FROM messages ORDER BY created_at DESC")->fetchAll();
?>

<div class="container-fluid">
    <h2 class="mb-4">Customer Messages</h2>

    <div class="card shadow-sm">
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover">
                    <thead class="table-dark">
                        <tr>
                            <th>ID</th>
                            <th>Name</th>
                            <th>Email</th>
                            <th>Subject</th>
                            <th>Message</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (count($messages) > 0): ?>
                            <?php foreach ($messages as $msg): ?>
                            <tr>
                                <td><?= $msg['id'] ?></td>
                                <td><?= htmlspecialchars($msg['name']) ?></td>
                                <td><a href="mailto:<?= htmlspecialchars($msg['email']) ?>"><?= htmlspecialchars($msg['email']) ?></a></td>
                                <td><?= htmlspecialchars($msg['subject']) ?></td>
                                <td><?= nl2br(htmlspecialchars($msg['message'])) ?></td>
                                <td><?= date('d M Y, h:i A', strtotime($msg['created_at'])) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr><td colspan="6" class="text-center">No messages found.</td></tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
