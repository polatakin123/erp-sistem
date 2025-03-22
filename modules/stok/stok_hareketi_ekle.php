<?php
/**
 * ERP Sistem - Stok Hareketi Ekleme İşlemi
 * 
 * Bu dosya stok hareketi ekleme işlemini gerçekleştirir.
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

// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    redirect('index.php');
}

// Form verilerini al
$urun_id = isset($_POST['urun_id']) ? (int)$_POST['urun_id'] : 0;
$hareket_tipi = isset($_POST['hareket_tipi']) ? clean($_POST['hareket_tipi']) : '';
$miktar = isset($_POST['miktar']) ? (float)str_replace(',', '.', $_POST['miktar']) : 0;
$birim_fiyat = isset($_POST['birim_fiyat']) ? (float)str_replace(',', '.', $_POST['birim_fiyat']) : 0;
$referans_tip = isset($_POST['referans_tip']) ? clean($_POST['referans_tip']) : '';
$referans_no = isset($_POST['referans_no']) ? clean($_POST['referans_no']) : '';
$aciklama = isset($_POST['aciklama']) ? clean($_POST['aciklama']) : '';

// Hata kontrolü
$error = '';

if ($urun_id <= 0) {
    $error = "Geçersiz ürün ID'si.";
} elseif (!in_array($hareket_tipi, ['giris', 'cikis'])) {
    $error = "Geçersiz hareket tipi.";
} elseif ($miktar <= 0) {
    $error = "Miktar 0'dan büyük olmalıdır.";
} elseif ($birim_fiyat < 0) {
    $error = "Birim fiyat negatif olamaz.";
}

// Hata varsa geri dön
if (!empty($error)) {
    $_SESSION['error'] = $error;
    redirect('urun_duzenle.php?id=' . $urun_id);
}

try {
    // Ürün bilgilerini al
    $stmt = $db->prepare("SELECT current_stock FROM products WHERE id = :id");
    $stmt->bindParam(':id', $urun_id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Ürün bulunamadı.";
        redirect('index.php');
    }
    
    $urun = $stmt->fetch();
    $mevcut_stok = $urun['current_stock'];
    
    // Çıkış işlemi için stok kontrolü
    if ($hareket_tipi == 'cikis' && $miktar > $mevcut_stok) {
        $_SESSION['error'] = "Yetersiz stok. Mevcut stok: " . $mevcut_stok;
        redirect('urun_duzenle.php?id=' . $urun_id);
    }
    
    // Veritabanı işlemlerini başlat
    $db->beginTransaction();
    
    // Stok hareketi ekle
    $sql = "INSERT INTO stock_movements (
                product_id, 
                movement_type, 
                quantity, 
                unit_price, 
                reference_type, 
                reference_no, 
                description, 
                user_id, 
                created_at
            ) VALUES (
                :urun_id, 
                :hareket_tipi, 
                :miktar, 
                :birim_fiyat, 
                :referans_tip, 
                :referans_no, 
                :aciklama, 
                :kullanici_id, 
                NOW()
            )";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':urun_id', $urun_id);
    $stmt->bindParam(':hareket_tipi', $hareket_tipi);
    $stmt->bindParam(':miktar', $miktar);
    $stmt->bindParam(':birim_fiyat', $birim_fiyat);
    $stmt->bindParam(':referans_tip', $referans_tip);
    $stmt->bindParam(':referans_no', $referans_no);
    $stmt->bindParam(':aciklama', $aciklama);
    $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
    $stmt->execute();
    
    // Stok miktarını güncelle
    $yeni_stok = $hareket_tipi == 'giris' ? $mevcut_stok + $miktar : $mevcut_stok - $miktar;
    
    $sql = "UPDATE products SET current_stock = :stok_miktari WHERE id = :id";
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':stok_miktari', $yeni_stok);
    $stmt->bindParam(':id', $urun_id);
    $stmt->execute();
    
    // İşlemleri tamamla
    $db->commit();
    
    // Başarı mesajı
    $_SESSION['success'] = "Stok hareketi başarıyla eklendi. Yeni stok miktarı: " . $yeni_stok;
    
} catch (PDOException $e) {
    // Hata durumunda işlemleri geri al
    $db->rollBack();
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
}

// Ürün düzenleme sayfasına yönlendir
redirect('urun_duzenle.php?id=' . $urun_id);
?> 