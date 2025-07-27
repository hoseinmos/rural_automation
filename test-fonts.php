<?php
/**
 * تست فونت‌ها و نمایش متن فارسی
 * این فایل را در root پروژه قرار دهید
 */

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>تست فونت‌ها</title>
    <style>
        body {
            font-family: Tahoma, Arial, sans-serif;
            direction: rtl;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .success { color: green; }
        .error { color: red; }
        .warning { color: orange; }
        img {
            border: 1px solid #ddd;
            margin: 10px 0;
            max-width: 100%;
        }
        .font-test {
            background: #f8f9fa;
            padding: 15px;
            margin: 10px 0;
            border-radius: 5px;
        }
    </style>
</head>
<body>
<div class="container">
    <h1>🔍 تست فونت‌ها و تولید تصویر نامه</h1>
    
    <?php
    // تنظیمات
    $assetsPath = __DIR__ . '/assets/fonts/';
    $uploadPath = __DIR__ . '/uploads/test/';
    
    // ایجاد پوشه تست
    if (!is_dir($uploadPath)) {
        @mkdir($uploadPath, 0755, true);
    }
    
    // لیست فونت‌های احتمالی
    $fontPaths = [
        'BNazanin.ttf' => $assetsPath . 'BNazanin.ttf',
        'B_Nazanin.ttf' => $assetsPath . 'B_Nazanin.ttf',
        'Vazir.ttf' => $assetsPath . 'Vazir.ttf',
        'IRANSans.ttf' => $assetsPath . 'IRANSans.ttf',
        'Sahel.ttf' => $assetsPath . 'Sahel.ttf',
        'Tahoma (Windows)' => 'C:/Windows/Fonts/tahoma.ttf',
        'Arial (Windows)' => 'C:/Windows/Fonts/arial.ttf',
        'Liberation (Linux)' => '/usr/share/fonts/truetype/liberation/LiberationSans-Regular.ttf'
    ];
    
    echo "<h2>1. بررسی فونت‌های موجود:</h2>";
    echo "<div class='font-test'>";
    
    $availableFonts = [];
    foreach ($fontPaths as $name => $path) {
        if (file_exists($path) && is_readable($path)) {
            echo "<p class='success'>✅ $name: موجود است ($path)</p>";
            $availableFonts[$name] = $path;
        } else {
            echo "<p class='error'>❌ $name: یافت نشد ($path)</p>";
        }
    }
    
    if (empty($availableFonts)) {
        echo "<p class='error'><strong>هیچ فونتی یافت نشد!</strong></p>";
        
        // تلاش برای ایجاد پوشه fonts
        if (!is_dir($assetsPath)) {
            echo "<p class='warning'>پوشه fonts وجود ندارد. سعی در ایجاد...</p>";
            if (@mkdir($assetsPath, 0755, true)) {
                echo "<p class='success'>✅ پوشه fonts ایجاد شد: $assetsPath</p>";
                echo "<p>لطفاً فایل‌های فونت را در این پوشه قرار دهید.</p>";
            } else {
                echo "<p class='error'>❌ نتوانستم پوشه را ایجاد کنم</p>";
            }
        }
    }
    echo "</div>";
    
    // تست تولید تصویر
    if (!empty($availableFonts)) {
        echo "<h2>2. تست تولید تصویر با متن فارسی:</h2>";
        
        // انتخاب اولین فونت موجود
        $testFont = reset($availableFonts);
        $fontName = key($availableFonts);
        
        echo "<p>استفاده از فونت: <strong>$fontName</strong></p>";
        
        try {
            // ایجاد تصویر
            $width = 600;
            $height = 400;
            $image = imagecreatetruecolor($width, $height);
            
            // رنگ‌ها
            $white = imagecolorallocate($image, 255, 255, 255);
            $black = imagecolorallocate($image, 0, 0, 0);
            $blue = imagecolorallocate($image, 0, 0, 255);
            $red = imagecolorallocate($image, 255, 0, 0);
            
            // پس‌زمینه سفید
            imagefill($image, 0, 0, $white);
            
            // کادر
            imagerectangle($image, 10, 10, $width-10, $height-10, $black);
            
            // متن‌های تست
            $texts = [
                ['text' => 'تست نمایش فونت فارسی', 'size' => 20, 'y' => 50],
                ['text' => 'سلام دنیا - Hello World', 'size' => 16, 'y' => 100],
                ['text' => '۱۲۳۴۵۶۷۸۹۰', 'size' => 18, 'y' => 150],
                ['text' => 'این یک متن طولانی برای تست wrap کردن متن است که باید در چند خط نمایش داده شود.', 'size' => 14, 'y' => 200],
                ['text' => 'حروف عربی: ي ك | حروف فارسی: ی ک', 'size' => 16, 'y' => 250],
            ];
            
            // نوشتن متن‌ها
            foreach ($texts as $i => $item) {
                $color = $i % 2 == 0 ? $black : $blue;
                
                // راست‌چین
                $bbox = imagettfbbox($item['size'], 0, $testFont, $item['text']);
                $textWidth = abs($bbox[4] - $bbox[0]);
                $x = $width - $textWidth - 20;
                
                imagettftext(
                    $image,
                    $item['size'],
                    0,
                    $x,
                    $item['y'],
                    $color,
                    $testFont,
                    $item['text']
                );
            }
            
            // ذخیره تصویر
            $testFile = $uploadPath . 'font_test_' . time() . '.jpg';
            imagejpeg($image, $testFile, 95);
            imagedestroy($image);
            
            echo "<p class='success'>✅ تصویر با موفقیت تولید شد</p>";
            echo "<img src='uploads/test/" . basename($testFile) . "' alt='تست فونت'>";
            
        } catch (Exception $e) {
            echo "<p class='error'>❌ خطا در تولید تصویر: " . $e->getMessage() . "</p>";
        }
    }
    
    // نمایش اطلاعات سیستم
    echo "<h2>3. اطلاعات سیستم:</h2>";
    echo "<div class='font-test'>";
    echo "<p><strong>PHP Version:</strong> " . PHP_VERSION . "</p>";
    echo "<p><strong>GD Library:</strong> " . (extension_loaded('gd') ? '✅ نصب شده' : '❌ نصب نشده') . "</p>";
    
    if (extension_loaded('gd')) {
        $gdInfo = gd_info();
        echo "<p><strong>GD Version:</strong> " . $gdInfo['GD Version'] . "</p>";
        echo "<p><strong>FreeType Support:</strong> " . ($gdInfo['FreeType Support'] ? '✅ فعال' : '❌ غیرفعال') . "</p>";
    }
    
    echo "<p><strong>mbstring:</strong> " . (extension_loaded('mbstring') ? '✅ نصب شده' : '❌ نصب نشده') . "</p>";
    echo "</div>";
    
    // دانلود فونت
    echo "<h2>4. دانلود فونت‌ها:</h2>";
    echo "<div class='font-test'>";
    echo "<p>اگر فونتی ندارید، می‌توانید از لینک‌های زیر دانلود کنید:</p>";
    echo "<ul>";
    echo "<li><a href='https://github.com/rastikerdar/vazir-font/releases' target='_blank'>فونت وزیر</a></li>";
    echo "<li><a href='https://github.com/rastikerdar/sahel-font/releases' target='_blank'>فونت ساحل</a></li>";
    echo "<li><a href='https://fontiran.com' target='_blank'>مخزن فونت‌های فارسی</a></li>";
    echo "</ul>";
    echo "<p><strong>توجه:</strong> فایل‌های فونت را در مسیر <code>$assetsPath</code> قرار دهید.</p>";
    echo "</div>";
    ?>
    
    <hr>
    <a href="index.php" class="btn btn-primary">بازگشت به سیستم</a>
</div>
</body>
</html>