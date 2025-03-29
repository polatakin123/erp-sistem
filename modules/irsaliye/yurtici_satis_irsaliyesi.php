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


// Depo değerleri için varsayılan bir değer kullanalım
$depolar = [
    ['ID' => 1, 'ADI' => 'Ana Depo']
];

// İrsaliye numarası oluştur
$query = "SELECT MAX(CAST(SUBSTRING(FISNO, 4) AS UNSIGNED)) as max_no FROM stk_fis WHERE TIP='12'";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$next_no = 'YSI' . str_pad(($result['max_no'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);

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
                    1, '12', :fisno, :tarih, NOW(), :cari_id, :depo_id,
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
                    KDVORANI, KDVTUTARI, CARIID, DEPOID, SUBEID
                ) VALUES (
                    :sirano, 1, '12', :irsaliye_id, :tarih, 1,
                    'S', :urun_id, :miktar, :birim_id, :birim_fiyat, :toplam_tutar,
                    0, 0, :cari_id, :depo_id, 1
                )";
        $stmt = $db->prepare($query);

        foreach ($_POST['urun_id'] as $key => $urun_id) {
            if (!empty($urun_id)) {
                // Ürün birim ID'sini alalım
                $stmt_birim = $db->prepare("SELECT sb.BIRIMID FROM stok_birim sb WHERE sb.STOKID = ? LIMIT 1");
                $stmt_birim->execute([$urun_id]);
                $birim_id = $stmt_birim->fetchColumn();

                // Sıra numarası
                $sirano = $key + 1;

                $stmt->execute([
                    'sirano' => $sirano,
                    'irsaliye_id' => $irsaliye_id,
                    'tarih' => $_POST['tarih'],
                    'urun_id' => $urun_id,
                    'miktar' => $_POST['miktar'][$key],
                    'birim_id' => $birim_id,
                    'birim_fiyat' => $_POST['birim_fiyat'][$key],
                    'toplam_tutar' => $_POST['kalem_toplam'][$key],
                    'cari_id' => $_POST['cari_id'],
                    'depo_id' => $_POST['depo_id']
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

// AJAX isteği - Ürün Arama
if (isset($_GET['urun_ara']) && !empty($_GET['q'])) {
    $arama = $_GET['q'];
    
    // SQL Injection koruması
    $arama = "%" . str_replace(['%', '_'], ['\%', '\_'], $arama) . "%";
    
    $query = "SELECT s.ID, s.KOD, s.ADI, 
                (SELECT FIYAT FROM stk_fiyat WHERE STOKID = s.ID AND TIP = 'S' LIMIT 1) as FIYAT,
                (SELECT g.KOD FROM stok_birim sb LEFT JOIN grup g ON sb.BIRIMID = g.ID WHERE sb.STOKID = s.ID LIMIT 1) as BIRIM
              FROM stok s 
              WHERE s.DURUM = 1 AND (s.KOD LIKE ? OR s.ADI LIKE ?)
              ORDER BY s.ADI
              LIMIT 20";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$arama, $arama]);
    $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    header('Content-Type: application/json');
    echo json_encode($sonuclar);
    exit;
}

// Sayfa başlığı
$pageTitle = "Yurt İçi Satış İrsaliyesi";

include_once '../../includes/header.php';
?>

<div class="container-fluid my-4">
    <div class="row">
        <div class="col">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2"><?php echo $pageTitle; ?></h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <a href="irsaliye_listesi.php" class="btn btn-sm btn-outline-secondary">
                        <i class="fas fa-list"></i> İrsaliye Listesi
                    </a>
                </div>
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
                        <label class="form-label">Müşteri</label>
                        <select class="form-select" name="cari_id" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($cariler as $cari): ?>
                            <option value="<?php echo $cari['ID']; ?>"><?php echo htmlspecialchars($cari['unvan']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Çıkış Deposu</label>
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

                <div class="row mb-3">
                    <div class="col-md-12">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Ürün Arama</span>
                                <button type="button" class="btn btn-sm btn-primary" id="yeniUrunEkle">
                                    <i class="fas fa-plus"></i> Ürün Ekle
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="form-group">
                                    <div class="input-group">
                                        <input type="text" class="form-control" id="urunArama" placeholder="Ürün kodu veya adı ile arama yapın...">
                                        <button type="button" class="btn btn-outline-secondary" id="urunAramaBtn">
                                            <i class="fas fa-search"></i> Ara
                                        </button>
                                    </div>
                                </div>
                                <div id="urunSonuclari" class="mt-2" style="display:none;">
                                    <div class="list-group" id="urunListesi"></div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="table-responsive mb-3">
                    <table class="table table-bordered" id="kalemTable">
                        <thead class="table-light">
                            <tr>
                                <th>Ürün Kodu</th>
                                <th>Ürün Adı</th>
                                <th>Miktar</th>
                                <th>Birim</th>
                                <th>Birim Fiyat</th>
                                <th>Toplam</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="kalemlerBody">
                            <!-- Kalemler JavaScript ile eklenecek -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="5" class="text-end"><strong>Genel Toplam:</strong></td>
                                <td>
                                    <input type="number" class="form-control" name="toplam_tutar" id="genelToplam" step="0.01" readonly>
                                </td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                </div>

                <div class="row">
                    <div class="col">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-save"></i> Kaydet
                        </button>
                        <a href="irsaliye_listesi.php" class="btn btn-secondary">
                            <i class="fas fa-times"></i> İptal
                        </a>
                    </div>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Ürün Seçme Modalı -->
<div class="modal fade" id="urunSecModal" tabindex="-1" aria-labelledby="urunSecModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="urunSecModalLabel">Ürün Seç</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Kapat"></button>
            </div>
            <div class="modal-body">
                <div class="input-group mb-3">
                    <input type="text" class="form-control" id="modalUrunArama" placeholder="Ürün kodu veya adı ile arama yapın...">
                    <button class="btn btn-outline-secondary" type="button" id="modalUrunAramaBtn">
                        <i class="fas fa-search"></i> Ara
                    </button>
                </div>
                <div id="modalUrunSonuclari">
                    <div class="list-group" id="modalUrunListesi"></div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Sayfa değişkenlerini tanımlayalım
    let kalemSayisi = 0;
    const urunListesi = new Map(); // Eklenen ürünleri hafızada tutalım

    // Ürün arama kutusu için event listener
    document.getElementById('urunAramaBtn').addEventListener('click', urunAra);
    document.getElementById('urunArama').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            urunAra();
        }
    });

    // Modal içindeki arama
    document.getElementById('modalUrunAramaBtn').addEventListener('click', modalUrunAra);
    document.getElementById('modalUrunArama').addEventListener('keyup', function(e) {
        if (e.key === 'Enter') {
            modalUrunAra();
        }
    });

    // Yeni Ürün Ekle butonu
    document.getElementById('yeniUrunEkle').addEventListener('click', function() {
        // Bootstrap 5 Modal
        const urunSecModal = new bootstrap.Modal(document.getElementById('urunSecModal'));
        urunSecModal.show();
    });

    // Ürün arama fonksiyonu
    function urunAra() {
        const aramaTerim = document.getElementById('urunArama').value.trim();
        if (aramaTerim.length < 2) {
            alert('Lütfen en az 2 karakter girin.');
            return;
        }

        document.getElementById('urunSonuclari').style.display = 'block';
        
        fetch(`?urun_ara=1&q=${encodeURIComponent(aramaTerim)}`)
            .then(response => response.json())
            .then(data => {
                const urunListesiDiv = document.getElementById('urunListesi');
                urunListesiDiv.innerHTML = '';

                if (data.length === 0) {
                    urunListesiDiv.innerHTML = '<div class="list-group-item">Sonuç bulunamadı.</div>';
                    return;
                }

                data.forEach(urun => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action';
                    button.textContent = `${urun.KOD} - ${urun.ADI}`;
                    button.dataset.id = urun.ID;
                    button.dataset.kod = urun.KOD;
                    button.dataset.ad = urun.ADI;
                    button.dataset.fiyat = urun.FIYAT || 0;
                    button.dataset.birim = urun.BIRIM || '';
                    
                    button.addEventListener('click', function() {
                        urunSec(urun.ID, urun.KOD, urun.ADI, urun.FIYAT || 0, urun.BIRIM || '');
                        document.getElementById('urunSonuclari').style.display = 'none';
                        document.getElementById('urunArama').value = '';
                    });
                    
                    urunListesiDiv.appendChild(button);
                });
            })
            .catch(error => {
                console.error('Arama hatası:', error);
                document.getElementById('urunListesi').innerHTML = '<div class="list-group-item text-danger">Arama sırasında bir hata oluştu.</div>';
            });
    }

    // Modal içindeki ürün arama fonksiyonu
    function modalUrunAra() {
        const aramaTerim = document.getElementById('modalUrunArama').value.trim();
        if (aramaTerim.length < 2) {
            alert('Lütfen en az 2 karakter girin.');
            return;
        }
        
        fetch(`?urun_ara=1&q=${encodeURIComponent(aramaTerim)}`)
            .then(response => response.json())
            .then(data => {
                const urunListesiDiv = document.getElementById('modalUrunListesi');
                urunListesiDiv.innerHTML = '';

                if (data.length === 0) {
                    urunListesiDiv.innerHTML = '<div class="list-group-item">Sonuç bulunamadı.</div>';
                    return;
                }

                data.forEach(urun => {
                    const button = document.createElement('button');
                    button.type = 'button';
                    button.className = 'list-group-item list-group-item-action';
                    button.textContent = `${urun.KOD} - ${urun.ADI}`;
                    
                    button.addEventListener('click', function() {
                        urunSec(urun.ID, urun.KOD, urun.ADI, urun.FIYAT || 0, urun.BIRIM || '');
                        bootstrap.Modal.getInstance(document.getElementById('urunSecModal')).hide();
                        document.getElementById('modalUrunArama').value = '';
                    });
                    
                    urunListesiDiv.appendChild(button);
                });
            })
            .catch(error => {
                console.error('Arama hatası:', error);
                document.getElementById('modalUrunListesi').innerHTML = '<div class="list-group-item text-danger">Arama sırasında bir hata oluştu.</div>';
            });
    }

    // Ürün seçme fonksiyonu
    function urunSec(id, kod, ad, fiyat, birim) {
        // Eğer bu ürün zaten eklenmişse, sadece miktarını artıralım
        if (urunListesi.has(id)) {
            const index = urunListesi.get(id);
            const miktarInput = document.querySelector(`input[name="miktar[${index}]"]`);
            miktarInput.value = (parseFloat(miktarInput.value) || 0) + 1;
            miktarInput.dispatchEvent(new Event('input'));
            return;
        }

        const tbody = document.getElementById('kalemlerBody');
        const index = kalemSayisi;
        kalemSayisi++;
        
        // Yeni ürün için Map'e kaydet
        urunListesi.set(id, index);
        
        const row = document.createElement('tr');
        row.id = `kalem_${index}`;
        row.innerHTML = `
            <td>${kod}<input type="hidden" name="urun_id[${index}]" value="${id}"></td>
            <td>${ad}</td>
            <td>
                <input type="number" class="form-control miktar" name="miktar[${index}]" step="0.01" min="0.01" value="1" required>
            </td>
            <td>
                <input type="text" class="form-control birim" name="birim[${index}]" value="${birim}" readonly>
            </td>
            <td>
                <input type="number" class="form-control birim-fiyat" name="birim_fiyat[${index}]" step="0.01" min="0" value="${fiyat}" required>
            </td>
            <td>
                <input type="number" class="form-control kalem-toplam" name="kalem_toplam[${index}]" step="0.01" value="${fiyat}" readonly>
            </td>
            <td>
                <button type="button" class="btn btn-danger btn-sm kalem-sil" data-index="${index}" data-id="${id}">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        
        tbody.appendChild(row);
        
        // Yeni satır için event listener ekle
        const miktarInput = row.querySelector('.miktar');
        const fiyatInput = row.querySelector('.birim-fiyat');
        const silBtn = row.querySelector('.kalem-sil');
        
        miktarInput.addEventListener('input', function() {
            hesaplaKalemToplam(index);
        });
        
        fiyatInput.addEventListener('input', function() {
            hesaplaKalemToplam(index);
        });
        
        silBtn.addEventListener('click', function() {
            satirSil(this.dataset.index, this.dataset.id);
        });
        
        hesaplaKalemToplam(index);
        hesaplaGenelToplam();
    }
    
    // Kalem toplam hesaplama fonksiyonu
    function hesaplaKalemToplam(index) {
        const miktar = parseFloat(document.querySelector(`input[name="miktar[${index}]"]`).value) || 0;
        const birimFiyat = parseFloat(document.querySelector(`input[name="birim_fiyat[${index}]"]`).value) || 0;
        const toplam = miktar * birimFiyat;
        
        document.querySelector(`input[name="kalem_toplam[${index}]"]`).value = toplam.toFixed(2);
        hesaplaGenelToplam();
    }
    
    // Genel toplam hesaplama fonksiyonu
    function hesaplaGenelToplam() {
        let toplam = 0;
        document.querySelectorAll('.kalem-toplam').forEach(input => {
            toplam += parseFloat(input.value) || 0;
        });
        document.getElementById('genelToplam').value = toplam.toFixed(2);
    }
    
    // Satır silme fonksiyonu
    function satirSil(index, id) {
        const row = document.getElementById(`kalem_${index}`);
        row.remove();
        urunListesi.delete(parseInt(id));
        hesaplaGenelToplam();
    }
    
    // Form gönderilmeden önce kontrol
    document.getElementById('irsaliyeForm').addEventListener('submit', function(e) {
        const kalemler = document.querySelectorAll('#kalemlerBody tr');
        
        if (kalemler.length === 0) {
            e.preventDefault();
            alert('Lütfen en az bir ürün ekleyin.');
            return;
        }
    });
});
</script>

<?php include_once '../../includes/footer.php'; ?> 