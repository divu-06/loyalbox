<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/redeem.php';
$u = require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $res = redeem_reward((int)$u['id'], (int)($_POST['reward_id'] ?? 0));
    flash($res['ok'] ? 'success' : 'danger', $res['msg']);
    redirect('customer/rewards.php');       // redirect after POST: a refresh can't redeem twice
}

$st = db()->prepare('SELECT COALESCE(SUM(points),0) FROM point_transactions WHERE user_id = ?');
$st->execute([$u['id']]);
$balance = (int)$st->fetchColumn();

$rewards = db()->query('SELECT id, name, description, points_cost, stock FROM rewards WHERE is_active = 1 ORDER BY points_cost')->fetchAll();

$pageTitle = 'Rewards';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Rewards</h3>
  <span class="badge text-bg-primary fs-6">Balance: <?= $balance ?> pts</span>
</div>
<div class="row g-3">
<?php foreach ($rewards as $r):
    $cost = (int)$r['points_cost']; $stock = (int)$r['stock'];
    $can = $balance >= $cost && $stock > 0; ?>
  <div class="col-md-4"><div class="card shadow-sm h-100"><div class="card-body d-flex flex-column">
    <h5><?= e($r['name']) ?></h5>
    <p class="text-muted small flex-grow-1"><?= e($r['description']) ?></p>
    <div class="mb-2"><strong><?= $cost ?> pts</strong> <span class="text-muted small">· <?= $stock ?> left</span></div>
    <form method="post" onsubmit="return confirm('Redeem this reward?');">
      <?= csrf_field() ?>
      <input type="hidden" name="reward_id" value="<?= (int)$r['id'] ?>">
      <button class="btn btn-primary w-100" <?= $can ? '' : 'disabled' ?>>
        <?= $stock < 1 ? 'Out of stock' : ($can ? 'Redeem' : 'Need ' . ($cost - $balance) . ' more points') ?>
      </button>
    </form>
  </div></div></div>
<?php endforeach; ?>
<?php if (!$rewards): ?><p class="text-muted">No rewards available right now.</p><?php endif; ?>
</div>
<a class="d-block mt-3 small" href="<?= url('customer/dashboard.php') ?>">Back to dashboard</a>
<?php require __DIR__ . '/../includes/footer.php'; ?>