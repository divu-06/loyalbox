<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name  = trim((string)($_POST['name'] ?? ''));
    $desc  = trim((string)($_POST['description'] ?? ''));
    $cost  = (int)($_POST['points_cost'] ?? 0);
    $stock = (int)($_POST['stock'] ?? -1);
    $active = isset($_POST['is_active']) ? 1 : 0;

    $err = [];
    if (mb_strlen($name) < 2 || mb_strlen($name) > 120) $err[] = 'Name must be 2 to 120 characters.';
    if ($cost < 1 || $cost > 100000) $err[] = 'Points cost must be between 1 and 100,000.';
    if ($stock < 0 || $stock > 100000) $err[] = 'Stock must be between 0 and 100,000.';

    if ($err) {
        flash('danger', implode(' ', $err));
    } elseif (($_POST['action'] ?? '') === 'create') {
        db()->prepare('INSERT INTO rewards (name, description, points_cost, stock, is_active) VALUES (?,?,?,?,?)')
            ->execute([$name, $desc, $cost, $stock, $active]);
        flash('success', 'Reward added.');
    } elseif (($_POST['action'] ?? '') === 'update') {
        db()->prepare('UPDATE rewards SET name=?, description=?, points_cost=?, stock=?, is_active=? WHERE id=?')
            ->execute([$name, $desc, $cost, $stock, $active, (int)($_POST['reward_id'] ?? 0)]);
        flash('success', 'Reward updated.');
    }
    redirect('admin/rewards.php');
}

$rewards = db()->query('SELECT * FROM rewards ORDER BY is_active DESC, points_cost')->fetchAll();

$pageTitle = 'Manage Rewards';
require __DIR__ . '/../includes/header.php';
?>
<h3>Manage rewards</h3>
<div class="card shadow-sm mb-4"><div class="card-body">
  <h6>Add reward</h6>
  <form method="post" class="row g-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="create">
    <div class="col-md-3"><input class="form-control" name="name" placeholder="Name" required></div>
    <div class="col-md-4"><input class="form-control" name="description" placeholder="Description"></div>
    <div class="col-md-2"><input class="form-control" type="number" name="points_cost" min="1" placeholder="Points" required></div>
    <div class="col-md-1"><input class="form-control" type="number" name="stock" min="0" placeholder="Stock" required></div>
    <div class="col-md-1 form-check pt-2 ms-2"><input class="form-check-input" type="checkbox" name="is_active" checked> <label class="form-check-label small">Active</label></div>
    <div class="col-md-1"><button class="btn btn-primary w-100">Add</button></div>
  </form>
</div></div>

<div class="card shadow-sm"><div class="card-body">
  <h6>Existing rewards</h6>
  <?php foreach ($rewards as $r): ?>
  <form method="post" class="row g-2 align-items-center border-bottom py-2">
    <?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="reward_id" value="<?= (int)$r['id'] ?>">
    <div class="col-md-3"><input class="form-control form-control-sm" name="name" value="<?= e($r['name']) ?>" required></div>
    <div class="col-md-4"><input class="form-control form-control-sm" name="description" value="<?= e($r['description']) ?>"></div>
    <div class="col-md-2"><input class="form-control form-control-sm" type="number" name="points_cost" min="1" value="<?= (int)$r['points_cost'] ?>" required></div>
    <div class="col-md-1"><input class="form-control form-control-sm" type="number" name="stock" min="0" value="<?= (int)$r['stock'] ?>" required></div>
    <div class="col-md-1 form-check ms-2"><input class="form-check-input" type="checkbox" name="is_active" <?= $r['is_active'] ? 'checked' : '' ?>> <label class="form-check-label small">Active</label></div>
    <div class="col-md-1"><button class="btn btn-sm btn-outline-primary w-100">Save</button></div>
  </form>
  <?php endforeach; ?>
  <p class="small text-muted mt-3 mb-0">Changing a cost never alters past redemptions, because each one stores the points actually spent.</p>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>