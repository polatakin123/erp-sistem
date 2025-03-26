<?php
// Veritabanı bağlantısını dahil et
require_once 'config/db.php';

// Hata raporlama ayarları
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

try {
    // Önce TST001 kodlu cari var mı kontrol edelim
    $check = $db->prepare("SELECT ID FROM cari WHERE KOD = ?");
    $check->execute(['TST001']);
    $existing = $check->fetch(PDO::FETCH_ASSOC);
    
    if ($existing) {
        echo "TST001 kodlu cari zaten var. ID: " . $existing['ID'];
        echo "<br><a href='modules/cari/cari_duzenle.php?id={$existing['ID']}' class='btn btn-primary'>Cari Düzenle</a>";
        echo "<br><a href='modules/cari/cari_detay.php?id={$existing['ID']}' class='btn btn-info'>Cari Detay</a>";
        exit;
    }

    // Test için bir cari kaydı ekleyelim
    $sql = "INSERT INTO cari (
        KOD, 
        TIP, 
        ADI, 
        YETKILI_ADI, 
        YETKILI_SOYADI, 
        ADRES, 
        IL, 
        ILCE, 
        POSTA_KODU, 
        TELEFON, 
        CEPNO, 
        FAX, 
        EMAIL, 
        WEB, 
        LIMITTL, 
        VADE, 
        DURUM, 
        NOTLAR
    ) VALUES (
        'TST001', 
        'musteri', 
        'Test Müşteri A.Ş.', 
        'Test', 
        'Kullanıcı', 
        'Test Adres', 
        'İstanbul', 
        'Kadıköy', 
        '34000', 
        '0216 1234567', 
        '0532 1234567', 
        '0216 7654321', 
        'test@test.com', 
        'www.test.com', 
        10000.00, 
        30, 
        1, 
        'Test cari kaydı'
    )";

    $db->exec($sql);
    $cari_id = $db->lastInsertId();
    
    echo "Test cari kaydı başarıyla oluşturuldu. ID: " . $cari_id;
    echo "<br><a href='modules/cari/cari_duzenle.php?id={$cari_id}' class='btn btn-primary'>Cari Düzenle</a>";
    echo "<br><a href='modules/cari/cari_detay.php?id={$cari_id}' class='btn btn-info'>Cari Detay</a>";
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
    
    // Hata ayıklama bilgisi
    echo "<pre>";
    var_dump($e);
    echo "</pre>";
}
?> 