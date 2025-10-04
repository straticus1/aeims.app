<?php
/**
 * AEIMS Identity Verification - Step 2
 * Photo ID verification and face matching for operator applications
 */

session_start();
$config = include 'config.php';

// Get application ID from URL
$application_id = $_GET['application_id'] ?? '';
if (empty($application_id)) {
    header('Location: become-operator.php?message=Invalid%20verification%20session&type=error');
    exit();
}

// Load application data to verify it exists
$application = loadApplication($application_id);
if (!$application) {
    header('Location: become-operator.php?message=Application%20not%20found&type=error');
    exit();
}

// Handle form submission
$message = '';
$messageType = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Form will be processed by identity-verification-handler.php
    $message = 'Identity verification submitted successfully! Your application is now under review.';
    $messageType = 'success';
}

// Handle messages from form processor
if (isset($_GET['message'])) {
    $message = urldecode($_GET['message']);
    $messageType = $_GET['type'] ?? 'info';
}

function loadApplication($application_id) {
    $dataDir = __DIR__ . '/data';
    $filename = $dataDir . '/operator_applications.json';

    if (!file_exists($filename)) {
        return null;
    }

    $applications = json_decode(file_get_contents($filename), true) ?: [];

    foreach ($applications as $app) {
        if ($app['id'] === $application_id) {
            return $app;
        }
    }

    return null;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Identity Verification - <?php echo $config['site']['name']; ?></title>
    <meta name="description" content="Complete your operator application with secure identity verification using photo ID and facial recognition.">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <style>
        .verification-container {
            max-width: 800px;
            margin: 0 auto;
            padding: 40px 20px;
        }

        .verification-header {
            text-align: center;
            margin-bottom: 40px;
        }

        .verification-title {
            font-size: 2.5rem;
            font-weight: 700;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            margin: 0 0 16px 0;
        }

        .verification-subtitle {
            font-size: 1.125rem;
            color: #64748b;
            margin: 0 0 32px 0;
        }

        .step-indicator {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            margin-bottom: 32px;
        }

        .step {
            width: 32px;
            height: 32px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 0.875rem;
        }

        .step.completed {
            background: #10b981;
            color: white;
        }

        .step.current {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .step-line {
            height: 2px;
            width: 60px;
            background: #10b981;
        }

        .security-notice {
            background: linear-gradient(135deg, #fef3c7 0%, #fde68a 100%);
            border: 1px solid #f59e0b;
            border-radius: 12px;
            padding: 24px;
            margin-bottom: 32px;
        }

        .security-notice h3 {
            color: #92400e;
            margin: 0 0 12px 0;
            font-size: 1.125rem;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .security-notice p {
            color: #78350f;
            margin: 0 0 16px 0;
            font-size: 0.875rem;
        }

        .security-features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 16px;
            margin-top: 16px;
        }

        .security-feature {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 0.875rem;
            color: #78350f;
        }

        .verification-steps {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 24px;
            margin-bottom: 32px;
        }

        .verification-step {
            background: white;
            border-radius: 12px;
            padding: 24px;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1);
            border: 1px solid #e2e8f0;
            text-align: center;
        }

        .step-number {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 600;
            font-size: 1.25rem;
            margin: 0 auto 16px auto;
        }

        .step-title {
            font-size: 1.125rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 8px 0;
        }

        .step-description {
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

        .upload-section {
            margin-bottom: 32px;
        }

        .upload-title {
            font-size: 1.25rem;
            font-weight: 600;
            color: #1e293b;
            margin: 0 0 16px 0;
        }

        .upload-area {
            border: 2px dashed #d1d5db;
            border-radius: 12px;
            padding: 32px;
            text-align: center;
            transition: all 0.2s ease;
            cursor: pointer;
            margin-bottom: 16px;
        }

        .upload-area:hover {
            border-color: #3b82f6;
            background: #f8fafc;
        }

        .upload-area.dragover {
            border-color: #3b82f6;
            background: #eff6ff;
        }

        .upload-icon {
            font-size: 3rem;
            color: #9ca3af;
            margin-bottom: 16px;
        }

        .upload-text {
            font-size: 1.125rem;
            font-weight: 500;
            color: #374151;
            margin: 0 0 8px 0;
        }

        .upload-subtext {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
        }

        .file-input {
            display: none;
        }

        .preview-container {
            display: none;
            margin-top: 16px;
            text-align: center;
        }

        .preview-image {
            max-width: 100%;
            max-height: 300px;
            border-radius: 8px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .requirements {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 16px;
            margin-top: 16px;
        }

        .requirements h4 {
            font-size: 0.875rem;
            font-weight: 600;
            color: #374151;
            margin: 0 0 8px 0;
        }

        .requirements ul {
            font-size: 0.875rem;
            color: #6b7280;
            margin: 0;
            padding-left: 20px;
        }

        .verification-actions {
            display: flex;
            gap: 16px;
            justify-content: center;
            margin-top: 32px;
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
        }

        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }

        .btn-primary:hover {
            transform: translateY(-1px);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
        }

        .btn-primary:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
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
            .verification-container {
                padding: 20px 16px;
            }

            .verification-title {
                font-size: 2rem;
            }

            .form-container {
                padding: 20px;
            }

            .verification-steps {
                grid-template-columns: 1fr;
            }

            .verification-actions {
                flex-direction: column;
            }
        }
    </style>
</head>
<body>
    <div class="verification-container">
        <div class="verification-header">
            <h1 class="verification-title">Identity Verification</h1>
            <p class="verification-subtitle">Secure verification for operator applications</p>
        </div>

        <div class="step-indicator">
            <div class="step completed">✓</div>
            <div class="step-line"></div>
            <div class="step current">2</div>
        </div>
        <p style="text-align: center; color: #64748b; font-size: 0.875rem; margin-bottom: 32px;">Step 1: Completed | Step 2: Identity Verification</p>

        <?php if ($message): ?>
            <div class="alert alert-<?= $messageType ?>">
                <?= htmlspecialchars($message) ?>
            </div>
        <?php endif; ?>

        <div class="security-notice">
            <h3>🔒 Secure Verification Process</h3>
            <p>Your privacy and security are our top priority. All uploaded documents are processed using military-grade encryption and are automatically deleted after verification.</p>
            <div class="security-features">
                <div class="security-feature">
                    <span>🛡️</span>
                    <span>End-to-end encryption</span>
                </div>
                <div class="security-feature">
                    <span>🔍</span>
                    <span>AI-powered verification</span>
                </div>
                <div class="security-feature">
                    <span>🗑️</span>
                    <span>Auto-deletion after 72 hours</span>
                </div>
                <div class="security-feature">
                    <span>✅</span>
                    <span>SOC 2 compliant</span>
                </div>
            </div>
        </div>

        <div class="verification-steps">
            <div class="verification-step">
                <div class="step-number">1</div>
                <h3 class="step-title">Front of ID</h3>
                <p class="step-description">Clear photo of the front of your government-issued ID</p>
            </div>
            <div class="verification-step">
                <div class="step-number">2</div>
                <h3 class="step-title">Back of ID</h3>
                <p class="step-description">Clear photo of the back of your government-issued ID</p>
            </div>
            <div class="verification-step">
                <div class="step-number">3</div>
                <h3 class="step-title">Photo Holding ID</h3>
                <p class="step-description">Photo of you holding your ID next to your face</p>
            </div>
        </div>

        <div class="form-container">
            <h2 class="form-title">Upload Verification Documents</h2>

            <!-- IP Address and Compliance Notice -->
            <div style="background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 1rem; margin-bottom: 2rem;">
                <div style="display: flex; align-items: center; gap: 0.5rem; margin-bottom: 0.5rem;">
                    <span style="font-size: 1.2rem;">🔒</span>
                    <strong style="color: #374151;">Security & Compliance Notice</strong>
                </div>
                <p style="margin: 0 0 0.5rem 0; color: #6b7280; font-size: 0.875rem;">
                    Your IP address <strong style="color: #1e40af;"><?php echo htmlspecialchars($_SERVER['REMOTE_ADDR'] ?? 'Unknown'); ?></strong> will be logged as part of this identity verification process for security and compliance purposes. This information helps us maintain platform security and meet regulatory requirements.
                </p>
                <p style="margin: 0; color: #6b7280; font-size: 0.875rem;">
                    By submitting these documents, you acknowledge that your IP address and verification details will be recorded.
                </p>
            </div>

            <form action="identity-verification-handler.php" method="POST" enctype="multipart/form-data" id="verificationForm">
                <input type="hidden" name="application_id" value="<?= htmlspecialchars($application_id) ?>">

                <!-- Photo ID Front Upload -->
                <div class="upload-section">
                    <h3 class="upload-title">1. Front of Government-Issued Photo ID</h3>
                    <div class="upload-area" onclick="document.getElementById('id_front').click()">
                        <div class="upload-icon">📄</div>
                        <div class="upload-text">Click to upload front of ID</div>
                        <div class="upload-subtext">Supported formats: JPG, PNG (max 10MB)</div>
                    </div>
                    <input type="file" id="id_front" name="id_front" class="file-input" accept=".jpg,.jpeg,.png" required>
                    <div class="preview-container" id="id_front_preview">
                        <img class="preview-image" id="id_front_image" alt="ID Front Preview">
                    </div>
                    <div class="requirements">
                        <h4>Front of ID Requirements:</h4>
                        <ul>
                            <li>Must be a government-issued ID (driver's license, passport, state ID)</li>
                            <li>All text must be clearly readable</li>
                            <li>All four corners must be visible</li>
                            <li>No glare or shadows obscuring information</li>
                            <li>ID must not be expired</li>
                            <li>Photo on ID must be clearly visible</li>
                        </ul>
                    </div>
                </div>

                <!-- Photo ID Back Upload -->
                <div class="upload-section">
                    <h3 class="upload-title">2. Back of Government-Issued Photo ID</h3>
                    <div class="upload-area" onclick="document.getElementById('id_back').click()">
                        <div class="upload-icon">📄</div>
                        <div class="upload-text">Click to upload back of ID</div>
                        <div class="upload-subtext">Supported formats: JPG, PNG (max 10MB)</div>
                    </div>
                    <input type="file" id="id_back" name="id_back" class="file-input" accept=".jpg,.jpeg,.png" required>
                    <div class="preview-container" id="id_back_preview">
                        <img class="preview-image" id="id_back_image" alt="ID Back Preview">
                    </div>
                    <div class="requirements">
                        <h4>Back of ID Requirements:</h4>
                        <ul>
                            <li>All text and security features must be clearly readable</li>
                            <li>All four corners must be visible</li>
                            <li>No glare or shadows obscuring information</li>
                            <li>Capture any barcodes, magnetic strips, or security features</li>
                            <li>Address information (if present) must be legible</li>
                        </ul>
                    </div>
                </div>

                <!-- Selfie with ID Upload -->
                <div class="upload-section">
                    <h3 class="upload-title">3. Photo of You Holding Your ID</h3>
                    <div class="upload-area" onclick="document.getElementById('selfie_with_id').click()">
                        <div class="upload-icon">🤳</div>
                        <div class="upload-text">Click to upload photo holding ID</div>
                        <div class="upload-subtext">Supported formats: JPG, PNG (max 10MB)</div>
                    </div>
                    <input type="file" id="selfie_with_id" name="selfie_with_id" class="file-input" accept=".jpg,.jpeg,.png" required>
                    <div class="preview-container" id="selfie_preview">
                        <img class="preview-image" id="selfie_image" alt="Selfie with ID Preview">
                    </div>
                    <div class="requirements">
                        <h4>Photo with ID Requirements:</h4>
                        <ul>
                            <li>Hold your ID next to your face (both clearly visible)</li>
                            <li>Look directly at the camera</li>
                            <li>Ensure good lighting with no shadows</li>
                            <li>Remove sunglasses, hats, or face coverings</li>
                            <li>Your entire face must be visible and match the ID photo</li>
                            <li>ID should be held steady and clearly readable</li>
                        </ul>
                    </div>
                </div>

                <!-- Additional Verification Options -->
                <div class="upload-section">
                    <h3 class="upload-title">4. Additional Verification (Optional)</h3>
                    <div class="upload-area" onclick="document.getElementById('additional_doc').click()">
                        <div class="upload-icon">📋</div>
                        <div class="upload-text">Upload additional verification document</div>
                        <div class="upload-subtext">Utility bill, bank statement, or secondary ID</div>
                    </div>
                    <input type="file" id="additional_doc" name="additional_doc" class="file-input" accept=".jpg,.jpeg,.png,.pdf">
                    <div class="preview-container" id="additional_preview">
                        <img class="preview-image" id="additional_image" alt="Additional Document Preview">
                    </div>
                </div>

                <div class="verification-actions">
                    <button type="button" class="btn btn-secondary" onclick="window.history.back()">Back to Step 1</button>
                    <button type="submit" class="btn btn-primary" id="submitVerification" disabled>Submit for Verification</button>
                </div>
            </form>
        </div>

        <div style="text-align: center; margin-top: 40px; padding-top: 32px; border-top: 1px solid #e2e8f0;">
            <p style="color: #64748b; font-size: 0.875rem; margin: 0;">
                Questions about verification? Contact us at <a href="mailto:<?= $config['site']['contact_email'] ?>" style="color: #3b82f6;"><?= $config['site']['contact_email'] ?></a>
            </p>
        </div>
    </div>

    <script>
        // File upload handling
        function setupFileUpload(inputId, previewId, imageId) {
            const input = document.getElementById(inputId);
            const preview = document.getElementById(previewId);
            const image = document.getElementById(imageId);

            input.addEventListener('change', function(e) {
                const file = e.target.files[0];
                if (file) {
                    if (file.type.startsWith('image/')) {
                        const reader = new FileReader();
                        reader.onload = function(e) {
                            image.src = e.target.result;
                            preview.style.display = 'block';
                        };
                        reader.readAsDataURL(file);
                    } else {
                        preview.style.display = 'none';
                    }
                    checkFormCompletion();
                }
            });
        }

        // Setup file uploads
        setupFileUpload('id_front', 'id_front_preview', 'id_front_image');
        setupFileUpload('id_back', 'id_back_preview', 'id_back_image');
        setupFileUpload('selfie_with_id', 'selfie_preview', 'selfie_image');
        setupFileUpload('additional_doc', 'additional_preview', 'additional_image');

        // Check if required files are uploaded
        function checkFormCompletion() {
            const idFront = document.getElementById('id_front').files[0];
            const idBack = document.getElementById('id_back').files[0];
            const selfie = document.getElementById('selfie_with_id').files[0];
            const submitBtn = document.getElementById('submitVerification');

            if (idFront && idBack && selfie) {
                submitBtn.disabled = false;
            } else {
                submitBtn.disabled = true;
            }
        }

        // Drag and drop functionality
        document.querySelectorAll('.upload-area').forEach(area => {
            area.addEventListener('dragover', function(e) {
                e.preventDefault();
                this.classList.add('dragover');
            });

            area.addEventListener('dragleave', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');
            });

            area.addEventListener('drop', function(e) {
                e.preventDefault();
                this.classList.remove('dragover');

                const fileInput = this.onclick.toString().match(/getElementById\('([^']+)'\)/)[1];
                const input = document.getElementById(fileInput);

                if (e.dataTransfer.files.length > 0) {
                    input.files = e.dataTransfer.files;
                    input.dispatchEvent(new Event('change'));
                }
            });
        });

        // Form validation
        document.getElementById('verificationForm').addEventListener('submit', function(e) {
            const idFront = document.getElementById('id_front').files[0];
            const idBack = document.getElementById('id_back').files[0];
            const selfie = document.getElementById('selfie_with_id').files[0];

            if (!idFront || !idBack || !selfie) {
                e.preventDefault();
                alert('Please upload all 3 required photos: front of ID, back of ID, and photo holding ID before submitting.');
                return false;
            }

            // Show loading state
            const submitBtn = document.getElementById('submitVerification');
            submitBtn.textContent = 'Processing...';
            submitBtn.disabled = true;
        });
    </script>
</body>
</html>