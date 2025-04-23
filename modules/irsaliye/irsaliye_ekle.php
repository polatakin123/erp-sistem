<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Carileri getir
$query = "SELECT ID, ADI as unvan FROM cari ORDER BY ADI";
$stmt = $db->query($query);
$cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Stok ürünlerini getir
$query = "SELECT s.ID, s.KOD as kod, s.ADI as ad,
         (SELECT FIYAT FROM stk_fiyat WHERE STOKID = s.ID AND TIP = 'S' LIMIT 1) as fiyat
         FROM stok s 
         WHERE s.DURUM = 1 
         ORDER BY s.ADI";
$stmt = $db->query($query);
$urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Birim bilgilerini ekleyelim
foreach ($urunler as &$urun) {
    // Stokun birim bilgisini almak için
    $birim_query = "SELECT g.KOD as birim 
                  FROM stok_birim sb 
                  LEFT JOIN grup g ON sb.BIRIMID = g.ID 
                  WHERE sb.STOKID = ? 
                  LIMIT 1";
    $birim_stmt = $db->prepare($birim_query);
    $birim_stmt->execute([$urun['ID']]);
    $birim = $birim_stmt->fetch(PDO::FETCH_ASSOC);
    $urun['birim'] = $birim ? $birim['birim'] : '';
}

// Depo değerleri için varsayılan bir değer kullanalım
$depolar = [
    ['ID' => 1, 'ADI' => 'Varsayılan Depo']
];

// İrsaliye numarası oluştur
$query = "SELECT MAX(CAST(SUBSTRING(FISNO, 3) AS UNSIGNED)) as max_no FROM stk_fis WHERE TIP='İrsaliye'";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$next_no = 'IR' . str_pad(($result['max_no'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // İrsaliye başlık bilgilerini kaydet (stk_fis tablosu)
        $query = "INSERT INTO stk_fis (
                    BOLUMID, TIP, FISNO, FISTAR, FISSAAT, CARIID, DEPOID, 
                    STOKTOPLAM, KALEMISKTOPLAM, KALEMKDVTOPLAM, ISKORAN1, ISKTUTAR1, 
                    ARATOPLAM, FISKDVTUTARI, GENELTOPLAM, CARIADI, IPTAL, FATURALANDI, 
                    NOTLAR, DURUM, SUBEID
                ) VALUES (
                    1, '20', :fisno, :tarih, NOW(), :cari_id, :depo_id,
                    :toplam_tutar, 0, 0, 0, 0,
                    :toplam_tutar, 0, :toplam_tutar, :cari_adi, 0, 0,
                    :notlar, 1, 1
                )";

        // Cari adını alalım
        $stmt_cari = $db->prepare("SELECT ADI FROM cari WHERE ID = ?");
        $stmt_cari->execute([$_POST['cari_id']]);
        $cari_adi = $stmt_cari->fetchColumn();

        $stmt = $db->prepare($query);
        $stmt->execute([
            'fisno' => $_POST['irsaliye_no'],
            'tarih' => $_POST['tarih'],
            'cari_id' => $_POST['cari_id'],
            'depo_id' => $_POST['depo_id'],
            'toplam_tutar' => $_POST['toplam_tutar'],
            'cari_adi' => $cari_adi,
            'notlar' => $_POST['notlar'] ?? ''
        ]);
        $irsaliye_id = $db->lastInsertId();

        // İrsaliye kalemlerini kaydet (stk_fis_har tablosu)
        $query = "INSERT INTO stk_fis_har (
                    SIRANO, BOLUMID, FISTIP, STKFISID, FISTAR, ISLEMTIPI,
                    KARTTIPI, KARTID, MIKTAR, BIRIMID, FIYAT, TUTAR,
                    KDVORANI, KDVTUTARI, CARIID, DEPOID, SUBEID, FATSIRANO
                ) VALUES (
                    :sirano, 1, '11', :irsaliye_id, :tarih, 1,
                    'S', :urun_id, :miktar, :birim_id, :birim_fiyat, :toplam_tutar,
                    :kdv_orani, :kdv_tutari, :cari_id, :depo_id, :sube_id, :fatsirano
                )";
        $stmt = $db->prepare($query);

        foreach ($_POST['urun_id'] as $key => $urun_id) {
            if (!empty($urun_id)) {
                // Ürün birim ID'sini alalım - stok_birim tablosu yoksa varsayılan değer kullan
                try {
                    $stmt_birim = $db->prepare("SELECT sb.BIRIMID FROM stok_birim sb WHERE sb.STOKID = ? LIMIT 1");
                    $stmt_birim->execute([$urun_id]);
                    $birim_id = $stmt_birim->fetchColumn();
                    
                    // Eğer birim bulunamadıysa varsayılan değer ata
                    if (!$birim_id) {
                        $birim_id = 3; // Varsayılan birim ID
                    }
                } catch (Exception $e) {
                    // Hata olursa varsayılan değer kullan
                    $birim_id = 3; // Varsayılan birim ID
                    error_log("Birim ID bulunamadı: " . $e->getMessage());
                }

                // Sıra numarası
                $sirano = $key + 1;
                
                // KDV oranı ve tutarı hesapla
                $kdv_orani = isset($_POST['kdv_orani'][$key]) ? floatval($_POST['kdv_orani'][$key]) : 0;
                $tutar = floatval($_POST['kalem_toplam'][$key]);
                // Formdan gelen KDV tutarını kullan
                $kdv_tutari = isset($_POST['kalem_kdv_tutari'][$key]) ? floatval($_POST['kalem_kdv_tutari'][$key]) : 0;
                
                // Debug için kalem parametrelerini kaydet
                $kalem_params = [
                    'sirano' => $sirano,
                    'irsaliye_id' => $irsaliye_id,
                    'tarih' => $_POST['tarih'],
                    'urun_id' => $urun_id,
                    'miktar' => $_POST['miktar'][$key],
                    'birim_id' => $birim_id,
                    'birim_fiyat' => $_POST['birim_fiyat'][$key],
                    'toplam_tutar' => $tutar,
                    'kdv_orani' => $kdv_orani,
                    'kdv_tutari' => $kdv_tutari,
                    'cari_id' => $_POST['cari_id'],
                    'depo_id' => $_POST['depo_id'],
                    'sube_id' => $_POST['sube_id'] ?? 1,
                    'fatsirano' => $sirano // FATSIRANO, SIRANO ile aynı değeri alacak
                ];
                
                $debug_info['kalem_'.$key] = $kalem_params;
                
                try {
                    $stmt->execute($kalem_params);
                } catch (PDOException $e) {
                    $debug_info['kalem_error_'.$key] = $e->getMessage();
                    throw $e;
                }
            }
        }

        $db->commit();
        header('Location: irsaliye_listesi.php');
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
    <title>Yeni İrsaliye - ERP Sistemi</title>
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
                    <h1 class="h2">Yeni İrsaliye</h1>
                </div>

                <?php if (isset($error)): ?>
                <div class="alert alert-danger"><?php echo $error; ?></div>
                <?php endif; ?>

                <form method="post" id="irsaliyeForm">
                    <div class="row mb-3">
                        <div class="col-md-3">
                            <label class="form-label">İrsaliye No</label>
                            <input type="text" class="form-control" name="irsaliye_no" value="<?php echo $next_no; ?>" readonly>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Tarih</label>
                            <input type="date" class="form-control" name="tarih" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Cari</label>
                            <select class="form-select" name="cari_id" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($cariler as $cari): ?>
                                <option value="<?php echo $cari['ID']; ?>"><?php echo htmlspecialchars($cari['unvan']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Depo</label>
                            <select class="form-select" name="depo_id" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($depolar as $depo): ?>
                                <option value="<?php echo $depo['ID']; ?>"><?php echo htmlspecialchars($depo['ADI']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Notlar</label>
                        <textarea class="form-control" name="notlar" rows="2"></textarea>
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
                                <tr>
                                    <td>
                                        <select class="form-select urun-select" name="urun_id[]" required>
                                            <option value="">Seçiniz</option>
                                            <?php foreach ($urunler as $urun): ?>
                                            <option value="<?php echo $urun['ID']; ?>" 
                                                    data-birim="<?php echo htmlspecialchars($urun['birim']); ?>"
                                                    data-fiyat="<?php echo $urun['fiyat']; ?>">
                                                <?php echo htmlspecialchars($urun['kod'] . ' - ' . $urun['ad']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control miktar" name="miktar[]" step="0.01" min="0.01" required>
                                    </td>
                                    <td>
                                        <input type="text" class="form-control birim" readonly>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control birim-fiyat" name="birim_fiyat[]" step="0.01" min="0" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control kalem-toplam" name="kalem_toplam[]" step="0.01" readonly>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-danger btn-sm kalem-sil">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                            <tfoot>
                                <tr>
                                    <td colspan="4" class="text-end"><strong>Genel Toplam:</strong></td>
                                    <td>
                                        <input type="number" class="form-control" name="toplam_tutar" id="genelToplam" step="0.01" readonly>
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
                        <a href="irsaliye_listesi.php" class="btn btn-secondary">İptal</a>
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
        });
    </script>
</body>
</html> 