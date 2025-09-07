-- Multi-Factor Authentication Database Schema
-- Comprehensive MFA support for traditional and modern authentication methods

-- MFA methods enabled by users
CREATE TABLE IF NOT EXISTS mfa_methods (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    method VARCHAR(50) NOT NULL, -- password, otp_email, otp_sms, totp, webauthn, etc.
    secret TEXT NULL, -- Encrypted secret for TOTP/WebAuthn
    enabled BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_method (method),
    INDEX idx_enabled (enabled),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- OTP codes storage
CREATE TABLE IF NOT EXISTS mfa_otps (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    otp_code TEXT NOT NULL, -- Encrypted OTP
    method VARCHAR(20) NOT NULL, -- email, sms
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_method (method),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- TOTP setup temporary storage
CREATE TABLE IF NOT EXISTS mfa_totp_setup (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    secret TEXT NOT NULL, -- Encrypted secret
    qr_uri TEXT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 30 MINUTE),
    INDEX idx_user_id (user_id),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- WebAuthn credentials
CREATE TABLE IF NOT EXISTS mfa_webauthn_credentials (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    credential_id VARCHAR(255) NOT NULL UNIQUE,
    public_key TEXT NOT NULL,
    sign_count BIGINT DEFAULT 0,
    user_handle VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_used_at TIMESTAMP NULL,
    INDEX idx_user_id (user_id),
    INDEX idx_credential_id (credential_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- WebAuthn challenges
CREATE TABLE IF NOT EXISTS mfa_webauthn_challenges (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    challenge VARCHAR(255) NOT NULL,
    user_handle VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP DEFAULT (CURRENT_TIMESTAMP + INTERVAL 5 MINUTE),
    INDEX idx_user_id (user_id),
    INDEX idx_challenge (challenge),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Recovery codes
CREATE TABLE IF NOT EXISTS mfa_recovery_codes (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    code_hash VARCHAR(255) NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_code_hash (code_hash),
    INDEX idx_used (used),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Magic link tokens
CREATE TABLE IF NOT EXISTS mfa_magic_links (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    token VARCHAR(255) NOT NULL UNIQUE,
    expires_at TIMESTAMP NOT NULL,
    used BOOLEAN DEFAULT FALSE,
    used_at TIMESTAMP NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_token (token),
    INDEX idx_expires_at (expires_at),
    INDEX idx_used (used),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Login attempts tracking
CREATE TABLE IF NOT EXISTS mfa_login_attempts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    email VARCHAR(255) NULL,
    success BOOLEAN NOT NULL,
    failure_reason VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    factors_used JSON NULL,
    session_id VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_email (email),
    INDEX idx_success (success),
    INDEX idx_ip_address (ip_address),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Remembered devices
CREATE TABLE IF NOT EXISTS mfa_remembered_devices (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    device_hash VARCHAR(255) NOT NULL,
    device_name VARCHAR(255) NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    last_used_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_device_hash (device_hash),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Password history for reuse prevention
CREATE TABLE IF NOT EXISTS password_history (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- User preferences for authentication
CREATE TABLE IF NOT EXISTS user_auth_preferences (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NOT NULL,
    preferred_method VARCHAR(50) DEFAULT 'password',
    mfa_required BOOLEAN DEFAULT FALSE,
    remember_device_days INT DEFAULT 30,
    login_notifications BOOLEAN DEFAULT TRUE,
    suspicious_login_alerts BOOLEAN DEFAULT TRUE,
    auto_lock_sessions BOOLEAN DEFAULT TRUE,
    session_timeout_minutes INT DEFAULT 60,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_user (user_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Security events log
CREATE TABLE IF NOT EXISTS security_events (
    id INT PRIMARY KEY AUTO_INCREMENT,
    user_id INT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_description TEXT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    session_id VARCHAR(255) NULL,
    metadata JSON NULL,
    severity VARCHAR(20) DEFAULT 'info', -- info, warning, error, critical
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_user_id (user_id),
    INDEX idx_event_type (event_type),
    INDEX idx_severity (severity),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default user auth preferences for existing users
INSERT IGNORE INTO user_auth_preferences (user_id, preferred_method, mfa_required)
SELECT id, 'password', CASE WHEN role = 'admin' THEN 1 ELSE 0 END
FROM users;

-- Create views for MFA analytics
CREATE OR REPLACE VIEW mfa_user_summary AS
SELECT
    u.id as user_id,
    u.email,
    u.role,
    COUNT(CASE WHEN mm.enabled = 1 THEN 1 END) as active_mfa_methods,
    GROUP_CONCAT(CASE WHEN mm.enabled = 1 THEN mm.method END) as mfa_methods,
    MAX(mm.created_at) as mfa_setup_date,
    COUNT(DISTINCT mla.id) as total_login_attempts,
    SUM(CASE WHEN mla.success = 1 THEN 1 ELSE 0 END) as successful_logins,
    MAX(mla.created_at) as last_login_attempt,
    CASE
        WHEN COUNT(CASE WHEN mm.enabled = 1 THEN 1 END) = 0 THEN 'none'
        WHEN COUNT(CASE WHEN mm.enabled = 1 THEN 1 END) = 1 THEN 'basic'
        WHEN COUNT(CASE WHEN mm.enabled = 1 THEN 1 END) = 2 THEN 'standard'
        ELSE 'enhanced'
    END as mfa_level
FROM users u
LEFT JOIN mfa_methods mm ON u.id = mm.user_id
LEFT JOIN mfa_login_attempts mla ON u.id = mla.user_id
    AND mla.created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY u.id, u.email, u.role;

CREATE OR REPLACE VIEW mfa_security_summary AS
SELECT
    DATE(created_at) as date,
    COUNT(*) as total_attempts,
    SUM(CASE WHEN success = 1 THEN 1 ELSE 0 END) as successful_attempts,
    SUM(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failed_attempts,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT ip_address) as unique_ips,
    AVG(CASE WHEN success = 0 THEN 1 ELSE 0 END) as failure_rate
FROM mfa_login_attempts
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;

CREATE OR REPLACE VIEW mfa_method_usage AS
SELECT
    method,
    COUNT(*) as total_users,
    SUM(enabled) as active_users,
    AVG(TIMESTAMPDIFF(DAY, created_at, NOW())) as avg_setup_age_days,
    MAX(last_used_at) as last_used,
    COUNT(CASE WHEN last_used_at >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 END) as active_last_week
FROM mfa_methods
GROUP BY method
ORDER BY active_users DESC;

CREATE OR REPLACE VIEW security_events_summary AS
SELECT
    DATE(created_at) as date,
    event_type,
    severity,
    COUNT(*) as event_count,
    COUNT(DISTINCT user_id) as affected_users,
    COUNT(DISTINCT ip_address) as unique_ips,
    GROUP_CONCAT(DISTINCT event_description) as descriptions
FROM security_events
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(created_at), event_type, severity
ORDER BY date DESC, event_count DESC;

-- Create indexes for performance
CREATE INDEX idx_mfa_otps_cleanup ON mfa_otps (used, expires_at);
CREATE INDEX idx_mfa_magic_links_cleanup ON mfa_magic_links (used, expires_at);
CREATE INDEX idx_mfa_webauthn_challenges_cleanup ON mfa_webauthn_challenges (expires_at);
CREATE INDEX idx_mfa_remembered_devices_cleanup ON mfa_remembered_devices (expires_at);
CREATE INDEX idx_security_events_recent ON security_events (created_at, severity);

-- Create cleanup procedures (run these periodically)
DELIMITER //

CREATE PROCEDURE cleanup_expired_mfa_data()
BEGIN
    -- Clean up expired OTPs
    DELETE FROM mfa_otps WHERE expires_at < NOW() OR (used = 1 AND used_at < DATE_SUB(NOW(), INTERVAL 30 DAY));

    -- Clean up expired magic links
    DELETE FROM mfa_magic_links WHERE expires_at < NOW() OR (used = 1 AND used_at < DATE_SUB(NOW(), INTERVAL 7 DAY));

    -- Clean up expired WebAuthn challenges
    DELETE FROM mfa_webauthn_challenges WHERE expires_at < NOW();

    -- Clean up expired remembered devices
    DELETE FROM mfa_remembered_devices WHERE expires_at < NOW();

    -- Clean up old password history (keep last 10 per user)
    DELETE ph FROM password_history ph
    INNER JOIN (
        SELECT user_id, MIN(created_at) as oldest_to_keep
        FROM (
            SELECT user_id, created_at,
                   ROW_NUMBER() OVER (PARTITION BY user_id ORDER BY created_at DESC) as rn
            FROM password_history
        ) ranked
        WHERE rn > 10
        GROUP BY user_id
    ) to_delete ON ph.user_id = to_delete.user_id AND ph.created_at < to_delete.oldest_to_keep;

    -- Clean up old security events (keep last 90 days)
    DELETE FROM security_events WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

    -- Clean up old login attempts (keep last 90 days)
    DELETE FROM mfa_login_attempts WHERE created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);
END //

DELIMITER ;

-- Create event to run cleanup daily
CREATE EVENT IF NOT EXISTS daily_mfa_cleanup
ON SCHEDULE EVERY 1 DAY STARTS (TIMESTAMP(CURRENT_DATE) + INTERVAL 2 HOUR)
DO
    CALL cleanup_expired_mfa_data();

-- Enable event scheduler
SET GLOBAL event_scheduler = ON;
