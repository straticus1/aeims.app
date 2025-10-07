-- AEIMS Website PostgreSQL Schema
-- Creates tables for user management, support tickets, and admin features
-- Converted from MySQL to PostgreSQL for AEIMS Core integration

-- Connect to aeims_core database (run this manually)
-- \c aeims_core;

-- Users table for customer authentication
CREATE TABLE IF NOT EXISTS aeims_app_users (
    id SERIAL PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role VARCHAR(20) DEFAULT 'customer' CHECK (role IN ('customer', 'admin')),
    first_name VARCHAR(50),
    last_name VARCHAR(50),
    phone VARCHAR(20),
    company VARCHAR(100),
    status VARCHAR(20) DEFAULT 'pending' CHECK (status IN ('active', 'suspended', 'pending')),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    last_login TIMESTAMP NULL,
    password_reset_token VARCHAR(100) NULL,
    password_reset_expires TIMESTAMP NULL,
    email_verified BOOLEAN DEFAULT FALSE,
    email_verification_token VARCHAR(100) NULL
);

-- Support tickets table
CREATE TABLE IF NOT EXISTS aeims_app_support_tickets (
    id SERIAL PRIMARY KEY,
    ticket_number VARCHAR(20) UNIQUE NOT NULL,
    user_id INTEGER,
    subject VARCHAR(200) NOT NULL,
    message TEXT NOT NULL,
    priority VARCHAR(20) DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'urgent')),
    status VARCHAR(20) DEFAULT 'open' CHECK (status IN ('open', 'in_progress', 'resolved', 'closed')),
    category VARCHAR(20) DEFAULT 'general' CHECK (category IN ('technical', 'billing', 'general', 'migration')),
    assigned_to INTEGER NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    resolved_at TIMESTAMP NULL,
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE SET NULL,
    FOREIGN KEY (assigned_to) REFERENCES aeims_app_users(id) ON DELETE SET NULL
);

-- Support ticket responses/comments
CREATE TABLE IF NOT EXISTS aeims_app_ticket_responses (
    id SERIAL PRIMARY KEY,
    ticket_id INTEGER NOT NULL,
    user_id INTEGER,
    message TEXT NOT NULL,
    is_internal BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (ticket_id) REFERENCES aeims_app_support_tickets(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE SET NULL
);

-- Domains management (for admin domain freeze feature)
CREATE TABLE IF NOT EXISTS aeims_app_domains (
    id SERIAL PRIMARY KEY,
    domain_name VARCHAR(100) UNIQUE NOT NULL,
    theme VARCHAR(100),
    description TEXT,
    services JSONB,
    status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'frozen', 'maintenance', 'suspended')),
    freeze_reason TEXT NULL,
    frozen_by INTEGER NULL,
    frozen_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (frozen_by) REFERENCES aeims_app_users(id) ON DELETE SET NULL
);

-- Domain statistics
CREATE TABLE IF NOT EXISTS aeims_app_domain_stats (
    id SERIAL PRIMARY KEY,
    domain_id INTEGER NOT NULL,
    stat_date DATE NOT NULL,
    page_views INTEGER DEFAULT 0,
    unique_visitors INTEGER DEFAULT 0,
    bounce_rate DECIMAL(5,2) DEFAULT 0,
    avg_session_duration INTEGER DEFAULT 0,
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (domain_id) REFERENCES aeims_app_domains(id) ON DELETE CASCADE,
    UNIQUE (domain_id, stat_date)
);

-- User sessions for tracking logins
CREATE TABLE IF NOT EXISTS aeims_app_user_sessions (
    id VARCHAR(128) PRIMARY KEY,
    user_id INTEGER,
    ip_address INET,
    user_agent TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    expires_at TIMESTAMP NOT NULL,
    data TEXT,
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE
);

-- Admin activity log
CREATE TABLE IF NOT EXISTS aeims_app_admin_activity (
    id SERIAL PRIMARY KEY,
    admin_id INTEGER NOT NULL,
    action VARCHAR(100) NOT NULL,
    target_type VARCHAR(50),
    target_id INTEGER,
    details JSONB,
    ip_address INET,
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (admin_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE
);

-- Email templates for automated emails
CREATE TABLE IF NOT EXISTS aeims_app_email_templates (
    id SERIAL PRIMARY KEY,
    name VARCHAR(50) UNIQUE NOT NULL,
    subject VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    variables JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW()
);

-- Create trigger function to update updated_at timestamp
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
   NEW.updated_at = NOW();
   RETURN NEW;
END;
$$ language 'plpgsql';

-- Create triggers for updated_at columns
CREATE TRIGGER update_aeims_app_users_updated_at BEFORE UPDATE ON aeims_app_users FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_aeims_app_support_tickets_updated_at BEFORE UPDATE ON aeims_app_support_tickets FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_aeims_app_domains_updated_at BEFORE UPDATE ON aeims_app_domains FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_aeims_app_email_templates_updated_at BEFORE UPDATE ON aeims_app_email_templates FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert default admin user (password: password)
INSERT INTO aeims_app_users (username, email, password_hash, role, first_name, last_name, status, email_verified)
VALUES ('admin', 'rjc@afterdarksys.com', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'admin', 'Admin', 'User', 'active', TRUE)
ON CONFLICT (username) DO NOTHING;

-- Insert sample domains from config
INSERT INTO aeims_app_domains (domain_name, theme, description, services, status) VALUES
('beastybitches.com', 'Beasty Women', 'Fierce and confident women who know what they want', '["Live Chat", "Video Calls", "Voice Calls"]'::jsonb, 'active'),
('cavern.love', 'The Love Cavern', 'Intimate and mysterious adult connection experiences', '["Live Chat", "Video Calls", "Voice Calls"]'::jsonb, 'active'),
('nitetext.com', 'Text-Only Platform', 'Pure text-based adult entertainment experience', '["Text Chat", "SMS Services", "Chat Rooms"]'::jsonb, 'active'),
('nineinchesof.com', 'Size Matters', 'For those who think they have what it takes', '["Live Chat", "Video Calls", "Cam Shows"]'::jsonb, 'active'),
('holyflirts.com', 'Dirty Church Girls', 'Forbidden desires and sacred temptations', '["Live Chat", "Video Calls", "Voice Calls"]'::jsonb, 'active'),
('nycflirts.com', 'City Girls, City Pearls', 'Sophisticated urban women with attitude', '["Live Chat", "Video Calls", "Voice Calls"]'::jsonb, 'active'),
('gfecalls.com', 'Girlfriend Experience', 'Dedicated GFE connections and intimate conversations', '["Voice Calls", "Video Calls", "Text Chat"]'::jsonb, 'active'),
('latenite.love', 'Late Night Love', 'For lonely nights when you need connection', '["Live Chat", "Video Calls", "Voice Calls"]'::jsonb, 'active'),
('fantasyflirts.live', 'Your Fantasy, Your Flirt', 'Live fantasy fulfillment and role-playing', '["Live Shows", "Interactive Chat", "Custom Content"]'::jsonb, 'active'),
('dommecats.com', 'Dominatrix Queens', 'Powerful women who will make any man purr', '["Domination Chat", "Video Sessions", "Voice Commands"]'::jsonb, 'active')
ON CONFLICT (domain_name) DO NOTHING;

-- Insert default email templates
INSERT INTO aeims_app_email_templates (name, subject, variables, body) VALUES
('welcome', 'Welcome to AEIMS', '["name", "username"]'::jsonb, 'Welcome {{name}} to AEIMS! Your username is {{username}}.'),
('password_reset', 'Password Reset Request', '["name", "reset_link"]'::jsonb, 'Hello {{name}}, click here to reset your password: {{reset_link}}'),
('ticket_created', 'Support Ticket Created', '["ticket_number", "subject"]'::jsonb, 'Your support ticket {{ticket_number}} has been created: {{subject}}'),
('ticket_response', 'Support Ticket Update', '["ticket_number", "response"]'::jsonb, 'Your ticket {{ticket_number}} has been updated: {{response}}')
ON CONFLICT (name) DO NOTHING;

-- Create indexes for better performance
CREATE INDEX IF NOT EXISTS idx_aeims_app_users_email ON aeims_app_users(email);
CREATE INDEX IF NOT EXISTS idx_aeims_app_users_username ON aeims_app_users(username);
CREATE INDEX IF NOT EXISTS idx_aeims_app_tickets_user ON aeims_app_support_tickets(user_id);
CREATE INDEX IF NOT EXISTS idx_aeims_app_tickets_status ON aeims_app_support_tickets(status);
CREATE INDEX IF NOT EXISTS idx_aeims_app_tickets_created ON aeims_app_support_tickets(created_at);
CREATE INDEX IF NOT EXISTS idx_aeims_app_domains_status ON aeims_app_domains(status);
CREATE INDEX IF NOT EXISTS idx_aeims_app_sessions_expires ON aeims_app_user_sessions(expires_at);
CREATE INDEX IF NOT EXISTS idx_aeims_app_admin_activity_admin ON aeims_app_admin_activity(admin_id);
CREATE INDEX IF NOT EXISTS idx_aeims_app_admin_activity_created ON aeims_app_admin_activity(created_at);