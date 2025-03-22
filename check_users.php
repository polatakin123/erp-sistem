<?php
/**
 * ERP Sistem - Kullanıcı Kontrolü
 * 
 * Bu dosya veritabanındaki kullanıcıları kontrol eder.
 */

// Veritabanı bağlantısı
require_once 'config/db.php';

try {
    // Tüm kullanıcıları getir
    $stmt = $db->query("SELECT id, username, email, full_name, role, status FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h1>Kullanıcı Listesi</h1>";
    
    if (count($users) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Kullanıcı Adı</th><th>E-posta</th><th>Ad Soyad</th><th>Rol</th><th>Durum</th></tr>";
        
        foreach ($users as $user) {
            echo "<tr>";
            echo "<td>{$user['id']}</td>";
            echo "<td>{$user['username']}</td>";
            echo "<td>{$user['email']}</td>";
            echo "<td>{$user['full_name']}</td>";
            echo "<td>{$user['role']}</td>";
            echo "<td>{$user['status']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Veritabanında kullanıcı bulunamadı.";
    }
    
    // Kullanıcı yetkilerini kontrol et
    echo "<h1>Kullanıcı Yetkileri</h1>";
    
    $stmt = $db->query("SELECT up.id, u.username, up.permission 
                        FROM user_permissions up 
                        JOIN users u ON up.user_id = u.id");
    $permissions = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($permissions) > 0) {
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Kullanıcı Adı</th><th>Yetki</th></tr>";
        
        foreach ($permissions as $perm) {
            echo "<tr>";
            echo "<td>{$perm['id']}</td>";
            echo "<td>{$perm['username']}</td>";
            echo "<td>{$perm['permission']}</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "Veritabanında kullanıcı yetkisi bulunamadı.";
    }
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 