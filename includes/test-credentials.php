<?php
/**
 * Test Credentials Display
 * Shows test login credentials for development - easily removable
 */
?>

<!-- TEST CREDENTIALS - REMOVE FOR PRODUCTION -->
<div class="test-credentials-banner" style="
    background: linear-gradient(135deg, #ff6b6b, #ff8e53);
    color: white;
    padding: 15px;
    margin: 20px 0;
    border-radius: 8px;
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
    border-left: 5px solid #fff;
">
    <div style="display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap;">
        <div>
            <h4 style="margin: 0 0 10px 0; font-size: 16px;">🧪 Development Test Accounts</h4>
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 10px; font-size: 13px;">
                <div>
                    <strong>👑 Admin:</strong><br>
                    admin@test.local / admin123
                </div>
                <div>
                    <strong>📞 Operator:</strong><br>
                    operator@test.local / operator123
                </div>
                <div>
                    <strong>👤 Customer:</strong><br>
                    customer@test.local / customer123
                </div>
                <div>
                    <strong>🏢 Reseller:</strong><br>
                    reseller@test.local / reseller123
                </div>
            </div>
        </div>
        <div style="margin-top: 10px;">
            <button type="button" onclick="fillTestCredentials('admin')"
                    style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin: 2px; font-size: 12px;">
                Fill Admin
            </button>
            <button type="button" onclick="fillTestCredentials('operator')"
                    style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin: 2px; font-size: 12px;">
                Fill Operator
            </button>
            <button type="button" onclick="fillTestCredentials('customer')"
                    style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin: 2px; font-size: 12px;">
                Fill Customer
            </button>
            <button type="button" onclick="fillTestCredentials('reseller')"
                    style="background: rgba(255,255,255,0.2); border: 1px solid rgba(255,255,255,0.3); color: white; padding: 5px 10px; border-radius: 4px; cursor: pointer; margin: 2px; font-size: 12px;">
                Fill Reseller
            </button>
        </div>
    </div>
</div>

<script>
function fillTestCredentials(type) {
    const credentials = {
        admin: { email: 'admin@test.local', password: 'admin123' },
        operator: { email: 'operator@test.local', password: 'operator123' },
        customer: { email: 'customer@test.local', password: 'customer123' },
        reseller: { email: 'reseller@test.local', password: 'reseller123' }
    };

    if (credentials[type]) {
        const emailField = document.querySelector('input[name="email"], input[type="email"]');
        const passwordField = document.querySelector('input[name="password"], input[type="password"]');

        if (emailField) emailField.value = credentials[type].email;
        if (passwordField) passwordField.value = credentials[type].password;

        // Focus the submit button for better UX
        const submitButton = document.querySelector('button[type="submit"], input[type="submit"]');
        if (submitButton) submitButton.focus();
    }
}
</script>
<!-- END TEST CREDENTIALS -->