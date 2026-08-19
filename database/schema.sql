-- ============================================================
-- Prosperminds Website Database Schema
-- ============================================================

-- Registrations from public event registration form
CREATE TABLE IF NOT EXISTS event_registrations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(100) NOT NULL,
    last_name VARCHAR(100) NOT NULL,
    phone VARCHAR(50) NOT NULL,
    email VARCHAR(150) NOT NULL,
    organization VARCHAR(150) NOT NULL,
    country VARCHAR(100) DEFAULT NULL,
    address TEXT DEFAULT NULL,
    attendee_count INT NOT NULL DEFAULT 1,
    attendee_details LONGTEXT DEFAULT NULL,
    invoice_number VARCHAR(50) DEFAULT NULL,
    invoice_path VARCHAR(255) DEFAULT NULL,
    event_id INT DEFAULT NULL,
    currency_code VARCHAR(10) DEFAULT 'USD',
    unit_price_amount DECIMAL(10,2) DEFAULT 0.00,
    total_amount DECIMAL(10,2) DEFAULT 0.00,
    gender VARCHAR(20) NOT NULL,
    meal_preference VARCHAR(100) NOT NULL,
    future_topics TEXT,
    consent TINYINT(1) NOT NULL DEFAULT 1,
    event_name VARCHAR(200) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Admin users
CREATE TABLE IF NOT EXISTS admin_users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Default admin: admin / Password123
INSERT INTO admin_users (username, password) VALUES
('admin', '$2y$10$wE4M./M1Y.N0s/yK.aC85ei01w4xN2c9N.f1oW6dCj5.6H5S2E22G')
ON DUPLICATE KEY UPDATE username = 'admin';

-- Login rate limiting (brute-force protection)
CREATE TABLE IF NOT EXISTS login_attempts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ip_address VARCHAR(45) NOT NULL,
    attempted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_ip_time (ip_address, attempted_at)
);

-- Events managed from the admin panel
CREATE TABLE IF NOT EXISTS events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(300) NOT NULL,
    tagline VARCHAR(400),
    focus_tags VARCHAR(300),
    why_intro TEXT,
    master_points TEXT,
    agenda JSON,
    audience TEXT,
    vvip_price VARCHAR(50) DEFAULT 'USD 2,899',
    vvip_seats_note VARCHAR(100) DEFAULT 'Limited to 15 seats',
    vvip_perks TEXT,
    vip_price VARCHAR(50) DEFAULT 'USD 1,999',
    vip_perks TEXT,
    regular_price VARCHAR(50) DEFAULT 'USD 599',
    regular_perks TEXT,
    date_display VARCHAR(100) NOT NULL,
    event_start_date DATE,
    location VARCHAR(200) NOT NULL,
    price VARCHAR(100) DEFAULT 'USD 599 Per Delegate',
    early_bird_1_pct INT DEFAULT 20,
    early_bird_1_date DATE,
    early_bird_2_pct INT DEFAULT 15,
    early_bird_2_date DATE,
    early_bird_3_pct INT DEFAULT 10,
    early_bird_3_date DATE,
    image_path VARCHAR(300),
    pdf_file VARCHAR(255) DEFAULT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

-- Seed the three existing events
INSERT INTO events (title, tagline, date_display, event_start_date, location, price, early_bird_1_pct, early_bird_1_date, early_bird_2_pct, early_bird_2_date, early_bird_3_pct, early_bird_3_date, image_path, sort_order) VALUES
(
    'Smart PFM & IPSAS Future Ready Finance Leaders Course',
    'PFM, Data Analytics & Government Automation for Leaders Who Must Deliver',
    '19-23 October 2026', '2026-10-19', 'Cape Town, South Africa',
    'USD 599 Per Delegate',
    20, '2026-07-19', 15, '2026-08-19', 10, '2026-09-19',
    'assets/images/smart PFM and IPSAS Future Ready Finance Leaders Course.jpg', 1
),
(
    'IPSAS Success & Clean Audit Compliance Training',
    'IPSAS Compliance & Zero-Failure Reporting for Government Officers',
    '16-20 November 2026', '2026-11-16', 'Kuala Lumpur, Malaysia',
    'USD 599 Per Delegate',
    20, '2026-08-16', 15, '2026-09-16', 10, '2026-10-16',
    'assets/images/IPSAS Success and Clean Audit compliance.jpg', 2
),
(
    'Budget Control, Revenue Growth & PFM Funding Breakthrough Conference',
    'Cash Control, IPSAS Reporting & Funding Strategies in Tough Times',
    '7-11 December 2026', '2026-12-07', 'Bali, Indonesia',
    'USD 599 Per Delegate',
    20, '2026-09-07', 15, '2026-10-07', 10, '2026-11-07',
    'assets/images/Budget Control and Revenue growth.jpg', 3
);

-- Site settings (editable from admin settings page)
CREATE TABLE IF NOT EXISTS site_settings (
    setting_key VARCHAR(100) NOT NULL PRIMARY KEY,
    setting_value TEXT,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

INSERT INTO site_settings (setting_key, setting_value) VALUES
('admin_email',   'info@prosper-minds.com'),
('company_name',  'ProsperMinds'),
('company_color', '#00B140'),
('smtp_host',     'mail.prosper-minds.com'),
('smtp_user',     'info@prosper-minds.com'),
('smtp_pass',     'info@prosper-minds.com'),
('smtp_port',     '587'),
('smtp_secure',   'tls')
ON DUPLICATE KEY UPDATE setting_key = setting_key;
