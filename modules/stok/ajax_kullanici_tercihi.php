<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Hata raporlama için
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    echo json_encode(['success' => false, 'message' => 'Oturum sonlanmış']);
    exit;
}

$kullanici_id = $_SESSION['user_id'];

// POST verilerini al
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tercihler'])) {
    try {
        $tercihler = json_decode($_POST['tercihler'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception('Geçersiz JSON verisi');
        }
        
        // JSON verilerini stringe çevir
        $jsonTercihler = json_encode($tercihler);
        
        // Tablo varlığını kontrol et ve oluştur
        $db->exec("CREATE TABLE IF NOT EXISTS kullanici_tercihleri (
            id INT AUTO_INCREMENT PRIMARY KEY,
            kullanici_id INT NOT NULL,
            modul VARCHAR(50) NOT NULL,
            ayar_turu VARCHAR(50) NOT NULL,
            ayar_degeri TEXT,
            guncelleme_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_kullanici_ayar (kullanici_id, modul, ayar_turu)
        )");
        
        // Mevcut kayıt var mı kontrol et
        $checkStmt = $db->prepare("SELECT id FROM kullanici_tercihleri WHERE kullanici_id = ? AND modul = 'stok' AND ayar_turu = 'urun_arama_alanlar'");
        $checkStmt->execute([$kullanici_id]);
        
        if ($checkStmt->rowCount() > 0) {
            // Güncelleme yap
            $updateStmt = $db->prepare("UPDATE kullanici_tercihleri SET ayar_degeri = ? WHERE kullanici_id = ? AND modul = 'stok' AND ayar_turu = 'urun_arama_alanlar'");
            $updateStmt->execute([$jsonTercihler, $kullanici_id]);
        } else {
            // Yeni kayıt ekle
            $insertStmt = $db->prepare("INSERT INTO kullanici_tercihleri (kullanici_id, modul, ayar_turu, ayar_degeri) VALUES (?, 'stok', 'urun_arama_alanlar', ?)");
            $insertStmt->execute([$kullanici_id, $jsonTercihler]);
        }
        
        // Oturuma da kaydet
        $_SESSION['urun_arama_alanlar'] = $tercihler;
        
        echo json_encode(['success' => true, 'message' => 'Tercihler kaydedildi']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Hata: ' . $e->getMessage()]);
    }
} else {
    echo json_encode(['success' => false, 'message' => 'Geçersiz istek']);
}
?> 