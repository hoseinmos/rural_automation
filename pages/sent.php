<?php
/**
 * Sent Messages Page
 * صفحه نامه‌های ارسالی
 */

Auth::requireLogin();

// پارامترهای فیلتر و صفحه‌بندی
$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page_num - 1) * $limit;

$search = Security::sanitize($_GET['search'] ?? '');
$status_filter = $_GET['status'] ?? '';
$priority_filter = $_GET['priority'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// ساخت شرایط WHERE
$where_conditions = ["m.sender_id = ?"];
$params = [$currentUser['id']];

// فیلتر بر اساس وضعیت
if ($status_filter && array_key_exists($status_filter, MESSAGE_STATUSES)) {
    $where_conditions[] = "m.status = ?";
    $params[] = $status_filter;
}

// فیلتر بر اساس اولویت
if ($priority_filter && in_array($priority_filter, ['low', 'normal', 'high', 'urgent'])) {
    $where_conditions[] = "m.priority = ?";
    $params[] = $priority_filter;
}

// فیلتر بر اساس تاریخ
if ($date_from) {
    $where_conditions[] = "DATE(m.created_at) >= ?";
    $params[] = $date_from;
}

if ($date_to) {
    $where_conditions[] = "DATE(m.created_at) <= ?";
    $params[] = $date_to;
}

// جستجو در موضوع و متن
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
     JOIN users u ON m.receiver_id = u.id 
     WHERE $where_clause",
    $params
)['count'];

$total_pages = ceil($total_count / $limit);

// دریافت نامه‌ها
$messages = $db->fetchAll(
    "SELECT m.*, u.name as receiver_name, u.username as receiver_username
     FROM messages m 
     JOIN users u ON m.receiver_id = u.id 
     WHERE $where_clause
     ORDER BY m.created_at DESC
     LIMIT $limit OFFSET $offset",
    $params
);

// آمار نامه‌های ارسالی
$sent_stats = $db->fetchRow(
    "SELECT 
        COUNT(*) as total,
        SUM(CASE WHEN status = 'read' THEN 1 ELSE 0 END) as read_count,
        SUM(CASE WHEN status = 'replied' THEN 1 ELSE 0 END) as replied_count,
        SUM(CASE WHEN priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count
     FROM messages WHERE sender_id = ?",
    [$currentUser['id']]
);
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-paper-plane text-primary"></i>
                نامه‌های ارسالی
            </h1>
            <p class="text-muted mb-0">
                مدیریت نامه‌های ارسال شده
            </p>
        </div>
        <div class="col-md-6 text-end">
            <a href="?page=compose" class="btn btn-primary">
                <i class="fas fa-pen"></i> نامه جدید
            </a>
        </div>
    </div>
</div>

<!-- آمار سریع -->
<div class="row mb-4">
    <div class="col-md-2-4 col-6">
        <div class="card border-primary">
            <div class="card-body text-center">
                <div class="h3 text-primary"><?= number_format($sent_stats['total']) ?></div>
                <small class="text-muted">کل ارسالی</small>
            </div>
        </div>
    </div>
    <div class="col-md-2-4 col-6">
        <div class="card border-success">
            <div class="card-body text-center">
                <div class="h3 text-success"><?= number_format($sent_stats['read_count']) ?></div>
                <small class="text-muted">خوانده شده</small>
            </div>
        </div>
    </div>
    <div class="col-md-2-4 col-6">
        <div class="card border-info">
            <div class="card-body text-center">
                <div class="h3 text-info"><?= number_format($sent_stats['replied_count']) ?></div>
                <small class="text-muted">پاسخ داده شده</small>
            </div>
        </div>
    </div>
    <div class="col-md-2-4 col-6">
        <div class="card border-danger">
            <div class="card-body text-center">
                <div class="h3 text-danger"><?= number_format($sent_stats['urgent_count']) ?></div>
                <small class="text-muted">فوری</small>
            </div>
        </div>
    </div>
    <div class="col-md-2-4 col-6">
        <div class="card border-warning">
            <div class="card-body text-center">
                <div class="h3 text-warning"><?= number_format($sent_stats['today_count']) ?></div>
                <small class="text-muted">امروز</small>
            </div>
        </div>
    </div>
</div>

<!-- فیلترها و جستجو -->
<div class="card mb-4">
    <div class="card-header">
        <h6 class="mb-0">
            <i class="fas fa-filter"></i> فیلتر و جستجو
        </h6>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="sent">
            
            <div class="col-md-4">
                <label class="form-label">جستجو</label>
                <div class="input-group">
                    <input type="text" class="form-control" name="search" 
                           value="<?= htmlspecialchars($search) ?>" 
                           placeholder="جستجو در موضوع، متن یا گیرنده">
                    <button class="btn btn-outline-secondary" type="submit">
                        <i class="fas fa-search"></i>
                    </button>
                </div>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">وضعیت</label>
                <select class="form-select" name="status">
                    <option value="">همه وضعیت‌ها</option>
                    <?php foreach (MESSAGE_STATUSES as $key => $value): ?>
                        <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>>
                            <?= $value ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">اولویت</label>
                <select class="form-select" name="priority">
                    <option value="">همه اولویت‌ها</option>
                    <option value="urgent" <?= $priority_filter === 'urgent' ? 'selected' : '' ?>>فوری</option>
                    <option value="high" <?= $priority_filter === 'high' ? 'selected' : '' ?>>زیاد</option>
                    <option value="normal" <?= $priority_filter === 'normal' ? 'selected' : '' ?>>عادی</option>
                    <option value="low" <?= $priority_filter === 'low' ? 'selected' : '' ?>>کم</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">از تاریخ</label>
                <input type="date" class="form-control" name="date_from" value="<?= htmlspecialchars($date_from) ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">تا تاریخ</label>
                <input type="date" class="form-control" name="date_to" value="<?= htmlspecialchars($date_to) ?>">
            </div>
            
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> اعمال فیلتر
                </button>
                <a href="?page=sent" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i> پاک کردن فیلترها
                </a>
                <button type="button" class="btn btn-outline-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> خروجی Excel
                </button>
            </div>
        </form>
    </div>
</div>

<!-- لیست نامه‌ها -->
<div class="card">
    <div class="card-header d-flex justify-content-between align-items-center">
        <h5 class="mb-0">
            <i class="fas fa-list"></i>
            نامه‌های ارسالی (<?= number_format($total_count) ?> نامه)
        </h5>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-outline-primary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="fas fa-sort"></i> مرتب‌سازی
            </button>
            <ul class="dropdown-menu">
                <li><a class="dropdown-item" href="?page=sent&sort=date_desc">جدیدترین</a></li>
                <li><a class="dropdown-item" href="?page=sent&sort=date_asc">قدیمی‌ترین</a></li>
                <li><a class="dropdown-item" href="?page=sent&sort=subject">موضوع</a></li>
                <li><a class="dropdown-item" href="?page=sent&sort=priority">اولویت</a></li>
            </ul>
        </div>
    </div>
    
    <?php if (empty($messages)): ?>
        <div class="card-body text-center py-5">
            <i class="fas fa-paper-plane fa-4x text-muted mb-3"></i>
            <h5 class="text-muted">نامه‌ای یافت نشد</h5>
            <p class="text-muted">
                <?php if ($search || $status_filter || $priority_filter || $date_from || $date_to): ?>
                    با فیلترهای انتخابی نامه‌ای وجود ندارد.
                <?php else: ?>
                    هنوز نامه‌ای ارسال نکرده‌اید.
                <?php endif; ?>
            </p>
            <div class="mt-3">
                <a href="?page=compose" class="btn btn-primary">
                    <i class="fas fa-pen"></i> اولین نامه خود را ارسال کنید
                </a>
                <a href="?page=sent" class="btn btn-outline-secondary">
                    <i class="fas fa-refresh"></i> نمایش همه نامه‌ها
                </a>
            </div>
        </div>
    <?php else: ?>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="sentMessagesTable">
                    <thead class="table-light">
                        <tr>
                            <th width="60">اولویت</th>
                            <th>موضوع</th>
                            <th width="150">گیرنده</th>
                            <th width="120">تاریخ ارسال</th>
                            <th width="100">وضعیت</th>
                            <th width="60">ضمیمه</th>
                            <th width="80">عملیات</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($messages as $message): ?>
                            <tr class="message-row" data-id="<?= $message['id'] ?>">
                                <td>
                                    <?php
                                    $priority_class = [
                                        'urgent' => 'text-danger',
                                        'high' => 'text-warning', 
                                        'normal' => 'text-muted',
                                        'low' => 'text-secondary'
                                    ];
                                    $priority_icon = [
                                        'urgent' => 'fas fa-exclamation-triangle',
                                        'high' => 'fas fa-exclamation-circle',
                                        'normal' => 'fas fa-minus',
                                        'low' => 'fas fa-arrow-down'
                                    ];
                                    ?>
                                    <i class="<?= $priority_icon[$message['priority']] ?> <?= $priority_class[$message['priority']] ?>"
                                       title="اولویت <?= ['urgent' => 'فوری', 'high' => 'زیاد', 'normal' => 'عادی', 'low' => 'کم'][$message['priority']] ?>"></i>
                                </td>
                                <td>
                                    <a href="?page=view&id=<?= $message['id'] ?>" class="text-decoration-none">
                                        <div class="fw-bold"><?= htmlspecialchars($message['subject']) ?></div>
                                        <?php if ($message['message_number']): ?>
                                            <small class="text-muted">شماره: <?= htmlspecialchars($message['message_number']) ?></small>
                                        <?php endif; ?>
                                    </a>
                                    <div class="message-preview">
                                        <small class="text-muted">
                                            <?= htmlspecialchars(substr($message['content'], 0, 80)) ?>...
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <div class="avatar-sm bg-success text-white rounded-circle d-flex align-items-center justify-content-center me-2">
                                            <?= mb_substr($message['receiver_name'], 0, 1, 'UTF-8') ?>
                                        </div>
                                        <div>
                                            <div class="fw-bold"><?= htmlspecialchars($message['receiver_name']) ?></div>
                                            <small class="text-muted"><?= htmlspecialchars($message['receiver_username']) ?></small>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <div class="text-nowrap">
                                        <?= JalaliDate::toJalali(strtotime($message['created_at']), 'Y/m/d') ?>
                                        <br>
                                        <small class="text-muted">
                                            <?= JalaliDate::toJalali(strtotime($message['created_at']), 'H:i') ?>
                                        </small>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $status_colors = [
                                        'unread' => 'secondary',
                                        'read' => 'success',
                                        'replied' => 'info',
                                        'archived' => 'dark'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $status_colors[$message['status']] ?? 'secondary' ?>">
                                        <?= MESSAGE_STATUSES[$message['status']] ?>
                                    </span>
                                    
                                    <!-- نشانگر وضعیت دقیق‌تر -->
                                    <?php if ($message['status'] === 'unread'): ?>
                                        <br><small class="text-muted">خوانده نشده</small>
                                    <?php elseif ($message['status'] === 'read'): ?>
                                        <br><small class="text-success">خوانده شده</small>
                                    <?php elseif ($message['status'] === 'replied'): ?>
                                        <br><small class="text-info">پاسخ داده شده</small>
                                    <?php endif; ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($message['attachment']): ?>
                                        <i class="fas fa-paperclip text-primary" title="دارای ضمیمه"></i>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <div class="btn-group btn-group-sm">
                                        <a href="?page=view&id=<?= $message['id'] ?>" 
                                           class="btn btn-outline-primary" title="مشاهده">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <button type="button" class="btn btn-outline-info dropdown-toggle" 
                                                data-bs-toggle="dropdown" title="عملیات بیشتر">
                                            <i class="fas fa-ellipsis-v"></i>
                                        </button>
                                        <ul class="dropdown-menu">
                                            <li>
                                                <a class="dropdown-item" href="?page=view&id=<?= $message['id'] ?>">
                                                    <i class="fas fa-eye"></i> مشاهده کامل
                                                </a>
                                            </li>
                                            <li>
                                                <a class="dropdown-item" href="?page=compose&forward=<?= $message['id'] ?>">
                                                    <i class="fas fa-share"></i> هدایت
                                                </a>
                                            </li>
                                            <li><hr class="dropdown-divider"></li>
                                            <li>
                                                <a class="dropdown-item text-info" href="#" onclick="showMessageStats(<?= $message['id'] ?>)">
                                                    <i class="fas fa-chart-bar"></i> آمار نامه
                                                </a>
                                            </li>
                                        </ul>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- صفحه‌بندی -->
<?php if ($total_pages > 1): ?>
    <nav class="mt-4">
        <ul class="pagination justify-content-center">
            <?php if ($page_num > 1): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=sent&p=<?= $page_num - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page' && $k !== 'p', ARRAY_FILTER_USE_KEY)) ?>">
                        <i class="fas fa-angle-right"></i> قبلی
                    </a>
                </li>
            <?php endif; ?>
            
            <?php
            $start = max(1, $page_num - 2);
            $end = min($total_pages, $page_num + 2);
            
            for ($i = $start; $i <= $end; $i++):
            ?>
                <li class="page-item <?= $i === $page_num ? 'active' : '' ?>">
                    <a class="page-link" href="?page=sent&p=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page' && $k !== 'p', ARRAY_FILTER_USE_KEY)) ?>">
                        <?= JalaliDate::englishToPersian($i) ?>
                    </a>
                </li>
            <?php endfor; ?>
            
            <?php if ($page_num < $total_pages): ?>
                <li class="page-item">
                    <a class="page-link" href="?page=sent&p=<?= $page_num + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => $k !== 'page' && $k !== 'p', ARRAY_FILTER_USE_KEY)) ?>">
                        بعدی <i class="fas fa-angle-left"></i>
                    </a>
                </li>
            <?php endif; ?>
        </ul>
        
        <div class="text-center text-muted">
            صفحه <?= JalaliDate::englishToPersian($page_num) ?> از <?= JalaliDate::englishToPersian($total_pages) ?>
            (<?= JalaliDate::englishToPersian($total_count) ?> نامه)
        </div>
    </nav>
<?php endif; ?>

<!-- مودال آمار نامه -->
<div class="modal fade" id="messageStatsModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-chart-bar"></i> آمار نامه
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="messageStatsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">در حال بارگذاری...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// خروجی Excel
function exportToExcel() {
    const table = document.getElementById('sentMessagesTable');
    if (!table) {
        alert('جدولی برای خروجی یافت نشد');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length - 1; j++) { // حذف ستون عملیات
            row.push('"' + cols[j].innerText.replace(/"/g, '""') + '"');
        }
        
        csv.push(row.join(','));
    }
    
    const csvContent = csv.join('\n');
    const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    
    if (link.download !== undefined) {
        const url = URL.createObjectURL(blob);
        link.setAttribute('href', url);
        link.setAttribute('download', 'نامه‌های_ارسالی_' + new Date().toISOString().slice(0, 10) + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// نمایش آمار نامه
function showMessageStats(messageId) {
    const modal = new bootstrap.Modal(document.getElementById('messageStatsModal'));
    const content = document.getElementById('messageStatsContent');
    
    // نمایش loading
    content.innerHTML = `
        <div class="text-center">
            <div class="spinner-border" role="status">
                <span class="visually-hidden">در حال بارگذاری...</span>
            </div>
        </div>
    `;
    
    modal.show();
    
    // درخواست آمار
    fetch(`ajax/message_stats.php?id=${messageId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = `
                    <div class="row text-center">
                        <div class="col-6">
                            <div class="h4 text-primary">${data.views || 0}</div>
                            <small class="text-muted">بازدید</small>
                        </div>
                        <div class="col-6">
                            <div class="h4 text-success">${data.replies || 0}</div>
                            <small class="text-muted">پاسخ</small>
                        </div>
                    </div>
                    <hr>
                    <div class="mb-3">
                        <strong>تاریخ ارسال:</strong> ${data.sent_date}
                    </div>
                    ${data.read_date ? `
                        <div class="mb-3">
                            <strong>تاریخ خواندن:</strong> ${data.read_date}
                        </div>
                    ` : ''}
                    ${data.reply_date ? `
                        <div class="mb-3">
                            <strong>تاریخ پاسخ:</strong> ${data.reply_date}
                        </div>
                    ` : ''}
                `;
            } else {
                content.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        خطا در دریافت آمار: ${data.message || 'خطای نامشخص'}
                    </div>
                `;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            content.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-circle"></i>
                    خطا در دریافت آمار
                </div>
            `;
        });
}

// کلیک روی ردیف برای مشاهده نامه
document.querySelectorAll('.message-row').forEach(row => {
    row.addEventListener('click', function(e) {
        // اگر روی دکمه یا لینک کلیک شده، کاری نکن
        if (e.target.tagName === 'BUTTON' || 
            e.target.tagName === 'A' ||
            e.target.closest('button') ||
            e.target.closest('a')) {
            return;
        }
        
        const messageId = this.dataset.id;
        window.location.href = `?page=view&id=${messageId}`;
    });
});

// جستجوی زنده
let searchTimeout;
document.querySelector('input[name="search"]').addEventListener('input', function() {
    clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        // می‌توانید اینجا جستجوی AJAX پیاده‌سازی کنید
    }, 500);
});

// نمایش tooltip برای وضعیت‌ها
document.querySelectorAll('[title]').forEach(element => {
    new bootstrap.Tooltip(element);
});

// به‌روزرسانی خودکار وضعیت نامه‌ها (هر 5 دقیقه)
setInterval(function() {
    fetch('ajax/check_message_status.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            message_ids: Array.from(document.querySelectorAll('.message-row')).map(row => row.dataset.id)
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success && data.updates) {
            data.updates.forEach(update => {
                const row = document.querySelector(`[data-id="${update.id}"]`);
                if (row) {
                    const statusBadge = row.querySelector('.badge');
                    if (statusBadge && statusBadge.textContent !== update.status_text) {
                        statusBadge.textContent = update.status_text;
                        statusBadge.className = `badge bg-${update.status_color}`;
                        
                        // نمایش نوتیفیکیشن
                        showNotification(`وضعیت نامه "${update.subject}" تغییر کرد`, 'info');
                    }
                }
            });
        }
    })
    .catch(error => console.error('Error checking message status:', error));
}, 300000); // 5 دقیقه

// نمایش نوتیفیکیشن
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
    notification.style.top = '20px';
    notification.style.left = '20px';
    notification.style.zIndex = '9999';
    notification.style.minWidth = '300px';
    notification.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        if (notification.parentNode) {
            notification.remove();
        }
    }, 5000);
}
</script>

<style>
.col-md-2-4 {
    flex: 0 0 auto;
    width: 20%;
}

@media (max-width: 768px) {
    .col-md-2-4 {
        width: 50% !important;
    }
}

.message-preview {
    max-height: 2.4em;
    overflow: hidden;
    line-height: 1.2em;
}

.avatar-sm {
    width: 32px;
    height: 32px;
    font-size: 14px;
}

.message-row {
    cursor: pointer;
    transition: background-color 0.2s ease;
}

.message-row:hover {
    background-color: var(--bs-gray-100);
}
</style>