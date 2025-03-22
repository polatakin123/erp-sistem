<?php
/**
 * ERP Sistem - Alış Faturası Ekleme Sayfası
 * 
 * Bu dosya alış faturası ekleme işlemlerini içerir.
 */

// Oturum başlat
session_start();

// Gerekli dosyaları dahil et
require_once '../../config/database.php';
require_once '../../includes/functions.php';

// Oturum kontrolü
checkLogin();

// Yetki kontrolü
if (!checkPermission('fatura_ekle')) {
    // Yetki yoksa ana sayfaya yönlendir
    redirect('../../index.php');
}

// Sayfa başlığı
$pageTitle = "Alış Faturası Ekle | ERP Sistem";

// Hata ve başarı mesajları için değişkenler
$error = "";
$success = "";

// Form gönderildi mi kontrol et
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al
    $tedarikci_id = isset($_POST['tedarikci_id']) ? (int)$_POST['tedarikci_id'] : 0;
    $fatura_tarihi = isset($_POST['fatura_tarihi']) ? $_POST['fatura_tarihi'] : date('Y-m-d');
    $vade_tarihi = isset($_POST['vade_tarihi']) ? $_POST['vade_tarihi'] : date('Y-m-d');
    $aciklama = isset($_POST['aciklama']) ? clean($_POST['aciklama']) : '';
    
    // Ürün detaylarını al
    $urunler = isset($_POST['urun']) ? $_POST['urun'] : [];
    $miktarlar = isset($_POST['miktar']) ? $_POST['miktar'] : [];
    $birim_fiyatlar = isset($_POST['birim_fiyat']) ? $_POST['birim_fiyat'] : [];
    $kdv_oranlari = isset($_POST['kdv_orani']) ? $_POST['kdv_orani'] : [];
    $iskonto_oranlari = isset($_POST['iskonto_orani']) ? $_POST['iskonto_orani'] : [];
    
    // Zorunlu alanları kontrol et
    if ($tedarikci_id <= 0) {
        $error = "Lütfen tedarikçi seçin.";
    } elseif (empty($urunler)) {
        $error = "En az bir ürün eklemelisiniz.";
    } else {
        try {
            // Fatura numarası oluştur
            $yil = date('Y');
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM faturalar WHERE YEAR(fatura_tarihi) = :yil AND fatura_tipi = 'alis'");
            $stmt->bindParam(':yil', $yil);
            $stmt->execute();
            $fatura_no = 'AF-' . $yil . '-' . str_pad($stmt->fetchColumn() + 1, 6, '0', STR_PAD_LEFT);
            
            // Fatura tutarlarını hesapla
            $ara_toplam = 0;
            $toplam_kdv = 0;
            $toplam_iskonto = 0;
            $genel_toplam = 0;
            
            // Veritabanı işlemlerini başlat
            $pdo->beginTransaction();
            
            // Faturayı ekle
            $sql = "INSERT INTO faturalar (
                        fatura_no, 
                        fatura_tipi,
                        tedarikci_id,
                        fatura_tarihi,
                        vade_tarihi,
                        ara_toplam,
                        toplam_kdv,
                        toplam_iskonto,
                        toplam_tutar,
                        aciklama,
                        odeme_durumu,
                        durum,
                        olusturan_id,
                        olusturma_tarihi
                    ) VALUES (
                        :fatura_no,
                        'alis',
                        :tedarikci_id,
                        :fatura_tarihi,
                        :vade_tarihi,
                        :ara_toplam,
                        :toplam_kdv,
                        :toplam_iskonto,
                        :toplam_tutar,
                        :aciklama,
                        'odenmedi',
                        1,
                        :olusturan_id,
                        NOW()
                    )";
            
            $stmt = $pdo->prepare($sql);
            $stmt->bindParam(':fatura_no', $fatura_no);
            $stmt->bindParam(':tedarikci_id', $tedarikci_id);
            $stmt->bindParam(':fatura_tarihi', $fatura_tarihi);
            $stmt->bindParam(':vade_tarihi', $vade_tarihi);
            $stmt->bindParam(':aciklama', $aciklama);
            $stmt->bindParam(':olusturan_id', $_SESSION['user_id']);
            
            // Fatura detaylarını ekle
            for ($i = 0; $i < count($urunler); $i++) {
                $urun_id = (int)$urunler[$i];
                $miktar = (float)str_replace(',', '.', $miktarlar[$i]);
                $birim_fiyat = (float)str_replace(',', '.', $birim_fiyatlar[$i]);
                $kdv_orani = (float)$kdv_oranlari[$i];
                $iskonto_orani = (float)$iskonto_oranlari[$i];
                
                // Satır tutarlarını hesapla
                $satir_tutari = $miktar * $birim_fiyat;
                $iskonto_tutari = $satir_tutari * ($iskonto_orani / 100);
                $kdv_tutari = ($satir_tutari - $iskonto_tutari) * ($kdv_orani / 100);
                
                // Genel toplamlara ekle
                $ara_toplam += $satir_tutari;
                $toplam_iskonto += $iskonto_tutari;
                $toplam_kdv += $kdv_tutari;
                
                // Stok miktarını güncelle
                $stmt = $pdo->prepare("UPDATE urunler SET stok_miktari = stok_miktari + :miktar WHERE id = :id");
                $stmt->bindParam(':miktar', $miktar);
                $stmt->bindParam(':id', $urun_id);
                $stmt->execute();
                
                // Stok hareketi ekle
                $sql = "INSERT INTO stok_hareketleri (
                            urun_id,
                            hareket_tipi,
                            miktar,
                            birim_fiyat,
                            toplam_tutar,
                            referans_tip,
                            referans_no,
                            aciklama,
                            kullanici_id
                        ) VALUES (
                            :urun_id,
                            'giris',
                            :miktar,
                            :birim_fiyat,
                            :toplam_tutar,
                            'alis_fatura',
                            :fatura_no,
                            'Alış faturası',
                            :kullanici_id
                        )";
                
                $stmt = $pdo->prepare($sql);
                $stmt->bindParam(':urun_id', $urun_id);
                $stmt->bindParam(':miktar', $miktar);
                $stmt->bindParam(':birim_fiyat', $birim_fiyat);
                $stmt->bindParam(':toplam_tutar', $satir_tutari);
                $stmt->bindParam(':fatura_no', $fatura_no);
                $stmt->bindParam(':kullanici_id', $_SESSION['user_id']);
                $stmt->execute();
            }
            
            // Genel toplamı hesapla
            $genel_toplam = $ara_toplam - $toplam_iskonto + $toplam_kdv;
            
            // Fatura tutarlarını güncelle
            $stmt->bindParam(':ara_toplam', $ara_toplam);
            $stmt->bindParam(':toplam_kdv', $toplam_kdv);
            $stmt->bindParam(':toplam_iskonto', $toplam_iskonto);
            $stmt->bindParam(':toplam_tutar', $genel_toplam);
            
            if ($stmt->execute()) {
                // İşlemleri tamamla
                $pdo->commit();
                
                $success = "Alış faturası başarıyla oluşturuldu. Fatura No: " . $fatura_no;
                
                // Formu temizle
                $tedarikci_id = 0;
                $fatura_tarihi = date('Y-m-d');
                $vade_tarihi = date('Y-m-d');
                $aciklama = '';
            }
            
        } catch (Exception $e) {
            // Hata durumunda işlemleri geri al
            $pdo->rollBack();
            $error = "İşlem hatası: " . $e->getMessage();
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
                <h1 class="h2">Alış Faturası Ekle</h1>
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
            
            <!-- Fatura Formu -->
            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post" class="needs-validation" novalidate>
                <div class="row">
                    <!-- Fatura Bilgileri -->
                    <div class="col-md-4">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3">
                                <h6 class="m-0 font-weight-bold text-primary">Fatura Bilgileri</h6>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label for="tedarikci_id" class="form-label">Tedarikçi <span class="text-danger">*</span></label>
                                    <select class="form-select select2" id="tedarikci_id" name="tedarikci_id" required>
                                        <option value="">Tedarikçi Seçin</option>
                                        <?php
                                        // Tedarikçileri listele
                                        try {
                                            $stmt = $pdo->query("SELECT * FROM tedarikciler WHERE durum = 1 ORDER BY ad");
                                            while ($row = $stmt->fetch()) {
                                                $selected = ($tedarikci_id == $row['id']) ? 'selected' : '';
                                                echo '<option value="' . $row['id'] . '" ' . $selected . '>' . $row['ad'] . '</option>';
                                            }
                                        } catch (PDOException $e) {
                                            // Hata durumunda
                                        }
                                        ?>
                                    </select>
                                    <div class="invalid-feedback">
                                        Lütfen tedarikçi seçin.
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="fatura_tarihi" class="form-label">Fatura Tarihi</label>
                                    <input type="date" class="form-control" id="fatura_tarihi" name="fatura_tarihi" value="<?php echo $fatura_tarihi ?? date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="vade_tarihi" class="form-label">Vade Tarihi</label>
                                    <input type="date" class="form-control" id="vade_tarihi" name="vade_tarihi" value="<?php echo $vade_tarihi ?? date('Y-m-d'); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="aciklama" class="form-label">Açıklama</label>
                                    <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?php echo $aciklama ?? ''; ?></textarea>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Ürün Listesi -->
                    <div class="col-md-8">
                        <div class="card shadow mb-4">
                            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                                <h6 class="m-0 font-weight-bold text-primary">Ürün Listesi</h6>
                                <button type="button" class="btn btn-sm btn-primary" id="urunEkle">
                                    <i class="fas fa-plus"></i> Ürün Ekle
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-bordered" id="urunTablosu">
                                        <thead>
                                            <tr>
                                                <th>Ürün</th>
                                                <th>Miktar</th>
                                                <th>Birim Fiyat</th>
                                                <th>KDV (%)</th>
                                                <th>İskonto (%)</th>
                                                <th>Tutar</th>
                                                <th>İşlem</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <!-- JavaScript ile doldurulacak -->
                                        </tbody>
                                        <tfoot>
                                            <tr>
                                                <td colspan="5" class="text-end">Ara Toplam:</td>
                                                <td colspan="2" class="text-end">
                                                    <span id="araToplam">0,00 ₺</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="5" class="text-end">Toplam İskonto:</td>
                                                <td colspan="2" class="text-end">
                                                    <span id="toplamIskonto">0,00 ₺</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="5" class="text-end">Toplam KDV:</td>
                                                <td colspan="2" class="text-end">
                                                    <span id="toplamKdv">0,00 ₺</span>
                                                </td>
                                            </tr>
                                            <tr>
                                                <td colspan="5" class="text-end fw-bold">Genel Toplam:</td>
                                                <td colspan="2" class="text-end fw-bold">
                                                    <span id="genelToplam">0,00 ₺</span>
                                                </td>
                                            </tr>
                                        </tfoot>
                                    </table>
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
                    </div>
                </div>
            </form>
        </main>
    </div>
</div>

<!-- Ürün Seçme Modalı -->
<div class="modal fade" id="urunModal" tabindex="-1" aria-labelledby="urunModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="urunModalLabel">Ürün Seç</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="table-responsive">
                    <table class="table table-bordered table-hover" id="urunSecimTablosu">
                        <thead>
                            <tr>
                                <th>Stok Kodu</th>
                                <th>Ürün Adı</th>
                                <th>Kategori</th>
                                <th>Stok Miktarı</th>
                                <th>Birim</th>
                                <th>Alış Fiyatı</th>
                                <th>İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            // Ürünleri listele
                            try {
                                $stmt = $pdo->query("SELECT u.*, k.ad AS kategori_adi, b.ad AS birim_adi 
                                                    FROM urunler u 
                                                    LEFT JOIN kategoriler k ON u.kategori_id = k.id 
                                                    LEFT JOIN birimler b ON u.birim_id = b.id 
                                                    WHERE u.durum = 1 
                                                    ORDER BY u.stok_kodu");
                                while ($row = $stmt->fetch()) {
                                    echo '<tr>';
                                    echo '<td>' . $row['stok_kodu'] . '</td>';
                                    echo '<td>' . $row['ad'] . '</td>';
                                    echo '<td>' . ($row['kategori_adi'] ?? '-') . '</td>';
                                    echo '<td>' . $row['stok_miktari'] . '</td>';
                                    echo '<td>' . ($row['birim_adi'] ?? '-') . '</td>';
                                    echo '<td>' . number_format($row['alis_fiyati'], 2, ',', '.') . ' ₺</td>';
                                    echo '<td>';
                                    echo '<button type="button" class="btn btn-sm btn-primary urun-sec" ';
                                    echo 'data-id="' . $row['id'] . '" ';
                                    echo 'data-ad="' . $row['ad'] . '" ';
                                    echo 'data-fiyat="' . $row['alis_fiyati'] . '" ';
                                    echo 'data-kdv="' . $row['kdv_orani'] . '">';
                                    echo '<i class="fas fa-check"></i> Seç';
                                    echo '</button>';
                                    echo '</td>';
                                    echo '</tr>';
                                }
                            } catch (PDOException $e) {
                                // Hata durumunda
                            }
                            ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
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

// Ürün işlemleri
document.addEventListener('DOMContentLoaded', function() {
    // DataTable'ları başlat
    var urunSecimTablosu = $('#urunSecimTablosu').DataTable({
        language: {
            url: '../../assets/js/Turkish.json'
        },
        pageLength: 10
    });
    
    // Select2'yi başlat
    $('.select2').select2({
        theme: 'bootstrap-5'
    });
    
    // Ürün ekleme butonu
    document.getElementById('urunEkle').addEventListener('click', function() {
        var modal = new bootstrap.Modal(document.getElementById('urunModal'));
        modal.show();
    });
    
    // Ürün seçme butonları
    document.querySelectorAll('.urun-sec').forEach(function(button) {
        button.addEventListener('click', function() {
            var urunId = this.getAttribute('data-id');
            var urunAd = this.getAttribute('data-ad');
            var birimFiyat = parseFloat(this.getAttribute('data-fiyat'));
            var kdvOrani = parseInt(this.getAttribute('data-kdv'));
            
            // Ürünü tabloya ekle
            var tbody = document.querySelector('#urunTablosu tbody');
            var tr = document.createElement('tr');
            tr.innerHTML = `
                <td>
                    ${urunAd}
                    <input type="hidden" name="urun[]" value="${urunId}">
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm miktar" name="miktar[]" value="1" min="1" required>
                </td>
                <td>
                    <input type="text" class="form-control form-control-sm birim-fiyat" name="birim_fiyat[]" value="${birimFiyat.toFixed(2)}" required>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm kdv-orani" name="kdv_orani[]" value="${kdvOrani}" min="0" max="100" required>
                </td>
                <td>
                    <input type="number" class="form-control form-control-sm iskonto-orani" name="iskonto_orani[]" value="0" min="0" max="100" required>
                </td>
                <td class="text-end">
                    <span class="satir-tutari">0,00 ₺</span>
                </td>
                <td>
                    <button type="button" class="btn btn-sm btn-danger satir-sil">
                        <i class="fas fa-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(tr);
            
            // Satır silme butonu
            tr.querySelector('.satir-sil').addEventListener('click', function() {
                tr.remove();
                hesaplaToplamlar();
            });
            
            // Değişiklikleri dinle
            ['miktar', 'birim-fiyat', 'kdv-orani', 'iskonto-orani'].forEach(function(className) {
                tr.querySelector('.' + className).addEventListener('change', hesaplaSatirTutari.bind(null, tr));
            });
            
            // İlk hesaplamayı yap
            hesaplaSatirTutari(tr);
            
            // Modalı kapat
            bootstrap.Modal.getInstance(document.getElementById('urunModal')).hide();
        });
    });
    
    // Satır tutarı hesaplama
    function hesaplaSatirTutari(tr) {
        var miktar = parseFloat(tr.querySelector('.miktar').value) || 0;
        var birimFiyat = parseFloat(tr.querySelector('.birim-fiyat').value.replace(',', '.')) || 0;
        var kdvOrani = parseFloat(tr.querySelector('.kdv-orani').value) || 0;
        var iskontoOrani = parseFloat(tr.querySelector('.iskonto-orani').value) || 0;
        
        var satir_tutari = miktar * birimFiyat;
        var iskonto_tutari = satir_tutari * (iskontoOrani / 100);
        var kdv_tutari = (satir_tutari - iskonto_tutari) * (kdvOrani / 100);
        var toplam_tutar = satir_tutari - iskonto_tutari + kdv_tutari;
        
        tr.querySelector('.satir-tutari').textContent = toplam_tutar.toFixed(2).replace('.', ',') + ' ₺';
        
        hesaplaToplamlar();
    }
    
    // Toplamları hesaplama
    function hesaplaToplamlar() {
        var araToplam = 0;
        var toplamIskonto = 0;
        var toplamKdv = 0;
        
        document.querySelectorAll('#urunTablosu tbody tr').forEach(function(tr) {
            var miktar = parseFloat(tr.querySelector('.miktar').value) || 0;
            var birimFiyat = parseFloat(tr.querySelector('.birim-fiyat').value.replace(',', '.')) || 0;
            var kdvOrani = parseFloat(tr.querySelector('.kdv-orani').value) || 0;
            var iskontoOrani = parseFloat(tr.querySelector('.iskonto-orani').value) || 0;
            
            var satir_tutari = miktar * birimFiyat;
            var iskonto_tutari = satir_tutari * (iskontoOrani / 100);
            var kdv_tutari = (satir_tutari - iskonto_tutari) * (kdvOrani / 100);
            
            araToplam += satir_tutari;
            toplamIskonto += iskonto_tutari;
            toplamKdv += kdv_tutari;
        });
        
        var genelToplam = araToplam - toplamIskonto + toplamKdv;
        
        document.getElementById('araToplam').textContent = araToplam.toFixed(2).replace('.', ',') + ' ₺';
        document.getElementById('toplamIskonto').textContent = toplamIskonto.toFixed(2).replace('.', ',') + ' ₺';
        document.getElementById('toplamKdv').textContent = toplamKdv.toFixed(2).replace('.', ',') + ' ₺';
        document.getElementById('genelToplam').textContent = genelToplam.toFixed(2).replace('.', ',') + ' ₺';
    }
});
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 