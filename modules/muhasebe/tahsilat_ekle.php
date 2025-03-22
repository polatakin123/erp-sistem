<?php
/**
 * ERP Sistem - Tahsilat Ekleme Sayfası
 * 
 * Bu dosya müşterilerden yapılan tahsilatları kaydetmek için kullanılır.
 */

// Oturum başlat
session_start();

// Gerekli dosyaları dahil et
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Oturum kontrolü
checkLogin();

// Yetki kontrolü
if (!checkPermission('muhasebe_ekle')) {
    // Yetki yoksa ana sayfaya yönlendir
    redirect('../../index.php');
}

// Sayfa başlığı
$pageTitle = "Tahsilat Ekle | ERP Sistem";

// Hata ve başarı mesajları için değişkenler
$error = "";
$success = "";

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al
    $musteri_id = isset($_POST['musteri_id']) ? (int)$_POST['musteri_id'] : 0;
    $tutar = isset($_POST['tutar']) ? (float)str_replace(',', '.', $_POST['tutar']) : 0;
    $tarih = isset($_POST['tarih']) ? $_POST['tarih'] : date('Y-m-d');
    $odeme_tipi = isset($_POST['odeme_tipi']) ? clean($_POST['odeme_tipi']) : '';
    $aciklama = isset($_POST['aciklama']) ? clean($_POST['aciklama']) : '';
    $referans_no = isset($_POST['referans_no']) ? clean($_POST['referans_no']) : '';
    
    // Zorunlu alanları kontrol et
    if ($musteri_id <= 0) {
        $error = "Lütfen müşteri seçin.";
    } elseif ($tutar <= 0) {
        $error = "Lütfen geçerli bir tutar girin.";
    } elseif (empty($odeme_tipi)) {
        $error = "Lütfen ödeme tipi seçin.";
    } else {
        try {
            // Tahsilat numarası oluştur
            $yil = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM tahsilatlar WHERE YEAR(tarih) = :yil");
            $stmt->bindParam(':yil', $yil);
            $stmt->execute();
            $tahsilat_no = 'THS-' . $yil . '-' . str_pad($stmt->fetchColumn() + 1, 6, '0', STR_PAD_LEFT);
            
            // Tahsilatı ekle
            $sql = "INSERT INTO tahsilatlar (
                        tahsilat_no,
                        musteri_id,
                        tutar,
                        tarih,
                        odeme_tipi,
                        referans_no,
                        aciklama,
                        durum,
                        olusturan_id,
                        olusturma_tarihi
                    ) VALUES (
                        :tahsilat_no,
                        :musteri_id,
                        :tutar,
                        :tarih,
                        :odeme_tipi,
                        :referans_no,
                        :aciklama,
                        1,
                        :olusturan_id,
                        NOW()
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':tahsilat_no', $tahsilat_no);
            $stmt->bindParam(':musteri_id', $musteri_id);
            $stmt->bindParam(':tutar', $tutar);
            $stmt->bindParam(':tarih', $tarih);
            $stmt->bindParam(':odeme_tipi', $odeme_tipi);
            $stmt->bindParam(':referans_no', $referans_no);
            $stmt->bindParam(':aciklama', $aciklama);
            $stmt->bindParam(':olusturan_id', $_SESSION['user_id']);
            
            if ($stmt->execute()) {
                // Müşteri bakiyesini güncelle
                $stmt = $pdo->prepare("UPDATE musteriler SET bakiye = bakiye - :tutar WHERE id = :musteri_id");
                $stmt->bindParam(':tutar', $tutar);
                $stmt->bindParam(':musteri_id', $musteri_id);
                $stmt->execute();
                
                $success = "Tahsilat başarıyla kaydedildi. Tahsilat No: " . $tahsilat_no;
                
                // Formu temizle
                $musteri_id = 0;
                $tutar = '';
                $tarih = date('Y-m-d');
                $odeme_tipi = '';
                $aciklama = '';
                $referans_no = '';
            } else {
                $error = "Tahsilat kaydedilirken bir hata oluştu.";
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
                <h1 class="h2">Tahsilat Ekle</h1>
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
            
            <!-- Tahsilat Formu -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Tahsilat Bilgileri</h6>
                </div>
                <div class="card-body">
                    <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="musteri_id" class="form-label">Müşteri <span class="text-danger">*</span></label>
                                    <select class="form-select select2" id="musteri_id" name="musteri_id" required>
                                        <option value="">Müşteri Seçin</option>
                                        <?php
                                        // Müşterileri listele
                                        try {
                                            $stmt = $pdo->query("SELECT id, ad, bakiye FROM musteriler WHERE durum = 1 ORDER BY ad");
                                            while ($row = $stmt->fetch()) {
                                                $selected = ($musteri_id == $row['id']) ? 'selected' : '';
                                                echo '<option value="' . $row['id'] . '" data-bakiye="' . $row['bakiye'] . '" ' . $selected . '>' . $row['ad'] . '</option>';
                                            }
                                        } catch (PDOException $e) {
                                            // Hata durumunda
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Lütfen müşteri seçin.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="musteri_bakiye" class="form-label">Müşteri Bakiyesi</label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="musteri_bakiye" readonly>
                                        <span class="input-group-text">₺</span>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tutar" class="form-label">Tutar <span class="text-danger">*</span></label>
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="tutar" name="tutar" value="<?php echo $tutar ?? ''; ?>" required>
                                        <span class="input-group-text">₺</span>
                                    </div>
                                    <div class="invalid-feedback">
                                        Lütfen geçerli bir tutar girin.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="tarih" class="form-label">Tarih</label>
                                    <input type="date" class="form-control" id="tarih" name="tarih" value="<?php echo $tarih ?? date('Y-m-d'); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="odeme_tipi" class="form-label">Ödeme Tipi <span class="text-danger">*</span></label>
                                    <select class="form-select" id="odeme_tipi" name="odeme_tipi" required>
                                        <option value="">Ödeme Tipi Seçin</option>
                                        <option value="Nakit" <?php echo (isset($odeme_tipi) && $odeme_tipi == 'Nakit') ? 'selected' : ''; ?>>Nakit</option>
                                        <option value="Banka Havalesi" <?php echo (isset($odeme_tipi) && $odeme_tipi == 'Banka Havalesi') ? 'selected' : ''; ?>>Banka Havalesi</option>
                                        <option value="Kredi Kartı" <?php echo (isset($odeme_tipi) && $odeme_tipi == 'Kredi Kartı') ? 'selected' : ''; ?>>Kredi Kartı</option>
                                        <option value="Çek" <?php echo (isset($odeme_tipi) && $odeme_tipi == 'Çek') ? 'selected' : ''; ?>>Çek</option>
                                        <option value="Senet" <?php echo (isset($odeme_tipi) && $odeme_tipi == 'Senet') ? 'selected' : ''; ?>>Senet</option>
                                    </select>
                                    <div class="invalid-feedback">
                                        Lütfen ödeme tipi seçin.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="referans_no" class="form-label">Referans No</label>
                                    <input type="text" class="form-control" id="referans_no" name="referans_no" value="<?php echo $referans_no ?? ''; ?>">
                                    <div class="form-text">Havale, çek veya senet numarası girebilirsiniz.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?php echo $aciklama ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                        
                        <div class="row mt-3">
                            <div class="col-12">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Kaydet
                                </button>
                                <a href="index.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> İptal
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </main>
    </div>
</div>

<script>
// Form doğrulama
(function() {
    'use strict';
    
    var forms = document.querySelectorAll('.needs-validation');
    
    Array.prototype.slice.call(forms).forEach(function(form) {
        form.addEventListener('submit', function(event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            
            form.classList.add('was-validated');
        }, false);
    });
})();

// Müşteri bakiyesini göster
document.addEventListener('DOMContentLoaded', function() {
    // Select2'yi başlat
    $('.select2').select2({
        theme: 'bootstrap-5'
    });
    
    // Müşteri seçildiğinde bakiyeyi göster
    $('#musteri_id').on('change', function() {
        var selectedOption = $(this).find('option:selected');
        var bakiye = selectedOption.data('bakiye') || 0;
        $('#musteri_bakiye').val(formatCurrency(bakiye));
    });
    
    // Sayfa yüklendiğinde seçili müşterinin bakiyesini göster
    var selectedOption = $('#musteri_id').find('option:selected');
    var bakiye = selectedOption.data('bakiye') || 0;
    $('#musteri_bakiye').val(formatCurrency(bakiye));
    
    // Para formatı
    function formatCurrency(value) {
        return parseFloat(value).toFixed(2).replace('.', ',') + ' ₺';
    }
    
    // Tutar alanı için sayısal giriş kontrolü
    $('#tutar').on('input', function() {
        this.value = this.value.replace(/[^0-9.,]/g, '');
    });
});
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 