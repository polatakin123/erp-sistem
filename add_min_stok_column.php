<?php
// Veritabanı bağlantısı
require_once 'config/db.php';

try {
    // MIN_STOK sütununu ekle
    $sql = "ALTER TABLE stok ADD COLUMN MIN_STOK DECIMAL(10,2) DEFAULT 0 NOT NULL";
    $db->exec($sql);
    echo "MIN_STOK sütunu başarıyla eklendi!";
} catch(PDOException $e) {
    echo "Hata: " . $e->getMessage();
}
?> 