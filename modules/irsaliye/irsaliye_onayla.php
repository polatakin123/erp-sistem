<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

// İrsaliye durumunu kontrol et
$query = "SELECT ID, IPTAL, FATURALANDI 
          FROM stk_fis 
          WHERE ID = ? AND TIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE', '20')";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$irsaliye || $irsaliye['IPTAL'] == 1 || $irsaliye['FATURALANDI'] == 1) {
    header('Location: irsaliye_listesi.php');
    exit;
}

// İrsaliyeyi onayla (faturalandı olarak işaretle)
$query = "UPDATE stk_fis SET FATURALANDI = 1 WHERE ID = ?";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);

// Stok hareketleri oluştur ve ürün miktarlarını güncelle
try {
    $db->beginTransaction();

    // İrsaliye kalemlerini getir
    $query = "SELECT * FROM stk_fis_har WHERE STKFISID = ? AND KARTTIPI = 'S'";
    $stmt = $db->prepare($query);
    $stmt->execute([$irsaliye_id]);
    $kalemler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Her kalem için stok hareketi oluştur
    foreach ($kalemler as $kalem) {
        // Ürün stoğunu azalt
        $query = "INSERT INTO stk_urun_miktar (STOKID, DEPOID, MIKTAR, TARIH, ISLEMTIP, REFERANSNO) 
                  VALUES (?, ?, ?, NOW(), 'Çıkış', ?)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $kalem['KARTID'],           // Ürün ID
            $kalem['DEPOID'],           // Depo ID
            -1 * $kalem['MIKTAR'],      // Miktar (eksi olarak giriyor çünkü stoktan çıkış)
            'IRS-' . $irsaliye_id       // Referans No
        ]);
    }

    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    // Hata durumunda da irsaliye detay sayfasına yönlendir
}

// Yönlendir
header('Location: irsaliye_detay.php?id=' . $irsaliye_id);
exit;
?> 