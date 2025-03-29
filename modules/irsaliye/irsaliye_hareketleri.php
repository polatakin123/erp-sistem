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
$pageTitle = "İrsaliye Hareketleri";

// Filtreleme parametreleri
$baslangic_tarihi = isset($_GET['baslangic_tarihi']) ? $_GET['baslangic_tarihi'] : date('Y-m-01');
$bitis_tarihi = isset($_GET['bitis_tarihi']) ? $_GET['bitis_tarihi'] : date('Y-m-d');
$irsaliye_tipi = isset($_GET['irsaliye_tipi']) ? $_GET['irsaliye_tipi'] : '';
$cari_id = isset($_GET['cari_id']) ? $_GET['cari_id'] : '';
$durum = isset($_GET['durum']) ? $_GET['durum'] : '';

// Tip değerlerini tanımlayalım
$tip_listesi = [
    '11' => 'Alış İrsaliyesi',
    '12' => 'Yurt İçi Satış İrsaliyesi',
    '13' => 'Yurt Dışı Satış İrsaliyesi',
    '14' => 'Konsinye Giriş İrsaliyesi',
    '15' => 'Konsinye Çıkış İrsaliyesi'
];

// Durum değerlerini tanımlayalım
$durum_listesi = [
    '0' => 'İptal Edilmiş',
    '1' => 'Aktif',
    '2' => 'Faturalanmış'
];

// Cari listesini çekelim
$cari_query = "SELECT ID, ADI FROM cari ORDER BY ADI";
$cari_stmt = $db->query($cari_query);
$cari_listesi = $cari_stmt->fetchAll(PDO::FETCH_ASSOC);

// Sayfalama
$sayfa = isset($_GET['sayfa']) ? (int)$_GET['sayfa'] : 1;
$limit = 50;
$offset = ($sayfa - 1) * $limit;

// Toplam kayıt sayısını al
$count_query = "SELECT COUNT(*) as total FROM stk_fis f
                LEFT JOIN cari c ON f.CARIID = c.ID
                WHERE 1=1";

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

if (strlen($durum) > 0) {
    if ($durum == '0') {
        $count_query .= " AND f.IPTAL = 1";
    } else if ($durum == '1') {
        $count_query .= " AND f.IPTAL = 0 AND f.FATURALANDI = 0";
    } else if ($durum == '2') {
        $count_query .= " AND f.IPTAL = 0 AND f.FATURALANDI = 1";
    }
}

$count_stmt = $db->prepare($count_query);
$count_stmt->execute($count_params);
$total_records = $count_stmt->fetch(PDO::FETCH_ASSOC)['total'];

$total_pages = ceil($total_records / $limit);

// İrsaliye hareketlerini getir
$query = "SELECT 
            f.ID, 
            f.FISNO, 
            f.FISTAR, 
            f.TIP, 
            f.GENELTOPLAM, 
            f.IPTAL,
            f.FATURALANDI,
            c.ADI AS cari_adi,
            CASE 
                WHEN f.IPTAL = 1 THEN 'İptal Edilmiş' 
                WHEN f.FATURALANDI = 1 THEN 'Faturalanmış' 
                ELSE 'Aktif' 
            END as durum
          FROM 
            stk_fis f
          LEFT JOIN 
            cari c ON f.CARIID = c.ID
          WHERE 
            1=1";

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

if (strlen($durum) > 0) {
    if ($durum == '0') {
        $query .= " AND f.IPTAL = 1";
    } else if ($durum == '1') {
        $query .= " AND f.IPTAL = 0 AND f.FATURALANDI = 0";
    } else if ($durum == '2') {
        $query .= " AND f.IPTAL = 0 AND f.FATURALANDI = 1";
    }
}

// Sıralama
$query .= " ORDER BY f.FISTAR DESC";

// Sayfalama
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

            <!-- Filtreleme Bölümü -->
            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-filter"></i> Filtreleme
                </div>
                <div class="card-body">
                    <form method="get" id="filterForm">
                        <div class="row g-3">
                            <div class="col-md-4 col-lg-2">
                                <label for="baslangic_tarihi" class="form-label">Başlangıç Tarihi</label>
                                <input type="date" class="form-control" id="baslangic_tarihi" name="baslangic_tarihi" value="<?php echo $baslangic_tarihi; ?>">
                            </div>
                            <div class="col-md-4 col-lg-2">
                                <label for="bitis_tarihi" class="form-label">Bitiş Tarihi</label>
                                <input type="date" class="form-control" id="bitis_tarihi" name="bitis_tarihi" value="<?php echo $bitis_tarihi; ?>">
                            </div>
                            <div class="col-md-4 col-lg-2">
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
                            <div class="col-md-4 col-lg-2">
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
                            <div class="col-md-4 col-lg-2">
                                <label for="durum" class="form-label">Durum</label>
                                <select class="form-select" id="durum" name="durum">
                                    <option value="">Tümü</option>
                                    <?php foreach ($durum_listesi as $durum_kodu => $durum_adi): ?>
                                        <option value="<?php echo $durum_kodu; ?>" <?php echo ($durum == (string)$durum_kodu) ? 'selected' : ''; ?>>
                                            <?php echo $durum_adi; ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-4 col-lg-2 d-flex align-items-end">
                                <div class="d-grid gap-2 w-100">
                                    <button type="submit" class="btn btn-primary">
                                        <i class="fas fa-search"></i> Filtrele
                                    </button>
                                </div>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- İrsaliye Hareketleri Listesi -->
            <div class="card">
                <div class="card-header">
                    <i class="fas fa-list"></i> İrsaliye Hareketleri (Toplam: <?php echo $total_records; ?>)
                </div>
                <div class="card-body">
                    <?php if (count($irsaliyeler) > 0): ?>
                        <div class="table-responsive">
                            <table class="table table-striped table-hover table-sm">
                                <thead>
                                    <tr>
                                        <th>İrsaliye No</th>
                                        <th>Tarih</th>
                                        <th>Tip</th>
                                        <th>Cari</th>
                                        <th class="text-end">Tutar</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach($irsaliyeler as $irsaliye): ?>
                                        <tr class="<?php echo $irsaliye['IPTAL'] == 1 ? 'table-danger' : ($irsaliye['FATURALANDI'] == 1 ? 'table-warning' : ''); ?>">
                                            <td><?php echo htmlspecialchars($irsaliye['FISNO']); ?></td>
                                            <td><?php echo date('d.m.Y', strtotime($irsaliye['FISTAR'])); ?></td>
                                            <td>
                                                <?php echo isset($tip_listesi[$irsaliye['TIP']]) ? $tip_listesi[$irsaliye['TIP']] : $irsaliye['TIP']; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($irsaliye['cari_adi']); ?></td>
                                            <td class="text-end"><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</td>
                                            <td>
                                                <?php if ($irsaliye['IPTAL'] == 1): ?>
                                                    <span class="badge bg-danger">İptal Edilmiş</span>
                                                <?php elseif ($irsaliye['FATURALANDI'] == 1): ?>
                                                    <span class="badge bg-warning text-dark">Faturalanmış</span>
                                                <?php else: ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <div class="btn-group">
                                                    <a href="irsaliye_detay.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-sm btn-info" title="Detay">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                    <?php if ($irsaliye['IPTAL'] == 0 && $irsaliye['FATURALANDI'] == 0): ?>
                                                    <a href="irsaliye_duzenle.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-sm btn-primary" title="Düzenle">
                                                        <i class="fas fa-edit"></i>
                                                    </a>
                                                    <?php endif; ?>
                                                    <a href="irsaliye_yazdir.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-sm btn-secondary" title="Yazdır">
                                                        <i class="fas fa-print"></i>
                                                    </a>
                                                </div>
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
                            Filtreleme kriterlerine uygun irsaliye bulunamadı.
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Cari ID seçilince otomatik filtrele
    document.getElementById('cari_id').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });

    // İrsaliye tipi seçilince otomatik filtrele
    document.getElementById('irsaliye_tipi').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });

    // Durum seçilince otomatik filtrele
    document.getElementById('durum').addEventListener('change', function() {
        document.getElementById('filterForm').submit();
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?> 