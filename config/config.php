<?php
/**
 * Main Configuration File - Complete Updated Version
 * فایل پیکربندی اصلی سیستم - نسخه کامل به‌روزرسانی شده
 */

// تنظیمات کلی سایت
define('SITE_TITLE', 'سیستم اتوماسیون اداری دهیاری');
define('SITE_URL', 'http://localhost/rural_automation');
define('SITE_VERSION', '1.0.0');

// تنظیمات فایل‌ها
define('UPLOAD_PATH', __DIR__ . '/../uploads/');
define('UPLOAD_URL', SITE_URL . '/uploads/');
define('MAX_FILE_SIZE', 10 * 1024 * 1024); // 10MB
define('ALLOWED_EXTENSIONS', ['pdf', 'jpg', 'jpeg', 'png', 'mp4', 'avi', 'doc', 'docx']);

// تنظیمات امنیتی
define('SESSION_TIMEOUT', 3600); // 1 hour
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOGIN_BLOCK_TIME', 900); // 15 minutes

// تنظیمات پایگاه داده
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATION', 'utf8mb4_unicode_ci');

// تنظیمات لاگ
define('LOG_PATH', __DIR__ . '/../logs/');
define('LOG_LEVEL', 'ERROR'); // DEBUG, INFO, WARNING, ERROR

// تنظیمات ایمیل (برای آینده)
define('MAIL_HOST', 'localhost');
define('MAIL_PORT', 587);
define('MAIL_USERNAME', '');
define('MAIL_PASSWORD', '');
define('MAIL_FROM_EMAIL', 'admin@rural-automation.ir');
define('MAIL_FROM_NAME', 'سیستم اتوماسیون دهیاری');

// تنظیمات تاریخ و زمان
define('DEFAULT_TIMEZONE', 'Asia/Tehran');
date_default_timezone_set(DEFAULT_TIMEZONE);

// تنظیمات نقش‌ها
define('USER_ROLES', [
    'admin' => 'مدیر کل',
    'manager' => 'مدیر',
    'supervisor' => 'سرپرست',
    'user' => 'کاربر عادی'
]);

// تنظیمات وضعیت نامه‌ها
define('MESSAGE_STATUSES', [
    'unread' => 'خوانده نشده',
    'read' => 'خوانده شده',
    'replied' => 'پاسخ داده شده',
    'archived' => 'آرشیو شده',
    'deleted' => 'حذف شده'
]);

// ✅ تنظیمات جدید پیام‌رسانی
define('MESSAGING_RULES', [
    'admin_can_message_all' => true,           // مدیران می‌توانند به همه پیام بدهند
    'users_only_to_admin' => true,            // کاربران فقط به مدیر پیام می‌دهند
    'allow_user_to_user' => false,            // ارسال بین کاربران عادی غیرفعال
    'admin_auto_recipient' => true,           // مدیر به طور خودکار گیرنده نامه‌های کاربران عادی
    'enforce_daily_limits' => true,           // اعمال محدودیت روزانه
    'allow_urgent_override' => false,         // آیا کاربران عادی می‌توانند اولویت فوری تنظیم کنند
]);

// ✅ تنظیمات محدودیت‌های ارسال
define('DAILY_MESSAGE_LIMITS', [
    'admin' => 100,
    'manager' => 50,
    'supervisor' => 30,
    'user' => 20
]);

// ✅ تنظیمات اولویت نامه
define('MESSAGE_PRIORITIES', [
    'low' => [
        'label' => 'کم',
        'color' => 'secondary',
        'icon' => 'arrow-down',
        'allowed_roles' => ['admin', 'manager', 'supervisor', 'user']
    ],
    'normal' => [
        'label' => 'عادی',
        'color' => 'primary',
        'icon' => 'minus',
        'allowed_roles' => ['admin', 'manager', 'supervisor', 'user']
    ],
    'high' => [
        'label' => 'زیاد',
        'color' => 'warning',
        'icon' => 'exclamation-circle',
        'allowed_roles' => ['admin', 'manager', 'supervisor', 'user']
    ],
    'urgent' => [
        'label' => 'فوری',
        'color' => 'danger',
        'icon' => 'exclamation-triangle',
        'allowed_roles' => ['admin'] // فقط مدیران می‌توانند اولویت فوری تنظیم کنند
    ]
]);

// تنظیمات صفحه‌بندی
define('ITEMS_PER_PAGE', 20);

// تنظیمات cache
define('CACHE_ENABLED', false);
define('CACHE_TIME', 3600);

// حالت debug
define('DEBUG_MODE', true);

// تنظیمات خطا
if (DEBUG_MODE) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
} else {
    error_reporting(0);
    ini_set('display_errors', 0);
}

// ایجاد پوشه‌های مورد نیاز
$required_dirs = [
    UPLOAD_PATH,
    LOG_PATH,
    __DIR__ . '/../cache/',
    __DIR__ . '/../backup/'
];

foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
}

// ✅ بارگذاری helper های جدید
if (file_exists(__DIR__ . '/../includes/message_permissions.php')) {
    require_once __DIR__ . '/../includes/message_permissions.php';
}

// تابع helper برای دریافت URL کامل
function getSiteUrl($path = '') {
    return SITE_URL . '/' . ltrim($path, '/');
}

// تابع helper برای دریافت path کامل
function getPath($path = '') {
    return __DIR__ . '/../' . ltrim($path, '/');
}

// تابع helper برای لاگ
function writeLog($message, $level = 'INFO') {
    if (!is_dir(LOG_PATH)) {
        mkdir(LOG_PATH, 0755, true);
    }
    
    $logFile = LOG_PATH . date('Y-m-d') . '.log';
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] [$level] $message" . PHP_EOL;
    
    file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX);
}

// تابع helper برای فرمت کردن حجم فایل
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB'];
    $bytes = max($bytes, 0);
    $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
    $pow = min($pow, count($units) - 1);
    
    $bytes /= pow(1024, $pow);
    
    return round($bytes, 2) . ' ' . $units[$pow];
}

// تابع helper برای تولید CSRF token
function generateCSRFToken() {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

// تابع helper برای بررسی CSRF token
function verifyCSRFToken($token) {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// ✅ تابع helper برای دریافت نقش کاربر در فرمت نمایشی
function getUserRoleDisplay($role) {
    return USER_ROLES[$role] ?? 'نامشخص';
}

// ✅ تابع helper برای بررسی مجوز پیام‌رسانی
function canUserSendMessage($senderRole, $receiverRole) {
    if (class_exists('MessagePermissions')) {
        return MessagePermissions::canSendMessage($senderRole, $receiverRole);
    }
    
    // fallback logic
    if ($senderRole === 'admin') {
        return true;
    }
    return $receiverRole === 'admin';
}

// ✅ تابع helper برای دریافت لیست مدیران
function getSystemAdmins($db) {
    return $db->fetchAll(
        "SELECT id, name, username FROM users WHERE role = 'admin' AND status = 'active' ORDER BY name"
    );
}

// ✅ تابع helper برای تعیین CSS class بر اساس اولویت نامه
function getPriorityClass($priority) {
    $priorities = MESSAGE_PRIORITIES;
    return isset($priorities[$priority]) ? 'text-' . $priorities[$priority]['color'] : 'text-muted';
}

// ✅ تابع helper برای تعیین آیکون بر اساس اولویت نامه
function getPriorityIcon($priority) {
    $priorities = MESSAGE_PRIORITIES;
    return isset($priorities[$priority]) ? 'fas fa-' . $priorities[$priority]['icon'] : 'fas fa-minus';
}

// ✅ تابع helper برای تعیین رنگ badge وضعیت نامه
function getStatusBadgeClass($status) {
    $classes = [
        'unread' => 'bg-warning',
        'read' => 'bg-success',
        'replied' => 'bg-info',
        'archived' => 'bg-secondary',
        'deleted' => 'bg-dark'
    ];
    return $classes[$status] ?? 'bg-secondary';
}

// ✅ تابع helper برای بررسی مجوز استفاده از اولویت خاص
function canUserSetPriority($userRole, $priority) {
    $priorities = MESSAGE_PRIORITIES;
    if (!isset($priorities[$priority])) {
        return false;
    }
    
    return in_array($userRole, $priorities[$priority]['allowed_roles']);
}

// ✅ تابع helper برای دریافت اولویت‌های مجاز برای کاربر
function getAllowedPriorities($userRole) {
    $allowed = [];
    foreach (MESSAGE_PRIORITIES as $key => $priority) {
        if (in_array($userRole, $priority['allowed_roles'])) {
            $allowed[$key] = $priority['label'];
        }
    }
    return $allowed;
}

// ✅ تابع helper برای بررسی محدودیت روزانه
function checkDailyMessageLimit($userId, $userRole, $db) {
    if (!MESSAGING_RULES['enforce_daily_limits']) {
        return ['allowed' => true, 'remaining' => 999];
    }
    
    $limit = DAILY_MESSAGE_LIMITS[$userRole] ?? 10;
    
    $todayCount = $db->fetchRow(
        "SELECT COUNT(*) as count FROM messages 
         WHERE sender_id = ? AND DATE(created_at) = CURDATE()",
        [$userId]
    )['count'];
    
    return [
        'allowed' => $todayCount < $limit,
        'used' => $todayCount,
        'limit' => $limit,
        'remaining' => max(0, $limit - $todayCount)
    ];
}

// ✅ تابع helper برای فرمت تاریخ شمسی
function formatPersianDate($timestamp, $format = 'Y/m/d') {
    if (class_exists('JalaliDate')) {
        return JalaliDate::toJalali($timestamp, $format);
    }
    
    // fallback to regular date
    return date($format, $timestamp);
}

// ✅ تابع helper برای تبدیل اعداد انگلیسی به فارسی
function toPersianNumber($number) {
    $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
    return str_replace(range(0, 9), $persian, $number);
}

// ✅ تابع helper برای truncate کردن متن
function truncateText($text, $length = 100, $suffix = '...') {
    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }
    
    return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
}

// ✅ تابع helper برای escape کردن output
function escape($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

// ✅ تابع helper برای بررسی اینکه آیا کاربر آنلاین است
function isUserOnline($lastActivity, $threshold = 300) {
    if (!$lastActivity) return false;
    
    $lastTime = is_string($lastActivity) ? strtotime($lastActivity) : $lastActivity;
    return (time() - $lastTime) <= $threshold;
}

// ✅ تابع helper برای تولید رنگ تصادفی برای آواتار
function generateAvatarColor($text) {
    $colors = [
        '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
        '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'
    ];
    
    $hash = crc32($text);
    $index = abs($hash) % count($colors);
    return $colors[$index];
}

// ✅ تابع helper برای validation شماره تلفن ایرانی
function isValidIranianPhone($phone) {
    $phone = preg_replace('/[^0-9]/', '', $phone);
    return preg_match('/^09\d{9}$/', $phone) || preg_match('/^0\d{10}$/', $phone);
}

// ✅ تابع helper برای validation ایمیل
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// ✅ تابع helper برای تولید slug از متن فارسی
function generateSlug($text) {
    // حذف کاراکترهای غیرضروری
    $text = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $text);
    // تبدیل فاصله‌ها به خط تیره
    $text = preg_replace('/[\s\-_]+/', '-', $text);
    // حذف خط تیره از ابتدا و انتها
    return trim($text, '-');
}

// ✅ initialization - بررسی وضعیت سیستم
function checkSystemStatus() {
    $status = [
        'database' => false,
        'uploads' => false,
        'logs' => false,
        'cache' => false
    ];
    
    // بررسی پایگاه داده
    try {
        $db = Database::getInstance();
        $db->query("SELECT 1");
        $status['database'] = true;
    } catch (Exception $e) {
        writeLog("Database connection failed: " . $e->getMessage(), 'ERROR');
    }
    
    // بررسی پوشه‌ها
    $status['uploads'] = is_dir(UPLOAD_PATH) && is_writable(UPLOAD_PATH);
    $status['logs'] = is_dir(LOG_PATH) && is_writable(LOG_PATH);
    $status['cache'] = is_dir(__DIR__ . '/../cache/') && is_writable(__DIR__ . '/../cache/');
    
    return $status;
}

// ✅ اجرای بررسی اولیه سیستم (فقط اگر کلاس Database در دسترس باشد)
if (DEBUG_MODE && class_exists('Database')) {
    $systemStatus = checkSystemStatus();
    if (!$systemStatus['database']) {
        writeLog("System initialization: Database connection failed", 'ERROR');
    }
}