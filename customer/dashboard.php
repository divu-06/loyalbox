<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('customer');

$st = db()->prepare('SELECT COALESCE(SUM(points), 0) FROM point_transactions WHERE user_id = ?');
$st->execute([$u['id']]);
$balance = (int)$st->fetchColumn();

$pageTitle = 'My Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h3>Hello, <?= e($u['name']) ?></h3>
<div class="card shadow-sm mt-3" style="max-width:320px"><div class="card-body">
  <div class="text-muted small">Points balance</div>
  <div class="display-5 fw-bold"><?= $balance ?></div>
  <a class="btn btn-primary mt-3" href="<?= url('customer/claim.php') ?>">Claim a code</a>
</div></div>
<p class="text-muted mt-3">Claim codes, history and rewards are coming in the next milestones.</p>
<a class="btn btn-outline-primary mt-3" href="<?= url('customer/rewards.php') ?>">Rewards</a>
<a class="btn btn-outline-secondary mt-3" href="<?= url('customer/history.php') ?>">History</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>