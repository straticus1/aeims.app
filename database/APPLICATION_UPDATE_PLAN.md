# 🎯 COMPLETE APPLICATION UPDATE PLAN
## PostgreSQL Migration - ALL Features

**Reality Check**: Database is useless if the app doesn't use it!

---

## 📋 **FULL SCOPE - What Needs Updating**

### **1. AEIMS.APP (Platform/Admin)**
**Files to Update:**
- `login.php` - Admin authentication
- `admin-dashboard.php` - Admin interface
- `admin/stats.php` - Statistics and analytics
- Profile management for admins
- User management tools
- Site management tools

**Features:**
- Admin login with DataLayer
- User CRUD operations
- Site CRUD operations
- Analytics queries
- Operator management
- Billing/transaction views

---

### **2. CLIENT SITES (flirts.nyc, nycflirts.com)**
**Files to Update:**
- `sites/flirts.nyc/auth.php` - Customer authentication
- `sites/flirts.nyc/login.php` - Login page
- `sites/flirts.nyc/chat.php` - Chat functionality
- `sites/flirts.nyc/messages.php` - Messaging
- `sites/flirts.nyc/search-operators.php` - Operator search
- `sites/flirts.nyc/profile.php` - Customer profiles
- `sites/flirts.nyc/favorites.php` - Favorites
- `sites/flirts.nyc/content-marketplace.php` - Content marketplace
- `sites/flirts.nyc/rooms.php` - Chat rooms
- `sites/flirts.nyc/room-chat.php` - Room chat
- `sites/nycflirts.com/*` - Same as above

**Features:**
- Customer signup/login with DataLayer
- Search operators (simple & advanced)
- Send messages
- Join chat rooms
- Purchase content
- Manage favorites
- View activity log
- Operator requests

---

### **3. OPERATOR PORTAL (sexacomms.com, agents/)**
**Files to Update:**
- `agents/login.php` - Operator authentication
- `agents/dashboard.php` - Operator dashboard
- `agents/profile.php` - Operator profile management
- `agents/earnings.php` - Earnings tracking
- `agents/content-marketplace.php` - Upload content
- `agents/operator-messages.php` - Message customers
- `agents/create-room.php` - Create chat rooms

**Features:**
- Operator login with DataLayer
- Profile management
- Availability status
- Earnings reports
- Content uploads
- Message customers
- Room management

---

### **4. ID VERIFICATION SYSTEM (NEW - CRITICAL)**
**New Files to Create:**
- `agents/id-verification.php` - Main verification page
- `agents/id-verification-submit.php` - Handle submission
- `includes/IDVerificationManager.php` - Backend logic
- `admin/id-verification-review.php` - Admin review interface

**Workflow:**
```
1. Operator signs up (basic info only)
   ├─ First name, Middle initial, Last name
   ├─ Email address
   ├─ Referral ID (username of referrer)
   └─ Password

2. After login → ID Verification Page
   ├─ Collect address (street, city, state, zip)
   ├─ Upload ID documents (front/back)
   ├─ Age verification
   └─ Submit for review

3. After initial verification → SSN Collection
   ├─ Collect SSN (encrypted)
   ├─ Store securely in PostgreSQL
   └─ Never shown again

4. Recovery Phrase Setup
   ├─ Present list of security questions
   ├─ Operator selects 3 questions
   ├─ Operator provides answers
   └─ Store hashed answers

5. Admin Review
   ├─ Admin views submitted documents
   ├─ Approve or reject with reason
   └─ Notify operator of decision

6. Enable Features After Approval
   ├─ Mark operator as verified
   ├─ Enable site features (messaging, content, etc.)
   ├─ Add to operator pool
   └─ Allow site assignments
```

**Tables Needed** (already in schema):
- `operators` - Basic operator info
- `id_verifications` - Verification submissions
- `verification_codes` - Recovery phrases

---

## 🏗️ **BUILD ORDER - Phase by Phase**

### **PHASE 2A: DataLayer Foundation (CRITICAL)**
**Build:** `includes/DataLayer.php`

**What It Does:**
```php
class DataLayer {
    // Automatically uses PostgreSQL or JSON
    public function getCustomer($username);
    public function saveCustomer($data);
    public function getOperator($username);
    public function saveOperator($data);
    public function getMessages($conversationId);
    public function saveMessage($data);
    // ... all data operations
}
```

**Time Estimate:** 4-6 hours
**Risk:** Low (abstraction layer, doesn't change behavior)

---

### **PHASE 2B: Auth Migration**
**Update:**
1. `login.php` (aeims.app)
2. `sites/flirts.nyc/auth.php`
3. `sites/nycflirts.com/auth.php`
4. `agents/login.php`

**Changes:**
```php
// OLD:
$data = json_decode(file_get_contents('data/customers.json'), true);

// NEW:
$dataLayer = new DataLayer();
$customer = $dataLayer->getCustomer($username);
```

**Time Estimate:** 2-3 hours
**Risk:** Low (DataLayer handles fallback)

---

### **PHASE 2C: Customer Features**
**Update:**
1. Search operators
2. Send messages
3. Chat rooms
4. Content marketplace
5. Favorites
6. Activity log

**Time Estimate:** 6-8 hours
**Risk:** Medium (many files, but similar patterns)

---

### **PHASE 2D: Operator Features**
**Update:**
1. Operator dashboard
2. Profile management
3. Earnings tracking
4. Content uploads
5. Message customers

**Time Estimate:** 4-6 hours
**Risk:** Medium

---

### **PHASE 2E: ID Verification System (NEW)**
**Build:**
1. ID verification form
2. Document upload handling
3. SSN collection (encrypted)
4. Recovery phrase system
5. Admin review interface
6. Feature gating logic

**Time Estimate:** 8-10 hours
**Risk:** Medium (new feature, security critical)

---

### **PHASE 2F: Admin Tools**
**Update:**
1. Admin dashboard
2. User management
3. Site management
4. Analytics
5. ID verification review

**Time Estimate:** 4-6 hours
**Risk:** Low

---

## 📊 **REALISTIC TIMELINE**

| Phase | Work | Hours | Risk |
|-------|------|-------|------|
| 2A | DataLayer | 4-6h | Low |
| 2B | Auth Migration | 2-3h | Low |
| 2C | Customer Features | 6-8h | Medium |
| 2D | Operator Features | 4-6h | Medium |
| 2E | ID Verification (NEW) | 8-10h | Medium |
| 2F | Admin Tools | 4-6h | Low |
| Testing | Full integration | 4-6h | - |
| **TOTAL** | **Complete Migration** | **32-45h** | **Medium** |

**Realistic**: 4-6 full working days for complete migration

---

## 🚀 **PRIORITIZED APPROACH**

### **Option 1: MVP (Fastest)**
**Goal:** Get basic features working with PostgreSQL

**Build Order:**
1. ✅ Phase 1 - DatabaseManager (DONE)
2. Phase 2A - DataLayer (4-6h)
3. Phase 2B - Auth Migration (2-3h)
4. Phase 2C - Customer Features (6-8h)
5. Deploy & Test

**Time:** ~12-17 hours (2 days)
**Features:** Login, signup, basic chat, search
**Skip:** ID verification, advanced features, admin tools

---

### **Option 2: Complete (Thorough)**
**Goal:** Full migration with all features

**Build Order:**
1. ✅ Phase 1 - DatabaseManager (DONE)
2. Phase 2A - DataLayer (4-6h)
3. Phase 2B - Auth Migration (2-3h)
4. Phase 2C - Customer Features (6-8h)
5. Phase 2D - Operator Features (4-6h)
6. Phase 2E - ID Verification (8-10h)
7. Phase 2F - Admin Tools (4-6h)
8. Deploy & Test

**Time:** ~32-45 hours (4-6 days)
**Features:** Everything, production-ready
**Skip:** Nothing

---

### **Option 3: Incremental (Safest)**
**Goal:** Migrate one feature at a time

**Week 1:** DataLayer + Auth
**Week 2:** Customer features
**Week 3:** Operator features
**Week 4:** ID verification
**Week 5:** Admin tools

**Time:** 5 weeks, 1-2h per day
**Features:** Gradual rollout
**Risk:** Lowest

---

## 🎯 **ID VERIFICATION DETAILS**

### **Step 1: Operator Signup (Light Data)**
**Collect:**
- ✅ Username (referral ID from another operator)
- ✅ Email address
- ✅ First name
- ✅ Middle initial
- ✅ Last name
- ✅ Password

**Store In:** `operators` table
**Status:** `verified = false`

---

### **Step 2: Address Collection**
**Collect:**
- Street address
- City
- State
- ZIP code

**Store In:** `operators.metadata` (JSONB)

---

### **Step 3: ID Document Upload**
**Collect:**
- ID front image
- ID back image
- Selfie with ID

**Store In:** `id_verifications` table
**Status:** `pending`

---

### **Step 4: SSN Collection (After Login)**
**Collect:**
- Social Security Number (encrypted)

**Store In:** `operators.metadata` (encrypted with pgcrypto)
**Security:**
```sql
-- Encrypt SSN
UPDATE operators SET metadata = metadata ||
  jsonb_build_object('ssn_encrypted', pgp_sym_encrypt('123-45-6789', 'encryption_key'))
WHERE operator_id = '...';
```

---

### **Step 5: Recovery Phrase**
**Questions List:**
1. "What was your first pet's name?"
2. "What city were you born in?"
3. "What was your mother's maiden name?"
4. "What was the name of your first school?"
5. "What is your favorite movie?"
... (20+ questions)

**Collect:**
- Select 3 questions
- Provide 3 answers

**Store In:** `verification_codes` table
**Security:** Answers hashed with password_hash()

---

### **Step 6: Admin Review**
**Admin Interface:**
```php
// admin/id-verification-review.php
- View submitted documents
- Check ID validity
- Verify age (18+)
- Approve or reject with reason
- Send notification to operator
```

---

### **Step 7: Enable Features**
**After Approval:**
```php
// Set operator as verified
UPDATE operators SET verified = true WHERE operator_id = '...';

// Enable features:
- Add to operator pool
- Enable messaging
- Enable content uploads
- Enable earnings tracking
- Allow site assignments
```

---

## 📝 **WHAT I'LL BUILD NEXT**

**Immediate Next Steps:**
1. **DataLayer.php** (4-6 hours)
   - Single interface for JSON + PostgreSQL
   - Automatic fallback
   - Dual-write support

2. **Update Auth Files** (2-3 hours)
   - Use DataLayer instead of direct file access
   - Test login/signup still works

3. **ID Verification System** (8-10 hours)
   - Operator signup flow
   - Document upload
   - SSN collection
   - Recovery phrase
   - Admin review

---

## ✅ **YOUR DECISION**

**What do you want me to build?**

**A.** MVP (DataLayer + Auth + Customer Features) → 12-17 hours → 2 days
**B.** Complete (Everything) → 32-45 hours → 4-6 days
**C.** Incremental (One feature at a time) → 5 weeks
**D.** Just start with DataLayer → 4-6 hours → today

**I recommend:** Start with **DataLayer** (Option D), then we can tackle the rest systematically.

---

**Ready to start? Which option?** 🚀
