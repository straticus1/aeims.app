# AEIMS Feature Implementation Plan

## Phase 1: Test Operators Setup ✅ IN PROGRESS
- [ ] Create 10 test operators with full profiles
- [ ] Add physical attributes for each operator
- [ ] Set password to 'flirtyuser' for all operators
- [ ] Add "Picture Coming Soon" placeholder images
- [ ] Configure for flirts.nyc and nycflirts.com

## Phase 2: Private Chat Rooms System
### Database Schema
- `data/chat_rooms.json` - Room definitions
  - room_id, operator_id, room_name, description
  - pin_protected (bool), pin_code
  - entry_fee, per_minute_rate
  - created_at, status (active/inactive)
  - max_users, current_users[]

- `data/room_messages.json` - Room messages
  - message_id, room_id, sender_id, sender_type, content, timestamp

### Features
- [ ] Operators can create private rooms
- [ ] PIN protection for rooms
- [ ] Entry fee system
- [ ] Per-minute billing for room access
- [ ] Multi-user chat in rooms
- [ ] Room search functionality
- [ ] Room discovery page

## Phase 3: Voice/Video Streaming
### Infrastructure Needed
- WebRTC for P2P streaming
- TURN/STUN servers for NAT traversal
- Media server (Janus/Mediasoup) for multi-user rooms

### Features
- [ ] Operator voice streaming in rooms
- [ ] Operator video streaming in rooms
- [ ] Per-stream billing (users pay to stream)
- [ ] "Tip for action" system
- [ ] Stream quality controls

## Phase 4: Pay-for-Call Feature
### Database Schema
- `data/call_requests.json`
  - request_id, customer_id, operator_id
  - requested_at, scheduled_time
  - duration_minutes, total_cost
  - status (pending/accepted/completed/cancelled)
  - customer_number_masked, operator_number_masked

### Features
- [ ] Customer can request operator callback
- [ ] Number masking via Twilio/VoIP provider
- [ ] Call scheduling system
- [ ] Pre-payment for call duration
- [ ] Call history and recordings

## Phase 5: VoIP Number Purchase
### Features
- [ ] Operator can purchase dedicated number
- [ ] Monthly fee billing
- [ ] Number management panel
- [ ] Per-minute rates for dedicated number
- [ ] DID provisioning via Twilio/Bandwidth

## Phase 6: Toast Notification System
### Infrastructure
- Server-Sent Events (SSE) or WebSocket for real-time
- `data/notifications.json` for notification queue

### Notification Types
- [ ] New chat message (blue toast)
- [ ] Chat room invitation (purple toast)
- [ ] New mail (green toast)
- [ ] Message sent confirmation (yellow toast)

### Features
- [ ] Real-time toast notifications
- [ ] Click-to-navigate
- [ ] Notification settings panel
- [ ] Enable/disable by type
- [ ] Sound alerts (optional)

## Implementation Priority
1. **Phase 1** - Test operators (IMMEDIATE)
2. **Phase 6** - Notifications (HIGH - enhances UX)
3. **Phase 2** - Chat rooms (HIGH - core feature)
4. **Phase 4** - Pay-for-call (MEDIUM - revenue generator)
5. **Phase 5** - VoIP numbers (MEDIUM - depends on Phase 4)
6. **Phase 3** - Streaming (LOW - complex infrastructure)

## Technology Stack Decisions
- **Real-time**: Server-Sent Events (simpler than WebSocket)
- **VoIP**: Twilio API (proven, reliable)
- **Streaming**: Defer to Phase 3 (needs dedicated media server)
- **Payments**: Existing credit system
- **File Storage**: Continue with JSON (migrate to DB later)

## Estimated Development Time
- Phase 1: 30 minutes
- Phase 6: 2 hours
- Phase 2: 3 hours
- Phase 4: 2 hours
- Phase 5: 2 hours
- Phase 3: 8+ hours (complex)

**TOTAL: ~18 hours for Phases 1,2,4,5,6**
**Phase 3 (streaming) deferred pending infrastructure**
