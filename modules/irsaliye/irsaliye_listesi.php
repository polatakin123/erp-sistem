<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Arama parametreleri
$tarih_baslangic = isset($_GET['tarih_baslangic']) ? $_GET['tarih_baslangic'] : date('Y-m-01');
$tarih_bitis = isset($_GET['tarih_bitis']) ? $_GET['tarih_bitis'] : date('Y-m-d');
$cari_id = isset($_GET['cari_id']) ? $_GET['cari_id'] : '';
$durum = isset($_GET['durum']) ? $_GET['durum'] : '';

// Cari listesi
$cari_listesi = [];
$cari_query = "SELECT ID, ADI FROM cari ORDER BY ADI";
$cari_stmt = $db->query($cari_query);
while ($row = $cari_stmt->fetch(PDO::FETCH_ASSOC)) {
    $cari_listesi[$row['ID']] = $row['ADI'];
}

// İrsaliye listesi sorgusu
$query = "SELECT f.ID, f.FISNO, f.FISTAR, f.CARIID, f.GENELTOPLAM, f.IPTAL, f.FATURALANDI,
          c.ADI as cari_adi,
          CASE 
            WHEN f.IPTAL = 1 THEN 'İptal' 
            WHEN f.FATURALANDI = 1 THEN 'Faturalandı' 
            ELSE 'Beklemede' 
          END as durum,
          f.TIP
          FROM stk_fis f
          LEFT JOIN cari c ON f.CARIID = c.ID
          WHERE f.TIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE', '20')";

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

$query .= " ORDER BY f.FISTAR DESC";

// Debug: Sorguyu ve parametreleri göster
echo '<div class="alert alert-info">';
echo '<strong>Debug - SQL Sorgusu:</strong> ' . $query . '<br>';
echo '<strong>Debug - Parametreler:</strong> ';
print_r($params);
echo '</div>';

// TIP değerlerini kontrol etmek için ayrı bir sorgu
$tip_query = "SELECT DISTINCT TIP FROM stk_fis";
$tip_stmt = $db->query($tip_query);
$tip_values = $tip_stmt->fetchAll(PDO::FETCH_COLUMN);

echo '<div class="alert alert-info">';
echo '<strong>Debug - stk_fis tablosundaki TIP değerleri:</strong> ';
print_r($tip_values);
echo '</div>';

$stmt = $db->prepare($query);
$stmt->execute($params);
$irsaliyeler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug: Kaç irsaliye bulunduğunu göster
echo '<div class="alert alert-info">';
echo '<strong>Debug - Bulunan irsaliye sayısı:</strong> ' . count($irsaliyeler);
echo '</div>';
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İrsaliye Listesi - ERP Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="https://cdn.datatables.net/1.13.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    <style>
        .btn-group-xs > .btn, .btn-xs {
            padding: .25rem .4rem;
            font-size: .875rem;
            line-height: .5;
            border-radius: .2rem;
        }
    </style>
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">İrsaliye Listesi</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <a href="irsaliye_ekle.php" class="btn btn-sm btn-outline-primary">
                            <i class="fas fa-plus"></i> Yeni İrsaliye
                        </a>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">Filtreleme</h5>
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

                <div class="card shadow-sm">
                    <div class="card-header">
                        <h5 class="mb-0">İrsaliyeler</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-hover" id="irsaliyeTable">
                                <thead>
                                    <tr>
                                        <th>İD</th>
                                        <th>İrsaliye No</th>
                                        <th>Tarih</th>
                                        <th>Cari</th>
                                        <th class="text-end">Tutar</th>
                                        <th>Durum</th>
                                        <th>İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($irsaliyeler as $irsaliye): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($irsaliye['ID']); ?></td>
                                        <td><?php echo htmlspecialchars($irsaliye['FISNO']); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($irsaliye['FISTAR'])); ?></td>
                                        <td><?php echo htmlspecialchars($irsaliye['cari_adi']); ?></td>
                                        <td class="text-end"><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</td>
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
                                            <div class="btn-group btn-group-xs">
                                                <a href="irsaliye_detay.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-info">
                                                    <i class="fas fa-eye"></i>
                                                </a>
                                                <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                                                <a href="irsaliye_duzenle.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-warning">
                                                    <i class="fas fa-edit"></i>
                                                </a>
                                                <a href="irsaliye_onayla.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-success" 
                                                   onclick="return confirm('Bu irsaliyeyi onaylamak istediğinize emin misiniz?')">
                                                    <i class="fas fa-check"></i>
                                                </a>
                                                <a href="irsaliye_iptal.php?id=<?php echo $irsaliye['ID']; ?>" class="btn btn-danger"
                                                   onclick="return confirm('Bu irsaliyeyi iptal etmek istediğinize emin misiniz?')">
                                                    <i class="fas fa-times"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        $(document).ready(function() {
            $('#irsaliyeTable').DataTable({
                "language": {
                    "url": "//cdn.datatables.net/plug-ins/1.13.5/i18n/tr.json"
                },
                "pageLength": 25,
                "order": []
            });
        });
    </script>
</body>
</html> 