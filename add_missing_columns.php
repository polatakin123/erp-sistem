<?php
// Veritabanı bağlantısını dahil et
require_once 'config/db.php';

try {
    // Eksik sütunları kontrol et ve ekle
    $columns = [
        'YETKILI_ADI' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS YETKILI_ADI VARCHAR(255)",
        'YETKILI_SOYADI' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS YETKILI_SOYADI VARCHAR(255)",
        'ADRES' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS ADRES TEXT",
        'IL' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS IL VARCHAR(100)",
        'ILCE' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS ILCE VARCHAR(100)",
        'POSTA_KODU' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS POSTA_KODU VARCHAR(20)",
        'TELEFON' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS TELEFON VARCHAR(20)",
        'CEPNO' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS CEPNO VARCHAR(20)",
        'FAX' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS FAX VARCHAR(20)",
        'EMAIL' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS EMAIL VARCHAR(100)",
        'WEB' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS WEB VARCHAR(255)",
        'LIMITTL' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS LIMITTL DECIMAL(18,2) DEFAULT 0",
        'VADE' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS VADE INT DEFAULT 0",
        'NOTLAR' => "ALTER TABLE cari ADD COLUMN IF NOT EXISTS NOTLAR TEXT"
    ];

    $added_columns = [];
    
    // Sütunları ekle
    foreach ($columns as $column => $sql) {
        try {
            $db->exec($sql);
            $added_columns[] = $column;
        } catch (PDOException $e) {
            echo "Sütun eklenemedi: $column - " . $e->getMessage() . "<br>";
        }
    }

    if (count($added_columns) > 0) {
        echo "Aşağıdaki sütunlar başarıyla eklendi:<br>";
        echo implode("<br>", $added_columns);
    } else {
        echo "Hiçbir sütun eklenmedi.";
    }
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 