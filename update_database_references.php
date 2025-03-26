<?php
/**
 * ERP Sistem - Tablo Referanslarını Güncelleme Scripti
 * 
 * Bu script, kod tabanındaki İngilizce tablo adı referanslarını 
 * Türkçe karşılıklarıyla değiştirir.
 */

// Zaman aşımı süresini artır
set_time_limit(300);

// Hata raporlamayı aktifleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Veritabanı bağlantısı
require_once 'config/db.php';

// Tablo eşleştirmeleri (İngilizce => Türkçe)
$table_mappings = [
    'users' => 'kullanicilar',
    'user_permissions' => 'kullanici_yetkileri',
    'products' => 'urunler',
    'product_categories' => 'kategoriler',
    'stock_movements' => 'stok_hareketleri',
    'customers' => 'musteriler',
    'suppliers' => 'tedarikciler',
    'sales_invoices' => 'satis_faturalari',
    'sales_invoice_items' => 'satis_fatura_detaylari',
    'purchase_invoices' => 'alis_faturalari',
    'purchase_invoice_items' => 'alis_fatura_detaylari',
    'system_settings' => 'sistem_ayarlari'
];

// Alan eşleştirmeleri (İngilizce => Türkçe)
$field_mappings = [
    // Users tablosu
    'username' => 'kullanici_adi',
    'password' => 'sifre',
    'name' => 'ad',
    'surname' => 'soyad',
    'email' => 'email',
    'role' => 'yetki',
    'status' => 'durum',
    'last_login' => 'son_giris',
    'created_at' => 'olusturma_tarihi',
    
    // Products tablosu
    'code' => 'stok_kodu',
    'description' => 'aciklama',
    'category_id' => 'kategori_id',
    'brand_id' => 'marka_id',
    'unit' => 'birim',
    'current_stock' => 'mevcut_stok',
    'min_stock' => 'min_stok',
    'purchase_price' => 'alis_fiyati',
    'sale_price' => 'satis_fiyati',
    'tax_rate' => 'kdv_orani',
    'special_code1' => 'ozel_kod1',
    'special_code2' => 'ozel_kod2',
    
    // Stock movements tablosu
    'product_id' => 'urun_id',
    'movement_type' => 'hareket_turu',
    'quantity' => 'miktar',
    'unit_price' => 'birim_fiyat',
    'total_amount' => 'toplam_tutar',
    'reference_no' => 'referans_no',
    'reference_type' => 'referans_turu',
    'user_id' => 'kullanici_id',
    'movement_date' => 'hareket_tarihi'
];

// Arama klasörü
$search_dirs = [
    'modules/stok',
    'modules/cari',
    'modules/fatura',
    'modules/ayarlar'
];

// HTML başlangıcı
echo '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Sistem - Tablo Referanslarını Güncelleme</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>
    <div class="container my-5">
        <h1 class="mb-4">Tablo Referanslarını Güncelleme</h1>';

// İşlem yapılıp yapılmadığını kontrol et
$islemYapildi = false;
if (isset($_POST['update_references'])) {
    $islemYapildi = true;
    echo '<div class="alert alert-info">Referanslar güncelleniyor, lütfen bekleyin...</div>';
    
    // Dosyaları tara ve güncelle
    foreach ($search_dirs as $dir) {
        echo "<h2>$dir klasörü taranıyor</h2>";
        updateFilesInDirectory($dir, $table_mappings, $field_mappings);
    }
    
    echo '<div class="alert alert-success">İşlem tamamlandı!</div>';
}

// Form
echo '
        <div class="card">
            <div class="card-body">
                <h5 class="card-title">Tablo Referanslarını Güncelleme</h5>
                <p class="card-text">Bu işlem, kod tabanındaki İngilizce tablo adı referanslarını Türkçe karşılıklarıyla değiştirecektir.</p>
                <p class="card-text text-danger"><strong>Uyarı:</strong> İşlem geri alınamaz. Devam etmeden önce mutlaka yedek alınız!</p>
                
                <form method="post" onsubmit="return confirm(\'Tüm referansları güncellemek istediğinize emin misiniz? Bu işlem geri alınamaz.\');">
                    <button type="submit" name="update_references" class="btn btn-primary" '.($islemYapildi ? 'disabled' : '').'>Referansları Güncelle</button>
                </form>
            </div>
        </div>
        
        <div class="card mt-4">
            <div class="card-header">
                Güncellenecek Tablo Eşleştirmeleri
            </div>
            <div class="card-body">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>İngilizce Tablo Adı</th>
                            <th>Türkçe Tablo Adı</th>
                        </tr>
                    </thead>
                    <tbody>';
                    
foreach ($table_mappings as $eng => $tr) {
    echo "<tr><td>$eng</td><td>$tr</td></tr>";
}

echo '          </tbody>
                </table>
            </div>
        </div>
    </div>
</body>
</html>';

/**
 * Belirtilen dizindeki tüm PHP dosyalarını tarar ve referansları günceller
 */
function updateFilesInDirectory($directory, $table_mappings, $field_mappings) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($directory)
    );
    
    foreach ($files as $file) {
        if ($file->isFile() && $file->getExtension() == 'php') {
            $filePath = $file->getPathname();
            updateFileReferences($filePath, $table_mappings, $field_mappings);
        }
    }
}

/**
 * Belirtilen dosyadaki tablo referanslarını günceller
 */
function updateFileReferences($filePath, $table_mappings, $field_mappings) {
    echo "<p>Dosya işleniyor: $filePath</p>";
    
    // Dosya içeriğini oku
    $content = file_get_contents($filePath);
    if ($content === false) {
        echo "<p class='text-danger'>Dosya okunamadı: $filePath</p>";
        return;
    }
    
    $originalContent = $content;
    $changes = 0;
    
    // Tablo referanslarını güncelle
    foreach ($table_mappings as $eng => $tr) {
        // SQL sorgularında tablo adlarını güncelle
        $pattern = "/\b(FROM|JOIN|INTO|UPDATE|TABLE)\s+`?$eng`?(\s|\(|;)/i";
        $replacement = "$1 `$tr`$2";
        $newContent = preg_replace($pattern, $replacement, $content);
        
        if ($newContent !== $content) {
            $content = $newContent;
            $changes++;
            echo "<p class='text-success'>- '$eng' referansı '$tr' ile değiştirildi</p>";
        }
        
        // CREATE TABLE ifadelerini güncelle
        $pattern = "/CREATE\s+TABLE\s+(IF\s+NOT\s+EXISTS\s+)?`?$eng`?/i";
        $replacement = "CREATE TABLE $1`$tr`";
        $newContent = preg_replace($pattern, $replacement, $content);
        
        if ($newContent !== $content) {
            $content = $newContent;
            $changes++;
            echo "<p class='text-success'>- CREATE TABLE '$eng' ifadesi '$tr' ile değiştirildi</p>";
        }
    }
    
    // Değişiklik yapıldıysa dosyayı güncelle
    if ($content !== $originalContent) {
        if (file_put_contents($filePath, $content) !== false) {
            echo "<p class='text-success'>Dosya güncellendi: $filePath ($changes değişiklik)</p>";
        } else {
            echo "<p class='text-danger'>Dosya güncellenemedi: $filePath</p>";
        }
    } else {
        echo "<p class='text-info'>Değişiklik yok: $filePath</p>";
    }
} 