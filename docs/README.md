# AEIMS Platform Documentation

Welcome to the comprehensive documentation for AEIMS Platform v2.3.0.

## Documentation Overview

This directory contains detailed documentation for the AEIMS adult entertainment platform management system.

### Available Documentation

- **[DEPLOYMENT.md](DEPLOYMENT.md)** - Complete deployment guide with Docker, virtual hosts, and production setup
- **[API.md](API.md)** - Comprehensive API reference for all platform services
- **[README.md](README.md)** - This overview file

### Quick Links

- **Main README**: [../README.md](../README.md) - Platform overview and setup
- **Changelog**: [../CHANGELOG.md](../CHANGELOG.md) - Version history and changes
- **Testing Guide**: [../TESTING.md](../TESTING.md) - Security and integration testing
- **Quick Login Guide**: [../QUICK_LOGIN_GUIDE.md](../QUICK_LOGIN_GUIDE.md) - Authentication setup

### Platform Features

The AEIMS Platform v2.3.0 includes:

#### Multi-Site Infrastructure
- Complete virtual host support with nginx routing
- Container orchestration with Docker
- Site-specific configurations and branding
- Centralized management with individual site autonomy

#### Authentication & Security
- Unified customer authentication across all sites
- Operator authentication with role-based access
- Single Sign-On (SSO) implementation
- Advanced session management and security features

#### Core Services
- **Content Marketplace** - Digital content sales and management
- **Messaging System** - Real-time communication between users
- **Chat Rooms** - Group chat with invite and management features
- **Notification System** - Real-time notifications and alerts
- **ID Verification** - Complete identity verification workflow

#### Administrative Features
- Comprehensive admin dashboard (fixed blank page issues)
- Multi-site management interface
- Operator management and verification
- System monitoring and health checks

### Getting Started

1. **Platform Setup**: Start with [../README.md](../README.md) for basic setup
2. **Deployment**: Follow [DEPLOYMENT.md](DEPLOYMENT.md) for production deployment
3. **API Integration**: Use [API.md](API.md) for service integration
4. **Testing**: Reference [../TESTING.md](../TESTING.md) for validation

### Architecture

The platform follows a modular architecture:

```
Core Platform
├── Multi-Site Management
│   ├── Site-Specific Configurations
│   ├── Virtual Host Routing
│   └── SSO Integration
├── Service Layer
│   ├── Messaging
│   ├── Content Marketplace
│   ├── Chat Rooms
│   ├── Notifications
│   └── ID Verification
├── Authentication Layer
│   ├── Customer Authentication
│   ├── Operator Authentication
│   └── Admin Authentication
└── Infrastructure Layer
    ├── Docker Containerization
    ├── Nginx Configuration
    └── SSL/TLS Management
```

### Recent Updates (v2.3.0)

- **Fixed Dashboard Issues**: Resolved all blank page and rendering problems
- **Enhanced Virtual Hosts**: Complete nginx routing and container networking
- **Improved Authentication**: Enhanced security and session management
- **Added Testing Suite**: Playwright-based security and integration tests
- **Enhanced Documentation**: Comprehensive guides and API documentation

### Support and Contact

For technical support, questions, or assistance:

- **Email**: rjc@afterdarksys.com
- **Response Time**: Within 24 hours
- **Documentation Updates**: Check changelog for latest features

### Contributing

When updating documentation:

1. Update relevant files in this directory
2. Update main README.md if needed
3. Add entries to CHANGELOG.md
4. Test all examples and code snippets

### License

This documentation is part of the AEIMS Platform by After Dark Systems.

---

**Built with ❤️ by After Dark Systems**

*AEIMS - The premier adult entertainment platform management system*