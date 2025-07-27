<?php
/**
 * Check for New Messages
 * بررسی نامه‌های جدید
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
    
    // دریافت آخرین زمان بررسی از session
    $last_check = $_SESSION['last_message_check'] ?? date('Y-m-d H:i:s', strtotime('-5 minutes'));
    
    // بررسی نامه‌های جدید از آخرین بررسی
    $new_messages = $db->fetchAll(
        "SELECT m.id, m.subject, m.priority, u.name as sender_name
         FROM messages m 
         JOIN users u ON m.sender_id = u.id 
         WHERE m.receiver_id = ? AND m.created_at > ? 
         ORDER BY m.created_at DESC",
        [$currentUser['id'], $last_check]
    );
    
    // تعداد کل نامه‌های خوانده نشده
    $unread_total = $db->fetchRow(
        "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND status = 'unread'",
        [$currentUser['id']]
    )['count'];
    
    // تعداد نامه‌های فوری خوانده نشده
    $urgent_unread = $db->fetchRow(
        "SELECT COUNT(*) as count FROM messages 
         WHERE receiver_id = ? AND status = 'unread' AND priority = 'urgent'",
        [$currentUser['id']]
    )['count'];
    
    // آمار امروز
    $today_stats = $db->fetchRow(
        "SELECT 
            COUNT(*) as received_today,
            SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread_today
         FROM messages 
         WHERE receiver_id = ? AND DATE(created_at) = CURDATE()",
        [$currentUser['id']]
    );
    
    // آخرین نامه دریافتی
    $latest_message = $db->fetchRow(
        "SELECT m.id, m.subject, m.created_at, u.name as sender_name
         FROM messages m 
         JOIN users u ON m.sender_id = u.id 
         WHERE m.receiver_id = ? 
         ORDER BY m.created_at DESC 
         LIMIT 1",
        [$currentUser['id']]
    );
    
    // به‌روزرسانی آخرین زمان بررسی
    $_SESSION['last_message_check'] = date('Y-m-d H:i:s');
    
    // آماده‌سازی پاسخ
    $response = [
        'success' => true,
        'new_count' => count($new_messages),
        'unread_total' => (int)$unread_total,
        'urgent_unread' => (int)$urgent_unread,
        'today_received' => (int)$today_stats['received_today'],
        'today_unread' => (int)$today_stats['unread_today'],
        'last_check' => $last_check,
        'current_time' => date('Y-m-d H:i:s'),
        'new_messages' => []
    ];
    
    // اضافه کردن جزئیات نامه‌های جدید
    foreach ($new_messages as $message) {
        $response['new_messages'][] = [
            'id' => $message['id'],
            'subject' => $message['subject'],
            'sender_name' => $message['sender_name'],
            'priority' => $message['priority'],
            'is_urgent' => $message['priority'] === 'urgent'
        ];
    }
    
    // اضافه کردن اطلاعات آخرین نامه
    if ($latest_message) {
        $response['latest_message'] = [
            'id' => $latest_message['id'],
            'subject' => $latest_message['subject'],
            'sender_name' => $latest_message['sender_name'],
            'created_at' => $latest_message['created_at']
        ];
    }
    
    // برای مدیران: آمار کلی
    if (Auth::isAdmin()) {
        $admin_stats = $db->fetchRow(
            "SELECT 
                COUNT(*) as total_system_messages,
                SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as total_unread,
                SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as total_today
             FROM messages"
        );
        
        $response['admin_stats'] = [
            'total_messages' => (int)$admin_stats['total_system_messages'],
            'total_unread' => (int)$admin_stats['total_unread'],
            'total_today' => (int)$admin_stats['total_today']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Error in check_new_messages.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در بررسی نامه‌های جدید'
    ]);
}