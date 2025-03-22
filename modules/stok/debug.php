<?php
/**
 * ERP Sistem - Debug Sayfası
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

// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<h1>Debug Bilgileri</h1>';

// Veritabanı bağlantısını kontrol et
try {
    echo '<h2>Veritabanı Bağlantısı</h2>';
    echo '<p style="color:green;">Veritabanı bağlantısı başarılı.</p>';
    
    // Ürün tablosunu kontrol et
    echo '<h2>Ürün Tablosu</h2>';
    
    // SQL sorgusunu oluştur
    $sql = "
        SELECT p.*, pc.name as category_name
        FROM products p
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        ORDER BY p.code
        LIMIT 20
    ";
    
    // Sorguyu çalıştır
    $stmt = $db->prepare($sql);
    $stmt->execute();
    $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<p>Sorgu sonucu: ' . count($products) . ' ürün bulundu.</p>';
    
    if (count($products) > 0) {
        echo '<table border="1" cellpadding="5" style="border-collapse: collapse;">';
        echo '<tr>';
        
        // Tablo başlıklarını göster
        foreach (array_keys($products[0]) as $column) {
            echo '<th>' . htmlspecialchars($column) . '</th>';
        }
        echo '</tr>';
        
        // Ürün verilerini göster
        foreach ($products as $product) {
            echo '<tr>';
            foreach ($product as $key => $value) {
                echo '<td>' . htmlspecialchars($value ?? 'NULL') . '</td>';
            }
            echo '</tr>';
        }
        echo '</table>';
    } else {
        echo '<p style="color:red;">Hiç ürün bulunamadı! Veritabanınızda ürün verileri mevcut değil.</p>';
        echo '<p>Ürün eklemek için <a href="test_et.php">test sayfasını</a> kullanabilirsiniz.</p>';
    }
    
    // JavaScript kütüphanelerini kontrol et
    echo '<h2>JavaScript Kontrolü</h2>';
    echo '<p>Script etiketlerinizi kontrol edin:</p>';
    echo '<ul>';
    echo '<li>jQuery: <script>document.write(typeof jQuery !== "undefined" ? "<span style=\'color:green\'>Yüklendi ✓</span>" : "<span style=\'color:red\'>Yüklenemedi ✗</span>");</script></li>';
    echo '<li>DataTables: <script>document.write(typeof $.fn.DataTable !== "undefined" ? "<span style=\'color:green\'>Yüklendi ✓</span>" : "<span style=\'color:red\'>Yüklenemedi ✗</span>");</script></li>';
    echo '<li>Bootstrap: <script>document.write(typeof bootstrap !== "undefined" ? "<span style=\'color:green\'>Yüklendi ✓</span>" : "<span style=\'color:red\'>Yüklenemedi ✗</span>");</script></li>';
    echo '</ul>';
    
} catch (PDOException $e) {
    echo '<p style="color:red;font-weight:bold;">Veritabanı hatası: ' . $e->getMessage() . '</p>';
} catch (Exception $e) {
    echo '<p style="color:red;font-weight:bold;">Genel hata: ' . $e->getMessage() . '</p>';
}

// DOM hata kontrolü için script
echo '<script>
window.addEventListener("error", function(e) {
    console.error("JavaScript Error:", e.message);
    const errorDiv = document.createElement("div");
    errorDiv.style.color = "red";
    errorDiv.style.padding = "10px";
    errorDiv.style.margin = "10px 0";
    errorDiv.style.border = "1px solid red";
    errorDiv.innerHTML = "<strong>JavaScript Hatası:</strong> " + e.message;
    document.body.appendChild(errorDiv);
});
</script>';

// DataTables başlatma özel script
echo '<h2>DataTables Testi</h2>';
echo '<table id="testTable" class="display" style="width:100%">
    <thead>
        <tr>
            <th>ID</th>
            <th>İsim</th>
        </tr>
    </thead>
    <tbody>
        <tr>
            <td>1</td>
            <td>Test 1</td>
        </tr>
        <tr>
            <td>2</td>
            <td>Test 2</td>
        </tr>
    </tbody>
</table>';

echo '<script>
$(document).ready(function() {
    try {
        $("#testTable").DataTable({
            "language": {
                "emptyTable": "Tabloda herhangi bir veri mevcut değil",
                "info": "_TOTAL_ kayıttan _START_ - _END_ arasındaki kayıtlar gösteriliyor",
                "infoEmpty": "Kayıt yok",
                "search": "Ara:"
            }
        });
        console.log("Test tablosu başarıyla DataTable olarak başlatıldı");
        document.write("<p style=\'color:green\'>DataTables test edildi. Table başarıyla oluşturuldu! ✓</p>");
    } catch(e) {
        console.error("DataTable başlatma hatası:", e);
        document.write("<p style=\'color:red\'>DataTables başlatılamadı: " + e.message + " ✗</p>");
    }
});
</script>';
?> 