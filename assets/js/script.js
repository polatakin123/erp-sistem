/**
 * ERP Sistem - Özel JavaScript Kodları
 */

// Sayfa yüklendiğinde çalışacak kodlar
document.addEventListener('DOMContentLoaded', function() {
    // Tooltips'i etkinleştir
    var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
    var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
        return new bootstrap.Tooltip(tooltipTriggerEl);
    });
    
    // Popovers'ı etkinleştir
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'));
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl);
    });
    
    // Tarih seçicileri etkinleştir
    initDatePickers();
    
    // Form doğrulama
    initFormValidation();
});

// Tarih seçicileri başlat
function initDatePickers() {
    // Tarih seçici sınıfına sahip tüm inputları seç
    var dateInputs = document.querySelectorAll('.datepicker');
    
    // Her bir input için flatpickr'ı başlat
    if (dateInputs.length > 0 && typeof flatpickr !== 'undefined') {
        dateInputs.forEach(function(input) {
            flatpickr(input, {
                dateFormat: "d.m.Y",
                locale: "tr",
                allowInput: true
            });
        });
    }
}

// Form doğrulama
function initFormValidation() {
    // Doğrulama sınıfına sahip tüm formları seç
    var forms = document.querySelectorAll('.needs-validation');
    
    // Her bir form için doğrulama işlemini başlat
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
}

// Para formatı
function formatMoney(amount) {
    return new Intl.NumberFormat('tr-TR', { style: 'currency', currency: 'TRY' }).format(amount);
}

// Tarih formatı
function formatDate(date) {
    if (!date) return '';
    
    if (typeof date === 'string') {
        date = new Date(date);
    }
    
    return new Intl.DateTimeFormat('tr-TR').format(date);
}

// AJAX isteği gönder
function sendAjaxRequest(url, method, data, successCallback, errorCallback) {
    // Fetch API kullanarak AJAX isteği gönder
    fetch(url, {
        method: method,
        headers: {
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: method !== 'GET' ? JSON.stringify(data) : null
    })
    .then(response => {
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        return response.json();
    })
    .then(data => {
        if (typeof successCallback === 'function') {
            successCallback(data);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        if (typeof errorCallback === 'function') {
            errorCallback(error);
        }
    });
}

// Bildirim göster
function showNotification(message, type = 'info', duration = 3000) {
    // Toast bildirimi oluştur
    var toast = document.createElement('div');
    toast.className = 'toast align-items-center text-white bg-' + type;
    toast.setAttribute('role', 'alert');
    toast.setAttribute('aria-live', 'assertive');
    toast.setAttribute('aria-atomic', 'true');
    
    // Toast içeriği
    var toastContent = `
        <div class="d-flex">
            <div class="toast-body">
                ${message}
            </div>
            <button type="button" class="btn-close me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
        </div>
    `;
    
    toast.innerHTML = toastContent;
    
    // Toast container'ı kontrol et veya oluştur
    var toastContainer = document.querySelector('.toast-container');
    if (!toastContainer) {
        toastContainer = document.createElement('div');
        toastContainer.className = 'toast-container position-fixed bottom-0 end-0 p-3';
        document.body.appendChild(toastContainer);
    }
    
    // Toast'u container'a ekle
    toastContainer.appendChild(toast);
    
    // Toast'u göster
    var bsToast = new bootstrap.Toast(toast, {
        autohide: true,
        delay: duration
    });
    
    bsToast.show();
    
    // Belirtilen süre sonra toast'u kaldır
    setTimeout(function() {
        if (toastContainer.contains(toast)) {
            toastContainer.removeChild(toast);
        }
    }, duration + 500);
}

// Onay kutusu göster
function showConfirmDialog(title, message, confirmCallback, cancelCallback) {
    // Modal oluştur
    var modalId = 'confirmModal' + Date.now();
    var modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = modalId;
    modal.setAttribute('tabindex', '-1');
    modal.setAttribute('aria-labelledby', modalId + 'Label');
    modal.setAttribute('aria-hidden', 'true');
    
    // Modal içeriği
    var modalContent = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="${modalId}Label">${title}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    ${message}
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="button" class="btn btn-primary confirm-btn">Onayla</button>
                </div>
            </div>
        </div>
    `;
    
    modal.innerHTML = modalContent;
    
    // Modal'ı body'e ekle
    document.body.appendChild(modal);
    
    // Modal nesnesini oluştur
    var bsModal = new bootstrap.Modal(modal);
    
    // Onay butonuna tıklama olayı ekle
    modal.querySelector('.confirm-btn').addEventListener('click', function() {
        bsModal.hide();
        if (typeof confirmCallback === 'function') {
            confirmCallback();
        }
    });
    
    // Modal kapandığında
    modal.addEventListener('hidden.bs.modal', function() {
        // Modal'ı DOM'dan kaldır
        document.body.removeChild(modal);
        
        // İptal callback'i çağır
        if (typeof cancelCallback === 'function') {
            cancelCallback();
        }
    });
    
    // Modal'ı göster
    bsModal.show();
} 