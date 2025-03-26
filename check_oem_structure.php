<?php
// Veritabanı bağlantı bilgilerini içe aktarın
require_once 'config/db.php';

// oem_numbers tablosunun yapısını kontrol et
try {
    // Tablo yapısını göster
    $table_info = $db->query("SHOW CREATE TABLE oem_numbers")->fetch(PDO::FETCH_ASSOC);
    
    echo "<h2>OEM Numbers Tablo Yapısı</h2>";
    echo "<pre>" . htmlspecialchars($table_info['Create Table']) . "</pre>";
    
    // Test kaydı ekle
    echo "<h3>Test Kaydı Ekle</h3>";
    
    if (isset($_POST['add_test'])) {
        $product_id = $_POST['product_id'];
        $oem_no = $_POST['oem_no'];
        
        $stmt = $db->prepare("INSERT INTO oem_numbers (product_id, oem_no) VALUES (?, ?)");
        $result = $stmt->execute([$product_id, $oem_no]);
        
        if ($result && $stmt->rowCount() > 0) {
            echo "<p style='color: green;'>Test kaydı başarıyla eklendi! ID: " . $db->lastInsertId() . "</p>";
        } else {
            echo "<p style='color: red;'>Test kaydı eklenemedi!</p>";
        }
    }
    
    // Test formu
    echo "<form method='post'>";
    echo "<p><label>Ürün ID: <input type='number' name='product_id' required></label></p>";
    echo "<p><label>OEM Kodu: <input type='text' name='oem_no' required></label></p>";
    echo "<p><button type='submit' name='add_test'>Test Kaydı Ekle</button></p>";
    echo "</form>";
    
    // Son 10 kaydı göster
    echo "<h3>Son Eklenen 10 Kayıt:</h3>";
    $latest_records = $db->query("SELECT * FROM oem_numbers ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
    if (count($latest_records) > 0) {
        echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Ürün ID</th><th>OEM Kodu</th><th>Oluşturulma Tarihi</th></tr>";
        
        foreach ($latest_records as $record) {
            echo "<tr>";
            echo "<td>" . $record['id'] . "</td>";
            echo "<td>" . $record['product_id'] . "</td>";
            echo "<td>" . $record['oem_no'] . "</td>";
            echo "<td>" . $record['created_at'] . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    } else {
        echo "<p>Henüz kayıt yok.</p>";
    }
    
    // Arayüz iyileştirmesi
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2, h3 { color: #333; }
        pre { background-color: #f8f8f8; padding: 10px; border: 1px solid #ddd; overflow: auto; }
        table { width: 100%; margin-top: 20px; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        input, button { padding: 5px; margin: 5px 0; }
        button { cursor: pointer; background-color: #4CAF50; color: white; border: none; padding: 10px; }
    </style>";
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Hata: " . $e->getMessage() . "</p>";
}
?> 