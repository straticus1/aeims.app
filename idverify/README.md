# ID Verification Microservice
## Government ID Verification Services by After Dark Systems

### Overview
Standalone API-based US state and federal government ID validation service accessible at `idcheck.aeims.app` and `idverify.aeims.app`.

### Features
1. **API Key Management** - Secure token-based authentication
2. **ID Submission** - POST endpoint for captured ID and face photos (base64)
3. **ID Retrieval** - GET endpoint to retrieve verified ID information
4. **Barcode Scanning** - US ID, Driver's License, and Passport barcode validation
5. **Face Matching** - Biometric verification between ID photo and selfie
6. **Fee Structure** - Free now, extensible for future billing

### API Endpoints

#### 1. Home Page
```
GET /
Returns: Restricted access message
```

#### 2. Submit ID for Verification
```
POST /api/verify.php
Parameters:
  - api_key: string (required)
  - captured_id: base64 encoded image (required)
  - captured_face: base64 encoded image (required)
  - id_type: 'drivers_license'|'state_id'|'passport' (optional, auto-detected)

Response:
{
  "success": true,
  "verification_id": "VER-ABC123XYZ",
  "status": "verified|pending|rejected",
  "confidence_score": 95.5
}
```

#### 3. Retrieve Verified ID
```
GET /api/retrieve.php
Parameters:
  - api_key: string (required)
  - verification_id: string (required)

Response:
{
  "success": true,
  "verification_id": "VER-ABC123XYZ",
  "status": "verified",
  "id_data": {
    "first_name": "John",
    "last_name": "Doe",
    "date_of_birth": "1990-01-15",
    "id_number": "DL123456789",
    "state": "NY",
    "expiration_date": "2025-01-15",
    "address": "123 Main St, City, State 12345"
  },
  "face_match": {
    "matched": true,
    "confidence": 98.2
  },
  "verified_at": "2025-10-13 10:30:00"
}
```

#### 4. API Key Management (Admin)
```
POST /admin/keys.php
- Generate new API keys
- Revoke API keys
- View usage statistics
```

### Integration with AEIMS Operator Onboarding

#### Step 1: Link Credit Card (No Charge)
- Operator provides payment method
- No actual charge, just verification

#### Step 2: ID Verification Required
- Operator uploads government-issued ID
- System calls ID verification API
- Returns verification_id

#### Step 3: Access Gate
- Operator CANNOT take calls or send messages until:
  - Credit card linked
  - ID verification = "verified"

### Database Schema

#### api_keys table
```json
{
  "api_key": "ADS_abc123...",
  "name": "AEIMS Production",
  "created_at": "2025-10-13",
  "last_used": "2025-10-13 10:30:00",
  "usage_count": 1523,
  "status": "active|revoked",
  "rate_limit": 1000,
  "rate_period": "hour"
}
```

#### verifications table
```json
{
  "verification_id": "VER-ABC123",
  "api_key": "ADS_abc123...",
  "status": "verified|pending|rejected",
  "id_type": "drivers_license",
  "id_data": {...},
  "face_match_score": 98.2,
  "barcode_data": {...},
  "created_at": "2025-10-13 10:30:00",
  "verified_at": "2025-10-13 10:30:05",
  "ip_address": "1.2.3.4"
}
```

### Barcode Formats Supported

1. **PDF417** - Driver's licenses (most US states)
2. **Code 128** - Some state IDs
3. **QR Code** - Enhanced driver's licenses
4. **MRZ (Machine Readable Zone)** - Passports

### Security Features

1. **Rate Limiting** - Per API key
2. **IP Whitelisting** - Optional per API key
3. **Encryption** - All data encrypted at rest
4. **Auto-deletion** - Biometric data deleted after 90 days
5. **Audit Logging** - All API calls logged
6. **HTTPS Only** - No HTTP access

### File Structure
```
idverify/
├── index.php              # Restricted access landing page
├── api/
│   ├── verify.php         # POST - Submit ID verification
│   ├── retrieve.php       # GET - Retrieve verification
│   └── health.php         # Health check endpoint
├── admin/
│   ├── keys.php          # API key management
│   ├── dashboard.php     # Stats and monitoring
│   └── verifications.php # Review verifications
├── lib/
│   ├── APIKeyManager.php
│   ├── BarcodeScanner.php
│   ├── FaceMatcher.php
│   ├── IDVerifier.php
│   └── RateLimiter.php
├── data/
│   ├── api_keys.json
│   ├── verifications.json
│   └── usage_logs.json
└── README.md
```

### Implementation Priority
1. ✅ Project structure and README
2. ⏳ API Key Management system
3. ⏳ Main index.php with access restriction
4. ⏳ Barcode scanner library
5. ⏳ ID verification endpoint
6. ⏳ Retrieval endpoint
7. ⏳ Operator onboarding integration
8. ⏳ Access gate for messaging/calling

### Testing
```bash
# Submit ID verification
curl -X POST https://idcheck.aeims.app/api/verify.php \
  -H "Content-Type: application/json" \
  -d '{
    "api_key": "ADS_test123",
    "captured_id": "data:image/jpeg;base64,...",
    "captured_face": "data:image/jpeg;base64,..."
  }'

# Retrieve verification
curl "https://idcheck.aeims.app/api/retrieve.php?api_key=ADS_test123&verification_id=VER-ABC123"
```
