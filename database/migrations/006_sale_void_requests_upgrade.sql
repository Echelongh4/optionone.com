USE pos_system;

CREATE TABLE IF NOT EXISTS sale_void_requests (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sale_id BIGINT UNSIGNED NOT NULL,
    requested_by BIGINT UNSIGNED NOT NULL,
    status ENUM('pending', 'approved', 'rejected') NOT NULL DEFAULT 'pending',
    reason VARCHAR(255) NOT NULL,
    review_notes VARCHAR(255) NULL,
    reviewed_by BIGINT UNSIGNED NULL,
    reviewed_at DATETIME NULL,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_void_requests_sale (sale_id),
    INDEX idx_void_requests_status (status),
    INDEX idx_void_requests_requested_by (requested_by),
    CONSTRAINT fk_void_requests_sale FOREIGN KEY (sale_id) REFERENCES sales(id) ON DELETE CASCADE,
    CONSTRAINT fk_void_requests_requested_by FOREIGN KEY (requested_by) REFERENCES users(id),
    CONSTRAINT fk_void_requests_reviewed_by FOREIGN KEY (reviewed_by) REFERENCES users(id) ON DELETE SET NULL
);