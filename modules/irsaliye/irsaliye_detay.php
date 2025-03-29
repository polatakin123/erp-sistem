<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';
require_once '../../config/helpers.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

// İrsaliye bilgilerini getir
$query = "SELECT f.*, c.ADI as cari_unvan, a.ADRES1 as cari_adres, 
         CASE 
           WHEN f.IPTAL = 1 THEN 'İptal' 
           WHEN f.FATURALANDI = 1 THEN 'Faturalandı' 
           ELSE 'Beklemede' 
         END as durum
         FROM stk_fis f 
         LEFT JOIN cari c ON f.CARIID = c.ID 
         LEFT JOIN adres a ON c.ID = a.KARTID
         WHERE f.ID = ? AND f.TIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE', '20')";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$irsaliye) {
    header('Location: irsaliye_listesi.php');
    exit;
}

// İrsaliye kalemlerini getir
$query = "SELECT h.*, s.KOD as urun_kod, s.ADI as urun_adi 
          FROM stk_fis_har h 
          LEFT JOIN stok s ON h.KARTID = s.ID
          WHERE h.STKFISID = ?
          ORDER BY h.SIRANO, h.ID ASC";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$all_kalemler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Debug amaçlı - Tüm kalem verilerini göster
$debug_mode = false; // Debug modu açık/kapalı

// İrsaliye kalemlerini doğru şekilde gruplayalım
$processed_kalemler = [];
$seen_indexes = [];
$filtered_out = []; // Filtrelenen kayıtlar

foreach ($all_kalemler as $key => $kalem) {
    // Her kalem için eşsiz tanımlayıcı oluştur
    $identifier = $kalem['KARTID'] . '_' . $kalem['SIRANO'];
    
    if (!isset($seen_indexes[$identifier])) {
        // Bu kombinasyon ilk kez görüldü, kalemin birim bilgisini alıp listeye ekleyelim
        $kalem['birim'] = getBirim($kalem['BIRIMID']);
        $processed_kalemler[] = $kalem;
        $seen_indexes[$identifier] = $kalem['ID']; // ID'yi saklayalım
    } else {
        // Bu kayıt filtrelendi, debug bilgisi için saklayalım
        $filtered_out[] = [
            'id' => $kalem['ID'],
            'kartid' => $kalem['KARTID'],
            'sirano' => $kalem['SIRANO'],
            'identifier' => $identifier,
            'duplicate_of' => $seen_indexes[$identifier]
        ];
    }
}

$kalemler = $processed_kalemler;

// Debug bilgisi
if ($debug_mode) {
    echo '<div class="alert alert-info" style="margin-top: 20px;">';
    echo '<h4>Debug Bilgileri</h4>';
    echo '<p><strong>Toplam sorgu sonucu:</strong> ' . count($all_kalemler) . ' kayıt</p>';
    echo '<p><strong>İşlenmiş kalemler:</strong> ' . count($processed_kalemler) . ' kayıt</p>';
    echo '<p><strong>Filtrelenen kayıtlar:</strong> ' . count($filtered_out) . ' kayıt</p>';
    
    if (count($filtered_out) > 0) {
        echo '<h5>Filtrelenen Kayıtlar:</h5>';
        echo '<table class="table table-sm table-bordered">';
        echo '<thead><tr><th>ID</th><th>Ürün ID</th><th>Sıra No</th><th>Tanımlayıcı</th><th>Şunun tekrarı</th></tr></thead>';
        echo '<tbody>';
        foreach ($filtered_out as $item) {
            echo '<tr>';
            echo '<td>' . $item['id'] . '</td>';
            echo '<td>' . $item['kartid'] . '</td>';
            echo '<td>' . $item['sirano'] . '</td>';
            echo '<td>' . $item['identifier'] . '</td>';
            echo '<td>' . $item['duplicate_of'] . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
    }
    
    echo '<h5>İşlenmiş Kalemler:</h5>';
    echo '<table class="table table-sm table-bordered">';
    echo '<thead><tr><th>ID</th><th>Ürün ID</th><th>Ürün Kod</th><th>Ürün Adı</th><th>Sıra No</th><th>Fiyat</th></tr></thead>';
    echo '<tbody>';
    foreach ($processed_kalemler as $item) {
        echo '<tr>';
        echo '<td>' . $item['ID'] . '</td>';
        echo '<td>' . $item['KARTID'] . '</td>';
        echo '<td>' . $item['urun_kod'] . '</td>';
        echo '<td>' . $item['urun_adi'] . '</td>';
        echo '<td>' . $item['SIRANO'] . '</td>';
        echo '<td>' . $item['FIYAT'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    
    echo '<h5>Ham Veriler:</h5>';
    echo '<table class="table table-sm table-bordered">';
    echo '<thead><tr><th>ID</th><th>Ürün ID</th><th>Ürün Kod</th><th>Ürün Adı</th><th>Sıra No</th><th>Fiyat</th></tr></thead>';
    echo '<tbody>';
    foreach ($all_kalemler as $item) {
        echo '<tr>';
        echo '<td>' . $item['ID'] . '</td>';
        echo '<td>' . $item['KARTID'] . '</td>';
        echo '<td>' . $item['urun_kod'] . '</td>';
        echo '<td>' . $item['urun_adi'] . '</td>';
        echo '<td>' . $item['SIRANO'] . '</td>';
        echo '<td>' . $item['FIYAT'] . '</td>';
        echo '</tr>';
    }
    echo '</tbody></table>';
    echo '</div>';
}

// Debug bilgisi
if (empty($kalemler)) {
    echo '<div class="alert alert-warning">İrsaliye kalemi bulunamadı. İrsaliye ID: '.$irsaliye_id.'</div>';
    
    // İlgili tabloları kontrol et
    echo '<div class="alert alert-info">';
    echo '<h5>Hata Ayıklama Bilgileri:</h5>';
    
    // stk_fis tablosundaki kayıt kontrolü
    $check_query = "SELECT ID, TIP, FISNO FROM stk_fis WHERE ID = ?";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$irsaliye_id]);
    $fis_record = $check_stmt->fetch(PDO::FETCH_ASSOC);
    
    echo '<p><strong>İrsaliye Kaydı:</strong> ';
    if ($fis_record) {
        echo 'Bulundu (ID: '.$fis_record['ID'].', TIP: '.$fis_record['TIP'].', FISNO: '.$fis_record['FISNO'].')';
    } else {
        echo 'Bulunamadı';
    }
    echo '</p>';
    
    // stk_fis_har tablosunda herhangi bir kayıt var mı?
    $check_query = "SELECT * FROM stk_fis_har WHERE STKFISID = ? ORDER BY ID";
    $check_stmt = $db->prepare($check_query);
    $check_stmt->execute([$irsaliye_id]);
    $har_records = $check_stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo '<p><strong>Bu İrsaliyeye Ait Hareket Kayıtları:</strong></p>';
    echo '<pre>';
    foreach ($har_records as $index => $record) {
        echo 'Kayıt #'.($index+1).': ID='.$record['ID'].', KARTID='.$record['KARTID'].', MIKTAR='.$record['MIKTAR'].', FIYAT='.$record['FIYAT'].', TUTAR='.$record['TUTAR'].'<br>';
    }
    echo '</pre>';
    
    echo '</div>';
}

// Eğer kalemler varsa, ürün ve birim bilgilerini ayrı sorgularla getirelim
if (!empty($kalemler)) {
    $final_kalemler = array(); // Son kalemler için yeni bir dizi
    
    foreach ($kalemler as $kalem) {
        // Ürün bilgilerini getir
        if (!empty($kalem['KARTID'])) {
            $query = "SELECT KOD as urun_kod, ADI as urun_adi FROM stok WHERE ID = ?";
            $stmt = $db->prepare($query);
            $stmt->execute([$kalem['KARTID']]);
            $urun = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($urun) {
                $kalem['urun_kod'] = $urun['urun_kod'];
                $kalem['urun_adi'] = $urun['urun_adi'];
            } else {
                $kalem['urun_kod'] = 'Bulunamadı';
                $kalem['urun_adi'] = 'Bulunamadı';
            }
        } else {
            $kalem['urun_kod'] = '-';
            $kalem['urun_adi'] = '-';
        }
        
        // Birim bilgisi zaten yukarıda fonksiyon ile alındı
        $final_kalemler[] = $kalem;
    }
    
    // Kalemleri yeni diziye atayalım
    $kalemler = $final_kalemler;
    
    // Debug için son durum
    if ($debug_mode) {
        echo '<div class="alert alert-success">Son işlemlerden sonra kalemler:</div>';
        echo '<pre>';
        print_r($kalemler);
        echo '</pre>';
    }
}
?>

<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>İrsaliye Detay - ERP Sistemi</title>
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
        /* Header ve navigasyon stilleri */
        .navbar {
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background-color: #fff;
        }
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
        }
        .nav-link {
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
        }
        .nav-link i {
            margin-right: 0.5rem;
        }
        .navbar-nav .dropdown-menu {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
        }
        /* İçerik alanı */
        main {
            margin-top: 110px; /* Navbar + Arama formu yüksekliği */
            padding: 1rem;
        }
        .badge-lg {
            font-size: 1rem;
            padding: 0.5rem 1rem;
        }
        .table-info th {
            background-color: #f8f9fa;
        }
        .table td, .table th {
            padding: 0.5rem;
            vertical-align: middle;
        }
        .form-control-sm {
            height: calc(1.5em + 0.5rem + 2px);
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
        .layout-fullwidth {
            max-width: 100%;
        }
        .card {
            margin-bottom: 10px;
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
                
                <!-- Sağdaki işlem butonları -->
                <div class="d-flex">
                    <!-- İşlemler Dropdown -->
                    <div class="dropdown me-2">
                        <button class="btn btn-outline-primary dropdown-toggle" type="button" id="islemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog"></i> İşlemler
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="islemlerDropdown">
                            <li><a class="dropdown-item" href="irsaliye_listesi.php"><i class="fas fa-arrow-left"></i> Listeye Dön</a></li>
                            <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                            <li><a class="dropdown-item" href="irsaliye_duzenle.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-edit"></i> Düzenle</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="irsaliye_onayla.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-check"></i> Onayla</a></li>
                            <li><a class="dropdown-item" href="irsaliye_iptal.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-times"></i> İptal Et</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="javascript:window.print();"><i class="fas fa-print"></i> Yazdır</a></li>
                        </ul>
                    </div>
                    
                    <!-- Ekstra İşlemler Dropdown -->
                    <div class="dropdown">
                        <button class="btn btn-outline-secondary dropdown-toggle" type="button" id="ekstraIslemlerDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-ellipsis-h"></i> Diğer
                        </button>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="ekstraIslemlerDropdown">
                            <li><a class="dropdown-item" href="irsaliye_kopyala.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-copy"></i> Kopyalayarak Yeni Oluştur</a></li>
                            <li><a class="dropdown-item" href="irsaliye_mail.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-envelope"></i> E-posta Gönder</a></li>
                            <?php if ($irsaliye['durum'] == 'Beklemede'): ?>
                            <li><a class="dropdown-item" href="irsaliye_faturalastir.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-file-invoice"></i> Faturalaştır</a></li>
                            <?php endif; ?>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="irsaliye_pdf.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-file-pdf"></i> PDF İndir</a></li>
                            <li><a class="dropdown-item" href="irsaliye_excel.php?id=<?php echo $irsaliye_id; ?>"><i class="fas fa-file-excel"></i> Excel İndir</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>

    <div class="container-fluid layout-fullwidth">
        <main>
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center mb-2 border-bottom">
                <h1 class="h2">İrsaliye Detay</h1>
            </div>

            <div class="row g-2">
                <div class="col-md-8">
                    <div class="card shadow-sm">
                        <div class="card-header py-2">
                            <h5 class="mb-0">İrsaliye Bilgileri</h5>
                        </div>
                        <div class="card-body p-2">
                            <div class="row">
                                <div class="col-md-4 mb-2">
                                    <strong>İrsaliye No:</strong> 
                                    <span class="badge bg-secondary"><?php echo htmlspecialchars($irsaliye['FISNO']); ?></span>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <strong>Tarih:</strong> 
                                    <?php echo date('d.m.Y', strtotime($irsaliye['FISTAR'])); ?>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <strong>Depo Kodu:</strong> 
                                    <?php echo htmlspecialchars($irsaliye['DEPOID'] ?? '-'); ?>
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-8 mb-2">
                                    <strong>Cari:</strong> 
                                    <?php echo htmlspecialchars($irsaliye['cari_unvan']); ?>
                                </div>
                                <div class="col-md-4 mb-2">
                                    <strong>Durum:</strong> 
                                    <?php
                                    $durum_class = 'warning';
                                    if ($irsaliye['durum'] == 'Faturalandı') $durum_class = 'success';
                                    if ($irsaliye['durum'] == 'İptal') $durum_class = 'danger';
                                    ?>
                                    <span class="badge bg-<?php echo $durum_class; ?> badge-lg">
                                        <?php echo htmlspecialchars($irsaliye['durum']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (!empty($irsaliye['cari_adres'])): ?>
                            <div class="row">
                                <div class="col-12 mb-2">
                                    <strong>Adres:</strong> 
                                    <?php echo nl2br(htmlspecialchars($irsaliye['cari_adres'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                            <?php if (!empty($irsaliye['NOTLAR'])): ?>
                            <div class="row">
                                <div class="col-12 mb-2">
                                    <strong>Notlar:</strong> 
                                    <?php echo nl2br(htmlspecialchars($irsaliye['NOTLAR'])); ?>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <div class="card shadow-sm">
                        <div class="card-header py-2">
                            <h5 class="mb-0">Özet Bilgiler</h5>
                        </div>
                        <div class="card-body p-2">
                            <div class="list-group">
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Toplam Tutar</h6>
                                        <strong><?php echo number_format($irsaliye['GENELTOPLAM'], 2, ',', '.'); ?> ₺</strong>
                                    </div>
                                </div>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Toplam Kalem Sayısı</h6>
                                        <strong><?php echo count($kalemler); ?></strong>
                                    </div>
                                </div>
                                <div class="list-group-item list-group-item-action">
                                    <div class="d-flex w-100 justify-content-between">
                                        <h6 class="mb-1">Sevk Tarihi</h6>
                                        <strong>
                                            <?php echo $irsaliye['SEVKTAR'] ? date('d.m.Y', strtotime($irsaliye['SEVKTAR'])) : '-'; ?>
                                        </strong>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- İrsaliye Kalemleri Bölümü -->
            <div class="card mt-4">
                <div class="card-header bg-light">
                    <h5 class="card-title mb-0">
                        <i class="fas fa-list-ul"></i> İrsaliye Kalemleri
                    </h5>
                </div>
                <div class="card-body">
                    <?php if (count($kalemler) > 0): ?>
                    <div class="table-responsive">
                        <table class="table table-striped table-hover table-bordered">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 5%">#</th>
                                    <th style="width: 10%">Ürün Kodu</th>
                                    <th style="width: 30%">Ürün Adı</th>
                                    <th style="width: 10%" class="text-end">Miktar</th>
                                    <th style="width: 10%">Birim</th>
                                    <th style="width: 15%" class="text-end">Birim Fiyat</th>
                                    <th style="width: 15%" class="text-end">Toplam Tutar</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php 
                                $toplam_miktar = 0;
                                $toplam_tutar = 0;
                                foreach ($kalemler as $i => $kalem): 
                                    $birim_fiyat = isset($kalem['FIYAT']) ? $kalem['FIYAT'] : 0;
                                    $miktar = isset($kalem['MIKTAR']) ? $kalem['MIKTAR'] : 0;
                                    $tutar = isset($kalem['TUTAR']) ? $kalem['TUTAR'] : 0;
                                    
                                    $toplam_miktar += $miktar;
                                    $toplam_tutar += $tutar;
                                ?>
                                <tr>
                                    <td class="text-center"><?php echo $i + 1; ?></td>
                                    <td><?php echo htmlspecialchars($kalem['urun_kod']); ?></td>
                                    <td><?php echo htmlspecialchars($kalem['urun_adi']); ?></td>
                                    <td class="text-end fw-bold"><?php echo number_format($miktar, 2, ',', '.'); ?></td>
                                    <td><?php echo htmlspecialchars($kalem['birim'] ?? ''); ?></td>
                                    <td class="text-end"><?php echo number_format($birim_fiyat, 2, ',', '.'); ?> ₺</td>
                                    <td class="text-end fw-bold"><?php echo number_format($tutar, 2, ',', '.'); ?> ₺</td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                            <tfoot class="table-light">
                                <tr>
                                    <td colspan="3" class="text-end fw-bold">TOPLAM:</td>
                                    <td class="text-end fw-bold"><?php echo number_format($toplam_miktar, 2, ',', '.'); ?></td>
                                    <td></td>
                                    <td></td>
                                    <td class="text-end fw-bold"><?php echo number_format($toplam_tutar, 2, ',', '.'); ?> ₺</td>
                                </tr>
                            </tfoot>
                        </table>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle me-2"></i> Bu irsaliyeye ait kalem bulunamadı.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        // Bildirimler ve Mesajlar butonları için
        var dropdownElementList = [].slice.call(document.querySelectorAll('.dropdown-toggle'))
        var dropdownList = dropdownElementList.map(function (dropdownToggleEl) {
            return new bootstrap.Dropdown(dropdownToggleEl, {
                hover: false
            })
        })
    });
    </script>
</body>
</html> 