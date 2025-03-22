/**
 * ERP Sistem - Ana Script Dosyası
 * 
 * Bu dosya sistemin JavaScript fonksiyonlarını içerir.
 */

// Doküman hazır olduğunda çalıştırılacak fonksiyonlar
document.addEventListener('DOMContentLoaded', function() {
    // Sidebar toggle
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            document.querySelector('.sidebar').classList.toggle('show');
        });
    }

    // Dropdown menüleri initialize et
    initDropdowns();

    // Tooltips initialize et
    initTooltips();

    // Modal işlevleri
    initModals();

    // DataTables initialize et (eğer sayfada DataTable varsa)
    initDataTables();

    // Form validasyonlarını aktif et
    initFormValidations();

    // Select2 initialize et (eğer sayfada Select2 varsa)
    initSelect2();

    // DatePicker initialize et (eğer sayfada DatePicker varsa)
    initDatePickers();

    // MaskInput initialize et (eğer sayfada MaskInput varsa)
    initInputMasks();

    // AJAX form submit işlemleri
    initAjaxForms();

    // Sidebar active class ekle
    setSidebarActive();
});

// Dropdown Menüleri Aktifleştir
function initDropdowns() {
    const dropdownToggles = document.querySelectorAll('.dropdown-toggle');
    if (dropdownToggles.length > 0) {
        dropdownToggles.forEach(function(toggle) {
            toggle.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                const parent = this.parentNode;
                parent.classList.toggle('show');
                const dropdownMenu = parent.querySelector('.dropdown-menu');
                if (dropdownMenu) {
                    dropdownMenu.classList.toggle('show');
                }
            });
        });

        // Dropdown menüyü dışarı tıklandığında kapat
        document.addEventListener('click', function(e) {
            const dropdowns = document.querySelectorAll('.dropdown.show');
            dropdowns.forEach(function(dropdown) {
                if (!dropdown.contains(e.target)) {
                    dropdown.classList.remove('show');
                    const dropdownMenu = dropdown.querySelector('.dropdown-menu');
                    if (dropdownMenu) {
                        dropdownMenu.classList.remove('show');
                    }
                }
            });
        });
    }
}

// Tooltips Aktifleştir
function initTooltips() {
    const tooltips = document.querySelectorAll('[data-bs-toggle="tooltip"]');
    if (tooltips.length > 0 && typeof bootstrap !== 'undefined') {
        tooltips.forEach(function(tooltip) {
            new bootstrap.Tooltip(tooltip);
        });
    }
}

// Modal İşlevleri
function initModals() {
    // Modal açma işlevi
    const modalTriggers = document.querySelectorAll('[data-bs-toggle="modal"]');
    if (modalTriggers.length > 0) {
        modalTriggers.forEach(function(trigger) {
            trigger.addEventListener('click', function(e) {
                e.preventDefault();
                const target = this.getAttribute('data-bs-target');
                const modal = document.querySelector(target);
                if (modal && typeof bootstrap !== 'undefined') {
                    const bsModal = new bootstrap.Modal(modal);
                    bsModal.show();
                }
            });
        });
    }

    // Modal içindeki formları temizle
    const modals = document.querySelectorAll('.modal');
    if (modals.length > 0 && typeof bootstrap !== 'undefined') {
        modals.forEach(function(modal) {
            modal.addEventListener('hidden.bs.modal', function() {
                const forms = this.querySelectorAll('form');
                forms.forEach(function(form) {
                    form.reset();
                });
            });
        });
    }
}

// DataTables Aktifleştir
function initDataTables() {
    const tables = document.querySelectorAll('.datatable');
    if (tables.length > 0 && typeof $.fn.DataTable !== 'undefined') {
        tables.forEach(function(table) {
            const options = {
                responsive: true,
                autoWidth: false,
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.11.5/i18n/tr.json'
                }
            };

            if (table.hasAttribute('data-options')) {
                try {
                    const customOptions = JSON.parse(table.getAttribute('data-options'));
                    Object.assign(options, customOptions);
                } catch (e) {
                    console.error('DataTable options JSON parse error:', e);
                }
            }

            $(table).DataTable(options);
        });
    }
}

// Form Validasyonlarını Aktifleştir
function initFormValidations() {
    const forms = document.querySelectorAll('.needs-validation');
    if (forms.length > 0) {
        forms.forEach(function(form) {
            form.addEventListener('submit', function(event) {
                if (!form.checkValidity()) {
                    event.preventDefault();
                    event.stopPropagation();
                }
                form.classList.add('was-validated');
            }, false);
        });
    }
}

// Select2 Aktifleştir
function initSelect2() {
    const select2Elements = document.querySelectorAll('.select2');
    if (select2Elements.length > 0 && typeof $.fn.select2 !== 'undefined') {
        $(select2Elements).select2({
            theme: 'bootstrap-5',
            width: 'resolve'
        });
    }
}

// DatePicker Aktifleştir
function initDatePickers() {
    const datepickers = document.querySelectorAll('.datepicker');
    if (datepickers.length > 0 && typeof $.fn.datepicker !== 'undefined') {
        $(datepickers).datepicker({
            format: 'dd.mm.yyyy',
            language: 'tr',
            autoclose: true,
            todayHighlight: true
        });
    }
}

// Input Mask Aktifleştir
function initInputMasks() {
    // Telefon numaraları için
    const phoneMasks = document.querySelectorAll('.phone-mask');
    if (phoneMasks.length > 0 && typeof $.fn.mask !== 'undefined') {
        $(phoneMasks).mask('(999) 999-9999');
    }

    // Para birimleri için
    const currencyMasks = document.querySelectorAll('.currency-mask');
    if (currencyMasks.length > 0 && typeof $.fn.mask !== 'undefined') {
        $(currencyMasks).mask('000.000.000.000.000,00', {reverse: true});
    }

    // Tarih birimleri için
    const dateMasks = document.querySelectorAll('.date-mask');
    if (dateMasks.length > 0 && typeof $.fn.mask !== 'undefined') {
        $(dateMasks).mask('99.99.9999');
    }
}

// AJAX Form İşlemleri
function initAjaxForms() {
    const ajaxForms = document.querySelectorAll('.ajax-form');
    if (ajaxForms.length > 0) {
        ajaxForms.forEach(function(form) {
            form.addEventListener('submit', function(e) {
                e.preventDefault();
                const formData = new FormData(form);
                const url = form.getAttribute('action');
                const method = form.getAttribute('method') || 'POST';
                const submitButton = form.querySelector('[type="submit"]');
                const originalButtonText = submitButton ? submitButton.innerHTML : null;

                // Submit butonunu devre dışı bırak
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> İşleniyor...';
                }

                // Form verilerini gönder
                fetch(url, {
                    method: method,
                    body: formData,
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                })
                .then(response => response.json())
                .then(data => {
                    // Başarılı işlem
                    if (data.success) {
                        showNotification(data.message || 'İşlem başarıyla tamamlandı', 'success');
                        
                        // Yönlendirme varsa
                        if (data.redirect) {
                            setTimeout(function() {
                                window.location.href = data.redirect;
                            }, 1500);
                        }
                        
                        // Form reset
                        if (!data.prevent_reset) {
                            form.reset();
                        }
                        
                        // Event trigger
                        const event = new CustomEvent('ajaxFormSuccess', { detail: data });
                        document.dispatchEvent(event);
                    } else {
                        // Hata mesajı
                        showNotification(data.message || 'İşlem sırasında bir hata oluştu', 'error');
                    }
                })
                .catch(error => {
                    console.error('AJAX Error:', error);
                    showNotification('İşlem sırasında bir hata oluştu: ' + error.message, 'error');
                })
                .finally(() => {
                    // Submit butonunu tekrar aktif et
                    if (submitButton) {
                        submitButton.disabled = false;
                        submitButton.innerHTML = originalButtonText;
                    }
                });
            });
        });
    }
}

// Bildirim Göster
function showNotification(message, type = 'info') {
    if (typeof $.notify !== 'undefined') {
        $.notify({
            message: message
        }, {
            type: type,
            placement: {
                from: 'top',
                align: 'center'
            },
            z_index: 9999,
            delay: 3000,
            animate: {
                enter: 'animated fadeInDown',
                exit: 'animated fadeOutUp'
            }
        });
    } else if (typeof bootstrap !== 'undefined' && typeof bootstrap.Toast !== 'undefined') {
        // Bootstrap Toast kullan
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0`;
        toast.setAttribute('role', 'alert');
        toast.setAttribute('aria-live', 'assertive');
        toast.setAttribute('aria-atomic', 'true');
        
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body">
                    ${message}
                </div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
            </div>
        `;
        
        const toastContainer = document.querySelector('.toast-container');
        if (!toastContainer) {
            const container = document.createElement('div');
            container.className = 'toast-container position-fixed top-0 end-0 p-3';
            document.body.appendChild(container);
            container.appendChild(toast);
        } else {
            toastContainer.appendChild(toast);
        }
        
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
        
        // Toast'ı kapat ve DOM'dan kaldır
        toast.addEventListener('hidden.bs.toast', function() {
            toast.remove();
        });
    } else {
        // Basit alert kullan
        alert(message);
    }
}

// Sidebar Active Class Ekle
function setSidebarActive() {
    const currentPath = window.location.pathname;
    const sidebarLinks = document.querySelectorAll('.sidebar .nav-link');
    
    sidebarLinks.forEach(function(link) {
        const href = link.getAttribute('href');
        if (href && currentPath.includes(href) && href !== '#' && href !== '/') {
            link.classList.add('active');
            
            // Parent kategorileri aç
            const parentLi = link.closest('li.nav-item');
            if (parentLi) {
                const parentUl = parentLi.closest('ul.submenu');
                if (parentUl) {
                    parentUl.style.display = 'block';
                    const parentNavItem = parentUl.closest('li.nav-item');
                    if (parentNavItem) {
                        const parentLink = parentNavItem.querySelector('.nav-link');
                        if (parentLink) {
                            parentLink.classList.add('active');
                        }
                    }
                }
            }
        }
    });
}

// Sayı Formatlama
function formatNumber(number, decimals = 2, dec_point = ',', thousands_sep = '.') {
    number = (number + '').replace(/[^0-9+\-Ee.]/g, '');
    const n = !isFinite(+number) ? 0 : +number;
    const prec = !isFinite(+decimals) ? 0 : Math.abs(decimals);
    const sep = (typeof thousands_sep === 'undefined') ? ',' : thousands_sep;
    const dec = (typeof dec_point === 'undefined') ? '.' : dec_point;
    
    let s = '';
    const toFixedFix = function(n, prec) {
        const k = Math.pow(10, prec);
        return '' + Math.round(n * k) / k;
    };
    
    s = (prec ? toFixedFix(n, prec) : '' + Math.round(n)).split('.');
    if (s[0].length > 3) {
        s[0] = s[0].replace(/\B(?=(?:\d{3})+(?!\d))/g, sep);
    }
    if ((s[1] || '').length < prec) {
        s[1] = s[1] || '';
        s[1] += new Array(prec - s[1].length + 1).join('0');
    }
    
    return s.join(dec);
}

// Para Birimi Formatla
function formatCurrency(amount, symbol = '₺') {
    return symbol + formatNumber(amount, 2, ',', '.');
}

// Tarih Formatla
function formatDate(date, format = 'dd.mm.yyyy') {
    if (!(date instanceof Date)) {
        date = new Date(date);
    }
    
    const day = date.getDate().toString().padStart(2, '0');
    const month = (date.getMonth() + 1).toString().padStart(2, '0');
    const year = date.getFullYear();
    
    return format
        .replace('dd', day)
        .replace('mm', month)
        .replace('yyyy', year);
}

// Veri Yazdırma
function printElement(element) {
    const printWindow = window.open('', '_blank');
    const styles = Array.from(document.styleSheets)
        .map(styleSheet => {
            try {
                return Array.from(styleSheet.cssRules)
                    .map(rule => rule.cssText)
                    .join('\n');
            } catch (e) {
                return null;
            }
        })
        .filter(Boolean)
        .join('\n');
    
    printWindow.document.write(`
        <!DOCTYPE html>
        <html>
        <head>
            <title>Yazdırma</title>
            <style>${styles}</style>
        </head>
        <body>
            ${element.outerHTML}
        </body>
        </html>
    `);
    
    printWindow.document.close();
    printWindow.focus();
    
    // Yazdırma işlemi
    setTimeout(() => {
        printWindow.print();
        printWindow.close();
    }, 1000);
}

// DataTable veri Excel'e aktarma
function exportTableToExcel(tableId, filename = 'tablo') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const ws = XLSX.utils.table_to_sheet(table);
    const wb = XLSX.utils.book_new();
    XLSX.utils.book_append_sheet(wb, ws, "Sayfa1");
    XLSX.writeFile(wb, filename + '.xlsx');
}

// AJAX ile veri getirme
function fetchData(url, options = {}) {
    const defaultOptions = {
        method: 'GET',
        headers: {
            'X-Requested-With': 'XMLHttpRequest',
            'Content-Type': 'application/json'
        }
    };
    
    const mergedOptions = {...defaultOptions, ...options};
    
    if (options.body && typeof options.body === 'object') {
        mergedOptions.body = JSON.stringify(options.body);
    }
    
    return fetch(url, mergedOptions)
        .then(response => {
            if (!response.ok) {
                throw new Error('Network response was not ok');
            }
            return response.json();
        });
}

// Grafik yüksekliği ayarla
function resizeCharts() {
    const chartAreas = document.querySelectorAll('.chart-area');
    const chartPies = document.querySelectorAll('.chart-pie');
    
    if (window.innerWidth < 768) {
        chartAreas.forEach(chart => {
            chart.style.height = '15rem';
        });
        chartPies.forEach(chart => {
            chart.style.height = '12rem';
        });
    } else {
        chartAreas.forEach(chart => {
            chart.style.height = '20rem';
        });
        chartPies.forEach(chart => {
            chart.style.height = '15rem';
        });
    }
}

// Window Resize Eventi
window.addEventListener('resize', function() {
    resizeCharts();
});

// Kullanıcı aktivitesi takibi
let idleTime = 0;
function resetIdleTime() {
    idleTime = 0;
}

// Her 1 dakikada bir kontrol et
setInterval(function() {
    idleTime += 1;
    // 30 dakika (30 * 1) sonra oturumu sonlandır
    if (idleTime >= 30) {
        // Kullanıcıyı bilgilendir
        if (confirm('Uzun süredir işlem yapmadınız. Oturumunuz sonlandırılacak. Devam etmek istiyor musunuz?')) {
            resetIdleTime();
        } else {
            window.location.href = 'logout.php';
        }
    }
}, 60000); // 1 dakika = 60000 ms

// Kullanıcı aktif olduğunda sayacı sıfırla
['mousedown', 'mousemove', 'keypress', 'scroll', 'touchstart'].forEach(function(name) {
    document.addEventListener(name, resetIdleTime, true);
}); 