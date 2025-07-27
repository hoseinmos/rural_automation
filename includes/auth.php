<?php
/**
 * Authentication and Authorization Class - Complete Fixed Version
 * کلاس احراز هویت و مجوز دسترسی - نسخه کامل اصلاح شده
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

class Auth {
    private $db;
    private static $instance = null;
    private $rememberTokensEnabled = false;

    private function __construct() {
        $this->db = Database::getInstance();
        $this->checkRememberTokensTable();
    }

    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }

    /**
     * Check if remember_tokens table exists
     * بررسی وجود جدول remember_tokens
     */
    private function checkRememberTokensTable() {
        try {
            $this->db->query("SELECT 1 FROM remember_tokens LIMIT 1");
            $this->rememberTokensEnabled = true;
        } catch (Exception $e) {
            $this->rememberTokensEnabled = false;
            writeLog("Remember tokens table not found, feature disabled", 'WARNING');
        }
    }

    /**
     * Login user
     * ورود کاربر
     */
    public function login($username, $password, $remember = false) {
        try {
            // بررسی تعداد تلاش‌های ناموفق
            if ($this->isBlocked($username)) {
                throw new Exception("حساب کاربری به دلیل تلاش‌های ناموفق متوالی مسدود شده است");
            }

            // دریافت اطلاعات کاربر
            $user = $this->db->fetchRow(
                "SELECT id, username, password, name, role, status, last_login FROM users WHERE username = ?",
                [$username]
            );

            if (!$user) {
                $this->recordFailedLogin($username);
                throw new Exception("نام کاربری یا رمز عبور اشتباه است");
            }

            // بررسی وضعیت کاربر
            if ($user['status'] !== 'active') {
                throw new Exception("حساب کاربری غیرفعال است");
            }

            // بررسی رمز عبور
            if (!password_verify($password, $user['password'])) {
                $this->recordFailedLogin($username);
                throw new Exception("نام کاربری یا رمز عبور اشتباه است");
            }

            // پاک کردن تلاش‌های ناموفق
            $this->clearFailedLogins($username);

            // ایجاد نشست
            $this->createSession($user);

            // به‌روزرسانی آخرین ورود
            $this->updateLastLogin($user['id']);

            // Remember me functionality (فقط اگر جدول وجود دارد)
            if ($remember && $this->rememberTokensEnabled) {
                $this->setRememberToken($user['id']);
            }

            writeLog("User {$username} logged in successfully", 'INFO');
            return true;

        } catch (Exception $e) {
            writeLog("Login failed for {$username}: " . $e->getMessage(), 'WARNING');
            throw $e;
        }
    }

    /**
     * Logout user
     * خروج کاربر
     */
    public function logout() {
        $username = $_SESSION['username'] ?? 'unknown';
        
        // حذف remember token (فقط اگر قابلیت فعال باشد)
        if (isset($_COOKIE['remember_token']) && $this->rememberTokensEnabled) {
            try {
                $this->clearRememberToken($_COOKIE['remember_token']);
            } catch (Exception $e) {
                writeLog("Error clearing remember token: " . $e->getMessage(), 'WARNING');
            }
        }
        
        // پاک کردن کوکی
        if (isset($_COOKIE['remember_token'])) {
            setcookie('remember_token', '', time() - 3600, '/', '', false, true);
        }

        // پاک کردن نشست
        session_destroy();
        
        writeLog("User {$username} logged out", 'INFO');
    }

    /**
     * Check if user is logged in
     * بررسی ورود کاربر
     */
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']) && isset($_SESSION['username']);
    }

    /**
     * Require login
     * الزام ورود
     */
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            // بررسی remember token (فقط اگر کوکی وجود دارد)
            if (isset($_COOKIE['remember_token'])) {
                $auth = self::getInstance();
                if ($auth->rememberTokensEnabled && $auth->loginByRememberToken($_COOKIE['remember_token'])) {
                    return;
                }
            }
            
            header('Location: ' . getSiteUrl('index.php'));
            exit;
        }

        // بررسی timeout
        if (isset($_SESSION['last_activity']) && 
            (time() - $_SESSION['last_activity'] > SESSION_TIMEOUT)) {
            session_destroy();
            header('Location: ' . getSiteUrl('index.php?timeout=1'));
            exit;
        }

        $_SESSION['last_activity'] = time();
    }

    /**
     * Check user role
     * بررسی نقش کاربر
     */
    public static function hasRole($role) {
        return isset($_SESSION['role']) && $_SESSION['role'] === $role;
    }

    /**
     * Check if user is admin
     * بررسی مدیر بودن کاربر
     */
    public static function isAdmin() {
        return self::hasRole('admin');
    }

    /**
     * Get current user info
     * دریافت اطلاعات کاربر جاری
     */
    public static function getCurrentUser() {
        if (!self::isLoggedIn()) {
            return null;
        }

        return [
            'id' => $_SESSION['user_id'],
            'username' => $_SESSION['username'],
            'name' => $_SESSION['name'],
            'role' => $_SESSION['role']
        ];
    }

    /**
     * Create user session
     * ایجاد نشست کاربر
     */
    private function createSession($user) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['name'] = $user['name'];
        $_SESSION['role'] = $user['role'];
        $_SESSION['last_activity'] = time();
        $_SESSION['login_time'] = time();

        // تولید session token برای امنیت بیشتر
        $_SESSION['session_token'] = bin2hex(random_bytes(32));
    }

    /**
     * Record failed login attempt
     * ثبت تلاش ناموفق ورود
     */
    private function recordFailedLogin($username) {
        try {
            $this->db->query(
                "INSERT INTO login_attempts (username, ip_address, attempt_time) VALUES (?, ?, NOW())",
                [$username, $_SERVER['REMOTE_ADDR'] ?? 'unknown']
            );
        } catch (Exception $e) {
            writeLog("Error recording failed login: " . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Check if user is blocked
     * بررسی مسدود بودن کاربر
     */
    private function isBlocked($username) {
        try {
            $attempts = $this->db->fetchRow(
                "SELECT COUNT(*) as count FROM login_attempts 
                 WHERE username = ? AND attempt_time > DATE_SUB(NOW(), INTERVAL ? SECOND)",
                [$username, LOGIN_BLOCK_TIME]
            );

            return $attempts['count'] >= MAX_LOGIN_ATTEMPTS;
        } catch (Exception $e) {
            writeLog("Error checking blocked status: " . $e->getMessage(), 'WARNING');
            return false;
        }
    }

    /**
     * Clear failed login attempts
     * پاک کردن تلاش‌های ناموفق
     */
    private function clearFailedLogins($username) {
        try {
            $this->db->query(
                "DELETE FROM login_attempts WHERE username = ?",
                [$username]
            );
        } catch (Exception $e) {
            writeLog("Error clearing failed logins: " . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Update last login time
     * به‌روزرسانی آخرین ورود
     */
    private function updateLastLogin($userId) {
        try {
            $this->db->query(
                "UPDATE users SET last_login = NOW() WHERE id = ?",
                [$userId]
            );
        } catch (Exception $e) {
            writeLog("Error updating last login: " . $e->getMessage(), 'WARNING');
        }
    }

    /**
     * Set remember token
     * تنظیم remember token
     */
    private function setRememberToken($userId) {
        if (!$this->rememberTokensEnabled) {
            return false;
        }

        try {
            $token = bin2hex(random_bytes(32));
            $expires = date('Y-m-d H:i:s', time() + (30 * 24 * 60 * 60)); // 30 days

            // حذف توکن‌های قدیمی کاربر
            $this->db->query(
                "DELETE FROM remember_tokens WHERE user_id = ?",
                [$userId]
            );

            // اضافه کردن توکن جدید
            $this->db->query(
                "INSERT INTO remember_tokens (user_id, token, expires_at) VALUES (?, ?, ?)",
                [$userId, hash('sha256', $token), $expires]
            );

            setcookie('remember_token', $token, time() + (30 * 24 * 60 * 60), '/', '', false, true);
            return true;

        } catch (Exception $e) {
            writeLog("Error setting remember token: " . $e->getMessage(), 'WARNING');
            return false;
        }
    }

    /**
     * Login by remember token
     * ورود با remember token
     */
    private function loginByRememberToken($token) {
        if (!$this->rememberTokensEnabled) {
            return false;
        }

        try {
            $tokenHash = hash('sha256', $token);
            
            $result = $this->db->fetchRow(
                "SELECT u.* FROM users u 
                 JOIN remember_tokens rt ON u.id = rt.user_id 
                 WHERE rt.token = ? AND rt.expires_at > NOW() AND u.status = 'active'",
                [$tokenHash]
            );

            if ($result) {
                $this->createSession($result);
                $this->updateLastLogin($result['id']);
                
                // تمدید توکن
                $this->setRememberToken($result['id']);
                
                return true;
            }

            return false;

        } catch (Exception $e) {
            writeLog("Error login by remember token: " . $e->getMessage(), 'WARNING');
            return false;
        }
    }

    /**
     * Clear remember token
     * پاک کردن remember token
     */
    private function clearRememberToken($token) {
        if (!$this->rememberTokensEnabled) {
            return false;
        }

        try {
            $tokenHash = hash('sha256', $token);
            $this->db->query(
                "DELETE FROM remember_tokens WHERE token = ?",
                [$tokenHash]
            );
            return true;

        } catch (Exception $e) {
            writeLog("Error clearing remember token: " . $e->getMessage(), 'WARNING');
            return false;
        }
    }

    /**
     * Change password
     * تغییر رمز عبور
     */
    public function changePassword($userId, $currentPassword, $newPassword) {
        try {
            // دریافت رمز عبور فعلی
            $user = $this->db->fetchRow(
                "SELECT password FROM users WHERE id = ?",
                [$userId]
            );

            if (!$user || !password_verify($currentPassword, $user['password'])) {
                throw new Exception("رمز عبور فعلی اشتباه است");
            }

            // تغییر رمز عبور
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            $this->db->query(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                [$hashedPassword, $userId]
            );

            // پاک کردن تمام remember token های کاربر
            if ($this->rememberTokensEnabled) {
                $this->db->query(
                    "DELETE FROM remember_tokens WHERE user_id = ?",
                    [$userId]
                );
            }

            writeLog("Password changed for user ID: {$userId}", 'INFO');
            return true;

        } catch (Exception $e) {
            writeLog("Error changing password: " . $e->getMessage(), 'WARNING');
            throw $e;
        }
    }

    /**
     * Reset password
     * بازنشانی رمز عبور
     */
    public function resetPassword($username, $newPassword) {
        try {
            $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
            
            $affected = $this->db->execute(
                "UPDATE users SET password = ?, updated_at = NOW() WHERE username = ?",
                [$hashedPassword, $username]
            );

            if ($affected > 0) {
                // پاک کردن تمام remember token های کاربر
                if ($this->rememberTokensEnabled) {
                    $user = $this->db->fetchRow("SELECT id FROM users WHERE username = ?", [$username]);
                    if ($user) {
                        $this->db->query(
                            "DELETE FROM remember_tokens WHERE user_id = ?",
                            [$user['id']]
                        );
                    }
                }

                writeLog("Password reset for user: {$username}", 'INFO');
                return true;
            }

            return false;

        } catch (Exception $e) {
            writeLog("Error resetting password: " . $e->getMessage(), 'WARNING');
            throw $e;
        }
    }

    /**
     * Clean expired remember tokens
     * پاک کردن توکن‌های منقضی شده
     */
    public function cleanExpiredTokens() {
        if (!$this->rememberTokensEnabled) {
            return false;
        }

        try {
            $affected = $this->db->execute(
                "DELETE FROM remember_tokens WHERE expires_at < NOW()"
            );

            if ($affected > 0) {
                writeLog("Cleaned {$affected} expired remember tokens", 'INFO');
            }

            return $affected;

        } catch (Exception $e) {
            writeLog("Error cleaning expired tokens: " . $e->getMessage(), 'WARNING');
            return false;
        }
    }
}