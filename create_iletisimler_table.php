<?php
// Veritabanı bağlantısını dahil et
require_once 'config/db.php';

try {
    // cari_iletisimler tablosunu oluştur
    $sql = "CREATE TABLE IF NOT EXISTS cari_iletisimler (
        id INT(11) NOT NULL AUTO_INCREMENT,
        cari_id INT(11) NOT NULL,
        iletisim_tipi VARCHAR(50) NOT NULL COMMENT 'telefon, email, fax, adres gibi',
        deger VARCHAR(255) NOT NULL,
        aciklama VARCHAR(255) DEFAULT NULL,
        varsayilan TINYINT(1) NOT NULL DEFAULT 0,
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        created_at TIMESTAMP NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY cari_id (cari_id),
        CONSTRAINT cari_iletisimler_ibfk_1 FOREIGN KEY (cari_id) REFERENCES cari (ID) ON DELETE CASCADE ON UPDATE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

    $db->exec($sql);
    echo "cari_iletisimler tablosu başarıyla oluşturuldu.";
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 