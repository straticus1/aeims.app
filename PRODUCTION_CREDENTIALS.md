# 🔐 AEIMS Production Credentials

**Deployment:** auth-fixes-20251013
**Updated:** October 13, 2025 09:30 AM EDT
**Status:** ✅ LIVE & TESTED

---

## Admin Access (aeims.app)

### Admin Account
- **Username:** `admin`
- **Password:** `admin123`
- **Email:** admin@aeims.app
- **Login URL:** https://aeims.app/login.php
- **Dashboard:** https://aeims.app/admin-dashboard.php
- **Permissions:** Full admin access

### Demo Customer
- **Username:** `demo@example.com`
- **Password:** `password123`
- **Login URL:** https://aeims.app/login.php
- **Dashboard:** https://aeims.app/dashboard.php

---

## Site Customers

### Flirts.NYC Customers

**FlirtyUser** (flirts.nyc only)
- **Username:** `flirtyuser`
- **Password:** `password123`
- **Email:** user@flirts.nyc
- **Login URL:** https://flirts.nyc/login.php
- **Sites:** flirts.nyc

### NYC Flirts Customers

**NYCUser** (nycflirts.com only)
- **Username:** `nycuser`
- **Password:** `password123`
- **Email:** user@nycflirts.com
- **Login URL:** https://nycflirts.com/login.php
- **Sites:** nycflirts.com

### Cross-Site Users (SSO Enabled)

**CrossUser** (Both sites)
- **Username:** `crossuser`
- **Password:** `password123`
- **Email:** cross@aeims.app
- **Sites:** flirts.nyc, nycflirts.com

**DemoCustomer** (Both sites)
- **Username:** `democustomer`
- **Password:** `password123`
- **Email:** demo@customer.com
- **Sites:** flirts.nyc, nycflirts.com

**NYCFun25**
- **Username:** `nycfun25`
- **Password:** `password123`
- **Email:** nycfun25@nycflirts.com
- **Sites:** nycflirts.com

---

## Operator Accounts

### NYC Flirts Operators (nycflirts.com)

**NYCDiamond** (Premium)
- **Username:** `NYCDiamond`
- **Password:** `diamond2024`
- **Email:** nycdiamond@nycflirts.com
- **Category:** Premium
- **Operator ID:** op_68eb76056f6e9
- **Verification Code:** VERIFY-605A7E1F

**NYCAngel** (Standard)
- **Username:** `NYCAngel`
- **Password:** `angel2024`
- **Email:** nycangel@nycflirts.com
- **Category:** Standard
- **Operator ID:** op_68eb7605a82d3
- **Verification Code:** VERIFY-605E0E19

**NYCGoddess** (Elite)
- **Username:** `NYCGoddess`
- **Password:** `goddess2024`
- **Email:** nycgoddess@nycflirts.com
- **Category:** Elite
- **Operator ID:** op_68eb760666e3d
- **Verification Code:** VERIFY-6069DE80

### Flirts.NYC Operators (flirts.nyc)

**ManhattanQueen** (Elite)
- **Username:** `ManhattanQueen`
- **Password:** `queen2024`
- **Email:** manhattanqueen@flirts.nyc
- **Category:** Elite
- **Operator ID:** op_68eb7605e1318
- **Verification Code:** VERIFY-6062A277

**BrooklynBabe** (Premium)
- **Username:** `BrooklynBabe`
- **Password:** `brooklyn2024`
- **Email:** brooklynbabe@flirts.nyc
- **Category:** Premium
- **Operator ID:** op_68eb76062a6e0
- **Verification Code:** VERIFY-60666973

---

## Test Sites URLs

### Main Platform
- **AEIMS App:** https://aeims.app/
- **Admin Dashboard:** https://aeims.app/admin-dashboard.php
- **Customer Dashboard:** https://aeims.app/dashboard.php

### Customer Sites
- **Flirts NYC:** https://flirts.nyc/
- **NYC Flirts:** https://nycflirts.com/
- **Sexacomms:** https://sexacomms.com/

---

## Features to Test

### Authentication & SSO
- ✅ Admin login (aeims.app)
- ✅ Customer login (site-specific)
- ✅ Cross-site SSO (crossuser, democustomer)
- ✅ Operator login
- ✅ Password verification with bcrypt

### Messaging & Chat
- 📍 Customer-to-Operator messaging
- 📍 Real-time chat functionality
- 📍 Message history
- 📍 Activity logging

### ID Verification
- 📍 ID upload and verification
- 📍 Verification status tracking
- 📍 Age verification

### Operator Features
- 📍 Operator dashboard
- 📍 Earnings tracking
- 📍 Customer management
- 📍 Search operators

---

## Deployment Info

**Docker Image:** `515966511618.dkr.ecr.us-east-1.amazonaws.com/afterdarksys/aeims:auth-fixes-20251013`
**Image Digest:** sha256:170612905274ffe8ba06874b1bb062f47ed24a0ed824c009f0ea758a92049bb2
**Task Definition:** aeims-app:101
**ECS Cluster:** aeims-cluster
**Service:** aeims-service
**Running Tasks:** 3

---

## What Was Fixed

1. **Authentication System**
   - ✅ Fixed invalid bcrypt password hashes
   - ✅ Updated all accounts.json with correct hashes
   - ✅ Updated all customers.json with correct hashes
   - ✅ Fixed double session_start() issue

2. **UI/UX Updates**
   - ✅ Applied dark After Dark theme to all dashboards
   - ✅ Fixed login form auto-fill credentials
   - ✅ Updated console log messages with correct passwords

3. **Infrastructure**
   - ✅ Fixed logger paths (local logs/ directory)
   - ✅ Deployed to production ECS cluster
   - ✅ All sites responding with 200 OK

---

## Quick Test Commands

```bash
# Test admin login
curl -s -L -X POST https://aeims.app/login.php -d "username=admin&password=admin123" -w "\nHTTP:%{http_code}\n"

# Test customer login
curl -s -L -X POST https://aeims.app/login.php -d "username=demo@example.com&password=password123" -w "\nHTTP:%{http_code}\n"

# Test site-specific login
curl -s -L -X POST https://flirts.nyc/auth.php -H "Host: flirts.nyc" -d "action=login&username=flirtyuser&password=password123" -w "\nHTTP:%{http_code}\n"
```

---

**⚠️ SECURITY NOTE:** This file contains production credentials. Do NOT commit to public repositories. Keep secure and delete after testing is complete.
