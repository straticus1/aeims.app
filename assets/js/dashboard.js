// Dashboard JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initNavigation();
    initQuickHelp();
    initPerformanceChart();
    initTooltips();
});

// Initialize navigation functionality
function initNavigation() {
    // Handle dropdown toggles on mobile
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');

    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            // Only prevent default on mobile
            if (window.innerWidth <= 768) {
                e.preventDefault();

                const dropdown = this.parentElement;
                const menu = dropdown.querySelector('.dropdown-menu');

                // Close other dropdowns
                dropdownToggles.forEach(otherToggle => {
                    if (otherToggle !== this) {
                        otherToggle.parentElement.classList.remove('active');
                    }
                });

                // Toggle current dropdown
                dropdown.classList.toggle('active');
            }
        });
    });

    // Handle user dropdown
    const userBtn = document.querySelector('.user-btn');
    if (userBtn) {
        userBtn.addEventListener('click', function(e) {
            if (window.innerWidth <= 768) {
                e.preventDefault();
                const dropdown = this.parentElement;
                dropdown.classList.toggle('active');
            }
        });
    }

    // Close dropdowns when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.dropdown') && !e.target.closest('.user-dropdown')) {
            document.querySelectorAll('.dropdown, .user-dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });

    // Handle window resize
    window.addEventListener('resize', function() {
        if (window.innerWidth > 768) {
            // Remove active states on desktop
            document.querySelectorAll('.dropdown, .user-dropdown').forEach(dropdown => {
                dropdown.classList.remove('active');
            });
        }
    });
}

// Quick Help Modal functionality
function initQuickHelp() {
    window.showQuickHelp = function() {
        const modal = document.getElementById('quickHelpModal');
        if (modal) {
            modal.classList.remove('hidden');
            document.body.style.overflow = 'hidden';
        }
    };

    window.hideQuickHelp = function() {
        const modal = document.getElementById('quickHelpModal');
        if (modal) {
            modal.classList.add('hidden');
            document.body.style.overflow = '';
        }
    };

    // Close modal when clicking outside
    const modal = document.getElementById('quickHelpModal');
    if (modal) {
        modal.addEventListener('click', function(e) {
            if (e.target === modal) {
                hideQuickHelp();
            }
        });
    }

    // Close modal with Escape key
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            hideQuickHelp();
        }
    });
}

// Initialize performance chart
function initPerformanceChart() {
    const canvas = document.getElementById('performanceChart');
    if (!canvas) return;

    // Check if Chart.js is available
    if (typeof Chart === 'undefined') {
        console.log('Chart.js not loaded, skipping chart initialization');
        return;
    }

    const ctx = canvas.getContext('2d');

    // Sample data - in production, this would come from your analytics API
    const data = {
        labels: ['00:00', '04:00', '08:00', '12:00', '16:00', '20:00'],
        datasets: [{
            label: 'Calls',
            data: [45, 32, 78, 92, 156, 134],
            borderColor: '#3b82f6',
            backgroundColor: 'rgba(59, 130, 246, 0.1)',
            tension: 0.4
        }, {
            label: 'Messages',
            data: [125, 89, 234, 287, 389, 312],
            borderColor: '#10b981',
            backgroundColor: 'rgba(16, 185, 129, 0.1)',
            tension: 0.4
        }]
    };

    const config = {
        type: 'line',
        data: data,
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        usePointStyle: true,
                        padding: 20
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: '#e2e8f0'
                    }
                },
                x: {
                    grid: {
                        color: '#e2e8f0'
                    }
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 6
                }
            }
        }
    };

    new Chart(ctx, config);

    // Handle chart period buttons
    const chartButtons = document.querySelectorAll('.chart-btn');
    chartButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            // Remove active class from all buttons
            chartButtons.forEach(b => b.classList.remove('active'));
            // Add active class to clicked button
            this.classList.add('active');

            // In production, you would update the chart data based on the selected period
            const period = this.dataset.period;
            console.log('Chart period changed to:', period);
        });
    });
}

// Initialize tooltips
function initTooltips() {
    // Simple tooltip implementation
    const tooltipElements = document.querySelectorAll('[title]');

    tooltipElements.forEach(element => {
        let tooltip = null;

        element.addEventListener('mouseenter', function() {
            const title = this.getAttribute('title');
            if (!title) return;

            // Create tooltip
            tooltip = document.createElement('div');
            tooltip.className = 'tooltip';
            tooltip.textContent = title;
            tooltip.style.cssText = `
                position: absolute;
                background: #1f2937;
                color: white;
                padding: 8px 12px;
                border-radius: 6px;
                font-size: 12px;
                white-space: nowrap;
                z-index: 10000;
                pointer-events: none;
                opacity: 0;
                transition: opacity 0.2s ease;
            `;

            document.body.appendChild(tooltip);

            // Position tooltip
            const rect = this.getBoundingClientRect();
            tooltip.style.left = rect.left + (rect.width / 2) - (tooltip.offsetWidth / 2) + 'px';
            tooltip.style.top = rect.top - tooltip.offsetHeight - 8 + 'px';

            // Show tooltip
            setTimeout(() => {
                if (tooltip) tooltip.style.opacity = '1';
            }, 100);

            // Remove title to prevent default tooltip
            this.removeAttribute('title');
            this.dataset.originalTitle = title;
        });

        element.addEventListener('mouseleave', function() {
            if (tooltip) {
                tooltip.style.opacity = '0';
                setTimeout(() => {
                    if (tooltip && tooltip.parentNode) {
                        tooltip.parentNode.removeChild(tooltip);
                    }
                }, 200);
                tooltip = null;
            }

            // Restore title
            if (this.dataset.originalTitle) {
                this.setAttribute('title', this.dataset.originalTitle);
                delete this.dataset.originalTitle;
            }
        });
    });
}

// Utility functions
function formatNumber(num) {
    if (num >= 1000000) {
        return (num / 1000000).toFixed(1) + 'M';
    } else if (num >= 1000) {
        return (num / 1000).toFixed(1) + 'K';
    }
    return num.toString();
}

function formatCurrency(amount) {
    return new Intl.NumberFormat('en-US', {
        style: 'currency',
        currency: 'USD'
    }).format(amount);
}

// Auto-refresh functionality for live data
function startAutoRefresh() {
    setInterval(() => {
        // In production, you would fetch updated data here
        updateLiveStats();
    }, 30000); // Refresh every 30 seconds
}

function updateLiveStats() {
    // Placeholder for live stats update
    // In production, this would make an AJAX call to get fresh data
    console.log('Updating live stats...');
}

// Initialize auto-refresh
startAutoRefresh();

// Console branding
console.log('%c📊 AEIMS Dashboard', 'color: #3b82f6; font-size: 16px; font-weight: bold;');
console.log('%cDashboard loaded successfully', 'color: #10b981; font-size: 12px;');