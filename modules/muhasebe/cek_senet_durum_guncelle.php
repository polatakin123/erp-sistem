<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasPermission($_SESSION['user_id'], 'muhasebe_cek_senet')) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim!']));
}

// POST verilerini kontrol et
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['id']) || !isset($_POST['durum'])) {
    die(json_encode(['success' => false, 'message' => 'Geçersiz istek!']));
}

$islem_id = $_POST['id'];
$yeni_durum = $_POST['durum'];

// Geçerli durumları kontrol et
$gecerli_durumlar = ['beklemede', 'tahsil_edildi', 'odendi', 'iptal'];
if (!in_array($yeni_durum, $gecerli_durumlar)) {
    die(json_encode(['success' => false, 'message' => 'Geçersiz durum!']));
}

try {
    // İşlem bilgilerini getir
    $sql = "SELECT * FROM cek_senet WHERE id = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$islem_id]);
    $islem = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$islem) {
        die(json_encode(['success' => false, 'message' => 'İşlem bulunamadı!']));
    }

    // İşlem başlat
    $db->beginTransaction();

    // Durumu güncelle
    $sql = "UPDATE cek_senet SET 
            durum = ?,
            updated_at = NOW(),
            updated_by = ?
            WHERE id = ?";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([$yeni_durum, $_SESSION['user_id'], $islem_id]);

    // Muhasebe kaydı oluştur
    $aciklama = "Çek/Senet İşlemi Durum Güncellemesi - " . $islem['islem_no'];
    
    if ($yeni_durum == 'tahsil_edildi') {
        // Tahsilat kaydı
        $sql = "INSERT INTO muhasebe_kayitlari (
                    tarih, aciklama, tutar, islem_tipi, referans_id, 
                    created_at, created_by
                ) VALUES (
                    NOW(), ?, ?, 'tahsilat', ?, NOW(), ?
                )";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$aciklama, $islem['tutar'], $islem_id, $_SESSION['user_id']]);
    } 
    elseif ($yeni_durum == 'odendi') {
        // Ödeme kaydı
        $sql = "INSERT INTO muhasebe_kayitlari (
                    tarih, aciklama, tutar, islem_tipi, referans_id, 
                    created_at, created_by
                ) VALUES (
                    NOW(), ?, ?, 'odeme', ?, NOW(), ?
                )";
        
        $stmt = $db->prepare($sql);
        $stmt->execute([$aciklama, $islem['tutar'], $islem_id, $_SESSION['user_id']]);
    }

    // İşlemi tamamla
    $db->commit();

    echo json_encode(['success' => true, 'message' => 'İşlem durumu başarıyla güncellendi.']);

} catch (Exception $e) {
    // Hata durumunda işlemi geri al
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
} 