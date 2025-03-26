<?php
// Veritabanı bağlantı bilgilerini içe aktarın
require_once 'config/db.php';

// oem_numbers tablosundaki kayıt sayısını kontrol et
try {
    // Tablodaki toplam kayıt sayısı
    $count_stmt = $db->query("SELECT COUNT(*) FROM oem_numbers");
    $total_count = $count_stmt->fetchColumn();
    
    echo "<h2>OEM Numbers Tablo İstatistikleri</h2>";
    echo "<p>Tablodaki toplam kayıt sayısı: <strong>" . $total_count . "</strong></p>";
    
    // Tablodaki distinct ürün ID'lerinin sayısı
    $product_count_stmt = $db->query("SELECT COUNT(DISTINCT product_id) FROM oem_numbers");
    $distinct_product_count = $product_count_stmt->fetchColumn();
    
    echo "<p>Tabloda OEM kodu olan benzersiz ürün sayısı: <strong>" . $distinct_product_count . "</strong></p>";
    
    // Son 10 kaydı göster
    echo "<h3>Son Eklenen 10 Kayıt:</h3>";
    $latest_records = $db->query("SELECT * FROM oem_numbers ORDER BY id DESC LIMIT 10")->fetchAll(PDO::FETCH_ASSOC);
    
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
    
    // Arayüz iyileştirmesi
    echo "<style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        h2 { color: #333; }
        table { width: 100%; margin-top: 20px; }
        th { background-color: #f2f2f2; }
        tr:nth-child(even) { background-color: #f9f9f9; }
        .action-btn { 
            display: inline-block; 
            margin: 10px 0; 
            padding: 8px 15px; 
            background-color: #4CAF50; 
            color: white; 
            text-decoration: none; 
            border-radius: 4px; 
        }
    </style>";
    
    // Temizleme butonunu ekle
    echo "<p><a href='?truncate=1' class='action-btn' onclick=\"return confirm('Tablodaki tüm OEM verilerini silmek istediğinizden emin misiniz?');\">Tabloyu Temizle</a></p>";
    
    // Temizleme işlemi
    if (isset($_GET['truncate']) && $_GET['truncate'] == 1) {
        $db->exec("TRUNCATE TABLE oem_numbers");
        echo "<p style='color: green;'>Tablo başarıyla temizlendi. Sayfayı yenileyerek güncel durumu görebilirsiniz.</p>";
        echo "<script>setTimeout(function(){ window.location.href = 'check_oem_count.php'; }, 2000);</script>";
    }
    
} catch (Exception $e) {
    echo "<p style='color: red;'>Hata: " . $e->getMessage() . "</p>";
}
?> 