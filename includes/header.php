<?php
// Include auth.php BEFORE this file.
$u = current_user();
$flash = get_flash();
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= e($pageTitle ?? 'LoyalBox') ?></title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
<nav class="navbar navbar-dark bg-dark mb-4">
  <div class="container">
    <a class="navbar-brand" href="<?= url('index.php') ?>">📦 LoyalBox</a>
    <div class="d-flex align-items-center gap-2">
      <?php if ($u): ?>
        <span class="text-light small"><?= e($u['name']) ?> (<?= e($u['role']) ?>)</span>
        <form method="post" action="<?= url('logout.php') ?>" class="m-0">
          <?= csrf_field() ?>
          <button class="btn btn-outline-light btn-sm">Logout</button>
        </form>
      <?php else: ?>
        <a class="btn btn-outline-light btn-sm" href="<?= url('login.php') ?>">Login</a>
        <a class="btn btn-light btn-sm" href="<?= url('register.php') ?>">Register</a>
      <?php endif; ?>
    </div>
  </div>
</nav>
<main class="container">
<?php if ($flash): ?>
  <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['msg']) ?></div>
<?php endif; ?>