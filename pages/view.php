<?php
/**
 * Message View Page
 * صفحه نمایش جزئیات نامه
 */

Auth::requireLogin();

$messageId = (int)($_GET['id'] ?? 0);

if (!$messageId) {
    header('Location: ?page=inbox');
    exit;
}

// دریافت جزئیات نامه
$message = $db->fetchRow(
    "SELECT m.*, 
            s.name as sender_name, s.username as sender_username, s.role as sender_role,
            r.name as receiver_name, r.username as receiver_username, r.role as receiver_role
     FROM messages m 
     JOIN users s ON m.sender_id = s.id 
     JOIN users r ON m.receiver_id = r.id 
     WHERE m.id = ? AND (m.sender_id = ? OR m.receiver_id = ?)",
    [$messageId, $currentUser['id'], $currentUser['id']]
);

if (!$message) {
    header('Location: ?page=inbox&error=message_not_found');
    exit;
}

// علامت‌گذاری به عنوان خوانده شده (فقط برای گیرنده)
if ($message['receiver_id'] == $currentUser['id'] && $message['status'] === 'unread') {
    $db->execute(
        "UPDATE messages SET status = 'read', updated_at = NOW() WHERE id = ?",
        [$messageId]
    );
    $message['status'] = 'read';
}

// دریافت نامه‌های مرتبط (پاسخ‌ها)
$replies = $db->fetchAll(
    "SELECT m.*, u.name as sender_name 
     FROM messages m 
     JOIN users u ON m.sender_id = u.id 
     WHERE m.reply_to = ? 
     ORDER BY m.created_at ASC",
    [$messageId]
);

// تعیین نوع نمایش (دریافتی یا ارسالی)
$isReceived = ($message['receiver_id'] == $currentUser['id']);
$isReplied = !empty($replies);

// پردازش عملیات
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('درخواست نامعتبر است');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'archive':
                if ($isReceived) {
                    $db->execute(
                        "UPDATE messages SET status = 'archived' WHERE id = ?",
                        [$messageId]
                    );
                    $message['status'] = 'archived';
                    $success = 'نامه آرشیو شد';
                }
                break;
                
            case 'unarchive':
                if ($isReceived) {
                    $db->execute(
                        "UPDATE messages SET status = 'read' WHERE id = ?",
                        [$messageId]
                    );
                    $message['status'] = 'read';
                    $success = 'نامه از آرشیو خارج شد';
                }
                break;
                
            case 'mark_unread':
                if ($isReceived) {
                    $db->execute(
                        "UPDATE messages SET status = 'unread' WHERE id = ?",
                        [$messageId]
                    );
                    $message['status'] = 'unread';
                    $success = 'نامه به عنوان خوانده نشده علامت‌گذاری شد';
                }
                break;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// محاسبه نامه قبلی و بعدی
$navigation = $db->fetchRow(
    "SELECT 
        (SELECT id FROM messages WHERE receiver_id = ? AND id < ? ORDER BY id DESC LIMIT 1) as prev_id,
        (SELECT id FROM messages WHERE receiver_id = ? AND id > ? ORDER BY id ASC LIMIT 1) as next_id",
    [$currentUser['id'], $messageId, $currentUser['id'], $messageId]
);
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-8">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item">
                        <a href="?page=<?= $isReceived ? 'inbox' : 'sent' ?>">
                            <i class="fas fa-<?= $isReceived ? 'inbox' : 'paper-plane' ?>"></i>
                            <?= $isReceived ? 'صندوق دریافت' : 'نامه‌های ارسالی' ?>
                        </a>
                    </li>
                    <li class="breadcrumb-item active"><?= htmlspecialchars($message['subject']) ?></li>
                </ol>
            </nav>
        </div>
        <div class="col-md-4 text-end">
            <!-- ناوبری نامه‌ها -->
            <div class="btn-group me-2">
                <?php if ($navigation['prev_id']): ?>
                    <a href="?page=view&id=<?= $navigation['prev_id'] ?>" class="btn btn-outline-secondary" title="نامه قبلی">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                <?php endif; ?>
                <?php if ($navigation['next_id']): ?>
                    <a href="?page=view&id=<?= $navigation['next_id'] ?>" class="btn btn-outline-secondary" title="نامه بعدی">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                <?php endif; ?>
            </div>
            
            <a href="?page=<?= $isReceived ? 'inbox' : 'sent' ?>" class="btn btn-outline-primary">
                <i class="fas fa-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>
</div>

<!-- پیام‌های سیستم -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= $success ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if ($error): ?>
    <div class="alert alert-danger alert-dismissible fade show">
        <i class="fas fa-exclamation-triangle"></i> <?= $error ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-9">
        <!-- جزئیات نامه اصلی -->
        <div class="card message-card">
            <div class="card-header">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h4 class="mb-1">
                            <?php if ($message['priority'] === 'urgent'): ?>
                                <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                            <?php elseif ($message['priority'] === 'high'): ?>
                                <i class="fas fa-exclamation-circle text-warning me-2"></i>
                            <?php endif; ?>
                            <?= htmlspecialchars($message['subject']) ?>
                        </h4>
                        <?php if ($message['message_number']): ?>
                            <p class="text-muted mb-0">
                                <i class="fas fa-hashtag"></i> شماره نامه: 
                                <strong><?= htmlspecialchars($message['message_number']) ?></strong>
                            </p>
                        <?php endif; ?>
                    </div>
                    <div class="col-md-4 text-end">
                        <span class="badge bg-<?= 
                            $message['status'] === 'unread' ? 'warning' : 
                            ($message['status'] === 'read' ? 'success' : 
                            ($message['status'] === 'replied' ? 'info' : 'secondary'))
                        ?> fs-6">
                            <?= MESSAGE_STATUSES[$message['status']] ?>
                        </span>
                    </div>
                </div>
            </div>
            
            <div class="card-body">
                <!-- اطلاعات فرستنده و گیرنده -->
                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                <?= mb_substr($message['sender_name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <div>
                                <strong>از:</strong> <?= htmlspecialchars($message['sender_name']) ?><br>
                                <small class="text-muted">
                                    @<?= htmlspecialchars($message['sender_username']) ?> 
                                    | <?= USER_ROLES[$message['sender_role']] ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="d-flex align-items-center">
                            <div class="avatar bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-3">
                                <?= mb_substr($message['receiver_name'], 0, 1, 'UTF-8') ?>
                            </div>
                            <div>
                                <strong>به:</strong> <?= htmlspecialchars($message['receiver_name']) ?><br>
                                <small class="text-muted">
                                    @<?= htmlspecialchars($message['receiver_username']) ?> 
                                    | <?= USER_ROLES[$message['receiver_role']] ?>
                                </small>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- تاریخ و زمان -->
                <div class="message-meta mb-4 p-3 bg-light rounded">
                    <div class="row">
                        <div class="col-md-6">
                            <i class="fas fa-calendar-alt text-primary"></i>
                            <strong>تاریخ ارسال:</strong> 
                            <?= JalaliDate::toJalali(strtotime($message['created_at']), 'l، d F Y') ?>
                        </div>
                        <div class="col-md-6">
                            <i class="fas fa-clock text-primary"></i>
                            <strong>ساعت:</strong> 
                            <?= JalaliDate::toJalali(strtotime($message['created_at']), 'H:i') ?>
                            <small class="text-muted">
                                (<?= JalaliDate::timeAgo(strtotime($message['created_at'])) ?>)
                            </small>
                        </div>
                    </div>
                </div>
                
                <!-- فایل ضمیمه -->
                <?php if ($message['attachment']): ?>
                    <div class="attachment-section mb-4">
                        <h6><i class="fas fa-paperclip text-primary"></i> فایل ضمیمه:</h6>
                        <div class="attachment-card p-3 border rounded">
                            <?php
                            $filePath = UPLOAD_PATH . $message['attachment'];
                            $fileExists = file_exists($filePath);
                            $fileSize = $fileExists ? filesize($filePath) : 0;
                            $fileExtension = strtolower(pathinfo($message['attachment'], PATHINFO_EXTENSION));
                            
                            $fileIcons = [
                                'pdf' => 'fas fa-file-pdf text-danger',
                                'doc' => 'fas fa-file-word text-primary',
                                'docx' => 'fas fa-file-word text-primary',
                                'jpg' => 'fas fa-file-image text-success',
                                'jpeg' => 'fas fa-file-image text-success',
                                'png' => 'fas fa-file-image text-success',
                                'mp4' => 'fas fa-file-video text-warning',
                                'avi' => 'fas fa-file-video text-warning'
                            ];
                            
                            $fileIcon = $fileIcons[$fileExtension] ?? 'fas fa-file text-secondary';
                            ?>
                            
                            <div class="d-flex align-items-center justify-content-between">
                                <div class="d-flex align-items-center">
                                    <i class="<?= $fileIcon ?> fa-2x me-3"></i>
                                    <div>
                                        <div class="fw-bold"><?= htmlspecialchars(basename($message['attachment'])) ?></div>
                                        <small class="text-muted">
                                            <?= $fileExists ? formatFileSize($fileSize) : 'فایل موجود نیست' ?>
                                        </small>
                                    </div>
                                </div>
                                <div>
                                    <?php if ($fileExists): ?>
                                        <a href="<?= UPLOAD_URL . $message['attachment'] ?>" 
                                           class="btn btn-primary btn-sm" target="_blank">
                                            <i class="fas fa-download"></i> دانلود
                                        </a>
                                        <?php if (in_array($fileExtension, ['jpg', 'jpeg', 'png'])): ?>
                                            <button type="button" class="btn btn-outline-info btn-sm" 
                                                    onclick="showImagePreview('<?= UPLOAD_URL . $message['attachment'] ?>')">
                                                <i class="fas fa-eye"></i> پیش‌نمایش
                                            </button>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-danger">فایل موجود نیست</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
                
                <!-- متن نامه -->
                <div class="message-content">
                    <h6><i class="fas fa-align-right text-primary"></i> متن نامه:</h6>
                    <div class="content-body p-4 border rounded bg-white">
                        <?= nl2br(htmlspecialchars($message['content'])) ?>
                    </div>
                </div>
            </div>
            
            <!-- عملیات -->
            <div class="card-footer">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <div class="btn-group">
                            <?php if ($isReceived): ?>
                                <a href="?page=compose&reply=<?= $message['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-reply"></i> پاسخ
                                </a>
                                <a href="?page=compose&forward=<?= $message['id'] ?>" class="btn btn-outline-secondary">
                                    <i class="fas fa-share"></i> هدایت
                                </a>
                            <?php endif; ?>
                            
                            <?php if ($message['attachment']): ?>
                                <a href="<?= UPLOAD_URL . $message['attachment'] ?>" 
                                   class="btn btn-outline-success" target="_blank">
                                    <i class="fas fa-download"></i> دانلود ضمیمه
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div class="col-md-4 text-end">
                        <?php if ($isReceived): ?>
                            <form method="POST" class="d-inline">
                                <?= Security::csrfField() ?>
                                <?php if ($message['status'] === 'archived'): ?>
                                    <button type="submit" name="action" value="unarchive" 
                                            class="btn btn-outline-warning btn-sm">
                                        <i class="fas fa-archive"></i> خروج از آرشیو
                                    </button>
                                <?php else: ?>
                                    <button type="submit" name="action" value="archive" 
                                            class="btn btn-outline-secondary btn-sm">
                                        <i class="fas fa-archive"></i> آرشیو
                                    </button>
                                    <?php if ($message['status'] !== 'unread'): ?>
                                        <button type="submit" name="action" value="mark_unread" 
                                                class="btn btn-outline-warning btn-sm">
                                            <i class="fas fa-envelope"></i> علامت‌گذاری خوانده نشده
                                        </button>
                                    <?php endif; ?>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- نمایش پاسخ‌ها -->
        <?php if (!empty($replies)): ?>
            <div class="mt-4">
                <h5><i class="fas fa-comments text-primary"></i> پاسخ‌ها (<?= count($replies) ?>)</h5>
                
                <?php foreach ($replies as $reply): ?>
                    <div class="card reply-card mt-3">
                        <div class="card-header bg-light">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <strong><?= htmlspecialchars($reply['sender_name']) ?></strong>
                                    <small class="text-muted">
                                        <?= JalaliDate::toJalali(strtotime($reply['created_at']), 'Y/m/d H:i') ?>
                                    </small>
                                </div>
                                <a href="?page=view&id=<?= $reply['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    مشاهده کامل
                                </a>
                            </div>
                        </div>
                        <div class="card-body">
                            <h6><?= htmlspecialchars($reply['subject']) ?></h6>
                            <p class="mb-0"><?= nl2br(htmlspecialchars(substr($reply['content'], 0, 200))) ?>...</p>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- اطلاعات جانبی -->
    <div class="col-lg-3">
        <!-- جزئیات فنی -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle text-info"></i> جزئیات فنی
                </h6>
            </div>
            <div class="card-body">
                <table class="table table-sm table-borderless">
                    <tr>
                        <td><strong>شناسه:</strong></td>
                        <td><?= $message['id'] ?></td>
                    </tr>
                    <tr>
                        <td><strong>اولویت:</strong></td>
                        <td>
                            <span class="badge bg-<?= 
                                $message['priority'] === 'urgent' ? 'danger' : 
                                ($message['priority'] === 'high' ? 'warning' : 
                                ($message['priority'] === 'normal' ? 'secondary' : 'info'))
                            ?>">
                                <?= ['urgent' => 'فوری', 'high' => 'زیاد', 'normal' => 'عادی', 'low' => 'کم'][$message['priority']] ?>
                            </span>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>وضعیت:</strong></td>
                        <td><?= MESSAGE_STATUSES[$message['status']] ?></td>
                    </tr>
                    <?php if ($message['reply_to']): ?>
                        <tr>
                            <td><strong>پاسخ به:</strong></td>
                            <td>
                                <a href="?page=view&id=<?= $message['reply_to'] ?>" class="text-decoration-none">
                                    نامه #<?= $message['reply_to'] ?>
                                </a>
                            </td>
                        </tr>
                    <?php endif; ?>
                    <tr>
                        <td><strong>ایجاد:</strong></td>
                        <td>
                            <small><?= JalaliDate::toJalali(strtotime($message['created_at']), 'Y/m/d H:i:s') ?></small>
                        </td>
                    </tr>
                    <?php if ($message['updated_at'] && $message['updated_at'] !== $message['created_at']): ?>
                        <tr>
                            <td><strong>آخرین تغییر:</strong></td>
                            <td>
                                <small><?= JalaliDate::toJalali(strtotime($message['updated_at']), 'Y/m/d H:i:s') ?></small>
                            </td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
        
        <!-- نامه‌های مرتبط -->
        <?php
        $relatedMessages = $db->fetchAll(
            "SELECT id, subject, created_at FROM messages 
             WHERE (sender_id = ? OR receiver_id = ?) 
             AND (sender_id = ? OR receiver_id = ?) 
             AND id != ? 
             ORDER BY created_at DESC 
             LIMIT 5",
            [
                $message['sender_id'], $message['sender_id'],
                $message['receiver_id'], $message['receiver_id'],
                $messageId
            ]
        );
        ?>
        
        <?php if (!empty($relatedMessages)): ?>
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-link text-primary"></i> نامه‌های مرتبط
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($relatedMessages as $related): ?>
                            <a href="?page=view&id=<?= $related['id'] ?>" 
                               class="list-group-item list-group-item-action">
                                <div class="fw-bold"><?= htmlspecialchars(substr($related['subject'], 0, 30)) ?>...</div>
                                <small class="text-muted">
                                    <?= JalaliDate::toJalali(strtotime($related['created_at']), 'Y/m/d') ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- عملیات سریع -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt text-warning"></i> عملیات سریع
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <?php if ($isReceived): ?>
                        <a href="?page=compose&reply=<?= $message['id'] ?>" class="btn btn-primary btn-sm">
                            <i class="fas fa-reply"></i> پاسخ سریع
                        </a>
                    <?php endif; ?>
                    <button onclick="printMessage()" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-print"></i> چاپ
                    </button>
                    <button onclick="shareMessage()" class="btn btn-outline-info btn-sm">
                        <i class="fas fa-share-alt"></i> اشتراک‌گذاری
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال پیش‌نمایش تصویر -->
<div class="modal fade" id="imagePreviewModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">پیش‌نمایش تصویر</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="previewImage" class="img-fluid" alt="Preview">
            </div>
        </div>
    </div>
</div>

<script>
// پیش‌نمایش تصویر
function showImagePreview(imageUrl) {
    document.getElementById('previewImage').src = imageUrl;
    new bootstrap.Modal(document.getElementById('imagePreviewModal')).show();
}

// چاپ نامه
function printMessage() {
    window.print();
}

// اشتراک‌گذاری نامه
function shareMessage() {
    if (navigator.share) {
        navigator.share({
            title: '<?= htmlspecialchars($message['subject']) ?>',
            text: 'نامه از سیستم اتوماسیون دهیاری',
            url: window.location.href
        });
    } else {
        // کپی لینک
        navigator.clipboard.writeText(window.location.href).then(() => {
            alert('لینک نامه کپی شد');
        });
    }
}

// میانبرهای کیبورد
document.addEventListener('keydown', function(e) {
    // R = پاسخ
    if (e.key === 'r' && !e.ctrlKey && !e.altKey) {
        <?php if ($isReceived): ?>
            window.location.href = '?page=compose&reply=<?= $message['id'] ?>';
        <?php endif; ?>
    }
    
    // P = چاپ
    if (e.key === 'p' && e.ctrlKey) {
        e.preventDefault();
        printMessage();
    }
    
    // Escape = بازگشت
    if (e.key === 'Escape') {
        window.history.back();
    }
    
    // جهت‌نماها برای ناوبری
    if (e.key === 'ArrowRight' && e.ctrlKey) {
        <?php if ($navigation['prev_id']): ?>
            window.location.href = '?page=view&id=<?= $navigation['prev_id'] ?>';
        <?php endif; ?>
    }
    
    if (e.key === 'ArrowLeft' && e.ctrlKey) {
        <?php if ($navigation['next_id']): ?>
            window.location.href = '?page=view&id=<?= $navigation['next_id'] ?>';
        <?php endif; ?>
    }
});

// استایل چاپ
const printStyles = `
@media print {
    .page-header, .card-footer, .btn, .sidebar, .navbar { display: none !important; }
    .card { border: none !important; box-shadow: none !important; }
    .card-body { padding: 0 !important; }
    body { background: white !important; color: black !important; }
}
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = printStyles;
document.head.appendChild(styleSheet);
</script>