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
        throw new Exception('İrsaliye iptal edilemez durumda.');
    }

    // İrsaliye durumunu güncelle
    $query = "UPDATE irsaliyeler SET durum = 'İptal', iptal_eden_id = :iptal_eden_id, iptal_tarihi = NOW() 
              WHERE id = :id";
    $stmt = $db->prepare($query);
    $stmt->execute([
        'id' => $irsaliye_id,
        'iptal_eden_id' => $_SESSION['user_id']
    ]);

    $db->commit();
    $_SESSION['success'] = 'İrsaliye başarıyla iptal edildi.';
} catch (Exception $e) {
    $db->rollBack();
    $_SESSION['error'] = 'Hata oluştu: ' . $e->getMessage();
}

header('Location: irsaliye_detay.php?id=' . $irsaliye_id);
exit; 