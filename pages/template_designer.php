<?php
/**
 * Letter Template Designer Page
 * صفحه طراحی قالب نامه
 */

Auth::requireLogin();

// بررسی دسترسی (فقط مدیران)
if (!Auth::isAdmin()) {
    header('Location: ?page=dashboard&error=access_denied');
    exit;
}

$success = '';
$errors = [];

// پردازش آپلود تصویر پس‌زمینه
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    try {
        if (!Security::verifyCSRFToken($_POST['csrf_token'] ?? '')) {
            throw new Exception('درخواست نامعتبر است');
        }
        
        if ($_POST['action'] === 'upload_background') {
            if (!isset($_FILES['background_image']) || $_FILES['background_image']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('لطفاً تصویر پس‌زمینه را انتخاب کنید');
            }
            
            // بررسی نوع فایل
            $allowed_types = ['image/jpeg', 'image/jpg', 'image/png'];
            if (!in_array($_FILES['background_image']['type'], $allowed_types)) {
                throw new Exception('فقط فایل‌های JPG و PNG مجاز هستند');
            }
            
            // آپلود فایل
            $filename = Security::uploadFile($_FILES['background_image'], UPLOAD_PATH . 'templates/');
            
            // ذخیره در دیتابیس
            $template_id = $db->insert(
                "INSERT INTO letter_templates (background_image) VALUES (?) 
                 ON DUPLICATE KEY UPDATE background_image = VALUES(background_image)",
                [$filename]
            );
            
            $success = 'تصویر پس‌زمینه با موفقیت آپلود شد';
        }
        
        if ($_POST['action'] === 'save_positions') {
            $positions = json_decode($_POST['positions'], true);
            $template_id = 1; // فعلاً یک قالب داریم
            
            foreach ($positions as $field) {
                $db->execute(
                    "UPDATE template_fields SET 
                     x_position = ?, y_position = ?, width = ?, height = ?, font_size = ?
                     WHERE template_id = ? AND field_name = ?",
                    [
                        $field['x'], $field['y'], 
                        $field['width'] ?? null, 
                        $field['height'] ?? null,
                        $field['fontSize'] ?? 14,
                        $template_id, $field['name']
                    ]
                );
            }
            
            $success = 'موقعیت فیلدها با موفقیت ذخیره شد';
        }
        
    } catch (Exception $e) {
        $errors[] = $e->getMessage();
    }
}

// دریافت قالب فعلی
$template = $db->fetchRow("SELECT * FROM letter_templates WHERE is_active = 1 LIMIT 1");
$fields = [];
if ($template) {
    $fields = $db->fetchAll(
        "SELECT * FROM template_fields WHERE template_id = ? ORDER BY id",
        [$template['id']]
    );
}

// ایجاد پوشه templates در صورت عدم وجود
$templates_dir = UPLOAD_PATH . 'templates/';
if (!is_dir($templates_dir)) {
    mkdir($templates_dir, 0755, true);
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-palette text-primary"></i>
                طراحی قالب نامه
            </h1>
            <p class="text-muted mb-0">
                تنظیم موقعیت فیلدهای نامه روی تصویر پس‌زمینه
            </p>
        </div>
        <div class="col-md-6 text-end">
            <?php if ($template): ?>
                <button type="button" class="btn btn-success" onclick="savePositions()">
                    <i class="fas fa-save"></i> ذخیره موقعیت‌ها
                </button>
                <button type="button" class="btn btn-warning" onclick="resetPositions()">
                    <i class="fas fa-undo"></i> بازنشانی
                </button>
            <?php endif; ?>
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
    <!-- بخش آپلود و طراحی -->
    <div class="col-lg-9">
        <?php if (!$template || !$template['background_image']): ?>
            <!-- فرم آپلود تصویر پس‌زمینه -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-upload"></i> آپلود تصویر پس‌زمینه
                    </h5>
                </div>
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="upload_background">
                        
                        <div class="mb-3">
                            <label class="form-label">تصویر نامه خام (A5)</label>
                            <input type="file" class="form-control" name="background_image" 
                                   accept="image/jpeg,image/jpg,image/png" required>
                            <div class="form-text">
                                - اندازه پیشنهادی: A5 (148×210 میلی‌متر) با کیفیت 300 DPI<br>
                                - فرمت‌های مجاز: JPG, PNG<br>
                                - حداکثر حجم: 5 مگابایت
                            </div>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-upload"></i> آپلود تصویر
                        </button>
                    </form>
                </div>
            </div>
        <?php else: ?>
            <!-- محیط طراحی -->
            <div class="card">
                <div class="card-header">
                    <h5 class="mb-0">
                        <i class="fas fa-edit"></i> محیط طراحی - فیلدها را بکشید و رها کنید
                    </h5>
                </div>
                <div class="card-body p-0">
                    <div id="designCanvas" style="position: relative; margin: 20px auto; overflow: auto;">
                        <!-- تصویر پس‌زمینه -->
                        <img id="backgroundImage" 
                             src="<?= UPLOAD_URL . 'templates/' . $template['background_image'] ?>" 
                             style="width: 100%; max-width: 595px; display: block; margin: 0 auto;">
                        
                        <!-- فیلدهای قابل جابجایی -->
                        <div id="draggableFields" style="position: absolute; top: 0; left: 0; width: 100%; height: 100%;">
                            <?php
                            $fieldLabels = [
                                'message_number' => 'شماره نامه',
                                'date' => 'تاریخ',
                                'subject' => 'موضوع',
                                'receiver_name' => 'گیرنده',
                                'sender_name' => 'فرستنده',
                                'content' => 'متن نامه',
                                'signature' => 'امضا',
                                'stamp_place' => 'محل مهر'
                            ];
                            
                            foreach ($fields as $field):
                                $isTextArea = $field['field_name'] === 'content';
                                $isImage = $field['field_name'] === 'signature';
                                $isStamp = $field['field_name'] === 'stamp_place';
                            ?>
                                <div class="draggable-field" 
                                     data-field="<?= $field['field_name'] ?>"
                                     data-fontsize="<?= $field['font_size'] ?>"
                                     style="position: absolute; 
                                            left: <?= $field['x_position'] ?>px; 
                                            top: <?= $field['y_position'] ?>px;
                                            <?php if ($field['width']): ?>width: <?= $field['width'] ?>px;<?php endif; ?>
                                            <?php if ($field['height']): ?>height: <?= $field['height'] ?>px;<?php endif; ?>
                                            cursor: move;
                                            border: 2px dashed #007bff;
                                            background: rgba(255,255,255,0.8);
                                            padding: 5px;
                                            font-size: <?= $field['font_size'] ?>px;
                                            font-family: 'B Nazanin', 'Tahoma';
                                            text-align: right;
                                            color: #000;">
                                    
                                    <?php if ($isImage): ?>
                                        <div style="text-align: center; opacity: 0.5;">
                                            <i class="fas fa-signature fa-2x"></i><br>
                                            <small>امضا</small>
                                        </div>
                                    <?php elseif ($isStamp): ?>
                                        <div style="text-align: center; opacity: 0.5;">
                                            <div style="border: 2px dashed #666; border-radius: 50%; 
                                                        width: 80px; height: 80px; margin: 0 auto;
                                                        display: flex; align-items: center; justify-content: center;">
                                                محل مهر
                                            </div>
                                        </div>
                                    <?php else: ?>
                                        <?= $fieldLabels[$field['field_name']] ?? $field['field_name'] ?>
                                    <?php endif; ?>
                                    
                                    <?php if ($isTextArea || $field['width']): ?>
                                        <div class="resize-handle" style="position: absolute; bottom: 0; right: 0; 
                                                width: 10px; height: 10px; background: #007bff; cursor: se-resize;"></div>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <div class="text-center text-muted">
                        <i class="fas fa-info-circle"></i>
                        فیلدها را می‌توانید با ماوس جابجا کنید | کادر متن را می‌توانید تغییر اندازه دهید
                    </div>
                </div>
            </div>
            
            <!-- فرم تغییر تصویر پس‌زمینه -->
            <div class="card mt-3">
                <div class="card-body">
                    <form method="POST" enctype="multipart/form-data" class="row align-items-end">
                        <?= Security::csrfField() ?>
                        <input type="hidden" name="action" value="upload_background">
                        
                        <div class="col-md-8">
                            <label class="form-label">تغییر تصویر پس‌زمینه</label>
                            <input type="file" class="form-control" name="background_image" 
                                   accept="image/jpeg,image/jpg,image/png" required>
                        </div>
                        <div class="col-md-4">
                            <button type="submit" class="btn btn-warning w-100">
                                <i class="fas fa-sync"></i> تغییر پس‌زمینه
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- پنل تنظیمات -->
    <div class="col-lg-3">
        <?php if ($template && $template['background_image']): ?>
            <div class="card">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-cog"></i> تنظیمات فیلد انتخابی
                    </h6>
                </div>
                <div class="card-body">
                    <div id="fieldSettings" style="display: none;">
                        <div class="mb-3">
                            <label>نام فیلد:</label>
                            <input type="text" class="form-control" id="selectedFieldName" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label>اندازه فونت:</label>
                            <input type="number" class="form-control" id="fontSize" min="8" max="48" value="14">
                        </div>
                        
                        <div class="mb-3">
                            <label>موقعیت X:</label>
                            <input type="number" class="form-control" id="posX" readonly>
                        </div>
                        
                        <div class="mb-3">
                            <label>موقعیت Y:</label>
                            <input type="number" class="form-control" id="posY" readonly>
                        </div>
                        
                        <div id="sizeControls" style="display: none;">
                            <div class="mb-3">
                                <label>عرض:</label>
                                <input type="number" class="form-control" id="fieldWidth">
                            </div>
                            
                            <div class="mb-3">
                                <label>ارتفاع:</label>
                                <input type="number" class="form-control" id="fieldHeight">
                            </div>
                        </div>
                    </div>
                    
                    <div id="noSelection" class="text-center text-muted">
                        <i class="fas fa-hand-pointer fa-2x mb-2"></i>
                        <p>روی یک فیلد کلیک کنید تا تنظیمات آن را ببینید</p>
                    </div>
                </div>
            </div>
            
            <!-- راهنما -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-question-circle"></i> راهنما
                    </h6>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled mb-0">
                        <li class="mb-2">
                            <i class="fas fa-mouse-pointer text-primary"></i>
                            فیلدها را با ماوس بکشید
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-expand-arrows-alt text-success"></i>
                            گوشه کادر متن را برای تغییر اندازه بکشید
                        </li>
                        <li class="mb-2">
                            <i class="fas fa-save text-warning"></i>
                            در انتها حتماً ذخیره کنید
                        </li>
                        <li>
                            <i class="fas fa-font text-info"></i>
                            اندازه فونت را از پنل تنظیم کنید
                        </li>
                    </ul>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- اسکریپت Drag & Drop -->
<script>
<?php if ($template && $template['background_image']): ?>
// متغیرهای سراسری
let selectedField = null;
let isDragging = false;
let isResizing = false;
let startX = 0;
let startY = 0;
let startLeft = 0;
let startTop = 0;
let startWidth = 0;
let startHeight = 0;

// راه‌اندازی Drag & Drop
document.addEventListener('DOMContentLoaded', function() {
    const fields = document.querySelectorAll('.draggable-field');
    const canvas = document.getElementById('draggableFields');
    const bgImage = document.getElementById('backgroundImage');
    
    // محاسبه نسبت تصویر
    function getImageScale() {
        const naturalWidth = bgImage.naturalWidth || bgImage.width;
        const displayWidth = bgImage.offsetWidth;
        return displayWidth / naturalWidth;
    }
    
    fields.forEach(field => {
        // رویداد کلیک برای انتخاب
        field.addEventListener('click', function(e) {
            e.stopPropagation();
            selectField(this);
        });
        
        // رویداد شروع Drag
        field.addEventListener('mousedown', function(e) {
            if (e.target.classList.contains('resize-handle')) {
                // شروع Resize
                isResizing = true;
                selectedField = this;
                startX = e.clientX;
                startY = e.clientY;
                startWidth = parseInt(window.getComputedStyle(this).width, 10);
                startHeight = parseInt(window.getComputedStyle(this).height, 10);
                e.preventDefault();
            } else {
                // شروع Drag
                isDragging = true;
                selectedField = this;
                const rect = this.getBoundingClientRect();
                const parentRect = canvas.getBoundingClientRect();
                startX = e.clientX;
                startY = e.clientY;
                startLeft = rect.left - parentRect.left;
                startTop = rect.top - parentRect.top;
                this.style.cursor = 'grabbing';
                e.preventDefault();
            }
        });
    });
    
    // رویداد حرکت ماوس
    document.addEventListener('mousemove', function(e) {
        if (isDragging && selectedField) {
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            const scale = getImageScale();
            
            selectedField.style.left = (startLeft + dx) + 'px';
            selectedField.style.top = (startTop + dy) + 'px';
            
            updateFieldSettings();
        } else if (isResizing && selectedField) {
            const dx = e.clientX - startX;
            const dy = e.clientY - startY;
            
            selectedField.style.width = Math.max(50, startWidth + dx) + 'px';
            selectedField.style.height = Math.max(30, startHeight + dy) + 'px';
            
            updateFieldSettings();
        }
    });
    
    // رویداد رها کردن ماوس
    document.addEventListener('mouseup', function() {
        if (selectedField) {
            selectedField.style.cursor = 'move';
        }
        isDragging = false;
        isResizing = false;
    });
    
    // کلیک خارج از فیلدها
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.draggable-field') && !e.target.closest('#fieldSettings')) {
            deselectAllFields();
        }
    });
});

// انتخاب فیلد
function selectField(field) {
    deselectAllFields();
    field.classList.add('selected');
    field.style.borderColor = '#28a745';
    selectedField = field;
    
    // نمایش تنظیمات
    document.getElementById('fieldSettings').style.display = 'block';
    document.getElementById('noSelection').style.display = 'none';
    
    // پر کردن فرم تنظیمات
    const fieldName = field.getAttribute('data-field');
    const labels = {
        'message_number': 'شماره نامه',
        'date': 'تاریخ',
        'subject': 'موضوع',
        'receiver_name': 'گیرنده',
        'sender_name': 'فرستنده',
        'content': 'متن نامه',
        'signature': 'امضا',
        'stamp_place': 'محل مهر'
    };
    
    document.getElementById('selectedFieldName').value = labels[fieldName] || fieldName;
    document.getElementById('fontSize').value = field.getAttribute('data-fontsize') || 14;
    
    updateFieldSettings();
    
    // نمایش/مخفی کردن کنترل‌های اندازه
    const needsSize = ['content', 'signature', 'stamp_place'].includes(fieldName);
    document.getElementById('sizeControls').style.display = needsSize ? 'block' : 'none';
}

// لغو انتخاب همه
function deselectAllFields() {
    document.querySelectorAll('.draggable-field').forEach(field => {
        field.classList.remove('selected');
        field.style.borderColor = '#007bff';
    });
    document.getElementById('fieldSettings').style.display = 'none';
    document.getElementById('noSelection').style.display = 'block';
    selectedField = null;
}

// به‌روزرسانی تنظیمات فیلد
function updateFieldSettings() {
    if (!selectedField) return;
    
    const rect = selectedField.getBoundingClientRect();
    const parentRect = document.getElementById('draggableFields').getBoundingClientRect();
    
    document.getElementById('posX').value = Math.round(rect.left - parentRect.left);
    document.getElementById('posY').value = Math.round(rect.top - parentRect.top);
    
    if (selectedField.style.width) {
        document.getElementById('fieldWidth').value = parseInt(selectedField.style.width, 10);
    }
    if (selectedField.style.height) {
        document.getElementById('fieldHeight').value = parseInt(selectedField.style.height, 10);
    }
}

// تغییر اندازه فونت
document.getElementById('fontSize').addEventListener('input', function() {
    if (selectedField) {
        const size = this.value;
        selectedField.style.fontSize = size + 'px';
        selectedField.setAttribute('data-fontsize', size);
    }
});

// تغییر عرض
document.getElementById('fieldWidth').addEventListener('input', function() {
    if (selectedField) {
        selectedField.style.width = this.value + 'px';
    }
});

// تغییر ارتفاع
document.getElementById('fieldHeight').addEventListener('input', function() {
    if (selectedField) {
        selectedField.style.height = this.value + 'px';
    }
});

// ذخیره موقعیت‌ها
function savePositions() {
    const fields = document.querySelectorAll('.draggable-field');
    const positions = [];
    
    fields.forEach(field => {
        const rect = field.getBoundingClientRect();
        const parentRect = document.getElementById('draggableFields').getBoundingClientRect();
        
        positions.push({
            name: field.getAttribute('data-field'),
            x: Math.round(rect.left - parentRect.left),
            y: Math.round(rect.top - parentRect.top),
            width: field.style.width ? parseInt(field.style.width, 10) : null,
            height: field.style.height ? parseInt(field.style.height, 10) : null,
            fontSize: field.getAttribute('data-fontsize') || 14
        });
    });
    
    // ارسال به سرور
    const form = document.createElement('form');
    form.method = 'POST';
    form.innerHTML = `
        <?= Security::csrfField() ?>
        <input type="hidden" name="action" value="save_positions">
        <input type="hidden" name="positions" value='${JSON.stringify(positions)}'>
    `;
    document.body.appendChild(form);
    form.submit();
}

// بازنشانی موقعیت‌ها
function resetPositions() {
    if (confirm('آیا از بازنشانی موقعیت‌ها اطمینان دارید؟')) {
        window.location.reload();
    }
}
<?php endif; ?>
</script>

<style>
.draggable-field {
    user-select: none;
    transition: border-color 0.3s;
}

.draggable-field:hover {
    border-color: #28a745 !important;
}

.draggable-field.selected {
    box-shadow: 0 0 10px rgba(40, 167, 69, 0.5);
}

.resize-handle {
    opacity: 0;
    transition: opacity 0.3s;
}

.draggable-field:hover .resize-handle,
.draggable-field.selected .resize-handle {
    opacity: 1;
}

#designCanvas {
    background: #f8f9fa;
    border: 2px solid #dee2e6;
    border-radius: 8px;
    position: relative;
}
</style>