<?php
/**
 * Database Backup Script
 * اسکریپت پشتیبان‌گیری از پایگاه داده
 */

// اجرا فقط از command line
if (php_sapi_name() !== 'cli') {
    die('این اسکریپت فقط از command line قابل اجرا است');
}

require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../config/database.php';

class BackupManager {
    private $db;
    private $backupPath;
    
    public function __construct() {
        $this->db = Database::getInstance();
        $this->backupPath = __DIR__ . '/../backup/';
        
        // ایجاد پوشه backup در صورت عدم وجود
        if (!is_dir($this->backupPath)) {
            mkdir($this->backupPath, 0755, true);
        }
    }
    
    /**
     * تهیه پشتیبان کامل از پایگاه داده
     */
    public function createDatabaseBackup($compress = true) {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "db_backup_{$timestamp}.sql";
        $filepath = $this->backupPath . $filename;
        
        echo "شروع پشتیبان‌گیری از پایگاه داده...\n";
        
        try {
            // دریافت لیست جداول
            $tables = $this->db->fetchAll("SHOW TABLES");
            $sql = '';
            
            // اضافه کردن header
            $sql .= "-- Rural Automation System Database Backup\n";
            $sql .= "-- تاریخ تهیه: " . date('Y-m-d H:i:s') . "\n";
            $sql .= "-- نسخه سیستم: " . SITE_VERSION . "\n\n";
            $sql .= "SET FOREIGN_KEY_CHECKS=0;\n";
            $sql .= "SET SQL_MODE=\"NO_AUTO_VALUE_ON_ZERO\";\n\n";
            
            foreach ($tables as $table) {
                $tableName = array_values($table)[0];
                echo "پشتیبان‌گیری از جدول: {$tableName}\n";
                
                // ساختار جدول
                $createTable = $this->db->fetchRow("SHOW CREATE TABLE `{$tableName}`");
                $sql .= "-- ساختار جدول `{$tableName}`\n";
                $sql .= "DROP TABLE IF EXISTS `{$tableName}`;\n";
                $sql .= $createTable['Create Table'] . ";\n\n";
                
                // داده‌های جدول
                $rows = $this->db->fetchAll("SELECT * FROM `{$tableName}`");
                if (!empty($rows)) {
                    $sql .= "-- داده‌های جدول `{$tableName}`\n";
                    $sql .= "INSERT INTO `{$tableName}` VALUES\n";
                    
                    $values = [];
                    foreach ($rows as $row) {
                        $rowValues = [];
                        foreach ($row as $value) {
                            if ($value === null) {
                                $rowValues[] = 'NULL';
                            } else {
                                $rowValues[] = "'" . addslashes($value) . "'";
                            }
                        }
                        $values[] = '(' . implode(',', $rowValues) . ')';
                    }
                    
                    $sql .= implode(",\n", $values) . ";\n\n";
                }
            }
            
            $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
            
            // ذخیره فایل
            file_put_contents($filepath, $sql);
            
            // فشرده‌سازی در صورت درخواست
            if ($compress && extension_loaded('zip')) {
                $zipFilepath = $this->backupPath . "db_backup_{$timestamp}.zip";
                $zip = new ZipArchive();
                
                if ($zip->open($zipFilepath, ZipArchive::CREATE) === TRUE) {
                    $zip->addFile($filepath, $filename);
                    $zip->close();
                    
                    // حذف فایل SQL غیرفشرده
                    unlink($filepath);
                    $filepath = $zipFilepath;
                    $filename = basename($zipFilepath);
                }
            }
            
            $fileSize = formatFileSize(filesize($filepath));
            echo "پشتیبان‌گیری با موفقیت تکمیل شد\n";
            echo "فایل: {$filename}\n";
            echo "حجم: {$fileSize}\n";
            
            // ثبت در لاگ
            $this->logBackup($filename, filesize($filepath));
            
            return $filepath;
            
        } catch (Exception $e) {
            echo "خطا در پشتیبان‌گیری: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * پشتیبان‌گیری از فایل‌ها
     */
    public function createFilesBackup() {
        $timestamp = date('Y-m-d_H-i-s');
        $filename = "files_backup_{$timestamp}.zip";
        $filepath = $this->backupPath . $filename;
        
        echo "شروع پشتیبان‌گیری از فایل‌ها...\n";
        
        if (!extension_loaded('zip')) {
            echo "افزونه ZIP نصب نیست\n";
            return false;
        }
        
        try {
            $zip = new ZipArchive();
            
            if ($zip->open($filepath, ZipArchive::CREATE) !== TRUE) {
                throw new Exception("نمی‌توان فایل ZIP ایجاد کرد");
            }
            
            $rootPath = realpath(__DIR__ . '/../');
            $excludeDirs = ['backup', 'logs', 'cache', '.git'];
            
            $files = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($rootPath),
                RecursiveIteratorIterator::LEAVES_ONLY
            );
            
            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($rootPath) + 1);
                    
                    // بررسی عدم وجود در فهرست حذف
                    $skip = false;
                    foreach ($excludeDirs as $excludeDir) {
                        if (strpos($relativePath, $excludeDir . '/') === 0) {
                            $skip = true;
                            break;
                        }
                    }
                    
                    if (!$skip) {
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }
            
            $zip->close();
            
            $fileSize = formatFileSize(filesize($filepath));
            echo "پشتیبان‌گیری فایل‌ها تکمیل شد\n";
            echo "فایل: {$filename}\n";
            echo "حجم: {$fileSize}\n";
            
            return $filepath;
            
        } catch (Exception $e) {
            echo "خطا در پشتیبان‌گیری فایل‌ها: " . $e->getMessage() . "\n";
            return false;
        }
    }
    
    /**
     * پاکسازی پشتیبان‌های قدیمی
     */
    public function cleanupOldBackups($keepDays = 30) {
        echo "پاکسازی پشتیبان‌های قدیمی‌تر از {$keepDays} روز...\n";
        
        $files = glob($this->backupPath . '*');
        $deletedCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $fileAge = time() - filemtime($file);
                $maxAge = $keepDays * 24 * 60 * 60; // تبدیل روز به ثانیه
                
                if ($fileAge > $maxAge) {
                    unlink($file);
                    $deletedCount++;
                    echo "حذف شد: " . basename($file) . "\n";
                }
            }
        }
        
        echo "تعداد {$deletedCount} فایل قدیمی حذف شد\n";
    }
    
    /**
     * ثبت اطلاعات پشتیبان در پایگاه داده
     */
    private function logBackup($filename, $filesize) {
        try {
            // ایجاد جدول backup_logs در صورت عدم وجود
            $this->db->query("
                CREATE TABLE IF NOT EXISTS backup_logs (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    filename VARCHAR(255) NOT NULL,
                    filesize BIGINT NOT NULL,
                    backup_type ENUM('database', 'files', 'full') DEFAULT 'database',
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
            
            $this->db->insert(
                "INSERT INTO backup_logs (filename, filesize, backup_type) VALUES (?, ?, ?)",
                [$filename, $filesize, 'database']
            );
            
        } catch (Exception $e) {
            // در صورت خطا در ثبت لاگ، کار را ادامه می‌دهیم
            echo "هشدار: ثبت لاگ پشتیبان‌گیری انجام نشد\n";
        }
    }
    
    /**
     * نمایش آمار پشتیبان‌ها
     */
    public function showBackupStats() {
        echo "\n=== آمار پشتیبان‌ها ===\n";
        
        $files = glob($this->backupPath . '*');
        $totalSize = 0;
        $fileCount = 0;
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $totalSize += filesize($file);
                $fileCount++;
                
                echo sprintf(
                    "%-30s %10s %s\n",
                    basename($file),
                    formatFileSize(filesize($file)),
                    date('Y-m-d H:i:s', filemtime($file))
                );
            }
        }
        
        echo "\nتعداد کل: {$fileCount} فایل\n";
        echo "حجم کل: " . formatFileSize($totalSize) . "\n";
    }
}

// تابع کمکی برای فرمت کردن حجم فایل
function formatFileSize($bytes) {
    if ($bytes === 0) return '0 Bytes';
    
    $k = 1024;
    $sizes = ['Bytes', 'KB', 'MB', 'GB'];
    $i = floor(log($bytes) / log($k));
    
    return round($bytes / pow($k, $i), 2) . ' ' . $sizes[$i];
}

// اجرای اصلی
if (isset($argv)) {
    $backup = new BackupManager();
    
    // پردازش آرگومان‌های command line
    $options = getopt('h', ['help', 'db-only', 'files-only', 'full', 'cleanup::', 'stats']);
    
    if (isset($options['h']) || isset($options['help'])) {
        echo "استفاده:\n";
        echo "  php backup.php [options]\n\n";
        echo "گزینه‌ها:\n";
        echo "  --db-only     فقط پشتیبان‌گیری پایگاه داده\n";
        echo "  --files-only  فقط پشتیبان‌گیری فایل‌ها\n";
        echo "  --full        پشتیبان‌گیری کامل (پیش‌فرض)\n";
        echo "  --cleanup[=days] پاکسازی فایل‌های قدیمی (پیش‌فرض: 30 روز)\n";
        echo "  --stats       نمایش آمار پشتیبان‌ها\n";
        echo "  -h, --help    نمایش این راهنما\n";
        exit(0);
    }
    
    if (isset($options['stats'])) {
        $backup->showBackupStats();
        exit(0);
    }
    
    if (isset($options['cleanup'])) {
        $days = is_string($options['cleanup']) ? (int)$options['cleanup'] : 30;
        $backup->cleanupOldBackups($days);
        exit(0);
    }
    
    echo "=== سیستم پشتیبان‌گیری اتوماسیون دهیاری ===\n";
    echo "تاریخ: " . date('Y-m-d H:i:s') . "\n\n";
    
    if (isset($options['db-only'])) {
        $backup->createDatabaseBackup();
    } elseif (isset($options['files-only'])) {
        $backup->createFilesBackup();
    } else {
        // پشتیبان‌گیری کامل (پیش‌فرض)
        $backup->createDatabaseBackup();
        $backup->createFilesBackup();
    }
    
    echo "\nپشتیبان‌گیری تکمیل شد.\n";
}