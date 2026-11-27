<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/redeem.php';
[$_, $userId, $rewardId, $startAt] = $argv;
while (microtime(true) < (float)$startAt) usleep(100);
$r = redeem_reward((int)$userId, (int)$rewardId);
echo ($r['ok'] ? 'WON   ' : 'lost  ') . $r['msg'] . "\n";