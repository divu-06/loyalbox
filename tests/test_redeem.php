<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/redeem.php';

$pdo = db();
$fail = 0;
function check(string $name, bool $ok): void { global $fail; echo ($ok ? 'PASS' : 'FAIL') . "  $name\n"; if (!$ok) $fail++; }
function val(PDO $pdo, string $sql, array $p = []) { $s = $pdo->prepare($sql); $s->execute($p); return $s->fetchColumn(); }
function bal(PDO $pdo, int $u): int { return (int)val($pdo, 'SELECT COALESCE(SUM(points),0) FROM point_transactions WHERE user_id=?', [$u]); }

$tag = bin2hex(random_bytes(4));
$mk = $pdo->prepare("INSERT INTO users (name,email,password_hash,role,is_blocked) VALUES (?,?,'x','customer',?)");
$mk->execute(['R-A', "ra_$tag@test.local", 0]); $ua = (int)$pdo->lastInsertId();
$mk->execute(['R-B', "rb_$tag@test.local", 0]); $ub = (int)$pdo->lastInsertId();
$mk->execute(['R-C', "rc_$tag@test.local", 1]); $uc = (int)$pdo->lastInsertId();   // blocked

$give = $pdo->prepare("INSERT INTO point_transactions (user_id,type,points) VALUES (?, 'earn', ?)");
$give->execute([$ua, 100]); $give->execute([$ub, 10]); $give->execute([$uc, 100]);

$mkR = $pdo->prepare('INSERT INTO rewards (name,description,points_cost,stock,is_active) VALUES (?,?,?,?,?)');
$mkR->execute(['TEST-R1', '', 40, 5, 1]); $r1 = (int)$pdo->lastInsertId();
$mkR->execute(['TEST-R2', '', 10, 0, 1]); $r2 = (int)$pdo->lastInsertId();   // no stock
$mkR->execute(['TEST-R3', '', 10, 5, 0]); $r3 = (int)$pdo->lastInsertId();   // inactive

try {
    check('too few points is rejected', !redeem_reward($ub, $r1)['ok'] && bal($pdo, $ub) === 10);
    check('valid redemption succeeds', redeem_reward($ua, $r1)['ok']);
    check('balance dropped by 40 and stock by 1',
        bal($pdo, $ua) === 60 && (int)val($pdo, 'SELECT stock FROM rewards WHERE id=?', [$r1]) === 4);
    check('exactly one redemption and one negative ledger row',
        (int)val($pdo, 'SELECT COUNT(*) FROM redemptions WHERE user_id=?', [$ua]) === 1
        && (int)val($pdo, "SELECT COUNT(*) FROM point_transactions WHERE user_id=? AND type='redeem' AND points=-40", [$ua]) === 1);
    check('out-of-stock reward is rejected', !redeem_reward($ua, $r2)['ok']);
    check('inactive reward is rejected', !redeem_reward($ua, $r3)['ok']);
    check('non-existent reward is rejected', !redeem_reward($ua, 999999)['ok']);
    check('blocked user cannot redeem', !redeem_reward($uc, $r1)['ok'] && bal($pdo, $uc) === 100);
    check('second redeem works (60 to 20)', redeem_reward($ua, $r1)['ok'] && bal($pdo, $ua) === 20);
    check('third redeem is rejected: balance can never go negative', !redeem_reward($ua, $r1)['ok'] && bal($pdo, $ua) === 20);
} finally {
    $in = "IN ($ua,$ub,$uc)";
    $pdo->exec("DELETE FROM point_transactions WHERE user_id $in");
    $pdo->exec("DELETE FROM redemptions WHERE user_id $in");
    $pdo->exec("DELETE FROM rewards WHERE id IN ($r1,$r2,$r3)");
    $pdo->exec("DELETE FROM users WHERE id $in");
}
exit($fail ? 1 : 0);