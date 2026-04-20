USE pos_system;

ALTER TABLE users
    ADD COLUMN username VARCHAR(100) NULL AFTER last_name;

UPDATE users
SET username = LOWER(TRIM(
    CASE
        WHEN email IS NOT NULL AND TRIM(email) <> '' THEN SUBSTRING_INDEX(TRIM(email), '@', 1)
        WHEN TRIM(COALESCE(first_name, '')) <> '' OR TRIM(COALESCE(last_name, '')) <> '' THEN REPLACE(CONCAT(TRIM(COALESCE(first_name, 'user')), '.', TRIM(COALESCE(last_name, ''))), ' ', '')
        ELSE CONCAT('user', id)
    END
))
WHERE username IS NULL OR TRIM(username) = '';

UPDATE users
SET username = CONCAT('user', id)
WHERE username IS NULL OR TRIM(username) = '' OR username = '.';

UPDATE users u
INNER JOIN (
    SELECT username
    FROM users
    WHERE username IS NOT NULL AND TRIM(username) <> ''
    GROUP BY username
    HAVING COUNT(*) > 1
) duplicates ON duplicates.username = u.username
SET u.username = LEFT(CONCAT(LEFT(u.username, 88), '.', u.id), 100);

ALTER TABLE users
    MODIFY COLUMN username VARCHAR(100) NOT NULL,
    ADD UNIQUE KEY uk_users_username (username);
