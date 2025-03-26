<?php
/**
 * ERP Sistem - Ürün Ekleme Sayfası
 * 
 * Bu dosya yeni ürün ekleme işlemlerini gerçekleştirir.
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
$pageTitle = "Yeni Ürün Ekle";

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al ve temizle
    $stok_kodu = clean($_POST['stok_kodu']);
    $urun_adi = cleanProductName($_POST['urun_adi']);
    $kategori_id = isset($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
    $birim = clean($_POST['birim']);
    $barkod = isset($_POST['barkod']) ? clean($_POST['barkod']) : null;
    $marka = isset($_POST['marka']) ? clean($_POST['marka']) : null;
    $model = isset($_POST['model']) ? clean($_POST['model']) : null;
    $aciklama = isset($_POST['description']) ? clean($_POST['description']) : null;
    $alis_fiyati = isset($_POST['alis_fiyati']) ? (float)$_POST['alis_fiyati'] : 0;
    $satis_fiyati = isset($_POST['satis_fiyati']) ? (float)$_POST['satis_fiyati'] : 0;
    $kdv_orani = isset($_POST['kdv_orani']) ? (float)$_POST['kdv_orani'] : 0;
    $stok_miktari = isset($_POST['stok_miktari']) ? (float)$_POST['stok_miktari'] : 0;
    $min_stok = isset($_POST['min_stok']) ? (float)$_POST['min_stok'] : 0;
    $durum = isset($_POST['durum']) ? clean($_POST['durum']) : 'active';
    
    // Araç ve Parça Bilgileri
    $oem_no = isset($_POST['oem_no']) ? clean($_POST['oem_no']) : null;
    $cross_reference = isset($_POST['cross_reference']) ? clean($_POST['cross_reference']) : null;
    $dimensions = isset($_POST['dimensions']) ? clean($_POST['dimensions']) : null;
    $shelf_code = isset($_POST['shelf_code']) ? clean($_POST['shelf_code']) : null;
    $vehicle_brand = isset($_POST['vehicle_brand']) ? clean($_POST['vehicle_brand']) : null;
    $vehicle_model = isset($_POST['vehicle_model']) ? clean($_POST['vehicle_model']) : null;
    $main_category = isset($_POST['main_category']) ? clean($_POST['main_category']) : null;
    $sub_category = isset($_POST['sub_category']) ? clean($_POST['sub_category']) : null;
    
    // Hata kontrolü
    $errors = [];
    
    // Zorunlu alanları kontrol et
    if (empty($stok_kodu)) {
        $errors[] = "Stok kodu zorunludur.";
    }
    if (empty($urun_adi)) {
        $errors[] = "Ürün adı zorunludur.";
    }
    if (empty($birim)) {
        $errors[] = "Birim bilgisi zorunludur.";
    }
    
    // Stok kodu benzersiz olmalı
    try {
        $stmt = $db->prepare("SELECT COUNT(*) FROM stok WHERE KOD = ?");
        $stmt->execute([$stok_kodu]);
        if ($stmt->fetchColumn() > 0) {
            $errors[] = "Bu stok kodu zaten kullanılmaktadır. Lütfen başka bir stok kodu girin.";
        }
    } catch (PDOException $e) {
        $errors[] = "Veritabanı hatası: " . $e->getMessage();
    }
    
    // Hata yoksa ürünü kaydet
    if (empty($errors)) {
        try {
            // İşlemi başlat
            $db->beginTransaction();
            
            // Ürünü kaydet
            $sql = "INSERT INTO stok (KOD, ADI, OZELGRUP1, OZELGRUP2, BARKOD, OZELGRUP4, OZELGRUP6, ACIKLAMA, 
                     MIN_STOK, DURUM, 
                     OZELALAN1, OZELALAN2, OZELALAN3, OZELGRUP3, 
                     OZELGRUP5, OZELGRUP7, OZELGRUP8, OZELGRUP9, 
                     EKLENME_TARIHI, EKLEYEN_ID) 
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 
                     ?, ?, 
                     ?, ?, ?, ?, 
                     ?, ?, ?, ?, 
                     NOW(), ?)";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $stok_kodu, $urun_adi, $main_category_id, $sub_category_id, $barkod, $brand_id, $vehicle_model_id, $aciklama, 
                $min_stok, $durum == 'active' ? 1 : 0, 
                $oem_no, $cross_reference, $dimensions, $shelf_code_id, 
                $vehicle_brand_id, $year_range, $chassis_id, $engine_id, 
                $_SESSION['user_id']
            ]);
            
            $product_id = $db->lastInsertId();
            
            // Birim bilgisini ekle
            if (!empty($birim_id)) {
                $stmt = $db->prepare("INSERT INTO stk_birim (STOKID, BIRIMID) VALUES (?, ?)");
                $stmt->execute([$product_id, $birim_id]);
            }
            
            // Fiyat bilgilerini ekle
            if (is_numeric($alis_fiyati)) {
                $stmt = $db->prepare("INSERT INTO stk_fiyat (STOKID, TIP, FIYAT) VALUES (?, 'A', ?)");
                $stmt->execute([$product_id, $alis_fiyati]);
            }
            
            if (is_numeric($satis_fiyati)) {
                $stmt = $db->prepare("INSERT INTO stk_fiyat (STOKID, TIP, FIYAT) VALUES (?, 'S', ?)");
                $stmt->execute([$product_id, $satis_fiyati]);
            }
            
            // KDV bilgisini ekle
            if (is_numeric($kdv_id)) {
                $stmt = $db->prepare("UPDATE stok SET KDVID = ? WHERE ID = ?");
                $stmt->execute([$kdv_id, $product_id]);
            }
            
            // OEM numaralarını işle
            if (!empty($oem_no)) {
                $oem_result = processOEMNumbers($db, $product_id, $oem_no, false);
                
                if (!$oem_result['success']) {
                    throw new Exception($oem_result['message']);
                }
            }
            
            // Eğer ilk stok girişi yapıldıysa, stok hareketi ve miktar kaydı ekle
            if ($stok_miktari > 0) {
                // Stok hareketini STK_FIS_HAR tablosuna kaydet
                $sql = "INSERT INTO STK_FIS_HAR (URUN_ID, HAREKET_TIPI, MIKTAR, BIRIM_FIYAT, REFERANS_TIP, ACIKLAMA, KULLANICI_ID, TARIH) 
                        VALUES (?, 'giris', ?, ?, 'ilk_stok', 'İlk stok girişi', ?, NOW())";
                $stmt = $db->prepare($sql);
                $stmt->execute([$product_id, $stok_miktari, $alis_fiyati, $_SESSION['user_id']]);
                
                // Toplam miktarı STK_URUN_MIKTAR tablosuna ekle
                $sql = "INSERT INTO stk_urun_miktar (URUN_ID, MIKTAR) VALUES (?, ?)";
                $stmt = $db->prepare($sql);
                $stmt->execute([$product_id, $stok_miktari]);
            }
            
            // İşlemi tamamla
            $db->commit();
            
            // Başarılı mesajı ve yönlendirme
            $_SESSION['success_message'] = "Ürün başarıyla eklendi.";
            header("Location: urun_detay.php?id=" . $product_id);
            exit;
            
        } catch (PDOException $e) {
            // Hata durumunda işlemi geri al
            $db->rollBack();
            $errors[] = "Veritabanı hatası: " . $e->getMessage();
        }
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Yeni Ürün Ekle</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Ürün Listesine Dön
            </a>
        </div>
    </div>
</div>

<!-- Hata Mesajları -->
<?php
if (isset($errors) && !empty($errors)) {
    echo '<div class="alert alert-danger" role="alert">';
    echo '<ul class="mb-0">';
    foreach ($errors as $error) {
        echo '<li>' . $error . '</li>';
    }
    echo '</ul>';
    echo '</div>';
}
?>

<!-- Ürün Ekleme Formu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Ürün Bilgileri</h6>
    </div>
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="row g-3">
            
            <!-- Temel Bilgiler -->
            <div class="col-md-12">
                <h5 class="border-bottom pb-2">Temel Bilgiler</h5>
            </div>
            
            <div class="col-md-3">
                <label for="stok_kodu" class="form-label">Stok Kodu <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="stok_kodu" name="stok_kodu" required>
            </div>
            <div class="col-md-3">
                <label for="barkod" class="form-label">Barkod</label>
                <input type="text" class="form-control" id="barkod" name="barkod">
            </div>
            <div class="col-md-6">
                <label for="urun_adi" class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="urun_adi" name="urun_adi" required>
            </div>
            
            <div class="col-md-3">
                <label for="kategori_id" class="form-label">Kategori</label>
                <select class="form-select" id="kategori_id" name="kategori_id">
                    <option value="">Kategori Seçin</option>
                    <?php
                    // Kategorileri listele
                    try {
                        $stmt = $db->query("SELECT * FROM product_categories ORDER BY name");
                        while ($row = $stmt->fetch()) {
                            echo '<option value="' . $row['id'] . '">' . htmlspecialchars($row['name']) . '</option>';
                        }
                    } catch (PDOException $e) {
                        echo '<option value="">Kategoriler yüklenemedi</option>';
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-3">
                <label for="birim" class="form-label">Birim <span class="text-danger">*</span></label>
                <select class="form-select" id="birim" name="birim" required>
                    <option value="Adet">Adet</option>
                    <option value="Kg">Kg</option>
                    <option value="Lt">Lt</option>
                    <option value="Mt">Mt</option>
                    <option value="Paket">Paket</option>
                    <option value="Kutu">Kutu</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="marka" class="form-label">Marka</label>
                <input type="text" class="form-control" id="marka" name="marka">
            </div>
            <div class="col-md-3">
                <label for="model" class="form-label">Ürün No:</label>
                <input type="text" class="form-control" id="model" name="model">
            </div>
            
            <!-- Stok ve Fiyat Bilgileri -->
            <div class="col-md-12 mt-3">
                <h5 class="border-bottom pb-2">Stok ve Fiyat Bilgileri</h5>
            </div>
            
            <div class="col-md-3">
                <label for="alis_fiyati" class="form-label">Alış Fiyatı <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" class="form-control" id="alis_fiyati" name="alis_fiyati" step="0.01" required>
                    <span class="input-group-text">₺</span>
                </div>
            </div>
            <div class="col-md-3">
                <label for="satis_fiyati" class="form-label">Satış Fiyatı <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="number" class="form-control" id="satis_fiyati" name="satis_fiyati" step="0.01" required>
                    <span class="input-group-text">₺</span>
                </div>
            </div>
            <div class="col-md-2">
                <label for="kdv_orani" class="form-label">KDV Oranı</label>
                <div class="input-group">
                    <input type="number" class="form-control" id="kdv_orani" name="kdv_orani" value="18" min="0" max="100">
                    <span class="input-group-text">%</span>
                </div>
            </div>
            <div class="col-md-2">
                <label for="stok_miktari" class="form-label">Mevcut Stok</label>
                <input type="number" class="form-control" id="stok_miktari" name="stok_miktari" value="0" step="0.01">
            </div>
            <div class="col-md-2">
                <label for="min_stok" class="form-label">Minimum Stok</label>
                <input type="number" class="form-control" id="min_stok" name="min_stok" value="0" step="0.01">
            </div>
            
            <!-- Ürün Detayları -->
            <div class="col-md-12 mt-3">
                <h5 class="border-bottom pb-2">Ürün Detayları</h5>
            </div>
            
            <div class="col-md-12">
                <label for="description" class="form-label">Açıklama</label>
                <textarea class="form-control" id="description" name="description" rows="3"></textarea>
            </div>

            <!-- Araç ve Parça Bilgileri -->
            <div class="col-md-12 mt-3">
                <h5 class="border-bottom pb-2">Araç ve Parça Bilgileri</h5>
            </div>
            
            <div class="col-md-3">
                <label for="oem_no" class="form-label">OEM No</label>
                <textarea class="form-control" id="oem_no" name="oem_no" rows="3"></textarea>
                <small class="form-text text-muted">Her satıra bir OEM numarası girin. Birden fazla numara girerseniz muadil ürün olarak eşleştirilecektir.</small>
            </div>
            <div class="col-md-3">
                <label for="cross_reference" class="form-label">Çapraz Referans</label>
                <textarea class="form-control" id="cross_reference" name="cross_reference" rows="2"></textarea>
            </div>
            <div class="col-md-3">
                <label for="dimensions" class="form-label">Ürün Ölçüleri</label>
                <input type="text" class="form-control" id="dimensions" name="dimensions">
            </div>
            <div class="col-md-3">
                <label for="shelf_code" class="form-label">Raf Kodu</label>
                <input type="text" class="form-control" id="shelf_code" name="shelf_code">
            </div>
            
            <!-- Araç Bilgileri -->
            <div class="col-md-3">
                <label for="vehicle_brand" class="form-label">Araç Markası</label>
                <input type="text" class="form-control" id="vehicle_brand" name="vehicle_brand">
            </div>
            <div class="col-md-3">
                <label for="vehicle_model" class="form-label">Araç Modeli</label>
                <input type="text" class="form-control" id="vehicle_model" name="vehicle_model">
            </div>
            <div class="col-md-3">
                <label for="main_category" class="form-label">Ana Kategori</label>
                <input type="text" class="form-control" id="main_category" name="main_category">
            </div>
            <div class="col-md-3">
                <label for="sub_category" class="form-label">Alt Kategori</label>
                <input type="text" class="form-control" id="sub_category" name="sub_category">
            </div>
            
            <div class="col-md-12 mt-3">
                <h5 class="border-bottom pb-2">Diğer Bilgiler</h5>
            </div>
            
            <div class="col-md-12">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="active" id="durum" name="durum" checked>
                    <label class="form-check-label" for="durum">
                        Aktif
                    </label>
                </div>
            </div>
            
            <!-- Butonlar -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Kaydet
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sayısal girişleri formatlama
        const numberInputs = document.querySelectorAll('#alis_fiyati, #satis_fiyati, #stok_miktari, #min_stok');
        
        numberInputs.forEach(input => {
            input.addEventListener('blur', function() {
                // Boşsa veya geçersizse 0 olarak ayarla
                if (this.value === '' || isNaN(parseFloat(this.value))) {
                    this.value = '0.00';
                } else {
                    // Sayısal değeri formatlı göster
                    this.value = parseFloat(this.value).toFixed(2);
                }
            });
        });
        
        // Stok kodu otomatik büyük harf yapma
        document.getElementById('stok_kodu').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 