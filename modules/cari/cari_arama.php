<?php
/**
 * ERP Sistem - Cari Arama Sayfası
 * 
 * Bu dosya müşteri ve tedarikçileri aramak için kullanılır.
 */

// Detaylı hata raporlama ve veritabanı debug modunu etkinleştir
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);
$debug_mode = false; // Debug modunu kapat

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
$pageTitle = "Cari Arama";

// Arama parametrelerini al
$arama = isset($_GET['arama']) ? clean($_GET['arama']) : '';
$cari_tipi = isset($_GET['cari_tipi']) ? clean($_GET['cari_tipi']) : '';
$durum = isset($_GET['durum']) ? clean($_GET['durum']) : '';
$il = isset($_GET['il']) ? clean($_GET['il']) : '';

// Sayfalama parametreleri
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = 20;
$offset = ($sayfa - 1) * $limit;

// İlleri al (filtreleme için)
try {
    $stmt = $db->query("SELECT DISTINCT il FROM cariler WHERE il IS NOT NULL AND il != '' ORDER BY il");
    $iller = $stmt->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
    $iller = [];
}

// Arama yapıldı mı kontrol et
$arama_yapildi = !empty($arama) || !empty($cari_tipi) || !empty($durum) || !empty($il);

// Arama sonuçları için değişkenler
$cariler = [];
$toplam_kayit = 0;
$toplam_sayfa = 0;

// Arama yapıldıysa sonuçları getir
if ($arama_yapildi) {
    try {
        // Toplam kayıt sayısını al için basit sorgu
        $sql_count = "SELECT COUNT(*) FROM cariler WHERE 1=1";
        $params = [];
        
        // Arama parametreleri için hazırlık
        $search_param = !empty($arama) ? '%' . $arama . '%' : '';
        $cari_tipi_param = !empty($cari_tipi) ? $cari_tipi : '';
        $durum_param = !empty($durum) ? $durum : '';
        $il_param = !empty($il) ? $il : '';

        if (!empty($arama)) {
            $sql_count .= " AND (
                cari_kodu LIKE ? OR 
                firma_unvani LIKE ? OR 
                yetkili_ad LIKE ? OR 
                yetkili_soyad LIKE ? OR 
                telefon LIKE ? OR 
                email LIKE ? OR 
                vergi_no LIKE ?
            )";
            // Her bir LIKE için aynı değeri ekle
            $params = array_merge($params, array_fill(0, 7, $search_param));
        }

        if (!empty($cari_tipi)) {
            $sql_count .= " AND cari_tipi = ?";
            $params[] = $cari_tipi_param;
        }

        if (!empty($durum)) {
            $sql_count .= " AND durum = ?";
            $params[] = $durum_param;
        }

        if (!empty($il)) {
            $sql_count .= " AND il = ?";
            $params[] = $il_param;
        }
        
        // Debug bilgilerini yazdır (COUNT sorgusu için)
        if ($debug_mode) {
            echo "<div class='alert alert-info'>";
            echo "<strong>DEBUG - COUNT Sorgusu:</strong><br>";
            echo "<pre>" . htmlspecialchars($sql_count) . "</pre>";
            echo "<strong>DEBUG - COUNT Parametreleri:</strong><br>";
            echo "<pre>";
            var_dump($params);
            echo "</pre>";
            echo "</div>";
        }

        $stmt_count = $db->prepare($sql_count);
        $stmt_count->execute($params);
        $toplam_kayit = $stmt_count->fetchColumn();
        $toplam_sayfa = ceil($toplam_kayit / $limit);

        // Cari kayıtları al
        $sql = "SELECT * FROM cariler WHERE 1=1";
        $params = [];
        
        if (!empty($arama)) {
            $sql .= " AND (
                cari_kodu LIKE ? OR 
                firma_unvani LIKE ? OR 
                yetkili_ad LIKE ? OR 
                yetkili_soyad LIKE ? OR 
                telefon LIKE ? OR 
                email LIKE ? OR 
                vergi_no LIKE ?
            )";
            // Her bir LIKE için aynı değeri ekle
            $params = array_merge($params, array_fill(0, 7, $search_param));
        }

        if (!empty($cari_tipi)) {
            $sql .= " AND cari_tipi = ?";
            $params[] = $cari_tipi_param;
        }

        if (!empty($durum)) {
            $sql .= " AND durum = ?";
            $params[] = $durum_param;
        }

        if (!empty($il)) {
            $sql .= " AND il = ?";
            $params[] = $il_param;
        }

        $sql .= " ORDER BY firma_unvani ASC LIMIT " . (int)$offset . ", " . (int)$limit;
        
        // Debug bilgilerini yazdır
        if ($debug_mode) {
            echo "<div class='alert alert-info'>";
            echo "<strong>DEBUG - SQL Sorgusu:</strong><br>";
            echo "<pre>" . htmlspecialchars($sql) . "</pre>";
            echo "<strong>DEBUG - Parametreler:</strong><br>";
            echo "<pre>";
            var_dump($params);
            echo "offset: " . $offset . "<br>";
            echo "limit: " . $limit . "<br>";
            echo "</pre>";
            echo "</div>";
        }
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $cariler = $stmt->fetchAll();
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
        
        // Hata durumunda detaylı bilgileri göster
        if ($debug_mode) {
            echo "<div class='alert alert-danger'>";
            echo "<strong>HATA DETAYLARI:</strong><br>";
            echo "<pre>";
            echo "Hata Kodu: " . $e->getCode() . "<br>";
            echo "Hata Mesajı: " . $e->getMessage() . "<br>";
            echo "Dosya: " . $e->getFile() . "<br>";
            echo "Satır: " . $e->getLine() . "<br>";
            echo "SQL Sorgusu: " . (isset($sql) ? htmlspecialchars($sql) : 'Tanımlanmamış') . "<br>";
            echo "Bağlanmış parametreler:<br>";
            
            // Parametreleri göster
            if (isset($params) && is_array($params)) {
                var_dump($params);
            } else {
                echo "Parametre bilgisi yok.";
            }
            
            echo "</pre>";
            echo "</div>";
        }
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Sayfa İçeriği -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cari Arama</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Cari Listesi</a>
                <a href="cari_ekle.php" class="btn btn-sm btn-outline-primary">Yeni Cari Ekle</a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <!-- Arama Formu -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Detaylı Arama</h6>
        </div>
        <div class="card-body">
            <form method="get" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label for="arama" class="form-label">Arama</label>
                        <input type="text" class="form-control" id="arama" name="arama" placeholder="Cari kodu, unvan, yetkili, telefon, e-posta..." value="<?php echo htmlspecialchars($arama); ?>">
                        <div class="form-text">Cari kodu, firma ünvanı, yetkili adı-soyadı, telefon, e-posta veya vergi numarası ile arama yapabilirsiniz.</div>
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
                </div>
                <div class="row">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Ara</button>
                        <a href="cari_arama.php" class="btn btn-secondary">Temizle</a>
                    </div>
                </div>
            </form>
        </div>
    </div>

    <!-- Arama Sonuçları -->
    <?php if ($arama_yapildi): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Arama Sonuçları</h6>
                <span class="badge bg-info"><?php echo $toplam_kayit; ?> Kayıt Bulundu</span>
            </div>
            <div class="card-body">
                <?php if ($toplam_kayit > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover" width="100%" cellspacing="0">
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
                                <?php foreach ($cariler as $cari): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($cari['cari_kodu']); ?></td>
                                        <td><?php echo htmlspecialchars($cari['firma_unvani']); ?></td>
                                        <td>
                                            <?php 
                                                $yetkili = [];
                                                if (!empty($cari['yetkili_ad'])) $yetkili[] = $cari['yetkili_ad'];
                                                if (!empty($cari['yetkili_soyad'])) $yetkili[] = $cari['yetkili_soyad'];
                                                echo htmlspecialchars(implode(' ', $yetkili)); 
                                            ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($cari['telefon']); ?></td>
                                        <td>
                                            <?php 
                                                $konum = [];
                                                if (!empty($cari['il'])) $konum[] = $cari['il'];
                                                if (!empty($cari['ilce'])) $konum[] = $cari['ilce'];
                                                echo htmlspecialchars(implode('/', $konum)); 
                                            ?>
                                        </td>
                                        <td>
                                            <?php 
                                                switch ($cari['cari_tipi']) {
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
                                        <td class="text-end <?php echo $cari['bakiye'] > 0 ? 'text-danger' : ($cari['bakiye'] < 0 ? 'text-success' : ''); ?>">
                                            <?php echo number_format(abs($cari['bakiye']), 2, ',', '.'); ?> ₺
                                            <?php echo $cari['bakiye'] > 0 ? '(B)' : ($cari['bakiye'] < 0 ? '(A)' : ''); ?>
                                        </td>
                                        <td class="text-center">
                                            <?php if ($cari['durum'] == 1): ?>
                                                <span class="badge bg-success">Aktif</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Pasif</span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <a href="cari_detay.php?id=<?php echo $cari['id']; ?>" class="btn btn-info btn-sm" title="Detay">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <a href="cari_duzenle.php?id=<?php echo $cari['id']; ?>" class="btn btn-primary btn-sm" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="javascript:void(0);" onclick="if(confirm('Bu cariyi silmek istediğinize emin misiniz?')) window.location.href='cari_sil.php?id=<?php echo $cari['id']; ?>';" class="btn btn-danger btn-sm" title="Sil">
                                                <i class="fas fa-trash"></i>
                                            </a>
                                            <a href="cari_ekstre.php?id=<?php echo $cari['id']; ?>" class="btn btn-warning btn-sm" title="Ekstre">
                                                <i class="fas fa-file-alt"></i>
                                            </a>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>

                    <!-- Sayfalama -->
                    <?php if ($toplam_sayfa > 1): ?>
                        <nav aria-label="Sayfalama">
                            <ul class="pagination justify-content-center">
                                <?php if ($sayfa > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?>&arama=<?php echo urlencode($arama); ?>&cari_tipi=<?php echo urlencode($cari_tipi); ?>&durum=<?php echo urlencode($durum); ?>&il=<?php echo urlencode($il); ?>">
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
                                        <a class="page-link" href="?sayfa=<?php echo $i; ?>&arama=<?php echo urlencode($arama); ?>&cari_tipi=<?php echo urlencode($cari_tipi); ?>&durum=<?php echo urlencode($durum); ?>&il=<?php echo urlencode($il); ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                <?php endfor; ?>
                                
                                <?php if ($sayfa < $toplam_sayfa): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?>&arama=<?php echo urlencode($arama); ?>&cari_tipi=<?php echo urlencode($cari_tipi); ?>&durum=<?php echo urlencode($durum); ?>&il=<?php echo urlencode($il); ?>">
                                            Sonraki
                                        </a>
                                    </li>
                                <?php endif; ?>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Arama kriterlerinize uygun sonuç bulunamadı.
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php include_once '../../includes/footer.php'; ?> 