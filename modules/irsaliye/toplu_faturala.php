<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Sayfa başlığı
$pageTitle = "İrsaliyeleri Toplu Faturala";

// Filtreleme parametreleri
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-01');
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$cari_id = isset($_GET['cari_id']) ? $_GET['cari_id'] : '';
$irsaliye_tipi = isset($_GET['irsaliye_tipi']) ? $_GET['irsaliye_tipi'] : '';

// Tip değerlerini tanımlayalım
$tip_listesi = [
    '12' => 'Yurt İçi Satış İrsaliyesi',
    '13' => 'Yurt Dışı Satış İrsaliyesi',
    '15' => 'Konsinye Çıkış İrsaliyesi'
];

// Cari listesini çekelim
$cari_query = "SELECT ID, ADI FROM cari ORDER BY ADI";
$cari_stmt = $db->query($cari_query);
$cari_listesi = $cari_stmt->fetchAll(PDO::FETCH_ASSOC);

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['faturala'])) {
    try {
        $db->beginTransaction();
        
        // Faturalanacak irsaliyeler
        $irsaliye_ids = $_POST['secilen_irsaliyeler'] ?? [];
        
        if (empty($irsaliye_ids)) {
            throw new Exception("Faturalanacak irsaliye seçilmedi.");
        }
        
        // İrsaliyeleri faturalandırma işlemi
        // Fatura oluşturma için gerekli verileri hazırla
        $tarih = $_POST['fatura_tarihi'] ?? date('Y-m-d');
        $fatura_tipi = $_POST['fatura_tipi'] ?? '21'; // Varsayılan satış faturası
        
        // Fatura numarası oluştur
        $fatura_no_query = "SELECT MAX(CAST(SUBSTRING(FISNO, 3) AS UNSIGNED)) as max_no FROM fatura WHERE TIP = ?";
        $fatura_no_stmt = $db->prepare($fatura_no_query);
        $fatura_no_stmt->execute([$fatura_tipi]);
        $fatura_no_result = $fatura_no_stmt->fetch(PDO::FETCH_ASSOC);
        $next_no = ($fatura_no_result['max_no'] ?? 0) + 1;
        
        $fatura_no_prefix = ($fatura_tipi == '21') ? 'SF' : 'YS';
        $fatura_no = $fatura_no_prefix . str_pad($next_no, 6, '0', STR_PAD_LEFT);
        
        // İlk irsaliyeden cari bilgilerini al
        $cari_query = "SELECT CARIID, CARIADI FROM stk_fis WHERE ID = ? LIMIT 1";
        $cari_stmt = $db->prepare($cari_query);
        $cari_stmt->execute([$irsaliye_ids[0]]);
        $cari_bilgi = $cari_stmt->fetch(PDO::FETCH_ASSOC);
        
        // Toplam tutarı hesapla
        $toplam_query = "SELECT SUM(GENELTOPLAM) as toplam FROM stk_fis WHERE ID IN (" . implode(',', array_fill(0, count($irsaliye_ids), '?')) . ")";
        $toplam_stmt = $db->prepare($toplam_query);
        $toplam_stmt->execute($irsaliye_ids);
        $toplam_bilgi = $toplam_stmt->fetch(PDO::FETCH_ASSOC);
        $toplam_tutar = $toplam_bilgi['toplam'] ?? 0;
        
        // Fatura başlık bilgilerini kaydet
        $fatura_query = "INSERT INTO fatura (
                        TIP, FISNO, FISTAR, FISSAAT, CARIID, 
                        GENELTOPLAM, CARIADI, IPTAL, NOTLAR, DURUM, 
                        ODENDI, TURU, VADE
                    ) VALUES (
                        ?, ?, ?, NOW(), ?,
                        ?, ?, 0, ?, 1,
                        0, ?, ?
                    )";
        
        $fatura_stmt = $db->prepare($fatura_query);
        $fatura_stmt->execute([
            $fatura_tipi,
            $fatura_no,
            $tarih,
            $cari_bilgi['CARIID'],
            $toplam_tutar,
            $cari_bilgi['CARIADI'],
            "Toplu irsaliye faturalaması (" . implode(',', $irsaliye_ids) . ")",
            $_POST['odeme_turu'] ?? 'Nakit',
            $_POST['vade_tarihi'] ?? $tarih
        ]);
        
        $fatura_id = $db->lastInsertId();
        
        // İrsaliyeleri faturalandı olarak işaretle
        $update_query = "UPDATE stk_fis SET FATURALANDI = 1, FATURAID = ? WHERE ID IN (" . implode(',', array_fill(0, count($irsaliye_ids), '?')) . ")";
        $update_params = [$fatura_id];
        $update_params = array_merge($update_params, $irsaliye_ids);
        
        $update_stmt = $db->prepare($update_query);
        $update_stmt->execute($update_params);
        
        // Her irsaliye için fatura_detay tablosuna kayıt ekle
        foreach ($irsaliye_ids as $key => $irsaliye_id) {
            $irsaliye_query = "SELECT * FROM stk_fis WHERE ID = ?";
            $irsaliye_stmt = $db->prepare($irsaliye_query);
            $irsaliye_stmt->execute([$irsaliye_id]);
            $irsaliye = $irsaliye_stmt->fetch(PDO::FETCH_ASSOC);
            
            $detay_query = "INSERT INTO fatura_detay (
                            FATURAID, IRSALIYEID, MIKTAR, BIRIM, FIYAT, 
                            TUTAR, ACIKLAMA, SIRANO
                        ) VALUES (
                            ?, ?, 1, 'ADET', ?,
                            ?, ?, ?
                        )";
            
            $detay_stmt = $db->prepare($detay_query);
            $detay_stmt->execute([
                $fatura_id,
                $irsaliye_id,
                $irsaliye['GENELTOPLAM'],
                $irsaliye['GENELTOPLAM'],
                "İrsaliye No: " . $irsaliye['FISNO'] . " (" . date('d.m.Y', strtotime($irsaliye['FISTAR'])) . ")",
                $key + 1
            ]);
        }
        
        $db->commit();
        $success = "Seçilen irsaliyeler başarıyla faturalandı. Fatura No: " . $fatura_no;
        
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Hata oluştu: " . $e->getMessage();
    }
}

// Faturalanabilir irsaliyeleri çekelim (Sayfalandırma desteği ekleyelim)
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = 50; // Sayfa başına gösterilecek kayıt sayısı
$offset = ($sayfa - 1) * $limit;

// Toplam kayıt sayısını al
$count_query = "SELECT COUNT(*) as total FROM stk_fis f
               WHERE f.IPTAL = 0 AND f.FATURALANDI = 0";

$count_params = [];

// Filtreleme koşullarını ekleyelim
if (!empty($baslangic_tarihi)) {
    $count_query .= " AND f.FISTAR >= ?";
    $count_params[] = $baslangic_tarihi;
}

if (!empty($bitis_tarihi)) {
    $count_query .= " AND f.FISTAR <= ?";
    $count_params[] = $bitis_tarihi;
}

if (!empty($irsaliye_tipi)) {
    $count_query .= " AND f.TIP = ?";
    $count_params[] = $irsaliye_tipi;
}

if (!empty($cari_id)) {
    $count_query .= " AND f.CARIID = ?";
    $count_params[] = $cari_id;
}

// Sadece faturalanabilir tipleri filtrele
$faturalanabilir_tipler = array_keys($tip_listesi);
$placeholders = implode(',', array_fill(0, count($faturalanabilir_tipler), '?'));
$count_query .= " AND f.TIP IN ($placeholders)";
$count_params = array_merge($count_params, $faturalanabilir_tipler);

$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$total_pages = ceil($total_records / $limit);

// Ana sorgu
$query = "SELECT 
            f.ID, 
            f.FISNO, 
            f.FISTAR, 
            f.TIP, 
            f.GENELTOPLAM, 
            c.ADI AS cari_adi
          FROM 
            stk_fis f
          LEFT JOIN 
            cari c ON f.CARIID = c.ID
          WHERE 
            f.IPTAL = 0 AND f.FATURALANDI = 0";

$params = [];

// Filtreleme koşullarını ekleyelim
if (!empty($baslangic_tarihi)) {
    $query .= " AND f.FISTAR >= ?";
    $params[] = $baslangic_tarihi;
}

if (!empty($bitis_tarihi)) {
    $query .= " AND f.FISTAR <= ?";
    $params[] = $bitis_tarihi;
}

if (!empty($irsaliye_tipi)) {
    $query .= " AND f.TIP = ?";
    $params[] = $irsaliye_tipi;
}

if (!empty($cari_id)) {
    $query .= " AND f.CARIID = ?";
    $params[] = $cari_id;
}

// Sadece faturalanabilir tipleri filtrele
$placeholders = implode(',', array_fill(0, count($faturalanabilir_tipler), '?'));
$query .= " AND f.TIP IN ($placeholders)";
$params = array_merge($params, $faturalanabilir_tipler);

// Sıralama
$query .= " ORDER BY f.FISTAR DESC";

// Limit ve Offset ekle
$query .= " LIMIT ? OFFSET ?";
$params[] = $limit;
$params[] = $offset;

// Sorguyu çalıştır
$stmt = $db->prepare($query);
$stmt->execute($params);
$irsaliyeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

include_once '../../includes/header.php';
?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <div class="btn-group me-2">
                        <a href="irsaliye_listesi.php" class="btn btn-sm btn-outline-secondary">
                            <i class="fas fa-list"></i> İrsaliye Listesi
                        </a>
                    </div>
                </div>
            </div>

            <?php if (isset($success)): ?>
            <div class="alert alert-success"><?php echo $success; ?></div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <!-- Filtreleme Bölümü -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-filter"></i> Filtreleme
                </div>
                <div class="card-body">
                    <form method="get" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-3">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>">
                            </div>
                            <div class="col-md-3">
                                <label for="irsaliye_tipi" class="form-label">İrsaliye Tipi</label>
                                <select class="form-select" id="irsaliye_tipi" name="irsaliye_tipi">
                                    <option value="">Tümü</option>
                                    <?php foreach ($tip_listesi as $tip_kodu => $tip_adi): ?>
                                        <option value="<?php echo $tip_kodu; ?>" <?php echo ($irsaliye_tipi == $tip_kodu) ? 'selected' : ''; ?>>
                                            <?php echo $tip_adi; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label for="cari_id" class="form-label">Cari</label>
                                <select class="form-select" id="cari_id" name="cari_id">
                                    <option value="">Tümü</option>
                                    <?php foreach ($cari_listesi as $cari): ?>
                                        <option value="<?php echo $cari['ID']; ?>" <?php echo ($cari_id == $cari['ID']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cari['ADI']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-12 text-end">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Filtrele
                                </button>
                                <a href="toplu_faturala.php" class="btn btn-secondary">
                                    <i class="fas fa-sync"></i> Sıfırla
                                </a>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Faturalanabilir İrsaliyeler Listesi -->
            <form method="post" id="faturaForm">
                <div class="card">
                    <div class="card-header">
                        <div class="d-flex justify-content-between align-items-center">
                            <span><i class="fas fa-list"></i> Faturalanabilir İrsaliyeler (Toplam: <?php echo $total_records; ?>)</span>
                            <div>
                                <button type="button" class="btn btn-sm btn-outline-primary" id="tumunuSec">
                                    <i class="fas fa-check-square"></i> Tümünü Seç
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-secondary" id="tumunuKaldir">
                                    <i class="fas fa-square"></i> Tümünü Kaldır
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (count($irsaliyeler) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-hover table-sm" id="irsaliyeTable">
                                    <thead>
                                        <tr>
                                            <th width="5%" class="text-center">Seç</th>
                                            <th>İrsaliye No</th>
                                            <th>Tarih</th>
                                            <th>Tip</th>
                                            <th>Cari</th>
                                            <th class="text-end">Tutar</th>
                                            <th>İşlemler</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($irsaliyeler as $irsaliye): ?>
                                            <tr>
                                                <td class="text-center">
                                                    <input type="checkbox" class="form-check-input irsaliye-check" 
                                                           name="secilen_irsaliyeler[]" 
                                                           value="<?php echo $irsaliye['ID']; ?>" 
                                                           data-cari="<?php echo $irsaliye['cari_adi']; ?>"
                                                           data-tip="<?php echo $irsaliye['TIP']; ?>">
                                                </td>
                                                <td><?php echo htmlspecialchars($irsaliye['FISNO']); ?></td>
                                                <td><?php echo date('d.m.Y', strtotime($irsaliye['FISTAR'])); ?></td>
                                                <td>
                                                    <?php echo isset($tip_listesi[$irsaliye['TIP']]) ? $tip_listesi[$irsaliye['TIP']] : $irsaliye['TIP']; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($irsaliye['cari_adi']); ?></td>
                                                <td class="text-end"><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</td>
                                                <td>
                                                    <a href="irsaliye_detay.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-sm btn-info" title="Detay">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- Sayfalama -->
                            <?php if ($total_pages > 1): ?>
                            <nav aria-label="Sayfalama" class="mt-4">
                                <ul class="pagination justify-content-center">
                                    <?php if ($sayfa > 1): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=1<?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['sayfa' => ''])) : ''; ?>">
                                            <i class="fas fa-angle-double-left"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa - 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['sayfa' => ''])) : ''; ?>">
                                            <i class="fas fa-angle-left"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                    
                                    <?php 
                                    $start_page = max(1, $sayfa - 2);
                                    $end_page = min($total_pages, $sayfa + 2);
                                    
                                    for ($i = $start_page; $i <= $end_page; $i++): 
                                    ?>
                                    <li class="page-item <?php echo ($i == $sayfa) ? 'active' : ''; ?>">
                                        <a class="page-link" href="?sayfa=<?php echo $i; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['sayfa' => ''])) : ''; ?>">
                                            <?php echo $i; ?>
                                        </a>
                                    </li>
                                    <?php endfor; ?>
                                    
                                    <?php if ($sayfa < $total_pages): ?>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $sayfa + 1; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['sayfa' => ''])) : ''; ?>">
                                            <i class="fas fa-angle-right"></i>
                                        </a>
                                    </li>
                                    <li class="page-item">
                                        <a class="page-link" href="?sayfa=<?php echo $total_pages; ?><?php echo !empty($_GET) ? '&' . http_build_query(array_diff_key($_GET, ['sayfa' => ''])) : ''; ?>">
                                            <i class="fas fa-angle-double-right"></i>
                                        </a>
                                    </li>
                                    <?php endif; ?>
                                </ul>
                            </nav>
                            <?php endif; ?>
                        <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> 
                                Faturalanabilir irsaliye bulunamadı.
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <?php if (count($irsaliyeler) > 0): ?>
                    <div class="card mt-4">
                        <div class="card-header">
                            <i class="fas fa-file-invoice"></i> Fatura Bilgileri
                        </div>
                        <div class="card-body">
                            <div class="row g-3">
                                <div class="col-md-3">
                                    <label for="fatura_tarihi" class="form-label">Fatura Tarihi</label>
                                    <input type="date" class="form-control" id="fatura_tarihi" name="fatura_tarihi" value="<?php echo date('Y-m-d'); ?>" required>
                                </div>
                                <div class="col-md-3">
                                    <label for="fatura_tipi" class="form-label">Fatura Tipi</label>
                                    <select class="form-select" id="fatura_tipi" name="fatura_tipi" required>
                                        <option value="21" selected>Satış Faturası</option>
                                        <option value="22">Yurt Dışı Satış Faturası</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="odeme_turu" class="form-label">Ödeme Türü</label>
                                    <select class="form-select" id="odeme_turu" name="odeme_turu" required>
                                        <option value="Nakit" selected>Nakit</option>
                                        <option value="Havale">Havale</option>
                                        <option value="Kredi Kartı">Kredi Kartı</option>
                                        <option value="Çek">Çek</option>
                                        <option value="Senet">Senet</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="vade_tarihi" class="form-label">Vade Tarihi</label>
                                    <input type="date" class="form-control" id="vade_tarihi" name="vade_tarihi" value="<?php echo date('Y-m-d', strtotime('+30 days')); ?>">
                                </div>
                            </div>
                            <div class="row mt-3">
                                <div class="col-12 text-end">
                                    <button type="submit" name="faturala" class="btn btn-success" id="faturaButton" disabled>
                                        <i class="fas fa-file-invoice"></i> Faturala
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Tümünü seç/kaldır butonları
    document.getElementById('tumunuSec').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.irsaliye-check');
        checkboxes.forEach(checkbox => checkbox.checked = true);
        kontrolEt();
    });
    
    document.getElementById('tumunuKaldir').addEventListener('click', function() {
        const checkboxes = document.querySelectorAll('.irsaliye-check');
        checkboxes.forEach(checkbox => checkbox.checked = false);
        kontrolEt();
    });
    
    // Checkbox'lar değiştiğinde kontrol et
    document.querySelectorAll('.irsaliye-check').forEach(checkbox => {
        checkbox.addEventListener('change', function() {
            kontrolEt();
        });
    });
    
    // Seçilen irsaliyeleri kontrol et
    function kontrolEt() {
        const seciliIrsaliyeler = document.querySelectorAll('.irsaliye-check:checked');
        const faturaButton = document.getElementById('faturaButton');
        
        if (seciliIrsaliyeler.length > 0) {
            faturaButton.disabled = false;
            
            // Farklı carilere ait irsaliyeleri kontrol et
            let carileri = new Set();
            let tipler = new Set();
            
            seciliIrsaliyeler.forEach(irsaliye => {
                carileri.add(irsaliye.dataset.cari);
                tipler.add(irsaliye.dataset.tip);
            });
            
            if (carileri.size > 1) {
                alert('Uyarı: Farklı müşterilere ait irsaliyeleri seçtiniz. Aynı müşteriye ait irsaliyeleri seçmeniz önerilir.');
            }
            
            if (tipler.size > 1) {
                alert('Uyarı: Farklı tiplerdeki irsaliyeleri seçtiniz. Aynı tipteki irsaliyeleri seçmeniz önerilir.');
            }
        } else {
            faturaButton.disabled = true;
        }
    }
    
    // Form gönderilmeden önce kontrol
    document.getElementById('faturaForm').addEventListener('submit', function(e) {
        const seciliIrsaliyeler = document.querySelectorAll('.irsaliye-check:checked');
        
        if (seciliIrsaliyeler.length === 0) {
            e.preventDefault();
            alert('Lütfen en az bir irsaliye seçin.');
            return;
        }
        
        if (!confirm('Seçilen irsaliyeleri faturalandırmak istediğinize emin misiniz?')) {
            e.preventDefault();
        }
    });

    // Cari ID seçilince otomatik filtrele
    document.getElementById('cari_id').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });

    // İrsaliye tipi seçilince otomatik filtrele
    document.getElementById('irsaliye_tipi').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?> 