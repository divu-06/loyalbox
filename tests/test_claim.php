<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/claim.php';

$pdo = db();
$fail = 0;
function check(string $name, bool $ok): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
    if (!$ok) $fail++;
}
function balance(PDO $pdo, int $uid): int {
    $s = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM point_transactions WHERE user_id=?');
    $s->execute([$uid]);
    return (int)$s->fetchColumn();
}

$adminId = (int)$pdo->query("SELECT id FROM users WHERE role='admin' LIMIT 1")->fetchColumn();
$tag = bin2hex(random_bytes(4));
$mkUser = $pdo->prepare("INSERT INTO users (name,email,password_hash,role) VALUES (?,?,'x','customer')");
$mkUser->execute(["T-A", "ta_$tag@test.local"]); $ua = (int)$pdo->lastInsertId();
$mkUser->execute(["T-B", "tb_$tag@test.local"]); $ub = (int)$pdo->lastInsertId();
$pdo->prepare('INSERT INTO code_batches (product_name,points_value,quantity,created_by) VALUES (?,?,?,?)')
    ->execute(['TEST-BATCH', 25, 3, $adminId]);
$batch = (int)$pdo->lastInsertId();

$codes = [generate_code(), generate_code(), generate_code()];
$ins = $pdo->prepare('INSERT INTO product_codes (batch_id, code_hash) VALUES (?,?)');
foreach ($codes as $c) $ins->execute([$batch, hash_code($c)]);

$ip1 = '203.0.113.9'; $ip2 = '203.0.113.10';
try {
    $r = claim_code($ua, $codes[0], $ip1);
    check('valid code is accepted and worth 25 points', $r['ok'] && $r['points'] === 25);
    check('same user cannot claim it again', !claim_code($ua, $codes[0], $ip1)['ok']);
    check('another user cannot claim it either', !claim_code($ub, $codes[0], $ip1)['ok']);
    check('balance is exactly 25 (no double credit)', balance($pdo, $ua) === 25 && balance($pdo, $ub) === 0);

    $s = $pdo->prepare('SELECT COUNT(*) FROM point_transactions WHERE code_id = (SELECT id FROM product_codes WHERE code_hash = ?)');
    $s->execute([hash_code($codes[0])]);
    check('exactly one ledger row exists for that code', (int)$s->fetchColumn() === 1);

    check('garbage input is rejected', !claim_code($ub, 'NOT-A-REAL-CODE', $ip1)['ok']);
    check('well-formed but non-existent code is rejected', !claim_code($ub, generate_code(), $ip1)['ok']);

    $messy = strtolower(str_replace('-', ' ', $codes[1]));
    check('lowercase / spaces / no hyphens still works', claim_code($ub, $messy, $ip1)['ok']);

    for ($i = 0; $i < 5; $i++) claim_code($ub, generate_code(), $ip2);
    $r = claim_code($ub, $codes[2], $ip2);
    check('after 5 bad attempts even a valid code is blocked', !$r['ok'] && str_contains($r['msg'], 'Too many'));
} finally {
    $pdo->prepare('DELETE FROM point_transactions WHERE user_id IN (?,?)')->execute([$ua, $ub]);
    $pdo->prepare('DELETE FROM product_codes WHERE batch_id = ?')->execute([$batch]);
    $pdo->prepare('DELETE FROM code_batches WHERE id = ?')->execute([$batch]);
    $pdo->prepare('DELETE FROM users WHERE id IN (?,?)')->execute([$ua, $ub]);
    $pdo->prepare('DELETE FROM login_attempts WHERE ip IN (?,?)')->execute([$ip1, $ip2]);
}
exit($fail ? 1 : 0);