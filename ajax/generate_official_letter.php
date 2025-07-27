<?php
/**
 * Generate Official Letter with Custom Template
 * تولید نامه اداری با قالب سفارشی - نسخه اصلاح شده با فونت بزرگتر
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/jalali.php';
require_once '../includes/utils.php';

// اطمینان از UTF-8
header('Content-Type: application/json; charset=utf-8');
mb_internal_encoding('UTF-8');

try {
    Auth::requireLogin();
    
    $messageId = (int)($_GET['id'] ?? 0);
    $currentUser = Auth::getCurrentUser();
    
    if (!$messageId) {
        throw new Exception('شناسه نامه نامعتبر است');
    }
    
    $db = Database::getInstance();
    
    // دریافت اطلاعات نامه
    $message = $db->fetchRow(
        "SELECT m.*, 
                s.name as sender_name, s.username as sender_username,
                r.name as receiver_name, r.username as receiver_username,
                ds.signature_image
         FROM messages m 
         JOIN users s ON m.sender_id = s.id 
         JOIN users r ON m.receiver_id = r.id 
         LEFT JOIN digital_signatures ds ON s.id = ds.user_id AND ds.is_active = 1
         WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)",
        [$messageId, $currentUser['id'], $currentUser['id']]
    );

    if (!$message) {
        throw new Exception('نامه یافت نشد یا شما دسترسی لازم را ندارید');
    }

    // دریافت قالب فعال
    $template = $db->fetchRow("SELECT * FROM letter_templates WHERE is_active = 1 LIMIT 1");
    
    // اگر قالبی وجود ندارد، از قالب پیش‌فرض استفاده کن
    if (!$template || !$template['background_image']) {
        $template = [
            'id' => 0,
            'background_image' => 'default_bg.jpg',
            'page_size' => 'A4'
        ];
    }
    
    // دریافت موقعیت فیلدها
    $fields = $db->fetchAll(
        "SELECT * FROM template_fields WHERE template_id = ?",
        [$template['id']]
    );
    
    // اگر فیلد تعریف نشده، از مقادیر پیش‌فرض استفاده کن
    if (empty($fields)) {
        $fields = getDefaultFields();
    }
    
    // تولید تصویر نامه
    $letterImage = generateLetterImage($message, $template, $fields);
    
    if ($letterImage) {
        echo json_encode([
            'success' => true,
            'letterHtml' => '<img src="' . $letterImage . '" style="width: 100%; max-width: 595px;" alt="نامه اداری">',
            'imageUrl' => $letterImage
        ]);
    } else {
        throw new Exception('خطا در تولید تصویر نامه');
    }

} catch (Exception $e) {
    error_log("Error in generate_official_letter: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

/**
 * دریافت فیلدهای پیش‌فرض
 */
function getDefaultFields() {
    return [
        ['field_name' => 'message_number', 'x_position' => 450, 'y_position' => 120, 'font_size' => 18, 'width' => 150, 'height' => 30],
        ['field_name' => 'date', 'x_position' => 450, 'y_position' => 150, 'font_size' => 18, 'width' => 150, 'height' => 30],
        ['field_name' => 'subject', 'x_position' => 300, 'y_position' => 250, 'font_size' => 20, 'width' => 400, 'height' => 30],
        ['field_name' => 'receiver_name', 'x_position' => 450, 'y_position' => 200, 'font_size' => 18, 'width' => 200, 'height' => 30],
        ['field_name' => 'sender_name', 'x_position' => 150, 'y_position' => 680, 'font_size' => 18, 'width' => 200, 'height' => 30],
        ['field_name' => 'content', 'x_position' => 100, 'y_position' => 300, 'font_size' => 16, 'width' => 400, 'height' => 300],
        ['field_name' => 'signature', 'x_position' => 150, 'y_position' => 620, 'font_size' => 0, 'width' => 100, 'height' => 60]
    ];
}

/**
 * تولید تصویر نامه با GD
 */
function generateLetterImage($message, $template, $fields) {
    // مسیر تصویر پس‌زمینه
    $backgroundPath = UPLOAD_PATH . 'templates/' . $template['background_image'];
    
    // بررسی وجود تصویر
    if (!file_exists($backgroundPath)) {
        // اگر تصویر پیش‌فرض هم وجود ندارد، یک تصویر سفید بساز
        return generateWhiteBackgroundLetter($message, $fields);
    }
    
    // بارگذاری تصویر پس‌زمینه
    $imageInfo = @getimagesize($backgroundPath);
    if (!$imageInfo) {
        return generateWhiteBackgroundLetter($message, $fields);
    }
    
    $mime = $imageInfo['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $bgImage = @imagecreatefromjpeg($backgroundPath);
            break;
        case 'image/png':
            $bgImage = @imagecreatefrompng($backgroundPath);
            break;
        default:
            return generateWhiteBackgroundLetter($message, $fields);
    }
    
    if (!$bgImage) {
        return generateWhiteBackgroundLetter($message, $fields);
    }
    
    // اندازه تصویر
    $width = imagesx($bgImage);
    $height = imagesy($bgImage);
    
    // ایجاد تصویر True Color برای کیفیت بهتر
    $image = imagecreatetruecolor($width, $height);
    
    // رنگ سفید برای پس‌زمینه
    $white = imagecolorallocate($image, 255, 255, 255);
    imagefill($image, 0, 0, $white);
    
    // کپی تصویر پس‌زمینه
    imagecopy($image, $bgImage, 0, 0, 0, 0, $width, $height);
    imagedestroy($bgImage);
    
    // نوشتن متن‌ها
    writeTextsOnImage($image, $message, $fields);
    
    // ذخیره تصویر
    return saveLetterImage($image, $message['id']);
}

/**
 * تولید نامه با پس‌زمینه سفید
 */
function generateWhiteBackgroundLetter($message, $fields) {
    // ابعاد A4 در 72 DPI
    $width = 595;
    $height = 842;
    
    // ایجاد تصویر
    $image = imagecreatetruecolor($width, $height);
    
    // رنگ‌ها
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);
    $gray = imagecolorallocate($image, 200, 200, 200);
    
    // پر کردن با رنگ سفید
    imagefill($image, 0, 0, $white);
    
    // رسم کادر
    imagerectangle($image, 20, 20, $width - 20, $height - 20, $gray);
    
    // نوشتن متن‌ها
    writeTextsOnImage($image, $message, $fields);
    
    // ذخیره تصویر
    return saveLetterImage($image, $message['id']);
}

/**
 * نوشتن متن‌ها روی تصویر
 */
function writeTextsOnImage($image, $message, $fields) {
    // رنگ متن
    $black = imagecolorallocate($image, 0, 0, 0);
    
    // پیدا کردن فونت مناسب
    $fontPath = findSuitableFont();
    
    if (!$fontPath) {
        throw new Exception('فونت مناسب یافت نشد');
    }
    
    // آماده‌سازی داده‌ها
    $currentDate = class_exists('JalaliDate') ? JalaliDate::toJalali(time(), 'Y/m/d') : date('Y/m/d');
    
    $fieldData = [
        'message_number' => $message['message_number'] ?: 'بدون شماره',
        'date' => $currentDate,
        'subject' => $message['subject'],
        'receiver_name' => $message['receiver_name'],
        'sender_name' => $message['sender_name'],
        'content' => strip_tags($message['content']) // حذف HTML tags
    ];
    
    // نوشتن فیلدها روی تصویر
    foreach ($fields as $field) {
        $fieldName = $field['field_name'];
        $text = $fieldData[$fieldName] ?? '';
        
        if (empty($text)) continue;
        
        $x = (int)$field['x_position'];
        $y = (int)$field['y_position'];
        // افزایش اندازه فونت پیش‌فرض از ۱۲ به ۱۶
        $fontSize = (int)($field['font_size'] ?: 16);
        
        // تنظیم متن برای نمایش صحیح
        $text = persianText($text);
        
        if ($fieldName === 'content') {
            // برای محتوای نامه
            writeWrappedTextImproved(
                $image, 
                $fontSize, 
                $x, 
                $y, 
                $black, 
                $fontPath, 
                $text, 
                $field['width'] ?? 400,
                $field['height'] ?? 300
            );
        } else {
            // برای سایر فیلدها - راست‌چین
            writeRightAlignedText(
                $image,
                $fontSize,
                $x,
                $y,
                $black,
                $fontPath,
                $text
            );
        }
    }
    
    // اضافه کردن امضا در صورت وجود
    if (!empty($message['signature_image'])) {
        $signaturePath = UPLOAD_PATH . 'signatures/' . $message['signature_image'];
        addSignatureToImage($image, $signaturePath, $fields);
    }
}

/**
 * پیدا کردن فونت مناسب
 */
function findSuitableFont() {
    $fontPaths = [
        __DIR__ . '/../assets/fonts/BNazanin.ttf',
        __DIR__ . '/../assets/fonts/B_Nazanin.ttf',
        __DIR__ . '/../assets/fonts/Vazir.ttf',
        __DIR__ . '/../assets/fonts/IRANSans.ttf',
        __DIR__ . '/../assets/fonts/Sahel.ttf',
        __DIR__ . '/../fonts/BNazanin.ttf',
        __DIR__ . '/../fonts/Vazir.ttf',
        'C:/Windows/Fonts/tahoma.ttf',
        'C:/Windows/Fonts/arial.ttf',
        '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'
    ];
    
    foreach ($fontPaths as $path) {
        if (file_exists($path) && is_readable($path)) {
            return $path;
        }
    }
    
    // اگر هیچ فونتی پیدا نشد، سعی کن از فونت‌های سیستم استفاده کن
    $systemFonts = glob('/usr/share/fonts/truetype/*/*.ttf');
    if (!empty($systemFonts)) {
        return $systemFonts[0];
    }
    
    return null;
}

/**
 * تصحیح متن فارسی برای نمایش صحیح
 */
function persianText($text) {
    include_once(__DIR__ . '/../includes/persian_shaper.php');
    return PersianShaper::reshape($text);
}

/**
 * نوشتن متن راست‌چین
 */
function writeRightAlignedText($image, $fontSize, $x, $y, $color, $font, $text) {
    // محاسبه عرض متن
    $bbox = imagettfbbox($fontSize, 0, $font, $text);
    $textWidth = abs($bbox[4] - $bbox[0]);
    
    // تنظیم موقعیت X برای راست‌چین
    $adjustedX = $x - $textWidth;
    
    // نوشتن متن
    imagettftext(
        $image,
        $fontSize,
        0,
        $adjustedX,
        $y,
        $color,
        $font,
        $text
    );
}

/**
 * نوشتن متن wrap شده - نسخه بهبود یافته
 */
function writeWrappedTextImproved($image, $fontSize, $x, $y, $color, $font, $text, $maxWidth, $maxHeight) {
    // جداسازی پاراگراف‌ها
    $paragraphs = explode("\n", $text);
    $allLines = [];
    
    // پردازش هر پاراگراف
    foreach ($paragraphs as $paragraph) {
        if (empty(trim($paragraph))) {
            $allLines[] = ''; // خط خالی بین پاراگراف‌ها
            continue;
        }
        
        // تقسیم به کلمات
        $words = preg_split('/\s+/u', trim($paragraph));
        $currentLine = '';
        
        foreach ($words as $word) {
            if (empty($word)) continue;
            
            // تست اضافه کردن کلمه به خط فعلی
            $testLine = empty($currentLine) ? $word : $currentLine . ' ' . $word;
            
            // محاسبه عرض
            $bbox = imagettfbbox($fontSize, 0, $font, $testLine);
            $lineWidth = abs($bbox[4] - $bbox[0]);
            
            if ($lineWidth > $maxWidth && !empty($currentLine)) {
                // خط فعلی را اضافه کن و خط جدید شروع کن
                $allLines[] = $currentLine;
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }
        
        // اضافه کردن آخرین خط
        if (!empty($currentLine)) {
            $allLines[] = $currentLine;
        }
    }
    
    // محاسبه فاصله بین خطوط (افزایش فاصله)
    $lineHeight = $fontSize * 2;
    $currentY = $y;
    
    // نوشتن خطوط با رعایت محدودیت ارتفاع
    foreach ($allLines as $line) {
        if ($currentY - $y > $maxHeight - $lineHeight) {
            break; // فضا تمام شده
        }
        
        if (!empty(trim($line))) {
            // راست‌چین کردن هر خط
            $bbox = imagettfbbox($fontSize, 0, $font, $line);
            $lineWidth = abs($bbox[4] - $bbox[0]);
            $adjustedX = $x + $maxWidth - $lineWidth;
            
            imagettftext(
                $image,
                $fontSize,
                0,
                $adjustedX,
                $currentY,
                $color,
                $font,
                $line
            );
        }
        
        $currentY += $lineHeight;
    }
}

/**
 * اضافه کردن امضا به تصویر
 */
function addSignatureToImage($image, $signaturePath, $fields) {
    if (!file_exists($signaturePath)) {
        return;
    }
    
    // پیدا کردن موقعیت امضا
    $signatureField = null;
    foreach ($fields as $field) {
        if ($field['field_name'] === 'signature') {
            $signatureField = $field;
            break;
        }
    }
    
    if (!$signatureField) {
        return;
    }
    
    // بارگذاری تصویر امضا
    $signatureInfo = @getimagesize($signaturePath);
    if (!$signatureInfo) {
        return;
    }
    
    $mime = $signatureInfo['mime'];
    $signature = null;
    
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $signature = @imagecreatefromjpeg($signaturePath);
            break;
        case 'image/png':
            $signature = @imagecreatefrompng($signaturePath);
            break;
    }
    
    if (!$signature) {
        return;
    }
    
    // محاسبه اندازه
    $sigWidth = imagesx($signature);
    $sigHeight = imagesy($signature);
    
    $maxWidth = $signatureField['width'] ?? 100;
    $maxHeight = $signatureField['height'] ?? 60;
    
    $scale = min($maxWidth / $sigWidth, $maxHeight / $sigHeight, 1);
    $newWidth = (int)($sigWidth * $scale);
    $newHeight = (int)($sigHeight * $scale);
    
    // موقعیت امضا
    $x = (int)$signatureField['x_position'];
    $y = (int)$signatureField['y_position'];
    
    // کپی با تغییر اندازه
    imagecopyresampled(
        $image,
        $signature,
        $x,
        $y,
        0,
        0,
        $newWidth,
        $newHeight,
        $sigWidth,
        $sigHeight
    );
    
    imagedestroy($signature);
}

/**
 * ذخیره تصویر نامه
 */
function saveLetterImage($image, $messageId) {
    // مسیر ذخیره
    $outputDir = UPLOAD_PATH . 'letters/';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    // نام فایل یکتا
    $filename = 'letter_' . $messageId . '_' . time() . '_' . uniqid() . '.jpg';
    $outputPath = $outputDir . $filename;
    
    // ذخیره با کیفیت بالا
    $result = imagejpeg($image, $outputPath, 95);
    imagedestroy($image);
    
    if (!$result) {
        throw new Exception('خطا در ذخیره تصویر نامه');
    }
    
    return UPLOAD_URL . 'letters/' . $filename;
}
?>