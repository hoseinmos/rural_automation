<?php
/**
 * Message Permissions Handler
 * مدیریت مجوزهای پیام‌رسانی
 */

class MessagePermissions {
    
    /**
     * بررسی مجوز ارسال پیام بین دو نقش
     */
    public static function canSendMessage($senderRole, $receiverRole) {
        // مدیران می‌توانند به همه پیام بدهند
        if ($senderRole === 'admin') {
            return true;
        }
        
        // کاربران فقط می‌توانند به مدیر پیام بدهند
        if (MESSAGING_RULES['users_only_to_admin']) {
            return $receiverRole === 'admin';
        }
        
        // اگر ارسال بین کاربران فعال باشد
        if (MESSAGING_RULES['allow_user_to_user']) {
            return true;
        }
        
        return false;
    }
    
    /**
     * دریافت لیست گیرندگان مجاز برای یک کاربر
     */
    public static function getAvailableRecipients($currentUser, $db) {
        $users = [];
        
        if ($currentUser['role'] === 'admin') {
            // مدیران می‌توانند به همه ارسال کنند
            $users = $db->fetchAll(
                "SELECT id, name, username, role FROM users 
                 WHERE id != ? AND status = 'active' 
                 ORDER BY role = 'admin' DESC, name",
                [$currentUser['id']]
            );
        } else {
            // کاربران عادی فقط به مدیران
            $users = $db->fetchAll(
                "SELECT id, name, username, role FROM users 
                 WHERE role = 'admin' AND status = 'active' 
                 ORDER BY name",
                []
            );
        }
        
        return $users;
    }
    
    /**
     * بررسی مجوز پاسخ به پیام
     */
    public static function canReplyToMessage($userRole, $originalSenderRole, $userId, $originalSenderId) {
        // مدیران همیشه می‌توانند پاسخ دهند
        if ($userRole === 'admin') {
            return true;
        }
        
        // کاربران فقط می‌توانند به نامه‌های مدیر پاسخ دهند
        return $originalSenderRole === 'admin';
    }
    
    /**
     * اعتبارسنجی ارسال پیام
     */
    public static function validateMessageSubmission($currentUser, $receiverId, $db) {
        $errors = [];
        
        // بررسی وجود گیرنده
        $receiver = $db->fetchRow(
            "SELECT id, role, status FROM users WHERE id = ?",
            [$receiverId]
        );
        
        if (!$receiver) {
            $errors[] = 'گیرنده انتخابی معتبر نیست';
            return $errors;
        }
        
        if ($receiver['status'] !== 'active') {
            $errors[] = 'گیرنده انتخابی غیرفعال است';
            return $errors;
        }
        
        // بررسی مجوز ارسال
        if (!self::canSendMessage($currentUser['role'], $receiver['role'])) {
            if ($currentUser['role'] !== 'admin' && $receiver['role'] !== 'admin') {
                $errors[] = 'شما فقط می‌توانید به مدیر سیستم نامه ارسال کنید';
            } else {
                $errors[] = 'شما مجوز ارسال نامه به این کاربر را ندارید';
            }
        }
        
        return $errors;
    }
    
    /**
     * دریافت پیام محدودیت برای نقش
     */
    public static function getRestrictionMessage($role) {
        switch ($role) {
            case 'admin':
                return 'شما می‌توانید به همه کاربران نامه ارسال کنید';
            case 'user':
                return 'شما فقط می‌توانید به مدیر سیستم نامه ارسال کنید';
            default:
                return 'محدودیت‌های ارسال نامه را رعایت کنید';
        }
    }
    
    /**
     * دریافت اطلاعات پیام‌رسانی کاربر
     */
    public static function getMessagingInfo($currentUser) {
        $info = [
            'can_send_to_all' => false,
            'can_receive_from_all' => false,
            'recipients' => '',
            'message' => ''
        ];
        
        if ($currentUser['role'] === 'admin') {
            $info['can_send_to_all'] = true;
            $info['can_receive_from_all'] = true;
            $info['recipients'] = 'همه کاربران';
            $info['message'] = 'شما می‌توانید به همه کاربران نامه ارسال کنید';
        } else {
            $info['recipients'] = 'فقط مدیران سیستم';
            $info['message'] = 'شما فقط می‌توانید به مدیر سیستم نامه ارسال کنید';
        }
        
        return $info;
    }
    
    /**
     * دریافت آمار پیام‌رسانی کاربر
     */
    public static function getMessagingStats($currentUser, $db) {
        $stats = [
            'sent_count' => 0,
            'received_count' => 0,
            'unread_count' => 0,
            'read_sent_count' => 0
        ];
        
        // تعداد پیام‌های ارسالی
        $stats['sent_count'] = $db->fetchRow(
            "SELECT COUNT(*) as count FROM messages WHERE sender_id = ?",
            [$currentUser['id']]
        )['count'];
        
        // تعداد پیام‌های دریافتی
        $stats['received_count'] = $db->fetchRow(
            "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ?",
            [$currentUser['id']]
        )['count'];
        
        // تعداد پیام‌های خوانده نشده
        $stats['unread_count'] = $db->fetchRow(
            "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND status = 'unread'",
            [$currentUser['id']]
        )['count'];
        
        // تعداد پیام‌های ارسالی که خوانده شده
        $stats['read_sent_count'] = $db->fetchRow(
            "SELECT COUNT(*) as count FROM messages WHERE sender_id = ? AND status IN ('read', 'replied')",
            [$currentUser['id']]
        )['count'];
        
        return $stats;
    }
    
    /**
     * دریافت رنگ نقش کاربر
     */
    public static function getRoleColor($role) {
        $colors = [
            'admin' => 'danger',
            'manager' => 'warning',
            'supervisor' => 'info',
            'user' => 'primary'
        ];
        
        return $colors[$role] ?? 'secondary';
    }
    
    /**
     * دریافت آیکون نقش کاربر
     */
    public static function getRoleIcon($role) {
        $icons = [
            'admin' => 'fas fa-crown',
            'manager' => 'fas fa-user-tie',
            'supervisor' => 'fas fa-user-shield',
            'user' => 'fas fa-user'
        ];
        
        return $icons[$role] ?? 'fas fa-user';
    }
}
?>