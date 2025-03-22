<?php
/**
 * ERP Sistem - Fatura Modülü Ana Sayfası
 * 
 * Bu dosya fatura modülünün ana sayfasını içerir.
 */

// Oturum başlat
session_start();

// Gerekli dosyaları dahil et
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Oturum kontrolü
checkLogin();

// Yetki kontrolü
if (!checkPermission('fatura_goruntule')) {
    // Yetki yoksa ana sayfaya yönlendir
    redirect('../../index.php');
}

// Sayfa başlığı
$pageTitle = "Fatura Yönetimi | ERP Sistem";

// Filtreleme parametreleri
$fatura_tipi = isset($_GET['fatura_tipi']) ? $_GET['fatura_tipi'] : '';
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-d', strtotime('-30 days'));
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$musteri_tedarikci = isset($_GET['musteri_tedarikci']) ? $_GET['musteri_tedarikci'] : '';
$fatura_no = isset($_GET['fatura_no']) ? $_GET['fatura_no'] : '';
$durum = isset($_GET['durum']) ? $_GET['durum'] : '';

// Faturaları al
try {
    $sql = "SELECT f.*, 
            CASE 
                WHEN f.fatura_tipi = 'satis' THEN m.ad
                WHEN f.fatura_tipi = 'alis' THEN t.ad
                ELSE ''
            END AS cari_ad
            FROM faturalar f
            LEFT JOIN musteriler m ON f.musteri_id = m.id AND f.fatura_tipi = 'satis'
            LEFT JOIN tedarikciler t ON f.tedarikci_id = t.id AND f.fatura_tipi = 'alis'
            WHERE 1=1";
    
    $params = [];
    
    // Fatura tipine göre filtrele
    if ($fatura_tipi) {
        $sql .= " AND f.fatura_tipi = :fatura_tipi";
        $params[':fatura_tipi'] = $fatura_tipi;
    }
    
    // Tarih aralığına göre filtrele
    if ($baslangic_tarihi) {
        $sql .= " AND DATE(f.fatura_tarihi) >= :baslangic_tarihi";
        $params[':baslangic_tarihi'] = $baslangic_tarihi;
    }
    
    if ($bitis_tarihi) {
        $sql .= " AND DATE(f.fatura_tarihi) <= :bitis_tarihi";
        $params[':bitis_tarihi'] = $bitis_tarihi;
    }
    
    // Müşteri/Tedarikçi adına göre filtrele
    if ($musteri_tedarikci) {
        $sql .= " AND (m.ad LIKE :musteri_tedarikci OR t.ad LIKE :musteri_tedarikci)";
        $params[':musteri_tedarikci'] = '%' . $musteri_tedarikci . '%';
    }
    
    // Fatura numarasına göre filtrele
    if ($fatura_no) {
        $sql .= " AND f.fatura_no LIKE :fatura_no";
        $params[':fatura_no'] = '%' . $fatura_no . '%';
    }
    
    // Duruma göre filtrele
    if ($durum !== '') {
        $sql .= " AND f.durum = :durum";
        $params[':durum'] = $durum;
    }
    
    // Sıralama
    $sql .= " ORDER BY f.fatura_tarihi DESC, f.id DESC";
    
    $stmt = $pdo->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $faturalar = $stmt->fetchAll();
    
    // Özet bilgileri al
    $satis_fatura_sayisi = 0;
    $alis_fatura_sayisi = 0;
    $toplam_satis_tutari = 0;
    $toplam_alis_tutari = 0;
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM faturalar WHERE fatura_tipi = 'satis'");
    $satis_fatura_sayisi = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT COUNT(*) FROM faturalar WHERE fatura_tipi = 'alis'");
    $alis_fatura_sayisi = $stmt->fetchColumn();
    
    $stmt = $pdo->query("SELECT SUM(toplam_tutar) FROM faturalar WHERE fatura_tipi = 'satis'");
    $toplam_satis_tutari = $stmt->fetchColumn() ?: 0;
    
    $stmt = $pdo->query("SELECT SUM(toplam_tutar) FROM faturalar WHERE fatura_tipi = 'alis'");
    $toplam_alis_tutari = $stmt->fetchColumn() ?: 0;
    
} catch (PDOException $e) {
    // Hata durumunda
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Yan menüyü dahil et -->
        <?php include_once '../../includes/sidebar.php'; ?>
        
        <!-- Ana içerik -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Fatura Yönetimi</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if (checkPermission('fatura_ekle')): ?>
                        <a href="satis_fatura_ekle.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Satış Faturası Ekle
                        </a>
                        <a href="alis_fatura_ekle.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-plus"></i> Alış Faturası Ekle
                        </a>
                        <?php endif; ?>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false">
                        <i class="fas fa-file-export"></i> Dışa Aktar
                    </button>
                    <ul class="dropdown-menu">
                        <li><a class="dropdown-item" href="#" id="exportExcel">Excel</a></li>
                        <li><a class="dropdown-item" href="#" id="exportPdf">PDF</a></li>
                        <li><a class="dropdown-item" href="#" id="printList">Yazdır</a></li>
                    </ul>
                </div>
            </div>
            
            <!-- Özet Kartları -->
            <div class="row">
                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Satış Fatura Sayısı</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($satis_fatura_sayisi); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-invoice-dollar fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Toplam Satış Tutarı</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₺<?php echo number_format($toplam_satis_tutari, 2, ',', '.'); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-info shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                                        Alış Fatura Sayısı</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($alis_fatura_sayisi); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-file-invoice fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-xl-3 col-md-6 mb-4">
                    <div class="card border-left-warning shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                                        Toplam Alış Tutarı</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">₺<?php echo number_format($toplam_alis_tutari, 2, ',', '.'); ?></div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-shopping-cart fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Filtreleme Formu -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3">
                        <div class="col-md-2">
                            <label for="fatura_tipi" class="form-label">Fatura Tipi</label>
                            <select class="form-select" id="fatura_tipi" name="fatura_tipi">
                                <option value="">Tümü</option>
                                <option value="satis" <?php echo $fatura_tipi == 'satis' ? 'selected' : ''; ?>>Satış</option>
                                <option value="alis" <?php echo $fatura_tipi == 'alis' ? 'selected' : ''; ?>>Alış</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>">
                        </div>
                        <div class="col-md-2">
                            <label for="musteri_tedarikci" class="form-label">Müşteri/Tedarikçi</label>
                            <input type="text" class="form-control" id="musteri_tedarikci" name="musteri_tedarikci" value="<?php echo $musteri_tedarikci; ?>" placeholder="Müşteri/Tedarikçi Adı">
                        </div>
                        <div class="col-md-2">
                            <label for="fatura_no" class="form-label">Fatura No</label>
                            <input type="text" class="form-control" id="fatura_no" name="fatura_no" value="<?php echo $fatura_no; ?>" placeholder="Fatura No">
                        </div>
                        <div class="col-md-2">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="">Tümü</option>
                                <option value="1" <?php echo $durum === '1' ? 'selected' : ''; ?>>Aktif</option>
                                <option value="0" <?php echo $durum === '0' ? 'selected' : ''; ?>>İptal</option>
                            </select>
                        </div>
                        <div class="col-12 mt-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrele
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-sync-alt"></i> Sıfırla
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Fatura Listesi -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-primary">Fatura Listesi</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" id="faturaTable" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Fatura No</th>
                                    <th>Fatura Tipi</th>
                                    <th>Müşteri/Tedarikçi</th>
                                    <th>Fatura Tarihi</th>
                                    <th>Vade Tarihi</th>
                                    <th>Toplam Tutar</th>
                                    <th>Ödeme Durumu</th>
                                    <th>E-Fatura</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                if (isset($faturalar) && count($faturalar) > 0):
                                    foreach ($faturalar as $fatura): 
                                ?>
                                <tr>
                                    <td><?php echo $fatura['fatura_no']; ?></td>
                                    <td>
                                        <?php if ($fatura['fatura_tipi'] == 'satis'): ?>
                                        <span class="badge bg-primary">Satış</span>
                                        <?php else: ?>
                                        <span class="badge bg-info">Alış</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo $fatura['cari_ad']; ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($fatura['fatura_tarihi'])); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($fatura['vade_tarihi'])); ?></td>
                                    <td><?php echo number_format($fatura['toplam_tutar'], 2, ',', '.') . ' ₺'; ?></td>
                                    <td>
                                        <?php 
                                        switch ($fatura['odeme_durumu']) {
                                            case 'odenmedi':
                                                echo '<span class="badge bg-danger">Ödenmedi</span>';
                                                break;
                                            case 'kismen_odendi':
                                                echo '<span class="badge bg-warning">Kısmen Ödendi</span>';
                                                break;
                                            case 'odendi':
                                                echo '<span class="badge bg-success">Ödendi</span>';
                                                break;
                                            default:
                                                echo '<span class="badge bg-secondary">Belirsiz</span>';
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($fatura['e_fatura'] == 1): ?>
                                        <span class="badge bg-success">Evet</span>
                                        <?php else: ?>
                                        <span class="badge bg-secondary">Hayır</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($fatura['durum'] == 1): ?>
                                        <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                        <span class="badge bg-danger">İptal</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group">
                                            <a href="<?php echo $fatura['fatura_tipi']; ?>_fatura_goruntule.php?id=<?php echo $fatura['id']; ?>" class="btn btn-sm btn-primary" title="Görüntüle">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if (checkPermission('fatura_duzenle') && $fatura['durum'] == 1): ?>
                                            <a href="<?php echo $fatura['fatura_tipi']; ?>_fatura_duzenle.php?id=<?php echo $fatura['id']; ?>" class="btn btn-sm btn-warning" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if ($fatura['fatura_tipi'] == 'satis' && $fatura['e_fatura'] == 0 && $fatura['durum'] == 1): ?>
                                            <a href="e_fatura_gonder.php?id=<?php echo $fatura['id']; ?>" class="btn btn-sm btn-info" title="E-Fatura Gönder">
                                                <i class="fas fa-paper-plane"></i>
                                            </a>
                                            <?php endif; ?>
                                            <?php if (checkPermission('fatura_sil') && $fatura['durum'] == 1): ?>
                                            <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="<?php echo $fatura['id']; ?>" title="İptal Et">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php 
                                    endforeach;
                                else:
                                ?>
                                <tr>
                                    <td colspan="10" class="text-center">Kayıt bulunamadı.</td>
                                </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Silme Onay Modalı -->
<div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="deleteModalLabel">Fatura İptal Onayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                Bu faturayı iptal etmek istediğinizden emin misiniz? Bu işlem geri alınamaz ve stok hareketlerini de etkileyecektir.
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Vazgeç</button>
                <a href="#" class="btn btn-danger" id="confirmDelete">Evet, İptal Et</a>
            </div>
        </div>
    </div>
</div>

<script>
// DataTable ve diğer işlemler
document.addEventListener('DOMContentLoaded', function() {
    // DataTable'ı başlat
    var table = $('#faturaTable').DataTable({
        language: {
            url: '../../assets/js/Turkish.json'
        },
        order: [[3, 'desc']],
        pageLength: 25
    });
    
    // Excel export butonu
    document.getElementById('exportExcel').addEventListener('click', function() {
        window.location.href = 'export.php?type=excel&' + new URLSearchParams(window.location.search).toString();
    });
    
    // PDF export butonu
    document.getElementById('exportPdf').addEventListener('click', function() {
        window.location.href = 'export.php?type=pdf&' + new URLSearchParams(window.location.search).toString();
    });
    
    // Yazdır butonu
    document.getElementById('printList').addEventListener('click', function() {
        window.print();
    });
    
    // Silme işlemi için
    const deleteButtons = document.querySelectorAll('.delete-btn');
    const confirmDeleteButton = document.getElementById('confirmDelete');
    const deleteModal = new bootstrap.Modal(document.getElementById('deleteModal'));
    
    deleteButtons.forEach(button => {
        button.addEventListener('click', function() {
            const id = this.getAttribute('data-id');
            confirmDeleteButton.href = 'fatura_iptal.php?id=' + id;
            deleteModal.show();
        });
    });
});
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 