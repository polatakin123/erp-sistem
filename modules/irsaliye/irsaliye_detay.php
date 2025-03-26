<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

// İrsaliye bilgilerini getir
$query = "SELECT f.*, c.ADI as cari_unvan, c.ADRES as cari_adres, 
         d.ADI as depo_adi,
         CASE 
           WHEN f.IPTAL = 1 THEN 'İptal' 
           WHEN f.FATURALANDI = 1 THEN 'Faturalandı' 
           ELSE 'Beklemede' 
         END as durum
         FROM stk_fis f 
         LEFT JOIN cari c ON f.CARIID = c.ID 
         LEFT JOIN stk_depo d ON f.DEPOID = d.ID 
         WHERE f.ID = ? AND f.TIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE')";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$irsaliye) {
    header('Location: irsaliye_listesi.php');
    exit;
}

// İrsaliye kalemlerini getir
$query = "SELECT h.*, s.KOD as urun_kod, s.ADI as urun_adi, b.BIRIM_ADI as birim 
          FROM stk_fis_har h 
          LEFT JOIN stok s ON h.KARTID = s.ID
          LEFT JOIN stk_birim b ON s.BIRIMID = b.ID
          WHERE h.STKFISID = ? AND h.FISTIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE')
          ORDER BY h.SIRANO";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$kalemler = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İrsaliye Detay - ERP Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .badge-lg {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        .table-info th {
            background-color: #f8f9fa;
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
                    <h1 class="h2">İrsaliye Detay</h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="irsaliye_listesi.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Listeye Dön
                            </a>
                            <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                            <a href="irsaliye_duzenle.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-edit"></i> Düzenle
                            </a>
                            <?php endif; ?>
                            <a href="javascript:window.print();" class="btn btn-sm btn-outline-dark">
                                <i class="fas fa-print"></i> Yazdır
                            </a>
                        </div>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-8">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0">İrsaliye Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-4 mb-3">
                                        <strong>İrsaliye No:</strong> 
                                        <span class="badge bg-secondary"><?php echo htmlspecialchars($irsaliye['FISNO']); ?></span>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Tarih:</strong> 
                                        <?php echo date('d.m.Y', strtotime($irsaliye['FISTAR'])); ?>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Depo:</strong> 
                                        <?php echo htmlspecialchars($irsaliye['depo_adi']); ?>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-8 mb-3">
                                        <strong>Cari:</strong> 
                                        <?php echo htmlspecialchars($irsaliye['cari_unvan']); ?>
                                    </div>
                                    <div class="col-md-4 mb-3">
                                        <strong>Durum:</strong> 
                                        <?php
                                        $durum_class = 'warning';
                                        if ($irsaliye['durum'] == 'Faturalandı') $durum_class = 'success';
                                        if ($irsaliye['durum'] == 'İptal') $durum_class = 'danger';
                                        ?>
                                        <span class="badge bg-<?php echo $durum_class; ?> badge-lg">
                                            <?php echo htmlspecialchars($irsaliye['durum']); ?>
                                        </span>
                                    </div>
                                </div>
                                <?php if (!empty($irsaliye['cari_adres'])): ?>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <strong>Adres:</strong> 
                                        <?php echo nl2br(htmlspecialchars($irsaliye['cari_adres'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                                <?php if (!empty($irsaliye['NOTLAR'])): ?>
                                <div class="row">
                                    <div class="col-12 mb-3">
                                        <strong>Notlar:</strong> 
                                        <?php echo nl2br(htmlspecialchars($irsaliye['NOTLAR'])); ?>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-4">
                        <div class="card shadow-sm">
                            <div class="card-header">
                                <h5 class="mb-0">Özet Bilgiler</h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Toplam Tutar</h6>
                                            <strong><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</strong>
                                        </div>
                                    </div>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Toplam Kalem Sayısı</h6>
                                            <strong><?php echo count($kalemler); ?></strong>
                                        </div>
                                    </div>
                                    <div class="list-group-item list-group-item-action">
                                        <div class="d-flex w-100 justify-content-between">
                                            <h6 class="mb-1">Sevk Tarihi</h6>
                                            <strong>
                                                <?php echo $irsaliye['SEVKTAR'] ? date('d.m.Y', strtotime($irsaliye['SEVKTAR'])) : '-'; ?>
                                            </strong>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                        <div class="d-grid gap-2 mt-3">
                            <a href="irsaliye_onayla.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-success">
                                <i class="fas fa-check"></i> Onayla
                            </a>
                            <a href="irsaliye_iptal.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-danger">
                                <i class="fas fa-times"></i> İptal Et
                            </a>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card shadow-sm mb-4">
                    <div class="card-header">
                        <h5 class="mb-0">İrsaliye Kalemleri</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-striped table-bordered">
                                <thead>
                                    <tr>
                                        <th width="5%">Sıra</th>
                                        <th width="15%">Ürün Kodu</th>
                                        <th width="35%">Ürün Adı</th>
                                        <th width="10%">Miktar</th>
                                        <th width="10%">Birim</th>
                                        <th width="10%">Birim Fiyat</th>
                                        <th width="15%">Toplam Tutar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kalemler as $kalem): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kalem['SIRANO']); ?></td>
                                        <td><?php echo htmlspecialchars($kalem['urun_kod']); ?></td>
                                        <td><?php echo htmlspecialchars($kalem['urun_adi']); ?></td>
                                        <td class="text-end"><?php echo number_format($kalem['MIKTAR'], 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($kalem['birim']); ?></td>
                                        <td class="text-end"><?php echo number_format($kalem['FIYAT'], 2, ',', '.'); ?> ₺</td>
                                        <td class="text-end"><?php echo number_format($kalem['TUTAR'], 2, ',', '.'); ?> ₺</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <th colspan="6" class="text-end">Genel Toplam:</th>
                                        <th class="text-end"><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 