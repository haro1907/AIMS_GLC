<?php
// data/security.php
// Security helper functions for input validation and sanitization

class Security {
    
    // Generate CSRF token
    public static function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token']) || !isset($_SESSION['csrf_token_time']) || 
            (time() - $_SESSION['csrf_token_time']) > CSRF_TOKEN_EXPIRE) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
            $_SESSION['csrf_token_time'] = time();
        }
        return $_SESSION['csrf_token'];
    }
    
    // Verify CSRF token
    public static function verifyCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && 
               isset($_SESSION['csrf_token_time']) &&
               (time() - $_SESSION['csrf_token_time']) <= CSRF_TOKEN_EXPIRE &&
               hash_equals($_SESSION['csrf_token'], $token);
    }
    
    // Sanitize input data
    public static function sanitizeInput($data, $type = 'string') {
        if (is_array($data)) {
            return array_map(function($item) use ($type) {
                return self::sanitizeInput($item, $type);
            }, $data);
        }
        
        // Remove null bytes
        $data = str_replace("\0", '', $data);
        
        switch ($type) {
            case 'int':
                return (int) filter_var($data, FILTER_SANITIZE_NUMBER_INT);
            
            case 'float':
                return (float) filter_var($data, FILTER_SANITIZE_NUMBER_FLOAT, FILTER_FLAG_ALLOW_FRACTION);
            
            case 'email':
                return filter_var($data, FILTER_SANITIZE_EMAIL);
            
            case 'url':
                return filter_var($data, FILTER_SANITIZE_URL);
            
            case 'html':
                return htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            
            case 'filename':
                return preg_replace('/[^A-Za-z0-9._-]/', '_', $data);
            
            case 'alphanumeric':
                return preg_replace('/[^A-Za-z0-9]/', '', $data);
            
            case 'string':
            default:
                return trim(htmlspecialchars($data, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
    }
    
    // Validate input data
    public static function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            $value = $data[$field] ?? '';
            $ruleList = explode('|', $rule);
            
            foreach ($ruleList as $r) {
                $ruleParts = explode(':', $r);
                $ruleName = $ruleParts[0];
                $ruleParam = $ruleParts[1] ?? null;
                
                switch ($ruleName) {
                    case 'required':
                        if (empty($value)) {
                            $errors[$field][] = ucfirst($field) . ' is required.';
                        }
                        break;
                    
                    case 'min':
                        if (strlen($value) < $ruleParam) {
                            $errors[$field][] = ucfirst($field) . ' must be at least ' . $ruleParam . ' characters.';
                        }
                        break;
                    
                    case 'max':
                        if (strlen($value) > $ruleParam) {
                            $errors[$field][] = ucfirst($field) . ' must not exceed ' . $ruleParam . ' characters.';
                        }
                        break;
                    
                    case 'email':
                        if (!empty($value) && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                            $errors[$field][] = ucfirst($field) . ' must be a valid email address.';
                        }
                        break;
                    
                    case 'numeric':
                        if (!empty($value) && !is_numeric($value)) {
                            $errors[$field][] = ucfirst($field) . ' must be a number.';
                        }
                        break;
                    
                    case 'alpha':
                        if (!empty($value) && !preg_match('/^[A-Za-z\s]+$/', $value)) {
                            $errors[$field][] = ucfirst($field) . ' must contain only letters.';
                        }
                        break;
                    
                    case 'alphanumeric':
                        if (!empty($value) && !preg_match('/^[A-Za-z0-9]+$/', $value)) {
                            $errors[$field][] = ucfirst($field) . ' must contain only letters and numbers.';
                        }
                        break;
                    
                    case 'unique':
                        if (!empty($value)) {
                            $tableName = $ruleParam;
                            $existing = fetchOne("SELECT id FROM $tableName WHERE $field = ?", [$value]);
                            if ($existing) {
                                $errors[$field][] = ucfirst($field) . ' already exists.';
                            }
                        }
                        break;
                }
            }
        }
        
        return $errors;
    }
    
    // Validate file upload
    public static function validateFileUpload($file, $allowedTypes = null, $maxSize = null) {
        $errors = [];
        
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            $uploadErrors = [
                UPLOAD_ERR_INI_SIZE => 'File is too large (server limit)',
                UPLOAD_ERR_FORM_SIZE => 'File is too large (form limit)',
                UPLOAD_ERR_PARTIAL => 'File was only partially uploaded',
                UPLOAD_ERR_NO_FILE => 'No file was uploaded',
                UPLOAD_ERR_NO_TMP_DIR => 'Missing temporary folder',
                UPLOAD_ERR_CANT_WRITE => 'Failed to write file to disk',
                UPLOAD_ERR_EXTENSION => 'File upload stopped by extension'
            ];
            
            $errors[] = $uploadErrors[$file['error']] ?? 'Unknown upload error';
            return $errors;
        }
        
        // Check file size
        $maxSize = $maxSize ?? MAX_FILE_SIZE;
        if ($file['size'] > $maxSize) {
            $errors[] = 'File size exceeds maximum allowed size of ' . self::formatBytes($maxSize);
        }
        
        // Check file type
        $allowedTypes = $allowedTypes ?? ALLOWED_FILE_TYPES;
        $fileExtension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        
        if (!in_array($fileExtension, $allowedTypes)) {
            $errors[] = 'File type not allowed. Allowed types: ' . implode(', ', $allowedTypes);
        }
        
        // Check MIME type
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        $allowedMimeTypes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'gif' => 'image/gif',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'txt' => 'text/plain'
        ];
        
        if (isset($allowedMimeTypes[$fileExtension]) && $mimeType !== $allowedMimeTypes[$fileExtension]) {
            $errors[] = 'File type does not match file extension';
        }
        
        return $errors;
    }
    
    // Generate safe filename
    public static function generateSafeFilename($originalName, $prefix = '') {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $filename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // Sanitize filename
        $filename = preg_replace('/[^A-Za-z0-9._-]/', '_', $filename);
        $filename = preg_replace('/_+/', '_', $filename);
        $filename = trim($filename, '_');
        
        // Add timestamp and random string to prevent conflicts
        $timestamp = date('Y-m-d_H-i-s');
        $random = substr(bin2hex(random_bytes(4)), 0, 8);
        
        return ($prefix ? $prefix . '_' : '') . $timestamp . '_' . $random . '_' . $filename . '.' . $extension;
    }
    
    // Format bytes for display
    public static function formatBytes($size, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, $precision) . ' ' . $units[$i];
    }
    
    // Log security events
    public static function logSecurityEvent($event, $details = []) {
        $logData = [
            'timestamp' => date('Y-m-d H:i:s'),
            'event' => $event,
            'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
            'user_id' => $_SESSION['user_id'] ?? null,
            'details' => $details
        ];
        
        $logFile = LOG_PATH . 'security_' . date('Y-m-d') . '.log';
        file_put_contents($logFile, json_encode($logData) . PHP_EOL, FILE_APPEND | LOCK_EX);
    }
    
    // Rate limiting
    public static function checkRateLimit($action, $limit = 10, $timeWindow = 3600) {
        $identifier = md5(($_SERVER['REMOTE_ADDR'] ?? '') . $action);
        $rateLimitFile = LOG_PATH . 'rate_limit_' . $identifier . '.json';
        
        $data = ['count' => 0, 'reset_time' => time() + $timeWindow];
        if (file_exists($rateLimitFile)) {
            $data = json_decode(file_get_contents($rateLimitFile), true);
        }
        
        // Reset if time window has passed
        if (time() > $data['reset_time']) {
            $data = ['count' => 0, 'reset_time' => time() + $timeWindow];
        }
        
        $data['count']++;
        file_put_contents($rateLimitFile, json_encode($data));
        
        return $data['count'] <= $limit;
    }
}

// Helper function to get POST data with sanitization
function getPost($key, $default = '', $type = 'string') {
    return Security::sanitizeInput($_POST[$key] ?? $default, $type);
}

// Helper function to get GET data with sanitization
function getGet($key, $default = '', $type = 'string') {
    return Security::sanitizeInput($_GET[$key] ?? $default, $type);
}

// Helper function to display CSRF token input
function csrfTokenInput() {
    return '<input type="hidden" name="csrf_token" value="' . Security::generateCSRFToken() . '">';
}
?>