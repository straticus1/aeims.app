<?php
/**
 * ID Verification Microservice - Landing Page
 * Government ID Verification Services by After Dark Systems
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ID Verification Services - AEIMS</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            max-width: 600px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        h1 {
            color: #1a202c;
            margin-bottom: 1rem;
            font-size: 2rem;
        }
        .subtitle {
            color: #667eea;
            font-weight: 600;
            margin-bottom: 1.5rem;
        }
        p {
            color: #4a5568;
            line-height: 1.6;
            margin-bottom: 1rem;
        }
        .status {
            background: #edf2f7;
            border-left: 4px solid #667eea;
            padding: 1rem;
            margin: 1.5rem 0;
            border-radius: 4px;
        }
        .status strong {
            color: #667eea;
        }
        .features {
            list-style: none;
            margin: 1.5rem 0;
        }
        .features li {
            padding: 0.5rem 0;
            padding-left: 1.5rem;
            position: relative;
        }
        .features li:before {
            content: "✓";
            position: absolute;
            left: 0;
            color: #48bb78;
            font-weight: bold;
        }
        .contact {
            background: #f7fafc;
            padding: 1.5rem;
            border-radius: 8px;
            margin-top: 2rem;
        }
        .contact h3 {
            color: #2d3748;
            margin-bottom: 0.5rem;
        }
        .contact a {
            color: #667eea;
            text-decoration: none;
            font-weight: 600;
        }
        .contact a:hover {
            text-decoration: underline;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 ID Verification Services</h1>
        <div class="subtitle">Government ID Verification by After Dark Systems</div>

        <div class="status">
            <strong>API Access Required</strong> - This service is restricted to authorized API clients only.
        </div>

        <p>Our ID verification microservice provides secure, API-based verification of US state and federal government-issued identification documents.</p>

        <h3 style="margin-top: 2rem; color: #2d3748;">Features:</h3>
        <ul class="features">
            <li>Secure API Key Authentication</li>
            <li>US Driver's License & State ID Validation</li>
            <li>Passport Verification</li>
            <li>Barcode Scanning & Data Extraction</li>
            <li>Biometric Face Matching</li>
            <li>Comprehensive Audit Logging</li>
        </ul>

        <div class="contact">
            <h3>Need API Access?</h3>
            <p>Contact our team to request API credentials and integration documentation.</p>
            <p><a href="mailto:info@afterdarksys.com">info@afterdarksys.com</a></p>
        </div>

        <p style="margin-top: 2rem; font-size: 0.875rem; color: #718096;">
            Part of the <strong>AEIMS Platform</strong> by <a href="https://afterdarksys.com" style="color: #667eea;">After Dark Systems</a>
        </p>
    </div>
</body>
</html>
