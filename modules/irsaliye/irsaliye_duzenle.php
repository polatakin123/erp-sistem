<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

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
$query = "SELECT f.* FROM stk_fis f 
          WHERE f.ID = ? AND f.TIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE')
          AND f.IPTAL = 0 AND f.FATURALANDI = 0";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$irsaliye) {
    header('Location: irsaliye_listesi.php');
    exit;
}

// Carileri getir
$query = "SELECT ID, ADI as unvan FROM cari ORDER BY ADI";
$stmt = $db->query($query);
$cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stok ürünlerini getir
$query = "SELECT s.ID, s.KOD as kod, s.ADI as ad, 
         (SELECT BIRIM_ADI FROM stk_birim WHERE ID = s.BIRIMID) as birim,
         (SELECT FIYAT FROM stk_fiyat WHERE STOKID = s.ID AND TIP = 'S' LIMIT 1) as fiyat,
         s.BIRIMID
         FROM stok s 
         WHERE s.DURUM = 1 
         ORDER BY s.ADI";
$stmt = $db->query($query);
$urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Depoları getir
$query = "SELECT ID, ADI FROM stk_depo ORDER BY ADI";
$stmt = $db->query($query);
$depolar = $stmt->fetchAll(PDO::FETCH_ASSOC);

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

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // Cari adını alalım
        $stmt_cari = $db->prepare("SELECT ADI FROM cari WHERE ID = ?");
        $stmt_cari->execute([$_POST['cari_id']]);
        $cari_adi = $stmt_cari->fetchColumn();

        // İrsaliye başlık bilgilerini güncelle
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

        // Mevcut kalemleri sil
        $query = "DELETE FROM stk_fis_har WHERE STKFISID = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$irsaliye_id]);

        // Yeni kalemleri ekle
        $query = "INSERT INTO stk_fis_har (
                    SIRANO, BOLUMID, FISTIP, STKFISID, FISTAR, ISLEMTIPI,
                    KARTTIPI, KARTID, MIKTAR, BIRIMID, FIYAT, TUTAR,
                    KDVORANI, KDVTUTARI, CARIID, DEPOID, SUBEID
                ) VALUES (
                    ?, 1, 'İrsaliye', ?, ?, 'Çıkış',
                    'S', ?, ?, ?, ?, ?,
                    0, 0, ?, ?, 1
                )";
        $stmt = $db->prepare($query);

        foreach ($_POST['urun_id'] as $key => $urun_id) {
            if (!empty($urun_id)) {
                // Ürün birim ID'sini alalım
                $stmt_birim = $db->prepare("SELECT BIRIMID FROM stok WHERE ID = ?");
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

        $db->commit();
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
</head>
<body>
    <?php include '../../includes/header.php'; ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>

            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">İrsaliye Düzenle</h1>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" id="irsaliyeForm">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">İrsaliye No</label>
                            <input type="text" class="form-control" value="<?php echo htmlspecialchars($irsaliye['FISNO']); ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tarih</label>
                            <input type="date" class="form-control" name="tarih" value="<?php echo $irsaliye['FISTAR']; ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cari</label>
                            <select class="form-select" name="cari_id" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($cariler as $cari): ?>
                                <option value="<?php echo $cari['ID']; ?>" <?php echo $cari['ID'] == $irsaliye['CARIID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($cari['unvan']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Depo</label>
                            <select class="form-select" name="depo_id" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($depolar as $depo): ?>
                                <option value="<?php echo $depo['ID']; ?>" <?php echo $depo['ID'] == $irsaliye['DEPOID'] ? 'selected' : ''; ?>>
                                    <?php echo htmlspecialchars($depo['ADI']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notlar" rows="2"><?php echo htmlspecialchars($irsaliye['NOTLAR'] ?? ''); ?></textarea>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered" id="kalemTable">
                            <thead>
                                <tr>
                                    <th width="40%">Ürün</th>
                                    <th width="15%">Miktar</th>
                                    <th width="15%">Birim</th>
                                    <th width="15%">Birim Fiyat</th>
                                    <th width="15%">Toplam</th>
                                    <th width="5%"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($kalemler as $kalem): ?>
                                <tr>
                                    <td>
                                        <select class="form-select urun-select" name="urun_id[]" required>
                                            <option value="">Seçiniz</option>
                                            <?php foreach ($urunler as $urun): ?>
                                            <option value="<?php echo $urun['ID']; ?>" 
                                                    data-birim="<?php echo htmlspecialchars($urun['birim']); ?>"
                                                    data-fiyat="<?php echo $urun['fiyat']; ?>"
                                                    <?php echo $urun['ID'] == $kalem['KARTID'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($urun['kod'] . ' - ' . $urun['ad']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control miktar" name="miktar[]" step="0.01" min="0.01" value="<?php echo $kalem['MIKTAR']; ?>" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control birim" value="<?php echo htmlspecialchars($kalem['birim']); ?>" readonly>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control birim-fiyat" name="birim_fiyat[]" step="0.01" min="0" value="<?php echo $kalem['FIYAT']; ?>" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control kalem-toplam" name="kalem_toplam[]" step="0.01" value="<?php echo $kalem['TUTAR']; ?>" readonly>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm kalem-sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Genel Toplam:</strong></td>
                                    <td>
                                        <input type="number" class="form-control" name="toplam_tutar" id="genelToplam" step="0.01" value="<?php echo $irsaliye['GENELTOPLAM']; ?>" readonly>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-success btn-sm" id="kalemEkle">
                                            <i class="fas fa-plus"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>

                    <div class="mb-3">
                        <button type="submit" class="btn btn-primary">Kaydet</button>
                        <a href="irsaliye_detay.php?id=<?php echo $irsaliye_id; ?>" class="btn btn-secondary">İptal</a>
                    </div>
                </form>
            </main>
        </div>
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
                const newRow = tbody.querySelector('tr').cloneNode(true);
                
                // Input değerlerini temizle
                newRow.querySelectorAll('input').forEach(input => input.value = '');
                newRow.querySelector('select').value = '';
                
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
                const urunSelect = row.querySelector('.urun-select');
                const miktarInput = row.querySelector('.miktar');
                const birimInput = row.querySelector('.birim');
                const birimFiyatInput = row.querySelector('.birim-fiyat');
                const kalemToplamInput = row.querySelector('.kalem-toplam');

                urunSelect.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const birim = selectedOption.dataset.birim || '';
                    const fiyat = selectedOption.dataset.fiyat || 0;
                    
                    birimInput.value = birim;
                    birimFiyatInput.value = fiyat;
                    
                    hesaplaKalemToplam(row);
                });

                miktarInput.addEventListener('input', function() {
                    hesaplaKalemToplam(row);
                });

                birimFiyatInput.addEventListener('input', function() {
                    hesaplaKalemToplam(row);
                });
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
</body>
</html> 