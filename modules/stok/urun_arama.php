<?php
/**
 * ERP Sistem - Ürün Arama Sayfası
 * 
 * Bu dosya ürünleri detaylı arama ve filtreleme işlemlerini gerçekleştirir.
 */

// Gerekli dosyaları dahil et
require_once '../../config/helpers.php';
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// Debug modu aktif
$debug = false; // Hata ayıklama modu açık
$performans_izleme = true; // Performans izleme modu açık
ini_set('display_errors', 1); // Hata mesajlarını göster
error_reporting(E_ALL); // Tüm hataları raporla

// Performans izleme değişkenleri
$performans_olcumleri = [];
$sorgu_sayaci = 0;
$toplam_sorgu_suresi = 0;
$sayfa_baslangic_zamani = microtime(true);

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// PDO hata modunu değiştir ve emulated prepares aktif et
if ($debug) {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Emulated prepares'ı aktif et - LIMIT ve benzeri ifadelerde parametre sorunu yaşamamak için
    $db->setAttribute(PDO::ATTR_EMULATE_PREPARES, true);
    debugEcho("PDO yapılandırması: Emulated Prepares aktif edildi");
}

// Sayfa başlığı
$pageTitle = "Ürün Arama";

// Kullanıcı tercihleri - varsayılan değerler
$goruntulenecek_alanlar = [
    'kod' => true,          // Stok Kodu
    'adi' => true,          // Ürün Adı
    'stok' => true,         // Stok Miktarı
    'alis_fiyat' => true,   // Alış Fiyatı
    'satis_fiyat' => true,  // Satış Fiyatı
    'oem' => true,          // OEM No
    'birim' => false,       // Birim
    'tip' => false,         // Tip
    'barkod' => false,      // Barkod
    'marka' => false,       // Marka
    'raf_kodu' => false,    // Raf Kodu
    'urun_kodu' => false,   // Ürün Kodu
    'ana_kategori' => false, // Ana Kategori
    'alt_kategori' => false, // Alt Kategori
    'urun_markasi' => false, // Ürün Markası
    'arac_markasi' => false, // Araç Markası
    'arac_modeli' => false, // Araç Modeli
    'arac_yil' => false,     // Araç Yıl Aralığı
    'kasa_ismi' => false,    // Kasa İsmi
    'motor_kodu' => false,   // Motor Kodu
    'tedarikci' => false,    // Tedarikçi Firma
];

// Önce oturumdan kontrol et
if (isset($_SESSION['urun_arama_alanlar'])) {
    $goruntulenecek_alanlar = $_SESSION['urun_arama_alanlar'];
} else {
    // Veritabanı tablosunun varlığını kontrol et ve gerekirse oluştur
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS kullanici_tercihleri (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_id INT NOT NULL,
            modul VARCHAR(50) NOT NULL,
            ayar_turu VARCHAR(50) NOT NULL,
            ayar_degeri TEXT,
            guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_kullanici_ayar (kullanici_id, modul, ayar_turu)
        )");
        
        // Veritabanından kullanıcı tercihlerini çek
        $stmt = $db->prepare("SELECT ayar_degeri FROM kullanici_tercihleri 
                           WHERE kullanici_id = :kullanici_id 
                           AND modul = 'stok' 
                           AND ayar_turu = 'urun_arama_alanlar'");
        $stmt->bindParam(':kullanici_id', $_SESSION['user_id'], PDO::PARAM_INT);
        $stmt->execute();
        
        if ($stmt->rowCount() > 0) {
            $ayar = $stmt->fetch(PDO::FETCH_ASSOC);
            $kullanici_tercihleri = json_decode($ayar['ayar_degeri'], true);
            
            if (json_last_error() === JSON_ERROR_NONE) {
                $goruntulenecek_alanlar = array_merge($goruntulenecek_alanlar, $kullanici_tercihleri);
                // Oturuma da kaydet
                $_SESSION['urun_arama_alanlar'] = $goruntulenecek_alanlar;
            }
        }
    } catch (PDOException $e) {
        // Hata durumunda varsayılan ayarları kullan
        error_log("Kullanıcı tercihleri alınırken hata: " . $e->getMessage());
    }
}

// Kullanıcı ayarlarını güncellediyse
if (isset($_POST['guncelle_alan_tercihleri'])) {
    // Tüm alanları önce false olarak ayarla
    $goruntulenecek_alanlar = [
        'kod' => false,
        'adi' => false,
        'stok' => false,
        'alis_fiyat' => false,
        'satis_fiyat' => false,
        'oem' => false,
        'birim' => false,
        'tip' => false,
        'barkod' => false,
        'marka' => false,
        'raf_kodu' => false,
        'urun_kodu' => false,
        'ana_kategori' => false,
        'alt_kategori' => false,
        'urun_markasi' => false,
        'arac_markasi' => false,
        'arac_modeli' => false,
        'arac_yil' => false,
        'kasa_ismi' => false,
        'motor_kodu' => false,
        'tedarikci' => false,
    ];
    
    // Seçilen alanları true olarak işaretle
    foreach ($_POST as $key => $value) {
        if (strpos($key, 'alan_') === 0) {
            $alan = str_replace('alan_', '', $key);
            $goruntulenecek_alanlar[$alan] = true;
        }
    }
    
    // Oturuma kaydet
    $_SESSION['urun_arama_alanlar'] = $goruntulenecek_alanlar;
    
    // Aynı sayfaya yönlendir (POST verilerini temizlemek için)
    header('Location: ' . $_SERVER['PHP_SELF'] . '?' . $_SERVER['QUERY_STRING']);
    exit;
}

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
$limit = 100; // Her sayfada 50 ürün
$offset = ($page - 1) * $limit;

// Ürünleri filtrele
$urunler = [];
$filtreSorgusu = false;
$toplam_kayit = 0;

// Aranan ürünlerin stok miktarlarını getir
if (isset($products) && !empty($products)) {
    $urunIdleri = array_column($products, 'ID');
    
    
    // Stok miktarlarını hesapla ve STK_URUN_MIKTAR tablosuna kaydet
    $stokMiktarlari = hesaplaVeKaydetStokMiktarlari($db, $urunIdleri);
    
    // Her ürün için stok miktarını ata
    foreach ($products as &$product) {
        $product['STOK_MIKTARI'] = $stokMiktarlari[$product['ID']] ?? 0;
        
    }
    
    if ($debug) {
        debugEcho("Stok miktarları hesaplandı ve ürünlere eklendi.");
    }
}

// Ürünleri gruplara ayır (hızlı arama modunda)
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
        
        // Her kelime için ayrı bir koşul oluştur
        foreach ($arama_kelimeleri as $kelime) {
            if (strlen($kelime) < 2) continue; // Çok kısa kelimeleri atla
            
            // Türkçe karakterleri PHP tarafında dönüştür
            $search_param = '%' . searchTermPrepare($kelime) . '%';
            
            // Her kelime kendi içinde OR ile aransın (KOD veya ADI içinde), ancak
            // kelimeler arasında AND bağlacı kullanılsın
            $where_parts[] = "(" . sqlUpperKolonu("s.KOD") . " LIKE ? OR " . sqlUpperKolonu("s.ADI") . " LIKE ? OR " . sqlUpperKolonu("s.OZELALAN10") . " LIKE ?)";
            
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            
            debugEcho("Kelime: $kelime, Arama parametresi: $search_param (kombine arama)");
        }
        
        // Hiç geçerli kelime yoksa
        if (empty($where_parts)) {
            // Genişletilmiş arama parametresi oluştur
            $search_param = '%' . searchTermPrepare($arama) . '%';
            
            // KOD veya ADI içinde ara
            $where_parts[] = "(" . sqlUpperKolonu("s.KOD") . " LIKE ? OR " . sqlUpperKolonu("s.ADI") . " LIKE ? OR " . sqlUpperKolonu("s.OZELALAN10") . " LIKE ?)";
            
            $params[] = $search_param;
            $params[] = $search_param;
            $params[] = $search_param;
            
            debugEcho("Tam arama: $arama, Arama parametresi: $search_param (genişletilmiş arama)");
        }
        
        // WHERE koşulunu oluştur - tüm kelimeler için AND bağlacı kullan,
        // her kelime kendi içinde "KOD LIKE ? OR ADI LIKE ?" şeklinde aranır
        $where_clause = !empty($where_parts) ? implode(' AND ', $where_parts) : "1=1";
        debugEcho("WHERE koşulu:", $where_clause);
        debugEcho("Sorgu parametreleri:", $params);
        
        // Toplam kayıt sayısını al
        $countSql = "SELECT COUNT(*) FROM stok s WHERE " . $where_clause;
        debugEcho("Sayım SQL sorgusu:", $countSql);
        
        try {
            // Performans ölçümlü sorgu çalıştır
            $toplam_kayit = olcumluSorguTekDeger($db, $countSql, $params);
            debugEcho("Toplam kayıt sayısı: " . $toplam_kayit);
        } catch (Exception $e) {
            error_log("Kayıt sayma hatası: " . $e->getMessage());
            debugEcho("Kayıt sayma hatası: " . $e->getMessage());
            $toplam_kayit = 0; // Hata durumunda varsayılan değer
        }
        
        // Ana sorguyu hazırla - LIMIT ve OFFSET sorununu düzelt
        $sql = "SELECT s.*, MAX(sfA.FIYAT) as ALIS_FIYAT,
               IFNULL(um.MIKTAR, 0) as STOK_MIKTARI,
               g1.KOD as ANA_KATEGORI,
               g2.KOD as ALT_KATEGORI, 
               g3.KOD as RAF_KODU,
               g4.KOD as URUN_MARKASI,
               g5.KOD as ARAC_MARKASI,
               g6.KOD as ARAC_MODELI,
               g7.KOD as ARAC_YIL,
               g8.KOD as KASA_ISMI,
               g9.KOD as MOTOR_KODU,
               g10.KOD as TEDARIKCI,
               s.OZELALAN10 as URUN_KODU
        FROM stok s 
        LEFT JOIN STK_URUN_MIKTAR um ON s.ID = um.URUN_ID 
        LEFT JOIN stk_fiyat sfA ON s.ID = sfA.STOKID AND sfA.TIP = 'A'
        LEFT JOIN grup g1 ON s.OZELGRUP1 = g1.ID
        LEFT JOIN grup g2 ON s.OZELGRUP2 = g2.ID
        LEFT JOIN grup g3 ON s.OZELGRUP3 = g3.ID
        LEFT JOIN grup g4 ON s.OZELGRUP4 = g4.ID
        LEFT JOIN grup g5 ON s.OZELGRUP5 = g5.ID
        LEFT JOIN grup g6 ON s.OZELGRUP6 = g6.ID
        LEFT JOIN grup g7 ON s.OZELGRUP7 = g7.ID
        LEFT JOIN grup g8 ON s.OZELGRUP8 = g8.ID
        LEFT JOIN grup g9 ON s.OZELGRUP9 = g9.ID
        LEFT JOIN grup g10 ON s.OZELGRUP10 = g10.ID
        WHERE " . $where_clause . " 
        GROUP BY s.ID
        ORDER BY STOK_MIKTARI DESC 
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        debugEcho("Ana SQL sorgusu:", $sql);
        
        try {
            // Performans ölçümlü sorgu çalıştır
            $urunler = olcumluSorgu($db, $sql, $params);
            debugEcho("Bulunan ürün sayısı: " . count($urunler));
            
            
        } catch (Exception $e) {
            error_log("Ürün sorgulama hatası: " . $e->getMessage());
            debugEcho("Ürün sorgulama hatası: " . $e->getMessage());
            $urunler = []; // Hata durumunda boş dizi
            echo '<div class="alert alert-danger">Ürün bilgileri alınırken bir hata oluştu. Lütfen sayfayı yenileyin veya yöneticinize başvurun.</div>';
        }
        
        // STK_FIS_HAR tablosundan stok miktarlarını al
        if (count($urunler) > 0) {
            try {
                // Ürün ID'lerini al
                $urunIds = array_map(function($urun) {
                    return $urun['ID'];
                }, $urunler);
                
                // Döngüye girmesi muhtemel işlemi db_islem_dene fonksiyonu ile kontrol altına alalım
                db_islem_dene($db, function($db) use ($urunIds, &$urunler) {
                    // Güncel stok miktarlarını hesapla
                    hesaplaVeKaydetStokMiktarlari($db, $urunIds);
                    
                    // Miktarları al
                    $miktarSql = "SELECT URUN_ID, MIKTAR FROM STK_URUN_MIKTAR WHERE URUN_ID IN (" . implode(',', $urunIds) . ")";
                    debugEcho("Miktar SQL sorgusu:", $miktarSql);
                    try {
                        // Performans ölçümlü sorgu çalıştır
                        $miktarSonuc = olcumluSorgu($db, $miktarSql);
                        $miktarlar = [];
                        
                        // Key-Value formatına dönüştür
                        foreach($miktarSonuc as $row) {
                            $miktarlar[$row['URUN_ID']] = $row['MIKTAR'];
                        }
                        
                        debugEcho("Bulunan stok miktarları:", $miktarlar);
                    } catch (Exception $e) {
                        debugEcho("Miktar sorgulama hatası:", $e->getMessage());
                        $miktarlar = [];
                    }
                    
                    // Satış fiyatlarını al
                    $fiyatSql = "SELECT STOKID, FIYAT FROM STK_FIYAT WHERE TIP = 'S' AND STOKID IN (" . implode(',', $urunIds) . ")";
                    debugEcho("Fiyat SQL sorgusu:", $fiyatSql);
                    try {
                        // Performans ölçümlü sorgu çalıştır
                        $fiyatSonuc = olcumluSorgu($db, $fiyatSql);
                        $fiyatlar = [];
                        
                        // Key-Value formatına dönüştür
                        foreach($fiyatSonuc as $row) {
                            $fiyatlar[$row['STOKID']] = $row['FIYAT'];
                        }
                        
                        debugEcho("Bulunan satış fiyatları:", $fiyatlar);
                    } catch (Exception $e) {
                        debugEcho("Fiyat sorgulama hatası:", $e->getMessage());
                        $fiyatlar = [];
                    }
                    
                    // Ürün verilerine ekle - Her ürün için stok miktarı ve satış fiyatını debug ile
                    foreach ($urunler as &$urun) {
                        $urun['current_stock'] = isset($miktarlar[$urun['ID']]) ? $miktarlar[$urun['ID']] : 0;
                        $urun['GUNCEL_FIYAT'] = isset($fiyatlar[$urun['ID']]) ? $fiyatlar[$urun['ID']] : 0;
                        
                        // Debug kontrolü yapmadan önce global değişkeni tanımla
                        global $debug;
                        
                        if ($debug) {
                            debugEcho("Ürün " . $urun['ID'] . " (" . $urun['KOD'] . ") bilgileri:", [
                                'miktar' => $urun['current_stock'],
                                'satış fiyatı' => $urun['GUNCEL_FIYAT']
                            ]);
                        }
                    }
                    
                    return true;
                });
            } catch (Exception $e) {
                error_log("Stok miktarı hesaplama hatası: " . $e->getMessage());
                // Hata mesajını gösterme, ama devam et
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
    !empty($_GET['urun_kodu']) || 
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
            // PHP tarafında dönüştürülmüş arama
            $search_param = '%' . searchTermPrepare($stok_kodu) . '%';
            $whereConditions[] = sqlUpperKolonu("KOD") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if (!empty($urun_adi)) {
            // PHP tarafında dönüştürülmüş arama
            $search_param = '%' . searchTermPrepare($urun_adi) . '%';
            $whereConditions[] = sqlUpperKolonu("ADI") . " LIKE ?";
            $params[] = $search_param;
        }
        
        // Ürün kodu araması (OZELALAN10)
        if (!empty($_GET['urun_kodu'])) {
            $urun_kodu = clean($_GET['urun_kodu']);
            $search_param = '%' . searchTermPrepare($urun_kodu) . '%';
            $whereConditions[] = sqlUpperKolonu("OZELALAN10") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if (!empty($tip)) {
            // PHP tarafında dönüştürülmüş arama
            $search_param = '%' . searchTermPrepare($tip) . '%';
            $whereConditions[] = sqlUpperKolonu("TIP") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if (!empty($birim)) {
            // PHP tarafında dönüştürülmüş arama
            $search_param = '%' . searchTermPrepare($birim) . '%';
            $whereConditions[] = sqlUpperKolonu("BIRIM") . " LIKE ?";
            $params[] = $search_param;
        }
        
        if ($min_stok !== null) {
            // Minimum stok miktarı filtresi için alt sorgu
            $whereConditions[] = "ID IN (SELECT URUN_ID FROM STK_URUN_MIKTAR WHERE MIKTAR >= ?)";
            $params[] = $min_stok;
        }
        
        if ($max_stok !== null) {
            // Maksimum stok miktarı filtresi için alt sorgu
            $whereConditions[] = "ID IN (SELECT URUN_ID FROM STK_URUN_MIKTAR WHERE MIKTAR <= ?)";
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
        
        try {
            // Performans ölçümlü sorgu ile toplam kayıt sayısını al
            $toplam_kayit = olcumluSorguTekDeger($db, $countSql, $countParams);
            debugEcho("Toplam kayıt sayısı: " . $toplam_kayit);
        } catch (Exception $e) {
            debugEcho("Kayıt sayma hatası: " . $e->getMessage(), $countSql);
            $toplam_kayit = 0;
        }
        
        // Ana sorguyu hazırla
        $sql = "SELECT s.*, MAX(sfA.FIYAT) as ALIS_FIYAT,
               IFNULL(um.MIKTAR, 0) as STOK_MIKTARI,
               g1.KOD as ANA_KATEGORI,
               g2.KOD as ALT_KATEGORI, 
               g3.KOD as RAF_KODU,
               g4.KOD as URUN_MARKASI,
               g5.KOD as ARAC_MARKASI,
               g6.KOD as ARAC_MODELI,
               g7.KOD as ARAC_YIL,
               g8.KOD as KASA_ISMI,
               g9.KOD as MOTOR_KODU,
               g10.KOD as TEDARIKCI,
               s.OZELALAN10 as URUN_KODU
        FROM stok s 
        LEFT JOIN STK_URUN_MIKTAR um ON s.ID = um.URUN_ID 
        LEFT JOIN stk_fiyat sfA ON s.ID = sfA.STOKID AND sfA.TIP = 'A'
        LEFT JOIN grup g1 ON s.OZELGRUP1 = g1.ID
        LEFT JOIN grup g2 ON s.OZELGRUP2 = g2.ID
        LEFT JOIN grup g3 ON s.OZELGRUP3 = g3.ID
        LEFT JOIN grup g4 ON s.OZELGRUP4 = g4.ID
        LEFT JOIN grup g5 ON s.OZELGRUP5 = g5.ID
        LEFT JOIN grup g6 ON s.OZELGRUP6 = g6.ID
        LEFT JOIN grup g7 ON s.OZELGRUP7 = g7.ID
        LEFT JOIN grup g8 ON s.OZELGRUP8 = g8.ID
        LEFT JOIN grup g9 ON s.OZELGRUP9 = g9.ID
        LEFT JOIN grup g10 ON s.OZELGRUP10 = g10.ID
        $whereClause 
        GROUP BY s.ID
        ORDER BY STOK_MIKTARI DESC 
        LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        debugEcho("Ana SQL sorgusu:", $sql);
        
        try {
            // Performans ölçümlü sorgu ile ürünleri al
            $urunler = olcumluSorgu($db, $sql, $params);
            debugEcho("Bulunan ürün sayısı: " . count($urunler));
            
            // DEBUG: Ürün tekrarı sorunu için debug bilgileri
            echo '<div class="alert alert-info mt-3">';
            echo '<h5>Debug Bilgileri - Detaylı Arama Sorgu Sonuçları:</h5>';
            echo 'Toplam sonuç sayısı: ' . count($urunler) . '<br>';
            echo 'SQL Sorgusu: ' . htmlspecialchars($sql) . '<br><br>';
            
            echo '<table class="table table-sm table-bordered">';
            echo '<thead><tr><th>Sıra</th><th>ID</th><th>Kod</th><th>Ürün Kodu</th><th>Adı</th><th>ALIS_FIYAT ID</th></tr></thead>';
            echo '<tbody>';
            
            foreach ($urunler as $i => $urun) {
                echo '<tr>';
                echo '<td>' . ($i + 1) . '</td>';
                echo '<td>' . $urun['ID'] . '</td>';
                echo '<td>' . htmlspecialchars($urun['KOD']) . '</td>';
                echo '<td>' . htmlspecialchars($urun['OZELALAN10'] ?? '-') . '</td>';
                echo '<td>' . htmlspecialchars($urun['ADI']) . '</td>';
                echo '<td>' . (isset($urun['ALIS_FIYAT']) ? 'Var (' . $urun['ALIS_FIYAT'] . ')' : 'Yok') . '</td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
            echo '</div>';
            // DEBUG SONU
            
        } catch (Exception $e) {
            debugEcho("Ürün sorgulama hatası: " . $e->getMessage(), $sql);
            $urunler = [];
        }
        
        // STK_FIS_HAR tablosundan stok miktarlarını al
        if (count($urunler) > 0) {
            try {
                // Ürün ID'lerini al
                $urunIds = [];
                foreach ($urunler as $urun) {
                    $urunIds[] = $urun['ID'];
                }
                
                if (!empty($urunIds)) {
                    // Stok miktarlarını hesapla ve gerekirse kaydet
                    $stokMiktarlari = hesaplaVeKaydetStokMiktarlari($db, $urunIds);
                    
                    // Her ürün için stok miktarını ata
                    foreach ($urunler as &$urun) {
                        $urun['current_stock'] = isset($stokMiktarlari[$urun['ID']]) ? $stokMiktarlari[$urun['ID']] : 0;
                        if ($debug) {
                            debugEcho("Ürün ID " . $urun['ID'] . " için stok miktarı: " . $urun['current_stock']);
                        }
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
                        // Performans ölçümlü sorgu ile fiyat bilgilerini al
                        $fiyatSonuclar = olcumluSorgu($db, $fiyatSql);
                        $fiyatlar = [];
                        
                        // Key-Value formatına dönüştür
                        foreach($fiyatSonuclar as $row) {
                            $fiyatlar[$row['STOKID']] = $row['FIYAT'];
                        }
                        
                        // Her ürün için fiyat bilgisini güncelle
                        foreach ($urunler as &$urun) {
                            $urun['GUNCEL_FIYAT'] = isset($fiyatlar[$urun['ID']]) ? $fiyatlar[$urun['ID']] : (isset($urun['SATIS_FIYAT']) ? $urun['SATIS_FIYAT'] : 0);
                        }
                        
                        debugEcho("Satış fiyatları alındı:", $fiyatlar);
                    } catch (Exception $fiyatHata) {
                        debugEcho("Fiyat alma hatası: " . $fiyatHata->getMessage());
                    }
                } catch (PDOException $e) {
                    debugEcho("Stok miktarları hesaplanırken hata oluştu: " . $e->getMessage());
                }
            } catch (PDOException $e) {
                debugEcho("Stok miktarları hesaplanırken hata oluştu: " . $e->getMessage());
            }
        }
        
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
        debugEcho("HATA: " . $error);
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';

// AJAX isteği varsa, sadece ürün listesini döndür
if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    if (count($urunler) > 0) {
        foreach ($groupedProducts as $groupId => $products) {
            echo '<div class="product-data">';
            foreach ($products as $index => $urun) {
                echo '<tr data-urun-id="'.$urun['ID'].'" data-urun-kod="'.htmlspecialchars($urun['KOD']).'" data-urun-ad="'.htmlspecialchars($urun['ADI']).'" class="secim-satir">';
                echo '<td>'.htmlspecialchars($urun['KOD']).'</td>';
                echo '<td>';
                echo '<a href="urun_detay.php?id='.$urun['ID'].'">';
                echo html_entity_decode(htmlspecialchars($urun['ADI']));
                echo '</a>';
                echo '<span class="secili-isaret"><i class="fas fa-check-circle"></i></span>';
                echo '</td>';
                echo '<td class="text-end">';
                if (isset($urun['current_stock'])) {
                    $guncel_stok = (float)$urun['current_stock'];
                    $sistem_stok = isset($urun['MIKTAR']) ? (float)$urun['MIKTAR'] : 0;
                    
                    if ($sistem_stok != $guncel_stok) {
                        echo '<span class="text-success">'.number_format($guncel_stok, 2, ',', '.').'</span>';
                        echo '<br><small class="text-muted">Sistem: '.number_format($sistem_stok, 2, ',', '.').'</small>';
                    } else {
                        echo number_format($guncel_stok, 2, ',', '.');
                    }
                } else {
                    echo isset($urun['MIKTAR']) ? number_format($urun['MIKTAR'], 2, ',', '.') : '0,00';
                }
                echo '</td>';
                echo '<td class="text-end">';
                if (isset($urun['ALIS_FIYAT'])): ?>
                    <span class="fiyat-gizli">
                        <span class="gizli-deger">****</span>
                        <span class="gercek-deger">₺<?php echo number_format($urun['ALIS_FIYAT'], 2, ',', '.'); ?></span>
                    </span>
                <?php else: ?>
                    <span class="fiyat-gizli">
                        <span class="gizli-deger">****</span>
                        <span class="gercek-deger">₺0,00</span>
                    </span>
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
                <?php 
                if (isset($urun['OZELALAN1']) && !empty($urun['OZELALAN1'])) {
                    $oem_array = preg_split('/\r\n|\r|\n/', $urun['OZELALAN1'], -1, PREG_SPLIT_NO_EMPTY);
                    if (!empty($oem_array)) {
                        echo htmlspecialchars(trim($oem_array[0]));
                        if (count($oem_array) > 1) {
                            echo ' <span class="badge bg-secondary">+'.(count($oem_array) - 1).'</span>';
                        }
                    }
                } else {
                    echo '-';
                }
                ?>
                </td>
                <?php 
                echo '<td class="text-center">';
                echo '<div class="btn-group">';
                echo '<a href="urun_detay.php?id='.$urun['ID'].'" class="btn btn-sm btn-info" title="Detay"><i class="fas fa-eye"></i></a>';
                echo '<a href="urun_duzenle.php?id='.$urun['ID'].'" class="btn btn-sm btn-primary" title="Düzenle"><i class="fas fa-edit"></i></a>';
                echo '<a href="stok_hareketi_ekle.php?urun_id='.$urun['ID'].'" class="btn btn-sm btn-success" title="Stok Hareketi Ekle"><i class="fas fa-plus"></i></a>';
                echo '</div>';
                echo '</td>';
                echo '</tr>';
            }
            echo '</div>';
        }
        exit;
    }
}
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-1 mb-2 border-bottom">
    <h1 class="h3"><?php echo $pageTitle; ?></h1>
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
                    <div class="col-md-3">
                        <input type="text" class="form-control form-control-sm" id="urun_adi" name="urun_adi" value="<?php echo html_entity_decode(htmlspecialchars($urun_adi)); ?>" placeholder="Ürün Adı">
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="urun_kodu" name="urun_kodu" value="<?php echo isset($_GET['urun_kodu']) ? html_entity_decode(htmlspecialchars($_GET['urun_kodu'])) : ''; ?>" placeholder="Ürün Kodu">
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
            <button type="button" class="btn btn-sm btn-secondary" data-bs-toggle="modal" data-bs-target="#alanlarModal">
                <i class="fas fa-columns"></i> Alanları Özelleştir
            </button>
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
                
                <?php foreach ($groupedProducts as $groupId => $products): ?>
                    <?php debugEcho("Gösterilen grup: " . $groupId . ", Ürün sayısı: " . count($products)); ?>
                    <div class="product-group">
                        <div class="product-group-content">
                            <table class="table table-bordered table-hover table-compact" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <?php if ($goruntulenecek_alanlar['kod']): ?>
                                        <th data-alan="kod">Stok Kodu</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['urun_kodu']): ?>
                                        <th data-alan="urun_kodu">Ürün Kodu</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['adi']): ?>
                                        <th data-alan="adi">Ürün Adı</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['raf_kodu']): ?>
                                        <th data-alan="raf_kodu">Raf Kodu</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['ana_kategori']): ?>
                                        <th data-alan="ana_kategori">Ana Kategori</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['alt_kategori']): ?>
                                        <th data-alan="alt_kategori">Alt Kategori</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['urun_markasi']): ?>
                                        <th data-alan="urun_markasi">Ürün Markası</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['arac_markasi']): ?>
                                        <th data-alan="arac_markasi">Araç Markası</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['arac_modeli']): ?>
                                        <th data-alan="arac_modeli">Araç Modeli</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['arac_yil']): ?>
                                        <th data-alan="arac_yil">Araç Yılı</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['kasa_ismi']): ?>
                                        <th data-alan="kasa_ismi">Kasa İsmi</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['motor_kodu']): ?>
                                        <th data-alan="motor_kodu">Motor Kodu</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['tedarikci']): ?>
                                        <th data-alan="tedarikci">Tedarikçi</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['stok']): ?>
                                        <th data-alan="stok" class="text-end">Stok</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['alis_fiyat']): ?>
                                        <th data-alan="alis_fiyat" class="text-end">Alış Fiyatı</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['satis_fiyat']): ?>
                                        <th data-alan="satis_fiyat" class="text-end">Satış Fiyatı</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['oem']): ?>
                                        <th data-alan="oem">OEM No</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['birim']): ?>
                                        <th data-alan="birim">Birim</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['tip']): ?>
                                        <th data-alan="tip">Tip</th>
                                        <?php endif; ?>
                                        
                                        <?php if ($goruntulenecek_alanlar['barkod']): ?>
                                        <th data-alan="barkod">Barkod</th>
                                        <?php endif; ?>
                                        
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Tüm ürünleri döngüye al
                                    foreach ($products as $index => $urun):
                                    ?>
                                        <tr data-urun-id="<?php echo $urun['ID']; ?>" data-urun-kod="<?php echo htmlspecialchars($urun['KOD']); ?>" data-urun-ad="<?php echo htmlspecialchars($urun['ADI']); ?>" class="secim-satir">
                                            
                                            <?php if ($goruntulenecek_alanlar['kod']): ?>
                                            <td><?php echo htmlspecialchars($urun['KOD']); ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['urun_kodu']): ?>
                                            <td><?php echo isset($urun['URUN_KODU']) ? htmlspecialchars($urun['URUN_KODU']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['adi']): ?>
                                            <td>
                                                <a href="urun_detay.php?id=<?php echo $urun['ID']; ?>">
                                                    <?php echo html_entity_decode(htmlspecialchars($urun['ADI'])); ?>
                                                </a>
                                                <span class="secili-isaret"><i class="fas fa-check-circle"></i></span>
                                            </td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['raf_kodu']): ?>
                                            <td><?php echo isset($urun['RAF_KODU']) ? htmlspecialchars($urun['RAF_KODU']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['ana_kategori']): ?>
                                            <td><?php echo isset($urun['ANA_KATEGORI']) ? htmlspecialchars($urun['ANA_KATEGORI']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['alt_kategori']): ?>
                                            <td><?php echo isset($urun['ALT_KATEGORI']) ? htmlspecialchars($urun['ALT_KATEGORI']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['urun_markasi']): ?>
                                            <td><?php echo isset($urun['URUN_MARKASI']) ? htmlspecialchars($urun['URUN_MARKASI']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['arac_markasi']): ?>
                                            <td><?php echo isset($urun['ARAC_MARKASI']) ? htmlspecialchars($urun['ARAC_MARKASI']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['arac_modeli']): ?>
                                            <td><?php echo isset($urun['ARAC_MODELI']) ? htmlspecialchars($urun['ARAC_MODELI']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['arac_yil']): ?>
                                            <td><?php echo isset($urun['ARAC_YIL']) ? htmlspecialchars($urun['ARAC_YIL']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['kasa_ismi']): ?>
                                            <td><?php echo isset($urun['KASA_ISMI']) ? htmlspecialchars($urun['KASA_ISMI']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['motor_kodu']): ?>
                                            <td><?php echo isset($urun['MOTOR_KODU']) ? htmlspecialchars($urun['MOTOR_KODU']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['tedarikci']): ?>
                                            <td><?php echo isset($urun['TEDARIKCI']) ? htmlspecialchars($urun['TEDARIKCI']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['stok']): ?>
                                            <td class="text-end">
                                                <?php if (isset($urun['current_stock'])): ?>
                                                    <?php
                                                    $guncel_stok = (float)$urun['current_stock'];
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
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['alis_fiyat']): ?>
                                            <td class="text-end">
                                                <?php if (isset($urun['ALIS_FIYAT'])): ?>
                                                    <span class="fiyat-gizli">
                                                        <span class="gizli-deger">****</span>
                                                        <span class="gercek-deger">₺<?php echo number_format($urun['ALIS_FIYAT'], 2, ',', '.'); ?></span>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="fiyat-gizli">
                                                        <span class="gizli-deger">****</span>
                                                        <span class="gercek-deger">₺0,00</span>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['satis_fiyat']): ?>
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
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['oem']): ?>
                                            <td>
                                                <?php 
                                                if (isset($urun['OZELALAN1']) && !empty($urun['OZELALAN1'])):
                                                    $oem_array = preg_split('/\r\n|\r|\n/', $urun['OZELALAN1'], -1, PREG_SPLIT_NO_EMPTY);
                                                    if (!empty($oem_array)):
                                                        echo htmlspecialchars(trim($oem_array[0]));
                                                        if (count($oem_array) > 1):
                                                            echo ' <span class="badge bg-secondary">+' . (count($oem_array) - 1) . '</span>';
                                                        endif;
                                                    endif;
                                                else:
                                                    echo '-';
                                                endif;
                                                ?>
                                            </td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['birim']): ?>
                                            <td><?php echo isset($urun['BIRIM']) ? htmlspecialchars($urun['BIRIM']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['tip']): ?>
                                            <td><?php echo isset($urun['TIP']) ? htmlspecialchars($urun['TIP']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['barkod']): ?>
                                            <td><?php echo isset($urun['BARKOD']) ? htmlspecialchars($urun['BARKOD']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
                                            <?php if ($goruntulenecek_alanlar['marka']): ?>
                                            <td><?php echo isset($urun['MARKA']) ? htmlspecialchars($urun['MARKA']) : '-'; ?></td>
                                            <?php endif; ?>
                                            
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
                ?>
                <div id="loading-indicator" class="text-center mt-3 d-none">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Yükleniyor...</span>
                    </div>
                    <p>Daha fazla ürün yükleniyor...</p>
                </div>
                <div id="sonuc-bilgisi" class="text-center mt-3 text-muted small">
                    <p>Gösterilen: <?php echo min($offset + count($urunler), $toplam_kayit); ?> / <?php echo $toplam_kayit; ?></p>
                </div>
                <input type="hidden" id="current-page" value="<?php echo $page; ?>">
                <input type="hidden" id="total-pages" value="<?php echo $totalPages; ?>">
                <input type="hidden" id="total-records" value="<?php echo $toplam_kayit; ?>">
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Arama kriterlerinize uygun ürün bulunamadı.
            </div>
        <?php endif; ?>
    </div>
</div>
                                        <?php endif; ?>
                                        
<!-- Sağ Tıklama Menüsü -->
<div id="sag-tikla-menu">
    <ul>
        <li class="menu-baslik">İşlemler</li>
        <li id="sag-tikla-alis-irsaliye"><i class="fas fa-file-import text-primary"></i> Alış İrsaliyesi</li>
        <li id="sag-tikla-satis-irsaliye"><i class="fas fa-file-export text-success"></i> Satış İrsaliyesi</li>
        <li id="sag-tikla-alis-fatura"><i class="fas fa-file-invoice text-primary"></i> Alış Faturası</li>
        <li id="sag-tikla-satis-fatura"><i class="fas fa-file-invoice-dollar text-success"></i> Satış Faturası</li>
        <li id="sag-tikla-alis-siparis"><i class="fas fa-shopping-cart text-primary"></i> Alış Siparişi</li>
        <li id="sag-tikla-satis-siparis"><i class="fas fa-shopping-basket text-success"></i> Satış Siparişi</li>
        <li id="sag-tikla-secim-iptal"><i class="fas fa-times text-danger"></i> Seçimi İptal Et</li>
    </ul>
</div>

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

        // Ürün seçim işlemleri için
        const seciliUrunler = new Set();
        const sagTiklaMenu = document.getElementById('sag-tikla-menu');
        
        // Sayfada herhangi bir yere tıklandığında sağ tıklama menüsünü kapat
        document.addEventListener('click', function() {
            sagTiklaMenu.style.display = 'none';
        });
        
        // Tüm ürün satırlarına tıklama olayı ekle
        function initUrunSelection() {
            document.querySelectorAll('.secim-satir').forEach(satir => {
                // Zaten tanımlı tıklama olaylarını temizle
                const clone = satir.cloneNode(true);
                if (satir.parentNode) {
                    satir.parentNode.replaceChild(clone, satir);
                }
                
                // Yeni dinleyicileri ekle
                clone.addEventListener('click', urunToggleSelection);
                clone.addEventListener('contextmenu', urunRightClick);
            });
        }
        
        // Ürün seçme/seçimi kaldırma fonksiyonu
        function urunToggleSelection(e) {
            // Eğer tıklanan öğe link veya buton ise işlem yapmayı engelle
            if (e.target.tagName === 'A' || e.target.tagName === 'BUTTON' || 
                e.target.closest('a') || e.target.closest('button')) {
                return;
            }
            
            // Ürün ID'sini al
            const urunId = this.dataset.urunId;
            
            // Ürün zaten seçili mi kontrol et
            if (seciliUrunler.has(urunId)) {
                // Seçimi kaldır
                seciliUrunler.delete(urunId);
                this.classList.remove('secili-urun');
                console.log('Ürün seçimi kaldırıldı:', urunId);
            } else {
                // Ürünü seç
                seciliUrunler.add(urunId);
                this.classList.add('secili-urun');
                console.log('Ürün seçildi:', urunId);
            }
        }
        
        // Sağ tıklama işlevi
        function urunRightClick(e) {
            e.preventDefault(); // Varsayılan sağ tıklama menüsünü engelle
            
            // Eğer hiç ürün seçili değilse, tıklanan ürünü seç
            if (seciliUrunler.size === 0) {
                const urunId = this.dataset.urunId;
                seciliUrunler.add(urunId);
                this.classList.add('secili-urun');
            }
            
            // Eğer seçili ürün yoksa menüyü gösterme
            if (seciliUrunler.size === 0) return;
            
            // Menüyü tıklanan konuma yerleştir
            sagTiklaMenu.style.display = 'block';
            sagTiklaMenu.style.left = e.pageX + 'px';
            sagTiklaMenu.style.top = e.pageY + 'px';
            
            // Eğer menü taşarsa, konumunu ayarla
            const menuRect = sagTiklaMenu.getBoundingClientRect();
            const windowWidth = window.innerWidth;
            const windowHeight = window.innerHeight;
            
            if (menuRect.right > windowWidth) {
                sagTiklaMenu.style.left = (e.pageX - menuRect.width) + 'px';
            }
            
            if (menuRect.bottom > windowHeight) {
                sagTiklaMenu.style.top = (e.pageY - menuRect.height) + 'px';
            }
        }
        
        // İlk yükleme sırasında tüm ürünlere olay dinleyicileri ekle
        initUrunSelection();
        
        // AJAX ile yeni ürünler yüklendiğinde çağrılacak fonksiyon
        function addEventListenersToRows() {
            initUrunSelection();
        }
        
        // Sağ tıklama menüsü butonlarına olay ekle
        document.getElementById('sag-tikla-alis-irsaliye').addEventListener('click', function() {
            yonlendirIslem('../irsaliye/alis_irsaliyesi.php', 'alis', Array.from(seciliUrunler));
        });
        
        document.getElementById('sag-tikla-satis-irsaliye').addEventListener('click', function() {
            yonlendirIslem('irsaliye_ekle.php', 'satis', Array.from(seciliUrunler));
        });
        
        document.getElementById('sag-tikla-alis-fatura').addEventListener('click', function() {
            yonlendirIslem('fatura_ekle.php', 'alis', Array.from(seciliUrunler));
        });
        
        document.getElementById('sag-tikla-satis-fatura').addEventListener('click', function() {
            yonlendirIslem('fatura_ekle.php', 'satis', Array.from(seciliUrunler));
        });
        
        document.getElementById('sag-tikla-alis-siparis').addEventListener('click', function() {
            yonlendirIslem('siparis_ekle.php', 'alis', Array.from(seciliUrunler));
        });
        
        document.getElementById('sag-tikla-satis-siparis').addEventListener('click', function() {
            yonlendirIslem('siparis_ekle.php', 'satis', Array.from(seciliUrunler));
        });
        
        document.getElementById('sag-tikla-secim-iptal').addEventListener('click', function() {
            seciliUrunler.clear();
            document.querySelectorAll('.secim-satir.secili-urun').forEach(satir => {
                satir.classList.remove('secili-urun');
            });
            sagTiklaMenu.style.display = 'none';
        });
        
        // İşlem sayfasına yönlendirme fonksiyonu
        function yonlendirIslem(sayfa, tur, urunIdleri) {
            if (urunIdleri.length === 0) {
                alert('Lütfen en az bir ürün seçin!');
                return;
            }
            
            // URL oluştur
            const params = new URLSearchParams();
            params.append('tur', tur);
            urunIdleri.forEach(id => params.append('urun_id[]', id));
            
            // Yönlendir
            window.location.href = sayfa + '?' + params.toString();
        }
        
        // Sonsuz kaydırma özelliği
        function initInfiniteScroll() {
            const loadingIndicator = document.getElementById('loading-indicator');
            const currentPage = document.getElementById('current-page');
            const totalPages = document.getElementById('total-pages');
            let isLoading = false;
            
            // Kaydırma olayını dinle
            window.addEventListener('scroll', function() {
                // Sayfa sonuna yaklaşıldı mı kontrol et
                if (window.innerHeight + window.scrollY >= document.body.offsetHeight - 500) {
                    // Eğer yükleme yapmıyorsak ve daha fazla sayfa varsa
                    if (!isLoading && parseInt(currentPage.value) < parseInt(totalPages.value)) {
                        loadMoreProducts();
                    }
                }
            });
            
            // Daha fazla ürün yükle
            function loadMoreProducts() {
                isLoading = true;
                loadingIndicator.classList.remove('d-none');
                
                const nextPage = parseInt(currentPage.value) + 1;
                
                // Mevcut URL'yi al ve sayfa parametresini güncelle
                const url = new URL(window.location.href);
                url.searchParams.set('page', nextPage);
                url.searchParams.set('ajax', '1');
                
                // AJAX isteği gönder
                fetch(url.toString())
                    .then(response => response.text())
                    .then(html => {
                        if (html.trim() !== '') {
                            // Geçici bir div oluştur ve HTML içeriğini içine koy
                            const tempDiv = document.createElement('div');
                            tempDiv.innerHTML = html;
                            
                            // Gelen satırları al
                            const newRows = tempDiv.querySelectorAll('tr');
                            
                            // Mevcut tablo gövdesini bul
                            const tbody = document.querySelector('.product-group table tbody');
                            
                            // Yeni satırları ekle
                            newRows.forEach(row => {
                                tbody.appendChild(row);
                            });
                            
                            // Yeni satırlara olay dinleyicileri ekle
                            addEventListenersToRows();
                            
                            // Sonuç bilgisini güncelle
                            const totalRecords = document.getElementById('total-records').value;
                            const sonucBilgisi = document.getElementById('sonuc-bilgisi');
                            const currentlyShown = Math.min((nextPage * 50), totalRecords);
                            sonucBilgisi.innerHTML = `<p>Gösterilen: ${currentlyShown} / ${totalRecords}</p>`;
                            
                            // Sayfayı güncelle
                            currentPage.value = nextPage;
                        }
                        
                        isLoading = false;
                        loadingIndicator.classList.add('d-none');
                    })
                    .catch(error => {
                        console.error('Daha fazla ürün yüklenirken hata oluştu:', error);
                        isLoading = false;
                        loadingIndicator.classList.add('d-none');
                    });
            }
        }
        
        // Eğer sayfa parametreleri varsa sonsuz kaydırmayı başlat
        if (document.getElementById('current-page')) {
            initInfiniteScroll();
        }
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
            ['Stok Kodu', 'Ürün Adı', 'Stok', 'Alış Fiyatı', 'Satış Fiyatı', 'OEM No'].forEach(header => {
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
                        
                        // Alış fiyatı hücresinde gerçek değeri çıkartalım (Excel'de gizli değer gelsin)
                        if (cellIndex === 3) { // Alış fiyatı sütunu
                            newCell.textContent = "****";
                        }
                        
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
// Performans raporu göster
if ($performans_izleme) {
    performansRaporuGoster();
}

// Alt kısmı dahil et
include_once '../../includes/footer.php'; 
?> 

<!-- Alanları Özelleştirme Modalı -->
<div class="modal fade" id="alanlarModal" tabindex="-1" aria-labelledby="alanlarModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="alanlarModalLabel">Görüntülenecek Alanları Seçin</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_kod" name="alan_kod" value="1" <?php echo $goruntulenecek_alanlar['kod'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_kod">Stok Kodu</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_adi" name="alan_adi" value="1" <?php echo $goruntulenecek_alanlar['adi'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_adi">Ürün Adı</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_stok" name="alan_stok" value="1" <?php echo $goruntulenecek_alanlar['stok'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_stok">Stok Miktarı</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_alis_fiyat" name="alan_alis_fiyat" value="1" <?php echo $goruntulenecek_alanlar['alis_fiyat'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_alis_fiyat">Alış Fiyatı</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_satis_fiyat" name="alan_satis_fiyat" value="1" <?php echo $goruntulenecek_alanlar['satis_fiyat'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_satis_fiyat">Satış Fiyatı</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_oem" name="alan_oem" value="1" <?php echo $goruntulenecek_alanlar['oem'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_oem">OEM No</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_birim" name="alan_birim" value="1" <?php echo $goruntulenecek_alanlar['birim'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_birim">Birim</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_tip" name="alan_tip" value="1" <?php echo $goruntulenecek_alanlar['tip'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_tip">Tip</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_barkod" name="alan_barkod" value="1" <?php echo $goruntulenecek_alanlar['barkod'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_barkod">Barkod</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_urun_kodu" name="alan_urun_kodu" value="1" <?php echo $goruntulenecek_alanlar['urun_kodu'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_urun_kodu">Ürün Kodu</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_raf_kodu" name="alan_raf_kodu" value="1" <?php echo $goruntulenecek_alanlar['raf_kodu'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_raf_kodu">Raf Kodu</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_ana_kategori" name="alan_ana_kategori" value="1" <?php echo $goruntulenecek_alanlar['ana_kategori'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_ana_kategori">Ana Kategori</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_alt_kategori" name="alan_alt_kategori" value="1" <?php echo $goruntulenecek_alanlar['alt_kategori'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_alt_kategori">Alt Kategori</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_urun_markasi" name="alan_urun_markasi" value="1" <?php echo $goruntulenecek_alanlar['urun_markasi'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_urun_markasi">Ürün Markası</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_arac_markasi" name="alan_arac_markasi" value="1" <?php echo $goruntulenecek_alanlar['arac_markasi'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_arac_markasi">Araç Markası</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_arac_modeli" name="alan_arac_modeli" value="1" <?php echo $goruntulenecek_alanlar['arac_modeli'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_arac_modeli">Araç Modeli</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_arac_yil" name="alan_arac_yil" value="1" <?php echo $goruntulenecek_alanlar['arac_yil'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_arac_yil">Araç Yılı</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_kasa_ismi" name="alan_kasa_ismi" value="1" <?php echo $goruntulenecek_alanlar['kasa_ismi'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_kasa_ismi">Kasa İsmi</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_motor_kodu" name="alan_motor_kodu" value="1" <?php echo $goruntulenecek_alanlar['motor_kodu'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_motor_kodu">Motor Kodu</label>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="form-check">
                            <input class="form-check-input alan-secim" type="checkbox" id="alan_tedarikci" name="alan_tedarikci" value="1" <?php echo $goruntulenecek_alanlar['tedarikci'] ? 'checked' : ''; ?>>
                            <label class="form-check-label" for="alan_tedarikci">Tedarikçi</label>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                <button type="button" class="btn btn-primary" id="alanKaydet">Kaydet</button>
            </div>
        </div>
    </div>
</div>

<link rel="stylesheet" href="assets/css/urun_arama.css">

<!-- Dinamik Alan Özelleştirme JavaScript Kodu -->
<script src="assets/js/urun_arama_alan.js"></script>

<?php
// Debug işlemlerinin ve performans raporlarının sonuçları
if (isset($debug) && $debug) {
    debugEcho("Debug modu aktif! Bu mesaj görüntüleniyorsa debug çalışıyor demektir.");
}

// Performans raporunu göster
if (isset($performans_izleme) && $performans_izleme) {
    performansRaporuGoster();
}
?>

</body>
</html>