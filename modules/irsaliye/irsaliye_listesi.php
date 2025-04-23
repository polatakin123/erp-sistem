<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Performans için script başlangıç zamanını not edelim
$baslangic_zamani = microtime(true);

// Hata yakalamak için try-catch bloğu ekleyelim
try {
    // Oturum kontrolü
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }

    // Başarı mesajını görüntülemek için
    $success = isset($_GET['success']) ? true : false;
    $success_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

    // Arama parametreleri
    $tarih_baslangic = isset($_GET['tarih_baslangic']) ? $_GET['tarih_baslangic'] : date('Y-m-01');
    $tarih_bitis = isset($_GET['tarih_bitis']) ? $_GET['tarih_bitis'] : date('Y-m-d');
    $cari_id = isset($_GET['cari_id']) ? $_GET['cari_id'] : '';
    $durum = isset($_GET['durum']) ? $_GET['durum'] : '';

    // Cari listesi - Performans için limit koyalım
    $cari_listesi = [];
    $cari_query = "SELECT ID, ADI FROM cari ORDER BY ADI LIMIT 1000"; // Çok fazla cari olmaması için limit
    $cari_stmt = $db->query($cari_query);
    
    if ($cari_stmt) {
        while ($row = $cari_stmt->fetch(PDO::FETCH_ASSOC)) {
            $cari_listesi[$row['ID']] = $row['ADI'];
        }
    } else {
        // Sorgu başarısız olursa hata mesajını kaydet
        error_log("Cari sorgusu çalıştırılamadı: " . print_r($db->errorInfo(), true));
    }

    // İrsaliye listesi sorgusu - daha verimli hale getirelim
    $query = "SELECT 
                f.ID, 
                f.FISNO, 
                f.FISTAR, 
                f.CARIID, 
                f.GENELTOPLAM, 
                f.IPTAL, 
                f.FATURALANDI,
                c.ADI as cari_adi,
                CASE 
                  WHEN f.IPTAL = 1 THEN 'İptal' 
                  WHEN f.FATURALANDI = 1 THEN 'Faturalandı' 
                  ELSE 'Beklemede' 
                END as durum,
                f.TIP
              FROM 
                stk_fis f
              LEFT JOIN 
                cari c ON f.CARIID = c.ID
              WHERE 
                f.TIP IN ('10', '11', '12', '13', '14', '15', '20', 'İrsaliye', 'Irsaliye', 'IRSALIYE')";

    $params = [];

    // Filtreleri ekle
    if (!empty($tarih_baslangic)) {
        $query .= " AND f.FISTAR >= ?";
        $params[] = date('Y-m-d', strtotime($tarih_baslangic));
    }

    if (!empty($tarih_bitis)) {
        $query .= " AND f.FISTAR <= ?";
        $params[] = date('Y-m-d', strtotime($tarih_bitis));
    }

    if (!empty($cari_id)) {
        $query .= " AND f.CARIID = ?";
        $params[] = $cari_id;
    }

    if (!empty($durum)) {
        if ($durum == 'İptal') {
            $query .= " AND f.IPTAL = 1";
        } elseif ($durum == 'Faturalandı') {
            $query .= " AND f.FATURALANDI = 1 AND f.IPTAL = 0";
        } else {
            $query .= " AND f.FATURALANDI = 0 AND f.IPTAL = 0";
        }
    }

    // Son 100 kayıtla sınırlayalım - performans için
    $query .= " ORDER BY f.FISTAR DESC LIMIT 100";

    $stmt = $db->prepare($query);
    if (!$stmt) {
        throw new Exception("Sorgu hazırlanamadı: " . print_r($db->errorInfo(), true));
    }
    
    $stmt_executed = $stmt->execute($params);
    if (!$stmt_executed) {
        throw new Exception("Sorgu çalıştırılamadı: " . print_r($stmt->errorInfo(), true));
    }
    
    $irsaliyeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // TIP değerlerini yorumla
    $tip_aciklamalari = [
        '10' => 'Alış İrsaliyesi',
        '11' => 'Alış İrsaliyesi',
        '12' => 'Yurt İçi Satış İrsaliyesi',
        '13' => 'Yurt Dışı Satış İrsaliyesi',
        '14' => 'Konsinye Giriş İrsaliyesi',
        '15' => 'Konsinye Çıkış İrsaliyesi',
        '20' => 'Satış İrsaliyesi'
    ];

    // Sayfa başlığı
    $pageTitle = "İrsaliye Listesi";
    
    // Performans ölçümü
    $bitis_zamani = microtime(true);
    $calisma_suresi = $bitis_zamani - $baslangic_zamani;
    
    // Önbellek kontrolü başlıkları
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');

    include_once '../../includes/header.php';
} catch (Exception $e) {
    error_log("İrsaliye listesi hatası: " . $e->getMessage());
    $error_message = "Bir hata oluştu: " . $e->getMessage();
    $irsaliyeler = [];
    $pageTitle = "İrsaliye Listesi - Hata";
    include_once '../../includes/header.php';
}
?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="alis_irsaliyesi.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-file-import"></i> Alış İrsaliyesi
                        </a>
                        <a href="irsaliye_ekle.php" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Yeni İrsaliye
                        </a>
                    </div>
                </div>
            </div>
            
            <?php if (isset($error_message)): ?>
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if ($success): ?>
                <div class="alert alert-success alert-dismissible fade show">
                    <i class="fas fa-check-circle"></i> İrsaliye başarıyla kaydedildi!
                    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Kapat"></button>
                </div>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Filtreleme</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-3">
                            <label for="tarih_baslangic" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="tarih_baslangic" name="tarih_baslangic" value="<?php echo $tarih_baslangic; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="tarih_bitis" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="tarih_bitis" name="tarih_bitis" value="<?php echo $tarih_bitis; ?>">
                        </div>
                        <div class="col-md-3">
                            <label for="cari_id" class="form-label">Cari</label>
                            <select class="form-select" id="cari_id" name="cari_id">
                                <option value="">Tümü</option>
                                <?php foreach ($cari_listesi as $id => $ad): ?>
                                <option value="<?php echo $id; ?>" <?php echo $cari_id == $id ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($ad); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="durum" class="form-label">Durum</label>
                            <select class="form-select" id="durum" name="durum">
                                <option value="">Tümü</option>
                                <option value="Beklemede" <?php echo $durum == 'Beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                <option value="Faturalandı" <?php echo $durum == 'Faturalandı' ? 'selected' : ''; ?>>Faturalandı</option>
                                <option value="İptal" <?php echo $durum == 'İptal' ? 'selected' : ''; ?>>İptal</option>
                            </select>
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary w-100">Filtrele</button>
                        </div>
                    </form>
                </div>
            </div>

            <div class="card">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-file-alt"></i> İrsaliyeler (<?php echo count($irsaliyeler); ?>)</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($calisma_suresi)): ?>
                    <div class="small text-muted mb-3">
                        Sorgu <?php echo number_format($calisma_suresi * 1000, 2); ?> ms'de tamamlandı.
                    </div>
                    <?php endif; ?>
                    
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered" id="irsaliyeTable">
                            <thead class="table-light">
                                <tr>
                                    <th width="12%">İrsaliye No</th>
                                    <th width="10%">Tarih</th>
                                    <th width="25%">Cari</th>
                                    <th width="15%">Tip</th>
                                    <th width="15%" class="text-end">Tutar</th>
                                    <th width="10%">Durum</th>
                                    <th width="13%">İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($irsaliyeler as $irsaliye): ?>
                                <tr<?php echo $success && $success_id == $irsaliye['ID'] ? ' class="table-success"' : ''; ?>>
                                    <td><strong><?php echo htmlspecialchars($irsaliye['FISNO']); ?></strong></td>
                                    <td><?php echo date('d.m.Y', strtotime($irsaliye['FISTAR'])); ?></td>
                                    <td><?php echo htmlspecialchars($irsaliye['cari_adi'] ?? 'Belirtilmemiş'); ?></td>
                                    <td>
                                        <?php 
                                        echo isset($tip_aciklamalari[$irsaliye['TIP']]) 
                                            ? $tip_aciklamalari[$irsaliye['TIP']] 
                                            : ($irsaliye['TIP'] ?? 'Belirsiz'); 
                                        ?>
                                    </td>
                                    <td class="text-end fw-bold"><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</td>
                                    <td>
                                        <?php
                                        $durum_class = 'warning';
                                        if ($irsaliye['durum'] == 'Faturalandı') $durum_class = 'success';
                                        if ($irsaliye['durum'] == 'İptal') $durum_class = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $durum_class; ?>">
                                            <?php echo htmlspecialchars($irsaliye['durum']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="irsaliye_detay.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-info" title="Detay">
                                                <i class="fas fa-eye"></i>
                                            </a>
                                            <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                                            <a href="irsaliye_duzenle.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-primary" title="Düzenle">
                                                <i class="fas fa-edit"></i>
                                            </a>
                                            <a href="irsaliye_iptal.php?id=<?php echo $irsaliye['ID']; ?>&confirm=1" class="btn btn-danger" title="İptal Et"
                                               onclick="return confirm('Bu irsaliyeyi iptal etmek istediğinize emin misiniz?')">
                                                <i class="fas fa-times"></i>
                                            </a>
                                            <?php endif; ?>
                                            <a href="irsaliye_yazdir.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-secondary" title="Yazdır">
                                                <i class="fas fa-print"></i>
                                            </a>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
<script>
    $(document).ready(function() {
        // Başarı mesajını 5 saniye sonra otomatik kapat
        setTimeout(function() {
            $('.alert-success').fadeOut('slow');
        }, 5000);
        
        // DataTable özelliklerini ayarla
        $('#irsaliyeTable').DataTable({
            "language": {
                "url": "//cdn.datatables.net/plug-ins/1.13.5/i18n/tr.json"
            },
            "pageLength": 25,
            "order": [],
            "stateSave": true,
            "processing": true,
            "responsive": true
        });
    });
</script>

<?php include_once '../../includes/footer.php'; ?> 