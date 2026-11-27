<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/codes.php';
require_once __DIR__ . '/ratelimit.php';

// One message for "doesn't exist", "already used" and "void": an attacker learns nothing from it.
const CLAIM_FAIL_MSG = 'That code is invalid or has already been used.';

function claim_code(int $userId, string $input, string $ip): array {
    if (too_many_attempts('claim', $ip)) {
        return ['ok' => false, 'msg' => 'Too many failed attempts. Please try again in 15 minutes.'];
    }

    $normalized = normalize_code($input);
    if (!preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/', $normalized)) {
        record_attempt('claim', $ip, (string)$userId);
        return ['ok' => false, 'msg' => CLAIM_FAIL_MSG];
    }
    $hash = hash('sha256', $normalized);
    $pdo = db();

    try {
        $pdo->beginTransaction();

        // The atomic step: only one request can ever flip unused -> claimed.
        $up = $pdo->prepare(
            "UPDATE product_codes
             SET status = 'claimed', claimed_by = ?, claimed_at = NOW(), claimed_ip = ?
             WHERE code_hash = ? AND status = 'unused'"
        );
        $up->execute([$userId, $ip, $hash]);

        if ($up->rowCount() !== 1) {
            $pdo->rollBack();
            record_attempt('claim', $ip, (string)$userId);   // after rollback, or the record would be undone too
            return ['ok' => false, 'msg' => CLAIM_FAIL_MSG];
        }

        $st = $pdo->prepare(
            'SELECT pc.id, b.points_value FROM product_codes pc
             JOIN code_batches b ON b.id = pc.batch_id WHERE pc.code_hash = ?'
        );
        $st->execute([$hash]);
        $row = $st->fetch();

        $pdo->prepare("INSERT INTO point_transactions (user_id, type, points, code_id) VALUES (?, 'earn', ?, ?)")
            ->execute([$userId, (int)$row['points_value'], (int)$row['id']]);

        $pdo->commit();
        return ['ok' => true, 'points' => (int)$row['points_value'], 'msg' => 'Code claimed.'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Something went wrong. No points were credited.'];
    }
}