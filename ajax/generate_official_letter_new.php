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

header('Content-Type: application/json; charset=utf-8');

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
    
    if (!$template || !$template['background_image']) {
        // اگر قالبی وجود ندارد، از روش قدیمی استفاده کن
        include 'generate_official_letter.php';
        exit;
    }
    
    // دریافت موقعیت فیلدها
    $fields = $db->fetchAll(
        "SELECT * FROM template_fields WHERE template_id = ?",
        [$template['id']]
    );
    
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
    echo json_encode([
        'success' => false, 
        'message' => $e->getMessage()
    ]);
}

/**
 * تولید تصویر نامه با GD
 */
function generateLetterImage($message, $template, $fields) {
    $backgroundPath = UPLOAD_PATH . 'templates/' . $template['background_image'];
    
    if (!file_exists($backgroundPath)) {
        throw new Exception('تصویر پس‌زمینه یافت نشد');
    }
    
    // بارگذاری تصویر پس‌زمینه
    $imageInfo = getimagesize($backgroundPath);
    $mime = $imageInfo['mime'];
    
    switch ($mime) {
        case 'image/jpeg':
        case 'image/jpg':
            $bgImage = imagecreatefromjpeg($backgroundPath);
            break;
        case 'image/png':
            $bgImage = imagecreatefrompng($backgroundPath);
            break;
        default:
            throw new Exception('فرمت تصویر پشتیبانی نمی‌شود');
    }
    
    if (!$bgImage) {
        throw new Exception('خطا در بارگذاری تصویر پس‌زمینه');
    }
    
    // اندازه تصویر
    $width = imagesx($bgImage);
    $height = imagesy($bgImage);
    
    // ایجاد تصویر True Color برای کیفیت بهتر
    $image = imagecreatetruecolor($width, $height);
    imagecopy($image, $bgImage, 0, 0, 0, 0, $width, $height);
    imagedestroy($bgImage);
    
    // رنگ‌ها
    $black = imagecolorallocate($image, 0, 0, 0);
    
    // فونت فارسی
    $fontPath = __DIR__ . '/../assets/fonts/BNazanin.ttf';
    if (!file_exists($fontPath)) {
        // اگر فونت B Nazanin موجود نیست، از فونت پیش‌فرض استفاده کن
        $fontPath = __DIR__ . '/../assets/fonts/Vazir.ttf';
    }
    
    // آماده‌سازی داده‌ها
    $currentDate = JalaliDate::toJalali(time(), 'Y/m/d');
    
    $fieldData = [
        'message_number' => $message['message_number'] ?: 'بدون شماره',
        'date' => $currentDate,
        'subject' => $message['subject'],
        'receiver_name' => $message['receiver_name'],
        'sender_name' => $message['sender_name'],
        'content' => $message['content']
    ];
    
    // نوشتن فیلدها روی تصویر
    foreach ($fields as $field) {
        $text = $fieldData[$field['field_name']] ?? '';
        
        if (empty($text)) continue;
        
        $x = (int)$field['x_position'];
        $y = (int)$field['y_position'];
        // افزایش اندازه فونت پیش‌فرض از ۱۲ به ۱۶
        $fontSize = (int)($field['font_size'] ?: 16);
        
        // تبدیل اندازه فونت به پوینت برای imagettftext
        $fontSizePt = $fontSize * 0.75;
        
        if ($field['field_name'] === 'content') {
            // متن نامه - نیاز به wrap کردن
            if ($field['width'] && $field['height']) {
                writeWrappedText(
                    $image, 
                    $fontSizePt, 
                    $x, 
                    $y, 
                    $black, 
                    $fontPath, 
                    $text, 
                    $field['width'],
                    $field['height']
                );
            }
        } elseif ($field['field_name'] === 'signature' && $message['signature_image']) {
            // امضای دیجیتال
            $signaturePath = UPLOAD_PATH . 'signatures/' . $message['signature_image'];
            if (file_exists($signaturePath)) {
                $signature = imagecreatefrompng($signaturePath);
                if ($signature) {
                    $sigWidth = imagesx($signature);
                    $sigHeight = imagesy($signature);
                    
                    // تنظیم اندازه امضا
                    $maxWidth = $field['width'] ?: 100;
                    $maxHeight = $field['height'] ?: 60;
                    
                    $ratio = min($maxWidth / $sigWidth, $maxHeight / $sigHeight);
                    $newWidth = (int)($sigWidth * $ratio);
                    $newHeight = (int)($sigHeight * $ratio);
                    
                    imagecopyresampled(
                        $image, $signature,
                        $x, $y,
                        0, 0,
                        $newWidth, $newHeight,
                        $sigWidth, $sigHeight
                    );
                    
                    imagedestroy($signature);
                }
            }
        } elseif ($field['field_name'] === 'stamp_place') {
            // محل مهر - خالی می‌ماند
            continue;
        } else {
            // فیلدهای معمولی
            imagettftext(
                $image,
                $fontSizePt,
                0, // زاویه
                $x,
                $y + $fontSize, // تنظیم Y برای baseline
                $black,
                $fontPath,
                persianText($text)
            );
        }
    }
    
    // ذخیره تصویر
    $outputDir = UPLOAD_PATH . 'letters/';
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $filename = 'letter_' . $message['id'] . '_' . time() . '.jpg';
    $outputPath = $outputDir . $filename;
    
    // ذخیره با کیفیت 100%
    imagejpeg($image, $outputPath, 100);
    imagedestroy($image);
    
    return UPLOAD_URL . 'letters/' . $filename;
}

/**
 * نوشتن متن wrap شده
 */
function writeWrappedText($image, $fontSize, $x, $y, $color, $font, $text, $maxWidth, $maxHeight) {
    // تبدیل متن به آرایه خطوط
    $lines = explode("\n", $text);
    $wrappedLines = [];
    
    foreach ($lines as $line) {
        $words = explode(' ', $line);
        $currentLine = '';
        
        foreach ($words as $word) {
            $testLine = $currentLine . ' ' . $word;
            $bbox = imagettfbbox($fontSize, 0, $font, persianText(trim($testLine)));
            $lineWidth = abs($bbox[4] - $bbox[0]);
            
            if ($lineWidth > $maxWidth && !empty($currentLine)) {
                $wrappedLines[] = trim($currentLine);
                $currentLine = $word;
            } else {
                $currentLine = $testLine;
            }
        }
        
        if (!empty(trim($currentLine))) {
            $wrappedLines[] = trim($currentLine);
        }
    }
    
    // محاسبه ارتفاع خط (افزایش فاصله بین خطوط)
    $lineHeight = $fontSize * 2;
    $totalLines = count($wrappedLines);
    $maxLines = (int)($maxHeight / $lineHeight);
    
    // اگر متن بیش از حد بلند است، اندازه فونت را کم کن
    if ($totalLines > $maxLines && $fontSize > 10) {
        $newFontSize = $fontSize - 2;
        writeWrappedText($image, $newFontSize, $x, $y, $color, $font, $text, $maxWidth, $maxHeight);
        return;
    }
    
    // نوشتن خطوط
    $currentY = $y + $fontSize;
    $linesToWrite = min($totalLines, $maxLines);
    
    for ($i = 0; $i < $linesToWrite; $i++) {
        imagettftext(
            $image,
            $fontSize,
            0,
            $x,
            $currentY,
            $color,
            $font,
            persianText($wrappedLines[$i])
        );
        
        $currentY += $lineHeight;
    }
}

/**
 * تصحیح متن فارسی برای نمایش صحیح
 */
function persianText($text) {
    include_once(__DIR__ . '/../includes/persian_shaper.php');
    return PersianShaper::reshape($text);
}
?>