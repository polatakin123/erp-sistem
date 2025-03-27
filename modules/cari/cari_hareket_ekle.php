<?php
/**
 * ERP Sistem - Cari Hareket Ekleme Sayfası
 * 
 * Bu dosya, cari hareketlerini eklemek için kullanılır.
 */

// Hata raporlama ayarları
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$debug_mode = true; // Debug modu açık

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../config/helpers.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Cari Hareket Ekle";

// Cari ID'yi al
$cari_id = isset($_GET['cari_id']) ? (int)$_GET['cari_id'] : 0;

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cari_id = isset($_POST['cari_id']) ? (int)$_POST['cari_id'] : 0;
    $tarih = isset($_POST['tarih']) ? clean($_POST['tarih']) : date('Y-m-d');
    $saat = isset($_POST['saat']) ? clean($_POST['saat']) : date('H:i:s');
    $islem_tipi = isset($_POST['islem_tipi']) ? clean($_POST['islem_tipi']) : 'borc';
    $tutar = isset($_POST['tutar']) ? (float)$_POST['tutar'] : 0;
    $evrak_no = isset($_POST['evrak_no']) ? clean($_POST['evrak_no']) : '';
    $izah = isset($_POST['izah']) ? clean($_POST['izah']) : '';
    $fis_tipi = isset($_POST['fis_tipi']) ? clean($_POST['fis_tipi']) : 'Diğer';
    
    // Hataları kontrol et
    $errors = [];
    
    if ($cari_id <= 0) {
        $errors[] = "Cari seçimi yapılmadı!";
    }
    
    if (empty($tarih)) {
        $errors[] = "Tarih alanı boş olamaz!";
    }
    
    if ($tutar <= 0) {
        $errors[] = "Tutar 0'dan büyük olmalıdır!";
    }
    
    // Hata yoksa kaydet
    if (empty($errors)) {
        try {
            // Seçilen cari bilgisini al
            $stmt = $db->prepare("SELECT ADI FROM cari WHERE ID = ?");
            $stmt->execute([$cari_id]);
            $cari = $stmt->fetch(PDO::FETCH_ASSOC);
            $cari_adi = $cari ? $cari['ADI'] : '';
            
            // İşlem tarihini birleştir
            $islem_tarihi = $tarih . ' ' . $saat;
            
            // Hareketi kaydet
            $stmt = $db->prepare("
                INSERT INTO cari_hareket 
                (CARIID, CARIADI, EVRAKNO, ISLEMTIPI, ISLEMTARIHI, TUTAR, FISTIPI, IZAH, OWNERID, ISLEMSAATI) 
                VALUES 
                (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $cari_id,
                $cari_adi,
                $evrak_no,
                $islem_tipi,
                $islem_tarihi,
                $tutar,
                $fis_tipi,
                $izah,
                $_SESSION['user_id']
            ]);
            
            // Cari bakiyesini güncelle
            $stmt = $db->prepare("UPDATE cari SET BAKIYE = BAKIYE + ? WHERE ID = ?");
            
            // Borç ise bakiyeyi artır, alacak ise azalt
            $bakiye_degisimi = $islem_tipi == 'borc' ? $tutar : -$tutar;
            $stmt->execute([$bakiye_degisimi, $cari_id]);
            
            // Başarılı mesajı ve yönlendirme
            $success = "Cari hareket başarıyla eklendi!";
            
            // 2 saniye sonra yönlendir
            header("refresh:2;url=cari_hareketleri.php?cari_id=" . $cari_id);
            
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
            
            if ($debug_mode) {
                echo "<div class='alert alert-danger'>";
                echo "<strong>HATA DETAYLARI:</strong><br>";
                echo "<pre>";
                echo "Hata Kodu: " . $e->getCode() . "<br>";
                echo "Hata Mesajı: " . $e->getMessage() . "<br>";
                echo "Dosya: " . $e->getFile() . "<br>";
                echo "Satır: " . $e->getLine() . "<br>";
                echo "</pre>";
                echo "</div>";
            }
        }
    }
}

try {
    // Cariler listesini al (dropdown için)
    $stmt = $db->query("SELECT ID, KOD, ADI FROM cari ORDER BY ADI ASC");
    $cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Seçilen carinin bilgilerini al
    $cari = null;
    if ($cari_id > 0) {
        $stmt = $db->prepare("SELECT ID, KOD, ADI, BAKIYE FROM cari WHERE ID = ?");
        $stmt->execute([$cari_id]);
        $cari = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Sayfa İçeriği -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cari Hareket Ekle</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="cari_hareketleri.php<?php echo $cari_id > 0 ? '?cari_id=' . $cari_id : ''; ?>" class="btn btn-sm btn-outline-secondary">Cari Hareketleri</a>
                <?php if ($cari_id > 0): ?>
                <a href="cari_detay.php?id=<?php echo $cari_id; ?>" class="btn btn-sm btn-outline-info">Cari Detayları</a>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <?php if (isset($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $err): ?>
                    <li><?php echo $err; ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- Seçili Cari Bilgileri -->
    <?php if ($cari_id > 0 && isset($cari)): ?>
    <div class="row mb-4">
        <div class="col-md-6">
            <div class="card border-left-primary shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                                Seçili Cari</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php echo htmlspecialchars($cari['KOD'] . ' - ' . $cari['ADI']); ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-user fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-6">
            <div class="card border-left-<?php echo isset($cari['BAKIYE']) && $cari['BAKIYE'] > 0 ? 'danger' : 'success'; ?> shadow h-100 py-2">
                <div class="card-body">
                    <div class="row no-gutters align-items-center">
                        <div class="col mr-2">
                            <div class="text-xs font-weight-bold text-<?php echo isset($cari['BAKIYE']) && $cari['BAKIYE'] > 0 ? 'danger' : 'success'; ?> text-uppercase mb-1">
                                Güncel Bakiye</div>
                            <div class="h5 mb-0 font-weight-bold text-gray-800">
                                <?php 
                                    if (isset($cari['BAKIYE'])) {
                                        echo number_format(abs($cari['BAKIYE']), 2, ',', '.') . ' ₺ ';
                                        echo $cari['BAKIYE'] > 0 ? '(Borçlu)' : ($cari['BAKIYE'] < 0 ? '(Alacaklı)' : '(Nötr)');
                                    } else {
                                        echo '0,00 ₺ (Nötr)';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="col-auto">
                            <i class="fas fa-<?php echo isset($cari['BAKIYE']) && $cari['BAKIYE'] > 0 ? 'arrow-up' : 'arrow-down'; ?> fa-2x text-gray-300"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Cari Hareket Ekleme Formu -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cari Hareket Ekleme Formu</h6>
        </div>
        <div class="card-body">
            <form method="post" class="mb-3">
                <div class="row g-3">
                    <div class="col-md-6 mb-3">
                        <label for="cari_id" class="form-label">Cari Seçimi <span class="text-danger">*</span></label>
                        <select class="form-select" id="cari_id" name="cari_id" required>
                            <option value="">Cari Seçiniz</option>
                            <?php foreach ($cariler as $c): ?>
                                <option value="<?php echo $c['ID']; ?>" <?php echo $cari_id == $c['ID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($c['KOD'] . ' - ' . $c['ADI']); ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="tarih" class="form-label">Tarih <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="tarih" name="tarih" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="saat" class="form-label">Saat <span class="text-danger">*</span></label>
                        <input type="time" class="form-control" id="saat" name="saat" value="<?php echo date('H:i'); ?>" required>
                    </div>
                    <div class="col-md-2 mb-3">
                        <label for="islem_tipi" class="form-label">İşlem Tipi <span class="text-danger">*</span></label>
                        <select class="form-select" id="islem_tipi" name="islem_tipi" required>
                            <option value="borc">Borç (Müşterinin Bize Borcu)</option>
                            <option value="alacak">Alacak (Bizim Müşteriye Borcumuz)</option>
                        </select>
                    </div>
                </div>
                <div class="row g-3">
                    <div class="col-md-2 mb-3">
                        <label for="tutar" class="form-label">Tutar (₺) <span class="text-danger">*</span></label>
                        <input type="number" step="0.01" min="0.01" class="form-control" id="tutar" name="tutar" required>
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="evrak_no" class="form-label">Evrak No</label>
                        <input type="text" class="form-control" id="evrak_no" name="evrak_no" placeholder="Örn: FTR-2023-001">
                    </div>
                    <div class="col-md-3 mb-3">
                        <label for="fis_tipi" class="form-label">Fiş Tipi</label>
                        <select class="form-select" id="fis_tipi" name="fis_tipi">
                            <option value="Tahsilat">Tahsilat</option>
                            <option value="Ödeme">Ödeme</option>
                            <option value="Fatura">Fatura</option>
                            <option value="İade">İade</option>
                            <option value="Diğer">Diğer</option>
                        </select>
                    </div>
                    <div class="col-md-4 mb-3">
                        <label for="izah" class="form-label">İzah</label>
                        <textarea class="form-control" id="izah" name="izah" rows="3" placeholder="İşlem hakkında açıklama yazınız"></textarea>
                    </div>
                </div>
                <div class="row mt-3">
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <a href="cari_hareketleri.php<?php echo $cari_id > 0 ? '?cari_id=' . $cari_id : ''; ?>" class="btn btn-secondary">İptal</a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?> 