<?php
/**
 * OEM Verileri İçe Aktarma - Tablo Kontrolü
 * 
 * Bu script, OEM verileri içe aktarma işlemi için gerekli tabloların varlığını kontrol eder.
 * Eğer tablolar yoksa, otomatik olarak oluşturur.
 * 
 * Gerekli tablolar:
 * - oem_numbers: OEM numaralarını saklar
 * - alternative_groups: Muadil grupları tanımlar
 * - product_alternatives: Ürünlerin hangi muadil gruplara ait olduğunu belirtir
 */

// Veritabanı bağlantı bilgilerini içe aktarın
require_once 'config/db.php';

echo "<h1>OEM Verileri İçe Aktarma - Tablo Kontrolü</h1>";
echo "<p>Bu sayfa, OEM verileri içe aktarma işlemi için gerekli tabloların durumunu kontrol eder.</p>";

// oem_numbers tablosunun varlığını kontrol et
try {
    $tables = $db->query("SHOW TABLES LIKE 'oem_numbers'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) > 0) {
        echo "<div style='background-color: #dff0d8; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "✅ oem_numbers tablosu mevcut.<br>";
        
        // Tablo yapısını kontrol et
        $columns = $db->query("DESCRIBE oem_numbers")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f2dede; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "❌ oem_numbers tablosu bulunamadı.<br>";
        
        // Tablo oluştur
        echo "oem_numbers tablosu oluşturuluyor...<br>";
        $db->exec("CREATE TABLE oem_numbers (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            oem_no VARCHAR(50) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (product_id, oem_no)
        )");
        echo "✅ oem_numbers tablosu başarıyla oluşturuldu.<br>";
        echo "</div>";
    }
    
    // alternative_groups tablosunun varlığını kontrol et
    $tables = $db->query("SHOW TABLES LIKE 'alternative_groups'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) > 0) {
        echo "<div style='background-color: #dff0d8; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "✅ alternative_groups tablosu mevcut.<br>";
        
        // Tablo yapısını kontrol et
        $columns = $db->query("DESCRIBE alternative_groups")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f2dede; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "❌ alternative_groups tablosu bulunamadı.<br>";
        
        // Tablo oluştur
        echo "alternative_groups tablosu oluşturuluyor...<br>";
        $db->exec("CREATE TABLE alternative_groups (
            id INT AUTO_INCREMENT PRIMARY KEY,
            group_name VARCHAR(100) NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        echo "✅ alternative_groups tablosu başarıyla oluşturuldu.<br>";
        echo "</div>";
    }
    
    // product_alternatives tablosunun varlığını kontrol et
    $tables = $db->query("SHOW TABLES LIKE 'product_alternatives'")->fetchAll(PDO::FETCH_COLUMN);
    if (count($tables) > 0) {
        echo "<div style='background-color: #dff0d8; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "✅ product_alternatives tablosu mevcut.<br>";
        
        // Tablo yapısını kontrol et
        $columns = $db->query("DESCRIBE product_alternatives")->fetchAll(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($columns);
        echo "</pre>";
        echo "</div>";
    } else {
        echo "<div style='background-color: #f2dede; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
        echo "❌ product_alternatives tablosu bulunamadı.<br>";
        
        // Tablo oluştur
        echo "product_alternatives tablosu oluşturuluyor...<br>";
        $db->exec("CREATE TABLE product_alternatives (
            id INT AUTO_INCREMENT PRIMARY KEY,
            product_id INT NOT NULL,
            alternative_group_id INT NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY (product_id, alternative_group_id),
            FOREIGN KEY (alternative_group_id) REFERENCES alternative_groups(id) ON DELETE CASCADE
        )");
        echo "✅ product_alternatives tablosu başarıyla oluşturuldu.<br>";
        echo "</div>";
    }
    
    echo "<div style='background-color: #d9edf7; padding: 10px; margin: 15px 0; border-radius: 5px;'>";
    echo "<h3>Sonuç</h3>";
    echo "<p>Tüm kontroller tamamlandı. Gerekli tablolar kontrol edildi/oluşturuldu.</p>";
    echo "<a href='modules/stok/import_oem_from_stok.php' class='btn btn-primary'>OEM Verilerini İçe Aktar Sayfasına Git</a>";
    echo "</div>";
    
} catch (Exception $e) {
    echo "<div style='background-color: #f2dede; padding: 10px; margin: 5px 0; border-radius: 5px;'>";
    echo "❌ Hata: " . $e->getMessage();
    echo "</div>";
}
?> 