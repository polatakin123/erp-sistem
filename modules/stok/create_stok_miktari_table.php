<?php
/**
 * Stok Miktarı Tablosu Oluşturma ve Doldurma
 * 
 * Bu script ürünlerin stok miktarlarını daha hızlı sorgulamak için 
 * yeni bir tablo oluşturur ve mevcut stok hareketlerinden hesaplayarak doldurur.
 */

// Oturum süresini uzat (6 saat) - session_start() çağrılmadan ÖNCE ayarla
ini_set('session.gc_maxlifetime', 21600);
session_set_cookie_params(21600);

// Şimdi oturumu başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// PHP zaman aşımı süresini artır (30 dakika)
set_time_limit(1800);

// İşlem bellek limitini artır
ini_set('memory_limit', '512M');

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Session üzerinden işlem devam mı kontrol et
$islemTamamlandi = false;
$offset = isset($_SESSION['stok_miktar_offset']) ? $_SESSION['stok_miktar_offset'] : 0;
$basariliSayisi = isset($_SESSION['stok_miktar_basarili']) ? $_SESSION['stok_miktar_basarili'] : 0;
$islemSayisi = isset($_SESSION['stok_miktar_islem']) ? $_SESSION['stok_miktar_islem'] : 0;
$urunSayisi = isset($_SESSION['stok_miktar_toplam']) ? $_SESSION['stok_miktar_toplam'] : 0;

// Eğer reset parametresi varsa, işlemi sıfırla
if (isset($_GET['reset'])) {
    $offset = 0;
    $basariliSayisi = 0;
    $islemSayisi = 0;
    $urunSayisi = 0;
    unset($_SESSION['stok_miktar_offset']);
    unset($_SESSION['stok_miktar_basarili']);
    unset($_SESSION['stok_miktar_islem']);
    unset($_SESSION['stok_miktar_toplam']);
}

// Sayfa başlığı
$pageTitle = "Stok Miktarı Tablosu Oluşturma";

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Stok Miktarı Tablosu Oluşturma</h1>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">İşlem Durumu</h6>
        <div>
            <a href="?reset=1" class="btn btn-danger btn-sm">İşlemi Sıfırla</a>
            <a href="index.php" class="btn btn-primary btn-sm">Stok Modülüne Dön</a>
        </div>
    </div>
    <div class="card-body">
        <?php
        try {
            // Tablo var mı kontrol et
            $checkTable = $db->query("SHOW TABLES LIKE 'STK_URUN_MIKTAR'");
            $tableExists = $checkTable->rowCount() > 0;
            
            if (!$tableExists) {
                echo '<div class="alert alert-info">STK_URUN_MIKTAR tablosu oluşturuluyor...</div>';
                
                // Tabloyu oluştur
                $createTableSQL = "CREATE TABLE STK_URUN_MIKTAR (
                    ID INT AUTO_INCREMENT PRIMARY KEY,
                    URUN_ID INT NOT NULL,
                    MIKTAR DECIMAL(10,2) NOT NULL DEFAULT 0,
                    SON_GUNCELLEME DATETIME NOT NULL,
                    UNIQUE KEY (URUN_ID)
                )";
                
                $db->exec($createTableSQL);
                echo '<div class="alert alert-success">STK_URUN_MIKTAR tablosu başarıyla oluşturuldu.</div>';
            } else {
                echo '<div class="alert alert-warning">STK_URUN_MIKTAR tablosu zaten mevcut.</div>';
            }
            
            // Hata ayıklama için PDO'yu exception moduna geçirelim
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // İlk kez çalışıyorsa toplam ürün sayısını al ve tabloyu temizle
            if ($offset == 0) {
                // Daha önce bir işlem var mı diye veritabanından kontrol et
                try {
                    $checkProcess = $db->query("SELECT * FROM STK_ISLEM_DURUM WHERE ISLEM_TIPI = 'STOK_MIKTAR' AND DURUM = 'DEVAM' LIMIT 1");
                    $existingProcess = $checkProcess->fetch(PDO::FETCH_ASSOC);
                    
                    if ($existingProcess) {
                        // Devam eden bir işlem var, bilgileri al
                        $offset = $existingProcess['MEVCUT_KONUM'];
                        $basariliSayisi = $existingProcess['BASARILI_SAYISI'];
                        $islemSayisi = $existingProcess['ISLEM_SAYISI'];
                        $urunSayisi = $existingProcess['TOPLAM_KAYIT'];
                        
                        // Session'a kaydet
                        $_SESSION['stok_miktar_offset'] = $offset;
                        $_SESSION['stok_miktar_basarili'] = $basariliSayisi;
                        $_SESSION['stok_miktar_islem'] = $islemSayisi;
                        $_SESSION['stok_miktar_toplam'] = $urunSayisi;
                        
                        echo '<div class="alert alert-warning">Devam eden bir işlem bulundu. Kaldığı yerden devam ediliyor. Tamamlanan: ' . $islemSayisi . '/' . $urunSayisi . ' ürün.</div>';
                    } else {
                        // Toplam ürün sayısını al
                        echo '<div class="alert alert-info">Ürünleri sayıyoruz...</div>';
                        $countSQL = "SELECT COUNT(*) as total FROM stok";
                        $countStmt = $db->query($countSQL);
                        $urunSayisi = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                        echo '<div class="alert alert-success">Toplam ' . $urunSayisi . ' ürün bulundu.</div>';
                        
                        // Tabloyu temizle
                        echo '<div class="alert alert-info">STK_URUN_MIKTAR tablosu temizleniyor...</div>';
                        $db->exec("TRUNCATE TABLE STK_URUN_MIKTAR");
                        echo '<div class="alert alert-success">Tablo başarıyla temizlendi.</div>';
                        
                        // İşlem durum tablosunu kontrol et
                        try {
                            $checkIslemTable = $db->query("SHOW TABLES LIKE 'STK_ISLEM_DURUM'");
                            if ($checkIslemTable->rowCount() == 0) {
                                // Tablo yoksa oluştur
                                $db->exec("CREATE TABLE STK_ISLEM_DURUM (
                                    ID INT AUTO_INCREMENT PRIMARY KEY,
                                    ISLEM_TIPI VARCHAR(50) NOT NULL,
                                    BASLANGIC_ZAMANI DATETIME NOT NULL,
                                    SON_GUNCELLEME DATETIME NOT NULL,
                                    DURUM VARCHAR(20) NOT NULL,
                                    TOPLAM_KAYIT INT NOT NULL,
                                    ISLEM_SAYISI INT NOT NULL,
                                    BASARILI_SAYISI INT NOT NULL,
                                    MEVCUT_KONUM INT NOT NULL,
                                    KULLANICI_ID INT NOT NULL
                                )");
                            }
                            
                            // Önceki işlemleri iptal et
                            $db->exec("UPDATE STK_ISLEM_DURUM SET DURUM = 'IPTAL' WHERE ISLEM_TIPI = 'STOK_MIKTAR' AND DURUM = 'DEVAM'");
                            
                            // Yeni işlem kaydı oluştur
                            $insertProcess = $db->prepare("INSERT INTO STK_ISLEM_DURUM 
                                (ISLEM_TIPI, BASLANGIC_ZAMANI, SON_GUNCELLEME, DURUM, TOPLAM_KAYIT, ISLEM_SAYISI, BASARILI_SAYISI, MEVCUT_KONUM, KULLANICI_ID) 
                                VALUES 
                                ('STOK_MIKTAR', NOW(), NOW(), 'DEVAM', ?, 0, 0, 0, ?)");
                            $insertProcess->execute([$urunSayisi, $_SESSION['user_id']]);
                            
                        } catch (PDOException $ex) {
                            // İşlem durum tablosu oluşturulamadıysa, devam et ama uyarı ver
                            echo '<div class="alert alert-warning">İşlem durum takibi için tablo oluşturulamadı. İşlem devam edecek ancak oturum sonlandığında kaldığınız yerden devam edemeyebilirsiniz.</div>';
                        }
                        
                        // Session'a kaydet
                        $_SESSION['stok_miktar_toplam'] = $urunSayisi;
                        
                        // İşlem başladı bilgisi
                        echo '<div class="alert alert-info">Stok miktarları hesaplama işlemi başlıyor... Her sayfada 200 ürün işlenecek.</div>';
                    }
                } catch (PDOException $e) {
                    // Hata durumunda normal süreçle devam et
                    echo '<div class="alert alert-warning">İşlem durumu kontrol edilirken hata oluştu: ' . $e->getMessage() . ' Normal süreçle devam ediliyor.</div>';
                    
                    // Toplam ürün sayısını al
                    echo '<div class="alert alert-info">Ürünleri sayıyoruz...</div>';
                    $countSQL = "SELECT COUNT(*) as total FROM stok";
                    $countStmt = $db->query($countSQL);
                    $urunSayisi = $countStmt->fetch(PDO::FETCH_ASSOC)['total'];
                    echo '<div class="alert alert-success">Toplam ' . $urunSayisi . ' ürün bulundu.</div>';
                    
                    // Session'a kaydet
                    $_SESSION['stok_miktar_toplam'] = $urunSayisi;
                }
            } else {
                echo '<div class="alert alert-info">İşlem kaldığı yerden devam ediyor. Tamamlanan: ' . $islemSayisi . '/' . $urunSayisi . ' ürün.</div>';
            }
            
            // İlerleme çubuğu göster
            $yuzdeTamamlanan = $urunSayisi > 0 ? round(($islemSayisi / $urunSayisi) * 100) : 0;
            echo '<div class="progress mb-3">
                <div class="progress-bar" role="progressbar" style="width: ' . $yuzdeTamamlanan . '%;" 
                    aria-valuenow="' . $yuzdeTamamlanan . '" aria-valuemin="0" aria-valuemax="100">' . $yuzdeTamamlanan . '%</div>
            </div>';
            
            $batchSize = 500; // Her seferde işlenecek ürün sayısı - artırıldı
            $maksimumIslem = 500; // Tek sayfa yüklemesinde maksimum işlenecek ürün sayısı - artırıldı
            $islemSayaci = 0;
            $sayfaBaslangic = microtime(true);
            
            // Ürünleri gruplar halinde işle
            if ($offset < $urunSayisi) {
                // Ürünleri batches halinde al - optimize edilmiş sorgu
                $urunlerSQL = "SELECT ID FROM stok WHERE ID NOT IN (SELECT URUN_ID FROM STK_URUN_MIKTAR) LIMIT $offset, $batchSize";
                $urunlerStmt = $db->query($urunlerSQL);
                
                // Her batch başlangıcında bilgi ver
                echo '<div class="alert alert-info">Ürün grubu işleniyor: ' . ($offset+1) . ' - ' . min($offset+$batchSize, $urunSayisi) . '</div>';
                
                // Bu grup için işlem başlangıç zamanı
                $batchStartTime = microtime(true);
                
                while ($urun = $urunlerStmt->fetch(PDO::FETCH_ASSOC)) {
                    $urunId = $urun['ID'];
                    
                    try {
                        // Önce mevcut ürünü kontrol et
                        $checkSQL = "SELECT URUN_ID FROM STK_URUN_MIKTAR WHERE URUN_ID = ?";
                        $checkStmt = $db->prepare($checkSQL);
                        $checkStmt->bindValue(1, $urunId, PDO::PARAM_INT);
                        $checkStmt->execute();
                        $exists = $checkStmt->rowCount() > 0;
                        
                        // Bu ürün için stok miktarını hesapla - daha optimize bir sorgu
                        $stokSql = "SELECT 
                            SUM(IF(ISLEMTIPI = 0, MIKTAR, 0)) - SUM(IF(ISLEMTIPI = 1, MIKTAR, 0)) AS TOPLAM_MIKTAR
                        FROM 
                            STK_FIS_HAR
                        WHERE 
                            IPTAL = 0 AND KARTID = :urunId";
                        
                        $stokStmt = $db->prepare($stokSql);
                        $stokStmt->bindParam(':urunId', $urunId, PDO::PARAM_INT);
                        $stokStmt->execute();
                        $stokRow = $stokStmt->fetch(PDO::FETCH_ASSOC);
                        
                        $toplamMiktar = $stokRow['TOPLAM_MIKTAR'] ?: 0;
                        
                        if ($exists) {
                            // Ürün zaten tabloda varsa güncelle
                            $updateSQL = "UPDATE STK_URUN_MIKTAR SET MIKTAR = ?, SON_GUNCELLEME = NOW() WHERE URUN_ID = ?";
                            $updateStmt = $db->prepare($updateSQL);
                            $updateStmt->bindValue(1, $toplamMiktar, PDO::PARAM_STR);
                            $updateStmt->bindValue(2, $urunId, PDO::PARAM_INT);
                            
                            if ($updateStmt->execute()) {
                                $basariliSayisi++;
                            }
                        } else {
                            // Ürün tabloda yoksa ekle
                            $insertSQL = "INSERT INTO STK_URUN_MIKTAR (URUN_ID, MIKTAR, SON_GUNCELLEME) VALUES (?, ?, NOW())";
                            $insertStmt = $db->prepare($insertSQL);
                            $insertStmt->bindValue(1, $urunId, PDO::PARAM_INT);
                            $insertStmt->bindValue(2, $toplamMiktar, PDO::PARAM_STR);
                            
                            if ($insertStmt->execute()) {
                                $basariliSayisi++;
                            }
                        }
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">Ürün ID: ' . $urunId . ' için hata: ' . $e->getMessage() . '</div>';
                        // Hatayı logla ama devam et
                        error_log("Ürün $urunId işlenirken hata: " . $e->getMessage());
                        continue; // Bir sonraki ürüne geç
                    }
                    
                    $islemSayisi++;
                    $islemSayaci++;
                    
                    // Her 100 üründe bir ilerleme durumunu güncelle
                    if ($islemSayisi % 100 == 0) {
                        $yuzdeTamamlanan = round(($islemSayisi / $urunSayisi) * 100);
                        echo '<script>
                            document.querySelector(".progress-bar").style.width = "' . $yuzdeTamamlanan . '%";
                            document.querySelector(".progress-bar").setAttribute("aria-valuenow", ' . $yuzdeTamamlanan . ');
                            document.querySelector(".progress-bar").textContent = "' . $yuzdeTamamlanan . '%";
                        </script>';
                        echo str_pad(' ', 4096); // Output buffer flush
                        flush();
                    }
                    
                    // Maksimum işlem sayısına ulaşıldı mı kontrol et
                    $gecenSure = microtime(true) - $sayfaBaslangic;
                    if ($islemSayaci >= $maksimumIslem || $gecenSure > 30) {
                        // Oturuma kaydet
                        $_SESSION['stok_miktar_offset'] = $offset + $islemSayaci;
                        $_SESSION['stok_miktar_basarili'] = $basariliSayisi;
                        $_SESSION['stok_miktar_islem'] = $islemSayisi;
                        
                        // Veritabanına da kaydet
                        try {
                            $updateProcess = $db->prepare("UPDATE STK_ISLEM_DURUM SET 
                                SON_GUNCELLEME = NOW(), 
                                ISLEM_SAYISI = ?, 
                                BASARILI_SAYISI = ?, 
                                MEVCUT_KONUM = ? 
                                WHERE ISLEM_TIPI = 'STOK_MIKTAR' AND DURUM = 'DEVAM'");
                            $updateProcess->execute([$islemSayisi, $basariliSayisi, $offset + $islemSayaci]);
                        } catch (PDOException $ex) {
                            // Hata olursa sessiona güven
                            error_log("İşlem durumu kaydedilirken hata: " . $ex->getMessage());
                        }
                        
                        echo '<div class="alert alert-warning">Zaman aşımını önlemek için işlem ara verildi. Kalan ürünler için sayfa otomatik olarak yenilenecek...</div>';
                        echo '<script>
                            setTimeout(function() {
                                window.location.href = window.location.pathname;
                            }, 3000);
                        </script>';
                        break; // Döngüden çık
                    }
                }
                
                // Batch işlem süresi
                $batchTime = round(microtime(true) - $batchStartTime, 2);
                echo '<div class="alert alert-success">Grup işleme tamamlandı. İşlenen: ' . $islemSayaci . ' ürün, Süre: ' . $batchTime . ' saniye</div>';
                
                // Tüm batch işlenmiş ve hala sayfa yenileme olmamışsa, offset'i güncelle
                if ($islemSayaci < $maksimumIslem) {
                    $offset += $islemSayaci;
                    $_SESSION['stok_miktar_offset'] = $offset;
                    $_SESSION['stok_miktar_basarili'] = $basariliSayisi;
                    $_SESSION['stok_miktar_islem'] = $islemSayisi;
                    
                    // Veritabanına da kaydet
                    try {
                        $updateProcess = $db->prepare("UPDATE STK_ISLEM_DURUM SET 
                            SON_GUNCELLEME = NOW(), 
                            ISLEM_SAYISI = ?, 
                            BASARILI_SAYISI = ?, 
                            MEVCUT_KONUM = ? 
                            WHERE ISLEM_TIPI = 'STOK_MIKTAR' AND DURUM = 'DEVAM'");
                        $updateProcess->execute([$islemSayisi, $basariliSayisi, $offset]);
                    } catch (PDOException $ex) {
                        // Hata olursa sessiona güven
                        error_log("İşlem durumu kaydedilirken hata: " . $ex->getMessage());
                    }
                    
                    // Tüm kayıtlar işlendi mi kontrol et
                    if ($offset >= $urunSayisi) {
                        $islemTamamlandi = true;
                        echo '<div class="alert alert-success mt-3">Tüm stok miktarları hesaplandı! Toplam ' . $urunSayisi . ' ürünün ' . $basariliSayisi . ' tanesinin stok miktarı başarıyla hesaplandı.</div>';
                        
                        // İşlemi tamamlandı olarak işaretle
                        try {
                            $completeProcess = $db->prepare("UPDATE STK_ISLEM_DURUM SET 
                                SON_GUNCELLEME = NOW(), 
                                DURUM = 'TAMAMLANDI' 
                                WHERE ISLEM_TIPI = 'STOK_MIKTAR' AND DURUM = 'DEVAM'");
                            $completeProcess->execute();
                        } catch (PDOException $ex) {
                            // Hata olursa görmezden gel
                            error_log("İşlem tamamlandı olarak işaretlenirken hata: " . $ex->getMessage());
                        }
                        
                        // Session bilgilerini temizle
                        unset($_SESSION['stok_miktar_offset']);
                        unset($_SESSION['stok_miktar_basarili']);
                        unset($_SESSION['stok_miktar_islem']);
                    } else {
                        // Hala işlenecek kayıt var, sayfayı yeniden yükle
                        echo '<div class="alert alert-info">Sonraki gruba geçiliyor... Sayfa otomatik olarak yenilenecek.</div>';
                        echo '<script>
                            setTimeout(function() {
                                window.location.href = window.location.pathname;
                            }, 3000);
                        </script>';
                    }
                }
            } else {
                $islemTamamlandi = true;
                echo '<div class="alert alert-success">Tüm stok miktarları zaten hesaplanmış! Toplam ' . $urunSayisi . ' ürünün stok miktarı hesaplandı.</div>';
            }
            
            // İşlem tamamlandı, trigger'ları oluştur
            if ($islemTamamlandi) {
                echo '<div class="alert alert-info">Stok hareketi triggerları oluşturuluyor...</div>';
                
                // Önceki triggerları sil
                $db->exec("DROP TRIGGER IF EXISTS after_stk_fis_har_insert_giris");
                $db->exec("DROP TRIGGER IF EXISTS after_stk_fis_har_insert_cikis");
                $db->exec("DROP TRIGGER IF EXISTS after_stk_fis_har_update_iptal");
                $db->exec("DROP TRIGGER IF EXISTS after_stk_fis_har_delete");
                
                try {
                    // 1. Adım: En basit trigger'ı oluşturmayı dene
                    echo '<div class="alert alert-info">1. Trigger oluşturuluyor (Giriş)...</div>';
                    
                    $trigger1 = "CREATE TRIGGER after_stk_fis_har_insert_giris AFTER INSERT ON STK_FIS_HAR 
                               FOR EACH ROW 
                               BEGIN 
                                   IF NEW.IPTAL = 0 AND NEW.ISLEMTIPI = 0 THEN 
                                       INSERT INTO STK_URUN_MIKTAR (URUN_ID, MIKTAR, SON_GUNCELLEME) 
                                       VALUES (NEW.KARTID, NEW.MIKTAR, NOW()) 
                                       ON DUPLICATE KEY UPDATE 
                                       MIKTAR = MIKTAR + NEW.MIKTAR, 
                                       SON_GUNCELLEME = NOW(); 
                                   END IF; 
                               END";
                    
                    $db->exec($trigger1);
                    echo '<div class="alert alert-success">1. Trigger başarıyla oluşturuldu!</div>';
                    
                    // 2. Adım: İkinci trigger'ı oluştur
                    echo '<div class="alert alert-info">2. Trigger oluşturuluyor (Çıkış)...</div>';
                    
                    $trigger2 = "CREATE TRIGGER after_stk_fis_har_insert_cikis AFTER INSERT ON STK_FIS_HAR 
                               FOR EACH ROW 
                               BEGIN 
                                   IF NEW.IPTAL = 0 AND NEW.ISLEMTIPI = 1 THEN 
                                       UPDATE STK_URUN_MIKTAR 
                                       SET MIKTAR = MIKTAR - NEW.MIKTAR, 
                                           SON_GUNCELLEME = NOW() 
                                       WHERE URUN_ID = NEW.KARTID; 
                                   END IF; 
                               END";
                    
                    $db->exec($trigger2);
                    echo '<div class="alert alert-success">2. Trigger başarıyla oluşturuldu!</div>';
                    
                    echo '<div class="alert alert-success">Stok triggerları başarıyla oluşturuldu! Sistem artık hazır!</div>';
                    
                } catch (PDOException $triggerEx) {
                    echo '<div class="alert alert-danger">Trigger oluşturma hatası: ' . $triggerEx->getMessage() . '</div>';
                    echo '<div class="alert alert-warning">
                        <p>Trigger\'ları manuel olarak oluşturmak için phpMyAdmin\'e giriş yapıp şu SQL komutlarını çalıştırın:</p>
                        <pre class="bg-dark text-light p-2">
CREATE TRIGGER after_stk_fis_har_insert_giris AFTER INSERT ON STK_FIS_HAR
FOR EACH ROW
BEGIN
    IF NEW.IPTAL = 0 AND NEW.ISLEMTIPI = 0 THEN
        INSERT INTO STK_URUN_MIKTAR (URUN_ID, MIKTAR, SON_GUNCELLEME)
        VALUES (NEW.KARTID, NEW.MIKTAR, NOW())
        ON DUPLICATE KEY UPDATE 
        MIKTAR = MIKTAR + NEW.MIKTAR, 
        SON_GUNCELLEME = NOW();
    END IF;
END;

CREATE TRIGGER after_stk_fis_har_insert_cikis AFTER INSERT ON STK_FIS_HAR
FOR EACH ROW
BEGIN
    IF NEW.IPTAL = 0 AND NEW.ISLEMTIPI = 1 THEN
        UPDATE STK_URUN_MIKTAR
        SET MIKTAR = MIKTAR - NEW.MIKTAR, 
            SON_GUNCELLEME = NOW()
        WHERE URUN_ID = NEW.KARTID;
    END IF;
END;
                        </pre>
                    </div>';
                }
            }
            
        } catch (PDOException $e) {
            // Session'a hata durumunu kaydet
            $_SESSION['stok_miktar_offset'] = $offset;
            $_SESSION['stok_miktar_basarili'] = $basariliSayisi;
            $_SESSION['stok_miktar_islem'] = $islemSayisi;
            
            echo '<div class="alert alert-danger">
                <h5>Hata oluştu:</h5>
                <p>' . $e->getMessage() . '</p>
                <p>Son işlenen pozisyon kaydedildi. Sayfayı yenileyerek kaldığınız yerden devam edebilirsiniz.</p>
            </div>';
        }
        ?>
        
        <div class="mt-4">
            <?php if (!$islemTamamlandi && $offset > 0): ?>
            <a href="?reset=1" class="btn btn-warning">İşlemi Baştan Başlat</a>
            <?php endif; ?>
            <a href="index.php" class="btn btn-primary">Stok Modülüne Dön</a>
        </div>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 