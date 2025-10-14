// Login Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initLoginForm();
    initAnimations();
    populateCredentialsDemo();
});

// Initialize login form functionality
function initLoginForm() {
    const form = document.querySelector('.login-form');
    const submitButton = document.querySelector('.login-btn');

    if (form && submitButton) {
        form.addEventListener('submit', function(e) {
            // Add loading state
            submitButton.classList.add('loading');
            submitButton.disabled = true;
            submitButton.textContent = 'Signing In...';

            // Let the form submit normally, but prevent double submission
            setTimeout(() => {
                if (submitButton.classList.contains('loading')) {
                    submitButton.classList.remove('loading');
                    submitButton.disabled = false;
                    submitButton.textContent = 'Sign In';
                }
            }, 5000); // Reset after 5 seconds if no redirect
        });

        // Form validation
        const usernameInput = document.getElementById('username');
        const passwordInput = document.getElementById('password');

        usernameInput.addEventListener('blur', validateUsername);
        passwordInput.addEventListener('blur', validatePassword);
    }

    // Remember me functionality
    const rememberCheckbox = document.querySelector('input[name="remember"]');
    const usernameInput = document.getElementById('username');

    if (rememberCheckbox && usernameInput) {
        // Load remembered username
        const rememberedUsername = localStorage.getItem('aeims_remembered_username');
        if (rememberedUsername) {
            usernameInput.value = rememberedUsername;
            rememberCheckbox.checked = true;
        }

        // Save username when form is submitted
        document.querySelector('.login-form').addEventListener('submit', function() {
            if (rememberCheckbox.checked) {
                localStorage.setItem('aeims_remembered_username', usernameInput.value);
            } else {
                localStorage.removeItem('aeims_remembered_username');
            }
        });
    }
}

// Validate username/email input
function validateUsername() {
    const input = document.getElementById('username');
    const value = input.value.trim();

    if (value.length === 0) {
        showFieldError(input, 'Username or email is required');
        return false;
    }

    // Basic email validation if it looks like an email
    if (value.includes('@')) {
        const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        if (!emailRegex.test(value)) {
            showFieldError(input, 'Please enter a valid email address');
            return false;
        }
    }

    clearFieldError(input);
    return true;
}

// Validate password input
function validatePassword() {
    const input = document.getElementById('password');
    const value = input.value;

    if (value.length === 0) {
        showFieldError(input, 'Password is required');
        return false;
    }

    if (value.length < 6) {
        showFieldError(input, 'Password must be at least 6 characters');
        return false;
    }

    clearFieldError(input);
    return true;
}

// Show field error
function showFieldError(input, message) {
    clearFieldError(input);

    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    errorDiv.style.cssText = `
        color: var(--accent-color);
        font-size: 0.8rem;
        margin-top: 0.25rem;
        animation: fadeIn 0.3s ease;
    `;

    input.parentNode.appendChild(errorDiv);
    input.style.borderColor = 'var(--accent-color)';
}

// Clear field error
function clearFieldError(input) {
    const existingError = input.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    input.style.borderColor = '';
}

// Initialize animations
function initAnimations() {
    // Animate login card entrance
    const loginCard = document.querySelector('.login-card');
    const loginInfo = document.querySelector('.login-info');

    if (loginCard && loginInfo) {
        loginCard.style.animation = 'slideInLeft 0.8s ease-out';
        loginInfo.style.animation = 'slideInRight 0.8s ease-out';
    }

    // Add CSS for animations
    const style = document.createElement('style');
    style.textContent = `
        @keyframes slideInLeft {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideInRight {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }
    `;
    document.head.appendChild(style);
}

// Demo credentials population
function populateCredentialsDemo() {
    const demoAccounts = document.querySelectorAll('.demo-account');

    demoAccounts.forEach(account => {
        account.addEventListener('click', function() {
            const text = this.textContent;
            let username = '';
            let password = '';

            // Extract credentials from demo account text
            if (text.includes('demo@example.com')) {
                username = 'demo@example.com';
                password = 'password123';
            } else if (text.includes('admin')) {
                username = 'admin';
                password = 'admin123';
            }

            if (username && password) {
                // Fill in the form
                document.getElementById('username').value = username;
                document.getElementById('password').value = password;

                // Add visual feedback
                this.style.backgroundColor = 'rgba(99, 102, 241, 0.2)';
                setTimeout(() => {
                    this.style.backgroundColor = '';
                }, 500);

                // Show notification
                showLoginNotification('Demo credentials filled in. Click "Sign In" to continue.', 'info');
            }
        });

        // Add cursor pointer to indicate clickability
        account.style.cursor = 'pointer';
        account.style.transition = 'background-color 0.3s ease';
        account.style.padding = '0.5rem';
        account.style.borderRadius = '4px';

        account.addEventListener('mouseenter', function() {
            this.style.backgroundColor = 'rgba(99, 102, 241, 0.1)';
        });

        account.addEventListener('mouseleave', function() {
            this.style.backgroundColor = '';
        });
    });
}

// Login notification system
function showLoginNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.login-notification');
    existingNotifications.forEach(notification => notification.remove());

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `login-notification login-notification-${type}`;
    notification.innerHTML = `
        <div class="login-notification-content">
            <span class="login-notification-message">${message}</span>
            <button class="login-notification-close">&times;</button>
        </div>
    `;

    // Add styles
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        background: ${type === 'success' ? '#10b981' : type === 'error' ? '#ef4444' : '#6366f1'};
        color: white;
        padding: 1rem 1.5rem;
        border-radius: 8px;
        box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1);
        z-index: 10000;
        transform: translateX(100%);
        transition: transform 0.3s ease;
        max-width: 350px;
        line-height: 1.5;
    `;

    // Add to page
    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);

    // Close functionality
    const closeBtn = notification.querySelector('.login-notification-close');
    closeBtn.addEventListener('click', () => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    });

    // Auto close after 5 seconds
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, 5000);
}

// Keyboard shortcuts
document.addEventListener('keydown', function(e) {
    // Alt + D for demo customer
    if (e.altKey && e.key === 'd') {
        e.preventDefault();
        document.getElementById('username').value = 'demo@example.com';
        document.getElementById('password').value = 'password123';
        showLoginNotification('Demo customer credentials loaded (Alt+D)', 'info');
    }

    // Alt + A for admin
    if (e.altKey && e.key === 'a') {
        e.preventDefault();
        document.getElementById('username').value = 'admin';
        document.getElementById('password').value = 'admin123';
        showLoginNotification('Admin credentials loaded (Alt+A)', 'info');
    }
});

// Password visibility toggle
function initPasswordToggle() {
    const passwordInput = document.getElementById('password');
    const toggleButton = document.createElement('button');
    toggleButton.type = 'button';
    toggleButton.className = 'password-toggle';
    toggleButton.innerHTML = '👁️';
    toggleButton.style.cssText = `
        position: absolute;
        right: 10px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        color: var(--text-secondary);
        cursor: pointer;
        font-size: 1rem;
        padding: 5px;
    `;

    // Make password field container relative
    passwordInput.parentNode.style.position = 'relative';
    passwordInput.style.paddingRight = '40px';
    passwordInput.parentNode.appendChild(toggleButton);

    toggleButton.addEventListener('click', function() {
        if (passwordInput.type === 'password') {
            passwordInput.type = 'text';
            toggleButton.innerHTML = '🙈';
        } else {
            passwordInput.type = 'password';
            toggleButton.innerHTML = '👁️';
        }
    });
}

// Initialize password toggle
document.addEventListener('DOMContentLoaded', initPasswordToggle);

// Session timeout warning
function initSessionWarning() {
    // Warn users about session timeout (30 minutes)
    setTimeout(() => {
        if (document.visibilityState === 'visible') {
            showLoginNotification(
                'For security, you will be automatically logged out after 30 minutes of inactivity.',
                'info'
            );
        }
    }, 25 * 60 * 1000); // 25 minutes
}

// Security features
document.addEventListener('DOMContentLoaded', function() {
    // Disable right-click on sensitive elements
    const sensitiveElements = document.querySelectorAll('.admin-password, .demo-account');
    sensitiveElements.forEach(element => {
        element.addEventListener('contextmenu', function(e) {
            e.preventDefault();
        });
    });

    // Clear form on page unload for security
    window.addEventListener('beforeunload', function() {
        const passwordInput = document.getElementById('password');
        if (passwordInput) {
            passwordInput.value = '';
        }
    });
});

console.log('%c🔐 AEIMS Login System', 'color: #6366f1; font-size: 16px; font-weight: bold;');
console.log('%cKeyboard shortcuts: Alt+D (demo), Alt+A (admin)', 'color: #a1a1aa; font-size: 12px;');
console.log('%cAdmin: admin / admin123 | Demo: demo@example.com / password123', 'color: #ef4444; font-size: 12px; font-weight: bold;');