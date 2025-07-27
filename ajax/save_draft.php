<?php
/**
 * Save Draft Message
 * ذخیره پیش‌نویس نامه
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/security.php';

header('Content-Type: application/json; charset=utf-8');

// بررسی ورود کاربر
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم دسترسی']);
    exit;
}

// بررسی متد درخواست
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'متد درخواست نامعتبر']);
    exit;
}

try {
    $db = Database::getInstance();
    $currentUser = Auth::getCurrentUser();
    
    // دریافت داده‌ها
    $subject = Security::sanitize($_POST['subject'] ?? '');
    $content = Security::sanitize($_POST['content'] ?? '');
    $receiver_id = (int)($_POST['receiver_id'] ?? 0);
    $message_number = Security::sanitize($_POST['message_number'] ?? '');
    $priority = $_POST['priority'] ?? 'normal';
    $reply_to = $_POST['reply_to'] ? (int)$_POST['reply_to'] : null;
    
    // اعتبارسنجی اولیه
    if (empty($subject) && empty($content)) {
        echo json_encode(['success' => false, 'message' => 'حداقل موضوع یا متن نامه باید وارد شود']);
        exit;
    }
    
    // بررسی وجود پیش‌نویس قبلی
    $existing_draft = $db->fetchRow(
        "SELECT id FROM drafts WHERE user_id = ? AND reply_to = ? ORDER BY updated_at DESC LIMIT 1",
        [$currentUser['id'], $reply_to]
    );
    
    if ($existing_draft) {
        // به‌روزرسانی پیش‌نویس موجود
        $affected = $db->execute(
            "UPDATE drafts SET 
                subject = ?, 
                content = ?, 
                receiver_id = ?, 
                message_number = ?, 
                priority = ?,
                updated_at = NOW()
             WHERE id = ?",
            [$subject, $content, $receiver_id, $message_number, $priority, $existing_draft['id']]
        );
        
        if ($affected > 0) {
            echo json_encode([
                'success' => true,
                'message' => 'پیش‌نویس به‌روزرسانی شد',
                'draft_id' => $existing_draft['id']
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در به‌روزرسانی پیش‌نویس']);
        }
    } else {
        // ایجاد پیش‌نویس جدید
        
        // ابتدا بررسی کنیم که جدول drafts وجود دارد یا نه
        try {
            $db->query("DESCRIBE drafts");
        } catch (Exception $e) {
            // ایجاد جدول در صورت عدم وجود
            $db->query("
                CREATE TABLE IF NOT EXISTS drafts (
                    id INT AUTO_INCREMENT PRIMARY KEY,
                    user_id INT NOT NULL,
                    subject VARCHAR(255) DEFAULT '',
                    content TEXT DEFAULT '',
                    receiver_id INT DEFAULT NULL,
                    message_number VARCHAR(50) DEFAULT '',
                    priority ENUM('low','normal','high','urgent') DEFAULT 'normal',
                    reply_to INT DEFAULT NULL,
                    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
                    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                    FOREIGN KEY (receiver_id) REFERENCES users(id) ON DELETE SET NULL,
                    FOREIGN KEY (reply_to) REFERENCES messages(id) ON DELETE SET NULL
                ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
            ");
        }
        
        $draft_id = $db->insert(
            "INSERT INTO drafts (user_id, subject, content, receiver_id, message_number, priority, reply_to) 
             VALUES (?, ?, ?, ?, ?, ?, ?)",
            [$currentUser['id'], $subject, $content, $receiver_id, $message_number, $priority, $reply_to]
        );
        
        if ($draft_id) {
            echo json_encode([
                'success' => true,
                'message' => 'پیش‌نویس ذخیره شد',
                'draft_id' => $draft_id
            ]);
        } else {
            echo json_encode(['success' => false, 'message' => 'خطا در ذخیره پیش‌نویس']);
        }
    }
    
} catch (Exception $e) {
    error_log("Error in save_draft.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در ذخیره پیش‌نویس: ' . $e->getMessage()
    ]);
}