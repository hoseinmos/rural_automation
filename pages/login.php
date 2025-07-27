<?php
/**
 * Login Page - Rural Automation System
 * صفحه ورود به سیستم اتوماسیون دهیاری
 */

// اگر کاربر قبلاً وارد شده، به داشبورد هدایت شود
if (Auth::isLoggedIn()) {
    header('Location: ?page=dashboard');
    exit;
}

$error = '';
$success = '';

// پردازش فرم ورود
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // بررسی CSRF token
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('درخواست نامعتبر است');
        }
        
        // بررسی rate limiting
        if (!Security::rateLimit('login', 5, 300)) { // 5 تلاش در 5 دقیقه
            throw new Exception('تعداد تلاش‌های ورود بیش از حد مجاز است. لطفاً 5 دقیقه صبر کنید.');
        }
        
        $username = Security::sanitize($_POST['username'] ?? '');
        $password = $_POST['password'] ?? '';
        $remember = isset($_POST['remember']);
        
        if (empty($username) || empty($password)) {
            throw new Exception('نام کاربری و رمز عبور الزامی است');
        }
        
        $auth = Auth::getInstance();
        if ($auth->login($username, $password, $remember)) {
            // ورود موفق - هدایت به داشبورد
            header('Location: ?page=dashboard');
            exit;
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// پردازش فراموشی رمز عبور (ساده)
if (isset($_POST['forgot_password'])) {
    $email = Security::sanitize($_POST['email'] ?? '');
    if (!empty($email)) {
        // اینجا می‌توانید کد ارسال ایمیل بازیابی رمز عبور را اضافه کنید
        $success = 'لینک بازیابی رمز عبور به ایمیل شما ارسال شد (در نسخه آینده پیاده‌سازی خواهد شد)';
    }
}

// بررسی پیام‌های URL
if (isset($_GET['timeout'])) {
    $error = 'نشست شما منقضی شده است. لطفاً مجدداً وارد شوید.';
}
if (isset($_GET['logged_out'])) {
    $success = 'با موفقیت خارج شدید.';
}
?>

<!DOCTYPE html>
<html lang="fa" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ورود به <?= SITE_TITLE ?></title>
    
    <!-- Bootstrap 5 RTL -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css" rel="stylesheet">
    
    <!-- فونت وزیر -->
    <link href="https://fonts.googleapis.com/css2?family=Vazir:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    
    <!-- CSRF Token -->
    <meta name="csrf-token" content="<?= Security::generateCSRFToken() ?>">
    
    <style>
        body {
            font-family: 'Vazir', sans-serif;
            background: #f5f6fa;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }

        .login-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 420px;
            overflow: hidden;
        }

        .login-header {
            background: linear-gradient(135deg, #e91e63, #c2185b);
            color: white;
            padding: 2rem;
            text-align: center;
        }

        .logo {
            width: 60px;
            height: 60px;
            background: rgba(255,255,255,0.2);
            border-radius: 50%;
            margin: 0 auto 1rem;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }

        .login-body {
            padding: 2rem;
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        .form-label {
            font-weight: 600;
            margin-bottom: 0.5rem;
            color: #333;
            display: block;
            text-align: right;
        }

        .form-control {
            padding: 0.875rem 1rem;
            border: 2px solid #e1e5e9;
            border-radius: 8px;
            font-size: 1rem;
            transition: border-color 0.3s;
            height: 45px;
        }

        .form-control:focus {
            border-color: #e91e63;
            box-shadow: 0 0 0 3px rgba(233, 30, 99, 0.1);
        }

        .btn-login {
            width: 100%;
            padding: 0.75rem;
            background: #e91e63;
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            transition: all 0.3s;
        }

        .btn-login:hover {
            background: #c2185b;
            transform: translateY(-1px);
        }

        .btn-login:disabled {
            background: #ccc;
            cursor: not-allowed;
            transform: none;
        }

        .alert {
            border-radius: 8px;
            border: none;
            margin-bottom: 1rem;
        }

        .alert-danger {
            background: #ffeaea;
            color: #d63384;
            border-right: 4px solid #dc3545;
        }

        .alert-success {
            background: #eafaf1;
            color: #0f5132;
            border-right: 4px solid #198754;
        }

        .forgot-link {
            text-align: center;
            margin-top: 1rem;
        }

        .forgot-link a {
            color: #e91e63;
            text-decoration: none;
            font-size: 0.9rem;
        }

        .forgot-link a:hover {
            text-decoration: underline;
        }

        .login-footer {
            background: #f8f9fa;
            padding: 1rem;
            text-align: center;
            font-size: 0.85rem;
            color: #666;
            border-top: 1px solid #eee;
        }

        .demo-info {
            background: #f8f9fa;
            border-radius: 8px;
            padding: 1rem;
            margin-top: 1rem;
            font-size: 0.85rem;
            text-align: center;
        }

        .demo-credentials {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.5rem;
            margin-top: 0.5rem;
        }

        .demo-item {
            background: white;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 0.5rem;
            font-size: 0.8rem;
            cursor: pointer;
            transition: all 0.3s;
        }

        .demo-item:hover {
            background: #e91e63;
            color: white;
            border-color: #e91e63;
        }

        .password-toggle {
            position: absolute;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: #666;
            cursor: pointer;
            padding: 0.25rem;
        }

        .input-group {
            position: relative;
        }

        @media (max-width: 576px) {
            .login-card {
                margin: 10px;
            }
            
            .login-header, 
            .login-body {
                padding: 1.5rem;
            }

            .demo-credentials {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="login-card">
        <!-- هدر -->
        <div class="login-header">
            <div class="logo">
                <i class="fas fa-building"></i>
            </div>
            <h4><?= SITE_TITLE ?></h4>
            <p class="mb-0">ورود به سیستم</p>
        </div>

        <!-- فرم ورود -->
        <div class="login-body">
            
            <!-- نمایش پیام‌های خطا -->
            <?php if ($error): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
                </div>
            <?php endif; ?>
            
            <!-- نمایش پیام‌های موفقیت -->
            <?php if ($success): ?>
                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
                </div>
            <?php endif; ?>

            <form method="POST" id="loginForm">
                <?= Security::csrfField() ?>
                
                <div class="form-group">
                    <label class="form-label">نام کاربری</label>
                    <input type="text" 
                           class="form-control" 
                           name="username" 
                           id="username"
                           value="<?= htmlspecialchars($_POST['username'] ?? '') ?>"
                           required 
                           autofocus>
                </div>

                <div class="form-group">
                    <label class="form-label">رمز عبور</label>
                    <div class="input-group">
                        <input type="password" 
                               class="form-control" 
                               name="password" 
                               id="password"
                               required>
                        <button type="button" class="password-toggle" onclick="togglePassword()">
                            <i class="fas fa-eye" id="passwordToggleIcon"></i>
                        </button>
                    </div>
                </div>

                <div class="form-check mb-3">
                    <input class="form-check-input" 
                           type="checkbox" 
                           name="remember" 
                           id="remember"
                           <?= isset($_POST['remember']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="remember">
                        مرا به خاطر بسپار
                    </label>
                </div>

                <button type="submit" class="btn-login" id="loginBtn">
                    <i class="fas fa-sign-in-alt"></i> ورود به سیستم
                </button>
            </form>

            <div class="forgot-link">
                <a href="#" data-bs-toggle="modal" data-bs-target="#forgotPasswordModal">
                    <i class="fas fa-question-circle"></i> رمز عبور را فراموش کرده‌اید؟
                </a>
            </div>

            <!-- اطلاعات ورود پیش‌فرض -->
            <?php if (defined('DEBUG_MODE') && DEBUG_MODE): ?>
                <div class="demo-info">
                    <div class="text-muted mb-2">
                        <small><strong>اطلاعات ورود آزمایشی:</strong></small>
                    </div>
                    <div class="demo-credentials">
                        <div class="demo-item" onclick="fillCredentials('admin', 'admin123')">
                            <strong>مدیر:</strong> admin
                        </div>
                        <div class="demo-item" onclick="fillCredentials('salimi', 'password')">
                            <strong>کاربر:</strong> salimi
                        </div>
                    </div>
                    <small class="text-muted d-block mt-2">کلیک کنید تا پر شود</small>
                </div>
            <?php endif; ?>
        </div>

        <!-- فوتر -->
        <div class="login-footer">
            <?php if (defined('SITE_VERSION')): ?>
                نسخه <?= SITE_VERSION ?> | 
            <?php endif; ?>
            <i class="fas fa-calendar-alt"></i> 
            <?php if (class_exists('JalaliDate')): ?>
                <?= JalaliDate::now('Y/m/d') ?>
            <?php else: ?>
                <?= date('Y/m/d') ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- مودال فراموشی رمز عبور -->
    <div class="modal fade" id="forgotPasswordModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-key"></i> بازیابی رمز عبور
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                
                <form method="POST">
                    <?= Security::csrfField() ?>
                    <div class="modal-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i>
                            آدرس ایمیل خود را وارد کنید تا لینک بازیابی برای شما ارسال شود.
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">آدرس ایمیل</label>
                            <input type="email" class="form-control" name="email" required>
                        </div>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                        <button type="submit" name="forgot_password" class="btn btn-primary">
                            <i class="fas fa-paper-plane"></i> ارسال لینک بازیابی
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // تابع نمایش/پنهان کردن رمز عبور
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const toggleIcon = document.getElementById('passwordToggleIcon');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                toggleIcon.classList.remove('fa-eye');
                toggleIcon.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                toggleIcon.classList.remove('fa-eye-slash');
                toggleIcon.classList.add('fa-eye');
            }
        }

        // پر کردن اطلاعات ورود (فقط در حالت DEBUG)
        function fillCredentials(username, password) {
            document.getElementById('username').value = username;
            document.getElementById('password').value = password;
            
            // افکت بصری
            const usernameInput = document.getElementById('username');
            const passwordInput = document.getElementById('password');
            
            usernameInput.style.borderColor = '#28a745';
            passwordInput.style.borderColor = '#28a745';
            
            setTimeout(() => {
                usernameInput.style.borderColor = '#e1e5e9';
                passwordInput.style.borderColor = '#e1e5e9';
            }, 1000);
        }

        // اعتبارسنجی فرم
        document.getElementById('loginForm').addEventListener('submit', function(e) {
            const username = document.getElementById('username').value.trim();
            const password = document.getElementById('password').value;
            const loginBtn = document.getElementById('loginBtn');
            
            if (username.length < 3) {
                e.preventDefault();
                alert('نام کاربری باید حداقل 3 کاراکتر باشد');
                document.getElementById('username').focus();
                return false;
            }
            
            if (password.length < 3) {
                e.preventDefault();
                alert('رمز عبور باید حداقل 3 کاراکتر باشد');
                document.getElementById('password').focus();
                return false;
            }

            // نمایش حالت لودینگ
            loginBtn.disabled = true;
            loginBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> در حال ورود...';
        });

        // میانبرهای کیبورد
        document.addEventListener('keydown', function(e) {
            // Ctrl + L = فوکوس روی نام کاربری
            if (e.ctrlKey && e.key === 'l') {
                e.preventDefault();
                document.getElementById('username').focus();
            }
        });

        // پر کردن خودکار در صورت وجود پارامتر demo در URL
        const urlParams = new URLSearchParams(window.location.search);
        if (urlParams.get('demo') === '1') {
            if (typeof fillCredentials === 'function') {
                fillCredentials('admin', 'admin123');
            }
        }

        // حذف پیام‌های خطا بعد از 5 ثانیه
        setTimeout(function() {
            const alerts = document.querySelectorAll('.alert');
            alerts.forEach(function(alert) {
                alert.style.transition = 'opacity 0.5s';
                alert.style.opacity = '0';
                setTimeout(() => alert.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>