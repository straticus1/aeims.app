/**
 * AEIMS Notification System
 * Real-time toast notifications via Server-Sent Events
 */

class NotificationSystem {
    constructor() {
        this.eventSource = null;
        this.notifications = [];
        this.settings = {
            enabled: true,
            sound: true,
            types: {
                chat: true,
                room_invite: true,
                mail: true,
                message_sent: true,
                system: true
            }
        };

        this.loadSettings();
        this.createToastContainer();
        this.connect();
    }

    loadSettings() {
        const saved = localStorage.getItem('notification_settings');
        if (saved) {
            try {
                this.settings = JSON.parse(saved);
            } catch (e) {
                console.error('Failed to load notification settings:', e);
            }
        }
    }

    saveSettings() {
        localStorage.setItem('notification_settings', JSON.stringify(this.settings));
    }

    createToastContainer() {
        if (document.getElementById('toast-container')) return;

        const container = document.createElement('div');
        container.id = 'toast-container';
        container.style.cssText = `
            position: fixed;
            top: 80px;
            right: 20px;
            z-index: 10000;
            display: flex;
            flex-direction: column;
            gap: 10px;
            pointer-events: none;
        `;
        document.body.appendChild(container);
    }

    connect() {
        if (!this.settings.enabled) return;

        try {
            this.eventSource = new EventSource('/api/notifications/stream.php');

            this.eventSource.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);

                    if (data.type === 'connected') {
                        console.log('Notification stream connected');
                        return;
                    }

                    this.handleNotification(data);
                } catch (e) {
                    console.error('Failed to parse notification:', e);
                }
            };

            this.eventSource.onerror = (error) => {
                console.error('Notification stream error:', error);
                this.eventSource.close();

                // Reconnect after 5 seconds
                setTimeout(() => this.connect(), 5000);
            };
        } catch (e) {
            console.error('Failed to connect to notification stream:', e);
        }
    }

    disconnect() {
        if (this.eventSource) {
            this.eventSource.close();
            this.eventSource = null;
        }
    }

    handleNotification(notification) {
        // Check if this notification type is enabled
        if (!this.settings.types[notification.type]) {
            return;
        }

        // Add to notifications array
        this.notifications.push(notification);

        // Show toast
        this.showToast(notification);

        // Play sound if enabled
        if (this.settings.sound) {
            this.playSound(notification.type);
        }
    }

    showToast(notification) {
        const toast = document.createElement('div');
        toast.className = `toast toast-${notification.type}`;
        toast.style.cssText = `
            background: linear-gradient(135deg, ${this.getTypeColor(notification.type)}, ${this.getTypeColorDark(notification.type)});
            color: white;
            padding: 16px 20px;
            border-radius: 12px;
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
            min-width: 300px;
            max-width: 400px;
            pointer-events: auto;
            cursor: pointer;
            transition: all 0.3s ease;
            animation: slideIn 0.3s ease;
        `;

        toast.innerHTML = `
            <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 8px;">
                <strong style="font-size: 1.1em;">${this.escapeHtml(notification.title)}</strong>
                <button onclick="this.parentElement.parentElement.remove()" style="background: none; border: none; color: white; font-size: 1.2em; cursor: pointer; padding: 0; margin-left: 10px;">&times;</button>
            </div>
            <div style="font-size: 0.95em; opacity: 0.95;">${this.escapeHtml(notification.message)}</div>
            ${notification.link ? `<div style="margin-top: 8px; font-size: 0.9em; opacity: 0.9;">Click to view</div>` : ''}
        `;

        toast.addEventListener('mouseenter', () => {
            toast.style.transform = 'translateX(-5px)';
            toast.style.boxShadow = '0 6px 20px rgba(0, 0, 0, 0.4)';
        });

        toast.addEventListener('mouseleave', () => {
            toast.style.transform = 'translateX(0)';
            toast.style.boxShadow = '0 4px 12px rgba(0, 0, 0, 0.3)';
        });

        if (notification.link) {
            toast.addEventListener('click', () => {
                window.location.href = notification.link;
            });
        }

        const container = document.getElementById('toast-container');
        container.appendChild(toast);

        // Auto-dismiss after 5 seconds
        setTimeout(() => {
            toast.style.animation = 'slideOut 0.3s ease';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    getTypeColor(type) {
        const colors = {
            chat: '#3b82f6',          // blue
            room_invite: '#8b5cf6',   // purple
            mail: '#10b981',          // green
            message_sent: '#f59e0b',  // yellow
            system: '#6b7280'         // gray
        };
        return colors[type] || '#6b7280';
    }

    getTypeColorDark(type) {
        const colors = {
            chat: '#2563eb',
            room_invite: '#7c3aed',
            mail: '#059669',
            message_sent: '#d97706',
            system: '#4b5563'
        };
        return colors[type] || '#4b5563';
    }

    playSound(type) {
        // Create a simple beep sound using Web Audio API
        try {
            const audioContext = new (window.AudioContext || window.webkitAudioContext)();
            const oscillator = audioContext.createOscillator();
            const gainNode = audioContext.createGain();

            oscillator.connect(gainNode);
            gainNode.connect(audioContext.destination);

            // Different frequencies for different notification types
            const frequencies = {
                chat: 800,
                room_invite: 1000,
                mail: 600,
                message_sent: 700,
                system: 500
            };

            oscillator.frequency.value = frequencies[type] || 700;
            oscillator.type = 'sine';

            gainNode.gain.setValueAtTime(0.1, audioContext.currentTime);
            gainNode.gain.exponentialRampToValueAtTime(0.01, audioContext.currentTime + 0.2);

            oscillator.start(audioContext.currentTime);
            oscillator.stop(audioContext.currentTime + 0.2);
        } catch (e) {
            console.error('Failed to play notification sound:', e);
        }
    }

    escapeHtml(text) {
        const map = {
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        };
        return text.replace(/[&<>"']/g, m => map[m]);
    }

    updateSettings(newSettings) {
        this.settings = { ...this.settings, ...newSettings };
        this.saveSettings();

        // Reconnect if enabled status changed
        if (newSettings.enabled !== undefined) {
            if (newSettings.enabled) {
                this.connect();
            } else {
                this.disconnect();
            }
        }
    }

    getSettings() {
        return { ...this.settings };
    }
}

// Add CSS animations
const style = document.createElement('style');
style.textContent = `
    @keyframes slideIn {
        from {
            transform: translateX(400px);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }

    @keyframes slideOut {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(400px);
            opacity: 0;
        }
    }
`;
document.head.appendChild(style);

// Initialize notification system when DOM is loaded
let notificationSystem;
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        notificationSystem = new NotificationSystem();
    });
} else {
    notificationSystem = new NotificationSystem();
}

// Export for use in other scripts
window.NotificationSystem = NotificationSystem;
window.notificationSystem = notificationSystem;
