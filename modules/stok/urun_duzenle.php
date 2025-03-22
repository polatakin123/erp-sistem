<?php
/**
 * ERP Sistem - Ürün Düzenleme Sayfası
 * 
 * Bu dosya ürün düzenleme işlemlerini gerçekleştirir.
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
$pageTitle = "Ürün Düzenle";

// Hata ve başarı mesajları için session kontrolü
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

if (isset($_SESSION['success'])) {
    $success = $_SESSION['success'];
    unset($_SESSION['success']);
}

// Ürün ID'sini kontrol et
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Ürün bilgilerini al
try {
    $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Ürün bulunamadı.";
        header('Location: index.php');
        exit;
    }
    
    $urun = $stmt->fetch();
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    header('Location: index.php');
    exit;
}

// Form gönderildiyse
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al ve temizle
    $urun_adi = cleanProductName($_POST['urun_adi']);
    $kategori_id = isset($_POST['kategori_id']) ? (int)$_POST['kategori_id'] : null;
    $birim = clean($_POST['birim']);
    $barkod = isset($_POST['barkod']) ? clean($_POST['barkod']) : null;
    $marka = isset($_POST['marka']) ? clean($_POST['marka']) : null;
    $model = isset($_POST['model']) ? clean($_POST['model']) : null;
    $aciklama = isset($_POST['aciklama']) ? clean($_POST['aciklama']) : null;
    $alis_fiyati = isset($_POST['alis_fiyati']) ? (float)$_POST['alis_fiyati'] : 0;
    $satis_fiyati = isset($_POST['satis_fiyati']) ? (float)$_POST['satis_fiyati'] : 0;
    $kdv_orani = isset($_POST['kdv_orani']) ? (float)$_POST['kdv_orani'] : 0;
    $min_stok = isset($_POST['min_stok']) ? (float)$_POST['min_stok'] : 0;
    $durum = isset($_POST['durum']) ? clean($_POST['durum']) : 'passive';
    
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
    if (empty($urun_adi)) {
        $errors[] = "Ürün adı zorunludur.";
    }
    if (empty($birim)) {
        $errors[] = "Birim bilgisi zorunludur.";
    }
    
    // Hata yoksa ürünü güncelle
    if (empty($errors)) {
        try {
            // İşlemi başlat
            $db->beginTransaction();
            
            // Ürünü güncelle
            $sql = "UPDATE products SET 
                    name = ?, 
                    category_id = ?, 
                    unit = ?, 
                    barcode = ?, 
                    brand = ?, 
                    model = ?, 
                    description = ?, 
                    purchase_price = ?, 
                    sale_price = ?, 
                    tax_rate = ?, 
                    min_stock = ?, 
                    status = ?, 
                    oem_no = ?, 
                    cross_reference = ?, 
                    dimensions = ?, 
                    shelf_code = ?, 
                    vehicle_brand = ?, 
                    vehicle_model = ?, 
                    main_category = ?, 
                    sub_category = ?, 
                    updated_at = NOW(), 
                    updated_by = ? 
                    WHERE id = ?";
            
            $stmt = $db->prepare($sql);
            $stmt->execute([
                $urun_adi, 
                $kategori_id, 
                $birim, 
                $barkod, 
                $marka, 
                $model, 
                $aciklama, 
                $alis_fiyati, 
                $satis_fiyati, 
                $kdv_orani, 
                $min_stok, 
                $durum, 
                $oem_no, 
                $cross_reference, 
                $dimensions, 
                $shelf_code, 
                $vehicle_brand, 
                $vehicle_model, 
                $main_category, 
                $sub_category, 
                $_SESSION['user_id'], 
                $id
            ]);
            
            // OEM numaralarını işle
            if (!empty($oem_no)) {
                $oem_result = processOEMNumbers($db, $id, $oem_no, true);
                
                if (!$oem_result['success']) {
                    throw new Exception($oem_result['message']);
                }
            } else {
                // OEM numarası boşsa, mevcut numaraları temizle
                $stmt = $db->prepare("DELETE FROM oem_numbers WHERE product_id = ?");
                $stmt->execute([$id]);
            }
            
            // İşlemi tamamla
            $db->commit();
            
            // Başarılı mesajı ve yönlendirme
            $_SESSION['success_message'] = "Ürün başarıyla güncellendi.";
            header("Location: urun_detay.php?id=" . $id);
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
    <h1 class="h2">Ürün Düzenle</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
            </a>
            <a href="stok_hareketleri.php?urun_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-info">
                <i class="fas fa-exchange-alt"></i> Stok Hareketleri
            </a>
        </div>
    </div>
</div>

<?php
// Hata ve Başarı Mesajları
if (isset($error)) {
    echo '<div class="alert alert-danger" role="alert">' . $error . '</div>';
}

if (isset($success)) {
    echo '<div class="alert alert-success" role="alert">' . $success . '</div>';
}
?>

<!-- Ürün Bilgileri -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Ürün Bilgileri</h6>
        <div class="btn-group">
            <button type="button" class="btn btn-sm btn-success" data-bs-toggle="modal" data-bs-target="#stokHareketiModal">
                <i class="fas fa-plus"></i> Stok Hareketi Ekle
            </button>
        </div>
    </div>
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"] . '?id=' . $id); ?>" method="post" class="row g-3">
            
            <!-- Temel Bilgiler -->
            <div class="col-md-4">
                <label for="urun_adi" class="form-label">Ürün Adı *</label>
                <input type="text" class="form-control" id="urun_adi" name="urun_adi" value="<?php echo html_entity_decode($urun['name'], ENT_QUOTES, 'UTF-8'); ?>" required>
            </div>
            <div class="col-md-4">
                <label for="kategori_id" class="form-label">Kategori</label>
                <select class="form-select select2" id="kategori_id" name="kategori_id">
                    <option value="">Seçiniz</option>
                    <?php
                    // Kategorileri listele
                    try {
                        $stmt = $db->query("SELECT * FROM product_categories WHERE status = 'active' ORDER BY name");
                        while ($row = $stmt->fetch()) {
                            $selected = ($urun['category_id'] == $row['id']) ? 'selected' : '';
                            echo '<option value="' . $row['id'] . '" ' . $selected . '>' . htmlspecialchars($row['name']) . '</option>';
                        }
                    } catch (PDOException $e) {
                        // Hata durumunda
                    }
                    ?>
                </select>
            </div>
            <div class="col-md-4">
                <label for="birim" class="form-label">Birim *</label>
                <select class="form-select" id="birim" name="birim" required>
                    <option value="">Seçiniz</option>
                    <option value="Adet" <?php echo ($urun['unit'] == 'Adet') ? 'selected' : ''; ?>>Adet</option>
                    <option value="Kg" <?php echo ($urun['unit'] == 'Kg') ? 'selected' : ''; ?>>Kg</option>
                    <option value="Gr" <?php echo ($urun['unit'] == 'Gr') ? 'selected' : ''; ?>>Gr</option>
                    <option value="Lt" <?php echo ($urun['unit'] == 'Lt') ? 'selected' : ''; ?>>Lt</option>
                    <option value="Mt" <?php echo ($urun['unit'] == 'Mt') ? 'selected' : ''; ?>>Mt</option>
                    <option value="Paket" <?php echo ($urun['unit'] == 'Paket') ? 'selected' : ''; ?>>Paket</option>
                    <option value="Kutu" <?php echo ($urun['unit'] == 'Kutu') ? 'selected' : ''; ?>>Kutu</option>
                    <option value="Çift" <?php echo ($urun['unit'] == 'Çift') ? 'selected' : ''; ?>>Çift</option>
                </select>
            </div>
            
            <!-- Marka ve Model Bilgileri -->
            <div class="col-md-4">
                <label for="marka" class="form-label">Marka</label>
                <input type="text" class="form-control" id="marka" name="marka" value="<?php echo htmlspecialchars($urun['brand'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="model" class="form-label">Ürün No:</label>
                <input type="text" class="form-control" id="model" name="model" value="<?php echo htmlspecialchars($urun['model'] ?? ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="barkod" class="form-label">Barkod</label>
                <input type="text" class="form-control" id="barkod" name="barkod" value="<?php echo htmlspecialchars($urun['barcode'] ?? ''); ?>">
            </div>
            
            <!-- Fiyat Bilgileri -->
            <div class="col-md-4">
                <label for="alis_fiyati" class="form-label">Alış Fiyatı (₺)</label>
                <input type="text" class="form-control" id="alis_fiyati" name="alis_fiyati" value="<?php echo number_format($urun['purchase_price'], 2, '.', ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="satis_fiyati" class="form-label">Satış Fiyatı (₺)</label>
                <input type="text" class="form-control" id="satis_fiyati" name="satis_fiyati" value="<?php echo number_format($urun['sale_price'], 2, '.', ''); ?>">
            </div>
            <div class="col-md-4">
                <label for="kdv_orani" class="form-label">KDV Oranı (%)</label>
                <select class="form-select" id="kdv_orani" name="kdv_orani">
                    <option value="0" <?php echo ($urun['tax_rate'] == 0) ? 'selected' : ''; ?>>0</option>
                    <option value="1" <?php echo ($urun['tax_rate'] == 1) ? 'selected' : ''; ?>>1</option>
                    <option value="8" <?php echo ($urun['tax_rate'] == 8) ? 'selected' : ''; ?>>8</option>
                    <option value="10" <?php echo ($urun['tax_rate'] == 10) ? 'selected' : ''; ?>>10</option>
                    <option value="18" <?php echo ($urun['tax_rate'] == 18) ? 'selected' : ''; ?>>18</option>
                    <option value="20" <?php echo ($urun['tax_rate'] == 20) ? 'selected' : ''; ?>>20</option>
                </select>
            </div>
            
            <!-- Stok Bilgileri -->
            <div class="col-md-4">
                <label for="current_stock" class="form-label">Mevcut Stok</label>
                <input type="text" class="form-control" id="current_stock" value="<?php echo number_format($urun['current_stock'], 2, '.', ''); ?>" readonly>
                <small class="form-text text-muted">Stok miktarını değiştirmek için stok hareketi ekleyin.</small>
            </div>
            <div class="col-md-4">
                <label for="min_stok" class="form-label">Minimum Stok</label>
                <input type="text" class="form-control" id="min_stok" name="min_stok" value="<?php echo number_format($urun['min_stock'], 2, '.', ''); ?>">
                <small class="form-text text-muted">Uyarı verilecek minimum stok seviyesi</small>
            </div>
            <div class="col-md-4">
                <label for="durum" class="form-label">Durum</label>
                <select class="form-select" id="durum" name="durum">
                    <option value="active" <?php echo ($urun['status'] == 'active') ? 'selected' : ''; ?>>Aktif</option>
                    <option value="passive" <?php echo ($urun['status'] == 'passive') ? 'selected' : ''; ?>>Pasif</option>
                </select>
                <small class="form-text text-muted">Pasif ürünler listelenmez ve satışta kullanılamaz</small>
            </div>
            
            <!-- Açıklama -->
            <div class="col-12">
                <label for="aciklama" class="form-label">Açıklama</label>
                <textarea class="form-control" id="aciklama" name="aciklama" rows="3"><?php echo htmlspecialchars($urun['description']); ?></textarea>
            </div>
            
            <!-- Araç ve Parça Bilgileri -->
            <div class="col-12 mt-4 mb-2">
                <h5 class="border-bottom pb-2">Araç ve Parça Bilgileri</h5>
            </div>
            
            <div class="col-md-3">
                <label for="oem_no" class="form-label">OEM No</label>
                <textarea class="form-control" id="oem_no" name="oem_no" rows="3"><?php echo htmlspecialchars($urun['oem_no'] ?? ''); ?></textarea>
                <small class="form-text text-muted">Her satıra bir OEM numarası girin. Birden fazla numara girerseniz muadil ürün olarak eşleştirilecektir.</small>
            </div>
            <div class="col-md-3">
                <label for="cross_reference" class="form-label">Çapraz Referans</label>
                <textarea class="form-control" id="cross_reference" name="cross_reference" rows="2"><?php echo htmlspecialchars($urun['cross_reference'] ?? ''); ?></textarea>
            </div>
            <div class="col-md-3">
                <label for="dimensions" class="form-label">Ürün Ölçüleri</label>
                <input type="text" class="form-control" id="dimensions" name="dimensions" value="<?php echo htmlspecialchars($urun['dimensions'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="shelf_code" class="form-label">Raf Kodu</label>
                <input type="text" class="form-control" id="shelf_code" name="shelf_code" value="<?php echo htmlspecialchars($urun['shelf_code'] ?? ''); ?>">
            </div>
            
            <!-- Araç Bilgileri -->
            <div class="col-md-3">
                <label for="vehicle_brand" class="form-label">Araç Markası</label>
                <input type="text" class="form-control" id="vehicle_brand" name="vehicle_brand" value="<?php echo htmlspecialchars($urun['vehicle_brand'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="vehicle_model" class="form-label">Araç Modeli</label>
                <input type="text" class="form-control" id="vehicle_model" name="vehicle_model" value="<?php echo htmlspecialchars($urun['vehicle_model'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="main_category" class="form-label">Ana Kategori</label>
                <input type="text" class="form-control" id="main_category" name="main_category" value="<?php echo htmlspecialchars($urun['main_category'] ?? ''); ?>">
            </div>
            <div class="col-md-3">
                <label for="sub_category" class="form-label">Alt Kategori</label>
                <input type="text" class="form-control" id="sub_category" name="sub_category" value="<?php echo htmlspecialchars($urun['sub_category'] ?? ''); ?>">
            </div>
            
            <!-- Butonlar -->
            <div class="col-12 mt-4">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-save"></i> Güncelle
                </button>
                <a href="index.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Son Stok Hareketleri -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Son Stok Hareketleri</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Hareket Tipi</th>
                        <th>Miktar</th>
                        <th>Birim Fiyat</th>
                        <th>Toplam Tutar</th>
                        <th>Referans</th>
                        <th>Kullanıcı</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Son 5 stok hareketini getir
                    try {
                        $stmt = $db->prepare("
                            SELECT sm.*, u.name as user_name, u.surname as user_surname 
                            FROM stock_movements sm
                            LEFT JOIN users u ON sm.user_id = u.id
                            WHERE sm.product_id = :product_id
                            ORDER BY sm.created_at DESC
                            LIMIT 5
                        ");
                        $stmt->bindParam(':product_id', $id);
                        $stmt->execute();
                        
                        $hareketler = $stmt->fetchAll();
                        
                        if (count($hareketler) > 0) {
                            foreach ($hareketler as $hareket) {
                                $toplam_tutar = $hareket['quantity'] * $hareket['unit_price'];
                                $hareket_tipi_text = $hareket['movement_type'] == 'giris' ? 
                                    '<span class="badge bg-success">Giriş</span>' : 
                                    '<span class="badge bg-danger">Çıkış</span>';
                                
                                echo '<tr>';
                                echo '<td>' . date('d.m.Y H:i', strtotime($hareket['created_at'])) . '</td>';
                                echo '<td>' . $hareket_tipi_text . '</td>';
                                echo '<td>' . number_format($hareket['quantity'], 2, ',', '.') . ' ' . $urun['unit'] . '</td>';
                                echo '<td>₺' . number_format($hareket['unit_price'], 2, ',', '.') . '</td>';
                                echo '<td>₺' . number_format($toplam_tutar, 2, ',', '.') . '</td>';
                                
                                // Referans
                                $referans_tipler = [
                                    'alis_fatura' => 'Alış Faturası',
                                    'satis_fatura' => 'Satış Faturası',
                                    'stok_sayim' => 'Stok Sayımı',
                                    'stok_transfer' => 'Stok Transferi',
                                    'diger' => 'Diğer'
                                ];
                                $referans_tip_text = isset($referans_tipler[$hareket['reference_type']]) ? 
                                    $referans_tipler[$hareket['reference_type']] : $hareket['reference_type'];
                                
                                echo '<td>' . $referans_tip_text . ' ' . $hareket['reference_no'] . '</td>';
                                echo '<td>' . htmlspecialchars($hareket['user_name'] . ' ' . $hareket['user_surname']) . '</td>';
                                echo '<td>' . htmlspecialchars($hareket['description']) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">Henüz stok hareketi bulunmuyor.</td></tr>';
                        }
                    } catch (PDOException $e) {
                        echo '<tr><td colspan="8" class="text-center text-danger">Veritabanı hatası: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
            <div class="text-center mt-3">
                <a href="stok_hareketleri.php?urun_id=<?php echo $id; ?>" class="btn btn-info">
                    <i class="fas fa-list"></i> Tüm Stok Hareketlerini Görüntüle
                </a>
            </div>
        </div>
    </div>
</div>

<!-- Stok Hareketi Ekleme Modalı -->
<div class="modal fade" id="stokHareketiModal" tabindex="-1" aria-labelledby="stokHareketiModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="stokHareketiModalLabel">Stok Hareketi Ekle</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form action="stok_hareketi_ekle.php" method="post">
                <div class="modal-body">
                    <input type="hidden" name="urun_id" value="<?php echo $id; ?>">
                    
                    <div class="mb-3">
                        <label for="hareket_tipi" class="form-label">Hareket Tipi</label>
                        <select class="form-select" id="hareket_tipi" name="hareket_tipi" required>
                            <option value="giris">Giriş</option>
                            <option value="cikis">Çıkış</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="miktar" class="form-label">Miktar</label>
                        <input type="number" class="form-control" id="miktar" name="miktar" min="0.01" step="0.01" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="birim_fiyat" class="form-label">Birim Fiyat (₺)</label>
                        <input type="text" class="form-control" id="birim_fiyat" name="birim_fiyat" value="<?php echo number_format($urun['purchase_price'], 2, '.', ''); ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="referans_tip" class="form-label">Referans Tipi</label>
                        <select class="form-select" id="referans_tip" name="referans_tip">
                            <option value="alis_fatura">Alış Faturası</option>
                            <option value="satis_fatura">Satış Faturası</option>
                            <option value="stok_sayim">Stok Sayımı</option>
                            <option value="stok_transfer">Stok Transferi</option>
                            <option value="diger">Diğer</option>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="referans_no" class="form-label">Referans No</label>
                        <input type="text" class="form-control" id="referans_no" name="referans_no">
                    </div>
                    
                    <div class="mb-3">
                        <label for="aciklama" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="aciklama" name="aciklama" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Sayısal girişleri formatlama
        const numberInputs = document.querySelectorAll('#alis_fiyati, #satis_fiyati, #min_stok, #birim_fiyat');
        
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
        document.getElementById('urun_adi').addEventListener('input', function() {
            this.value = this.value.toUpperCase();
        });
        
        // Hareket tipi değiştiğinde birim fiyat değerini değiştir
        document.getElementById('hareket_tipi').addEventListener('change', function() {
            const birimFiyatInput = document.getElementById('birim_fiyat');
            const alisFiyati = <?php echo $urun['purchase_price']; ?>;
            const satisFiyati = <?php echo $urun['sale_price']; ?>;
            
            if (this.value === 'giris') {
                birimFiyatInput.value = alisFiyati.toFixed(2);
            } else {
                birimFiyatInput.value = satisFiyati.toFixed(2);
            }
        });
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 