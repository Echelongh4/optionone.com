<?php

declare(strict_types=1);

namespace App\Core;

use App\Models\User;

class RateLimiter
{
    private int $maxAttempts = 5;
    private int $lockMinutes = 15;

    public function isBlocked(string $identifier, string $ipAddress): bool
    {
        $statement = Database::connection()->prepare(
            'SELECT COUNT(*) AS attempts
             FROM login_attempts
             WHERE success = 0
               AND email = :email
               AND ip_address = :ip_address
               AND attempted_at >= :cutoff'
        );
        $statement->execute([
            'email' => strtolower(trim($identifier)),
            'ip_address' => $ipAddress,
            'cutoff' => date('Y-m-d H:i:s', strtotime('-' . $this->lockMinutes . ' minutes')),
        ]);
        $attempts = (int) $statement->fetchColumn();

        $user = (new User())->findByLogin($identifier);

        return $attempts >= $this->maxAttempts
            || ($user !== null && !empty($user['locked_until']) && strtotime((string) $user['locked_until']) > time());
    }

    public function recordFailure(string $identifier, string $ipAddress, ?int $userId = null): void
    {
        Database::connection()->prepare(
            'INSERT INTO login_attempts (email, ip_address, attempted_at, success) VALUES (:email, :ip_address, NOW(), 0)'
        )->execute([
            'email' => strtolower(trim($identifier)),
            'ip_address' => $ipAddress,
        ]);

        if ($userId === null) {
            return;
        }

        $userModel = new User();
        $user = $userModel->findById($userId);
        $failedAttempts = ((int) ($user['failed_login_attempts'] ?? 0)) + 1;
        $payload = ['failed_login_attempts' => $failedAttempts];

        if ($failedAttempts >= $this->maxAttempts) {
            $payload['locked_until'] = date('Y-m-d H:i:s', strtotime('+' . $this->lockMinutes . ' minutes'));
        }

        $userModel->updateSecurityFields($userId, $payload);
    }

    public function recordSuccess(string $identifier, string $ipAddress, int $userId): void
    {
        Database::connection()->prepare(
            'INSERT INTO login_attempts (email, ip_address, attempted_at, success) VALUES (:email, :ip_address, NOW(), 1)'
        )->execute([
            'email' => strtolower(trim($identifier)),
            'ip_address' => $ipAddress,
        ]);

        (new User())->updateSecurityFields($userId, [
            'failed_login_attempts' => 0,
            'locked_until' => null,
        ]);
    }
}
