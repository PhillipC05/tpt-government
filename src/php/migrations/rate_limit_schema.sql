-- Rate Limiting & DDoS Protection Database Schema
-- Advanced protection system for government platform

-- Rate limit whitelist
CREATE TABLE IF NOT EXISTS rate_limit_whitelist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    identifier_type VARCHAR(50) NOT NULL DEFAULT 'ip',
    reason TEXT,
    added_by INT NULL,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_identifier_type (identifier_type),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    FOREIGN KEY (added_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Rate limit blacklist
CREATE TABLE IF NOT EXISTS rate_limit_blacklist (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    identifier_type VARCHAR(50) NOT NULL DEFAULT 'ip',
    reason TEXT,
    banned_by INT NULL,
    expires_at TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    violation_count INT DEFAULT 1,
    last_violation TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_identifier_type (identifier_type),
    INDEX idx_is_active (is_active),
    INDEX idx_expires_at (expires_at),
    INDEX idx_last_violation (last_violation),
    FOREIGN KEY (banned_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Rate limit violations
CREATE TABLE IF NOT EXISTS rate_limit_violations (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    limit_type VARCHAR(50) NOT NULL,
    violation_type VARCHAR(100) NOT NULL,
    ip_address VARCHAR(45) NOT NULL,
    user_agent TEXT NULL,
    request_uri TEXT NULL,
    request_method VARCHAR(10) NULL,
    user_id INT NULL,
    headers JSON NULL,
    response_code INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_limit_type (limit_type),
    INDEX idx_violation_type (violation_type),
    INDEX idx_ip_address (ip_address),
    INDEX idx_user_id (user_id),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- DDoS attacks log
CREATE TABLE IF NOT EXISTS ddos_attacks (
    id INT PRIMARY KEY AUTO_INCREMENT,
    identifier VARCHAR(255) NOT NULL,
    attack_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'medium',
    attack_vector VARCHAR(100) NULL,
    source_ips JSON NULL,
    affected_endpoints JSON NULL,
    request_pattern JSON NULL,
    peak_requests_per_second INT NULL,
    duration_seconds INT NULL,
    mitigation_action VARCHAR(255) NULL,
    mitigation_duration INT NULL,
    detected_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    mitigated_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_identifier (identifier),
    INDEX idx_attack_type (attack_type),
    INDEX idx_severity (severity),
    INDEX idx_detected_at (detected_at),
    INDEX idx_mitigated_at (mitigated_at)
);

-- Rate limit configurations
CREATE TABLE IF NOT EXISTS rate_limit_configs (
    id INT PRIMARY KEY AUTO_INCREMENT,
    config_name VARCHAR(255) NOT NULL,
    limit_type VARCHAR(50) NOT NULL,
    algorithm VARCHAR(50) NOT NULL DEFAULT 'sliding_window',
    requests_per_window INT NULL,
    window_seconds INT NULL,
    bucket_capacity INT NULL,
    refill_rate_per_second DECIMAL(10,2) NULL,
    leak_rate_per_second DECIMAL(10,2) NULL,
    precision_seconds INT DEFAULT 10,
    burst_allowance DECIMAL(3,2) DEFAULT 1.2,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_config_name (config_name),
    INDEX idx_limit_type (limit_type),
    INDEX idx_algorithm (algorithm),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Rate limit alerts
CREATE TABLE IF NOT EXISTS rate_limit_alerts (
    id INT PRIMARY KEY AUTO_INCREMENT,
    alert_type VARCHAR(100) NOT NULL,
    severity VARCHAR(20) NOT NULL DEFAULT 'medium',
    identifier VARCHAR(255) NOT NULL,
    limit_type VARCHAR(50) NOT NULL,
    threshold_value INT NOT NULL,
    actual_value INT NOT NULL,
    ip_address VARCHAR(45) NULL,
    user_agent TEXT NULL,
    request_uri TEXT NULL,
    user_id INT NULL,
    alert_message TEXT NOT NULL,
    is_acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT NULL,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_alert_type (alert_type),
    INDEX idx_severity (severity),
    INDEX idx_identifier (identifier),
    INDEX idx_limit_type (limit_type),
    INDEX idx_is_acknowledged (is_acknowledged),
    INDEX idx_created_at (created_at),
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (acknowledged_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Traffic analysis data
CREATE TABLE IF NOT EXISTS traffic_analysis (
    id INT PRIMARY KEY AUTO_INCREMENT,
    time_window_start TIMESTAMP NOT NULL,
    time_window_end TIMESTAMP NOT NULL,
    total_requests INT NOT NULL DEFAULT 0,
    unique_ips INT NOT NULL DEFAULT 0,
    unique_users INT NOT NULL DEFAULT 0,
    rate_limited_requests INT NOT NULL DEFAULT 0,
    blocked_requests INT NOT NULL DEFAULT 0,
    avg_response_time DECIMAL(8,3) NULL,
    peak_requests_per_second INT NULL,
    top_endpoints JSON NULL,
    top_ips JSON NULL,
    suspicious_patterns JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_time_window (time_window_start, time_window_end),
    INDEX idx_created_at (created_at)
);

-- IP reputation data
CREATE TABLE IF NOT EXISTS ip_reputation (
    id INT PRIMARY KEY AUTO_INCREMENT,
    ip_address VARCHAR(45) NOT NULL UNIQUE,
    reputation_score DECIMAL(5,2) DEFAULT 0.5,
    total_requests INT DEFAULT 0,
    blocked_requests INT DEFAULT 0,
    violation_count INT DEFAULT 0,
    last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    first_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    is_whitelisted BOOLEAN DEFAULT FALSE,
    is_blacklisted BOOLEAN DEFAULT FALSE,
    geo_country VARCHAR(2) NULL,
    geo_region VARCHAR(100) NULL,
    geo_city VARCHAR(100) NULL,
    asn_number INT NULL,
    asn_name VARCHAR(255) NULL,
    threat_categories JSON NULL,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_address (ip_address),
    INDEX idx_reputation_score (reputation_score),
    INDEX idx_last_seen (last_seen),
    INDEX idx_is_blacklisted (is_blacklisted),
    INDEX idx_geo_country (geo_country)
);

-- Rate limit policies
CREATE TABLE IF NOT EXISTS rate_limit_policies (
    id INT PRIMARY KEY AUTO_INCREMENT,
    policy_name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    priority INT DEFAULT 100,
    conditions JSON NOT NULL,
    actions JSON NOT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_by INT NULL,
    updated_by INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_policy_name (policy_name),
    INDEX idx_priority (priority),
    INDEX idx_is_active (is_active),
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Insert default rate limit configurations
INSERT IGNORE INTO rate_limit_configs (
    config_name, limit_type, algorithm, requests_per_window, window_seconds, precision_seconds
) VALUES
('General API Limits', 'general', 'sliding_window', 100, 60, 10),
('Authentication Limits', 'auth', 'fixed_window', 5, 300, 10),
('API Endpoint Limits', 'api', 'token_bucket', 100, 60, 10),
('Admin Panel Limits', 'admin', 'sliding_window', 1000, 60, 10),
('File Upload Limits', 'upload', 'fixed_window', 10, 3600, 60),
('Search Limits', 'search', 'leaky_bucket', 30, 60, 10);

-- Insert default rate limit policies
INSERT IGNORE INTO rate_limit_policies (
    policy_name, description, priority, conditions, actions
) VALUES
('Emergency DDoS Response', 'High-priority policy for DDoS attack response', 10, '{
    "ddos_detected": true,
    "severity": "high"
}', '{
    "block_traffic": true,
    "alert_admin": true,
    "enable_captcha": true
}'),

('Suspicious IP Policy', 'Handle traffic from suspicious IP addresses', 20, '{
    "ip_reputation_score": {"lt": 0.3},
    "violation_count": {"gt": 5}
}', '{
    "reduce_limits": true,
    "require_captcha": true,
    "log_detailed": true
}'),

('Bot Detection Policy', 'Detect and handle automated bot traffic', 30, '{
    "user_agent_pattern": "bot|crawler|spider",
    "request_frequency": {"gt": 10, "per": "minute"},
    "session_duration": {"lt": 30}
}', '{
    "require_captcha": true,
    "limit_requests": true,
    "log_suspicious": true
}'),

('Geographic Restriction Policy', 'Apply different limits based on geographic location', 40, '{
    "country_code": ["CN", "RU", "IN"],
    "request_volume": {"gt": 1000, "per": "hour"}
}', '{
    "reduce_limits": true,
    "require_verification": true
}');

-- Create views for rate limiting analytics
CREATE OR REPLACE VIEW rate_limit_violation_summary AS
SELECT
    DATE(created_at) as violation_date,
    limit_type,
    violation_type,
    COUNT(*) as violation_count,
    COUNT(DISTINCT identifier) as unique_identifiers,
    COUNT(DISTINCT ip_address) as unique_ips
FROM rate_limit_violations
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(created_at), limit_type, violation_type
ORDER BY violation_date DESC, violation_count DESC;

CREATE OR REPLACE VIEW rate_limit_blacklist_summary AS
SELECT
    identifier_type,
    COUNT(*) as total_bans,
    AVG(violation_count) as avg_violations,
    MIN(created_at) as first_ban,
    MAX(last_violation) as last_violation,
    SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_bans
FROM rate_limit_blacklist
GROUP BY identifier_type;

CREATE OR REPLACE VIEW ddos_attack_summary AS
SELECT
    DATE(detected_at) as attack_date,
    attack_type,
    severity,
    COUNT(*) as attack_count,
    AVG(peak_requests_per_second) as avg_peak_rps,
    SUM(duration_seconds) as total_duration,
    COUNT(DISTINCT identifier) as unique_attackers
FROM ddos_attacks
WHERE detected_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
GROUP BY DATE(detected_at), attack_type, severity
ORDER BY attack_date DESC, attack_count DESC;

CREATE OR REPLACE VIEW ip_reputation_summary AS
SELECT
    CASE
        WHEN reputation_score >= 0.8 THEN 'Excellent'
        WHEN reputation_score >= 0.6 THEN 'Good'
        WHEN reputation_score >= 0.4 THEN 'Fair'
        WHEN reputation_score >= 0.2 THEN 'Poor'
        ELSE 'Very Poor'
    END as reputation_category,
    COUNT(*) as ip_count,
    AVG(total_requests) as avg_requests,
    AVG(violation_count) as avg_violations,
    SUM(CASE WHEN is_blacklisted = 1 THEN 1 ELSE 0 END) as blacklisted_count
FROM ip_reputation
GROUP BY
    CASE
        WHEN reputation_score >= 0.8 THEN 'Excellent'
        WHEN reputation_score >= 0.6 THEN 'Good'
        WHEN reputation_score >= 0.4 THEN 'Fair'
        WHEN reputation_score >= 0.2 THEN 'Poor'
        ELSE 'Very Poor'
    END
ORDER BY MIN(reputation_score);

CREATE OR REPLACE VIEW rate_limit_performance AS
SELECT
    DATE(created_at) as date,
    COUNT(*) as total_requests,
    SUM(CASE WHEN violation_type = 'rate_limit_exceeded' THEN 1 ELSE 0 END) as rate_limited,
    SUM(CASE WHEN violation_type = 'ddos_detected' THEN 1 ELSE 0 END) as ddos_blocked,
    ROUND(
        (SUM(CASE WHEN violation_type = 'rate_limit_exceeded' THEN 1 ELSE 0 END) * 100.0) / COUNT(*),
        2
    ) as block_percentage
FROM rate_limit_violations
WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)
GROUP BY DATE(created_at)
ORDER BY date DESC;
