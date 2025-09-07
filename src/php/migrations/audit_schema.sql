-- Audit Logging System Database Schema
-- Comprehensive audit trail for government platform compliance

-- System settings table for audit configuration
CREATE TABLE IF NOT EXISTS system_settings (
    id INT PRIMARY KEY AUTO_INCREMENT,
    setting_key VARCHAR(255) NOT NULL UNIQUE,
    setting_value TEXT,
    setting_type VARCHAR(50) DEFAULT 'string',
    description TEXT,
    is_system BOOLEAN DEFAULT FALSE,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_setting_key (setting_key),
    INDEX idx_setting_type (setting_type),
    INDEX idx_is_system (is_system),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Main audit logs table
CREATE TABLE IF NOT EXISTS audit_logs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    event_type VARCHAR(100) NOT NULL,
    event_description VARCHAR(255) NOT NULL,
    risk_level VARCHAR(20) NOT NULL DEFAULT 'low',
    risk_score TINYINT NOT NULL DEFAULT 1,
    user_id INT NULL,
    user_email VARCHAR(255) NULL,
    user_role VARCHAR(100) NULL,
    session_id VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    request_method VARCHAR(10) NULL,
    request_uri TEXT NULL,
    data JSON NULL,
    metadata JSON NULL,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_user_email (user_email),
    INDEX idx_risk_level (risk_level),
    INDEX idx_timestamp (timestamp),
    INDEX idx_ip_address (ip_address),
    INDEX idx_session_id (session_id),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit log archives (for long-term storage)
CREATE TABLE IF NOT EXISTS audit_log_archives (
    id INT PRIMARY KEY AUTO_INCREMENT,
    original_id INT NOT NULL,
    event_type VARCHAR(100) NOT NULL,
    event_description VARCHAR(255) NOT NULL,
    risk_level VARCHAR(20) NOT NULL,
    risk_score TINYINT NOT NULL,
    user_id INT NULL,
    user_email VARCHAR(255) NULL,
    user_role VARCHAR(100) NULL,
    session_id VARCHAR(255) NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    request_method VARCHAR(10) NULL,
    request_uri TEXT NULL,
    data JSON NULL,
    metadata JSON NULL,
    original_timestamp TIMESTAMP NOT NULL,
    archived_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    archive_reason VARCHAR(100) DEFAULT 'retention_policy',
    INDEX idx_original_id (original_id),
    INDEX idx_event_type (event_type),
    INDEX idx_user_id (user_id),
    INDEX idx_archived_at (archived_at),
    INDEX idx_original_timestamp (original_timestamp)
);

-- Security alerts and incidents
CREATE TABLE IF NOT EXISTS audit_security_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alert_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'medium',
    title VARCHAR(255) NOT NULL,
    description TEXT NOT NULL,
    affected_users INT DEFAULT 0,
    affected_resources TEXT NULL,
    trigger_event_id INT NULL,
    trigger_ip VARCHAR(45) NULL,
    trigger_user_id INT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'active',
    assigned_to INT NULL,
    resolution TEXT NULL,
    resolved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_trigger_event (trigger_event_id),
    FOREIGN KEY (trigger_event_id) REFERENCES audit_logs(id) ON DELETE SET NULL,
    FOREIGN KEY (trigger_user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit log exports and reports
CREATE TABLE IF NOT EXISTS audit_log_exports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    export_name VARCHAR(255) NOT NULL,
    export_filters JSON NULL,
    export_format VARCHAR(20) NOT NULL DEFAULT 'json',
    record_count INT NOT NULL DEFAULT 0,
    file_path VARCHAR(500) NULL,
    file_size INT NULL,
    checksum VARCHAR(128) NULL,
    requested_by INT NOT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'processing',
    completed_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL,
    download_count INT DEFAULT 0,
    last_download TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_requested_by (requested_by),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (requested_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Audit configuration and policies
CREATE TABLE IF NOT EXISTS audit_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policy_name VARCHAR(255) NOT NULL,
    policy_type VARCHAR(100) NOT NULL,
    description TEXT NULL,
    configuration JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_policy_type (policy_type),
    INDEX idx_is_active (is_active),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit compliance reports
CREATE TABLE IF NOT EXISTS audit_compliance_reports (
    id INT PRIMARY KEY AUTO_INCREMENT,
    report_type VARCHAR(100) NOT NULL,
    report_period_start TIMESTAMP NOT NULL,
    report_period_end TIMESTAMP NOT NULL,
    report_data JSON NOT NULL,
    compliance_score DECIMAL(5,2) NULL,
    findings_count INT DEFAULT 0,
    critical_findings INT DEFAULT 0,
    generated_by INT NOT NULL,
    approved_by INT NULL,
    status VARCHAR(50) NOT NULL DEFAULT 'draft',
    approved_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_report_type (report_type),
    INDEX idx_period (report_period_start, report_period_end),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (generated_by) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Audit data retention rules
CREATE TABLE IF NOT EXISTS audit_retention_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_name VARCHAR(255) NOT NULL,
    event_types JSON NOT NULL,
    retention_days INT NOT NULL,
    archive_after_days INT NULL,
    delete_after_days INT NULL,
    legal_hold BOOLEAN DEFAULT FALSE,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_is_active (is_active),
    INDEX idx_retention_days (retention_days),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Audit monitoring rules
CREATE TABLE IF NOT EXISTS audit_monitoring_rules (
    id INT PRIMARY KEY AUTO_INCREMENT,
    rule_name VARCHAR(255) NOT NULL,
    rule_type VARCHAR(100) NOT NULL,
    conditions JSON NOT NULL,
    actions JSON NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'medium',
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NOT NULL,
    last_triggered TIMESTAMP NULL,
    trigger_count INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_rule_type (rule_type),
    INDEX idx_is_active (is_active),
    INDEX idx_severity (severity),
    INDEX idx_last_triggered (last_triggered),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
);

-- Insert default audit policies
INSERT IGNORE INTO audit_policies (policy_name, policy_type, configuration, created_by) VALUES
('Default Authentication Policy', 'authentication', '{
    "log_successful_logins": true,
    "log_failed_logins": true,
    "log_logout_events": true,
    "log_password_changes": true,
    "alert_on_brute_force": true,
    "brute_force_threshold": 5
}', 1),

('Default Data Access Policy', 'data_access', '{
    "log_all_access": true,
    "log_modifications": true,
    "log_deletions": true,
    "log_exports": true,
    "sensitive_data_masking": true,
    "pii_detection": true
}', 1),

('Default Security Policy', 'security', '{
    "log_suspicious_activity": true,
    "log_unauthorized_access": true,
    "log_admin_actions": true,
    "alert_on_high_risk": true,
    "real_time_monitoring": true
}', 1);

-- Insert default retention rules
INSERT IGNORE INTO audit_retention_rules (rule_name, event_types, retention_days, archive_after_days, delete_after_days, created_by) VALUES
('Authentication Events', '["auth.login", "auth.logout", "auth.failed_login", "auth.password_change"]', 2555, 365, 2555, 1),
('Security Events', '["security.*"]', 2555, 365, 2555, 1),
('Data Access Events', '["data.access", "data.modify", "data.delete"]', 2555, 365, 2555, 1),
('System Events', '["system.*"]', 1095, 365, 1095, 1),
('GDPR Events', '["gdpr.*"]', 2555, 365, 2555, 1);

-- Insert default monitoring rules
INSERT IGNORE INTO audit_monitoring_rules (rule_name, rule_type, conditions, actions, severity, created_by) VALUES
('Brute Force Detection', 'threshold', '{
    "event_type": "auth.failed_login",
    "threshold": 5,
    "time_window": "1 hour",
    "group_by": "ip_address"
}', '["alert", "block_ip"]', 'high', 1),

('Suspicious Activity Burst', 'threshold', '{
    "event_type": "security.suspicious_activity",
    "threshold": 3,
    "time_window": "1 hour",
    "group_by": "ip_address"
}', '["alert", "notify_admin"]', 'high', 1),

('Unauthorized Access Attempts', 'pattern', '{
    "event_type": "security.unauthorized_access",
    "pattern": "repeated_access",
    "time_window": "30 minutes"
}', '["alert", "lock_account"]', 'critical', 1);

-- Create views for audit analytics
CREATE OR REPLACE VIEW audit_events_summary AS
SELECT
    DATE(timestamp) as event_date,
    event_type,
    risk_level,
    COUNT(*) as event_count,
    COUNT(DISTINCT user_id) as unique_users,
    COUNT(DISTINCT ip_address) as unique_ips
FROM audit_logs
WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(timestamp), event_type, risk_level
ORDER BY event_date DESC, event_count DESC;

CREATE OR REPLACE VIEW audit_security_summary AS
SELECT
    DATE(timestamp) as date,
    SUM(CASE WHEN event_type LIKE 'auth.failed_%' THEN 1 ELSE 0 END) as failed_auth_attempts,
    SUM(CASE WHEN event_type LIKE 'security.%' THEN 1 ELSE 0 END) as security_events,
    SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_risk_events,
    SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as critical_events,
    COUNT(DISTINCT ip_address) as suspicious_ips,
    COUNT(DISTINCT user_id) as affected_users
FROM audit_logs
WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(timestamp)
ORDER BY date DESC;

CREATE OR REPLACE VIEW audit_user_activity AS
SELECT
    user_id,
    user_email,
    user_role,
    COUNT(*) as total_events,
    SUM(CASE WHEN risk_level = 'high' THEN 1 ELSE 0 END) as high_risk_actions,
    SUM(CASE WHEN risk_level = 'critical' THEN 1 ELSE 0 END) as critical_actions,
    MAX(timestamp) as last_activity,
    COUNT(DISTINCT DATE(timestamp)) as active_days,
    COUNT(DISTINCT ip_address) as unique_ips
FROM audit_logs
WHERE user_id IS NOT NULL
    AND timestamp >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY user_id, user_email, user_role
ORDER BY total_events DESC;

CREATE OR REPLACE VIEW audit_ip_analysis AS
SELECT
    ip_address,
    COUNT(*) as total_requests,
    COUNT(DISTINCT user_id) as unique_users,
    SUM(CASE WHEN event_type LIKE 'auth.failed_%' THEN 1 ELSE 0 END) as failed_logins,
    SUM(CASE WHEN event_type LIKE 'security.%' THEN 1 ELSE 0 END) as security_events,
    MAX(timestamp) as last_seen,
    MIN(timestamp) as first_seen,
    COUNT(DISTINCT DATE(timestamp)) as active_days
FROM audit_logs
WHERE timestamp >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY ip_address
HAVING total_requests > 10
ORDER BY security_events DESC, failed_logins DESC;
