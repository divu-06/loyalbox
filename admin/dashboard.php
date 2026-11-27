<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin');

$s = db()->query(
    "SELECT
      (SELECT COUNT(*) FROM users WHERE role='customer') AS customers,
      (SELECT COUNT(*) FROM users WHERE role='customer' AND is_blocked=1) AS blocked,
      (SELECT COUNT(*) FROM product_codes) AS codes_total,
      (SELECT COUNT(*) FROM product_codes WHERE status='unused') AS codes_unused,
      (SELECT COUNT(*) FROM product_codes WHERE status='claimed') AS codes_claimed,
      (SELECT COALESCE(SUM(points),0) FROM point_transactions WHERE type='earn') AS pts_earned,
      (SELECT COALESCE(-SUM(points),0) FROM point_transactions WHERE type='redeem') AS pts_redeemed,
      (SELECT COUNT(*) FROM redemptions) AS redemptions,
      (SELECT COUNT(*) FROM redemptions WHERE status='pending') AS pending"
)->fetch();
$lowStock = db()->query('SELECT name, stock FROM rewards WHERE is_active = 1 AND stock <= 3 ORDER BY stock')->fetchAll();

function stat_card(string $label, $value, string $cls = ''): void {
    echo '<div class="col-6 col-md-3"><div class="card shadow-sm h-100"><div class="card-body">'
       . '<div class="text-muted small">' . e($label) . '</div>'
       . '<div class="h2 mb-0 ' . e($cls) . '">' . e((string)$value) . '</div></div></div></div>';
}

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h3>Admin dashboard</h3>
<div class="row g-3 mt-1">
  <?php
  stat_card('Customers', $s['customers']);
  stat_card('Blocked customers', $s['blocked'], $s['blocked'] ? 'text-danger' : '');
  stat_card('Codes generated', $s['codes_total']);
  stat_card('Codes unused', $s['codes_unused']);
  stat_card('Codes claimed', $s['codes_claimed']);
  stat_card('Points earned (all time)', $s['pts_earned']);
  stat_card('Points redeemed', $s['pts_redeemed']);
  stat_card('Redemptions pending', $s['pending'] . ' / ' . $s['redemptions'], $s['pending'] ? 'text-warning' : '');
  ?>
</div>
<?php if ($lowStock): ?>
<div class="alert alert-warning mt-4">
  <strong>Low stock:</strong>
  <?php foreach ($lowStock as $l): ?><span class="ms-2"><?= e($l['name']) ?> (<?= (int)$l['stock'] ?> left)</span><?php endforeach; ?>
</div>
<?php endif; ?>
<?php require __DIR__ . '/../includes/footer.php'; ?>