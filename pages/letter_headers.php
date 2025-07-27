<?php
/**
 * Letter Headers Management Page
 * صفحه مدیریت سربرگ نامه‌ها
 */

Auth::requireLogin();

// بررسی دسترسی (فقط مدیران)
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
            case 'add_header':
                $title = Security::sanitize($_POST['title'] ?? '');
                $organization_name = Security::sanitize($_POST['organization_name'] ?? '');
                $address = Security::sanitize($_POST['address'] ?? '');
                $phone = Security::sanitize($_POST['phone'] ?? '');
                $email = Security::sanitize($_POST['email'] ?? '');
                $website = Security::sanitize($_POST['website'] ?? '');
                $is_default = isset($_POST['is_default']) ? 1 : 0;
                
                if (empty($title) || empty($organization_name)) {
                    $errors[] = 'عنوان و نام سازمان الزامی است';
                }
                
                // پردازش آپلود لوگو
                $logo_filename = null;
                if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($_FILES['logo_image']['type'], $allowed_types)) {
                        $errors[] = 'فقط فایل‌های تصویری JPG، PNG و GIF مجاز هستند';
                    } else {
                        $logo_filename = Security::uploadFile($_FILES['logo_image'], UPLOAD_PATH . 'logos/');
                    }
                }
                
                if (empty($errors)) {
                    // اگر این سربرگ پیش‌فرض باشد، سایر سربرگ‌ها را غیرپیش‌فرض کن
                    if ($is_default) {
                        $db->execute("UPDATE letter_headers SET is_default = 0");
                    }
                    
                    $header_id = $db->insert(
                        "INSERT INTO letter_headers (title, logo_image, organization_name, address, phone, email, website, is_default) 
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?)",
                        [$title, $logo_filename, $organization_name, $address, $phone, $email, $website, $is_default]
                    );
                    
                    if ($header_id) {
                        $success = 'سربرگ جدید با موفقیت اضافه شد';
                    }
                }
                break;
                
            case 'edit_header':
                $header_id = (int)($_POST['header_id'] ?? 0);
                $title = Security::sanitize($_POST['title'] ?? '');
                $organization_name = Security::sanitize($_POST['organization_name'] ?? '');
                $address = Security::sanitize($_POST['address'] ?? '');
                $phone = Security::sanitize($_POST['phone'] ?? '');
                $email = Security::sanitize($_POST['email'] ?? '');
                $website = Security::sanitize($_POST['website'] ?? '');
                $is_default = isset($_POST['is_default']) ? 1 : 0;
                
                if (empty($title) || empty($organization_name)) {
                    $errors[] = 'عنوان و نام سازمان الزامی است';
                }
                
                // پردازش آپلود لوگوی جدید
                $logo_filename = $_POST['existing_logo'] ?? null;
                if (isset($_FILES['logo_image']) && $_FILES['logo_image']['error'] === UPLOAD_ERR_OK) {
                    $allowed_types = ['image/jpeg', 'image/png', 'image/gif'];
                    if (!in_array($_FILES['logo_image']['type'], $allowed_types)) {
                        $errors[] = 'فقط فایل‌های تصویری JPG، PNG و GIF مجاز هستند';
                    } else {
                        // حذف لوگوی قبلی
                        if ($logo_filename) {
                            $old_logo_path = UPLOAD_PATH . 'logos/' . $logo_filename;
                            if (file_exists($old_logo_path)) {
                                unlink($old_logo_path);
                            }
                        }
                        $logo_filename = Security::uploadFile($_FILES['logo_image'], UPLOAD_PATH . 'logos/');
                    }
                }
                
                if (empty($errors)) {
                    // اگر این سربرگ پیش‌فرض باشد، سایر سربرگ‌ها را غیرپیش‌فرض کن
                    if ($is_default) {
                        $db->execute("UPDATE letter_headers SET is_default = 0");
                    }
                    
                    $affected = $db->execute(
                        "UPDATE letter_headers SET title = ?, logo_image = ?, organization_name = ?, address = ?, phone = ?, email = ?, website = ?, is_default = ? WHERE id = ?",
                        [$title, $logo_filename, $organization_name, $address, $phone, $email, $website, $is_default, $header_id]
                    );
                    
                    if ($affected > 0) {
                        $success = 'سربرگ با موفقیت ویرایش شد';
                    }
                }
                break;
                
            case 'delete_header':
                $header_id = (int)($_POST['header_id'] ?? 0);
                
                $header = $db->fetchRow(
                    "SELECT * FROM letter_headers WHERE id = ?",
                    [$header_id]
                );
                
                if ($header) {
                    // حذف لوگو
                    if ($header['logo_image']) {
                        $logo_path = UPLOAD_PATH . 'logos/' . $header['logo_image'];
                        if (file_exists($logo_path)) {
                            unlink($logo_path);
                        }
                    }
                    
                    $db->execute(
                        "DELETE FROM letter_headers WHERE id = ?",
                        [$header_id]
                    );
                    
                    $success = 'سربرگ با موفقیت حذف شد';
                    
                    // اگر سربرگ پیش‌فرض حذف شد، اولین سربرگ را پیش‌فرض کن
                    if ($header['is_default']) {
                        $db->execute(
                            "UPDATE letter_headers SET is_default = 1 WHERE id = (SELECT id FROM (SELECT id FROM letter_headers ORDER BY id LIMIT 1) as temp)"
                        );
                    }
                } else {
                    $errors[] = 'سربرگ مورد نظر یافت نشد';
                }
                break;
                
            case 'set_default':
                $header_id = (int)($_POST['header_id'] ?? 0);
                
                // همه را غیرپیش‌فرض کن
                $db->execute("UPDATE letter_headers SET is_default = 0");
                
                // انتخابی را پیش‌فرض کن
                $affected = $db->execute(
                    "UPDATE letter_headers SET is_default = 1 WHERE id = ?",
                    [$header_id]
                );
                
                if ($affected > 0) {
                    $success = 'سربرگ به عنوان پیش‌فرض انتخاب شد';
                }
                break;
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// دریافت لیست سربرگ‌ها
$headers = $db->fetchAll(
    "SELECT * FROM letter_headers ORDER BY is_default DESC, created_at DESC"
);

// ایجاد پوشه logos در صورت عدم وجود
$logos_dir = UPLOAD_PATH . 'logos/';
if (!is_dir($logos_dir)) {
    mkdir($logos_dir, 0755, true);
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-file-alt text-primary"></i>
                مدیریت سربرگ نامه‌ها
            </h1>
            <p class="text-muted mb-0">
                تنظیم سربرگ و اطلاعات سازمانی برای نامه‌های اداری
            </p>
        </div>
        <div class="col-md-6 text-end">
            <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHeaderModal">
                <i class="fas fa-plus"></i> سربرگ جدید
            </button>
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

<!-- لیست سربرگ‌ها -->
<div class="row">
    <?php if (empty($headers)): ?>
        <div class="col-12">
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-alt fa-4x text-muted mb-3"></i>
                    <h5 class="text-muted">سربرگی تعریف نشده</h5>
                    <p class="text-muted">برای تولید نامه‌های اداری، ابتدا سربرگ سازمانی خود را تعریف کنید.</p>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addHeaderModal">
                        <i class="fas fa-plus"></i> اولین سربرگ
                    </button>
                </div>
            </div>
        </div>
    <?php else: ?>
        <?php foreach ($headers as $header): ?>
            <div class="col-md-6 col-lg-4 mb-4">
                <div class="card header-card <?= $header['is_default'] ? 'border-success' : '' ?>">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h6 class="mb-0"><?= htmlspecialchars($header['title']) ?></h6>
                        <?php if ($header['is_default']): ?>
                            <span class="badge bg-success">پیش‌فرض</span>
                        <?php endif; ?>
                    </div>
                    <div class="card-body">
                        <?php if ($header['logo_image']): ?>
                            <div class="text-center mb-3">
                                <img src="<?= UPLOAD_URL . 'logos/' . $header['logo_image'] ?>" 
                                     alt="لوگوی <?= htmlspecialchars($header['organization_name']) ?>"
                                     class="img-fluid"
                                     style="max-height: 80px; border: 1px solid #ddd; border-radius: 5px; padding: 5px;">
                            </div>
                        <?php endif; ?>
                        
                        <h6 class="text-primary"><?= htmlspecialchars($header['organization_name']) ?></h6>
                        
                        <?php if ($header['address']): ?>
                            <p class="small text-muted mb-2">
                                <i class="fas fa-map-marker-alt"></i> <?= htmlspecialchars($header['address']) ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($header['phone']): ?>
                            <p class="small text-muted mb-2">
                                <i class="fas fa-phone"></i> <?= htmlspecialchars($header['phone']) ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($header['email']): ?>
                            <p class="small text-muted mb-2">
                                <i class="fas fa-envelope"></i> <?= htmlspecialchars($header['email']) ?>
                            </p>
                        <?php endif; ?>
                        
                        <?php if ($header['website']): ?>
                            <p class="small text-muted mb-2">
                                <i class="fas fa-globe"></i> <?= htmlspecialchars($header['website']) ?>
                            </p>
                        <?php endif; ?>
                        
                        <small class="text-muted d-block">
                            ایجاد: <?= JalaliDate::toJalali(strtotime($header['created_at']), 'Y/m/d') ?>
                        </small>
                    </div>
                    <div class="card-footer">
                        <div class="btn-group w-100">
                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                    onclick="editHeader(<?= $header['id'] ?>)">
                                <i class="fas fa-edit"></i> ویرایش
                            </button>
                            
                            <?php if (!$header['is_default']): ?>
                                <form method="POST" class="d-inline">
                                    <?= Security::csrfField() ?>
                                    <input type="hidden" name="action" value="set_default">
                                    <input type="hidden" name="header_id" value="<?= $header['id'] ?>">
                                    <button type="submit" class="btn btn-sm btn-success">
                                        <i class="fas fa-star"></i> پیش‌فرض
                                    </button>
                                </form>
                            <?php endif; ?>
                            
                            <form method="POST" class="d-inline" onsubmit="return confirm('آیا مطمئن هستید؟')">
                                <?= Security::csrfField() ?>
                                <input type="hidden" name="action" value="delete_header">
                                <input type="hidden" name="header_id" value="<?= $header['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                    <i class="fas fa-trash"></i> حذف
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<!-- مودال اضافه کردن سربرگ -->
<div class="modal fade" id="addHeaderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt"></i> اضافه کردن سربرگ جدید
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="addHeaderForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="add_header">
                
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required">عنوان سربرگ</label>
                                <input type="text" class="form-control" name="title" 
                                       placeholder="مثال: دهیاری محترم" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required">نام سازمان</label>
                                <input type="text" class="form-control" name="organization_name" 
                                       placeholder="مثال: دهیاری روستای نمونه" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">آدرس کامل</label>
                        <textarea class="form-control" name="address" rows="2" 
                                  placeholder="آدرس: استان - شهرستان - بخش - روستا"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تلفن</label>
                                <input type="text" class="form-control" name="phone" 
                                       placeholder="021-12345678">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">ایمیل</label>
                                <input type="email" class="form-control" name="email" 
                                       placeholder="info@dehyari.ir">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">وب‌سایت</label>
                                <input type="url" class="form-control" name="website" 
                                       placeholder="https://dehyari.ir">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">لوگوی سازمان</label>
                        <input type="file" class="form-control" name="logo_image" 
                               accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">
                            فرمت‌های مجاز: JPG, PNG, GIF | حداکثر 2 مگابایت
                        </div>
                    </div>
                    
                    <div id="logoPreview" class="text-center mb-3" style="display: none;">
                        <img id="previewLogo" class="img-fluid" style="max-height: 100px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" id="is_default">
                        <label class="form-check-label" for="is_default">
                            تنظیم به عنوان سربرگ پیش‌فرض
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره سربرگ
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- مودال ویرایش سربرگ -->
<div class="modal fade" id="editHeaderModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-edit"></i> ویرایش سربرگ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" enctype="multipart/form-data" id="editHeaderForm">
                <?= Security::csrfField() ?>
                <input type="hidden" name="action" value="edit_header">
                <input type="hidden" name="header_id" id="edit_header_id">
                <input type="hidden" name="existing_logo" id="edit_existing_logo">
                
                <div class="modal-body">
                    <!-- محتوای فرم ویرایش (مشابه فرم اضافه کردن) -->
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required">عنوان سربرگ</label>
                                <input type="text" class="form-control" name="title" id="edit_title" required>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label required">نام سازمان</label>
                                <input type="text" class="form-control" name="organization_name" id="edit_organization_name" required>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">آدرس کامل</label>
                        <textarea class="form-control" name="address" id="edit_address" rows="2"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">تلفن</label>
                                <input type="text" class="form-control" name="phone" id="edit_phone">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">ایمیل</label>
                                <input type="email" class="form-control" name="email" id="edit_email">
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="mb-3">
                                <label class="form-label">وب‌سایت</label>
                                <input type="url" class="form-control" name="website" id="edit_website">
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">لوگوی سازمان</label>
                        <input type="file" class="form-control" name="logo_image" 
                               accept="image/jpeg,image/png,image/gif">
                        <div class="form-text">
                            در صورت انتخاب فایل جدید، لوگوی قبلی جایگزین خواهد شد
                        </div>
                    </div>
                    
                    <div id="editCurrentLogo" class="text-center mb-3" style="display: none;">
                        <p class="small text-muted">لوگوی فعلی:</p>
                        <img id="currentLogoImg" class="img-fluid" style="max-height: 80px; border: 1px solid #ddd; border-radius: 5px;">
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_default" id="edit_is_default">
                        <label class="form-check-label" for="edit_is_default">
                            تنظیم به عنوان سربرگ پیش‌فرض
                        </label>
                    </div>
                </div>
                
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">انصراف</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> ذخیره تغییرات
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
// پیش‌نمایش لوگو برای فرم اضافه کردن
document.querySelector('#addHeaderForm input[name="logo_image"]').addEventListener('change', function(e) {
    previewLogo(e.target, 'logoPreview', 'previewLogo');
});

// پیش‌نمایش لوگو برای فرم ویرایش
document.querySelector('#editHeaderForm input[name="logo_image"]').addEventListener('change', function(e) {
    previewLogo(e.target, 'editCurrentLogo', 'currentLogoImg');
});

function previewLogo(input, containerId, imgId) {
    const file = input.files[0];
    const container = document.getElementById(containerId);
    const img = document.getElementById(imgId);
    
    if (file) {
        if (!['image/jpeg', 'image/png', 'image/gif'].includes(file.type)) {
            alert('فقط فایل‌های تصویری مجاز هستند');
            input.value = '';
            container.style.display = 'none';
            return;
        }
        
        if (file.size > 2 * 1024 * 1024) {
            alert('حجم فایل نباید بیش از 2 مگابایت باشد');
            input.value = '';
            container.style.display = 'none';
            return;
        }
        
        const reader = new FileReader();
        reader.onload = function(e) {
            img.src = e.target.result;
            container.style.display = 'block';
        };
        reader.readAsDataURL(file);
    }
}

// ویرایش سربرگ
function editHeader(headerId) {
    const headers = <?= json_encode($headers) ?>;
    const header = headers.find(h => h.id == headerId);
    
    if (header) {
        document.getElementById('edit_header_id').value = header.id;
        document.getElementById('edit_title').value = header.title;
        document.getElementById('edit_organization_name').value = header.organization_name;
        document.getElementById('edit_address').value = header.address || '';
        document.getElementById('edit_phone').value = header.phone || '';
        document.getElementById('edit_email').value = header.email || '';
        document.getElementById('edit_website').value = header.website || '';
        document.getElementById('edit_is_default').checked = header.is_default == 1;
        document.getElementById('edit_existing_logo').value = header.logo_image || '';
        
        // نمایش لوگوی فعلی
        const currentLogoDiv = document.getElementById('editCurrentLogo');
        const currentLogoImg = document.getElementById('currentLogoImg');
        
        if (header.logo_image) {
            currentLogoImg.src = '<?= UPLOAD_URL ?>logos/' + header.logo_image;
            currentLogoDiv.style.display = 'block';
        } else {
            currentLogoDiv.style.display = 'none';
        }
        
        const modal = new bootstrap.Modal(document.getElementById('editHeaderModal'));
        modal.show();
    }
}
</script>

<style>
.header-card {
    transition: all 0.3s ease;
}

.header-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
}

.border-success {
    border-color: #198754 !important;
    border-width: 2px !important;
}

.required::after {
    content: " *";
    color: red;
}
</style>