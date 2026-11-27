<?php
declare(strict_types=1);
require_once __DIR__ . '/db.php';

function redeem_reward(int $userId, int $rewardId): array {
    $pdo = db();
    $fail = function (string $msg) use ($pdo): array {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => $msg];
    };

    try {
        $pdo->beginTransaction();

        // Lock order is always user, then reward. A fixed order avoids deadlocks.
        $s = $pdo->prepare('SELECT id, is_blocked FROM users WHERE id = ? FOR UPDATE');
        $s->execute([$userId]);
        $user = $s->fetch();
        if (!$user || $user['is_blocked']) return $fail('Your account cannot redeem rewards.');

        $s = $pdo->prepare('SELECT id, name, points_cost, stock, is_active FROM rewards WHERE id = ? FOR UPDATE');
        $s->execute([$rewardId]);
        $reward = $s->fetch();
        if (!$reward || !$reward['is_active']) return $fail('This reward is not available.');
        if ((int)$reward['stock'] < 1)         return $fail('Sorry, this reward is out of stock.');

        // Balance is read only AFTER we hold the user lock, so it can't be stale.
        $s = $pdo->prepare('SELECT COALESCE(SUM(points),0) FROM point_transactions WHERE user_id = ?');
        $s->execute([$userId]);
        $cost = (int)$reward['points_cost'];
        if ((int)$s->fetchColumn() < $cost) return $fail('Not enough points for this reward.');

        $d = $pdo->prepare('UPDATE rewards SET stock = stock - 1 WHERE id = ? AND stock > 0');
        $d->execute([$rewardId]);
        if ($d->rowCount() !== 1) return $fail('Sorry, this reward is out of stock.');

        $pdo->prepare('INSERT INTO redemptions (user_id, reward_id, points_spent) VALUES (?, ?, ?)')
            ->execute([$userId, $rewardId, $cost]);
        $redemptionId = (int)$pdo->lastInsertId();

        $pdo->prepare("INSERT INTO point_transactions (user_id, type, points, redemption_id) VALUES (?, 'redeem', ?, ?)")
            ->execute([$userId, -$cost, $redemptionId]);

        $pdo->commit();
        return ['ok' => true, 'msg' => 'Redeemed: ' . $reward['name'] . ' (-' . $cost . ' points).'];
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) $pdo->rollBack();
        return ['ok' => false, 'msg' => 'Something went wrong. No points were deducted.'];
    }
}