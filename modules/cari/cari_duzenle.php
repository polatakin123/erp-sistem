<?php
/**
 * ERP Sistem - Cari Düzenleme Sayfası
 * 
 * Bu dosya, seçilen carinin bilgilerini düzenlemeyi sağlar
 */

// Hata raporlama ayarları
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$debug_mode = true; // Debug modu açık

// Oturum başlat
session_start();

// Test için otomatik oturum ataması
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Test için kullanıcı ID'si tanımlıyoruz
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Cari Düzenle";

// ID kontrolü
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$cari_id = (int)$_GET['id'];
$success = "";
$error = "";

try {
    // Cari bilgilerini al
    $stmt = $db->prepare("SELECT * FROM cari WHERE ID = ?");
    if ($debug_mode) {
        echo "<div class='alert alert-info'>SQL sorgusu: SELECT * FROM cari WHERE ID = {$cari_id}</div>";
    }
    $stmt->execute([$cari_id]);
    $cari = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cari) {
        $error = "Cari bulunamadı! ID: " . $cari_id;
    } else {
        // Debug için cari bilgilerini yazdır
        if ($debug_mode) {
            $debug_info = "<div class='alert alert-info'><pre>Cari ID: " . $cari_id . "\nCari Veri: " . print_r($cari, true) . "</pre></div>";
        }
    }

    // İlleri al (selectbox için)
    $stmt = $db->query("SELECT DISTINCT IL FROM cari WHERE IL IS NOT NULL AND IL != '' ORDER BY IL");
    $iller = $stmt->fetchAll(PDO::FETCH_COLUMN);

    // Form gönderildi mi kontrol et
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        // Form verilerini al ve temizle
        $cari_kodu = clean($_POST['cari_kodu'] ?? '');
        $cari_tipi = clean($_POST['cari_tipi'] ?? '');
        $firma_unvani = clean($_POST['firma_unvani'] ?? '');
        $yetkili_adi = clean($_POST['yetkili_adi'] ?? '');
        $yetkili_soyadi = clean($_POST['yetkili_soyadi'] ?? '');
        $adres = clean($_POST['adres'] ?? '');
        $il = clean($_POST['il'] ?? '');
        $ilce = clean($_POST['ilce'] ?? '');
        $posta_kodu = clean($_POST['posta_kodu'] ?? '');
        $telefon = clean($_POST['telefon'] ?? '');
        $cep_telefon = clean($_POST['cep_telefon'] ?? '');
        $faks = clean($_POST['faks'] ?? '');
        $email = clean($_POST['email'] ?? '');
        $web_sitesi = clean($_POST['web_sitesi'] ?? '');
        $risk_limiti = !empty($_POST['risk_limiti']) ? (float)$_POST['risk_limiti'] : 0;
        $odeme_vade_suresi = !empty($_POST['odeme_vade_suresi']) ? (int)$_POST['odeme_vade_suresi'] : 0;
        $durum = isset($_POST['durum']) ? 1 : 0;
        $aciklama = clean($_POST['aciklama'] ?? '');

        // Form doğrulama
        if (empty($cari_kodu)) {
            $error = "Cari kodu alanı zorunludur!";
        } elseif (empty($firma_unvani)) {
            $error = "Firma ünvanı alanı zorunludur!";
        } else {
            // Cari kodu kontrolü (aynı kod başka bir cariye ait olmamalı)
            $stmt = $db->prepare("SELECT COUNT(*) FROM cari WHERE KOD = ? AND ID != ?");
            $stmt->execute([$cari_kodu, $cari_id]);
            if ($stmt->fetchColumn() > 0) {
                $error = "Bu cari kodu başka bir cari tarafından kullanılıyor!";
            } else {
                // Güncelleme işlemi
                $sql = "UPDATE cari SET 
                    KOD = ?, 
                    TIP = ?, 
                    ADI = ?, 
                    YETKILI_ADI = ?, 
                    YETKILI_SOYADI = ?, 
                    ADRES = ?, 
                    IL = ?, 
                    ILCE = ?, 
                    POSTA_KODU = ?, 
                    TELEFON = ?, 
                    CEPNO = ?, 
                    FAX = ?, 
                    EMAIL = ?, 
                    WEB = ?, 
                    LIMITTL = ?, 
                    VADE = ?, 
                    DURUM = ?, 
                    NOTLAR = ?
                WHERE ID = ?";

                $stmt = $db->prepare($sql);
                $stmt->execute([
                    $cari_kodu, 
                    $cari_tipi, 
                    $firma_unvani, 
                    $yetkili_adi, 
                    $yetkili_soyadi, 
                    $adres, 
                    $il, 
                    $ilce, 
                    $posta_kodu, 
                    $telefon, 
                    $cep_telefon, 
                    $faks, 
                    $email, 
                    $web_sitesi, 
                    $risk_limiti, 
                    $odeme_vade_suresi, 
                    $durum, 
                    $aciklama,
                    $cari_id
                ]);

                // Başarılı mesajı göster
                $success = "Cari başarıyla güncellendi.";
                
                // Güncellenen verileri tekrar al
                $stmt = $db->prepare("SELECT * FROM cari WHERE ID = ?");
                $stmt->execute([$cari_id]);
                $cari = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    }
} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
// Debug için header/footer devre dışı bırakalım
// include_once '../../includes/header.php';

// Doğrudan HTML çıktısı verelim
?>
<!DOCTYPE html>
<html>
<head>
    <title>Cari Düzenle Test</title>
    <meta charset="utf-8">
    <style>
        body { font-family: Arial, sans-serif; margin: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .form-group { margin-bottom: 15px; }
        label { display: block; margin-bottom: 5px; font-weight: bold; }
        input, select, textarea { width: 100%; padding: 8px; border: 1px solid #ddd; }
        .btn { padding: 10px 15px; background: #4CAF50; color: white; border: none; cursor: pointer; }
        .alert { padding: 15px; margin-bottom: 20px; border-radius: 4px; }
        .alert-danger { background-color: #f8d7da; color: #721c24; }
        .alert-success { background-color: #d4edda; color: #155724; }
        .debug-info { background-color: #f8f9fa; padding: 15px; border: 1px solid #ddd; margin: 20px 0; }
    </style>
</head>
<body>

<div class="container">
    <h1>Cari Düzenle (Test Modu)</h1>
    
    <div class="debug-info">
        <h3>Debug Bilgisi:</h3>
        <p>Cari ID: <?php echo $cari_id; ?></p>
        <p>Session User ID: <?php echo $_SESSION['user_id'] ?? 'Yok'; ?></p>
        <p>Debug Mode: <?php echo $debug_mode ? 'Açık' : 'Kapalı'; ?></p>
        
        <?php if (isset($cari)) : ?>
            <h4>Cari Veri:</h4>
            <pre><?php print_r($cari); ?></pre>
        <?php else : ?>
            <p style="color: red;">Cari verisi bulunamadı!</p>
        <?php endif; ?>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($cari)): ?>
        <form method="post" action="cari_duzenle.php?id=<?php echo $cari_id; ?>">
            <div class="form-group">
                <label for="cari_kodu">Cari Kodu</label>
                <input type="text" id="cari_kodu" name="cari_kodu" value="<?php echo htmlspecialchars($cari['KOD']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="firma_unvani">Firma Ünvanı</label>
                <input type="text" id="firma_unvani" name="firma_unvani" value="<?php echo htmlspecialchars($cari['ADI']); ?>" required>
            </div>
            
            <div class="form-group">
                <label for="yetkili_adi">Yetkili Adı</label>
                <input type="text" id="yetkili_adi" name="yetkili_adi" value="<?php echo htmlspecialchars($cari['YETKILI_ADI'] ?? ''); ?>">
            </div>
            
            <div class="form-group">
                <label for="yetkili_soyadi">Yetkili Soyadı</label>
                <input type="text" id="yetkili_soyadi" name="yetkili_soyadi" value="<?php echo htmlspecialchars($cari['YETKILI_SOYADI'] ?? ''); ?>">
            </div>
            
            <button type="submit" class="btn">Güncelle</button>
        </form>
    <?php else: ?>
        <div class="alert alert-danger">Cari kaydı bulunamadı veya bir hata oluştu.</div>
    <?php endif; ?>
    
    <div style="margin-top: 20px;">
        <a href="test.php">Cari Listesine Dön</a>
    </div>
</div>

</body>
</html>
<?php
// include_once '../../includes/footer.php';
?> 