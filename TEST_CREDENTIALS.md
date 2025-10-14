# 🔐 AEIMS TEST CREDENTIALS
## Complete Login Information for All Platforms

**Last Updated:** October 14, 2025 - 10:15 AM EST
**Build:** `phase2b-complete`
**Status:** ✅ All systems operational with DataLayer

---

## 🎯 **ADMINISTRATOR ACCESS**

### **Platform:** aeims.app (Admin Dashboard)
**URL:** https://aeims.app/login.php

| Username | Password | Role | Access Level |
|----------|----------|------|--------------|
| `admin` | `admin123` | Administrator | Full platform access |
| `demo@example.com` | `password123` | Customer Admin | Customer portal |

**Features:**
- Admin dashboard
- User management
- Site configuration
- Analytics & statistics
- System settings

---

## 👥 **CUSTOMER ACCESS**

### **Site 1:** flirts.nyc (Customer Portal)
**URL:** https://flirts.nyc/login.php

| Username | Password | Type | Status |
|----------|----------|------|--------|
| `testuser` | `testpass` | Customer | Active |
| `demo_customer` | `demo123` | Customer | Active |

**Features:**
- Search operators
- Send messages
- Video chat
- Content marketplace
- Favorites management

### **Site 2:** nycflirts.com (Customer Portal)
**URL:** https://nycflirts.com/login.php

| Username | Password | Type | Status |
|----------|----------|------|--------|
| `testuser` | `testpass` | Customer | Active |
| `demo_customer` | `demo123` | Customer | Active |

**Features:**
- Operator search
- Messaging system
- Chat rooms
- Content purchases
- Activity tracking

---

## 🎧 **OPERATOR ACCESS**

### **Portal:** sexacomms.com / agents/ (Operator Dashboard)
**URL:** https://sexacomms.com (redirects to agents/login.php)
**Direct:** https://aeims.app/agents/login.php

| Email | Password | Name | Status |
|-------|----------|------|--------|
| `sarah@example.com` | `demo123` | Sarah Jones | Active, Verified |
| `maya@example.com` | `demo456` | Maya Red | Active, Verified |
| `luna@example.com` | `demo789` | Luna Night | Active, Verified |

**Assigned Sites:**
- **Sarah:** beastybitches.com, cavern.love, nycflirts.com
- **Maya:** holyflirts.com, dommecats.com, fantasyflirts.live
- **Luna:** latenite.love, nitetext.com, cavern.love

**Features:**
- Operator dashboard
- Customer messaging
- Earnings tracking
- Profile management
- Content uploads
- Room management

---

## 📝 **QUICK TEST GUIDE**

### **Test 1: Admin Login**
```bash
1. Go to: https://aeims.app/login.php
2. Enter: admin / admin123
3. Expected: Redirect to admin-dashboard.php
4. Verify: Dashboard loads with statistics
```

### **Test 2: Customer Login (Flirts NYC)**
```bash
1. Go to: https://flirts.nyc/login.php
2. Enter: testuser / testpass
3. Expected: Redirect to customer dashboard
4. Verify: Can access search-operators.php
```

### **Test 3: Operator Login (SexaComms)**
```bash
1. Go to: https://sexacomms.com
2. Enter: sarah@example.com / demo123
3. Expected: Redirect to agents/dashboard.php
4. Verify: Operator dashboard loads with earnings
```

### **Test 4: Customer Signup (NYC Flirts)**
```bash
1. Go to: https://nycflirts.com/login.php
2. Click signup
3. Enter: newuser / user@test.com / SecurePass123!
4. Expected: Account created, logged in
5. Verify: Redirected to dashboard
```

---

## 🔒 **PASSWORD REQUIREMENTS**

### **Current Security Policy:**
- **Minimum Length:** 10 characters
- **Complexity:** Must include:
  - At least 1 uppercase letter
  - At least 1 lowercase letter
  - At least 1 number
  - At least 1 special character
- **Not Allowed:** Common passwords, sequential characters

### **Admin Passwords:**
- Stored with `password_hash()` (bcrypt)
- Located in: `data/accounts.json`

### **Customer Passwords:**
- Stored with `password_hash()` (bcrypt)
- Located in: `data/customers.json`

### **Operator Passwords:**
- Stored with `password_hash()` (bcrypt)
- Located in: `agents/data/operators.json`

---

## 🧪 **TESTING SCENARIOS**

### **Authentication Testing**

**✅ Valid Login Tests:**
- Admin with correct credentials → Success
- Customer with correct credentials → Success
- Operator with correct credentials → Success

**✅ Invalid Login Tests:**
- Wrong password → Error message
- Non-existent user → Error message
- Inactive account → Error message
- Too many attempts → Rate limit (5 attempts / 5 minutes)

**✅ Session Tests:**
- Session persists across pages → Success
- Logout clears session → Success
- Session timeout after inactivity → Success

### **Authorization Testing**

**✅ Access Control:**
- Customer cannot access admin pages → Redirect to login
- Operator cannot access customer pages → Redirect to login
- Unauthenticated users blocked from protected pages → Redirect to login

### **Security Testing**

**✅ CSRF Protection:**
- Forms include CSRF tokens → Success
- Invalid CSRF token rejected → Error

**✅ Rate Limiting:**
- 5 failed login attempts → Locked for 5 minutes
- Successful login resets counter → Success

**✅ Session Security:**
- Session fixation protection → New session ID after login
- Secure session cookies → HTTPOnly, Secure, SameSite
- Session timeout → 8 hours for operators, 2 hours for customers

---

## 🗂️ **DATA FILE LOCATIONS**

### **Admin Accounts:**
```
/var/www/html/data/accounts.json
```

### **Customer Accounts:**
```
/var/www/html/data/customers.json
```

### **Operator Accounts:**
```
/var/www/html/agents/data/operators.json
```

### **Session Data:**
```
Redis: tcp://127.0.0.1:6379 (in Docker container)
Fallback: /var/lib/php/sessions
```

---

## 🔧 **ADDING NEW ACCOUNTS**

### **Add Admin Account (via PHP):**
```php
require_once 'includes/SecurityManager.php';
$security = SecurityManager::getInstance();

$accounts = $security->safeJSONRead('data/accounts.json');
$accounts['newadmin'] = [
    'id' => 'admin-' . uniqid(),
    'name' => 'New Administrator',
    'type' => 'admin',
    'password' => password_hash('YourPassword123!', PASSWORD_DEFAULT),
    'email' => 'newadmin@aeims.app',
    'created_at' => date('c'),
    'permissions' => ['all']
];
$security->safeJSONWrite('data/accounts.json', $accounts);
```

### **Add Customer Account (via Signup or PHP):**
```php
require_once 'includes/DataLayer.php';
$dataLayer = getDataLayer();

$dataLayer->saveCustomer([
    'customer_id' => 'cust_' . uniqid(),
    'username' => 'newcustomer',
    'email' => 'customer@example.com',
    'password_hash' => password_hash('SecurePass123!', PASSWORD_DEFAULT),
    'sites' => ['flirts.nyc', 'nycflirts.com'],
    'active' => true,
    'verified' => true,
    'created_at' => date('Y-m-d H:i:s')
]);
```

### **Add Operator Account (via PHP):**
```php
require_once 'includes/DataLayer.php';
$dataLayer = getDataLayer();

$dataLayer->saveOperator([
    'operator_id' => 'op_' . uniqid(),
    'username' => 'newoperator',
    'email' => 'operator@example.com',
    'password_hash' => password_hash('OperatorPass123!', PASSWORD_DEFAULT),
    'name' => 'New Operator',
    'sites' => ['flirts.nyc'],
    'active' => true,
    'verified' => true,
    'status' => 'active',
    'created_at' => date('Y-m-d H:i:s')
]);
```

---

## 🚨 **TROUBLESHOOTING**

### **Login Not Working:**
1. Check credentials exactly (case-sensitive)
2. Clear browser cookies
3. Check Redis is running: `docker exec [container] redis-cli ping`
4. Check session directory permissions: `ls -la /var/lib/php/sessions`
5. Check ECS logs: `aws logs tail /ecs/aeims-app --region us-east-1`

### **"Account Locked" Message:**
- Wait 5 minutes after failed attempts
- OR reset in data file (remove entry from `data/account_locks.json`)

### **Session Timeout:**
- Customers: 2 hours of inactivity
- Operators: 8 hours of inactivity
- Admins: 2 hours of inactivity

### **CSRF Token Error:**
- Clear cookies
- Reload page to get fresh token
- Ensure JavaScript is enabled

---

## 📊 **CURRENT SYSTEM STATUS**

### **✅ Operational Systems:**
- Authentication (all 3 types)
- Session management
- CSRF protection
- Rate limiting
- Password validation
- Account locking
- DataLayer abstraction
- JSON data access (primary)
- PostgreSQL ready (disabled)

### **⏸️ Database Status:**
- PostgreSQL: Installed, schema ready
- Current Mode: Disabled (`USE_DATABASE=false`)
- Data Source: JSON files (primary)
- DataLayer: Ready for dual-write mode
- Migration: Ready when enabled

### **🔐 Security Features:**
- ✅ Session fixation protection
- ✅ CSRF protection
- ✅ Rate limiting (5 attempts / 5 min)
- ✅ Strong password enforcement
- ✅ Account locking
- ✅ Secure session cookies
- ✅ SQL injection prevention (prepared statements)
- ✅ XSS protection (htmlspecialchars)

---

## 📞 **SUPPORT**

**Documentation:**
- Main: `/README.md`
- Phase 2A: `/PHASE2A_DEPLOYMENT_COMPLETE.md`
- Migration: `/database/APPLICATION_UPDATE_PLAN.md`
- Schema: `/database/schema.sql`

**Logs:**
- Application: `/var/log/aeims/`
- Login attempts: `/data/login-attempts.log`
- Operator logins: `/agents/data/operator-login.log`
- ECS: `aws logs tail /ecs/aeims-app --region us-east-1`

**Git Commits:**
- Phase 1: `fe1a08c` (DatabaseManager safety)
- Phase 2A: `aa0b6f9` (DataLayer foundation)
- Phase 2B: `7b47114` (Auth migration)

---

**🎯 Ready to Test!**

All credentials are functional and ready for testing. The system uses JSON files as the primary data source, with PostgreSQL infrastructure ready for future migration.

For production use, change all default passwords immediately!
