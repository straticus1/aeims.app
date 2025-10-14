<?php
/**
 * SexaComms.com - Authorized Access Warning
 * Warning page for unauthorized access attempts
 */
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Authorized Access Only - SexaComms</title>
    <meta name="description" content="Authorized access only for After Dark Systems platforms.">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body class="warning-page">
    <div class="warning-container">
        <div class="warning-card">
            <div class="warning-icon">
                <svg width="80" height="80" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
                    <path d="M12 2L2 22h20L12 2z" fill="#fbbf24" stroke="#f59e0b" stroke-width="2"/>
                    <path d="M12 9v6M12 17h.01" stroke="#1f2937" stroke-width="2" stroke-linecap="round"/>
                </svg>
            </div>

            <h1>Authorized Access Only</h1>

            <div class="warning-message">
                <p>This site is for authorized users and employees of the After Dark Systems hosted platforms.</p>
                <p><strong>All access is logged and recorded for compliance and auditing purposes.</strong></p>
            </div>

            <div class="access-info">
                <p>If you are an authorized user of this system, please click the button below to proceed to login.</p>
            </div>

            <a href="https://login.sexacomms.com/" class="btn btn-primary">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" style="display: inline-block; vertical-align: middle; margin-right: 8px;">
                    <path d="M15 3h4a2 2 0 012 2v14a2 2 0 01-2 2h-4M10 17l5-5-5-5M15 12H3" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
                Authorized Login
            </a>

            <div class="legal-notice">
                <p><small>Unauthorized access attempts are monitored and may be subject to prosecution under applicable laws including the Computer Fraud and Abuse Act (18 U.S.C. § 1030).</small></p>
            </div>
        </div>
    </div>

    <style>
        .warning-page {
            background: linear-gradient(135deg, #1e40af 0%, #1e3a8a 100%);
            min-height: 100vh;
            font-family: 'Inter', sans-serif;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .warning-container {
            max-width: 600px;
            width: 100%;
        }

        .warning-card {
            background: white;
            border-radius: 16px;
            padding: 3rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            text-align: center;
        }

        .warning-icon {
            margin-bottom: 2rem;
            animation: pulse 2s ease-in-out infinite;
        }

        @keyframes pulse {
            0%, 100% {
                transform: scale(1);
            }
            50% {
                transform: scale(1.05);
            }
        }

        .warning-card h1 {
            font-size: 2rem;
            font-weight: 700;
            color: #1f2937;
            margin-bottom: 1.5rem;
        }

        .warning-message {
            background: #fef3c7;
            border-left: 4px solid #fbbf24;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 2rem;
            text-align: left;
        }

        .warning-message p {
            color: #78350f;
            margin-bottom: 0.5rem;
            line-height: 1.6;
        }

        .warning-message p:last-child {
            margin-bottom: 0;
        }

        .warning-message strong {
            color: #92400e;
            font-weight: 600;
        }

        .access-info {
            margin-bottom: 2rem;
        }

        .access-info p {
            color: #6b7280;
            line-height: 1.6;
        }

        .btn {
            display: inline-block;
            padding: 1rem 2rem;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            transition: all 0.3s ease;
            border: none;
            cursor: pointer;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            font-size: 1.1rem;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px rgba(102, 126, 234, 0.3);
        }

        .legal-notice {
            margin-top: 2rem;
            padding-top: 2rem;
            border-top: 1px solid #e5e7eb;
        }

        .legal-notice p {
            color: #9ca3af;
            font-size: 0.8rem;
            line-height: 1.5;
        }

        @media (max-width: 768px) {
            .warning-card {
                padding: 2rem;
            }

            .warning-card h1 {
                font-size: 1.5rem;
            }

            .btn-primary {
                width: 100%;
            }
        }
    </style>
</body>
</html>
