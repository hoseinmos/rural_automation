<?php
/**
 * Get Message Statistics
 * دریافت آمار نامه
 */

require_once '../config/config.php';
require_once '../config/database.php';
require_once '../includes/auth.php';
require_once '../includes/jalali.php';

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
    $messageId = (int)($_GET['id'] ?? 0);
    
    if (!$messageId) {
        echo json_encode(['success' => false, 'message' => 'شناسه نامه نامعتبر']);
        exit;
    }
    
    // بررسی دسترسی به نامه
    $message = $db->fetchRow(
        "SELECT * FROM messages WHERE id = ? AND (sender_id = ? OR receiver_id = ?)",
        [$messageId, $currentUser['id'], $currentUser['id']]
    );
    
    if (!$message) {
        echo json_encode(['success' => false, 'message' => 'نامه یافت نشد']);
        exit;
    }
    
    // دریافت آمار پایه
    $stats = [
        'message_id' => $messageId,
        'sent_date' => JalaliDate::toJalali(strtotime($message['created_at']), 'Y/m/d H:i'),
        'status' => $message['status'],
        'priority' => $message['priority'],
        'has_attachment' => !empty($message['attachment']),
        'views' => 0, // در پیاده‌سازی واقعی از جدول مجزا دریافت شود
        'replies' => 0
    ];
    
    // تاریخ خواندن (اگر وضعیت read یا replied باشد)
    if (in_array($message['status'], ['read', 'replied']) && $message['updated_at'] > $message['created_at']) {
        $stats['read_date'] = JalaliDate::toJalali(strtotime($message['updated_at']), 'Y/m/d H:i');
        
        // محاسبه زمان پاسخ (بر حسب ساعت)
        $response_time = (strtotime($message['updated_at']) - strtotime($message['created_at'])) / 3600;
        $stats['response_time_hours'] = round($response_time, 1);
    }
    
    // شمارش پاسخ‌ها
    $replies_count = $db->fetchRow(
        "SELECT COUNT(*) as count FROM messages WHERE reply_to = ?",
        [$messageId]
    )['count'];
    
    $stats['replies'] = (int)$replies_count;
    
    // دریافت اطلاعات پاسخ‌ها
    if ($replies_count > 0) {
        $replies = $db->fetchAll(
            "SELECT m.id, m.subject, m.created_at, m.status, u.name as sender_name
             FROM messages m 
             JOIN users u ON m.sender_id = u.id 
             WHERE m.reply_to = ? 
             ORDER BY m.created_at ASC",
            [$messageId]
        );
        
        $stats['reply_list'] = [];
        foreach ($replies as $reply) {
            $stats['reply_list'][] = [
                'id' => $reply['id'],
                'subject' => $reply['subject'],
                'sender_name' => $reply['sender_name'],
                'created_at' => JalaliDate::toJalali(strtotime($reply['created_at']), 'Y/m/d H:i'),
                'status' => $reply['status']
            ];
        }
        
        // تاریخ اولین پاسخ
        $first_reply = $replies[0];
        $stats['first_reply_date'] = JalaliDate::toJalali(strtotime($first_reply['created_at']), 'Y/m/d H:i');
        
        // تاریخ آخرین پاسخ
        $last_reply = end($replies);
        $stats['last_reply_date'] = JalaliDate::toJalali(strtotime($last_reply['created_at']), 'Y/m/d H:i');
    }
    
    // اطلاعات فرستنده و گیرنده
    $participants = $db->fetchRow(
        "SELECT 
            s.name as sender_name, s.username as sender_username,
            r.name as receiver_name, r.username as receiver_username
         FROM messages m
         JOIN users s ON m.sender_id = s.id
         JOIN users r ON m.receiver_id = r.id
         WHERE m.id = ?",
        [$messageId]
    );
    
    $stats['sender'] = [
        'name' => $participants['sender_name'],
        'username' => $participants['sender_username']
    ];
    
    $stats['receiver'] = [
        'name' => $participants['receiver_name'],
        'username' => $participants['receiver_username']
    ];
    
    // اطلاعات نامه اصلی (اگر این نامه پاسخی باشد)
    if ($message['reply_to']) {
        $original_message = $db->fetchRow(
            "SELECT m.subject, u.name as sender_name FROM messages m 
             JOIN users u ON m.sender_id = u.id 
             WHERE m.id = ?",
            [$message['reply_to']]
        );
        
        if ($original_message) {
            $stats['original_message'] = [
                'id' => $message['reply_to'],
                'subject' => $original_message['subject'],
                'sender_name' => $original_message['sender_name']
            ];
        }
    }
    
    // محاسبه آمار عملکرد
    $performance_stats = [];
    
    // اگر فرستنده نامه کاربر جاری است
    if ($message['sender_id'] == $currentUser['id']) {
        // میانگین زمان پاسخ نامه‌های این کاربر
        $avg_response = $db->fetchRow(
            "SELECT AVG(TIMESTAMPDIFF(HOUR, m.created_at, m.updated_at)) as avg_hours
             FROM messages m 
             WHERE m.sender_id = ? AND m.status IN ('read', 'replied') AND m.updated_at > m.created_at",
            [$currentUser['id']]
        );
        
        $performance_stats['avg_response_time'] = $avg_response['avg_hours'] ? round($avg_response['avg_hours'], 1) : null;
        
        // درصد نامه‌های پاسخ داده شده
        $response_rate = $db->fetchRow(
            "SELECT 
                COUNT(*) as total,
                SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied
             FROM messages 
             WHERE sender_id = ?",
            [$currentUser['id']]
        );
        
        if ($response_rate['total'] > 0) {
            $performance_stats['response_rate'] = round(($response_rate['replied'] / $response_rate['total']) * 100, 1);
        }
    }
    
    $stats['performance'] = $performance_stats;
    
    // آمار امنیتی (فقط برای مدیران)
    if (Auth::isAdmin()) {
        $security_stats = [
            'ip_address' => $_SERVER['REMOTE_ADDR'] ?? 'نامشخص',
            'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'نامشخص',
            'accessed_at' => JalaliDate::nowWithTime(),
            'access_count' => 1 // در پیاده‌سازی واقعی از جدول لاگ دریافت شود
        ];
        
        $stats['security'] = $security_stats;
    }
    
    echo json_encode([
        'success' => true,
        'stats' => $stats
    ]);
    
} catch (Exception $e) {
    error_log("Error in message_stats.php: " . $e->getMessage());
    
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'خطا در دریافت آمار نامه'
    ]);
}