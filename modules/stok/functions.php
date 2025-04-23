<?php
/**
 * Stok Modülü Yardımcı Fonksiyonları
 * Bu dosya, stok modülünde kullanılan ortak fonksiyonları içerir.
 */

// Debug modu aktif
$debug = true; // Hata ayıklama modu açık
$performans_izleme = true; // Performans izleme modu açık
ini_set('display_errors', 1); // Hata mesajlarını göster
error_reporting(E_ALL); // Tüm hataları raporla

/**
 * Zaman aşımı kontrollü güvenli sorgu çalıştırma
 * @param PDO $db Veritabanı bağlantısı
 * @param string $sql SQL sorgusu
 * @param array $params Sorgu parametreleri
 * @param int $timeout Zaman aşımı süresi (saniye)
 * @return PDOStatement Sorgu sonucu
 */
function guvenli_sorgu($db, $sql, $params = [], $timeout = 5) {
    global $performans_izleme;
    
    // Performans ölçümü başlat
    $olcum = sorguBaslat($sql);
    
    // Önceki zaman aşımı ayarını yedekle
    $db->query("SET @old_max_execution_time = @@max_execution_time");
    
    // Yeni zaman aşımı ayarını belirle (milisaniye cinsinden)
    $db->query("SET SESSION max_execution_time=" . ($timeout * 1000));
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        // Zaman aşımı ayarını geri yükle
        $db->query("SET SESSION max_execution_time = @old_max_execution_time");
        
        // Performans ölçümü bitir
        sorguBitir($olcum);
        
        return $stmt;
    } catch (PDOException $e) {
        // Zaman aşımı ayarını geri yükle
        $db->query("SET SESSION max_execution_time = @old_max_execution_time");
        
        // Performans ölçümü bitir (hata durumunda da)
        sorguBitir($olcum);
        
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

/**
 * Debug mesajı gösterir
 * @param string $message Mesaj metni
 * @param mixed $variable İsteğe bağlı değişken
 */
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

/**
 * Sorgu performans ölçümü başlatır
 * @param string $sorgu SQL sorgusu
 * @return array|null Ölçüm bilgileri
 */
function sorguBaslat($sorgu) {
    global $performans_izleme, $sorgu_sayaci;
    if ($performans_izleme) {
        $sorgu_sayaci++;
        return [
            'id' => $sorgu_sayaci,
            'sorgu' => $sorgu,
            'baslangic' => microtime(true),
            'bitis' => null,
            'sure' => null
        ];
    }
    return null;
}

/**
 * Sorgu performans ölçümünü bitirir
 * @param array $olcum Ölçüm bilgileri
 * @return float|null Ölçüm süresi
 */
function sorguBitir($olcum) {
    global $performans_izleme, $performans_olcumleri, $toplam_sorgu_suresi;
    if ($performans_izleme && $olcum !== null) {
        $olcum['bitis'] = microtime(true);
        $olcum['sure'] = ($olcum['bitis'] - $olcum['baslangic']) * 1000; // milisaniye cinsinden
        $toplam_sorgu_suresi += $olcum['sure'];
        $performans_olcumleri[] = $olcum;
        return $olcum['sure'];
    }
    return null;
}

/**
 * Performans raporunu gösterir
 */
function performansRaporuGoster() {
    global $performans_izleme, $performans_olcumleri, $toplam_sorgu_suresi, $sayfa_baslangic_zamani;
    if ($performans_izleme) {
        $sayfa_yukleme_suresi = (microtime(true) - $sayfa_baslangic_zamani) * 1000; // milisaniye cinsinden
        
        echo '<div id="performans-raporu" style="position:fixed; bottom:10px; right:10px; width:600px; max-height:80vh; overflow-y:auto; background-color:#fff; border:1px solid #ddd; border-radius:5px; box-shadow:0 0 10px rgba(0,0,0,0.1); padding:10px; z-index:9999; font-size:12px; display:none;">';
        echo '<h4 style="margin-top:0;border-bottom:1px solid #ddd;padding-bottom:5px;">Performans Raporu</h4>';
        echo '<div style="margin-bottom:10px;padding:5px;background-color:#f8f9fa;border-radius:3px;">';
        echo '<strong>Toplam Sayfa Yükleme Süresi:</strong> ' . number_format($sayfa_yukleme_suresi, 2) . ' ms<br>';
        echo '<strong>Toplam Sorgu Sayısı:</strong> ' . count($performans_olcumleri) . '<br>';
        echo '<strong>Toplam Sorgu Süresi:</strong> ' . number_format($toplam_sorgu_suresi, 2) . ' ms (' . number_format(($toplam_sorgu_suresi / $sayfa_yukleme_suresi) * 100, 1) . '%)<br>';
        echo '</div>';
        
        if (!empty($performans_olcumleri)) {
            // Sorguları süreye göre sırala (en yavaşlar üstte)
            usort($performans_olcumleri, function($a, $b) {
                return $b['sure'] <=> $a['sure'];
            });
            
            echo '<table style="width:100%;border-collapse:collapse;font-size:11px;">';
            echo '<thead style="background-color:#f0f0f0;">';
            echo '<tr><th style="padding:4px;text-align:left;border:1px solid #ddd;">ID</th><th style="padding:4px;text-align:left;border:1px solid #ddd;">Süre (ms)</th><th style="padding:4px;text-align:left;border:1px solid #ddd;">Sorgu</th></tr>';
            echo '</thead><tbody>';
            
            foreach ($performans_olcumleri as $olcum) {
                $renk = $olcum['sure'] > 100 ? '#ffebee' : ($olcum['sure'] > 50 ? '#fff8e1' : '#f1f8e9');
                
                echo '<tr style="background-color:' . $renk . ';">';
                echo '<td style="padding:4px;border:1px solid #ddd;">' . $olcum['id'] . '</td>';
                echo '<td style="padding:4px;border:1px solid #ddd;">' . number_format($olcum['sure'], 2) . '</td>';
                echo '<td style="padding:4px;border:1px solid #ddd;"><pre style="margin:0;white-space:pre-wrap;word-break:break-all;max-width:450px;">' . htmlspecialchars($olcum['sorgu']) . '</pre></td>';
                echo '</tr>';
            }
            
            echo '</tbody></table>';
        }
        
        echo '<div style="margin-top:10px;text-align:right;"><button onclick="this.parentNode.parentNode.style.display=\'none\';" style="padding:3px 8px;background:#f1f1f1;border:1px solid #ddd;border-radius:3px;cursor:pointer;">Kapat</button></div>';
        echo '</div>';
        
        // Performans raporu göster/gizle düğmesi
        echo '<div style="position:fixed; bottom:10px; right:10px; z-index:9998;">';
        echo '<button id="performans-buton" onclick="togglePerformansRaporu()" style="padding:5px 10px;background:#4caf50;color:white;border:none;border-radius:3px;cursor:pointer;box-shadow:0 2px 5px rgba(0,0,0,0.2);">Performans Raporu</button>';
        echo '</div>';
        
        // JavaScript kodu ekle
        echo '<script>
        function togglePerformansRaporu() {
            var rapor = document.getElementById("performans-raporu");
            if (rapor.style.display === "none") {
                rapor.style.display = "block";
            } else {
                rapor.style.display = "none";
            }
        }
        </script>';
    }
}

/**
 * Sorguları performans ölçümlü olarak çalıştırma
 * @param PDO $db Veritabanı bağlantısı
 * @param string $sql SQL sorgusu
 * @param array $params Sorgu parametreleri
 * @param bool $fetchAll Tüm sonuçları getir (false ise tek satır)
 * @param int $fetchMode Getirme modu (PDO::FETCH_ASSOC vs.)
 * @return mixed Sorgu sonucu
 */
function olcumluSorgu($db, $sql, $params = [], $fetchAll = true, $fetchMode = PDO::FETCH_ASSOC) {
    $olcum = sorguBaslat($sql);
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        
        if ($fetchAll) {
            $sonuc = $stmt->fetchAll($fetchMode);
        } else {
            $sonuc = $stmt->fetch($fetchMode);
        }
        
        sorguBitir($olcum);
        return $sonuc;
    } catch (Exception $e) {
        sorguBitir($olcum);
        debugEcho("Sorgu hatası: " . $e->getMessage(), $sql);
        throw $e;
    }
}

/**
 * Tek değer döndüren sorguları performans ölçümlü olarak çalıştırma
 * @param PDO $db Veritabanı bağlantısı
 * @param string $sql SQL sorgusu
 * @param array $params Sorgu parametreleri
 * @return mixed Sorgu sonucu (tek değer)
 */
function olcumluSorguTekDeger($db, $sql, $params = []) {
    $olcum = sorguBaslat($sql);
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute($params);
        $sonuc = $stmt->fetchColumn();
        
        sorguBitir($olcum);
        return $sonuc;
    } catch (Exception $e) {
        sorguBitir($olcum);
        debugEcho("Sorgu hatası: " . $e->getMessage(), $sql);
        throw $e;
    }
}

/**
 * Türkçe karakterleri İngilizce eşdeğerlerine dönüştürür
 * @param string $str Dönüştürülecek metin
 * @return string Dönüştürülmüş metin
 */
function turkceKarakterDonustur($str) {
    $turkceKarakterler = ['ç', 'Ç', 'ğ', 'Ğ', 'ı', 'İ', 'ö', 'Ö', 'ş', 'Ş', 'ü', 'Ü'];
    $ingilizceKarakterler = ['c', 'C', 'g', 'G', 'i', 'I', 'o', 'O', 's', 'S', 'u', 'U'];
    return str_replace($turkceKarakterler, $ingilizceKarakterler, $str);
}

/**
 * SQL sorgusu için büyük harfe çevirme
 * @param string $columnName Kolon adı
 * @return string SQL ifadesi
 */
function sqlUpperKolonu($columnName) {
    return "UPPER($columnName)";
}

/**
 * SQL için arama terimi hazırlama
 * @param string $term Arama terimi
 * @return string Hazırlanmış terim
 */
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
    
    if (empty($urunIds)) {
        debugEcho("Boş ürün ID'leri dizisi, hesaplama yapılmayacak.");
        return [];
    }
    
    // Zaman aşımı kontrolü başlat
    $startTime = microtime(true);
    $timeoutSeconds = 10; // 10 saniye sonra zaman aşımı
    
    // İşlemler arasında küçük gecikmeler ekleyerek veritabanının nefes almasına izin ver
    $sleep = function($ms = 100) {
        usleep($ms * 1000);
    };
    
    try {
        debugEcho("Stok miktarları hesaplanıyor, ürün sayısı: " . count($urunIds));
        $stokMiktarlari = [];
        
        // ID'leri 20'şer gruplara böl
        $urunChunks = array_chunk($urunIds, 20);
        
        foreach ($urunChunks as $chunk) {
            // Zaman aşımı kontrolü
            if (microtime(true) - $startTime > $timeoutSeconds) {
                throw new Exception("Stok miktarları hesaplanırken zaman aşımı oluştu.");
            }
            
            $idPlaceholders = implode(',', $chunk);
            
            // Önce STK_URUN_MIKTAR tablosundaki mevcut stok miktarlarını kontrol et
            $mevcutMiktarSql = "SELECT URUN_ID, MIKTAR FROM STK_URUN_MIKTAR WHERE URUN_ID IN ($idPlaceholders)";
            $mevcutMiktarlar = olcumluSorgu($db, $mevcutMiktarSql);
            
            $urunlerinMevcutMiktarlari = [];
            $islemYapilacakUrunler = []; // STK_FIS_HAR kontrolü yapılacak ürünler
            
            // Mevcut miktarları ayarla ve işlem yapılacak ürünleri belirle
            foreach ($mevcutMiktarlar as $row) {
                $urunlerinMevcutMiktarlari[$row['URUN_ID']] = (float)$row['MIKTAR'];
                $stokMiktarlari[$row['URUN_ID']] = (float)$row['MIKTAR']; // Mevcut miktarları direkt kullan
            }
            
            // Mevcut miktarı olmayan ürünleri belirle
            foreach ($chunk as $urunId) {
                if (!isset($urunlerinMevcutMiktarlari[$urunId])) {
                    $islemYapilacakUrunler[] = $urunId;
                }
            }
            
            // Sadece stok miktarı olmayan ürünler için STK_FIS_HAR kontrolü yap
            if (!empty($islemYapilacakUrunler)) {
                $islemIdPlaceholders = implode(',', $islemYapilacakUrunler);
                
                // STK_FIS_HAR tablosundan stok hareketlerini topla
                $hareketSql = "
                    SELECT 
                        KARTID,
                        SUM(CASE 
                            WHEN ISLEMTIPI = '0' THEN MIKTAR  -- Giriş işlemi
                            WHEN ISLEMTIPI = '1' THEN -MIKTAR -- Çıkış işlemi
                            ELSE 0
                        END) as TOPLAM_MIKTAR
                    FROM 
                        STK_FIS_HAR
                    WHERE 
                        KARTID IN ($islemIdPlaceholders)
                    GROUP BY 
                        KARTID
                ";
                
                try {
                    // Performans ölçümlü sorgu ile stok hareketlerini hesapla
                    $hareketSonuclar = olcumluSorgu($db, $hareketSql);
                    $hareketMiktarlari = [];
                    
                    // Debug için detaylı bilgi göster
                    if ($debug) {
                        $detaylıHareketSql = "
                            SELECT 
                                KARTID,
                                ISLEMTIPI,
                                MIKTAR
                            FROM 
                                STK_FIS_HAR
                            WHERE 
                                KARTID IN ($islemIdPlaceholders)
                            ORDER BY
                                KARTID, ISLEMTIPI
                        ";
                        $detaylıHareketler = olcumluSorgu($db, $detaylıHareketSql);
                        debugEcho("Detaylı stok hareketleri (her satır):", $detaylıHareketler);
                    }
                    
                    // Key-Value formatına dönüştür
                    foreach($hareketSonuclar as $row) {
                        $hareketMiktarlari[$row['KARTID']] = (float)$row['TOPLAM_MIKTAR'];
                    }
                    
                    if ($debug) {
                        debugEcho("Hesaplanan stok hareketleri:", $hareketMiktarlari);
                    }
                    
                    // Her işlem yapılacak ürün için stok miktarını belirle
                    foreach ($islemYapilacakUrunler as $urunId) {
                        $stokMiktarlari[$urunId] = isset($hareketMiktarlari[$urunId]) ? $hareketMiktarlari[$urunId] : 0;
                    }
                } catch (Exception $e) {
                    debugEcho("Stok hareketi hesaplama hatası: " . $e->getMessage());
                }
            }
            
            // Hesaplanan miktarları STK_URUN_MIKTAR tablosuna kaydet
            foreach ($stokMiktarlari as $urunId => $miktar) {
                try {
                    // Önce mevcut kaydı kontrol et
                    $kontrolSql = "SELECT URUN_ID FROM STK_URUN_MIKTAR WHERE URUN_ID = ?";
                    $kontrolStmt = $db->prepare($kontrolSql);
                    $kontrolStmt->execute([$urunId]);
                    
                    if ($kontrolStmt->rowCount() > 0) {
                        // Mevcut kaydı güncelle
                        $updateSql = "UPDATE STK_URUN_MIKTAR SET MIKTAR = ?, SON_GUNCELLEME = NOW() WHERE URUN_ID = ?";
                        $db->prepare($updateSql)->execute([$miktar, $urunId]);
                    } else {
                        // Yeni kayıt ekle
                        $insertSql = "INSERT INTO STK_URUN_MIKTAR (URUN_ID, MIKTAR, SON_GUNCELLEME) VALUES (?, ?, NOW())";
                        $db->prepare($insertSql)->execute([$urunId, $miktar]);
                    }
                } catch (Exception $e) {
                    debugEcho("Miktar güncelleme hatası (ürün ID: $urunId): " . $e->getMessage());
                }
            }
            
            $sleep(); // 100ms bekle
        }
        
        // Debug detaylar
        if ($debug) {
            debugEcho("Hesaplanan son stok miktarları:", $stokMiktarlari);
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

/**
 * OEM numaralarını işler ve muadil ürün gruplarını günceller
 * 
 * @param PDO $db Veritabanı bağlantısı
 * @param int $product_id İşlem yapılacak ürün ID
 * @param string $oem_no OEM numaraları (birden fazla satır olabilir)
 * @param bool $clear_existing Mevcut OEM numaralarını temizle
 * @return array İşlem sonucu bilgilerini içeren dizi
 */
function processOEMNumbers($db, $product_id, $oem_no, $clear_existing = false) {
    try {
        $result = [
            'success' => true,
            'message' => '',
            'imported_count' => 0,
            'groups_updated' => 0
        ];
        
        // Eski OEM numaralarını temizle (eğer isteniyorsa)
        if ($clear_existing) {
            $stmt = $db->prepare("DELETE FROM oem_numbers WHERE product_id = ?");
            $stmt->execute([$product_id]);
        }
        
        // OEM numarası yoksa işlem yapma
        if (empty($oem_no)) {
            return $result;
        }
        
        // OEM numaralarını satırlara ayır
        $oem_numbers = preg_split('/\r\n|\r|\n/', $oem_no);
        $groups_updated = [];
        
        foreach ($oem_numbers as $oem_line) {
            $oem_trim = trim($oem_line);
            if (empty($oem_trim)) continue;
            
            // Bu OEM numarasını tabloya ekle
            $stmt = $db->prepare("INSERT IGNORE INTO oem_numbers (product_id, oem_no) VALUES (?, ?)");
            $stmt->execute([$product_id, $oem_trim]);
            
            if ($stmt->rowCount() > 0) {
                $result['imported_count']++;
            }
            
            // Bu OEM numarasına sahip başka ürünler var mı kontrol et (muadil grup için)
            $stmt = $db->prepare("SELECT DISTINCT product_id FROM oem_numbers WHERE oem_no = ? AND product_id != ?");
            $stmt->execute([$oem_trim, $product_id]);
            $related_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($related_products)) {
                // Bu ürünün dahil olduğu mevcut bir alternatif grup var mı kontrol et
                $stmt = $db->prepare("SELECT alternative_group_id FROM product_alternatives WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $existing_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Related products'ın dahil olduğu grupları bul
                $related_groups = [];
                foreach ($related_products as $rel_pid) {
                    $stmt = $db->prepare("SELECT alternative_group_id FROM product_alternatives WHERE product_id = ?");
                    $stmt->execute([$rel_pid]);
                    $rel_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $related_groups = array_merge($related_groups, $rel_groups);
                }
                $related_groups = array_unique($related_groups);
                
                // Mevcut bir grup varsa ona ekle, yoksa yeni oluştur
                $target_group = null;
                
                // Önce mevcut grupları kontrol et
                $common_groups = array_intersect($existing_groups, $related_groups);
                if (!empty($common_groups)) {
                    // Ortak bir grup varsa onu kullan
                    $target_group = reset($common_groups);
                } elseif (!empty($related_groups)) {
                    // İlişkili ürünlerin bir grubu varsa ona ekle
                    $target_group = reset($related_groups);
                } elseif (!empty($existing_groups)) {
                    // Bu ürünün bir grubu varsa ona ekle
                    $target_group = reset($existing_groups);
                }
                
                // Hiç grup yoksa yeni oluştur
                if ($target_group === null) {
                    $stmt = $db->prepare("INSERT INTO alternative_groups (group_name) VALUES (?)");
                    $group_name = "Muadil Grup " . $oem_trim;
                    $stmt->execute([$group_name]);
                    $target_group = $db->lastInsertId();
                }
                
                // Bu ürünü gruba ekle
                $stmt = $db->prepare("INSERT IGNORE INTO product_alternatives (product_id, alternative_group_id) VALUES (?, ?)");
                $stmt->execute([$product_id, $target_group]);
                
                // İlişkili ürünleri de aynı gruba ekle
                foreach ($related_products as $rel_pid) {
                    $stmt = $db->prepare("INSERT IGNORE INTO product_alternatives (product_id, alternative_group_id) VALUES (?, ?)");
                    $stmt->execute([$rel_pid, $target_group]);
                }
                
                // İşlenen grup ID'lerini takip et
                if (!in_array($target_group, $groups_updated)) {
                    $groups_updated[] = $target_group;
                }
            }
        }
        
        $result['groups_updated'] = count($groups_updated);
        $result['message'] = "Toplam {$result['imported_count']} OEM numarası işlendi ve {$result['groups_updated']} muadil grup güncellendi.";
        
        return $result;
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "OEM verileri işlenirken hata: " . $e->getMessage(),
            'imported_count' => 0,
            'groups_updated' => 0
        ];
    }
}

/**
 * EAN-13 barkod oluşturur
 * 
 * @param string $prefix Barkodu başlangıç rakamları (ülke kodu vs.)
 * @return string EAN-13 formatında oluşturulan barkod
 */
function generateEAN13($prefix = '9670000', $db = null) {
    // EAN-13 formatı toplam 13 haneden oluşur
    // İlk 12 hane + 1 kontrol hanesi
    
    // Prefix zaten belirtilmiş (ülke/firma kodu gibi)
    $code = $prefix;
    
    // Son kullanılan seri numarasını bul
    $last_number = 0;
    
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT MAX(KOD) as max_code FROM stok WHERE KOD LIKE ?");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['max_code']) {
                // Prefix'i kaldır ve sıra numarasını al
                $last_code = $result['max_code'];
                $sequence_part = substr($last_code, strlen($prefix), -1); // Son kontrolsüz rakamı da çıkar
                $last_number = (int)$sequence_part;
            }
        } catch (Exception $e) {
            // Hata durumunda 0'dan başla
            $last_number = 0;
        }
    }
    
    // Sıradaki numarayı oluştur
    $next_number = $last_number + 1;
    
    // Kalan haneleri sıfırla doldur ve sıra numarasını ekle
    $sequence_length = 12 - strlen($prefix);
    $sequence_str = str_pad($next_number, $sequence_length, '0', STR_PAD_LEFT);
    
    // Tam kodu oluştur (prefix + sıra numarası)
    $code = $prefix . $sequence_str;
    
    // Kontrol hanesi hesaplama (EAN-13 algoritması)
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += $code[$i] * ($i % 2 == 0 ? 1 : 3);
    }
    $checksum = (10 - ($sum % 10)) % 10;
    
    // Son kodu döndür (12 hane + kontrol hanesi)
    return $code . $checksum;
} 