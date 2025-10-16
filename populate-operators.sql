-- Populate demo operators into PostgreSQL
-- Run with: psql -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com -U nitetext -d aeims_core -f populate-operators.sql

-- Ensure columns exist
ALTER TABLE operators ADD COLUMN IF NOT EXISTS password_hash VARCHAR(255);
ALTER TABLE operators ADD COLUMN IF NOT EXISTS phone VARCHAR(50);

-- Insert 3 demo operators with passwords: demo123, demo456, demo789
INSERT INTO operators (username, email, display_name, password_hash, phone, status, is_active, is_verified, created_at)
VALUES
  ('sarah@example.com', 'sarah@example.com', 'Sarah Johnson', '$2y$12$uP769e4CWOCmgm7iXSsqoeH0Vqoi5lSmsPizDmSTmxOoyyTNuykMm', '+1-555-0101', 'active', true, true, CURRENT_TIMESTAMP),
  ('jessica@example.com', 'jessica@example.com', 'Jessica Williams', '$2y$12$Au9qvnvZrHNoa6IDYSFRlOr54j3WI9hZ264Hh9u3W5kWqZWUSGzgW', '+1-555-0102', 'active', true, true, CURRENT_TIMESTAMP),
  ('amanda@example.com', 'amanda@example.com', 'Amanda Rodriguez', '$2y$12$PAW1bUSBAJzneXOqUYtdYelR/w.6CKPVK9ScuyrfvBR3dr3A43xMa', '+1-555-0103', 'active', true, true, CURRENT_TIMESTAMP)
ON CONFLICT (email) DO UPDATE SET
  password_hash = EXCLUDED.password_hash,
  display_name = EXCLUDED.display_name,
  phone = EXCLUDED.phone,
  updated_at = CURRENT_TIMESTAMP;

-- Verify
SELECT username, email, display_name, is_active, is_verified FROM operators WHERE username IN ('sarah@example.com', 'jessica@example.com', 'amanda@example.com');
