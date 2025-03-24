<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// Carileri getir
$query = "SELECT id, unvan FROM cariler ORDER BY unvan";
$stmt = $db->query($query);
$cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Ürünleri getir
$query = "SELECT id, kod, ad, birim, fiyat FROM urunler ORDER BY ad";
$stmt = $db->query($query);
$urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// İrsaliye numarası oluştur
$query = "SELECT MAX(CAST(SUBSTRING(irsaliye_no, 3) AS UNSIGNED)) as max_no FROM irsaliyeler";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$next_no = 'IR' . str_pad(($result['max_no'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // İrsaliye başlık bilgilerini kaydet
        $query = "INSERT INTO irsaliyeler (irsaliye_no, tarih, cari_id, toplam_tutar, durum, olusturan_id) 
                  VALUES (:irsaliye_no, :tarih, :cari_id, :toplam_tutar, 'Beklemede', :olusturan_id)";
        $stmt = $db->prepare($query);
        $stmt->execute([
            'irsaliye_no' => $_POST['irsaliye_no'],
            'tarih' => $_POST['tarih'],
            'cari_id' => $_POST['cari_id'],
            'toplam_tutar' => $_POST['toplam_tutar'],
            'olusturan_id' => $_SESSION['user_id']
        ]);
        $irsaliye_id = $db->lastInsertId();

        // İrsaliye kalemlerini kaydet
        $query = "INSERT INTO irsaliye_kalemleri (irsaliye_id, urun_id, miktar, birim_fiyat, toplam_tutar) 
                  VALUES (:irsaliye_id, :urun_id, :miktar, :birim_fiyat, :toplam_tutar)";
        $stmt = $db->prepare($query);

        foreach ($_POST['urun_id'] as $key => $urun_id) {
            if (!empty($urun_id)) {
                $stmt->execute([
                    'irsaliye_id' => $irsaliye_id,
                    'urun_id' => $urun_id,
                    'miktar' => $_POST['miktar'][$key],
                    'birim_fiyat' => $_POST['birim_fiyat'][$key],
                    'toplam_tutar' => $_POST['toplam_tutar'][$key]
                ]);
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
                        <div class="col-md-4">
                            <label class="form-label">İrsaliye No</label>
                            <input type="text" class="form-control" name="irsaliye_no" value="<?php echo $next_no; ?>" readonly>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Tarih</label>
                            <input type="date" class="form-control" name="tarih" value="<?php echo date('Y-m-d'); ?>" required>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Cari</label>
                            <select class="form-select" name="cari_id" required>
                                <option value="">Seçiniz</option>
                                <?php foreach ($cariler as $cari): ?>
                                <option value="<?php echo $cari['id']; ?>"><?php echo htmlspecialchars($cari['unvan']); ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>

                    <div class="table-responsive mb-3">
                        <table class="table table-bordered" id="kalemTable">
                            <thead>
                                <tr>
                                    <th>Ürün</th>
                                    <th>Miktar</th>
                                    <th>Birim Fiyat</th>
                                    <th>Toplam Tutar</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>
                                        <select class="form-select urun-select" name="urun_id[]" required>
                                            <option value="">Seçiniz</option>
                                            <?php foreach ($urunler as $urun): ?>
                                            <option value="<?php echo $urun['id']; ?>" 
                                                    data-birim="<?php echo htmlspecialchars($urun['birim']); ?>"
                                                    data-fiyat="<?php echo $urun['fiyat']; ?>">
                                                <?php echo htmlspecialchars($urun['kod'] . ' - ' . $urun['ad']); ?>
                                            </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control miktar" name="miktar[]" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control birim-fiyat" name="birim_fiyat[]" step="0.01" required>
                                    </td>
                                    <td>
                                        <input type="number" class="form-control toplam-tutar" name="toplam_tutar[]" step="0.01" readonly>
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
                                    <td colspan="3" class="text-end"><strong>Genel Toplam:</strong></td>
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
                
                tbody.appendChild(newRow);
            });

            // Kalem satırı sil
            kalemTable.addEventListener('click', function(e) {
                if (e.target.closest('.kalem-sil')) {
                    const tbody = kalemTable.querySelector('tbody');
                    if (tbody.children.length > 1) {
                        e.target.closest('tr').remove();
                        hesaplaGenelToplam();
                    }
                }
            });

            // Ürün seçildiğinde
            kalemTable.addEventListener('change', function(e) {
                if (e.target.classList.contains('urun-select')) {
                    const row = e.target.closest('tr');
                    const option = e.target.options[e.target.selectedIndex];
                    const birimFiyatInput = row.querySelector('.birim-fiyat');
                    const miktarInput = row.querySelector('.miktar');
                    
                    birimFiyatInput.value = option.dataset.fiyat;
                    miktarInput.value = '1';
                    
                    hesaplaSatirToplam(row);
                    hesaplaGenelToplam();
                }
            });

            // Miktar veya birim fiyat değiştiğinde
            kalemTable.addEventListener('input', function(e) {
                if (e.target.classList.contains('miktar') || e.target.classList.contains('birim-fiyat')) {
                    const row = e.target.closest('tr');
                    hesaplaSatirToplam(row);
                    hesaplaGenelToplam();
                }
            });

            function hesaplaSatirToplam(row) {
                const miktar = parseFloat(row.querySelector('.miktar').value) || 0;
                const birimFiyat = parseFloat(row.querySelector('.birim-fiyat').value) || 0;
                const toplamTutar = miktar * birimFiyat;
                row.querySelector('.toplam-tutar').value = toplamTutar.toFixed(2);
            }

            function hesaplaGenelToplam() {
                const toplamTutarlar = Array.from(kalemTable.querySelectorAll('.toplam-tutar'))
                    .map(input => parseFloat(input.value) || 0);
                const genelToplam = toplamTutarlar.reduce((a, b) => a + b, 0);
                genelToplamInput.value = genelToplam.toFixed(2);
            }
        });
    </script>
</body>
</html> 