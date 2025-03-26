<?php
/**
 * ERP Sistem - Daha Fazla Ürün Yükleme Scripti
 * 
 * Bu dosya AJAX isteği ile çağrılır ve sonraki sayfa ürünlerini döndürür.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('HTTP/1.0 403 Forbidden');
    echo "Bu sayfaya erişim izniniz yok!";
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

/**
 * Ürün ID'leri için stok miktarlarını hesaplar ve STK_URUN_MIKTAR tablosuna kaydeder
 * 
 * @param PDO $db Veritabanı bağlantısı
 * @param array $urunIds Ürün ID'leri dizisi
 * @return array Ürün ID'leri ve stok miktarları içeren dizi
 */
function hesaplaVeKaydetStokMiktarlari($db, $urunIds) {
    if (empty($urunIds)) {
        return [];
    }
    
    $stokMiktarlari = [];
    
    try {
        // Tablonun varlığını kontrol et
        $tableCheck = $db->query("SHOW TABLES LIKE 'STK_URUN_MIKTAR'");
        if ($tableCheck->rowCount() == 0) {
            throw new Exception("STK_URUN_MIKTAR tablosu bulunamadı");
        }
        
        // STK_URUN_MIKTAR tablosundan stok miktarlarını çek
        $stokSql = "SELECT URUN_ID, MIKTAR FROM STK_URUN_MIKTAR WHERE URUN_ID IN (" . implode(',', $urunIds) . ")";
        $stokStmt = $db->query($stokSql);
        
        // Bulunan ürünleri kaydet
        $bulunanUrunIds = [];
        while ($stokRow = $stokStmt->fetch(PDO::FETCH_ASSOC)) {
            $stokMiktarlari[$stokRow['URUN_ID']] = $stokRow['MIKTAR'];
            $bulunanUrunIds[] = $stokRow['URUN_ID'];
        }
        
        // Eksik ürünleri bul
        $eksikUrunIds = array_diff($urunIds, $bulunanUrunIds);
        
        // Eksik ürünler için STK_FIS_HAR'dan hesapla ve kaydet
        if (!empty($eksikUrunIds)) {
            // STK_FIS_HAR tablosundan stok hareketlerini al
            $eskiStokSql = "SELECT 
                KARTID,
                SUM(CASE WHEN ISLEMTIPI = 0 THEN MIKTAR ELSE 0 END) AS GIRIS_MIKTAR,
                SUM(CASE WHEN ISLEMTIPI = 1 THEN MIKTAR ELSE 0 END) AS CIKIS_MIKTAR
            FROM 
                STK_FIS_HAR
            WHERE 
                IPTAL = 0 AND KARTID IN (" . implode(',', $eksikUrunIds) . ")
            GROUP BY 
                KARTID";
            
            // Transaction başlat
            $db->beginTransaction();
            
            try {
                $eskiStokStmt = $db->query($eskiStokSql);
                $updatedCount = 0;
                
                // Stok hareketi olan ürünler
                while ($eskiStokRow = $eskiStokStmt->fetch(PDO::FETCH_ASSOC)) {
                    $urunId = $eskiStokRow['KARTID'];
                    $girisMiktar = $eskiStokRow['GIRIS_MIKTAR'] ?? 0;
                    $cikisMiktar = $eskiStokRow['CIKIS_MIKTAR'] ?? 0;
                    $netMiktar = $girisMiktar - $cikisMiktar;
                    
                    // Stok miktarını ekle
                    $stokMiktarlari[$urunId] = $netMiktar;
                    
                    // STK_URUN_MIKTAR tablosuna ekle/güncelle
                    $insertSql = "INSERT INTO STK_URUN_MIKTAR (URUN_ID, MIKTAR, SON_GUNCELLENME) 
                                 VALUES (:urun_id, :miktar, NOW()) 
                                 ON DUPLICATE KEY UPDATE 
                                 MIKTAR = :miktar, 
                                 SON_GUNCELLENME = NOW()";
                    
                    $insertStmt = $db->prepare($insertSql);
                    $insertStmt->bindParam(':urun_id', $urunId, PDO::PARAM_INT);
                    $insertStmt->bindParam(':miktar', $netMiktar, PDO::PARAM_STR);
                    $insertStmt->execute();
                    $updatedCount++;
                }
                
                // Stok hareketi olmayan ürünler için sıfır değeri ekle
                $kayitsizUrunIds = array_diff($eksikUrunIds, array_keys($stokMiktarlari));
                foreach ($kayitsizUrunIds as $urunId) {
                    // Stok miktarını sıfır olarak ayarla
                    $stokMiktarlari[$urunId] = 0;
                    
                    // STK_URUN_MIKTAR tablosuna ekle
                    $insertSql = "INSERT INTO STK_URUN_MIKTAR (URUN_ID, MIKTAR, SON_GUNCELLENME) 
                                 VALUES (:urun_id, 0, NOW()) 
                                 ON DUPLICATE KEY UPDATE 
                                 MIKTAR = 0, 
                                 SON_GUNCELLENME = NOW()";
                    
                    $insertStmt = $db->prepare($insertSql);
                    $insertStmt->bindParam(':urun_id', $urunId, PDO::PARAM_INT);
                    $insertStmt->execute();
                    $updatedCount++;
                }
                
                // Transaction'ı tamamla
                $db->commit();
                
            } catch (Exception $e) {
                // Hata durumunda transaction'ı geri al
                $db->rollBack();
            }
        }
        
    } catch (Exception $e) {
        // STK_URUN_MIKTAR tablosu yoksa, sadece STK_FIS_HAR'dan hesapla
        try {
            $eskiStokSql = "SELECT 
                KARTID,
                SUM(CASE WHEN ISLEMTIPI = 0 THEN MIKTAR ELSE 0 END) AS GIRIS_MIKTAR,
                SUM(CASE WHEN ISLEMTIPI = 1 THEN MIKTAR ELSE 0 END) AS CIKIS_MIKTAR
            FROM STK_FIS_HAR
            WHERE IPTAL = 0 AND KARTID IN (" . implode(',', $urunIds) . ")
            GROUP BY KARTID";
            
            $eskiStokStmt = $db->query($eskiStokSql);
            while ($eskiStokRow = $eskiStokStmt->fetch(PDO::FETCH_ASSOC)) {
                $stokMiktarlari[$eskiStokRow['KARTID']] = $eskiStokRow['GIRIS_MIKTAR'] - $eskiStokRow['CIKIS_MIKTAR'];
            }
            
        } catch (Exception $eskiStokHata) {
            // Hata durumunda boş dizi dön
        }
    }
    
    return $stokMiktarlari;
}

// AJAX isteklerini işle
if (isset($_GET['q']) && isset($_GET['page'])) {
    $search = $_GET['q'];
    $page = (int)$_GET['page'];
    $limit = 100; // Her sayfada 100 ürün
    $offset = ($page - 1) * $limit;
    
    try {
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
        
        // Ürünleri listele
        $html = '';
        
        // Önce verileri çek
        $stmt->execute();
        $stmt_urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (count($stmt_urunler) > 0) {
            // Stok miktarlarını hesapla
            try {
                // Ürün ID'lerini al
                $urunIds = [];
                foreach ($stmt_urunler as $urun) {
                    $urunIds[] = $urun['ID'];
                }
                
                if (!empty($urunIds)) {
                    // Stok miktarlarını hesapla ve STK_URUN_MIKTAR tablosuna kaydet
                    $stokMiktarlari = hesaplaVeKaydetStokMiktarlari($db, $urunIds);
                    
                    // Stok tablosundan temel bilgileri göster, ama gerçek stok miktarları için hesaplanan değerleri kullan
                    foreach ($stmt_urunler as $urun) {
                        $guncel_stok = isset($stokMiktarlari[$urun['ID']]) ? $stokMiktarlari[$urun['ID']] : 0;
                        $sistem_stok = isset($urun['MIKTAR']) ? (float)$urun['MIKTAR'] : 0;
                        
                        // Birim bilgisi
                        $birim = isset($urun['BIRIM']) ? $urun['BIRIM'] : 'Adet';
                        
                        // Stok hücresi
                        if ($sistem_stok != $guncel_stok) {
                            $stok_hucresi = '<span class="text-success">' . number_format($guncel_stok, 2, ',', '.') . '</span>';
                        } else {
                            $stok_hucresi = number_format($guncel_stok, 2, ',', '.');
                        }
                        
                        // Stok durumu
                        $stok_durumu = ($guncel_stok <= $urun['MIN_STOK']) ? 
                            '<span class="badge bg-danger">Kritik</span>' : 
                            '<span class="badge bg-success">Normal</span>';
                        
                        $html .= '<tr>
                            <td class="text-center">
                                <input type="checkbox" class="select-item" value="' . $urun['ID'] . '">
                            </td>
                            <td>' . htmlspecialchars($urun['KOD']) . '</td>
                            <td><a href="urun_detay.php?id=' . $urun['ID'] . '">' . htmlspecialchars($urun['ADI']) . '</a></td>
                            <td>' . (isset($urun['TIP']) ? htmlspecialchars($urun['TIP']) : '') . '</td>
                            <td>' . (isset($urun['BIRIM']) ? htmlspecialchars($urun['BIRIM']) : '-') . '</td>
                            <td class="text-end">' . (isset($urun['ALIS_FIYAT']) ? number_format($urun['ALIS_FIYAT'], 2, ',', '.') : '0,00') . ' TL</td>
                            <td class="text-end">' . (isset($urun['SATIS_FIYAT']) ? number_format($urun['SATIS_FIYAT'], 2, ',', '.') : '0,00') . ' TL</td>
                            <td class="text-center">' . $stok_hucresi . '</td>
                            <td class="text-center">' . $stok_durumu . '</td>
                            <td class="text-center">
                                <div class="btn-group">
                                    <a href="urun_detay.php?id=' . $urun['ID'] . '" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="urun_duzenle.php?id=' . $urun['ID'] . '" class="btn btn-sm btn-primary">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="' . $urun['ID'] . '" data-name="' . htmlspecialchars($urun['ADI']) . '">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </td>
                        </tr>';
                    }
                }
                
            } catch (PDOException $stokHata) {
                // Hata durumunda normal stok değerlerini göster
                foreach ($stmt_urunler as $urun) {
                    $html .= '<tr>
                        <td class="text-center">
                            <input type="checkbox" class="select-item" value="' . $urun['ID'] . '">
                        </td>
                        <td>' . htmlspecialchars($urun['KOD']) . '</td>
                        <td><a href="urun_detay.php?id=' . $urun['ID'] . '">' . htmlspecialchars($urun['ADI']) . '</a></td>
                        <td>' . (isset($urun['TIP']) ? htmlspecialchars($urun['TIP']) : '') . '</td>
                        <td>' . (isset($urun['BIRIM']) ? htmlspecialchars($urun['BIRIM']) : '-') . '</td>
                        <td class="text-end">' . (isset($urun['ALIS_FIYAT']) ? number_format($urun['ALIS_FIYAT'], 2, ',', '.') : '0,00') . ' TL</td>
                        <td class="text-end">' . (isset($urun['SATIS_FIYAT']) ? number_format($urun['SATIS_FIYAT'], 2, ',', '.') : '0,00') . ' TL</td>
                        <td class="text-center">' . (isset($urun['MIKTAR']) ? number_format($urun['MIKTAR'], 2, ',', '.') : '0,00') . '</td>
                        <td class="text-center">
                            <span class="badge bg-' . ($urun['DURUM'] == 1 ? 'success' : 'danger') . '">
                                ' . ($urun['DURUM'] == 1 ? 'Aktif' : 'Pasif') . '
                            </span>
                        </td>
                        <td class="text-center">
                            <div class="btn-group">
                                <a href="urun_detay.php?id=' . $urun['ID'] . '" class="btn btn-sm btn-info">
                                    <i class="fas fa-eye"></i>
                                </a>
                                <a href="urun_duzenle.php?id=' . $urun['ID'] . '" class="btn btn-sm btn-primary">
                                    <i class="fas fa-edit"></i>
                                </a>
                                <button type="button" class="btn btn-sm btn-danger delete-btn" data-id="' . $urun['ID'] . '" data-name="' . htmlspecialchars($urun['ADI']) . '">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>';
                }
            }
        } else {
            $html = '<tr><td colspan="10" class="text-center">Hiçbir ürün bulunamadı</td></tr>';
        }
        
        // Sayfalama bilgileri
        $totalPages = ceil($totalRecords / $limit);
        $nextPage = $page + 1;
        
        // Daha fazla sayfa varsa "Daha Fazla Yükle" butonu ekle
        if ($page < $totalPages) {
            $html .= '<tr id="loadMoreRow"><td colspan="10" class="text-center">
                <button type="button" id="loadMoreBtn" class="btn btn-outline-primary btn-sm mt-2" 
                        data-page="' . $nextPage . '" data-search="' . htmlspecialchars($search) . '">
                    <i class="fas fa-sync"></i> Daha Fazla Yükle (' . ($offset + count($stmt_urunler)) . '/' . $totalRecords . ')
                </button>
            </td></tr>';
        }
        
        echo $html;
        
    } catch (PDOException $e) {
        header('HTTP/1.0 500 Internal Server Error');
        echo '<tr><td colspan="10" class="text-center text-danger">Veritabanı hatası: ' . $e->getMessage() . '</td></tr>';
    }
} else {
    header('HTTP/1.0 400 Bad Request');
    echo '<tr><td colspan="10" class="text-center text-danger">Geçersiz istek! Arama ve sayfa parametreleri gerekli.</td></tr>';
}
?> 