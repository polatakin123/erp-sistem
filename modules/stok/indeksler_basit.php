<?php
/**
 * ERP Sistem - Stok Modülü Veritabanı Optimizasyonu (Basit)
 * 
 * Bu dosya yetki kontrolü olmadan doğrudan veritabanında indeksler oluşturur.
 */

require_once '../../config/db.php';

// Hata mesajlarını görüntüle
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Zaman aşımı süresini uzat
ini_set('max_execution_time', 300);
set_time_limit(300);

// İndeksleri adım adım oluşturacak bir mekanizma
$adim = isset($_GET['adim']) ? (int)$_GET['adim'] : 0;
$tamamlandi = false;
$hata = false;
$mesaj = '';

// Adımları tanımla
$adimlar = [
    1 => ['islem' => 'CREATE INDEX idx_stok_kod ON stok (KOD)', 'aciklama' => 'Stok Kodu İndeksi Oluşturuluyor'],
    2 => ['islem' => 'CREATE INDEX idx_stok_adi ON stok (ADI)', 'aciklama' => 'Stok Adı İndeksi Oluşturuluyor'],
    3 => ['islem' => 'CREATE INDEX idx_stk_urun_miktar ON STK_URUN_MIKTAR (URUN_ID, MIKTAR)', 'aciklama' => 'Ürün Miktar İndeksi Oluşturuluyor'],
    4 => ['islem' => 'CREATE INDEX idx_stk_fiyat ON stk_fiyat (STOKID, TIP)', 'aciklama' => 'Fiyat İndeksi Oluşturuluyor'],
    5 => ['islem' => 'ANALYZE TABLE stok', 'aciklama' => 'Stok Tablosu Analiz Ediliyor'],
    6 => ['islem' => 'ANALYZE TABLE STK_URUN_MIKTAR', 'aciklama' => 'Ürün Miktar Tablosu Analiz Ediliyor'],
    7 => ['islem' => 'ANALYZE TABLE stk_fiyat', 'aciklama' => 'Fiyat Tablosu Analiz Ediliyor'],
];

// İşlemi yürüt
if ($adim > 0 && $adim <= count($adimlar)) {
    try {
        // SQL çalıştır
        $stmt = $db->prepare($adimlar[$adim]['islem']);
        $stmt->execute();
        $mesaj = $adimlar[$adim]['aciklama'] . ' - <span class="text-success">✓ Başarılı</span>';
        
        // Son adım mı?
        if ($adim == count($adimlar)) {
            $tamamlandi = true;
        }
    } catch (PDOException $e) {
        // Eğer indeks zaten varsa hata değil
        if (strpos($e->getMessage(), 'Duplicate key name') !== false || 
            strpos($e->getMessage(), 'already exists') !== false) {
            $mesaj = $adimlar[$adim]['aciklama'] . ' - <span class="text-warning">İndeks zaten var</span>';
        } else {
            $hata = true;
            $mesaj = $adimlar[$adim]['aciklama'] . ' - <span class="text-danger">Hata: ' . $e->getMessage() . '</span>';
        }
    }
}

// HTML başlık
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Modülü İndeks Oluşturma (Basit)</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .text-success { color: green; }
        .text-danger { color: red; }
        .text-warning { color: orange; }
        .progress { height: 25px; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stok Modülü İndeks Oluşturma</h1>
        
        <div class="alert alert-info">
            Bu sayfa veritabanı indekslerini adım adım oluşturarak sistem performansını artırır.
        </div>
        
        <?php if ($tamamlandi): ?>
            <div class="alert alert-success">
                <h4><i class="fas fa-check-circle"></i> Tüm indeksler başarıyla oluşturuldu!</h4>
                <p>Stok modülü artık daha hızlı çalışacaktır.</p>
            </div>
        <?php else: ?>
            <!-- İlerleme Çubuğu -->
            <div class="card mb-4">
                <div class="card-header">
                    <h5>İlerleme Durumu</h5>
                </div>
                <div class="card-body">
                    <div class="progress mb-3">
                        <div class="progress-bar" role="progressbar" style="width: <?php echo ($adim / count($adimlar)) * 100; ?>%;" 
                             aria-valuenow="<?php echo $adim; ?>" aria-valuemin="0" aria-valuemax="<?php echo count($adimlar); ?>">
                            <?php echo $adim; ?>/<?php echo count($adimlar); ?>
                        </div>
                    </div>
                    
                    <?php if (!empty($mesaj)): ?>
                        <div class="alert <?php echo $hata ? 'alert-danger' : 'alert-info'; ?>">
                            <?php echo $mesaj; ?>
                        </div>
                    <?php endif; ?>
                    
                    <?php if ($adim < count($adimlar)): ?>
                        <div class="d-grid gap-2">
                            <a href="?adim=<?php echo $adim + 1; ?>" class="btn btn-primary">
                                <?php echo $adim == 0 ? 'Başlat' : 'Sonraki Adım'; ?>
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>
        
        <!-- İndeks Bilgileri -->
        <div class="card">
            <div class="card-header">
                <h5>Oluşturulacak İndeksler</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered">
                        <thead>
                            <tr>
                                <th>#</th>
                                <th>İşlem</th>
                                <th>Açıklama</th>
                                <th>Durum</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($adimlar as $i => $adimBilgi): ?>
                                <tr class="<?php echo $i < $adim ? 'table-success' : ''; ?>">
                                    <td><?php echo $i; ?></td>
                                    <td><code><?php echo htmlspecialchars($adimBilgi['islem']); ?></code></td>
                                    <td><?php echo $adimBilgi['aciklama']; ?></td>
                                    <td>
                                        <?php if ($i < $adim): ?>
                                            <span class="badge bg-success">Tamamlandı</span>
                                        <?php elseif ($i == $adim && !empty($mesaj)): ?>
                                            <span class="badge <?php echo $hata ? 'bg-danger' : 'bg-info'; ?>">
                                                <?php echo $hata ? 'Hata' : 'İşleniyor'; ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">Bekliyor</span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="urun_arama.php" class="btn btn-primary">Ürün Arama Sayfasına Dön</a>
        </div>
    </div>
</body>
</html> 