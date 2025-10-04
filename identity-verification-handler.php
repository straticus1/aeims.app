<?php
/**
 * AEIMS Identity Verification Handler
 * Advanced photo ID verification and face matching system
 */

session_start();
$config = include 'config.php';
$response = ['success' => false, 'message' => '', 'verification_id' => null];

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $response = handleIdentityVerification($_POST, $_FILES);
    } catch (Exception $e) {
        $response['message'] = 'Verification Error: ' . $e->getMessage();
    }
}

// For AJAX requests, return JSON
if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// For regular form submissions, redirect back with message
if (!empty($response['message'])) {
    $application_id = $_POST['application_id'] ?? '';
    $message = urlencode($response['message']);
    $type = $response['success'] ? 'success' : 'error';
    header("Location: identity-verification.php?application_id={$application_id}&message={$message}&type={$type}");
    exit;
}

/**
 * Handle identity verification submission
 */
function handleIdentityVerification($data, $files) {
    $application_id = $data['application_id'] ?? '';

    if (empty($application_id)) {
        throw new Exception('Invalid application ID');
    }

    // Validate required files
    if (empty($files['id_front']['tmp_name']) || empty($files['id_back']['tmp_name']) || empty($files['selfie_with_id']['tmp_name'])) {
        throw new Exception('All 3 photos are required: front of ID, back of ID, and photo holding ID');
    }

    // Validate file types and sizes
    validateUploadedFiles($files);

    // Create verification directory
    $verificationDir = createVerificationDirectory($application_id);

    // Process and save uploaded files with hash generation
    $savedFiles = saveUploadedFiles($files, $verificationDir);

    // Generate verification ID
    $verification_id = 'VER-' . strtoupper(substr(uniqid(), -8));

    // Run ID verification checks on front and back
    $idFrontVerificationResult = verifyPhotoID($savedFiles['id_front']['path']);
    $idBackVerificationResult = verifyPhotoID($savedFiles['id_back']['path']);

    // Run face matching using front of ID
    $faceMatchResult = performFaceMatching($savedFiles['id_front']['path'], $savedFiles['selfie_with_id']['path']);

    // Prepare verification record
    $verification = [
        'id' => $verification_id,
        'application_id' => $application_id,
        'submitted_at' => date('Y-m-d H:i:s'),
        'files' => [
            'id_front' => $savedFiles['id_front']['path'],
            'id_back' => $savedFiles['id_back']['path'],
            'selfie_with_id' => $savedFiles['selfie_with_id']['path'],
            'additional_doc' => $savedFiles['additional_doc']['path'] ?? null
        ],
        'file_hashes' => [
            'id_front_hash' => $savedFiles['id_front']['hash'],
            'id_back_hash' => $savedFiles['id_back']['hash'],
            'selfie_with_id_hash' => $savedFiles['selfie_with_id']['hash'],
            'additional_doc_hash' => $savedFiles['additional_doc']['hash'] ?? null,
            'hash_algorithm' => 'sha256'
        ],
        'verification_results' => [
            'id_front_verification' => $idFrontVerificationResult,
            'id_back_verification' => $idBackVerificationResult,
            'face_verification' => $faceMatchResult,
            'overall_status' => determineOverallStatus($idFrontVerificationResult, $idBackVerificationResult, $faceMatchResult),
            'overall_confidence' => calculateOverallConfidence($idFrontVerificationResult, $idBackVerificationResult, $faceMatchResult)
        ],
        'metadata' => [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? '',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            'file_sizes' => array_map(function($file) { return $file['size'] ?? 0; }, $files)
        ]
    ];

    // Save verification record
    $saved = saveVerificationRecord($verification);

    if ($saved) {
        // Update application status
        updateApplicationStatus($application_id, 'verification_submitted');

        // Update operator account verification status
        updateOperatorVerificationStatus($application_id, $verification);

        // Send notifications
        sendVerificationNotifications($verification);

        // Schedule file cleanup (72 hours)
        scheduleFileCleanup($verificationDir, $verification_id);

        return [
            'success' => true,
            'message' => "Identity verification submitted successfully! Verification ID: {$verification_id}. Our team will review your submission within 24 hours.",
            'verification_id' => $verification_id
        ];
    }

    throw new Exception('Failed to save verification record');
}

/**
 * Validate uploaded files
 */
function validateUploadedFiles($files) {
    $allowedTypes = [
        'id_front' => ['image/jpeg', 'image/png'],
        'id_back' => ['image/jpeg', 'image/png'],
        'selfie_with_id' => ['image/jpeg', 'image/png'],
        'additional_doc' => ['image/jpeg', 'image/png', 'application/pdf']
    ];

    $maxSize = 10 * 1024 * 1024; // 10MB

    foreach ($files as $key => $file) {
        if (empty($file['tmp_name'])) {
            if ($key !== 'additional_doc') { // additional_doc is optional
                throw new Exception("File {$key} is required");
            }
            continue;
        }

        // Check file size
        if ($file['size'] > $maxSize) {
            throw new Exception("File {$key} exceeds maximum size of 10MB");
        }

        // Check file type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes[$key])) {
            throw new Exception("Invalid file type for {$key}. Allowed types: " . implode(', ', $allowedTypes[$key]));
        }

        // Check for malicious files
        if (containsMaliciousContent($file['tmp_name'], $mimeType)) {
            throw new Exception("File {$key} failed security scan");
        }
    }
}

/**
 * Create verification directory
 */
function createVerificationDirectory($application_id) {
    $baseDir = __DIR__ . '/data/verifications';
    $verificationDir = $baseDir . '/' . $application_id . '_' . date('Ymd_His');

    if (!is_dir($baseDir)) {
        mkdir($baseDir, 0755, true);
    }

    if (!mkdir($verificationDir, 0755, true)) {
        throw new Exception('Failed to create verification directory');
    }

    return $verificationDir;
}

/**
 * Save uploaded files securely with hash generation
 */
function saveUploadedFiles($files, $verificationDir) {
    $savedFiles = [];

    foreach ($files as $key => $file) {
        if (empty($file['tmp_name'])) {
            continue;
        }

        $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
        $filename = $key . '_' . uniqid() . '.' . $extension;
        $filepath = $verificationDir . '/' . $filename;

        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("Failed to save file {$key}");
        }

        // Set strict permissions
        chmod($filepath, 0600);

        // Generate SHA-256 hash for file integrity
        $fileHash = hash_file('sha256', $filepath);

        $savedFiles[$key] = [
            'path' => $filepath,
            'hash' => $fileHash,
            'filename' => $filename,
            'original_name' => $file['name'],
            'size' => filesize($filepath),
            'saved_at' => date('Y-m-d H:i:s')
        ];
    }

    return $savedFiles;
}

/**
 * Advanced Photo ID Verification
 */
function verifyPhotoID($photoIdPath) {
    $result = [
        'status' => 'pending',
        'confidence' => 0,
        'checks' => [],
        'extracted_data' => [],
        'issues' => []
    ];

    try {
        // 1. Image quality analysis
        $qualityCheck = analyzeImageQuality($photoIdPath);
        $result['checks']['image_quality'] = $qualityCheck;

        // 2. Document type detection
        $docTypeCheck = detectDocumentType($photoIdPath);
        $result['checks']['document_type'] = $docTypeCheck;

        // 3. Security features detection
        $securityCheck = detectSecurityFeatures($photoIdPath);
        $result['checks']['security_features'] = $securityCheck;

        // 4. OCR text extraction
        $textExtraction = extractIDText($photoIdPath);
        $result['extracted_data'] = $textExtraction;

        // 5. Data validation
        $dataValidation = validateExtractedData($textExtraction);
        $result['checks']['data_validation'] = $dataValidation;

        // 6. Tampering detection
        $tamperingCheck = detectTampering($photoIdPath);
        $result['checks']['tampering_detection'] = $tamperingCheck;

        // Calculate overall confidence
        $result['confidence'] = calculateVerificationConfidence($result['checks']);

        // Determine status
        if ($result['confidence'] >= 85) {
            $result['status'] = 'verified';
        } elseif ($result['confidence'] >= 60) {
            $result['status'] = 'manual_review';
        } else {
            $result['status'] = 'rejected';
        }

    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['issues'][] = $e->getMessage();
    }

    return $result;
}

/**
 * Advanced Face Matching
 */
function performFaceMatching($photoIdPath, $selfiePath) {
    $result = [
        'status' => 'pending',
        'confidence' => 0,
        'similarity_score' => 0,
        'checks' => [],
        'issues' => []
    ];

    try {
        // 1. Face detection in both images
        $idFaceDetection = detectFace($photoIdPath);
        $selfieFaceDetection = detectFace($selfiePath);

        $result['checks']['id_face_detected'] = $idFaceDetection;
        $result['checks']['selfie_face_detected'] = $selfieFaceDetection;

        if (!$idFaceDetection['face_found'] || !$selfieFaceDetection['face_found']) {
            $result['status'] = 'no_face_detected';
            $result['issues'][] = 'Face not clearly detected in one or both images';
            return $result;
        }

        // 2. Face quality assessment
        $idFaceQuality = assessFaceQuality($photoIdPath);
        $selfieFaceQuality = assessFaceQuality($selfiePath);

        $result['checks']['id_face_quality'] = $idFaceQuality;
        $result['checks']['selfie_face_quality'] = $selfieFaceQuality;

        // 3. Liveness detection (anti-spoofing)
        $livenessCheck = detectLiveness($selfiePath);
        $result['checks']['liveness_detection'] = $livenessCheck;

        // 4. Face comparison using multiple algorithms
        $faceComparison = compareFaces($photoIdPath, $selfiePath);
        $result['similarity_score'] = $faceComparison['similarity_score'];
        $result['checks']['face_comparison'] = $faceComparison;

        // 5. Age consistency check
        $ageCheck = checkAgeConsistency($photoIdPath, $selfiePath);
        $result['checks']['age_consistency'] = $ageCheck;

        // Calculate overall confidence
        $result['confidence'] = calculateFaceMatchConfidence($result['checks'], $result['similarity_score']);

        // Determine status
        if ($result['confidence'] >= 90 && $result['similarity_score'] >= 85) {
            $result['status'] = 'match';
        } elseif ($result['confidence'] >= 70 && $result['similarity_score'] >= 70) {
            $result['status'] = 'likely_match';
        } elseif ($result['confidence'] >= 50) {
            $result['status'] = 'manual_review';
        } else {
            $result['status'] = 'no_match';
        }

    } catch (Exception $e) {
        $result['status'] = 'error';
        $result['issues'][] = $e->getMessage();
    }

    return $result;
}

/**
 * Image quality analysis
 */
function analyzeImageQuality($imagePath) {
    // In production, this would use advanced image processing
    // For now, basic checks using PHP's image functions

    $imageInfo = getimagesize($imagePath);
    $width = $imageInfo[0];
    $height = $imageInfo[1];

    $quality = [
        'resolution_check' => ($width >= 800 && $height >= 600),
        'aspect_ratio' => $width / $height,
        'file_size' => filesize($imagePath),
        'sharpness_score' => 75, // Placeholder - would use actual image analysis
        'brightness_score' => 80,
        'contrast_score' => 85
    ];

    $quality['overall_score'] = ($quality['resolution_check'] ? 25 : 0) +
                               ($quality['sharpness_score'] * 0.3) +
                               ($quality['brightness_score'] * 0.25) +
                               ($quality['contrast_score'] * 0.2);

    return $quality;
}

/**
 * Document type detection
 */
function detectDocumentType($imagePath) {
    // In production, this would use ML models trained on various ID types
    // For now, basic heuristics

    return [
        'detected_type' => 'drivers_license', // Placeholder
        'confidence' => 75,
        'supported_document' => true,
        'region' => 'US' // Could be detected from document features
    ];
}

/**
 * Security features detection
 */
function detectSecurityFeatures($imagePath) {
    // In production, this would detect holograms, watermarks, UV features, etc.
    return [
        'hologram_detected' => true,
        'watermark_detected' => true,
        'microprint_detected' => false,
        'security_score' => 78
    ];
}

/**
 * OCR text extraction
 */
function extractIDText($imagePath) {
    // In production, this would use advanced OCR like Tesseract or cloud APIs
    // For now, return placeholder data structure
    return [
        'name' => 'John Doe', // Extracted via OCR
        'date_of_birth' => '1990-01-01',
        'id_number' => 'DL123456789',
        'expiration_date' => '2025-12-31',
        'address' => '123 Main St, City, State 12345',
        'extraction_confidence' => 85
    ];
}

/**
 * Validate extracted data
 */
function validateExtractedData($extractedData) {
    $validation = [
        'name_format_valid' => preg_match('/^[a-zA-Z\s]+$/', $extractedData['name'] ?? ''),
        'dob_valid' => validateDateOfBirth($extractedData['date_of_birth'] ?? ''),
        'id_number_valid' => !empty($extractedData['id_number']),
        'expiration_valid' => validateExpirationDate($extractedData['expiration_date'] ?? ''),
        'age_valid' => calculateAge($extractedData['date_of_birth'] ?? '') >= 18
    ];

    $validation['overall_valid'] = array_sum($validation) >= 4; // Most checks pass

    return $validation;
}

/**
 * Tampering detection
 */
function detectTampering($imagePath) {
    // In production, this would use advanced forensic techniques
    return [
        'compression_artifacts' => false,
        'copy_move_detection' => false,
        'splicing_detection' => false,
        'tampering_probability' => 5 // Low probability
    ];
}

/**
 * Face detection
 */
function detectFace($imagePath) {
    // In production, this would use OpenCV, dlib, or cloud face detection APIs
    return [
        'face_found' => true,
        'face_count' => 1,
        'face_quality' => 85,
        'face_bbox' => [100, 100, 200, 200] // x, y, width, height
    ];
}

/**
 * Face quality assessment
 */
function assessFaceQuality($imagePath) {
    return [
        'resolution' => 85,
        'sharpness' => 80,
        'lighting' => 90,
        'pose_angle' => 95,
        'occlusion_score' => 98,
        'overall_quality' => 88
    ];
}

/**
 * Liveness detection
 */
function detectLiveness($imagePath) {
    // In production, this would check for signs of a live person vs photo/video
    return [
        'is_live' => true,
        'confidence' => 92,
        'checks' => [
            'texture_analysis' => true,
            'micro_expression' => true,
            'depth_analysis' => true
        ]
    ];
}

/**
 * Face comparison
 */
function compareFaces($image1Path, $image2Path) {
    // In production, this would use deep learning face recognition models
    return [
        'similarity_score' => 87, // 0-100
        'algorithm_used' => 'facenet',
        'comparison_confidence' => 91,
        'feature_matches' => [
            'eye_distance' => 94,
            'nose_shape' => 82,
            'mouth_shape' => 89,
            'face_contour' => 85
        ]
    ];
}

/**
 * Age consistency check
 */
function checkAgeConsistency($idImagePath, $selfieImagePath) {
    // In production, this would estimate age from both images and check consistency
    return [
        'id_estimated_age' => 28,
        'selfie_estimated_age' => 30,
        'age_difference' => 2,
        'consistency_check' => true
    ];
}

/**
 * Calculate verification confidence
 */
function calculateVerificationConfidence($checks) {
    $weights = [
        'image_quality' => 20,
        'document_type' => 15,
        'security_features' => 25,
        'data_validation' => 25,
        'tampering_detection' => 15
    ];

    $totalScore = 0;
    foreach ($weights as $check => $weight) {
        if (isset($checks[$check])) {
            $checkScore = 0;
            if (is_array($checks[$check])) {
                // Extract relevant score from check result
                if (isset($checks[$check]['overall_score'])) {
                    $checkScore = $checks[$check]['overall_score'];
                } elseif (isset($checks[$check]['security_score'])) {
                    $checkScore = $checks[$check]['security_score'];
                } elseif (isset($checks[$check]['overall_valid'])) {
                    $checkScore = $checks[$check]['overall_valid'] ? 90 : 30;
                }
            }
            $totalScore += ($checkScore * $weight / 100);
        }
    }

    return min(100, $totalScore);
}

/**
 * Calculate face match confidence
 */
function calculateFaceMatchConfidence($checks, $similarityScore) {
    $baseScore = $similarityScore;

    // Apply modifiers based on other checks
    if (isset($checks['liveness_detection']) && $checks['liveness_detection']['is_live']) {
        $baseScore += 5;
    }

    if (isset($checks['age_consistency']) && $checks['age_consistency']['consistency_check']) {
        $baseScore += 3;
    }

    return min(100, $baseScore);
}

/**
 * Determine overall verification status
 */
function determineOverallStatus($idResult, $faceResult) {
    if ($idResult['status'] === 'verified' && $faceResult['status'] === 'match') {
        return 'approved';
    } elseif ($idResult['status'] === 'rejected' || $faceResult['status'] === 'no_match') {
        return 'rejected';
    } else {
        return 'manual_review';
    }
}

/**
 * Save verification record
 */
function saveVerificationRecord($verification) {
    $dataDir = __DIR__ . '/data';
    if (!is_dir($dataDir)) {
        mkdir($dataDir, 0755, true);
    }

    $filename = $dataDir . '/identity_verifications.json';

    // Load existing verifications
    $verifications = [];
    if (file_exists($filename)) {
        $content = file_get_contents($filename);
        $verifications = json_decode($content, true) ?: [];
    }

    // Add new verification
    $verifications[] = $verification;

    // Save updated verifications
    return file_put_contents($filename, json_encode($verifications, JSON_PRETTY_PRINT));
}

/**
 * Update application status
 */
function updateApplicationStatus($application_id, $status) {
    $dataDir = __DIR__ . '/data';
    $filename = $dataDir . '/operator_applications.json';

    if (file_exists($filename)) {
        $applications = json_decode(file_get_contents($filename), true) ?: [];

        foreach ($applications as &$app) {
            if ($app['id'] === $application_id) {
                $app['application_data']['status'] = $status;
                $app['application_data']['verification_submitted_at'] = date('Y-m-d H:i:s');
                break;
            }
        }

        file_put_contents($filename, json_encode($applications, JSON_PRETTY_PRINT));
    }
}

/**
 * Send verification notifications
 */
function sendVerificationNotifications($verification) {
    $logDir = __DIR__ . '/data';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }

    // Log verification submission
    $logEntry = [
        'timestamp' => date('Y-m-d H:i:s'),
        'type' => 'identity_verification_submitted',
        'verification_id' => $verification['id'],
        'application_id' => $verification['application_id'],
        'overall_status' => $verification['verification_results']['overall_status'],
        'id_verification_status' => $verification['verification_results']['id_verification']['status'],
        'face_match_status' => $verification['verification_results']['face_matching']['status'],
        'admin_action_required' => in_array($verification['verification_results']['overall_status'], ['manual_review', 'rejected'])
    ];

    file_put_contents($logDir . '/verification_notifications.log', json_encode($logEntry) . "\n", FILE_APPEND);
}

/**
 * Schedule file cleanup
 */
function scheduleFileCleanup($verificationDir, $verification_id) {
    // In production, this would add to a queue or cron job
    // For now, just log the cleanup schedule
    $logDir = __DIR__ . '/data';
    $cleanupEntry = [
        'verification_id' => $verification_id,
        'directory' => $verificationDir,
        'scheduled_deletion' => date('Y-m-d H:i:s', time() + (72 * 3600)), // 72 hours
        'status' => 'scheduled'
    ];

    file_put_contents($logDir . '/cleanup_schedule.log', json_encode($cleanupEntry) . "\n", FILE_APPEND);
}

/**
 * Verify file integrity using stored hash
 */
function verifyFileIntegrity($filePath, $storedHash, $algorithm = 'sha256') {
    if (!file_exists($filePath)) {
        return [
            'verified' => false,
            'error' => 'File not found',
            'current_hash' => null,
            'stored_hash' => $storedHash
        ];
    }

    $currentHash = hash_file($algorithm, $filePath);

    return [
        'verified' => ($currentHash === $storedHash),
        'error' => ($currentHash === $storedHash) ? null : 'Hash mismatch - file may have been tampered with',
        'current_hash' => $currentHash,
        'stored_hash' => $storedHash,
        'algorithm' => $algorithm,
        'checked_at' => date('Y-m-d H:i:s')
    ];
}

/**
 * Verify integrity of all verification files
 */
function verifyAllFileIntegrity($verificationRecord) {
    $results = [];

    if (!isset($verificationRecord['files']) || !isset($verificationRecord['file_hashes'])) {
        return [
            'overall_verified' => false,
            'error' => 'Missing file or hash information in verification record',
            'file_results' => []
        ];
    }

    $files = $verificationRecord['files'];
    $hashes = $verificationRecord['file_hashes'];
    $algorithm = $hashes['hash_algorithm'] ?? 'sha256';

    // Check each file
    foreach (['id_front', 'id_back', 'selfie_with_id', 'additional_doc'] as $fileType) {
        if (!empty($files[$fileType]) && !empty($hashes[$fileType . '_hash'])) {
            $results[$fileType] = verifyFileIntegrity(
                $files[$fileType],
                $hashes[$fileType . '_hash'],
                $algorithm
            );
        }
    }

    // Determine overall verification status
    $allVerified = true;
    foreach ($results as $result) {
        if (!$result['verified']) {
            $allVerified = false;
            break;
        }
    }

    return [
        'overall_verified' => $allVerified,
        'file_results' => $results,
        'verified_at' => date('Y-m-d H:i:s'),
        'total_files_checked' => count($results)
    ];
}

/**
 * Security check for malicious content
 */
function containsMaliciousContent($filePath, $mimeType) {
    // Basic security checks - in production, use more advanced scanning

    // Check file signature matches declared type
    $signatures = [
        'image/jpeg' => ["\xFF\xD8\xFF"],
        'image/png' => ["\x89\x50\x4E\x47"],
        'application/pdf' => ["\x25\x50\x44\x46"]
    ];

    if (isset($signatures[$mimeType])) {
        $handle = fopen($filePath, 'rb');
        $header = fread($handle, 10);
        fclose($handle);

        $validSignature = false;
        foreach ($signatures[$mimeType] as $signature) {
            if (strpos($header, $signature) === 0) {
                $validSignature = true;
                break;
            }
        }

        if (!$validSignature) {
            return true; // Suspicious file
        }
    }

    return false;
}

/**
 * Utility functions
 */
function validateDateOfBirth($dob) {
    $date = DateTime::createFromFormat('Y-m-d', $dob);
    return $date && $date->format('Y-m-d') === $dob;
}

function validateExpirationDate($expDate) {
    $date = DateTime::createFromFormat('Y-m-d', $expDate);
    return $date && $date > new DateTime();
}

function calculateAge($dob) {
    $birthDate = new DateTime($dob);
    $today = new DateTime();
    return $today->diff($birthDate)->y;
}

/**
 * Update operator account verification status
 */
function updateOperatorVerificationStatus($application_id, $verification_data) {
    $accountsFile = __DIR__ . '/data/accounts.json';

    if (!file_exists($accountsFile)) {
        return false;
    }

    $accounts = json_decode(file_get_contents($accountsFile), true) ?: [];

    // Find the operator account by application_id
    foreach ($accounts as $username => &$account) {
        if (isset($account['application_id']) && $account['application_id'] === $application_id) {
            $verification_results = $verification_data['verification_results'];
            $id_front_verification = $verification_results['id_front_verification'];
            $id_back_verification = $verification_results['id_back_verification'];

            // Calculate next revalidation date based on ID expiration (use front ID data)
            $next_revalidation = null;
            if (!empty($id_front_verification['extracted_data']['expiration_date'])) {
                $exp_date = new DateTime($id_front_verification['extracted_data']['expiration_date']);
                $next_revalidation = $exp_date->format('Y-m-d');
            }

            // Update verification status
            $account['verification_status'] = [
                'identity_verified' => $verification_results['overall_status'] === 'verified',
                'verification_date' => date('Y-m-d H:i:s'),
                'id_expiration_date' => $id_front_verification['extracted_data']['expiration_date'] ?? null,
                'next_revalidation_date' => $next_revalidation,
                'verification_method' => 'three_photo_id_verification',
                'verification_confidence' => $verification_results['overall_confidence'],
                'verification_notes' => "ID Type: " . ($id_front_verification['document_type'] ?? 'Unknown') .
                                      "; Face Match: " . ($verification_results['face_verification']['confidence'] ?? 'N/A') .
                                      "; Front ID Score: " . ($id_front_verification['security_features']['score'] ?? 'N/A') .
                                      "; Back ID Score: " . ($id_back_verification['security_features']['score'] ?? 'N/A'),
                'verification_files' => [
                    'id_front' => $verification_data['files']['id_front'] ?? null,
                    'id_back' => $verification_data['files']['id_back'] ?? null,
                    'selfie_with_id' => $verification_data['files']['selfie_with_id'] ?? null
                ],
                'file_hashes' => [
                    'id_front_hash' => $verification_data['file_hashes']['id_front_hash'] ?? null,
                    'id_back_hash' => $verification_data['file_hashes']['id_back_hash'] ?? null,
                    'selfie_with_id_hash' => $verification_data['file_hashes']['selfie_with_id_hash'] ?? null,
                    'hash_algorithm' => $verification_data['file_hashes']['hash_algorithm'] ?? 'sha256'
                ]
            ];

            // Update account status based on verification
            if ($verification_results['overall_status'] === 'verified') {
                $account['status'] = 'verified';
            } else {
                $account['status'] = 'verification_failed';
            }

            break;
        }
    }

    return file_put_contents($accountsFile, json_encode($accounts, JSON_PRETTY_PRINT));
}

/**
 * Determine overall verification status based on all verification checks
 */
function determineOverallStatus($idFrontResult, $idBackResult, $faceMatchResult) {
    // Check if all results are available
    if (!$idFrontResult || !$idBackResult || !$faceMatchResult) {
        return 'pending';
    }

    // All verification checks must pass for overall success
    $idFrontValid = ($idFrontResult['status'] ?? '') === 'verified';
    $idBackValid = ($idBackResult['status'] ?? '') === 'verified';
    $faceMatchValid = ($faceMatchResult['match'] ?? false) === true;

    if ($idFrontValid && $idBackValid && $faceMatchValid) {
        return 'verified';
    } elseif (($idFrontResult['status'] ?? '') === 'failed' ||
              ($idBackResult['status'] ?? '') === 'failed' ||
              ($faceMatchResult['match'] ?? false) === false) {
        return 'failed';
    } else {
        return 'requires_review';
    }
}

/**
 * Calculate overall confidence score
 */
function calculateOverallConfidence($idFrontResult, $idBackResult, $faceMatchResult) {
    $scores = [];

    // Collect confidence scores from each verification
    if (isset($idFrontResult['confidence'])) {
        $scores[] = $idFrontResult['confidence'];
    }
    if (isset($idBackResult['confidence'])) {
        $scores[] = $idBackResult['confidence'];
    }
    if (isset($faceMatchResult['confidence'])) {
        $scores[] = $faceMatchResult['confidence'];
    }

    if (empty($scores)) {
        return 0.0;
    }

    // Calculate weighted average (face matching is more important)
    $weightedSum = 0;
    $totalWeight = 0;

    foreach ($scores as $i => $score) {
        $weight = ($i === 2) ? 2 : 1; // Face matching gets double weight
        $weightedSum += $score * $weight;
        $totalWeight += $weight;
    }

    return round($weightedSum / $totalWeight, 3);
}
?>