// Support Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initSupportForms();
    initKnowledgeBaseSearch();
});

// Initialize support forms
function initSupportForms() {
    const ticketForm = document.getElementById('supportTicketForm');
    const emergencyForm = document.getElementById('emergencyForm');

    if (ticketForm) {
        ticketForm.addEventListener('submit', handleTicketSubmission);
    }

    if (emergencyForm) {
        emergencyForm.addEventListener('submit', handleEmergencySubmission);
    }
}

// Handle ticket form submission
function handleTicketSubmission(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    // Validate form
    if (!validateTicketForm(data)) {
        return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Submitting...';
    submitButton.disabled = true;

    // Submit ticket
    fetch('support-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ...data,
            type: 'ticket'
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showSupportNotification(
                `Ticket submitted successfully! Ticket #${result.ticket_id}. Expected response time: ${getResponseTime(data.priority)}.`,
                'success'
            );
            form.reset();
        } else {
            showSupportNotification(result.message || 'An error occurred. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Ticket submission error:', error);
        showSupportNotification('Network error. Please check your connection and try again.', 'error');
    })
    .finally(() => {
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}

// Handle emergency form submission
function handleEmergencySubmission(e) {
    e.preventDefault();

    const form = e.target;
    const formData = new FormData(form);
    const data = Object.fromEntries(formData);

    // Validate form
    if (!validateEmergencyForm(data)) {
        return;
    }

    // Show loading state
    const submitButton = form.querySelector('button[type="submit"]');
    const originalText = submitButton.textContent;
    submitButton.textContent = 'Submitting Emergency Request...';
    submitButton.disabled = true;

    // Submit emergency request
    fetch('support-handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            ...data,
            type: 'emergency'
        })
    })
    .then(response => response.json())
    .then(result => {
        if (result.success) {
            showSupportNotification(
                `Emergency request submitted! Ticket #${result.ticket_id}. Our emergency team has been notified and will contact you within 1 hour.`,
                'success'
            );
            form.reset();
        } else {
            showSupportNotification(result.message || 'An error occurred. Please try again.', 'error');
        }
    })
    .catch(error => {
        console.error('Emergency submission error:', error);
        showSupportNotification('Network error. Please check your connection and try again.', 'error');
    })
    .finally(() => {
        submitButton.textContent = originalText;
        submitButton.disabled = false;
    });
}

// Validate ticket form
function validateTicketForm(data) {
    const required = ['name', 'email', 'priority', 'category', 'subject', 'description'];
    const errors = [];

    for (let field of required) {
        if (!data[field] || data[field].trim() === '') {
            errors.push(`${field.charAt(0).toUpperCase() + field.slice(1)} is required`);
        }
    }

    // Email validation
    if (data.email && !isValidEmail(data.email)) {
        errors.push('Invalid email address');
    }

    if (errors.length > 0) {
        showSupportNotification('Please fix the following errors: ' + errors.join(', '), 'error');
        return false;
    }

    return true;
}

// Validate emergency form
function validateEmergencyForm(data) {
    const required = ['name', 'phone', 'email', 'domain', 'issue_type', 'description', 'impact'];
    const errors = [];

    for (let field of required) {
        if (!data[field] || data[field].trim() === '') {
            errors.push(`${field.replace('_', ' ').charAt(0).toUpperCase() + field.replace('_', ' ').slice(1)} is required`);
        }
    }

    // Email validation
    if (data.email && !isValidEmail(data.email)) {
        errors.push('Invalid email address');
    }

    // Phone validation
    if (data.phone && !isValidPhone(data.phone)) {
        errors.push('Invalid phone number');
    }

    if (errors.length > 0) {
        showSupportNotification('Please fix the following errors: ' + errors.join(', '), 'error');
        return false;
    }

    return true;
}

// Email validation helper
function isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
}

// Phone validation helper
function isValidPhone(phone) {
    const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
    return phoneRegex.test(phone.replace(/[\s\-\(\)]/g, ''));
}

// Get response time based on priority
function getResponseTime(priority) {
    const responseTimes = {
        'critical': '1 hour',
        'high': '4 hours',
        'medium': '24 hours',
        'low': '72 hours'
    };
    return responseTimes[priority] || '24 hours';
}

// Support notification system
function showSupportNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.support-notification');
    existingNotifications.forEach(notification => notification.remove());

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `support-notification support-notification-${type}`;
    notification.innerHTML = `
        <div class="support-notification-content">
            <span class="support-notification-message">${message}</span>
            <button class="support-notification-close">&times;</button>
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
        max-width: 400px;
        line-height: 1.5;
    `;

    // Add to page
    document.body.appendChild(notification);

    // Animate in
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);

    // Close functionality
    const closeBtn = notification.querySelector('.support-notification-close');
    closeBtn.addEventListener('click', () => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => notification.remove(), 300);
    });

    // Auto close after 8 seconds for longer messages
    setTimeout(() => {
        if (notification.parentNode) {
            notification.style.transform = 'translateX(100%)';
            setTimeout(() => notification.remove(), 300);
        }
    }, 8000);
}

// Knowledge base search functionality
function initKnowledgeBaseSearch() {
    const searchInput = document.getElementById('kb-search');
    const searchButton = document.querySelector('.search-btn');

    if (searchInput && searchButton) {
        searchButton.addEventListener('click', performSearch);
        searchInput.addEventListener('keypress', function(e) {
            if (e.key === 'Enter') {
                performSearch();
            }
        });
    }
}

// Perform knowledge base search
function performSearch() {
    const searchInput = document.getElementById('kb-search');
    const query = searchInput.value.trim();

    if (query.length < 2) {
        showSupportNotification('Please enter at least 2 characters to search.', 'error');
        return;
    }

    // Show loading state
    const searchButton = document.querySelector('.search-btn');
    const originalText = searchButton.textContent;
    searchButton.textContent = '⏳';

    // Simulate search (in a real implementation, this would query a search API)
    setTimeout(() => {
        // Reset button
        searchButton.textContent = originalText;

        // Show search results (placeholder)
        showSearchResults(query);
    }, 1000);
}

// Show search results
function showSearchResults(query) {
    // This is a placeholder for search results
    // In a real implementation, you would display actual search results
    showSupportNotification(
        `Search completed for "${query}". Results would be displayed here in a real implementation.`,
        'info'
    );
}

// Login required function for protected links
function loginRequired() {
    showSupportNotification(
        'This documentation requires customer login. Please <a href="login.php" style="color: white; text-decoration: underline;">log in</a> to access.',
        'info'
    );
}

// Priority selection helper
document.addEventListener('change', function(e) {
    if (e.target.id === 'ticket-priority') {
        const priority = e.target.value;
        const responseTimeText = getResponseTime(priority);

        // Update UI to show expected response time
        let responseDisplay = document.getElementById('response-time-display');
        if (!responseDisplay) {
            responseDisplay = document.createElement('div');
            responseDisplay.id = 'response-time-display';
            responseDisplay.style.cssText = `
                margin-top: 0.5rem;
                padding: 0.5rem;
                background: var(--background);
                border-radius: 4px;
                font-size: 0.9rem;
                color: var(--primary-color);
            `;
            e.target.parentNode.appendChild(responseDisplay);
        }

        if (priority) {
            responseDisplay.textContent = `Expected response time: ${responseTimeText}`;
        } else {
            responseDisplay.textContent = '';
        }
    }
});

// Form field auto-population for returning users
function populateFormFields() {
    // Check if user has submitted before (from localStorage)
    const savedUserData = localStorage.getItem('aeims_support_user');
    if (savedUserData) {
        try {
            const userData = JSON.parse(savedUserData);

            // Populate name and email fields if they exist
            const nameField = document.querySelector('#ticket-name, #emergency-name');
            const emailField = document.querySelector('#ticket-email, #emergency-email');

            if (nameField && userData.name) nameField.value = userData.name;
            if (emailField && userData.email) emailField.value = userData.email;
        } catch (e) {
            console.log('Error loading saved user data:', e);
        }
    }
}

// Save user data for future forms
function saveUserData(data) {
    if (data.name && data.email) {
        const userData = {
            name: data.name,
            email: data.email
        };
        localStorage.setItem('aeims_support_user', JSON.stringify(userData));
    }
}

// Initialize form auto-population when page loads
document.addEventListener('DOMContentLoaded', populateFormFields);

console.log('%c🎫 AEIMS Support System', 'color: #ef4444; font-size: 16px; font-weight: bold;');
console.log('%cSupport available 24/7/365', 'color: #a1a1aa; font-size: 12px;');