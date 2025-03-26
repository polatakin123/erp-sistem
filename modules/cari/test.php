<?php
// Hata raporlama
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Oturum başlat
session_start();
$_SESSION['user_id'] = 1; // Test için

// Veritabanı bağlantısı
require_once '../../config/db.php';

echo "<h1>Basit Test</h1>";

try {
    // Cari kayıtlarını listele
    $sql = "SELECT ID, KOD, ADI, TIP, YETKILI_ADI, YETKILI_SOYADI FROM cari LIMIT 10";
    $stmt = $db->query($sql);
    $cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Cari Listesi:</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Kod</th><th>Adı</th><th>Tip</th><th>Yetkili</th><th>İşlemler</th></tr>";
    
    foreach ($cariler as $cari) {
        echo "<tr>";
        echo "<td>" . $cari['ID'] . "</td>";
        echo "<td>" . $cari['KOD'] . "</td>";
        echo "<td>" . $cari['ADI'] . "</td>";
        echo "<td>" . $cari['TIP'] . "</td>";
        echo "<td>" . $cari['YETKILI_ADI'] . " " . $cari['YETKILI_SOYADI'] . "</td>";
        echo "<td>";
        echo "<a href='cari_duzenle.php?id=" . $cari['ID'] . "'>Düzenle</a> | ";
        echo "<a href='cari_detay.php?id=" . $cari['ID'] . "'>Detay</a>";
        echo "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch (PDOException $e) {
    echo "Veritabanı hatası: " . $e->getMessage();
}
?> 