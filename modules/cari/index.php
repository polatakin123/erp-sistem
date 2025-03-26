<?php
/**
 * ERP Sistem - Cari Modülü Ana Sayfası
 * 
 * Bu dosya cari modülünün ana sayfasını içerir.
 * Müşteriler ve tedarikçilerin listesi, arama, filtreleme özellikleri ve özet bilgiler yer alır.
 */

// Hata raporlama ayarları
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
$debug_mode = false; // Debug modu kapalı

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
$pageTitle = "Cari Yönetimi";

// Filtreleme parametreleri
$aranan = isset($_GET['aranan']) ? clean($_GET['aranan']) : '';
$cari_tipi = isset($_GET['cari_tipi']) ? clean($_GET['cari_tipi']) : '';
$durum = isset($_GET['durum']) ? clean($_GET['durum']) : '';
$il = isset($_GET['il']) ? clean($_GET['il']) : '';
$bakiye_durum = isset($_GET['bakiye_durum']) ? clean($_GET['bakiye_durum']) : '';

// Sayfalama parametreleri
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = 20;
$offset = ($sayfa - 1) * $limit;

// Carileri al
try {
    // Toplam kayıt sayısını al
    $sql_count = "SELECT COUNT(*) FROM cari WHERE 1=1";
    $params = [];
    
    // Arama parametreleri için hazırlık
    $search_param = !empty($aranan) ? '%' . $aranan . '%' : '';
    $cari_tipi_param = !empty($cari_tipi) ? $cari_tipi : '';
    $durum_param = !empty($durum) ? $durum : '';
    $il_param = !empty($il) ? $il : '';

    if (!empty($aranan)) {
        $sql_count .= " AND (
            KOD LIKE ? OR 
            ADI LIKE ? OR 
            YETKILI_ADI LIKE ? OR 
            YETKILI_SOYADI LIKE ? OR 
            TELEFON LIKE ? OR 
            EMAIL LIKE ?
        )";
        // Her bir LIKE için aynı değeri ekle
        $params = array_merge($params, array_fill(0, 6, $search_param));
    }

    if (!empty($cari_tipi)) {
        $sql_count .= " AND TIP = ?";
        $params[] = $cari_tipi_param;
    }

    if (!empty($durum)) {
        $sql_count .= " AND DURUM = ?";
        $params[] = $durum_param;
    }

    if (!empty($il)) {
        $sql_count .= " AND IL = ?";
        $params[] = $il_param;
    }

    if (!empty($bakiye_durum)) {
        if ($bakiye_durum == 'borclu') {
            $sql_count .= " AND BAKIYE > 0";
        } elseif ($bakiye_durum == 'alacakli') {
            $sql_count .= " AND BAKIYE < 0";
        } elseif ($bakiye_durum == 'notr') {
            $sql_count .= " AND BAKIYE = 0";
        }
    }

    $stmt_count = $db->prepare($sql_count);
    $stmt_count->execute($params);
    $toplam_kayit = $stmt_count->fetchColumn();
    $toplam_sayfa = ceil($toplam_kayit / $limit);

    // Carileri al
    $sql = "SELECT * FROM cari WHERE 1=1";
    $params = [];

    if (!empty($aranan)) {
        $sql .= " AND (
            KOD LIKE ? OR 
            ADI LIKE ? OR 
            YETKILI_ADI LIKE ? OR 
            YETKILI_SOYADI LIKE ? OR 
            TELEFON LIKE ? OR 
            EMAIL LIKE ?
        )";
        // Her bir LIKE için aynı değeri ekle
        $params = array_merge($params, array_fill(0, 6, $search_param));
    }

    if (!empty($cari_tipi)) {
        $sql .= " AND TIP = ?";
        $params[] = $cari_tipi_param;
    }

    if (!empty($durum)) {
        $sql .= " AND DURUM = ?";
        $params[] = $durum_param;
    }

    if (!empty($il)) {
        $sql .= " AND IL = ?";
        $params[] = $il_param;
    }

    if (!empty($bakiye_durum)) {
        if ($bakiye_durum == 'borclu') {
            $sql .= " AND BAKIYE > 0";
        } elseif ($bakiye_durum == 'alacakli') {
            $sql .= " AND BAKIYE < 0";
        } elseif ($bakiye_durum == 'notr') {
            $sql .= " AND BAKIYE = 0";
        }
    }

    $sql .= " ORDER BY ADI ASC LIMIT " . (int)$offset . ", " . (int)$limit;

    $stmt = $db->prepare($sql);
    $stmt->execute($params);
    $cariler = $stmt->fetchAll();

    // Özet bilgileri al
    $musteri_sayisi = 0;
    $tedarikci_sayisi = 0;
    $toplam_alacak = 0;
    $toplam_borc = 0;

    $stmt = $db->query("SELECT COUNT(*) FROM cari WHERE TIP IN ('musteri', 'her_ikisi')");
    $musteri_sayisi = $stmt->fetchColumn();

    $stmt = $db->query("SELECT COUNT(*) FROM cari WHERE TIP IN ('tedarikci', 'her_ikisi')");
    $tedarikci_sayisi = $stmt->fetchColumn();

    $stmt = $db->query("SELECT SUM(CASE WHEN BAKIYE < 0 THEN BAKIYE ELSE 0 END) AS toplam_alacak, 
                                SUM(CASE WHEN BAKIYE > 0 THEN BAKIYE ELSE 0 END) AS toplam_borc
                         FROM cari");
    $bakiye_bilgileri = $stmt->fetch();
    $toplam_alacak = abs($bakiye_bilgileri['toplam_alacak'] ?: 0);
    $toplam_borc = $bakiye_bilgileri['toplam_borc'] ?: 0;

    // İlleri al (filtreleme için)
    $stmt = $db->query("SELECT DISTINCT IL FROM cari WHERE IL IS NOT NULL AND IL != '' ORDER BY IL");
    $iller = $stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    // Hata durumunda
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Sayfa İçeriği -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cari Yönetimi</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="cari_ekle.php" class="btn btn-sm btn-outline-primary">Yeni Cari Ekle</a>
                <a href="cari_arama.php" class="btn btn-sm btn-outline-info">Detaylı Arama</a>
                <a href="export.php" class="btn btn-sm btn-outline-secondary">Dışa Aktar</a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Özet Kartları -->
    <div class="row mb-4">
        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Toplam Müşteri</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $musteri_sayisi; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-users fa-2x text-gray-300"></i>
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
                                Toplam Tedarikçi</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo $tedarikci_sayisi; ?></div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-building fa-2x text-gray-300"></i>
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

        <div class="col-xl-3 col-md-6 mb-4">
            <div class="card border-left-warning shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
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
    </div>

    <!-- Filtreleme Formu -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Arama ve Filtreleme</h6>
        </div>
        <div class="card-body">
            <form method="get" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-4 mb-3">
                        <label for="aranan" class="form-label">Arama</label>
                        <input type="text" class="form-control" id="aranan" name="aranan" placeholder="Cari kodu, unvan, yetkili, telefon, e-posta..." value="<?php echo htmlspecialchars($aranan); ?>">
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="cari_tipi" class="form-label">Cari Tipi</label>
                        <select class="form-select" id="cari_tipi" name="cari_tipi">
                            <option value="">Tümü</option>
                            <option value="musteri" <?php echo $cari_tipi == 'musteri' ? 'selected' : ''; ?>>Müşteri</option>
                            <option value="tedarikci" <?php echo $cari_tipi == 'tedarikci' ? 'selected' : ''; ?>>Tedarikçi</option>
                            <option value="her_ikisi" <?php echo $cari_tipi == 'her_ikisi' ? 'selected' : ''; ?>>Her İkisi</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="durum" class="form-label">Durum</label>
                        <select class="form-select" id="durum" name="durum">
                            <option value="">Tümü</option>
                            <option value="1" <?php echo $durum == '1' ? 'selected' : ''; ?>>Aktif</option>
                            <option value="0" <?php echo $durum == '0' ? 'selected' : ''; ?>>Pasif</option>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="il" class="form-label">İl</label>
                        <select class="form-select" id="il" name="il">
                            <option value="">Tümü</option>
                            <?php foreach ($iller as $il_adi): ?>
                                <option value="<?php echo htmlspecialchars($il_adi); ?>" <?php echo $il == $il_adi ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($il_adi); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="bakiye_durum" class="form-label">Bakiye Durumu</label>
                        <select class="form-select" id="bakiye_durum" name="bakiye_durum">
                            <option value="">Tümü</option>
                            <option value="borclu" <?php echo $bakiye_durum == 'borclu' ? 'selected' : ''; ?>>Borçlu</option>
                            <option value="alacakli" <?php echo $bakiye_durum == 'alacakli' ? 'selected' : ''; ?>>Alacaklı</option>
                            <option value="notr" <?php echo $bakiye_durum == 'notr' ? 'selected' : ''; ?>>Nötr</option>
                        </select>
                    </div>
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Filtrele</button>
                        <a href="index.php" class="btn btn-secondary">Temizle</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Cari Listesi -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cari Listesi</h6>
        </div>
        <div class="card-body">
            <div class="table-responsive">
                <table class="table table-bordered table-hover" id="dataTable" width="100%" cellspacing="0">
                    <thead>
                        <tr>
                            <th>Cari Kodu</th>
                            <th>Firma Ünvanı</th>
                            <th>Yetkili</th>
                            <th>Telefon</th>
                            <th>İl/İlçe</th>
                            <th>Cari Tipi</th>
                            <th>Bakiye</th>
                            <th>Durum</th>
                            <th>İşlemler</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (isset($cariler) && !empty($cariler)): ?>
                            <?php foreach ($cariler as $cari): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($cari['KOD']); ?></td>
                                    <td><?php echo htmlspecialchars($cari['ADI']); ?></td>
                                    <td>
                                        <?php 
                                            $yetkili = [];
                                            if (!empty($cari['YETKILI_ADI'])) $yetkili[] = $cari['YETKILI_ADI'];
                                            if (!empty($cari['YETKILI_SOYADI'])) $yetkili[] = $cari['YETKILI_SOYADI'];
                                            echo htmlspecialchars(implode(' ', $yetkili)); 
                                        ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($cari['TELEFON']); ?></td>
                                    <td>
                                        <?php 
                                            $konum = [];
                                            if (!empty($cari['IL'])) $konum[] = $cari['IL'];
                                            if (!empty($cari['ILCE'])) $konum[] = $cari['ILCE'];
                                            echo htmlspecialchars(implode('/', $konum)); 
                                        ?>
                                    </td>
                                    <td>
                                        <?php 
                                            switch ($cari['TIP']) {
                                                case 'musteri':
                                                    echo '<span class="badge bg-primary">Müşteri</span>';
                                                    break;
                                                case 'tedarikci':
                                                    echo '<span class="badge bg-success">Tedarikçi</span>';
                                                    break;
                                                case 'her_ikisi':
                                                    echo '<span class="badge bg-info">Müşteri/Tedarikçi</span>';
                                                    break;
                                            }
                                        ?>
                                    </td>
                                    <td class="text-end <?php echo $cari['BAKIYE'] > 0 ? 'text-danger' : ($cari['BAKIYE'] < 0 ? 'text-success' : ''); ?>">
                                        <?php echo number_format(abs($cari['BAKIYE'] ?? 0), 2, ',', '.'); ?> ₺
                                        <?php echo $cari['BAKIYE'] > 0 ? '(B)' : ($cari['BAKIYE'] < 0 ? '(A)' : ''); ?>
                                    </td>
                                    <td class="text-center">
                                        <?php if ($cari['DURUM'] == 1): ?>
                                            <span class="badge bg-success">Aktif</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Pasif</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-center">
                                        <a href="cari_detay.php?id=<?php echo $cari['ID']; ?>" class="btn btn-info btn-sm" title="Detay">
                                            <i class="fas fa-eye"></i>
                                        </a>
                                        <a href="cari_duzenle.php?id=<?php echo $cari['ID']; ?>" class="btn btn-primary btn-sm" title="Düzenle">
                                            <i class="fas fa-edit"></i>
                                        </a>
                                        <a href="javascript:void(0);" onclick="if(confirm('Bu cariyi silmek istediğinize emin misiniz?')) window.location.href='cari_sil.php?id=<?php echo $cari['ID']; ?>';" class="btn btn-danger btn-sm" title="Sil">
                                            <i class="fas fa-trash"></i>
                                        </a>
                                        <a href="cari_ekstre.php?id=<?php echo $cari['ID']; ?>" class="btn btn-warning btn-sm" title="Ekstre">
                                            <i class="fas fa-file-alt"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9" class="text-center">Kayıt bulunamadı</td>
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
                                <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?>&aranan=<?php echo urlencode($aranan); ?>&cari_tipi=<?php echo urlencode($cari_tipi); ?>&durum=<?php echo urlencode($durum); ?>&il=<?php echo urlencode($il); ?>&bakiye_durum=<?php echo urlencode($bakiye_durum); ?>">
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
                                <a class="page-link" href="?sayfa=<?php echo $i; ?>&aranan=<?php echo urlencode($aranan); ?>&cari_tipi=<?php echo urlencode($cari_tipi); ?>&durum=<?php echo urlencode($durum); ?>&il=<?php echo urlencode($il); ?>&bakiye_durum=<?php echo urlencode($bakiye_durum); ?>">
                                    <?php echo $i; ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($sayfa < $toplam_sayfa): ?>
                            <li class="page-item">
                                <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?>&aranan=<?php echo urlencode($aranan); ?>&cari_tipi=<?php echo urlencode($cari_tipi); ?>&durum=<?php echo urlencode($durum); ?>&il=<?php echo urlencode($il); ?>&bakiye_durum=<?php echo urlencode($bakiye_durum); ?>">
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