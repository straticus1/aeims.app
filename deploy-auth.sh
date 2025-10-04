#!/bin/bash

# Quick deployment script for auth.php and dashboard updates
# Moves the authentication status page from footer to logged-in user menu

echo "🚀 Deploying Authentication Status Page"
echo "======================================"

# Check if auth.php exists
if [[ ! -f "auth.php" ]]; then
    echo "❌ auth.php not found in current directory"
    exit 1
fi

echo "✅ Found auth.php locally"

# Check if this is the aeims.app directory
if [[ ! -f "dashboard.php" || ! -f "auth_functions.php" ]]; then
    echo "❌ This doesn't appear to be the aeims.app directory"
    exit 1
fi

echo "✅ Confirmed aeims.app directory"

# Summary of changes
echo ""
echo "📋 Changes made:"
echo "  ✅ Added 'Authentication Status' to user dropdown in dashboard.php"
echo "  ✅ Added 'Authentication Status' to admin dashboard"
echo "  ✅ Added divider styling to user dropdown menu"
echo "  📁 auth.php is ready for deployment"

echo ""
echo "🌐 What auth.php provides:"
echo "  🔐 Current authentication status"
echo "  👤 User session details (name, email, type, permissions)"
echo "  ⏰ Session timing info (login time, duration, last activity)"
echo "  🎛️  Quick actions (dashboard, admin panel, logout)"
echo "  🌐 Multi-site access information"

echo ""
echo "📍 Where to access it after deployment:"
echo "  • From dashboard: User menu → 🔐 Authentication Status"
echo "  • From admin panel: Support & Monitoring → 🔐 Authentication Status"
echo "  • Direct URL: https://aeims.app/auth.php (when logged in)"

echo ""
echo "⚠️  To complete deployment:"
echo "  1. Copy auth.php to your production server"
echo "  2. Copy updated dashboard.php to production server"
echo "  3. Copy updated admin-dashboard.php to production server"
echo "  4. Copy updated assets/css/dashboard.css to production server"
echo "  5. Remove the footer link from the main page (index.php or similar)"

echo ""
echo "🧪 Test the deployment:"
echo "  1. Login to aeims.app"
echo "  2. Click your user avatar in top-right"
echo "  3. Click '🔐 Authentication Status'"
echo "  4. Verify it shows your session details"

echo ""
echo "✅ Ready for deployment!"