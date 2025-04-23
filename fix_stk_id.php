<?php
require_once 'config/db.php';

try {
    // Veritabanı bağlantısını kontrol et
    if (!$db) {
        die('Veritabanı bağlantısı kurulamadı!');
    }

    // stk_fis tablosunu düzeltme
    $db->beginTransaction();
    
    // Önce AUTO_INCREMENT değerini kaldıralım ve ardından yeniden ekleyelim
    $query_fis = "ALTER TABLE stk_fis MODIFY COLUMN ID int(11) NOT NULL AUTO_INCREMENT";
    $db->exec($query_fis);
    
    echo "stk_fis tablosu düzeltildi: ID alanı AUTO_INCREMENT olarak ayarlandı.<br>";
    
    // stk_fis_har tablosunu düzeltme
    $query_har = "ALTER TABLE stk_fis_har MODIFY COLUMN ID int(11) NOT NULL AUTO_INCREMENT";
    $db->exec($query_har);
    
    echo "stk_fis_har tablosu düzeltildi: ID alanı AUTO_INCREMENT olarak ayarlandı.<br>";
    
    $db->commit();
    
    echo "İşlem başarıyla tamamlandı. Veritabanı tabloları düzeltildi.";
    
} catch (PDOException $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    die("Hata oluştu: " . $e->getMessage());
}
?> 