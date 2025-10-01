// Migration Page JavaScript

document.addEventListener('DOMContentLoaded', function() {
    initChecklist();
    initAnimations();
    loadChecklistProgress();
});

// Initialize checklist functionality
function initChecklist() {
    const checklistItems = document.querySelectorAll('.checklist-item input[type="checkbox"]');

    checklistItems.forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            const item = this.closest('.checklist-item');

            if (this.checked) {
                item.classList.add('completed');
                showMigrationNotification('✅ Checklist item completed!', 'success');
            } else {
                item.classList.remove('completed');
            }

            // Save progress
            saveChecklistProgress();
            updateChecklistProgress();
        });
    });

    // Initialize progress
    updateChecklistProgress();
}

// Save checklist progress to localStorage
function saveChecklistProgress() {
    const checklistData = {};
    const checkboxes = document.querySelectorAll('.checklist-item input[type="checkbox"]');

    checkboxes.forEach(checkbox => {
        checklistData[checkbox.id] = checkbox.checked;
    });

    localStorage.setItem('aeims_migration_checklist', JSON.stringify(checklistData));
}

// Load checklist progress from localStorage
function loadChecklistProgress() {
    const savedData = localStorage.getItem('aeims_migration_checklist');

    if (savedData) {
        try {
            const checklistData = JSON.parse(savedData);

            Object.keys(checklistData).forEach(checkboxId => {
                const checkbox = document.getElementById(checkboxId);
                if (checkbox) {
                    checkbox.checked = checklistData[checkboxId];
                    const item = checkbox.closest('.checklist-item');
                    if (checkbox.checked) {
                        item.classList.add('completed');
                    }
                }
            });

            updateChecklistProgress();
        } catch (e) {
            console.log('Error loading checklist progress:', e);
        }
    }
}

// Update checklist progress display
function updateChecklistProgress() {
    const checkboxes = document.querySelectorAll('.checklist-item input[type="checkbox"]');
    const completedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    const totalCount = checkboxes.length;
    const percentage = Math.round((completedCount / totalCount) * 100);

    // Create or update progress indicator
    let progressIndicator = document.getElementById('checklist-progress');
    if (!progressIndicator) {
        progressIndicator = document.createElement('div');
        progressIndicator.id = 'checklist-progress';
        progressIndicator.style.cssText = `
            position: fixed;
            bottom: 20px;
            right: 20px;
            background: var(--surface);
            padding: 1rem;
            border-radius: 8px;
            border: 1px solid var(--border);
            box-shadow: var(--shadow-lg);
            z-index: 1000;
            min-width: 200px;
        `;

        document.body.appendChild(progressIndicator);
    }

    progressIndicator.innerHTML = `
        <div style="color: var(--text-primary); font-weight: 600; margin-bottom: 0.5rem;">
            Migration Checklist
        </div>
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 0.5rem;">
            <span style="color: var(--text-secondary); font-size: 0.9rem;">Progress</span>
            <span style="color: var(--primary-color); font-weight: 600;">${completedCount}/${totalCount}</span>
        </div>
        <div style="background: var(--background); height: 8px; border-radius: 4px; overflow: hidden;">
            <div style="background: var(--gradient-primary); height: 100%; width: ${percentage}%; transition: width 0.3s ease;"></div>
        </div>
        <div style="color: var(--text-secondary); font-size: 0.8rem; margin-top: 0.5rem;">
            ${percentage}% Complete
        </div>
    `;

    // Hide progress indicator if nothing is checked
    if (completedCount === 0) {
        progressIndicator.style.display = 'none';
    } else {
        progressIndicator.style.display = 'block';
    }

    // Show completion message
    if (percentage === 100) {
        showMigrationNotification('🎉 Checklist completed! You\'re ready to request migration.', 'success');
    }
}

// Export checklist functionality
function exportChecklist() {
    const checklistData = {
        timestamp: new Date().toISOString(),
        progress: {},
        sections: {}
    };

    // Get all sections and their items
    const sections = document.querySelectorAll('.checklist-section');
    sections.forEach(section => {
        const sectionTitle = section.querySelector('h3').textContent;
        const items = section.querySelectorAll('.checklist-item');

        checklistData.sections[sectionTitle] = [];

        items.forEach(item => {
            const checkbox = item.querySelector('input[type="checkbox"]');
            const label = item.textContent.trim();
            const isCompleted = checkbox.checked;

            checklistData.sections[sectionTitle].push({
                item: label,
                completed: isCompleted
            });

            checklistData.progress[checkbox.id] = isCompleted;
        });
    });

    // Create downloadable file
    const dataStr = JSON.stringify(checklistData, null, 2);
    const dataBlob = new Blob([dataStr], { type: 'application/json' });
    const url = URL.createObjectURL(dataBlob);

    const link = document.createElement('a');
    link.href = url;
    link.download = `aeims-migration-checklist-${new Date().toISOString().split('T')[0]}.json`;
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
    URL.revokeObjectURL(url);

    showMigrationNotification('📥 Checklist exported successfully!', 'success');
}

// Request migration support
function requestMigration() {
    const checkboxes = document.querySelectorAll('.checklist-item input[type="checkbox"]');
    const completedCount = Array.from(checkboxes).filter(cb => cb.checked).length;
    const totalCount = checkboxes.length;
    const percentage = Math.round((completedCount / totalCount) * 100);

    if (percentage < 50) {
        showMigrationNotification(
            'Please complete at least 50% of the checklist before requesting migration support.',
            'error'
        );
        return;
    }

    // Redirect to support page with migration context
    const migrationUrl = new URL('support.php', window.location.origin);
    migrationUrl.searchParams.set('service', 'migration');
    migrationUrl.searchParams.set('progress', percentage);

    window.location.href = migrationUrl.toString();
}

// Initialize scroll animations
function initAnimations() {
    const animatedElements = document.querySelectorAll(
        '.timeline-item, .requirement-card, .migration-option, .challenge-card'
    );

    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry, index) => {
            if (entry.isIntersecting) {
                setTimeout(() => {
                    entry.target.style.animation = 'fadeInUp 0.6s ease-out forwards';
                }, index * 100);
                observer.unobserve(entry.target);
            }
        });
    }, {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    });

    animatedElements.forEach(element => {
        element.style.opacity = '0';
        observer.observe(element);
    });

    // Add CSS animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
    `;
    document.head.appendChild(style);
}

// Migration notification system
function showMigrationNotification(message, type = 'info') {
    // Remove existing notifications
    const existingNotifications = document.querySelectorAll('.migration-notification');
    existingNotifications.forEach(notification => notification.remove());

    // Create notification element
    const notification = document.createElement('div');
    notification.className = `migration-notification migration-notification-${type}`;
    notification.innerHTML = `
        <div class="migration-notification-content">
            <span class="migration-notification-message">${message}</span>
            <button class="migration-notification-close">&times;</button>
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
        z-index: 10001;
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
    const closeBtn = notification.querySelector('.migration-notification-close');
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

// Timeline progress tracking
function initTimelineProgress() {
    const timelineItems = document.querySelectorAll('.timeline-item');

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const number = entry.target.querySelector('.timeline-number');
                number.style.animation = 'pulse 0.6s ease-out';
            }
        });
    }, { threshold: 0.5 });

    timelineItems.forEach(item => {
        observer.observe(item);
    });

    // Add pulse animation
    const style = document.createElement('style');
    style.textContent = `
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.1); }
            100% { transform: scale(1); }
        }
    `;
    document.head.appendChild(style);
}

// Initialize timeline progress
document.addEventListener('DOMContentLoaded', initTimelineProgress);

// Interactive migration type selection
function initMigrationTypeSelection() {
    const migrationOptions = document.querySelectorAll('.migration-option');

    migrationOptions.forEach(option => {
        option.addEventListener('click', function() {
            // Remove selected class from all options
            migrationOptions.forEach(opt => opt.classList.remove('selected'));

            // Add selected class to clicked option
            this.classList.add('selected');

            // Get migration type
            const typeHeader = this.querySelector('.option-header h3').textContent;
            const typeName = typeHeader.split(' ')[1]; // Extract "Express", "Professional", "Enterprise"

            showMigrationNotification(
                `${typeName} Migration selected. Contact our team to get started!`,
                'info'
            );
        });

        // Add selection styles
        option.style.cursor = 'pointer';
    });

    // Add CSS for selection
    const style = document.createElement('style');
    style.textContent = `
        .migration-option.selected {
            border-color: var(--primary-color);
            transform: translateY(-5px);
            box-shadow: var(--shadow-lg);
        }

        .migration-option.selected .option-header {
            background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, transparent 100%);
        }
    `;
    document.head.appendChild(style);
}

// Initialize migration type selection
document.addEventListener('DOMContentLoaded', initMigrationTypeSelection);

// Form pre-population for migration requests
function prepareMigrationRequest() {
    const checklistData = localStorage.getItem('aeims_migration_checklist');
    const migrationData = {
        checklist_completed: false,
        completion_percentage: 0,
        timestamp: new Date().toISOString()
    };

    if (checklistData) {
        try {
            const parsed = JSON.parse(checklistData);
            const completedItems = Object.values(parsed).filter(Boolean).length;
            const totalItems = Object.keys(parsed).length;

            migrationData.checklist_completed = completedItems === totalItems;
            migrationData.completion_percentage = Math.round((completedItems / totalItems) * 100);
        } catch (e) {
            console.log('Error parsing checklist data:', e);
        }
    }

    // Store migration request data
    localStorage.setItem('aeims_migration_request', JSON.stringify(migrationData));

    return migrationData;
}

console.log('%c🚀 AEIMS Migration Guide', 'color: #6366f1; font-size: 16px; font-weight: bold;');
console.log('%cUse the interactive checklist to track your progress', 'color: #a1a1aa; font-size: 12px;');