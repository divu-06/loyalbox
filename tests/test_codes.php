<?php
require __DIR__ . '/../includes/codes.php';

$fail = 0;
function check(string $name, bool $ok): void {
    global $fail;
    echo ($ok ? 'PASS' : 'FAIL') . "  $name\n";
    if (!$ok) $fail++;
}

$seen = [];
for ($i = 0; $i < 20000; $i++) $seen[generate_code()] = true;
check('20,000 generated codes are all unique', count($seen) === 20000);

$c = generate_code();
check('format is 5-5-5-5-6 from the allowed alphabet',
    (bool)preg_match('/^[0-9A-HJKMNP-TV-Z]{5}(-[0-9A-HJKMNP-TV-Z]{5}){3}-[0-9A-HJKMNP-TV-Z]{6}$/', $c));
check('26 symbols = 130 bits', strlen(normalize_code($c)) === 26);
check('lowercase/spaces/no-hyphen input hashes identically',
    hash_code($c) === hash_code(strtolower(str_replace('-', ' ', $c))));
check('hash is 64 hex chars', (bool)preg_match('/^[0-9a-f]{64}$/', hash_code($c)));
check('two codes give different hashes', hash_code(generate_code()) !== hash_code(generate_code()));

exit($fail ? 1 : 0);