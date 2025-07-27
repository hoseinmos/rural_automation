<?php
/**
 * Utils Class - Helper Functions
 * کلاس ابزارهای کمکی
 */

class Utils {
    
    /**
     * تبدیل اعداد انگلیسی به فارسی
     */
    public static function toPersianNumber($number) {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        return str_replace(range(0, 9), $persian, $number);
    }
    
    /**
     * تبدیل اعداد فارسی به انگلیسی
     */
    public static function toEnglishNumber($number) {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        
        $number = str_replace($persian, range(0, 9), $number);
        $number = str_replace($arabic, range(0, 9), $number);
        
        return $number;
    }
    
    /**
     * فرمت کردن حجم فایل
     */
    public static function formatFileSize($bytes) {
        if ($bytes === 0) return '0 بایت';
        
        $k = 1024;
        $sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
        $i = floor(log($bytes) / log($k));
        
        return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
    }
    
    /**
     * کوتاه کردن متن
     */
    public static function truncateText($text, $length = 100, $suffix = '...') {
        if (mb_strlen($text, 'UTF-8') <= $length) {
            return $text;
        }
        
        return mb_substr($text, 0, $length, 'UTF-8') . $suffix;
    }
    
    /**
     * escape کردن HTML
     */
    public static function escape($text) {
        return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * تولید رنگ آواتار بر اساس متن
     */
    public static function generateAvatarColor($text) {
        $colors = [
            '#FF6B6B', '#4ECDC4', '#45B7D1', '#96CEB4', '#FFEAA7',
            '#DDA0DD', '#98D8C8', '#F7DC6F', '#BB8FCE', '#85C1E9'
        ];
        
        $hash = crc32($text);
        $index = abs($hash) % count($colors);
        return $colors[$index];
    }
    
    /**
     * اعتبارسنجی ایمیل
     */
    public static function validateEmail($email) {
        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
    }
    
    /**
     * اعتبارسنجی شماره تلفن ایرانی
     */
    public static function validatePhone($phone) {
        $phone = preg_replace('/[^0-9]/', '', $phone);
        return preg_match('/^09\d{9}$/', $phone) || preg_match('/^0\d{10}$/', $phone);
    }
    
    /**
     * تولید slug از متن فارسی
     */
    public static function generateSlug($text) {
        // حذف کاراکترهای غیرضروری
        $text = preg_replace('/[^\p{L}\p{N}\s\-_]/u', '', $text);
        // تبدیل فاصله‌ها به خط تیره
        $text = preg_replace('/[\s\-_]+/', '-', $text);
        // حذف خط تیره از ابتدا و انتها
        return trim($text, '-');
    }
    
    /**
     * فرمت کردن تاریخ نسبی
     */
    public static function timeAgo($timestamp) {
        $time = time() - $timestamp;
        
        if ($time < 60) {
            return 'همین الان';
        } elseif ($time < 3600) {
            $minutes = floor($time / 60);
            return $minutes . ' دقیقه پیش';
        } elseif ($time < 86400) {
            $hours = floor($time / 3600);
            return $hours . ' ساعت پیش';
        } elseif ($time < 2592000) {
            $days = floor($time / 86400);
            return $days . ' روز پیش';
        } else {
            return date('Y/m/d', $timestamp);
        }
    }
}
?>