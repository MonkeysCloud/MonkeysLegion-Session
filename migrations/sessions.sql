CREATE TABLE IF NOT EXISTS sessions (
    session_id VARCHAR(255) NOT NULL,
    payload TEXT,
    flash_data TEXT,
    created_at BIGINT UNSIGNED NOT NULL,
    last_activity BIGINT UNSIGNED NOT NULL,
    expiration BIGINT UNSIGNED NOT NULL,
    user_id BIGINT UNSIGNED NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    PRIMARY KEY (session_id),
    INDEX idx_sessions_last_activity (last_activity),
    INDEX idx_sessions_expiration (expiration),
    INDEX idx_sessions_user_id (user_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;