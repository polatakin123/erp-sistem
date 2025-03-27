<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Performans ölçümü
$performans_olcum = [];
$toplam_sure = microtime(true);

function olcumBaslat($islem_adi) {
    global $performans_olcum;
    $performans_olcum[$islem_adi] = ['baslangic' => microtime(true), 'bitis' => 0, 'sure' => 0];
}

function olcumBitir($islem_adi) {
    global $performans_olcum;
    if (isset($performans_olcum[$islem_adi])) {
        $performans_olcum[$islem_adi]['bitis'] = microtime(true);
        $performans_olcum[$islem_adi]['sure'] = $performans_olcum[$islem_adi]['bitis'] - $performans_olcum[$islem_adi]['baslangic'];
    }
}

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Oturum zaman aşımına uğradı']);
    exit;
}

// Arama parametresi kontrolü
if (!isset($_GET['q']) || strlen($_GET['q']) < 2) {
    header('Content-Type: application/json');
    echo json_encode([]);
    exit;
}

$search = '%' . $_GET['q'] . '%';

try {
    // Ürün ara
    olcumBaslat('urunleri_ara');
    
    // Önce sadece temel ürün bilgilerini getirelim
    $query = "SELECT s.ID, s.KOD as kod, s.ADI as ad
             FROM stok s 
             WHERE s.DURUM = 1 AND (s.KOD LIKE ? OR s.ADI LIKE ?)
             ORDER BY 
                CASE WHEN s.KOD LIKE ? THEN 0 
                     WHEN s.KOD LIKE ? THEN 1
                     ELSE 2 
                END,
                s.ADI
             LIMIT 30";
    
    $starts_with = $_GET['q'] . '%';
    
    $stmt = $db->prepare($query);
    $stmt->execute([$search, $search, $starts_with, $starts_with]);
    $urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Sonra her ürün için BIRIMID ve fiyat bilgilerini ekleyelim
    olcumBaslat('urun_detaylari');
    foreach ($urunler as &$urun) {
        // Birim ID'si
        $query_birim = "SELECT ID FROM stk_birim WHERE STOKID = ? LIMIT 1";
        $stmt_birim = $db->prepare($query_birim);
        $stmt_birim->execute([$urun['ID']]);
        $urun['BIRIMID'] = $stmt_birim->fetchColumn();
        
        // Fiyat
        $query_fiyat = "SELECT FIYAT FROM stk_fiyat WHERE STOKID = ? AND TIP = 'S' LIMIT 1";
        $stmt_fiyat = $db->prepare($query_fiyat);
        $stmt_fiyat->execute([$urun['ID']]);
        $urun['fiyat'] = $stmt_fiyat->fetchColumn();
    }
    olcumBitir('urun_detaylari');
    
    // Birim bilgilerini ekle
    olcumBaslat('birim_bilgileri');
    foreach ($urunler as &$urun) {
        $urun['birim'] = getBirim($urun['BIRIMID']);
    }
    olcumBitir('birim_bilgileri');
    
    olcumBitir('urunleri_ara');
    
    // Toplam süre
    $toplam_sure_sonu = microtime(true);
    $toplam_gecen_sure = $toplam_sure_sonu - $toplam_sure;
    
    // Performans bilgisini ekleyin
    $performans_sonucu = [
        'toplam_sure' => number_format($toplam_gecen_sure, 4),
        'sureler' => $performans_olcum,
        'urun_sayisi' => count($urunler)
    ];
    
    header('Content-Type: application/json');
    echo json_encode([
        'urunler' => $urunler,
        'performans' => $performans_sonucu
    ]);
    
} catch (Exception $e) {
    header('Content-Type: application/json');
    echo json_encode(['error' => $e->getMessage()]);
} 