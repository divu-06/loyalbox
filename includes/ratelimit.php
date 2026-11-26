<?php
declare(strict_types=1);

require_once __DIR__ . '/db.php';

// Counts recent failed attempts from this IP (login or claim).
function too_many_attempts(string $type, string $ip, int $max = 5, int $minutes = 15): bool {
    $minutes = max(1, $minutes); // int, safe to inline
    $st = db()->prepare(
        "SELECT COUNT(*) FROM login_attempts
         WHERE attempt_type = ? AND ip = ? AND attempted_at > (NOW() - INTERVAL $minutes MINUTE)"
    );
    $st->execute([$type, $ip]);
    return (int)$st->fetchColumn() >= $max;
}

function record_attempt(string $type, string $ip, ?string $identifier): void {
    db()->prepare('INSERT INTO login_attempts (attempt_type, ip, identifier) VALUES (?, ?, ?)')
        ->execute([$type, $ip, $identifier]);
}

function clear_attempts(string $type, string $ip): void {
    db()->prepare('DELETE FROM login_attempts WHERE attempt_type = ? AND ip = ?')
        ->execute([$type, $ip]);
}