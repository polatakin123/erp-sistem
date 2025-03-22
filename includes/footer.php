<?php
/**
 * Footer Dosyası
 * 
 * Bu dosya sayfanın alt kısmını ve gerekli JavaScript kütüphanelerini içerir.
 */

// Ana dizin yolunu belirle
$rootPath = '';
if (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) {
    $rootPath = '../../';
} else {
    $rootPath = '';
}
?>
            </main>
        </div>
    </div>

    <footer class="footer mt-auto py-3 bg-light">
        <div class="container">
            <div class="row">
                <div class="col-md-6">
                    <span class="text-muted">&copy; <?php echo date('Y'); ?> ERP Sistem. Tüm hakları saklıdır.</span>
                </div>
                <div class="col-md-6 text-end">
                    <span class="text-muted">Versiyon 1.0.0</span>
                </div>
            </div>
        </div>
    </footer>

    <!-- jQuery - jQuery CDN'deki olası bir sorun durumunda yerel kütüphaneyi de ekleyelim -->
    <script>
    if (typeof jQuery === "undefined") {
        console.log("jQuery header.php'de yüklenemedi, yeniden yükleme deneniyor...");
        document.write('<script src="https://code.jquery.com/jquery-3.6.0.min.js"><\/script>');
        document.write('<script src="<?php echo $rootPath; ?>assets/js/jquery-3.6.0.min.js"><\/script>');
    }
    </script>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>

    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

    <!-- Bootstrap Datepicker -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/js/bootstrap-datepicker.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/locales/bootstrap-datepicker.tr.min.js"></script>

    <!-- Select2 -->
    <script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@3.7.1/dist/chart.min.js"></script>

    <!-- Custom Script -->
    <script src="<?php echo $rootPath; ?>assets/js/scripts.js"></script>

    <script>
        // DataTable için Türkçe dil desteği
        const dataTablesTurkishLanguage = {
            "emptyTable": "Tabloda herhangi bir veri mevcut değil",
            "info": "_TOTAL_ kayıttan _START_ - _END_ arasındaki kayıtlar gösteriliyor",
            "infoEmpty": "Kayıt yok",
            "infoFiltered": "(_MAX_ kayıt içerisinden bulunan)",
            "infoThousands": ".",
            "lengthMenu": "Sayfada _MENU_ kayıt göster",
            "loadingRecords": "Yükleniyor...",
            "processing": "İşleniyor...",
            "search": "Ara:",
            "zeroRecords": "Eşleşen kayıt bulunamadı",
            "paginate": {
                "first": "İlk",
                "last": "Son",
                "next": "Sonraki",
                "previous": "Önceki"
            },
            "aria": {
                "sortAscending": ": artan sütun sıralamasını aktifleştir",
                "sortDescending": ": azalan sütun sıralamasını aktifleştir"
            },
            "select": {
                "rows": {
                    "_": "%d kayıt seçildi",
                    "1": "1 kayıt seçildi"
                }
            },
            "buttons": {
                "print": "Yazdır",
                "copyKeys": "Ctrl veya CTRL + Enter tuşlarına basarak konum panosuna kopyalayın ve tablo verilerini seçin veya bir web uygulamasını yakalayın.<br \/><br \/>Bu mesajı kapatmak için tıklayın veya escape tuşuna basın.",
                "copySuccess": {
                    "_": "%d satır panoya kopyalandı",
                    "1": "1 satır panoya kopyalandı"
                },
                "copyTitle": "Panoya Kopyala",
                "csv": "CSV",
                "excel": "Excel",
                "pdf": "PDF",
                "copyColumns": "Kopyala",
                "collection": "Koleksiyon <span class=\"ui-button-icon-primary ui-icon ui-icon-triangle-1-s\"><\/span>",
                "colvis": "Sütun Görünürlüğü"
            }
        };

        // DOM Yükleme Kontrolü
        $(document).ready(function() {
            console.log("Footer: Document ready tetiklendi");
            
            // jQuery durumunu kontrol et
            if (typeof jQuery !== 'undefined') {
                console.log("Footer: jQuery yüklü, versiyon: " + jQuery.fn.jquery);
            } else {
                console.error("Footer: jQuery yüklü değil!");
            }
            
            // DataTable durumunu kontrol et
            if (typeof $.fn.DataTable !== 'undefined') {
                console.log("Footer: DataTable kütüphanesi yüklü");
            } else {
                console.error("Footer: DataTable kütüphanesi yüklü değil!");
                return; // DataTable yüklü değilse devam etme
            }

            try {
                // DataTable başlatma
                console.log("Footer: DataTable başlatma işlemi başlıyor...");
                
                // Önce tüm mevcut DataTable'ları yok et
                if ($.fn.DataTable.isDataTable('#productTable')) {
                    console.log("Footer: productTable zaten başlatılmış, önce destroy ediliyor");
                    $('#productTable').DataTable().destroy();
                }
                
                // Ürün tablosunu başlat
                if ($('#productTable').length > 0) {
                    console.log("Footer: #productTable elementi bulundu, DataTable başlatılıyor");
                    $('#productTable').DataTable({
                        "language": dataTablesTurkishLanguage,
                        "responsive": true,
                        "autoWidth": false,
                        "pageLength": 10,
                        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tümü"]]
                    });
                    console.log("Footer: #productTable DataTable olarak başlatıldı");
                } else {
                    console.log("Footer: #productTable elementi bulunamadı");
                }
                
                // Genel .datatable sınıfına sahip tablolar için
                $('.datatable:not(#productTable)').each(function() {
                    var tableId = $(this).attr('id');
                    console.log("Footer: .datatable tablosu işleniyor: " + (tableId || 'isimsiz'));
                    
                    if(tableId && $.fn.DataTable.isDataTable('#' + tableId)) {
                        console.log("Footer: " + tableId + " tablosu zaten başlatılmış, önce destroy ediliyor");
                        $('#' + tableId).DataTable().destroy();
                    }
                    
                    $(this).DataTable({
                        "language": dataTablesTurkishLanguage,
                        "responsive": true,
                        "autoWidth": false,
                        "pageLength": 10,
                        "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tümü"]]
                    });
                    console.log("Footer: " + (tableId || 'isimsiz') + " tablosu DataTable olarak başlatıldı");
                });
            } catch (e) {
                console.error("Footer: DataTable başlatma hatası: ", e);
            }
            
            // Bootstrap Datepicker
            if($.fn.datepicker) {
                console.log("Footer: Datepicker yüklü, başlatılıyor");
                $('.datepicker').datepicker({
                    format: 'dd.mm.yyyy',
                    language: 'tr',
                    autoclose: true,
                    todayHighlight: true
                });
            } else {
                console.log("Footer: Datepicker yüklü değil");
            }
            
            // Select2
            if($.fn.select2) {
                console.log("Footer: Select2 yüklü, başlatılıyor");
                $('.select2').select2({
                    theme: 'bootstrap-5'
                });
            } else {
                console.log("Footer: Select2 yüklü değil");
            }
        });
    </script>
</body>
</html> 