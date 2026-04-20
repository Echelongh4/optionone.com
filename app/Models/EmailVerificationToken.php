<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class EmailVerificationToken extends Model
{
    protected string $table = 'email_verification_tokens';

    public function createForUser(array $user, int $ttlMinutes = 1440): string
    {
        $this->purgeExpired();
        $this->invalidateUserTokens((int) $user['id']);

        $token = bin2hex(random_bytes(32));
        $expiresAt = date('Y-m-d H:i:s', time() + max(1, $ttlMinutes) * 60);

        $this->insert([
            'user_id' => (int) $user['id'],
            'email' => strtolower(trim((string) $user['email'])),
            'token_hash' => hash('sha256', $token),
            'expires_at' => $expiresAt,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        return $token;
    }

    public function issuedRecentlyForUser(int $userId, int $cooldownSeconds = 60): bool
    {
        $this->purgeExpired();
        $cooldownAt = date('Y-m-d H:i:s', time() - max(1, $cooldownSeconds));

        return $this->fetch(
            'SELECT id
             FROM email_verification_tokens
             WHERE user_id = :user_id
               AND used_at IS NULL
               AND created_at >= :cooldown_at
             LIMIT 1',
            [
                'user_id' => $userId,
                'cooldown_at' => $cooldownAt,
            ]
        ) !== null;
    }

    public function findValid(string $email, string $token): ?array
    {
        $this->purgeExpired();

        return $this->fetch(
            'SELECT evt.*, u.first_name, u.last_name, u.status, u.email_verified_at
             FROM email_verification_tokens evt
             INNER JOIN users u ON u.id = evt.user_id
             WHERE evt.email = :email
               AND evt.token_hash = :token_hash
               AND evt.used_at IS NULL
               AND evt.expires_at >= NOW()
               AND u.deleted_at IS NULL
             LIMIT 1',
            [
                'email' => strtolower(trim($email)),
                'token_hash' => hash('sha256', $token),
            ]
        );
    }

    public function markUsed(int $id): void
    {
        $this->updateRecord([
            'used_at' => date('Y-m-d H:i:s'),
        ], 'id = :id', ['id' => $id]);
    }

    public function invalidateUserTokens(int $userId): void
    {
        $this->execute(
            'DELETE FROM email_verification_tokens WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
    }

    public function invalidateOtherOpenTokens(int $userId, int $exceptId): void
    {
        $this->execute(
            'DELETE FROM email_verification_tokens
             WHERE user_id = :user_id
               AND id <> :except_id
               AND used_at IS NULL',
            [
                'user_id' => $userId,
                'except_id' => $exceptId,
            ]
        );
    }

    public function purgeExpired(): void
    {
        $this->execute('DELETE FROM email_verification_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');
    }
}
