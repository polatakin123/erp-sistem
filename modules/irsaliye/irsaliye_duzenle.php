<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Performans ölçümü
$performans_olcum = [];
$toplam_sure = microtime(true);

function olcumBaslat($islem_adi) {
    global $performans_olcum;
    $performans_olcum[$islem_adi] = ['baslangic' => microtime(true), 'bitis' => 0, 'sure' => 0];
}

function olcumBitir($islem_adi) {
    global $performans_olcum;
    if (isset($performans_olcum[$islem_adi])) {
        $performans_olcum[$islem_adi]['bitis'] = microtime(true);
        $performans_olcum[$islem_adi]['sure'] = $performans_olcum[$islem_adi]['bitis'] - $performans_olcum[$islem_adi]['baslangic'];
    }
}

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// İrsaliye ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

// İrsaliye bilgilerini getir
olcumBaslat('irsaliye_bilgileri');
$query = "SELECT f.* FROM stk_fis f 
          WHERE f.ID = ? AND f.TIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE', '20')
          AND f.IPTAL = 0 AND f.FATURALANDI = 0";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);
olcumBitir('irsaliye_bilgileri');

if (!$irsaliye) {
    header('Location: irsaliye_listesi.php');
    exit;
}

// Carileri getir
olcumBaslat('cariler');
$query = "SELECT ID, ADI as unvan FROM cari ORDER BY ADI";
$stmt = $db->query($query);
$cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);
olcumBitir('cariler');

// Stok ürünlerini getir - SİLİNDİ
olcumBaslat('stok_urunleri');
// Sayfa ilk açılışta hiç ürün getirmiyoruz
// Ürünler arama fonksiyonu ile lazy loading olarak getirilecek
$urunler = [];
olcumBitir('stok_urunleri');

// Birim bilgilerini getBirim fonksiyonu ile ekleyelim - SİLİNDİ
olcumBaslat('birim_bilgisi');
// Ürün olmadığı için bilgi ekleme işlemi yok
olcumBitir('birim_bilgisi');

// Depo değerleri için varsayılan bir değer kullanalım
$depolar = [
    ['ID' => 1, 'ADI' => 'Varsayılan Depo']
];

// İrsaliye kalemlerini getir
olcumBaslat('irsaliye_kalemleri');
$query = "SELECT h.*, s.KOD as urun_kod, s.ADI as urun_adi 
          FROM stk_fis_har h 
          LEFT JOIN stok s ON h.KARTID = s.ID
          WHERE h.STKFISID = ? AND h.FISTIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE', '20')
          ORDER BY h.SIRANO";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$kalemler = $stmt->fetchAll(PDO::FETCH_ASSOC);
olcumBitir('irsaliye_kalemleri');

// Birim bilgilerini getBirim fonksiyonu ile ekleyelim
olcumBaslat('kalem_birim_bilgisi');
foreach ($kalemler as &$kalem) {
    $kalem['birim'] = getBirim($kalem['BIRIMID']);
}
olcumBitir('kalem_birim_bilgisi');

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        olcumBaslat('kaydetme_islemi');
        $db->beginTransaction();

        // Cari adını alalım
        olcumBaslat('cari_adi_getir');
        $stmt_cari = $db->prepare("SELECT ADI FROM cari WHERE ID = ?");
        $stmt_cari->execute([$_POST['cari_id']]);
        $cari_adi = $stmt_cari->fetchColumn();
        olcumBitir('cari_adi_getir');

        // İrsaliye başlık bilgilerini güncelle
        olcumBaslat('irsaliye_guncelle');
        $query = "UPDATE stk_fis SET 
                  FISTAR = ?, 
                  CARIID = ?, 
                  DEPOID = ?,
                  CARIADI = ?,
                  GENELTOPLAM = ?,
                  STOKTOPLAM = ?,
                  NOTLAR = ?
                  WHERE ID = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([
            $_POST['tarih'],
            $_POST['cari_id'],
            $_POST['depo_id'],
            $cari_adi,
            $_POST['toplam_tutar'],
            $_POST['toplam_tutar'],
            $_POST['notlar'] ?? '',
            $irsaliye_id
        ]);
        olcumBitir('irsaliye_guncelle');

        // Mevcut kalemleri sil
        olcumBaslat('mevcut_kalemler_sil');
        $query = "DELETE FROM stk_fis_har WHERE STKFISID = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$irsaliye_id]);
        olcumBitir('mevcut_kalemler_sil');

        // Yeni kalemleri ekle
        olcumBaslat('yeni_kalemler_ekle');
        $query = "INSERT INTO stk_fis_har (
                    SIRANO, BOLUMID, FISTIP, STKFISID, FISTAR, ISLEMTIPI,
                    KARTTIPI, KARTID, MIKTAR, BIRIMID, FIYAT, TUTAR,
                    KDVORANI, KDVTUTARI, CARIID, DEPOID, SUBEID
                ) VALUES (
                    ?, 1, '20', ?, ?, 'Çıkış',
                    'S', ?, ?, ?, ?, ?,
                    0, 0, ?, ?, 1
                )";
        $stmt = $db->prepare($query);

        foreach ($_POST['urun_id'] as $key => $urun_id) {
            if (!empty($urun_id)) {
                // Ürün birim ID'sini alalım
                $stmt_birim = $db->prepare("SELECT ID FROM stk_birim WHERE STOKID = ? LIMIT 1");
                $stmt_birim->execute([$urun_id]);
                $birim_id = $stmt_birim->fetchColumn();

                // Sıra numarası
                $sirano = $key + 1;

                $stmt->execute([
                    $sirano,
                    $irsaliye_id,
                    $_POST['tarih'],
                    $urun_id,
                    $_POST['miktar'][$key],
                    $birim_id,
                    $_POST['birim_fiyat'][$key],
                    $_POST['kalem_toplam'][$key],
                    $_POST['cari_id'],
                    $_POST['depo_id']
                ]);
            }
        }
        olcumBitir('yeni_kalemler_ekle');

        $db->commit();
        olcumBitir('kaydetme_islemi');
        header('Location: irsaliye_detay.php?id=' . $irsaliye_id);
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Hata oluştu: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İrsaliye Düzenle - ERP Sistemi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body, html {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
        }
        
        main {
            margin-top: 110px; /* Navbar + Arama formu yüksekliği */
            padding: 15px;
        }
        
        .table {
            font-size: 0.9rem;
        }
        
        .urun-bilgi {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 10px;
            margin-bottom: 15px;
        }
        
        .kalemler-tablosu {
            border: 1px solid #dee2e6;
        }
        
        .kalemler-tablosu th {
            background-color: #f8f9fa;
            font-weight: 500;
        }
        
        .toplamlar {
            background-color: #f8f9fa;
            border-radius: 5px;
            padding: 15px;
            margin-top: 20px;
        }
        
        .form-group {
            margin-bottom: 10px;
        }
        
        label {
            font-weight: 500;
            margin-bottom: 3px;
        }
        
        .form-control-sm {
            height: calc(1.5em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        
        /* Responsive düzenlemeler */
        @media (max-width: 768px) {
            .btn-group-sm > .btn, .btn-sm {
                padding: 0.2rem 0.4rem;
                font-size: 0.7rem;
            }
            
            .table th, .table td {
                padding: 0.3rem;
            }
        }
    </style>
</head>
<body>
    <!-- Sabit Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="../../index.php">ERP Sistemi</a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav" aria-controls="navbarNav" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="../../index.php">
                            <i class="fas fa-home"></i> Ana Sayfa
                        </a>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="stokDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-boxes"></i> Stok İşlemleri
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="stokDropdown">
                            <li><a class="dropdown-item" href="../stok/stok_listesi.php">Stok Listesi</a></li>
                            <li><a class="dropdown-item" href="../stok/stok_ekle.php">Stok Ekle</a></li>
                            <li><a class="dropdown-item" href="../stok/stok_hareket.php">Stok Hareket</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle active" href="#" id="irsaliyeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-invoice"></i> İrsaliye İşlemleri
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="irsaliyeDropdown">
                            <li><a class="dropdown-item" href="irsaliye_listesi.php">İrsaliye Listesi</a></li>
                            <li><a class="dropdown-item" href="irsaliye_ekle.php">İrsaliye Ekle</a></li>
                        </ul>
                    </li>
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="cariDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-address-book"></i> Cari İşlemleri
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="cariDropdown">
                            <li><a class="dropdown-item" href="../cari/cari_listesi.php">Cari Listesi</a></li>
                            <li><a class="dropdown-item" href="../cari/cari_ekle.php">Cari Ekle</a></li>
                        </ul>
                    </li>
                </ul>
                
                <div class="d-flex">
                    <div class="btn-group">
                        <a href="irsaliye_detay.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left"></i> Geri
                        </a>
                        <button type="submit" form="irsaliyeForm" class="btn btn-primary">
                            <i class="fas fa-save"></i> Kaydet
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid layout-fullwidth">
        <main>
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-2 border-bottom">
                <h1 class="h2">İrsaliye Düzenle</h1>
            </div>

            <?php if (isset($error)): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
            <?php endif; ?>

            <?php
            // Toplam süre hesaplama
            $toplam_sure_sonu = microtime(true);
            $toplam_gecen_sure = $toplam_sure_sonu - $toplam_sure;
            
            // Performans sonuçlarını göster
            ?>
            <div class="card mb-2">
                <div class="card-header bg-info text-white py-1">
                    <h5 class="mb-0">Performans Analizi (Toplam süre: <?php echo number_format($toplam_gecen_sure, 4); ?> sn)</h5>
                </div>
                <div class="card-body p-1">
                    <div class="table-responsive">
                        <table class="table table-sm table-striped">
                            <thead>
                                <tr>
                                    <th>İşlem</th>
                                    <th>Süre (sn)</th>
                                    <th>Yüzde (%)</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($performans_olcum as $islem => $olcum): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($islem); ?></td>
                                    <td><?php echo number_format($olcum['sure'], 4); ?></td>
                                    <td><?php echo number_format(($olcum['sure'] / $toplam_gecen_sure) * 100, 2); ?>%</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <form method="post" id="irsaliyeForm">
                <div class="row g-2 mb-2">
                    <div class="col-md-2">
                        <label class="form-label">İrsaliye No</label>
                        <input type="text" class="form-control form-control-sm" value="<?php echo htmlspecialchars($irsaliye['FISNO']); ?>" readonly>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Tarih</label>
                        <input type="date" class="form-control form-control-sm" name="tarih" value="<?php echo $irsaliye['FISTAR']; ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cari</label>
                        <select class="form-select form-select-sm" name="cari_id" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($cariler as $cari): ?>
                            <option value="<?php echo $cari['ID']; ?>" <?php echo $cari['ID'] == $irsaliye['CARIID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($cari['unvan']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Depo</label>
                        <select class="form-select form-select-sm" name="depo_id" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($depolar as $depo): ?>
                            <option value="<?php echo $depo['ID']; ?>" <?php echo $depo['ID'] == $irsaliye['DEPOID'] ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($depo['ADI']); ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control form-control-sm" name="notlar" rows="1"><?php echo htmlspecialchars($irsaliye['NOTLAR'] ?? ''); ?></textarea>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="table table-bordered table-sm" id="kalemTable">
                        <thead class="table-light">
                            <tr>
                                <th width="3%">#</th>
                                <th width="15%">Barkod</th>
                                <th>Ürün Adı</th>
                                <th width="8%">Miktar</th>
                                <th width="8%">Birim</th>
                                <th width="10%">Birim Fiyat</th>
                                <th width="10%">Toplam</th>
                                <th width="3%">İşlem</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($kalemler as $index => $kalem): ?>
                            <tr>
                                <td class="text-center">
                                    <?php echo $index + 1; ?>
                                </td>
                                <td>
                                    <div class="input-group input-group-sm">
                                        <input type="hidden" name="urun_id[]" class="urun-id-input" value="<?php echo $kalem['KARTID']; ?>" required>
                                        <input type="text" class="form-control form-control-sm urun-kod-input" 
                                            value="<?php echo $kalem['KARTID'] ? htmlspecialchars($kalem['urun_kod']) : ''; ?>" 
                                            placeholder="Barkod" readonly>
                                        <button type="button" class="btn btn-outline-secondary btn-sm urun-ara-btn">
                                            <i class="fas fa-search"></i>
                                        </button>
                                    </div>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm urun-ad-input" 
                                        value="<?php echo $kalem['KARTID'] ? htmlspecialchars($kalem['urun_adi']) : ''; ?>" 
                                        placeholder="Ürün adı" readonly>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm miktar" name="miktar[]" step="0.01" min="0.01" value="<?php echo $kalem['MIKTAR']; ?>" required>
                                </td>
                                <td>
                                    <input type="text" class="form-control form-control-sm birim" value="<?php echo htmlspecialchars($kalem['birim']); ?>" readonly>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm birim-fiyat" name="birim_fiyat[]" step="0.01" min="0" value="<?php echo $kalem['FIYAT']; ?>" required>
                                </td>
                                <td>
                                    <input type="number" class="form-control form-control-sm kalem-toplam" name="kalem_toplam[]" step="0.01" value="<?php echo $kalem['TUTAR']; ?>" readonly>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-danger btn-sm kalem-sil">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end"><strong>Genel Toplam:</strong></td>
                                <td>
                                    <input type="number" class="form-control form-control-sm" name="toplam_tutar" id="genelToplam" step="0.01" value="<?php echo $irsaliye['GENELTOPLAM']; ?>" readonly>
                                </td>
                                <td class="text-center">
                                    <button type="button" class="btn btn-success btn-sm" id="kalemEkle">
                                        <i class="fas fa-plus"></i>
                                    </button>
                                </td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </form>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const kalemTable = document.getElementById('kalemTable');
            const kalemEkleBtn = document.getElementById('kalemEkle');
            const genelToplamInput = document.getElementById('genelToplam');

            // Yeni kalem satırı ekle
            kalemEkleBtn.addEventListener('click', function() {
                const tbody = kalemTable.querySelector('tbody');
                const newRow = document.createElement('tr');
                const rowCount = tbody.querySelectorAll('tr').length + 1;
                
                newRow.innerHTML = `
                    <td class="text-center">${rowCount}</td>
                    <td>
                        <div class="input-group input-group-sm">
                            <input type="hidden" name="urun_id[]" class="urun-id-input" value="" required>
                            <input type="text" class="form-control form-control-sm urun-kod-input" 
                                value="" placeholder="Barkod" readonly>
                            <button type="button" class="btn btn-outline-secondary btn-sm urun-ara-btn">
                                <i class="fas fa-search"></i>
                            </button>
                        </div>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm urun-ad-input" 
                            value="" placeholder="Ürün adı" readonly>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm miktar" name="miktar[]" step="0.01" min="0.01" value="1" required>
                    </td>
                    <td>
                        <input type="text" class="form-control form-control-sm birim" value="" readonly>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm birim-fiyat" name="birim_fiyat[]" step="0.01" min="0" value="0" required>
                    </td>
                    <td>
                        <input type="number" class="form-control form-control-sm kalem-toplam" name="kalem_toplam[]" step="0.01" value="0" readonly>
                    </td>
                    <td class="text-center">
                        <button type="button" class="btn btn-danger btn-sm kalem-sil">
                            <i class="fas fa-trash"></i>
                        </button>
                    </td>
                `;
                
                // Event listenerları yeniden ekle
                setupEventListeners(newRow);
                
                tbody.appendChild(newRow);
            });

            // Satır silme fonksiyonu
            document.addEventListener('click', function(e) {
                if (e.target.closest('.kalem-sil')) {
                    const btn = e.target.closest('.kalem-sil');
                    const row = btn.closest('tr');
                    
                    // Eğer tabloda sadece bir satır kaldıysa silme
                    if (kalemTable.querySelectorAll('tbody tr').length > 1) {
                        row.remove();
                        hesaplaGenelToplam();
                    }
                }
            });

            // Ürün seçildiğinde birim ve fiyat bilgilerini doldur
            function setupEventListeners(row) {
                const miktarInput = row.querySelector('.miktar');
                const birimFiyatInput = row.querySelector('.birim-fiyat');
                
                if (miktarInput) {
                    miktarInput.addEventListener('input', function() {
                        hesaplaKalemToplam(row);
                    });
                }
                
                if (birimFiyatInput) {
                    birimFiyatInput.addEventListener('input', function() {
                        hesaplaKalemToplam(row);
                    });
                }
            }

            // Kalem toplam hesaplama
            function hesaplaKalemToplam(row) {
                const miktar = parseFloat(row.querySelector('.miktar').value) || 0;
                const birimFiyat = parseFloat(row.querySelector('.birim-fiyat').value) || 0;
                const kalemToplam = miktar * birimFiyat;
                
                row.querySelector('.kalem-toplam').value = kalemToplam.toFixed(2);
                
                hesaplaGenelToplam();
            }

            // Genel toplam hesaplama
            function hesaplaGenelToplam() {
                let genelToplam = 0;
                document.querySelectorAll('.kalem-toplam').forEach(input => {
                    genelToplam += parseFloat(input.value) || 0;
                });
                
                genelToplamInput.value = genelToplam.toFixed(2);
            }

            // Form gönderilmeden önce kontroller
            document.getElementById('irsaliyeForm').addEventListener('submit', function(e) {
                const urunSecilileri = document.querySelectorAll('.urun-select');
                let urunSecili = false;
                
                urunSecilileri.forEach(select => {
                    if (select.value) urunSecili = true;
                });
                
                if (!urunSecili) {
                    e.preventDefault();
                    alert('En az bir ürün seçmelisiniz!');
                    return;
                }
                
                const genelToplam = parseFloat(genelToplamInput.value) || 0;
                if (genelToplam <= 0) {
                    e.preventDefault();
                    alert('Toplam tutar 0 olamaz!');
                    return;
                }
            });

            // İlk satır için event listener'ları ayarla
            document.querySelectorAll('#kalemTable tbody tr').forEach(row => {
                setupEventListeners(row);
            });
            
            // Sayfa yüklendiğinde genel toplamı hesapla
            hesaplaGenelToplam();
        });
    </script>
    
    <!-- Ürün Arama Modal -->
    <div class="modal fade" id="urunAramaModal" tabindex="-1" aria-labelledby="urunAramaModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="urunAramaModalLabel">Ürün Ara</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
                </div>
                <div class="modal-body">
                    <div class="input-group mb-3">
                        <input type="text" class="form-control" id="urunAramaInput" placeholder="Ürün adı veya kodu girin...">
                        <button class="btn btn-primary" id="urunAramaButton" type="button">Ara</button>
                    </div>
                    <div id="urunSonuclar" class="table-responsive mt-3">
                        <table class="table table-striped table-hover">
                            <thead>
                                <tr>
                                    <th>Ürün Kodu</th>
                                    <th>Ürün Adı</th>
                                    <th>Birim</th>
                                    <th>Fiyat</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="urunSonuclarBody">
                                <!-- Arama sonuçları buraya eklenecek -->
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
        // Ürün arama ve seçme işlemleri
        document.addEventListener('DOMContentLoaded', function() {
            let currentRow = null;
            const urunAramaModal = new bootstrap.Modal(document.getElementById('urunAramaModal'));
            
            document.addEventListener('click', function(e) {
                if (e.target.closest('.urun-ara-btn')) {
                    const btn = e.target.closest('.urun-ara-btn');
                    currentRow = btn.closest('tr');
                    urunAramaModal.show();
                    document.getElementById('urunAramaInput').focus();
                }
            });
            
            document.getElementById('urunAramaInput').addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    document.getElementById('urunAramaButton').click();
                }
            });
            
            document.getElementById('urunAramaButton').addEventListener('click', function() {
                const aramaMetni = document.getElementById('urunAramaInput').value.trim();
                if (aramaMetni.length < 2) {
                    alert('En az 2 karakter girmelisiniz!');
                    return;
                }
                
                urunAra(aramaMetni);
            });
            
            function urunAra(aramaMetni) {
                fetch('urun_ara.php?q=' + encodeURIComponent(aramaMetni))
                    .then(response => response.json())
                    .then(data => {
                        const tbody = document.getElementById('urunSonuclarBody');
                        tbody.innerHTML = '';
                        
                        if (data.performans) {
                            const performansDiv = document.createElement('div');
                            performansDiv.className = 'alert alert-info mb-3';
                            performansDiv.innerHTML = `
                                <strong>Toplam süre:</strong> ${data.performans.toplam_sure} sn<br>
                                <strong>Bulunan ürün sayısı:</strong> ${data.performans.urun_sayisi}<br>
                                <strong>Arama süresi:</strong> ${data.performans.sureler.urunleri_ara.sure.toFixed(4)} sn<br>
                                <strong>Birim bilgisi süresi:</strong> ${data.performans.sureler.birim_bilgileri.sure.toFixed(4)} sn
                            `;
                            tbody.parentNode.parentNode.parentNode.insertBefore(performansDiv, tbody.parentNode.parentNode);
                        }
                        
                        const urunler = data.urunler || data;
                        
                        if (urunler.length === 0) {
                            const tr = document.createElement('tr');
                            tr.innerHTML = '<td colspan="5" class="text-center">Sonuç bulunamadı</td>';
                            tbody.appendChild(tr);
                            return;
                        }
                        
                        urunler.forEach(urun => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td>${urun.kod}</td>
                                <td>${urun.ad}</td>
                                <td>${urun.birim}</td>
                                <td>${urun.fiyat || '0.00'}</td>
                                <td>
                                    <button type="button" class="btn btn-sm btn-success urun-sec-btn" 
                                        data-id="${urun.ID}" 
                                        data-kod="${urun.kod}" 
                                        data-ad="${urun.ad}" 
                                        data-birim="${urun.birim}" 
                                        data-fiyat="${urun.fiyat || 0}">
                                        Seç
                                    </button>
                                </td>
                            `;
                            tbody.appendChild(tr);
                        });
                    })
                    .catch(error => {
                        console.error('Arama hatası:', error);
                        alert('Arama sırasında bir hata oluştu!');
                    });
            }
            
            document.addEventListener('click', function(e) {
                if (e.target.closest('.urun-sec-btn')) {
                    const btn = e.target.closest('.urun-sec-btn');
                    const urunId = btn.dataset.id;
                    const urunKod = btn.dataset.kod;
                    const urunAd = btn.dataset.ad;
                    const birim = btn.dataset.birim;
                    const fiyat = btn.dataset.fiyat;
                    
                    if (currentRow) {
                        currentRow.querySelector('.urun-id-input').value = urunId;
                        currentRow.querySelector('.urun-kod-input').value = urunKod;
                        currentRow.querySelector('.urun-ad-input').value = urunAd;
                        currentRow.querySelector('.birim').value = birim;
                        currentRow.querySelector('.birim-fiyat').value = fiyat;
                        
                        const miktar = parseFloat(currentRow.querySelector('.miktar').value) || 0;
                        const birimFiyat = parseFloat(fiyat) || 0;
                        const kalemToplam = miktar * birimFiyat;
                        
                        currentRow.querySelector('.kalem-toplam').value = kalemToplam.toFixed(2);
                        
                        hesaplaGenelToplam();
                    }
                    
                    urunAramaModal.hide();
                }
            });
        });
    </script>
</body>
</html> 