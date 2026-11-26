<?php
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/ratelimit.php';
if (current_user()) redirect('index.php');

$error = null;
$email = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
    $email = strtolower(trim((string)($_POST['email'] ?? '')));
    $pw    = (string)($_POST['password'] ?? '');
    $ip    = client_ip();

    if (too_many_attempts('login', $ip)) {
        $error = 'Too many failed attempts. Please try again in 15 minutes.';
    } else {
        $st = db()->prepare('SELECT id, password_hash, is_blocked FROM users WHERE email = ?');
        $st->execute([$email]);
        $user = $st->fetch();

        // Hash work happens even when the email is unknown, so response time doesn't reveal which emails exist.
        $hash = $user['password_hash'] ?? password_hash('dummy-password', PASSWORD_DEFAULT);
        $ok = password_verify($pw, $hash) && $user;

        if ($ok && $user['is_blocked']) {
            $error = 'This account has been blocked. Please contact the factory.';
        } elseif ($ok) {
            clear_attempts('login', $ip);
            login_user($user);
            redirect('index.php');
        } else {
            record_attempt('login', $ip, $email);
            $error = 'Invalid email or password.'; // same message for both cases
        }
    }
}

$pageTitle = 'Login';
require __DIR__ . '/includes/header.php';
?>
<div class="row justify-content-center"><div class="col-md-4">
  <div class="card shadow-sm"><div class="card-body">
    <h4 class="mb-3">Login</h4>
    <?php if ($error): ?><div class="alert alert-danger py-2"><?= e($error) ?></div><?php endif; ?>
    <form method="post">
      <?= csrf_field() ?>
      <div class="mb-3"><label class="form-label">Email</label>
        <input class="form-control" type="email" name="email" value="<?= e($email) ?>" required></div>
      <div class="mb-3"><label class="form-label">Password</label>
        <input class="form-control" type="password" name="password" required></div>
      <button class="btn btn-primary w-100">Login</button>
    </form>
    <p class="mt-3 mb-0 small">New here? <a href="<?= url('register.php') ?>">Create an account</a></p>
  </div></div>
</div></div>
<?php require __DIR__ . '/includes/footer.php'; ?>