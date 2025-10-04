<?php
/**
 * AEIMS Operator Registration
 * Registration form for new platform operators
 */

session_start();
$config = include 'config.php';

// Handle form submission
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form will be processed by operator-registration.php
    $message = 'Thank you for your interest! Your application will be reviewed within 24-48 hours.';
    $messageType = 'success';
}

// Handle messages from form processor
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'] ?? 'info';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Become an Operator - <?php echo $config['site']['name']; ?></title>
    <meta name="description" content="Join the AEIMS platform as an operator. Create multiple identities, manage ads, and start earning with our comprehensive adult entertainment platform.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .operator-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .operator-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .operator-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 16px 0;
        }

        .operator-subtitle {
            font-size: 1.125rem;
            color: #64748b;
            margin: 0 0 32px 0;
        }

        .benefits-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 24px;
            margin-bottom: 40px;
        }

        .benefit-card {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .benefit-icon {
            font-size: 2.5rem;
            margin-bottom: 16px;
        }

        .benefit-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 8px 0;
        }

        .benefit-description {
            font-size: 0.875rem;
            color: #64748b;
            margin: 0;
        }

        .form-container {
            background: white;
            border-radius: 12px;
            padding: 32px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            margin-bottom: 32px;
        }

        .form-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 24px 0;
            text-align: center;
        }

        .form-row {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 20px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            color: #374151;
        }

        .form-group .required {
            color: #ef4444;
        }

        .form-group input,
        .form-group select,
        .form-group textarea {
            width: 100%;
            padding: 12px;
            border: 1px solid #d1d5db;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.2s ease;
            box-sizing: border-box;
        }

        .form-group input:focus,
        .form-group select:focus,
        .form-group textarea:focus {
            outline: none;
            border-color: #3b82f6;
            box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.1);
        }

        .form-group .help-text {
            font-size: 0.875rem;
            color: #6b7280;
            margin-top: 4px;
        }

        .checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }

        .checkbox-group input[type="checkbox"] {
            width: auto;
            margin: 0;
        }

        .checkbox-group label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
        }

        .radio-group {
            display: flex;
            gap: 20px;
            flex-wrap: wrap;
        }

        .radio-item {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .radio-item input[type="radio"] {
            width: auto;
            margin: 0;
        }

        .radio-item label {
            margin: 0;
            font-weight: 400;
            cursor: pointer;
        }

        .password-requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 16px;
            margin-top: 8px;
        }

        .password-requirements h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 8px 0;
        }

        .password-requirements ul {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
            padding-left: 20px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 12px 24px;
            border: 1px solid transparent;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 500;
            text-decoration: none;
            cursor: pointer;
            transition: all 0.2s ease;
            width: 100%;
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-secondary {
            background: white;
            color: #374151;
            border-color: #d1d5db;
        }

        .btn-secondary:hover {
            background: #f9fafb;
        }

        .alert {
            padding: 16px;
            border-radius: 8px;
            margin-bottom: 24px;
            border: 1px solid;
        }

        .alert-success {
            background: #f0fdf4;
            border-color: #bbf7d0;
            color: #166534;
        }

        .alert-error {
            background: #fef2f2;
            border-color: #fecaca;
            color: #991b1b;
        }

        .alert-info {
            background: #eff6ff;
            border-color: #bfdbfe;
            color: #1e40af;
        }

        @media (max-width: 768px) {
            .operator-container {
                padding: 20px 16px;
            }

            .operator-title {
                font-size: 2rem;
            }

            .form-container {
                padding: 20px;
            }

            .form-row {
                grid-template-columns: 1fr;
            }

            .radio-group {
                flex-direction: column;
                gap: 12px;
            }
        }
    </style>
</head>
<body>
    <div class="operator-container">
        <div class="operator-header">
            <h1 class="operator-title">Become an Operator</h1>
            <p class="operator-subtitle">Join the AEIMS platform and start building your adult entertainment business</p>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="benefits-grid">
            <div class="benefit-card">
                <div class="benefit-icon">🎭</div>
                <h3 class="benefit-title">Multiple Identities</h3>
                <p class="benefit-description">Create per-site identities to match different audiences and maximize your reach</p>
            </div>
            <div class="benefit-card">
                <div class="benefit-icon">📢</div>
                <h3 class="benefit-title">Unlimited Ads</h3>
                <p class="benefit-description">Create multiple ads per identity across various categories to increase visibility</p>
            </div>
            <div class="benefit-card">
                <div class="benefit-icon">💰</div>
                <h3 class="benefit-title">Multiple Revenue Streams</h3>
                <p class="benefit-description">Earn from video calls, audio calls, text chat, and premium content</p>
            </div>
        </div>

        <div class="form-container">
            <div style="text-align: center; margin-bottom: 24px;">
                <div style="display: flex; align-items: center; justify-content: center; gap: 16px; margin-bottom: 16px;">
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; display: flex; align-items: center; justify-content: center; font-weight: 600;">1</div>
                    <div style="height: 2px; width: 60px; background: #e2e8f0;"></div>
                    <div style="width: 32px; height: 32px; border-radius: 50%; background: #e2e8f0; color: #94a3b8; display: flex; align-items: center; justify-content: center; font-weight: 600;">2</div>
                </div>
                <p style="color: #64748b; font-size: 0.875rem; margin: 0;">Step 1: Basic Information | Step 2: Identity Verification</p>
            </div>
            <h2 class="form-title">Operator Application - Step 1</h2>

            <!-- IP Address and Compliance Notice -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.2rem;">🔒</span>
                    <strong style="color: #374151;">Security & Compliance Notice</strong>
                </div>
                <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem;">
                    Your IP address <strong style="color: #1e40af;"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?></strong> will be logged as part of this application for security and compliance purposes. This information helps us maintain platform security and meet regulatory requirements.
                </p>
                <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
                    By submitting this application, you acknowledge that your IP address and submission details will be recorded.
                </p>
            </div>

            <form action="operator-registration.php" method="POST" id="operatorForm">
                <div class="form-row">
                    <div class="form-group">
                        <label for="first_name">First Name <span class="required">*</span></label>
                        <input type="text" id="first_name" name="first_name" required>
                    </div>
                    <div class="form-group">
                        <label for="middle_initial">Middle Initial</label>
                        <input type="text" id="middle_initial" name="middle_initial" maxlength="1" placeholder="Optional">
                    </div>
                    <div class="form-group">
                        <label for="last_name">Last Name <span class="required">*</span></label>
                        <input type="text" id="last_name" name="last_name" required>
                    </div>
                </div>

                <div class="form-group">
                    <label for="email">Email Address <span class="required">*</span></label>
                    <input type="email" id="email" name="email" required>
                    <div class="help-text">This will be used for account communications and login (if no custom login name is provided)</div>
                </div>

                <div class="form-group">
                    <label for="address">Address <span class="required">*</span></label>
                    <textarea id="address" name="address" rows="3" required placeholder="Full address including city, state, and ZIP code"></textarea>
                </div>

                <div class="form-group">
                    <label for="phone">Phone Number <span class="required">*</span></label>
                    <input type="tel" id="phone" name="phone" required placeholder="(555) 123-4567">
                </div>

                <div class="form-group">
                    <label>Is this your first adult entertainment, PSO-type role? <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-item">
                            <input type="radio" id="first_role_yes" name="first_role" value="yes" required>
                            <label for="first_role_yes">Yes, this is my first role</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" id="first_role_no" name="first_role" value="no" required>
                            <label for="first_role_no">No, I have experience</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label>Does your phone support SMS and MMS? <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-item">
                            <input type="radio" id="sms_yes" name="sms_support" value="yes" required>
                            <label for="sms_yes">Yes, both SMS and MMS</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" id="sms_partial" name="sms_support" value="partial" required>
                            <label for="sms_partial">SMS only</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" id="sms_no" name="sms_support" value="no" required>
                            <label for="sms_no">Neither</label>
                        </div>
                    </div>
                </div>

                <div class="form-group">
                    <label for="hours_per_week">Hours per week you estimate keeping your line(s) active <span class="required">*</span></label>
                    <select id="hours_per_week" name="hours_per_week" required>
                        <option value="">Select hours per week</option>
                        <option value="1-10">1-10 hours</option>
                        <option value="11-20">11-20 hours</option>
                        <option value="21-30">21-30 hours</option>
                        <option value="31-40">31-40 hours</option>
                        <option value="41-50">41-50 hours</option>
                        <option value="50+">50+ hours</option>
                    </select>
                </div>

                <div class="form-group">
                    <label>Have you worked for a competing platform? <span class="required">*</span></label>
                    <div class="radio-group">
                        <div class="radio-item">
                            <input type="radio" id="competitor_yes" name="worked_competitor" value="yes" required>
                            <label for="competitor_yes">Yes</label>
                        </div>
                        <div class="radio-item">
                            <input type="radio" id="competitor_no" name="worked_competitor" value="no" required>
                            <label for="competitor_no">No</label>
                        </div>
                    </div>
                </div>

                <div class="form-group" id="competitor_details" style="display: none;">
                    <label for="competitor_platforms">Which platforms? (NF, T2M, etc.)</label>
                    <input type="text" id="competitor_platforms" name="competitor_platforms" placeholder="List platform names">
                </div>

                <div class="form-group" id="rating_group" style="display: none;">
                    <label for="average_rating">What is your average rating?</label>
                    <select id="average_rating" name="average_rating">
                        <option value="">Select rating</option>
                        <option value="1.0-2.0">1.0 - 2.0</option>
                        <option value="2.1-3.0">2.1 - 3.0</option>
                        <option value="3.1-4.0">3.1 - 4.0</option>
                        <option value="4.1-5.0">4.1 - 5.0</option>
                        <option value="no_ratings">No ratings system</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="login_name">Preferred Login Name</label>
                    <input type="text" id="login_name" name="login_name" placeholder="Leave blank to use email address">
                    <div class="help-text">If left blank, your email address will be used as your login name</div>
                </div>

                <div class="form-group">
                    <label for="password">Create Password <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required>
                    <div class="password-requirements">
                        <h4>Password Requirements:</h4>
                        <ul>
                            <li>At least 8 characters long</li>
                            <li>Include at least one number</li>
                            <li>Include at least one special character</li>
                        </ul>
                    </div>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password <span class="required">*</span></label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="terms_agree" name="terms_agree" required>
                        <label for="terms_agree">
                            I agree to the <a href="legal.php" target="_blank">Terms of Service</a> and <a href="legal.php#privacy" target="_blank">Privacy Policy</a> <span class="required">*</span>
                        </label>
                    </div>
                </div>

                <div class="form-group">
                    <div class="checkbox-group">
                        <input type="checkbox" id="age_verify" name="age_verify" required>
                        <label for="age_verify">
                            I confirm that I am at least 18 years old <span class="required">*</span>
                        </label>
                    </div>
                </div>

                <div style="margin-top: 32px;">
                    <button type="submit" class="btn btn-primary">Continue to Identity Verification</button>
                </div>
            </form>
        </div>

        <div style="text-align: center; margin-top: 32px;">
            <a href="index.php" class="btn btn-secondary" style="max-width: 200px;">Back to Home</a>
        </div>

        <div style="text-align: center; margin-top: 40px; padding-top: 32px; border-top: 1px solid #e2e8f0;">
            <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                Questions? Contact us at <a href="mailto:<?= $config['site']['contact_email'] ?>" style="color: #3b82f6;"><?= $config['site']['contact_email'] ?></a>
            </p>
        </div>
    </div>

    <script>
        // Show/hide competitor details based on selection
        document.querySelectorAll('input[name="worked_competitor"]').forEach(radio => {
            radio.addEventListener('change', function() {
                const competitorDetails = document.getElementById('competitor_details');
                const ratingGroup = document.getElementById('rating_group');

                if (this.value === 'yes') {
                    competitorDetails.style.display = 'block';
                    ratingGroup.style.display = 'block';
                    document.getElementById('competitor_platforms').required = true;
                } else {
                    competitorDetails.style.display = 'none';
                    ratingGroup.style.display = 'none';
                    document.getElementById('competitor_platforms').required = false;
                    document.getElementById('competitor_platforms').value = '';
                    document.getElementById('average_rating').value = '';
                }
            });
        });

        // Password validation
        function validatePassword() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;

            // Check password requirements
            const hasNumber = /\d/.test(password);
            const hasSpecial = /[!@#$%^&*(),.?":{}|<>]/.test(password);
            const isLongEnough = password.length >= 8;

            if (!isLongEnough || !hasNumber || !hasSpecial) {
                alert('Password must be at least 8 characters long and include at least one number and one special character.');
                return false;
            }

            if (password !== confirmPassword) {
                alert('Passwords do not match.');
                return false;
            }

            return true;
        }

        // Form validation
        document.getElementById('operatorForm').addEventListener('submit', function(e) {
            if (!validatePassword()) {
                e.preventDefault();
                return false;
            }
        });

        // Phone number formatting
        document.getElementById('phone').addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            if (value.length >= 6) {
                value = value.replace(/(\d{3})(\d{3})(\d{4})/, '($1) $2-$3');
            } else if (value.length >= 3) {
                value = value.replace(/(\d{3})(\d{0,3})/, '($1) $2');
            }
            e.target.value = value;
        });
    </script>
</body>
</html>