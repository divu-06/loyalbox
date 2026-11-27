<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin');
const CLAIMS_ALERT_24H = 10;    // claims by one user in 24h that gets flagged
const BAD_ATTEMPTS_ALERT = 5;   // failed claim attempts from one IP in 24h that gets flagged

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'fulfil') {
    csrf_verify();
    // Only a pending redemption can move to fulfilled; repeating the click changes nothing
    $st = db()->prepare("UPDATE redemptions SET status='fulfilled' WHERE id = ? AND status = 'pending'");
    $st->execute([(int)($_POST['redemption_id'] ?? 0)]);
    flash($st->rowCount() ? 'success' : 'warning', $st->rowCount() ? 'Marked as fulfilled.' : 'No change made.');
    redirect('admin/monitor.php');
}

$claims = db()->query(
    "SELECT pc.claimed_at, u.name, u.email, b.product_name, b.points_value, pc.claimed_ip
     FROM product_codes pc JOIN users u ON u.id = pc.claimed_by JOIN code_batches b ON b.id = pc.batch_id
     WHERE pc.status = 'claimed' ORDER BY pc.claimed_at DESC LIMIT 50"
)->fetchAll();

$reds = db()->query(
    "SELECT rd.id, rd.created_at, u.name, u.email, r.name AS reward, rd.points_spent, rd.status
     FROM redemptions rd JOIN users u ON u.id = rd.user_id JOIN rewards r ON r.id = rd.reward_id
     ORDER BY rd.id DESC LIMIT 50"
)->fetchAll();

$heavy = db()->prepare(
    "SELECT u.name, u.email, COUNT(*) AS c FROM product_codes pc JOIN users u ON u.id = pc.claimed_by
     WHERE pc.claimed_at > (NOW() - INTERVAL 24 HOUR) GROUP BY u.id HAVING c >= ? ORDER BY c DESC"
);
$heavy->execute([CLAIMS_ALERT_24H]);
$heavy = $heavy->fetchAll();

$badIps = db()->prepare(
    "SELECT ip, COUNT(*) AS c FROM login_attempts
     WHERE attempt_type = 'claim' AND attempted_at > (NOW() - INTERVAL 24 HOUR)
     GROUP BY ip HAVING c >= ? ORDER BY c DESC"
);
$badIps->execute([BAD_ATTEMPTS_ALERT]);
$badIps = $badIps->fetchAll();

$pageTitle = 'Monitor';
require __DIR__ . '/../includes/header.php';
?>
<h3>Monitoring</h3>

<div class="card shadow-sm mb-4 border-warning"><div class="card-body">
  <h6>Unusual activity (last 24 hours)</h6>
  <?php foreach ($heavy as $h): ?>
    <div class="small">⚠ <?= e($h['name']) ?> (<?= e($h['email']) ?>) claimed <strong><?= (int)$h['c'] ?></strong> codes</div>
  <?php endforeach; ?>
  <?php foreach ($badIps as $b): ?>
    <div class="small">⚠ IP <?= e($b['ip']) ?> made <strong><?= (int)$b['c'] ?></strong> failed claim attempts</div>
  <?php endforeach; ?>
  <?php if (!$heavy && !$badIps): ?><div class="small text-muted">Nothing unusual. Alerts: <?= CLAIMS_ALERT_24H ?>+ claims by one user, or <?= BAD_ATTEMPTS_ALERT ?>+ failed attempts from one IP.</div><?php endif; ?>
</div></div>

<h5>Redemptions</h5>
<div class="card shadow-sm mb-4"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
  <thead><tr><th>Date</th><th>Customer</th><th>Reward</th><th class="text-end">Points</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($reds as $r): ?>
    <tr><td class="small text-muted"><?= e($r['created_at']) ?></td><td><?= e($r['name']) ?><div class="small text-muted"><?= e($r['email']) ?></div></td>
        <td><?= e($r['reward']) ?></td><td class="text-end"><?= (int)$r['points_spent'] ?></td>
        <td><span class="badge text-bg-<?= $r['status'] === 'pending' ? 'warning' : 'success' ?>"><?= e($r['status']) ?></span></td>
        <td class="text-end"><?php if ($r['status'] === 'pending'): ?>
          <form method="post" class="m-0"><?= csrf_field() ?><input type="hidden" name="action" value="fulfil">
            <input type="hidden" name="redemption_id" value="<?= (int)$r['id'] ?>"><button class="btn btn-sm btn-outline-success">Mark fulfilled</button></form>
        <?php endif; ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$reds): ?><tr><td colspan="6" class="text-muted">No redemptions yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>

<h5>Claimed codes</h5>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table table-sm align-middle mb-0">
  <thead><tr><th>Claimed at</th><th>Customer</th><th>Product</th><th class="text-end">Points</th><th>IP</th></tr></thead>
  <tbody>
  <?php foreach ($claims as $c): ?>
    <tr><td class="small text-muted"><?= e($c['claimed_at']) ?></td><td><?= e($c['name']) ?><div class="small text-muted"><?= e($c['email']) ?></div></td>
        <td><?= e($c['product_name']) ?></td><td class="text-end"><?= (int)$c['points_value'] ?></td><td class="small"><?= e($c['claimed_ip']) ?></td></tr>
  <?php endforeach; ?>
  <?php if (!$claims): ?><tr><td colspan="5" class="text-muted">No claims yet.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>