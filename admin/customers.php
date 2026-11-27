<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $id = (int)($_POST['user_id'] ?? 0);
    $block = ($_POST['action'] ?? '') === 'block' ? 1 : 0;
    // role='customer' in the WHERE clause: an admin account can never be blocked from this page
    $st = db()->prepare("UPDATE users SET is_blocked = ? WHERE id = ? AND role = 'customer'");
    $st->execute([$block, $id]);
    if ($st->rowCount()) flash('success', $block ? 'Customer blocked.' : 'Customer unblocked.');
    else flash('warning', 'No change made.');
    redirect('admin/customers.php');
}

$q = trim((string)($_GET['q'] ?? ''));
$like = '%' . $q . '%';
$st = db()->prepare(
    "SELECT u.id, u.name, u.email, u.is_blocked, u.created_at,
            COALESCE(SUM(t.points),0) AS balance,
            COALESCE(SUM(CASE WHEN t.type='earn' THEN t.points END),0) AS earned,
            COUNT(CASE WHEN t.type='earn' THEN 1 END) AS claims
     FROM users u LEFT JOIN point_transactions t ON t.user_id = u.id
     WHERE u.role = 'customer' AND (u.name LIKE ? OR u.email LIKE ?)
     GROUP BY u.id ORDER BY u.id DESC LIMIT 200"
);
$st->execute([$like, $like]);
$rows = $st->fetchAll();

$pageTitle = 'Customers';
require __DIR__ . '/../includes/header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
  <h3 class="mb-0">Customers</h3>
  <form class="d-flex gap-2" method="get">
    <input class="form-control form-control-sm" name="q" placeholder="Search name or email" value="<?= e($q) ?>">
    <button class="btn btn-sm btn-outline-secondary">Search</button>
  </form>
</div>
<div class="card shadow-sm"><div class="table-responsive">
<table class="table align-middle mb-0">
  <thead><tr><th>#</th><th>Name</th><th>Email</th><th class="text-end">Balance</th><th class="text-end">Earned</th><th class="text-end">Claims</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($rows as $r): ?>
    <tr>
      <td><?= (int)$r['id'] ?></td><td><?= e($r['name']) ?></td><td><?= e($r['email']) ?></td>
      <td class="text-end"><?= (int)$r['balance'] ?></td><td class="text-end"><?= (int)$r['earned'] ?></td><td class="text-end"><?= (int)$r['claims'] ?></td>
      <td><?= $r['is_blocked'] ? '<span class="badge text-bg-danger">Blocked</span>' : '<span class="badge text-bg-success">Active</span>' ?></td>
      <td class="text-end">
        <form method="post" class="m-0" onsubmit="return confirm('Are you sure?');">
          <?= csrf_field() ?>
          <input type="hidden" name="user_id" value="<?= (int)$r['id'] ?>">
          <input type="hidden" name="action" value="<?= $r['is_blocked'] ? 'unblock' : 'block' ?>">
          <button class="btn btn-sm <?= $r['is_blocked'] ? 'btn-outline-success' : 'btn-outline-danger' ?>"><?= $r['is_blocked'] ? 'Unblock' : 'Block' ?></button>
        </form>
      </td>
    </tr>
  <?php endforeach; ?>
  <?php if (!$rows): ?><tr><td colspan="8" class="text-muted">No customers found.</td></tr><?php endif; ?>
  </tbody>
</table>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>