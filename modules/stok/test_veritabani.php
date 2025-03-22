<?php
/**
 * ERP Sistem - Veritabanı Bağlantı Testi
 */

try {
    $db = new PDO('mysql:host=localhost;dbname=erp_sistem', 'root', '');
    echo '<p style="color:green;font-weight:bold;">Veritabanı bağlantısı başarılı.</p>';
    
    // Tabloları listele
    $stmt = $db->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
    echo '<p>Veritabanındaki tablolar:</p>';
    echo '<ul>';
    foreach ($tables as $table) {
        echo '<li>' . $table . '</li>';
    }
    echo '</ul>';
    
    // Ürünleri kontrol et
    echo '<p style="font-weight:bold;">Ürün Tablosu Kontrolü:</p>';
    
    // Ürün tablosunun varlığını kontrol et
    $stmt = $db->query("SHOW TABLES LIKE 'products'");
    if ($stmt->rowCount() == 0) {
        echo '<p style="color:red;">products tablosu bulunamadı!</p>';
    } else {
        // Ürün sayısını kontrol et
        $stmt = $db->query("SELECT COUNT(*) FROM products");
        $count = $stmt->fetchColumn();
        
        echo '<p>Toplam ' . $count . ' ürün kayıtlı.</p>';
        
        if ($count > 0) {
            // İlk 5 ürünü listele
            echo '<p>İlk 5 ürün:</p>';
            $stmt = $db->query("SELECT * FROM products LIMIT 5");
            echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
            echo '<tr><th>ID</th><th>Kod</th><th>Ürün Adı</th><th>Stok</th><th>Durum</th></tr>';
            while ($row = $stmt->fetch()) {
                echo '<tr>';
                echo '<td>' . $row['id'] . '</td>';
                echo '<td>' . $row['code'] . '</td>';
                echo '<td>' . $row['name'] . '</td>';
                echo '<td>' . $row['current_stock'] . '</td>';
                echo '<td>' . $row['status'] . '</td>';
                echo '</tr>';
            }
            echo '</table>';
        } else {
            echo '<p style="color:orange;">Ürün tablosu boş, hiç ürün kaydedilmemiş!</p>';
            echo '<p>Ürün eklemek için <a href="urun_test_verileri.php">test verilerini ekle</a> sayfasını kullanabilirsiniz.</p>';
        }
    }
    
} catch (PDOException $e) {
    echo '<p style="color:red;font-weight:bold;">Bağlantı hatası: ' . $e->getMessage() . '</p>';
}
?> 