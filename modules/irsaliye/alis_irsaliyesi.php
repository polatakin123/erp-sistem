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
$query = "SELECT MAX(CAST(SUBSTRING(FISNO, 3) AS UNSIGNED)) as max_no FROM stk_fis WHERE TIP='11'";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$next_no = 'AI' . str_pad(($result['max_no'] ?? 0) + 1, 6, '0', STR_PAD_LEFT);

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        $db->beginTransaction();

        // İrsaliye başlık bilgilerini kaydet (stk_fis tablosu)
        $query = "INSERT INTO stk_fis (
                    BOLUMID, TIP, FISNO, FISTAR, FISSAAT, CARIID, DEPOID, FATFISID,
                    STOKTOPLAM, KALEMISKTOPLAM, KALEMKDVTOPLAM, ISKORAN1, ISKTUTAR1, 
                    ARATOPLAM, FISKDVTUTARI, GENELTOPLAM, CARIADI, IPTAL, FATURALANDI, 
                    NOTLAR, SUBEID
                ) VALUES (
                    1, '10', :fisno, :tarih, NOW(), :cari_id, :depo_id, -1,
                    :toplam_tutar, 0, 0, 0, 0,
                    :toplam_tutar, 0, :toplam_tutar, :cari_adi, 0, 0,
                    :notlar, 1
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
                    :sirano, 1, '11', :irsaliye_id, :tarih, 0,
                    'S', :urun_id, :miktar, :birim_id, :birim_fiyat, :toplam_tutar,
                    0, 0, :cari_id, :depo_id, 1
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
                        $birim_id = 1; // Varsayılan birim ID
                    }
                } catch (Exception $e) {
                    // Hata olursa varsayılan değer kullan
                    $birim_id = 1; // Varsayılan birim ID
                    error_log("Birim ID bulunamadı: " . $e->getMessage());
                }

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
    try {
        $arama = $_GET['q'];
        
        // Özel ID arama formatı: "id:123" şeklinde
        if (preg_match('/^id:(\d+)$/', $arama, $matches)) {
            $urunId = $matches[1];
            $query = "SELECT s.ID, s.KOD, s.ADI, 
                        IFNULL((SELECT FIYAT FROM stk_fiyat WHERE STOKID = s.ID AND TIP = 'A' LIMIT 1), 0) as ALIS_FIYAT,
                        IFNULL((SELECT FIYAT FROM stk_fiyat WHERE STOKID = s.ID AND TIP = 'S' LIMIT 1), 0) as SATIS_FIYAT,
                        IFNULL((SELECT SUM(MIKTAR) FROM stk_urun_miktar WHERE URUN_ID = s.ID), 0) as MIKTAR,
                        'AD' as BIRIM
                      FROM stok s 
                      WHERE s.ID = ?";
            
            $stmt = $db->prepare($query);
            $stmt->execute([$urunId]);
            $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            header('Content-Type: application/json');
            echo json_encode($sonuclar);
            exit;
        }
        
        // Kelimelere ayır ve her birini ayrı arama ifadesine dönüştür
        $arama_kelimeleri = explode(' ', $arama);
        $where_conditions = [];
        $params = [];
        
        foreach ($arama_kelimeleri as $kelime) {
            if (strlen($kelime) >= 2) { // 2 karakterden kısa kelimeleri atla
                // SQL Injection koruması
                $kelime = "%" . str_replace(['%', '_'], ['\%', '\_'], $kelime) . "%";
                $where_conditions[] = "(s.KOD LIKE ? OR s.ADI LIKE ?)";
                $params[] = $kelime;
                $params[] = $kelime;
            }
        }
        
        // Eğer hiç koşul yoksa basit arama yap
        if (empty($where_conditions)) {
            $arama = "%" . str_replace(['%', '_'], ['\%', '\_'], $arama) . "%";
            $where_sql = "(s.KOD LIKE ? OR s.ADI LIKE ?)";
            $params = [$arama, $arama];
        } else {
            // Her koşulu AND ile bağla (tüm kelimeleri içermeli)
            $where_sql = implode(' AND ', $where_conditions);
        }
        
        // Sorguyu genişletelim ve alış/satış fiyatları ile stok miktarını da alalım
        $query = "SELECT s.ID, s.KOD, s.ADI, 
                    IFNULL((SELECT FIYAT FROM stk_fiyat WHERE STOKID = s.ID AND TIP = 'A' LIMIT 1), 0) as ALIS_FIYAT,
                    IFNULL((SELECT FIYAT FROM stk_fiyat WHERE STOKID = s.ID AND TIP = 'S' LIMIT 1), 0) as SATIS_FIYAT,
                    IFNULL((SELECT SUM(MIKTAR) FROM stk_urun_miktar WHERE URUN_ID = s.ID), 0) as MIKTAR,
                    'AD' as BIRIM  -- Geçici olarak varsayılan bir birim tanımlıyoruz
                  FROM stok s 
                  WHERE s.DURUM = 1 AND {$where_sql}
                  ORDER BY MIKTAR DESC  -- Miktara göre azalan sırada listele (çoktan aza)
                  LIMIT 20";
        
        $stmt = $db->prepare($query);
        $stmt->execute($params);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($sonuclar);
    } catch (Exception $e) {
        // Hata durumunda bile JSON formatında yanıt dönelim
        header('Content-Type: application/json');
        echo json_encode(['error' => true, 'message' => $e->getMessage()]);
    }
    exit;
}

// Sayfa başlığı
$pageTitle = "Alış İrsaliyesi";

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
                        <label class="form-label">Tedarikçi</label>
                        <select class="form-select" name="cari_id" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($cariler as $cari): ?>
                            <option value="<?php echo $cari['ID']; ?>"><?php echo htmlspecialchars($cari['unvan']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Giriş Deposu</label>
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
                        <a href="javascript:history.back()" class="btn btn-secondary">
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

    // URL'den gelen ürün ID'lerini kontrol et ve otomatik olarak ekle
    function urldenUrunleriEkle() {
        // URL parametrelerini al
        const urlParams = new URLSearchParams(window.location.search);
        
        // urun_id[] parametrelerini al
        const urunIdleri = urlParams.getAll('urun_id[]');
        
        if (urunIdleri && urunIdleri.length > 0) {
            console.log('URL\'den ' + urunIdleri.length + ' ürün ID\'si alındı:', urunIdleri);
            
            // Her ürün ID'si için veritabanından bilgileri al
            const promises = urunIdleri.map(id => 
                fetch(`${window.location.pathname}?urun_ara=1&q=id:${id}`)
                    .then(response => response.json())
                    .then(data => {
                        if (data && data.length > 0) {
                            const urun = data[0];
                            // Ürünü tabloya ekle
                            urunSec(urun.ID, urun.KOD, urun.ADI, urun.ALIS_FIYAT || 0, urun.BIRIM || '');
                            return true;
                        }
                        return false;
                    })
                    .catch(error => {
                        console.error(`Ürün ID ${id} için bilgi alınamadı:`, error);
                        return false;
                    })
            );
            
            // Tüm ürünler yüklendikten sonra
            Promise.all(promises).then(results => {
                const basariliYuklenen = results.filter(success => success).length;
                console.log(`${basariliYuklenen}/${urunIdleri.length} ürün başarıyla yüklendi.`);
                
                if (basariliYuklenen < urunIdleri.length) {
                    alert(`Dikkat: ${urunIdleri.length - basariliYuklenen} ürün yüklenemedi.`);
                }
                
                // Genel toplamı güncelle
                hesaplaGenelToplam();
            });
        }
    }

    // Sayfa yüklenince URL'den ürünleri ekle
    urldenUrunleriEkle();

    // F4 tuşuyla ürün arama modalını açma
    document.addEventListener('keydown', function(e) {
        if (e.keyCode === 115 || e.key === 'F4') { // F4 tuşu
            e.preventDefault(); // Tarayıcı varsayılan davranışını engelle
            const urunSecModal = new bootstrap.Modal(document.getElementById('urunSecModal'));
            urunSecModal.show();
        }
    });

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
        console.log('Ürün arama başlatılıyor:', aramaTerim);
        
        // URL'yi mevcut sayfanın yolunu kullanarak tam olarak belirtelim
        const currentPath = window.location.pathname;
        fetch(`${currentPath}?urun_ara=1&q=${encodeURIComponent(aramaTerim)}`)
            .then(response => {
                console.log('Sunucu yanıtı alındı, durum kodu:', response.status);
                // Önce yanıtı text olarak alıp içeriğini kontrol edelim
                return response.text();
            })
            .then(text => {
                console.log('Yanıt içeriği:', text.substring(0, 200)); // İlk 200 karakteri göster
                
                try {
                    // Text'i JSON'a çevirmeyi deneyelim
                    const data = JSON.parse(text);
                    
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
                        
                        // Ürün bilgilerini daha detaylı gösterelim
                        const alis = parseFloat(urun.ALIS_FIYAT || 0).toFixed(2);
                        const satis = parseFloat(urun.SATIS_FIYAT || 0).toFixed(2);
                        const miktar = parseFloat(urun.MIKTAR || 0).toFixed(2);
                        
                        button.innerHTML = `
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${urun.KOD} - ${urun.ADI}</h6>
                                <small>Stok: ${miktar} ${urun.BIRIM}</small>
                            </div>
                            <p class="mb-1 small">Alış: ${alis} TL | Satış: ${satis} TL</p>
                        `;
                        
                        button.dataset.id = urun.ID;
                        button.dataset.kod = urun.KOD;
                        button.dataset.ad = urun.ADI;
                        button.dataset.fiyat = urun.ALIS_FIYAT || 0; // Alış fiyatını kullan
                        button.dataset.birim = urun.BIRIM || '';
                        
                        button.addEventListener('click', function() {
                            urunSec(urun.ID, urun.KOD, urun.ADI, urun.ALIS_FIYAT || 0, urun.BIRIM || '');
                            document.getElementById('urunSonuclari').style.display = 'none';
                            document.getElementById('urunArama').value = '';
                        });
                        
                        urunListesiDiv.appendChild(button);
                    });
                } catch (e) {
                    console.error('JSON ayrıştırma hatası:', e);
                    document.getElementById('urunListesi').innerHTML = '<div class="list-group-item text-danger">Sunucu yanıtı geçerli JSON formatında değil. Lütfen yönetici ile iletişime geçin.</div>';
                }
            })
            .catch(error => {
                console.error('Arama hatası detayı:', error);
                document.getElementById('urunListesi').innerHTML = '<div class="list-group-item text-danger">Arama sırasında bir hata oluştu: ' + error.message + '</div>';
            });
    }

    // Modal içindeki ürün arama fonksiyonu
    function modalUrunAra() {
        const aramaTerim = document.getElementById('modalUrunArama').value.trim();
        if (aramaTerim.length < 2) {
            alert('Lütfen en az 2 karakter girin.');
            return;
        }
        
        console.log('Modal içinde ürün arama başlatılıyor:', aramaTerim);
        
        // URL'yi mevcut sayfanın yolunu kullanarak tam olarak belirtelim
        const currentPath = window.location.pathname;
        fetch(`${currentPath}?urun_ara=1&q=${encodeURIComponent(aramaTerim)}`)
            .then(response => {
                console.log('Modal arama yanıtı alındı, durum kodu:', response.status);
                // Önce yanıtı text olarak alıp içeriğini kontrol edelim
                return response.text();
            })
            .then(text => {
                console.log('Yanıt içeriği:', text.substring(0, 200)); // İlk 200 karakteri göster
                
                try {
                    // Text'i JSON'a çevirmeyi deneyelim
                    const data = JSON.parse(text);
                    
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
                        
                        // Ürün bilgilerini daha detaylı gösterelim
                        const alis = parseFloat(urun.ALIS_FIYAT || 0).toFixed(2);
                        const satis = parseFloat(urun.SATIS_FIYAT || 0).toFixed(2);
                        const miktar = parseFloat(urun.MIKTAR || 0).toFixed(2);
                        
                        button.innerHTML = `
                            <div class="d-flex w-100 justify-content-between">
                                <h6 class="mb-1">${urun.KOD} - ${urun.ADI}</h6>
                                <small>Stok: ${miktar} ${urun.BIRIM}</small>
                            </div>
                            <p class="mb-1 small">Alış: ${alis} TL | Satış: ${satis} TL</p>
                        `;
                        
                        button.dataset.id = urun.ID;
                        button.dataset.kod = urun.KOD;
                        button.dataset.ad = urun.ADI;
                        button.dataset.fiyat = urun.ALIS_FIYAT || 0; // Alış fiyatını kullan
                        button.dataset.birim = urun.BIRIM || '';
                        
                        button.addEventListener('click', function() {
                            urunSec(urun.ID, urun.KOD, urun.ADI, urun.ALIS_FIYAT || 0, urun.BIRIM || '');
                            bootstrap.Modal.getInstance(document.getElementById('urunSecModal')).hide();
                            document.getElementById('modalUrunArama').value = '';
                        });
                        
                        urunListesiDiv.appendChild(button);
                    });
                } catch (e) {
                    console.error('JSON ayrıştırma hatası:', e);
                    document.getElementById('modalUrunListesi').innerHTML = '<div class="list-group-item text-danger">Sunucu yanıtı geçerli JSON formatında değil. Lütfen yönetici ile iletişime geçin.</div>';
                }
            })
            .catch(error => {
                console.error('Arama hatası:', error);
                document.getElementById('modalUrunListesi').innerHTML = '<div class="list-group-item text-danger">Arama sırasında bir hata oluştu: ' + error.message + '</div>';
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