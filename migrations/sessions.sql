CREATE TABLE IF NOT EXISTS sessions (
    session_id VARCHAR(255) NOT NULL PRIMARY KEY,
    payload TEXT,
    flash_data TEXT,
    created_at INTEGER,
    last_activity INTEGER,
    expiration INTEGER,
    user_id VARCHAR(255),
    ip_address VARCHAR(45),
    user_agent TEXT
);
