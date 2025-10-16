# AEIMS Telephony Integration - Complete Implementation Plan
**Date:** 2025-10-16
**Status:** Ready for Implementation

## Overview
This document outlines the complete integration of voice calls and text messaging into the customer-facing sites (flirts.nyc, nycflirts.com, sexacomms.com) with real-time billing through Asterisk.

---

## ✅ Phase 1: Database & Core Services (COMPLETED)

### Created Files:
1. ✅ `database/migrations/004-create-telephony-tables.sql`
   - Tables: calls, messages, transactions, chat_sessions, operator_ratings, operator_customer_interactions
   - Indexes and triggers for automatic interaction tracking

2. ✅ `database/migrations/005-add-free-minutes-support.sql`
   - Free minutes packages with FIFO consumption
   - Connect fee support
   - Auto-expiration triggers

3. ✅ `includes/CallService.php`
   - Initiates calls via Asterisk adapter
   - Validates customer balance
   - Tracks call records

4. ✅ `includes/TextService.php`
   - Customer→Operator messaging (charged)
   - Operator→Customer messaging (free)
   - Twilio SMS integration

5. ✅ `includes/OperatorStatsService.php`
   - Real database-backed stats
   - Already deployed and working

---

## 🔨 Phase 2: API Endpoints (TO BUILD)

### File: `api/calls/initiate.php`
**Purpose:** Customer initiates call to operator

**Request:**
```json
POST /api/calls/initiate.php
{
  "operator_id": 22,
  "customer_phone": "+15551234567"
}
```

**Response:**
```json
{
  "success": true,
  "call_id": "call_1234567890_abc123",
  "operator": "Sarah Johnson",
  "rate_per_minute": 3.99,
  "connect_fee": 0.99,
  "free_minutes_used": 0,
  "estimated_duration_minutes": 15
}
```

**Logic:**
1. Check customer balance
2. Check for available free minutes
3. If free minutes: Charge connect fee only
4. If no free minutes: Validate balance ≥ rate_per_minute
5. Call CallService->initiateBridgedCall()
6. Return call details

---

### File: `api/calls/status.php`
**Purpose:** Check active call status

**Request:**
```json
GET /api/calls/status.php?call_id=call_123
```

**Response:**
```json
{
  "success": true,
  "call_id": "call_123",
  "status": "answered",
  "duration_seconds": 127,
  "operator_id": 22,
  "rate_per_minute": 3.99,
  "estimated_charges": 7.98
}
```

---

### File: `api/messages/send.php`
**Purpose:** Send text message to operator

**Request:**
```json
POST /api/messages/send.php
{
  "operator_id": 22,
  "message_text": "Hey, are you available?",
  "message_type": "chat"
}
```

**Response:**
```json
{
  "success": true,
  "message_id": "msg_1234567890_abc123",
  "charged": 0.50,
  "balance_remaining": 25.50
}
```

---

### File: `api/messages/conversation.php`
**Purpose:** Get message history with operator

**Request:**
```json
GET /api/messages/conversation.php?operator_id=22&limit=50
```

**Response:**
```json
{
  "success": true,
  "messages": [
    {
      "message_id": "msg_123",
      "sender_type": "customer",
      "message_text": "Hey there!",
      "created_at": "2025-10-16 14:30:00"
    },
    {
      "message_id": "msg_124",
      "sender_type": "operator",
      "message_text": "Hi! How can I help?",
      "created_at": "2025-10-16 14:30:15"
    }
  ]
}
```

---

### File: `api/balance/check.php`
**Purpose:** Get customer balance and call time estimates

**Request:**
```json
GET /api/balance/check.php?operator_id=22
```

**Response:**
```json
{
  "success": true,
  "balance": 45.00,
  "free_minutes": 10,
  "operator_rate": 3.99,
  "connect_fee": 0.99,
  "estimated_duration": {
    "with_free_minutes": 10,
    "with_paid_balance": 11,
    "total_minutes": 21
  }
}
```

---

## 🎨 Phase 3: UI Components (TO BUILD)

### Component: Call Button Module
**Location:** Create `sites/shared/components/call-button.php`

**Features:**
1. Shows operator availability status
2. Displays balance and estimated talk time
3. "Add Funds" button if insufficient balance
4. "Call Now" button to initiate call
5. Real-time call timer during active calls

**UI Flow:**
```
[Operator Profile Page]
├── Operator Status: 🟢 Online
├── Your Balance: $45.00
├── Free Minutes: 10 minutes available
├── Talk Time: ~21 minutes available
├── [Add More Funds] [Call Sarah - $3.99/min]
└── Connect Fee: $0.99
```

**HTML Structure:**
```html
<div class="call-module">
  <div class="operator-status">
    <span class="status-indicator online"></span>
    <span>Sarah is Online</span>
  </div>

  <div class="balance-info">
    <h3>Your Account</h3>
    <p class="balance">Balance: $45.00</p>
    <p class="free-minutes">Free Minutes: 10 min</p>
    <p class="talk-time">Est. Talk Time: ~21 minutes</p>
  </div>

  <div class="call-pricing">
    <p>Rate: $3.99/min</p>
    <p>Connect Fee: $0.99</p>
  </div>

  <div class="call-actions">
    <button class="add-funds-btn">Add More Funds</button>
    <button class="call-btn" data-operator-id="22">
      📞 Call Sarah
    </button>
  </div>

  <!-- During call -->
  <div class="active-call" style="display:none;">
    <div class="call-timer">05:23</div>
    <div class="call-cost">Cost: $21.52</div>
    <button class="hangup-btn">Hang Up</button>
  </div>
</div>
```

**JavaScript:**
```javascript
// Call initiation
document.querySelector('.call-btn').addEventListener('click', async function() {
  const operatorId = this.dataset.operatorId;

  // Show loading state
  this.disabled = true;
  this.innerHTML = 'Connecting...';

  try {
    const response = await fetch('/api/calls/initiate.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/json'},
      body: JSON.stringify({
        operator_id: operatorId,
        customer_phone: customerPhone // From session
      })
    });

    const data = await response.json();

    if (data.success) {
      // Hide call button, show active call UI
      document.querySelector('.call-actions').style.display = 'none';
      document.querySelector('.active-call').style.display = 'block';

      // Start call timer
      startCallTimer(data.call_id);
    } else {
      alert(data.error || 'Failed to initiate call');
    }
  } catch (error) {
    alert('Connection error. Please try again.');
  } finally {
    this.disabled = false;
    this.innerHTML = '📞 Call Sarah';
  }
});

// Call timer
function startCallTimer(callId) {
  let seconds = 0;
  const timerEl = document.querySelector('.call-timer');
  const costEl = document.querySelector('.call-cost');

  const interval = setInterval(async () => {
    seconds++;

    // Update timer display
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    timerEl.textContent = `${mins.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;

    // Poll for call status every 5 seconds
    if (seconds % 5 === 0) {
      const status = await fetch(`/api/calls/status.php?call_id=${callId}`).then(r => r.json());

      if (status.status === 'ended') {
        clearInterval(interval);
        showCallSummary(status);
      } else {
        costEl.textContent = `Cost: $${status.estimated_charges.toFixed(2)}`;
      }
    }
  }, 1000);
}
```

---

### Integration into Existing Pages

**1. `operator-profile.php` (Both sites)**

Add after line 200 (in the profile info section):

```php
<?php
// Get customer balance and free minutes
$balance = $customer['balance'] ?? 0;
$freeMinutes = $db->fetchOne("
    SELECT get_available_free_minutes(:customer_id, :operator_id) as minutes
", [
    'customer_id' => $customer['id'],
    'operator_id' => $operator['id']
])['minutes'] ?? 0;

// Calculate estimated talk time
$operatorRate = $operator['metadata']['rate_per_minute'] ?? 3.99;
$connectFee = $operator['metadata']['connect_fee'] ?? 0.99;
$paidMinutes = $balance > $connectFee ? floor(($balance - $connectFee) / $operatorRate) : 0;
$totalMinutes = $freeMinutes + $paidMinutes;
?>

<?php include __DIR__ . '/../shared/components/call-button.php'; ?>
```

**2. `search-operators.php` (Both sites)**

Add mini call button to each operator card:

```html
<div class="operator-actions">
  <a href="operator-profile.php?id=<?= $op['id'] ?>">View Profile</a>
  <button class="quick-call-btn" data-operator-id="<?= $op['id'] ?>">
    📞 Call
  </button>
</div>
```

**3. `dashboard.php` (Both sites)**

Add "Active Calls" widget showing any ongoing calls.

---

## ⚙️ Phase 4: Asterisk Billing Service Modifications (TO BUILD)

### File: `aeims-asterisk/services/aeims-billing/app.py`

**Changes Needed:**

1. **Add PostgreSQL connection:**
```python
import psycopg2
from psycopg2.extras import RealDictCursor

PG_HOST = os.getenv("PG_HOST", "nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com")
PG_USER = os.getenv("PG_USER", "nitetext_user")
PG_PASS = os.getenv("PG_PASS", "NiteText2025!SecureProd")
PG_DB = os.getenv("PG_DB", "nitetext_production")

def get_db():
    return psycopg2.connect(
        host=PG_HOST, user=PG_USER,
        password=PG_PASS, database=PG_DB
    )
```

2. **Update CallEvent model:**
```python
class CallEvent(BaseModel):
    call_id: str
    direction: str
    ts: float
    state: str
    account_id: str  # customer_id
    operator_id: Optional[int] = None
    rate_per_minute: Optional[float] = None
    free_minutes_id: Optional[int] = None
    session_id: Optional[str] = None
    metadata: Optional[Dict[str, str]] = None
```

3. **Modify `on_call_event` endpoint:**
```python
@app.post("/events/call")
async def on_call_event(ev: CallEvent):
    if ev.state == "started":
        # Update call status in PostgreSQL
        conn = get_db()
        cur = conn.cursor()
        cur.execute("""
            UPDATE calls
            SET status = 'answered', answered_at = CURRENT_TIMESTAMP
            WHERE call_id = %s
            RETURNING id, operator_id, customer_id, free_minutes_id
        """, (ev.call_id,))
        call_data = cur.fetchone()
        conn.commit()
        cur.close()
        conn.close()

        store_session(ev.call_id, ev.ts)
        return {"ok": True, "tracking": ev.call_id}

    elif ev.state == "ended":
        # Calculate charges with free minutes support
        start = get_session(ev.call_id)
        if not start:
            return {"ok": False, "error": "unknown call_id"}

        dur_sec = max(0, ev.ts - start)
        minutes = math.ceil(dur_sec / 60.0)

        conn = get_db()
        cur = conn.cursor(cursor_factory=RealDictCursor)

        # Get call details
        cur.execute("""
            SELECT id, operator_id, customer_id, free_minutes_id, connect_fee_charged
            FROM calls WHERE call_id = %s
        """, (ev.call_id,))
        call = cur.fetchone()

        if not call:
            return {"ok": False, "error": "Call not found in database"}

        # Check for free minutes
        free_minutes_used = 0
        paid_minutes = minutes

        if call['free_minutes_id']:
            # Consume free minutes
            cur.execute("""
                SELECT * FROM consume_free_minutes(%s, %s, %s)
            """, (call['customer_id'], call['operator_id'], minutes))

            free_packages = cur.fetchall()
            free_minutes_used = sum(p['minutes_consumed'] for p in free_packages)
            paid_minutes = max(0, minutes - free_minutes_used)

        # Calculate charges
        rate = ev.rate_per_minute or 3.99
        total_amount = round(paid_minutes * rate, 2)
        operator_amount = round(total_amount * 0.80, 2)
        platform_amount = round(total_amount * 0.20, 2)

        # Update call record
        cur.execute("""
            UPDATE calls
            SET status = 'ended',
                duration_seconds = %s,
                free_minutes_used = %s,
                ended_at = CURRENT_TIMESTAMP
            WHERE id = %s
        """, (dur_sec, free_minutes_used, call['id']))

        # Create transaction
        cur.execute("""
            INSERT INTO transactions
            (call_id, customer_id, operator_id, transaction_type,
             amount, operator_amount, platform_amount,
             minutes, rate_per_minute, description, status)
            VALUES (%s, %s, %s, 'call', %s, %s, %s, %s, %s, %s, 'completed')
        """, (
            call['id'], call['customer_id'], call['operator_id'],
            total_amount, operator_amount, platform_amount,
            paid_minutes, rate,
            f"Call charges ({paid_minutes} paid min + {free_minutes_used} free min)"
        ))

        conn.commit()
        cur.close()
        conn.close()

        delete_session(ev.call_id)

        return {
            "ok": True,
            "duration_seconds": dur_sec,
            "total_minutes": minutes,
            "free_minutes_used": free_minutes_used,
            "paid_minutes": paid_minutes,
            "total_amount": total_amount,
            "operator_amount": operator_amount,
            "platform_amount": platform_amount
        }
```

---

## 📋 Phase 5: Deployment Checklist

### Database Setup:
```bash
# Run migrations
PGPASSWORD='NiteText2025!SecureProd' psql \
  -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext_user \
  -d nitetext_production \
  -f database/migrations/004-create-telephony-tables.sql

PGPASSWORD='NiteText2025!SecureProd' psql \
  -h nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com \
  -U nitetext_user \
  -d nitetext_production \
  -f database/migrations/005-add-free-minutes-support.sql
```

### Add Environment Variables:
```bash
# In ECS task definition for aeims-app
ASTERISK_ADAPTER_URL=http://aeims-asterisk-adapter.aeims-cluster.local:8080
AEIMS_API_KEY=<secure-key>

# In ECS task definition for aeims-billing
PG_HOST=nitetext-db.c3iuy64is41m.us-east-1.rds.amazonaws.com
PG_USER=nitetext_user
PG_PASS=NiteText2025!SecureProd
PG_DB=nitetext_production
```

### Deploy Order:
1. Deploy database migrations
2. Deploy aeims-app with new API endpoints
3. Deploy modified aeims-billing service
4. Test with Playwright

---

## 🧪 Testing Plan

### Test Case 1: Call with Sufficient Balance
```bash
curl -X POST https://aeims.app/api/calls/initiate.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=<session>" \
  -d '{"operator_id": 22, "customer_phone": "+15551234567"}'

# Expected: Call initiated, charges applied
```

### Test Case 2: Call with Free Minutes
```bash
# First, grant free minutes
psql -c "INSERT INTO customer_free_minutes (customer_id, operator_id, minutes_granted) VALUES (1, 22, 10)"

# Then initiate call
curl -X POST https://aeims.app/api/calls/initiate.php \
  -H "Content-Type: application/json" \
  -H "Cookie: PHPSESSID=<session>" \
  -d '{"operator_id": 22, "customer_phone": "+15551234567"}'

# Expected: Only connect fee charged, free minutes consumed
```

### Test Case 3: Insufficient Balance
```bash
# Set balance to $0
psql -c "UPDATE customers SET balance = 0 WHERE id = 1"

# Try to call
curl -X POST https://aeims.app/api/calls/initiate.php ...

# Expected: Error - "Insufficient balance"
```

---

## 📊 Success Metrics

After deployment, verify:
- ✅ Calls can be initiated from operator profiles
- ✅ Balance checks work correctly
- ✅ Free minutes are consumed properly
- ✅ Connect fees are charged
- ✅ Revenue split (80/20) is accurate
- ✅ Operator dashboard shows real earnings
- ✅ Call timers work in real-time
- ✅ Text messages are billed correctly

---

## 🚀 Next Steps

1. **Create API endpoints** (Phase 2)
2. **Build call button component** (Phase 3)
3. **Modify Asterisk billing** (Phase 4)
4. **Deploy and test** (Phase 5)

**Estimated Time:** 6-8 hours of focused development
