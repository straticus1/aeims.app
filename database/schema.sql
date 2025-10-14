-- ============================================================================
-- AEIMS Platform - Complete PostgreSQL Schema
-- Multi-Tenant Architecture for sexacomms.com, flirts.nyc, nycflirts.com, aeims.app
-- DESIGNED FOR ZERO-DOWNTIME MIGRATION WITH DUAL-WRITE SUPPORT
-- ============================================================================

-- Enable extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pgcrypto";

-- ============================================================================
-- CORE ENTITIES
-- ============================================================================

-- Sites table (multi-tenant foundation)
CREATE TABLE sites (
    site_id VARCHAR(50) PRIMARY KEY,
    domain VARCHAR(255) UNIQUE NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    template VARCHAR(50) DEFAULT 'default',
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Configuration
    categories JSONB DEFAULT '[]'::jsonb,
    theme JSONB DEFAULT '{}'::jsonb,
    features JSONB DEFAULT '{}'::jsonb,
    billing_config JSONB DEFAULT '{}'::jsonb,

    -- Stats (cached for performance)
    total_customers INTEGER DEFAULT 0,
    active_operators INTEGER DEFAULT 0,
    total_calls INTEGER DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_sites_domain ON sites(domain);
CREATE INDEX idx_sites_active ON sites(active);

-- ============================================================================
-- USER MANAGEMENT
-- ============================================================================

-- Admin accounts
CREATE TABLE admins (
    admin_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    name VARCHAR(255),
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_login TIMESTAMP,

    -- Permissions
    permissions JSONB DEFAULT '["all"]'::jsonb,

    -- Security
    two_factor_enabled BOOLEAN DEFAULT false,
    two_factor_secret VARCHAR(255),

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_admins_username ON admins(username);
CREATE INDEX idx_admins_email ON admins(email);
CREATE INDEX idx_admins_active ON admins(active);

-- Customers (can belong to multiple sites)
CREATE TABLE customers (
    customer_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    -- Profile
    display_name VARCHAR(255),
    bio TEXT,
    avatar_url VARCHAR(500),
    age_verified BOOLEAN DEFAULT false,

    -- Status
    active BOOLEAN DEFAULT true,
    verified BOOLEAN DEFAULT false,
    suspended BOOLEAN DEFAULT false,

    -- Security
    registration_ip INET,
    last_login_ip INET,
    last_login_at TIMESTAMP,
    failed_login_attempts INTEGER DEFAULT 0,
    locked_until TIMESTAMP,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Billing
    total_spent DECIMAL(10,2) DEFAULT 0.00,
    credits DECIMAL(10,2) DEFAULT 0.00,

    -- Metadata
    preferences JSONB DEFAULT '{}'::jsonb,
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_customers_username ON customers(username);
CREATE INDEX idx_customers_email ON customers(email);
CREATE INDEX idx_customers_active ON customers(active);
CREATE INDEX idx_customers_created_at ON customers(created_at);

-- Customer-Site relationship (many-to-many)
CREATE TABLE customer_sites (
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_activity TIMESTAMP,

    -- Site-specific stats
    total_sessions INTEGER DEFAULT 0,
    total_messages INTEGER DEFAULT 0,
    total_calls INTEGER DEFAULT 0,
    site_credits DECIMAL(10,2) DEFAULT 0.00,

    PRIMARY KEY (customer_id, site_id)
);

CREATE INDEX idx_customer_sites_customer ON customer_sites(customer_id);
CREATE INDEX idx_customer_sites_site ON customer_sites(site_id);
CREATE INDEX idx_customer_sites_last_activity ON customer_sites(last_activity);

-- Operators (work across multiple sites)
CREATE TABLE operators (
    operator_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    username VARCHAR(100) UNIQUE NOT NULL,
    email VARCHAR(255) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,

    -- Profile
    display_name VARCHAR(255),
    bio TEXT,
    age INTEGER,
    location VARCHAR(100),
    languages JSONB DEFAULT '[]'::jsonb,
    specialties JSONB DEFAULT '[]'::jsonb,
    avatar_url VARCHAR(500),
    gallery_images JSONB DEFAULT '[]'::jsonb,

    -- Status
    active BOOLEAN DEFAULT true,
    verified BOOLEAN DEFAULT false,
    category VARCHAR(50) DEFAULT 'standard', -- standard, premium, elite

    -- Availability
    online BOOLEAN DEFAULT false,
    available BOOLEAN DEFAULT false,
    status_message TEXT,

    -- Earnings
    commission_rate DECIMAL(3,2) DEFAULT 0.60,
    total_earned DECIMAL(12,2) DEFAULT 0.00,
    pending_payout DECIMAL(12,2) DEFAULT 0.00,

    -- Security
    last_login_at TIMESTAMP,
    last_login_ip INET,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_operators_username ON operators(username);
CREATE INDEX idx_operators_email ON operators(email);
CREATE INDEX idx_operators_active ON operators(active);
CREATE INDEX idx_operators_verified ON operators(verified);
CREATE INDEX idx_operators_online ON operators(online);
CREATE INDEX idx_operators_category ON operators(category);

-- Operator-Site relationship (many-to-many)
CREATE TABLE operator_sites (
    operator_id UUID REFERENCES operators(operator_id) ON DELETE CASCADE,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    active BOOLEAN DEFAULT true,

    -- Site-specific settings
    site_commission_rate DECIMAL(3,2),
    site_display_name VARCHAR(255),
    site_bio TEXT,

    -- Site-specific stats
    total_calls INTEGER DEFAULT 0,
    total_messages INTEGER DEFAULT 0,
    total_earnings DECIMAL(12,2) DEFAULT 0.00,
    rating DECIMAL(3,2) DEFAULT 0.00,
    total_ratings INTEGER DEFAULT 0,

    PRIMARY KEY (operator_id, site_id)
);

CREATE INDEX idx_operator_sites_operator ON operator_sites(operator_id);
CREATE INDEX idx_operator_sites_site ON operator_sites(site_id);
CREATE INDEX idx_operator_sites_active ON operator_sites(active);

-- ============================================================================
-- MESSAGING & COMMUNICATION
-- ============================================================================

-- Conversations (private messaging between customer and operator)
CREATE TABLE conversations (
    conversation_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,
    operator_id UUID REFERENCES operators(operator_id) ON DELETE CASCADE,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,

    -- Status
    active BOOLEAN DEFAULT true,
    started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    last_message_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Stats
    total_messages INTEGER DEFAULT 0,
    unread_customer INTEGER DEFAULT 0,
    unread_operator INTEGER DEFAULT 0,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_conversations_customer ON conversations(customer_id);
CREATE INDEX idx_conversations_operator ON conversations(operator_id);
CREATE INDEX idx_conversations_site ON conversations(site_id);
CREATE INDEX idx_conversations_last_message ON conversations(last_message_at);

-- Messages
CREATE TABLE messages (
    message_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    conversation_id UUID REFERENCES conversations(conversation_id) ON DELETE CASCADE,

    -- Sender
    sender_type VARCHAR(20) NOT NULL, -- 'customer', 'operator'
    sender_id UUID NOT NULL,

    -- Content
    content TEXT NOT NULL,
    content_type VARCHAR(20) DEFAULT 'text', -- text, image, video, audio, file
    media_url VARCHAR(500),

    -- Status
    read BOOLEAN DEFAULT false,
    read_at TIMESTAMP,
    deleted BOOLEAN DEFAULT false,

    -- Timestamps
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_messages_conversation ON messages(conversation_id);
CREATE INDEX idx_messages_sender ON messages(sender_type, sender_id);
CREATE INDEX idx_messages_sent_at ON messages(sent_at);
CREATE INDEX idx_messages_read ON messages(read);

-- Chat Rooms (group chat)
CREATE TABLE chat_rooms (
    room_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,
    operator_id UUID REFERENCES operators(operator_id) ON DELETE CASCADE,

    -- Details
    name VARCHAR(255) NOT NULL,
    description TEXT,
    room_type VARCHAR(50) DEFAULT 'public', -- public, private, vip

    -- Capacity
    max_participants INTEGER DEFAULT 50,
    current_participants INTEGER DEFAULT 0,

    -- Status
    active BOOLEAN DEFAULT true,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Settings
    settings JSONB DEFAULT '{}'::jsonb,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_chat_rooms_site ON chat_rooms(site_id);
CREATE INDEX idx_chat_rooms_operator ON chat_rooms(operator_id);
CREATE INDEX idx_chat_rooms_active ON chat_rooms(active);

-- Room Messages
CREATE TABLE room_messages (
    message_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    room_id UUID REFERENCES chat_rooms(room_id) ON DELETE CASCADE,

    -- Sender
    sender_type VARCHAR(20) NOT NULL, -- 'customer', 'operator'
    sender_id UUID NOT NULL,
    sender_username VARCHAR(100),

    -- Content
    content TEXT NOT NULL,
    content_type VARCHAR(20) DEFAULT 'text',

    -- Timestamps
    sent_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_room_messages_room ON room_messages(room_id);
CREATE INDEX idx_room_messages_sent_at ON room_messages(sent_at);

-- Room Participants
CREATE TABLE room_participants (
    room_id UUID REFERENCES chat_rooms(room_id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,
    joined_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    left_at TIMESTAMP,
    active BOOLEAN DEFAULT true,

    PRIMARY KEY (room_id, customer_id)
);

CREATE INDEX idx_room_participants_room ON room_participants(room_id);
CREATE INDEX idx_room_participants_customer ON room_participants(customer_id);

-- ============================================================================
-- CONTENT MARKETPLACE
-- ============================================================================

-- Content Items (photos, videos, etc. for sale)
CREATE TABLE content_items (
    content_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    operator_id UUID REFERENCES operators(operator_id) ON DELETE CASCADE,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,

    -- Details
    title VARCHAR(255) NOT NULL,
    description TEXT,
    content_type VARCHAR(50) NOT NULL, -- photo, video, album, custom

    -- Pricing
    price DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',

    -- Media
    thumbnail_url VARCHAR(500),
    media_urls JSONB DEFAULT '[]'::jsonb,

    -- Status
    active BOOLEAN DEFAULT true,
    approved BOOLEAN DEFAULT false,
    featured BOOLEAN DEFAULT false,

    -- Stats
    views INTEGER DEFAULT 0,
    purchases INTEGER DEFAULT 0,
    total_revenue DECIMAL(12,2) DEFAULT 0.00,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Metadata
    tags JSONB DEFAULT '[]'::jsonb,
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_content_items_operator ON content_items(operator_id);
CREATE INDEX idx_content_items_site ON content_items(site_id);
CREATE INDEX idx_content_items_active ON content_items(active);
CREATE INDEX idx_content_items_price ON content_items(price);

-- Content Purchases
CREATE TABLE content_purchases (
    purchase_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    content_id UUID REFERENCES content_items(content_id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,

    -- Transaction
    amount DECIMAL(10,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',
    purchased_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Access
    access_expires_at TIMESTAMP,
    download_count INTEGER DEFAULT 0,
    max_downloads INTEGER,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_content_purchases_content ON content_purchases(content_id);
CREATE INDEX idx_content_purchases_customer ON content_purchases(customer_id);
CREATE INDEX idx_content_purchases_purchased_at ON content_purchases(purchased_at);

-- ============================================================================
-- OPERATOR MANAGEMENT
-- ============================================================================

-- Operator Requests (customers requesting specific operators)
CREATE TABLE operator_requests (
    request_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,
    operator_id UUID REFERENCES operators(operator_id) ON DELETE CASCADE,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,

    -- Request details
    request_type VARCHAR(50) NOT NULL, -- 'call', 'chat', 'video', 'custom'
    message TEXT,

    -- Status
    status VARCHAR(50) DEFAULT 'pending', -- pending, accepted, declined, expired
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP,
    expires_at TIMESTAMP,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_operator_requests_customer ON operator_requests(customer_id);
CREATE INDEX idx_operator_requests_operator ON operator_requests(operator_id);
CREATE INDEX idx_operator_requests_site ON operator_requests(site_id);
CREATE INDEX idx_operator_requests_status ON operator_requests(status);

-- Room Invites
CREATE TABLE room_invites (
    invite_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    room_id UUID REFERENCES chat_rooms(room_id) ON DELETE CASCADE,
    operator_id UUID REFERENCES operators(operator_id) ON DELETE CASCADE,
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,

    -- Status
    status VARCHAR(50) DEFAULT 'pending', -- pending, accepted, declined, expired
    invited_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    responded_at TIMESTAMP,
    expires_at TIMESTAMP,

    -- Message
    message TEXT,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_room_invites_room ON room_invites(room_id);
CREATE INDEX idx_room_invites_customer ON room_invites(customer_id);
CREATE INDEX idx_room_invites_status ON room_invites(status);

-- ============================================================================
-- CUSTOMER FEATURES
-- ============================================================================

-- Favorites (customers saving favorite operators)
CREATE TABLE favorites (
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,
    operator_id UUID REFERENCES operators(operator_id) ON DELETE CASCADE,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    PRIMARY KEY (customer_id, operator_id, site_id)
);

CREATE INDEX idx_favorites_customer ON favorites(customer_id);
CREATE INDEX idx_favorites_operator ON favorites(operator_id);

-- Notifications
CREATE TABLE notifications (
    notification_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),

    -- Recipient
    recipient_type VARCHAR(20) NOT NULL, -- 'customer', 'operator', 'admin'
    recipient_id UUID NOT NULL,

    -- Content
    title VARCHAR(255) NOT NULL,
    message TEXT NOT NULL,
    notification_type VARCHAR(50) NOT NULL, -- 'message', 'request', 'payment', 'system'

    -- Status
    read BOOLEAN DEFAULT false,
    read_at TIMESTAMP,

    -- Action
    action_url VARCHAR(500),
    action_label VARCHAR(100),

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_notifications_recipient ON notifications(recipient_type, recipient_id);
CREATE INDEX idx_notifications_read ON notifications(read);
CREATE INDEX idx_notifications_created_at ON notifications(created_at);

-- Customer Activity Log
CREATE TABLE customer_activity (
    activity_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),
    customer_id UUID REFERENCES customers(customer_id) ON DELETE CASCADE,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,

    -- Activity
    activity_type VARCHAR(50) NOT NULL, -- 'login', 'message', 'call', 'purchase', etc.
    description TEXT,

    -- Context
    ip_address INET,
    user_agent TEXT,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_customer_activity_customer ON customer_activity(customer_id);
CREATE INDEX idx_customer_activity_site ON customer_activity(site_id);
CREATE INDEX idx_customer_activity_type ON customer_activity(activity_type);
CREATE INDEX idx_customer_activity_created_at ON customer_activity(created_at);

-- ============================================================================
-- SECURITY & VERIFICATION
-- ============================================================================

-- ID Verifications
CREATE TABLE id_verifications (
    verification_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),

    -- Subject
    subject_type VARCHAR(20) NOT NULL, -- 'customer', 'operator'
    subject_id UUID NOT NULL,

    -- Verification details
    verification_type VARCHAR(50) NOT NULL, -- 'id_document', 'age_verification', 'address'
    status VARCHAR(50) DEFAULT 'pending', -- pending, approved, rejected

    -- Documents
    document_urls JSONB DEFAULT '[]'::jsonb,

    -- Review
    reviewed_by UUID REFERENCES admins(admin_id),
    reviewed_at TIMESTAMP,
    rejection_reason TEXT,

    -- Timestamps
    submitted_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_id_verifications_subject ON id_verifications(subject_type, subject_id);
CREATE INDEX idx_id_verifications_status ON id_verifications(status);

-- Account Locks (rate limiting, security)
CREATE TABLE account_locks (
    lock_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),

    -- Account
    account_type VARCHAR(20) NOT NULL, -- 'customer', 'operator', 'admin'
    account_id UUID NOT NULL,

    -- Lock details
    reason VARCHAR(255) NOT NULL,
    locked_by UUID,
    locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    locked_until TIMESTAMP,

    -- Status
    active BOOLEAN DEFAULT true,
    unlocked_at TIMESTAMP,
    unlocked_by UUID,

    -- Metadata
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_account_locks_account ON account_locks(account_type, account_id);
CREATE INDEX idx_account_locks_active ON account_locks(active);

-- Verification Codes (2FA, email verification, password reset)
CREATE TABLE verification_codes (
    code_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),

    -- Account
    account_type VARCHAR(20) NOT NULL,
    account_id UUID NOT NULL,

    -- Code
    code VARCHAR(100) NOT NULL,
    code_type VARCHAR(50) NOT NULL, -- 'email_verification', 'password_reset', '2fa'

    -- Status
    used BOOLEAN DEFAULT false,
    used_at TIMESTAMP,
    expires_at TIMESTAMP NOT NULL,

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,

    -- Metadata
    ip_address INET,
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_verification_codes_account ON verification_codes(account_type, account_id);
CREATE INDEX idx_verification_codes_code ON verification_codes(code);
CREATE INDEX idx_verification_codes_used ON verification_codes(used);

-- Username Reservations (prevent username squatting)
CREATE TABLE username_reservations (
    username VARCHAR(100) PRIMARY KEY,
    reserved_by VARCHAR(50) NOT NULL, -- system identifier
    reserved_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    expires_at TIMESTAMP,
    reason VARCHAR(255)
);

CREATE INDEX idx_username_reservations_expires ON username_reservations(expires_at);

-- ============================================================================
-- BILLING & TRANSACTIONS
-- ============================================================================

-- Transactions
CREATE TABLE transactions (
    transaction_id UUID PRIMARY KEY DEFAULT uuid_generate_v4(),

    -- Parties
    customer_id UUID REFERENCES customers(customer_id) ON DELETE SET NULL,
    operator_id UUID REFERENCES operators(operator_id) ON DELETE SET NULL,
    site_id VARCHAR(50) REFERENCES sites(site_id) ON DELETE CASCADE,

    -- Transaction details
    transaction_type VARCHAR(50) NOT NULL, -- 'purchase', 'call', 'message', 'content', 'tip'
    amount DECIMAL(12,2) NOT NULL,
    currency VARCHAR(3) DEFAULT 'USD',

    -- Commission
    operator_commission DECIMAL(12,2),
    platform_fee DECIMAL(12,2),

    -- Payment
    payment_method VARCHAR(50),
    payment_reference VARCHAR(255),

    -- Status
    status VARCHAR(50) DEFAULT 'completed', -- pending, completed, failed, refunded

    -- Timestamps
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    completed_at TIMESTAMP,

    -- Metadata
    description TEXT,
    metadata JSONB DEFAULT '{}'::jsonb
);

CREATE INDEX idx_transactions_customer ON transactions(customer_id);
CREATE INDEX idx_transactions_operator ON transactions(operator_id);
CREATE INDEX idx_transactions_site ON transactions(site_id);
CREATE INDEX idx_transactions_created_at ON transactions(created_at);
CREATE INDEX idx_transactions_status ON transactions(status);

-- ============================================================================
-- TRIGGERS FOR UPDATED_AT
-- ============================================================================

CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

-- Apply updated_at triggers
CREATE TRIGGER update_sites_updated_at BEFORE UPDATE ON sites FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_admins_updated_at BEFORE UPDATE ON admins FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_customers_updated_at BEFORE UPDATE ON customers FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_operators_updated_at BEFORE UPDATE ON operators FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
CREATE TRIGGER update_content_items_updated_at BEFORE UPDATE ON content_items FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- ============================================================================
-- INITIAL DATA
-- ============================================================================

-- Insert core sites
INSERT INTO sites (site_id, domain, name, description, template, categories, theme, features, billing_config) VALUES
('aeims_app', 'aeims.app', 'AEIMS Platform', 'Automated Entertainment & Interaction Management System', 'admin',
 '["platform", "admin", "management"]'::jsonb,
 '{"primary_color": "#667eea", "secondary_color": "#764ba2", "accent_color": "#f093fb"}'::jsonb,
 '{"operator_management": true, "multi_site": true, "analytics": true, "billing": true}'::jsonb,
 '{"currency": "USD"}'::jsonb),

('sexacomms_com', 'sexacomms.com', 'SexaComms', 'Adult communication platform for operators', 'operator',
 '["operator", "dashboard", "admin"]'::jsonb,
 '{"primary_color": "#2c3e50", "secondary_color": "#e74c3c", "accent_color": "#3498db"}'::jsonb,
 '{"video_chat": true, "voice_calls": true, "text_chat": true, "operator_profiles": true, "operator_dashboard": true, "earnings_tracking": true}'::jsonb,
 '{"operator_commission": 0.60, "currency": "USD"}'::jsonb),

('flirts_nyc', 'flirts.nyc', 'Flirts NYC', 'New York''s hottest connections and entertainment', 'default',
 '["dating", "chat", "video"]'::jsonb,
 '{"primary_color": "#ff6b6b", "secondary_color": "#4ecdc4", "accent_color": "#ffe66d"}'::jsonb,
 '{"video_chat": true, "voice_calls": true, "text_chat": true, "operator_profiles": true, "payment_processing": true}'::jsonb,
 '{"per_minute_rate": 2.99, "minimum_purchase": 10.00, "currency": "USD"}'::jsonb),

('nycflirts_com', 'nycflirts.com', 'NYC Flirts', 'Premium NYC dating and entertainment service', 'default',
 '["dating", "chat", "video"]'::jsonb,
 '{"primary_color": "#e91e63", "secondary_color": "#9c27b0", "accent_color": "#ff4081"}'::jsonb,
 '{"video_chat": true, "voice_calls": true, "text_chat": true, "operator_profiles": true, "payment_processing": true}'::jsonb,
 '{"per_minute_rate": 2.99, "minimum_purchase": 10.00, "currency": "USD"}'::jsonb)
ON CONFLICT (site_id) DO NOTHING;

-- ============================================================================
-- VIEWS FOR COMMON QUERIES
-- ============================================================================

-- Active operators across all sites
CREATE OR REPLACE VIEW v_active_operators AS
SELECT
    o.operator_id,
    o.username,
    o.email,
    o.display_name,
    o.online,
    o.available,
    o.category,
    o.commission_rate,
    array_agg(os.site_id) as sites
FROM operators o
LEFT JOIN operator_sites os ON o.operator_id = os.operator_id AND os.active = true
WHERE o.active = true AND o.verified = true
GROUP BY o.operator_id, o.username, o.email, o.display_name, o.online, o.available, o.category, o.commission_rate;

-- Customer summary with site counts
CREATE OR REPLACE VIEW v_customer_summary AS
SELECT
    c.customer_id,
    c.username,
    c.email,
    c.display_name,
    c.active,
    c.credits,
    c.total_spent,
    count(DISTINCT cs.site_id) as site_count,
    array_agg(DISTINCT cs.site_id) as sites
FROM customers c
LEFT JOIN customer_sites cs ON c.customer_id = cs.customer_id
GROUP BY c.customer_id, c.username, c.email, c.display_name, c.active, c.credits, c.total_spent;

-- Site statistics
CREATE OR REPLACE VIEW v_site_stats AS
SELECT
    s.site_id,
    s.domain,
    s.name,
    s.active,
    count(DISTINCT cs.customer_id) as customer_count,
    count(DISTINCT os.operator_id) as operator_count,
    COALESCE(sum(t.amount), 0) as total_revenue
FROM sites s
LEFT JOIN customer_sites cs ON s.site_id = cs.site_id
LEFT JOIN operator_sites os ON s.site_id = os.site_id AND os.active = true
LEFT JOIN transactions t ON s.site_id = t.site_id AND t.status = 'completed'
GROUP BY s.site_id, s.domain, s.name, s.active;

-- ============================================================================
-- COMMENTS FOR DOCUMENTATION
-- ============================================================================

COMMENT ON TABLE sites IS 'Multi-tenant foundation - all sites in the AEIMS platform';
COMMENT ON TABLE customers IS 'End users who use the dating/chat services';
COMMENT ON TABLE operators IS 'Service providers who interact with customers';
COMMENT ON TABLE admins IS 'Platform administrators with full access';
COMMENT ON TABLE transactions IS 'All financial transactions across the platform';
COMMENT ON TABLE content_items IS 'Marketplace content (photos, videos) for purchase';
COMMENT ON TABLE messages IS 'Private 1-on-1 messages between customers and operators';
COMMENT ON TABLE room_messages IS 'Group chat messages in public/private rooms';

-- ============================================================================
-- END OF SCHEMA
-- ============================================================================
