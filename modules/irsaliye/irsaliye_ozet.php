<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Tarih filtresi
$tarih_baslangic = isset($_GET['tarih_baslangic']) ? $_GET['tarih_baslangic'] : date('Y-m-01');
$tarih_bitis = isset($_GET['tarih_bitis']) ? $_GET['tarih_bitis'] : date('Y-m-d');

// Tip açıklamaları
$tip_aciklamalari = [
    '11' => 'Alış İrsaliyesi',
    '12' => 'Yurt İçi Satış İrsaliyesi',
    '13' => 'Yurt Dışı Satış İrsaliyesi',
    '14' => 'Konsinye Giriş İrsaliyesi',
    '15' => 'Konsinye Çıkış İrsaliyesi',
    '20' => 'İrsaliye'
];

// Özet istatistikler
$ozet_query = "SELECT 
                TIP,
                COUNT(*) as irsaliye_sayisi,
                SUM(GENELTOPLAM) as toplam_tutar,
                SUM(CASE WHEN IPTAL = 0 AND FATURALANDI = 0 THEN 1 ELSE 0 END) as bekleyen_sayisi,
                SUM(CASE WHEN IPTAL = 0 AND FATURALANDI = 1 THEN 1 ELSE 0 END) as faturalanan_sayisi,
                SUM(CASE WHEN IPTAL = 1 THEN 1 ELSE 0 END) as iptal_sayisi
               FROM stk_fis
               WHERE FISTAR BETWEEN ? AND ?
               GROUP BY TIP
               ORDER BY TIP";

$stmt = $db->prepare($ozet_query);
$stmt->execute([$tarih_baslangic, $tarih_bitis]);
$ozet_istatistikler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ürün hareket analizi - En çok ve en az hareket gören ürünler
$urun_query = "SELECT 
                s.ID as stok_id,
                s.KOD as stok_kodu,
                s.ADI as stok_adi,
                COUNT(h.ID) as hareket_sayisi,
                SUM(h.MIKTAR) as toplam_miktar,
                SUM(h.TUTAR) as toplam_tutar,
                (SELECT g.KOD FROM stok_birim sb 
                 LEFT JOIN grup g ON sb.BIRIMID = g.ID 
                 WHERE sb.STOKID = s.ID LIMIT 1) as birim
               FROM stok s
               INNER JOIN stk_fis_har h ON s.ID = h.KARTID
               INNER JOIN stk_fis f ON h.STKFISID = f.ID
               WHERE f.FISTAR BETWEEN ? AND ? AND f.IPTAL = 0
               GROUP BY s.ID, s.KOD, s.ADI
               ORDER BY toplam_miktar DESC
               LIMIT 10";

$stmt = $db->prepare($urun_query);
$stmt->execute([$tarih_baslangic, $tarih_bitis]);
$en_cok_hareket = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Cari hareket analizi - En çok irsaliye oluşturulan cariler
$cari_query = "SELECT 
                c.ID as cari_id,
                c.ADI as cari_adi,
                COUNT(f.ID) as irsaliye_sayisi,
                SUM(f.GENELTOPLAM) as toplam_tutar,
                MAX(f.FISTAR) as son_irsaliye_tarihi
              FROM cari c
              INNER JOIN stk_fis f ON c.ID = f.CARIID
              WHERE f.FISTAR BETWEEN ? AND ? AND f.IPTAL = 0
              GROUP BY c.ID, c.ADI
              ORDER BY irsaliye_sayisi DESC
              LIMIT 10";

$stmt = $db->prepare($cari_query);
$stmt->execute([$tarih_baslangic, $tarih_bitis]);
$en_cok_islem = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Sayfa başlığı
$pageTitle = "İrsaliye Analiz ve Özet";
include_once '../../includes/header.php';
?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="irsaliye_listesi.php" class="btn btn-sm btn-outline-secondary me-2">
                        <i class="fas fa-list"></i> İrsaliye Listesi
                    </a>
                    <a href="irsaliye_ekle.php" class="btn btn-sm btn-primary">
                        <i class="fas fa-plus"></i> Yeni İrsaliye
                    </a>
                </div>
            </div>

            <!-- Tarih Filtresi -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-filter"></i> Tarih Filtresi</h5>
                </div>
                <div class="card-body">
                    <form method="get" class="row g-3">
                        <div class="col-md-4">
                            <label for="tarih_baslangic" class="form-label">Başlangıç Tarihi</label>
                            <input type="date" class="form-control" id="tarih_baslangic" name="tarih_baslangic" value="<?php echo $tarih_baslangic; ?>">
                        </div>
                        <div class="col-md-4">
                            <label for="tarih_bitis" class="form-label">Bitiş Tarihi</label>
                            <input type="date" class="form-control" id="tarih_bitis" name="tarih_bitis" value="<?php echo $tarih_bitis; ?>">
                        </div>
                        <div class="col-md-4 d-flex align-items-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filtrele
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- İstatistik Kartları -->
            <div class="row mb-4">
                <?php 
                $toplam_irsaliye = 0;
                $toplam_tutar = 0;
                $bekleyen_sayisi = 0;
                $faturalanan_sayisi = 0;
                $iptal_sayisi = 0;
                
                foreach ($ozet_istatistikler as $ozet) {
                    $toplam_irsaliye += $ozet['irsaliye_sayisi'];
                    $toplam_tutar += $ozet['toplam_tutar'];
                    $bekleyen_sayisi += $ozet['bekleyen_sayisi'];
                    $faturalanan_sayisi += $ozet['faturalanan_sayisi'];
                    $iptal_sayisi += $ozet['iptal_sayisi'];
                }
                ?>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 border-primary">
                        <div class="card-body">
                            <h5 class="card-title text-primary mb-2">
                                <i class="fas fa-file-alt"></i> Toplam İrsaliye
                            </h5>
                            <h2 class="card-text fs-1"><?php echo number_format($toplam_irsaliye, 0, ',', '.'); ?></h2>
                            <div class="text-muted small">
                                <span class="badge bg-success"><?php echo $bekleyen_sayisi; ?> Beklemede</span>
                                <span class="badge bg-warning"><?php echo $faturalanan_sayisi; ?> Faturalanmış</span>
                                <span class="badge bg-danger"><?php echo $iptal_sayisi; ?> İptal</span>
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 border-success">
                        <div class="card-body">
                            <h5 class="card-title text-success mb-2">
                                <i class="fas fa-money-bill-wave"></i> Toplam Tutar
                            </h5>
                            <h2 class="card-text fs-1"><?php echo number_format($toplam_tutar, 2, ',', '.'); ?> ₺</h2>
                            <div class="text-muted small">
                                <?php echo date('d.m.Y', strtotime($tarih_baslangic)); ?> - <?php echo date('d.m.Y', strtotime($tarih_bitis)); ?> arası
                            </div>
                        </div>
                    </div>
                </div>
                <div class="col-md-4 mb-3">
                    <div class="card h-100 border-info">
                        <div class="card-body">
                            <h5 class="card-title text-info mb-2">
                                <i class="fas fa-chart-bar"></i> Ortalama İrsaliye Değeri
                            </h5>
                            <h2 class="card-text fs-1">
                                <?php echo $toplam_irsaliye > 0 ? number_format($toplam_tutar / $toplam_irsaliye, 2, ',', '.') : 0; ?> ₺
                            </h2>
                            <div class="text-muted small">
                                İrsaliye başına ortalama tutar
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İrsaliye Tipleri -->
            <div class="card mb-4">
                <div class="card-header bg-light">
                    <h5 class="mb-0"><i class="fas fa-chart-pie"></i> İrsaliye Tipleri</h5>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th>İrsaliye Tipi</th>
                                    <th class="text-center">İrsaliye Sayısı</th>
                                    <th class="text-end">Toplam Tutar</th>
                                    <th class="text-center">Bekleyen</th>
                                    <th class="text-center">Faturalanmış</th>
                                    <th class="text-center">İptal</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach($ozet_istatistikler as $ozet): ?>
                                <tr>
                                    <td>
                                        <?php 
                                        echo isset($tip_aciklamalari[$ozet['TIP']]) 
                                            ? $tip_aciklamalari[$ozet['TIP']] 
                                            : ($ozet['TIP'] ?? 'Belirsiz'); 
                                        ?>
                                    </td>
                                    <td class="text-center fw-bold"><?php echo $ozet['irsaliye_sayisi']; ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($ozet['toplam_tutar'], 2, ',', '.'); ?> ₺</td>
                                    <td class="text-center">
                                        <span class="badge bg-success"><?php echo $ozet['bekleyen_sayisi']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-warning"><?php echo $ozet['faturalanan_sayisi']; ?></span>
                                    </td>
                                    <td class="text-center">
                                        <span class="badge bg-danger"><?php echo $ozet['iptal_sayisi']; ?></span>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- En Çok Hareket Gören Ürünler -->
            <div class="row mb-4">
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-sort-amount-up"></i> En Çok Hareket Gören Ürünler</h5>
                        </div>
                        <div class="card-body">
                            <?php if(count($en_cok_hareket) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Ürün Kodu</th>
                                            <th>Ürün Adı</th>
                                            <th class="text-end">Toplam Miktar</th>
                                            <th>Birim</th>
                                            <th class="text-end">Toplam Tutar</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($en_cok_hareket as $urun): ?>
                                        <tr>
                                            <td><?php echo $urun['stok_kodu']; ?></td>
                                            <td><?php echo $urun['stok_adi']; ?></td>
                                            <td class="text-end fw-bold"><?php echo number_format($urun['toplam_miktar'], 2, ',', '.'); ?></td>
                                            <td><?php echo $urun['birim']; ?></td>
                                            <td class="text-end"><?php echo number_format($urun['toplam_tutar'], 2, ',', '.'); ?> ₺</td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Bu tarih aralığında ürün hareketi bulunamadı.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="card h-100">
                        <div class="card-header bg-light">
                            <h5 class="mb-0"><i class="fas fa-user-friends"></i> En Çok İşlem Yapılan Cariler</h5>
                        </div>
                        <div class="card-body">
                            <?php if(count($en_cok_islem) > 0): ?>
                            <div class="table-responsive">
                                <table class="table table-striped table-sm table-hover">
                                    <thead>
                                        <tr>
                                            <th>Cari Adı</th>
                                            <th class="text-center">İrsaliye Sayısı</th>
                                            <th class="text-end">Toplam Tutar</th>
                                            <th>Son İşlem</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach($en_cok_islem as $cari): ?>
                                        <tr>
                                            <td><?php echo $cari['cari_adi']; ?></td>
                                            <td class="text-center fw-bold"><?php echo $cari['irsaliye_sayisi']; ?></td>
                                            <td class="text-end"><?php echo number_format($cari['toplam_tutar'], 2, ',', '.'); ?> ₺</td>
                                            <td><?php echo date('d.m.Y', strtotime($cari['son_irsaliye_tarihi'])); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            <?php else: ?>
                            <div class="alert alert-info">
                                <i class="fas fa-info-circle"></i> Bu tarih aralığında cari hareketi bulunamadı.
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

<?php include_once '../../includes/footer.php'; ?> 