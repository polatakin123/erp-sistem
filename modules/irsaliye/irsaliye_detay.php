<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Hata mesajlarını gösterme ayarları
ini_set('display_errors', 1);
error_reporting(E_ALL);

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    $_SESSION['error'] = 'Geçersiz irsaliye ID.';
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

// Debug modu açık olsun
$debug_mode = true;

try {
    // İrsaliye bilgilerini getir - TIP değerlerini güncelledik
    $query = "SELECT f.*, c.ADI as cari_unvan, a.ADRES1 as cari_adres, 
             CASE 
               WHEN f.IPTAL = 1 THEN 'İptal' 
               WHEN f.FATURALANDI = 1 THEN 'Faturalandı' 
               ELSE 'Beklemede' 
             END as durum,
             CASE 
               WHEN f.TIP = '10' THEN 'Alış İrsaliyesi'
               WHEN f.TIP = '11' THEN 'Alış İrsaliyesi'
               WHEN f.TIP = '12' THEN 'Yurt İçi Satış İrsaliyesi'
               WHEN f.TIP = '13' THEN 'Yurt Dışı Satış İrsaliyesi'
               WHEN f.TIP = '14' THEN 'Konsinye Giriş İrsaliyesi'
               WHEN f.TIP = '15' THEN 'Konsinye Çıkış İrsaliyesi'
               WHEN f.TIP = '20' THEN 'İrsaliye'
               ELSE f.TIP
             END as tip_aciklama
             FROM stk_fis f 
             LEFT JOIN cari c ON f.CARIID = c.ID 
             LEFT JOIN adres a ON c.ID = a.KARTID
             WHERE f.ID = ? AND f.TIP IN ('10', '11', '12', '13', '14', '15', '20', 'İrsaliye', 'Irsaliye', 'IRSALIYE')";
    $stmt = $db->prepare($query);
    $stmt->execute([$irsaliye_id]);
    $irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$irsaliye) {
        throw new Exception("İrsaliye bulunamadı (ID: $irsaliye_id)");
    }

    // İrsaliye kalemlerini getir
    $query = "SELECT h.*, s.KOD as urun_kod, s.ADI as urun_adi 
              FROM stk_fis_har h 
              LEFT JOIN stok s ON h.KARTID = s.ID
              WHERE h.STKFISID = ? AND h.KARTTIPI = 'S' 
              ORDER BY h.SIRANO, h.ID ASC";
    $stmt = $db->prepare($query);
    $stmt->execute([$irsaliye_id]);
    $all_kalemler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İrsaliye kalemlerini doğru şekilde gruplayalım
    $processed_kalemler = [];
    $seen_indexes = [];
    $filtered_out = []; // Filtrelenen kayıtlar

    foreach ($all_kalemler as $key => $kalem) {
        // Her kalem için eşsiz tanımlayıcı oluştur
        $identifier = $kalem['KARTID'] . '_' . $kalem['SIRANO'];
        
        if (!isset($seen_indexes[$identifier])) {
            // Bu kombinasyon ilk kez görüldü, kalemin birim bilgisini alıp listeye ekleyelim
            $kalem['birim'] = getBirim($kalem['BIRIMID']);
            $processed_kalemler[] = $kalem;
            $seen_indexes[$identifier] = $kalem['ID']; // ID'yi saklayalım
        } else {
            // Bu kayıt filtrelendi, debug bilgisi için saklayalım
            $filtered_out[] = [
                'id' => $kalem['ID'],
                'kartid' => $kalem['KARTID'],
                'sirano' => $kalem['SIRANO'],
                'identifier' => $identifier,
                'duplicate_of' => $seen_indexes[$identifier]
            ];
        }
    }

    $kalemler = $processed_kalemler;

} catch (Exception $e) {
    // Hata durumunda fonksiyonları yüklemeye devam edelim ancak hatayı gösterelim
    $error_message = $e->getMessage();
    error_log("İrsaliye Detay Hatası: " . $error_message);
    
    // Hatayı boş dizilerle yönetelim
    $irsaliye = [];
    $kalemler = [];
    $all_kalemler = [];
    $processed_kalemler = [];
    $filtered_out = [];
}

// Sayfa başlığı
$pageTitle = isset($irsaliye['FISNO']) ? $irsaliye['FISNO'] . " Numaralı İrsaliye" : "İrsaliye Detayı";

// Header'ı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center mb-3">
                <h2><?php echo $pageTitle; ?></h2>
                <div>
                    <a href="irsaliye_listesi.php" class="btn btn-secondary">
                        <i class="fas fa-arrow-left"></i> Listeye Dön
                    </a>
                    <a href="javascript:window.print()" class="btn btn-info">
                        <i class="fas fa-print"></i> Yazdır
                    </a>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
            <div class="alert alert-danger">
                <h4><i class="fas fa-exclamation-triangle"></i> Hata Oluştu</h4>
                <p><?php echo $error_message; ?></p>
                <hr>
                <p>
                    <strong>İrsaliye ID:</strong> <?php echo htmlspecialchars($irsaliye_id); ?><br>
                    <strong>TIP değeri kontrolü:</strong> TIP değeri '10', '11', '12', '13', '14', '15', '20', 'İrsaliye', 'Irsaliye', 'IRSALIYE' olmalıdır.
                </p>
                <div class="mt-3">
                    <h5>Veritabanı Kontrolü:</h5>
                    <?php 
                    // İrsaliye kaydını kontrol et
                    $check_query = "SELECT ID, TIP, FISNO FROM stk_fis WHERE ID = ?";
                    $check_stmt = $db->prepare($check_query);
                    $check_stmt->execute([$irsaliye_id]);
                    $check_result = $check_stmt->fetch(PDO::FETCH_ASSOC);
                    
                    if ($check_result) {
                        echo '<p>İrsaliye kaydı bulundu: ID='.$check_result['ID'].', TIP='.$check_result['TIP'].', FISNO='.$check_result['FISNO'].'</p>';
                    } else {
                        echo '<p>İrsaliye kaydı bulunamadı!</p>';
                    }
                    ?>
                </div>
            </div>
            <?php endif; ?>
            
            <?php if (!empty($irsaliye)): ?>
            <div class="card mb-4">
                <div class="card-header">
                    <h4>İrsaliye Bilgileri</h4>
                </div>
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">İrsaliye No</th>
                                    <td><?php echo $irsaliye['FISNO']; ?></td>
                                </tr>
                                <tr>
                                    <th>Tarih</th>
                                    <td><?php echo date('d.m.Y', strtotime($irsaliye['FISTAR'])); ?></td>
                                </tr>
                                <tr>
                                    <th>İrsaliye Tipi</th>
                                    <td><?php echo $irsaliye['tip_aciklama'] ?? $irsaliye['TIP']; ?></td>
                                </tr>
                                <tr>
                                    <th>Cari</th>
                                    <td><?php echo $irsaliye['cari_unvan']; ?></td>
                                </tr>
                                <tr>
                                    <th>Durum</th>
                                    <td>
                                        <?php 
                                        $durum_class = 'warning';
                                        if ($irsaliye['durum'] == 'Faturalandı') $durum_class = 'success';
                                        if ($irsaliye['durum'] == 'İptal') $durum_class = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $durum_class; ?>"><?php echo $irsaliye['durum']; ?></span>
                                    </td>
                                </tr>
                            </table>
                        </div>
                        <div class="col-md-6">
                            <table class="table table-bordered">
                                <tr>
                                    <th width="30%">Adres</th>
                                    <td><?php echo $irsaliye['cari_adres'] ?? '-'; ?></td>
                                </tr>
                                <tr>
                                    <th>Notlar</th>
                                    <td><?php echo $irsaliye['NOTLAR'] ?? '-'; ?></td>
                                </tr>
                                <tr>
                                    <th>KDV Dahil</th>
                                    <td><?php echo $irsaliye['KDVDAHIL'] ? 'Evet' : 'Hayır'; ?></td>
                                </tr>
                                <tr>
                                    <th>Toplam Tutar</th>
                                    <td><strong><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</strong></td>
                                </tr>
                                <tr>
                                    <th>İşlem Zamanı</th>
                                    <td><?php echo isset($irsaliye['FISSAAT']) ? $irsaliye['FISSAAT'] : '-'; ?></td>
                                </tr>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="card">
                <div class="card-header">
                    <h4>İrsaliye Kalemleri</h4>
                </div>
                <div class="card-body">
                    <?php if (empty($kalemler)): ?>
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> Bu irsaliyeye ait kalem bulunamadı!
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th>S.No</th>
                                        <th>Ürün Kodu</th>
                                        <th>Ürün Adı</th>
                                        <th class="text-center">Miktar</th>
                                        <th>Birim</th>
                                        <th class="text-end">Birim Fiyat</th>
                                        <th class="text-end">KDV Oranı</th>
                                        <th class="text-end">KDV Tutarı</th>
                                        <th class="text-end">Toplam Tutar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    $toplam_miktar = 0;
                                    $toplam_tutar = 0;
                                    $toplam_kdv = 0;
                                    
                                    foreach ($kalemler as $index => $kalem): 
                                        $toplam_miktar += $kalem['MIKTAR'] ?? 0;
                                        $toplam_tutar += $kalem['TUTAR'] ?? 0;
                                        $kdv_tutari = ($kalem['KDVTUTARI'] ?? 0);
                                        $toplam_kdv += $kdv_tutari;
                                    ?>
                                    <tr>
                                        <td><?php echo $kalem['SIRANO'] ?? ($index + 1); ?></td>
                                        <td><?php echo $kalem['urun_kod'] ?? '-'; ?></td>
                                        <td><?php echo $kalem['urun_adi'] ?? '-'; ?></td>
                                        <td class="text-center"><?php echo number_format($kalem['MIKTAR'] ?? 0, 2, ',', '.'); ?></td>
                                        <td><?php echo $kalem['birim'] ?? '-'; ?></td>
                                        <td class="text-end"><?php echo number_format($kalem['FIYAT'] ?? 0, 2, ',', '.'); ?> ₺</td>
                                        <td class="text-end">%<?php echo number_format($kalem['KDVORANI'] ?? 0, 0); ?></td>
                                        <td class="text-end"><?php echo number_format($kdv_tutari, 2, ',', '.'); ?> ₺</td>
                                        <td class="text-end"><?php echo number_format($kalem['TUTAR'] ?? 0, 2, ',', '.'); ?> ₺</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="table-secondary">
                                        <th colspan="3">TOPLAM</th>
                                        <th class="text-center"><?php echo number_format($toplam_miktar, 2, ',', '.'); ?></th>
                                        <th></th>
                                        <th></th>
                                        <th></th>
                                        <th class="text-end"><?php echo number_format($toplam_kdv, 2, ',', '.'); ?> ₺</th>
                                        <th class="text-end"><?php echo number_format($toplam_tutar, 2, ',', '.'); ?> ₺</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Debug bilgisi gösterimi -->
            <?php if ($debug_mode && !empty($all_kalemler)): ?>
            <div class="card mt-4">
                <div class="card-header bg-dark text-white">
                    <h5 class="mb-0"><i class="fas fa-bug"></i> Debug Bilgileri</h5>
                </div>
                <div class="card-body">
                    <ul class="nav nav-tabs" id="debugTab" role="tablist">
                        <li class="nav-item" role="presentation">
                            <button class="nav-link active" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">Özet</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="filtered-tab" data-bs-toggle="tab" data-bs-target="#filtered" type="button" role="tab">Filtrelenen Kayıtlar</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="processed-tab" data-bs-toggle="tab" data-bs-target="#processed" type="button" role="tab">İşlenmiş Kalemler</button>
                        </li>
                        <li class="nav-item" role="presentation">
                            <button class="nav-link" id="raw-tab" data-bs-toggle="tab" data-bs-target="#raw" type="button" role="tab">Ham Veriler</button>
                        </li>
                    </ul>
                    <div class="tab-content pt-3" id="debugTabContent">
                        <div class="tab-pane fade show active" id="summary" role="tabpanel">
                            <p><strong>Toplam sorgu sonucu:</strong> <?php echo count($all_kalemler); ?> kayıt</p>
                            <p><strong>İşlenmiş kalemler:</strong> <?php echo count($processed_kalemler); ?> kayıt</p>
                            <p><strong>Filtrelenen kayıtlar:</strong> <?php echo count($filtered_out); ?> kayıt</p>
                            <p><strong>İrsaliye Tipi:</strong> <?php echo isset($irsaliye['TIP']) ? $irsaliye['TIP'] . ' (' . ($irsaliye['tip_aciklama'] ?? 'Bilinmiyor') . ')' : 'Bilinmiyor'; ?></p>
                        </div>
                        <div class="tab-pane fade" id="filtered" role="tabpanel">
                            <?php if (count($filtered_out) > 0): ?>
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>ID</th><th>Ürün ID</th><th>Sıra No</th><th>Tanımlayıcı</th><th>Şunun tekrarı</th></tr></thead>
                                <tbody>
                                    <?php foreach ($filtered_out as $item): ?>
                                    <tr>
                                        <td><?php echo $item['id']; ?></td>
                                        <td><?php echo $item['kartid']; ?></td>
                                        <td><?php echo $item['sirano']; ?></td>
                                        <td><?php echo $item['identifier']; ?></td>
                                        <td><?php echo $item['duplicate_of']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                            <?php else: ?>
                            <p>Filtrelenen kayıt bulunmuyor.</p>
                            <?php endif; ?>
                        </div>
                        <div class="tab-pane fade" id="processed" role="tabpanel">
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>ID</th><th>Ürün ID</th><th>Ürün Kod</th><th>Ürün Adı</th><th>Sıra No</th><th>Fiyat</th></tr></thead>
                                <tbody>
                                    <?php foreach ($processed_kalemler as $item): ?>
                                    <tr>
                                        <td><?php echo $item['ID']; ?></td>
                                        <td><?php echo $item['KARTID']; ?></td>
                                        <td><?php echo isset($item['urun_kod']) ? $item['urun_kod'] : '-'; ?></td>
                                        <td><?php echo isset($item['urun_adi']) ? $item['urun_adi'] : '-'; ?></td>
                                        <td><?php echo $item['SIRANO']; ?></td>
                                        <td><?php echo $item['FIYAT']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        <div class="tab-pane fade" id="raw" role="tabpanel">
                            <table class="table table-sm table-bordered">
                                <thead><tr><th>ID</th><th>Ürün ID</th><th>Ürün Kod</th><th>Ürün Adı</th><th>Sıra No</th><th>Fiyat</th></tr></thead>
                                <tbody>
                                    <?php foreach ($all_kalemler as $item): ?>
                                    <tr>
                                        <td><?php echo $item['ID']; ?></td>
                                        <td><?php echo $item['KARTID']; ?></td>
                                        <td><?php echo isset($item['urun_kod']) ? $item['urun_kod'] : '-'; ?></td>
                                        <td><?php echo isset($item['urun_adi']) ? $item['urun_adi'] : '-'; ?></td>
                                        <td><?php echo $item['SIRANO']; ?></td>
                                        <td><?php echo $item['FIYAT']; ?></td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?> 