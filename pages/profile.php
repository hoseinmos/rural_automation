<?php
/**
 * User Profile Page - Fixed
 * صفحه پروفایل کاربر - اصلاح شده
 */

Auth::requireLogin();

$success = '';
$errors = [];

// دریافت اطلاعات کاربر جاری
$user = $db->fetchRow(
    "SELECT * FROM users WHERE id = ?",
    [$currentUser['id']]
);

if (!$user) {
    header('Location: ?page=dashboard&error=user_not_found');
    exit;
}

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('درخواست نامعتبر است');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_profile':
                // به‌روزرسانی اطلاعات پروفایل
                $name = Security::sanitize($_POST['name'] ?? '');
                $email = Security::sanitize($_POST['email'] ?? '');
                $phone = Security::sanitize($_POST['phone'] ?? '');
                
                // اعتبارسنجی
                if (empty($name)) {
                    $errors[] = 'نام الزامی است';
                }
                
                if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $errors[] = 'فرمت ایمیل نامعتبر است';
                }
                
                // بررسی تکراری نبودن ایمیل
                if ($email && $email !== $user['email']) {
                    $existing = $db->fetchRow(
                        "SELECT id FROM users WHERE email = ? AND id != ?",
                        [$email, $currentUser['id']]
                    );
                    
                    if ($existing) {
                        $errors[] = 'این ایمیل قبلاً استفاده شده است';
                    }
                }
                
                if (empty($errors)) {
                    $db->execute(
                        "UPDATE users SET name = ?, email = ?, phone = ?, updated_at = NOW() WHERE id = ?",
                        [$name, $email, $phone, $currentUser['id']]
                    );
                    
                    // به‌روزرسانی session
                    $_SESSION['user']['name'] = $name;
                    $_SESSION['user']['email'] = $email;
                    
                    $success = 'اطلاعات پروفایل با موفقیت به‌روزرسانی شد';
                    
                    // دریافت مجدد اطلاعات کاربر
                    $user = $db->fetchRow(
                        "SELECT * FROM users WHERE id = ?",
                        [$currentUser['id']]
                    );
                }
                break;
                
            case 'change_password':
                // تغییر رمز عبور
                $current_password = $_POST['current_password'] ?? '';
                $new_password = $_POST['new_password'] ?? '';
                $confirm_password = $_POST['confirm_password'] ?? '';
                
                // اعتبارسنجی
                if (empty($current_password)) {
                    $errors[] = 'رمز عبور فعلی الزامی است';
                }
                
                if (empty($new_password)) {
                    $errors[] = 'رمز عبور جدید الزامی است';
                }
                
                if (strlen($new_password) < 6) {
                    $errors[] = 'رمز عبور جدید باید حداقل 6 کاراکتر باشد';
                }
                
                if ($new_password !== $confirm_password) {
                    $errors[] = 'رمز عبور جدید و تکرار آن مطابقت ندارند';
                }
                
                // بررسی رمز عبور فعلی
                if (!password_verify($current_password, $user['password'])) {
                    $errors[] = 'رمز عبور فعلی اشتباه است';
                }
                
                if (empty($errors)) {
                    $hashedPassword = password_hash($new_password, PASSWORD_DEFAULT);
                    $db->execute(
                        "UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?",
                        [$hashedPassword, $currentUser['id']]
                    );
                    
                    $success = 'رمز عبور با موفقیت تغییر کرد';
                }
                break;
                
            case 'upload_avatar':
                // آپلود آواتار (در صورت وجود فایل)
                if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
                    try {
                        // بررسی نوع فایل
                        $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                        if (!in_array($_FILES['avatar']['type'], $allowed_types)) {
                            throw new Exception('فقط فایل‌های JPG، PNG و GIF مجاز هستند');
                        }
                        
                        // بررسی حجم فایل (حداکثر 2MB)
                        if ($_FILES['avatar']['size'] > 2 * 1024 * 1024) {
                            throw new Exception('حجم فایل نباید بیش از 2 مگابایت باشد');
                        }
                        
                        $avatarFileName = Security::uploadFile($_FILES['avatar'], 'avatars/');
                        
                        $db->execute(
                            "UPDATE users SET avatar = ?, updated_at = NOW() WHERE id = ?",
                            [$avatarFileName, $currentUser['id']]
                        );
                        
                        $success = 'آواتار با موفقیت آپلود شد';
                        
                        // دریافت مجدد اطلاعات کاربر
                        $user = $db->fetchRow(
                            "SELECT * FROM users WHERE id = ?",
                            [$currentUser['id']]
                        );
                        
                    } catch (Exception $e) {
                        $errors[] = 'خطا در آپلود آواتار: ' . $e->getMessage();
                    }
                }
                break;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// آمار کاربر
$user_stats = $db->fetchRow(
    "SELECT 
        COUNT(m1.id) as messages_sent,
        COUNT(m2.id) as messages_received,
        SUM(CASE WHEN m2.status = 'unread' THEN 1 ELSE 0 END) as unread_received,
        SUM(CASE WHEN m1.status = 'read' THEN 1 ELSE 0 END) as read_sent,
        MAX(m1.created_at) as last_sent,
        MAX(m2.created_at) as last_received
     FROM users u
     LEFT JOIN messages m1 ON u.id = m1.sender_id
     LEFT JOIN messages m2 ON u.id = m2.receiver_id
     WHERE u.id = ?",
    [$currentUser['id']]
);

// فعالیت‌های اخیر
$recent_activities = $db->fetchAll(
    "SELECT 
        'sent' as type,
        m.subject,
        m.created_at,
        u.name as other_user
     FROM messages m
     JOIN users u ON m.receiver_id = u.id
     WHERE m.sender_id = ?
     
     UNION ALL
     
     SELECT 
        'received' as type,
        m.subject,
        m.created_at,
        u.name as other_user
     FROM messages m
     JOIN users u ON m.sender_id = u.id
     WHERE m.receiver_id = ?
     
     ORDER BY created_at DESC
     LIMIT 10",
    [$currentUser['id'], $currentUser['id']]
);
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-user text-primary"></i>
                پروفایل کاربری
            </h1>
            <p class="text-muted mb-0">
                مدیریت اطلاعات شخصی و تنظیمات حساب
            </p>
        </div>
        <div class="col-md-6 text-end">
            <span class="badge bg-success fs-6">
                <i class="fas fa-circle"></i> آنلاین
            </span>
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

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle"></i> خطاهای زیر رخ داده است:</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= $error ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<div class="row">
    <!-- اطلاعات پروفایل -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-body text-center">
                <!-- آواتار -->
                <div class="position-relative d-inline-block mb-3">
                    <?php if (!empty($user['avatar']) && file_exists(UPLOAD_PATH . $user['avatar'])): ?>
                        <img src="<?= UPLOAD_URL . $user['avatar'] ?>" 
                             class="rounded-circle" 
                             width="120" height="120" 
                             alt="آواتار کاربر"
                             style="object-fit: cover;">
                    <?php else: ?>
                        <div class="bg-primary text-white rounded-circle d-flex align-items-center justify-content-center" 
                             style="width: 120px; height: 120px; font-size: 3rem; font-weight: 600;">
                            <?= mb_substr($user['name'], 0, 1, 'UTF-8') ?>
                        </div>
                    <?php endif; ?>
                    
                    <!-- دکمه تغییر آواتار -->
                    <button type="button" 
                            class="btn btn-primary btn-sm position-absolute bottom-0 end-0 rounded-circle" 
                            style="width: 32px; height: 32px;"
                            data-bs-toggle="modal" 
                            data-bs-target="#avatarModal">
                        <i class="fas fa-camera"></i>
                    </button>
                </div>
                
                <h4 class="mb-1"><?= htmlspecialchars($user['name']) ?></h4>
                <p class="text-muted mb-2">@<?= htmlspecialchars($user['username']) ?></p>
                <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?> mb-3">
                    <?= USER_ROLES[$user['role']] ?>
                </span>
                
                <div class="row text-center">
                    <div class="col-6">
                        <div class="h4 text-primary"><?= number_format($user_stats['messages_sent']) ?></div>
                        <small class="text-muted">ارسالی</small>
                    </div>
                    <div class="col-6">
                        <div class="h4 text-success"><?= number_format($user_stats['messages_received']) ?></div>
                        <small class="text-muted">دریافتی</small>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- اطلاعات حساب -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle"></i> اطلاعات حساب
                </h6>
            </div>
            <div class="card-body">
                <div class="mb-3">
                    <label class="form-label text-muted">تاریخ عضویت</label>
                    <div><?= JalaliDate::toJalali(strtotime($user['created_at']), 'Y/m/d') ?></div>
                </div>
                
                <div class="mb-3">
                    <label class="form-label text-muted">آخرین ورود</label>
                    <div>
                        <?= $user['last_login'] ? JalaliDate::timeAgo(strtotime($user['last_login'])) : 'هرگز' ?>
                    </div>
                </div>
                
                <div class="mb-0">
                    <label class="form-label text-muted">وضعیت حساب</label>
                    <div>
                        <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                            <?= $user['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تب‌های تنظیمات -->
    <div class="col-lg-8">
        <ul class="nav nav-tabs" id="profileTabs" role="tablist">
            <li class="nav-item">
                <button class="nav-link active" id="profile-tab" data-bs-toggle="tab" data-bs-target="#profile" type="button">
                    <i class="fas fa-user"></i> اطلاعات شخصی
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
                    <i class="fas fa-lock"></i> امنیت
                </button>
            </li>
            <li class="nav-item">
                <button class="nav-link" id="activity-tab" data-bs-toggle="tab" data-bs-target="#activity" type="button">
                    <i class="fas fa-history"></i> فعالیت‌ها
                </button>
            </li>
        </ul>
        
        <div class="tab-content" id="profileTabsContent">
            <!-- تب اطلاعات شخصی -->
            <div class="tab-pane fade show active" id="profile" role="tabpanel">
                <div class="card">
                    <div class="card-body">
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="update_profile">
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label required">نام کامل</label>
                                        <input type="text" class="form-control" name="name" 
                                               value="<?= htmlspecialchars($user['name']) ?>" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">نام کاربری</label>
                                        <input type="text" class="form-control" 
                                               value="<?= htmlspecialchars($user['username']) ?>" 
                                               readonly disabled>
                                        <div class="form-text">نام کاربری قابل تغییر نیست</div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">ایمیل</label>
                                        <input type="email" class="form-control" name="email" 
                                               value="<?= htmlspecialchars($user['email'] ?? '') ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label">شماره تلفن</label>
                                        <input type="tel" class="form-control" name="phone" 
                                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> ذخیره تغییرات
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- تب امنیت -->
            <div class="tab-pane fade" id="security" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">تغییر رمز عبور</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="change_password">
                            
                            <div class="mb-3">
                                <label class="form-label required">رمز عبور فعلی</label>
                                <input type="password" class="form-control" name="current_password" required>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label required">رمز عبور جدید</label>
                                        <input type="password" class="form-control" name="new_password" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label class="form-label required">تکرار رمز عبور جدید</label>
                                        <input type="password" class="form-control" name="confirm_password" required>
                                    </div>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-key"></i> تغییر رمز عبور
                            </button>
                        </form>
                    </div>
                </div>
                
                <!-- آمار امنیتی -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">آمار امنیتی</h6>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-4">
                                <div class="h4 text-success"><?= number_format($user_stats['read_sent']) ?></div>
                                <small class="text-muted">نامه‌های خوانده شده</small>
                            </div>
                            <div class="col-md-4">
                                <div class="h4 text-warning"><?= number_format($user_stats['unread_received']) ?></div>
                                <small class="text-muted">نامه‌های خوانده نشده</small>
                            </div>
                            <div class="col-md-4">
                                <div class="h4 text-info">0</div>
                                <small class="text-muted">ورودهای ناموفق</small>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- تب فعالیت‌ها -->
            <div class="tab-pane fade" id="activity" role="tabpanel">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">آخرین فعالیت‌ها</h6>
                    </div>
                    <div class="card-body p-0">
                        <?php if (!empty($recent_activities)): ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_activities as $activity): ?>
                                    <div class="list-group-item">
                                        <div class="d-flex w-100 justify-content-between align-items-start">
                                            <div>
                                                <div class="d-flex align-items-center mb-1">
                                                    <i class="fas fa-<?= $activity['type'] === 'sent' ? 'paper-plane text-primary' : 'inbox text-success' ?> me-2"></i>
                                                    <span class="fw-bold">
                                                        <?= $activity['type'] === 'sent' ? 'ارسال نامه' : 'دریافت نامه' ?>
                                                    </span>
                                                </div>
                                                <p class="mb-1"><?= htmlspecialchars($activity['subject']) ?></p>
                                                <small class="text-muted">
                                                    <?= $activity['type'] === 'sent' ? 'به: ' : 'از: ' ?>
                                                    <?= htmlspecialchars($activity['other_user']) ?>
                                                </small>
                                            </div>
                                            <small class="text-muted">
                                                <?= JalaliDate::timeAgo(strtotime($activity['created_at'])) ?>
                                            </small>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-center p-4 text-muted">
                                <i class="fas fa-history fa-3x mb-3"></i>
                                <p>فعالیتی یافت نشد</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- مودال تغییر آواتار -->
<div class="modal fade" id="avatarModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-camera"></i> تغییر آواتار
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="upload_avatar">
                
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">انتخاب فایل تصویر</label>
                        <input type="file" class="form-control" name="avatar" 
                               accept="image/jpeg,image/png,image/gif" required>
                        <div class="form-text">
                            فرمت‌های مجاز: JPG, PNG, GIF | حداکثر 2 مگابایت
                        </div>
                    </div>
                    
                    <div id="imagePreview" class="text-center" style="display: none;">
                        <img id="previewImg" class="img-fluid rounded" style="max-height: 200px;">
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                        لغو
                    </button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> آپلود آواتار
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// پیش‌نمایش آواتار
document.querySelector('input[name="avatar"]').addEventListener('change', function(e) {
    const file = e.target.files[0];
    if (file) {
        // بررسی حجم فایل
        if (file.size > 2 * 1024 * 1024) {
            alert('حجم فایل نباید بیش از 2 مگابایت باشد');
            this.value = '';
            return;
        }
        
        // بررسی نوع فایل
        if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
            alert('فقط فایل‌های JPG، PNG و GIF مجاز هستند');
            this.value = '';
            return;
        }
        
        // نمایش پیش‌نمایش
        const reader = new FileReader();
        reader.onload = function(e) {
            document.getElementById('previewImg').src = e.target.result;
            document.getElementById('imagePreview').style.display = 'block';
        };
        reader.readAsDataURL(file);
    } else {
        document.getElementById('imagePreview').style.display = 'none';
    }
});

// اعتبارسنجی فرم تغییر رمز عبور
document.querySelector('form[action=""] input[name="action"][value="change_password"]')?.closest('form').addEventListener('submit', function(e) {
    const newPassword = this.querySelector('input[name="new_password"]').value;
    const confirmPassword = this.querySelector('input[name="confirm_password"]').value;
    
    if (newPassword !== confirmPassword) {
        e.preventDefault();
        alert('رمز عبور جدید و تکرار آن مطابقت ندارند');
        return false;
    }
    
    if (newPassword.length < 6) {
        e.preventDefault();
        alert('رمز عبور جدید باید حداقل 6 کاراکتر باشد');
        return false;
    }
});

// تأیید تغییر رمز عبور
document.querySelector('button[type="submit"] i.fa-key')?.parentElement.addEventListener('click', function(e) {
    if (!confirm('آیا مطمئن هستید که می‌خواهید رمز عبور را تغییر دهید؟')) {
        e.preventDefault();
    }
});
</script>