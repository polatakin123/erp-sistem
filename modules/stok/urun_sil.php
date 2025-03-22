<?php
/**
 * ERP Sistem - Ürün Silme İşlemi
 * 
 * Bu dosya ürün silme işlemini gerçekleştirir.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Ürün ID'sini kontrol et
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Geçersiz ürün ID'si.";
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

try {
    // Ürün bilgilerini kontrol et
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Silinecek ürün bulunamadı.";
        header('Location: index.php');
        exit;
    }
    
    $urun = $stmt->fetch();
    
    // İlişkili stok hareketleri var mı kontrol et
    $stmt = $db->prepare("SELECT COUNT(*) FROM stock_movements WHERE product_id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    $hasStokeMovements = ($stmt->fetchColumn() > 0);
    
    // Veritabanı işlemlerini başlat
    $db->beginTransaction();
    
    if ($hasStokeMovements) {
        // Stok hareketleri varsa ürünü pasif hale getir
        $stmt = $db->prepare("UPDATE products SET status = 'passive', updated_at = NOW(), updated_by = :updated_by WHERE id = :id");
        $stmt->bindParam(':updated_by', $_SESSION['user_id']);
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // İşlemi tamamla
        $db->commit();
        
        $_SESSION['warning'] = "Ürüne ait stok hareketleri bulunduğu için ürün pasif hale getirildi.";
    } else {
        // Stok hareketleri yoksa ürünü sil
        $stmt = $db->prepare("DELETE FROM products WHERE id = :id");
        $stmt->bindParam(':id', $id);
        $stmt->execute();
        
        // İşlemi tamamla
        $db->commit();
        
        $_SESSION['success'] = "Ürün başarıyla silindi.";
    }
    
} catch (PDOException $e) {
    // Hata durumunda işlemleri geri al
    $db->rollBack();
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
}

// Ürün listesine yönlendir
header('Location: index.php');
exit;
?> 