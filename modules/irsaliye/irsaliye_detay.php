<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// İrsaliye ID kontrolü
if (!isset($_GET['id'])) {
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

// İrsaliye bilgilerini getir
$query = "SELECT i.*, c.unvan as cari_unvan, c.adres, c.vergi_no, k.ad_soyad as kullanici_adi 
          FROM irsaliyeler i 
          LEFT JOIN cariler c ON i.cari_id = c.id 
          LEFT JOIN kullanicilar k ON i.olusturan_id = k.id 
          WHERE i.id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $irsaliye_id]);
$irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$irsaliye) {
    header('Location: irsaliye_listesi.php');
    exit;
}

// İrsaliye kalemlerini getir
$query = "SELECT ik.*, u.kod as urun_kodu, u.ad as urun_adi, u.birim 
          FROM irsaliye_kalemleri ik 
          LEFT JOIN urunler u ON ik.urun_id = u.id 
          WHERE ik.irsaliye_id = :id";
$stmt = $db->prepare($query);
$stmt->execute(['id' => $irsaliye_id]);
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
                        <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                        <a href="irsaliye_duzenle.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-warning me-2">
                            <i class="fas fa-edit"></i> Düzenle
                        </a>
                        <a href="irsaliye_onayla.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-success me-2">
                            <i class="fas fa-check"></i> Onayla
                        </a>
                        <a href="irsaliye_iptal.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-danger">
                            <i class="fas fa-times"></i> İptal Et
                        </a>
                        <?php endif; ?>
                        <a href="irsaliye_listesi.php" class="btn btn-secondary ms-2">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                    </div>
                </div>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">İrsaliye Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="150">İrsaliye No:</th>
                                        <td><?php echo htmlspecialchars($irsaliye['irsaliye_no']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Tarih:</th>
                                        <td><?php echo date('d.m.Y', strtotime($irsaliye['tarih'])); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Durum:</th>
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
                                    </tr>
                                    <tr>
                                        <th>Oluşturan:</th>
                                        <td><?php echo htmlspecialchars($irsaliye['kullanici_adi']); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5 class="card-title mb-0">Cari Bilgileri</h5>
                            </div>
                            <div class="card-body">
                                <table class="table table-borderless">
                                    <tr>
                                        <th width="150">Unvan:</th>
                                        <td><?php echo htmlspecialchars($irsaliye['cari_unvan']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Vergi No:</th>
                                        <td><?php echo htmlspecialchars($irsaliye['vergi_no']); ?></td>
                                    </tr>
                                    <tr>
                                        <th>Adres:</th>
                                        <td><?php echo nl2br(htmlspecialchars($irsaliye['adres'])); ?></td>
                                    </tr>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">İrsaliye Kalemleri</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table class="table table-bordered">
                                <thead>
                                    <tr>
                                        <th>Ürün Kodu</th>
                                        <th>Ürün Adı</th>
                                        <th>Miktar</th>
                                        <th>Birim</th>
                                        <th>Birim Fiyat</th>
                                        <th>Toplam Tutar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($kalemler as $kalem): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($kalem['urun_kodu']); ?></td>
                                        <td><?php echo htmlspecialchars($kalem['urun_adi']); ?></td>
                                        <td class="text-end"><?php echo number_format($kalem['miktar'], 2, ',', '.'); ?></td>
                                        <td><?php echo htmlspecialchars($kalem['birim']); ?></td>
                                        <td class="text-end"><?php echo number_format($kalem['birim_fiyat'], 2, ',', '.'); ?> ₺</td>
                                        <td class="text-end"><?php echo number_format($kalem['toplam_tutar'], 2, ',', '.'); ?> ₺</td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr>
                                        <td colspan="5" class="text-end"><strong>Genel Toplam:</strong></td>
                                        <td class="text-end"><strong><?php echo number_format($irsaliye['toplam_tutar'], 2, ',', '.'); ?> ₺</strong></td>
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