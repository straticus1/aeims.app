# AEIMS Security Implementation Plan
## Complete Production Readiness Checklist

**Status**: 🟡 IN PROGRESS
**Target Completion**: Before Production Launch
**Last Updated**: <?php echo date('Y-m-d H:i:s'); ?>

---

## ✅ COMPLETED (Phase 1)

### 1. Core Security Infrastructure
- [x] **SecurityManager.php** - Comprehensive security library
  - Session fixation protection with `session_regenerate_id()`
  - CSRF token generation and validation
  - Directory traversal prevention
  - Open redirect protection
  - Rate limiting system
  - Safe file operations with locking (FIX #5)
  - Password strength validation
  - Security headers management

- [x] **GeoLocationManager.php** - EU user detection & GDPR
  - IP geolocation with multiple fallback services
  - EU member state detection
  - Restricted US state detection (FL, LA, AR, MS, TX, UT, VA, MT)
  - GDPR consent requirements
  - Enhanced age verification triggers
  - Location-based legal requirements

- [x] **agents/profile.php** - Missing operator profile page
  - Full profile management
  - Availability status control
  - Secure password changes
  - CSRF protected forms
  - Tab-based interface

---

## 🔄 IN PROGRESS (Phase 2)

### 2. Database Migration & Management
**Files to create**:
- [ ] `includes/DatabaseManager.php` - PDO wrapper with proper error handling
- [ ] `cli/account-manager.php` - CLI tool for account management
- [ ] `admin/account-management.php` - Web-based account management
- [ ] `database/migrations/` - Database migration scripts
- [ ] `scripts/migrate-json-to-db.php` - Automated JSON → PostgreSQL migration

**Features needed**:
- Connection pooling
- Prepared statements for all queries
- Transaction support
- Automatic reconnection
- Query logging
- Migration rollback support

### 3. Enhanced Legal & Compliance
**Files to create**:
- [ ] `legal/privacy-policy.php` - Detailed GDPR-compliant privacy policy
- [ ] `legal/terms-of-service.php` - Comprehensive TOS
- [ ] `legal/cookie-policy.php` - Cookie usage and consent
- [ ] `legal/gdpr-compliance.php` - EU-specific rights and procedures
- [ ] `components/gdpr-consent-banner.php` - Cookie consent UI
- [ ] `components/age-verification-modal.php` - Enhanced verification for restricted states

**Requirements**:
- Multi-language support (EN, DE, FR, ES, IT)
- EU representative contact info
- Data processing agreements
- Right to be forgotten implementation
- Data portability tools
- Consent management

### 4. Authentication System Overhaul
**Files to update**:
- [ ] `login.php` - Add session regeneration + CSRF
- [ ] `sites/flirts.nyc/auth.php` - Add security fixes
- [ ] `sites/nycflirts.com/auth.php` - Add security fixes
- [ ] `agents/login.php` - Add security fixes
- [ ] `auth_functions.php` - Replace JSON file operations with file locking

**Fixes needed**:
- ✅ Session fixation (call `$security->regenerateSessionOnLogin()`)
- ✅ CSRF protection (add `verify_csrf()` to all POST handlers)
- ✅ Rate limiting (integrate with SecurityManager)
- ✅ Stronger passwords (enforce 10+ chars with complexity)
- File locking for account operations

---

## 📋 PENDING (Phase 3)

### 5. Account Management Tools

#### CLI Tool (`cli/account-manager.php`)
```bash
# Usage examples:
php cli/account-manager.php user:create --username=admin --email=admin@aeims.app --role=admin
php cli/account-manager.php user:list --role=operator
php cli/account-manager.php user:lock --username=badactor
php cli/account-manager.php user:unlock --username=gooduser
php cli/account-manager.php user:delete --username=inactive --confirm
php cli/account-manager.php user:reset-password --username=forgotuser
php cli/account-manager.php migrate:json-to-db --dry-run
```

#### Web Interface (`admin/account-management.php`)
- User listing with search/filter
- Bulk operations
- Account locking/unlocking
- Password reset
- Role management
- Audit log viewer

### 6. File Operations Refactoring

**Files to update** (all using SecurityManager file locking):
- [ ] `auth_functions.php` - Replace file operations
- [ ] `services/MessagingManager.php` - Add file locking
- [ ] `services/ActivityLogger.php` - Add file locking
- [ ] `services/CustomerManager.php` - Add file locking
- [ ] `services/ContentMarketplaceManager.php` - Add file locking
- [ ] All other files using `file_get_contents()` / `file_put_contents()`

**Pattern to follow**:
```php
// OLD (Race condition vulnerable):
$data = json_decode(file_get_contents($file), true);
$data['new_key'] = 'value';
file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));

// NEW (Thread-safe):
$security = SecurityManager::getInstance();
$data = $security->safeJSONRead($file);
$data['new_key'] = 'value';
$security->safeJSONWrite($file, $data);
```

### 7. Router Security Hardening

**File**: `router.php`

**Current issue** (Line 115-123):
```php
if (strpos($agentFile, '..') === false && file_exists($agentFile)) {
    require_once $agentFile;
}
```

**Fixed version**:
```php
$security = SecurityManager::getInstance();
$safePath = $security->validateFilePath($matches[1], __DIR__ . '/agents');
if ($safePath) {
    require_once $safePath;
} else {
    http_response_code(404);
    die('File not found');
}
```

### 8. Security Headers Implementation

**File**: `.htaccess` (add to root)

```apache
# Security Headers
Header always set X-Frame-Options "DENY"
Header always set X-Content-Type-Options "nosniff"
Header always set X-XSS-Protection "1; mode=block"
Header always set Referrer-Policy "strict-origin-when-cross-origin"
Header always set Permissions-Policy "geolocation=(), microphone=(), camera=()"

# Content Security Policy
Header always set Content-Security-Policy "default-src 'self'; script-src 'self' 'unsafe-inline' https://fonts.googleapis.com; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; connect-src 'self'"

# HSTS (only if using HTTPS)
Header always set Strict-Transport-Security "max-age=31536000; includeSubDomains; preload" env=HTTPS
```

### 9. Automated Security Testing

**Files to create**:
- [ ] `tests/SecurityTest.php` - PHPUnit security tests
- [ ] `tests/AuthenticationTest.php` - Auth flow tests
- [ ] `tests/CSRFTest.php` - CSRF protection tests
- [ ] `tests/SessionTest.php` - Session security tests
- [ ] `.github/workflows/security-scan.yml` - CI/CD security scanning

**Test coverage needed**:
- Session fixation prevention
- CSRF token validation
- Directory traversal attempts
- Open redirect attempts
- Rate limiting enforcement
- SQL injection (once database migrated)
- XSS prevention
- File upload validation

---

## 🎯 CRITICAL PATH TO PRODUCTION

### Phase 1: Immediate Security Fixes (DONE ✅)
1. ✅ Create SecurityManager.php
2. ✅ Create GeoLocationManager.php
3. ✅ Fix agents/profile.php

### Phase 2: Authentication & Database (NEXT 48 HOURS)
1. Create DatabaseManager.php
2. Update all login flows with security fixes
3. Create account management CLI tool
4. Implement file locking across all JSON operations

### Phase 3: Legal & Compliance (NEXT WEEK)
1. Enhanced legal policies
2. GDPR consent banner
3. Cookie policy implementation
4. Age verification enhancements

### Phase 4: Testing & Validation (BEFORE LAUNCH)
1. Security test suite
2. Penetration testing
3. Load testing
4. Compliance review

---

## 📊 METRICS & VALIDATION

### Security Checklist
- [ ] All forms have CSRF protection
- [ ] Session IDs regenerated on login
- [ ] Rate limiting on all auth endpoints
- [ ] Password requirements enforced (10+ chars)
- [ ] File operations use locking
- [ ] Security headers set
- [ ] Input validation on all user data
- [ ] Output escaping on all displays
- [ ] Directory traversal prevented
- [ ] Open redirects blocked
- [ ] HTTPS enforced in production
- [ ] Database uses prepared statements
- [ ] Logs sanitized (no passwords/tokens)
- [ ] Error messages don't leak info

### GDPR Compliance Checklist
- [ ] Privacy policy published
- [ ] Cookie consent banner
- [ ] Data processing agreements
- [ ] Right to access implemented
- [ ] Right to be forgotten implemented
- [ ] Data portability tools
- [ ] Breach notification system
- [ ] EU representative appointed
- [ ] DPO designated (if required)

### Production Readiness Checklist
- [ ] All tests passing
- [ ] Security scan clean
- [ ] Database migrations tested
- [ ] Backup & restore tested
- [ ] Monitoring & alerting configured
- [ ] Incident response plan documented
- [ ] Team trained on security procedures

---

## 🚨 KNOWN ISSUES TO FIX

1. **Credential Stuffing Edge Cases**
   - Implement CAPTCHA after 3 failed attempts
   - Add device fingerprinting
   - Implement IP reputation checking
   - Add honeypot fields

2. **Supply Chain Security**
   - Audit all npm dependencies
   - Implement SRI for external resources
   - Self-host Google Fonts (GDPR)
   - Review Composer dependencies

3. **Memory Leaks**
   - Profile JSON file loading performance
   - Implement data pagination
   - Consider Redis caching
   - Monitor memory usage

---

## 📞 SUPPORT & ESCALATION

**Security Issues**: security@aeims.app
**GDPR Requests**: privacy@aeims.app
**Emergency**: emergency@aeims.app

---

*This document is a living guide. Update as work progresses.*
