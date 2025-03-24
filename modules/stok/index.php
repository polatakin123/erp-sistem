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
    $stmt = $db->query("SELECT COUNT(*) FROM stok");
    $stokSayisi = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Kritik stok sayısını al
$kritikStokSayisi = 0;
try {
    $stmt = $db->query("SELECT COUNT(*) FROM stok WHERE current_stock <= min_stock AND status = 'active'");
    $kritikStokSayisi = $stmt->fetchColumn();
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Toplam stok değerini al
$toplamStokDegeri = 0;
try {
    $stmt = $db->query("SELECT SUM(current_stock * purchase_price) AS toplam_deger FROM stok WHERE status = 'active'");
    $result = $stmt->fetch();
    $toplamStokDegeri = $result['toplam_deger'] ?? 0;
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Son eklenen ürünleri al
$sonEklenenUrunler = [];
try {
    $stmt = $db->query("SELECT * FROM stok ORDER BY created_at DESC LIMIT 5");
    $sonEklenenUrunler = $stmt->fetchAll();
} catch (PDOException $e) {
    // Hata durumunda
    echo errorMessage("Veritabanı hatası: " . $e->getMessage());
}

// Kritik stok seviyesindeki ürünleri al
$kritikStokUrunler = [];
try {
    $stmt = $db->query("SELECT * FROM stok WHERE current_stock <= min_stock AND status = 'active' ORDER BY current_stock ASC LIMIT 5");
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
            <a href="urun_arama.php" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-search"></i> Detaylı Arama
            </a>
            <a href="urun_ekle.php" class="btn btn-sm btn-outline-success">
                <i class="fas fa-plus"></i> Yeni Ürün
            </a>
            <a href="import_oem_from_stok.php" class="btn btn-sm btn-outline-info">
                <i class="fas fa-sync"></i> OEM Verilerini İçe Aktar
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
        <form action="" method="get" class="mb-4" id="searchForm">
            <div class="input-group">
                <input type="text" class="form-control" name="q" placeholder="Ürün kodu veya adı ile arama yapın..." 
                    value="<?php echo isset($_GET['q']) ? htmlspecialchars($_GET['q']) : ''; ?>">
                <button class="btn btn-primary" type="submit">
                    <i class="fas fa-search"></i> Ara
                </button>
                <?php if(isset($_GET['q'])): ?>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> Temizle
                </a>
                <?php endif; ?>
            </div>
        </form>
        
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
                <tbody id="productTableBody">
                    <?php 
                    try {
                        // Arama yapılmış mı kontrol et
                        $search = isset($_GET['q']) && !empty($_GET['q']) ? $_GET['q'] : null;
                        
                        // Arama yoksa bilgi mesajı göster
                        if ($search === null) {
                            echo '<tr><td colspan="10" class="text-center">Ürün görmek için lütfen arama yapın</td></tr>';
                        } else {
                            // Sayfa parametresini al
                            $page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
                            $limit = 100; // Her sayfada 100 ürün
                            $offset = ($page - 1) * $limit;
                            
                            // SQL sorgusu oluştur - ürün kodu veya adı ile filtreleme
                            $sql = "SELECT * FROM stok WHERE 
                                    (KOD LIKE :search OR ADI LIKE :search)
                                    ORDER BY ID DESC LIMIT :limit OFFSET :offset";
                            
                            $stmt = $db->prepare($sql);
                            $stmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
                            $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
                            $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
                            $stmt->execute();
                            
                            // Toplam kayıt sayısını al (sayfalama için)
                            $countSql = "SELECT COUNT(*) FROM stok WHERE 
                                        (KOD LIKE :search OR ADI LIKE :search)";
                            $countStmt = $db->prepare($countSql);
                            $countStmt->bindValue(':search', '%' . $search . '%', PDO::PARAM_STR);
                            $countStmt->execute();
                            $totalRecords = $countStmt->fetchColumn();
                            
                            // Bulunan kayıt yoksa
                            if ($totalRecords == 0) {
                                echo '<tr><td colspan="10" class="text-center">Aramanıza uygun ürün bulunamadı</td></tr>';
                            } else {
                                // STK_FIS_HAR tablosundan stok miktarlarını al
                                try {
                                    // Her ürün için güncel stok miktarını hesapla
                                    $stokMiktarlari = [];
                                    
                                    // Ürün ID'lerini al
                                    $urunIds = [];
                                    $stmt_urunler = $stmt->fetchAll();
                                    foreach ($stmt_urunler as $urun) {
                                        $urunIds[] = $urun['ID'];
                                    }
                                    
                                    if (!empty($urunIds)) {
                                        // Stok hareketlerinden miktarları hesapla
                                        $stokSql = "SELECT 
                                            KARTID,
                                            SUM(CASE WHEN ISLEMTIPI = 0 THEN MIKTAR ELSE 0 END) AS GIRIS_MIKTAR,
                                            SUM(CASE WHEN ISLEMTIPI = 1 THEN MIKTAR ELSE 0 END) AS CIKIS_MIKTAR
                                        FROM 
                                            STK_FIS_HAR
                                        WHERE 
                                            IPTAL = 0 AND KARTID IN (" . implode(',', $urunIds) . ")
                                        GROUP BY 
                                            KARTID";
                                        
                                        try {
                                            $stokStmt = $db->query($stokSql);
                                            while ($stokRow = $stokStmt->fetch()) {
                                                $stokMiktarlari[$stokRow['KARTID']] = $stokRow['GIRIS_MIKTAR'] - $stokRow['CIKIS_MIKTAR'];
                                            }
                                        } catch (PDOException $stokHata) {
                                            echo '<tr><td colspan="10" class="text-center text-warning">Stok miktarları hesaplanırken hata oluştu: ' . $stokHata->getMessage() . '</td></tr>';
                                        }
                                    }
                                    
                                    // Stok tablosundan temel bilgileri göster, ama gerçek stok miktarları için STK_FIS_HAR tablosunu kullan
                                    foreach ($stmt_urunler as $urun) {
                                        $guncel_stok = isset($stokMiktarlari[$urun['ID']]) ? $stokMiktarlari[$urun['ID']] : 0;
                                        ?>
                                        <tr>
                                            <td class="text-center">
                                                <input type="checkbox" class="select-item" value="<?php echo $urun['ID']; ?>">
                                            </td>
                                            <td><?php echo htmlspecialchars($urun['KOD']); ?></td>
                                            <td><a href="urun_detay.php?id=<?php echo $urun['ID']; ?>"><?php echo htmlspecialchars($urun['ADI']); ?></a></td>
                                            <td><?php echo isset($urun['TIP']) ? htmlspecialchars($urun['TIP']) : ''; ?></td>
                                            <td><?php echo isset($urun['BIRIM']) ? htmlspecialchars($urun['BIRIM']) : '-'; ?></td>
                                            <td class="text-end"><?php echo isset($urun['ALIS_FIYAT']) ? number_format($urun['ALIS_FIYAT'], 2, ',', '.') : '0,00'; ?> TL</td>
                                            <td class="text-end"><?php echo isset($urun['SATIS_FIYAT']) ? number_format($urun['SATIS_FIYAT'], 2, ',', '.') : '0,00'; ?> TL</td>
                                            <td class="text-center">
                                                <?php 
                                                // İki stok bilgisini karşılaştır ve uyarı göster
                                                $sistem_stok = isset($urun['MIKTAR']) ? (float)$urun['MIKTAR'] : 0;
                                                if ($sistem_stok != $guncel_stok) {
                                                    echo '<span class="text-success">' . number_format($guncel_stok, 2, ',', '.') . '</span>';
                                                    echo '<br><small class="text-muted">Sistem: ' . number_format($sistem_stok, 2, ',', '.') . '</small>';
                                                } else {
                                                    echo number_format($guncel_stok, 2, ',', '.');
                                                }
                                                ?>
                                            </td>
                                            <td class="text-center">
                                                <span class="badge bg-<?php echo ($urun['DURUM'] == 1) ? 'success' : 'danger'; ?>">
                                                    <?php echo ($urun['DURUM'] == 1) ? 'Aktif' : 'Pasif'; ?>
                                                </span>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="urun_detay.php?id=<?php echo $urun['ID']; ?>" class="btn btn-sm btn-info">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <a href="urun_duzenle.php?id=<?php echo $urun['ID']; ?>" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $urun['ID']; ?>" data-name="<?php echo htmlspecialchars($urun['ADI']); ?>">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                        <?php
                                    }
                                    
                                } catch (PDOException $stokHata) {
                                    echo '<tr><td colspan="10" class="text-center text-danger">Stok bilgileri alınırken hata oluştu: ' . $stokHata->getMessage() . '</td></tr>';
                                }
                                
                                // Sayfalama bilgileri
                                $totalPages = ceil($totalRecords / $limit);
                                $nextPage = $page + 1;
                                
                                // Daha fazla sayfa varsa "Daha Fazla Yükle" butonu göster
                                if ($page < $totalPages) {
                                    echo '<tr id="loadMoreRow"><td colspan="10" class="text-center">
                                        <button type="button" id="loadMoreBtn" class="btn btn-outline-primary btn-sm mt-2" 
                                                data-page="' . $nextPage . '" data-search="' . htmlspecialchars($search) . '">
                                            <i class="fas fa-sync"></i> Daha Fazla Yükle (' . $offset . '/' . $totalRecords . ')
                                        </button>
                                    </td></tr>';
                                }
                            }
                        }
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

        // Daha Fazla Yükle butonuna tıklama
        $(document).on('click', '#loadMoreBtn', function() {
            const nextPage = $(this).data('page');
            const search = $(this).data('search');
            const loadingBtn = $(this);
            
            // Butonu yükleniyor olarak değiştir
            loadingBtn.html('<i class="fas fa-spinner fa-spin"></i> Yükleniyor...');
            loadingBtn.prop('disabled', true);
            
            // AJAX ile sonraki sayfayı yükle
            $.ajax({
                url: 'load_more_products.php',
                type: 'GET',
                data: {
                    page: nextPage,
                    q: search
                },
                success: function(response) {
                    // Yükleme satırını kaldır
                    $('#loadMoreRow').remove();
                    
                    // Yeni ürünleri ekle
                    $('#productTableBody').append(response);
                },
                error: function(xhr, status, error) {
                    alert('Ürünler yüklenirken bir hata oluştu: ' + error);
                    loadingBtn.html('<i class="fas fa-sync"></i> Tekrar Dene');
                    loadingBtn.prop('disabled', false);
                }
            });
        });
        
        // Arama formunun gönderilmesini yönet
        $('#searchForm').on('submit', function(e) {
            const searchTerm = $('input[name="q"]').val();
            if (!searchTerm || searchTerm.trim() === '') {
                e.preventDefault();
                alert('Lütfen arama yapmak için bir değer girin');
                return false;
            }
        });
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 