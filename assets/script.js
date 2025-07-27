/**
 * Rural Automation System - Custom JavaScript
 * سیستم اتوماسیون دهیاری - اسکریپت‌های سفارشی
 */

// متغیرهای سراسری
window.RuralSystem = {
    config: {
        ajaxTimeout: 30000,
        notificationDuration: 5000,
        autoRefreshInterval: 300000, // 5 دقیقه
        maxFileSize: 10 * 1024 * 1024, // 10MB
        allowedExtensions: ['pdf', 'jpg', 'jpeg', 'png', 'mp4', 'avi', 'doc', 'docx']
    },
    
    // Cache برای نتایج AJAX
    cache: new Map(),
    
    // تایمرها
    timers: {
        autoRefresh: null,
        notificationTimeout: null
    }
};

/**
 * ابزارهای کمکی
 */
const Utils = {
    
    /**
     * فرمت کردن اعداد به فارسی
     */
    toPersianNumber(num) {
        const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
        return num.toString().replace(/\d/g, digit => persianDigits[digit]);
    },
    
    /**
     * تبدیل اعداد فارسی به انگلیسی
     */
    toEnglishNumber(str) {
        const persianDigits = '۰۱۲۳۴۵۶۷۸۹';
        const arabicDigits = '٠١٢٣٤٥٦٧٨٩';
        
        str = str.replace(/[۰-۹]/g, char => persianDigits.indexOf(char));
        str = str.replace(/[٠-٩]/g, char => arabicDigits.indexOf(char));
        
        return str;
    },
    
    /**
     * فرمت کردن حجم فایل
     */
    formatFileSize(bytes) {
        if (bytes === 0) return '0 بایت';
        
        const k = 1024;
        const sizes = ['بایت', 'کیلوبایت', 'مگابایت', 'گیگابایت'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    },
    
    /**
     * اعتبارسنجی ایمیل
     */
    validateEmail(email) {
        const re = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
        return re.test(email);
    },
    
    /**
     * اعتبارسنجی شماره تلفن ایرانی
     */
    validatePhone(phone) {
        const re = /^(\+98|0)?9\d{9}$/;
        return re.test(phone.replace(/\s/g, ''));
    },
    
    /**
     * تولید UUID ساده
     */
    generateUUID() {
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
            const r = Math.random() * 16 | 0;
            const v = c == 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    },
    
    /**
     * کپی متن در کلیپ‌برد
     */
    copyToClipboard(text) {
        if (navigator.clipboard) {
            return navigator.clipboard.writeText(text);
        } else {
            // fallback برای مرورگرهای قدیمی
            const textArea = document.createElement('textarea');
            textArea.value = text;
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                document.body.removeChild(textArea);
                return Promise.resolve();
            } catch (err) {
                document.body.removeChild(textArea);
                return Promise.reject(err);
            }
        }
    },
    
    /**
     * تبدیل تاریخ به فرمت قابل خواندن
     */
    formatRelativeTime(timestamp) {
        const now = new Date();
        const date = new Date(timestamp);
        const diff = now - date;
        
        const seconds = Math.floor(diff / 1000);
        const minutes = Math.floor(seconds / 60);
        const hours = Math.floor(minutes / 60);
        const days = Math.floor(hours / 24);
        
        if (seconds < 60) return 'همین الان';
        if (minutes < 60) return `${minutes} دقیقه پیش`;
        if (hours < 24) return `${hours} ساعت پیش`;
        if (days < 30) return `${days} روز پیش`;
        
        return date.toLocaleDateString('fa-IR');
    }
};

/**
 * مدیریت نوتیفیکیشن‌ها
 */
const Notification = {
    
    /**
     * نمایش نوتیفیکیشن
     */
    show(message, type = 'info', duration = null) {
        // حذف نوتیفیکیشن قبلی
        this.hide();
        
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.style.cssText = `
            top: 20px;
            left: 20px;
            right: 20px;
            z-index: 9999;
            max-width: 400px;
            margin: 0 auto;
            box-shadow: 0 4px 12px rgba(0,0,0,0.15);
        `;
        
        alertDiv.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="fas fa-${this.getIcon(type)} me-2"></i>
                <span>${message}</span>
                <button type="button" class="btn-close ms-auto" data-bs-dismiss="alert"></button>
            </div>
        `;
        
        document.body.appendChild(alertDiv);
        
        // حذف خودکار
        const timeout = duration || window.RuralSystem.config.notificationDuration;
        window.RuralSystem.timers.notificationTimeout = setTimeout(() => {
            this.hide();
        }, timeout);
        
        return alertDiv;
    },
    
    /**
     * پنهان کردن نوتیفیکیشن
     */
    hide() {
        const existingAlert = document.querySelector('.alert.position-fixed');
        if (existingAlert) {
            existingAlert.remove();
        }
        
        if (window.RuralSystem.timers.notificationTimeout) {
            clearTimeout(window.RuralSystem.timers.notificationTimeout);
        }
    },
    
    /**
     * دریافت آیکون مناسب برای نوع نوتیفیکیشن
     */
    getIcon(type) {
        const icons = {
            success: 'check-circle',
            error: 'exclamation-triangle',
            warning: 'exclamation-circle',
            info: 'info-circle'
        };
        return icons[type] || 'info-circle';
    },
    
    /**
     * میانبرهای مختلف انواع نوتیفیکیشن
     */
    success(message, duration = null) {
        return this.show(message, 'success', duration);
    },
    
    error(message, duration = null) {
        return this.show(message, 'danger', duration);
    },
    
    warning(message, duration = null) {
        return this.show(message, 'warning', duration);
    },
    
    info(message, duration = null) {
        return this.show(message, 'info', duration);
    }
};

/**
 * مدیریت درخواست‌های AJAX
 */
const Ajax = {
    
    /**
     * ارسال درخواست AJAX
     */
    async request(url, options = {}) {
        const defaults = {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            },
            timeout: window.RuralSystem.config.ajaxTimeout
        };
        
        // اضافه کردن CSRF token
        const csrfToken = document.querySelector('meta[name="csrf-token"]');
        if (csrfToken) {
            defaults.headers['X-CSRF-TOKEN'] = csrfToken.getAttribute('content');
        }
        
        const config = { ...defaults, ...options };
        
        // بررسی cache
        const cacheKey = `${config.method}:${url}`;
        if (config.method === 'GET' && window.RuralSystem.cache.has(cacheKey)) {
            const cached = window.RuralSystem.cache.get(cacheKey);
            if (Date.now() - cached.timestamp < 60000) { // 1 دقیقه
                return cached.data;
            }
        }
        
        try {
            const controller = new AbortController();
            const timeoutId = setTimeout(() => controller.abort(), config.timeout);
            
            const response = await fetch(url, {
                ...config,
                signal: controller.signal
            });
            
            clearTimeout(timeoutId);
            
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            
            // ذخیره در cache
            if (config.method === 'GET') {
                window.RuralSystem.cache.set(cacheKey, {
                    data: data,
                    timestamp: Date.now()
                });
            }
            
            return data;
            
        } catch (error) {
            if (error.name === 'AbortError') {
                throw new Error('درخواست به دلیل طولانی شدن لغو شد');
            }
            throw error;
        }
    },
    
    /**
     * درخواست GET
     */
    get(url, params = {}) {
        const searchParams = new URLSearchParams(params);
        const fullUrl = searchParams.toString() ? `${url}?${searchParams}` : url;
        return this.request(fullUrl);
    },
    
    /**
     * درخواست POST
     */
    post(url, data = {}) {
        return this.request(url, {
            method: 'POST',
            body: JSON.stringify(data)
        });
    },
    
    /**
     * ارسال فرم با فایل
     */
    postForm(url, formData) {
        return this.request(url, {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
                // Content-Type را حذف می‌کنیم تا مرورگر خودش تنظیم کند
            },
            body: formData
        });
    }
};

/**
 * مدیریت فایل‌ها
 */
const FileManager = {
    
    /**
     * اعتبارسنجی فایل
     */
    validateFile(file) {
        const errors = [];
        
        // بررسی حجم
        if (file.size > window.RuralSystem.config.maxFileSize) {
            errors.push(`حجم فایل نباید بیشتر از ${Utils.formatFileSize(window.RuralSystem.config.maxFileSize)} باشد`);
        }
        
        // بررسی پسوند
        const extension = file.name.split('.').pop().toLowerCase();
        if (!window.RuralSystem.config.allowedExtensions.includes(extension)) {
            errors.push(`فرمت فایل مجاز نیست. فرمت‌های مجاز: ${window.RuralSystem.config.allowedExtensions.join(', ')}`);
        }
        
        return {
            valid: errors.length === 0,
            errors: errors
        };
    },
    
    /**
     * پیش‌نمایش فایل
     */
    previewFile(file, container) {
        const reader = new FileReader();
        
        reader.onload = function(e) {
            const extension = file.name.split('.').pop().toLowerCase();
            let preview = '';
            
            if (['jpg', 'jpeg', 'png', 'gif'].includes(extension)) {
                preview = `<img src="${e.target.result}" class="img-fluid rounded" style="max-height: 200px;">`;
            } else if (extension === 'pdf') {
                preview = `<i class="fas fa-file-pdf fa-4x text-danger"></i><br><small>${file.name}</small>`;
            } else {
                preview = `<i class="fas fa-file fa-4x text-secondary"></i><br><small>${file.name}</small>`;
            }
            
            container.innerHTML = `
                <div class="text-center p-3 border rounded">
                    ${preview}
                    <div class="mt-2">
                        <small class="text-muted">${Utils.formatFileSize(file.size)}</small>
                    </div>
                </div>
            `;
        };
        
        if (file.type.startsWith('image/')) {
            reader.readAsDataURL(file);
        } else {
            // برای فایل‌های غیر تصویری، فقط نام و حجم نمایش می‌دهیم
            const extension = file.name.split('.').pop().toLowerCase();
            let icon = 'file';
            
            if (extension === 'pdf') icon = 'file-pdf';
            else if (['doc', 'docx'].includes(extension)) icon = 'file-word';
            else if (['mp4', 'avi'].includes(extension)) icon = 'file-video';
            
            container.innerHTML = `
                <div class="text-center p-3 border rounded">
                    <i class="fas fa-${icon} fa-4x text-primary"></i>
                    <div class="mt-2">
                        <div class="fw-bold">${file.name}</div>
                        <small class="text-muted">${Utils.formatFileSize(file.size)}</small>
                    </div>
                </div>
            `;
        }
    }
};

/**
 * مدیریت فرم‌ها
 */
const FormManager = {
    
    /**
     * اعتبارسنجی فرم
     */
    validateForm(form) {
        const inputs = form.querySelectorAll('input[required], select[required], textarea[required]');
        let valid = true;
        
        inputs.forEach(input => {
            if (!input.value.trim()) {
                this.showFieldError(input, 'این فیلد الزامی است');
                valid = false;
            } else {
                this.clearFieldError(input);
            }
        });
        
        // اعتبارسنجی‌های خاص
        form.querySelectorAll('input[type="email"]').forEach(input => {
            if (input.value && !Utils.validateEmail(input.value)) {
                this.showFieldError(input, 'فرمت ایمیل نامعتبر است');
                valid = false;
            }
        });
        
        form.querySelectorAll('input[type="tel"]').forEach(input => {
            if (input.value && !Utils.validatePhone(input.value)) {
                this.showFieldError(input, 'فرمت شماره تلفن نامعتبر است');
                valid = false;
            }
        });
        
        return valid;
    },
    
    /**
     * نمایش خطای فیلد
     */
    showFieldError(input, message) {
        input.classList.add('is-invalid');
        
        let feedback = input.parentNode.querySelector('.invalid-feedback');
        if (!feedback) {
            feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            input.parentNode.appendChild(feedback);
        }
        
        feedback.textContent = message;
    },
    
    /**
     * پاک کردن خطای فیلد
     */
    clearFieldError(input) {
        input.classList.remove('is-invalid');
        
        const feedback = input.parentNode.querySelector('.invalid-feedback');
        if (feedback) {
            feedback.remove();
        }
    },
    
    /**
     * تبدیل فرم به FormData
     */
    serializeForm(form) {
        const formData = new FormData(form);
        return formData;
    },
    
    /**
     * ریست کردن فرم
     */
    resetForm(form) {
        form.reset();
        
        // پاک کردن خطاها
        form.querySelectorAll('.is-invalid').forEach(input => {
            this.clearFieldError(input);
        });
        
        // پاک کردن پیش‌نمایش فایل‌ها
        form.querySelectorAll('.file-preview').forEach(preview => {
            preview.innerHTML = '';
        });
    }
};

/**
 * مدیریت جدول‌ها
 */
const TableManager = {
    
    /**
     * خروجی Excel
     */
    exportToExcel(tableId, filename = null) {
        const table = document.getElementById(tableId);
        if (!table) {
            Notification.error('جدول مورد نظر یافت نشد');
            return;
        }
        
        let csv = [];
        const rows = table.querySelectorAll('tr');
        
        for (let i = 0; i < rows.length; i++) {
            const row = [];
            const cols = rows[i].querySelectorAll('td, th');
            
            for (let j = 0; j < cols.length; j++) {
                const text = cols[j].innerText.replace(/"/g, '""');
                row.push(`"${text}"`);
            }
            
            csv.push(row.join(','));
        }
        
        const csvContent = csv.join('\n');
        const blob = new Blob(['\uFEFF' + csvContent], { type: 'text/csv;charset=utf-8;' });
        
        const link = document.createElement('a');
        if (link.download !== undefined) {
            const url = URL.createObjectURL(blob);
            link.setAttribute('href', url);
            link.setAttribute('download', filename || `export_${Date.now()}.csv`);
            link.style.visibility = 'hidden';
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
            
            Notification.success('فایل Excel با موفقیت دانلود شد');
        }
    },
    
    /**
     * جستجو در جدول
     */
    searchTable(tableId, searchTerm) {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const rows = table.querySelectorAll('tbody tr');
        const term = searchTerm.toLowerCase();
        
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            if (text.includes(term)) {
                row.style.display = '';
            } else {
                row.style.display = 'none';
            }
        });
    },
    
    /**
     * مرتب‌سازی جدول
     */
    sortTable(tableId, columnIndex, direction = 'asc') {
        const table = document.getElementById(tableId);
        if (!table) return;
        
        const tbody = table.querySelector('tbody');
        const rows = Array.from(tbody.querySelectorAll('tr'));
        
        rows.sort((a, b) => {
            const aVal = a.children[columnIndex]?.textContent.trim() || '';
            const bVal = b.children[columnIndex]?.textContent.trim() || '';
            
            // تشخیص نوع داده
            const aNum = parseFloat(aVal.replace(/[^\d.-]/g, ''));
            const bNum = parseFloat(bVal.replace(/[^\d.-]/g, ''));
            
            if (!isNaN(aNum) && !isNaN(bNum)) {
                return direction === 'asc' ? aNum - bNum : bNum - aNum;
            } else {
                return direction === 'asc' ? 
                    aVal.localeCompare(bVal, 'fa') : 
                    bVal.localeCompare(aVal, 'fa');
            }
        });
        
        rows.forEach(row => tbody.appendChild(row));
    }
};

/**
 * سیستم خودکار refresh
 */
const AutoRefresh = {
    
    /**
     * شروع refresh خودکار
     */
    start() {
        this.stop(); // توقف timer قبلی
        
        window.RuralSystem.timers.autoRefresh = setInterval(() => {
            this.checkForUpdates();
        }, window.RuralSystem.config.autoRefreshInterval);
    },
    
    /**
     * توقف refresh خودکار
     */
    stop() {
        if (window.RuralSystem.timers.autoRefresh) {
            clearInterval(window.RuralSystem.timers.autoRefresh);
            window.RuralSystem.timers.autoRefresh = null;
        }
    },
    
    /**
     * بررسی به‌روزرسانی‌ها
     */
    async checkForUpdates() {
        try {
            const response = await Ajax.get('ajax/check_updates.php');
            
            if (response.success) {
                // به‌روزرسانی شمارش نامه‌های خوانده نشده
                if (response.unread_count !== undefined) {
                    this.updateUnreadCount(response.unread_count);
                }
                
                // نمایش نوتیفیکیشن برای نامه‌های جدید
                if (response.new_messages > 0) {
                    Notification.info(`${response.new_messages} نامه جدید دریافت شد`);
                }
            }
        } catch (error) {
            console.error('خطا در بررسی به‌روزرسانی‌ها:', error);
        }
    },
    
    /**
     * به‌روزرسانی شمارش نامه‌های خوانده نشده
     */
    updateUnreadCount(count) {
        const badges = document.querySelectorAll('.unread-count');
        badges.forEach(badge => {
            if (count > 0) {
                badge.textContent = count;
                badge.style.display = 'inline';
            } else {
                badge.style.display = 'none';
            }
        });
    }
};

/**
 * میانبرهای کیبورد سراسری
 */
const KeyboardShortcuts = {
    
    /**
     * فعال‌سازی میانبرها
     */
    init() {
        document.addEventListener('keydown', (e) => {
            // Ctrl/Cmd + /: نمایش راهنما میانبرها
            if ((e.ctrlKey || e.metaKey) && e.key === '/') {
                e.preventDefault();
                this.showShortcutsHelp();
            }
            
            // ESC: بستن مودال‌ها و لغو عملیات
            if (e.key === 'Escape') {
                this.handleEscape();
            }
            
            // Alt + N: نامه جدید
            if (e.altKey && e.key === 'n') {
                e.preventDefault();
                window.location.href = '?page=compose';
            }
            
            // Alt + I: صندوق دریافت
            if (e.altKey && e.key === 'i') {
                e.preventDefault();
                window.location.href = '?page=inbox';
            }
            
            // Alt + S: نامه‌های ارسالی
            if (e.altKey && e.key === 's') {
                e.preventDefault();
                window.location.href = '?page=sent';
            }
            
            // Alt + R: گزارش‌ها
            if (e.altKey && e.key === 'r') {
                e.preventDefault();
                window.location.href = '?page=reports';
            }
        });
    },
    
    /**
     * نمایش راهنما میانبرها
     */
    showShortcutsHelp() {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-keyboard"></i> میانبرهای کیبورد
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>ناوبری</h6>
                                <div class="shortcut-item">
                                    <kbd>Alt + N</kbd> نامه جدید
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Alt + I</kbd> صندوق دریافت
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Alt + S</kbd> نامه‌های ارسالی
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Alt + R</kbd> گزارش‌ها
                                </div>
                            </div>
                            <div class="col-md-6">
                                <h6>عمومی</h6>
                                <div class="shortcut-item">
                                    <kbd>Ctrl + /</kbd> نمایش راهنما
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Esc</kbd> لغو / بستن
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Ctrl + Enter</kbd> ارسال فرم
                                </div>
                                <div class="shortcut-item">
                                    <kbd>Ctrl + S</kbd> ذخیره
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const bootstrapModal = new bootstrap.Modal(modal);
        bootstrapModal.show();
        
        // حذف مودال بعد از بسته شدن
        modal.addEventListener('hidden.bs.modal', () => {
            modal.remove();
        });
    },
    
    /**
     * مدیریت کلید Escape
     */
    handleEscape() {
        // بستن مودال‌های باز
        const openModals = document.querySelectorAll('.modal.show');
        openModals.forEach(modal => {
            const bootstrapModal = bootstrap.Modal.getInstance(modal);
            if (bootstrapModal) {
                bootstrapModal.hide();
            }
        });
        
        // لغو انتخاب‌ها
        const selectedItems = document.querySelectorAll('.selected');
        selectedItems.forEach(item => {
            item.classList.remove('selected');
        });
    }
};

/**
 * راه‌اندازی اولیه سیستم
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // فعال‌سازی تولتیپ‌ها
    const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // فعال‌سازی پاپ‌اورها
    const popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // فعال‌سازی میانبرهای کیبورد
    KeyboardShortcuts.init();
    
    // شروع refresh خودکار (فقط در صفحات مناسب)
    const currentPage = new URLSearchParams(window.location.search).get('page');
    if (['dashboard', 'inbox', 'sent'].includes(currentPage)) {
        AutoRefresh.start();
    }
    
    // مدیریت فرم‌ها
    document.querySelectorAll('form').forEach(form => {
        form.addEventListener('submit', function(e) {
            if (!FormManager.validateForm(this)) {
                e.preventDefault();
            }
        });
    });
    
    // مدیریت فایل‌های آپلود
    document.querySelectorAll('input[type="file"]').forEach(input => {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            if (file) {
                const validation = FileManager.validateFile(file);
                if (!validation.valid) {
                    Notification.error(validation.errors.join('<br>'));
                    this.value = '';
                    return;
                }
                
                // نمایش پیش‌نمایش
                const previewContainer = this.parentNode.querySelector('.file-preview');
                if (previewContainer) {
                    FileManager.previewFile(file, previewContainer);
                }
            }
        });
    });
    
    // مدیریت لینک‌های AJAX
    document.querySelectorAll('[data-ajax]').forEach(link => {
        link.addEventListener('click', async function(e) {
            e.preventDefault();
            
            const url = this.getAttribute('href') || this.getAttribute('data-url');
            const method = this.getAttribute('data-method') || 'GET';
            const confirm = this.getAttribute('data-confirm');
            
            if (confirm && !window.confirm(confirm)) {
                return;
            }
            
            try {
                const response = await Ajax.request(url, { method });
                
                if (response.success) {
                    Notification.success(response.message || 'عملیات با موفقیت انجام شد');
                    
                    // به‌روزرسانی صفحه در صورت نیاز
                    if (response.reload) {
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    }
                } else {
                    Notification.error(response.message || 'خطا در انجام عملیات');
                }
            } catch (error) {
                Notification.error('خطا در ارتباط با سرور');
                console.error(error);
            }
        });
    });
    
    // مدیریت جستجوی زنده
    document.querySelectorAll('[data-live-search]').forEach(input => {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const target = this.getAttribute('data-target');
                const table = document.querySelector(target);
                if (table) {
                    TableManager.searchTable(table.id, this.value);
                }
            }, 300);
        });
    });
    
    // انیمیشن fade-in برای کارت‌ها
    const observerOptions = {
        threshold: 0.1,
        rootMargin: '0px 0px -50px 0px'
    };
    
    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                entry.target.classList.add('fade-in');
            }
        });
    }, observerOptions);
    
    document.querySelectorAll('.card').forEach(card => {
        observer.observe(card);
    });
    
    // مدیریت ریسپانسیو sidebar
    const sidebarToggle = document.querySelector('.navbar-toggler');
    const sidebar = document.querySelector('.sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('show');
        });
        
        // بستن sidebar با کلیک خارج از آن
        document.addEventListener('click', function(e) {
            if (window.innerWidth <= 991 && 
                !sidebar.contains(e.target) && 
                !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('show');
            }
        });
    }
});

// توابع سراسری برای دسترسی آسان
window.Utils = Utils;
window.Notification = Notification;
window.Ajax = Ajax;
window.FileManager = FileManager;
window.FormManager = FormManager;
window.TableManager = TableManager;
window.AutoRefresh = AutoRefresh;

// مدیریت خطاهای سراسری JavaScript
window.addEventListener('error', function(e) {
    console.error('JavaScript Error:', e.error);
    
    if (window.location.hostname !== 'localhost') {
        // ارسال خطا به سرور برای لاگ (در صورت نیاز)
        Ajax.post('ajax/log_error.php', {
            error: e.error.toString(),
            file: e.filename,
            line: e.lineno,
            url: window.location.href
        }).catch(() => {
            // خطا در ارسال لاگ
        });
    }
});

// CSS اضافی برای استایل‌های JavaScript
const additionalStyles = `
    .shortcut-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 0.25rem 0;
        border-bottom: 1px solid #f0f0f0;
    }
    
    .shortcut-item:last-child {
        border-bottom: none;
    }
    
    .file-preview {
        margin-top: 1rem;
    }
    
    .fade-in {
        animation: fadeInUp 0.6s ease-out;
    }
    
    @keyframes fadeInUp {
        from {
            opacity: 0;
            transform: translateY(30px);
        }
        to {
            opacity: 1;
            transform: translateY(0);
        }
    }
    
    .loading {
        position: relative;
        pointer-events: none;
    }
    
    .loading::after {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.8);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
    }
`;

const styleSheet = document.createElement('style');
styleSheet.textContent = additionalStyles;
document.head.appendChild(styleSheet);