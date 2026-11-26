<?php
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/codes.php';
$u = require_role('admin');

// Stops spreadsheet formula injection if a product name starts with = + - @
function csv_safe(string $s): string {
    return preg_match('/^[=+\-@\t\r]/', $s) ? "'" . $s : $s;
}

$errors = [];
$product = '';
$points = 10;
$qty = 50;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $product = trim((string)($_POST['product_name'] ?? ''));
    $points  = (int)($_POST['points_value'] ?? 0);
    $qty     = (int)($_POST['quantity'] ?? 0);

    if (mb_strlen($product) < 2 || mb_strlen($product) > 120) $errors[] = 'Product name must be 2 to 120 characters.';
    if ($points < 1 || $points > 10000) $errors[] = 'Points must be between 1 and 10,000.';
    if ($qty < 1 || $qty > 1000) $errors[] = 'Quantity must be between 1 and 1,000 per batch.';

    if (!$errors) {
        $pdo = db();
        $plain = [];
        try {
            $pdo->beginTransaction();
            $pdo->prepare('INSERT INTO code_batches (product_name, points_value, quantity, created_by) VALUES (?,?,?,?)')
                ->execute([$product, $points, $qty, $u['id']]);
            $batchId = (int)$pdo->lastInsertId();
            $ins = $pdo->prepare('INSERT INTO product_codes (batch_id, code_hash) VALUES (?, ?)');
            for ($i = 0; $i < $qty; $i++) {
                $code = generate_code();
                $ins->execute([$batchId, hash_code($code)]);   // only the hash is stored
                $plain[] = $code;
            }
            $pdo->commit();
        } catch (Throwable $ex) {
            if ($pdo->inTransaction()) $pdo->rollBack();
            $errors[] = 'Could not generate codes. Nothing was saved.';
        }

        if (!$errors) {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="batch-' . $batchId . '-codes.csv"');
            header('Cache-Control: no-store');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['batch_id', 'product', 'points', 'code'], ',', '"', '');
            foreach ($plain as $c) {
                fputcsv($out, [$batchId, csv_safe($product), $points, $c], ',', '"', '');
            }
            fclose($out);
            exit;
        }
    }
}

$batches = db()->query(
    "SELECT b.id, b.product_name, b.points_value, b.quantity, b.created_at,
            COALESCE(SUM(c.status='unused'),0) AS unused,
            COALESCE(SUM(c.status='claimed'),0) AS claimed,
            COALESCE(SUM(c.status='void'),0) AS voided
     FROM code_batches b LEFT JOIN product_codes c ON c.batch_id = b.id
     GROUP BY b.id ORDER BY b.id DESC LIMIT 20"
)->fetchAll();

$pageTitle = 'Generate Codes';
require __DIR__ . '/../includes/header.php';
?>
<h3>Generate product codes</h3>
<div class="row g-4 mt-1">
  <div class="col-md-5">
    <div class="card shadow-sm"><div class="card-body">
      <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
      <form method="post">
        <?= csrf_field() ?>
        <div class="mb-3"><label class="form-label">Product name</label>
          <input class="form-control" name="product_name" value="<?= e($product) ?>" required></div>
        <div class="mb-3"><label class="form-label">Points per code</label>
          <input class="form-control" type="number" name="points_value" min="1" max="10000" value="<?= (int)$points ?>" required></div>
        <div class="mb-3"><label class="form-label">Quantity (max 1,000)</label>
          <input class="form-control" type="number" name="quantity" min="1" max="1000" value="<?= (int)$qty ?>" required></div>
        <button class="btn btn-primary w-100">Generate and download CSV</button>
      </form>
      <p class="small text-muted mt-3 mb-0">Codes are shown <strong>once</strong>, in the CSV. Only hashes are stored, so a lost file cannot be recovered.</p>
    </div></div>
  </div>
  <div class="col-md-7">
    <div class="card shadow-sm"><div class="card-body">
      <h6>Recent batches</h6>
      <div class="table-responsive">
      <table class="table table-sm align-middle mb-0">
        <thead><tr><th>#</th><th>Product</th><th>Pts</th><th>Qty</th><th>Unused</th><th>Claimed</th><th>Void</th></tr></thead>
        <tbody>
        <?php foreach ($batches as $b): ?>
          <tr><td><?= (int)$b['id'] ?></td><td><?= e($b['product_name']) ?></td><td><?= (int)$b['points_value'] ?></td>
              <td><?= (int)$b['quantity'] ?></td><td><?= (int)$b['unused'] ?></td><td><?= (int)$b['claimed'] ?></td><td><?= (int)$b['voided'] ?></td></tr>
        <?php endforeach; ?>
        <?php if (!$batches): ?><tr><td colspan="7" class="text-muted">No batches yet.</td></tr><?php endif; ?>
        </tbody>
      </table>
      </div>
    </div></div>
  </div>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>