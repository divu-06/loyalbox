<?php
if (PHP_SAPI !== 'cli') exit;
require __DIR__ . '/../includes/claim.php';

[$_, $userId, $code, $startAt] = $argv;
while (microtime(true) < (float)$startAt) usleep(100);   // all processes fire at the same instant
$r = claim_code((int)$userId, $code, '198.51.100.' . $userId . rand(0, 9));
echo ($r['ok'] ? 'WON   ' : 'lost  ') . "user $userId\n";