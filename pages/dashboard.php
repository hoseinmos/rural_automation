<?php
/**
 * Dashboard Page
 * صفحه داشبورد
 */

Auth::requireLogin();

// دریافت آمار کلی
$stats = [];

// تعداد کل نامه‌های دریافتی
$stats['total_received'] = $db->fetchRow(
    "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ?",
    [$currentUser['id']]
)['count'];

// تعداد کل نامه‌های ارسالی
$stats['total_sent'] = $db->fetchRow(
    "SELECT COUNT(*) as count FROM messages WHERE sender_id = ?",
    [$currentUser['id']]
)['count'];

// تعداد نامه‌های خوانده نشده
$stats['unread_count'] = $db->fetchRow(
    "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND status = 'unread'",
    [$currentUser['id']]
)['count'];

// تعداد نامه‌های امروز
$stats['today_count'] = $db->fetchRow(
    "SELECT COUNT(*) as count FROM messages WHERE receiver_id = ? AND DATE(created_at) = CURDATE()",
    [$currentUser['id']]
)['count'];

// آخرین نامه‌های دریافتی
$recentMessages = $db->fetchAll(
    "SELECT m.*, u.name as sender_name, u.username as sender_username
     FROM messages m 
     JOIN users u ON m.sender_id = u.id 
     WHERE m.receiver_id = ? 
     ORDER BY m.created_at DESC 
     LIMIT 10",
    [$currentUser['id']]
);

// نامه‌های مهم (اولویت بالا)
$urgentMessages = $db->fetchAll(
    "SELECT m.*, u.name as sender_name
     FROM messages m 
     JOIN users u ON m.sender_id = u.id 
     WHERE m.receiver_id = ? AND m.priority = 'urgent' AND m.status != 'archived'
     ORDER BY m.created_at DESC 
     LIMIT 5",
    [$currentUser['id']]
);

// آمار ماهانه (برای چارت)
$monthlyStats = $db->fetchAll(
    "SELECT 
        MONTH(created_at) as month,
        COUNT(*) as received_count,
        (SELECT COUNT(*) FROM messages WHERE sender_id = ? AND MONTH(created_at) = MONTH(m.created_at)) as sent_count
     FROM messages m 
     WHERE receiver_id = ? AND YEAR(created_at) = YEAR(CURDATE())
     GROUP BY MONTH(created_at)
     ORDER BY MONTH(created_at)",
    [$currentUser['id'], $currentUser['id']]
);
?>

<div class="dashboard-header">
    <div class="row align-items-center">
        <div class="col-md-6">
            <h1>
                <i class="fas fa-tachometer-alt text-primary"></i>
                داشبورد
            </h1>
            <p class="text-muted mb-0">
                خوش آمدید، <?= htmlspecialchars($currentUser['name']) ?>
            </p>
        </div>
        <div class="col-md-6 text-end">
            <div class="d-flex align-items-center justify-content-end">
                <div class="me-3">
                    <i class="fas fa-calendar-alt text-primary"></i>
                    <span class="fw-bold"><?= JalaliDate::nowWithTime('l، d F Y') ?></span>
                </div>
                <div>
                    <i class="fas fa-clock text-primary"></i>
                    <span id="currentTime" class="fw-bold"></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- آمار کلی -->
<div class="row mb-4">
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-primary text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-inbox fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= number_format($stats['total_received']) ?></div>
                        <div class="stat-label">نامه‌های دریافتی</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white bg-opacity-25">
                <a href="?page=inbox" class="text-white text-decoration-none">
                    <small>مشاهده همه <i class="fas fa-arrow-left"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-success text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-paper-plane fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= number_format($stats['total_sent']) ?></div>
                        <div class="stat-label">نامه‌های ارسالی</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white bg-opacity-25">
                <a href="?page=sent" class="text-white text-decoration-none">
                    <small>مشاهده همه <i class="fas fa-arrow-left"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-warning text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-exclamation-circle fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= number_format($stats['unread_count']) ?></div>
                        <div class="stat-label">خوانده نشده</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white bg-opacity-25">
                <a href="?page=inbox&filter=unread" class="text-white text-decoration-none">
                    <small>مشاهده همه <i class="fas fa-arrow-left"></i></small>
                </a>
            </div>
        </div>
    </div>
    
    <div class="col-xl-3 col-md-6 mb-3">
        <div class="card stat-card bg-info text-white">
            <div class="card-body">
                <div class="d-flex align-items-center">
                    <div class="stat-icon">
                        <i class="fas fa-calendar-day fa-2x"></i>
                    </div>
                    <div class="ms-3">
                        <div class="stat-number"><?= number_format($stats['today_count']) ?></div>
                        <div class="stat-label">نامه‌های امروز</div>
                    </div>
                </div>
            </div>
            <div class="card-footer bg-white bg-opacity-25">
                <a href="?page=inbox&filter=today" class="text-white text-decoration-none">
                    <small>مشاهده همه <i class="fas fa-arrow-left"></i></small>
                </a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <!-- آخرین نامه‌ها -->
    <div class="col-lg-8 mb-4">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-clock text-primary"></i>
                    آخرین نامه‌های دریافتی
                </h5>
                <a href="?page=inbox" class="btn btn-sm btn-outline-primary">
                    مشاهده همه
                </a>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentMessages)): ?>
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-inbox fa-3x mb-3"></i>
                        <p>نامه‌ای وجود ندارد</p>
                    </div>
                <?php else: ?>
                    <div class="list-group list-group-flush">
                        <?php foreach ($recentMessages as $message): ?>
                            <a href="?page=view&id=<?= $message['id'] ?>" 
                               class="list-group-item list-group-item-action <?= $message['status'] === 'unread' ? 'list-group-item-warning' : '' ?>">
                                <div class="d-flex w-100 justify-content-between align-items-center">
                                    <div class="flex-grow-1">
                                        <h6 class="mb-1 <?= $message['status'] === 'unread' ? 'fw-bold' : '' ?>">
                                            <?= htmlspecialchars($message['subject']) ?>
                                        </h6>
                                        <p class="mb-1 text-muted">
                                            <i class="fas fa-user"></i>
                                            <?= htmlspecialchars($message['sender_name']) ?>
                                            <?php if ($message['message_number']): ?>
                                                | شماره: <?= htmlspecialchars($message['message_number']) ?>
                                            <?php endif; ?>
                                        </p>
                                        <small class="text-muted">
                                            <i class="fas fa-calendar"></i>
                                            <?= JalaliDate::toJalali(strtotime($message['created_at']), 'Y/m/d H:i') ?>
                                        </small>
                                    </div>
                                    <div class="text-end">
                                        <?php if ($message['attachment']): ?>
                                            <i class="fas fa-paperclip text-primary me-2"></i>
                                        <?php endif; ?>
                                        <?php if ($message['priority'] === 'urgent'): ?>
                                            <i class="fas fa-exclamation-triangle text-danger me-2"></i>
                                        <?php endif; ?>
                                        <span class="badge bg-<?= $message['status'] === 'unread' ? 'warning' : 'success' ?>">
                                            <?= MESSAGE_STATUSES[$message['status']] ?>
                                        </span>
                                    </div>
                                </div>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <!-- نامه‌های مهم و عملیات سریع -->
    <div class="col-lg-4">
        <!-- نامه‌های مهم -->
        <?php if (!empty($urgentMessages)): ?>
            <div class="card mb-4">
                <div class="card-header bg-danger text-white">
                    <h6 class="mb-0">
                        <i class="fas fa-exclamation-triangle"></i>
                        نامه‌های مهم
                    </h6>
                </div>
                <div class="card-body p-0">
                    <div class="list-group list-group-flush">
                        <?php foreach ($urgentMessages as $message): ?>
                            <a href="?page=view&id=<?= $message['id'] ?>" 
                               class="list-group-item list-group-item-action">
                                <h6 class="mb-1"><?= htmlspecialchars(substr($message['subject'], 0, 40)) ?>...</h6>
                                <small class="text-muted">
                                    از: <?= htmlspecialchars($message['sender_name']) ?>
                                </small>
                            </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- عملیات سریع -->
        <div class="card">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-bolt text-warning"></i>
                    عملیات سریع
                </h6>
            </div>
            <div class="card-body">
                <div class="d-grid gap-2">
                    <a href="?page=compose" class="btn btn-primary">
                        <i class="fas fa-pen"></i> نامه جدید
                    </a>
                    <a href="?page=inbox" class="btn btn-outline-primary">
                        <i class="fas fa-inbox"></i> صندوق دریافت
                    </a>
                    <a href="?page=reports" class="btn btn-outline-secondary">
                        <i class="fas fa-chart-bar"></i> گزارش‌گیری
                    </a>
                    <a href="?page=profile" class="btn btn-outline-info">
                        <i class="fas fa-user-edit"></i> ویرایش پروفایل
                    </a>
                </div>
            </div>
        </div>
        
        <!-- نمودار آمار ماهانه -->
        <?php if (!empty($monthlyStats)): ?>
            <div class="card mt-4">
                <div class="card-header">
                    <h6 class="mb-0">
                        <i class="fas fa-chart-line text-success"></i>
                        آمار ماهانه
                    </h6>
                </div>
                <div class="card-body">
                    <canvas id="monthlyChart" height="200"></canvas>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- اطلاعات سیستم -->
        <div class="card mt-4">
            <div class="card-header">
                <h6 class="mb-0">
                    <i class="fas fa-info-circle text-info"></i>
                    اطلاعات سیستم
                </h6>
            </div>
            <div class="card-body">
                <div class="row text-center">
                    <div class="col-6">
                        <div class="border-end">
                            <div class="h4 text-primary"><?= SITE_VERSION ?></div>
                            <small class="text-muted">نسخه سیستم</small>
                        </div>
                    </div>
                    <div class="col-6">
                        <div class="h4 text-success">
                            <?= $db->fetchRow("SELECT COUNT(*) as count FROM users WHERE status = 'active'")['count'] ?>
                        </div>
                        <small class="text-muted">کاربران فعال</small>
                    </div>
                </div>
                <hr>
                <div class="text-center">
                    <small class="text-muted">
                        آخرین ورود: <?= JalaliDate::timeAgo(strtotime($_SESSION['login_time'] ?? 'now')) ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
// نمایش ساعت جاری
function updateCurrentTime() {
    const now = new Date();
    const timeString = now.toLocaleTimeString('fa-IR');
    document.getElementById('currentTime').textContent = timeString;
}

// به‌روزرسانی ساعت هر ثانیه
setInterval(updateCurrentTime, 1000);
updateCurrentTime();

// نمودار آمار ماهانه
<?php if (!empty($monthlyStats)): ?>
const ctx = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = <?= json_encode($monthlyStats) ?>;

// نام ماه‌های فارسی
const persianMonths = ['فروردین', 'اردیبهشت', 'خرداد', 'تیر', 'مرداد', 'شهریور', 
                      'مهر', 'آبان', 'آذر', 'دی', 'بهمن', 'اسفند'];

const labels = monthlyData.map(item => persianMonths[item.month - 1]);
const receivedData = monthlyData.map(item => item.received_count);
const sentData = monthlyData.map(item => item.sent_count);

// ایجاد نمودار ساده با canvas
const canvas = document.getElementById('monthlyChart');
const ctx2d = canvas.getContext('2d');

// پاک کردن canvas
ctx2d.clearRect(0, 0, canvas.width, canvas.height);

// تنظیمات نمودار
const padding = 40;
const chartWidth = canvas.width - 2 * padding;
const chartHeight = canvas.height - 2 * padding;

const maxValue = Math.max(...receivedData, ...sentData) || 1;

// رسم محورها
ctx2d.strokeStyle = '#ddd';
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

// رسم داده‌ها
const barWidth = chartWidth / (labels.length * 2);

labels.forEach((label, index) => {
    const x = padding + (index * 2 + 0.5) * barWidth;
    
    // نامه‌های دریافتی (آبی)
    const receivedHeight = (receivedData[index] / maxValue) * chartHeight;
    ctx2d.fillStyle = '#0d6efd';
    ctx2d.fillRect(x, canvas.height - padding - receivedHeight, barWidth * 0.8, receivedHeight);
    
    // نامه‌های ارسالی (سبز)
    const sentHeight = (sentData[index] / maxValue) * chartHeight;
    ctx2d.fillStyle = '#198754';
    ctx2d.fillRect(x + barWidth * 0.8, canvas.height - padding - sentHeight, barWidth * 0.8, sentHeight);
});

// راهنما
ctx2d.fillStyle = '#0d6efd';
ctx2d.fillRect(padding, 10, 15, 15);
ctx2d.fillStyle = '#333';
ctx2d.font = '12px Vazir';
ctx2d.fillText('دریافتی', padding + 20, 22);

ctx2d.fillStyle = '#198754';
ctx2d.fillRect(padding + 80, 10, 15, 15);
ctx2d.fillStyle = '#333';
ctx2d.fillText('ارسالی', padding + 105, 22);
<?php endif; ?>

// به‌روزرسانی خودکار آمار (هر 5 دقیقه)
setInterval(function() {
    fetch('ajax/dashboard_stats.php')
        .then(response => response.json())
        .then(data => {
            // به‌روزرسانی آمار در صورت تغییر
            if (data.success) {
                document.querySelector('.stat-card.bg-warning .stat-number').textContent = 
                    new Intl.NumberFormat('fa-IR').format(data.unread_count);
            }
        })
        .catch(error => console.error('Error updating stats:', error));
}, 300000); // 5 دقیقه
</script>