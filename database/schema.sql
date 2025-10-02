-- AEIMS Database Schema
-- Creates tables for user management, support tickets, and admin features

CREATE DATABASE IF NOT EXISTS aeims;
USE aeims;

-- Users table for customer authentication
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('customer', 'admin') DEFAULT 'customer',
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    company VARCHAR(100),
    status ENUM('active', 'suspended', 'pending') DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL,
    password_reset_token VARCHAR(100) NULL,
    password_reset_expires TIMESTAMP NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(100) NULL
);

-- Support tickets table
CREATE TABLE IF NOT EXISTS support_tickets (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INT,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority ENUM('low', 'medium', 'high', 'urgent') DEFAULT 'medium',
    status ENUM('open', 'in_progress', 'resolved', 'closed') DEFAULT 'open',
    category ENUM('technical', 'billing', 'general', 'migration') DEFAULT 'general',
    assigned_to INT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL
);

-- Support ticket responses/comments
CREATE TABLE IF NOT EXISTS ticket_responses (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ticket_id INT NOT NULL,
    user_id INT,
    message TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (ticket_id) REFERENCES support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
);

-- Domains management (for admin domain freeze feature)
CREATE TABLE IF NOT EXISTS domains (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_name VARCHAR(100) UNIQUE NOT NULL,
    theme VARCHAR(100),
    description TEXT,
    services JSON,
    status ENUM('active', 'frozen', 'maintenance', 'suspended') DEFAULT 'active',
    freeze_reason TEXT NULL,
    frozen_by INT NULL,
    frozen_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (frozen_by) REFERENCES users(id) ON DELETE SET NULL
);

-- Domain statistics
CREATE TABLE IF NOT EXISTS domain_stats (
    id INT AUTO_INCREMENT PRIMARY KEY,
    domain_id INT NOT NULL,
    stat_date DATE NOT NULL,
    page_views INT DEFAULT 0,
    unique_visitors INT DEFAULT 0,
    bounce_rate DECIMAL(5,2) DEFAULT 0,
    avg_session_duration INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (domain_id) REFERENCES domains(id) ON DELETE CASCADE,
    UNIQUE KEY unique_domain_date (domain_id, stat_date)
);

-- User sessions for tracking logins
CREATE TABLE IF NOT EXISTS user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INT,
    ip_address VARCHAR(45),
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,
    data TEXT,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Admin activity log
CREATE TABLE IF NOT EXISTS admin_activity (
    id INT AUTO_INCREMENT PRIMARY KEY,
    admin_id INT NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INT,
    details JSON,
    ip_address VARCHAR(45),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (admin_id) REFERENCES users(id) ON DELETE CASCADE
);

-- Email templates for automated emails
CREATE TABLE IF NOT EXISTS email_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    variables JSON,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

-- Insert default admin user
INSERT INTO users (username, email, password_hash, role, first_name, last_name, status, email_verified)
VALUES ('admin', 'rjc@afterdarksys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', 'active', TRUE)
ON DUPLICATE KEY UPDATE username=username;

-- Insert sample domains from config
INSERT INTO domains (domain_name, theme, description, services, status) VALUES
('beastybitches.com', 'Beasty Women', 'Fierce and confident women who know what they want', '["Live Chat", "Video Calls", "Voice Calls"]', 'active'),
('cavern.love', 'The Love Cavern', 'Intimate and mysterious adult connection experiences', '["Live Chat", "Video Calls", "Voice Calls"]', 'active'),
('nitetext.com', 'Text-Only Platform', 'Pure text-based adult entertainment experience', '["Text Chat", "SMS Services", "Chat Rooms"]', 'active'),
('nineinchesof.com', 'Size Matters', 'For those who think they have what it takes', '["Live Chat", "Video Calls", "Cam Shows"]', 'active'),
('holyflirts.com', 'Dirty Church Girls', 'Forbidden desires and sacred temptations', '["Live Chat", "Video Calls", "Voice Calls"]', 'active'),
('nycflirts.com', 'City Girls, City Pearls', 'Sophisticated urban women with attitude', '["Live Chat", "Video Calls", "Voice Calls"]', 'active'),
('gfecalls.com', 'Girlfriend Experience', 'Dedicated GFE connections and intimate conversations', '["Voice Calls", "Video Calls", "Text Chat"]', 'active'),
('latenite.love', 'Late Night Love', 'For lonely nights when you need connection', '["Live Chat", "Video Calls", "Voice Calls"]', 'active'),
('fantasyflirts.live', 'Your Fantasy, Your Flirt', 'Live fantasy fulfillment and role-playing', '["Live Shows", "Interactive Chat", "Custom Content"]', 'active'),
('dommecats.com', 'Dominatrix Queens', 'Powerful women who will make any man purr', '["Domination Chat", "Video Sessions", "Voice Commands"]', 'active')
ON DUPLICATE KEY UPDATE domain_name=VALUES(domain_name);

-- Insert default email templates
INSERT INTO email_templates (name, subject, variables, body) VALUES
('welcome', 'Welcome to AEIMS', '["name", "username"]', 'Welcome {{name}} to AEIMS! Your username is {{username}}.'),
('password_reset', 'Password Reset Request', '["name", "reset_link"]', 'Hello {{name}}, click here to reset your password: {{reset_link}}'),
('ticket_created', 'Support Ticket Created', '["ticket_number", "subject"]', 'Your support ticket {{ticket_number}} has been created: {{subject}}'),
('ticket_response', 'Support Ticket Update', '["ticket_number", "response"]', 'Your ticket {{ticket_number}} has been updated: {{response}}')
ON DUPLICATE KEY UPDATE name=VALUES(name);

-- Create indexes for better performance
CREATE INDEX idx_users_email ON users(email);
CREATE INDEX idx_users_username ON users(username);
CREATE INDEX idx_tickets_user ON support_tickets(user_id);
CREATE INDEX idx_tickets_status ON support_tickets(status);
CREATE INDEX idx_tickets_created ON support_tickets(created_at);
CREATE INDEX idx_domains_status ON domains(status);
CREATE INDEX idx_sessions_expires ON user_sessions(expires_at);
CREATE INDEX idx_admin_activity_admin ON admin_activity(admin_id);
CREATE INDEX idx_admin_activity_created ON admin_activity(created_at);