<?php
/**
 * ERP Sistem - Stok Yeniden Hesaplama Sayfası
 * 
 * Bu dosya tüm ürünlerin stok miktarlarını stok hareketlerine göre yeniden hesaplar.
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
$pageTitle = "Stok Miktarlarını Yeniden Hesapla";

// POST isteği kontrolü - Hesaplama işlemi için
$islem_yapildi = false;
$sonuc = [];

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['hesapla'])) {
    try {
        // Tüm stokları yeniden hesapla
        $sonuc = recalculateAllStocks($db);
        $islem_yapildi = true;
    } catch (Exception $e) {
        $sonuc = [
            'success' => false,
            'total' => 0,
            'updated' => 0,
            'errors' => [$e->getMessage()]
        ];
        $islem_yapildi = true;
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Stok Miktarlarını Yeniden Hesapla</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Stok Yönetimine Dön
            </a>
        </div>
    </div>
</div>

<!-- Açıklama Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Stok Yeniden Hesaplama İşlemi Hakkında</h6>
    </div>
    <div class="card-body">
        <p>
            Bu işlem, tüm ürünlerin mevcut stok miktarlarını stok hareketlerine göre yeniden hesaplar. 
            Stok hareketleri veritabanından çekilerek, her ürün için toplam giriş ve çıkış miktarları hesaplanır
            ve ürün tablosundaki stok miktarları güncellenir.
        </p>
        <p>
            <strong>Not:</strong> Bu işlem, stok miktarlarının yanlış olduğunu düşündüğünüzde veya 
            stok hareketleri ile ürün stok miktarları arasında tutarsızlık olduğunda kullanılmalıdır.
        </p>
        <p>
            <strong>Dikkat:</strong> Bu işlem tüm ürünlerin stok miktarlarını değiştirecektir. 
            İşlem tamamlandıktan sonra, güncel stok miktarlarını kontrol etmeyi unutmayın.
        </p>
        
        <form method="post" class="mt-4">
            <div class="d-grid gap-2 col-md-6 mx-auto">
                <button type="submit" name="hesapla" class="btn btn-lg btn-primary">
                    <i class="fas fa-sync-alt"></i> Stokları Yeniden Hesapla
                </button>
            </div>
        </form>
    </div>
</div>

<?php if ($islem_yapildi): ?>
<!-- Sonuç Kartı -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">İşlem Sonucu</h6>
    </div>
    <div class="card-body">
        <?php if ($sonuc['success']): ?>
        <div class="alert alert-success" role="alert">
            <h4 class="alert-heading"><i class="fas fa-check-circle"></i> İşlem Başarılı!</h4>
            <p>Toplam <?php echo $sonuc['total']; ?> ürünün stok miktarı yeniden hesaplandı ve <?php echo $sonuc['updated']; ?> ürün güncellendi.</p>
        </div>
        <?php else: ?>
        <div class="alert alert-danger" role="alert">
            <h4 class="alert-heading"><i class="fas fa-exclamation-triangle"></i> İşlem Sırasında Hatalar Oluştu!</h4>
            <p>Toplam <?php echo $sonuc['total']; ?> ürünün <?php echo $sonuc['updated']; ?> tanesi güncellendi, ancak bazı hatalar oluştu:</p>
            <ul>
                <?php foreach ($sonuc['errors'] as $error): ?>
                <li><?php echo $error; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
        
        <div class="text-center mt-3">
            <a href="index.php" class="btn btn-success">
                <i class="fas fa-check"></i> Stok Yönetimine Dön
            </a>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
    // Form gönderildiğinde onay iste
    document.querySelector('form').addEventListener('submit', function(e) {
        if (!confirm('Tüm ürünlerin stok miktarları yeniden hesaplanacak. Bu işlem geri alınamaz. Devam etmek istiyor musunuz?')) {
            e.preventDefault();
        }
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 