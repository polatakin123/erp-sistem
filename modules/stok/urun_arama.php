<?php
/**
 * ERP Sistem - Ürün Arama Sayfası
 * 
 * Bu dosya ürünleri detaylı arama ve filtreleme işlemlerini gerçekleştirir.
 */

 require_once '../../config/helpers.php';

// Yukarıdaki require ifadesinde bir sorun varsa, guvenli_sorgu fonksiyonunu doğrudan tanımlayalım
if (!function_exists('guvenli_sorgu')) {
    /**
     * Zaman aşımı kontrollü güvenli sorgu çalıştırma
     * @param PDO $db Veritabanı bağlantısı
     * @param string $sql SQL sorgusu
     * @param array $params Sorgu parametreleri
     * @param int $timeout Zaman aşımı süresi (saniye)
     * @return PDOStatement Sorgu sonucu
     */
    function guvenli_sorgu($db, $sql, $params = [], $timeout = 5) {
        // Önceki zaman aşımı ayarını yedekle
        $db->query("SET @old_max_execution_time = @@max_execution_time");
        
        // Yeni zaman aşımı ayarını belirle (milisaniye cinsinden)
        $db->query("SET SESSION max_execution_time=" . ($timeout * 1000));
        
        try {
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            
            // Zaman aşımı ayarını geri yükle
            $db->query("SET SESSION max_execution_time = @old_max_execution_time");
            
            return $stmt;
        } catch (PDOException $e) {
            // Zaman aşımı ayarını geri yükle
            $db->query("SET SESSION max_execution_time = @old_max_execution_time");
            
            // Hata kodu kontrol et (veritabanı kilitleme/zaman aşımı kodları)
            if ($e->getCode() == 'HY000' || $e->getCode() == 'S1T00') {
                // Zaman aşımı hatası
                error_log("Sorgu zaman aşımı hatası: " . $e->getMessage() . " - SQL: " . $sql);
                throw new Exception("Sorgu zaman aşımına uğradı. Lütfen daha sonra tekrar deneyin.");
            } else {
                // Diğer hatalar
                error_log("Veritabanı hatası: " . $e->getMessage() . " - SQL: " . $sql);
                throw $e;
            }
        }
    }
}

// db_islem_dene fonksiyonu da eksik olabilir
if (!function_exists('db_islem_dene')) {
    /**
     * Döngü kontrolü ile veritabanı işlemi
     * @param PDO $db Veritabanı bağlantısı
     * @param callable $callback Çalıştırılacak fonksiyon
     * @param int $maxTries Maksimum deneme sayısı
     * @return mixed Fonksiyonun dönüş değeri
     */
    function db_islem_dene($db, $callback, $maxTries = 3) {
        $tries = 0;
        $lastError = null;
        
        while ($tries < $maxTries) {
            try {
                return $callback($db);
            } catch (PDOException $e) {
                $tries++;
                $lastError = $e;
                
                // Deadlock hatası ise yeniden dene
                if ($e->getCode() == 40001 || $e->getCode() == 1213) {
                    // Biraz bekle ve yeniden dene
                    usleep(rand(500000, 1000000)); // 0.5-1 saniye bekle
                    continue;
                } else {
                    // Diğer hataları hemen fırlat
                    throw $e;
                }
            }
        }
        
        // Maksimum deneme sayısına ulaşıldı
        throw new Exception("Veritabanı işlemi $maxTries deneme sonrasında başarısız oldu: " . $lastError->getMessage());
    }
}

// Debug modu aktif
$debug = false; // Hata ayıklama modu kapalı
ini_set('display_errors', 0); // Hata mesajlarını gösterme
error_reporting(0); // Hata raporlamayı kapat

// Oturum başlat
session_start();

// Debug fonksiyonu
function debugEcho($message, $variable = null) {
    global $debug;
    if ($debug) {
        echo '<div style="background-color:#f8d7da; border:1px solid #f5c6cb; color:#721c24; padding:10px; margin:10px 0; border-radius:5px;">';
        echo '<strong>DEBUG:</strong> ' . htmlspecialchars($message);
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

/**
 * Ürün ID'leri için stok miktarlarını hesaplar ve STK_URUN_MIKTAR tablosuna kaydeder
 * 
 * @param PDO $db Veritabanı bağlantısı
 * @param array $urunIds Ürün ID'leri dizisi
 * @return array Ürün ID'leri ve stok miktarları (key-value array)
 */
function hesaplaVeKaydetStokMiktarlari($db, $urunIds) {
    global $debug; // Debug değişkenini küresel değişken olarak tanımla
    
    // Debug modu zorlama satırını kaldırıyorum
    
    if (empty($urunIds)) {
        debugEcho("Boş ürün ID'leri dizisi, hesaplama yapılmayacak.");
        return [];
    }
    
    // Zaman aşımı kontrolü başlat
    $startTime = microtime(true);
    $timeoutSeconds = 5; // 5 saniye sonra zaman aşımı
    
    // İşlemler arasında küçük gecikmeler ekleyerek veritabanının nefes almasına izin ver
    $sleep = function($ms = 100) {
        usleep($ms * 1000);
    };
    
    try {
        debugEcho("Stok miktarları hesaplanıyor, ürün sayısı: " . count($urunIds));
        $stokMiktarlari = [];
        $urunChunks = array_chunk($urunIds, 20); // 20'şer ürün ID'sine böl
        
        foreach ($urunChunks as $chunk) {
            // Zaman aşımı kontrolü
            if (microtime(true) - $startTime > $timeoutSeconds) {
                throw new Exception("Stok miktarları hesaplanırken zaman aşımı oluştu.");
            }
            
            $queryParams = implode(',', $chunk);
            
            // Mevcut stok miktarlarını al
            $sql = "SELECT URUN_ID, MIKTAR FROM STK_URUN_MIKTAR WHERE URUN_ID IN ($queryParams)";
            if ($debug) {
                debugEcho("Stok miktarı sorgusu:", $sql);
            }
            
            try {
                // Doğrudan veritabanı sorgusu yap
                $stmt = $db->prepare($sql);
                $stmt->execute();
                $stokSonuclari = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
                
                if ($debug) {
                    debugEcho("Stok sonuçları:", $stokSonuclari);
                    
                    // Her ürün için stok tablosunu kontrol et
                    foreach ($chunk as $urunId) {
                        $detaySql = "SELECT * FROM STK_URUN_MIKTAR WHERE URUN_ID = $urunId";
                        $detayStmt = $db->query($detaySql);
                        $detaySonuc = $detayStmt->fetchAll(PDO::FETCH_ASSOC);
                        debugEcho("Ürün $urunId stok miktarı detayları:", $detaySonuc);
                        
                        // Stok hareketlerini de kontrol et
                        $hareketSql = "SELECT * FROM STK_FIS_HAR WHERE STOK_ID = $urunId ORDER BY ID DESC LIMIT 5";
                        $hareketStmt = $db->query($hareketSql);
                        $hareketSonuc = $hareketStmt->fetchAll(PDO::FETCH_ASSOC);
                        debugEcho("Ürün $urunId son 5 stok hareketi:", $hareketSonuc);
                    }
                }
            } catch (Exception $e) {
                debugEcho("Stok miktarı sorgu hatası:", $e->getMessage());
                $stokSonuclari = [];
            }
            
            foreach ($chunk as $urunId) {
                $stokMiktarlari[$urunId] = isset($stokSonuclari[$urunId]) ? $stokSonuclari[$urunId] : 0;
            }
            
            $sleep(); // 100ms bekle
        }
        
        // Satış fiyatlarını al
        $fiyatSql = "SELECT STOKID, FIYAT FROM STK_FIYAT WHERE TIP = 'S' AND STOKID IN (" . implode(',', $urunIds) . ")";
        if ($debug) {
            debugEcho("Satış fiyatı sorgusu:", $fiyatSql);
        }
        
        try {
            // Doğrudan veritabanı sorgusu yap
            $fiyatStmt = $db->prepare($fiyatSql);
            $fiyatStmt->execute();
            $fiyatlar = $fiyatStmt->fetchAll(PDO::FETCH_KEY_PAIR);
            debugEcho("Bulunan satış fiyatları:", $fiyatlar);
            
            // Fiyat detaylarını kontrol et
            foreach ($urunIds as $urunId) {
                $fiyatDetaySql = "SELECT * FROM STK_FIYAT WHERE STOKID = $urunId";
                $fiyatDetayStmt = $db->query($fiyatDetaySql);
                $fiyatDetaySonuc = $fiyatDetayStmt->fetchAll(PDO::FETCH_ASSOC);
                debugEcho("Ürün $urunId fiyat detayları:", $fiyatDetaySonuc);
            }
        } catch (Exception $e) {
            debugEcho("Fiyat sorgulama hatası:", $e->getMessage());
            $fiyatlar = [];
        }
        
        // Sonuçları debug et
        if ($debug) {
            debugEcho("Hesaplanan stok miktarları:", $stokMiktarlari);
        }
        
        return $stokMiktarlari;
    } catch (Exception $e) {
        error_log("Stok miktarları hesaplanırken hata: " . $e->getMessage());
        if ($debug) {
            debugEcho("Stok miktarları hesaplanırken hata:", $e->getMessage());
        }
        // Boş değer döndür, işlemi durdurmak yerine basitçe ilerle
        return [];
    }
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
        
        foreach ($arama_kelimeleri as $kelime) {
            if (strlen($kelime) < 2) continue; // Çok kısa kelimeleri atla
            
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($kelime) . '%';
            
            // Büyük harfe dönüştürülerek arama yapalım
            $where_parts[] = "(" . sqlTurkceUpper("s.KOD") . " LIKE ? OR " . sqlTurkceUpper("s.ADI") . " LIKE ?)";
            
            $params[] = $search_param;
            $params[] = $search_param;
            
            debugEcho("Kelime: $kelime, Arama parametresi: $search_param");
        }
        
        // Hiç geçerli kelime yoksa
        if (empty($where_parts)) {
            // Türkçe karakterleri dönüştür ve büyük harfe çevir
            $search_param = '%' . searchTermPrepare($arama) . '%';
            
            // Büyük harfe dönüştürülerek arama yapalım
            $where_parts[] = "(" . sqlTurkceUpper("s.KOD") . " LIKE ? OR " . sqlTurkceUpper("s.ADI") . " LIKE ?)";
            
            $params[] = $search_param;
            $params[] = $search_param;
            
            debugEcho("Tam arama: $arama, Arama parametresi: $search_param");
        }
        
        // WHERE koşulunu oluştur - tüm kelimeler için AND bağlacı kullan
        $where_clause = implode(' AND ', $where_parts);
        debugEcho("WHERE koşulu:", $where_clause);
        debugEcho("Sorgu parametreleri:", $params);
        
        // Toplam kayıt sayısını al
        $countSql = "SELECT COUNT(*) FROM stok s WHERE " . $where_clause;
        debugEcho("Sayım SQL sorgusu:", $countSql);
        
        try {
            // Doğrudan veritabanı sorgusu yap
            $countStmt = $db->prepare($countSql);
            $countStmt->execute($params);
            $toplam_kayit = $countStmt->fetchColumn();
            debugEcho("Toplam kayıt sayısı: " . $toplam_kayit);
        } catch (Exception $e) {
            error_log("Kayıt sayma hatası: " . $e->getMessage());
            debugEcho("Kayıt sayma hatası: " . $e->getMessage());
            $toplam_kayit = 0; // Hata durumunda varsayılan değer
        }
        
        // Ana sorguyu hazırla - LIMIT ve OFFSET sorununu düzelt
        $sql = "SELECT s.*, sfA.FIYAT as ALIS_FIYAT FROM stok s 
                LEFT JOIN STK_URUN_MIKTAR um ON s.ID = um.URUN_ID 
                LEFT JOIN stk_fiyat sfA ON s.ID = sfA.STOKID AND sfA.TIP = 'A'
                WHERE " . $where_clause . " 
                ORDER BY CASE WHEN um.MIKTAR IS NULL THEN 0 ELSE 1 END DESC, um.MIKTAR DESC 
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
        debugEcho("Ana SQL sorgusu:", $sql);
        
        try {
            // Doğrudan veritabanı sorgusu yap
            $stmt = $db->prepare($sql);
            $stmt->execute($params);
            $urunler = $stmt->fetchAll();
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
                        // Doğrudan veritabanı sorgusu yap
                        $miktarStmt = $db->prepare($miktarSql);
                        $miktarStmt->execute();
                        $miktarlar = $miktarStmt->fetchAll(PDO::FETCH_KEY_PAIR);
                        debugEcho("Bulunan stok miktarları:", $miktarlar);
                    } catch (Exception $e) {
                        debugEcho("Miktar sorgulama hatası:", $e->getMessage());
                        $miktarlar = [];
                    }
                    
                    // Satış fiyatlarını al
                    $fiyatSql = "SELECT STOKID, FIYAT FROM STK_FIYAT WHERE TIP = 'S' AND STOKID IN (" . implode(',', $urunIds) . ")";
                    debugEcho("Fiyat SQL sorgusu:", $fiyatSql);
                    try {
                        // Doğrudan veritabanı sorgusu yap
                        $fiyatStmt = $db->prepare($fiyatSql);
                        $fiyatStmt->execute();
                        $fiyatlar = $fiyatStmt->fetchAll(PDO::FETCH_KEY_PAIR);
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
        
        $countStmt = $db->prepare($countSql);
        $countStmt->execute($countParams);
        debugEcho("Execute parametreleri (COUNT):", $countParams);
        
        $toplam_kayit = $countStmt->fetchColumn();
        debugEcho("Toplam kayıt sayısı: " . $toplam_kayit);
        
        // Ana sorguyu hazırla
        $sql = "SELECT s.*, sfA.FIYAT as ALIS_FIYAT FROM stok s 
                LEFT JOIN STK_URUN_MIKTAR um ON s.ID = um.URUN_ID 
                LEFT JOIN stk_fiyat sfA ON s.ID = sfA.STOKID AND sfA.TIP = 'A'
                $whereClause 
                ORDER BY CASE WHEN um.MIKTAR IS NULL THEN 0 ELSE 1 END DESC, um.MIKTAR DESC 
                LIMIT " . (int)$limit . " OFFSET " . (int)$offset;
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
                    
                    $fiyatStmt = $db->query($fiyatSql);
                    $fiyatlar = [];
                    
                    while ($fiyatRow = $fiyatStmt->fetch(PDO::FETCH_ASSOC)) {
                        $fiyatlar[$fiyatRow['STOKID']] = $fiyatRow['FIYAT'];
                    }
                    
                    // Her ürün için fiyat bilgisini güncelle
                    foreach ($urunler as &$urun) {
                        $urun['GUNCEL_FIYAT'] = isset($fiyatlar[$urun['ID']]) ? $fiyatlar[$urun['ID']] : (isset($urun['SATIS_FIYAT']) ? $urun['SATIS_FIYAT'] : 0);
                    }
                    
                    debugEcho("Satış fiyatları alındı:", $fiyatlar);
                } catch (PDOException $fiyatHata) {
                    debugEcho("Fiyat alma hatası: " . $fiyatHata->getMessage());
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
                    .fiyat-gizli {
                        cursor: pointer;
                    }
                    .fiyat-gizli .gizli-deger {
                        display: inline-block;
                    }
                    .fiyat-gizli .gercek-deger {
                        display: none;
                        position: absolute;
                        background: #333;
                        color: white;
                        padding: 5px;
                        border-radius: 3px;
                        z-index: 100;
                        white-space: nowrap;
                    }
                    .fiyat-gizli:hover .gercek-deger {
                        display: block;
                    }
                    
                    /* Ürün seçimi için stillendirme */
                    tr.secili-urun {
                        background-color: #e8f4ff !important;
                    }
                    
                    /* Seçili ürün işareti */
                    .secili-isaret {
                        display: none;
                        color: #28a745;
                        margin-left: 5px;
                    }
                    
                    tr.secili-urun .secili-isaret {
                        display: inline-block;
                    }
                    
                    /* Sağ tıklama menüsü için stil */
                    #sag-tikla-menu {
                        position: fixed;
                        display: none;
                        background-color: #fff;
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        box-shadow: 0 2px 5px rgba(0,0,0,0.2);
                        z-index: 1000;
                        overflow: hidden;
                    }
                    
                    #sag-tikla-menu ul {
                        list-style: none;
                        padding: 0;
                        margin: 0;
                    }
                    
                    #sag-tikla-menu li {
                        padding: 8px 12px;
                        cursor: pointer;
                        border-bottom: 1px solid #f0f0f0;
                    }
                    
                    #sag-tikla-menu li:hover {
                        background-color: #f8f9fa;
                    }
                    
                    #sag-tikla-menu .menu-baslik {
                        font-weight: bold;
                        background-color: #f0f0f0;
                        color: #333;
                        padding: 8px 12px;
                    }
                    
                    #sag-tikla-menu li i {
                        margin-right: 8px;
                        width: 16px;
                        text-align: center;
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
                                        <th class="text-end">Stok</th>
                                        <th class="text-end">Alış Fiyatı</th>
                                        <th class="text-end">Satış Fiyatı</th>
                                        <th>OEM No</th>
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    // Tüm ürünleri döngüye al
                                    foreach ($products as $index => $urun):
                                    ?>
                                        <tr data-urun-id="<?php echo $urun['ID']; ?>" data-urun-kod="<?php echo htmlspecialchars($urun['KOD']); ?>" data-urun-ad="<?php echo htmlspecialchars($urun['ADI']); ?>" class="secim-satir">
                                            <td><?php echo htmlspecialchars($urun['KOD']); ?></td>
                                            <td>
                                                <a href="urun_detay.php?id=<?php echo $urun['ID']; ?>">
                                                    <?php echo html_entity_decode(htmlspecialchars($urun['ADI'])); ?>
                                                </a>
                                                <span class="secili-isaret"><i class="fas fa-check-circle"></i></span>
                                            </td>
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
// Alt kısmı dahil et
include_once '../../includes/footer.php'; 
?> 