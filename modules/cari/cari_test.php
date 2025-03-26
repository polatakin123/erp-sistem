<?php
// Hata raporlama ayarları
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Oturum başlat - test için user_id tanımlayalım
session_start();
$_SESSION['user_id'] = 1; // Test için kullanıcı ID'si tanımlıyoruz

// Veritabanı bağlantısı
require_once '../../config/db.php';

echo "<h1>Cari Bilgisi Test Sayfası</h1>";

if (!isset($_GET['id'])) {
    echo "<div style='color:red'>ID parametresi eksik!</div>";
    echo "<a href='cari_test.php?id=2'>ID=2 ile test et</a>";
    exit;
}

$cari_id = (int)$_GET['id'];
echo "<div>Aranan Cari ID: " . $cari_id . "</div>";

try {
    // Cari bilgilerini al
    $stmt = $db->prepare("SELECT * FROM cari WHERE ID = ?");
    $stmt->execute([$cari_id]);
    $cari = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$cari) {
        echo "<div style='color:red'>Cari bulunamadı!</div>";
    } else {
        echo "<h2>Cari Bilgileri:</h2>";
        echo "<pre>";
        print_r($cari);
        echo "</pre>";
        
        echo "<h3>Form Görünümü:</h3>";
        ?>
        <form method="post">
            <div>
                <label>Cari Kodu:</label>
                <input type="text" name="cari_kodu" value="<?php echo $cari['KOD']; ?>">
            </div>
            <div>
                <label>Firma Ünvanı:</label>
                <input type="text" name="firma_unvani" value="<?php echo $cari['ADI']; ?>">
            </div>
            <div>
                <label>Yetkili Adı:</label>
                <input type="text" name="yetkili_adi" value="<?php echo $cari['YETKILI_ADI']; ?>">
            </div>
            <div>
                <label>Yetkili Soyadı:</label>
                <input type="text" name="yetkili_soyadi" value="<?php echo $cari['YETKILI_SOYADI']; ?>">
            </div>
        </form>
        <?php
    }
} catch (PDOException $e) {
    echo "<div style='color:red'>Veritabanı hatası: " . $e->getMessage() . "</div>";
    echo "<pre>" . print_r($e, true) . "</pre>";
}
?> 