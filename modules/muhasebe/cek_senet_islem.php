<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasPermission($_SESSION['user_id'], 'muhasebe_cek_senet')) {
    die(json_encode(['success' => false, 'message' => 'Yetkisiz erişim!']));
}

// POST verilerini kontrol et
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    die(json_encode(['success' => false, 'message' => 'Geçersiz istek!']));
}

// Gerekli alanları kontrol et
$required_fields = ['tip', 'tutar', 'tarih', 'vade_tarihi'];
foreach ($required_fields as $field) {
    if (!isset($_POST[$field]) || empty($_POST[$field])) {
        die(json_encode(['success' => false, 'message' => 'Lütfen tüm zorunlu alanları doldurun!']));
    }
}

$tip = $_POST['tip'];
$tutar = floatval($_POST['tutar']);
$tarih = $_POST['tarih'];
$vade_tarihi = $_POST['vade_tarihi'];
$referans_no = isset($_POST['referans_no']) ? $_POST['referans_no'] : null;
$aciklama = isset($_POST['aciklama']) ? $_POST['aciklama'] : null;

// Geçerli işlem tiplerini kontrol et
$gecerli_tipler = ['cek', 'senet'];
if (!in_array($tip, $gecerli_tipler)) {
    die(json_encode(['success' => false, 'message' => 'Geçersiz işlem tipi!']));
}

// Tutar kontrolü
if ($tutar <= 0) {
    die(json_encode(['success' => false, 'message' => 'Tutar 0\'dan büyük olmalıdır!']));
}

// Tarih kontrolü
if (strtotime($vade_tarihi) < strtotime($tarih)) {
    die(json_encode(['success' => false, 'message' => 'Vade tarihi işlem tarihinden önce olamaz!']));
}

try {
    // İşlem başlat
    $db->beginTransaction();

    // İşlem numarası oluştur
    $yil = date('Y');
    $sql = "SELECT COUNT(*) as total FROM cek_senet WHERE YEAR(created_at) = ?";
    $stmt = $db->prepare($sql);
    $stmt->execute([$yil]);
    $result = $stmt->fetch(PDO::FETCH_ASSOC);
    $islem_no = $yil . str_pad($result['total'] + 1, 6, '0', STR_PAD_LEFT);

    // Çek/Senet kaydı oluştur
    $sql = "INSERT INTO cek_senet (
                islem_no, tip, tutar, tarih, vade_tarihi, 
                referans_no, aciklama, durum,
                created_at, created_by
            ) VALUES (
                ?, ?, ?, ?, ?, 
                ?, ?, 'beklemede',
                NOW(), ?
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $islem_no, $tip, $tutar, $tarih, $vade_tarihi,
        $referans_no, $aciklama, $_SESSION['user_id']
    ]);

    $islem_id = $db->lastInsertId();

    // Muhasebe kaydı oluştur
    $aciklama = "Çek/Senet İşlemi - " . $islem_no;
    
    $sql = "INSERT INTO muhasebe_kayitlari (
                tarih, aciklama, tutar, islem_tipi, referans_id, 
                created_at, created_by
            ) VALUES (
                ?, ?, ?, ?, ?, NOW(), ?
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->execute([
        $tarih, $aciklama, $tutar, $tip, $islem_id, $_SESSION['user_id']
    ]);

    // İşlemi tamamla
    $db->commit();

    echo json_encode([
        'success' => true, 
        'message' => 'İşlem başarıyla kaydedildi.',
        'islem_no' => $islem_no
    ]);

} catch (Exception $e) {
    // Hata durumunda işlemi geri al
    $db->rollBack();
    echo json_encode(['success' => false, 'message' => 'Bir hata oluştu: ' . $e->getMessage()]);
} 