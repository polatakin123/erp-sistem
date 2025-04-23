<?php
// Basit hata raporlama kullan
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Oturum zaman aşımı süresini uzat
ini_set('max_execution_time', 300); // 5 dakika
session_write_close(); // Oturum kilidini serbest bırak

require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// CSS dosyasını sayfaya dahil et
$customCSS = '<link href="../../assets/css/search-component.css" rel="stylesheet">';

// Carileri getir
$query = "SELECT ID, KOD, ADI as unvan FROM cari ORDER BY ADI";
$stmt = $db->query($query);
$cariler = $stmt->fetchAll(PDO::FETCH_ASSOC);


// Depo değerleri için varsayılan bir değer kullanalım
$depolar = [
    ['ID' => 1, 'ADI' => 'Ana Depo']
];

// Para birimleri için
$dovizler = [
    ['ID' => 1, 'KOD' => 'TRY', 'ADI' => 'Türk Lirası'],
    ['ID' => 2, 'KOD' => 'USD', 'ADI' => 'Amerikan Doları'],
    ['ID' => 3, 'KOD' => 'EUR', 'ADI' => 'Euro']
];

// Şubeler için
$subeler = [
    ['ID' => 1, 'ADI' => 'Merkez Şube']
];

// İrsaliye numarası oluştur
$query = "SELECT MAX(CAST(SUBSTRING(FISNO, 3) AS UNSIGNED)) as max_no FROM stk_fis WHERE FISNO LIKE 'AI%'";
$stmt = $db->query($query);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
$next_no_value = ($result['max_no'] ?? 0) + 1;
$irsaliye_no = 'AI' . str_pad($next_no_value, 6, '0', STR_PAD_LEFT);

// Oluşturulan irsaliye numarasının benzersiz olup olmadığını kontrol et
$check_query = "SELECT COUNT(*) FROM stk_fis WHERE FISNO = ?";
$check_stmt = $db->prepare($check_query);
$check_stmt->execute([$irsaliye_no]);
$exists = $check_stmt->fetchColumn();

// Eğer bu numara zaten kullanılıyorsa, bir sonraki numarayı kullan
while ($exists > 0) {
    $next_no_value++;
    $irsaliye_no = 'AI' . str_pad($next_no_value, 6, '0', STR_PAD_LEFT);
    $check_stmt->execute([$irsaliye_no]);
    $exists = $check_stmt->fetchColumn();
}

// Form gönderildiğinde
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    try {
        // Cari ID'sinin doğru şekilde gönderildiğinden emin ol
        if (empty($_POST['cari_id'])) {
            throw new Exception("Lütfen bir cari seçiniz.");
        }
        
        // İrsaliye numarasını güncel ve benzersiz olarak oluştur
        $query = "SELECT MAX(CAST(SUBSTRING(FISNO, 3) AS UNSIGNED)) as max_no FROM stk_fis WHERE FISNO LIKE 'AI%'";
        $stmt = $db->query($query);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $next_no_value = ($result['max_no'] ?? 0) + 1;
        $irsaliye_no = 'AI' . str_pad($next_no_value, 6, '0', STR_PAD_LEFT);
        
        // İrsaliye numarasının benzersiz olup olmadığını kontrol et
        $check_query = "SELECT COUNT(*) FROM stk_fis WHERE FISNO = ?";
        $check_stmt = $db->prepare($check_query);
        $check_stmt->execute([$irsaliye_no]);
        $exists = $check_stmt->fetchColumn();
        
        // Eğer bu numara zaten kullanılıyorsa, yeni otomatik numara oluştur
        while ($exists > 0) {
            $next_no_value++;
            $irsaliye_no = 'AI' . str_pad($next_no_value, 6, '0', STR_PAD_LEFT);
            $check_stmt->execute([$irsaliye_no]);
            $exists = $check_stmt->fetchColumn();
        }
        
        // Debug için
        $debug_info = [];
        $debug_info['post_data'] = $_POST;
        $debug_info['irsaliye_no'] = $irsaliye_no;
        
        $db->beginTransaction();

        // Debugging için POST değerlerini kontrol et
        error_log("POST verileri: " . print_r($_POST, true));

        // İrsaliye başlık bilgilerini kaydet (stk_fis tablosu)
        $query = "INSERT INTO stk_fis (
                    BOLUMID, TIP, FISNO, FISTAR, FISSAAT, CARIID, DEPOID, FATFISID,
                    STOKTOPLAM, HIZMETTOPLAM, KALEMISKTOPLAM, KALEMKDVTOPLAM, 
                    ISKORAN1, ISKTUTAR1, ISKORAN2, ISKTUTAR2,
                    ARATOPLAM, FISKDVTUTARI, GENELTOPLAM, CARIADI, IPTAL, FATURALANDI, 
                    NOTLAR, SUBEID, KUR_RAPOR, GENELTOPLAM_RAPOR, KDVDAHIL, KDVDAHILISK,
                    DOVIZUSETIP, OTPLANID, ONODEMELISIPARIS, OTVTOPLAMI, BASILDI,
                    KAMPANYA_KALEMISKTOPLAM, KAMPANYA_ISKTUTAR, TYPEID, HS_FIS_UUID
                ) VALUES (
                    :bolumid, :tip, :fisno, :tarih, NOW(), :cari_id, :depo_id, :fatfisid,
                    :toplam_tutar, :hizmet_toplam, :kalem_isk_toplam, :kalem_kdv_toplam,
                    :iskonto_oran1, :iskonto_tutar1, :iskonto_oran2, :iskonto_tutar2,
                    :ara_toplam, :fis_kdv_tutari, :genel_toplam, :cari_adi, :iptal, :faturalandi,
                    :notlar, :sube_id, :kur_rapor, :genel_toplam_rapor, :kdv_dahil, :kdv_dahil_iskonto,
                    :doviz_use_tip, :ot_plan_id, :on_odemeli_siparis, :otv_toplami, :basildi,
                    :kampanya_kalem_isk_toplam, :kampanya_isk_tutar, :type_id, :hs_fis_uuid
                )";
        
        // SQL sorgusu ve parametreleri debug bilgisine ekle
        $debug_info['stk_fis_query'] = $query;

        // Cari adını alalım
        $stmt_cari = $db->prepare("SELECT ADI FROM cari WHERE ID = ?");
        $stmt_cari->execute([$_POST['cari_id']]);
        $cari_adi = $stmt_cari->fetchColumn();
        
        if (!$cari_adi) {
            throw new Exception("Seçili cari bulunamadı (ID: " . $_POST['cari_id'] . ")");
        }
        
        // Toplam KDV tutarını hesapla
        $kdv_tutari = 0;
        if (isset($_POST['kalem_kdv_tutari']) && is_array($_POST['kalem_kdv_tutari'])) {
            foreach ($_POST['kalem_kdv_tutari'] as $tutar) {
                $kdv_tutari += floatval($tutar);
            }
        }

        // Genel iskonto değerleri
        $iskonto_oran1 = isset($_POST['iskonto_oran']) ? floatval($_POST['iskonto_oran']) : 0;
        $iskonto_tutar1 = isset($_POST['iskonto_tutar']) ? floatval($_POST['iskonto_tutar']) : 0;
        
        // UUID oluştur
        $uuid = sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',
            mt_rand(0, 0xffff), mt_rand(0, 0xffff),
            mt_rand(0, 0xffff),
            mt_rand(0, 0x0fff) | 0x4000,
            mt_rand(0, 0x3fff) | 0x8000,
            mt_rand(0, 0xffff), mt_rand(0, 0xffff), mt_rand(0, 0xffff)
        );
        
        // KDV dahil mi?
        $kdv_dahil = isset($_POST['kdv_dahil']) ? 1 : 0;
        
        // Genel toplam (ara toplam + kdv tutarı veya direkt toplam)
        $ara_toplam = floatval($_POST['toplam_tutar']) - $kdv_tutari;
        $genel_toplam = floatval($_POST['toplam_tutar']);
        
        // Debug için parametreleri listeyelim
        $stk_fis_params = [
            'bolumid' => 1, // Sabit değer
            'tip' => '10', // Sabit değer
            'fisno' => $irsaliye_no,
            'tarih' => $_POST['tarih'],
            'cari_id' => $_POST['cari_id'],
            'depo_id' => $_POST['depo_id'],
            'fatfisid' => -1, // Sabit değer
            'toplam_tutar' => $_POST['toplam_tutar'],
            'hizmet_toplam' => 0, // Varsayılan
            'kalem_isk_toplam' => 0, // İskonto toplamı
            'kalem_kdv_toplam' => $kdv_tutari,
            'iskonto_oran1' => $iskonto_oran1,
            'iskonto_tutar1' => $iskonto_tutar1,
            'iskonto_oran2' => 0, // İkinci iskonto
            'iskonto_tutar2' => 0, // İkinci iskonto
            'ara_toplam' => $ara_toplam,
            'fis_kdv_tutari' => $kdv_tutari,
            'genel_toplam' => $genel_toplam,
            'cari_adi' => $cari_adi,
            'iptal' => 0, // İptal değil
            'faturalandi' => 0, // Faturalanmadı
            'notlar' => $_POST['notlar'] ?? '',
            'sube_id' => $_POST['sube_id'] ?? 1,
            'kur_rapor' => 1, // TL için kur
            'genel_toplam_rapor' => $genel_toplam, // Raporlama tutarı
            'kdv_dahil' => $kdv_dahil,
            'kdv_dahil_iskonto' => $kdv_dahil, // İskontoda KDV dahil mi
            'doviz_use_tip' => $_POST['doviz'] ?? 'TL',
            'ot_plan_id' => $_POST['ot_plan_id'] ?? 1, // Ödeme planı ID
            'on_odemeli_siparis' => isset($_POST['on_odemeli_siparis']) ? 1 : 0, // Ön ödemeli sipariş
            'otv_toplami' => 0, // ÖTV toplamı
            'basildi' => 0, // Basılmadı
            'kampanya_kalem_isk_toplam' => 0, // Kampanya iskontosu
            'kampanya_isk_tutar' => 0, // Kampanya iskontosu
            'type_id' => '10', // Type ID
            'hs_fis_uuid' => $uuid // UUID oluştur
        ];
        
        $debug_info['stk_fis_params'] = $stk_fis_params;
        
        // Debug için parametre sayılarını kontrol edelim
        preg_match_all('/:(\w+)/', $query, $matches);
        $debug_info['placeholders'] = $matches[1];
        $debug_info['placeholder_count'] = count($matches[1]);
        $debug_info['params_count'] = count($stk_fis_params);

        try {
            $stmt = $db->prepare($query);
            $stmt->execute($stk_fis_params);
            $irsaliye_id = $db->lastInsertId();
            $debug_info['irsaliye_id'] = $irsaliye_id;
        } catch (PDOException $e) {
            $debug_info['stk_fis_error'] = $e->getMessage();
            throw $e;
        }

        // İrsaliye kalemlerini kaydet (stk_fis_har tablosu)
        $query_har = "INSERT INTO stk_fis_har (
                    SIRANO, BOLUMID, FISTIP, STKFISID, FISTAR, ISLEMTIPI,
                    KARTTIPI, KARTID, MIKTAR, BIRIMID, FIYAT, TUTAR,
                    KDVORANI, KDVTUTARI, CARIID, DEPOID, SUBEID, FATSIRANO
                ) VALUES (
                    :sirano, 1, '10', :irsaliye_id, :tarih, 'Giriş',
                    'S', :urun_id, :miktar, :birim_id, :birim_fiyat, :toplam_tutar,
                    :kdv_orani, :kdv_tutari, :cari_id, :depo_id, 1, :fatsirano
                )";
        
        // Debug bilgilerine ikinci sorguyu da ekleyelim
        $debug_info['stk_fis_har_query'] = $query_har;
        
        try {
            $stmt = $db->prepare($query_har);
            
            $debug_info['kalem_sayisi'] = isset($_POST['urun_id']) ? count($_POST['urun_id']) : 0;
            
            if (isset($_POST['urun_id']) && is_array($_POST['urun_id'])) {
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
                            'kdv_orani' => $_POST['kdv_orani'][$key],
                            'kdv_tutari' => $_POST['kdv_tutari'][$key],
                            'cari_id' => $_POST['cari_id'],
                            'depo_id' => $_POST['depo_id'],
                            'fatsirano' => $sirano // FATSIRANO, SIRANO ile aynı değeri alacak
                        ]);
                    }
                }
            } else {
                $debug_info['urun_id_error'] = 'urun_id parametresi bulunamadı veya dizi değil';
            }
        } catch (PDOException $e) {
            $debug_info['stk_fis_har_error'] = $e->getMessage();
            throw $e;
        }

        $db->commit();
        
        // Hata ayıklama için ilerleme durumunu kaydedelim
        error_log("İrsaliye başarıyla kaydedildi. ID: " . $irsaliye_id);
        
        // Tüm çıktı önbelleğini temizleyelim
        if (ob_get_level()) {
            ob_end_clean();
        }
        
        // Önbellek başlıklarını ayarlayalım - önbellek sorunlarını önler
        header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
        header('Cache-Control: post-check=0, pre-check=0', false);
        header('Pragma: no-cache');
        
        // Güvenli yönlendirme için hem META hem JavaScript kullanalım
        // Benzersiz bir parametre ekleyerek yönlendirme döngüsünü engelleyelim
        $redirectUrl = "irsaliye_listesi.php?success=1&id=" . $irsaliye_id . "&t=" . time();
        
        echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="2;url=' . $redirectUrl . '">
    <title>İşlem Başarılı</title>
    <link href="../../assets/bootstrap/css/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-light">
    <div class="container mt-5">
        <div class="card shadow">
            <div class="card-body text-center">
                <div class="mb-4">
                    <i class="fas fa-check-circle text-success" style="font-size: 48px;"></i>
                </div>
                <h3 class="card-title text-success">İrsaliye Başarıyla Kaydedildi</h3>
                <p class="card-text">İrsaliye listesine yönlendiriliyorsunuz...</p>
                <div class="mt-4">
                    <a href="' . $redirectUrl . '" class="btn btn-primary">İrsaliye Listesine Git</a>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    // 2 saniye sonra yönlendirmeyi JavaScript ile de yapalım
    setTimeout(function() {
        window.location.href = "' . $redirectUrl . '";
    }, 2000);
    </script>
</body>
</html>';
        exit;
    } catch (Exception $e) {
        $db->rollBack();
        $error = "Hata oluştu: " . $e->getMessage();
        
        // Hata ayıklama bilgisi
        error_log("İrsaliye oluşturma hatası: " . $e->getMessage());
        
        // Debug bilgisini ekrana yazdır
        if (isset($debug_info)) {
            echo '<div class="alert alert-danger">' . $error . '</div>';
            echo '<div class="card p-3 mb-3">';
            echo '<h5>Debug Bilgisi:</h5>';
            echo '<pre>' . print_r($debug_info, true) . '</pre>';
            echo '</div>';
        }
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

// AJAX isteği - Cari Arama
if (isset($_GET['cari_ara']) && !empty($_GET['q'])) {
    try {
        $arama = $_GET['q'];
        // Artık alan belirtilmesine gerek yok, her iki alanda arama yapılacak
        
        // SQL Injection koruması için parametreyi hazırla
        $arama = "%" . str_replace(['%', '_'], ['\%', '\_'], $arama) . "%";
        
        // Hem KOD hem de ADI alanlarında arama yap
        $query = "SELECT ID, KOD, ADI FROM cari WHERE KOD LIKE ? OR ADI LIKE ? ORDER BY KOD LIMIT 10";
        $stmt = $db->prepare($query);
        $stmt->execute([$arama, $arama]);
        $sonuclar = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        header('Content-Type: application/json');
        echo json_encode($sonuclar);
    } catch (Exception $e) {
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
                        <input type="text" class="form-control" name="irsaliye_no" value="<?php echo $irsaliye_no; ?>" readonly>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Tarih</label>
                        <input type="date" class="form-control" name="tarih" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    <div class="col-md-4">
                        <label class="form-label">Cari <span class="text-danger">*</span></label>
                        <div class="search-container">
                            <div class="input-group">
                                <input type="text" class="form-control" id="cari_input" placeholder="Cari kodu veya adı ile ara..." required>
                                <button class="btn btn-outline-secondary" type="button" id="cari_ara_btn">
                                    <i class="fas fa-search"></i>
                                </button>
                            </div>
                            <div class="search-loading" id="cari_loading"><i class="fas fa-spinner fa-spin"></i></div>
                            <div class="search-results" id="cari_sonuclari"></div>
                            <input type="hidden" name="cari_id" id="cari_id_hidden" required>
                        </div>
                        <div class="form-text text-muted" id="cari_secim_durum">Henüz cari seçilmedi</div>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Giriş Deposu</label>
                        <select class="form-select" name="depo_id" required>
                            <option value="">Seçiniz</option>
                            <?php foreach ($depolar as $depo): ?>
                            <option value="<?php echo $depo['ID']; ?>" <?php echo ($depo['ID'] == 1) ? 'selected' : ''; ?>><?php echo htmlspecialchars($depo['ADI']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <label class="form-label">Para Birimi</label>
                        <select class="form-select" name="doviz" required>
                            <?php foreach ($dovizler as $doviz): ?>
                            <option value="<?php echo $doviz['KOD']; ?>" <?php echo ($doviz['KOD'] == 'TRY') ? 'selected' : ''; ?>><?php echo htmlspecialchars($doviz['ADI']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Şube</label>
                        <select class="form-select" name="sube_id" required>
                            <?php foreach ($subeler as $sube): ?>
                            <option value="<?php echo $sube['ID']; ?>" selected><?php echo htmlspecialchars($sube['ADI']); ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">İskonto Oranı (%)</label>
                        <input type="number" class="form-control" name="iskonto_oran" value="0" min="0" max="100" step="0.01">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">İskonto Tutarı</label>
                        <input type="number" class="form-control" name="iskonto_tutar" value="0" min="0" step="0.01">
                    </div>
                </div>

                <div class="row mb-3">
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kdv_dahil" id="kdv_dahil" value="1">
                            <label class="form-check-label" for="kdv_dahil">
                                KDV Dahil
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="kdv_dahil_isk" id="kdv_dahil_isk" value="1">
                            <label class="form-check-label" for="kdv_dahil_isk">
                                İskonto KDV Dahil
                            </label>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Ödeme Planı</label>
                        <select class="form-select" name="ot_plan_id" required>
                            <option value="1" selected>Peşin</option>
                            <option value="2">Vadeli</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="on_odemeli_siparis" id="on_odemeli_siparis" value="1">
                            <label class="form-check-label" for="on_odemeli_siparis">
                                Ön Ödemeli Sipariş
                            </label>
                        </div>
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
                                <th>KDV Oranı</th>
                                <th>KDV Tutarı</th>
                                <th>Toplam</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody id="kalemlerBody">
                            <!-- Kalemler JavaScript ile eklenecek -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="6" class="text-end"><strong>Ara Toplam:</strong></td>
                                <td colspan="2">
                                    <input type="number" class="form-control" id="araToplam" step="0.01" readonly>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-end"><strong>İskonto Tutarı:</strong></td>
                                <td colspan="2">
                                    <input type="number" class="form-control" id="iskontoToplamı" step="0.01" readonly>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-end"><strong>KDV Toplam:</strong></td>
                                <td colspan="2">
                                    <input type="number" class="form-control" id="kdvToplam" step="0.01" readonly>
                                </td>
                                <td></td>
                            </tr>
                            <tr>
                                <td colspan="6" class="text-end"><strong>Genel Toplam:</strong></td>
                                <td colspan="2">
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

    // Cari arama elementlerini seçelim
    const cariInput = document.getElementById('cari_input');
    const cariAraBtn = document.getElementById('cari_ara_btn');
    const cariSonuclar = document.getElementById('cari_sonuclari');
    const cariLoading = document.getElementById('cari_loading');
    const cariIdHidden = document.getElementById('cari_id_hidden');

    // Debounce fonksiyonu - belirli bir süre beklemeden çağrıları önler
    function debounce(func, wait) {
        let timeout;
        return function() {
            const context = this;
            const args = arguments;
            clearTimeout(timeout);
            timeout = setTimeout(function() {
                func.apply(context, args);
            }, wait);
        };
    }

    // Cari arama - tuş vuruşunda
    cariInput.addEventListener('input', debounce(function() {
        if (this.value.trim().length >= 2) {
            cariAra();
        } else {
            cariSonuclar.style.display = 'none';
        }
    }, 300));
    
    // Arama butonu tıklaması
    cariAraBtn.addEventListener('click', function() {
        if (cariInput.value.trim().length >= 2) {
            cariAra();
        }
    });
    
    // Enter tuşu ile arama
    cariInput.addEventListener('keyup', function(e) {
        if (e.key === 'Enter' && this.value.trim().length >= 2) {
            cariAra();
        }
    });
    
    // Cari arama fonksiyonu
    function cariAra() {
        const aramaTerim = cariInput.value.trim();
        if (aramaTerim.length < 2) {
            alert('Lütfen en az 2 karakter girin.');
            return;
        }
        
        // Yükleniyor gösterimini başlat
        cariLoading.style.display = 'block';
        
        // Sonuçlar alanını göster
        cariSonuclar.style.display = 'block';
        
        // URL'yi mevcut sayfanın yolunu kullanarak belirtelim
        const currentPath = window.location.pathname;
        fetch(`${currentPath}?cari_ara=1&q=${encodeURIComponent(aramaTerim)}`)
            .then(response => response.json())
            .then(data => {
                // Yükleniyor gösterimini kaldır
                cariLoading.style.display = 'none';
                
                cariSonuclar.innerHTML = '';
                
                if (data.error) {
                    cariSonuclar.innerHTML = `<div class="search-error">Hata: ${data.message}</div>`;
                    return;
                }
                
                if (data.length === 0) {
                    cariSonuclar.innerHTML = '<div class="search-no-results">Sonuç bulunamadı.</div>';
                    return;
                }
                
                // Sonuçları göster
                data.forEach(cari => {
                    const div = document.createElement('div');
                    div.className = 'list-group-item list-group-item-action';
                    div.innerHTML = `<div class="search-result-item">
                                        <span class="search-result-code">${cari.KOD}</span>
                                        <span class="search-result-name">${cari.ADI}</span>
                                     </div>`;
                    div.dataset.id = cari.ID;
                    div.dataset.kod = cari.KOD;
                    div.dataset.adi = cari.ADI;
                    
                    div.addEventListener('click', function() {
                        cariSec(this.dataset.id, this.dataset.kod, this.dataset.adi);
                        cariSonuclar.style.display = 'none';
                    });
                    
                    cariSonuclar.appendChild(div);
                });
            })
            .catch(error => {
                // Yükleniyor gösterimini kaldır
                cariLoading.style.display = 'none';
                cariSonuclar.innerHTML = `<div class="search-error">Arama sırasında bir hata oluştu: ${error.message}</div>`;
            });
    }
    
    // Cari seçme fonksiyonu
    function cariSec(id, kod, adi) {
        cariInput.value = `${kod} - ${adi}`;
        cariIdHidden.value = id;
        
        // Cari seçim durumunu güncelle
        document.getElementById('cari_secim_durum').textContent = 'Seçilen cari: ' + kod + ' - ' + adi;
        document.getElementById('cari_secim_durum').className = 'form-text text-success';
        
        // Sonuçları gizle
        cariSonuclar.style.display = 'none';
    }
    
    // Boş alana tıklandığında sonuçları gizle
    document.addEventListener('click', function(e) {
        if (!cariSonuclar.contains(e.target) && e.target !== cariInput && e.target !== cariAraBtn) {
            cariSonuclar.style.display = 'none';
        }
    });

    // Form gönderilmeden önce kontrol
    document.getElementById('irsaliyeForm').addEventListener('submit', function(e) {
        // Cari seçilmiş mi kontrol et
        if (!cariIdHidden.value) {
            e.preventDefault();
            alert('Lütfen bir cari seçin.');
            cariInput.focus();
            return false;
        }
        
        const kalemler = document.querySelectorAll('#kalemlerBody tr');
        
        if (kalemler.length === 0) {
            e.preventDefault();
            alert('Lütfen en az bir ürün ekleyin.');
            return false;
        }
        
        return true;
    });

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
                <select class="form-control kdv-orani" name="kdv_orani[${index}]">
                    <option value="0">%0</option>
                    <option value="1">%1</option>
                    <option value="8">%8</option>
                    <option value="18" selected>%18</option>
                    <option value="20">%20</option>
                </select>
            </td>
            <td>
                <input type="number" class="form-control kalem-kdv-tutari" name="kalem_kdv_tutari[${index}]" step="0.01" value="0" readonly>
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
        const kdvSelect = row.querySelector('.kdv-orani');
        const silBtn = row.querySelector('.kalem-sil');
        
        miktarInput.addEventListener('input', function() {
            hesaplaKalemToplam(index);
        });
        
        fiyatInput.addEventListener('input', function() {
            hesaplaKalemToplam(index);
        });
        
        kdvSelect.addEventListener('change', function() {
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
        const kdvOrani = parseFloat(document.querySelector(`select[name="kdv_orani[${index}]"]`).value) || 0;
        const kdvDahil = document.getElementById('kdv_dahil').checked;
        
        let netToplam, kdvTutari, toplamTutar;
        
        if (kdvDahil) {
            // KDV dahil fiyattan hesapla
            toplamTutar = miktar * birimFiyat;
            netToplam = toplamTutar / (1 + (kdvOrani / 100));
            kdvTutari = toplamTutar - netToplam;
        } else {
            // KDV hariç fiyattan hesapla
            netToplam = miktar * birimFiyat;
            kdvTutari = (netToplam * kdvOrani) / 100;
            toplamTutar = netToplam + kdvTutari;
        }
        
        document.querySelector(`input[name="kalem_toplam[${index}]"]`).value = toplamTutar.toFixed(2);
        document.querySelector(`input[name="kalem_kdv_tutari[${index}]"]`).value = kdvTutari.toFixed(2);
        
        hesaplaGenelToplam();
    }
    
    // KDV dahil değişikliğini izle
    document.getElementById('kdv_dahil').addEventListener('change', function() {
        // Tüm kalemlerin toplamlarını yeniden hesapla
        document.querySelectorAll('#kalemlerBody tr').forEach(function(row) {
            const index = row.id.split('_')[1];
            hesaplaKalemToplam(index);
        });
    });
    
    // İskonto değişikliklerini izle
    document.querySelector('input[name="iskonto_oran"]').addEventListener('input', function() {
        const oran = parseFloat(this.value) || 0;
        let toplamTutar = 0;
        document.querySelectorAll('.kalem-toplam').forEach(input => {
            toplamTutar += parseFloat(input.value) || 0;
        });
        
        const iskontoTutar = (toplamTutar * oran) / 100;
        document.querySelector('input[name="iskonto_tutar"]').value = iskontoTutar.toFixed(2);
        hesaplaGenelToplam();
    });
    
    document.querySelector('input[name="iskonto_tutar"]').addEventListener('input', function() {
        const tutar = parseFloat(this.value) || 0;
        let toplamTutar = 0;
        document.querySelectorAll('.kalem-toplam').forEach(input => {
            toplamTutar += parseFloat(input.value) || 0;
        });
        
        if (toplamTutar > 0) {
            const iskontoOran = (tutar / toplamTutar) * 100;
            document.querySelector('input[name="iskonto_oran"]').value = iskontoOran.toFixed(2);
        }
        hesaplaGenelToplam();
    });
    
    // Genel toplam hesaplama fonksiyonu
    function hesaplaGenelToplam() {
        let araToplam = 0;
        let kdvToplam = 0;
        
        // Tüm kalemlerin toplamını ve KDV toplamını hesapla
        document.querySelectorAll('.kalem-toplam').forEach(input => {
            araToplam += parseFloat(input.value) || 0;
        });
        
        document.querySelectorAll('.kalem-kdv-tutari').forEach(input => {
            kdvToplam += parseFloat(input.value) || 0;
        });
        
        // İskonto tutarını al
        const iskontoTutar = parseFloat(document.querySelector('input[name="iskonto_tutar"]').value) || 0;
        
        // Genel toplam hesapla
        const genelToplam = araToplam - iskontoTutar;
        
        // Formdaki değerleri güncelle
        document.getElementById('araToplam').value = araToplam.toFixed(2);
        document.getElementById('iskontoToplamı').value = iskontoTutar.toFixed(2);
        document.getElementById('kdvToplam').value = kdvToplam.toFixed(2);
        document.getElementById('genelToplam').value = genelToplam.toFixed(2);
    }
    
    // Satır silme fonksiyonu
    function satirSil(index, id) {
        const row = document.getElementById(`kalem_${index}`);
        row.remove();
        urunListesi.delete(parseInt(id));
        hesaplaGenelToplam();
    }
});
</script>

<?php include_once '../../includes/footer.php'; ?> 