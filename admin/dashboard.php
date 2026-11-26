<?php
require_once __DIR__ . '/../includes/auth.php';
$u = require_role('admin');

$customers = (int)db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn();
$codes = db()->query("SELECT status, COUNT(*) c FROM product_codes GROUP BY status")->fetchAll(PDO::FETCH_KEY_PAIR);

$pageTitle = 'Admin Dashboard';
require __DIR__ . '/../includes/header.php';
?>
<h3>Admin dashboard</h3>
<a class="btn btn-primary btn-sm mt-2" href="<?= url('admin/generate.php') ?>">Generate codes</a>
<div class="row g-3 mt-1">
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
    <div class="text-muted small">Customers</div><div class="h2"><?= $customers ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
    <div class="text-muted small">Unused codes</div><div class="h2"><?= (int)($codes['unused'] ?? 0) ?></div></div></div></div>
  <div class="col-md-3"><div class="card shadow-sm"><div class="card-body">
    <div class="text-muted small">Claimed codes</div><div class="h2"><?= (int)($codes['claimed'] ?? 0) ?></div></div></div></div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>