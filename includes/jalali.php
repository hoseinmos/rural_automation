<?php
/**
 * Jalali (Persian) Date Helper Class
 * کلاس کمکی تاریخ شمسی (جلالی)
 */

class JalaliDate {
    
    private static $persianMonths = [
        1 => 'فروردین', 2 => 'اردیبهشت', 3 => 'خرداد', 4 => 'تیر',
        5 => 'مرداد', 6 => 'شهریور', 7 => 'مهر', 8 => 'آبان',
        9 => 'آذر', 10 => 'دی', 11 => 'بهمن', 12 => 'اسفند'
    ];
    
    private static $persianWeekDays = [
        'شنبه', 'یکشنبه', 'دوشنبه', 'سه‌شنبه', 'چهارشنبه', 'پنج‌شنبه', 'جمعه'
    ];

    /**
     * Convert Gregorian to Jalali
     * تبدیل میلادی به شمسی
     */
    public static function gregorianToJalali($gy, $gm, $gd) {
        $g_d_m = [0, 31, 59, 90, 120, 151, 181, 212, 243, 273, 304, 334];
        
        if ($gy <= 1600) {
            $jy = 0;
            $gy -= 621;
        } else {
            $jy = 979;
            $gy -= 1600;
        }
        
        if ($gm > 2) {
            $gy2 = $gy + 1;
        } else {
            $gy2 = $gy;
        }
        
        $days = (365 * $gy) + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + 
                ((int)(($gy2 + 399) / 400)) - 80 + $gd + $g_d_m[$gm - 1];
        
        $jy += 33 * ((int)($days / 12053));
        $days %= 12053;
        
        $jy += 4 * ((int)($days / 1461));
        $days %= 1461;
        
        if ($days > 365) {
            $jy += (int)(($days - 1) / 365);
            $days = ($days - 1) % 365;
        }
        
        if ($days < 186) {
            $jm = 1 + (int)($days / 31);
            $jd = 1 + ($days % 31);
        } else {
            $jm = 7 + (int)(($days - 186) / 30);
            $jd = 1 + (($days - 186) % 30);
        }
        
        return [$jy, $jm, $jd];
    }

    /**
     * Convert Jalali to Gregorian
     * تبدیل شمسی به میلادی
     */
    public static function jalaliToGregorian($jy, $jm, $jd) {
        if ($jy <= 979) {
            $gy = 1600;
            $jy += 621;
        } else {
            $gy = 1979;
            $jy += 1600;
        }
        
        if ($jm < 7) {
            $days = ($jm - 1) * 31;
        } else {
            $days = ($jm - 7) * 30 + 186;
        }
        
        $days += ($jd - 1);
        
        $gy += 400 * ((int)($jy / 1029983));
        $jy %= 1029983;
        
        if ($jy >= 1029983) {
            $leap = true;
        } else {
            $leap = false;
        }
        
        $jp = 0;
        for ($i = 0; $i < count([33, 33, 33, 33, 33, 33, 33, 33, 33, 33, 33, 33, 29]); $i++) {
            $j = $i == 12 ? 29 : 33;
            if ($jy >= $j) {
                $jy -= $j;
                $jp += $j;
            } else {
                break;
            }
        }
        
        if ($jy >= 29) {
            $leap = true;
            $jy -= 29;
            $jp += 29;
        }
        
        if ($leap) {
            $gy += (int)($jp / 33) * 33;
            $jp %= 33;
            $gy += ((int)($jy / 4)) * 4;
            $jy %= 4;
            $gy += $jy;
        }
        
        if ($jm < 7) {
            $leap = (($gy % 4 == 0) && ($gy % 100 != 0)) || ($gy % 400 == 0);
        } else {
            $leap = (((($gy + 1) % 4) == 0) && ((($gy + 1) % 100) != 0)) || ((($gy + 1) % 400) == 0);
        }
        
        $sal_a = [0, 31, ($leap ? 29 : 28), 31, 30, 31, 30, 31, 31, 30, 31, 30, 31];
        
        $gy2 = $gy + 1;
        $days += 365 * $gy + ((int)(($gy2 + 3) / 4)) - ((int)(($gy2 + 99) / 100)) + 
                ((int)(($gy2 + 399) / 400)) + 80;
        
        $gm = 0;
        while ($gm < 13 && $days >= $sal_a[$gm]) {
            $days -= $sal_a[$gm];
            $gm++;
        }
        
        if ($days < $sal_a[$gm]) {
            $gd = $days + 1;
        }
        
        return [$gy, $gm, $gd];
    }

    /**
     * Convert timestamp to Jalali date
     * تبدیل timestamp به تاریخ شمسی
     */
    public static function toJalali($timestamp = null, $format = 'Y/m/d') {
        if ($timestamp === null) {
            $timestamp = time();
        }
        
        if (is_string($timestamp)) {
            $timestamp = strtotime($timestamp);
        }
        
        $date = getdate($timestamp);
        list($jy, $jm, $jd) = self::gregorianToJalali($date['year'], $date['mon'], $date['mday']);
        
        return self::formatJalaliDate($jy, $jm, $jd, $format, $timestamp);
    }

    /**
     * Format Jalali date
     * فرمت کردن تاریخ شمسی
     */
    private static function formatJalaliDate($jy, $jm, $jd, $format, $timestamp) {
        $search = ['Y', 'y', 'm', 'n', 'd', 'j', 'F', 'M', 'l', 'D', 'H', 'i', 's'];
        $replace = [];
        
        $date = getdate($timestamp);
        
        $replace[] = $jy; // Y - سال چهار رقمی
        $replace[] = substr($jy, -2); // y - سال دو رقمی
        $replace[] = sprintf('%02d', $jm); // m - ماه با صفر
        $replace[] = $jm; // n - ماه بدون صفر
        $replace[] = sprintf('%02d', $jd); // d - روز با صفر
        $replace[] = $jd; // j - روز بدون صفر
        $replace[] = self::$persianMonths[$jm]; // F - نام کامل ماه
        $replace[] = self::$persianMonths[$jm]; // M - نام مختصر ماه
        $replace[] = self::$persianWeekDays[$date['wday']]; // l - نام کامل روز هفته
        $replace[] = self::$persianWeekDays[$date['wday']]; // D - نام مختصر روز هفته
        $replace[] = sprintf('%02d', $date['hours']); // H - ساعت
        $replace[] = sprintf('%02d', $date['minutes']); // i - دقیقه
        $replace[] = sprintf('%02d', $date['seconds']); // s - ثانیه
        
        return str_replace($search, $replace, $format);
    }

    /**
     * Get current Jalali date
     * دریافت تاریخ شمسی جاری
     */
    public static function now($format = 'Y/m/d') {
        return self::toJalali(time(), $format);
    }

    /**
     * Get current Jalali date and time
     * دریافت تاریخ و زمان شمسی جاری
     */
    public static function nowWithTime($format = 'Y/m/d H:i:s') {
        return self::toJalali(time(), $format);
    }

    /**
     * Convert Jalali date string to timestamp
     * تبدیل رشته تاریخ شمسی به timestamp
     */
    public static function jalaliToTimestamp($jalaliDate) {
        // فرض: فرمت ورودی Y/m/d یا Y-m-d
        $parts = preg_split('/[\/\-]/', $jalaliDate);
        
        if (count($parts) !== 3) {
            return false;
        }
        
        list($jy, $jm, $jd) = $parts;
        list($gy, $gm, $gd) = self::jalaliToGregorian($jy, $jm, $jd);
        
        return mktime(0, 0, 0, $gm, $gd, $gy);
    }

    /**
     * Get Jalali month names
     * دریافت نام ماه‌های شمسی
     */
    public static function getMonthNames() {
        return self::$persianMonths;
    }

    /**
     * Get Jalali weekday names
     * دریافت نام روزهای هفته شمسی
     */
    public static function getWeekDayNames() {
        return self::$persianWeekDays;
    }

    /**
     * Calculate age in Jalali
     * محاسبه سن بر اساس تاریخ شمسی
     */
    public static function calculateAge($birthDate, $currentDate = null) {
        if ($currentDate === null) {
            $currentDate = time();
        }
        
        if (is_string($birthDate)) {
            $birthDate = self::jalaliToTimestamp($birthDate);
        }
        
        if (is_string($currentDate)) {
            $currentDate = strtotime($currentDate);
        }
        
        $birthJalali = self::toJalali($birthDate);
        $currentJalali = self::toJalali($currentDate);
        
        list($birthYear, $birthMonth, $birthDay) = explode('/', $birthJalali);
        list($currentYear, $currentMonth, $currentDay) = explode('/', $currentJalali);
        
        $age = $currentYear - $birthYear;
        
        if ($currentMonth < $birthMonth || 
            ($currentMonth == $birthMonth && $currentDay < $birthDay)) {
            $age--;
        }
        
        return $age;
    }

    /**
     * Get days in Jalali month
     * دریافت تعداد روزهای ماه شمسی
     */
    public static function getDaysInMonth($jy, $jm) {
        if ($jm <= 6) {
            return 31;
        } elseif ($jm <= 11) {
            return 30;
        } else {
            return self::isLeapYear($jy) ? 30 : 29;
        }
    }

    /**
     * Check if Jalali year is leap
     * بررسی کبیسه بودن سال شمسی
     */
    public static function isLeapYear($jy) {
        $cycle = 2820;
        $year = $jy % $cycle;
        
        if ($year < 0) {
            $year += $cycle;
        }
        
        $aux = 0;
        if ($year >= 29) {
            $aux = (int)(($year - 29) / 33);
            $year = ($year - 29) % 33;
        }
        
        if ($year < 29) {
            if ($year % 4 == 1) {
                return true;
            }
        }
        
        return false;
    }

    /**
     * Get time ago in Persian
     * دریافت زمان گذشته به فارسی
     */
    public static function timeAgo($timestamp) {
        $diff = time() - $timestamp;
        
        if ($diff < 60) {
            return 'همین الان';
        } elseif ($diff < 3600) {
            $minutes = floor($diff / 60);
            return $minutes . ' دقیقه پیش';
        } elseif ($diff < 86400) {
            $hours = floor($diff / 3600);
            return $hours . ' ساعت پیش';
        } elseif ($diff < 2592000) {
            $days = floor($diff / 86400);
            return $days . ' روز پیش';
        } else {
            return self::toJalali($timestamp, 'Y/m/d');
        }
    }

    /**
     * Convert Persian numbers to English
     * تبدیل اعداد فارسی به انگلیسی
     */
    public static function persianToEnglish($string) {
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        $arabic = ['٠', '١', '٢', '٣', '٤', '٥', '٦', '٧', '٨', '٩'];
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        
        $string = str_replace($persian, $english, $string);
        $string = str_replace($arabic, $english, $string);
        
        return $string;
    }

    /**
     * Convert English numbers to Persian
     * تبدیل اعداد انگلیسی به فارسی
     */
    public static function englishToPersian($string) {
        $english = ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9'];
        $persian = ['۰', '۱', '۲', '۳', '۴', '۵', '۶', '۷', '۸', '۹'];
        
        return str_replace($english, $persian, $string);
    }
}