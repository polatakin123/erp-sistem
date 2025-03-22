<?php
/**
 * ERP Sistem - Muhasebe Ana Sayfası
 * 
 * Bu dosya muhasebe modülünün ana sayfasını içerir.
 */

// Oturum başlat
session_start();

// Gerekli dosyaları dahil et
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Oturum kontrolü
checkLogin();

// Yetki kontrolü
if (!checkPermission('muhasebe_goruntule')) {
    // Yetki yoksa ana sayfaya yönlendir
    redirect('../../index.php');
}

// Sayfa başlığı
$pageTitle = "Muhasebe | ERP Sistem";

// Tarih filtreleri
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-01');
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-t');

// Tahsilat ve ödemeleri al
try {
    // Tahsilatlar
    $stmt = $pdo->prepare("SELECT SUM(tutar) as toplam_tahsilat FROM tahsilatlar 
                          WHERE tarih BETWEEN :baslangic_tarihi AND :bitis_tarihi AND durum = 1");
    $stmt->bindParam(':baslangic_tarihi', $baslangic_tarihi);
    $stmt->bindParam(':bitis_tarihi', $bitis_tarihi);
    $stmt->execute();
    $toplam_tahsilat = $stmt->fetch(PDO::FETCH_ASSOC)['toplam_tahsilat'] ?? 0;
    
    // Ödemeler
    $stmt = $pdo->prepare("SELECT SUM(tutar) as toplam_odeme FROM odemeler 
                          WHERE tarih BETWEEN :baslangic_tarihi AND :bitis_tarihi AND durum = 1");
    $stmt->bindParam(':baslangic_tarihi', $baslangic_tarihi);
    $stmt->bindParam(':bitis_tarihi', $bitis_tarihi);
    $stmt->execute();
    $toplam_odeme = $stmt->fetch(PDO::FETCH_ASSOC)['toplam_odeme'] ?? 0;
    
    // Son 5 tahsilat
    $stmt = $pdo->prepare("SELECT t.*, m.ad as musteri_ad 
                          FROM tahsilatlar t
                          LEFT JOIN musteriler m ON t.musteri_id = m.id
                          WHERE t.durum = 1
                          ORDER BY t.tarih DESC, t.id DESC
                          LIMIT 5");
    $stmt->execute();
    $son_tahsilatlar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Son 5 ödeme
    $stmt = $pdo->prepare("SELECT o.*, t.ad as tedarikci_ad 
                          FROM odemeler o
                          LEFT JOIN tedarikciler t ON o.tedarikci_id = t.id
                          WHERE o.durum = 1
                          ORDER BY o.tarih DESC, o.id DESC
                          LIMIT 5");
    $stmt->execute();
    $son_odemeler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Vadesi yaklaşan çek/senetler
    $bugun = date('Y-m-d');
    $bir_ay_sonra = date('Y-m-d', strtotime('+30 days'));
    
    $stmt = $pdo->prepare("SELECT cs.*, m.ad as musteri_ad, t.ad as tedarikci_ad
                          FROM cek_senet cs
                          LEFT JOIN musteriler m ON cs.musteri_id = m.id
                          LEFT JOIN tedarikciler t ON cs.tedarikci_id = t.id
                          WHERE cs.vade_tarihi BETWEEN :bugun AND :bir_ay_sonra
                          AND cs.durum = 'beklemede'
                          ORDER BY cs.vade_tarihi ASC
                          LIMIT 10");
    $stmt->bindParam(':bugun', $bugun);
    $stmt->bindParam(':bir_ay_sonra', $bir_ay_sonra);
    $stmt->execute();
    $vadesi_yaklasan_cek_senetler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Hata durumunda
    $error = "Veritabanı hatası: " . $e->getMessage();
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
                <h1 class="h2">Muhasebe</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <?php if (checkPermission('muhasebe_ekle')): ?>
                        <a href="tahsilat_ekle.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Tahsilat Ekle
                        </a>
                        <a href="odeme_ekle.php" class="btn btn-sm btn-outline-danger">
                            <i class="fas fa-minus"></i> Ödeme Ekle
                        </a>
                        <a href="cek_senet.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-money-check"></i> Çek/Senet İşlemleri
                        </a>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            
            <!-- Tarih Filtresi -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tarih Aralığı Seçin</h6>
                </div>
                <div class="card-body">
                    <form method="get" action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" class="row g-3">
                        <div class="col-md-4">
                            <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-filter"></i> Filtrele
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Özet Bilgiler -->
            <div class="row">
                <!-- Toplam Tahsilat -->
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-primary shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                        Toplam Tahsilat</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($toplam_tahsilat, 2, ',', '.') . ' ₺'; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Toplam Ödeme -->
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-danger shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-danger text-uppercase mb-1">
                                        Toplam Ödeme</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($toplam_odeme, 2, ',', '.') . ' ₺'; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-money-bill fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Bakiye -->
                <div class="col-xl-4 col-md-6 mb-4">
                    <div class="card border-left-success shadow h-100 py-2">
                        <div class="card-body">
                            <div class="row no-gutters align-items-center">
                                <div class="col mr-2">
                                    <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                                        Bakiye</div>
                                    <div class="h5 mb-0 font-weight-bold text-gray-800">
                                        <?php echo number_format($toplam_tahsilat - $toplam_odeme, 2, ',', '.') . ' ₺'; ?>
                                    </div>
                                </div>
                                <div class="col-auto">
                                    <i class="fas fa-balance-scale fa-2x text-gray-300"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class="row">
                <!-- Son Tahsilatlar -->
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-primary">Son Tahsilatlar</h6>
                            <a href="tahsilat_ekle.php" class="btn btn-sm btn-primary">
                                <i class="fas fa-plus"></i> Yeni Tahsilat
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tarih</th>
                                            <th>Müşteri</th>
                                            <th>Tutar</th>
                                            <th>Ödeme Tipi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($son_tahsilatlar)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Kayıt bulunamadı.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($son_tahsilatlar as $tahsilat): ?>
                                            <tr>
                                                <td><?php echo date('d.m.Y', strtotime($tahsilat['tarih'])); ?></td>
                                                <td><?php echo $tahsilat['musteri_ad']; ?></td>
                                                <td class="text-end"><?php echo number_format($tahsilat['tutar'], 2, ',', '.') . ' ₺'; ?></td>
                                                <td><?php echo $tahsilat['odeme_tipi']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Son Ödemeler -->
                <div class="col-md-6">
                    <div class="card shadow mb-4">
                        <div class="card-header py-3 d-flex justify-content-between align-items-center">
                            <h6 class="m-0 font-weight-bold text-danger">Son Ödemeler</h6>
                            <a href="odeme_ekle.php" class="btn btn-sm btn-danger">
                                <i class="fas fa-plus"></i> Yeni Ödeme
                            </a>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-bordered table-hover">
                                    <thead>
                                        <tr>
                                            <th>Tarih</th>
                                            <th>Tedarikçi</th>
                                            <th>Tutar</th>
                                            <th>Ödeme Tipi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php if (empty($son_odemeler)): ?>
                                        <tr>
                                            <td colspan="4" class="text-center">Kayıt bulunamadı.</td>
                                        </tr>
                                        <?php else: ?>
                                            <?php foreach ($son_odemeler as $odeme): ?>
                                            <tr>
                                                <td><?php echo date('d.m.Y', strtotime($odeme['tarih'])); ?></td>
                                                <td><?php echo $odeme['tedarikci_ad']; ?></td>
                                                <td class="text-end"><?php echo number_format($odeme['tutar'], 2, ',', '.') . ' ₺'; ?></td>
                                                <td><?php echo $odeme['odeme_tipi']; ?></td>
                                            </tr>
                                            <?php endforeach; ?>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Vadesi Yaklaşan Çek/Senetler -->
            <div class="card shadow mb-4">
                <div class="card-header py-3 d-flex justify-content-between align-items-center">
                    <h6 class="m-0 font-weight-bold text-info">Vadesi Yaklaşan Çek/Senetler</h6>
                    <a href="cek_senet.php" class="btn btn-sm btn-info">
                        <i class="fas fa-money-check"></i> Tüm Çek/Senetler
                    </a>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>No</th>
                                    <th>Tür</th>
                                    <th>Vade Tarihi</th>
                                    <th>Müşteri/Tedarikçi</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($vadesi_yaklasan_cek_senetler)): ?>
                                <tr>
                                    <td colspan="6" class="text-center">Vadesi yaklaşan çek/senet bulunamadı.</td>
                                </tr>
                                <?php else: ?>
                                    <?php foreach ($vadesi_yaklasan_cek_senetler as $cek_senet): ?>
                                    <tr>
                                        <td><?php echo $cek_senet['no']; ?></td>
                                        <td><?php echo $cek_senet['tur']; ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($cek_senet['vade_tarihi'])); ?></td>
                                        <td>
                                            <?php 
                                            if ($cek_senet['tur'] == 'Müşteri Çeki' || $cek_senet['tur'] == 'Müşteri Senedi') {
                                                echo $cek_senet['musteri_ad'];
                                            } else {
                                                echo $cek_senet['tedarikci_ad'];
                                            }
                                            ?>
                                        </td>
                                        <td class="text-end"><?php echo number_format($cek_senet['tutar'], 2, ',', '.') . ' ₺'; ?></td>
                                        <td>
                                            <?php
                                            $durum_class = '';
                                            switch ($cek_senet['durum']) {
                                                case 'beklemede':
                                                    $durum_class = 'warning';
                                                    break;
                                                case 'tahsil_edildi':
                                                    $durum_class = 'success';
                                                    break;
                                                case 'odendi':
                                                    $durum_class = 'info';
                                                    break;
                                                case 'iade_edildi':
                                                    $durum_class = 'danger';
                                                    break;
                                                default:
                                                    $durum_class = 'secondary';
                                            }
                                            ?>
                                            <span class="badge bg-<?php echo $durum_class; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $cek_senet['durum'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 