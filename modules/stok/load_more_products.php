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
            // STK_FIS_HAR tablosundan stok miktarlarını al
            try {
                // Her ürün için güncel stok miktarını hesapla
                $stokMiktarlari = [];
                
                // Ürün ID'lerini al
                $urunIds = [];
                foreach ($stmt_urunler as $urun) {
                    $urunIds[] = $urun['ID'];
                }
                
                if (!empty($urunIds)) {
                    // Debug bilgisi ekle
                    $debug_info = '<div class="alert alert-info">Ürün ID\'leri: ' . implode(',', $urunIds) . '</div>';
                    
                    // Stok hareketlerinden miktarları hesapla
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
                    
                    // SQL sorgusunu debug bilgisine ekle
                    $debug_info .= '<div class="alert alert-info">SQL Sorgusu: ' . $stokSql . '</div>';
                    
                    try {
                        $stokStmt = $db->query($stokSql);
                        
                        // Sonuç bilgilerini debug bilgisine ekle
                        $debug_info .= '<div class="alert alert-info">Sonuç Sayısı: ' . $stokStmt->rowCount() . '</div>';
                        
                        // Bulunan stok hareketlerini göster
                        $debug_info .= '<div class="alert alert-info">Stok Hareketleri:<br>';
                        $debug_stokData = [];
                        
                        while ($stokRow = $stokStmt->fetch(PDO::FETCH_ASSOC)) {
                            $stokMiktarlari[$stokRow['KARTID']] = $stokRow['GIRIS_MIKTAR'] - $stokRow['CIKIS_MIKTAR'];
                            $debug_stokData[] = "KARTID: " . $stokRow['KARTID'] . 
                                ", GİRİŞ: " . $stokRow['GIRIS_MIKTAR'] . 
                                ", ÇIKIŞ: " . $stokRow['CIKIS_MIKTAR'] . 
                                ", NET: " . ($stokRow['GIRIS_MIKTAR'] - $stokRow['CIKIS_MIKTAR']);
                        }
                        
                        if (!empty($debug_stokData)) {
                            $debug_info .= implode('<br>', $debug_stokData);
                        } else {
                            $debug_info .= 'Hiç stok hareketi bulunamadı!';
                        }
                        $debug_info .= '</div>';
                        
                    } catch (PDOException $stokHata) {
                        // Stok miktarları hesaplanamadı, varsayılan değerleri kullan
                        error_log("Stok miktarları hesaplanırken hata: " . $stokHata->getMessage());
                        $debug_info .= '<div class="alert alert-danger">Hata: ' . $stokHata->getMessage() . '</div>';
                    }
                    
                    // Debug bilgisini ekrana yazdır
                    $html .= '<tr><td colspan="10">' . $debug_info . '</td></tr>';
                }
                
                // Stok tablosundan temel bilgileri göster, ama gerçek stok miktarları için STK_FIS_HAR tablosunu kullan
                foreach ($stmt_urunler as $urun) {
                    $guncel_stok = isset($stokMiktarlari[$urun['ID']]) ? $stokMiktarlari[$urun['ID']] : 0;
                    $sistem_stok = isset($urun['MIKTAR']) ? (float)$urun['MIKTAR'] : 0;
                    
                    // Stok hücresi içeriğini hazırla
                    $stok_hucresi = '';
                    if ($sistem_stok != $guncel_stok) {
                        $stok_hucresi = '<span class="text-success">' . number_format($guncel_stok, 2, ',', '.') . '</span>';
                        $stok_hucresi .= '<br><small class="text-muted">Sistem: ' . number_format($sistem_stok, 2, ',', '.') . '</small>';
                    } else {
                        $stok_hucresi = number_format($guncel_stok, 2, ',', '.');
                    }
                    
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