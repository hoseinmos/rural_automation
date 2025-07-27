<?php
/**
 * Reports Page - Fixed SQL queries
 * صفحه گزارشات - کوئری های SQL اصلاح شده
 */

Auth::requireLogin();

// تنظیمات صفحه‌بندی
$page_num = max(1, (int)($_GET['p'] ?? 1));
$limit = ITEMS_PER_PAGE;
$offset = ($page_num - 1) * $limit;

// فیلترهای گزارش
$date_from = $_GET['date_from'] ?? date('Y-m-01'); // اول ماه جاری
$date_to = $_GET['date_to'] ?? date('Y-m-d'); // امروز
$report_type = $_GET['report_type'] ?? 'summary';
$user_filter = $_GET['user_filter'] ?? '';
$status_filter = $_GET['status_filter'] ?? '';

// آمار کلی - با alias های صحیح
try {
    $overall_stats = $db->fetchRow(
        "SELECT 
            COUNT(m.id) as total_messages,
            SUM(CASE WHEN m.status = 'unread' THEN 1 ELSE 0 END) as unread_count,
            SUM(CASE WHEN m.status = 'read' THEN 1 ELSE 0 END) as read_count,
            SUM(CASE WHEN m.status = 'replied' THEN 1 ELSE 0 END) as replied_count,
            SUM(CASE WHEN m.status = 'archived' THEN 1 ELSE 0 END) as archived_count,
            SUM(CASE WHEN m.priority = 'urgent' THEN 1 ELSE 0 END) as urgent_count,
            SUM(CASE WHEN DATE(m.created_at) = CURDATE() THEN 1 ELSE 0 END) as today_count,
            SUM(CASE WHEN WEEK(m.created_at) = WEEK(CURDATE()) THEN 1 ELSE 0 END) as week_count,
            SUM(CASE WHEN MONTH(m.created_at) = MONTH(CURDATE()) THEN 1 ELSE 0 END) as month_count
         FROM messages m 
         WHERE DATE(m.created_at) BETWEEN ? AND ?",
        [$date_from, $date_to]
    );
} catch (Exception $e) {
    // در صورت خطا، آمار صفر
    $overall_stats = [
        'total_messages' => 0,
        'unread_count' => 0,
        'read_count' => 0,
        'replied_count' => 0,
        'archived_count' => 0,
        'urgent_count' => 0,
        'today_count' => 0,
        'week_count' => 0,
        'month_count' => 0
    ];
}

// آمار کاربران - با alias های صحیح
try {
    $user_stats = $db->fetchAll(
        "SELECT 
            u.id,
            u.name,
            u.username,
            COUNT(m.id) as total_sent,
            SUM(CASE WHEN m.status = 'read' THEN 1 ELSE 0 END) as read_count,
            SUM(CASE WHEN m.status = 'replied' THEN 1 ELSE 0 END) as replied_count,
            SUM(CASE WHEN m.status = 'unread' THEN 1 ELSE 0 END) as unread_count
         FROM users u 
         LEFT JOIN messages m ON u.id = m.sender_id 
         WHERE u.status = 'active' 
         AND (m.created_at IS NULL OR DATE(m.created_at) BETWEEN ? AND ?)
         GROUP BY u.id, u.name, u.username
         ORDER BY total_sent DESC 
         LIMIT 10",
        [$date_from, $date_to]
    );
} catch (Exception $e) {
    $user_stats = [];
}

// آمار روزانه - برای چارت
try {
    $daily_stats = $db->fetchAll(
        "SELECT 
            DATE(m.created_at) as date,
            COUNT(m.id) as count,
            SUM(CASE WHEN m.status = 'unread' THEN 1 ELSE 0 END) as unread,
            SUM(CASE WHEN m.status = 'read' THEN 1 ELSE 0 END) as read_msgs,
            SUM(CASE WHEN m.status = 'replied' THEN 1 ELSE 0 END) as replied
         FROM messages m 
         WHERE DATE(m.created_at) BETWEEN ? AND ?
         GROUP BY DATE(m.created_at)
         ORDER BY DATE(m.created_at) DESC
         LIMIT 30",
        [$date_from, $date_to]
    );
} catch (Exception $e) {
    $daily_stats = [];
}

// گزارش تفصیلی
$detailed_messages = [];
if ($report_type === 'detailed') {
    try {
        // ساخت WHERE clause
        $where_conditions = ["DATE(m.created_at) BETWEEN ? AND ?"];
        $params = [$date_from, $date_to];
        
        if ($user_filter) {
            $where_conditions[] = "(s.name LIKE ? OR r.name LIKE ?)";
            $search_term = "%$user_filter%";
            $params[] = $search_term;
            $params[] = $search_term;
        }
        
        if ($status_filter) {
            $where_conditions[] = "m.status = ?";
            $params[] = $status_filter;
        }
        
        $where_clause = implode(' AND ', $where_conditions);
        
        // کوئری اصلی با alias های صحیح
        $detailed_messages = $db->fetchAll(
            "SELECT 
                m.id,
                m.subject,
                m.message_number,
                m.status as message_status,
                m.priority,
                m.created_at,
                m.updated_at,
                m.attachment,
                s.name as sender_name,
                s.username as sender_username,
                r.name as receiver_name,
                r.username as receiver_username
             FROM messages m 
             JOIN users s ON m.sender_id = s.id 
             JOIN users r ON m.receiver_id = r.id 
             WHERE $where_clause
             ORDER BY m.created_at DESC
             LIMIT $limit OFFSET $offset",
            $params
        );
        
        // شمارش کل برای صفحه‌بندی
        $total_count = $db->fetchRow(
            "SELECT COUNT(m.id) as count 
             FROM messages m 
             JOIN users s ON m.sender_id = s.id 
             JOIN users r ON m.receiver_id = r.id 
             WHERE $where_clause",
            $params
        )['count'];
        
        $total_pages = ceil($total_count / $limit);
        
    } catch (Exception $e) {
        $detailed_messages = [];
        $total_count = 0;
        $total_pages = 0;
        $error_message = "خطا در دریافت گزارش تفصیلی: " . $e->getMessage();
    }
}
?>

<div class="page-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-chart-bar text-primary"></i>
                گزارش‌گیری
            </h1>
            <p class="text-muted mb-0">
                تحلیل و گزارش‌گیری از نامه‌ها
            </p>
        </div>
        <div class="col-md-6 text-end">
            <div class="btn-group">
                <button type="button" class="btn btn-success" onclick="exportToExcel()">
                    <i class="fas fa-file-excel"></i> خروجی Excel
                </button>
                <button type="button" class="btn btn-info" onclick="exportToPDF()">
                    <i class="fas fa-file-pdf"></i> خروجی PDF
                </button>
                <button type="button" class="btn btn-primary" onclick="printReport()">
                    <i class="fas fa-print"></i> چاپ
                </button>
            </div>
        </div>
    </div>
</div>

<!-- نمایش خطا در صورت وجود -->
<?php if (isset($error_message)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i>
        <?= $error_message ?>
    </div>
<?php endif; ?>

<!-- فیلترهای گزارش -->
<div class="card mb-4">
    <div class="card-header">
        <h5 class="mb-0">
            <i class="fas fa-filter"></i> فیلترهای گزارش
        </h5>
    </div>
    <div class="card-body">
        <form method="GET" class="row g-3">
            <input type="hidden" name="page" value="reports">
            
            <div class="col-md-3">
                <label class="form-label">نوع گزارش</label>
                <select class="form-select" name="report_type">
                    <option value="summary" <?= $report_type === 'summary' ? 'selected' : '' ?>>خلاصه</option>
                    <option value="detailed" <?= $report_type === 'detailed' ? 'selected' : '' ?>>تفصیلی</option>
                </select>
            </div>
            
            <div class="col-md-2">
                <label class="form-label">از تاریخ</label>
                <input type="date" class="form-control" name="date_from" 
                       value="<?= htmlspecialchars($date_from) ?>">
            </div>
            
            <div class="col-md-2">
                <label class="form-label">تا تاریخ</label>
                <input type="date" class="form-control" name="date_to" 
                       value="<?= htmlspecialchars($date_to) ?>">
            </div>
            
            <?php if ($report_type === 'detailed'): ?>
                <div class="col-md-2">
                    <label class="form-label">کاربر</label>
                    <input type="text" class="form-control" name="user_filter" 
                           value="<?= htmlspecialchars($user_filter) ?>" 
                           placeholder="نام کاربر">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">وضعیت</label>
                    <select class="form-select" name="status_filter">
                        <option value="">همه وضعیت‌ها</option>
                        <?php foreach (MESSAGE_STATUSES as $key => $value): ?>
                            <option value="<?= $key ?>" <?= $status_filter === $key ? 'selected' : '' ?>>
                                <?= $value ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
            <?php endif; ?>
            
            <div class="col-md-1">
                <label class="form-label">&nbsp;</label>
                <button type="submit" class="btn btn-primary d-block">
                    <i class="fas fa-search"></i> اعمال
                </button>
            </div>
        </form>
    </div>
</div>

<!-- آمار کلی -->
<div class="row mb-4">
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body text-center">
                <div class="h2"><?= number_format($overall_stats['total_messages']) ?></div>
                <div>کل نامه‌ها</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body text-center">
                <div class="h2"><?= number_format($overall_stats['unread_count']) ?></div>
                <div>خوانده نشده</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body text-center">
                <div class="h2"><?= number_format($overall_stats['read_count']) ?></div>
                <div>خوانده شده</div>
            </div>
        </div>
    </div>
    
    <div class="col-lg-3 col-md-6 mb-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body text-center">
                <div class="h2"><?= number_format($overall_stats['replied_count']) ?></div>
                <div>پاسخ داده شده</div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- نمودار آمار روزانه -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-chart-line"></i> آمار روزانه نامه‌ها
                </h5>
            </div>
            <div class="card-body">
                <canvas id="dailyChart" height="100"></canvas>
            </div>
        </div>
    </div>
    
    <!-- آمار کاربران -->
    <div class="col-lg-4 mb-4">
        <div class="card">
            <div class="card-header">
                <h5 class="mb-0">
                    <i class="fas fa-users"></i> فعال‌ترین کاربران
                </h5>
            </div>
            <div class="card-body p-0">
                <?php if (!empty($user_stats)): ?>
                    <div class="list-group list-group-flush">
                        <?php foreach (array_slice($user_stats, 0, 5) as $user): ?>
                            <div class="list-group-item d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="fw-bold"><?= htmlspecialchars($user['name']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($user['username']) ?></small>
                                </div>
                                <span class="badge bg-primary rounded-pill">
                                    <?= number_format($user['total_sent']) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center p-3 text-muted">
                        داده‌ای یافت نشد
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- گزارش تفصیلی -->
<?php if ($report_type === 'detailed' && !empty($detailed_messages)): ?>
    <div class="card">
        <div class="card-header">
            <h5 class="mb-0">
                <i class="fas fa-table"></i> گزارش تفصیلی نامه‌ها
            </h5>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive">
                <table class="table table-hover mb-0" id="detailedReportTable">
                    <thead class="table-light">
                        <tr>
                            <th>موضوع</th>
                            <th>شماره نامه</th>
                            <th>فرستنده</th>
                            <th>گیرنده</th>
                            <th>وضعیت</th>
                            <th>اولویت</th>
                            <th>تاریخ ارسال</th>
                            <th>ضمیمه</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($detailed_messages as $message): ?>
                            <tr>
                                <td>
                                    <a href="?page=view&id=<?= $message['id'] ?>" class="text-decoration-none">
                                        <?= htmlspecialchars($message['subject']) ?>
                                    </a>
                                </td>
                                <td><?= htmlspecialchars($message['message_number'] ?? '-') ?></td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($message['sender_name']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($message['sender_username']) ?></small>
                                </td>
                                <td>
                                    <div class="fw-bold"><?= htmlspecialchars($message['receiver_name']) ?></div>
                                    <small class="text-muted">@<?= htmlspecialchars($message['receiver_username']) ?></small>
                                </td>
                                <td>
                                    <span class="badge bg-<?= 
                                        $message['message_status'] === 'unread' ? 'warning' : 
                                        ($message['message_status'] === 'read' ? 'success' : 
                                        ($message['message_status'] === 'replied' ? 'info' : 'secondary'))
                                    ?>">
                                        <?= MESSAGE_STATUSES[$message['message_status']] ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    $priority_colors = [
                                        'urgent' => 'danger',
                                        'high' => 'warning',
                                        'normal' => 'secondary',
                                        'low' => 'info'
                                    ];
                                    $priority_labels = [
                                        'urgent' => 'فوری',
                                        'high' => 'زیاد',
                                        'normal' => 'عادی',
                                        'low' => 'کم'
                                    ];
                                    ?>
                                    <span class="badge bg-<?= $priority_colors[$message['priority']] ?>">
                                        <?= $priority_labels[$message['priority']] ?>
                                    </span>
                                </td>
                                <td>
                                    <?= JalaliDate::toJalali(strtotime($message['created_at']), 'Y/m/d H:i') ?>
                                </td>
                                <td class="text-center">
                                    <?php if ($message['attachment']): ?>
                                        <i class="fas fa-paperclip text-primary"></i>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        
        <!-- صفحه‌بندی -->
        <?php if (isset($total_pages) && $total_pages > 1): ?>
            <div class="card-footer">
                <nav>
                    <ul class="pagination justify-content-center mb-0">
                        <?php if ($page_num > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=reports&report_type=detailed&p=<?= $page_num - 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => !in_array($k, ['page', 'p']), ARRAY_FILTER_USE_KEY)) ?>">
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
                                <a class="page-link" href="?page=reports&report_type=detailed&p=<?= $i ?>&<?= http_build_query(array_filter($_GET, fn($k) => !in_array($k, ['page', 'p']), ARRAY_FILTER_USE_KEY)) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($page_num < $total_pages): ?>
                            <li class="page-item">
                                <a class="page-link" href="?page=reports&report_type=detailed&p=<?= $page_num + 1 ?>&<?= http_build_query(array_filter($_GET, fn($k) => !in_array($k, ['page', 'p']), ARRAY_FILTER_USE_KEY)) ?>">
                                    بعدی
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
// رسم نمودار آمار روزانه
const dailyData = <?= json_encode($daily_stats) ?>;

if (dailyData.length > 0) {
    const ctx = document.getElementById('dailyChart').getContext('2d');
    
    const labels = dailyData.map(item => {
        const date = new Date(item.date);
        return date.toLocaleDateString('fa-IR');
    }).reverse();
    
    const totalData = dailyData.map(item => parseInt(item.count)).reverse();
    const unreadData = dailyData.map(item => parseInt(item.unread)).reverse();
    const readData = dailyData.map(item => parseInt(item.read_msgs)).reverse();
    
    // رسم نمودار ساده با Canvas
    const canvas = document.getElementById('dailyChart');
    const ctx2d = canvas.getContext('2d');
    
    // تنظیمات نمودار
    const padding = 40;
    const chartWidth = canvas.width - 2 * padding;
    const chartHeight = canvas.height - 2 * padding;
    
    const maxValue = Math.max(...totalData) || 1;
    
    // پاک کردن canvas
    ctx2d.clearRect(0, 0, canvas.width, canvas.height);
    
    // رسم محورها
    ctx2d.strokeStyle = '#e2e8f0';
    ctx2d.lineWidth = 1;
    
    // محور افقی
    ctx2d.beginPath();
    ctx2d.moveTo(padding, canvas.height - padding);
    ctx2d.lineTo(canvas.width - padding, canvas.height - padding);
    ctx2d.stroke();
    
    // محور عمودی
    ctx2d.beginPath();
    ctx2d.moveTo(padding, padding);
    ctx2d.lineTo(padding, canvas.height - padding);
    ctx2d.stroke();
    
    // رسم خط نمودار
    if (totalData.length > 1) {
        ctx2d.strokeStyle = '#3b82f6';
        ctx2d.lineWidth = 3;
        ctx2d.beginPath();
        
        totalData.forEach((value, index) => {
            const x = padding + (index / (totalData.length - 1)) * chartWidth;
            const y = canvas.height - padding - (value / maxValue) * chartHeight;
            
            if (index === 0) {
                ctx2d.moveTo(x, y);
            } else {
                ctx2d.lineTo(x, y);
            }
        });
        
        ctx2d.stroke();
        
        // رسم نقاط
        ctx2d.fillStyle = '#3b82f6';
        totalData.forEach((value, index) => {
            const x = padding + (index / (totalData.length - 1)) * chartWidth;
            const y = canvas.height - padding - (value / maxValue) * chartHeight;
            
            ctx2d.beginPath();
            ctx2d.arc(x, y, 4, 0, 2 * Math.PI);
            ctx2d.fill();
        });
    }
    
    // عنوان نمودار
    ctx2d.fillStyle = '#1f2937';
    ctx2d.font = '14px Vazir';
    ctx2d.textAlign = 'center';
    ctx2d.fillText('تعداد نامه‌ها در روزهای اخیر', canvas.width / 2, 30);
}

// خروجی Excel
function exportToExcel() {
    const table = document.getElementById('detailedReportTable');
    if (!table) {
        alert('گزارش تفصیلی را انتخاب کنید');
        return;
    }
    
    let csv = [];
    const rows = table.querySelectorAll('tr');
    
    for (let i = 0; i < rows.length; i++) {
        const row = [], cols = rows[i].querySelectorAll('td, th');
        
        for (let j = 0; j < cols.length; j++) {
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
        link.setAttribute('download', 'گزارش_نامه‌ها_' + new Date().toISOString().slice(0, 10) + '.csv');
        link.style.visibility = 'hidden';
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    }
}

// چاپ گزارش
function printReport() {
    window.print();
}

// خروجی PDF (نیاز به کتابخانه jsPDF دارد)
function exportToPDF() {
    alert('قابلیت خروجی PDF در نسخه آینده اضافه خواهد شد');
}

// تغییر نوع گزارش
document.querySelector('select[name="report_type"]').addEventListener('change', function() {
    if (this.value === 'detailed') {
        // نمایش فیلدهای اضافی
        const form = this.closest('form');
        form.submit();
    } else {
        // مخفی کردن فیلدهای اضافی
        const form = this.closest('form');
        form.submit();
    }
});
</script>

<style>
.stat-card {
    transition: transform 0.2s ease;
}

.stat-card:hover {
    transform: translateY(-2px);
}

@media print {
    .btn, .card-header, .pagination {
        display: none !important;
    }
    
    .card {
        border: none !important;
        box-shadow: none !important;
    }
    
    body {
        background: white !important;
    }
}
</style>