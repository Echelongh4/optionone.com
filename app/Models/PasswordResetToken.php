<?php

declare(strict_types=1);

namespace App\Models;

use App\Core\Model;

class PasswordResetToken extends Model
{
    protected string $table = 'password_reset_tokens';

    public function createForUser(array $user, int $ttlMinutes = 60): string
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

    public function findValid(string $email, string $token): ?array
    {
        $this->purgeExpired();

        return $this->fetch(
            'SELECT prt.*, u.first_name, u.last_name, u.status
             FROM password_reset_tokens prt
             INNER JOIN users u ON u.id = prt.user_id
             WHERE prt.email = :email
               AND prt.token_hash = :token_hash
               AND prt.used_at IS NULL
               AND prt.expires_at >= NOW()
               AND u.status = "active"
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
            'DELETE FROM password_reset_tokens WHERE user_id = :user_id',
            ['user_id' => $userId]
        );
    }

    public function invalidateOtherOpenTokens(int $userId, int $exceptId): void
    {
        $this->execute(
            'DELETE FROM password_reset_tokens
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
        $this->execute('DELETE FROM password_reset_tokens WHERE expires_at < NOW() OR used_at IS NOT NULL');
    }
}
