<?php
/**
 * Security and Validation Class
 * کلاس امنیت و اعتبارسنجی
 */

class Security {
    
    /**
     * Sanitize input data
     * پاکسازی داده‌های ورودی
     */
    public static function sanitize($data) {
        if (is_array($data)) {
            return array_map([self::class, 'sanitize'], $data);
        }
        
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Clean string for database
     * پاکسازی رشته برای پایگاه داده
     */
    public static function cleanString($string) {
        return trim(preg_replace('/\s+/', ' ', $string));
    }

    /**
     * Validate email
     * اعتبارسنجی ایمیل
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }

    /**
     * Validate phone number (Iranian format)
     * اعتبارسنجی شماره تلفن (فرمت ایرانی)
     */
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return preg_match('/^09\d{9}$/', $phone) || preg_match('/^0\d{10}$/', $phone);
    }

    /**
     * Validate password strength
     * اعتبارسنجی قدرت رمز عبور
     */
    public static function validatePassword($password) {
        $errors = [];
        
        if (strlen($password) < 8) {
            $errors[] = "رمز عبور باید حداقل 8 کاراکتر باشد";
        }
        
        if (!preg_match('/[A-Za-z]/', $password)) {
            $errors[] = "رمز عبور باید شامل حداقل یک حرف باشد";
        }
        
        if (!preg_match('/[0-9]/', $password)) {
            $errors[] = "رمز عبور باید شامل حداقل یک عدد باشد";
        }
        
        return empty($errors) ? true : $errors;
    }

    /**
     * Validate username
     * اعتبارسنجی نام کاربری
     */
    public static function validateUsername($username) {
        if (strlen($username) < 3 || strlen($username) > 20) {
            return "نام کاربری باید بین 3 تا 20 کاراکتر باشد";
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            return "نام کاربری فقط می‌تواند شامل حروف، اعداد و خط زیر باشد";
        }
        
        return true;
    }

    /**
     * Validate uploaded file
     * اعتبارسنجی فایل آپلود شده
     */
    public static function validateFile($file) {
        $errors = [];
        
        // بررسی خطای آپلود
        if ($file['error'] !== UPLOAD_ERR_OK) {
            switch ($file['error']) {
                case UPLOAD_ERR_INI_SIZE:
                case UPLOAD_ERR_FORM_SIZE:
                    $errors[] = "حجم فایل بیش از حد مجاز است";
                    break;
                case UPLOAD_ERR_PARTIAL:
                    $errors[] = "فایل به طور کامل آپلود نشده است";
                    break;
                case UPLOAD_ERR_NO_FILE:
                    $errors[] = "هیچ فایلی انتخاب نشده است";
                    break;
                default:
                    $errors[] = "خطا در آپلود فایل";
            }
            return $errors;
        }
        
        // بررسی حجم فایل
        if ($file['size'] > MAX_FILE_SIZE) {
            $errors[] = "حجم فایل نباید بیشتر از " . formatFileSize(MAX_FILE_SIZE) . " باشد";
        }
        
        // بررسی پسوند فایل
        $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ALLOWED_EXTENSIONS)) {
            $errors[] = "فرمت فایل مجاز نیست. فرمت‌های مجاز: " . implode(', ', ALLOWED_EXTENSIONS);
        }
        
        // بررسی نوع MIME
        $allowedMimes = [
            'pdf' => 'application/pdf',
            'jpg' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'mp4' => 'video/mp4',
            'avi' => 'video/x-msvideo',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        
        if (isset($allowedMimes[$extension])) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            $mimeType = finfo_file($finfo, $file['tmp_name']);
            finfo_close($finfo);
            
            if ($mimeType !== $allowedMimes[$extension]) {
                $errors[] = "نوع فایل با پسوند آن مطابقت ندارد";
            }
        }
        
        // بررسی امنیتی اضافی برای تصاویر
        if (in_array($extension, ['jpg', 'jpeg', 'png'])) {
            $imageInfo = getimagesize($file['tmp_name']);
            if ($imageInfo === false) {
                $errors[] = "فایل انتخابی تصویر معتبری نیست";
            }
        }
        
        return empty($errors) ? true : $errors;
    }

    /**
     * Generate secure filename
     * تولید نام فایل امن
     */
    public static function generateSecureFilename($originalName) {
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        $basename = pathinfo($originalName, PATHINFO_FILENAME);
        
        // حذف کاراکترهای غیرمجاز
        $basename = preg_replace('/[^a-zA-Z0-9_\-\s]/', '', $basename);
        $basename = trim($basename);
        
        if (empty($basename)) {
            $basename = 'file';
        }
        
        // محدود کردن طول نام
        $basename = substr($basename, 0, 50);
        
        // اضافه کردن timestamp برای یکتا بودن
        return time() . '_' . uniqid() . '_' . $basename . '.' . $extension;
    }

    /**
     * Upload file securely
     * آپلود امن فایل
     */
    public static function uploadFile($file, $uploadPath = null) {
        if ($uploadPath === null) {
            $uploadPath = UPLOAD_PATH;
        }
        
        // اعتبارسنجی فایل
        $validation = self::validateFile($file);
        if ($validation !== true) {
            throw new Exception(implode(', ', $validation));
        }
        
        // تولید نام فایل امن
        $filename = self::generateSecureFilename($file['name']);
        $filepath = $uploadPath . $filename;
        
        // ایجاد پوشه در صورت عدم وجود
        if (!is_dir($uploadPath)) {
            mkdir($uploadPath, 0755, true);
        }
        
        // انتقال فایل
        if (!move_uploaded_file($file['tmp_name'], $filepath)) {
            throw new Exception("خطا در آپلود فایل");
        }
        
        // تنظیم مجوزهای فایل
        chmod($filepath, 0644);
        
        writeLog("File uploaded: {$filename}", 'INFO');
        return $filename;
    }

    /**
     * Delete file securely
     * حذف امن فایل
     */
    public static function deleteFile($filename, $uploadPath = null) {
        if ($uploadPath === null) {
            $uploadPath = UPLOAD_PATH;
        }
        
        $filepath = $uploadPath . $filename;
        
        if (file_exists($filepath) && is_file($filepath)) {
            if (unlink($filepath)) {
                writeLog("File deleted: {$filename}", 'INFO');
                return true;
            }
        }
        
        return false;
    }

    /**
     * Generate CSRF token
     * تولید CSRF token
     */
    public static function generateCSRFToken() {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        
        return $_SESSION['csrf_token'];
    }

    /**
     * Verify CSRF token
     * بررسی CSRF token
     */
    public static function verifyCSRFToken($token) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    /**
     * Generate CSRF token field
     * تولید فیلد CSRF token برای فرم
     */
    public static function csrfField() {
        $token = self::generateCSRFToken();
        return '<input type="hidden" name="csrf_token" value="' . $token . '">';
    }

    /**
     * Rate limiting
     * محدودیت نرخ درخواست
     */
    public static function rateLimit($action, $limit = 10, $window = 3600) {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        
        $key = 'rate_limit_' . $action;
        $now = time();
        
        if (!isset($_SESSION[$key])) {
            $_SESSION[$key] = [];
        }
        
        // پاک کردن درخواست‌های قدیمی
        $_SESSION[$key] = array_filter($_SESSION[$key], function($timestamp) use ($now, $window) {
            return ($now - $timestamp) < $window;
        });
        
        // بررسی محدودیت
        if (count($_SESSION[$key]) >= $limit) {
            return false;
        }
        
        // اضافه کردن درخواست جدید
        $_SESSION[$key][] = $now;
        return true;
    }

    /**
     * Validate Iranian national ID
     * اعتبارسنجی کد ملی ایرانی
     */
    public static function validateNationalId($id) {
        $id = preg_replace('/[^0-9]/', '', $id);
        
        if (strlen($id) !== 10) {
            return false;
        }
        
        // بررسی کدهای غیرمعتبر
        $invalidIds = ['0000000000', '1111111111', '2222222222', '3333333333', 
                      '4444444444', '5555555555', '6666666666', '7777777777', 
                      '8888888888', '9999999999'];
        
        if (in_array($id, $invalidIds)) {
            return false;
        }
        
        // محاسبه رقم کنترل
        $sum = 0;
        for ($i = 0; $i < 9; $i++) {
            $sum += $id[$i] * (10 - $i);
        }
        
        $remainder = $sum % 11;
        $checkDigit = $remainder < 2 ? $remainder : 11 - $remainder;
        
        return $checkDigit == $id[9];
    }

    /**
     * XSS protection
     * محافظت از XSS
     */
    public static function preventXSS($input) {
        return htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * SQL injection protection (additional layer)
     * محافظت از SQL injection (لایه اضافی)
     */
    public static function escapeSql($input) {
        return addslashes($input);
    }

    /**
     * Generate random string
     * تولید رشته تصادفی
     */
    public static function generateRandomString($length = 10) {
        return bin2hex(random_bytes($length / 2));
    }

    /**
     * Hash sensitive data
     * هش کردن داده‌های حساس
     */
    public static function hashData($data, $salt = '') {
        return hash('sha256', $data . $salt);
    }

    /**
     * Verify hash
     * بررسی هش
     */
    public static function verifyHash($data, $hash, $salt = '') {
        return hash_equals($hash, self::hashData($data, $salt));
    }
}