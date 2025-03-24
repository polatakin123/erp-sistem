<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// İrsaliyeleri getir
$query = "SELECT i.*, c.unvan as cari_unvan, k.ad_soyad as kullanici_adi 
          FROM irsaliyeler i 
          LEFT JOIN cariler c ON i.cari_id = c.id 
          LEFT JOIN kullanicilar k ON i.olusturan_id = k.id 
          ORDER BY i.tarih DESC";
$stmt = $db->query($query);
$irsaliyeler = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İrsaliye Listesi - ERP Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
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
                        <a href="irsaliye_ekle.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Yeni İrsaliye
                        </a>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-striped table-hover">
                        <thead>
                            <tr>
                                <th>İrsaliye No</th>
                                <th>Tarih</th>
                                <th>Cari</th>
                                <th>Toplam Tutar</th>
                                <th>Durum</th>
                                <th>Oluşturan</th>
                                <th>İşlemler</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($irsaliyeler as $irsaliye): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($irsaliye['irsaliye_no']); ?></td>
                                <td><?php echo date('d.m.Y', strtotime($irsaliye['tarih'])); ?></td>
                                <td><?php echo htmlspecialchars($irsaliye['cari_unvan']); ?></td>
                                <td><?php echo number_format($irsaliye['toplam_tutar'], 2, ',', '.'); ?> ₺</td>
                                <td>
                                    <?php
                                    $durum_class = '';
                                    switch($irsaliye['durum']) {
                                        case 'Beklemede':
                                            $durum_class = 'warning';
                                            break;
                                        case 'Onaylandı':
                                            $durum_class = 'success';
                                            break;
                                        case 'İptal':
                                            $durum_class = 'danger';
                                            break;
                                    }
                                    ?>
                                    <span class="badge bg-<?php echo $durum_class; ?>">
                                        <?php echo htmlspecialchars($irsaliye['durum']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($irsaliye['kullanici_adi']); ?></td>
                                <td>
                                    <a href="irsaliye_detay.php?id=<?php echo $irsaliye['id']; ?>" class="btn btn-sm btn-info">
                                        <i class="fas fa-eye"></i>
                                    </a>
                                    <a href="irsaliye_duzenle.php?id=<?php echo $irsaliye['id']; ?>" class="btn btn-sm btn-warning">
                                        <i class="fas fa-edit"></i>
                                    </a>
                                    <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                                    <a href="irsaliye_onayla.php?id=<?php echo $irsaliye['id']; ?>" class="btn btn-sm btn-success">
                                        <i class="fas fa-check"></i>
                                    </a>
                                    <a href="irsaliye_iptal.php?id=<?php echo $irsaliye['id']; ?>" class="btn btn-sm btn-danger">
                                        <i class="fas fa-times"></i>
                                    </a>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </main>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 