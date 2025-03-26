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

// Önbellek yenileme kontrolü
if (isset($_GET['refresh_cache'])) {
    // Önbelleği temizle
    unset($_SESSION['cache_timestamp']);
    unset($_SESSION['cache_stok_sayisi']);
    unset($_SESSION['cache_kritik_stok_sayisi']);
    unset($_SESSION['cache_toplam_stok_degeri']);
    unset($_SESSION['cache_son_eklenen_urunler']);
    unset($_SESSION['cache_kritik_stok_urunler']);
    
    // Sayfayı yeniden yükle
    header('Location: index.php?cache_refreshed=1');
    exit;
}

// Önbellek yenileme sonrası mesaj
$cacheRefreshMessage = '';
if (isset($_GET['cache_refreshed'])) {
    $cacheRefreshMessage = '<div class="alert alert-success alert-dismissible fade show" role="alert">
                            <i class="fas fa-check-circle"></i> Önbellek başarıyla temizlendi ve yeniden oluşturuldu.
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>';
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Stok Yönetimi";

// Stok sayısını al
$stokSayisi = 0;
$cacheSuresi = 3600; // 1 saat cache süresi

// Session cache kontrolü
if (!isset($_SESSION['cache_timestamp']) || (time() - $_SESSION['cache_timestamp'] > $cacheSuresi)) {
    // Cache süresi dolmuş veya hiç oluşturulmamış, verileri yeniden çekelim
    $_SESSION['cache_timestamp'] = time();
    
    // Stok sayısını al
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM stok");
        $stokSayisi = $stmt->fetchColumn();
        $_SESSION['cache_stok_sayisi'] = $stokSayisi;
    } catch (PDOException $e) {
        // Hata durumunda
        echo errorMessage("Veritabanı hatası: " . $e->getMessage());
    }
    
    // Kritik stok sayısını al - STK_URUN_MIKTAR'ı kullanarak
    $kritikStokSayisi = 0;
    try {
        $stmt = $db->query("SELECT COUNT(*) FROM stok s 
                             LEFT JOIN stk_urun_miktar m ON s.ID = m.URUN_ID
                             WHERE m.MIKTAR <= s.MIN_STOK AND s.DURUM = 1");
        
        $kritikStokSayisi = $stmt->fetchColumn();
        $_SESSION['cache_kritik_stok_sayisi'] = $kritikStokSayisi;
    } catch (PDOException $e) {
        // Hata durumunda
        $_SESSION['cache_kritik_stok_sayisi'] = 0;
        echo errorMessage("Veritabanı hatası: " . $e->getMessage());
    }
    
    // Toplam stok değerini al - STK_URUN_MIKTAR'ı kullanarak
    $toplamStokDegeri = 0;
    try {
        // Önce stk_fiyat tablosundan alış fiyatlarını alıp birleştirelim
        $stmt = $db->query("SELECT SUM(m.MIKTAR * f.FIYAT) AS toplam_deger 
                             FROM stok s 
                             LEFT JOIN stk_urun_miktar m ON s.ID = m.URUN_ID
                             LEFT JOIN stk_fiyat f ON s.ID = f.STOKID AND f.TIP = 'A'
                             WHERE s.DURUM = 1");
        
        $result = $stmt->fetch();
        $toplamStokDegeri = $result['toplam_deger'] ?? 0;
        $_SESSION['cache_toplam_stok_degeri'] = $toplamStokDegeri;
    } catch (PDOException $e) {
        // Hata durumunda
        $_SESSION['cache_toplam_stok_degeri'] = 0;
        echo errorMessage("Veritabanı hatası: " . $e->getMessage());
    }
    
    // Son eklenen ürünleri al
    $sonEklenenUrunler = [];
    try {
        $stmt = $db->query("SELECT * FROM stok ORDER BY EKLENME_TARIHI DESC LIMIT 5");
        $sonEklenenUrunler = $stmt->fetchAll();
        $_SESSION['cache_son_eklenen_urunler'] = $sonEklenenUrunler;
    } catch (PDOException $e) {
        // Hata durumunda
        $_SESSION['cache_son_eklenen_urunler'] = [];
        echo errorMessage("Veritabanı hatası: " . $e->getMessage());
    }
    
    // Kritik stok seviyesindeki ürünleri al
    $kritikStokUrunler = [];
    try {
        $stmt = $db->query("SELECT s.*, m.MIKTAR 
                            FROM stok s 
                            LEFT JOIN stk_urun_miktar m ON s.ID = m.URUN_ID 
                            WHERE m.MIKTAR <= s.MIN_STOK AND s.DURUM = 1 
                            ORDER BY m.MIKTAR ASC LIMIT 5");
        $kritikStokUrunler = $stmt->fetchAll();
        $_SESSION['cache_kritik_stok_urunler'] = $kritikStokUrunler;
    } catch (PDOException $e) {
        // Hata durumunda
        $_SESSION['cache_kritik_stok_urunler'] = [];
        echo errorMessage("Veritabanı hatası: " . $e->getMessage());
    }
    
    // Cache oluşturulduğuna dair mesaj
    echo '<div class="alert alert-info alert-dismissible fade show" role="alert">
            <i class="fas fa-sync"></i> Önbellek başarıyla güncellendi. 
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
          </div>';
} else {
    // Cache'den verileri al
    $stokSayisi = $_SESSION['cache_stok_sayisi'] ?? 0;
    $kritikStokSayisi = $_SESSION['cache_kritik_stok_sayisi'] ?? 0;
    $toplamStokDegeri = $_SESSION['cache_toplam_stok_degeri'] ?? 0;
    $sonEklenenUrunler = $_SESSION['cache_son_eklenen_urunler'] ?? [];
    $kritikStokUrunler = $_SESSION['cache_kritik_stok_urunler'] ?? [];
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
            <a href="create_stok_miktari_table.php" class="btn btn-sm btn-outline-danger">
                <i class="fas fa-database"></i> Stok Miktarı Tablosu Oluştur
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

<?php 
// Önbellek yenileme mesajını göster
echo $cacheRefreshMessage;
?>

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

<!-- Son Eklenen Ürünler ve Kritik Stok Seviyesindeki Ürünler -->
<div class="row">
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Son Eklenen Ürünler</h6>
                <a href="urun_ekle.php" class="btn btn-sm btn-outline-success">
                    <i class="fas fa-plus"></i> Yeni Ürün
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($sonEklenenUrunler)): ?>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Henüz kayıtlı ürün bulunmuyor veya veri çekilemedi.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Stok Kodu</th>
                                    <th>Ürün Adı</th>
                                    <th>Stok Miktarı</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($sonEklenenUrunler as $urun): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($urun['KOD']); ?></td>
                                        <td><a href="urun_detay.php?id=<?php echo $urun['ID']; ?>"><?php echo htmlspecialchars($urun['ADI']); ?></a></td>
                                        <td><?php echo isset($urun['MIKTAR']) ? number_format($urun['MIKTAR'], 2, ',', '.') : '0,00'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="urun_detay.php?id=<?php echo $urun['ID']; ?>" class="btn btn-info" title="Detay"><i class="fas fa-eye"></i></a>
                                                <a href="urun_duzenle.php?id=<?php echo $urun['ID']; ?>" class="btn btn-primary" title="Düzenle"><i class="fas fa-edit"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
                <div class="mt-2">
                    <small class="text-muted">
                        <i class="fas fa-history"></i> Önbellek zamanı: <?php echo isset($_SESSION['cache_timestamp']) ? date('d.m.Y H:i:s', $_SESSION['cache_timestamp']) : 'Belirlenmedi'; ?>
                    </small>
                </div>
            </div>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-warning">Kritik Stok Seviyesindeki Ürünler</h6>
                <a href="stok_hareketi_ekle.php" class="btn btn-sm btn-outline-warning">
                    <i class="fas fa-plus"></i> Stok Girişi
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($kritikStokUrunler)): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> Kritik stok seviyesinde ürün bulunmuyor.
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-bordered table-sm" width="100%" cellspacing="0">
                            <thead>
                                <tr>
                                    <th>Stok Kodu</th>
                                    <th>Ürün Adı</th>
                                    <th>Kritik</th>
                                    <th>Mevcut</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kritikStokUrunler as $urun): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($urun['KOD']); ?></td>
                                        <td><a href="urun_detay.php?id=<?php echo $urun['ID']; ?>"><?php echo htmlspecialchars($urun['ADI']); ?></a></td>
                                        <td><?php echo isset($urun['MIN_STOK']) ? number_format($urun['MIN_STOK'], 2, ',', '.') : '0,00'; ?></td>
                                        <td class="text-danger fw-bold"><?php echo isset($urun['MIKTAR']) ? number_format($urun['MIKTAR'], 2, ',', '.') : '0,00'; ?></td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <a href="stok_hareketi_ekle.php?urun_id=<?php echo $urun['ID']; ?>" class="btn btn-success" title="Stok Ekle"><i class="fas fa-plus"></i></a>
                                                <a href="urun_detay.php?id=<?php echo $urun['ID']; ?>" class="btn btn-info" title="Detay"><i class="fas fa-eye"></i></a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Önbellek Yenileme -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-info">Önbellek Yönetimi</h6>
    </div>
    <div class="card-body">
        <p>Önbellek son güncellenme: <strong><?php echo isset($_SESSION['cache_timestamp']) ? date('d.m.Y H:i:s', $_SESSION['cache_timestamp']) : 'Belirlenmedi'; ?></strong></p>
        <p>Otomatik güncellenme süresi: <strong><?php echo $cacheSuresi / 60; ?> dakika</strong></p>
        <form action="" method="get">
            <input type="hidden" name="refresh_cache" value="1">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-sync"></i> Önbelleği Yenile
            </button>
        </form>
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
                                        // STK_URUN_MIKTAR tablosundan stok miktarlarını al
                                        $stokSql = "SELECT 
                                            URUN_ID as KARTID,
                                            MIKTAR
                                        FROM 
                                            STK_URUN_MIKTAR
                                        WHERE 
                                            URUN_ID IN (" . implode(',', $urunIds) . ")";
                                        
                                        try {
                                            $stokStmt = $db->query($stokSql);
                                            while ($stokRow = $stokStmt->fetch()) {
                                                $stokMiktarlari[$stokRow['KARTID']] = $stokRow['MIKTAR'];
                                            }
                                        } catch (PDOException $stokHata) {
                                            // STK_URUN_MIKTAR tablosu yoksa veya hata oluşursa eski metodu kullan
                                            try {
                                                // Stok hareketlerinden miktarları hesapla
                                                $eskiStokSql = "SELECT 
                                                    KARTID,
                                                    SUM(CASE WHEN ISLEMTIPI = 0 THEN MIKTAR ELSE 0 END) AS GIRIS_MIKTAR,
                                                    SUM(CASE WHEN ISLEMTIPI = 1 THEN MIKTAR ELSE 0 END) AS CIKIS_MIKTAR
                                                FROM 
                                                    STK_FIS_HAR
                                                WHERE 
                                                    IPTAL = 0 AND KARTID IN (" . implode(',', $urunIds) . ")
                                                GROUP BY 
                                                    KARTID";
                                                
                                                $eskiStokStmt = $db->query($eskiStokSql);
                                                while ($eskiStokRow = $eskiStokStmt->fetch()) {
                                                    $stokMiktarlari[$eskiStokRow['KARTID']] = $eskiStokRow['GIRIS_MIKTAR'] - $eskiStokRow['CIKIS_MIKTAR'];
                                                }
                                                
                                                echo '<tr><td colspan="10" class="text-center text-warning">STK_URUN_MIKTAR tablosu bulunamadı. Stok miktarları STK_FIS_HAR tablosundan hesaplandı. <a href="create_stok_miktari_table.php" class="btn btn-sm btn-warning">Stok Miktarı Tablosunu Oluştur</a></td></tr>';
                                            } catch (PDOException $eskiStokHata) {
                                                echo '<tr><td colspan="10" class="text-center text-warning">Stok miktarları hesaplanırken hata oluştu: ' . $eskiStokHata->getMessage() . '</td></tr>';
                                            }
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