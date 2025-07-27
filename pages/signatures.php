<?php
/**
 * Signature Management Page - Fixed Version
 * صفحه مدیریت امضاهای دیجیتال - نسخه اصلاح شده
 */

Auth::requireLogin();

$success = '';
$errors = [];
$redirect = false;

// بررسی دسترسی (فقط مدیران و کاربران مجاز)
if (!in_array($currentUser['role'], ['admin', 'manager', 'supervisor', 'user'])) {
    $errors[] = 'شما دسترسی لازم برای این بخش را ندارید';
}

// بررسی اینکه آیا کاربر قبلاً امضا ثبت کرده یا نه
$existingSignature = $db->fetchRow(
    "SELECT * FROM digital_signatures WHERE user_id = ? LIMIT 1",
    [$currentUser['id']]
);

// پردازش فرم‌ها
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($errors)) {
    try {
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('درخواست نامعتبر است');
        }
        
        $action = $_POST['action'] ?? '';
        
        switch ($action) {
            case 'add_signature':
                // بررسی اینکه آیا کاربر قبلاً امضا ثبت کرده
                if ($existingSignature) {
                    throw new Exception('شما قبلاً امضای خود را ثبت کرده‌اید. امکان ثبت مجدد وجود ندارد.');
                }
                
                $signature_name = Security::sanitize($_POST['signature_name'] ?? '');
                $position_title = Security::sanitize($_POST['position_title'] ?? '');
                $organization_name = Security::sanitize($_POST['organization_name'] ?? '');
                
                if (empty($signature_name)) {
                    $errors[] = 'نام امضا الزامی است';
                }
                
                // پردازش آپلود تصویر امضا
                if (!isset($_FILES['signature_image']) || $_FILES['signature_image']['error'] !== UPLOAD_ERR_OK) {
                    $errors[] = 'آپلود تصویر امضا الزامی است';
                } else {
                    // اعتبارسنجی فایل تصویر
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($_FILES['signature_image']['type'], $allowed_types)) {
                        $errors[] = 'فقط فایل‌های تصویری JPG، PNG و GIF مجاز هستند';
                    }
                    
                    if ($_FILES['signature_image']['size'] > 2 * 1024 * 1024) {
                        $errors[] = 'حجم فایل نباید بیش از 2 مگابایت باشد';
                    }
                }
                
                if (empty($errors)) {
                    // آپلود فایل امضا
                    $signature_filename = Security::uploadFile($_FILES['signature_image'], UPLOAD_PATH . 'signatures/');
                    
                    // ثبت امضای جدید
                    $signature_id = $db->insert(
                        "INSERT INTO digital_signatures (user_id, signature_name, signature_image, position_title, organization_name, is_active) 
                         VALUES (?, ?, ?, ?, ?, 1)",
                        [$currentUser['id'], $signature_name, $signature_filename, $position_title, $organization_name]
                    );
                    
                    if ($signature_id) {
                        $success = 'امضای دیجیتال با موفقیت ثبت شد';
                        $redirect = true;
                        // به‌روزرسانی متغیر existingSignature
                        $existingSignature = $db->fetchRow(
                            "SELECT * FROM digital_signatures WHERE id = ?",
                            [$signature_id]
                        );
                    }
                }
                break;
                
            case 'delete_signature':
                if (!$existingSignature) {
                    throw new Exception('امضای قابل حذف یافت نشد');
                }
                
                // حذف فایل تصویر
                $signature_path = UPLOAD_PATH . 'signatures/' . $existingSignature['signature_image'];
                if (file_exists($signature_path)) {
                    unlink($signature_path);
                }
                
                // حذف از پایگاه داده
                $db->execute(
                    "DELETE FROM digital_signatures WHERE user_id = ?",
                    [$currentUser['id']]
                );
                
                $success = 'امضا با موفقیت حذف شد';
                $redirect = true;
                $existingSignature = null;
                break;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// اگر عملیات موفق بود، صفحه را reload کن
if ($redirect && empty($errors)) {
    echo "<script>
        setTimeout(function() {
            window.location.href = '?page=signatures&success=" . urlencode($success) . "';
        }, 1000);
    </script>";
}

// نمایش پیام موفقیت از URL
if (isset($_GET['success'])) {
    $success = $_GET['success'];
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-signature text-primary"></i>
                مدیریت امضای دیجیتال
            </h1>
            <p class="text-muted mb-0">
                امضای خود را برای نامه‌های اداری تعریف کنید
            </p>
        </div>
        <div class="col-md-6 text-end">
            <?php if (!$existingSignature): ?>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addSignatureModal">
                    <i class="fas fa-plus"></i> ثبت امضای من
                </button>
            <?php else: ?>
                <span class="badge bg-success fs-6">
                    <i class="fas fa-check-circle"></i> امضا ثبت شده
                </span>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- پیام‌های سیستم -->
<?php if ($success): ?>
    <div class="alert alert-success alert-dismissible fade show">
        <i class="fas fa-check-circle"></i> <?= htmlspecialchars($success) ?>
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    </div>
<?php endif; ?>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger">
        <h6><i class="fas fa-exclamation-triangle"></i> خطاهای زیر رخ داده است:</h6>
        <ul class="mb-0">
            <?php foreach ($errors as $error): ?>
                <li><?= htmlspecialchars($error) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<!-- نمایش امضا -->
<div class="row">
    <?php if (!$existingSignature): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-signature fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">امضای دیجیتالی ثبت نشده</h5>
                    <p class="text-muted">برای استفاده از قابلیت تولید نامه اداری، امضای خود را ثبت کنید.</p>
                    <div class="alert alert-info d-inline-block">
                        <i class="fas fa-info-circle"></i>
                        <strong>توجه:</strong> هر کاربر فقط یکبار می‌تواند امضای خود را ثبت کند
                    </div>
                    <br>
                    <button type="button" class="btn btn-primary mt-3" data-bs-toggle="modal" data-bs-target="#addSignatureModal">
                        <i class="fas fa-plus"></i> ثبت امضای من
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="col-12">
            <div class="card signature-card border-success">
                <div class="card-header bg-success text-white">
                    <div class="d-flex justify-content-between align-items-center">
                        <h6 class="mb-0">
                            <i class="fas fa-signature"></i> امضای شما
                        </h6>
                        <span class="badge bg-white text-success">فعال</span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 text-center">
                            <div class="signature-preview mb-3">
                                <img src="<?= UPLOAD_URL . 'signatures/' . $existingSignature['signature_image'] ?>" 
                                     alt="امضای <?= htmlspecialchars($existingSignature['signature_name']) ?>"
                                     class="img-fluid border rounded p-2"
                                     style="max-height: 150px; background: white;">
                            </div>
                        </div>
                        <div class="col-md-8">
                            <table class="table table-borderless">
                                <tr>
                                    <th width="30%">نام امضا:</th>
                                    <td><?= htmlspecialchars($existingSignature['signature_name']) ?></td>
                                </tr>
                                <?php if ($existingSignature['position_title']): ?>
                                <tr>
                                    <th>سمت:</th>
                                    <td><?= htmlspecialchars($existingSignature['position_title']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <?php if ($existingSignature['organization_name']): ?>
                                <tr>
                                    <th>سازمان:</th>
                                    <td><?= htmlspecialchars($existingSignature['organization_name']) ?></td>
                                </tr>
                                <?php endif; ?>
                                <tr>
                                    <th>تاریخ ثبت:</th>
                                    <td><?= JalaliDate::toJalali(strtotime($existingSignature['created_at']), 'Y/m/d H:i') ?></td>
                                </tr>
                                <tr>
                                    <th>وضعیت:</th>
                                    <td>
                                        <span class="badge bg-success">
                                            <i class="fas fa-check-circle"></i> فعال
                                        </span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="d-flex justify-content-between align-items-center">
                        <div class="text-muted">
                            <i class="fas fa-info-circle"></i>
                            امضای شما با موفقیت ثبت شده و در نامه‌های اداری استفاده می‌شود
                        </div>
                        <form method="POST" onsubmit="return confirm('آیا مطمئن هستید که می‌خواهید امضای خود را حذف کنید؟\\n\\nتوجه: پس از حذف، امکان ثبت مجدد امضا وجود ندارد.')">
                            <?= Security::csrfField() ?>
                            <input type="hidden" name="action" value="delete_signature">
                            <button type="submit" class="btn btn-outline-danger btn-sm">
                                <i class="fas fa-trash"></i> حذف امضا
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- راهنمای استفاده -->
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-question-circle text-info"></i> نحوه استفاده از امضا
                    </h6>
                </div>
                <div class="card-body">
                    <ol class="mb-0">
                        <li>به بخش <strong>صندوق دریافت</strong> بروید</li>
                        <li>روی نامه مورد نظر کلیک کنید</li>
                        <li>دکمه <strong>"نامه با سربرگ"</strong> را فشار دهید</li>
                        <li>نامه اداری با امضای شما تولید خواهد شد</li>
                        <li>می‌توانید نامه را چاپ یا دانلود کنید</li>
                    </ol>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- مودال اضافه کردن امضا -->
<?php if (!$existingSignature): ?>
<div class="modal fade" id="addSignatureModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-signature"></i> ثبت امضای دیجیتال
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="add_signature">
                
                <div class="modal-body">
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>توجه مهم:</strong> هر کاربر فقط یکبار می‌تواند امضای خود را ثبت کند. پس از ثبت، امکان ویرایش یا ثبت مجدد وجود ندارد.
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required">نام امضا</label>
                                <input type="text" class="form-control" name="signature_name" 
                                       placeholder="نام کامل شما" 
                                       value="<?= htmlspecialchars($currentUser['name']) ?>"
                                       required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">سمت/مقام</label>
                                <input type="text" class="form-control" name="position_title" 
                                       placeholder="مثال: دهیار روستا" 
                                       value="<?= $currentUser['role'] === 'admin' ? 'مدیر سیستم' : 'دهیار' ?>">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">نام سازمان</label>
                        <input type="text" class="form-control" name="organization_name" 
                               placeholder="نام دهیاری یا سازمان" 
                               value="دهیاری روستای نمونه">
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label required">تصویر امضا</label>
                        <input type="file" class="form-control" name="signature_image" 
                               accept="image/jpeg,image/png,image/gif" required>
                        <div class="form-text">
                            <strong>راهنما:</strong> فرمت‌های مجاز: JPG, PNG, GIF | حداکثر 2 مگابایت
                            <br>
                            تصویر امضا باید دارای پس‌زمینه شفاف یا سفید باشد
                        </div>
                    </div>
                    
                    <div id="signaturePreview" class="text-center" style="display: none;">
                        <div class="border rounded p-3 bg-light">
                            <img id="previewImg" class="img-fluid" style="max-height: 150px;">
                        </div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-lightbulb"></i>
                        <strong>نکات مهم:</strong>
                        <ul class="mb-0 mt-2">
                            <li>امضای خود را روی کاغذ سفید بنویسید</li>
                            <li>عکس با کیفیت از امضا بگیرید</li>
                            <li>پس‌زمینه اضافی را حذف کنید</li>
                            <li>فایل را در فرمت PNG یا JPG ذخیره کنید</li>
                            <li><strong>این امضا در تمام نامه‌های اداری شما استفاده خواهد شد</strong></li>
                        </ul>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ثبت نهایی امضا
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
// پیش‌نمایش تصویر امضا
document.addEventListener('DOMContentLoaded', function() {
    const fileInput = document.querySelector('input[name="signature_image"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const preview = document.getElementById('signaturePreview');
            const img = document.getElementById('previewImg');
            
            if (file) {
                // بررسی نوع فایل
                if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
                    alert('فقط فایل‌های تصویری مجاز هستند');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                // بررسی حجم فایل
                if (file.size > 2 * 1024 * 1024) {
                    alert('حجم فایل نباید بیش از 2 مگابایت باشد');
                    this.value = '';
                    preview.style.display = 'none';
                    return;
                }
                
                const reader = new FileReader();
                reader.onload = function(e) {
                    img.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            } else {
                preview.style.display = 'none';
            }
        });
    }
});

// ایجاد پوشه signatures در صورت عدم وجود
<?php
$signatures_dir = UPLOAD_PATH . 'signatures/';
if (!is_dir($signatures_dir)) {
    mkdir($signatures_dir, 0755, true);
}
?>
</script>

<style>
.signature-card {
    transition: all 0.3s ease;
}

.signature-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 5px 15px rgba(0,0,0,0.1);
}

.signature-preview img {
    max-width: 100%;
    height: auto;
}

.border-success {
    border-color: #198754 !important;
    border-width: 2px !important;
}

.required::after {
    content: " *";
    color: red;
    font-weight: bold;
}

.alert {
    border-radius: 8px;
}

.card {
    border-radius: 10px;
    overflow: hidden;
}
</style>