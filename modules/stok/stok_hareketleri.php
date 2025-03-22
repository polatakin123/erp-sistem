<?php
/**
 * ERP Sistem - Stok Hareketleri Sayfası
 * 
 * Bu dosya stok hareketlerini görüntüleme ve filtreleme işlevlerini içerir.
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
$pageTitle = "Stok Hareketleri";

// Filtreleme parametreleri
$urun_id = isset($_GET['urun_id']) ? (int)$_GET['urun_id'] : null;
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-d', strtotime('-30 days'));
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$hareket_tipi = isset($_GET['hareket_tipi']) ? $_GET['hareket_tipi'] : '';
$referans_tip = isset($_GET['referans_tip']) ? $_GET['referans_tip'] : '';

// Ürün bilgisini al (eğer ürün ID'si belirtilmişse)
$urun = null;
if ($urun_id) {
    try {
        $stmt = $db->prepare("SELECT * FROM products WHERE id = :id");
        $stmt->bindParam(':id', $urun_id);
        $stmt->execute();
        $urun = $stmt->fetch();
    } catch (PDOException $e) {
        // Hata durumunda
    }
}

// Stok hareketlerini al
try {
    $sql = "SELECT sm.*, p.name as urun_adi, p.code as stok_kodu, p.unit as birim, 
            u.name as kullanici_adi, u.surname as kullanici_soyad
            FROM stock_movements sm
            LEFT JOIN products p ON sm.product_id = p.id
            LEFT JOIN users u ON sm.user_id = u.id
            WHERE 1=1";
    
    $params = [];
    
    // Ürün ID'sine göre filtrele
    if ($urun_id) {
        $sql .= " AND sm.product_id = :urun_id";
        $params[':urun_id'] = $urun_id;
    }
    
    // Tarih aralığına göre filtrele
    if ($baslangic_tarihi) {
        $sql .= " AND DATE(sm.created_at) >= :baslangic_tarihi";
        $params[':baslangic_tarihi'] = $baslangic_tarihi;
    }
    
    if ($bitis_tarihi) {
        $sql .= " AND DATE(sm.created_at) <= :bitis_tarihi";
        $params[':bitis_tarihi'] = $bitis_tarihi;
    }
    
    // Hareket tipine göre filtrele
    if ($hareket_tipi) {
        $sql .= " AND sm.movement_type = :hareket_tipi";
        $params[':hareket_tipi'] = $hareket_tipi;
    }
    
    // Referans tipine göre filtrele
    if ($referans_tip) {
        $sql .= " AND sm.reference_type = :referans_tip";
        $params[':referans_tip'] = $referans_tip;
    }
    
    // Sıralama
    $sql .= " ORDER BY sm.created_at DESC";
    
    $stmt = $db->prepare($sql);
    foreach ($params as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->execute();
    $stok_hareketleri = $stmt->fetchAll();
    
} catch (PDOException $e) {
    // Hata durumunda
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Stok Hareketleri</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <?php if ($urun_id): ?>
            <a href="urun_duzenle.php?id=<?php echo $urun_id; ?>" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Ürüne Dön
            </a>
            <?php else: ?>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Stok Yönetimine Dön
            </a>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-secondary" onclick="window.print()">
                <i class="fas fa-print"></i> Yazdır
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="exportExcel">
                <i class="fas fa-file-excel"></i> Excel
            </button>
        </div>
    </div>
</div>

<?php if ($urun): ?>
<div class="alert alert-info">
    <strong>Ürün:</strong> <?php echo $urun['code'] . ' - ' . $urun['name']; ?>
    <br>
    <strong>Güncel Stok:</strong> <?php echo $urun['current_stock']; ?> <?php echo $urun['unit']; ?>
</div>
<?php endif; ?>

<!-- Filtreleme Formu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Filtreleme</h6>
    </div>
    <div class="card-body">
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-3">
            <?php if ($urun_id): ?>
            <input type="hidden" name="urun_id" value="<?php echo $urun_id; ?>">
            <?php else: ?>
            <div class="col-md-12">
                <label for="urun_id" class="form-label">Ürün</label>
                <select class="form-select select2" id="urun_id" name="urun_id">
                    <option value="">Tüm Ürünler</option>
                    <?php
                    // Ürünleri listele
                    try {
                        $stmt = $db->query("SELECT id, code, name FROM products ORDER BY code");
                        while ($row = $stmt->fetch()) {
                            $selected = ($urun_id == $row['id']) ? 'selected' : '';
                            echo '<option value="' . $row['id'] . '" ' . $selected . '>' . $row['code'] . ' - ' . $row['name'] . '</option>';
                        }
                    } catch (PDOException $e) {
                        // Hata durumunda
                    }
                    ?>
                </select>
            </div>
            <?php endif; ?>
            
            <div class="col-md-3">
                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>">
            </div>
            <div class="col-md-3">
                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>">
            </div>
            <div class="col-md-3">
                <label for="hareket_tipi" class="form-label">Hareket Tipi</label>
                <select class="form-select" id="hareket_tipi" name="hareket_tipi">
                    <option value="">Tümü</option>
                    <option value="giris" <?php echo $hareket_tipi == 'giris' ? 'selected' : ''; ?>>Giriş</option>
                    <option value="cikis" <?php echo $hareket_tipi == 'cikis' ? 'selected' : ''; ?>>Çıkış</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="referans_tip" class="form-label">Referans Tipi</label>
                <select class="form-select" id="referans_tip" name="referans_tip">
                    <option value="">Tümü</option>
                    <option value="alis_fatura" <?php echo $referans_tip == 'alis_fatura' ? 'selected' : ''; ?>>Alış Faturası</option>
                    <option value="satis_fatura" <?php echo $referans_tip == 'satis_fatura' ? 'selected' : ''; ?>>Satış Faturası</option>
                    <option value="stok_sayim" <?php echo $referans_tip == 'stok_sayim' ? 'selected' : ''; ?>>Stok Sayımı</option>
                    <option value="stok_transfer" <?php echo $referans_tip == 'stok_transfer' ? 'selected' : ''; ?>>Stok Transferi</option>
                    <option value="diger" <?php echo $referans_tip == 'diger' ? 'selected' : ''; ?>>Diğer</option>
                </select>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-filter"></i> Filtrele
                </button>
                <a href="<?php echo $urun_id ? 'stok_hareketleri.php?urun_id=' . $urun_id : 'stok_hareketleri.php'; ?>" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Sıfırla
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Stok Hareketleri Tablosu -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Stok Hareketleri Listesi</h6>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover datatable" id="stokHareketleriTable" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <?php if (!$urun_id): ?>
                        <th>Stok Kodu</th>
                        <th>Ürün Adı</th>
                        <?php endif; ?>
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
                    $toplam_giris = 0;
                    $toplam_cikis = 0;
                    $toplam_giris_tutar = 0;
                    $toplam_cikis_tutar = 0;
                    
                    foreach ($stok_hareketleri as $hareket): 
                        $toplam_tutar = $hareket['quantity'] * $hareket['unit_price'];
                        
                        if ($hareket['movement_type'] == 'giris') {
                            $toplam_giris += $hareket['quantity'];
                            $toplam_giris_tutar += $toplam_tutar;
                            $hareket_tipi_text = '<span class="badge bg-success">Giriş</span>';
                        } else {
                            $toplam_cikis += $hareket['quantity'];
                            $toplam_cikis_tutar += $toplam_tutar;
                            $hareket_tipi_text = '<span class="badge bg-danger">Çıkış</span>';
                        }
                    ?>
                    <tr>
                        <td><?php echo date('d.m.Y H:i', strtotime($hareket['created_at'])); ?></td>
                        <?php if (!$urun_id): ?>
                        <td><?php echo htmlspecialchars($hareket['stok_kodu']); ?></td>
                        <td><?php echo html_entity_decode($hareket['urun_adi'], ENT_QUOTES, 'UTF-8'); ?></td>
                        <?php endif; ?>
                        <td><?php echo $hareket_tipi_text; ?></td>
                        <td><?php echo number_format($hareket['quantity'], 2, ',', '.'); ?> <?php echo htmlspecialchars($hareket['birim']); ?></td>
                        <td>₺<?php echo number_format($hareket['unit_price'], 2, ',', '.'); ?></td>
                        <td>₺<?php echo number_format($toplam_tutar, 2, ',', '.'); ?></td>
                        <td>
                            <?php
                            $referans_tipler = [
                                'alis_fatura' => 'Alış Faturası',
                                'satis_fatura' => 'Satış Faturası',
                                'stok_sayim' => 'Stok Sayımı',
                                'stok_transfer' => 'Stok Transferi',
                                'diger' => 'Diğer'
                            ];
                            echo isset($referans_tipler[$hareket['reference_type']]) ? 
                                  $referans_tipler[$hareket['reference_type']] . ' - ' . htmlspecialchars($hareket['reference_no']) : 
                                  htmlspecialchars($hareket['reference_no']);
                            ?>
                        </td>
                        <td><?php echo htmlspecialchars($hareket['kullanici_adi'] . ' ' . $hareket['kullanici_soyad']); ?></td>
                        <td><?php echo htmlspecialchars($hareket['description']); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-primary">
                        <th>Toplam</th>
                        <?php if (!$urun_id): ?>
                        <th colspan="2"></th>
                        <?php endif; ?>
                        <th>
                            Giriş: <?php echo number_format($toplam_giris, 2, ',', '.'); ?><br>
                            Çıkış: <?php echo number_format($toplam_cikis, 2, ',', '.'); ?>
                        </th>
                        <th></th>
                        <th>
                            Giriş: ₺<?php echo number_format($toplam_giris_tutar, 2, ',', '.'); ?><br>
                            Çıkış: ₺<?php echo number_format($toplam_cikis_tutar, 2, ',', '.'); ?>
                        </th>
                        <th colspan="4"></th>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Excel'e dışa aktarma
        document.getElementById('exportExcel').addEventListener('click', function() {
            window.location.href = 'stok_hareketleri_export.php?format=excel<?php 
                echo $urun_id ? '&urun_id=' . $urun_id : ''; 
                echo $baslangic_tarihi ? '&baslangic_tarihi=' . $baslangic_tarihi : ''; 
                echo $bitis_tarihi ? '&bitis_tarihi=' . $bitis_tarihi : ''; 
                echo $hareket_tipi ? '&hareket_tipi=' . $hareket_tipi : ''; 
                echo $referans_tip ? '&referans_tip=' . $referans_tip : ''; 
            ?>';
        });
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 