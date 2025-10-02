# AEIMS Showcase Website v2.0

A modern, responsive website showcasing the Adult Entertainment Information Management System (AEIMS) by After Dark Systems, featuring an integrated agent management system and comprehensive legal compliance.

## Features

- **Modern Design**: Responsive, mobile-first design with dark theme
- **Interactive Elements**: Animated statistics, smooth scrolling, and dynamic pricing tabs
- **Contact Form**: Functional PHP contact form with validation and email notifications
- **Agent Management**: Complete operator/agent dashboard with authentication
- **Legal Compliance**: Comprehensive legal documentation and terms
- **Showcase**: Comprehensive feature overview and site portfolio
- **Pricing**: Flexible licensing options with clear pricing tiers
- **Infrastructure**: Terraform-based deployment automation

## Technologies Used

- **Frontend**: HTML5, CSS3, JavaScript (ES6+)
- **Backend**: PHP 8.2+
- **Styling**: Custom CSS with CSS Grid and Flexbox
- **Fonts**: Inter font family from Google Fonts
- **Icons**: Unicode emoji icons

## File Structure

```
aeims.app/
├── index.php               # Main website page (updated to PHP)
├── legal.php               # Legal compliance and terms page
├── contact-handler.php     # Contact form processing
├── CHANGELOG.md            # Version history and changes
├── README.md               # This file
├── agents/                 # Agent management system
│   ├── index.php          # Agent portal entry point
│   ├── login.php          # Secure agent authentication
│   ├── dashboard.php      # Agent dashboard interface
│   ├── config.php         # Agent system configuration
│   ├── cookies.txt        # Session data storage
│   ├── data/
│   │   └── operators.json # Operator/agent data storage
│   └── includes/
│       └── OperatorAuth.php # Authentication handling
├── infrastructure/         # Deployment automation
│   └── terraform/         # Infrastructure as Code
│       ├── main.tf        # Main infrastructure configuration
│       └── dns-only.tf    # DNS-only deployment option
├── assets/
│   ├── css/
│   │   └── style.css      # Enhanced stylesheet with agent styling
│   ├── js/
│   │   └── script.js      # Main JavaScript file
│   └── images/            # Image assets (if needed)
└── logs/                  # Contact form logs (auto-created)
```

## Setup Instructions

### Prerequisites

- Web server with PHP 8.2+ support
- Mail server configuration for contact form
- Modern web browser

### Installation

1. **Clone or download** the project files to your web server directory

2. **Configure PHP mail settings** in your server configuration or update `contact-handler.php` with SMTP settings

3. **Set file permissions**:
   ```bash
   chmod 755 contact-handler.php
   chmod 755 assets/
   chmod -R 644 assets/css/ assets/js/
   ```

4. **Create logs directory** (if not auto-created):
   ```bash
   mkdir logs
   chmod 755 logs
   ```

### Configuration

#### Contact Form Configuration

Edit `contact-handler.php` to configure:

```php
$config = [
    'recipient_email' => 'your-email@domain.com',  // Change this
    'subject_prefix' => '[AEIMS License Inquiry]',
    'from_email' => 'noreply@yourdomain.com',      // Change this
    'from_name' => 'AEIMS Contact Form',
    'max_message_length' => 2000,
    'required_fields' => ['name', 'email', 'message']
];
```

#### Styling Customization

Edit `assets/css/style.css` to customize:

- Colors: Modify CSS custom properties in `:root`
- Typography: Update font families and sizes
- Layout: Adjust grid and flexbox properties
- Animations: Modify keyframes and transitions

## Features Overview

### 🏠 Homepage Sections

1. **Hero Section**
   - Animated statistics counter
   - Call-to-action buttons
   - Platform preview cards

2. **Features Section**
   - Six key platform features
   - Interactive hover effects
   - Responsive grid layout

3. **Powered By Section**
   - Showcase of sites using AEIMS
   - Six featured domains
   - Service type indicators

4. **Pricing Section**
   - Three pricing models (Per User, Per Domain, Enterprise)
   - Tabbed interface
   - Featured plan highlighting

5. **Contact Section**
   - Comprehensive contact form
   - Real-time validation
   - Success/error notifications

### 🎨 Design Features

- **Dark Theme**: Professional cyberpunk-inspired design
- **Gradient Elements**: Modern gradient text and buttons
- **Animations**: Smooth scroll animations and hover effects
- **Responsive**: Mobile-first responsive design
- **Performance**: Optimized loading and smooth interactions

### 📱 Mobile Responsive

- Hamburger navigation menu
- Stacked layouts for small screens
- Touch-friendly interface elements
- Optimized typography scaling

## Contact Form Features

### Security Features

- Input validation and sanitization
- CSRF protection through method verification
- Rate limiting (5 submissions per hour per IP)
- SQL injection prevention
- XSS protection through input sanitization

### Functionality

- Required field validation
- Email format validation
- Message length limits
- Automatic logging of submissions
- Email notifications to administrator
- JSON API response format

### Email Template

The contact form generates structured emails with:
- Contact information
- Business requirements
- User message
- Submission metadata (timestamp, IP, user agent)

## Browser Compatibility

- **Modern Browsers**: Chrome 80+, Firefox 75+, Safari 13+, Edge 80+
- **Features Used**: CSS Grid, Flexbox, ES6+ JavaScript
- **Fallbacks**: Graceful degradation for older browsers

## Performance Optimizations

- **CSS**: Optimized selectors and minimal repaints
- **JavaScript**: Event delegation and debounced events
- **Images**: Optimized loading (when images are added)
- **Fonts**: Preconnect hints for Google Fonts

## SEO Features

- **Meta Tags**: Proper title, description, and viewport tags
- **Semantic HTML**: Proper heading hierarchy and semantic elements
- **URL Structure**: Clean, descriptive URLs
- **Performance**: Fast loading times

## Accessibility

- **ARIA Labels**: Screen reader support
- **Keyboard Navigation**: Full keyboard accessibility
- **Color Contrast**: WCAG compliant color ratios
- **Focus Management**: Visible focus indicators

## Deployment

### Production Deployment

1. **Upload files** to your web server
2. **Configure domain** to point to the project directory
3. **Set up SSL certificate** for HTTPS
4. **Configure mail server** for contact form functionality
5. **Test all functionality** including contact form submission

### Testing

- Test responsive design on various devices
- Verify contact form email delivery
- Check form validation and error handling
- Test navigation and smooth scrolling
- Verify all animations and interactions

## Maintenance

### Regular Tasks

- Monitor contact form logs in `/logs/` directory
- Update content as needed
- Check for broken links
- Monitor website performance
- Update dependencies if any are added

### Log Files

- `logs/contact-submissions.log`: All form submissions
- `logs/rate-limit.json`: Rate limiting data

## Customization

### Adding New Sections

1. Add HTML structure to `index.html`
2. Add corresponding styles to `assets/css/style.css`
3. Add JavaScript functionality to `assets/js/script.js` if needed
4. Update navigation menu if required

### Modifying Pricing

Update the pricing tables in the HTML and modify the tab switching logic in JavaScript if you add new pricing tiers.

### Adding Images

1. Add images to `assets/images/` directory
2. Reference them in HTML/CSS
3. Optimize for web (WebP format recommended)
4. Add alt text for accessibility

## Agent Management System

### Features

- **Secure Authentication**: Login system with session management
- **Agent Dashboard**: Comprehensive interface for call management
- **Operator Management**: JSON-based operator data storage
- **Role-Based Access**: Different permission levels for agents
- **Session Security**: Timeout handling and secure session management

### Usage

1. **Access Agent Portal**: Navigate to `/agents/`
2. **Login**: Use operator credentials defined in `agents/data/operators.json`
3. **Dashboard**: Access call management and operator tools
4. **Security**: Automatic session timeout and secure authentication

### Configuration

Edit `agents/config.php` to configure:

```php
$config = [
    'session_timeout' => 3600,  // Session timeout in seconds
    'max_login_attempts' => 3,  // Maximum failed login attempts
    'operator_data_file' => 'data/operators.json',
    'session_name' => 'AEIMS_AGENT_SESSION'
];
```

### Adding Operators

Add new operators to `agents/data/operators.json`:

```json
{
    "operators": [
        {
            "id": "op1",
            "username": "operator1",
            "password_hash": "hashed_password",
            "role": "agent",
            "active": true
        }
    ]
}
```

## Legal Compliance

### Features

- **Terms of Service**: Comprehensive user agreement
- **Privacy Policy**: Data protection and usage policies
- **Age Verification**: Adult content compliance requirements
- **Liability Disclaimers**: Legal protection for operators
- **Regulatory Compliance**: Adult entertainment industry standards

### Access

- Navigate to `/legal.php` for complete legal documentation
- Required reading for all users and operators
- Regular updates to maintain compliance

## Infrastructure Deployment

### Terraform Support

The website includes infrastructure automation:

#### DNS-Only Deployment

```bash
cd infrastructure/terraform/
terraform init
terraform plan -var-file="dns-only.tfvars"
terraform apply
```

#### Full Infrastructure

```bash
cd infrastructure/terraform/
terraform init
terraform plan
terraform apply
```

### Infrastructure Features

- **DNS Management**: Automated domain configuration
- **SSL Certificates**: Automatic HTTPS setup
- **Load Balancing**: High availability configuration
- **Monitoring**: Health checks and alerting
- **Scaling**: Auto-scaling based on demand

## Support

For technical support or questions about AEIMS licensing:
- **Email**: rjc@afterdarksys.com
- **Response Time**: Within 24 hours

---

**Built with ❤️ by After Dark Systems**

*AEIMS - The premier adult entertainment platform management system*