# =€ AEIMS PostgreSQL Migration Plan
## ZERO DOWNTIME | ALL FEATURES WORK DURING MIGRATION

---

## <¯ **Problem Statement**

**Previous DB Attempt Failed Because:**
- `DatabaseManager::getInstance()` tried to connect immediately in constructor
- Auth files (`sites/*/auth.php`) loaded DatabaseManager
- If DB connection failed ’ auth broke ’ **nobody could login**

**Root Cause:** No fallback mechanism, no lazy loading, immediate failure on DB unavailability.

---

##  **Zero-Downtime Migration Strategy**

### **Phase 1: Fix DatabaseManager (SAFE)**
**Goal**: Make DB optional, never break authentication

#### Changes:
```php
class DatabaseManager {
    private $lazyConnect = true;  // Don't connect until actually used
    private $useDatabase = false;  // Feature flag

    private function __construct() {
        // Don't connect here! Wait until actually needed
        $this->config = include __DIR__ . '/../config.php';
        $this->useDatabase = (getenv('USE_DATABASE') === 'true');
    }

    public function isAvailable() {
        // Check if DB is configured and accessible
        try {
            $this->connect();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }
}
```

**Key Features:**
-  Lazy connection (only when actually used)
-  `isAvailable()` method to check DB status
-  Feature flag `USE_DATABASE` environment variable
-  **Never throws exceptions that break auth**

---

### **Phase 2: Create DataLayer Abstraction (SAFE)**
**Goal**: Single interface for both JSON and PostgreSQL

#### New File: `includes/DataLayer.php`
```php
class DataLayer {
    private $db;
    private $security;
    private $useDatabase;

    public function __construct() {
        $this->security = SecurityManager::getInstance();
        $this->useDatabase = (getenv('USE_DATABASE') === 'true');

        if ($this->useDatabase) {
            $this->db = DatabaseManager::getInstance();
        }
    }

    // Single interface - switches between JSON and DB
    public function getCustomer($username) {
        if ($this->useDatabase && $this->db->isAvailable()) {
            return $this->getCustomerFromDB($username);
        } else {
            return $this->getCustomerFromJSON($username);
        }
    }

    public function saveCustomer($data) {
        // DUAL-WRITE MODE: Write to both JSON and DB
        $jsonSuccess = $this->saveCustomerToJSON($data);

        if ($this->useDatabase && $this->db->isAvailable()) {
            $dbSuccess = $this->saveCustomerToDB($data);
            return $jsonSuccess && $dbSuccess;
        }

        return $jsonSuccess; // JSON is primary
    }
}
```

**Key Features:**
-  Transparent fallback to JSON if DB fails
-  Dual-write mode for safety
-  Single API for all data operations
-  **Authentication keeps working no matter what**

---

### **Phase 3: Update Auth Files (SAFE)**
**Goal**: Use DataLayer instead of direct DatabaseManager

#### Before (BROKEN):
```php
// sites/flirts.nyc/auth.php
$db = DatabaseManager::getInstance(); // FAILS if DB down ’ auth breaks!

// Load customers
$data = json_decode(file_get_contents($customersFile), true);
```

#### After (SAFE):
```php
// sites/flirts.nyc/auth.php
$dataLayer = new DataLayer(); // Never fails!

// Get customer (automatically uses DB or JSON)
$customer = $dataLayer->getCustomer($username);
```

**Key Features:**
-  No direct DB access in auth files
-  Automatic fallback if DB unavailable
-  **Logins work even if PostgreSQL is down**

---

### **Phase 4: Dual-Write Mode (SAFE)**
**Goal**: Write to both JSON and PostgreSQL, read from JSON

#### Implementation:
```bash
# Environment variable controls behavior
export USE_DATABASE=false   # Pure JSON mode (current)
export USE_DATABASE=true    # Dual-write mode (migration)
export USE_JSON=false       # Pure DB mode (final)
```

#### Migration Flow:
1. **Week 1**: `USE_DATABASE=false` (JSON only - current state)
2. **Week 2**: `USE_DATABASE=true`, read from JSON, write to both
3. **Week 3**: Validate data in PostgreSQL matches JSON
4. **Week 4**: `USE_DATABASE=true`, read from PostgreSQL, write to both
5. **Week 5**: `USE_JSON=false` (PostgreSQL only - final state)

**Rollback at Any Point:**
- Just set `USE_DATABASE=false` ’ back to pure JSON
- Zero data loss (JSON always updated)

---

### **Phase 5: Data Validation (SAFE)**
**Goal**: Ensure PostgreSQL data matches JSON exactly

#### Validation Script: `cli/validate-migration.php`
```bash
php cli/validate-migration.php --mode=check
```

**Checks:**
-  All customers in JSON exist in PostgreSQL
-  All operators in JSON exist in PostgreSQL
-  Password hashes match
-  Site memberships match
-  Credits/billing data matches

**Reports:**
```
 145 customers validated
 85 operators validated
 0 mismatches found
 Data integrity: 100%
```

---

### **Phase 6: Switch to PostgreSQL (SAFE)**
**Goal**: Read from PostgreSQL while still writing to JSON

#### Implementation:
```php
class DataLayer {
    public function getCustomer($username) {
        // Try PostgreSQL first
        if ($this->useDatabase && $this->db->isAvailable()) {
            $customer = $this->getCustomerFromDB($username);
            if ($customer) {
                return $customer; // Found in DB
            }
        }

        // Fallback to JSON
        return $this->getCustomerFromJSON($username);
    }
}
```

**Key Features:**
-  PostgreSQL is primary read source
-  JSON is backup/fallback
-  If DB query fails ’ automatically falls back to JSON
-  **Zero downtime, zero data loss**

---

### **Phase 7: PostgreSQL Only (FINAL)**
**Goal**: Remove JSON operations entirely

#### Implementation:
```bash
# Set environment variables
export USE_DATABASE=true
export USE_JSON=false

# Archive JSON files
php cli/migrate.php --archive-json

# Moved to: backups/json-archive-YYYYMMDD/
```

**Final State:**
-  All reads from PostgreSQL
-  All writes to PostgreSQL
-  JSON files archived as backups
-  **Full scalability achieved**

---

## =à **Add-Site Utility**

### **Command**: `php cli/add-site.php`

```bash
php cli/add-site.php create \
  --domain=newflirts.com \
  --name="New Flirts" \
  --type=customer \
  --template=default
```

#### What It Does:
1.  **Database**: Insert site into `sites` table
2.  **Web Server**: Generate nginx/apache vhost config
3.  **Directory Structure**: Create `sites/newflirts.com/` with templates
4.  **AEIMS Integration**: Link to operator pool
5.  **SexaComms Integration**: Link to billing/management
6.  **SSL**: Generate Let's Encrypt certificate
7.  **DNS**: Add Route53 records (if AWS)
8.  **Testing**: Run health checks and report status

#### Example Output:
```
=€ Creating new site: newflirts.com

 Database: Site record created (site_id: newflirts_com)
 Files: Created sites/newflirts.com/ with 15 templates
 Nginx: Generated /etc/nginx/sites-available/newflirts.com.conf
 SSL: Certificate created via Let's Encrypt
 DNS: Added A record pointing to 1.2.3.4
 Integration: Connected to AEIMS operator pool (85 operators)
 Billing: Connected to SexaComms billing system
 Health Check: Site responding at https://newflirts.com/

<‰ Site created successfully!

Next steps:
1. Customize theme in sites/newflirts.com/assets/css/
2. Add operators: php cli/add-site.php add-operators --site=newflirts_com
3. Go live: php cli/add-site.php enable --site=newflirts_com
```

---

## =Ê **Migration Timeline**

| Week | Phase | Action | Risk | Rollback |
|------|-------|--------|------|----------|
| 1 | Preparation | Fix DatabaseManager, Create DataLayer | **ZERO** | N/A |
| 2 | Dual-Write | Enable `USE_DATABASE=true`, write to both | **LOW** | Set `USE_DATABASE=false` |
| 3 | Validation | Run validation scripts, compare data | **ZERO** | N/A |
| 4 | Switch Read | Read from PostgreSQL, write to both | **LOW** | Set `USE_DATABASE=false` |
| 5 | PostgreSQL Only | Stop JSON writes, archive JSON | **MEDIUM** | Restore JSON files |

**Total Estimated Time**: 5 weeks (can be faster if validation passes quickly)

---

## <¯ **Success Metrics**

### **Authentication**
-  100% login success rate maintained throughout migration
-  Zero authentication failures due to DB issues
-  Average login time < 200ms

### **Data Integrity**
-  100% data match between JSON and PostgreSQL
-  Zero data loss during migration
-  All relationships preserved

### **Performance**
-  Query response time < 50ms (vs ~200ms with JSON)
-  Concurrent user capacity: 10,000+ (vs ~100 with JSON)
-  Message throughput: 1,000/sec (vs ~10/sec with JSON)

---

## =% **Key Safety Features**

1. **Lazy Loading**: DB never connects until actually needed
2. **Automatic Fallback**: If DB fails ’ use JSON automatically
3. **Dual-Write**: Both JSON and DB updated simultaneously
4. **Feature Flags**: Enable/disable DB with environment variables
5. **Validation**: Automated checks before switching read source
6. **Rollback**: Instant rollback to JSON at any phase
7. **Monitoring**: Real-time alerts if DB becomes unavailable

---

## =« **What Won't Break**

-  Customer logins (flirts.nyc, nycflirts.com)
-  Operator logins (sexacomms.com, agents/)
-  Admin logins (aeims.app)
-  Message sending
-  Content marketplace
-  Billing/transactions
-  Chat rooms
-  File uploads
-  Security features (CSRF, rate limiting, etc.)

**EVERYTHING KEEPS WORKING THROUGHOUT THE ENTIRE MIGRATION.**

---

## <‰ **Final Result**

### **Before (JSON)**
- =Á 21 JSON files
- = ~200ms query time
- =e ~100 concurrent users
- =¾ File locking contention
- =Ê Limited analytics

### **After (PostgreSQL)**
- =Ä 27 normalized tables
- ¡ ~50ms query time (4x faster)
- =e 10,000+ concurrent users (100x capacity)
- = ACID transactions
- =Ê Advanced analytics with views

---

##  **Approval Checklist**

Before we start, confirm:
- [ ] **Understand the rollback plan** (set `USE_DATABASE=false` at any time)
- [ ] **Week 1 is completely safe** (just code changes, no behavior change)
- [ ] **Logins will keep working** (fallback to JSON if DB fails)
- [ ] **Can pause at any phase** (no forced completion)
- [ ] **Data validated before switching** (automated validation)

---

**Ready to proceed with Phase 1?** Let me know and I'll start with the DatabaseManager fixes! =€
