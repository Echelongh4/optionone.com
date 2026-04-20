USE pos_system;

ALTER TABLE users
    ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER email;

UPDATE users
SET email_verified_at = COALESCE(last_login_at, created_at, NOW())
WHERE status = 'active'
  AND email_verified_at IS NULL;

CREATE TABLE IF NOT EXISTS email_verification_tokens (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id BIGINT UNSIGNED NOT NULL,
    email VARCHAR(150) NOT NULL,
    token_hash CHAR(64) NOT NULL,
    expires_at DATETIME NOT NULL,
    used_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY uk_email_verification_token_hash (token_hash),
    INDEX idx_email_verification_user (user_id),
    INDEX idx_email_verification_email (email),
    INDEX idx_email_verification_expires (expires_at),
    CONSTRAINT fk_email_verification_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);
