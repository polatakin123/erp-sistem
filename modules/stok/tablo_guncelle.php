<?php
/**
 * ERP Sistem - Stok Modülü Tablo Güncelleme Scripti
 * 
 * Bu script veritabanında stok modülü için mevcut tabloları günceller.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sayfa başlığı
$pageTitle = "Tabloları Güncelle";

// Üst kısmı dahil et
include_once '../../includes/header.php';

// Tablo güncelleme işlemleri
$updates = [];

// Güncelleme işlemi için buton kontrolü
$guncelle = isset($_POST['guncelle']) ? true : false;

if ($guncelle) {
    try {
        // Products tablosuna yeni alanları ekle
        $columns_to_add = [
            'oem_no' => 'ALTER TABLE products ADD COLUMN oem_no VARCHAR(100) DEFAULT NULL',
            'cross_reference' => 'ALTER TABLE products ADD COLUMN cross_reference TEXT DEFAULT NULL',
            'dimensions' => 'ALTER TABLE products ADD COLUMN dimensions VARCHAR(100) DEFAULT NULL',
            'shelf_code' => 'ALTER TABLE products ADD COLUMN shelf_code VARCHAR(50) DEFAULT NULL',
            'vehicle_brand' => 'ALTER TABLE products ADD COLUMN vehicle_brand VARCHAR(100) DEFAULT NULL',
            'vehicle_model' => 'ALTER TABLE products ADD COLUMN vehicle_model VARCHAR(100) DEFAULT NULL',
            'main_category' => 'ALTER TABLE products ADD COLUMN main_category VARCHAR(100) DEFAULT NULL',
            'sub_category' => 'ALTER TABLE products ADD COLUMN sub_category VARCHAR(100) DEFAULT NULL'
        ];

        foreach ($columns_to_add as $column => $sql) {
            try {
                // Sütun var mı kontrol et
                $check_column = $db->query("SHOW COLUMNS FROM products LIKE '$column'");
                if ($check_column->rowCount() == 0) {
                    // Sütun yoksa ekle
                    $db->exec($sql);
                    $updates[$column] = "Sütun başarıyla eklendi";
                } else {
                    $updates[$column] = "Sütun zaten mevcut";
                }
            } catch (PDOException $e) {
                $updates[$column] = "Hata: " . $e->getMessage();
            }
        }
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
    }
}

// Tablonun durumunu kontrol et
$kategorilerTablo = [];
try {
    // Kategoriler tablosunun yapısını al
    $stmt = $db->query("SHOW TABLES LIKE 'product_categories'");
    $kategorilerTabloVar = $stmt->rowCount() > 0;
    
    if ($kategorilerTabloVar) {
        // Sütunları kontrol et
        $stmt = $db->query("SHOW COLUMNS FROM product_categories");
        $kategorilerTablo = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    // Kullanıcı tablosunu kontrol et
    $stmt = $db->query("SHOW TABLES LIKE 'users'");
    $kullaniciTabloVar = $stmt->rowCount() > 0;
    
    if ($kullaniciTabloVar) {
        // Kullanıcı sayısını kontrol et
        $stmt = $db->query("SELECT COUNT(*) FROM users");
        $kullaniciSayisi = $stmt->fetchColumn();
    } else {
        $kullaniciSayisi = 0;
    }
    
    // Foreign key kısıtlamalarını kontrol et
    $stmt = $db->query("
        SELECT CONSTRAINT_NAME
        FROM information_schema.TABLE_CONSTRAINTS
        WHERE TABLE_SCHEMA = 'erp_sistem'
        AND TABLE_NAME = 'product_categories'
        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
    ");
    $fkKisitlamalari = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $hata = "Veritabanı hatası: " . $e->getMessage();
}

// Sayfa içeriği
echo '<div class="container">';
echo '<div class="row mt-4">';
echo '<div class="col-md-12">';

if (isset($error)) {
    echo '<div class="alert alert-danger" role="alert">';
    echo "<strong>Hata!</strong> " . $error;
    echo '</div>';
} elseif (!empty($updates)) {
    echo '<div class="alert alert-success" role="alert">';
    echo "<strong>İşlem Tamamlandı!</strong> Tablolar başarıyla güncellendi.";
    echo '</div>';
}

echo '<div class="card shadow mb-4">';
echo '<div class="card-header py-3">';
echo '<h6 class="m-0 font-weight-bold text-primary">Tablo Güncelleme İşlemleri</h6>';
echo '</div>';

echo '<div class="card-body">';

if (!empty($updates)) {
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered" width="100%" cellspacing="0">';
    echo '<thead><tr><th>Sütun Adı</th><th>Sonuç</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($updates as $column => $sonuc) {
        $alertClass = strpos($sonuc, 'Hata') !== false ? 'text-danger' : (strpos($sonuc, 'mevcut') !== false ? 'text-info' : 'text-success');
        echo '<tr>';
        echo '<td>' . $column . '</td>';
        echo '<td class="' . $alertClass . '">' . $sonuc . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>'; // table-responsive
} else {
    echo '<p>Ürün tablosuna yeni alanlar eklemek için aşağıdaki butonu kullanın:</p>';
    echo '<ul>';
    echo '<li><strong>OEM No (oem_no)</strong>: Orijinal ekipman üreticisi numarası</li>';
    echo '<li><strong>Çapraz Referans (cross_reference)</strong>: Alternatif veya uyumlu parça numaraları</li>';
    echo '<li><strong>Ürün Ölçüleri (dimensions)</strong>: Parçanın boyutları</li>';
    echo '<li><strong>Raf Kodu (shelf_code)</strong>: Depo yerleşim bilgisi</li>';
    echo '<li><strong>Araç Markası (vehicle_brand)</strong>: Uyumlu araç markası</li>';
    echo '<li><strong>Araç Modeli (vehicle_model)</strong>: Uyumlu araç modeli</li>';
    echo '<li><strong>Ana Kategori (main_category)</strong>: Ürünün ana kategorisi</li>';
    echo '<li><strong>Alt Kategori (sub_category)</strong>: Ürünün alt kategorisi</li>';
    echo '</ul>';
    
    echo '<form action="' . htmlspecialchars($_SERVER["PHP_SELF"]) . '" method="post" class="mt-3">';
    echo '<button type="submit" name="guncelle" class="btn btn-primary"><i class="fas fa-sync"></i> Tabloları Güncelle</button>';
    echo '</form>';
}

echo '</div>'; // card-body

echo '<div class="card-footer">';
echo '<div class="text-center">';
echo '<a href="index.php" class="btn btn-secondary"><i class="fas fa-arrow-left"></i> Ürün Listesine Dön</a>';
echo '</div>';
echo '</div>';

echo '</div>'; // card
echo '</div>'; // col
echo '</div>'; // row
echo '</div>'; // container

// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 