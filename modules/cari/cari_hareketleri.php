<?php
/**
 * ERP Sistem - Cari Hareketleri Sayfası
 * 
 * Bu dosya, cari hareketlerini listeler, filtreleme ve raporlama özelliklerine sahiptir.
 */

// Hata raporlama ayarları
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$debug_mode = true; // Debug modu açık

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../config/helpers.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Cari Hareketleri";

// Filtreleme parametreleri
$cari_id = isset($_GET['cari_id']) ? (int)$_GET['cari_id'] : 0;
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-d', strtotime('-30 days'));
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$islem_tipi = isset($_GET['islem_tipi']) ? clean($_GET['islem_tipi']) : '';
$min_tutar = isset($_GET['min_tutar']) ? (float)$_GET['min_tutar'] : '';
$max_tutar = isset($_GET['max_tutar']) ? (float)$_GET['max_tutar'] : '';
$islem_no = isset($_GET['islem_no']) ? clean($_GET['islem_no']) : '';

// Sayfalama parametreleri
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = 50;
$offset = ($sayfa - 1) * $limit;

try {
    // Cariler listesini al (dropdown için)
    $stmt = $db->query("SELECT ID, KOD, ADI FROM cari ORDER BY ADI ASC");
    $cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Seçilen carinin bilgilerini al
    $cari = null;
    if ($cari_id > 0) {
        $stmt = $db->prepare("SELECT ID, KOD, ADI, BAKIYE FROM cari WHERE ID = ?");
        $stmt->execute([$cari_id]);
        $cari = $stmt->fetch(PDO::FETCH_ASSOC);
    }

    // Hareket listesini almak için SQL sorgusunu hazırla
    $sql = "
        SELECT ch.*, c.KOD as cari_kodu, c.ADI as cari_adi
        FROM cari_hareket ch
        LEFT JOIN cari c ON ch.CARIID = c.ID
        WHERE 1=1
    ";
    $count_sql = "SELECT COUNT(*) FROM cari_hareket ch WHERE 1=1";
    $params = [];
    $where_conditions = [];

    // Filtreleme koşullarını ekle
    if ($cari_id > 0) {
        $where_conditions[] = "ch.CARIID = ?";
        $params[] = $cari_id;
    }

    if (!empty($baslangic_tarihi)) {
        $where_conditions[] = "DATE(ch.ISLEMTARIHI) >= ?";
        $params[] = $baslangic_tarihi;
    }

    if (!empty($bitis_tarihi)) {
        $where_conditions[] = "DATE(ch.ISLEMTARIHI) <= ?";
        $params[] = $bitis_tarihi;
    }

    if (!empty($islem_tipi)) {
        $where_conditions[] = "ch.ISLEMTIPI = ?";
        $params[] = $islem_tipi;
    }

    if (!empty($min_tutar)) {
        $where_conditions[] = "ch.TUTAR >= ?";
        $params[] = $min_tutar;
    }

    if (!empty($max_tutar)) {
        $where_conditions[] = "ch.TUTAR <= ?";
        $params[] = $max_tutar;
    }

    if (!empty($islem_no)) {
        $where_conditions[] = "ch.EVRAKNO LIKE ?";
        $params[] = "%$islem_no%";
    }

    // WHERE koşullarını ekle
    if (!empty($where_conditions)) {
        $sql .= " AND " . implode(" AND ", $where_conditions);
        $count_sql .= " AND " . implode(" AND ", $where_conditions);
    }

    // Sıralama ve limit
    $sql .= " ORDER BY ch.ISLEMTARIHI DESC, ch.ID DESC LIMIT $offset, $limit";

    // Toplam kayıt sayısını al
    $stmt_count = $db->prepare($count_sql);
    $stmt_count->execute($params);
    $toplam_kayit = $stmt_count->fetchColumn();
    $toplam_sayfa = ceil($toplam_kayit / $limit);

    // Hareketleri al
    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $hareketler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Özet bilgileri hesapla
    $toplam_borc = 0;
    $toplam_alacak = 0;

    if ($cari_id > 0) {
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN ISLEMTIPI = 'borc' THEN TUTAR ELSE 0 END) as toplam_borc,
                SUM(CASE WHEN ISLEMTIPI = 'alacak' THEN TUTAR ELSE 0 END) as toplam_alacak
            FROM cari_hareket
            WHERE CARIID = ?
        ");
        $stmt->execute([$cari_id]);
        $ozet = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($ozet) {
            $toplam_borc = $ozet['toplam_borc'] ?: 0;
            $toplam_alacak = $ozet['toplam_alacak'] ?: 0;
        }
    }

} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    if ($debug_mode) {
        echo "<div class='alert alert-danger'>";
        echo "<strong>HATA DETAYLARI:</strong><br>";
        echo "<pre>";
        echo "Hata Kodu: " . $e->getCode() . "<br>";
        echo "Hata Mesajı: " . $e->getMessage() . "<br>";
        echo "Dosya: " . $e->getFile() . "<br>";
        echo "Satır: " . $e->getLine() . "<br>";
        echo "</pre>";
        echo "</div>";
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Sayfa İçeriği -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cari Hareketleri</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Cari Listesi</a>
                <?php if ($cari_id > 0): ?>
                <a href="cari_detay.php?id=<?php echo $cari_id; ?>" class="btn btn-sm btn-outline-info">Cari Detayları</a>
                <?php endif; ?>
                <a href="javascript:void(0);" onclick="window.print();" class="btn btn-sm btn-outline-primary">Yazdır</a>
                <a href="cari_hareket_ekle.php<?php echo $cari_id > 0 ? '?cari_id=' . $cari_id : ''; ?>" class="btn btn-sm btn-outline-success">Yeni Hareket</a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Seçili Cari Bilgileri -->
    <?php if ($cari_id > 0 && isset($cari)): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Seçili Cari</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo htmlspecialchars($cari['KOD'] . ' - ' . $cari['ADI']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-left-<?php echo isset($cari['BAKIYE']) && $cari['BAKIYE'] > 0 ? 'danger' : 'success'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo isset($cari['BAKIYE']) && $cari['BAKIYE'] > 0 ? 'danger' : 'success'; ?> text-uppercase mb-1">
                                Güncel Bakiye</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                    if (isset($cari['BAKIYE'])) {
                                        echo number_format(abs($cari['BAKIYE']), 2, ',', '.') . ' ₺ ';
                                        echo $cari['BAKIYE'] > 0 ? '(Borçlu)' : ($cari['BAKIYE'] < 0 ? '(Alacaklı)' : '(Nötr)');
                                    } else {
                                        echo '0,00 ₺ (Nötr)';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-<?php echo isset($cari['BAKIYE']) && $cari['BAKIYE'] > 0 ? 'arrow-up' : 'arrow-down'; ?> fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Filtreleme Formu -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
        </div>
        <div class="card-body">
            <form method="get" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-4 mb-3">
                        <label for="cari_id" class="form-label">Cari Seçimi</label>
                        <select class="form-select" id="cari_id" name="cari_id">
                            <option value="">Tüm Cariler</option>
                            <?php foreach ($cariler as $c): ?>
                                <option value="<?php echo $c['ID']; ?>" <?php echo $cari_id == $c['ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['KOD'] . ' - ' . $c['ADI']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                        <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                        <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="islem_tipi" class="form-label">İşlem Tipi</label>
                        <select class="form-select" id="islem_tipi" name="islem_tipi">
                            <option value="">Tümü</option>
                            <option value="borc" <?php echo $islem_tipi == 'borc' ? 'selected' : ''; ?>>Borç</option>
                            <option value="alacak" <?php echo $islem_tipi == 'alacak' ? 'selected' : ''; ?>>Alacak</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="islem_no" class="form-label">Evrak No</label>
                        <input type="text" class="form-control" id="islem_no" name="islem_no" value="<?php echo htmlspecialchars($islem_no); ?>">
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-2 mb-3">
                        <label for="min_tutar" class="form-label">Min. Tutar</label>
                        <input type="number" step="0.01" class="form-control" id="min_tutar" name="min_tutar" value="<?php echo $min_tutar; ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="max_tutar" class="form-label">Max. Tutar</label>
                        <input type="number" step="0.01" class="form-control" id="max_tutar" name="max_tutar" value="<?php echo $max_tutar; ?>">
                    </div>
                    <div class="col-md-8 d-flex align-items-end mb-3">
                        <button type="submit" class="btn btn-primary me-2">Filtrele</button>
                        <a href="cari_hareketleri.php" class="btn btn-secondary">Sıfırla</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Özet Bilgileri -->
    <?php if ($cari_id > 0): ?>
    <div class="row mb-4">
        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-danger shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                Toplam Borç</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($toplam_borc, 2, ',', '.'); ?> ₺</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-credit-card fa-2x text-gray-300"></i>
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
                                Toplam Alacak</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($toplam_alacak, 2, ',', '.'); ?> ₺</div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-coins fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="col-xl-4 col-md-6 mb-4">
            <div class="card border-left-<?php echo ($toplam_borc - $toplam_alacak) > 0 ? 'danger' : 'success'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo ($toplam_borc - $toplam_alacak) > 0 ? 'danger' : 'success'; ?> text-uppercase mb-1">
                                Toplam Bakiye</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                    $bakiye = $toplam_borc - $toplam_alacak;
                                    echo number_format(abs($bakiye), 2, ',', '.') . ' ₺ ';
                                    echo $bakiye > 0 ? '(Borçlu)' : ($bakiye < 0 ? '(Alacaklı)' : '(Nötr)');
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Hareket Listesi -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cari Hareketleri</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Tarih</th>
                            <?php if ($cari_id == 0): ?>
                            <th>Cari</th>
                            <?php endif; ?>
                            <th>Evrak No</th>
                            <th>İzah</th>
                            <th>İşlem Tipi</th>
                            <th class="text-end">Tutar</th>
                            <th class="text-end">Bakiye</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($hareketler) && !empty($hareketler)): ?>
                            <?php
                            $guncel_bakiye = 0;
                            $prev_cari_id = null;
                            foreach ($hareketler as $hareket): 
                                // Eğer tüm carileri listeliyorsak, her cari için bakiyeyi yeniden başlat
                                if ($cari_id == 0 && $prev_cari_id != $hareket['CARIID']) {
                                    $guncel_bakiye = 0;
                                    $prev_cari_id = $hareket['CARIID'];
                                }
                                
                                if ($hareket['ISLEMTIPI'] == 'borc') {
                                    $guncel_bakiye += $hareket['TUTAR'];
                                } else {
                                    $guncel_bakiye -= $hareket['TUTAR'];
                                }
                            ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($hareket['ISLEMTARIHI'])); ?></td>
                                    <?php if ($cari_id == 0): ?>
                                    <td>
                                        <a href="cari_detay.php?id=<?php echo $hareket['CARIID']; ?>">
                                            <?php echo htmlspecialchars($hareket['cari_kodu'] . ' - ' . $hareket['cari_adi']); ?>
                                        </a>
                                    </td>
                                    <?php endif; ?>
                                    <td><?php echo htmlspecialchars($hareket['EVRAKNO']); ?></td>
                                    <td><?php echo htmlspecialchars($hareket['IZAH']); ?></td>
                                    <td>
                                        <?php if ($hareket['ISLEMTIPI'] == 'borc'): ?>
                                            <span class="badge bg-danger">Borç</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Alacak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo number_format($hareket['TUTAR'], 2, ',', '.'); ?> ₺</td>
                                    <td class="text-end <?php echo $guncel_bakiye > 0 ? 'text-danger' : ($guncel_bakiye < 0 ? 'text-success' : ''); ?>">
                                        <?php echo number_format(abs($guncel_bakiye), 2, ',', '.'); ?> ₺
                                        <?php echo $guncel_bakiye > 0 ? '(B)' : ($guncel_bakiye < 0 ? '(A)' : ''); ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="cari_hareket_duzenle.php?id=<?php echo $hareket['ID']; ?>" class="btn btn-primary btn-sm" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);" onclick="if(confirm('Bu hareketi silmek istediğinize emin misiniz?')) window.location.href='cari_hareket_sil.php?id=<?php echo $hareket['ID']; ?>';" class="btn btn-danger btn-sm" title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="<?php echo $cari_id == 0 ? '8' : '7'; ?>" class="text-center">Kayıt bulunamadı</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <!-- Sayfalama -->
            <?php if (isset($toplam_sayfa) && $toplam_sayfa > 1): ?>
                <nav aria-label="Sayfalama">
                    <ul class="pagination justify-content-center">
                        <?php if ($sayfa > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?>&cari_id=<?php echo $cari_id; ?>&baslangic_tarihi=<?php echo $baslangic_tarihi; ?>&bitis_tarihi=<?php echo $bitis_tarihi; ?>&islem_tipi=<?php echo $islem_tipi; ?>&min_tutar=<?php echo $min_tutar; ?>&max_tutar=<?php echo $max_tutar; ?>&islem_no=<?php echo urlencode($islem_no); ?>">
                                    Önceki
                                </a>
                            </li>
                        <?php endif; ?>
                        
                        <?php
                        $start_page = max(1, $sayfa - 2);
                        $end_page = min($toplam_sayfa, $sayfa + 2);
                        
                        for ($i = $start_page; $i <= $end_page; $i++):
                        ?>
                            <li class="page-item <?php echo $i == $sayfa ? 'active' : ''; ?>">
                                <a class="page-link" href="?sayfa=<?php echo $i; ?>&cari_id=<?php echo $cari_id; ?>&baslangic_tarihi=<?php echo $baslangic_tarihi; ?>&bitis_tarihi=<?php echo $bitis_tarihi; ?>&islem_tipi=<?php echo $islem_tipi; ?>&min_tutar=<?php echo $min_tutar; ?>&max_tutar=<?php echo $max_tutar; ?>&islem_no=<?php echo urlencode($islem_no); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($sayfa < $toplam_sayfa): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?>&cari_id=<?php echo $cari_id; ?>&baslangic_tarihi=<?php echo $baslangic_tarihi; ?>&bitis_tarihi=<?php echo $bitis_tarihi; ?>&islem_tipi=<?php echo $islem_tipi; ?>&min_tutar=<?php echo $min_tutar; ?>&max_tutar=<?php echo $max_tutar; ?>&islem_no=<?php echo urlencode($islem_no); ?>">
                                    Sonraki
                                </a>
                            </li>
                        <?php endif; ?>
                    </ul>
                </nav>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?> 