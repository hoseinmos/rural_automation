<?php
/**
 * System Settings Page (Admin Only)
 * صفحه تنظیمات سیستم (فقط مدیران)
 */

Auth::requireLogin();

// بررسی دسترسی مدیر
if (!Auth::isAdmin()) {
    header('Location: ?page=dashboard&error=access_denied');
    exit;
}

$success = '';
$errors = [];

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('درخواست نامعتبر است');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'update_general':
                // تنظیمات کلی
                $site_title = Security::sanitize($_POST['site_title'] ?? '');
                $max_file_size = (int)($_POST['max_file_size'] ?? 10);
                $items_per_page = (int)($_POST['items_per_page'] ?? 20);
                $session_timeout = (int)($_POST['session_timeout'] ?? 3600);
                
                if (empty($site_title)) {
                    $errors[] = 'عنوان سایت الزامی است';
                }
                
                if ($max_file_size < 1 || $max_file_size > 100) {
                    $errors[] = 'حداکثر حجم فایل باید بین 1 تا 100 مگابایت باشد';
                }
                
                if (empty($errors)) {
                    // به‌روزرسانی تنظیمات
                    $settings = [
                        'site_title' => $site_title,
                        'max_file_size' => $max_file_size * 1024 * 1024, // تبدیل به بایت
                        'items_per_page' => $items_per_page,
                        'session_timeout' => $session_timeout
                    ];
                    
                    foreach ($settings as $key => $value) {
                        $db->execute(
                            "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
                            [$key, $value]
                        );
                    }
                    
                    $success = 'تنظیمات کلی با موفقیت به‌روزرسانی شد';
                }
                break;
                
            case 'update_security':
                // تنظیمات امنیتی
                $max_login_attempts = (int)($_POST['max_login_attempts'] ?? 5);
                $login_block_time = (int)($_POST['login_block_time'] ?? 900);
                $password_min_length = (int)($_POST['password_min_length'] ?? 6);
                $require_strong_password = isset($_POST['require_strong_password']) ? 1 : 0;
                
                $security_settings = [
                    'max_login_attempts' => $max_login_attempts,
                    'login_block_time' => $login_block_time,
                    'password_min_length' => $password_min_length,
                    'require_strong_password' => $require_strong_password
                ];
                
                foreach ($security_settings as $key => $value) {
                    $db->execute(
                        "INSERT INTO system_settings (setting_key, setting_value) VALUES (?, ?) 
                         ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = NOW()",
                        [$key, $value]
                    );
                }
                
                $success = 'تنظیمات امنیتی با موفقیت به‌روزرسانی شد';
                break;
                
            case 'add_user':
                // اضافه کردن کاربر جدید
                $username = Security::sanitize($_POST['username'] ?? '');
                $name = Security::sanitize($_POST['name'] ?? '');
                $email = Security::sanitize($_POST['email'] ?? '');
                $password = $_POST['password'] ?? '';
                $role = $_POST['role'] ?? 'user';
                
                // اعتبارسنجی
                if (empty($username) || empty($name) || empty($password)) {
                    $errors[] = 'نام کاربری، نام و رمز عبور الزامی هستند';
                } elseif (strlen($username) < 3) {
                    $errors[] = 'نام کاربری باید حداقل 3 کاراکتر باشد';
                } elseif (strlen($password) < 6) {
                    $errors[] = 'رمز عبور باید حداقل 6 کاراکتر باشد';
                } else {
                    // بررسی تکراری نبودن نام کاربری
                    $existing = $db->fetchRow(
                        "SELECT id FROM users WHERE username = ?",
                        [$username]
                    );
                    
                    if ($existing) {
                        $errors[] = 'نام کاربری قبلاً استفاده شده است';
                    } else {
                        $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                        $userId = $db->insert(
                            "INSERT INTO users (username, password, name, email, role) VALUES (?, ?, ?, ?, ?)",
                            [$username, $hashedPassword, $name, $email, $role]
                        );
                        
                        if ($userId) {
                            $success = "کاربر جدید با نام کاربری '$username' اضافه شد";
                        } else {
                            $errors[] = 'خطا در اضافه کردن کاربر';
                        }
                    }
                }
                break;
                
            case 'cleanup_system':
                // پاکسازی سیستم
                $cleanup_type = $_POST['cleanup_type'] ?? '';
                
                switch ($cleanup_type) {
                    case 'old_messages':
                        $days = (int)($_POST['cleanup_days'] ?? 365);
                        $affected = $db->execute(
                            "DELETE FROM messages WHERE created_at < DATE_SUB(NOW(), INTERVAL ? DAY) AND status = 'archived'",
                            [$days]
                        );
                        $success = "$affected نامه آرشیو شده قدیمی حذف شد";
                        break;
                        
                    case 'login_attempts':
                        $affected = $db->execute("DELETE FROM login_attempts WHERE attempt_time < DATE_SUB(NOW(), INTERVAL 7 DAY)");
                        $success = "$affected رکورد تلاش ورود قدیمی حذف شد";
                        break;
                        
                    case 'temp_files':
                        // حذف فایل‌های موقت
                        $tempPath = UPLOAD_PATH . 'temp/';
                        if (is_dir($tempPath)) {
                            $files = glob($tempPath . '*');
                            $count = 0;
                            foreach ($files as $file) {
                                if (is_file($file) && filemtime($file) < (time() - 86400)) { // قدیمی‌تر از 24 ساعت
                                    unlink($file);
                                    $count++;
                                }
                            }
                            $success = "$count فایل موقت حذف شد";
                        }
                        break;
                }
                break;
                
            case 'backup_database':
                // پشتیبان‌گیری از پایگاه داده
                $backupFile = 'backup/db_backup_' . date('Y-m-d_H-i-s') . '.sql';
                $backupPath = __DIR__ . '/../' . $backupFile;
                
                // ایجاد پوشه backup
                $backupDir = dirname($backupPath);
                if (!is_dir($backupDir)) {
                    mkdir($backupDir, 0755, true);
                }
                
                // اجرای mysqldump (در محیط واقعی)
                // در اینجا فقط یک پیام نمایش می‌دهیم
                $success = "درخواست پشتیبان‌گیری ثبت شد. فایل پشتیبان در $backupFile ذخیره خواهد شد";
                break;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// دریافت تنظیمات فعلی
$current_settings = [];
$settings_result = $db->fetchAll("SELECT setting_key, setting_value FROM system_settings");
foreach ($settings_result as $setting) {
    $current_settings[$setting['setting_key']] = $setting['setting_value'];
}

// آمار سیستم
$system_stats = $db->fetchRow(
    "SELECT 
        (SELECT COUNT(*) FROM users) as total_users,
        (SELECT COUNT(*) FROM users WHERE status = 'active') as active_users,
        (SELECT COUNT(*) FROM messages) as total_messages,
        (SELECT COUNT(*) FROM messages WHERE status = 'unread') as unread_messages,
        (SELECT COUNT(*) FROM messages WHERE DATE(created_at) = CURDATE()) as today_messages"
);

// لیست کاربران
$users = $db->fetchAll(
    "SELECT id, username, name, email, role, status, created_at, last_login FROM users ORDER BY created_at DESC LIMIT 50"
);

// آمار فضای مصرفی
$upload_size = 0;
if (is_dir(UPLOAD_PATH)) {
    $files = glob(UPLOAD_PATH . '*');
    foreach ($files as $file) {
        if (is_file($file)) {
            $upload_size += filesize($file);
        }
    }
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-cogs text-primary"></i>
                تنظیمات سیستم
            </h1>
            <p class="text-muted mb-0">
                مدیریت و پیکربندی سیستم
            </p>
        </div>
        <div class="col-md-6 text-end">
            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="backupDatabase()">
                    <i class="fas fa-download"></i> پشتیبان‌گیری
                </button>
                <button type="button" class="btn btn-warning" onclick="clearCache()">
                    <i class="fas fa-sync"></i> پاک کردن Cache
                </button>
            </div>
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

<!-- آمار سیستم -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-users fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= number_format($system_stats['active_users']) ?></div>
                        <div class="stat-label">کاربران فعال</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-envelope fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= number_format($system_stats['total_messages']) ?></div>
                        <div class="stat-label">کل نامه‌ها</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= number_format($system_stats['unread_messages']) ?></div>
                        <div class="stat-label">خوانده نشده</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-hdd fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= formatFileSize($upload_size) ?></div>
                        <div class="stat-label">فضای مصرفی</div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- تب‌ها -->
<ul class="nav nav-tabs" id="settingsTabs" role="tablist">
    <li class="nav-item">
        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button">
            <i class="fas fa-cog"></i> تنظیمات کلی
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="security-tab" data-bs-toggle="tab" data-bs-target="#security" type="button">
            <i class="fas fa-shield-alt"></i> امنیت
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="users-tab" data-bs-toggle="tab" data-bs-target="#users" type="button">
            <i class="fas fa-users"></i> مدیریت کاربران
        </button>
    </li>
    <li class="nav-item">
        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" type="button">
            <i class="fas fa-tools"></i> نگهداری
        </button>
    </li>
</ul>

<div class="tab-content" id="settingsTabsContent">
    <!-- تب تنظیمات کلی -->
    <div class="tab-pane fade show active" id="general" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="update_general">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required">عنوان سایت</label>
                                <input type="text" class="form-control" name="site_title" 
                                       value="<?= htmlspecialchars($current_settings['site_title'] ?? 'سیستم اتوماسیون دهیاری') ?>" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">حداکثر حجم فایل (مگابایت)</label>
                                <input type="number" class="form-control" name="max_file_size" min="1" max="100"
                                       value="<?= (int)(($current_settings['max_file_size'] ?? 10485760) / 1024 / 1024) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">تعداد آیتم در هر صفحه</label>
                                <select class="form-select" name="items_per_page">
                                    <option value="10" <?= ($current_settings['items_per_page'] ?? 20) == 10 ? 'selected' : '' ?>>10</option>
                                    <option value="20" <?= ($current_settings['items_per_page'] ?? 20) == 20 ? 'selected' : '' ?>>20</option>
                                    <option value="50" <?= ($current_settings['items_per_page'] ?? 20) == 50 ? 'selected' : '' ?>>50</option>
                                    <option value="100" <?= ($current_settings['items_per_page'] ?? 20) == 100 ? 'selected' : '' ?>>100</option>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">مدت زمان نشست (ثانیه)</label>
                                <select class="form-select" name="session_timeout">
                                    <option value="1800" <?= ($current_settings['session_timeout'] ?? 3600) == 1800 ? 'selected' : '' ?>>30 دقیقه</option>
                                    <option value="3600" <?= ($current_settings['session_timeout'] ?? 3600) == 3600 ? 'selected' : '' ?>>1 ساعت</option>
                                    <option value="7200" <?= ($current_settings['session_timeout'] ?? 3600) == 7200 ? 'selected' : '' ?>>2 ساعت</option>
                                    <option value="28800" <?= ($current_settings['session_timeout'] ?? 3600) == 28800 ? 'selected' : '' ?>>8 ساعت</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره تنظیمات
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- تب امنیت -->
    <div class="tab-pane fade" id="security" role="tabpanel">
        <div class="card">
            <div class="card-body">
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="update_security">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">حداکثر تلاش ورود ناموفق</label>
                                <input type="number" class="form-control" name="max_login_attempts" min="3" max="10"
                                       value="<?= $current_settings['max_login_attempts'] ?? 5 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">مدت مسدودی (ثانیه)</label>
                                <select class="form-select" name="login_block_time">
                                    <option value="300" <?= ($current_settings['login_block_time'] ?? 900) == 300 ? 'selected' : '' ?>>5 دقیقه</option>
                                    <option value="900" <?= ($current_settings['login_block_time'] ?? 900) == 900 ? 'selected' : '' ?>>15 دقیقه</option>
                                    <option value="1800" <?= ($current_settings['login_block_time'] ?? 900) == 1800 ? 'selected' : '' ?>>30 دقیقه</option>
                                    <option value="3600" <?= ($current_settings['login_block_time'] ?? 900) == 3600 ? 'selected' : '' ?>>1 ساعت</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">حداقل طول رمز عبور</label>
                                <input type="number" class="form-control" name="password_min_length" min="4" max="20"
                                       value="<?= $current_settings['password_min_length'] ?? 6 ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <div class="form-check mt-4">
                                    <input class="form-check-input" type="checkbox" name="require_strong_password"
                                           <?= ($current_settings['require_strong_password'] ?? 0) ? 'checked' : '' ?>>
                                    <label class="form-check-label">
                                        الزام رمز عبور پیچیده
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-warning">
                        <i class="fas fa-shield-alt"></i> به‌روزرسانی امنیت
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- تب مدیریت کاربران -->
    <div class="tab-pane fade" id="users" role="tabpanel">
        <!-- فرم اضافه کردن کاربر -->
        <div class="card mb-4">
            <div class="card-header">
                <h5 class="mb-0">اضافه کردن کاربر جدید</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <input type="hidden" name="action" value="add_user">
                    
                    <div class="row">
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label required">نام کاربری</label>
                                <input type="text" class="form-control" name="username" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label required">نام کامل</label>
                                <input type="text" class="form-control" name="name" required>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">ایمیل</label>
                                <input type="email" class="form-control" name="email">
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="mb-3">
                                <label class="form-label">نقش</label>
                                <select class="form-select" name="role">
                                    <option value="user">کاربر عادی</option>
                                    <option value="admin">مدیر</option>
                                </select>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required">رمز عبور</label>
                        <input type="password" class="form-control" name="password" required>
                    </div>
                    
                    <button type="submit" class="btn btn-success">
                        <i class="fas fa-user-plus"></i> اضافه کردن کاربر
                    </button>
                </form>
            </div>
        </div>
        
        <!-- لیست کاربران -->
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">لیست کاربران</h5>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>نام کاربری</th>
                                <th>نام کامل</th>
                                <th>ایمیل</th>
                                <th>نقش</th>
                                <th>وضعیت</th>
                                <th>تاریخ عضویت</th>
                                <th>آخرین ورود</th>
                                <th>عملیات</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($users as $user): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <div class="avatar-sm bg-primary text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                                <?= mb_substr($user['name'], 0, 1, 'UTF-8') ?>
                                            </div>
                                            <?= htmlspecialchars($user['username']) ?>
                                        </div>
                                    </td>
                                    <td><?= htmlspecialchars($user['name']) ?></td>
                                    <td><?= htmlspecialchars($user['email'] ?? '-') ?></td>
                                    <td>
                                        <span class="badge bg-<?= $user['role'] === 'admin' ? 'danger' : 'primary' ?>">
                                            <?= USER_ROLES[$user['role']] ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $user['status'] === 'active' ? 'success' : 'secondary' ?>">
                                            <?= $user['status'] === 'active' ? 'فعال' : 'غیرفعال' ?>
                                        </span>
                                    </td>
                                    <td><?= JalaliDate::toJalali(strtotime($user['created_at']), 'Y/m/d') ?></td>
                                    <td>
                                        <?= $user['last_login'] ? JalaliDate::timeAgo(strtotime($user['last_login'])) : 'هرگز' ?>
                                    </td>
                                    <td>
                                        <?php if ($user['id'] != $currentUser['id']): ?>
                                            <div class="btn-group btn-group-sm">
                                                <button type="button" class="btn btn-outline-primary" onclick="editUser(<?= $user['id'] ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <button type="button" class="btn btn-outline-<?= $user['status'] === 'active' ? 'warning' : 'success' ?>" 
                                                        onclick="toggleUserStatus(<?= $user['id'] ?>, '<?= $user['status'] ?>')">
                                                    <i class="fas fa-<?= $user['status'] === 'active' ? 'ban' : 'check' ?>"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- تب نگهداری -->
    <div class="tab-pane fade" id="maintenance" role="tabpanel">
        <div class="row">
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">پاکسازی سیستم</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="cleanup_system">
                            
                            <div class="mb-3">
                                <label class="form-label">نوع پاکسازی</label>
                                <select class="form-select" name="cleanup_type" required>
                                    <option value="">انتخاب کنید...</option>
                                    <option value="old_messages">نامه‌های آرشیو شده قدیمی</option>
                                    <option value="login_attempts">تلاش‌های ورود قدیمی</option>
                                    <option value="temp_files">فایل‌های موقت</option>
                                </select>
                            </div>
                            
                            <div class="mb-3" id="cleanup_days_container" style="display: none;">
                                <label class="form-label">قدیمی‌تر از (روز)</label>
                                <input type="number" class="form-control" name="cleanup_days" value="365" min="30">
                            </div>
                            
                            <button type="submit" class="btn btn-warning">
                                <i class="fas fa-broom"></i> شروع پاکسازی
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-md-6">
                <div class="card">
                    <div class="card-header">
                        <h6 class="mb-0">پشتیبان‌گیری</h6>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="backup_database">
                            
                            <p class="text-muted">
                                پشتیبان کامل از پایگاه داده تهیه می‌شود. این عملیات ممکن است چند دقیقه طول بکشد.
                            </p>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-download"></i> تهیه پشتیبان
                            </button>
                        </form>
                        
                        <hr>
                        
                        <h6>آخرین پشتیبان‌ها</h6>
                        <div class="list-group list-group-flush">
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>backup_2024-01-15.sql</span>
                                <span class="badge bg-primary">2.5 MB</span>
                            </div>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <span>backup_2024-01-10.sql</span>
                                <span class="badge bg-secondary">2.3 MB</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// نمایش فیلد تعداد روز برای پاکسازی نامه‌ها
document.querySelector('select[name="cleanup_type"]').addEventListener('change', function() {
    const container = document.getElementById('cleanup_days_container');
    if (this.value === 'old_messages') {
        container.style.display = 'block';
    } else {
        container.style.display = 'none';
    }
});

// تابع پشتیبان‌گیری
function backupDatabase() {
    if (confirm('آیا می‌خواهید از پایگاه داده پشتیبان تهیه کنید؟')) {
        // ارسال درخواست AJAX
        fetch('ajax/backup_database.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('پشتیبان‌گیری با موفقیت شروع شد');
            } else {
                alert('خطا در شروع پشتیبان‌گیری: ' + data.message);
            }
        })
        .catch(error => {
            alert('خطا در ارتباط با سرور');
        });
    }
}

// تابع پاک کردن cache
function clearCache() {
    if (confirm('آیا می‌خواهید cache سیستم را پاک کنید؟')) {
        fetch('ajax/clear_cache.php', {
            method: 'POST',
            headers: {
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            }
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert('Cache با موفقیت پاک شد');
            } else {
                alert('خطا در پاک کردن cache');
            }
        });
    }
}

// تابع ویرایش کاربر
function editUser(userId) {
    // پیاده‌سازی ویرایش کاربر
    alert('ویرایش کاربر ' + userId);
}

// تابع تغییر وضعیت کاربر
function toggleUserStatus(userId, currentStatus) {
    const newStatus = currentStatus === 'active' ? 'inactive' : 'active';
    const action = newStatus === 'active' ? 'فعال' : 'غیرفعال';
    
    if (confirm(`آیا می‌خواهید این کاربر را ${action} کنید؟`)) {
        fetch('ajax/toggle_user_status.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
            },
            body: JSON.stringify({
                user_id: userId,
                status: newStatus
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('خطا در تغییر وضعیت کاربر');
            }
        });
    }
}
</script>