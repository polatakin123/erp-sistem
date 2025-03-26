<?php
// Veritabanı bağlantı bilgilerini içe aktarın
require_once 'config/db.php';

echo "<h2>OEM Numbers Tablosu Düzeltme İşlemi</h2>";

try {
    // İşlem başlat
    $db->beginTransaction();
    
    // 1. Adım: Foreign key kısıtlamasını kaldır
    echo "<p>1. Foreign key kısıtlamasını kaldırma işlemi başlatılıyor...</p>";
    $db->exec("ALTER TABLE oem_numbers DROP FOREIGN KEY oem_numbers_ibfk_1");
    echo "<p style='color: green;'>✅ Foreign key kısıtlaması başarıyla kaldırıldı.</p>";
    
    // 2. Adım: İndeksi kaldır
    echo "<p>2. product_id indeksini değiştirme işlemi başlatılıyor...</p>";
    $db->exec("ALTER TABLE oem_numbers DROP INDEX product_oem_unique");
    $db->exec("ALTER TABLE oem_numbers ADD UNIQUE INDEX product_oem_unique (product_id, oem_no)");
    echo "<p style='color: green;'>✅ product_id indeksi başarıyla güncellendi.</p>";
    
    // İşlemi tamamla
    $db->commit();
    
    // Sonuç
    echo "<div style='background-color: #dff0d8; padding: 15px; margin-top: 20px; border-radius: 4px;'>";
    echo "<h3>OEM Numbers Tablosu Düzeltme İşlemi Tamamlandı</h3>";
    echo "<p>OEM Numbers tablosu artık stok tablosundaki ürün ID'lerini kullanabilir.</p>";
    echo "<p><a href='modules/stok/import_oem_from_stok.php' style='display: inline-block; padding: 10px 15px; background-color: #5cb85c; color: #fff; text-decoration: none; border-radius: 4px;'>OEM Verilerini İçe Aktar Sayfasına Git</a></p>";
    echo "</div>";
    
    // Yeni tablo yapısını göster
    $table_info = $db->query("SHOW CREATE TABLE oem_numbers")->fetch(PDO::FETCH_ASSOC);
    echo "<h3>Yeni Tablo Yapısı</h3>";
    echo "<pre style='background-color: #f8f8f8; padding: 10px; border: 1px solid #ddd; overflow: auto;'>" . htmlspecialchars($table_info['Create Table']) . "</pre>";
    
} catch (Exception $e) {
    // Hata durumunda işlemi geri al
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    
    echo "<div style='background-color: #f2dede; padding: 15px; margin-top: 20px; border-radius: 4px;'>";
    echo "<h3>Hata!</h3>";
    echo "<p>" . $e->getMessage() . "</p>";
    echo "</div>";
}

// Arayüz iyileştirmesi
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    h2, h3 { color: #333; }
</style>";
?> 