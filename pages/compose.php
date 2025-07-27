<?php
/**
 * Compose Message Page - Complete Modified Version
 * صفحه نوشتن نامه جدید - نسخه کامل اصلاح شده برای ارسال فقط به مدیر
 */

Auth::requireLogin();

$success = '';
$errors = [];

// بررسی پاسخ به نامه
$replyTo = null;
if (isset($_GET['reply']) && is_numeric($_GET['reply'])) {
    $replyTo = $db->fetchRow(
        "SELECT m.*, u.name as sender_name, u.role as sender_role
         FROM messages m 
         JOIN users u ON m.sender_id = u.id 
         WHERE m.id = ? AND m.receiver_id = ?",
        [$_GET['reply'], $currentUser['id']]
    );
    
    // بررسی مجوز پاسخ برای کاربران عادی
    if ($replyTo && $currentUser['role'] !== 'admin') {
        if (!MessagePermissions::canReplyToMessage(
            $currentUser['role'], 
            $replyTo['sender_role'], 
            $currentUser['id'], 
            $replyTo['sender_id']
        )) {
            $errors[] = 'شما فقط می‌توانید به نامه‌های مدیر سیستم پاسخ دهید';
            $replyTo = null;
        }
    }
}

// بررسی محدودیت روزانه
$dailyLimit = checkDailyMessageLimit($currentUser['id'], $currentUser['role'], $db);
if (!$dailyLimit['allowed']) {
    $errors[] = "شما به حد روزانه ارسال نامه رسیده‌اید ({$dailyLimit['limit']} نامه در روز)";
}

// پردازش فرم ارسال نامه
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // بررسی CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('درخواست نامعتبر است');
        }
        
        // بررسی rate limiting
        if (!Security::rateLimit('send_message', 20, 3600)) { // 20 نامه در ساعت
            throw new Exception('تعداد نامه‌های ارسالی بیش از حد مجاز است');
        }
        
        // بررسی مجدد محدودیت روزانه
        $dailyLimit = checkDailyMessageLimit($currentUser['id'], $currentUser['role'], $db);
        if (!$dailyLimit['allowed']) {
            throw new Exception("شما به حد روزانه ارسال نامه رسیده‌اید");
        }
        
        // دریافت و اعتبارسنجی داده‌ها
        $subject = Security::sanitize($_POST['subject'] ?? '');
        $messageNumber = Security::sanitize($_POST['message_number'] ?? '');
        $content = Security::sanitize($_POST['content'] ?? '');
        $receiverId = (int)($_POST['receiver_id'] ?? 0);
        $priority = $_POST['priority'] ?? 'normal';
        $replyToId = !empty($_POST['reply_to']) ? (int)$_POST['reply_to'] : null;
        
        // اعتبارسنجی فیلدهای اجباری
        if (empty($subject)) {
            $errors[] = 'موضوع نامه الزامی است';
        }
        
        if (empty($content)) {
            $errors[] = 'متن نامه الزامی است';
        }
        
        if ($receiverId <= 0) {
            $errors[] = 'انتخاب گیرنده الزامی است';
        }
        
        // اعتبارسنجی اولویت
        if (!canUserSetPriority($currentUser['role'], $priority)) {
            $priority = 'normal'; // بازگشت به اولویت عادی
            $errors[] = 'شما مجوز تنظیم این اولویت را ندارید';
        }
        
        // استفاده از MessagePermissions برای validation
        $permissionErrors = MessagePermissions::validateMessageSubmission($currentUser, $receiverId, $db);
        $errors = array_merge($errors, $permissionErrors);
        
        // پردازش فایل ضمیمه
        $attachmentFileName = null;
        if (isset($_FILES['attachment']) && $_FILES['attachment']['error'] === UPLOAD_ERR_OK) {
            try {
                $attachmentFileName = Security::uploadFile($_FILES['attachment']);
            } catch (Exception $e) {
                $errors[] = 'خطا در آپلود فایل: ' . $e->getMessage();
            }
        }
        
        // در صورت عدم وجود خطا، ذخیره نامه
        if (empty($errors)) {
            $messageId = $db->insert(
                "INSERT INTO messages (subject, message_number, content, sender_id, receiver_id, attachment, priority, reply_to, status) 
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'unread')",
                [$subject, $messageNumber, $content, $currentUser['id'], $receiverId, $attachmentFileName, $priority, $replyToId]
            );
            
            // اگر این پاسخ به نامه‌ای است، وضعیت نامه اصلی را به‌روزرسانی کن
            if ($replyToId) {
                $db->execute(
                    "UPDATE messages SET status = 'replied' WHERE id = ?",
                    [$replyToId]
                );
            }
            
            // ثبت لاگ
            writeLog("Message sent: ID $messageId from user {$currentUser['id']} to user $receiverId", 'INFO');
            
            $success = 'نامه با موفقیت ارسال شد';
            
            // پاک کردن فرم
            $_POST = [];
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// دریافت لیست کاربران مجاز برای انتخاب گیرنده
$users = MessagePermissions::getAvailableRecipients($currentUser, $db);

// اگر پاسخ به نامه است، گیرنده را از فرستنده نامه اصلی تنظیم کن
$defaultReceiverId = null;
if ($replyTo && empty($errors)) {
    $defaultReceiverId = $replyTo['sender_id'];
}

// دریافت اطلاعات پیام‌رسانی کاربر
$messagingInfo = MessagePermissions::getMessagingInfo($currentUser);
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-pen text-primary"></i>
                <?= $replyTo ? 'پاسخ به نامه' : 'نامه جدید' ?>
            </h1>
            <?php if ($replyTo): ?>
                <p class="text-muted mb-0">
                    پاسخ به: "<?= htmlspecialchars($replyTo['subject']) ?>" از <?= htmlspecialchars($replyTo['sender_name']) ?>
                </p>
            <?php else: ?>
                <p class="text-muted mb-0">
                    <i class="fas fa-info-circle"></i> <?= $messagingInfo['message'] ?>
                </p>
            <?php endif; ?>
        </div>
        <div class="col-md-6 text-end">
            <a href="?page=inbox" class="btn btn-outline-secondary">
                <i class="fas fa-arrow-right"></i> بازگشت
            </a>
        </div>
    </div>
</div>

<!-- نمایش پیام‌های موفقیت -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show" role="alert">
        <i class="fas fa-check-circle"></i> <?= $success ?>
        <div class="mt-2">
            <a href="?page=sent" class="btn btn-sm btn-outline-success me-2">مشاهده نامه‌های ارسالی</a>
            <a href="?page=compose" class="btn btn-sm btn-success">نامه جدید</a>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<!-- نمایش خطاها -->
<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <h6><i class="fas fa-exclamation-triangle"></i> خطاهای زیر رخ داده است:</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- نمایش محدودیت روزانه -->
<?php if ($dailyLimit['remaining'] <= 5 && $dailyLimit['remaining'] > 0): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-clock"></i>
        <strong>توجه:</strong> شما امروز <?= toPersianNumber($dailyLimit['remaining']) ?> نامه دیگر می‌توانید ارسال کنید.
        (<?= toPersianNumber($dailyLimit['used']) ?> از <?= toPersianNumber($dailyLimit['limit']) ?> استفاده شده)
    </div>
<?php endif; ?>

<!-- هشدار برای عدم وجود گیرنده -->
<?php if (empty($users)): ?>
    <div class="alert alert-warning" role="alert">
        <i class="fas fa-exclamation-triangle"></i>
        <strong>هیچ گیرنده‌ای در دسترس نیست!</strong>
        <p class="mb-0 mt-2">
            <?php if ($currentUser['role'] !== 'admin'): ?>
                در حال حاضر هیچ مدیری در سیستم فعال نیست. لطفاً بعداً تلاش کنید.
            <?php else: ?>
                هیچ کاربر فعال دیگری در سیستم وجود ندارد.
            <?php endif; ?>
        </p>
    </div>
<?php endif; ?>

<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-edit"></i> جزئیات نامه
                </h5>
            </div>
            <div class="card-body">
                <form method="POST" enctype="multipart/form-data" id="composeForm">
                    <?= Security::csrfField() ?>
                    
                    <?php if ($replyTo): ?>
                        <input type="hidden" name="reply_to" value="<?= $replyTo['id'] ?>">
                    <?php endif; ?>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="receiver_id" class="form-label required">
                                    <i class="fas fa-user"></i> گیرنده
                                    <?php if ($currentUser['role'] !== 'admin'): ?>
                                        <small class="text-muted">(فقط مدیران سیستم)</small>
                                    <?php endif; ?>
                                </label>
                                <select class="form-select" name="receiver_id" id="receiver_id" required <?= empty($users) ? 'disabled' : '' ?>>
                                    <option value="">انتخاب کنید...</option>
                                    <?php foreach ($users as $user): ?>
                                        <option value="<?= $user['id'] ?>" 
                                                <?= ($defaultReceiverId && $user['id'] == $defaultReceiverId) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($user['name']) ?> 
                                            (<?= htmlspecialchars($user['username']) ?>) 
                                            - <?= USER_ROLES[$user['role']] ?>
                                            <?php if ($user['role'] === 'admin'): ?>
                                                <i class="fas fa-crown text-warning ms-1"></i>
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <?php if (empty($users)): ?>
                                    <div class="form-text text-danger">هیچ گیرنده‌ای در دسترس نیست</div>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="priority" class="form-label">
                                    <i class="fas fa-exclamation-circle"></i> اولویت
                                </label>
                                <select class="form-select" name="priority" id="priority">
                                    <?php 
                                    $allowedPriorities = getAllowedPriorities($currentUser['role']);
                                    foreach ($allowedPriorities as $key => $label): 
                                    ?>
                                        <option value="<?= $key ?>" <?= $key === 'normal' ? 'selected' : '' ?>>
                                            <?= $label ?>
                                            <?php if ($key === 'urgent' && $currentUser['role'] !== 'admin'): ?>
                                                (فقط در موارد اضطراری)
                                            <?php endif; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-8">
                            <div class="mb-3">
                                <label for="subject" class="form-label required">
                                    <i class="fas fa-tag"></i> موضوع نامه
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="subject" 
                                       name="subject" 
                                       value="<?= $replyTo ? 'پاسخ: ' . htmlspecialchars($replyTo['subject']) : (isset($_POST['subject']) ? htmlspecialchars($_POST['subject']) : '') ?>"
                                       maxlength="255"
                                       required>
                                <div class="form-text">حداکثر 255 کاراکتر</div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label for="message_number" class="form-label">
                                    <i class="fas fa-hashtag"></i> شماره نامه
                                </label>
                                <input type="text" 
                                       class="form-control" 
                                       id="message_number" 
                                       name="message_number" 
                                       value="<?= isset($_POST['message_number']) ? htmlspecialchars($_POST['message_number']) : '' ?>"
                                       maxlength="50"
                                       placeholder="اختیاری">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="content" class="form-label required">
                            <i class="fas fa-align-right"></i> متن نامه
                        </label>
                        <textarea class="form-control" 
                                  id="content" 
                                  name="content" 
                                  rows="12" 
                                  placeholder="متن نامه خود را اینجا بنویسید..."
                                  required><?php if ($replyTo && !isset($_POST['content'])): ?>


در پاسخ به نامه شما:
<?= htmlspecialchars($replyTo['content']) ?><?php elseif (isset($_POST['content'])): ?><?= htmlspecialchars($_POST['content']) ?><?php endif; ?></textarea>
                        <div class="form-text">
                            <span id="charCount">0</span> کاراکتر
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label for="attachment" class="form-label">
                            <i class="fas fa-paperclip"></i> فایل ضمیمه
                        </label>
                        <input type="file" 
                               class="form-control" 
                               id="attachment" 
                               name="attachment" 
                               accept=".pdf,.jpg,.jpeg,.png,.mp4,.avi,.doc,.docx">
                        <div class="form-text">
                            فرمت‌های مجاز: PDF, JPG, PNG, MP4, AVI, DOC, DOCX | حداکثر <?= formatFileSize(MAX_FILE_SIZE) ?>
                        </div>
                        <div id="filePreview" class="mt-2" style="display: none;">
                            <div class="alert alert-info">
                                <i class="fas fa-file"></i>
                                <span id="fileName"></span>
                                <button type="button" class="btn btn-sm btn-outline-danger ms-2" onclick="removeFile()">
                                    <i class="fas fa-times"></i> حذف
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <button type="submit" class="btn btn-primary btn-lg" <?= (empty($users) || !$dailyLimit['allowed']) ? 'disabled' : '' ?>>
                                <i class="fas fa-paper-plane"></i> ارسال نامه
                            </button>
                            <button type="button" class="btn btn-outline-secondary" onclick="saveDraft()">
                                <i class="fas fa-save"></i> ذخیره پیش‌نویس
                            </button>
                        </div>
                        <div>
                            <button type="reset" class="btn btn-outline-warning" onclick="resetForm()">
                                <i class="fas fa-undo"></i> پاک کردن فرم
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- راهنما و نکات -->
    <div class="col-lg-3">
        <!-- محدودیت‌های کاربر -->
        <div class="card">
            <div class="card-header bg-<?= MessagePermissions::getRoleColor($currentUser['role']) ?> text-white">
                <h6 class="mb-0">
                    <i class="<?= MessagePermissions::getRoleIcon($currentUser['role']) ?>"></i> 
                    اطلاعات حساب شما
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <strong>نقش:</strong> <?= getUserRoleDisplay($currentUser['role']) ?>
                </div>
                <div class="mb-3">
                    <strong>مجوز ارسال:</strong> <?= $messagingInfo['recipients'] ?>
                </div>
                <div class="mb-3">
                    <strong>محدودیت روزانه:</strong>
                    <div class="progress mt-1">
                        <div class="progress-bar" role="progressbar" 
                             style="width: <?= ($dailyLimit['used'] / $dailyLimit['limit']) * 100 ?>%">
                        </div>
                    </div>
                    <small class="text-muted">
                        <?= toPersianNumber($dailyLimit['used']) ?> از <?= toPersianNumber($dailyLimit['limit']) ?> استفاده شده
                    </small>
                </div>
                <div class="alert alert-info p-2">
                    <small><?= MessagePermissions::getRestrictionMessage($currentUser['role']) ?></small>
                </div>
            </div>
        </div>
        
        <!-- نکات مهم -->
        <div class="card mt-3">
            <div class="card-header bg-info text-white">
                <h6 class="mb-0">
                    <i class="fas fa-lightbulb"></i> نکات مهم
                </h6>
            </div>
            <div class="card-body">
                <ul class="list-unstyled mb-0">
                    <?php if ($currentUser['role'] !== 'admin'): ?>
                        <li class="mb-2">
                            <i class="fas fa-info-circle text-primary"></i>
                            شما فقط می‌توانید به مدیر سیستم نامه ارسال کنید
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-clock text-warning"></i>
                            مدیر سیستم در اسرع وقت به شما پاسخ خواهد داد
                        </li>
                    <?php endif; ?>
                    <li class="mb-2">
                        <i class="fas fa-check text-success"></i>
                        موضوع نامه را واضح و مفصل بنویسید
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success"></i>
                        از زبان محترمانه استفاده کنید
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success"></i>
                        فایل‌های ضمیمه را بررسی کنید
                    </li>
                    <li class="mb-2">
                        <i class="fas fa-check text-success"></i>
                        قبل از ارسال، نامه را مطالعه کنید
                    </li>
                </ul>
            </div>
        </div>
        
        <!-- آمار نامه‌های کاربر -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-chart-pie"></i> آمار نامه‌های شما
                </h6>
            </div>
            <div class="card-body">
                <?php
                $userStats = MessagePermissions::getMessagingStats($currentUser, $db);
                ?>
                <div class="row text-center">
                    <div class="col-12 mb-2">
                        <div class="h4 text-primary"><?= toPersianNumber($userStats['sent_count']) ?></div>
                        <small class="text-muted">کل نامه‌های ارسالی</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-success"><?= toPersianNumber($dailyLimit['remaining']) ?></div>
                        <small class="text-muted">باقی‌مانده امروز</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-info"><?= toPersianNumber($userStats['read_sent_count']) ?></div>
                        <small class="text-muted">خوانده شده</small>
                    </div>
                    <div class="col-4">
                        <div class="h5 text-warning"><?= toPersianNumber($userStats['unread_count']) ?></div>
                        <small class="text-muted">خوانده نشده</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- میانبرهای کیبورد -->
        <div class="card mt-3">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-keyboard"></i> میانبرهای کیبورد
                </h6>
            </div>
            <div class="card-body">
                <small>
                    <strong>Ctrl + Enter:</strong> ارسال نامه<br>
                    <strong>Ctrl + S:</strong> ذخیره پیش‌نویس<br>
                    <strong>Escape:</strong> لغو<br>
                    <strong>Alt + R:</strong> پاک کردن فرم
                </small>
            </div>
        </div>
    </div>
</div>

<script>
// شمارش کاراکترها
document.getElementById('content').addEventListener('input', function() {
    const count = this.value.length;
    document.getElementById('charCount').textContent = count.toLocaleString('fa-IR');
    
    // تغییر رنگ بر اساس تعداد کاراکتر
    const charCountElement = document.getElementById('charCount');
    if (count > 1000) {
        charCountElement.className = 'text-success fw-bold';
    } else if (count > 500) {
        charCountElement.className = 'text-warning fw-bold';
    } else {
        charCountElement.className = 'text-muted';
    }
});

// پیش‌نمایش فایل انتخابی
document.getElementById('attachment').addEventListener('change', function() {
    const file = this.files[0];
    const preview = document.getElementById('filePreview');
    const fileName = document.getElementById('fileName');
    
    if (file) {
        // بررسی حجم فایل
        if (file.size > <?= MAX_FILE_SIZE ?>) {
            alert('حجم فایل بیش از حد مجاز است');
            removeFile();
            return;
        }
        
        // بررسی نوع فایل
        const allowedExtensions = <?= json_encode(ALLOWED_EXTENSIONS) ?>;
        const fileExtension = file.name.split('.').pop().toLowerCase();
        if (!allowedExtensions.includes(fileExtension)) {
            alert('فرمت فایل مجاز نیست');
            removeFile();
            return;
        }
        
        fileName.textContent = file.name + ' (' + formatFileSize(file.size) + ')';
        preview.style.display = 'block';
    } else {
        preview.style.display = 'none';
    }
});

// حذف فایل انتخابی
function removeFile() {
    document.getElementById('attachment').value = '';
    document.getElementById('filePreview').style.display = 'none';
}

// فرمت کردن حجم فایل
function formatFileSize(bytes) {
    if (bytes === 0) return '0 بایت';
    const k = 1024;
    const sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

// ذخیره پیش‌نویس
function saveDraft() {
    const formData = new FormData(document.getElementById('composeForm'));
    formData.append('action', 'save_draft');
    
    fetch('ajax/save_draft.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('پیش‌نویس ذخیره شد');
        } else {
            alert('خطا در ذخیره پیش‌نویس: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('خطا در ذخیره پیش‌نویس');
    });
}

// پاک کردن فرم
function resetForm() {
    if (confirm('آیا مطمئن هستید که می‌خواهید فرم را پاک کنید؟')) {
        document.getElementById('composeForm').reset();
        document.getElementById('charCount').textContent = '0';
        removeFile();
    }
}

// اعتبارسنجی فرم
document.getElementById('composeForm').addEventListener('submit', function(e) {
    const subject = document.getElementById('subject').value.trim();
    const content = document.getElementById('content').value.trim();
    const receiverId = document.getElementById('receiver_id').value;
    
    if (!subject) {
        e.preventDefault();
        alert('موضوع نامه الزامی است');
        document.getElementById('subject').focus();
        return false;
    }
    
    if (!content) {
        e.preventDefault();
        alert('متن نامه الزامی است');
        document.getElementById('content').focus();
        return false;
    }
    
    if (!receiverId) {
        e.preventDefault();
        alert('انتخاب گیرنده الزامی است');
        document.getElementById('receiver_id').focus();
        return false;
    }
    
    // بررسی اضافی برای کاربران عادی
    <?php if ($currentUser['role'] !== 'admin'): ?>
    const selectedOption = document.querySelector('#receiver_id option:checked');
    if (selectedOption && !selectedOption.textContent.includes('مدیر')) {
        e.preventDefault();
        alert('شما فقط می‌توانید به مدیر سیستم نامه ارسال کنید');
        return false;
    }
    <?php endif; ?>
    
    // تأیید ارسال
    if (!confirm('آیا مطمئن هستید که می‌خواهید این نامه را ارسال کنید؟')) {
        e.preventDefault();
        return false;
    }
});

// میانبرهای کیبورد
document.addEventListener('keydown', function(e) {
    // Ctrl + Enter = ارسال
    if (e.ctrlKey && e.key === 'Enter') {
        e.preventDefault();
        document.getElementById('composeForm').submit();
    }
    
    // Ctrl + S = ذخیره پیش‌نویس
    if (e.ctrlKey && e.key === 's') {
        e.preventDefault();
        saveDraft();
    }
    
    // Alt + R = پاک کردن فرم
    if (e.altKey && e.key === 'r') {
        e.preventDefault();
        resetForm();
    }
    
    // Escape = لغو
    if (e.key === 'Escape') {
        if (confirm('آیا می‌خواهید از این صفحه خارج شوید؟')) {
            window.history.back();
        }
    }
});

// شمارش کاراکتر اولیه
document.getElementById('content').dispatchEvent(new Event('input'));

// تمرکز روی فیلد اول
<?php if (!$replyTo): ?>
document.getElementById('receiver_id').focus();
<?php else: ?>
document.getElementById('subject').focus();
<?php endif; ?>

// به‌روزرسانی پیشرفت محدودیت روزانه
function updateDailyProgress() {
    fetch('ajax/get_daily_usage.php')
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const progressBar = document.querySelector('.progress-bar');
                const percentage = (data.used / data.limit) * 100;
                progressBar.style.width = percentage + '%';
                
                const usageText = document.querySelector('.progress').nextElementSibling;
                usageText.textContent = `${data.used} از ${data.limit} استفاده شده`;
                
                // غیرفعال کردن دکمه ارسال در صورت رسیدن به حد
                const submitBtn = document.querySelector('button[type="submit"]');
                if (data.used >= data.limit) {
                    submitBtn.disabled = true;
                    submitBtn.innerHTML = '<i class="fas fa-ban"></i> حد روزانه پر شده';
                }
            }
        })
        .catch(error => console.error('Error updating daily progress:', error));
}

// بررسی محدودیت روزانه هر 5 دقیقه
setInterval(updateDailyProgress, 300000);
</script>