-- Migration: Create Telephony and Messaging Tables
-- Purpose: Support real-time call/text billing and operator stats
-- Date: 2025-10-16

-- ============================================================
-- CALLS TABLE - Track all voice calls
-- ============================================================
CREATE TABLE IF NOT EXISTS calls (
  id SERIAL PRIMARY KEY,
  call_id VARCHAR(255) UNIQUE NOT NULL,
  channel_id VARCHAR(255),
  operator_id INTEGER REFERENCES operators(id) ON DELETE SET NULL,
  customer_id INTEGER,
  domain VARCHAR(255),
  direction VARCHAR(20) CHECK (direction IN ('inbound', 'outbound')),
  call_type VARCHAR(50) DEFAULT 'bridged',
  duration_seconds INTEGER DEFAULT 0,
  status VARCHAR(50) DEFAULT 'initiated' CHECK (status IN ('initiated', 'ringing', 'answered', 'busy', 'no_answer', 'failed', 'ended')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  answered_at TIMESTAMP,
  ended_at TIMESTAMP,
  metadata JSONB DEFAULT '{}'
);

CREATE INDEX idx_calls_operator_id ON calls(operator_id);
CREATE INDEX idx_calls_customer_id ON calls(customer_id);
CREATE INDEX idx_calls_created_at ON calls(created_at);
CREATE INDEX idx_calls_status ON calls(status);
CREATE INDEX idx_calls_domain ON calls(domain);

COMMENT ON TABLE calls IS 'Tracks all voice calls between customers and operators';
COMMENT ON COLUMN calls.call_id IS 'Unique call identifier from Asterisk';
COMMENT ON COLUMN calls.call_type IS 'bridged = customer+operator bridge, customer_leg = outbound to customer, operator_leg = outbound to operator';

-- ============================================================
-- MESSAGES TABLE - Track all text messages (SMS/Chat)
-- ============================================================
CREATE TABLE IF NOT EXISTS messages (
  id SERIAL PRIMARY KEY,
  message_id VARCHAR(255) UNIQUE,
  sender_id INTEGER NOT NULL,
  sender_type VARCHAR(20) NOT NULL CHECK (sender_type IN ('operator', 'customer')),
  recipient_id INTEGER NOT NULL,
  recipient_type VARCHAR(20) NOT NULL CHECK (recipient_type IN ('operator', 'customer')),
  domain VARCHAR(255),
  message_text TEXT NOT NULL,
  message_type VARCHAR(50) DEFAULT 'sms' CHECK (message_type IN ('sms', 'chat', 'mms')),
  direction VARCHAR(20) CHECK (direction IN ('inbound', 'outbound')),
  status VARCHAR(50) DEFAULT 'sent' CHECK (status IN ('queued', 'sent', 'delivered', 'failed', 'read')),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  delivered_at TIMESTAMP,
  read_at TIMESTAMP,
  metadata JSONB DEFAULT '{}'
);

CREATE INDEX idx_messages_sender ON messages(sender_id, sender_type);
CREATE INDEX idx_messages_recipient ON messages(recipient_id, recipient_type);
CREATE INDEX idx_messages_created_at ON messages(created_at);
CREATE INDEX idx_messages_domain ON messages(domain);

COMMENT ON TABLE messages IS 'Tracks all text messages between customers and operators';
COMMENT ON COLUMN messages.sender_type IS 'operator or customer';
COMMENT ON COLUMN messages.direction IS 'inbound = to operator (charged), outbound = from operator (free)';

-- ============================================================
-- TRANSACTIONS TABLE - Track all financial transactions
-- ============================================================
CREATE TABLE IF NOT EXISTS transactions (
  id SERIAL PRIMARY KEY,
  transaction_id VARCHAR(255) UNIQUE,
  call_id INTEGER REFERENCES calls(id) ON DELETE SET NULL,
  message_id INTEGER REFERENCES messages(id) ON DELETE SET NULL,
  customer_id INTEGER NOT NULL,
  operator_id INTEGER REFERENCES operators(id) ON DELETE SET NULL,
  domain VARCHAR(255),
  transaction_type VARCHAR(50) NOT NULL CHECK (transaction_type IN ('call', 'message', 'tip', 'subscription', 'refund')),
  amount DECIMAL(10,2) NOT NULL,
  operator_amount DECIMAL(10,2) NOT NULL,
  platform_amount DECIMAL(10,2) NOT NULL,
  currency VARCHAR(3) DEFAULT 'USD',
  minutes INTEGER DEFAULT 0,
  message_count INTEGER DEFAULT 0,
  rate_per_minute DECIMAL(10,2),
  rate_per_message DECIMAL(10,2),
  status VARCHAR(50) DEFAULT 'completed' CHECK (status IN ('pending', 'completed', 'refunded', 'failed')),
  description TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  metadata JSONB DEFAULT '{}'
);

CREATE INDEX idx_transactions_customer_id ON transactions(customer_id);
CREATE INDEX idx_transactions_operator_id ON transactions(operator_id);
CREATE INDEX idx_transactions_created_at ON transactions(created_at);
CREATE INDEX idx_transactions_status ON transactions(status);
CREATE INDEX idx_transactions_type ON transactions(transaction_type);
CREATE INDEX idx_transactions_domain ON transactions(domain);

COMMENT ON TABLE transactions IS 'Tracks all financial transactions with 80/20 operator/platform revenue split';
COMMENT ON COLUMN transactions.operator_amount IS '80% of amount goes to operator';
COMMENT ON COLUMN transactions.platform_amount IS '20% of amount goes to platform';

-- ============================================================
-- CHAT SESSIONS TABLE - Track chat conversations
-- ============================================================
CREATE TABLE IF NOT EXISTS chat_sessions (
  id SERIAL PRIMARY KEY,
  session_id VARCHAR(255) UNIQUE NOT NULL,
  operator_id INTEGER REFERENCES operators(id) ON DELETE SET NULL,
  customer_id INTEGER NOT NULL,
  domain VARCHAR(255),
  status VARCHAR(50) DEFAULT 'active' CHECK (status IN ('active', 'paused', 'ended')),
  message_count INTEGER DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_activity_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  ended_at TIMESTAMP,
  metadata JSONB DEFAULT '{}'
);

CREATE INDEX idx_chat_sessions_operator_id ON chat_sessions(operator_id);
CREATE INDEX idx_chat_sessions_customer_id ON chat_sessions(customer_id);
CREATE INDEX idx_chat_sessions_created_at ON chat_sessions(created_at);
CREATE INDEX idx_chat_sessions_status ON chat_sessions(status);

COMMENT ON TABLE chat_sessions IS 'Tracks ongoing chat conversations between customers and operators';

-- ============================================================
-- OPERATOR RATINGS TABLE - Track customer ratings
-- ============================================================
CREATE TABLE IF NOT EXISTS operator_ratings (
  id SERIAL PRIMARY KEY,
  operator_id INTEGER NOT NULL REFERENCES operators(id) ON DELETE CASCADE,
  customer_id INTEGER NOT NULL,
  call_id INTEGER REFERENCES calls(id) ON DELETE SET NULL,
  rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
  review_text TEXT,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(operator_id, customer_id, call_id)
);

CREATE INDEX idx_operator_ratings_operator_id ON operator_ratings(operator_id);
CREATE INDEX idx_operator_ratings_rating ON operator_ratings(rating);
CREATE INDEX idx_operator_ratings_created_at ON operator_ratings(created_at);

COMMENT ON TABLE operator_ratings IS 'Customer ratings for operators after calls/chats';

-- ============================================================
-- OPERATOR CUSTOMER INTERACTIONS TABLE - Track relationships
-- ============================================================
CREATE TABLE IF NOT EXISTS operator_customer_interactions (
  id SERIAL PRIMARY KEY,
  operator_id INTEGER NOT NULL REFERENCES operators(id) ON DELETE CASCADE,
  customer_id INTEGER NOT NULL,
  interaction_type VARCHAR(50) NOT NULL CHECK (interaction_type IN ('call', 'chat', 'message')),
  interaction_count INTEGER DEFAULT 1,
  last_interaction_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE(operator_id, customer_id, interaction_type)
);

CREATE INDEX idx_oci_operator_id ON operator_customer_interactions(operator_id);
CREATE INDEX idx_oci_customer_id ON operator_customer_interactions(customer_id);
CREATE INDEX idx_oci_interaction_type ON operator_customer_interactions(interaction_type);
CREATE INDEX idx_oci_last_interaction ON operator_customer_interactions(last_interaction_at);

COMMENT ON TABLE operator_customer_interactions IS 'Tracks unique customer relationships for repeat customer stats';

-- ============================================================
-- TRIGGERS - Auto-update interaction counts
-- ============================================================

-- Trigger function to update operator_customer_interactions
CREATE OR REPLACE FUNCTION update_operator_customer_interaction()
RETURNS TRIGGER AS $$
BEGIN
  INSERT INTO operator_customer_interactions
    (operator_id, customer_id, interaction_type, interaction_count, last_interaction_at)
  VALUES
    (NEW.operator_id, NEW.customer_id,
     CASE
       WHEN TG_TABLE_NAME = 'calls' THEN 'call'
       WHEN TG_TABLE_NAME = 'messages' AND NEW.sender_type = 'customer' THEN 'message'
       WHEN TG_TABLE_NAME = 'chat_sessions' THEN 'chat'
     END,
     1,
     CURRENT_TIMESTAMP)
  ON CONFLICT (operator_id, customer_id, interaction_type)
  DO UPDATE SET
    interaction_count = operator_customer_interactions.interaction_count + 1,
    last_interaction_at = CURRENT_TIMESTAMP;

  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

-- Apply trigger to calls
DROP TRIGGER IF EXISTS trigger_call_interaction ON calls;
CREATE TRIGGER trigger_call_interaction
  AFTER INSERT ON calls
  FOR EACH ROW
  WHEN (NEW.operator_id IS NOT NULL AND NEW.customer_id IS NOT NULL)
  EXECUTE FUNCTION update_operator_customer_interaction();

-- Apply trigger to messages (only for customer->operator messages)
DROP TRIGGER IF EXISTS trigger_message_interaction ON messages;
CREATE TRIGGER trigger_message_interaction
  AFTER INSERT ON messages
  FOR EACH ROW
  WHEN (NEW.sender_type = 'customer' AND NEW.recipient_type = 'operator')
  EXECUTE FUNCTION update_operator_customer_interaction();

-- Apply trigger to chat sessions
DROP TRIGGER IF EXISTS trigger_chat_interaction ON chat_sessions;
CREATE TRIGGER trigger_chat_interaction
  AFTER INSERT ON chat_sessions
  FOR EACH ROW
  WHEN (NEW.operator_id IS NOT NULL AND NEW.customer_id IS NOT NULL)
  EXECUTE FUNCTION update_operator_customer_interaction();

-- ============================================================
-- GRANT PERMISSIONS
-- ============================================================

GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public TO nitetext_user;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public TO nitetext_user;
