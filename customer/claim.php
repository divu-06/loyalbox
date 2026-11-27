<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/claim.php';
$u = require_role('customer');

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $res = claim_code((int)$u['id'], (string)($_POST['code'] ?? ''), client_ip());
    if ($res['ok']) {
        flash('success', $res['points'] . ' points added to your balance!');
        redirect('customer/dashboard.php');   // redirect after POST: refreshing can't resubmit
    }
    $error = $res['msg'];
}

$pageTitle = 'Claim Code';
require __DIR__ . '/../includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-5">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Claim points</h4>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post" autocomplete="off">
      <?= csrf_field() ?>
      <div class="mb-3">
        <label class="form-label">Product code (found inside the box)</label>
        <input class="form-control form-control-lg text-uppercase" name="code" maxlength="40"
               placeholder="XXXXX-XXXXX-XXXXX-XXXXX-XXXXXX" required>
      </div>
      <button class="btn btn-primary w-100">Claim</button>
    </form>
    <a class="d-block mt-3 small" href="<?= url('customer/dashboard.php') ?>">Back to dashboard</a>
  </div></div>
</div></div>
<?php require __DIR__ . '/../includes/footer.php'; ?>