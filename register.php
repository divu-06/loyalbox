<?php
require_once __DIR__ . '/includes/auth.php';
if (current_user()) redirect('index.php');

$errors = [];
$name = $email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $name    = trim((string)($_POST['name'] ?? ''));
    $email   = strtolower(trim((string)($_POST['email'] ?? '')));
    $pw      = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm'] ?? '');

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100) $errors[] = 'Name must be 2 to 100 characters.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 190) $errors[] = 'Enter a valid email.';
    if (strlen($pw) < 8) $errors[] = 'Password must be at least 8 characters.';
    if (strlen($pw) > 72) $errors[] = 'Password must be at most 72 characters.'; // bcrypt ignores bytes past 72
    if ($pw !== $confirm) $errors[] = 'Passwords do not match.';

    if (!$errors) {
        try {
            // Role is hardcoded: public registration can only ever create customers.
            db()->prepare("INSERT INTO users (name, email, password_hash, role) VALUES (?, ?, ?, 'customer')")
                ->execute([$name, $email, password_hash($pw, PASSWORD_DEFAULT)]);
            $id = (int)db()->lastInsertId();
            login_user(['id' => $id]);
            flash('success', 'Welcome to LoyalBox!');
            redirect('customer/dashboard.php');
        } catch (PDOException $e) {
            if ($e->getCode() === '23000') $errors[] = 'That email is already registered.'; // UNIQUE constraint
            else throw $e;
        }
    }
}

$pageTitle = 'Register';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-5">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Create account</h4>
    <?php foreach ($errors as $err): ?><div class="alert alert-danger py-2"><?= e($err) ?></div><?php endforeach; ?>
    <form method="post" novalidate>
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Name</label>
        <input class="form-control" name="name" value="<?= e($name) ?>" required></div>
      <div class="mb-3"><label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" value="<?= e($email) ?>" required></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required></div>
      <div class="mb-3"><label class="form-label">Confirm password</label>
        <input class="form-control" type="password" name="confirm" required></div>
      <button class="btn btn-primary w-100">Register</button>
    </form>
    <p class="mt-3 mb-0 small">Already registered? <a href="<?= url('login.php') ?>">Login</a></p>
  </div></div>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>