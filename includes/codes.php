<?php
declare(strict_types=1);

// 32 symbols, no I, L, O, U (avoids look-alike confusion when typing from a box)
const CODE_ALPHABET = '0123456789ABCDEFGHJKMNPQRSTVWXYZ';

// 26 chars x 5 bits = 130 bits of CSPRNG randomness. Format: 5-5-5-5-6
function generate_code(): string {
    $c = '';
    for ($i = 0; $i < 26; $i++) {
        $c .= CODE_ALPHABET[random_int(0, 31)];
    }
    return substr($c, 0, 5) . '-' . substr($c, 5, 5) . '-' . substr($c, 10, 5)
         . '-' . substr($c, 15, 5) . '-' . substr($c, 20, 6);
}

// Accepts what a customer might type: lowercase, spaces, missing hyphens, O/0, I/L/1 mix-ups
function normalize_code(string $input): string {
    $s = preg_replace('/[^0-9A-Z]/', '', strtoupper($input)) ?? '';
    return strtr($s, ['O' => '0', 'I' => '1', 'L' => '1']);
}

function hash_code(string $input): string {
    return hash('sha256', normalize_code($input));
}