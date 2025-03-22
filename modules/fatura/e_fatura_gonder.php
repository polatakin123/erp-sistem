<?php
/**
 * ERP Sistem - E-Fatura Gönderme Sayfası
 * 
 * Bu dosya seçilen faturanın e-fatura olarak gönderilmesini sağlar.
 */

// Oturum başlat
session_start();

// Gerekli dosyaları dahil et
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Oturum kontrolü
checkLogin();

// Yetki kontrolü
if (!checkPermission('fatura_goruntule')) {
    // Yetki yoksa ana sayfaya yönlendir
    redirect('../../index.php');
}

// Sayfa başlığı
$pageTitle = "E-Fatura Gönder | ERP Sistem";

// Hata ve başarı mesajları için değişkenler
$error = "";
$success = "";

// Fatura ID'sini al
$fatura_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

// Fatura bilgilerini al
if ($fatura_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT f.*, m.ad AS musteri_ad, m.vergi_no AS musteri_vergi_no, m.vergi_dairesi AS musteri_vergi_dairesi, 
                                     m.adres AS musteri_adres, m.telefon AS musteri_telefon, m.email AS musteri_email
                              FROM faturalar f
                              LEFT JOIN musteriler m ON f.musteri_id = m.id
                              WHERE f.id = :id AND f.fatura_tipi = 'satis' AND f.durum = 1");
        $stmt->bindParam(':id', $fatura_id);
        $stmt->execute();
        $fatura = $stmt->fetch();
        
        if (!$fatura) {
            redirect('index.php');
        }
        
        // Fatura detaylarını al
        $stmt = $pdo->prepare("SELECT fd.*, u.stok_kodu, u.ad AS urun_ad, b.ad AS birim_ad
                              FROM fatura_detaylari fd
                              LEFT JOIN urunler u ON fd.urun_id = u.id
                              LEFT JOIN birimler b ON u.birim_id = b.id
                              WHERE fd.fatura_id = :fatura_id");
        $stmt->bindParam(':fatura_id', $fatura_id);
        $stmt->execute();
        $fatura_detaylari = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
    }
}

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // E-fatura entegrasyonu burada yapılacak
        // Örnek olarak sadece faturanın e-fatura durumunu güncelliyoruz
        $stmt = $pdo->prepare("UPDATE faturalar SET e_fatura = 1 WHERE id = :id");
        $stmt->bindParam(':id', $fatura_id);
        
        if ($stmt->execute()) {
            $success = "E-fatura başarıyla gönderildi.";
        } else {
            $error = "E-fatura gönderilirken bir hata oluştu.";
        }
        
    } catch (Exception $e) {
        $error = "İşlem hatası: " . $e->getMessage();
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Yan menüyü dahil et -->
        <?php include_once '../../includes/sidebar.php'; ?>
        
        <!-- Ana içerik -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">E-Fatura Gönder</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Geri Dön
                        </a>
                    </div>
                </div>
            </div>
            
            <?php
            // Hata mesajı varsa göster
            if (!empty($error)) {
                echo errorMessage($error);
            }
            
            // Başarı mesajı varsa göster
            if (!empty($success)) {
                echo successMessage($success);
            }
            ?>
            
            <?php if (isset($fatura) && $fatura): ?>
            <!-- Fatura Bilgileri -->
            <div class="row">
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Fatura Bilgileri</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Fatura No</th>
                                    <td><?php echo $fatura['fatura_no']; ?></td>
                                </tr>
                                <tr>
                                    <th>Fatura Tarihi</th>
                                    <td><?php echo date('d.m.Y', strtotime($fatura['fatura_tarihi'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Vade Tarihi</th>
                                    <td><?php echo date('d.m.Y', strtotime($fatura['vade_tarihi'])); ?></td>
                                </tr>
                                <tr>
                                    <th>Toplam Tutar</th>
                                    <td><?php echo number_format($fatura['toplam_tutar'], 2, ',', '.') . ' ₺'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
                
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3">
                            <h6 class="m-0 font-weight-bold text-primary">Müşteri Bilgileri</h6>
                        </div>
                        <div class="card-body">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Müşteri</th>
                                    <td><?php echo $fatura['musteri_ad']; ?></td>
                                </tr>
                                <tr>
                                    <th>Vergi No</th>
                                    <td><?php echo $fatura['musteri_vergi_no']; ?></td>
                                </tr>
                                <tr>
                                    <th>Vergi Dairesi</th>
                                    <td><?php echo $fatura['musteri_vergi_dairesi']; ?></td>
                                </tr>
                                <tr>
                                    <th>Adres</th>
                                    <td><?php echo $fatura['musteri_adres']; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Fatura Detayları -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Fatura Detayları</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Stok Kodu</th>
                                    <th>Ürün</th>
                                    <th>Miktar</th>
                                    <th>Birim</th>
                                    <th>Birim Fiyat</th>
                                    <th>KDV (%)</th>
                                    <th>İskonto (%)</th>
                                    <th>Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($fatura_detaylari as $detay): ?>
                                <tr>
                                    <td><?php echo $detay['stok_kodu']; ?></td>
                                    <td><?php echo $detay['urun_ad']; ?></td>
                                    <td><?php echo $detay['miktar']; ?></td>
                                    <td><?php echo $detay['birim_ad']; ?></td>
                                    <td class="text-end"><?php echo number_format($detay['birim_fiyat'], 2, ',', '.') . ' ₺'; ?></td>
                                    <td class="text-end"><?php echo number_format($detay['kdv_orani'], 0) . '%'; ?></td>
                                    <td class="text-end"><?php echo number_format($detay['iskonto_orani'], 0) . '%'; ?></td>
                                    <td class="text-end"><?php echo number_format($detay['toplam_tutar'], 2, ',', '.') . ' ₺'; ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="7" class="text-end">Ara Toplam:</td>
                                    <td class="text-end"><?php echo number_format($fatura['ara_toplam'], 2, ',', '.') . ' ₺'; ?></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end">Toplam İskonto:</td>
                                    <td class="text-end"><?php echo number_format($fatura['toplam_iskonto'], 2, ',', '.') . ' ₺'; ?></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end">Toplam KDV:</td>
                                    <td class="text-end"><?php echo number_format($fatura['toplam_kdv'], 2, ',', '.') . ' ₺'; ?></td>
                                </tr>
                                <tr>
                                    <td colspan="7" class="text-end fw-bold">Genel Toplam:</td>
                                    <td class="text-end fw-bold"><?php echo number_format($fatura['toplam_tutar'], 2, ',', '.') . ' ₺'; ?></td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- E-Fatura Gönderme Formu -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">E-Fatura Gönderme</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $fatura_id); ?>" method="post" class="needs-validation" novalidate>
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Bu fatura e-fatura olarak gönderilecektir. Devam etmek istediğinizden emin misiniz?
                        </div>
                        
                        <div class="mb-3">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-paper-plane"></i> E-Fatura Gönder
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i> İptal
                            </a>
                        </div>
                    </form>
                </div>
            </div>
            <?php endif; ?>
            
        </main>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 