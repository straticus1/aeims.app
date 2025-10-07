-- aeimsLib PostgreSQL Tables
-- Device control and toy management tables for unified AEIMS database

-- Users table (may already exist from aeims_app)
-- CREATE TABLE IF NOT EXISTS users ...

-- Toys/Devices table
CREATE TABLE IF NOT EXISTS aeims_lib_toys (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    device_type VARCHAR(100) NOT NULL,
    manufacturer VARCHAR(100),
    model VARCHAR(100),
    connection_type VARCHAR(50) DEFAULT 'bluetooth',
    device_id VARCHAR(255) UNIQUE,
    capabilities JSONB,
    last_connected TIMESTAMP NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE
);

-- Vibration patterns
CREATE TABLE IF NOT EXISTS aeims_lib_patterns (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT,
    pattern_data JSONB NOT NULL,
    duration INTEGER DEFAULT 0,
    is_public BOOLEAN DEFAULT FALSE,
    category VARCHAR(100) DEFAULT 'custom',
    difficulty_level INTEGER DEFAULT 1 CHECK (difficulty_level >= 1 AND difficulty_level <= 5),
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE
);

-- Pattern ratings
CREATE TABLE IF NOT EXISTS aeims_lib_pattern_ratings (
    id SERIAL PRIMARY KEY,
    pattern_id INTEGER NOT NULL,
    user_id INTEGER NOT NULL,
    rating INTEGER NOT NULL CHECK (rating >= 1 AND rating <= 5),
    review TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (pattern_id) REFERENCES aeims_lib_patterns(id) ON DELETE CASCADE,
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE,
    UNIQUE (pattern_id, user_id)
);

-- User preferences for device control
CREATE TABLE IF NOT EXISTS aeims_lib_user_preferences (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    preference_key VARCHAR(100) NOT NULL,
    preference_value TEXT,
    created_at TIMESTAMP DEFAULT NOW(),
    updated_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE,
    UNIQUE (user_id, preference_key)
);

-- Device control sessions
CREATE TABLE IF NOT EXISTS aeims_lib_control_sessions (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    toy_id INTEGER NOT NULL,
    session_token VARCHAR(255) UNIQUE NOT NULL,
    operator_id INTEGER,
    session_type VARCHAR(50) DEFAULT 'solo',
    started_at TIMESTAMP DEFAULT NOW(),
    ended_at TIMESTAMP NULL,
    total_duration INTEGER DEFAULT 0,
    session_data JSONB,
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE,
    FOREIGN KEY (toy_id) REFERENCES aeims_lib_toys(id) ON DELETE CASCADE,
    FOREIGN KEY (operator_id) REFERENCES aeims_app_users(id) ON DELETE SET NULL
);

-- Activity log for device usage
CREATE TABLE IF NOT EXISTS aeims_lib_activity_log (
    id SERIAL PRIMARY KEY,
    user_id INTEGER NOT NULL,
    toy_id INTEGER,
    activity_type VARCHAR(100) NOT NULL,
    details JSONB,
    created_at TIMESTAMP DEFAULT NOW(),
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE,
    FOREIGN KEY (toy_id) REFERENCES aeims_lib_toys(id) ON DELETE SET NULL
);

-- WebSocket connections tracking
CREATE TABLE IF NOT EXISTS aeims_lib_websocket_connections (
    id VARCHAR(255) PRIMARY KEY,
    user_id INTEGER NOT NULL,
    connection_type VARCHAR(50) DEFAULT 'client',
    ip_address INET,
    user_agent TEXT,
    connected_at TIMESTAMP DEFAULT NOW(),
    last_activity TIMESTAMP DEFAULT NOW(),
    is_active BOOLEAN DEFAULT TRUE,
    session_data JSONB,
    FOREIGN KEY (user_id) REFERENCES aeims_app_users(id) ON DELETE CASCADE
);

-- Device synchronization log
CREATE TABLE IF NOT EXISTS aeims_lib_sync_log (
    id SERIAL PRIMARY KEY,
    toy_id INTEGER NOT NULL,
    command_type VARCHAR(100) NOT NULL,
    command_data JSONB,
    sent_at TIMESTAMP DEFAULT NOW(),
    ack_received_at TIMESTAMP NULL,
    response_data JSONB,
    status VARCHAR(50) DEFAULT 'pending',
    error_message TEXT,
    FOREIGN KEY (toy_id) REFERENCES aeims_lib_toys(id) ON DELETE CASCADE
);

-- Indexes for better performance
CREATE INDEX IF NOT EXISTS idx_aeims_lib_toys_user ON aeims_lib_toys(user_id);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_toys_active ON aeims_lib_toys(is_active);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_patterns_user ON aeims_lib_patterns(user_id);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_patterns_public ON aeims_lib_patterns(is_public);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_sessions_user ON aeims_lib_control_sessions(user_id);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_sessions_active ON aeims_lib_control_sessions(ended_at);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_activity_user ON aeims_lib_activity_log(user_id);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_activity_created ON aeims_lib_activity_log(created_at);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_ws_user ON aeims_lib_websocket_connections(user_id);
CREATE INDEX IF NOT EXISTS idx_aeims_lib_ws_active ON aeims_lib_websocket_connections(is_active);

-- Create triggers for updated_at columns
CREATE TRIGGER update_aeims_lib_toys_updated_at
    BEFORE UPDATE ON aeims_lib_toys
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_aeims_lib_patterns_updated_at
    BEFORE UPDATE ON aeims_lib_patterns
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

CREATE TRIGGER update_aeims_lib_preferences_updated_at
    BEFORE UPDATE ON aeims_lib_user_preferences
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();

-- Insert some default patterns for testing
INSERT INTO aeims_lib_patterns (user_id, name, description, pattern_data, is_public, category) VALUES
(1, 'Gentle Wave', 'A soft, rhythmic pattern perfect for beginners', '{"intensity": [20, 40, 60, 40, 20], "duration": 30000}', TRUE, 'gentle'),
(1, 'Pulse Train', 'Quick pulses with building intensity', '{"intensity": [10, 80, 10, 90, 10, 100], "duration": 45000}', TRUE, 'intense'),
(1, 'Relaxation', 'Slow and steady for calming sessions', '{"intensity": [30, 35, 30, 35, 30], "duration": 60000}', TRUE, 'gentle')
ON CONFLICT DO NOTHING;