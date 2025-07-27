<?php
/**
 * Database Configuration and Connection Class
 * فایل پیکربندی و اتصال به پایگاه داده - نسخه اصلاح شده برای UTF-8
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private static $instance = null;

    private function __construct() {
        $this->loadConfig();
    }

    private function loadConfig() {
        $configFile = __DIR__ . '/../config/database_config.php';
        
        if (file_exists($configFile)) {
            require_once $configFile;
            
            $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $this->db_name = defined('DB_NAME') ? DB_NAME : 'rural_automation';
            $this->username = defined('DB_USERNAME') ? DB_USERNAME : 'root';
            $this->password = defined('DB_PASSWORD') ? DB_PASSWORD : '';
        } else {
            $this->host = 'localhost';
            $this->db_name = 'rural_automation';
            $this->username = 'root';
            $this->password = '';
        }
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    public function getConnection() {
        if ($this->conn === null) {
            try {
                // تنظیمات مهم برای UTF-8
                $options = [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                    // تنظیم charset به صورت صحیح
                    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES 'utf8mb4' COLLATE 'utf8mb4_persian_ci'"
                ];
                
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                    $this->username, 
                    $this->password,
                    $options
                );
                
                // تنظیمات اضافی برای اطمینان
                $this->conn->exec("SET CHARACTER SET utf8mb4");
                $this->conn->exec("SET NAMES utf8mb4");
                $this->conn->exec("SET COLLATION_CONNECTION = 'utf8mb4_persian_ci'");
                
            } catch(PDOException $exception) {
                error_log("Database connection error: " . $exception->getMessage());
                
                // نمایش خطای دقیق‌تر
                if (strpos($exception->getMessage(), 'Access denied') !== false) {
                    throw new Exception("خطا در دسترسی به پایگاه داده: نام کاربری یا رمز عبور اشتباه است");
                } elseif (strpos($exception->getMessage(), 'Unknown database') !== false) {
                    throw new Exception("خطا: پایگاه داده وجود ندارد");
                } else {
                    throw new Exception("خطا در اتصال به پایگاه داده: " . $exception->getMessage());
                }
            }
        }
        return $this->conn;
    }

    /**
     * Execute a prepared statement with UTF-8 support
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            
            // اطمینان از اینکه پارامترها UTF-8 هستند
            foreach ($params as $key => $value) {
                if (is_string($value)) {
                    $params[$key] = mb_convert_encoding($value, 'UTF-8', 'auto');
                }
            }
            
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            error_log("SQL: " . $sql);
            throw new Exception("خطا در اجرای کوئری: " . $e->getMessage());
        }
    }

    /**
     * Get single row
     */
    public function fetchRow($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }

    /**
     * Get multiple rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Insert and get last insert ID
     */
    public function insert($sql, $params = []) {
        $this->query($sql, $params);
        return $this->getConnection()->lastInsertId();
    }

    /**
     * Update/Delete and get affected rows
     */
    public function execute($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }

    /**
     * Execute raw SQL
     */
    public function exec($sql) {
        try {
            return $this->getConnection()->exec($sql);
        } catch(PDOException $e) {
            error_log("Database exec error: " . $e->getMessage());
            throw new Exception("خطا در اجرای دستور SQL: " . $e->getMessage());
        }
    }

    /**
     * تست اتصال با بررسی encoding
     */
    public function testConnection() {
        try {
            $conn = $this->getConnection();
            
            // بررسی charset فعلی
            $charset = $conn->query("SELECT @@character_set_connection")->fetchColumn();
            $collation = $conn->query("SELECT @@collation_connection")->fetchColumn();
            
            if ($charset !== 'utf8mb4' || $collation !== 'utf8mb4_persian_ci') {
                error_log("Warning: Database charset/collation mismatch. Current: $charset/$collation");
                return false;
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Database connection test failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * بررسی و تصحیح encoding جداول
     */
    public function fixTablesEncoding() {
        try {
            $conn = $this->getConnection();
            
            // دریافت لیست جداول
            $tables = $conn->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($tables as $table) {
                // تغییر charset جدول
                $sql = "ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_persian_ci";
                $conn->exec($sql);
                error_log("Table $table encoding fixed to utf8mb4_persian_ci");
            }
            
            return true;
        } catch (Exception $e) {
            error_log("Error fixing tables encoding: " . $e->getMessage());
            return false;
        }
    }

    /**
     * دریافت اطلاعات اتصال فعلی
     */
    public function getConnectionInfo() {
        try {
            $conn = $this->getConnection();
            
            return [
                'host' => $this->host,
                'database' => $this->db_name,
                'username' => $this->username,
                'password' => '***',
                'charset' => $conn->query("SELECT @@character_set_connection")->fetchColumn(),
                'collation' => $conn->query("SELECT @@collation_connection")->fetchColumn(),
                'client_charset' => $conn->query("SELECT @@character_set_client")->fetchColumn(),
                'server_charset' => $conn->query("SELECT @@character_set_server")->fetchColumn()
            ];
        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }
}

// اسکریپت تست اتصال
if (basename($_SERVER['PHP_SELF']) == 'database.php') {
    header('Content-Type: text/html; charset=utf-8');
    
    echo "<h2>تست اتصال به دیتابیس</h2>";
    
    try {
        $db = Database::getInstance();
        
        if ($db->testConnection()) {
            echo "<p style='color: green;'>✓ اتصال برقرار شد</p>";
            
            $info = $db->getConnectionInfo();
            echo "<h3>اطلاعات اتصال:</h3>";
            echo "<pre>" . print_r($info, true) . "</pre>";
            
            // پیشنهاد تصحیح encoding
            if ($info['charset'] !== 'utf8mb4' || $info['collation'] !== 'utf8mb4_persian_ci') {
                echo "<p style='color: orange;'>⚠ Encoding نیاز به تصحیح دارد</p>";
                echo "<button onclick='fixEncoding()'>تصحیح Encoding جداول</button>";
            }
        } else {
            echo "<p style='color: red;'>✗ اتصال برقرار نشد</p>";
        }
    } catch (Exception $e) {
        echo "<p style='color: red;'>خطا: " . $e->getMessage() . "</p>";
    }
}
?>