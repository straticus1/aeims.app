-- Migration: Add Free Minutes and Connect Fee Support
-- Purpose: Support operators giving customers free minutes
-- Date: 2025-10-16

-- ============================================================
-- CUSTOMER FREE MINUTES TABLE
-- ============================================================
CREATE TABLE IF NOT EXISTS customer_free_minutes (
  id SERIAL PRIMARY KEY,
  customer_id INTEGER NOT NULL,
  operator_id INTEGER REFERENCES operators(id) ON DELETE CASCADE,
  minutes_granted INTEGER NOT NULL,
  minutes_used INTEGER DEFAULT 0,
  minutes_remaining INTEGER GENERATED ALWAYS AS (minutes_granted - minutes_used) STORED,
  expires_at TIMESTAMP,
  granted_by VARCHAR(50) DEFAULT 'operator',
  reason VARCHAR(255),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  is_active BOOLEAN DEFAULT true,
  CHECK (minutes_used <= minutes_granted)
);

CREATE INDEX idx_free_minutes_customer ON customer_free_minutes(customer_id);
CREATE INDEX idx_free_minutes_operator ON customer_free_minutes(operator_id);
CREATE INDEX idx_free_minutes_active ON customer_free_minutes(is_active, expires_at);

COMMENT ON TABLE customer_free_minutes IS 'Tracks free minutes granted to customers by operators';
COMMENT ON COLUMN customer_free_minutes.minutes_remaining IS 'Auto-calculated: minutes_granted - minutes_used';

-- ============================================================
-- ALTER CALLS TABLE - Add free minutes tracking
-- ============================================================
ALTER TABLE calls
  ADD COLUMN IF NOT EXISTS free_minutes_id INTEGER REFERENCES customer_free_minutes(id) ON DELETE SET NULL,
  ADD COLUMN IF NOT EXISTS free_minutes_used INTEGER DEFAULT 0,
  ADD COLUMN IF NOT EXISTS connect_fee_charged DECIMAL(10,2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS is_free_minutes_call BOOLEAN DEFAULT false;

CREATE INDEX idx_calls_free_minutes ON calls(free_minutes_id) WHERE free_minutes_id IS NOT NULL;

COMMENT ON COLUMN calls.free_minutes_id IS 'Links to customer_free_minutes if this call used free minutes';
COMMENT ON COLUMN calls.free_minutes_used IS 'Number of free minutes consumed by this call';
COMMENT ON COLUMN calls.connect_fee_charged IS 'Connection fee charged even on free-minute calls';
COMMENT ON COLUMN calls.is_free_minutes_call IS 'True if call used free minutes package';

-- ============================================================
-- ALTER TRANSACTIONS TABLE - Add connect fee type
-- ============================================================
-- Add new transaction type for connect fees
ALTER TABLE transactions
  DROP CONSTRAINT IF EXISTS transactions_transaction_type_check;

ALTER TABLE transactions
  ADD CONSTRAINT transactions_transaction_type_check
  CHECK (transaction_type IN ('call', 'message', 'tip', 'subscription', 'refund', 'connect_fee'));

ALTER TABLE transactions
  ADD COLUMN IF NOT EXISTS is_connect_fee BOOLEAN DEFAULT false,
  ADD COLUMN IF NOT EXISTS free_minutes_id INTEGER REFERENCES customer_free_minutes(id) ON DELETE SET NULL;

COMMENT ON COLUMN transactions.is_connect_fee IS 'True if this is a connect fee (charged on free-minute calls)';
COMMENT ON COLUMN transactions.free_minutes_id IS 'Links to free minutes package if applicable';

-- ============================================================
-- OPERATOR SETTINGS - Add connect fee rates
-- ============================================================
-- Operators can set their own connect fee in metadata
COMMENT ON COLUMN operators.metadata IS 'JSONB field. Expected keys: phone, rate_per_minute, rate_per_message, connect_fee (default $0.99), languages, specialties, availability';

-- ============================================================
-- FUNCTIONS - Get available free minutes
-- ============================================================
CREATE OR REPLACE FUNCTION get_available_free_minutes(
  p_customer_id INTEGER,
  p_operator_id INTEGER
) RETURNS INTEGER AS $$
DECLARE
  v_total_minutes INTEGER := 0;
BEGIN
  SELECT COALESCE(SUM(minutes_remaining), 0)
  INTO v_total_minutes
  FROM customer_free_minutes
  WHERE customer_id = p_customer_id
    AND operator_id = p_operator_id
    AND is_active = true
    AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
    AND minutes_remaining > 0;

  RETURN v_total_minutes;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION get_available_free_minutes IS 'Returns total available free minutes for a customer with specific operator';

-- ============================================================
-- FUNCTIONS - Consume free minutes (FIFO)
-- ============================================================
CREATE OR REPLACE FUNCTION consume_free_minutes(
  p_customer_id INTEGER,
  p_operator_id INTEGER,
  p_minutes_to_use INTEGER
) RETURNS TABLE (
  free_minutes_id INTEGER,
  minutes_consumed INTEGER
) AS $$
DECLARE
  v_remaining INTEGER := p_minutes_to_use;
  v_package RECORD;
BEGIN
  -- Get active packages in FIFO order (oldest first)
  FOR v_package IN
    SELECT id, minutes_remaining
    FROM customer_free_minutes
    WHERE customer_id = p_customer_id
      AND operator_id = p_operator_id
      AND is_active = true
      AND (expires_at IS NULL OR expires_at > CURRENT_TIMESTAMP)
      AND minutes_remaining > 0
    ORDER BY created_at ASC
  LOOP
    IF v_remaining <= 0 THEN
      EXIT;
    END IF;

    DECLARE
      v_to_consume INTEGER := LEAST(v_remaining, v_package.minutes_remaining);
    BEGIN
      -- Update the package
      UPDATE customer_free_minutes
      SET
        minutes_used = minutes_used + v_to_consume,
        updated_at = CURRENT_TIMESTAMP
      WHERE id = v_package.id;

      -- Return consumed amount
      free_minutes_id := v_package.id;
      minutes_consumed := v_to_consume;
      RETURN NEXT;

      v_remaining := v_remaining - v_to_consume;
    END;
  END LOOP;

  RETURN;
END;
$$ LANGUAGE plpgsql;

COMMENT ON FUNCTION consume_free_minutes IS 'Consumes free minutes from oldest packages first (FIFO). Returns affected package IDs and amounts.';

-- ============================================================
-- TRIGGER - Auto-deactivate expired free minutes
-- ============================================================
CREATE OR REPLACE FUNCTION deactivate_expired_free_minutes()
RETURNS TRIGGER AS $$
BEGIN
  IF NEW.expires_at IS NOT NULL AND NEW.expires_at <= CURRENT_TIMESTAMP THEN
    NEW.is_active := false;
  END IF;
  RETURN NEW;
END;
$$ LANGUAGE plpgsql;

DROP TRIGGER IF EXISTS trigger_check_free_minutes_expiry ON customer_free_minutes;
CREATE TRIGGER trigger_check_free_minutes_expiry
  BEFORE INSERT OR UPDATE ON customer_free_minutes
  FOR EACH ROW
  EXECUTE FUNCTION deactivate_expired_free_minutes();

-- ============================================================
-- SAMPLE DATA - Grant 10 free minutes to test customer
-- ============================================================
-- Uncomment to create test data:
-- INSERT INTO customer_free_minutes
--   (customer_id, operator_id, minutes_granted, reason, expires_at)
-- VALUES
--   (1, 22, 10, 'Welcome bonus', CURRENT_TIMESTAMP + INTERVAL '30 days'),
--   (1, 23, 5, 'Promotional offer', CURRENT_TIMESTAMP + INTERVAL '7 days');
