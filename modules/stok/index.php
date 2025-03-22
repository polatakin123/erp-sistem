<?php
/**
 * ERP Sistem - Stok Modülü Ana Sayfası
 * 
 * Bu dosya stok modülünün ana sayfasını içerir.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Stok Yönetimi";

// Stok sayısını al
$stokSayisi = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM products");
    $stokSayisi = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Kritik stok sayısını al
$kritikStokSayisi = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM products WHERE current_stock <= min_stock AND status = 'active'");
    $kritikStokSayisi = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Toplam stok değerini al
$toplamStokDegeri = 0;
try {
    $stmt = $db->query("SELECT SUM(current_stock * purchase_price) AS toplam_deger FROM products WHERE status = 'active'");
    $result = $stmt->fetch();
    $toplamStokDegeri = $result['toplam_deger'] ?? 0;
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Son eklenen ürünleri al
$sonEklenenUrunler = [];
try {
    $stmt = $db->query("SELECT * FROM products ORDER BY created_at DESC LIMIT 5");
    $sonEklenenUrunler = $stmt->fetchAll();
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Kritik stok seviyesindeki ürünleri al
$kritikStokUrunler = [];
try {
    $stmt = $db->query("SELECT * FROM products WHERE current_stock <= min_stock AND status = 'active' ORDER BY current_stock ASC LIMIT 5");
    $kritikStokUrunler = $stmt->fetchAll();
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Stok Yönetimi</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="urun_ekle.php" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus"></i> Yeni Ürün Ekle
            </a>
            <a href="urun_arama.php" class="btn btn-sm btn-outline-info">
                <i class="fas fa-search"></i> Detaylı Arama
            </a>
            <a href="stok_hareketleri.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-exchange-alt"></i> Stok Hareketleri
            </a>
        </div>
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
            <i class="fas fa-file-export"></i> Dışa Aktar
        </button>
        <ul class="dropdown-menu">
            <li><a class="dropdown-item" href="#">Excel</a></li>
            <li><a class="dropdown-item" href="#">PDF</a></li>
            <li><a class="dropdown-item" href="#">CSV</a></li>
        </ul>
    </div>
</div>

<!-- Özet Kartları -->
<div class="row">
    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Toplam Ürün Sayısı</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($stokSayisi); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-boxes fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Toplam Stok Değeri</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800">₺<?php echo number_format($toplamStokDegeri, 2, ',', '.'); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Kritik Stok Uyarıları</div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($kritikStokSayisi); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Arama Formu -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Ürün Ara</h6>
        <a href="urun_arama.php" class="btn btn-sm btn-outline-info">
            <i class="fas fa-search-plus"></i> Detaylı Arama
        </a>
    </div>
    <div class="card-body">
        <form action="urun_arama.php" method="get" class="mb-2">
            <input type="hidden" name="arama_modu" value="hizli">
            <div class="input-group">
                <input type="text" class="form-control form-control-lg" 
                       placeholder="Stok kodu, ürün adı, açıklama, marka, model veya kategori ile arama yapın..." 
                       name="arama" autofocus>
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-search"></i> Ara
                </button>
            </div>
            <div class="form-text text-muted mt-2">
                <i class="fas fa-info-circle"></i> Her türlü ürün bilgisi ile hızlı arama yapabilir veya <a href="urun_arama.php">detaylı arama</a> sayfasını kullanabilirsiniz.
            </div>
        </form>
    </div>
</div>

<!-- Ürün Listesi -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Ürün Listesi</h6>
        <div>
            <a href="urun_ekle.php" class="btn btn-sm btn-primary">
                <i class="fas fa-plus"></i> Yeni Ürün
            </a>
        </div>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered" id="productTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th style="width: 5%"></th>
                        <th>Stok Kodu</th>
                        <th>Ürün Adı</th>
                        <th>Kategori</th>
                        <th>Birim</th>
                        <th>Alış Fiyatı</th>
                        <th>Satış Fiyatı</th>
                        <th>Stok Miktarı</th>
                        <th>Durum</th>
                        <th>İşlemler</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    try {
                        $sql = "SELECT p.*, c.name as category_name 
                                FROM products p 
                                LEFT JOIN product_categories c ON p.category_id = c.id 
                                ORDER BY p.id DESC";
                        $stmt = $db->query($sql);
                        $stmt->execute();
                        
                        while ($urun = $stmt->fetch()): ?>
                        <tr>
                            <td class="text-center">
                                <input type="checkbox" class="select-item" value="<?php echo $urun['id']; ?>">
                            </td>
                            <td><?php echo htmlspecialchars($urun['code']); ?></td>
                            <td><a href="urun_detay.php?id=<?php echo $urun['id']; ?>"><?php echo displayHtml($urun['name']); ?></a></td>
                            <td><?php echo htmlspecialchars($urun['category_name'] ?? ''); ?></td>
                            <td><?php echo htmlspecialchars($urun['unit']); ?></td>
                            <td class="text-end"><?php echo number_format($urun['purchase_price'], 2, ',', '.'); ?> TL</td>
                            <td class="text-end"><?php echo number_format($urun['sale_price'], 2, ',', '.'); ?> TL</td>
                            <td class="text-center"><?php echo number_format($urun['current_stock'], 2, ',', '.'); ?></td>
                            <td class="text-center">
                                <span class="badge bg-<?php echo ($urun['status'] == 'active') ? 'success' : 'danger'; ?>">
                                    <?php echo ($urun['status'] == 'active') ? 'Aktif' : 'Pasif'; ?>
                                </span>
                            </td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="urun_detay.php?id=<?php echo $urun['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="urun_duzenle.php?id=<?php echo $urun['id']; ?>" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $urun['id']; ?>" data-name="<?php echo htmlspecialchars($urun['name']); ?>">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>
                        <?php endwhile;
                    } catch (PDOException $e) {
                        echo '<tr><td colspan="10" class="text-center text-danger">Veritabanı hatası: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Silme Onay Modalı -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Ürün Silme Onayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p>Seçilen ürünü silmek istediğinizden emin misiniz? Bu işlem geri alınamaz.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <a href="#" id="confirmDelete" class="btn btn-danger">Evet, Sil</a>
            </div>
        </div>
    </div>
</div>

<!-- Hata Ayıklama Panel -->
<div class="card shadow mb-4" id="debug-panel" style="display:none;">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-danger">Hata Ayıklama Paneli</h6>
        <button type="button" class="btn btn-sm btn-outline-secondary" onclick="document.getElementById('debug-panel').style.display='none';">Kapat</button>
    </div>
    <div class="card-body">
        <div id="debug-info"></div>
        <hr>
        <p><a href="debug.php" target="_blank" class="btn btn-warning">Detaylı Hata Ayıklama</a></p>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hata yakalama
        window.onerror = function(message, source, lineno, colno, error) {
            let debugPanel = document.getElementById('debug-panel');
            let debugInfo = document.getElementById('debug-info');
            
            debugPanel.style.display = 'block';
            debugInfo.innerHTML += '<div class="alert alert-danger"><strong>JavaScript Hatası:</strong> ' + message + '<br>Kaynak: ' + source + '<br>Satır: ' + lineno + '</div>';
            
            return false;
        };

        // Silme işlemi için modal
        const deleteButtons = document.querySelectorAll('.delete-btn');
        const confirmDeleteButton = document.getElementById('confirmDelete');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const productId = this.getAttribute('data-id');
                const productName = this.getAttribute('data-name');
                confirmDeleteButton.href = 'urun_sil.php?id=' + productId + '&name=' + encodeURIComponent(productName);
                
                const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
                deleteModal.show();
            });
        });

        // DataTables başlatmayı manuel olarak deneyelim
        console.log("DOM yüklendi, DataTables'ı başlatma deneniyor...");
        try {
            if (typeof $.fn.DataTable !== 'undefined') {
                console.log("DataTables kütüphanesi bulundu");
                
                if ($.fn.DataTable.isDataTable('#productTable')) {
                    console.log("productTable zaten DataTable olarak başlatılmış, önce yok ediliyor");
                    $('#productTable').DataTable().destroy();
                }
                
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
                    }
                };
                
                console.log("productTable için DataTable başlatılıyor...");
                $('#productTable').DataTable({
                    "language": dataTablesTurkishLanguage,
                    "responsive": true,
                    "autoWidth": false,
                    "pageLength": 10,
                    "lengthMenu": [[10, 25, 50, 100, -1], [10, 25, 50, 100, "Tümü"]]
                });
                console.log("productTable başarıyla DataTable olarak başlatıldı");

                // Başarı mesajı
                let debugPanel = document.getElementById('debug-panel');
                let debugInfo = document.getElementById('debug-info');
                debugPanel.style.display = 'block';
                debugInfo.innerHTML += '<div class="alert alert-success"><strong>Başarılı:</strong> DataTable başarıyla başlatıldı</div>';
            } else {
                console.error("DataTables kütüphanesi bulunamadı!");
                let debugPanel = document.getElementById('debug-panel');
                let debugInfo = document.getElementById('debug-info');
                debugPanel.style.display = 'block';
                debugInfo.innerHTML += '<div class="alert alert-danger"><strong>Hata:</strong> DataTables kütüphanesi bulunamadı. jQuery ve DataTables eklendiğinden emin olun.</div>';
            }
        } catch (error) {
            console.error("DataTable başlatılırken hata oluştu:", error);
            let debugPanel = document.getElementById('debug-panel');
            let debugInfo = document.getElementById('debug-info');
            debugPanel.style.display = 'block';
            debugInfo.innerHTML += '<div class="alert alert-danger"><strong>DataTable Hatası:</strong> ' + error.message + '</div>';
        }
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 