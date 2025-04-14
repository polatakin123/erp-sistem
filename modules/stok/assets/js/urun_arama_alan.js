// Alanları özelleştirme işlevselliği
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa ilk yüklendiğinde modal karartmalarını temizle (önceki oturumdan kalma olabilir)
    const backdrops = document.getElementsByClassName('modal-backdrop');
    for (let i = backdrops.length - 1; i >= 0; i--) {
        backdrops[i].style.visibility = 'hidden';
        backdrops[i].style.display = 'none';
        if (backdrops[i].parentNode) {
            backdrops[i].parentNode.removeChild(backdrops[i]);
        }
    }
    
    // Body'deki modal-open sınıfını kaldır
    document.body.classList.remove('modal-open');
    document.body.style.overflow = '';
    document.body.style.paddingRight = '';
    
    // Alanları Kaydet butonu için olay dinleyicisi
    const alanKaydetBtn = document.getElementById('alanKaydet');
    if (alanKaydetBtn) {
        alanKaydetBtn.addEventListener('click', function() {
            // Tüm seçili alanları topla
            const tercihler = {};
            document.querySelectorAll('.alan-secim').forEach(function(checkbox) {
                const alanAdi = checkbox.id.replace('alan_', '');
                tercihler[alanAdi] = checkbox.checked;
            });
            
            // AJAX ile tercihleri kaydet
            const xhr = new XMLHttpRequest();
            xhr.open('POST', 'ajax_kullanici_tercihi.php', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            xhr.onload = function() {
                try {
                    if (xhr.status === 200) {
                        console.log('Sunucu yanıtı:', xhr.responseText);
                        const response = JSON.parse(xhr.responseText);
                        if (response.success) {
                            // Başarı mesajı göster
                            showNotification('Tercihleriniz başarıyla kaydedildi. Sayfa yenileniyor...', 'success');
                            
                            // Doğrudan DOM manipülasyonu ile modal ve arka planı temizle
                            const modalElement = document.getElementById('alanlarModal');
                            // Style özelliklerini değiştir - kullanıcının önerdiği gibi
                            modalElement.style.visibility = 'hidden';
                            modalElement.style.display = 'none';
                            
                            // Arka plan karartmasını temizle
                            const backdrops = document.getElementsByClassName('modal-backdrop');
                            for (let i = 0; i < backdrops.length; i++) {
                                backdrops[i].style.visibility = 'hidden';
                                backdrops[i].style.display = 'none';
                                if (backdrops[i].parentNode) {
                                    backdrops[i].parentNode.removeChild(backdrops[i]);
                                }
                            }
                            
                            // Body'deki modal-open sınıfını kaldır
                            document.body.classList.remove('modal-open');
                            document.body.style.overflow = '';
                            document.body.style.paddingRight = '';
                            
                            // Tüm temizliklerden sonra kısa bir bekleme ve sayfayı yenile
                            setTimeout(function() {
                                window.location.reload();
                            }, 1000);
                        } else {
                            showNotification('Hata: ' + response.message, 'danger');
                        }
                    } else {
                        showNotification('Sunucu hatası: ' + xhr.status, 'danger');
                    }
                } catch (error) {
                    console.error('AJAX yanıtı işlenirken hata:', error);
                    console.error('Ham yanıt:', xhr.responseText);
                    showNotification('AJAX yanıtı işlenirken hata: ' + error.message, 'danger');
                }
            };
            xhr.onerror = function(error) {
                console.error('AJAX isteği sırasında hata:', error);
                showNotification('Bağlantı hatası', 'danger');
            };
            xhr.send('tercihler=' + encodeURIComponent(JSON.stringify(tercihler)));
        });
    }
    
    // Sayfa yüklendiğinde mevcut tercihlerle tabloyu güncelle
    const tercihler = {};
    document.querySelectorAll('.alan-secim').forEach(function(checkbox) {
        const alanAdi = checkbox.id.replace('alan_', '');
        tercihler[alanAdi] = checkbox.checked;
    });
    
    // Tabloyu mevcut tercihlerle güncelle
    updateTableColumns(tercihler);
    
    // Tablo sütunlarını tercihlerine göre güncelle
    function updateTableColumns(tercihler) {
        try {
            // Tablolar listesi - tüm ürün tablolarını güncelle
            const tables = document.querySelectorAll('.table-bordered');
            if (tables.length === 0) {
                console.error('Hiç tablo bulunamadı!');
                return;
            }
            
            tables.forEach(table => {
                // Başlık satırı
                const thead = table.querySelector('thead tr');
                // Veri satırları
                const tbody = table.querySelector('tbody');
                
                if (!thead || !tbody) {
                    console.error('Tabloda thead veya tbody bulunamadı!');
                    return;
                }
                
                // Her bir alan için sütunları gizle/göster
                for (const [alan, goster] of Object.entries(tercihler)) {
                    // Başlıktaki sütun indeksini bul
                    let index = -1;
                    for (let i = 0; i < thead.children.length; i++) {
                        const thElement = thead.children[i];
                        if (thElement.getAttribute('data-alan') === alan) {
                            index = i;
                            break;
                        }
                    }
                    
                    if (index !== -1) {
                        // Başlıktaki hücreyi gizle/göster
                        const th = thead.children[index];
                        th.style.display = goster ? '' : 'none';
                        
                        // Tüm satırlarda ilgili hücreleri gizle/göster
                        Array.from(tbody.children).forEach(row => {
                            if (row.children[index]) {
                                row.children[index].style.display = goster ? '' : 'none';
                            }
                        });
                    }
                }
            });
        } catch (error) {
            console.error('Tablo güncellenirken hata oluştu:', error);
            showNotification('Tablo güncellenirken hata oluştu: ' + error.message, 'danger');
        }
    }
    
    // Bildirim göster
    function showNotification(message, type) {
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show position-fixed`;
        alertDiv.setAttribute('role', 'alert');
        alertDiv.style.top = '20px';
        alertDiv.style.right = '20px';
        alertDiv.style.zIndex = '9999';
        
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
        `;
        
        document.body.appendChild(alertDiv);
        
        // 3 saniye sonra kaldır
        setTimeout(() => {
            alertDiv.remove();
        }, 3000);
    }
    
    // Modal olayları için dinleyiciler ekle
    const alanlarModal = document.getElementById('alanlarModal');
    if (alanlarModal) {
        alanlarModal.addEventListener('hidden.bs.modal', function () {
            console.log('Modal kapandı, sayfayı yeniliyorum...');
            // Modalı gizle
            alanlarModal.style.visibility = 'hidden';
            alanlarModal.style.display = 'none';
            
            // Arka plan karartmasını temizle
            const backdrops = document.getElementsByClassName('modal-backdrop');
            for (let i = 0; i < backdrops.length; i++) {
                backdrops[i].style.visibility = 'hidden';
                backdrops[i].style.display = 'none';
                if (backdrops[i].parentNode) {
                    backdrops[i].parentNode.removeChild(backdrops[i]);
                }
            }
            
            // Body'deki modal-open sınıfını kaldır
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
            
            // Sayfayı yenile
            setTimeout(function() {
                window.location.reload();
            }, 500);
        });
        
        alanlarModal.addEventListener('show.bs.modal', function () {
            console.log('Modal açılıyor...');
        });
    }
}); 