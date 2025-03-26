<?php
/**
 * ERP Sistem - Cari Ekleme Sayfası
 * 
 * Bu dosya müşteri ve tedarikçi ekleme işlemlerini gerçekleştirir.
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

// Sayfa başlığı
$pageTitle = "Yeni Cari Ekle";

// Hata ve başarı mesajları için değişkenler
$error = "";
$success = "";

// Form değişkenlerinin varsayılan değerlerini tanımla
$cari_tipi = 'musteri';
$firma_unvani = $yetkili_ad = $yetkili_soyad = $vergi_dairesi = $vergi_no = $tc_kimlik_no = '';
$adres = $il = $ilce = $posta_kodu = $telefon = $cep_telefon = $faks = $email = $web_sitesi = '';
$banka_adi = $sube_adi = $hesap_no = $iban = $kategori = $aciklama = '';
$risk_limiti = $odeme_vade_suresi = $iskonto_orani = 0;
$durum = 1;

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al ve temizle
    $cari_tipi = isset($_POST['cari_tipi']) ? clean($_POST['cari_tipi']) : 'musteri';
    $firma_unvani = isset($_POST['firma_unvani']) ? clean($_POST['firma_unvani']) : '';
    $yetkili_ad = isset($_POST['yetkili_ad']) ? clean($_POST['yetkili_ad']) : '';
    $yetkili_soyad = isset($_POST['yetkili_soyad']) ? clean($_POST['yetkili_soyad']) : '';
    $vergi_dairesi = isset($_POST['vergi_dairesi']) ? clean($_POST['vergi_dairesi']) : '';
    $vergi_no = isset($_POST['vergi_no']) ? clean($_POST['vergi_no']) : '';
    $tc_kimlik_no = isset($_POST['tc_kimlik_no']) ? clean($_POST['tc_kimlik_no']) : '';
    $adres = isset($_POST['adres']) ? clean($_POST['adres']) : '';
    $il = isset($_POST['il']) ? clean($_POST['il']) : '';
    $ilce = isset($_POST['ilce']) ? clean($_POST['ilce']) : '';
    $posta_kodu = isset($_POST['posta_kodu']) ? clean($_POST['posta_kodu']) : '';
    $telefon = isset($_POST['telefon']) ? clean($_POST['telefon']) : '';
    $cep_telefon = isset($_POST['cep_telefon']) ? clean($_POST['cep_telefon']) : '';
    $faks = isset($_POST['faks']) ? clean($_POST['faks']) : '';
    $email = isset($_POST['email']) ? clean($_POST['email']) : '';
    $web_sitesi = isset($_POST['web_sitesi']) ? clean($_POST['web_sitesi']) : '';
    $banka_adi = isset($_POST['banka_adi']) ? clean($_POST['banka_adi']) : '';
    $sube_adi = isset($_POST['sube_adi']) ? clean($_POST['sube_adi']) : '';
    $hesap_no = isset($_POST['hesap_no']) ? clean($_POST['hesap_no']) : '';
    $iban = isset($_POST['iban']) ? clean($_POST['iban']) : '';
    $risk_limiti = isset($_POST['risk_limiti']) ? (float)$_POST['risk_limiti'] : 0.00;
    $odeme_vade_suresi = isset($_POST['odeme_vade_suresi']) ? (int)$_POST['odeme_vade_suresi'] : 0;
    $iskonto_orani = isset($_POST['iskonto_orani']) ? (float)$_POST['iskonto_orani'] : 0.00;
    $kategori = isset($_POST['kategori']) ? clean($_POST['kategori']) : '';
    $aciklama = isset($_POST['aciklama']) ? clean($_POST['aciklama']) : '';
    $durum = isset($_POST['durum']) ? (int)$_POST['durum'] : 1;
    
    // Zorunlu alanları kontrol et
    $required_fields = array(
        'firma_unvani' => 'Firma ünvanı',
        'telefon' => 'Telefon'
    );
    
    $validation_errors = array();
    foreach ($required_fields as $field => $label) {
        if (empty(${$field})) {
            $validation_errors[] = $label . ' alanı zorunludur.';
        }
    }
    
    if (!empty($validation_errors)) {
        $error = implode('<br>', $validation_errors);
    } else {
        try {
            // Benzersiz cari kodu oluştur
            $prefix = $cari_tipi == 'musteri' ? 'MUS' : ($cari_tipi == 'tedarikci' ? 'TED' : 'HER');
            
            // Son kodu al
            $stmt = $db->query("SELECT KOD FROM cari WHERE KOD LIKE '{$prefix}%' ORDER BY KOD DESC LIMIT 1");
            $last_code = $stmt->fetchColumn();
            
            if ($last_code) {
                // Son koddan sayısal kısmı al ve bir artır
                $numeric_part = (int)substr($last_code, strlen($prefix));
                $next_numeric = $numeric_part + 1;
                $cari_kodu = $prefix . str_pad($next_numeric, 3, '0', STR_PAD_LEFT);
            } else {
                // İlk kayıt
                $cari_kodu = $prefix . '001';
            }
            
            // Cari ekle
            $sql = "INSERT INTO cari (
                KOD, TIP, ADI, YETKILI_ADI, YETKILI_SOYADI, 
                ADRES, IL, ILCE, POSTA_KODU, 
                TELEFON, CEPNO, FAX, EMAIL, WEB, 
                LIMITTL, VADE, DURUM, NOTLAR
            ) VALUES (
                :cari_kodu, :cari_tipi, :firma_unvani, :yetkili_ad, :yetkili_soyad, 
                :adres, :il, :ilce, :posta_kodu, 
                :telefon, :cep_telefon, :faks, :email, :web_sitesi, 
                :risk_limiti, :odeme_vade_suresi, :durum, :aciklama
            )";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':cari_kodu', $cari_kodu);
            $stmt->bindParam(':cari_tipi', $cari_tipi);
            $stmt->bindParam(':firma_unvani', $firma_unvani);
            $stmt->bindParam(':yetkili_ad', $yetkili_ad);
            $stmt->bindParam(':yetkili_soyad', $yetkili_soyad);
            $stmt->bindParam(':adres', $adres);
            $stmt->bindParam(':il', $il);
            $stmt->bindParam(':ilce', $ilce);
            $stmt->bindParam(':posta_kodu', $posta_kodu);
            $stmt->bindParam(':telefon', $telefon);
            $stmt->bindParam(':cep_telefon', $cep_telefon);
            $stmt->bindParam(':faks', $faks);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':web_sitesi', $web_sitesi);
            $stmt->bindParam(':risk_limiti', $risk_limiti);
            $stmt->bindParam(':odeme_vade_suresi', $odeme_vade_suresi);
            $stmt->bindParam(':durum', $durum);
            $stmt->bindParam(':aciklama', $aciklama);
            
            if ($stmt->execute()) {
                $cari_id = $db->lastInsertId();
                $cari_tipi_text = $cari_tipi == 'musteri' ? 'Müşteri' : ($cari_tipi == 'tedarikci' ? 'Tedarikçi' : 'Müşteri/Tedarikçi');
                $success = $cari_tipi_text . " başarıyla eklendi. Cari Kodu: " . $cari_kodu;
                
                // Formu temizle
                $cari_tipi = 'musteri';
                $firma_unvani = $yetkili_ad = $yetkili_soyad = $vergi_dairesi = $vergi_no = $tc_kimlik_no = '';
                $adres = $il = $ilce = $posta_kodu = $telefon = $cep_telefon = $faks = $email = $web_sitesi = '';
                $banka_adi = $sube_adi = $hesap_no = $iban = $kategori = $aciklama = '';
                $risk_limiti = $odeme_vade_suresi = $iskonto_orani = 0;
                $durum = 1;
            } else {
                $error = "Cari eklenirken bir hata oluştu.";
            }
        } catch (PDOException $e) {
            $error = "Veritabanı hatası: " . $e->getMessage();
        }
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
                <h1 class="h2">Yeni Cari Ekle</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="index.php" class="btn btn-sm btn-outline-secondary">Cari Listesine Dön</a>
                    </div>
                </div>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Cari Bilgileri</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                        <div class="row mb-3">
                            <div class="col-md-4">
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="cari_tipi" id="tipi_musteri" value="musteri" <?php echo $cari_tipi == 'musteri' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tipi_musteri">Müşteri</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="cari_tipi" id="tipi_tedarikci" value="tedarikci" <?php echo $cari_tipi == 'tedarikci' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tipi_tedarikci">Tedarikçi</label>
                                </div>
                                <div class="form-check form-check-inline">
                                    <input class="form-check-input" type="radio" name="cari_tipi" id="tipi_her_ikisi" value="her_ikisi" <?php echo $cari_tipi == 'her_ikisi' ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="tipi_her_ikisi">Her İkisi</label>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="durum" id="durum" value="1" <?php echo $durum ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="durum">Aktif</label>
                                </div>
                            </div>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">Firma Bilgileri</h5>
                                
                                <div class="mb-3">
                                    <label for="firma_unvani" class="form-label">Firma Ünvanı <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="firma_unvani" name="firma_unvani" value="<?php echo htmlspecialchars($firma_unvani); ?>" required>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="yetkili_ad" class="form-label">Yetkili Adı</label>
                                            <input type="text" class="form-control" id="yetkili_ad" name="yetkili_ad" value="<?php echo htmlspecialchars($yetkili_ad); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="yetkili_soyad" class="form-label">Yetkili Soyadı</label>
                                            <input type="text" class="form-control" id="yetkili_soyad" name="yetkili_soyad" value="<?php echo htmlspecialchars($yetkili_soyad); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="vergi_dairesi" class="form-label">Vergi Dairesi</label>
                                            <input type="text" class="form-control" id="vergi_dairesi" name="vergi_dairesi" value="<?php echo htmlspecialchars($vergi_dairesi); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="mb-3">
                                            <label for="vergi_no" class="form-label">Vergi No</label>
                                            <input type="text" class="form-control" id="vergi_no" name="vergi_no" value="<?php echo htmlspecialchars($vergi_no); ?>">
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tc_kimlik_no" class="form-label">TC Kimlik No</label>
                                    <input type="text" class="form-control" id="tc_kimlik_no" name="tc_kimlik_no" value="<?php echo htmlspecialchars($tc_kimlik_no); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="adres" class="form-label">Adres</label>
                                    <textarea class="form-control" id="adres" name="adres" rows="3"><?php echo htmlspecialchars($adres); ?></textarea>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="il" class="form-label">İl</label>
                                            <input type="text" class="form-control" id="il" name="il" value="<?php echo htmlspecialchars($il); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="ilce" class="form-label">İlçe</label>
                                            <input type="text" class="form-control" id="ilce" name="ilce" value="<?php echo htmlspecialchars($ilce); ?>">
                                        </div>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="mb-3">
                                            <label for="posta_kodu" class="form-label">Posta Kodu</label>
                                            <input type="text" class="form-control" id="posta_kodu" name="posta_kodu" value="<?php echo htmlspecialchars($posta_kodu); ?>">
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <h5 class="border-bottom pb-2 mb-3">İletişim Bilgileri</h5>
                                
                                <div class="mb-3">
                                    <label for="telefon" class="form-label">Telefon <span class="text-danger">*</span></label>
                                    <input type="text" class="form-control" id="telefon" name="telefon" value="<?php echo htmlspecialchars($telefon); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="cep_telefon" class="form-label">Cep Telefonu</label>
                                    <input type="text" class="form-control" id="cep_telefon" name="cep_telefon" value="<?php echo htmlspecialchars($cep_telefon); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="faks" class="form-label">Faks</label>
                                    <input type="text" class="form-control" id="faks" name="faks" value="<?php echo htmlspecialchars($faks); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="email" class="form-label">E-posta</label>
                                    <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($email); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="web_sitesi" class="form-label">Web Sitesi</label>
                                    <input type="url" class="form-control" id="web_sitesi" name="web_sitesi" value="<?php echo htmlspecialchars($web_sitesi); ?>">
                                </div>
                                
                                <h5 class="border-bottom pb-2 mb-3 mt-4">Banka Bilgileri</h5>
                                
                                <div class="mb-3">
                                    <label for="banka_adi" class="form-label">Banka Adı</label>
                                    <input type="text" class="form-control" id="banka_adi" name="banka_adi" value="<?php echo htmlspecialchars($banka_adi); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="sube_adi" class="form-label">Şube Adı</label>
                                    <input type="text" class="form-control" id="sube_adi" name="sube_adi" value="<?php echo htmlspecialchars($sube_adi); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="hesap_no" class="form-label">Hesap No</label>
                                    <input type="text" class="form-control" id="hesap_no" name="hesap_no" value="<?php echo htmlspecialchars($hesap_no); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="iban" class="form-label">IBAN</label>
                                    <input type="text" class="form-control" id="iban" name="iban" value="<?php echo htmlspecialchars($iban); ?>">
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-md-12">
                                <h5 class="border-bottom pb-2 mb-3">Ek Bilgiler</h5>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="risk_limiti" class="form-label">Risk Limiti (₺)</label>
                                    <input type="number" class="form-control" id="risk_limiti" name="risk_limiti" step="0.01" min="0" value="<?php echo htmlspecialchars($risk_limiti); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="odeme_vade_suresi" class="form-label">Ödeme Vade Süresi (Gün)</label>
                                    <input type="number" class="form-control" id="odeme_vade_suresi" name="odeme_vade_suresi" min="0" value="<?php echo htmlspecialchars($odeme_vade_suresi); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="iskonto_orani" class="form-label">İskonto Oranı (%)</label>
                                    <input type="number" class="form-control" id="iskonto_orani" name="iskonto_orani" step="0.01" min="0" max="100" value="<?php echo htmlspecialchars($iskonto_orani); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-3">
                                <div class="mb-3">
                                    <label for="kategori" class="form-label">Kategori</label>
                                    <input type="text" class="form-control" id="kategori" name="kategori" value="<?php echo htmlspecialchars($kategori); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-12">
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?php echo htmlspecialchars($aciklama); ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-4">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">Kaydet</button>
                                <a href="index.php" class="btn btn-secondary">İptal</a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Bootstrap form validation
(function () {
    'use strict'
    
    // Fetch all the forms we want to apply custom Bootstrap validation styles to
    var forms = document.querySelectorAll('.needs-validation')
    
    // Loop over them and prevent submission
    Array.prototype.slice.call(forms)
        .forEach(function (form) {
            form.addEventListener('submit', function (event) {
                if (!form.checkValidity()) {
                    event.preventDefault()
                    event.stopPropagation()
                }
                
                form.classList.add('was-validated')
            }, false)
        })
})()
</script>

<?php include_once '../../includes/footer.php'; ?> 