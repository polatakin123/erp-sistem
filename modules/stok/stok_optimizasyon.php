<?php
/**
 * ERP Sistem - Stok Modülü Veritabanı Optimizasyonu
 * 
 * Bu dosya stok modülünde yapılan aramaların performansını artırmak için 
 * gerekli indeksleri ve optimizasyonları oluşturur.
 */

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Oturum kontrolü
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Sadece admin yetkisi olanlar bu betiği çalıştırabilir
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    echo "Bu işlemi yapmak için yetkiniz bulunmamaktadır.";
    exit;
}

// Hata raporlama ayarları
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Zaman aşımı süresini 10 dakikaya çıkar
ini_set('max_execution_time', 600);
set_time_limit(600);

// Güvenli SQL çalıştırma fonksiyonu
function guvenli_sql_calistir($db, $sql, $aciklama) {
    echo "<p><strong>İŞLEM:</strong> $aciklama</p>";
    
    try {
        $stmt = $db->prepare($sql);
        $stmt->execute();
        echo "<p class='text-success'>✓ Başarılı</p>";
        return true;
    } catch (PDOException $e) {
        echo "<p class='text-danger'>✗ Hata: " . $e->getMessage() . "</p>";
        return false;
    }
    echo "<hr>";
}

// İşlem türünü belirle
$islem = isset($_GET['islem']) ? $_GET['islem'] : 'hepsi';

// HTML başlık
echo '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Modülü Veritabanı Optimizasyonu</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .text-success { color: green; }
        .text-danger { color: red; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stok Modülü Veritabanı Optimizasyonu</h1>
        <div class="alert alert-warning">
            <strong>Dikkat!</strong> Bu işlem veritabanında değişiklikler yapacak ve biraz zaman alabilir.
        </div>';

// İndeks işlemleri menüsü
echo '<div class="mb-4">
    <h4>İndeks İşlemleri</h4>
    <div class="btn-group mb-3">
        <a href="?islem=stok_kod" class="btn btn-outline-primary">Stok Kodu İndeksi</a>
        <a href="?islem=stok_adi" class="btn btn-outline-primary">Stok Adı İndeksi</a>
        <a href="?islem=urun_miktar" class="btn btn-outline-primary">Ürün Miktar İndeksi</a>
        <a href="?islem=stk_fiyat" class="btn btn-outline-primary">Fiyat İndeksi</a>
        <a href="?islem=analiz" class="btn btn-outline-info">Tablo Analizi</a>
        <a href="?islem=hepsi" class="btn btn-danger">Tümünü Çalıştır</a>
    </div>
</div>';

echo '<div class="card">
    <div class="card-body">';

// Seçilen işlemi çalıştır
$basarili = true;

switch ($islem) {
    case 'stok_kod':
        $basarili = guvenli_sql_calistir($db, "CREATE INDEX idx_stok_kod ON stok (KOD)", 
                    "stok tablosunda KOD alanı için indeks oluşturuluyor");
        break;
        
    case 'stok_adi':
        $basarili = guvenli_sql_calistir($db, "CREATE INDEX idx_stok_adi ON stok (ADI)", 
                    "stok tablosunda ADI alanı için indeks oluşturuluyor");
        break;
        
    case 'urun_miktar':
        $basarili = guvenli_sql_calistir($db, "CREATE INDEX idx_stk_urun_miktar ON STK_URUN_MIKTAR (URUN_ID, MIKTAR)", 
                    "STK_URUN_MIKTAR tablosunda URUN_ID ve MIKTAR alanları için indeks oluşturuluyor");
        break;
        
    case 'stk_fiyat':
        $basarili = guvenli_sql_calistir($db, "CREATE INDEX idx_stk_fiyat ON stk_fiyat (STOKID, TIP)", 
                    "stk_fiyat tablosunda STOKID ve TIP alanları için indeks oluşturuluyor");
        break;
        
    case 'analiz':
        $basarili = guvenli_sql_calistir($db, "ANALYZE TABLE stok, STK_URUN_MIKTAR, stk_fiyat", 
                    "İstatistikler güncelleniyor");
        break;
        
    case 'hepsi':
    default:
        echo "<h4>Tüm İndeksler Oluşturuluyor</h4>";
        
        // Her bir indeksi ayrı ayrı oluştur, hata olsa bile devam et
        $indeksler = [
            ["CREATE INDEX idx_stok_kod ON stok (KOD)", "stok tablosunda KOD alanı için indeks oluşturuluyor"],
            ["CREATE INDEX idx_stok_adi ON stok (ADI)", "stok tablosunda ADI alanı için indeks oluşturuluyor"],
            ["CREATE INDEX idx_stk_urun_miktar ON STK_URUN_MIKTAR (URUN_ID, MIKTAR)", "STK_URUN_MIKTAR tablosunda URUN_ID ve MIKTAR alanları için indeks oluşturuluyor"],
            ["CREATE INDEX idx_stk_fiyat ON stk_fiyat (STOKID, TIP)", "stk_fiyat tablosunda STOKID ve TIP alanları için indeks oluşturuluyor"],
            ["ANALYZE TABLE stok, STK_URUN_MIKTAR, stk_fiyat", "İstatistikler güncelleniyor"]
        ];
        
        foreach ($indeksler as $indeks) {
            $sonuc = guvenli_sql_calistir($db, $indeks[0], $indeks[1]);
            if (!$sonuc) {
                $basarili = false;
            }
            echo "<hr>";
            // Her işlem arasında biraz bekle
            flush();
            ob_flush();
            sleep(1);
        }
        break;
}

echo '    </div>
</div>';

// İşlem sonucu
if ($basarili) {
    echo '<div class="alert alert-success mt-3">
        <strong>İşlem tamamlandı!</strong> Seçilen işlem başarıyla tamamlandı.
    </div>';
} else {
    echo '<div class="alert alert-warning mt-3">
        <strong>Dikkat!</strong> Bazı işlemlerde hatalar oluştu. Lütfen hata mesajlarını kontrol edin.
    </div>';
}

echo '<div class="mt-3">
    <a href="urun_arama.php" class="btn btn-primary">Ürün Arama Sayfasına Dön</a>
</div>
</div>
</body>
</html>'; 