<?php
/**
 * Database Configuration and Connection Class
 * فایل پیکربندی و اتصال به پایگاه داده - نسخه اصلاح شده
 */

class Database {
    private $host;
    private $db_name;
    private $username;
    private $password;
    private $conn;
    private static $instance = null;

    private function __construct() {
        // بارگذاری تنظیمات از فایل config یا مقادیر پیش‌فرض
        $this->loadConfig();
    }

    private function loadConfig() {
        // ابتدا بررسی می‌کنیم که فایل database_config.php وجود دارد یا نه
        $configFile = __DIR__ . '/database_config.php';
        
        if (file_exists($configFile)) {
            // اگر فایل config وجود دارد، از آن استفاده می‌کنیم
            require_once $configFile;
            
            $this->host = defined('DB_HOST') ? DB_HOST : 'localhost';
            $this->db_name = defined('DB_NAME') ? DB_NAME : 'rural_automation';
            $this->username = defined('DB_USERNAME') ? DB_USERNAME : 'root';
            $this->password = defined('DB_PASSWORD') ? DB_PASSWORD : '';
        } else {
            // مقادیر پیش‌فرض
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
                $this->conn = new PDO(
                    "mysql:host=" . $this->host . ";dbname=" . $this->db_name . ";charset=utf8mb4", 
                    $this->username, 
                    $this->password,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                        PDO::ATTR_EMULATE_PREPARES => false,
                        PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
                    ]
                );
            } catch(PDOException $exception) {
                // لاگ خطا برای debug
                error_log("Database connection error: " . $exception->getMessage());
                echo "خطای اتصال: " . $exception->getMessage(); // برای debug در حین نصب
                throw new Exception("خطا در اتصال به پایگاه داده: " . $exception->getMessage());
            }
        }
        return $this->conn;
    }

    /**
     * Execute a prepared statement
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->getConnection()->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch(PDOException $e) {
            error_log("Database query error: " . $e->getMessage());
            echo "خطای کوئری: " . $e->getMessage(); // برای debug
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
     * Execute raw SQL (برای ایجاد جداول)
     */
    public function exec($sql) {
        try {
            return $this->getConnection()->exec($sql);
        } catch(PDOException $e) {
            error_log("Database exec error: " . $e->getMessage());
            echo "خطا در اجرای SQL: " . $e->getMessage(); // برای debug
            throw new Exception("خطا در اجرای دستور SQL: " . $e->getMessage());
        }
    }

    /**
     * تست اتصال
     */
    public function testConnection() {
        try {
            $this->getConnection();
            return true;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * دریافت اطلاعات اتصال فعلی (برای debug)
     */
    public function getConnectionInfo() {
        return [
            'host' => $this->host,
            'database' => $this->db_name,
            'username' => $this->username,
            'password' => '***'
        ];
    }
}