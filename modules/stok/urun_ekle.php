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
    $birim = clean($_POST['birim']);
    $barkod = isset($_POST['barkod']) ? clean($_POST['barkod']) : null;
    
    // Barkod boşsa stok kodunu ata
    if (empty($barkod)) {
        $barkod = $stok_kodu;
    }
    
    $marka = isset($_POST['marka']) ? clean($_POST['marka']) : null;
    $model = isset($_POST['model']) ? clean($_POST['model']) : null;
    $aciklama = isset($_POST['description']) ? clean($_POST['description']) : null;
    
    // Araç ve parça bilgileri
    $main_category = isset($_POST['main_category']) ? clean($_POST['main_category']) : null;
    $sub_category = isset($_POST['sub_category']) ? clean($_POST['sub_category']) : null;
    
    // Kaldırılan alanlar için varsayılan değerler
    $alis_fiyati = 0;
    $satis_fiyati = 0;
    $kdv_orani = 18;
    $stok_miktari = 0;
    $min_stok = 0;
    
    $durum = isset($_POST['durum']) ? clean($_POST['durum']) : 'active';
    
    // Araç ve Parça Bilgileri
    $oem_no = isset($_POST['oem_no']) ? clean($_POST['oem_no']) : null;
    $cross_reference = isset($_POST['cross_reference']) ? clean($_POST['cross_reference']) : null;
    $dimensions = isset($_POST['dimensions']) ? clean($_POST['dimensions']) : null;
    $shelf_code = isset($_POST['shelf_code']) ? clean($_POST['shelf_code']) : null;
    $vehicle_brand = isset($_POST['vehicle_brand']) ? clean($_POST['vehicle_brand']) : null;
    $vehicle_model = isset($_POST['vehicle_model']) ? clean($_POST['vehicle_model']) : null;
    
    // ID değişkenlerini tanımla
    $main_category_id = null;
    $sub_category_id = null;
    $brand_id = null;
    $vehicle_model_id = null;
    $shelf_code_id = null;
    $vehicle_brand_id = null;
    $year_range = null;
    $chassis_id = null;
    $engine_id = null;
    $birim_id = null;
    $kdv_id = null;
    
    // Birim ID değerini belirle
    if (!empty($birim)) {
        // Birim adına göre ID bul veya yeni birim oluştur
        try {
            $birim_stmt = $db->prepare("SELECT ID FROM birimler WHERE BIRIMADI = ?");
            $birim_stmt->execute([$birim]);
            $birim_result = $birim_stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($birim_result) {
                $birim_id = $birim_result['ID'];
            } else {
                // Yeni birim oluştur
                $max_birim_stmt = $db->query("SELECT MAX(ID) as max_id FROM birimler");
                $max_birim = $max_birim_stmt->fetch(PDO::FETCH_ASSOC);
                $new_birim_id = ($max_birim['max_id'] ?? 0) + 1;
                
                $ins_birim_stmt = $db->prepare("INSERT INTO birimler (ID, BIRIMADI) VALUES (?, ?)");
                $ins_birim_stmt->execute([$new_birim_id, $birim]);
                $birim_id = $new_birim_id;
            }
        } catch (PDOException $e) {
            // Hata durumunda varsayılan birim ID'si (örneğin Adet için 1)
            $birim_id = 1;
        }
    }
    
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
            
            // stk_urun_miktar tablosunu kontrol et ve yeniden oluştur
            $db->exec("DROP TABLE IF EXISTS stk_urun_miktar");
            $db->exec("CREATE TABLE stk_urun_miktar (
                ID int(11) NOT NULL AUTO_INCREMENT,
                URUN_ID int(11) NOT NULL,
                MIKTAR decimal(10,2) NOT NULL DEFAULT '0.00',
                SON_GUNCELLEME datetime NOT NULL DEFAULT current_timestamp(),
                PRIMARY KEY (ID),
                UNIQUE KEY URUN_ID (URUN_ID)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci");
            
            // Stok tablosunun yapısını kontrol et
            $tableCheck = $db->query("SHOW CREATE TABLE stok");
            $tableInfo = $tableCheck->fetch(PDO::FETCH_ASSOC);
            $createTableSQL = $tableInfo['Create Table'] ?? '';
            
            // Yeni bir ID oluştur
            $stmt = $db->query("SELECT MAX(ID) as max_id FROM stok");
            $row = $stmt->fetch(PDO::FETCH_ASSOC);
            $new_id = ($row['max_id'] ?? 0) + 1;
            
            // Ürünü kaydet
            $sql = "INSERT INTO stok (ID, KARTTIPI, KOD, ADI, DURUM, KDVIDPERAKENDE, OZELGRUP1, OZELGRUP2, OZELGRUP4, OZELGRUP6, ACIKLAMA, 
                    MIN_STOK, DEPOUSEDEF, DEPOBSIZUYAR, ERP_YERTAKIBI, ERP_AMBALAJ, ERP_VARYANTLI, YERLI_URETIM, 
                    HS_BIRIM_KATSAYISINDAN_FIYAT_HESAPLA, YAZDIRMA_GRUPID, 
                    OZELALAN1, OZELALAN2, OZELALAN3, OZELGRUP3, 
                    OZELGRUP5, OZELGRUP7, OZELGRUP8, OZELGRUP9, 
                    EKLENME_TARIHI, EKLEYEN_ID, KDVID, GUNCELLEYEN_ID) 
                    VALUES (?, 'U', ?, ?, ?, 0, ?, ?, ?, ?, ?, 
                    ?, 0, 0, 0, 0, 0, 0, 
                    0, 0, 
                    ?, ?, ?, ?, 
                    ?, ?, ?, ?, 
                    NOW(), ?, 0, -1)";
            
            // KOD'un benzersiz olup olmadığını kontrol et
            $stmt = $db->prepare("SELECT COUNT(*) as count FROM stok WHERE KOD = ?");
            $stmt->execute([$stok_kodu]);
            $kodCheck = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($kodCheck['count'] > 0) {
                // KOD'u benzersiz yap
                $stok_kodu = $stok_kodu . '_' . time();
            }
            
            $stmt = $db->prepare($sql);
            $result = $stmt->execute([
                $new_id, $stok_kodu, $urun_adi, $durum == 'active' ? 1 : 0, $main_category_id, $sub_category_id, $brand_id, $vehicle_model_id, $aciklama, 
                $min_stok, 
                $oem_no, $cross_reference, $dimensions, $shelf_code_id, 
                $vehicle_brand_id, $year_range, $chassis_id, $engine_id, 
                $_SESSION['user_id']
            ]);
            
            if (!$result) {
                throw new Exception("SQL sorgusu çalıştırılamadı: " . implode(", ", $stmt->errorInfo()));
            }
            
            $product_id = $new_id; // Oluşturduğumuz ID'yi kullan
            
            if (!$product_id) {
                throw new Exception("Ürün veritabanına eklendi ancak ID alınamadı.");
            }
            
            // Ürün ID kontrolü
            if ($product_id > 0) {
                // Barkod bilgisini barkodlar tablosuna ekle
                if (!empty($barkod)) {
                    $stmt = $db->prepare("INSERT INTO barkodlar (stok_id, barkod) VALUES (?, ?)");
                    $stmt->execute([$product_id, $barkod]);
                }
                
                // Birim bilgisini ekle
                if (!empty($birim_id)) {
                    // Yeni bir ID oluştur
                    $stmt = $db->query("SELECT MAX(ID) as max_id FROM stk_birim");
                    $birim_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $birim_tablo_id = ($birim_row['max_id'] ?? 0) + 1;
                    
                    // ID değerini manuel olarak belirt
                    $stmt = $db->prepare("INSERT INTO stk_birim (ID, STOKID, BIRIMID) VALUES (?, ?, ?)");
                    $stmt->execute([$birim_tablo_id, $product_id, $birim_id]);
                }
                
                // Fiyat bilgilerini ekle
                if (is_numeric($alis_fiyati)) {
                    // Yeni bir ID oluştur
                    $stmt = $db->query("SELECT MAX(ID) as max_id FROM stk_fiyat");
                    $fiyat_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $fiyat_id = ($fiyat_row['max_id'] ?? 0) + 1;
                    
                    // ID değerini manuel olarak belirt
                    $stmt = $db->prepare("INSERT INTO stk_fiyat (ID, STOKID, TIP, FIYAT) VALUES (?, ?, 'A', ?)");
                    $stmt->execute([$fiyat_id, $product_id, $alis_fiyati]);
                }
                
                if (is_numeric($satis_fiyati)) {
                    // Yeni bir ID oluştur
                    $stmt = $db->query("SELECT MAX(ID) as max_id FROM stk_fiyat");
                    $fiyat_row = $stmt->fetch(PDO::FETCH_ASSOC);
                    $fiyat_id = ($fiyat_row['max_id'] ?? 0) + 1;
                    
                    // ID değerini manuel olarak belirt
                    $stmt = $db->prepare("INSERT INTO stk_fiyat (ID, STOKID, TIP, FIYAT) VALUES (?, ?, 'S', ?)");
                    $stmt->execute([$fiyat_id, $product_id, $satis_fiyati]);
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
                    
                    // Ürün kaydı var mı kontrol et, varsa güncelle, yoksa ekle
                    // Önce kaydı sil
                    $delete_sql = "DELETE FROM stk_urun_miktar WHERE URUN_ID = ?";
                    $delete_stmt = $db->prepare($delete_sql);
                    $delete_stmt->execute([$product_id]);
                    
                    // Sonra yeni kayıt ekle
                    $insert_sql = "INSERT INTO stk_urun_miktar (URUN_ID, MIKTAR) VALUES (?, ?)";
                    $insert_stmt = $db->prepare($insert_sql);
                    $insert_stmt->execute([$product_id, $stok_miktari]);
                }
            } else {
                throw new Exception("Ürün kaydı oluşturulamadı: Geçersiz ürün ID.");
            }
            
            // İşlemi tamamla
            $db->commit();
            
            // Başarılı mesajı ve yönlendirme
            $_SESSION['success_message'] = "Ürün başarıyla eklendi.";
            header("Location: urun_detay.php?id=" . $product_id);
            exit;
            
        } catch (Exception $e) {
            // Hata durumunda işlemi geri al
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            $errors[] = $e->getMessage();
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
                <label for="stok_kodu" class="form-label">Stok Kodu (EAN-13) <span class="text-danger">*</span></label>
                <div class="input-group">
                    <input type="text" class="form-control" id="stok_kodu" name="stok_kodu" value="<?php echo generateEAN13('9670000', $db); ?>" required>
                    <button class="btn btn-outline-secondary" type="button" id="generateEan13">Yeni Oluştur</button>
                </div>
                <small class="form-text text-muted">EAN-13 formatında sıralı olarak oluşturulmuştur.</small>
            </div>
            <div class="col-md-3">
                <label for="barkod" class="form-label">Barkod</label>
                <input type="text" class="form-control" id="barkod" name="barkod" readonly>
                <small class="form-text text-muted">Stok kodu ile otomatik olarak aynı değeri alır.</small>
            </div>
            <div class="col-md-6">
                <label for="urun_adi" class="form-label">Ürün Adı <span class="text-danger">*</span></label>
                <input type="text" class="form-control" id="urun_adi" name="urun_adi" required>
            </div>
            
            <div class="col-md-3">
                <label for="main_category" class="form-label">Ana Kategori</label>
                <input type="text" class="form-control" id="main_category" name="main_category">
            </div>
            <div class="col-md-3">
                <label for="sub_category" class="form-label">Alt Kategori</label>
                <input type="text" class="form-control" id="sub_category" name="sub_category">
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
        // EAN-13 barkod oluşturma butonu
        document.getElementById('generateEan13').addEventListener('click', function() {
            // AJAX ile sunucudan yeni bir EAN-13 kodu iste
            fetch('generate_ean13.php')
                .then(response => response.text())
                .then(ean13 => {
                    document.getElementById('stok_kodu').value = ean13;
                    // Barkod alanını da güncelle
                    document.getElementById('barkod').value = ean13;
                })
                .catch(error => {
                    console.error('EAN-13 oluşturulamadı:', error);
                    // Hata durumunda istemci tarafında basit bir EAN-13 oluştur
                    const newEan13 = generateClientEAN13();
                    document.getElementById('stok_kodu').value = newEan13;
                    // Barkod alanını da güncelle
                    document.getElementById('barkod').value = newEan13;
                });
        });
        
        // Sayfa yüklendiğinde barkod alanını stok kodu ile doldur
        const stokKodu = document.getElementById('stok_kodu').value;
        if (stokKodu) {
            document.getElementById('barkod').value = stokKodu;
        }
        
        // Stok kodu manuel değiştirilirse barkod alanını da güncelle
        document.getElementById('stok_kodu').addEventListener('input', function() {
            document.getElementById('barkod').value = this.value;
        });
        
        // İstemci tarafında EAN-13 oluşturma (sunucu çalışmazsa)
        function generateClientEAN13() {
            // Prefix (firma kodu)
            let code = '9670000';
            
            // Mevcut stok kodundaki değeri al
            let currentCode = document.getElementById('stok_kodu').value;
            let lastNumber = 0;
            
            if (currentCode && currentCode.startsWith('9670000')) {
                // Prefix'i kaldır ve sıra numarasını al
                let sequencePart = currentCode.substring(7, 12); // 5 haneli sıra no
                lastNumber = parseInt(sequencePart) || 0;
            }
            
            // Sıradaki numarayı oluştur
            let nextNumber = lastNumber + 1;
            
            // Kalan haneleri sıfırla doldur ve sıra numarasını ekle
            let sequenceStr = nextNumber.toString().padStart(5, '0');
            
            // Tam kodu oluştur (prefix + sıra numarası)
            code = '9670000' + sequenceStr;
            
            // Kontrol hanesi hesaplama (EAN-13 algoritması)
            let sum = 0;
            for (let i = 0; i < 12; i++) {
                sum += parseInt(code[i]) * (i % 2 === 0 ? 1 : 3);
            }
            let checksum = (10 - (sum % 10)) % 10;
            
            // Son kodu döndür (12 hane + kontrol hanesi)
            return code + checksum;
        }
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 