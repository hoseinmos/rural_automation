<?php
/**
 * New Inbox Page Design - with Letter Template Feature
 * طراحی جدید صندوق ورودی - با قابلیت تولید نامه اداری
 */

Auth::requireLogin();

// پارامترهای فیلتر و صفحه‌بندی
$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page_num - 1) * $limit;

$search = Security::sanitize($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';

// ساخت شرایط WHERE
$where_conditions = ["m.receiver_id = ?"];
$params = [$currentUser['id']];

// فیلترها
if ($status_filter && array_key_exists($status_filter, MESSAGE_STATUSES)) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

if ($priority_filter && in_array($priority_filter, ['low', 'normal', 'high', 'urgent'])) {
    $where_conditions[] = "m.priority = ?";
    $params[] = $priority_filter;
}

if ($search) {
    $where_conditions[] = "(m.subject LIKE ? OR m.content LIKE ? OR u.name LIKE ?)";
    $search_term = "%$search%";
    $params[] = $search_term;
    $params[] = $search_term;
    $params[] = $search_term;
}

$where_clause = implode(' AND ', $where_conditions);

// شمارش کل رکوردها
$total_count = $db->fetchRow(
    "SELECT COUNT(*) as count 
     FROM messages m 
     JOIN users u ON m.sender_id = u.id 
     WHERE $where_clause",
    $params
)['count'];

$total_pages = ceil($total_count / $limit);

// دریافت نامه‌ها با اطلاعات امضا فرستنده
$messages = $db->fetchAll(
    "SELECT m.*, 
            u.name as sender_name, 
            u.username as sender_username,
            ds.signature_image,
            ds.position_title,
            ds.organization_name
     FROM messages m 
     JOIN users u ON m.sender_id = u.id 
     LEFT JOIN digital_signatures ds ON u.id = ds.user_id AND ds.is_active = 1
     WHERE $where_clause
     ORDER BY 
        CASE WHEN m.status = 'unread' THEN 0 ELSE 1 END,
        CASE m.priority 
            WHEN 'urgent' THEN 1 
            WHEN 'high' THEN 2 
            WHEN 'normal' THEN 3 
            WHEN 'low' THEN 4 
        END,
        m.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

// آمار صندوق دریافت
$inbox_stats = $db->fetchRow(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'unread' THEN 1 ELSE 0 END) as unread,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today
     FROM messages WHERE receiver_id = ?",
    [$currentUser['id']]
);
?>

<div class="container-fluid">
    <!-- هدر صفحه -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="d-flex justify-content-between align-items-center">
                <h2 class="text-primary">
                    <i class="fas fa-inbox"></i> نامه های دریافتی
                </h2>
                <div class="d-flex gap-2">
                    <button class="btn btn-success" onclick="window.location.href='?page=compose'">
                        <i class="fas fa-plus"></i> نامه جدید
                    </button>
                    <button class="btn btn-warning" onclick="refreshInbox()">
                        <i class="fas fa-sync"></i> بروزرسانی
                    </button>
                    <button class="btn btn-info" onclick="exportToExcel()">
                        <i class="fas fa-file-excel"></i> خروجی اکسل
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- فیلترها -->
    <div class="row mb-4">
        <div class="col-12">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <input type="hidden" name="page" value="inbox">
                        
                        <div class="col-md-3">
                            <input type="text" class="form-control" name="search" 
                                   value="<?= htmlspecialchars($search) ?>" 
                                   placeholder="جستجو...">
                        </div>
                        
                        <div class="col-md-2">
                            <select class="form-select" name="status">
                                <option value="">وضعیت نامه</option>
                                <?php foreach (MESSAGE_STATUSES as $key => $value): ?>
                                    <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>>
                                        <?= $value ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <select class="form-select" name="priority">
                                <option value="">نوع نامه</option>
                                <option value="urgent" <?= $priority_filter === 'urgent' ? 'selected' : '' ?>>فوری</option>
                                <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>مهم</option>
                                <option value="normal" <?= $priority_filter === 'normal' ? 'selected' : '' ?>>عادی</option>
                                <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>کم اهمیت</option>
                            </select>
                        </div>
                        
                        <div class="col-md-2">
                            <input type="text" class="form-control" placeholder="فرستنده نامه">
                        </div>
                        
                        <div class="col-md-1">
                            <input type="date" class="form-control" placeholder="از تاریخ">
                        </div>
                        
                        <div class="col-md-1">
                            <input type="date" class="form-control" placeholder="تا تاریخ">
                        </div>
                        
                        <div class="col-md-1">
                            <button type="submit" class="btn btn-success w-100">جستجو</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- دکمه‌های عملیات -->
    <div class="row mb-3">
        <div class="col-12">
            <div class="d-flex gap-2">
                <button class="btn btn-primary" onclick="exportTable()">
                    <i class="fas fa-download"></i> خروجی اکسل
                </button>
                <button class="btn btn-secondary" onclick="readSelected()">
                    <i class="fas fa-eye"></i> خوانده شده
                </button>
                <button class="btn btn-secondary" onclick="unreadSelected()">
                    <i class="fas fa-eye-slash"></i> خوانده نشده
                </button>
                <button class="btn btn-warning" onclick="selectAll()">
                    <i class="fas fa-check-square"></i> انتخاب همه
                </button>
                <button class="btn btn-light" onclick="clearSelection()">
                    <i class="fas fa-square"></i> لغو انتخاب
                </button>
            </div>
        </div>
    </div>

    <!-- جدول نامه‌ها -->
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0" id="messagesTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="50">
                                        <input type="checkbox" class="form-check-input" id="selectAllCheckbox">
                                    </th>
                                    <th width="100">ردیف</th>
                                    <th width="150">تاریخ ایجاد</th>
                                    <th width="150">تاریخ ویرایش</th>
                                    <th width="200">شماره نامه</th>
                                    <th>موضوع نامه</th>
                                    <th width="150">فرستنده نامه</th>
                                    <th width="100">نوع نامه</th>
                                    <th width="100">وضعیت نامه</th>
                                    <th width="100">پیوست</th>
                                    <th width="200">عملیات</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($messages)): ?>
                                    <tr>
                                        <td colspan="11" class="text-center py-5">
                                            <i class="fas fa-inbox fa-3x text-muted mb-3"></i>
                                            <h5 class="text-muted">نامه‌ای یافت نشد</h5>
                                        </td>
                                    </tr>
                                <?php else: ?>
                                    <?php foreach ($messages as $index => $message): ?>
                                        <tr class="message-row <?= $message['status'] === 'unread' ? 'table-warning' : '' ?>" 
                                            data-id="<?= $message['id'] ?>">
                                            <td>
                                                <input type="checkbox" class="form-check-input message-checkbox" 
                                                       value="<?= $message['id'] ?>">
                                            </td>
                                            <td class="text-center">
                                                <?= Utils::toPersianNumber($offset + $index + 1) ?>
                                            </td>
                                            <td>
                                                <?= JalaliDate::toJalali(strtotime($message['created_at']), 'Y/m/d') ?>
                                            </td>
                                            <td>
                                                <?= $message['updated_at'] ? JalaliDate::toJalali(strtotime($message['updated_at']), 'Y/m/d') : '-' ?>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($message['message_number'] ?: '-') ?>
                                            </td>
                                            <td>
                                                <a href="?page=view&id=<?= $message['id'] ?>" 
                                                   class="text-decoration-none fw-bold text-primary">
                                                    <?= htmlspecialchars($message['subject']) ?>
                                                </a>
                                            </td>
                                            <td>
                                                <?= htmlspecialchars($message['sender_name']) ?>
                                                <br>
                                                <small class="text-muted"><?= htmlspecialchars($message['sender_username']) ?></small>
                                            </td>
                                            <td>
                                                <?php
                                                $priority_badges = [
                                                    'urgent' => 'danger',
                                                    'high' => 'warning', 
                                                    'normal' => 'primary',
                                                    'low' => 'secondary'
                                                ];
                                                $priority_labels = [
                                                    'urgent' => 'فوری',
                                                    'high' => 'مهم',
                                                    'normal' => 'عادی',
                                                    'low' => 'کم اهمیت'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $priority_badges[$message['priority']] ?>">
                                                    <?= $priority_labels[$message['priority']] ?>
                                                </span>
                                            </td>
                                            <td>
                                                <?php
                                                $status_badges = [
                                                    'unread' => 'warning',
                                                    'read' => 'success',
                                                    'replied' => 'info',
                                                    'archived' => 'secondary'
                                                ];
                                                ?>
                                                <span class="badge bg-<?= $status_badges[$message['status']] ?>">
                                                    <?= MESSAGE_STATUSES[$message['status']] ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <?php if ($message['attachment']): ?>
                                                    <i class="fas fa-paperclip text-success" title="دارای پیوست"></i>
                                                <?php else: ?>
                                                    <span class="text-muted">ندارد</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group btn-group-sm">
                                                    <!-- دکمه مشاهده عادی -->
                                                    <a href="?page=view&id=<?= $message['id'] ?>" 
                                                       class="btn btn-outline-primary" title="مشاهده">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    
                                                    <!-- دکمه مشاهده با سربرگ - همیشه فعال -->
                                                    <button type="button" 
                                                            class="btn btn-success" 
                                                            onclick="generateOfficialLetter(<?= $message['id'] ?>)"
                                                            title="مشاهده نامه با سربرگ اداری">
                                                        <i class="fas fa-file-alt"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- صفحه‌بندی -->
    <?php if ($total_pages > 1): ?>
        <div class="row mt-4">
            <div class="col-12">
                <nav>
                    <ul class="pagination justify-content-center">
                        <?php if ($page_num > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=inbox&p=<?= $page_num - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page' && $k !== 'p', ARRAY_FILTER_USE_KEY)) ?>">
                                    قبلی
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start = max(1, $page_num - 2);
                        $end = min($total_pages, $page_num + 2);
                        
                        for ($i = $start; $i <= $end; $i++):
                        ?>
                            <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                                <a class="page-link" href="?page=inbox&p=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page' && $k !== 'p', ARRAY_FILTER_USE_KEY)) ?>">
                                    <?= Utils::toPersianNumber($i) ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page_num < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=inbox&p=<?= $page_num + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page' && $k !== 'p', ARRAY_FILTER_USE_KEY)) ?>">
                                    بعدی
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
                
                <div class="text-center text-muted">
                    اکنون در صفحه: <?= Utils::toPersianNumber($page_num) ?> از <?= Utils::toPersianNumber($total_pages) ?>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- مودال پیش‌نمایش نامه اداری -->
<div class="modal fade" id="officialLetterModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-file-alt"></i> نامه اداری با سربرگ
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="letterContent" class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">بستن</button>
                <button type="button" class="btn btn-primary" id="downloadLetterBtn">
                    <i class="fas fa-download"></i> دانلود تصویر
                </button>
                <button type="button" class="btn btn-success" id="printLetterBtn">
                    <i class="fas fa-print"></i> چاپ
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// تولید نامه اداری با امضا
function generateOfficialLetter(messageId) {
    const modal = new bootstrap.Modal(document.getElementById('officialLetterModal'));
    const content = document.getElementById('letterContent');
    
    // نمایش loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">در حال تولید نامه...</span>
            </div>
            <p class="mt-2">لطفاً صبر کنید...</p>
        </div>
    `;
    
    modal.show();
    
    // درخواست تولید نامه
    fetch(`ajax/generate_official_letter_new.php?id=${messageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = data.letterHtml;
                
                // تنظیم دکمه دانلود
                document.getElementById('downloadLetterBtn').onclick = function() {
                    downloadLetterAsImage(messageId);
                };
                
                // تنظیم دکمه چاپ
                document.getElementById('printLetterBtn').onclick = function() {
                    printLetter();
                };
            } else {
                content.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        خطا در تولید نامه: ${data.message || 'خطای نامشخص'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    خطا در ارتباط با سرور
                </div>
            `;
        });
}

// دانلود نامه به صورت تصویر
function downloadLetterAsImage(messageId) {
    const letterContent = document.getElementById('letterContent');
    
    // استفاده از html2canvas برای تبدیل HTML به تصویر
    html2canvas(letterContent, {
        scale: 2,
        useCORS: true,
        backgroundColor: '#ffffff'
    }).then(canvas => {
        const link = document.createElement('a');
        link.download = `official_letter_${messageId}_${Date.now()}.png`;
        link.href = canvas.toDataURL();
        link.click();
        
        // نمایش پیام موفقیت
        const alert = document.createElement('div');
        alert.className = 'alert alert-success alert-dismissible fade show position-fixed';
        alert.style.cssText = 'top: 20px; right: 20px; z-index: 9999;';
        alert.innerHTML = `
            <i class="fas fa-check-circle"></i> نامه اداری دانلود شد
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        document.body.appendChild(alert);
        
        setTimeout(() => {
            if (alert.parentNode) {
                alert.parentNode.removeChild(alert);
            }
        }, 3000);
        
    }).catch(error => {
        console.error('Error generating image:', error);
        alert('خطا در تولید تصویر');
    });
}

// چاپ نامه
function printLetter() {
    const printContent = document.getElementById('letterContent').innerHTML;
    const originalContent = document.body.innerHTML;
    
    document.body.innerHTML = printContent;
    window.print();
    document.body.innerHTML = originalContent;
    
    // بازگرداندن event listenerها
    location.reload();
}

// سایر توابع جدول
function selectAll() {
    document.querySelectorAll('.message-checkbox').forEach(checkbox => {
        checkbox.checked = true;
    });
    document.getElementById('selectAllCheckbox').checked = true;
}

function clearSelection() {
    document.querySelectorAll('.message-checkbox').forEach(checkbox => {
        checkbox.checked = false;
    });
    document.getElementById('selectAllCheckbox').checked = false;
}

function refreshInbox() {
    window.location.reload();
}

function exportToExcel() {
    if (typeof TableManager !== 'undefined') {
        TableManager.exportToExcel('messagesTable', 'inbox_messages.csv');
    } else {
        // fallback export
        alert('در حال توسعه...');
    }
}

// انتخاب همه با checkbox هدر
document.getElementById('selectAllCheckbox').addEventListener('change', function() {
    if (this.checked) {
        selectAll();
    } else {
        clearSelection();
    }
});

// بارگذاری کتابخانه html2canvas در صورت عدم وجود
if (!window.html2canvas) {
    const script = document.createElement('script');
    script.src = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    document.head.appendChild(script);
}
</script>

<style>
.message-row:hover {
    background-color: #f8f9fa;
}

.table-warning {
    background-color: #fff3cd !important;
}

.btn-group-sm .btn {
    font-size: 0.775rem;
    padding: 0.25rem 0.5rem;
}

#letterContent {
    min-height: 400px;
    background: white;
    padding: 20px;
    border: 1px solid #ddd;
}

@media print {
    body * {
        visibility: hidden;
    }
    
    #letterContent, 
    #letterContent * {
        visibility: visible;
    }
    
    #letterContent {
        position: absolute;
        left: 0;
        top: 0;
        width: 100%;
    }
}
</style>