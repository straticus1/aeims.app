<?php
/**
 * Barcode Scanner for US Government IDs
 * Government ID Verification Services by After Dark Systems
 *
 * Supports:
 * - PDF417 (US Driver's Licenses - most states)
 * - Code 128 (Some state IDs)
 * - QR Codes (Enhanced driver's licenses)
 * - MRZ (Machine Readable Zone - Passports)
 */

namespace IDVerify;

class BarcodeScanner {
    private array $supportedFormats = ['PDF417', 'CODE_128', 'QR_CODE', 'MRZ'];

    /**
     * Scan and decode barcode from image
     */
    public function scanBarcode(string $imageData): array {
        // If base64 encoded, decode first
        if (strpos($imageData, 'data:image') === 0) {
            $imageData = $this->decodeBase64Image($imageData);
        }

        // Try each barcode format
        foreach ($this->supportedFormats as $format) {
            $result = $this->scanFormat($imageData, $format);
            if ($result['success']) {
                return $result;
            }
        }

        return [
            'success' => false,
            'error' => 'No readable barcode found in image'
        ];
    }

    /**
     * Scan specific barcode format
     */
    private function scanFormat(string $imageData, string $format): array {
        switch ($format) {
            case 'PDF417':
                return $this->scanPDF417($imageData);
            case 'CODE_128':
                return $this->scanCode128($imageData);
            case 'QR_CODE':
                return $this->scanQRCode($imageData);
            case 'MRZ':
                return $this->scanMRZ($imageData);
            default:
                return ['success' => false, 'error' => 'Unsupported format'];
        }
    }

    /**
     * Scan PDF417 barcode (most US driver's licenses)
     */
    private function scanPDF417(string $imageData): array {
        // PDF417 contains AAMVA standard data
        // Format: @\n\u001e\rANSI 636000... (varies by state)

        // This is a placeholder implementation
        // In production, use ZXing library or similar
        $rawData = $this->extractPDF417Data($imageData);

        if (empty($rawData)) {
            return ['success' => false];
        }

        $parsed = $this->parseAAMVAData($rawData);

        return [
            'success' => true,
            'format' => 'PDF417',
            'raw_data' => $rawData,
            'parsed_data' => $parsed,
            'confidence' => 95.0
        ];
    }

    /**
     * Parse AAMVA standard data from driver's license
     */
    private function parseAAMVAData(string $data): array {
        $parsed = [
            'first_name' => '',
            'last_name' => '',
            'middle_name' => '',
            'date_of_birth' => '',
            'id_number' => '',
            'address' => '',
            'city' => '',
            'state' => '',
            'zip' => '',
            'issue_date' => '',
            'expiration_date' => '',
            'sex' => '',
            'height' => '',
            'weight' => '',
            'eye_color' => '',
            'hair_color' => '',
            'restrictions' => '',
            'endorsements' => ''
        ];

        // AAMVA field codes
        $fieldMap = [
            'DAC' => 'first_name',
            'DCS' => 'last_name',
            'DAD' => 'middle_name',
            'DBB' => 'date_of_birth',
            'DAQ' => 'id_number',
            'DAG' => 'address',
            'DAI' => 'city',
            'DAJ' => 'state',
            'DAK' => 'zip',
            'DBD' => 'issue_date',
            'DBA' => 'expiration_date',
            'DBC' => 'sex',
            'DAU' => 'height',
            'DAW' => 'weight',
            'DAY' => 'eye_color',
            'DAZ' => 'hair_color',
            'DAR' => 'restrictions',
            'DAS' => 'endorsements'
        ];

        // Parse each field
        foreach ($fieldMap as $code => $field) {
            if (preg_match('/' . $code . '([^\n\r]+)/', $data, $matches)) {
                $parsed[$field] = trim($matches[1]);
            }
        }

        // Format dates (YYYYMMDD to YYYY-MM-DD)
        foreach (['date_of_birth', 'issue_date', 'expiration_date'] as $dateField) {
            if (!empty($parsed[$dateField]) && strlen($parsed[$dateField]) === 8) {
                $parsed[$dateField] = substr($parsed[$dateField], 0, 4) . '-' .
                                      substr($parsed[$dateField], 4, 2) . '-' .
                                      substr($parsed[$dateField], 6, 2);
            }
        }

        return $parsed;
    }

    /**
     * Extract PDF417 barcode data from image
     * Placeholder - integrate ZXing or similar library
     */
    private function extractPDF417Data(string $imageData): string {
        // In production, use: php-zxing, zbarcode, or external service
        // For now, return empty to indicate scanning not yet implemented
        return '';
    }

    /**
     * Scan Code 128 barcode
     */
    private function scanCode128(string $imageData): array {
        // Code 128 is simpler than PDF417
        // Used by some state IDs
        return ['success' => false]; // Placeholder
    }

    /**
     * Scan QR Code (Enhanced Driver's Licenses)
     */
    private function scanQRCode(string $imageData): array {
        // Some enhanced DLs use QR codes
        return ['success' => false]; // Placeholder
    }

    /**
     * Scan MRZ (Machine Readable Zone) for passports
     */
    private function scanMRZ(string $imageData): array {
        // MRZ is 2-3 lines of OCR text at bottom of passport
        // Format: P<USADOE<<JOHN<<<<<<<<<<<<<<<<<<<<<<<<<<<<
        //         1234567890USA9001011M2501017<<<<<<<<<<<<<<04

        $mrzLines = $this->extractMRZLines($imageData);

        if (count($mrzLines) < 2) {
            return ['success' => false];
        }

        $parsed = $this->parseMRZ($mrzLines);

        return [
            'success' => true,
            'format' => 'MRZ',
            'raw_data' => implode("\n", $mrzLines),
            'parsed_data' => $parsed,
            'confidence' => 90.0
        ];
    }

    /**
     * Parse MRZ data from passport
     */
    private function parseMRZ(array $lines): array {
        $parsed = [
            'document_type' => '',
            'country_code' => '',
            'last_name' => '',
            'first_name' => '',
            'passport_number' => '',
            'nationality' => '',
            'date_of_birth' => '',
            'sex' => '',
            'expiration_date' => ''
        ];

        if (isset($lines[0])) {
            // Line 1: P<COUNTRY_CODE<LASTNAME<<FIRSTNAME<<<...
            $parsed['document_type'] = substr($lines[0], 0, 1); // P = Passport
            $parsed['country_code'] = substr($lines[0], 2, 3);

            $names = explode('<<', substr($lines[0], 5));
            $parsed['last_name'] = str_replace('<', ' ', trim($names[0]));
            $parsed['first_name'] = isset($names[1]) ? str_replace('<', ' ', trim($names[1])) : '';
        }

        if (isset($lines[1])) {
            // Line 2: PASSPORT_NUM<NATIONALITY<BIRTH_DATE<SEX<EXP_DATE<...
            $parsed['passport_number'] = substr($lines[1], 0, 9);
            $parsed['nationality'] = substr($lines[1], 10, 3);

            $birthDate = substr($lines[1], 13, 6); // YYMMDD
            $parsed['date_of_birth'] = $this->formatMRZDate($birthDate);

            $parsed['sex'] = substr($lines[1], 20, 1);

            $expDate = substr($lines[1], 21, 6); // YYMMDD
            $parsed['expiration_date'] = $this->formatMRZDate($expDate);
        }

        return $parsed;
    }

    /**
     * Format MRZ date (YYMMDD) to YYYY-MM-DD
     */
    private function formatMRZDate(string $date): string {
        if (strlen($date) !== 6) {
            return '';
        }

        $year = substr($date, 0, 2);
        $month = substr($date, 2, 2);
        $day = substr($date, 4, 2);

        // Determine century (assume 00-30 = 2000s, 31-99 = 1900s)
        $yearFull = (intval($year) <= 30) ? '20' . $year : '19' . $year;

        return $yearFull . '-' . $month . '-' . $day;
    }

    /**
     * Extract MRZ lines from passport image using OCR
     * Placeholder - integrate Tesseract OCR
     */
    private function extractMRZLines(string $imageData): array {
        // In production, use Tesseract OCR to extract MRZ lines
        // MRZ is typically at bottom of passport in OCR-B font
        return []; // Placeholder
    }

    /**
     * Decode base64 image data
     */
    private function decodeBase64Image(string $base64Data): string {
        // Remove data:image/xxx;base64, prefix if present
        if (preg_match('/^data:image\/\w+;base64,/', $base64Data)) {
            $base64Data = preg_replace('/^data:image\/\w+;base64,/', '', $base64Data);
        }

        return base64_decode($base64Data);
    }

    /**
     * Validate barcode data integrity
     */
    public function validateBarcode(array $barcodeData): array {
        if (!$barcodeData['success']) {
            return [
                'valid' => false,
                'errors' => ['No barcode data to validate']
            ];
        }

        $errors = [];
        $parsed = $barcodeData['parsed_data'] ?? [];

        // Check required fields
        $requiredFields = ['first_name', 'last_name', 'date_of_birth', 'id_number'];
        foreach ($requiredFields as $field) {
            if (empty($parsed[$field])) {
                $errors[] = "Missing required field: $field";
            }
        }

        // Validate date of birth
        if (!empty($parsed['date_of_birth'])) {
            $dob = strtotime($parsed['date_of_birth']);
            if ($dob === false || $dob > time()) {
                $errors[] = "Invalid date of birth";
            }

            // Check age (must be at least 18)
            $age = (time() - $dob) / (365.25 * 24 * 60 * 60);
            if ($age < 18) {
                $errors[] = "Must be at least 18 years old";
            }
        }

        // Validate expiration date
        if (!empty($parsed['expiration_date'])) {
            $expDate = strtotime($parsed['expiration_date']);
            if ($expDate !== false && $expDate < time()) {
                $errors[] = "ID has expired";
            }
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors,
            'confidence' => $barcodeData['confidence'] ?? 0
        ];
    }

    /**
     * Get supported formats
     */
    public function getSupportedFormats(): array {
        return $this->supportedFormats;
    }
}
