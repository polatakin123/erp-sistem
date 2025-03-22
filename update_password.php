<?php
/**
 * ERP Sistem - Şifre Güncelleme
 * 
 * Bu dosya admin şifresini günceller.
 */

// Veritabanı bağlantısı
require_once 'config/db.php';

try {
    // Yeni şifre hash'i
    $newPasswordHash = '$2y$10$M.OyqklKuIU46ca3oyelp.WaXj6il.jGnHDN/ZYbERHY.4iv7rJIi'; // 123456
    
    // Admin kullanıcısının şifresini güncelle
    $stmt = $db->prepare("UPDATE users SET password = :password WHERE username = 'admin'");
    $stmt->bindParam(':password', $newPasswordHash);
    $results = $stmt->execute();
    
    if ($results) {
        echo "Admin kullanıcısının şifresi başarıyla güncellendi. Yeni şifre: 123456";
    } else {
        echo "Şifre güncellenirken bir hata oluştu.";
    }
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 