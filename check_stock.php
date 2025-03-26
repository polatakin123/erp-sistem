<?php
require 'config/db.php';

try {
    // İngilizce stok hareketleri tablosunu kontrol et
    $sql1 = "SELECT COUNT(*) as total FROM stock_movements";
    $result1 = $db->query($sql1)->fetch(PDO::FETCH_ASSOC);
    
    // Türkçe stok hareketleri tablosunu kontrol et
    $sql2 = "SELECT COUNT(*) as total FROM stok_hareketleri";
    $result2 = $db->query($sql2)->fetch(PDO::FETCH_ASSOC);
    
    echo "İngilizce stok hareketleri sayısı: " . $result1['total'] . "<br>";
    echo "Türkçe stok hareketleri sayısı: " . $result2['total'] . "<br>";
    
    // İngilizce tablodaki son 5 kaydı göster
    echo "<h3>İngilizce Stok Hareketleri (son 5)</h3>";
    $sql3 = "SELECT sm.*, p.name as product_name 
             FROM stock_movements sm
             JOIN products p ON sm.product_id = p.id
             ORDER BY sm.id DESC LIMIT 5";
    $stmt3 = $db->query($sql3);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Ürün</th><th>Hareket Türü</th><th>Miktar</th><th>Birim Fiyat</th><th>Referans No</th><th>Tarih</th></tr>";
    
    while ($row = $stmt3->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['product_name'] . "</td>";
        echo "<td>" . $row['movement_type'] . "</td>";
        echo "<td>" . $row['quantity'] . "</td>";
        echo "<td>" . $row['unit_price'] . "</td>";
        echo "<td>" . $row['reference_no'] . "</td>";
        echo "<td>" . $row['movement_date'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Türkçe tablodaki son 5 kaydı göster
    echo "<h3>Türkçe Stok Hareketleri (son 5)</h3>";
    $sql4 = "SELECT sh.*, u.ad as urun_adi 
             FROM stok_hareketleri sh
             JOIN urunler u ON sh.urun_id = u.id
             ORDER BY sh.id DESC LIMIT 5";
    $stmt4 = $db->query($sql4);
    
    echo "<table border='1' cellpadding='5'>";
    echo "<tr><th>ID</th><th>Ürün</th><th>Hareket Türü</th><th>Miktar</th><th>Birim Fiyat</th><th>Referans No</th><th>Tarih</th></tr>";
    
    while ($row = $stmt4->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . $row['id'] . "</td>";
        echo "<td>" . $row['urun_adi'] . "</td>";
        echo "<td>" . $row['hareket_turu'] . "</td>";
        echo "<td>" . $row['miktar'] . "</td>";
        echo "<td>" . $row['birim_fiyat'] . "</td>";
        echo "<td>" . $row['referans_no'] . "</td>";
        echo "<td>" . $row['hareket_tarihi'] . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // stok_hareketleri.php dosyasının içeriğini kontrol et
    echo "<h3>stok_hareketleri.php Dosyasının İçeriği</h3>";
    $file_path = "modules/stok/stok_hareketleri.php";
    if (file_exists($file_path)) {
        $lines = file($file_path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $sql_line = "";
        
        foreach ($lines as $line) {
            if (strpos($line, "SELECT") !== false && strpos($line, "stok_hareketleri") !== false) {
                $sql_line = $line;
                break;
            }
        }
        
        if ($sql_line) {
            echo "Sorgu bulundu: <pre>" . htmlspecialchars($sql_line) . "</pre>";
        } else {
            echo "Sorgu bulunamadı.";
        }
    } else {
        echo "stok_hareketleri.php dosyası bulunamadı.";
    }
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 