<?php
/**
 * ERP Sistem - Ürün Arama Sayfası
 * 
 * Bu dosya ürünleri detaylı arama ve filtreleme işlemlerini gerçekleştirir.
 */

// Debug modu aktif
$debug = true; // Hata ayıklama modunu aktifleştir
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Oturum başlat
session_start();

// Debug fonksiyonu
function debugEcho($message, $variable = null) {
    global $debug;
    if ($debug) {
        echo '<div style="background-color:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; margin:10px 0; border-radius:5px;">';
        echo '<strong>DEBUG:</strong> ' . $message;
        if ($variable !== null) {
            echo '<pre>';
            print_r($variable);
            echo '</pre>';
        }
        echo '</div>';
    }
}

// Türkçe karakterleri İngilizce eşdeğerlerine dönüştüren fonksiyon
function turkceKarakterDonustur($str) {
    $turkceKarakterler = ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];
    $ingilizceKarakterler = ['c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U'];
    return str_replace($turkceKarakterler, $ingilizceKarakterler, $str);
}

// SQL sorgusu için Türkçe karakter dönüşümlü UPPER fonksiyonu
function sqlTurkceUpper($columnName) {
    return "UPPER(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($columnName,'Ç','C'),'Ğ','G'),'İ','I'),'Ö','O'),'Ş','S'),'Ü','U'))";
}

// SQL için arama terimi hazırlama
function searchTermPrepare($term) {
    // Önce HTML entity'leri decode et
    $term = html_entity_decode($term);
    // Sonra Türkçe karakterleri dönüştür
    $normalized = turkceKarakterDonustur($term);
    // Son olarak büyük harfe çevir
    return strtoupper($normalized);
}

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// PDO hata modunu değiştir ve emulated prepares aktif et
if ($debug) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Emulated prepares'ı aktif et - LIMIT ve benzeri ifadelerde parametre sorunu yaşamamak için
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    debugEcho("PDO yapılandırması: Emulated Prepares aktif edildi");
}

// Sayfa başlığı
$pageTitle = "Ürün Arama";

// Arama parametresini al
$arama = isset($_GET['arama']) ? clean($_GET['arama']) : '';

// Detaylı arama için parametreleri al
$stok_kodu = isset($_GET['stok_kodu']) ? clean($_GET['stok_kodu']) : '';
$urun_adi = isset($_GET['urun_adi']) ? clean($_GET['urun_adi']) : '';
$tip = isset($_GET['tip']) ? clean($_GET['tip']) : '';
$birim = isset($_GET['birim']) ? clean($_GET['birim']) : '';
$min_stok = isset($_GET['min_stok']) ? (float)$_GET['min_stok'] : null;
$max_stok = isset($_GET['max_stok']) ? (float)$_GET['max_stok'] : null;
$min_fiyat = isset($_GET['min_fiyat']) ? (float)$_GET['min_fiyat'] : null;
$max_fiyat = isset($_GET['max_fiyat']) ? (float)$_GET['max_fiyat'] : null;
$durum = isset($_GET['durum']) ? clean($_GET['durum']) : '';

// Arama modunu belirle (hızlı/detaylı)
$arama_modu = isset($_GET['arama_modu']) ? $_GET['arama_modu'] : 'hizli';

// Sayfalama parametreleri
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$limit = 100; // Her sayfada 100 ürün
$offset = ($page - 1) * $limit;

// Ürünleri filtrele
$urunler = [];
$filtreSorgusu = false;
$toplam_kayit = 0;

// Hızlı arama
if ($arama_modu == 'hizli' && !empty($arama)) {
    $filtreSorgusu = true;
    
    try {
        debugEcho("Hızlı arama işlemi başlatılıyor...");
        
        // Arama terimini kelimelere ayır
        $arama_kelimeleri = explode(' ', trim($arama));
        debugEcho("Arama terimleri:", $arama_kelimeleri);
        
        // Her kelime için sorgu koşulu ve parametre oluştur
        $where_parts = [];
        $params = [];
        
        foreach ($arama_kelimeleri as $kelime) {
            if (strlen($kelime) < 2) continue; // Çok kısa kelimeleri atla
            
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($kelime) . '%';
            
            // Büyük harfe dönüştürülerek arama yapalım
            $where_parts[] = "(" . sqlTurkceUpper("KOD") . " LIKE ? OR " . sqlTurkceUpper("ADI") . " LIKE ?)";
            
            $params[] = $search_param;
            $params[] = $search_param;
            
            debugEcho("Kelime: $kelime, Arama parametresi: $search_param");
        }
        
        // Hiç geçerli kelime yoksa
        if (empty($where_parts)) {
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($arama) . '%';
            
            // Büyük harfe dönüştürülerek arama yapalım
            $where_parts[] = "(" . sqlTurkceUpper("KOD") . " LIKE ? OR " . sqlTurkceUpper("ADI") . " LIKE ?)";
            
            $params[] = $search_param;
            $params[] = $search_param;
            
            debugEcho("Tam arama: $arama, Arama parametresi: $search_param");
        }
        
        // WHERE koşulunu oluştur - tüm kelimeler için AND bağlacı kullan
        $where_clause = implode(' AND ', $where_parts);
        debugEcho("WHERE koşulu:", $where_clause);
        debugEcho("Sorgu parametreleri:", $params);
        
        // Toplam kayıt sayısını al
        $countSql = "SELECT COUNT(*) FROM stok WHERE " . $where_clause;
        debugEcho("Sayım SQL sorgusu:", $countSql);
        
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($params);
        debugEcho("Execute parametreleri COUNT: ", $params);
        
        $toplam_kayit = $countStmt->fetchColumn();
        debugEcho("Toplam kayıt sayısı: " . $toplam_kayit);
        
        // Ana sorguyu hazırla - LIMIT ve OFFSET sorununu düzelt
        $sql = "SELECT * FROM stok WHERE " . $where_clause . " ORDER BY ID DESC LIMIT " . (int)$offset . ", " . (int)$limit;
        debugEcho("Ana SQL sorgusu:", $sql);
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        debugEcho("Execute parametreleri: ", $params);
        
        $urunler = $stmt->fetchAll();
        debugEcho("Bulunan ürün sayısı: " . count($urunler));
        
        // STK_FIS_HAR tablosundan stok miktarlarını al
        if (count($urunler) > 0) {
            try {
                // Ürün ID'lerini al
                $urunIds = [];
                foreach ($urunler as $urun) {
                    $urunIds[] = $urun['ID'];
                }
                
                // Stok miktarlarını hesapla
                if (!empty($urunIds)) {
                    $stokMiktarlari = [];
                    
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
                    
                    debugEcho("Stok SQL sorgusu:", $stokSql);
                    
                    try {
                        $stokStmt = $db->query($stokSql);
                        while ($stokRow = $stokStmt->fetch(PDO::FETCH_ASSOC)) {
                            $stokMiktarlari[$stokRow['KARTID']] = $stokRow['GIRIS_MIKTAR'] - $stokRow['CIKIS_MIKTAR'];
                        }
                        
                        // Her ürün için stok miktarlarını güncelle
                        foreach ($urunler as &$urun) {
                            $urun['GUNCEL_STOK'] = isset($stokMiktarlari[$urun['ID']]) ? $stokMiktarlari[$urun['ID']] : 0;
                        }
                        
                        debugEcho("Stok miktarları hesaplandı:", $stokMiktarlari);
                    } catch (PDOException $stokHata) {
                        debugEcho("Stok miktarı hesaplama hatası: " . $stokHata->getMessage());
                    }
                }
                
                // Satış fiyatlarını STK_FIYAT tablosundan al
                try {
                    $fiyatSql = "SELECT 
                        STOKID,
                        FIYAT
                    FROM 
                        STK_FIYAT
                    WHERE 
                        TIP = 'S' AND STOKID IN (" . implode(',', $urunIds) . ")";
                    
                    debugEcho("Fiyat SQL sorgusu:", $fiyatSql);
                    
                    try {
                        $fiyatStmt = $db->query($fiyatSql);
                        $fiyatlar = [];
                        
                        while ($fiyatRow = $fiyatStmt->fetch(PDO::FETCH_ASSOC)) {
                            $fiyatlar[$fiyatRow['STOKID']] = $fiyatRow['FIYAT'];
                        }
                        
                        // Her ürün için fiyat bilgisini güncelle
                        foreach ($urunler as &$urun) {
                            $urun['GUNCEL_FIYAT'] = isset($fiyatlar[$urun['ID']]) ? $fiyatlar[$urun['ID']] : $urun['SATIS_FIYAT'];
                        }
                        
                        debugEcho("Satış fiyatları alındı:", $fiyatlar);
                    } catch (PDOException $fiyatHata) {
                        debugEcho("Fiyat alma hatası: " . $fiyatHata->getMessage());
                    }
                } catch (Exception $e) {
                    debugEcho("Fiyat işlem hatası: " . $e->getMessage());
                }
            } catch (Exception $e) {
                debugEcho("Stok miktarı işlem hatası: " . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
        debugEcho("HATA: " . $error);
    }
}
// Detaylı arama
elseif ($arama_modu == 'detayli' && ($_SERVER['REQUEST_METHOD'] == 'GET' && (
    !empty($stok_kodu) || 
    !empty($urun_adi) || 
    !empty($tip) || 
    !empty($birim) || 
    $min_stok !== null || 
    $max_stok !== null || 
    $min_fiyat !== null || 
    $max_fiyat !== null || 
    !empty($durum)
))) {
    $filtreSorgusu = true;
    
    try {
        debugEcho("Detaylı arama işlemi başlatılıyor...");
        
        // SQL sorgusunu oluştur
        $whereConditions = [];
        $params = [];
        
        if (!empty($stok_kodu)) {
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($stok_kodu) . '%';
            $whereConditions[] = sqlTurkceUpper("KOD") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if (!empty($urun_adi)) {
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($urun_adi) . '%';
            $whereConditions[] = sqlTurkceUpper("ADI") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if (!empty($tip)) {
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($tip) . '%';
            $whereConditions[] = sqlTurkceUpper("TIP") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if (!empty($birim)) {
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($birim) . '%';
            $whereConditions[] = sqlTurkceUpper("BIRIM") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if ($min_stok !== null) {
            $whereConditions[] = "MIKTAR >= ?";
            $params[] = $min_stok;
        }
        
        if ($max_stok !== null) {
            $whereConditions[] = "MIKTAR <= ?";
            $params[] = $max_stok;
        }
        
        if ($min_fiyat !== null) {
            $whereConditions[] = "SATIS_FIYAT >= ?";
            $params[] = $min_fiyat;
        }
        
        if ($max_fiyat !== null) {
            $whereConditions[] = "SATIS_FIYAT <= ?";
            $params[] = $max_fiyat;
        }
        
        if (!empty($durum)) {
            $whereConditions[] = "DURUM = ?";
            $params[] = $durum;
        }
        
        // WHERE koşulunu oluştur
        $whereClause = "";
        if (!empty($whereConditions)) {
            $whereClause = "WHERE " . implode(" AND ", $whereConditions);
        }
        
        debugEcho("Oluşturulan WHERE koşulu:", $whereClause);
        debugEcho("Hazırlanan parametreler:", $params);
        
        // Artık LIMIT ve OFFSET doğrudan SQL sorgusuna eklendiğinden
        // sadece normal parametreleri kullanıyoruz
        $countParams = $params;
        
        // Toplam kayıt sayısını al
        $countSql = "SELECT COUNT(*) FROM stok $whereClause";
        debugEcho("Sayım SQL sorgusu:", $countSql);
        
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        debugEcho("Execute parametreleri (COUNT):", $countParams);
        
        $toplam_kayit = $countStmt->fetchColumn();
        debugEcho("Toplam kayıt sayısı: " . $toplam_kayit);
        
        // Ana sorguyu hazırla
        $sql = "SELECT * FROM stok $whereClause ORDER BY ID DESC LIMIT " . (int)$offset . ", " . (int)$limit;
        debugEcho("Ana SQL sorgusu:", $sql);
        
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        debugEcho("Execute parametreleri:", $params);
        
        $urunler = $stmt->fetchAll();
        debugEcho("Bulunan ürün sayısı: " . count($urunler));
        
        // STK_FIS_HAR tablosundan stok miktarlarını al
        if (count($urunler) > 0) {
            try {
                // Ürün ID'lerini al
                $urunIds = [];
                foreach ($urunler as $urun) {
                    $urunIds[] = $urun['ID'];
                }
                
                // Stok miktarlarını hesapla
                if (!empty($urunIds)) {
                    $stokMiktarlari = [];
                    
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
                    
                    debugEcho("Stok SQL sorgusu:", $stokSql);
                    
                    try {
                        $stokStmt = $db->query($stokSql);
                        while ($stokRow = $stokStmt->fetch(PDO::FETCH_ASSOC)) {
                            $stokMiktarlari[$stokRow['KARTID']] = $stokRow['GIRIS_MIKTAR'] - $stokRow['CIKIS_MIKTAR'];
                        }
                        
                        // Her ürün için stok miktarlarını güncelle
                        foreach ($urunler as &$urun) {
                            $urun['GUNCEL_STOK'] = isset($stokMiktarlari[$urun['ID']]) ? $stokMiktarlari[$urun['ID']] : 0;
                        }
                        
                        debugEcho("Stok miktarları hesaplandı:", $stokMiktarlari);
                    } catch (PDOException $stokHata) {
                        debugEcho("Stok miktarı hesaplama hatası: " . $stokHata->getMessage());
                    }
                }
                
                // Satış fiyatlarını STK_FIYAT tablosundan al
                try {
                    $fiyatSql = "SELECT 
                        STOKID,
                        FIYAT
                    FROM 
                        STK_FIYAT
                    WHERE 
                        TIP = 'S' AND STOKID IN (" . implode(',', $urunIds) . ")";
                    
                    debugEcho("Fiyat SQL sorgusu:", $fiyatSql);
                    
                    try {
                        $fiyatStmt = $db->query($fiyatSql);
                        $fiyatlar = [];
                        
                        while ($fiyatRow = $fiyatStmt->fetch(PDO::FETCH_ASSOC)) {
                            $fiyatlar[$fiyatRow['STOKID']] = $fiyatRow['FIYAT'];
                        }
                        
                        // Her ürün için fiyat bilgisini güncelle
                        foreach ($urunler as &$urun) {
                            $urun['GUNCEL_FIYAT'] = isset($fiyatlar[$urun['ID']]) ? $fiyatlar[$urun['ID']] : $urun['SATIS_FIYAT'];
                        }
                        
                        debugEcho("Satış fiyatları alındı:", $fiyatlar);
                    } catch (PDOException $fiyatHata) {
                        debugEcho("Fiyat alma hatası: " . $fiyatHata->getMessage());
                    }
                } catch (Exception $e) {
                    debugEcho("Fiyat işlem hatası: " . $e->getMessage());
                }
            } catch (Exception $e) {
                debugEcho("Stok miktarı işlem hatası: " . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
        debugEcho("HATA: " . $error);
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-1 mb-2 border-bottom">
    <h1 class="h3">Ürün Arama</h1>
    <div class="btn-toolbar mb-0 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Stok Yönetimine Dön
            </a>
            <a href="urun_ekle.php" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus"></i> Yeni Ürün Ekle
            </a>
        </div>
    </div>
</div>

<?php
// Hata mesajı varsa göster
if (isset($error)) {
    echo '<div class="alert alert-danger">' . $error . '</div>';
}
?>

<!-- Arama Formları -->
<div class="card shadow mb-3">
    <div class="card-header py-2">
        <ul class="nav nav-tabs card-header-tabs" id="arama-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($arama_modu == 'hizli') ? 'active' : ''; ?>" 
                        id="hizli-arama-tab" data-bs-toggle="tab" data-bs-target="#hizli-arama"
                        type="button" role="tab" aria-controls="hizli-arama" aria-selected="<?php echo ($arama_modu == 'hizli') ? 'true' : 'false'; ?>">
                    <i class="fas fa-bolt"></i> Hızlı Arama
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($arama_modu == 'detayli') ? 'active' : ''; ?>" 
                        id="detayli-arama-tab" data-bs-toggle="tab" data-bs-target="#detayli-arama"
                        type="button" role="tab" aria-controls="detayli-arama" aria-selected="<?php echo ($arama_modu == 'detayli') ? 'true' : 'false'; ?>">
                    <i class="fas fa-search-plus"></i> Detaylı Arama
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body py-2">
        <div class="tab-content" id="arama-tabs-content">
            <!-- Hızlı Arama Formu -->
            <div class="tab-pane fade <?php echo ($arama_modu == 'hizli') ? 'show active' : ''; ?>" id="hizli-arama" role="tabpanel" aria-labelledby="hizli-arama-tab">
                <form action="urun_arama.php" method="get" class="mb-0">
                    <input type="hidden" name="arama_modu" value="hizli">
                    <div class="input-group">
                        <input type="text" class="form-control" 
                               placeholder="Ürün kodu veya adı ile arama yapın..." 
                               name="arama" value="<?php echo html_entity_decode(htmlspecialchars($arama)); ?>" autofocus>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Ara
                        </button>
                        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?arama_modu=hizli" class="btn btn-secondary">
                            <i class="fas fa-eraser"></i>
                        </a>
                    </div>
                    <small class="form-text text-muted mt-1">
                        <i class="fas fa-info-circle"></i> Ürün kodu veya adı ile arama yapabilirsiniz.
                    </small>
                </form>
            </div>
            
            <!-- Detaylı Arama Formu -->
            <div class="tab-pane fade <?php echo ($arama_modu == 'detayli') ? 'show active' : ''; ?>" id="detayli-arama" role="tabpanel" aria-labelledby="detayli-arama-tab">
                <form action="urun_arama.php" method="get" class="row g-2">
                    <input type="hidden" name="arama_modu" value="detayli">
                    
                    <!-- Temel Bilgiler -->
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="stok_kodu" name="stok_kodu" value="<?php echo html_entity_decode(htmlspecialchars($stok_kodu)); ?>" placeholder="Stok Kodu">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" id="urun_adi" name="urun_adi" value="<?php echo html_entity_decode(htmlspecialchars($urun_adi)); ?>" placeholder="Ürün Adı">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="tip" name="tip" value="<?php echo html_entity_decode(htmlspecialchars($tip)); ?>" placeholder="Tip">
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="birim" name="birim" value="<?php echo html_entity_decode(htmlspecialchars($birim)); ?>" placeholder="Birim">
                    </div>
                    
                    <!-- İkinci Satır -->
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" id="min_stok" name="min_stok" value="<?php echo $min_stok !== null ? htmlspecialchars($min_stok) : ''; ?>" placeholder="Min. Stok Miktarı">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" id="max_stok" name="max_stok" value="<?php echo $max_stok !== null ? htmlspecialchars($max_stok) : ''; ?>" placeholder="Max. Stok Miktarı">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" step="0.01" class="form-control form-control-sm" id="min_fiyat" name="min_fiyat" value="<?php echo $min_fiyat !== null ? htmlspecialchars($min_fiyat) : ''; ?>" placeholder="Min. Fiyat (₺)">
                    </div>
                    <div class="col-md-2">
                        <input type="number" step="0.01" class="form-control form-control-sm" id="max_fiyat" name="max_fiyat" value="<?php echo $max_fiyat !== null ? htmlspecialchars($max_fiyat) : ''; ?>" placeholder="Max. Fiyat (₺)">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="durum" name="durum">
                            <option value="">Durum</option>
                            <option value="1" <?php echo ($durum == '1') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="0" <?php echo ($durum == '0') ? 'selected' : ''; ?>>Pasif</option>
                        </select>
                    </div>
                    
                    <!-- Butonlar -->
                    <div class="col-md-2">
                        <div class="d-grid gap-2 d-md-block">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Ara
                            </button>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?arama_modu=detayli" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eraser"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($filtreSorgusu): ?>
<!-- Arama Sonuçları -->
<div class="card shadow mb-4">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Sonuçlar (<?php echo count($urunler); ?> Ürün)</h6>
        <?php if (count($urunler) > 0): ?>
        <div>
            <button type="button" class="btn btn-sm btn-success" onclick="exportSearchResults()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (count($urunler) > 0): ?>
            <div class="table-responsive">
                <?php
                // Ürünleri gruplarına göre düzenle - Stok tablosunda grup olmadığından basitleştirilmiş
                debugEcho("Ürünleri gruplama işlemi başlatılıyor...");
                $groupedProducts = [];
                $groupId = 'all_products'; // Tek bir grup ID'si kullanıyoruz
                $groupedProducts[$groupId] = $urunler;
                debugEcho("Ürünler başarıyla gruplandı. Tek grup: " . $groupId . ", Ürün sayısı: " . count($urunler));
                ?>
                
                <style>
                    .product-group {
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        margin-bottom: 10px;
                        overflow: hidden;
                    }
                    .product-group-content {
                        padding: 0;
                    }
                    .product-group-content table {
                        margin-bottom: 0;
                    }
                    .product-group table th {
                        position: sticky;
                        top: 0;
                        background-color: #fff;
                        z-index: 10;
                        padding: 6px;
                        font-size: 0.85rem;
                    }
                    .product-group table td {
                        padding: 5px 6px;
                        font-size: 0.85rem;
                    }
                    .btn-sm {
                        padding: 0.2rem 0.4rem;
                        font-size: 0.75rem;
                    }
                    .table-compact {
                        margin-bottom: 0;
                    }
                </style>
                
                <?php foreach ($groupedProducts as $groupId => $products): ?>
                    <?php debugEcho("Gösterilen grup: " . $groupId . ", Ürün sayısı: " . count($products)); ?>
                    <div class="product-group">
                        <div class="product-group-content">
                            <table class="table table-bordered table-hover table-compact" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Stok Kodu</th>
                                        <th>Ürün Adı</th>
                                        <th>Tip</th>
                                        <th>Birim</th>
                                        <th class="text-end">Stok</th>
                                        <th class="text-end">Satış Fiyatı</th>
                                        <th>Durum</th>
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $index => $urun): ?>
                                        <?php 
                                        if ($index == 0) {
                                            debugEcho("İlk ürün bilgileri:", $urun);
                                        }
                                        ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($urun['KOD']); ?></td>
                                            <td>
                                                <a href="urun_detay.php?id=<?php echo $urun['ID']; ?>">
                                                    <?php echo html_entity_decode(htmlspecialchars($urun['ADI'])); ?>
                                                </a>
                                            </td>
                                            <td><?php echo isset($urun['TIP']) ? htmlspecialchars($urun['TIP']) : ''; ?></td>
                                            <td><?php echo isset($urun['BIRIM']) ? htmlspecialchars($urun['BIRIM']) : '-'; ?></td>
                                            <td class="text-end">
                                                <?php if (isset($urun['GUNCEL_STOK'])): ?>
                                                    <?php
                                                    $guncel_stok = (float)$urun['GUNCEL_STOK'];
                                                    $sistem_stok = isset($urun['MIKTAR']) ? (float)$urun['MIKTAR'] : 0;
                                                    
                                                    if ($sistem_stok != $guncel_stok):
                                                    ?>
                                                        <span class="text-success"><?php echo number_format($guncel_stok, 2, ',', '.'); ?></span>
                                                        <br><small class="text-muted">Sistem: <?php echo number_format($sistem_stok, 2, ',', '.'); ?></small>
                                                    <?php else: ?>
                                                        <?php echo number_format($guncel_stok, 2, ',', '.'); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    <?php echo isset($urun['MIKTAR']) ? number_format($urun['MIKTAR'], 2, ',', '.') : '0,00'; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end">
                                                <?php if (isset($urun['GUNCEL_FIYAT'])): ?>
                                                    <?php
                                                    $guncel_fiyat = (float)$urun['GUNCEL_FIYAT'];
                                                    $sistem_fiyat = isset($urun['SATIS_FIYAT']) ? (float)$urun['SATIS_FIYAT'] : 0;
                                                    
                                                    if ($sistem_fiyat != $guncel_fiyat):
                                                    ?>
                                                        <span class="text-primary">₺<?php echo number_format($guncel_fiyat, 2, ',', '.'); ?></span>
                                                        <br><small class="text-muted">Sistem: ₺<?php echo number_format($sistem_fiyat, 2, ',', '.'); ?></small>
                                                    <?php else: ?>
                                                        ₺<?php echo number_format($guncel_fiyat, 2, ',', '.'); ?>
                                                    <?php endif; ?>
                                                <?php else: ?>
                                                    ₺<?php echo isset($urun['SATIS_FIYAT']) ? number_format($urun['SATIS_FIYAT'], 2, ',', '.') : '0,00'; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <?php if ($urun['DURUM'] == 1): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php elseif ($urun['DURUM'] == 0): ?>
                                                    <span class="badge bg-danger">Pasif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="urun_detay.php?id=<?php echo $urun['ID']; ?>" class="btn btn-sm btn-info" title="Detay"><i class="fas fa-eye"></i></a>
                                                    <a href="urun_duzenle.php?id=<?php echo $urun['ID']; ?>" class="btn btn-sm btn-primary" title="Düzenle"><i class="fas fa-edit"></i></a>
                                                    <a href="stok_hareketi_ekle.php?urun_id=<?php echo $urun['ID']; ?>" class="btn btn-sm btn-success" title="Stok Hareketi Ekle"><i class="fas fa-plus"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
                
                <?php 
                // Sayfalama bilgileri
                $totalPages = ceil($toplam_kayit / $limit);
                $nextPage = $page + 1;
                
                // Daha fazla sayfa varsa "Daha Fazla Yükle" butonu göster
                if ($page < $totalPages): 
                    $params = $_GET;
                    $params['page'] = $nextPage;
                    $queryString = http_build_query($params);
                ?>
                <div class="mt-3 text-center">
                    <a href="?<?php echo $queryString; ?>" class="btn btn-outline-primary">
                        <i class="fas fa-sync"></i> Daha Fazla Yükle (<?php echo ($offset + count($urunler)); ?>/<?php echo $toplam_kayit; ?>)
                    </a>
                </div>
                <?php endif; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Arama kriterlerinize uygun ürün bulunamadı.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab değiştiğinde form modunu ayarla
        const tabLinks = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabLinks.forEach(function(tabLink) {
            tabLink.addEventListener('shown.bs.tab', function (event) {
                const targetId = event.target.getAttribute('id');
                if (targetId === 'hizli-arama-tab') {
                    document.querySelectorAll('input[name="arama_modu"]').forEach(input => input.value = 'hizli');
                } else if (targetId === 'detayli-arama-tab') {
                    document.querySelectorAll('input[name="arama_modu"]').forEach(input => input.value = 'detayli');
                }
            });
        });
        
        // Hızlı arama inputu üzerinde enter tuşuna basıldığında form gönderme
        const hizliAramaInput = document.querySelector('#hizli-arama input[name="arama"]');
        if (hizliAramaInput) {
            hizliAramaInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.closest('form').submit();
                }
            });
        }
        
        // Grup başlıklarının tıklanabilir olması için
        const groupTitles = document.querySelectorAll('.product-group-title');
        groupTitles.forEach(function(title) {
            title.addEventListener('click', function() {
                const content = this.nextElementSibling;
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    this.querySelector('i').classList.remove('fa-plus');
                    this.querySelector('i').classList.add('fa-minus');
                } else {
                    content.style.display = 'none';
                    this.querySelector('i').classList.remove('fa-minus');
                    this.querySelector('i').classList.add('fa-plus');
                }
            });
            
            // İkon değiştir
            const icon = title.querySelector('i');
            if (icon && icon.classList.contains('fa-layer-group')) {
                icon.classList.remove('fa-layer-group');
                icon.classList.add('fa-minus');
            }
            
            // Tıklanabilir olduğunu göster
            title.style.cursor = 'pointer';
        });
    });
    
    // Excel'e aktarma fonksiyonu - Tüm arama sonuçlarını toplar
    function exportSearchResults() {
        try {
            // Tüm grupları birleştirerek bir tablo oluştur
            let combinedTable = document.createElement('table');
            combinedTable.id = 'combined_search_results';
            combinedTable.style.display = 'none';
            document.body.appendChild(combinedTable);
            
            console.log('Excel tablosu oluşturuldu');
            
            // Başlık satırını ekle
            let headerRow = document.createElement('tr');
            ['Stok Kodu', 'Ürün Adı', 'Tip', 'Birim', 'Stok', 'Satış Fiyatı', 'Durum'].forEach(header => {
                let th = document.createElement('th');
                th.textContent = header;
                headerRow.appendChild(th);
            });
            
            let thead = document.createElement('thead');
            thead.appendChild(headerRow);
            combinedTable.appendChild(thead);
            
            console.log('Başlık satırı eklendi');
            
            // Tüm gruplardaki verileri bir tbody'e ekle
            let tbody = document.createElement('tbody');
            
            const productGroups = document.querySelectorAll('.product-group');
            console.log(`${productGroups.length} adet ürün grubu bulundu`);
            
            productGroups.forEach((group, groupIndex) => {
                const rows = group.querySelectorAll('tbody tr');
                console.log(`Grup ${groupIndex}: ${rows.length} satır içeriyor`);
                
                rows.forEach((row, rowIndex) => {
                    let newRow = document.createElement('tr');
                    
                    // İşlem sütununu hariç tut (son sütun)
                    const cells = row.querySelectorAll('td:not(:last-child)');
                    cells.forEach((cell, cellIndex) => {
                        let newCell = document.createElement('td');
                        newCell.innerHTML = cell.innerHTML.replace(/<\/?a[^>]*>/g, ''); // Linkleri kaldır
                        newRow.appendChild(newCell);
                    });
                    
                    tbody.appendChild(newRow);
                    if (rowIndex === 0) {
                        console.log(`İlk satır ${cells.length} hücre içeriyor`);
                    }
                });
            });
            
            combinedTable.appendChild(tbody);
            console.log('Tablo verileri eklendi');
            
            // Excel'e aktar
            exportTableToExcel('combined_search_results', 'urun_arama_sonuclari');
            
            // Geçici tabloyu temizle
            document.body.removeChild(combinedTable);
            console.log('Excel aktarımı tamamlandı');
        } catch (error) {
            console.error('Excel aktarma hatası:', error);
            alert('Excel aktarma sırasında bir hata oluştu: ' + error.message);
        }
    }
    
    // Excel dönüştürme temel fonksiyonu
    function exportTableToExcel(tableID, filename = '') {
        try {
            var downloadLink;
            var dataType = 'application/vnd.ms-excel';
            var tableSelect = document.getElementById(tableID);
            
            if (!tableSelect) {
                throw new Error(`'${tableID}' ID'sine sahip tablo bulunamadı`);
            }
            
            var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
            
            // Dosya adını oluştur
            filename = filename ? filename + '.xls' : 'excel_data.xls';
            
            // Link oluştur ve indir
            downloadLink = document.createElement("a");
            document.body.appendChild(downloadLink);
            
            if(navigator.msSaveOrOpenBlob) {
                var blob = new Blob(['\ufeff', tableHTML], {
                    type: dataType
                });
                navigator.msSaveOrOpenBlob(blob, filename);
            } else {
                // Base64 formatına dönüştür
                downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
                downloadLink.download = filename;
                downloadLink.click();
            }
            
            console.log('Excel indirme bağlantısı oluşturuldu: ' + filename);
        } catch (error) {
            console.error('Excel dönüştürme hatası:', error);
            alert('Excel dönüştürme sırasında bir hata oluştu: ' + error.message);
        }
    }
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 