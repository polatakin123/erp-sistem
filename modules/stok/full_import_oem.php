<?php
/**
 * ERP Sistem - OZELALAN'lardan OEM Verileri İçe Aktarma (TAM SÜRÜM)
 * 
 * Bu script stok tablosundaki OZELALAN1, OZELALAN2 ve OZELALAN3 sütunlarında bulunan
 * OEM kodlarını boşluklarla ayırarak oem_numbers tablosuna aktarır.
 *
 * NOT: Bu dosya, import_oem_from_stok.php dosyasının limit olmadan çalışan versiyonudur.
 * Tüm ürünlerin OEM verilerini içe aktarır.
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
require_once 'functions.php';

// Sayfa başlığı
$pageTitle = "OEM Verilerini İçe Aktar (Tam Sürüm)";

// Aktar butonuna basıldıysa işlemi başlat
$import_results = null;
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['import_oem'])) {
    try {
        // İstatistik bilgileri
        $stats = [
            'total_products' => 0,
            'total_oem_codes' => 0,
            'products_with_codes' => 0,
            'imported_to_oem_numbers' => 0,
            'error_count' => 0
        ];
        
        // İşlemi başlat
        $db->beginTransaction();
        
        // Hata mesajları için dizi
        $error_messages = [];

        // Stok veritabanından aktif ürünleri getir (limite olmadan tam sürüm)
        $sql = "SELECT ID, OZELALAN1, OZELALAN2, OZELALAN3 FROM stok WHERE DURUM = 1";
        $stmt = $db->query($sql);
        $products = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if (!$products) {
            throw new Exception("Veritabanından ürünler alınamadı.");
        }
        
        // Toplam ürün sayısını al
        $total_products = count($products);
        
        // Her ürün için döngü
        $processed_count = 0;
        echo '<div class="progress-info" style="margin-bottom: 15px;">İşlenen ürün sayısı: <span id="processed_count">0</span>/'.$total_products.'</div>';
        echo '<script>function updateCount(count) { document.getElementById("processed_count").innerText = count; }</script>';

        foreach ($products as $row) {
            $stats['total_products']++;
            $processed_count++;
            
            // Her 50 üründe bir sayacı güncelle
            if ($processed_count % 50 == 0) {
                echo '<script>updateCount('.$processed_count.');</script>';
                // Çıktıyı hemen göndermek için flush
                if (ob_get_level() > 0) {
                    ob_flush();
                    flush();
                }
            }
            
            $stok_id = $row['ID'];
            $oem_codes = [];
            
            // OZELALAN'lardan OEM kodlarını topla
            foreach (['OZELALAN1', 'OZELALAN2', 'OZELALAN3'] as $field) {
                if (!empty($row[$field])) {
                    // Boşlukları normalleştir, tireler kaldırılır, trim yapılır
                    $value = trim(preg_replace('/\s+/', ' ', $row[$field]));
                    // Boşluklara göre parçala
                    $codes = explode(' ', $value);
                    foreach ($codes as $code) {
                        $code = trim($code);
                        if (!empty($code)) {
                            // Tire varsa kaldır
                            $code_without_dash = str_replace('-', '', $code);
                            // Her iki versiyonu da ekle (tireli ve tiresiz)
                            if ($code != $code_without_dash) {
                                $oem_codes[] = $code; // Tireli versiyonu
                            }
                            $oem_codes[] = $code_without_dash; // Tiresiz versiyonu
                        }
                    }
                }
            }
            
            // Tekrarlayan kodları kaldır
            $oem_codes = array_unique($oem_codes);
            
            // OEM kodları varsa ürün sayısını artır
            if (!empty($oem_codes)) {
                $stats['products_with_codes']++;
                
                // OEM kodlarını ekle
                foreach ($oem_codes as $oem_code) {
                    $stats['total_oem_codes']++;
                    
                    try {
                        // OEM kodunu tabloya ekle
                        $stmt = $db->prepare("INSERT IGNORE INTO oem_numbers (product_id, oem_no) VALUES (?, ?)");
                        $stmt->execute([$stok_id, $oem_code]);
                        
                        if ($stmt->rowCount() > 0) {
                            $stats['imported_to_oem_numbers']++;
                        }
                    } catch (PDOException $e) {
                        $stats['error_count']++;
                        $error_messages[] = "Ürün ID: {$stok_id}, OEM Kodu: {$oem_code} - Hata: " . $e->getMessage();
                    }
                }
            }
        }
        
        // Muadil ürün gruplarını oluştur
        $group_sql = "
            SELECT DISTINCT o1.oem_no, COUNT(DISTINCT o1.product_id) AS product_count
            FROM oem_numbers o1
            WHERE EXISTS (
                SELECT 1 FROM oem_numbers o2 
                WHERE o2.oem_no = o1.oem_no AND o2.product_id != o1.product_id
            )
            GROUP BY o1.oem_no
            HAVING COUNT(DISTINCT o1.product_id) > 1
        ";
        
        $muadil_groups = $db->query($group_sql)->fetchAll(PDO::FETCH_ASSOC);
        $created_groups = 0;
        
        foreach ($muadil_groups as $group) {
            // Yeni bir muadil grup oluştur
            $stmt = $db->prepare("INSERT INTO alternative_groups (group_name) VALUES (?)");
            $group_name = "Muadil Grup " . $group['oem_no'];
            $stmt->execute([$group_name]);
            $group_id = $db->lastInsertId();
            
            // Bu OEM koduna sahip ürünleri bul
            $stmt = $db->prepare("SELECT DISTINCT product_id FROM oem_numbers WHERE oem_no = ?");
            $stmt->execute([$group['oem_no']]);
            $products = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Ürünleri gruba ekle
            foreach ($products as $product_id) {
                $stmt = $db->prepare("INSERT IGNORE INTO product_alternatives (product_id, alternative_group_id) VALUES (?, ?)");
                $stmt->execute([$product_id, $group_id]);
            }
            
            $created_groups++;
        }
        
        // İşlemi tamamla
        $db->commit();
        
        // Sonuçları dön
        $import_results = [
            'status' => 'success',
            'stats' => $stats,
            'created_groups' => $created_groups,
            'error_messages' => $error_messages
        ];
        
    } catch (Exception $e) {
        // Hata durumunda işlemi geri al
        if (isset($db) && $db->inTransaction()) {
            $db->rollBack();
        }
        
        $import_results = [
            'status' => 'error',
            'message' => "İçe aktarma sırasında hata oluştu: " . $e->getMessage()
        ];
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">OEM Verilerini İçe Aktar (Tam Sürüm)</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
        </div>
    </div>
</div>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">OZELALAN'lardan OEM Verileri İçe Aktar (Tam Sürüm)</h6>
        <div class="mt-2 badge badge-warning">DİKKAT: Tüm ürünler işlenecek</div>
    </div>
    <div class="card-body">
        <p>
            Bu işlem stok tablosundaki OZELALAN1, OZELALAN2 ve OZELALAN3 sütunlarında bulunan OEM kodlarını analiz eder ve
            boşluklarla ayrılmış her bir OEM kodunu ayrı bir kayıt olarak oem_numbers tablosuna aktarır. Bu sayede muadil ürün
            grupları oluşturulabilir ve OEM numaraları üzerinden ürün araması yapılabilir.
        </p>
        <div class="alert alert-warning">
            <p><strong>Dikkat:</strong> Bu işlem veritabanındaki <strong>TÜM AKTİF ÜRÜNLERİ</strong> işleyecektir. İşlem, ürün sayınıza bağlı olarak uzun sürebilir.</p>
        </div>
        
        <?php if ($import_results !== null): ?>
            <div class="alert alert-<?php echo $import_results['status'] === 'success' ? 'success' : 'danger'; ?> mb-4">
                <?php if ($import_results['status'] === 'success'): ?>
                    <h5 class="alert-heading">İçe Aktarma Başarılı!</h5>
                    <hr>
                    <p>İşlem sonuçları:</p>
                    <ul>
                        <li>Toplam Ürün Sayısı: <?php echo $import_results['stats']['total_products']; ?></li>
                        <li>OEM Kodu Bulunan Ürün Sayısı: <?php echo $import_results['stats']['products_with_codes']; ?></li>
                        <li>Toplam OEM Kodu Sayısı: <?php echo $import_results['stats']['total_oem_codes']; ?></li>
                        <li>İçe Aktarılan OEM Kodu Sayısı: <?php echo $import_results['stats']['imported_to_oem_numbers']; ?></li>
                        <li>Oluşturulan Muadil Grup Sayısı: <?php echo $import_results['created_groups']; ?></li>
                        <li>Hata Sayısı: <?php echo $import_results['stats']['error_count']; ?></li>
                    </ul>
                    
                    <?php if (!empty($import_results['error_messages'])): ?>
                        <hr>
                        <h6>Hatalar:</h6>
                        <div class="small" style="max-height: 200px; overflow-y: auto;">
                            <ul>
                                <?php foreach (array_slice($import_results['error_messages'], 0, 50) as $error): ?>
                                    <li><?php echo htmlspecialchars($error); ?></li>
                                <?php endforeach; ?>
                                
                                <?php if (count($import_results['error_messages']) > 50): ?>
                                    <li>... ve <?php echo count($import_results['error_messages']) - 50; ?> daha fazla hata</li>
                                <?php endif; ?>
                            </ul>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <h5 class="alert-heading">İçe Aktarma Hatası!</h5>
                    <p><?php echo htmlspecialchars($import_results['message']); ?></p>
                <?php endif; ?>
            </div>
        <?php endif; ?>
        
        <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="alert alert-danger">
                <p><i class="fas fa-exclamation-triangle"></i> <strong>Uyarı:</strong> Bu işlem büyük veritabanlarında zaman alabilir. İşlem sırasında sayfayı kapatmayın veya yenilemeyin.</p>
            </div>
            
            <button type="submit" name="import_oem" class="btn btn-danger">
                <i class="fas fa-download"></i> Tüm Ürünlerin OEM Verilerini İçe Aktar
            </button>
            <a href="import_oem_from_stok.php" class="btn btn-outline-primary ml-2">
                <i class="fas fa-flask"></i> Test Moduna Dön
            </a>
        </form>
    </div>
</div>

<!-- OEM Tablosu Yapısı Bilgisi -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Tablo Yapısı Bilgisi</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <h5>oem_numbers</h5>
                <p>Bu tablo ürünlere ait OEM numaralarını saklar.</p>
                <ul>
                    <li><strong>id:</strong> Otomatik artan benzersiz kimlik</li>
                    <li><strong>product_id:</strong> Ürün ID (stok tablosuna referans)</li>
                    <li><strong>oem_no:</strong> OEM numarası</li>
                    <li><strong>created_at:</strong> Oluşturulma tarihi</li>
                </ul>
            </div>
            <div class="col-md-6">
                <h5>alternative_groups ve product_alternatives</h5>
                <p>Bu tablolar, aynı OEM numarasına sahip ürünleri gruplamak için kullanılır.</p>
                <ul>
                    <li><strong>alternative_groups:</strong> Muadil ürün gruplarını tanımlar</li>
                    <li><strong>product_alternatives:</strong> Hangi ürünlerin hangi muadil gruba ait olduğunu belirtir</li>
                </ul>
            </div>
        </div>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 