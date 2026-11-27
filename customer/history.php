<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('customer');

$st = db()->prepare(
    "SELECT t.type, t.points, t.created_at, b.product_name, r.name AS reward_name, rd.status
     FROM point_transactions t
     LEFT JOIN product_codes pc ON pc.id = t.code_id
     LEFT JOIN code_batches b   ON b.id = pc.batch_id
     LEFT JOIN redemptions rd   ON rd.id = t.redemption_id
     LEFT JOIN rewards r        ON r.id = rd.reward_id
     WHERE t.user_id = ? ORDER BY t.id DESC LIMIT 100"
);
$st->execute([$u['id']]);
$rows = $st->fetchAll();

$pageTitle = 'History';
require __DIR__ . '/../includes/header.php';
?>
<h3>Transaction history</h3>
<div class="card shadow-sm mt-3"><div class="table-responsive">
<table class="table align-middle mb-0">
  <thead><tr><th>Date</th><th>Activity</th><th class="text-end">Points</th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td class="text-muted small"><?= e($r['created_at']) ?></td>
      <td><?= $r['type'] === 'earn'
            ? 'Claimed code' . ($r['product_name'] ? ': ' . e($r['product_name']) : '')
            : 'Redeemed: ' . e($r['reward_name']) . ' <span class="badge text-bg-secondary">' . e($r['status']) . '</span>' ?></td>
      <td class="text-end fw-bold <?= $r['points'] > 0 ? 'text-success' : 'text-danger' ?>">
        <?= $r['points'] > 0 ? '+' : '' ?><?= (int)$r['points'] ?></td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="3" class="text-muted">No activity yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
<a class="d-block mt-3 small" href="<?= url('customer/dashboard.php') ?>">Back to dashboard</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>