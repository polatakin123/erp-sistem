<?php
// Veritabanı bağlantısı
require_once 'config/db.php';

try {
    // GUNCELLEME_TARIHI sütununu ekle
    $sql = "ALTER TABLE stok ADD COLUMN GUNCELLEME_TARIHI DATETIME DEFAULT NULL";
    $db->exec($sql);
    echo "GUNCELLEME_TARIHI sütunu başarıyla eklendi!";
} catch(PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?> 