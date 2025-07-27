<?php
/**
 * اسکریپت تصحیح مشکلات Encoding
 * این فایل را یکبار اجرا کنید تا مشکلات encoding حل شود
 */

// اضافه کردن header برای UTF-8
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');

// شامل کردن فایل‌های ضروری
require_once 'includes/database.php';
require_once 'includes/config.php';

?>
<!DOCTYPE html>
<html dir="rtl" lang="fa">
<head>
    <meta charset="UTF-8">
    <title>تصحیح Encoding دیتابیس</title>
    <style>
        body {
            font-family: 'Tahoma', 'Arial', sans-serif;
            direction: rtl;
            max-width: 800px;
            margin: 20px auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .success { color: green; background: #e8f5e9; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .error { color: red; background: #ffebee; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .warning { color: orange; background: #fff3e0; padding: 10px; margin: 10px 0; border-radius: 5px; }
        .info { background: #e3f2fd; padding: 10px; margin: 10px 0; border-radius: 5px; }
        button { padding: 10px 20px; margin: 5px; cursor: pointer; }
        pre { background: #f5f5f5; padding: 10px; overflow-x: auto; }
        .box { background: white; padding: 20px; margin: 20px 0; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
    </style>
</head>
<body>

<h1>🔧 تصحیح Encoding دیتابیس</h1>

<?php
try {
    $db = Database::getInstance();
    
    echo "<div class='box'>";
    echo "<h2>1️⃣ بررسی وضعیت فعلی</h2>";
    
    // بررسی charset دیتابیس
    $dbCharset = $db->fetchRow("SELECT DEFAULT_CHARACTER_SET_NAME, DEFAULT_COLLATION_NAME 
                                FROM information_schema.SCHEMATA 
                                WHERE SCHEMA_NAME = ?", [DB_NAME]);
    
    echo "<div class='info'>";
    echo "<strong>Charset دیتابیس:</strong> " . $dbCharset['DEFAULT_CHARACTER_SET_NAME'] . "<br>";
    echo "<strong>Collation دیتابیس:</strong> " . $dbCharset['DEFAULT_COLLATION_NAME'];
    echo "</div>";
    
    // بررسی charset اتصال
    $connCharset = $db->fetchRow("SELECT @@character_set_connection as charset, 
                                         @@collation_connection as collation");
    
    echo "<div class='info'>";
    echo "<strong>Charset اتصال:</strong> " . $connCharset['charset'] . "<br>";
    echo "<strong>Collation اتصال:</strong> " . $connCharset['collation'];
    echo "</div>";
    echo "</div>";
    
    // تصحیح charset دیتابیس
    if (isset($_POST['fix_database'])) {
        echo "<div class='box'>";
        echo "<h2>2️⃣ تصحیح Charset دیتابیس</h2>";
        
        try {
            $db->exec("ALTER DATABASE `" . DB_NAME . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
            echo "<div class='success'>✅ Charset دیتابیس با موفقیت تصحیح شد</div>";
        } catch (Exception $e) {
            echo "<div class='error'>❌ خطا در تصحیح دیتابیس: " . $e->getMessage() . "</div>";
        }
        echo "</div>";
    }
    
    // تصحیح جداول
    if (isset($_POST['fix_tables'])) {
        echo "<div class='box'>";
        echo "<h2>3️⃣ تصحیح Charset جداول</h2>";
        
        $tables = $db->fetchAll("SHOW TABLES");
        
        foreach ($tables as $table) {
            $tableName = array_values($table)[0];
            
            try {
                // تغییر charset جدول
                $db->exec("ALTER TABLE `$tableName` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci");
                echo "<div class='success'>✅ جدول $tableName تصحیح شد</div>";
                
                // تصحیح ستون‌های متنی
                $columns = $db->fetchAll("SHOW FULL COLUMNS FROM `$tableName` WHERE Type LIKE '%char%' OR Type LIKE '%text%'");
                
                foreach ($columns as $column) {
                    $colName = $column['Field'];
                    $colType = $column['Type'];
                    $colNull = $column['Null'] == 'YES' ? 'NULL' : 'NOT NULL';
                    $colDefault = $column['Default'] ? "DEFAULT '{$column['Default']}'" : '';
                    
                    $sql = "ALTER TABLE `$tableName` MODIFY `$colName` $colType CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci $colNull $colDefault";
                    
                    try {
                        $db->exec($sql);
                    } catch (Exception $e) {
                        echo "<div class='warning'>⚠️ نتوانستم ستون $colName در جدول $tableName را تصحیح کنم</div>";
                    }
                }
                
            } catch (Exception $e) {
                echo "<div class='error'>❌ خطا در تصحیح جدول $tableName: " . $e->getMessage() . "</div>";
            }
        }
        echo "</div>";
    }
    
    // تصحیح داده‌های موجود
    if (isset($_POST['fix_data'])) {
        echo "<div class='box'>";
        echo "<h2>4️⃣ تصحیح داده‌های موجود</h2>";
        
        // این قسمت برای تبدیل داده‌هایی که با encoding اشتباه ذخیره شده‌اند
        $tables = ['users', 'messages', 'system_settings'];
        
        foreach ($tables as $tableName) {
            try {
                // دریافت تمام رکوردها
                $records = $db->fetchAll("SELECT * FROM `$tableName`");
                
                foreach ($records as $record) {
                    $updates = [];
                    $params = [];
                    
                    foreach ($record as $field => $value) {
                        if (is_string($value) && !is_numeric($value)) {
                            // تلاش برای تصحیح encoding
                            $fixed = mb_convert_encoding($value, 'UTF-8', 'auto');
                            
                            if ($fixed !== $value) {
                                $updates[] = "`$field` = ?";
                                $params[] = $fixed;
                            }
                        }
                    }
                    
                    if (!empty($updates)) {
                        $params[] = $record['id'];
                        $sql = "UPDATE `$tableName` SET " . implode(', ', $updates) . " WHERE id = ?";
                        $db->execute($sql, $params);
                    }
                }
                
                echo "<div class='success'>✅ داده‌های جدول $tableName بررسی و تصحیح شد</div>";
                
            } catch (Exception $e) {
                echo "<div class='warning'>⚠️ نتوانستم داده‌های جدول $tableName را تصحیح کنم</div>";
            }
        }
        echo "</div>";
    }
    
    // فرم‌ها
    echo "<div class='box'>";
    echo "<h2>🛠️ عملیات تصحیح</h2>";
    echo "<form method='post'>";
    echo "<button type='submit' name='fix_database'>1. تصحیح Charset دیتابیس</button>";
    echo "<button type='submit' name='fix_tables'>2. تصحیح Charset جداول</button>";
    echo "<button type='submit' name='fix_data'>3. تصحیح داده‌های موجود</button>";
    echo "</form>";
    echo "</div>";
    
    // راهنما
    echo "<div class='box'>";
    echo "<h2>📝 راهنما</h2>";
    echo "<ol>";
    echo "<li>ابتدا دکمه «تصحیح Charset دیتابیس» را بزنید</li>";
    echo "<li>سپس دکمه «تصحیح Charset جداول» را بزنید</li>";
    echo "<li>در صورت نیاز، دکمه «تصحیح داده‌های موجود» را بزنید</li>";
    echo "<li>بعد از اتمام، این فایل را حذف کنید</li>";
    echo "</ol>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div class='error'>";
    echo "<h2>❌ خطا در اتصال به دیتابیس</h2>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}
?>

</body>
</html>