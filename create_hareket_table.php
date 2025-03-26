<?php
// Veritabanı bağlantısını dahil et
require_once 'config/db.php';

try {
    // cari_hareket tablosunu oluştur
    $sql = "CREATE TABLE IF NOT EXISTS cari_hareket (
        id INT(11) NOT NULL AUTO_INCREMENT,
        cari_id INT(11) NOT NULL,
        islem_no VARCHAR(50) NOT NULL,
        islem_tipi ENUM('borc', 'alacak') NOT NULL,
        tarih DATE NOT NULL,
        tutar DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        aciklama TEXT DEFAULT NULL,
        belge_no VARCHAR(50) DEFAULT NULL,
        belge_tarih DATE DEFAULT NULL,
        vade_tarih DATE DEFAULT NULL,
        odeme_durum TINYINT(1) NOT NULL DEFAULT 0,
        created_by INT(11) DEFAULT NULL,
        updated_by INT(11) DEFAULT NULL,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY cari_id (cari_id),
        KEY islem_no (islem_no),
        CONSTRAINT cari_hareket_ibfk_1 FOREIGN KEY (cari_id) REFERENCES cari (ID) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "cari_hareket tablosu başarıyla oluşturuldu.";
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 