# AEIMS Platform - Product Requirements Document

**Version:** 2.0.0
**Date:** October 13, 2025
**Author:** Ryan Coleman
**Status:** Production-Ready Platform

---

## Table of Contents

1. [Executive Summary](#1-executive-summary)
2. [System Architecture Overview](#2-system-architecture-overview)
3. [Current Features by Project](#3-current-features-by-project)
4. [Technology Stack](#4-technology-stack)
5. [Integration Architecture](#5-integration-architecture)
6. [Security & Billing State Machine Requirements](#6-security--billing-state-machine-requirements)
7. [Feature Gaps & Required Enhancements](#7-feature-gaps--required-enhancements)
8. [Implementation Roadmap](#8-implementation-roadmap)
9. [Technical Debt & Risks](#9-technical-debt--risks)
10. [Recommendations](#10-recommendations)

---

## 1. Executive Summary

### 1.1 Platform Overview

**AEIMS (Adult Entertainment Interactive Management System)** is a comprehensive, production-grade platform for managing adult entertainment services with integrated telephony, device control, multi-site management, and billing capabilities. The platform is built by After Dark Systems and represents the world's ONLY adult entertainment platform with comprehensive device control integration.

### 1.2 Business Model

AEIMS operates as a **white-label licensing platform** with multiple revenue streams:

1. **Per-User Licensing**: $0.99-$2.99 per user/month depending on tier
2. **Per-Domain Licensing**: $99-$299 per domain/month
3. **Enterprise Solutions**: Custom pricing for large-scale deployments
4. **Transaction Revenue**: Per-minute billing for calls, messages, and video streams
5. **Operator Revenue Split**: 80/20 operator/platform revenue sharing model

### 1.3 Key Differentiators

1. **Revolutionary Device Control**: Integration with 15+ major brands (Lovense, WeVibe, Kiiroo, Magic Motion, etc.) through aeimsLib - NO OTHER platform offers this
2. **Cross-Site Operator Support**: Single operator can work across multiple sites simultaneously, maximizing earnings
3. **100% Discrete Billing**: All transactions appear discretely on customer statements
4. **Anonymous Protection**: Both operators and customers are fully anonymized
5. **Enterprise-Grade Infrastructure**: AWS-based with auto-scaling, multi-AZ deployment, comprehensive compliance monitoring
6. **Microservices Architecture**: 12+ independent services for scalability and resilience

### 1.4 Target Market

- **Primary**: Adult entertainment site operators seeking turnkey platform solutions
- **Secondary**: Individual operators/performers needing multi-site management tools
- **Tertiary**: Enterprise networks requiring white-label adult content platforms

### 1.5 Current Deployment Status

- **Production Sites**: 3 active (nycflirts.com, flirts.nyc, sexacomms.com)
- **Total Operators**: 85+ cross-site operators
- **Platform Uptime**: 99.9%
- **Daily Metrics**: 1,247 calls, 3,856 messages, $12,458 revenue

---

## 2. System Architecture Overview

### 2.1 High-Level Architecture

```
                            AWS Cloud Infrastructure
                                        │
                    ┌───────────────────┼───────────────────┐
                    │                   │                   │
              ┌─────▼─────┐      ┌─────▼─────┐      ┌─────▼─────┐
              │   AEIMS   │      │   AEIMS   │      │  AEIMS    │
              │    App    │◄────►│   Core    │◄────►│   Lib     │
              │ (Web/CMS) │      │(Telephony)│      │ (Devices) │
              └───────────┘      └───────────┘      └───────────┘
                    │                   │                   │
                    └───────────────────┼───────────────────┘
                                        │
                    ┌───────────────────┼───────────────────┐
                    │                   │                   │
              ┌─────▼─────┐      ┌─────▼─────┐      ┌─────▼─────┐
              │PostgreSQL │      │   Redis   │      │   MySQL   │
              │  (Core)   │      │  Cluster  │      │   (App)   │
              └───────────┘      └───────────┘      └───────────┘
```

### 2.2 Five-Project Ecosystem

#### Project 1: aeims.app (Marketing & Multi-Site CMS)
**Location**: `/Users/ryan/development/aeims.app`
**Purpose**: Marketing website, multi-site CMS, operator portal, admin dashboard
**Tech Stack**: PHP 8.2+, MySQL 8.0, Nginx, JSON file storage
**Key Features**: Virtual host routing, SSO management, discrete billing, multi-domain support

#### Project 2: aeimsLib (Device Control Library)
**Location**: `/Users/ryan/development/aeimslib`
**Purpose**: Device control and WebSocket communication library
**Tech Stack**: TypeScript/Node.js, Express, WebSocket (ws), Redis, Buttplug.io
**Key Features**: 15+ device brand support, VR/XR integration, AI pattern generation

#### Project 3: aeims-control (Infrastructure Management)
**Location**: `/Users/ryan/development/aeims-control`
**Purpose**: Infrastructure as Code for AWS deployment
**Tech Stack**: Terraform, Ansible, Docker Compose, AWS ECS/Fargate
**Key Features**: Multi-environment deployment, compliance monitoring, auto-scaling

#### Project 4: aeims-asterisk (VoIP Telephony)
**Location**: `/Users/ryan/development/aeims-asterisk`
**Purpose**: Asterisk-based VoIP server with AEIMS integration
**Tech Stack**: Asterisk 20 LTS, Python, ARI, AMI, DynamoDB
**Key Features**: Per-minute billing, call recording, CDR management, operator revenue split

#### Project 5: SuperDeploy (Unified Deployment System)
**Location**: `/Users/ryan/development/SuperDeploy`
**Purpose**: Centralized deployment orchestration across all projects
**Tech Stack**: Bash scripting, Terraform integration, Ansible integration
**Key Features**: Smart deployment detection, multi-project management, lifecycle control

### 2.3 Production Service Architecture

```
                        🌐 AWS Application Load Balancer
                            (Multi-Domain SSL)
                    aeims.app | sexacomms.com | nycflirts.com | flirts.nyc
                                       │
                                       ▼
                             ┌─────────────────┐
                             │   Nginx Proxy   │
                             │   (8085/8445)   │
                             │ • SSL Termination
                             │ • Rate Limiting │
                             │ • CORS Headers  │
                             └─────────────────┘
                                       │
              ┌────────────────────────┼────────────────────────┐
              ▼                        ▼                        ▼
    ┌─────────────────┐      ┌─────────────────┐      ┌─────────────────┐
    │   AEIMS Core    │      │    AEIMS App    │      │   AEIMS Lib     │
    │    (8000)       │      │     (81/443)    │      │    (8081 WS)    │
    │                 │      │                 │      │                 │
    │ • Django API    │      │ • Marketing     │      │ • Buttplug.io   │
    │ • 12 Services   │      │ • Admin Portal  │      │ • WebSocket     │
    │ • PostgreSQL    │◄────►│ • MySQL         │◄────►│ • Device Ctrl   │
    │ • Auth/OAuth    │      │ • CMS           │      │ • VR/AR Support │
    └─────────────────┘      └─────────────────┘      └─────────────────┘
              │                        │                        │
              └────────────────────────┼────────────────────────┘
                                       ▼
                          ┌─────────────────────────┐
                          │    Redis Cluster        │
                          │     (6379)              │
                          │ • Session Storage       │
                          │ • Rate Limiting         │
                          │ • WebSocket State       │
                          │ • Cache Layer           │
                          └─────────────────────────┘

📊 Microservices (PHP/Python):
   User(8001) | Billing(8002) | Telephony(8003) | Call(8004) |
   Operator(8005) | Session(8006) | Analytics(8007) | Admin(8008) |
   Content(8009) | File(8010) | Marketing(8011) | Verification(8012)

🗄️ Persistent Storage:
   EFS Sites | EFS Data | EFS Nginx Config | RDS PostgreSQL | RDS MySQL

🔍 Monitoring Stack:
   CloudWatch | Prometheus(9090) | Grafana(3001) | ELK Stack
```

### 2.4 Network Topology

- **VPC**: 10.0.0.0/16 (afterdarksys-vpc: vpc-0c1b813880b3982a5)
- **Public Subnets**: 2x /24 subnets for ALB and NAT gateways
- **Private Subnets**: 2x /24 subnets (10.0.30.0/24, 10.0.31.0/24) for ECS tasks
- **Database Subnets**: 2x /24 subnets for RDS instances
- **Multi-AZ Deployment**: us-east-1a and us-east-1b for high availability

---

## 3. Current Features by Project

### 3.1 aeims.app (Marketing & Multi-Site CMS)

#### Core Features
1. **Marketing Website**
   - Responsive design with dark theme
   - Animated statistics counters
   - Device showcase highlighting 15+ supported brands
   - Pricing models (per-user, per-domain, enterprise)
   - Contact form with rate limiting and validation

2. **Multi-Site Management**
   - Virtual host routing for multiple domains
   - Site-specific authentication and SSO
   - Cross-site operator support
   - JSON-based site configuration
   - Dynamic content management per site

3. **Operator Portal (sexacomms.com)**
   - Secure operator login/authentication
   - Operator dashboard with earnings tracking
   - Multi-site operator assignments
   - Real-time activity logging
   - Message management interface

4. **Customer Sites (flirts.nyc, nycflirts.com)**
   - Customer authentication with session management
   - Operator search and filtering by attributes
   - Favorites system
   - Messaging system with real-time notifications
   - Activity tracking and logging
   - Profile management

5. **Admin Dashboard (admin.aeims.app)**
   - System health monitoring
   - Operator verification and ID management
   - Domain management
   - Site configuration
   - Analytics and reporting

6. **Services Layer**
   - ActivityLogger: Comprehensive activity tracking
   - BalanceMonitorService: Real-time balance checking
   - ChatRoomManager: Private room management (NEW)
   - CustomerManager: Customer lifecycle management
   - DomainManager: Multi-domain orchestration
   - IDVerificationManager: Identity verification processing
   - MessagingManager: Message routing and delivery
   - MultisiteManager: Cross-site coordination
   - NginxManager: Dynamic nginx configuration
   - NotificationManager: Toast notification system (NEW)
   - OperatorManager: Operator account management
   - PaymentManager: Payment processing integration
   - PaymentProcessorService: Transaction processing
   - SiteManager: Site lifecycle management
   - SSLManager: SSL certificate management
   - SSOManager: Single sign-on coordination
   - ToyManager: Device control integration

7. **Age Verification & Compliance**
   - ID scanning and verification
   - Barcode/QR code processing
   - Face matching capabilities
   - 18+ age verification banner
   - Cookie consent management
   - Legal documentation and terms

8. **Security Features**
   - JWT-based authentication
   - Session timeout handling
   - CSRF protection
   - XSS prevention
   - SQL injection prevention
   - Rate limiting per IP
   - Audit trail logging

#### Data Storage (JSON-based)
- `data/accounts.json` - Operator accounts
- `data/customers.json` - Customer accounts
- `data/operators.json` - Operator profiles
- `data/messages.json` - Message history
- `data/conversations.json` - Conversation threads
- `data/customer_activity.json` - Activity logs
- `data/sites.json` - Site configurations
- `data/chat_rooms.json` - Private chat rooms (NEW)
- `data/notifications.json` - Notification queue (NEW)
- `data/verification_codes.json` - Auth codes
- `data/id_verifications.json` - ID verification records

### 3.2 aeimsLib (Device Control Library)

#### Device Support (15+ Brands)
1. **Primary Devices** (Production-Ready)
   - Lovense (full protocol support)
   - WeVibe/WowTech
   - Kiiroo
   - Magic Motion
   - Generic BLE devices

2. **Experimental Devices** (Beta)
   - Svakom
   - Vorze
   - XInput/DirectInput Gamepads
   - Handy/Stroker
   - OSR/OpenSexRouter
   - MaxPro/Max2
   - PiShock (Electrostimulation)
   - TCode Protocol Devices
   - Bluetooth TENS Units
   - Vibease
   - Satisfyer Connect
   - Hicoo/Hi-Link
   - LoveLife Krush/Apex

#### Protocol Support
- Bluetooth LE (BLE)
- Buttplug.io protocol
- WebSocket control
- TCode protocol
- OSR (OpenSexRouter)

#### Advanced Features
1. **XR/VR Integration**
   - Device synchronization with VR content
   - Haptic feedback system
   - 3D spatial control
   - Mixed reality support
   - Real-time sync capabilities

2. **Media Integration**
   - Audio synchronization
   - Video framework integration
   - Beat detection for music
   - Pattern extraction from media

3. **Mesh Networking**
   - Device meshing capabilities
   - Auto-routing between devices
   - Secure topology management
   - Network monitoring

4. **Mobile Support**
   - React Native components
   - iOS BLE optimization
   - Android BLE framework
   - Cross-platform pattern support

5. **Platform Features**
   - Remote control interface
   - Pattern marketplace
   - User profiles and sharing
   - Activity scheduling

6. **AI & Analytics**
   - ML-based pattern generation
   - Usage analytics
   - Anomaly detection
   - Personalized recommendations

7. **Developer Tools**
   - Pattern Designer GUI
   - Pattern Playground
   - Performance Profiler
   - Device Simulator
   - Protocol Analyzer
   - VS Code Extension
   - CLI for device management

#### Pattern System
- Constant intensity
- Wave patterns
- Pulse patterns
- Escalation patterns
- Custom pattern creation
- Pattern library management

#### Security Features
- HTTPS/WSS encryption
- JWT authentication
- OAuth2 with PKCE
- MFA support
- Advanced rate limiting
- Input validation
- Data encryption at rest
- Secure storage
- Challenge-based verification
- Comprehensive audit trails
- Token management
- Automatic blocking for abuse
- Event archival

#### Performance Optimizations
- Connection pooling
- Message batching
- Compression (zlib)
- State caching
- Auto-scaling connection pools
- Health monitoring
- Performance tracking

#### Monitoring
- Real-time metrics
- Health checks
- Performance monitoring
- Alert system
- Prometheus integration
- CloudWatch integration

### 3.3 aeims-control (Infrastructure Management)

#### Infrastructure as Code
1. **Terraform Resources**
   - VPC and networking (public, private, database subnets)
   - ECS cluster (Fargate-based)
   - RDS databases (PostgreSQL for Core, MySQL for App)
   - ElastiCache (Redis clusters for all services)
   - Application Load Balancer (multi-domain SSL)
   - Auto-scaling policies (CPU/Memory-based)
   - CloudWatch logging and metrics
   - S3 buckets (asset storage, backups)
   - IAM roles (least-privilege policies)
   - EFS file systems (persistent storage)

2. **Multi-Environment Support**
   - Development (local Docker Compose)
   - Staging (AWS ECS with reduced capacity)
   - Production (multi-AZ, auto-scaling, WAF)

3. **Compliance Monitoring**
   - **FOSTA-SESTA**: Anti-trafficking monitoring, interstate commerce tracking
   - **Florida Compliance**: Age verification, content filtering, geolocation controls
   - **GDPR**: Privacy rights management, data protection
   - **NY SHIELD Act**: Breach detection and notification
   - **Law Enforcement Reporting**: Automated suspicious activity reports

4. **Security Infrastructure**
   - WAF (Web Application Firewall) protection
   - KMS encryption for databases and storage
   - Comprehensive audit logging
   - Real-time threat detection
   - Network policies and security groups
   - SSL certificate management

5. **Monitoring & Observability**
   - CloudWatch (logs, metrics, alarms)
   - Prometheus (metrics collection)
   - Grafana (visualization dashboards)
   - ELK Stack (log aggregation and analysis)
   - Health check endpoints for all services
   - Performance metrics and alerting

6. **Testing Framework**
   - Playwright-based end-to-end testing
   - 245+ automated tests across 5 browsers
   - Multi-viewport testing (desktop, tablet, mobile)
   - Network traffic analysis
   - Accessibility (WCAG) testing
   - Security penetration testing

7. **Deployment Capabilities**
   - SuperDeploy integration
   - Plan-only mode (dry run)
   - Infrastructure-only deployment
   - Application-only deployment
   - Auto-approve mode
   - Verbose logging
   - Rollback capabilities

8. **Ansible Configuration Management**
   - Container build automation
   - Service orchestration
   - Configuration management
   - Secrets management
   - Health verification
   - Deployment playbooks

9. **Backup & Disaster Recovery**
   - Automated RDS backups (7-day retention)
   - S3 versioning and lifecycle policies
   - EFS snapshots
   - Multi-AZ database failover
   - Point-in-time recovery
   - Disaster recovery procedures

### 3.4 aeims-asterisk (VoIP Telephony)

#### Core Telephony Features
1. **Asterisk 20 LTS**
   - PJSIP support for SIP trunking
   - ARI (Asterisk REST Interface) for call control
   - AMI (Asterisk Manager Interface) for monitoring
   - Dialplan management
   - Call routing and forwarding
   - Conference calling capabilities

2. **Microservices Architecture**
   - **aeims-asterisk-adapter** (Port 8080)
     - Enhanced REST API with authentication
     - Monitoring and health checks
     - CDR (Call Detail Records) management
     - Call origination and control
     - Channel management
   - **aeims-billing** (Port 8090)
     - Advanced billing with AEIMS integration
     - Operator revenue split (80/20)
     - Fraud detection
     - Balance checking
     - Per-minute rate calculation

3. **Enterprise Features**
   - API key authentication
   - JWT support
   - Rate limiting
   - Call Detail Records (DynamoDB + S3)
   - Automatic call recording with S3 upload
   - Pre-call balance validation
   - Real-time event publishing via WebSocket
   - Circuit breakers for resilience
   - Auto-reconnection logic
   - Structured logging

4. **Billing Integration**
   - Per-minute billing (rounds up to next minute)
   - Operator revenue split: 80% operator, 20% platform
   - Real-time balance checking before call
   - Integration with AEIMS billing service
   - Call event tracking (started, ended)
   - Fraud detection and prevention

5. **Call Recording & Storage**
   - Automatic recording of all calls
   - S3 storage with lifecycle policies
   - Metadata stored in DynamoDB
   - Playback API endpoints
   - Retention policy management

6. **Monitoring & Metrics**
   - Prometheus metrics export
   - CloudWatch integration
   - Health check endpoints
   - System information API
   - Channel statistics
   - Call quality metrics

7. **Network Integration**
   - Runs in AEIMS VPC private subnets
   - Direct internal communication with AEIMS services
   - ECS service discovery
   - Load balancer integration
   - Security group isolation

8. **Cost Management**
   - Start/stop scripts for development
   - Service scaling to zero
   - Cost optimization recommendations
   - Resource utilization tracking

### 3.5 SuperDeploy (Unified Deployment System)

#### Core Capabilities
1. **Smart Deployment Detection**
   - Auto-detects custom deployment scripts
   - Fallback to standard Terraform/Ansible
   - Project structure analysis
   - Deployment system priority logic

2. **Multi-Project Management**
   - Centralized deployment interface
   - Project lifecycle control (deploy, teardown, refresh)
   - Build list management (add/remove projects)
   - Status checking and reporting

3. **Deployment Modes**
   - Plan-only (dry run)
   - Infrastructure-only
   - Application-only
   - Auto-approve
   - Verbose output

4. **Supported Patterns**
   - Custom deployment scripts (Python, Bash, JavaScript)
   - Terraform-based infrastructure
   - Ansible playbooks
   - Docker Compose
   - Hybrid approaches

5. **Template System**
   - Pre-built deployment templates
   - Template installation to projects
   - Custom template creation
   - Language-specific optimizations (Python, Node.js, Bash)

6. **Logging & Reporting**
   - Timestamped log entries
   - Daily log rotation
   - Deployment status tracking
   - Error handling and reporting
   - Prerequisites checking

7. **Integration**
   - Compatible with all AEIMS projects
   - Terraform state management
   - Ansible inventory generation
   - Docker registry integration
   - AWS CLI integration

---

## 4. Technology Stack

### 4.1 Frontend Technologies

#### Web Applications
- **HTML5**: Semantic markup, accessibility features
- **CSS3**: Custom styles, Grid, Flexbox, animations
- **JavaScript (ES6+)**: Modern browser features, async/await
- **Fonts**: Inter font family from Google Fonts
- **Icons**: Unicode emoji icons

#### Frameworks & Libraries (Planned)
- **React**: For interactive dashboards and real-time features
- **Vue.js**: Alternative for simpler components
- **WebSocket Client**: Real-time bidirectional communication
- **WebRTC**: P2P video/audio streaming

### 4.2 Backend Technologies

#### Primary Languages
- **PHP 8.2+**: Main application logic for aeims.app
- **Python 3.9+**: Microservices, Lambda functions, Asterisk integration
- **Node.js 18+**: aeimsLib WebSocket server, real-time services
- **TypeScript**: Type-safe development for aeimsLib

#### Frameworks
- **Django**: Planned for AEIMS Core REST API
- **Express.js**: aeimsLib HTTP and WebSocket server
- **Flask**: Lightweight Python microservices

### 4.3 Databases

#### Relational Databases
- **PostgreSQL 14.19**: AEIMS Core data (user, billing, call records)
- **MySQL 8.0.43**: AEIMS App data (sites, operators, messages)

#### NoSQL & Caching
- **Redis 7**: Session storage, caching, rate limiting, WebSocket state
- **DynamoDB**: CDR storage, call metadata (Asterisk integration)

#### File Storage
- **JSON Files**: Current data storage for aeims.app (migration path to DB)
- **S3**: Object storage for recordings, backups, assets
- **EFS**: Persistent file systems for site content and configurations

### 4.4 Infrastructure & DevOps

#### Cloud Platform
- **AWS**: Primary cloud provider
  - ECS/Fargate: Container orchestration
  - RDS: Managed databases
  - ElastiCache: Managed Redis
  - S3: Object storage
  - EFS: File systems
  - ALB: Load balancing
  - CloudWatch: Logging and monitoring
  - KMS: Encryption key management
  - WAF: Web application firewall
  - Lambda: Serverless functions (compliance monitoring)

#### Infrastructure as Code
- **Terraform 1.5+**: Infrastructure provisioning and management
- **Ansible 9+**: Configuration management and deployment automation

#### Containerization
- **Docker 24+**: Container runtime
- **Docker Compose**: Local development orchestration
- **ECR**: Docker image registry

#### CI/CD (Planned)
- **GitHub Actions**: Automated testing and deployment
- **Ansible**: Deployment automation
- **SuperDeploy**: Unified deployment interface

### 4.5 Telephony & Communication

#### VoIP
- **Asterisk 20 LTS**: PBX and call control
- **PJSIP**: SIP protocol implementation
- **ARI**: Asterisk REST Interface for call control
- **AMI**: Asterisk Manager Interface for monitoring

#### Communication Protocols
- **SIP**: Session Initiation Protocol for VoIP
- **WebRTC**: Real-time communication for browsers
- **WebSocket**: Bidirectional real-time communication
- **REST API**: HTTP-based service communication

#### Providers (Planned Integration)
- **Twilio**: SMS, voice, video APIs
- **Bandwidth**: VoIP DID provisioning
- **Custom SIP Trunks**: Direct carrier integration

### 4.6 Device Control

#### Protocols
- **Bluetooth LE**: Primary device communication
- **Buttplug.io**: Unified device control protocol
- **TCode**: Tactile communication protocol
- **OSR**: OpenSexRouter protocol
- **WebSocket**: Remote device control

#### Libraries
- **aeimsLib**: Custom TypeScript device control library
- **Web Bluetooth API**: Browser-based BLE communication
- **Noble**: Node.js BLE library (backend)

### 4.7 Monitoring & Observability

#### Metrics & Monitoring
- **Prometheus 2.45+**: Metrics collection and storage
- **Grafana 10+**: Visualization and dashboards
- **CloudWatch**: AWS native monitoring and alerting

#### Logging
- **CloudWatch Logs**: Centralized log aggregation
- **ELK Stack**: Elasticsearch, Logstash, Kibana for log analysis
- **Winston**: Structured logging for Node.js
- **Custom PHP Logger**: Application-level logging

#### Testing
- **Playwright**: End-to-end browser testing
- **Jest**: JavaScript unit testing
- **PHPUnit**: PHP unit testing (planned)
- **Pytest**: Python testing (planned)

### 4.8 Security Technologies

#### Authentication & Authorization
- **JWT (JSON Web Tokens)**: Stateless authentication
- **OAuth2**: Third-party authentication (planned)
- **PKCE**: Enhanced OAuth security
- **MFA**: Multi-factor authentication (planned)
- **API Keys**: Service-to-service authentication

#### Encryption
- **SSL/TLS**: Transport layer encryption (Let's Encrypt)
- **AES-256-GCM**: Data encryption at rest
- **KMS**: AWS Key Management Service
- **bcrypt**: Password hashing
- **Argon2**: Modern password hashing (planned migration)

#### Security Tools
- **AWS WAF**: Web application firewall
- **Rate Limiting**: Request throttling (Redis-based)
- **CSRF Protection**: Cross-site request forgery prevention
- **Input Validation**: XSS and SQL injection prevention
- **Audit Logging**: Comprehensive activity tracking

### 4.9 Payment Processing

#### Current Implementation
- **Credit System**: Internal credit management
- **Manual Processing**: Admin-initiated transactions

#### Planned Integrations
- **Stripe**: Credit card processing
- **PayPal**: Alternative payment method
- **Cryptocurrency**: Bitcoin, Ethereum (future consideration)
- **ACH**: Direct bank transfers
- **Paxum**: Adult-industry friendly payment processor

### 4.10 Development Tools

#### Version Control
- **Git**: Source code management
- **GitHub**: Repository hosting (assumed)

#### Code Quality
- **ESLint**: JavaScript linting
- **Prettier**: Code formatting (planned)
- **PHPStan**: PHP static analysis (planned)
- **TypeScript**: Type checking for JavaScript

#### IDEs & Editors
- **VS Code**: Primary development environment
- **VS Code Extension**: Custom aeimsLib extension for device control

#### CLI Tools
- **aeims-config**: Configuration management CLI (aeimsLib)
- **SuperDeploy**: Deployment orchestration CLI
- **AWS CLI**: AWS resource management
- **Docker CLI**: Container management

### 4.11 Documentation

#### Format
- **Markdown**: Primary documentation format
- **README.md**: Project documentation
- **CHANGELOG.md**: Version history
- **API Documentation**: Planned OpenAPI/Swagger specs

#### Tools
- **JSDoc**: JavaScript code documentation (planned)
- **PHPDoc**: PHP code documentation (planned)
- **TypeDoc**: TypeScript documentation generation

---

## 5. Integration Architecture

### 5.1 Inter-Service Communication

#### HTTP/REST Integration
```
aeims.app (PHP) ──REST──> AEIMS Core (Django/Python) ──REST──> Microservices
       │                         │                                   │
       └──REST──> aeimsLib (Node.js) ◄──WebSocket──> Customers
       │                         │
       └──REST──> aeims-asterisk (Python) ──REST──> Billing Service
```

#### Service Endpoints
- **AEIMS Core API**: `/api/*` → aeims-core:8000
- **User Management**: `/users/*` → user-service:8001
- **Billing**: `/billing/*` → billing-service:8002
- **Telephony**: `/telephony/*` → call-service:8003
- **Operator**: `/operator/*` → operator-service:8004
- **Analytics**: `/analytics/*` → analytics-service:8007
- **AEIMS Lib WebSocket**: `/ws/*` → aeims-lib:8081
- **AEIMS App**: `/*` → aeims-app:80 (fallback)

### 5.2 Data Flow Patterns

#### Authentication Flow
```
1. Customer → aeims.app/login
2. aeims.app → Validate credentials (JSON/DB)
3. aeims.app → Create JWT token
4. JWT stored in session + httponly cookie
5. Subsequent requests include JWT for validation
```

#### Messaging Flow
```
1. Customer sends message → aeims.app/messages.php
2. MessagingManager validates session
3. Message stored in data/messages.json
4. NotificationManager triggers toast notification
5. Operator receives notification (SSE/WebSocket planned)
6. Operator responds via operator portal
7. Customer receives notification
```

#### Device Control Flow
```
1. Customer connects → aeimsLib WebSocket (ws://aeims-lib:8081/ws)
2. Customer authenticates with JWT
3. Customer pairs device via Bluetooth LE
4. Operator sends control commands → aeimsLib REST API
5. aeimsLib translates to Buttplug.io protocol
6. Device receives command and responds
7. Device state synchronized via WebSocket
```

#### Call Flow (Asterisk Integration)
```
1. Customer requests call → aeims.app/call-request
2. aeims.app → Check balance (BalanceMonitorService)
3. If balance OK → Create call request
4. aeims-asterisk-adapter → Originate call (ARI)
5. Asterisk → Dial SIP provider → Connect customer
6. Call events → aeims-billing → Calculate charges
7. Per-minute billing → Deduct from balance
8. Call ends → CDR stored (DynamoDB + S3)
9. Operator revenue split calculated (80/20)
10. Earnings updated in operator account
```

### 5.3 Database Integration

#### PostgreSQL (AEIMS Core)
- **Connection**: PostgreSQL 14.19 on RDS (aeims-core-postgres:5432)
- **Schema**: User accounts, billing records, call logs, operator data
- **Access Pattern**: Direct connection from AEIMS Core microservices
- **Migration Path**: Planned migration of aeims.app JSON data

#### MySQL (AEIMS App)
- **Connection**: MySQL 8.0.43 on RDS (aeims-app-mysql:3306)
- **Schema**: Sites, operators, customers, messages, activity logs
- **Access Pattern**: Direct connection from aeims.app PHP
- **Current State**: Prepared but using JSON files for development

#### Redis (All Services)
- **Connection**: Redis 7 on ElastiCache (aeims-redis:6379)
- **Use Cases**:
  - Session storage (PHP sessions, JWT tokens)
  - Rate limiting (request throttling)
  - WebSocket state (connected clients, device states)
  - Cache layer (frequently accessed data)
  - Pub/Sub (real-time notifications - planned)
- **Access Pattern**: All services connect to shared Redis cluster

#### DynamoDB (Asterisk)
- **Use Case**: Call Detail Records (CDRs), call metadata
- **Access Pattern**: aeims-asterisk writes, analytics reads
- **Benefits**: Serverless, scalable, low-latency

#### JSON Files (aeims.app - Current)
- **Location**: `/Users/ryan/development/aeims.app/data/`
- **Files**: accounts.json, customers.json, operators.json, messages.json, etc.
- **Access Pattern**: PHP file I/O with locking
- **Migration Plan**: Move to MySQL/PostgreSQL for production

### 5.4 External Service Integration

#### Cloud Services (AWS)
- **S3**: Asset storage, call recordings, backups
- **CloudWatch**: Centralized logging, metrics, alarms
- **EFS**: Persistent file storage for site content
- **Lambda**: Compliance monitoring functions
- **KMS**: Encryption key management
- **WAF**: Web application firewall rules

#### Future Integrations (Planned)
- **Twilio API**: SMS, voice, video capabilities
- **Stripe API**: Payment processing
- **SendGrid**: Email notifications
- **Cloudflare**: CDN and DDoS protection
- **Sentry**: Error tracking and monitoring

### 5.5 Single Sign-On (SSO) Architecture

#### Current Implementation
- **SSOManager**: Manages cross-site authentication
- **JWT Tokens**: Shared authentication tokens across domains
- **Session Sharing**: Redis-based session storage
- **Domain Cookies**: Secure, httponly cookies per domain

#### SSO Flow
```
1. Operator logs in at sexacomms.com
2. SSOManager creates JWT + session in Redis
3. Operator navigates to nycflirts.com
4. SiteSpecificAuth validates JWT from cookie
5. Session created on nycflirts.com
6. Operator fully authenticated on both sites
```

#### Security Measures
- Secure cookies (httponly, samesite=Lax)
- JWT expiration (2 hours default)
- Session timeout enforcement
- IP-based session validation (optional)
- Audit logging of all SSO events

### 5.6 Multi-Site Routing

#### Virtual Host Configuration
```nginx
server {
    listen 80;
    server_name nycflirts.com www.nycflirts.com;
    root /var/www/aeims.app/sites/nycflirts.com;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}

server {
    listen 80;
    server_name flirts.nyc www.flirts.nyc;
    root /var/www/aeims.app/sites/flirts.nyc;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }
}
```

#### PHP Router Logic
- **index.php**: Detects HTTP_HOST and routes to site-specific files
- **router.php**: Development server routing with virtual host support
- **Site Isolation**: Each site has independent includes and auth

---

## 6. Security & Billing State Machine Requirements

### 6.1 Critical Security Issues (10 Issues from /tmp/other_consideratios.txt)

#### Issue 1: Chat Session Reuse / Infinite Loop Exploit
**Problem**: Users can re-open or continue a "free" or "paid" chat without resetting session IDs, getting unlimited replies under one purchase.

**Fix Requirements**:
- Each operator chat must have unique session ID tied to single payment
- Session expires after N minutes or after message limit
- New message after expiry = new charge event
- Session token must be cryptographically secure (UUID v4)

#### Issue 2: Message Counting & Reply Abuse
**Problem**: If replies aren't strictly counted, users can spam operators or trigger messages before system flags it.

**Fix Requirements**:
- Server must validate each operator response as paid event before sending
- Rate limiter or reply counter per session to prevent "reply flooding"
- Client-side JS cannot be trusted - enforce on backend
- Implement pessimistic locking during billing validation

#### Issue 3: Timing / Latency Exploit
**Problem**: Users might send multiple messages while system hasn't processed charge event.

**Fix Requirements**:
- Lock chat input until billing event confirms
- Implement pessimistic lock (temporary freeze) until billing webhook response
- Queue messages during lock period
- Timeout mechanism if billing doesn't respond (30 seconds max)

#### Issue 4: Multi-Account or Device Abuse
**Problem**: Users can bypass "5 chats per day" by using multiple accounts, VPNs, or clearing cookies.

**Fix Requirements**:
- Track by verified identifier (phone, email, payment ID, or device fingerprint)
- Optional: tie to hashed IP ranges or payment instrument fingerprints
- Implement device fingerprinting (Canvas, WebGL, fonts, etc.)
- Flag suspicious patterns (multiple accounts from same fingerprint)

#### Issue 5: Chargeback or Payment Fraud
**Problem**: Users start chats, receive replies, then reverse payment.

**Fix Requirements**:
- Implement delayed content release - replies held until payment capture confirmed
- Keep metadata logs (timestamps, user ID, reply IDs) for chargeback disputes
- Store forensic data: IP, user agent, device fingerprint
- Automatic fraud scoring based on user history
- Permanent ban for repeated chargebacks

#### Issue 6: Operator Error Exploits
**Problem**: Operators accidentally click "reply" before payment or system flag, giving away content for free.

**Fix Requirements**:
- Require backend validation that chat is "paid/unlocked" before operator UI allows sending
- Add visual indicator ("PAID ✅" vs "LOCKED 🔒") in operator interface
- Disable send button until payment confirmed
- Warning dialog if operator attempts to send to locked chat

#### Issue 7: "Delete and Reopen" Hack
**Problem**: If users can delete or close chats and reopen, they might reset limits or avoid charges.

**Fix Requirements**:
- Deleted chats remain soft-deleted for accounting (120 days minimum)
- Reopening a chat after deletion triggers new session charge
- Conversation history retained even if "deleted" from user view
- User cannot see deleted conversations but system retains for audit

#### Issue 8: Pricing Tier Circumvention
**Problem**: If reply price tiers are dynamic (first reply 99¢, second 50¢), users might close and reopen to always get first-tier pricing.

**Fix Requirements**:
- Maintain rolling chat history token per user/operator pair
- Use history token to price progressively, even across sessions
- Store `conversation_history_id` that persists across sessions
- Progressive pricing tied to conversation, not session

#### Issue 9: Free Trial or Promo Abuse
**Problem**: Users may exploit "first reply free" by creating multiple dummy chats or accounts.

**Fix Requirements**:
- Tie "first reply free" to verified user ID only once
- Audit daily for multiple free chats from same device fingerprint/IP cluster
- Require email or phone verification before free trial
- Machine learning model to detect sock puppet accounts

#### Issue 10: System Crash or Timeout
**Problem**: Users might intentionally disconnect mid-transaction (network loss, browser close) to avoid being billed.

**Fix Requirements**:
- Charge at initiation of chat session, not after completion
- On reconnect, system resumes from last known billing state
- Idempotent billing events (same event ID doesn't charge twice)
- Store billing state in Redis with expiration for recovery

### 6.2 Operator Chat State Machine

#### State Diagram (ASCII)
```
[Idle]
  |
  | create_session (client) --> generates session_token, amount_due
  v
[SessionInitiated] -- initiate_payment --> [PaymentPending]
  | payment confirmed --> [Active]
  | payment failed/timeout --> [FailedPayment] --> retry or close
  v
[Active] -- operator_reply_allowed --> [ReplySent] -- ack_billing --> [Billed]
  | \-- reply_not_allowed --> [Locked]
  | message_limit_reached --> [Expired]
  | user_closes --> [SoftDeleted]
  v
[Locked] -- resolve (billing/pass) --> [Active]
[Expired] -- reopen (new payment) --> [SessionInitiated]
[SoftDeleted] -- reopen? (policy) --> [SessionInitiated or Closed]

Additional states: [Disputed], [ChargebackProcessing], [Closed]
```

#### States and Invariants

**Idle**
- No active session token
- User may request new session
- No billing state

**SessionInitiated**
- Session record exists with `status = initiated`
- `expires_at` set (short TTL: 5-15 minutes)
- `amount_due` calculated and set
- `session_token` returned to client (UUID v4)
- Idempotency key on creation to avoid duplicate sessions

**PaymentPending**
- Payment attempt in progress
- Lock on session prevents operator access until payment capture
- Timeout and retry policy timers set
- Billing webhook expected within 30 seconds

**Active**
- Payment captured (or authorized depending on policy)
- `remaining_replies` counter created/updated
- Operator UI allowed to send replies
- Chat input on client unlocked
- Session active until expiry or message limit

**ReplySent**
- Reply recorded with `reply_id`
- `billed = false` initially until billing ack
- Each reply associated with `billing_event_id` once invoiced

**Billed**
- Payment event marked captured and linked to reply(s) / session
- `billed = true` for reply record
- Operator earnings updated (80/20 split)

**Locked**
- Temporary lock while billing/validation completes
- Rate limiting enforcement
- Operator cannot send during lock
- Customer sees "Processing payment..." message

**Expired**
- TTL or reply cap reached
- Operators cannot send further replies
- Session remains for accounting (soft-deleted) for retention window (120 days)

**SoftDeleted / Closed**
- **SoftDeleted**: User deleted chat but record kept for accounting
- **Closed**: Final state after retention purge or finalization
- All conversation data retained for audit/chargeback

**Disputed / ChargebackProcessing**
- Flagged for manual review
- Replies may be suppressed from public view until resolution
- Forensic logs preserved
- Operator earnings on hold pending resolution

### 6.3 Guard Clauses & Transition Rules

#### create_session (Idle → SessionInitiated)
```
GUARD:
  - Validate user authentication (JWT or session)
  - Enforce per-user daily session cap (backend check)
  - Validate operator availability
  - Check user not banned

ACTION:
  - Generate session_token (UUID v4)
  - Set expires_at (current_time + session_timeout)
  - Calculate amount_due based on pricing tier
  - Store device_fingerprint and payment_instrument_hash
  - Return idempotency key to client
  - Create audit log entry

ROLLBACK:
  - If validation fails, return 403 Forbidden
  - If rate limit exceeded, return 429 Too Many Requests
```

#### initiate_payment (SessionInitiated → PaymentPending)
```
GUARD:
  - Session must exist and status = initiated
  - Session not expired
  - User has valid payment method

ACTION:
  - Lock session: locked_by = billing_service
  - Create payment intent with provider (Stripe/PayPal)
  - Store payment_intent_id in session
  - Prevent duplicate payment attempts (unique payment_intent_id)
  - Set timeout timer (30 seconds)

ROLLBACK:
  - If payment provider fails, transition to FailedPayment
  - If timeout, transition to Expired
```

#### payment_confirmed (PaymentPending → Active)
```
GUARD:
  - Verify payment_status = captured (or authorized)
  - Verify webhook signature (authentic)
  - Check webhook not duplicate (webhook_event_id unique)

ACTION:
  - If capture_on_reply: mark authorized, don't release UI until capture
  - Set remaining_replies based on payment amount
  - Set billing_events and ledger entries atomically
  - Unlock session: locked_by = null
  - Trigger notification to operator (new paid chat)

ROLLBACK:
  - If verification fails, transition to FailedPayment
  - Refund payment if initiated incorrectly
```

#### operator_reply (Active → ReplySent → Billed)
```
GUARD:
  - session.status == Active
  - remaining_replies > 0
  - Rate limit check (max 5 replies/minute per session)
  - Operator authenticated and authorized for this session

ACTION:
  - Create reply record with billed=false
  - If per-reply billing: trigger billing capture for reply
  - Decrement remaining_replies
  - When billing provider returns success: set billed=true
  - Calculate operator earnings (80% of reply cost)
  - Update operator balance
  - Deliver reply to customer (only after billing ack)
  - Trigger notification to customer

ROLLBACK:
  - If billing fails, reply not delivered
  - Increment remaining_replies
  - Delete reply record
  - Notify operator of failed send
```

#### timeout / latency defensive logic
```
GUARD:
  - Check time since payment_initiated < 30 seconds
  - Check webhook received within timeout window

ACTION:
  - Use pessimistic lock on session during billing operations
  - Release lock on final state (Active, FailedPayment, Expired)
  - Implement webhook idempotency (store webhook_event_id)
  - Use webhook signing for verification

ROLLBACK:
  - If timeout: transition to FailedPayment
  - Refund payment if captured but session failed
```

#### session_expiry (Active → Expired)
```
GUARD:
  - current_time > expires_at OR remaining_replies == 0

ACTION:
  - Set status = expired
  - Prevent further operator sends
  - Notify both parties of expiration
  - Archive session data to long-term storage

ROLLBACK:
  - None (irreversible)
```

#### reopen_session (Expired/SoftDeleted → SessionInitiated)
```
GUARD:
  - Reopen policy allows (not too many recent sessions)
  - User has valid payment method
  - Operator still available

ACTION:
  - If reopen allowed: create new session token
  - Require new payment flow
  - Enforce progressive pricing if tiers persist
  - Link to previous conversation via conversation_history_id

ROLLBACK:
  - If policy denies, return 403 Forbidden
```

#### chargeback handling (Any → Disputed)
```
GUARD:
  - Payment provider webhook indicates chargeback

ACTION:
  - Mark payment as disputed
  - Mark all affected replies disputed = true
  - Record forensic logs (IP, device_fingerprint, reply payloads)
  - Hold operator earnings pending resolution
  - Notify admin for manual review
  - Optionally disable user if repeated fraud detected

ROLLBACK:
  - If dispute resolved in platform favor: restore operator earnings
  - If dispute resolved in customer favor: deduct from operator balance
```

### 6.4 Database Schema

#### users table
```sql
CREATE TABLE users (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  email VARCHAR(255) UNIQUE NOT NULL,
  phone_hash VARCHAR(64), -- SHA256 hash of phone number
  verified BOOLEAN DEFAULT FALSE,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_login TIMESTAMP,
  status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'banned', 'suspended')),
  device_fingerprint_hash VARCHAR(64), -- Most recent device fingerprint
  INDEX idx_email (email),
  INDEX idx_phone_hash (phone_hash),
  INDEX idx_status (status)
);
```

#### sessions table
```sql
CREATE TABLE sessions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID NOT NULL REFERENCES users(id),
  operator_id UUID NOT NULL REFERENCES users(id),
  session_token VARCHAR(64) UNIQUE NOT NULL, -- UUID v4
  status VARCHAR(30) NOT NULL CHECK (status IN (
    'initiated', 'payment_pending', 'active', 'locked',
    'expired', 'soft_deleted', 'closed', 'failed_payment',
    'disputed', 'chargeback_processing'
  )),
  amount_due_cents INTEGER NOT NULL,
  currency VARCHAR(3) DEFAULT 'USD',
  remaining_replies INTEGER DEFAULT 0,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  device_fp_hash VARCHAR(64), -- Device fingerprint hash
  payment_instrument_hash VARCHAR(64), -- Payment method hash
  locked_by VARCHAR(50), -- Service holding lock (e.g., 'billing_service')
  parent_session_id UUID REFERENCES sessions(id), -- For session chains
  conversation_history_id UUID, -- Links related conversations for pricing
  INDEX idx_user_id (user_id),
  INDEX idx_operator_id (operator_id),
  INDEX idx_session_token (session_token),
  INDEX idx_status (status),
  INDEX idx_expires_at (expires_at),
  INDEX idx_conversation_history (conversation_history_id)
);
```

#### replies table
```sql
CREATE TABLE replies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id UUID NOT NULL REFERENCES sessions(id),
  operator_id UUID NOT NULL REFERENCES users(id),
  body TEXT NOT NULL,
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  billed BOOLEAN DEFAULT FALSE,
  billing_event_id VARCHAR(64), -- External billing system ID
  delivered BOOLEAN DEFAULT FALSE,
  disputed BOOLEAN DEFAULT FALSE,
  amount_cents INTEGER NOT NULL, -- Cost of this reply
  operator_earnings_cents INTEGER, -- 80% of amount_cents
  INDEX idx_session_id (session_id),
  INDEX idx_operator_id (operator_id),
  INDEX idx_billed (billed),
  INDEX idx_disputed (disputed),
  INDEX idx_created_at (created_at)
);
```

#### payments table
```sql
CREATE TABLE payments (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  session_id UUID NOT NULL REFERENCES sessions(id),
  payment_provider VARCHAR(50) NOT NULL, -- 'stripe', 'paypal', etc.
  provider_id VARCHAR(255) UNIQUE NOT NULL, -- External payment ID
  status VARCHAR(30) NOT NULL CHECK (status IN (
    'pending', 'authorized', 'captured', 'failed', 'refunded', 'disputed'
  )),
  amount_cents INTEGER NOT NULL,
  currency VARCHAR(3) DEFAULT 'USD',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  captured_at TIMESTAMP,
  webhook_event_id VARCHAR(255) UNIQUE, -- For idempotency
  payment_instrument_hash VARCHAR(64), -- For tracking payment method
  INDEX idx_session_id (session_id),
  INDEX idx_provider_id (provider_id),
  INDEX idx_status (status),
  INDEX idx_webhook_event_id (webhook_event_id)
);
```

#### audit_logs table
```sql
CREATE TABLE audit_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  entity_type VARCHAR(50) NOT NULL, -- 'session', 'reply', 'payment', 'user'
  entity_id UUID NOT NULL,
  action VARCHAR(100) NOT NULL, -- 'session_created', 'reply_sent', 'payment_captured', etc.
  actor_id UUID, -- User or system that performed action
  metadata JSONB, -- Additional context
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_entity_type_id (entity_type, entity_id),
  INDEX idx_actor_id (actor_id),
  INDEX idx_created_at (created_at)
);
```

#### device_fingerprints table
```sql
CREATE TABLE device_fingerprints (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  user_id UUID REFERENCES users(id),
  fp_hash VARCHAR(64) UNIQUE NOT NULL, -- SHA256 of fingerprint
  last_seen TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  metadata JSONB, -- Canvas, WebGL, fonts, etc.
  suspicious BOOLEAN DEFAULT FALSE, -- Flagged for multi-account abuse
  INDEX idx_user_id (user_id),
  INDEX idx_fp_hash (fp_hash),
  INDEX idx_suspicious (suspicious)
);
```

#### chargebacks table
```sql
CREATE TABLE chargebacks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  payment_id UUID NOT NULL REFERENCES payments(id),
  status VARCHAR(30) NOT NULL CHECK (status IN (
    'received', 'under_review', 'won', 'lost'
  )),
  reason TEXT,
  provider_reason_code VARCHAR(50),
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  resolved_at TIMESTAMP,
  resolution_notes TEXT,
  INDEX idx_payment_id (payment_id),
  INDEX idx_status (status)
);
```

### 6.5 API Endpoints & Responsibilities

#### POST /sessions
**Purpose**: Create new chat session

**Request**:
```json
{
  "user_id": "uuid",
  "operator_id": "uuid",
  "intent": "chat",
  "metadata": {}
}
```

**Response**:
```json
{
  "session_token": "uuid-v4",
  "amount_due_cents": 99,
  "currency": "USD",
  "expires_at": "2025-10-13T12:00:00Z",
  "remaining_replies": 1
}
```

**Guards**:
- User authenticated (JWT)
- User not banned
- Operator available
- User under daily session limit
- Valid payment method on file

**Actions**:
- Create session record (status: initiated)
- Generate session_token (UUID v4)
- Calculate expires_at (current_time + session_timeout)
- Store device fingerprint
- Return idempotency key

#### POST /payments
**Purpose**: Initiate payment for session

**Request**:
```json
{
  "session_token": "uuid-v4",
  "payment_method_id": "pm_123456"
}
```

**Response**:
```json
{
  "payment_intent_id": "pi_123456",
  "status": "pending",
  "client_secret": "pi_123456_secret_abc"
}
```

**Guards**:
- Session exists and status = initiated
- Session not expired
- Payment method valid

**Actions**:
- Lock session (locked_by: billing_service)
- Create payment intent with provider
- Store payment_intent_id
- Update session status to payment_pending
- Set timeout timer (30 seconds)

#### POST /payments/webhook
**Purpose**: Handle payment provider webhooks (Stripe, PayPal)

**Request** (Stripe example):
```json
{
  "id": "evt_123456",
  "type": "payment_intent.succeeded",
  "data": {
    "object": {
      "id": "pi_123456",
      "amount": 99,
      "status": "succeeded"
    }
  }
}
```

**Response**:
```json
{
  "received": true
}
```

**Security**:
- Validate webhook signature (Stripe: verify header signature)
- Check webhook_event_id not duplicate
- Store webhook_event_id in payments table

**Actions**:
- Find session by payment_intent_id
- Update payment status to captured
- Update session status to active
- Set remaining_replies
- Unlock session (locked_by: null)
- Trigger notification to operator
- Create audit log entry

#### POST /sessions/:id/reply
**Purpose**: Operator sends reply to customer

**Request**:
```json
{
  "body": "Hello! How can I help you?",
  "client_reply_id": "uuid-v4" // For idempotency
}
```

**Response**:
```json
{
  "reply_id": "uuid",
  "billed": true,
  "delivered": true,
  "remaining_replies": 0
}
```

**Guards**:
- Operator authenticated (JWT)
- session.status == active
- remaining_replies > 0
- Rate limit check (5 replies/min per session)
- client_reply_id unique (idempotency)

**Actions**:
- Create reply record (billed: false)
- If per-reply billing: trigger billing capture
- Lock session until billing completes
- On billing success: set billed=true, delivered=true
- Decrement remaining_replies
- Calculate operator earnings (80% of amount)
- Update operator balance
- Trigger customer notification
- Create audit log entry

#### GET /sessions/:id
**Purpose**: Get session status and details

**Response**:
```json
{
  "session_id": "uuid",
  "status": "active",
  "remaining_replies": 2,
  "expires_at": "2025-10-13T12:00:00Z",
  "operator_id": "uuid",
  "amount_due_cents": 99
}
```

**Guards**:
- User or operator authenticated
- User/operator owns session

**Actions**:
- Return session details (redact sensitive billing info)

#### POST /sessions/:id/close
**Purpose**: User-requested session closure

**Response**:
```json
{
  "session_id": "uuid",
  "status": "soft_deleted"
}
```

**Guards**:
- User authenticated and owns session

**Actions**:
- Update session status to soft_deleted
- Conversation data retained for 120 days
- Create audit log entry

#### POST /admin/sessions/:id/resolve-dispute
**Purpose**: Admin manually resolves chargeback dispute

**Request**:
```json
{
  "resolution": "won", // or "lost"
  "notes": "Customer admitted fraud"
}
```

**Response**:
```json
{
  "chargeback_id": "uuid",
  "status": "won",
  "operator_earnings_restored": true
}
```

**Guards**:
- Admin authenticated with admin role

**Actions**:
- Update chargeback status
- If won: restore operator earnings
- If lost: deduct from operator balance (if not already)
- Create audit log entry
- Notify operator of resolution

**Security**:
- All endpoints require JWT or mTLS between services
- CSRF protection for browser flows
- Rate limiting on all endpoints
- Input validation and sanitization

### 6.6 Edge-Case Flows & Decisions

#### Capture vs Authorize
**Recommendation**: Prefer **capturing payment at initiation** to avoid lost revenue.

**Rationale**:
- Authorize-on-reply is more complex (requires retry/capture patterns)
- Risk of authorization expiring before reply sent
- Simpler state machine with upfront capture

**Implementation**:
- Capture full session amount when payment initiated
- No per-reply charges (session includes N replies)
- Refund unused amount if session expires early (optional)

#### Rate Limiting
**Per-Session Rate Limits**:
- 5 replies / 5 minutes per session (prevents reply flooding)
- Enforced in operator_reply guard clause

**Per-User Rate Limits**:
- 30 replies / day per user (prevents abuse)
- 10 new sessions / day per user
- Tracked in Redis with sliding window

**Per-IP Rate Limits**:
- 100 requests / minute per IP (API-wide)
- Enforced at nginx/ALB level

#### First-Reply-Free Promo
**Implementation**:
- Tag user record when promo used: `first_reply_free_used = true`
- Enforce uniqueness per user ID (not per device)
- Require email or phone verification before promo eligibility
- Audit daily for multiple free chats from same device fingerprint

**Fraud Detection**:
- Flag if 5+ free chats from same IP in 24 hours
- Flag if 3+ free chats from same device fingerprint
- Machine learning model to detect sock puppet patterns (future)

#### Reopening / Tier Persistence
**Pricing Tier Persistence**:
- If pricing tiers should persist across sessions between same user/operator:
  - Store `conversation_history_id` (UUID) in sessions table
  - Link all sessions between user/operator pair via conversation_history_id
  - Use conversation_history_id to determine pricing tier

**Example**:
- First session (user A + operator B): $0.99 per reply (Tier 1)
- Second session (user A + operator B): $0.79 per reply (Tier 2)
- Third session (user A + operator B): $0.59 per reply (Tier 3)

#### Soft-Delete Policy
**Retention Period**: 120 days minimum for all chat records

**Rationale**:
- Chargeback disputes can take 90-120 days to resolve
- Legal compliance (GDPR right to erasure has exceptions for legal claims)
- Audit trail for fraud investigation

**Implementation**:
- User deletion sets `status = soft_deleted` but data retained
- Purge job runs daily to hard-delete records older than 120 days
- Hard delete = anonymize PII but keep transaction metadata

#### Forensic Logging
**What to Log** (per reply):
- Request IP address
- User agent string
- Device fingerprint hash
- Timestamp (precise to millisecond)
- Session ID, reply ID, payment ID
- User ID, operator ID

**Storage**:
- Immutable logging recommended (append-only)
- Store in CloudWatch Logs or dedicated audit DB
- Retention: 1 year minimum
- Indexing for fast search during disputes

### 6.7 Implementation Checklist

- [ ] **Database Schema**: Create all tables (users, sessions, replies, payments, audit_logs, device_fingerprints, chargebacks)
- [ ] **State Machine**: Implement state transitions with guard clauses
- [ ] **API Endpoints**: Implement all 7 core endpoints with guards
- [ ] **Payment Integration**: Integrate Stripe or PayPal with webhook handling
- [ ] **Session Management**: Implement session creation, expiry, locking
- [ ] **Reply Management**: Implement reply sending with billing validation
- [ ] **Idempotency**: Ensure webhook and reply idempotency
- [ ] **Rate Limiting**: Implement per-session, per-user, per-IP rate limits
- [ ] **Device Fingerprinting**: Implement fingerprint collection and hashing
- [ ] **Fraud Detection**: Implement basic fraud rules (multi-account, chargeback history)
- [ ] **Audit Logging**: Implement comprehensive audit trail
- [ ] **Chargeback Handling**: Implement dispute workflow and admin interface
- [ ] **Testing**: Write integration tests for all state transitions
- [ ] **Monitoring**: Set up alerts for fraud patterns and system issues
- [ ] **Documentation**: Document API endpoints and state machine for operators

### 6.8 Example Sequence (Happy Path)

```
1. Client → POST /sessions
   → Creates session_token S1, amount $0.99, expires_in: 10m

2. Client → POST /payments (session_token: S1)
   → Starts payment intent P1 → status: pending

3. Payment provider webhook → POST /payments/webhook (P1 captured)
   → Server marks session S1: status=active, remaining_replies=1

4. Operator UI polls GET /sessions/S1
   → Sees status=active, remaining_replies=1
   → Operator clicks send

5. Operator → POST /sessions/S1/reply
   → Server checks guards (active, remaining_replies > 0, rate limit OK)
   → Creates reply R1 (billed=false)
   → Locks session

6. Server triggers per-reply capture (if per-reply billing)
   → Or links to existing session payment
   → On success: marks billed=true, delivered=true
   → Decrements remaining_replies to 0
   → Unlocks session
   → Calculates operator earnings (80% of $0.99 = $0.79)
   → Updates operator balance

7. Session moves to Expired (remaining_replies == 0)
   → Operator cannot send more replies
   → User can view reply history
   → Session soft-deleted after 120 days
```

### 6.9 Race Condition Tests

**Critical Tests**:

1. **Concurrent Reply Attempts**:
   - Simulate multiple `POST /sessions/:id/reply` from operator while payment webhook processing
   - Expected: Only one reply succeeds, others blocked by lock

2. **Duplicate Webhook Events**:
   - Send same `webhook_event_id` twice
   - Expected: Second webhook ignored (idempotency check)

3. **Network Disconnect During Reply**:
   - Client sends reply, network fails before response received
   - Client reconnects and attempts resend with same `client_reply_id`
   - Expected: Idempotency prevents duplicate reply

4. **Chargeback During Active Session**:
   - Chargeback webhook received while session still active
   - Expected: Session moved to Disputed, operator earnings held, both parties notified

5. **Session Expiry During Payment**:
   - Session expires_at reached while payment pending
   - Expected: Payment fails, session moved to Expired, refund issued

---

## 7. Feature Gaps & Required Enhancements

### 7.1 Critical Missing Features (High Priority)

#### 1. Production Database Migration
**Current State**: aeims.app uses JSON file storage
**Required**: Migrate to MySQL/PostgreSQL for production

**Implementation**:
- Database schema design (users, sessions, messages, operators)
- Migration script to import JSON data to SQL
- Update PHP code to use PDO/MySQLi instead of file I/O
- Transaction support for data integrity
- Connection pooling for performance

**Estimated Effort**: 20 hours
**Priority**: CRITICAL - Blocking production at scale

#### 2. Real-Time Notification System
**Current State**: Toast notifications planned but not implemented
**Required**: Server-Sent Events (SSE) or WebSocket for real-time updates

**Features**:
- New message notifications
- Chat room invitations
- Operator status changes
- Payment confirmations
- Activity updates

**Implementation**:
- SSE endpoint: `/events/subscribe`
- Redis pub/sub for event distribution
- NotificationManager integration
- Client-side EventSource handling
- Notification preferences per user

**Estimated Effort**: 8 hours
**Priority**: HIGH - Core UX enhancement

#### 3. Private Chat Rooms
**Current State**: ChatRoomManager exists but not fully implemented
**Required**: Complete chat room functionality

**Features**:
- Operators create private rooms
- PIN protection for rooms
- Entry fee system
- Per-minute billing for room access
- Multi-user chat in rooms
- Room search and discovery

**Database Schema**:
- `chat_rooms` table (room_id, operator_id, pin_code, entry_fee, per_minute_rate)
- `room_messages` table (message_id, room_id, sender_id, content, timestamp)
- `room_participants` table (user_id, room_id, joined_at, paid_until)

**Estimated Effort**: 12 hours
**Priority**: HIGH - Revenue generator

#### 4. Pay-Per-Call System (VoIP Integration)
**Current State**: aeims-asterisk infrastructure ready but not integrated with aeims.app
**Required**: Complete integration of Asterisk calling with customer interface

**Features**:
- Customer requests operator callback
- Number masking via Twilio or Asterisk
- Call scheduling system
- Pre-payment for call duration
- Call history and CDR access
- Per-minute billing with balance checking

**Implementation**:
- API integration: aeims.app → aeims-asterisk-adapter
- Call request workflow
- Real-time balance monitoring during calls
- Call history UI
- Recording playback (S3 integration)

**Estimated Effort**: 10 hours
**Priority**: HIGH - Major revenue stream

#### 5. Billing State Machine Implementation
**Current State**: Conceptual design complete (see Section 6)
**Required**: Full implementation of secure billing state machine

**Features**:
- Session-based billing with state tracking
- Payment capture at session initiation
- Reply counting and validation
- Operator revenue split (80/20)
- Chargeback handling
- Fraud detection
- Device fingerprinting

**Implementation**:
- Migrate from JSON to SQL (sessions, payments, replies tables)
- Implement all API endpoints from Section 6.5
- State machine with guard clauses
- Payment provider integration (Stripe)
- Webhook handling with idempotency
- Admin dispute resolution interface

**Estimated Effort**: 40 hours
**Priority**: CRITICAL - Prevents billing exploits

#### 6. Device Control Integration (aeimsLib → aeims.app)
**Current State**: aeimsLib exists as standalone library
**Required**: Full integration with customer and operator interfaces

**Features**:
- Customer device pairing interface
- Operator control panel for devices
- Pattern selection and customization
- Real-time device state synchronization
- VR/XR integration (future)

**Implementation**:
- WebSocket client in aeims.app JavaScript
- Device pairing UI flow
- Operator control interface (sexacomms.com)
- JWT authentication for WebSocket
- Redis state synchronization

**Estimated Effort**: 15 hours
**Priority**: MEDIUM - Unique differentiator

### 7.2 Important Missing Features (Medium Priority)

#### 7. Operator VoIP Number Purchase
**Features**:
- Operators purchase dedicated DID numbers
- Monthly fee billing
- Number management panel
- Assign numbers to customer accounts
- DID provisioning via Twilio/Bandwidth

**Estimated Effort**: 8 hours
**Priority**: MEDIUM

#### 8. Video/Voice Streaming (WebRTC)
**Features**:
- Operator video streaming in chat rooms
- Operator voice streaming
- Per-stream billing (users pay to watch)
- "Tip for action" system
- Stream quality controls

**Infrastructure Needed**:
- WebRTC implementation
- TURN/STUN servers for NAT traversal
- Media server (Janus/Mediasoup) for multi-user
- CDN for stream delivery

**Estimated Effort**: 40 hours (complex)
**Priority**: MEDIUM - High complexity

#### 9. Payment Processor Integration
**Current State**: Manual credit system
**Required**: Automated payment processing

**Processors to Integrate**:
- Stripe (primary)
- PayPal (alternative)
- Paxum (adult-industry friendly)
- Cryptocurrency (Bitcoin, Ethereum - future)

**Features**:
- Credit card processing
- ACH/bank transfers
- Subscription billing
- Refund handling
- Chargeback automation
- PCI compliance

**Estimated Effort**: 20 hours
**Priority**: MEDIUM

#### 10. Advanced Analytics Dashboard
**Features**:
- Revenue analytics (per site, per operator, per service)
- Operator performance metrics
- Customer behavior analytics
- Retention metrics
- Churn prediction
- Real-time dashboards with Grafana

**Implementation**:
- Analytics service (Python/Django)
- Data warehouse (PostgreSQL analytics DB)
- ETL pipelines for data aggregation
- Grafana dashboard configuration
- API endpoints for custom reports

**Estimated Effort**: 25 hours
**Priority**: MEDIUM

### 7.3 Nice-to-Have Features (Low Priority)

#### 11. Mobile Apps (Native)
**Platforms**: iOS, Android
**Features**: Native operator and customer apps with push notifications

**Estimated Effort**: 200+ hours
**Priority**: LOW - Can use responsive web for now

#### 12. AI-Powered Features
- Chatbot for customer support
- AI-generated operator responses (suggestions)
- Fraud detection with machine learning
- Personalized recommendations
- Content moderation automation

**Estimated Effort**: 80+ hours
**Priority**: LOW - Future enhancement

#### 13. White-Label Customization Interface
**Features**:
- No-code site builder for licensees
- Theme customization
- Logo/branding management
- Custom domain setup
- Pricing configuration

**Estimated Effort**: 60 hours
**Priority**: LOW - Currently handled manually

#### 14. Advanced Compliance Tools
- Automated GDPR data export
- California CCPA compliance
- COPPA age verification (enhanced)
- Automated compliance reporting
- Content moderation AI
- Geolocation-based content filtering

**Estimated Effort**: 40 hours
**Priority**: LOW - Basic compliance in place

#### 15. Operator Social Features
- Operator profiles with bios and galleries
- Operator-to-operator messaging
- Operator communities and forums
- Operator rating/review system
- Operator referral program

**Estimated Effort**: 30 hours
**Priority**: LOW

### 7.4 Infrastructure Gaps

#### 16. CI/CD Pipeline
**Current State**: Manual deployments via SuperDeploy
**Required**: Automated CI/CD with GitHub Actions

**Features**:
- Automated testing on pull requests
- Automated Docker image builds
- Automated deployments to staging
- Manual approval for production
- Rollback capabilities

**Estimated Effort**: 16 hours
**Priority**: MEDIUM

#### 17. Multi-Region Deployment
**Current State**: Single AWS region (us-east-1)
**Required**: Multi-region for global performance

**Features**:
- Multi-region AWS deployment
- Route53 geolocation routing
- Cross-region database replication
- CDN integration (CloudFront)
- Regional failover

**Estimated Effort**: 40 hours
**Priority**: LOW - Not needed until global scale

#### 18. Enhanced Monitoring & Alerting
**Additions Needed**:
- PagerDuty integration for critical alerts
- Slack/Discord notifications
- Custom CloudWatch dashboards
- Advanced anomaly detection
- SLA monitoring

**Estimated Effort**: 12 hours
**Priority**: MEDIUM

#### 19. Disaster Recovery Automation
**Features**:
- Automated backup verification
- One-click restore procedures
- Regular DR drills
- RTO/RPO enforcement
- Cross-region backup replication

**Estimated Effort**: 20 hours
**Priority**: MEDIUM

---

## 8. Implementation Roadmap

### Phase 1: Production Readiness (Critical Path) - 8 Weeks

**Goal**: Make aeims.app production-ready at scale

#### Week 1-2: Database Migration
- [ ] Design MySQL schema for aeims.app
- [ ] Create migration scripts from JSON to SQL
- [ ] Update PHP code to use PDO
- [ ] Implement database connection pooling
- [ ] Test data integrity and rollback procedures
- [ ] Deploy to staging and validate

**Deliverables**:
- MySQL database schema (SQL files)
- Migration scripts (PHP)
- Updated aeims.app codebase using SQL
- Migration documentation

**Success Criteria**:
- All JSON data successfully migrated
- No data loss or corruption
- Application functions identically on SQL vs JSON
- Performance acceptable (< 100ms query time)

#### Week 3-4: Billing State Machine
- [ ] Implement sessions, payments, replies tables
- [ ] Create all API endpoints (POST /sessions, POST /payments, etc.)
- [ ] Implement state machine with guard clauses
- [ ] Integrate Stripe payment provider
- [ ] Implement webhook handling with idempotency
- [ ] Device fingerprinting implementation
- [ ] Fraud detection rules
- [ ] Admin dispute resolution UI

**Deliverables**:
- Complete billing state machine (Section 6 implementation)
- API documentation (OpenAPI spec)
- Admin interface for dispute management
- Test suite for state transitions

**Success Criteria**:
- All 10 security issues (Section 6.1) resolved
- State machine handles race conditions correctly
- Webhooks processed idempotently
- Fraud detection flags suspicious patterns
- Chargebacks handled correctly

#### Week 5-6: Real-Time Notifications
- [ ] Implement SSE endpoint `/events/subscribe`
- [ ] Redis pub/sub for event distribution
- [ ] NotificationManager integration
- [ ] Client-side EventSource handling
- [ ] Notification preferences UI
- [ ] Toast notification UI components

**Deliverables**:
- Real-time notification system (SSE-based)
- Notification preferences panel
- Toast UI components
- Documentation for notification types

**Success Criteria**:
- Notifications delivered in < 1 second
- Client reconnects automatically on disconnect
- Preferences persisted and respected
- No notification loss during high load

#### Week 7-8: Pay-Per-Call Integration
- [ ] API integration: aeims.app → aeims-asterisk-adapter
- [ ] Call request workflow (UI + backend)
- [ ] Real-time balance monitoring during calls
- [ ] Call history UI
- [ ] Recording playback interface (S3 integration)
- [ ] Number masking setup

**Deliverables**:
- Integrated VoIP calling in customer interface
- Operator call management interface
- Call history and CDR access
- Documentation for call flows

**Success Criteria**:
- Customers can request and receive calls
- Per-minute billing works correctly
- Balance checked before call initiation
- Recordings accessible and playable
- No call drops due to billing issues

### Phase 2: Revenue Features - 6 Weeks

**Goal**: Implement revenue-generating features

#### Week 9-10: Private Chat Rooms
- [ ] Complete ChatRoomManager implementation
- [ ] Database tables (chat_rooms, room_messages, room_participants)
- [ ] Room creation UI (operator interface)
- [ ] Room discovery UI (customer interface)
- [ ] PIN protection implementation
- [ ] Entry fee and per-minute billing
- [ ] Multi-user chat functionality

**Deliverables**:
- Full chat room system
- Operator room management panel
- Customer room discovery and entry
- Billing integration for rooms

**Success Criteria**:
- Operators can create and manage rooms
- Customers can discover and join rooms
- PIN protection works correctly
- Billing accurate for entry fees and per-minute charges
- Multi-user chat synchronized in real-time

#### Week 11-12: Device Control Integration
- [ ] WebSocket client in aeims.app JavaScript
- [ ] Device pairing UI flow (customer interface)
- [ ] Operator control panel (sexacomms.com)
- [ ] JWT authentication for WebSocket
- [ ] Redis state synchronization
- [ ] Pattern selection interface

**Deliverables**:
- Device control UI for customers and operators
- WebSocket integration with aeimsLib
- Pattern library interface
- Documentation for supported devices

**Success Criteria**:
- Customers can pair devices via Bluetooth
- Operators can send control commands
- Device state synchronized across clients
- Patterns load and execute correctly
- Connection resilient to network issues

#### Week 13-14: Payment Processor Integration
- [ ] Stripe integration (credit card processing)
- [ ] PayPal integration (alternative payment)
- [ ] Paxum integration (adult-friendly processor)
- [ ] Credit purchase flow
- [ ] Refund handling
- [ ] Chargeback automation

**Deliverables**:
- Multi-processor payment system
- Credit purchase UI
- Refund management interface
- Chargeback automation

**Success Criteria**:
- All three processors functional
- Credit purchases processed correctly
- Refunds issued properly
- Chargebacks handled automatically
- PCI compliance maintained

### Phase 3: Platform Enhancements - 8 Weeks

**Goal**: Enhance platform capabilities and scalability

#### Week 15-16: Advanced Analytics
- [ ] Analytics service (Python microservice)
- [ ] Data warehouse setup (PostgreSQL analytics)
- [ ] ETL pipelines for data aggregation
- [ ] Grafana dashboards
- [ ] API endpoints for custom reports
- [ ] Revenue analytics UI

**Deliverables**:
- Analytics microservice
- Grafana dashboards (revenue, operators, customers)
- Custom reporting API
- Admin analytics UI

**Success Criteria**:
- Real-time revenue tracking
- Operator performance metrics accurate
- Customer behavior analytics actionable
- Dashboards update in real-time
- Custom reports exportable (CSV, PDF)

#### Week 17-18: Operator VoIP Numbers
- [ ] DID provisioning via Twilio/Bandwidth
- [ ] Number purchase workflow
- [ ] Monthly billing for DIDs
- [ ] Number management panel (operator)
- [ ] Number assignment to customers

**Deliverables**:
- DID purchase and management system
- Monthly billing for numbers
- Number assignment interface

**Success Criteria**:
- Operators can purchase DIDs
- Numbers provisioned within 5 minutes
- Monthly billing accurate
- Numbers assigned to customers correctly
- Number porting supported (future)

#### Week 19-20: CI/CD Pipeline
- [ ] GitHub Actions workflows
- [ ] Automated testing (Playwright, Jest, PHPUnit)
- [ ] Docker image builds (ECR push)
- [ ] Automated deployments to staging
- [ ] Manual approval gates for production
- [ ] Rollback procedures

**Deliverables**:
- Complete CI/CD pipeline
- Automated test suite
- Deployment documentation
- Rollback runbooks

**Success Criteria**:
- All tests run on pull requests
- Docker images built and pushed automatically
- Staging deploys automatically on merge to main
- Production deploys require manual approval
- Rollback takes < 5 minutes

#### Week 21-22: Enhanced Monitoring
- [ ] PagerDuty integration
- [ ] Slack/Discord notifications
- [ ] Custom CloudWatch dashboards
- [ ] Advanced anomaly detection
- [ ] SLA monitoring (99.9% uptime)

**Deliverables**:
- Enhanced monitoring and alerting
- Custom CloudWatch dashboards
- On-call rotation (PagerDuty)
- SLA tracking dashboard

**Success Criteria**:
- Critical alerts trigger PagerDuty
- Non-critical alerts sent to Slack
- Dashboards show real-time system health
- Anomalies detected and alerted
- SLA tracked and reported

### Phase 4: Advanced Features - 12 Weeks

**Goal**: Implement advanced and future-facing features

#### Week 23-26: Video/Voice Streaming (WebRTC)
- [ ] WebRTC implementation
- [ ] TURN/STUN server setup
- [ ] Media server (Janus/Mediasoup) deployment
- [ ] Operator streaming interface
- [ ] Customer viewing interface
- [ ] Per-stream billing
- [ ] "Tip for action" system

**Deliverables**:
- WebRTC streaming system
- Operator streaming interface
- Customer viewing and tipping interface
- CDN integration for stream delivery

**Success Criteria**:
- Operators can stream video/audio
- Customers can view streams with < 2 second latency
- Billing accurate for stream viewing time
- Tips processed correctly
- Streams scale to 100+ concurrent viewers per operator

#### Week 27-30: AI-Powered Features
- [ ] Chatbot for customer support (GPT-4 based)
- [ ] AI-generated operator response suggestions
- [ ] Fraud detection with machine learning
- [ ] Personalized recommendations
- [ ] Content moderation automation

**Deliverables**:
- AI chatbot integration
- Fraud detection ML model
- Recommendation engine
- Content moderation system

**Success Criteria**:
- Chatbot handles 70%+ of common questions
- Fraud detection catches 90%+ of fraudulent accounts
- Recommendations increase engagement by 20%+
- Content moderation flags 95%+ of prohibited content

#### Week 31-34: White-Label Customization
- [ ] No-code site builder interface
- [ ] Theme customization (colors, fonts, logos)
- [ ] Logo/branding upload
- [ ] Custom domain setup wizard
- [ ] Pricing configuration UI

**Deliverables**:
- White-label customization interface
- Site builder (drag-and-drop)
- Theme editor
- Custom domain wizard

**Success Criteria**:
- Licensees can customize sites without code
- Themes apply consistently across all pages
- Custom domains work with SSL auto-provisioning
- Pricing customizable per site

### Phase 5: Scale & Polish - Ongoing

**Goal**: Continuous improvement and scaling

#### Ongoing Tasks
- [ ] Performance optimization (database queries, caching)
- [ ] Security audits (quarterly penetration testing)
- [ ] Compliance updates (GDPR, CCPA, state laws)
- [ ] User feedback incorporation
- [ ] Bug fixes and stability improvements
- [ ] Documentation updates
- [ ] Operator training materials

**Success Criteria**:
- Platform maintains 99.9% uptime
- Response times < 200ms for 95% of requests
- Zero critical security vulnerabilities
- Compliance with all applicable laws
- Customer satisfaction > 4.5/5.0

---

## 9. Technical Debt & Risks

### 9.1 Technical Debt

#### 1. JSON File Storage in aeims.app
**Description**: Current use of JSON files for data storage limits scalability and data integrity.

**Impact**:
- Cannot scale beyond ~1,000 concurrent users
- No transaction support (risk of data corruption)
- Slow performance on large datasets
- No query optimization
- File locking issues under high concurrency

**Remediation**: Migrate to MySQL/PostgreSQL (Phase 1, Week 1-2)

**Risk Level**: CRITICAL
**Estimated Fix Effort**: 20 hours

#### 2. Lack of Automated Testing
**Description**: No unit tests, integration tests, or end-to-end tests (except Playwright in aeims-control).

**Impact**:
- High risk of regressions when making changes
- Difficult to refactor code safely
- Manual testing time-consuming
- Bugs discovered in production

**Remediation**: Implement CI/CD with automated testing (Phase 3, Week 19-20)

**Risk Level**: HIGH
**Estimated Fix Effort**: 40 hours (ongoing)

#### 3. Monolithic PHP Codebase
**Description**: aeims.app is a monolithic PHP application without clear service boundaries.

**Impact**:
- Difficult to scale individual components
- Changes risk affecting unrelated features
- Hard to onboard new developers
- Limited ability to use different languages for different services

**Remediation**: Gradual extraction to microservices (long-term, post-Phase 4)

**Risk Level**: MEDIUM
**Estimated Fix Effort**: 200+ hours (incremental)

#### 4. Inconsistent Error Handling
**Description**: Error handling varies across codebase; some errors logged, others silently fail.

**Impact**:
- Difficult to debug production issues
- Poor user experience (generic error messages)
- Security risk (errors may leak sensitive info)

**Remediation**: Standardize error handling and logging (Phase 1, ongoing)

**Risk Level**: MEDIUM
**Estimated Fix Effort**: 16 hours

#### 5. Hardcoded Configuration Values
**Description**: Some configuration values hardcoded instead of environment variables.

**Impact**:
- Difficult to deploy to multiple environments
- Security risk (secrets in code)
- Manual changes required per environment

**Remediation**: Move all config to environment variables or config files (Phase 1, Week 1)

**Risk Level**: MEDIUM
**Estimated Fix Effort**: 8 hours

#### 6. No Code Documentation
**Description**: Limited inline comments and no API documentation.

**Impact**:
- Difficult to onboard new developers
- Unclear API contracts between services
- Risk of breaking changes without awareness

**Remediation**: Generate API documentation (OpenAPI), add PHPDoc/JSDoc (Phase 1, ongoing)

**Risk Level**: MEDIUM
**Estimated Fix Effort**: 24 hours

#### 7. Unoptimized Database Queries (Future)
**Description**: Once migrated to SQL, queries may not be optimized with indexes.

**Impact**:
- Slow response times under load
- High database CPU usage
- Scalability bottleneck

**Remediation**: Query optimization and indexing (Phase 2, ongoing)

**Risk Level**: LOW (future concern)
**Estimated Fix Effort**: 12 hours

#### 8. No Rate Limiting on aeims.app Endpoints
**Description**: Some endpoints lack rate limiting, vulnerable to abuse.

**Impact**:
- DDoS vulnerability
- Brute force attacks on login
- Resource exhaustion

**Remediation**: Implement Redis-based rate limiting on all endpoints (Phase 1, Week 3)

**Risk Level**: HIGH
**Estimated Fix Effort**: 8 hours

### 9.2 Risks

#### Risk 1: Billing Exploit Before State Machine Implementation
**Description**: Current billing system vulnerable to the 10 security issues outlined in Section 6.1.

**Likelihood**: HIGH (if attackers aware)
**Impact**: CRITICAL (revenue loss, fraud)

**Mitigation**:
- Prioritize billing state machine implementation (Phase 1, Week 3-4)
- Temporary mitigations: manual review of high-value transactions, daily audit logs
- Deploy fraud detection rules immediately

**Status**: IN PROGRESS (Phase 1)

#### Risk 2: Data Loss During JSON to SQL Migration
**Description**: Migration script errors could corrupt or lose data.

**Likelihood**: MEDIUM
**Impact**: CRITICAL (customer and operator data loss)

**Mitigation**:
- Thorough testing on staging with production data copies
- Backup JSON files before migration
- Rollback plan (restore from JSON if migration fails)
- Incremental migration (migrate table by table, validate each)
- Manual verification of critical data (payments, accounts)

**Status**: PLANNED (Phase 1, Week 1-2)

#### Risk 3: Asterisk Integration Failure
**Description**: VoIP integration may have reliability issues (call drops, billing errors).

**Likelihood**: MEDIUM
**Impact**: HIGH (customer dissatisfaction, revenue loss)

**Mitigation**:
- Extensive testing with real SIP providers
- Circuit breakers and retry logic
- Monitoring and alerting for call failures
- Manual fallback (operators call customers directly if system fails)
- Gradual rollout (beta testers first)

**Status**: PLANNED (Phase 1, Week 7-8)

#### Risk 4: Payment Processor Account Suspension
**Description**: Adult industry stigma may lead to payment processor suspensions (Stripe, PayPal).

**Likelihood**: MEDIUM
**Impact**: CRITICAL (no way to accept payments)

**Mitigation**:
- Diversify payment processors (Stripe, PayPal, Paxum, crypto)
- Use adult-friendly processors as primary (Paxum, CCBill)
- Maintain high compliance standards to reduce risk
- Legal review of terms of service for each processor
- Emergency plan: manual payment processing if all processors suspend

**Status**: PLANNED (Phase 2, Week 13-14)

#### Risk 5: AWS Cost Overruns
**Description**: Auto-scaling and high-traffic could lead to unexpected AWS bills.

**Likelihood**: MEDIUM
**Impact**: MEDIUM (financial burden)

**Mitigation**:
- Set CloudWatch billing alarms (daily and monthly thresholds)
- Right-size ECS tasks (use t3.micro/small when possible)
- Use FARGATE_SPOT for non-critical services (50% cost savings)
- Implement cost monitoring dashboard
- Review AWS costs weekly during initial deployment

**Status**: ONGOING (cost monitoring active)

#### Risk 6: GDPR/Privacy Compliance Violation
**Description**: Mishandling EU customer data could result in fines (up to 4% of revenue).

**Likelihood**: LOW (if compliance followed)
**Impact**: CRITICAL (fines, reputation damage)

**Mitigation**:
- GDPR compliance Lambda functions already deployed (aeims-control)
- Data retention policies enforced (soft-delete for 120 days, then anonymize)
- User data export and deletion on request
- Privacy policy clearly communicated
- Legal review of privacy practices
- GDPR training for team

**Status**: ACTIVE (compliance monitoring in place)

#### Risk 7: Security Breach (Data Leak)
**Description**: Unauthorized access to customer or operator data.

**Likelihood**: LOW (with proper security)
**Impact**: CRITICAL (legal liability, reputation damage)

**Mitigation**:
- AWS WAF enabled with custom rule sets
- KMS encryption for all databases and storage
- Regular security audits (quarterly penetration testing)
- Intrusion detection (CloudWatch anomaly detection)
- Incident response plan documented
- Security training for developers
- Bug bounty program (future)

**Status**: ACTIVE (security measures deployed)

#### Risk 8: Key Developer Departure
**Description**: Loss of primary developer (Ryan Coleman) could halt development.

**Likelihood**: LOW
**Impact**: HIGH (development slowdown)

**Mitigation**:
- Comprehensive documentation (PRD, READMEs, CHANGELOGs)
- Knowledge transfer to additional developers
- Code comments and API documentation
- Standard tools and patterns (reduce "tribal knowledge")
- Backup contractor or agency on retainer

**Status**: IN PROGRESS (documentation improving)

#### Risk 9: Third-Party Service Outage
**Description**: AWS, Twilio, Stripe, or other third-party outage impacts platform.

**Likelihood**: LOW (but inevitable eventually)
**Impact**: MEDIUM to HIGH (depending on service)

**Mitigation**:
- Multi-AZ deployment for AWS resilience
- Circuit breakers for third-party API calls
- Graceful degradation (disable features if service down)
- Status page for customers (communicate outages)
- SLAs and uptime monitoring for critical services

**Status**: ACTIVE (multi-AZ deployed)

#### Risk 10: Legal/Regulatory Changes
**Description**: New laws (state or federal) may require platform changes (e.g., age verification, content restrictions).

**Likelihood**: MEDIUM (ongoing regulatory changes)
**Impact**: MEDIUM to HIGH (compliance burden)

**Mitigation**:
- Monitor regulatory changes (legal counsel on retainer)
- Compliance monitoring Lambda functions (FOSTA, Florida, GDPR, NY SHIELD)
- Flexible architecture to accommodate new requirements
- Budget for compliance updates (10% of development time)
- Industry associations and advocacy groups for advance notice

**Status**: ACTIVE (compliance monitoring in place)

### 9.3 Technical Debt Prioritization

**Critical Priority** (fix immediately):
1. JSON file storage → SQL migration
2. Billing state machine security issues
3. Rate limiting on endpoints

**High Priority** (fix in Phase 1-2):
4. Automated testing
5. Error handling standardization
6. Code documentation

**Medium Priority** (fix in Phase 3-4):
7. Monolithic codebase → microservices
8. Query optimization (post-migration)
9. Hardcoded configuration

**Low Priority** (ongoing improvement):
10. Code refactoring
11. Performance tuning
12. Developer experience improvements

---

## 10. Recommendations

### 10.1 Strategic Recommendations

#### 1. Accelerate Phase 1 Completion
**Rationale**: Phase 1 (Production Readiness) is the foundation for all revenue-generating features. Delays here cascade to all other phases.

**Actions**:
- Dedicate 100% focus to Phase 1 until complete
- Consider hiring a contractor for database migration (parallel work)
- Defer all non-critical feature requests until Phase 1 done
- Set aggressive but achievable milestone deadlines (2-week sprints)

**Expected Outcome**: Phase 1 complete in 6 weeks instead of 8

#### 2. Prioritize Billing State Machine
**Rationale**: Current billing vulnerabilities expose the platform to fraud and revenue loss. This is the highest-risk technical debt.

**Actions**:
- Implement minimum viable billing state machine in Week 3 (before full feature set)
- Deploy fraud detection rules immediately (multi-account detection, rate limits)
- Manual review process for transactions > $100 until automation complete
- Daily audit logs reviewed by admin

**Expected Outcome**: Fraud risk reduced by 90% within 2 weeks

#### 3. Start Payment Processor Onboarding Early
**Rationale**: Adult industry payment processors have lengthy approval processes (2-6 weeks). Delays here block revenue.

**Actions**:
- Apply to Paxum and CCBill immediately (adult-friendly processors)
- Prepare compliance documentation for processor applications
- Stripe and PayPal as secondary (higher suspension risk)
- Budget for higher transaction fees (adult industry: 7-12% vs 2.9% standard)

**Expected Outcome**: Payment processors approved by end of Phase 1

#### 4. Adopt Agile/Scrum Methodology
**Rationale**: Large roadmap (30+ weeks) requires iterative approach with regular course corrections.

**Actions**:
- 2-week sprints with defined goals
- Weekly demos to stakeholders (even if just Ryan + PM/designer)
- Sprint retrospectives to identify blockers
- Kanban board for task tracking (Jira, Trello, or GitHub Projects)

**Expected Outcome**: Faster velocity, fewer surprises, better alignment

#### 5. Hire Strategically
**Rationale**: Current workload exceeds one developer's capacity. Strategic hires accelerate roadmap.

**Recommended Hires** (in order of priority):
1. **Backend Developer (PHP/Python)**: Database migration, API development (Phase 1-2)
2. **Frontend Developer (React/Vue)**: Real-time UI, operator interfaces (Phase 2)
3. **DevOps Engineer**: CI/CD, monitoring, infrastructure scaling (Phase 3)
4. **QA Engineer**: Automated testing, manual testing, regression testing (Phase 1, ongoing)

**Expected Outcome**: Roadmap completion in 18 weeks instead of 30

### 10.2 Technical Recommendations

#### 6. Migrate to PostgreSQL for All Databases
**Rationale**: Currently using PostgreSQL for AEIMS Core and MySQL for AEIMS App. Consolidating reduces operational complexity.

**Actions**:
- Use PostgreSQL 14 for both AEIMS Core and AEIMS App
- Simplifies backup and replication strategies
- Better JSON support (JSONB) for flexible schemas
- Superior performance for complex queries

**Expected Outcome**: Simpler infrastructure, better performance

#### 7. Implement GraphQL API (Long-Term)
**Rationale**: Multiple frontend clients (web, mobile apps, operator portals) benefit from flexible, efficient API.

**Actions**:
- Phase 4+: Introduce GraphQL layer on top of REST APIs
- Use Apollo Server (Node.js) or Graphene (Python)
- Gradually migrate REST endpoints to GraphQL
- Better developer experience for frontend teams

**Expected Outcome**: Faster frontend development, reduced over-fetching

#### 8. Use Kubernetes Instead of ECS (Future)
**Rationale**: Kubernetes provides better portability and ecosystem (Helm, Operators, service mesh).

**Actions**:
- Phase 5+: Evaluate migration from ECS to EKS (AWS Kubernetes)
- Benefits: easier multi-cloud, better auto-scaling, larger community
- Tradeoffs: higher operational complexity, steeper learning curve
- Decision point: when scaling beyond 50+ services

**Expected Outcome**: Better long-term scalability and flexibility

#### 9. Implement Service Mesh (Istio/Linkerd)
**Rationale**: As microservices grow (12+ services), service-to-service communication becomes complex.

**Actions**:
- Phase 4+: Introduce service mesh for observability and security
- Automatic mTLS between services
- Distributed tracing (Jaeger, Zipkin)
- Traffic management (canary deployments, A/B testing)

**Expected Outcome**: Better observability, security, and deployment strategies

#### 10. Adopt Infrastructure as Code for All Resources
**Rationale**: Currently using Terraform for infrastructure but manual processes for some configurations.

**Actions**:
- 100% of infrastructure defined in Terraform (no manual AWS console changes)
- Use Terraform modules for reusability
- Terraform Cloud for state management and collaboration
- Atlantis for automated Terraform pull request workflows

**Expected Outcome**: Reproducible infrastructure, easier disaster recovery

### 10.3 Business Recommendations

#### 11. Launch Beta Program for Phase 1 Features
**Rationale**: Early feedback from real users prevents costly post-launch changes.

**Actions**:
- Recruit 10-20 beta operators and 50-100 beta customers
- Offer free credits or discounted rates for beta participation
- Weekly feedback sessions with beta users
- Iterative improvements based on feedback before public launch

**Expected Outcome**: Higher quality launch, fewer bugs, better product-market fit

#### 12. Create Tiered Pricing for Licensees
**Rationale**: Different customer segments have different needs and willingness to pay.

**Recommended Tiers**:
- **Starter** ($299/month): 1 domain, 10 operators, basic features
- **Professional** ($899/month): 3 domains, 50 operators, cross-site support, device control
- **Enterprise** ($2,499/month): Unlimited domains/operators, custom features, dedicated support
- **White-Label** ($5,000+ setup + monthly): Fully customized, separate infrastructure

**Expected Outcome**: Capture more market segments, increase revenue per customer

#### 13. Develop Operator Recruitment Program
**Rationale**: Platform value depends on operator availability. Operators are supply side of marketplace.

**Actions**:
- Operator referral bonuses ($50-100 per successful referral)
- Partnerships with adult industry job boards
- Social media marketing targeting performers
- Highlight cross-site earnings potential (AEIMS differentiator)
- 80/20 revenue split is competitive (some platforms do 70/30 or 60/40)

**Expected Outcome**: 200+ operators by end of Phase 2

#### 14. Build Customer Acquisition Funnel
**Rationale**: Customer-facing sites (nycflirts.com, flirts.nyc) need traffic to generate revenue.

**Actions**:
- SEO optimization (adult industry keywords)
- PPC advertising (adult ad networks: TrafficJunky, ExoClick)
- Content marketing (blog, video tutorials for customers)
- Affiliate program (5-10% of customer spending)
- Social media presence (Twitter/X, Reddit communities)

**Expected Outcome**: 1,000+ active customers per site by end of Phase 2

#### 15. Establish Legal & Compliance Foundation
**Rationale**: Adult industry is heavily regulated. Legal issues can shut down business overnight.

**Actions**:
- Retain attorney specializing in adult entertainment law
- Quarterly legal reviews of terms of service, privacy policy
- 2257 compliance (record-keeping for adult performers)
- DMCA agent registration
- State-by-state compliance review (especially CA, FL, NY, TX)
- Insurance: general liability, cyber liability, errors & omissions

**Expected Outcome**: Legal protection, reduced regulatory risk

### 10.4 Operational Recommendations

#### 16. Implement 24/7 On-Call Rotation
**Rationale**: Adult entertainment operates 24/7. Outages at 2 AM are unacceptable.

**Actions**:
- Phase 3+: Implement PagerDuty on-call rotation
- Start with single on-call engineer (Ryan)
- Expand to rotation as team grows (3+ engineers)
- Document runbooks for common incidents
- Post-incident reviews (blameless)

**Expected Outcome**: < 15 minute response time for critical incidents

#### 17. Create Operator Onboarding Program
**Rationale**: Operators need training to maximize earnings and provide quality service.

**Actions**:
- Operator onboarding documentation and videos
- Best practices guide (profiles, messaging, device control)
- Earnings optimization tips (peak hours, cross-site presence)
- Community forum or Slack for operator support
- Monthly operator training webinars

**Expected Outcome**: Higher operator retention, better customer experience

#### 18. Establish Customer Support Infrastructure
**Rationale**: Customers need help with accounts, payments, technical issues.

**Actions**:
- Phase 2+: Implement support ticket system (Zendesk, Freshdesk)
- Live chat integration (Intercom, Drift)
- Knowledge base for common questions
- Response SLAs: < 4 hours for critical, < 24 hours for standard
- Escalation path to developers for technical issues

**Expected Outcome**: Higher customer satisfaction, reduced churn

#### 19. Build Data-Driven Culture
**Rationale**: Product decisions should be informed by data, not intuition.

**Actions**:
- Implement analytics tracking (Mixpanel, Amplitude)
- Weekly metrics review (revenue, DAU/MAU, churn, operator earnings)
- A/B testing framework for product changes
- Dashboard accessible to all team members
- OKRs (Objectives and Key Results) aligned with metrics

**Expected Outcome**: Better product decisions, faster iteration

#### 20. Document Everything
**Rationale**: Knowledge transfer is critical as team grows. Documentation prevents "key person risk."

**Actions**:
- Maintain up-to-date README files for all projects
- API documentation (OpenAPI/Swagger specs)
- Architecture decision records (ADRs) for major technical decisions
- Runbooks for operational procedures
- Onboarding documentation for new developers
- Regular documentation reviews (quarterly)

**Expected Outcome**: Easier onboarding, reduced "tribal knowledge," faster development

---

## Appendix A: Glossary

- **AEIMS**: Adult Entertainment Interactive Management System
- **ARI**: Asterisk REST Interface
- **AMI**: Asterisk Manager Interface
- **BLE**: Bluetooth Low Energy
- **CDR**: Call Detail Record
- **CI/CD**: Continuous Integration / Continuous Deployment
- **CSRF**: Cross-Site Request Forgery
- **DID**: Direct Inward Dialing (phone number)
- **ECS**: Elastic Container Service (AWS)
- **EFS**: Elastic File System (AWS)
- **JWT**: JSON Web Token
- **KMS**: Key Management Service (AWS)
- **MFA**: Multi-Factor Authentication
- **NAT**: Network Address Translation
- **PKCE**: Proof Key for Code Exchange
- **RDS**: Relational Database Service (AWS)
- **SSE**: Server-Sent Events
- **SSO**: Single Sign-On
- **TURN/STUN**: NAT traversal protocols for WebRTC
- **VoIP**: Voice over Internet Protocol
- **WAF**: Web Application Firewall
- **WebRTC**: Web Real-Time Communication
- **XR**: Extended Reality (VR/AR/MR)

---

## Appendix B: Key Contacts

- **Platform Owner**: Ryan Coleman (coleman.ryan@gmail.com)
- **Company**: After Dark Systems
- **Primary Domain**: https://www.aeims.app
- **Support Email**: info@aeims.app
- **Operator Support**: operators@aeims.app (planned)
- **Customer Support**: support@aeims.app (planned)

---

## Appendix C: Document Revision History

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0.0 | 2025-10-13 | Ryan Coleman | Initial PRD creation based on comprehensive project analysis |
| 2.0.0 | 2025-10-13 | Ryan Coleman | Added billing state machine requirements from /tmp/other_consideratios.txt |

---

**End of Product Requirements Document**

*This PRD is a living document and should be updated as the platform evolves. All stakeholders should refer to this document for authoritative information about AEIMS platform requirements, architecture, and roadmap.*
