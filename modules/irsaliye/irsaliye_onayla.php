<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// İrsaliye ID kontrolü
if (!isset($_GET['id'])) {
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

try {
    $db->beginTransaction();

    // İrsaliye durumunu kontrol et
    $query = "SELECT durum FROM irsaliyeler WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $irsaliye_id]);
    $irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$irsaliye || $irsaliye['durum'] != 'Beklemede') {
        throw new Exception('İrsaliye onaylanamaz durumda.');
    }

    // İrsaliye durumunu güncelle
    $query = "UPDATE irsaliyeler SET durum = 'Onaylandı', onaylayan_id = :onaylayan_id, onay_tarihi = NOW() 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'id' => $irsaliye_id,
        'onaylayan_id' => $_SESSION['user_id']
    ]);

    // Stok hareketlerini oluştur
    $query = "SELECT ik.*, u.stok_kodu 
              FROM irsaliye_kalemleri ik 
              LEFT JOIN urunler u ON ik.urun_id = u.id 
              WHERE ik.irsaliye_id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute(['id' => $irsaliye_id]);
    $kalemler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($kalemler as $kalem) {
        // Stok hareketi oluştur
        $query = "INSERT INTO stok_hareketleri (stok_kodu, hareket_tipi, miktar, birim_fiyat, referans_id, referans_tipi) 
                  VALUES (:stok_kodu, 'Cikis', :miktar, :birim_fiyat, :referans_id, 'Irsaliye')";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'stok_kodu' => $kalem['stok_kodu'],
            'miktar' => $kalem['miktar'],
            'birim_fiyat' => $kalem['birim_fiyat'],
            'referans_id' => $irsaliye_id
        ]);

        // Stok miktarını güncelle
        $query = "UPDATE stok SET miktar = miktar - :miktar WHERE stok_kodu = :stok_kodu";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'stok_kodu' => $kalem['stok_kodu'],
            'miktar' => $kalem['miktar']
        ]);
    }

    $db->commit();
    $_SESSION['success'] = 'İrsaliye başarıyla onaylandı.';
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Hata oluştu: ' . $e->getMessage();
}

header('Location: irsaliye_detay.php?id=' . $irsaliye_id);
exit; 