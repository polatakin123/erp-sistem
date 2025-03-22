<?php
/**
 * ERP Sistem - Kullanıcı Oluşturma
 * 
 * Bu dosya yeni bir kullanıcı oluşturur.
 */

// Veritabanı bağlantısı
require_once 'config/db.php';

try {
    // Veritabanını boşalt
    $db->exec("DELETE FROM user_permissions");
    $db->exec("DELETE FROM users");
    
    // Yeni kullanıcı bilgileri
    $username = 'admin';
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $email = 'admin@example.com';
    $fullName = 'Sistem Yöneticisi';
    $role = 'admin';
    
    // Kullanıcıyı ekle
    $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (:username, :password, :email, :full_name, :role)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':full_name', $fullName);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    
    $userId = $db->lastInsertId();
    
    // Kullanıcı yetkilerini ekle
    $permissions = [
        'muhasebe_cek_senet',
        'muhasebe_tahsilat',
        'muhasebe_odeme',
        'stok_urun',
        'stok_hareket',
        'fatura_satis',
        'fatura_alis'
    ];
    
    $stmt = $db->prepare("INSERT INTO user_permissions (user_id, permission) VALUES (:user_id, :permission)");
    
    foreach ($permissions as $permission) {
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':permission', $permission);
        $stmt->execute();
    }
    
    echo "Kullanıcı başarıyla oluşturuldu.<br>";
    echo "Kullanıcı adı: $username<br>";
    echo "Şifre: 123456<br>";
    echo "Yetkiler: " . implode(", ", $permissions);
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 