<?php
/**
 * ERP Sistem - Stok Modülü Tablo Oluşturma Scripti
 * 
 * Bu script veritabanında stok modülü için gerekli tabloları kontrol eder ve yoksa oluşturur.
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
require_once 'functions.php';

// Sayfa başlığı
$pageTitle = "Tabloları Oluştur";

// Üst kısmı dahil et
include_once '../../includes/header.php';

// Tablolar ve oluşturma sorguları
$tables = [
    'products' => "CREATE TABLE products (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL,
        name VARCHAR(255) NOT NULL,
        category_id INT(11) DEFAULT NULL,
        unit VARCHAR(50) NOT NULL,
        barcode VARCHAR(100) DEFAULT NULL,
        brand VARCHAR(100) DEFAULT NULL,
        model VARCHAR(100) DEFAULT NULL,
        description TEXT,
        purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00,
        current_stock DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        min_stock DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        status ENUM('active', 'passive') NOT NULL DEFAULT 'active',
        oem_no TEXT DEFAULT NULL,
        cross_reference TEXT DEFAULT NULL,
        dimensions VARCHAR(100) DEFAULT NULL,
        shelf_code VARCHAR(50) DEFAULT NULL,
        vehicle_brand VARCHAR(100) DEFAULT NULL,
        vehicle_model VARCHAR(100) DEFAULT NULL,
        main_category VARCHAR(100) DEFAULT NULL,
        sub_category VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL,
        created_by INT(11) NOT NULL,
        updated_at DATETIME DEFAULT NULL,
        updated_by INT(11) DEFAULT NULL,
        UNIQUE KEY (code)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'stock_movements' => "CREATE TABLE stock_movements (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        movement_type ENUM('giris', 'cikis') NOT NULL,
        quantity DECIMAL(10,2) NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL,
        reference_type VARCHAR(50) DEFAULT NULL,
        reference_no VARCHAR(50) DEFAULT NULL,
        description TEXT,
        user_id INT(11) NOT NULL,
        created_at DATETIME NOT NULL,
        INDEX (product_id),
        INDEX (user_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // OEM numaraları tablosu - Her ürün için tek tek OEM numaralarını saklar
    'oem_numbers' => "CREATE TABLE oem_numbers (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        oem_no VARCHAR(100) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (product_id),
        INDEX (oem_no),
        UNIQUE KEY (product_id, oem_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Muadil ürün grupları tablosu
    'alternative_groups' => "CREATE TABLE alternative_groups (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(100) NOT NULL,
        description TEXT DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    // Ürün-Muadil Grup ilişki tablosu
    'product_alternatives' => "CREATE TABLE product_alternatives (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        product_id INT(11) NOT NULL,
        alternative_group_id INT(11) NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        INDEX (product_id),
        INDEX (alternative_group_id),
        UNIQUE KEY (product_id, alternative_group_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;",
    
    'product_categories' => "CREATE TABLE product_categories (
        id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        description TEXT,
        status ENUM('active', 'passive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;"
];

try {
    // Tablolar için sonuç izleme
    $result = [];
    
    // Her tablo için döngü
    foreach ($tables as $tablo => $sql) {
        // Tablo var mı kontrol et
        $stmt = $db->query("SHOW TABLES LIKE '$tablo'");
        if ($stmt->rowCount() > 0) {
            $result[$tablo] = "Tablo zaten mevcut";
            continue;
        }
        
        // Tabloyu oluştur
        $db->exec($sql);
        $result[$tablo] = "Tablo başarıyla oluşturuldu";
    }
    
    // Sonuçları göster
    echo '<div class="container">';
    echo '<div class="row mt-4">';
    echo '<div class="col-md-12">';
    
    echo '<div class="alert alert-info">';
    echo "<strong>Tablo Kontrol/Oluşturma İşlemi</strong>";
    echo '</div>';
    
    echo '<div class="card shadow mb-4">';
    echo '<div class="card-header py-3">';
    echo '<h6 class="m-0 font-weight-bold text-primary">Tablo Sonuçları</h6>';
    echo '</div>';
    
    echo '<div class="card-body">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered" width="100%" cellspacing="0">';
    echo '<thead><tr><th>Tablo Adı</th><th>Sonuç</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($result as $tablo => $sonuc) {
        $alertClass = strpos($sonuc, 'mevcut') !== false ? 'text-info' : 'text-success';
        echo '<tr>';
        echo '<td>' . $tablo . '</td>';
        echo '<td class="' . $alertClass . '">' . $sonuc . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>'; // table-responsive
    echo '</div>'; // card-body
    
    // Mevcut OEM numaralarını yeni tabloya aktarma (OEM numarası olan ürünler varsa)
    try {
        $hasProductsWithOEM = false;
        $stmt = $db->query("SELECT COUNT(*) as count FROM products WHERE oem_no IS NOT NULL AND oem_no != ''");
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($row['count'] > 0) {
            $hasProductsWithOEM = true;
        }
        
        if ($hasProductsWithOEM) {
            echo '<div class="card-footer">';
            echo '<h6 class="font-weight-bold">Mevcut OEM Numaralarını Yeni Tabloya Aktarma</h6>';
            
            if (isset($_POST['import_oem_data'])) {
                // Mevcut OEM numaralarını al ve yeni tabloya aktar
                $db->beginTransaction();
                
                try {
                    // Ürünleri ve OEM numaralarını al
                    $stmt = $db->query("SELECT id, oem_no FROM products WHERE oem_no IS NOT NULL AND oem_no != ''");
                    $products_with_oem = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $total_imported = 0;
                    $total_groups = 0;
                    
                    foreach ($products_with_oem as $product) {
                        $product_id = $product['id'];
                        $oem_text = $product['oem_no'];
                        
                        // OEM numaralarını işle
                        $oem_result = processOEMNumbers($db, $product_id, $oem_text, false);
                        
                        if ($oem_result['success']) {
                            $total_imported += $oem_result['imported_count'];
                            $total_groups += $oem_result['groups_updated'];
                        }
                    }
                    
                    $db->commit();
                    
                    echo '<div class="alert alert-success mt-3">'; 
                    echo "Toplam " . $total_imported . " OEM numarası ve " . $total_groups . " muadil grup başarıyla aktarıldı.";
                    echo '</div>';
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    echo '<div class="alert alert-danger mt-3">';
                    echo "Veri aktarımı sırasında hata: " . $e->getMessage();
                    echo '</div>';
                }
                
            } else {
                echo '<form method="post" class="mt-3">';
                echo '<div class="alert alert-warning">Mevcut ürünlerin OEM numaralarını yeni tablolara aktarmak için aşağıdaki butona tıklayın:</div>';
                echo '<button type="submit" name="import_oem_data" class="btn btn-warning">OEM Verilerini Aktar</button>';
                echo '</form>';
            }
            
            echo '</div>'; // card-footer
        }
    } catch (Exception $e) {
        echo '<div class="card-footer">';
        echo '<div class="alert alert-danger">OEM veri kontrolü sırasında hata: ' . $e->getMessage() . '</div>';
        echo '</div>';
    }
    
    echo '</div>'; // card
    
    echo '<div class="text-center mb-4">';
    echo '<a href="kategori_ekle.php" class="btn btn-primary"><i class="fas fa-folder"></i> Kategorileri Ekle</a>';
    echo '<a href="urun_test_verileri.php" class="btn btn-success ml-2"><i class="fas fa-plus"></i> Test Ürünlerini Ekle</a>';
    echo '<a href="index.php" class="btn btn-secondary ml-2"><i class="fas fa-list"></i> Ürün Listesine Git</a>';
    echo '</div>';
    
    echo '</div>'; // col
    echo '</div>'; // row
    echo '</div>'; // container
    
} catch (PDOException $e) {
    echo '<div class="container">';
    echo '<div class="row mt-4">';
    echo '<div class="col-md-12">';
    echo '<div class="alert alert-danger" role="alert">';
    echo "<strong>Hata!</strong> Veritabanı hatası: " . $e->getMessage();
    echo '</div>';
    echo '<div class="text-center mb-4">';
    echo '<a href="index.php" class="btn btn-primary"><i class="fas fa-list"></i> Ürün Listesine Git</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 