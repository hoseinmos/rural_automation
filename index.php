<?php
/**
 * Main Index File - Rural Automation System (Updated)
 * فایل اصلی سیستم اتوماسیون دهیاری - نسخه به‌روزرسانی شده
 */
header('Content-Type: text/html; charset=utf-8');
mb_internal_encoding('UTF-8');
// بررسی نصب سیستم
if (!file_exists('installed.lock')) {
    header('Location: install.php');
    exit;
}

// بارگذاری فایل‌های مورد نیاز
require_once 'config/config.php';
require_once 'config/database.php';
require_once 'includes/auth.php';
require_once 'includes/security.php';
require_once 'includes/jalali.php';
require_once 'includes/utils.php';
require_once 'includes/message_permissions.php';

// تنظیم charset برای نمایش صحیح فارسی
header('Content-Type: text/html; charset=utf-8');

// شروع session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// دریافت صفحه درخواستی
$page = $_GET['page'] ?? 'login';
$allowedPages = [
    'login', 'dashboard', 'compose', 'inbox', 'sent', 'view', 'reports', 
    'profile', 'settings', 'signatures', 'letter_headers', 'template_designer', 'logout'
];

if (!in_array($page, $allowedPages)) {
    $page = 'login';
}

// بررسی ورود کاربر برای صفحات محافظت شده
if ($page !== 'login' && !Auth::isLoggedIn()) {
    $page = 'login';
}

// بررسی دسترسی صفحات مدیریتی
$adminPages = ['settings', 'letter_headers', 'template_designer'];
if (in_array($page, $adminPages) && !Auth::isAdmin()) {
    $page = 'dashboard';
    $_GET['error'] = 'access_denied';
}

// پردازش logout
if ($page === 'logout') {
    $auth = Auth::getInstance();
    $auth->logout();
    header('Location: index.php?logged_out=1');
    exit;
}

// متغیرهای کلی
$pageTitle = 'سیستم اتوماسیون اداری دهیاری';
$currentUser = Auth::getCurrentUser();
$db = Database::getInstance();

// پیام‌های سیستم
$systemMessage = '';
$systemMessageType = '';

if (isset($_GET['installed'])) {
    $systemMessage = 'سیستم با موفقیت نصب شد. می‌توانید وارد شوید.';
    $systemMessageType = 'success';
} elseif (isset($_GET['logged_out'])) {
    $systemMessage = 'با موفقیت خارج شدید.';
    $systemMessageType = 'info';
} elseif (isset($_GET['timeout'])) {
    $systemMessage = 'نشست شما منقضی شده است. لطفاً مجدداً وارد شوید.';
    $systemMessageType = 'warning';
} elseif (isset($_GET['error'])) {
    switch ($_GET['error']) {
        case 'access_denied':
            $systemMessage = 'شما دسترسی لازم برای مشاهده این صفحه را ندارید.';
            $systemMessageType = 'danger';
            break;
        case 'message_not_found':
            $systemMessage = 'نامه مورد نظر یافت نشد.';
            $systemMessageType = 'warning';
            break;
        case 'user_not_found':
            $systemMessage = 'کاربر مورد نظر یافت نشد.';
            $systemMessageType = 'warning';
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- فونت وزیر -->
    <link href="https://fonts.googleapis.com/css2?family=Vazir:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="assets/style.css" rel="stylesheet">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= Security::generateCSRFToken() ?>">
</head>
<body class="<?= $page === 'login' ? 'login-page' : 'dashboard-page' ?>">

<?php if ($page === 'login'): ?>
    <!-- صفحه ورود -->
    <?php include 'pages/login.php'; ?>
<?php else: ?>
    <!-- نوار ناوبری -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary fixed-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="?page=dashboard">
                <i class="fas fa-building"></i> <?= SITE_TITLE ?>
            </a>
            
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                            <i class="fas fa-tachometer-alt"></i> داشبورد
                        </a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'compose' ? 'active' : '' ?>" href="?page=compose">
                            <i class="fas fa-pen"></i> نامه جدید
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-envelope"></i> نامه‌ها
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?page=inbox"><i class="fas fa-inbox"></i> صندوق دریافت</a></li>
                            <li><a class="dropdown-item" href="?page=sent"><i class="fas fa-paper-plane"></i> ارسالی</a></li>
                        </ul>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="?page=reports">
                            <i class="fas fa-chart-bar"></i> گزارش‌ها
                        </a>
                    </li>
                </ul>
                
                <div class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-user"></i> <?= htmlspecialchars($currentUser['name']) ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end">
                            <li><a class="dropdown-item" href="?page=profile"><i class="fas fa-user-edit"></i> پروفایل</a></li>
                            <li><a class="dropdown-item" href="?page=signatures"><i class="fas fa-signature"></i> مدیریت امضا</a></li>
                            <?php if (Auth::isAdmin()): ?>
                                <li><hr class="dropdown-divider"></li>
                                <li><a class="dropdown-item" href="?page=settings"><i class="fas fa-cog"></i> تنظیمات سیستم</a></li>
                                <li><a class="dropdown-item" href="?page=letter_headers"><i class="fas fa-file-alt"></i> مدیریت سربرگ‌ها</a></li>
                                <li><a class="dropdown-item" href="?page=template_designer"><i class="fas fa-palette"></i> طراحی قالب نامه</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="?page=logout"><i class="fas fa-sign-out-alt"></i> خروج</a></li>
                        </ul>
                    </li>
                </div>
            </div>
        </div>
    </nav>

    <!-- نوار کناری -->
    <div class="sidebar">
        <div class="sidebar-header">
            <h5><i class="fas fa-building"></i> دهیاری</h5>
        </div>
        <ul class="nav flex-column sidebar-nav">
            <li class="nav-item">
                <a class="nav-link <?= $page === 'dashboard' ? 'active' : '' ?>" href="?page=dashboard">
                    <i class="fas fa-tachometer-alt"></i> داشبورد
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'compose' ? 'active' : '' ?>" href="?page=compose">
                    <i class="fas fa-pen"></i> نامه جدید
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'inbox' ? 'active' : '' ?>" href="?page=inbox">
                    <i class="fas fa-inbox"></i> صندوق دریافت
                    <?php
                    // نمایش تعداد پیام‌های خوانده نشده
                    $unreadCount = $db->fetchRow(
                        "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND status = 'unread'",
                        [$currentUser['id']]
                    )['count'];
                    if ($unreadCount > 0):
                    ?>
                        <span class="badge bg-danger unread-count"><?= Utils::toPersianNumber($unreadCount) ?></span>
                    <?php endif; ?>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'sent' ? 'active' : '' ?>" href="?page=sent">
                    <i class="fas fa-paper-plane"></i> نامه‌های ارسالی
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'reports' ? 'active' : '' ?>" href="?page=reports">
                    <i class="fas fa-chart-bar"></i> گزارش‌گیری
                </a>
            </li>
            
            <!-- بخش کاربری -->
            <li class="nav-item mt-3">
                <h6 class="nav-header">امضا و مدارک</h6>
            </li>
            <li class="nav-item">
                <a class="nav-link <?= $page === 'signatures' ? 'active' : '' ?>" href="?page=signatures">
                    <i class="fas fa-signature"></i> مدیریت امضا
                </a>
            </li>
            
            <?php if (Auth::isAdmin()): ?>
                <li class="nav-item mt-3">
                    <h6 class="nav-header">مدیریت سیستم</h6>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'settings' ? 'active' : '' ?>" href="?page=settings">
                        <i class="fas fa-cog"></i> تنظیمات سیستم
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'letter_headers' ? 'active' : '' ?>" href="?page=letter_headers">
                        <i class="fas fa-file-alt"></i> مدیریت سربرگ‌ها
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?= $page === 'template_designer' ? 'active' : '' ?>" href="?page=template_designer">
                        <i class="fas fa-palette"></i> طراحی قالب نامه
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <div class="sidebar-footer">
            <div class="user-info">
                <div class="d-flex align-items-center">
                    <div class="avatar">
                        <i class="fas fa-user"></i>
                    </div>
                    <div class="ms-2">
                        <div class="fw-bold"><?= htmlspecialchars($currentUser['name']) ?></div>
                        <small class="text-muted"><?= htmlspecialchars($currentUser['username']) ?></small>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- محتوای اصلی -->
    <main class="main-content">
        <div class="container-fluid py-4">
            
            <!-- نمایش پیام‌های سیستم -->
            <?php if ($systemMessage): ?>
                <div class="alert alert-<?= $systemMessageType ?> alert-dismissible fade show" role="alert">
                    <?= $systemMessage ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            <?php endif; ?>
            
            <?php
            // بارگذاری صفحه درخواستی
            $pageFile = "pages/{$page}.php";
            if (file_exists($pageFile)) {
                include $pageFile;
            } else {
                echo '<div class="alert alert-danger">صفحه مورد نظر یافت نشد.</div>';
            }
            ?>
            
        </div>
    </main>
<?php endif; ?>

<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

<!-- jQuery -->
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

<!-- html2canvas برای تبدیل HTML به تصویر -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Custom JS -->
<script src="assets/script.js"></script>

<!-- توکن CSRF برای AJAX -->
<script>
    $.ajaxSetup({
        headers: {
            'X-CSRF-TOKEN': $('meta[name="csrf-token"]').attr('content')
        }
    });
    
    // اضافه کردن Utils به window برای دسترسی آسان
    window.Utils = window.Utils || {};
    
    // تابع کمکی برای تبدیل اعداد انگلیسی به فارسی
    window.Utils.toPersianNumber = function(num) {
        const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
        return num.toString().replace(/\d/g, digit => persianDigits[digit]);
    };
</script>

</body>
</html>