<?php
/**
 * ERP Sistem - Kullanıcı Tercihleri Tablosu Oluşturma
 * 
 * Bu dosya, kullanıcı tercihlerini saklamak için gerekli tabloyu oluşturur.
 */

// Gerekli dosyaları dahil et
require_once '../../config/db.php';

try {
    // Kullanıcı tercihleri tablosunu oluştur
    $sql = "CREATE TABLE IF NOT EXISTS kullanici_tercihleri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_id INT NOT NULL,
        modul VARCHAR(50) NOT NULL,
        ayar_turu VARCHAR(50) NOT NULL,
        ayar_degeri TEXT,
        guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_kullanici_ayar (kullanici_id, modul, ayar_turu)
    )";
    
    $db->exec($sql);
    echo "Kullanıcı Tercihleri tablosu başarıyla oluşturuldu!";
    
} catch(PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?> 