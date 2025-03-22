<?php
/**
 * ERP Sistem - Test Verilerini Temizle
 * 
 * Bu dosya test verilerini temizlemek için kullanılır.
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

// Sayfa başlığı
$pageTitle = "Test Verilerini Temizle";

// Sonuç mesajları
$islemSonucu = '';
$hata = '';

// Temizleme işlemi yapıldıysa
if (isset($_POST['temizle'])) {
    try {
        // Ürünleri temizle
        $db->exec("DELETE FROM products");
        $islemSonucu = "Tüm ürünler başarıyla silindi!";
    } catch (PDOException $e) {
        $hata = 'Veritabanı hatası: ' . $e->getMessage();
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Test Verilerini Temizle</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Geri Dön
        </a>
    </div>
</div>

<?php if (!empty($islemSonucu)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Başarılı!</strong> <?php echo $islemSonucu; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($hata)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Hata!</strong> <?php echo $hata; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Ana İçerik -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Veritabanı Temizleme İşlemi</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-warning">
            <h5 class="alert-heading">Dikkat!</h5>
            <p>Bu işlem tüm ürünleri veritabanından silecektir. Bu işlem geri alınamaz!</p>
        </div>
        
        <p>Bu sayfadan veritabanındaki tüm ürünleri temizleyebilirsiniz. Bu özellik, test verilerini silip yeniden oluşturmak için kullanışlıdır.</p>
        
        <h5>Mevcut Durum:</h5>
        <?php
        try {
            $stmt = $db->query("SELECT COUNT(*) FROM products");
            $urunSayisi = $stmt->fetchColumn();
            echo '<p>Veritabanında şu anda <strong>' . $urunSayisi . '</strong> adet ürün bulunmaktadır.</p>';
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Veritabanı hatası: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <div class="mt-4">
            <form method="post" action="" onsubmit="return confirm('DİKKAT: Tüm ürünler silinecektir. Bu işlem geri alınamaz! Devam etmek istediğinize emin misiniz?');">
                <button type="submit" name="temizle" class="btn btn-danger">
                    <i class="fas fa-trash"></i> Tüm Ürünleri Temizle
                </button>
                
                <a href="test_et.php" class="btn btn-primary ms-2">
                    <i class="fas fa-plus-circle"></i> Test Veri Oluşturma Sayfasına Git
                </a>
            </form>
        </div>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 