<?php
/**
 * Check for System Updates
 * بررسی به‌روزرسانی‌های سیستم
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';

header('Content-Type: application/json; charset=utf-8');

// بررسی ورود کاربر
if (!Auth::isLoggedIn()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'message' => 'عدم دسترسی']);
    exit;
}

try {
    $db = Database::getInstance();
    $currentUser = Auth::getCurrentUser();
    
    // بررسی نامه‌های خوانده نشده
    $unread_count = $db->fetchRow(
        "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND status = 'unread'",
        [$currentUser['id']]
    )['count'];
    
    // بررسی نامه‌های جدید (آخرین 5 دقیقه)
    $new_messages = $db->fetchRow(
        "SELECT COUNT(*) as count FROM messages 
         WHERE receiver_id = ? AND created_at > DATE_SUB(NOW(), INTERVAL 5 MINUTE)",
        [$currentUser['id']]
    )['count'];
    
    // آخرین فعالیت سیستم
    $last_activity = $db->fetchRow(
        "SELECT MAX(created_at) as last_time FROM messages WHERE receiver_id = ?",
        [$currentUser['id']]
    )['last_time'];
    
    // بررسی وضعیت سیستم
    $system_status = [
        'online' => true,
        'maintenance' => false,
        'last_backup' => date('Y-m-d H:i:s') // در پیاده‌سازی واقعی از پایگاه داده دریافت شود
    ];
    
    // برای مدیران: آمار کلی سیستم
    $admin_stats = [];
    if (Auth::isAdmin()) {
        $admin_stats = [
            'total_users' => $db->fetchRow("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'],
            'total_messages_today' => $db->fetchRow("SELECT COUNT(*) as count FROM messages WHERE DATE(created_at) = CURDATE()")['count'],
            'pending_messages' => $db->fetchRow("SELECT COUNT(*) as count FROM messages WHERE status = 'unread'")['count']
        ];
    }
    
    echo json_encode([
        'success' => true,
        'timestamp' => time(),
        'unread_count' => (int)$unread_count,
        'new_messages' => (int)$new_messages,
        'last_activity' => $last_activity,
        'system_status' => $system_status,
        'admin_stats' => $admin_stats
    ]);
    
} catch (Exception $e) {
    error_log("Error in check_updates.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت به‌روزرسانی‌ها'
    ]);
}