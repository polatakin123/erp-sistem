<?php
require_once 'config/db.php';

try {
    $sql = "CREATE TABLE IF NOT EXISTS `barkodlar` (
        `id` int(11) NOT NULL AUTO_INCREMENT,
        `stok_id` int(11) NOT NULL,
        `barkod` varchar(50) NOT NULL,
        `aktif` tinyint(1) NOT NULL DEFAULT 1,
        `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
        `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
        PRIMARY KEY (`id`),
        UNIQUE KEY `barkod` (`barkod`),
        KEY `stok_id` (`stok_id`),
        CONSTRAINT `barkodlar_ibfk_1` FOREIGN KEY (`stok_id`) REFERENCES `stok` (`ID`) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;";
    
    $db->exec($sql);
    echo "Barkod tablosu başarıyla oluşturuldu.";
} catch(PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?> 