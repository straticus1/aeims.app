# AEIMS Platform API Documentation

## Overview

AEIMS Platform v2.3.0 provides comprehensive RESTful APIs for managing multi-site operations, authentication, content marketplace, messaging, and administrative functions.

## Authentication

All API endpoints require proper authentication:

- **Customer Authentication**: Session-based for site users
- **Operator Authentication**: Token-based for agents and operators
- **Admin Authentication**: Enhanced security for administrative functions

## Core Services

### Messaging API

**Base Path**: `/services/MessagingManager.php`

#### Send Message
```
POST /api/messages/send
```

Parameters:
- `recipient_id`: Target user ID
- `message`: Message content
- `sender_id`: Sender user ID
- `site_id`: Site identifier

#### Get Messages
```
GET /api/messages/get
```

Parameters:
- `user_id`: User ID
- `conversation_id`: Optional conversation filter
- `limit`: Number of messages to retrieve

### Content Marketplace API

**Base Path**: `/services/ContentMarketplaceManager.php`

#### List Content Items
```
GET /api/content/list
```

Parameters:
- `category`: Content category filter
- `site_id`: Site identifier
- `limit`: Number of items to retrieve

#### Purchase Content
```
POST /api/content/purchase
```

Parameters:
- `content_id`: Content item ID
- `user_id`: Purchasing user ID
- `payment_method`: Payment method

### Chat Room API

**Base Path**: `/services/ChatRoomManager.php`

#### Create Room
```
POST /api/rooms/create
```

Parameters:
- `room_name`: Room display name
- `creator_id`: User creating the room
- `site_id`: Site identifier
- `is_private`: Boolean for private rooms

#### Join Room
```
POST /api/rooms/join
```

Parameters:
- `room_id`: Room identifier
- `user_id`: User joining the room

### Notification API

**Base Path**: `/api/notifications/`

#### Send Notification
```
POST /api/notifications/send
```

Parameters:
- `user_id`: Target user ID
- `type`: Notification type
- `message`: Notification content
- `metadata`: Additional data (JSON)

#### Get Notifications
```
GET /api/notifications/get
```

Parameters:
- `user_id`: User ID
- `unread_only`: Boolean for unread filter
- `limit`: Number of notifications

### ID Verification API

**Base Path**: `/services/IDVerificationManager.php`

#### Submit Verification
```
POST /api/verification/submit
```

Parameters:
- `user_id`: User requesting verification
- `document_type`: Type of ID document
- `document_data`: Base64 encoded document

#### Check Status
```
GET /api/verification/status
```

Parameters:
- `user_id`: User ID
- `verification_id`: Verification request ID

## Site-Specific APIs

Each site in the platform has dedicated API endpoints:

### Site Authentication
```
POST /sites/{site_domain}/api/auth/login
POST /sites/{site_domain}/api/auth/logout
GET /sites/{site_domain}/api/auth/status
```

### Site Dashboard
```
GET /sites/{site_domain}/api/dashboard/stats
GET /sites/{site_domain}/api/dashboard/activities
```

## Administrative APIs

### Site Management
```
GET /api/admin/sites/list
POST /api/admin/sites/create
PUT /api/admin/sites/update
DELETE /api/admin/sites/delete
```

### User Management
```
GET /api/admin/users/list
POST /api/admin/users/create
PUT /api/admin/users/update
DELETE /api/admin/users/delete
```

### Operator Management
```
GET /api/admin/operators/list
POST /api/admin/operators/create
PUT /api/admin/operators/update
```

## Response Format

All API responses follow this standard format:

```json
{
  "success": true|false,
  "data": {},
  "message": "Response message",
  "errors": [],
  "timestamp": "ISO 8601 timestamp"
}
```

## Error Codes

- `200` - Success
- `400` - Bad Request
- `401` - Unauthorized
- `403` - Forbidden
- `404` - Not Found
- `429` - Rate Limit Exceeded
- `500` - Internal Server Error

## Rate Limiting

API endpoints have rate limiting:

- Standard endpoints: 100 requests per minute
- Authentication endpoints: 10 requests per minute
- File upload endpoints: 5 requests per minute

## Security

### HTTPS Required

All API endpoints require HTTPS in production environments.

### CSRF Protection

POST, PUT, DELETE requests require CSRF tokens.

### Input Validation

All inputs are validated and sanitized to prevent:
- SQL injection
- XSS attacks
- Path traversal
- File upload vulnerabilities

## SDK and Integration

### JavaScript SDK

```javascript
const aeims = new AEIMSClient({
  baseUrl: 'https://your-domain.com',
  apiKey: 'your-api-key'
});

// Send message
await aeims.messaging.send({
  recipient_id: 'user123',
  message: 'Hello world'
});
```

### PHP SDK

```php
$aeims = new AEIMSClient([
    'base_url' => 'https://your-domain.com',
    'api_key' => 'your-api-key'
]);

// Get notifications
$notifications = $aeims->notifications->get([
    'user_id' => 'user123',
    'unread_only' => true
]);
```

## Testing

Use the included test suite for API validation:

```bash
# Run API tests
npm test

# Run specific endpoint tests
npm test -- --grep "messaging"
```

## Support

For API support and integration assistance:
- Email: rjc@afterdarksys.com
- Documentation: Full API reference available
- Response time: Within 24 hours