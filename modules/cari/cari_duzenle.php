<?php
/**
 * ERP Sistem - Cari Düzenleme Sayfası
 * 
 * Bu dosya, seçilen carinin bilgilerini düzenlemeyi sağlar
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
    $stmt->execute([$cari_id]);
    $cari = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cari) {
        $error = "Cari bulunamadı! ID: " . $cari_id;
    }
    
    // Adres bilgilerini cariler tablosundan al - cari.KOD ile eşleşen kayıt
    $adres_bilgileri = [];
    try {
        $stmt = $db->prepare("SELECT * FROM cariler WHERE cari_kodu = ?");
        $stmt->execute([$cari['KOD']]);
        $adres_bilgileri = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug için adres bilgilerini göster
        //echo '<div class="alert alert-info"><pre>Adres Bilgileri: ' . print_r($adres_bilgileri, true) . '</pre></div>';
    } catch (PDOException $e) {
        $error = "Adres bilgileri alınırken hata oluştu: " . $e->getMessage();
    }

    // İlleri al (selectbox için) - cariler tablosundan
    try {
        $stmt = $db->query("SELECT DISTINCT il FROM cariler WHERE il IS NOT NULL AND il != '' ORDER BY il");
        $iller = $stmt->fetchAll(PDO::FETCH_COLUMN);
    } catch (PDOException $e) {
        $iller = []; // Hata durumunda boş dizi
    }

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
                
                // Adres bilgilerini cariler tablosuna kaydet
                try {
                    // Önce cari_kodu ile kayıt var mı kontrol et
                    $stmt = $db->prepare("SELECT id FROM cariler WHERE cari_kodu = ?");
                    $stmt->execute([$cari_kodu]);
                    $cariler_id = $stmt->fetchColumn();
                    
                    if ($cariler_id) {
                        // Kayıt varsa güncelle
                        $sql = "UPDATE cariler SET 
                            firma_unvani = ?,
                            yetkili_ad = ?,
                            yetkili_soyad = ?,
                            email = ?,
                            telefon = ?,
                            cep_telefonu = ?,
                            fax = ?,
                            adres = ?,
                            il = ?,
                            ilce = ?,
                            posta_kodu = ?,
                            web_sitesi = ?,
                            kredi_limiti = ?,
                            odeme_vade = ?,
                            durum = ?,
                            notlar = ?,
                            updated_at = CURRENT_TIMESTAMP
                        WHERE id = ?";
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            $firma_unvani,
                            $yetkili_adi,
                            $yetkili_soyadi,
                            $email,
                            $telefon,
                            $cep_telefon,
                            $faks,
                            $adres,
                            $il,
                            $ilce,
                            $posta_kodu,
                            $web_sitesi,
                            $risk_limiti,
                            $odeme_vade_suresi,
                            $durum,
                            $aciklama,
                            $cariler_id
                        ]);
                    } else {
                        // Kayıt yoksa ekle
                        $sql = "INSERT INTO cariler (
                            cari_kodu, firma_unvani, yetkili_ad, yetkili_soyad, 
                            email, telefon, cep_telefonu, fax, adres, il, ilce, posta_kodu,
                            web_sitesi, cari_tipi, kredi_limiti, odeme_vade, durum, notlar
                        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
                        
                        $stmt = $db->prepare($sql);
                        $stmt->execute([
                            $cari_kodu,
                            $firma_unvani,
                            $yetkili_adi,
                            $yetkili_soyadi,
                            $email,
                            $telefon,
                            $cep_telefon,
                            $faks,
                            $adres,
                            $il,
                            $ilce,
                            $posta_kodu,
                            $web_sitesi,
                            $cari_tipi,
                            $risk_limiti,
                            $odeme_vade_suresi,
                            $durum,
                            $aciklama
                        ]);
                    }
                } catch (PDOException $e) {
                    // Cariler tablosuna kayıt hatası - loga yazabilir veya kullanıcıya gösterebiliriz
                    error_log("Cariler tablosu güncelleme hatası: " . $e->getMessage());
                }

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
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Sayfa İçeriği -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cari Düzenle</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Cari Listesi</a>
                <a href="cari_detay.php?id=<?php echo $cari_id; ?>" class="btn btn-sm btn-outline-info">Cari Detay</a>
                <a href="cari_ekstre.php?id=<?php echo $cari_id; ?>" class="btn btn-sm btn-outline-primary">Ekstre</a>
            </div>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php endif; ?>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success"><?php echo $success; ?></div>
    <?php endif; ?>

    <?php if (isset($cari)): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Cari Bilgileri Düzenle</h6>
            </div>
            <div class="card-body">
                <form method="post" action="cari_duzenle.php?id=<?php echo $cari_id; ?>" class="needs-validation" novalidate>
                    <div class="row mb-3">
                        <div class="col-md-4">
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cari_tipi" id="tipi_musteri" value="musteri" <?php echo ($cari['TIP'] == 'musteri') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipi_musteri">Müşteri</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cari_tipi" id="tipi_tedarikci" value="tedarikci" <?php echo ($cari['TIP'] == 'tedarikci') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipi_tedarikci">Tedarikçi</label>
                            </div>
                            <div class="form-check form-check-inline">
                                <input class="form-check-input" type="radio" name="cari_tipi" id="tipi_her_ikisi" value="her_ikisi" <?php echo ($cari['TIP'] == 'her_ikisi') ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="tipi_her_ikisi">Her İkisi</label>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" name="durum" id="durum" value="1" <?php echo ($cari['DURUM'] == 1) ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="durum">
                                    Aktif
                                </label>
                            </div>
                        </div>
                    </div>

                    <h5 class="mt-4 border-bottom pb-2">Temel Bilgiler</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="cari_kodu">Cari Kodu <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="cari_kodu" name="cari_kodu" value="<?php echo htmlspecialchars($cari['KOD']); ?>" required>
                            <div class="invalid-feedback">Cari kodu zorunludur!</div>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="firma_unvani">Firma Ünvanı <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" id="firma_unvani" name="firma_unvani" value="<?php echo htmlspecialchars($cari['ADI']); ?>" required>
                            <div class="invalid-feedback">Firma ünvanı zorunludur!</div>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="yetkili_adi">Yetkili Adı</label>
                            <input type="text" class="form-control" id="yetkili_adi" name="yetkili_adi" value="<?php echo htmlspecialchars($cari['YETKILI_ADI'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="yetkili_soyadi">Yetkili Soyadı</label>
                            <input type="text" class="form-control" id="yetkili_soyadi" name="yetkili_soyadi" value="<?php echo htmlspecialchars($cari['YETKILI_SOYADI'] ?? ''); ?>">
                        </div>
                    </div>

                    <h5 class="mt-4 border-bottom pb-2">İletişim Bilgileri</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="telefon">Telefon</label>
                            <input type="text" class="form-control" id="telefon" name="telefon" value="<?php echo htmlspecialchars($cari['TELEFON'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="cep_telefon">Cep Telefonu</label>
                            <input type="text" class="form-control" id="cep_telefon" name="cep_telefon" value="<?php echo htmlspecialchars($cari['CEPNO'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="faks">Faks</label>
                            <input type="text" class="form-control" id="faks" name="faks" value="<?php echo htmlspecialchars($cari['FAX'] ?? ''); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="email">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($cari['EMAIL'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="web_sitesi">Web Sitesi</label>
                            <input type="url" class="form-control" id="web_sitesi" name="web_sitesi" placeholder="https://example.com" value="<?php echo htmlspecialchars($cari['WEB'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <h5 class="mt-4 border-bottom pb-2">Adres Bilgileri</h5>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="adres">Adres</label>
                            <textarea class="form-control" id="adres" name="adres" rows="3"><?php echo htmlspecialchars($cari['ADRES'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label for="il">İl</label>
                            <select class="form-select" id="il" name="il">
                                <option value="">Seçiniz</option>
                                <?php foreach ($iller as $sehir): ?>
                                    <option value="<?php echo htmlspecialchars($sehir); ?>" <?php echo ($adres_bilgileri['il'] == $sehir) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($sehir); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="ilce">İlçe</label>
                            <input type="text" class="form-control" id="ilce" name="ilce" value="<?php echo htmlspecialchars($adres_bilgileri['ilce'] ?? ''); ?>">
                        </div>
                        <div class="col-md-4 mb-3">
                            <label for="posta_kodu">Posta Kodu</label>
                            <input type="text" class="form-control" id="posta_kodu" name="posta_kodu" value="<?php echo htmlspecialchars($adres_bilgileri['posta_kodu'] ?? ''); ?>">
                        </div>
                    </div>
                    
                    <h5 class="mt-4 border-bottom pb-2">Finansal Bilgiler</h5>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="risk_limiti">Risk Limiti (TL)</label>
                            <input type="number" class="form-control" id="risk_limiti" name="risk_limiti" step="0.01" min="0" value="<?php echo htmlspecialchars($cari['LIMITTL'] ?? 0); ?>">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label for="odeme_vade_suresi">Ödeme Vade Süresi (Gün)</label>
                            <input type="number" class="form-control" id="odeme_vade_suresi" name="odeme_vade_suresi" min="0" value="<?php echo htmlspecialchars($cari['VADE'] ?? 0); ?>">
                        </div>
                    </div>
                    
                    <h5 class="mt-4 border-bottom pb-2">Ek Bilgiler</h5>
                    <div class="row">
                        <div class="col-md-12 mb-3">
                            <label for="aciklama">Açıklama/Notlar</label>
                            <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?php echo htmlspecialchars($cari['NOTLAR'] ?? ''); ?></textarea>
                        </div>
                    </div>
                    
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <a href="cari_detay.php?id=<?php echo $cari_id; ?>" class="btn btn-secondary me-md-2">İptal</a>
                        <button type="submit" class="btn btn-primary">Güncelle</button>
                    </div>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert alert-danger">Cari kaydı bulunamadı veya bir hata oluştu.</div>
    <?php endif; ?>
</div>

<script>
// Form doğrulama için JavaScript
(function() {
  'use strict';
  window.addEventListener('load', function() {
    var forms = document.getElementsByClassName('needs-validation');
    Array.prototype.filter.call(forms, function(form) {
      form.addEventListener('submit', function(event) {
        if (form.checkValidity() === false) {
          event.preventDefault();
          event.stopPropagation();
        }
        form.classList.add('was-validated');
      }, false);
    });
  }, false);
})();
</script>

<?php
include_once '../../includes/footer.php';
?> 