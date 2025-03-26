<?php
// Sayfa yükleme süresini ölçmek için
$baslangic_zamani = microtime(true);

// Performans ölçüm dizisi
$performans = [];

// Hata ayıklama modu
ini_set('display_errors', 0);
error_reporting(0);

// Yönlendirmeleri tamamen devre dışı bırakalım
function custom_redirect($url, $error_message = null) {
    header('Location: ' . $url);
    exit;
}

/**
 * ERP Sistem - Ürün Detay Sayfası
 * 
 * Bu dosya ürün detaylarını görüntüleme işlemlerini gerçekleştirir.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Ürün ID'sini kontrol et
if (!isset($_GET['id']) || empty($_GET['id'])) {
    $_SESSION['error'] = "Geçersiz ürün ID'si.";
    header('Location: index.php');
    exit;
}

$id = (int)$_GET['id'];

// Debug bilgisi
// Devre dışı bırakıldı
// echo "<div style='background:#eeffee; padding:10px; margin-bottom:10px; border:1px solid #ddd;'>";
// echo "Debug: Aranan ürün ID: " . $id . "<br>";
// echo "</div>";

// Stok sorgularının başarılı olduğunu kontrol etmek için
ini_set('display_errors', 0);
error_reporting(0);

// Ürün bilgilerini al
try {
    $urun_sorgu_baslangic = microtime(true);
    
    $stmt = $db->prepare("
        SELECT s.*, 
        s.ADI as name, 
        s.KOD as code, 
        s.OZELGRUP1 as main_category_id,
        s.OZELGRUP2 as sub_category_id,
        s.OZELGRUP3 as shelf_code_id,
        s.OZELGRUP4 as brand_id, 
        s.OZELGRUP5 as vehicle_brand_id, 
        s.OZELGRUP6 as vehicle_model_id, 
        s.OZELGRUP7 as year_range, 
        s.OZELGRUP8 as chassis_id, 
        s.OZELGRUP9 as engine_id, 
        s.OZELGRUP10 as supplier_id, 
        s.DURUM as status, 
        s.ACIKLAMA as description, 
        g.KOD as unit,
        sfA.FIYAT as purchase_price,
        sfS.FIYAT as sale_price,
        kdv.ORAN as tax_rate,
        sum.MIKTAR as current_stock,
        s.MIN_STOK as min_stock,
        s.OZELALAN1 as oem_no,
        s.OZELALAN2 as cross_reference,
        s.OZELALAN3 as dimensions
        FROM stok s
        LEFT JOIN stk_birim sb ON s.ID = sb.STOKID
        LEFT JOIN grup g ON sb.BIRIMID = g.ID
        LEFT JOIN stk_fiyat sfA ON s.ID = sfA.STOKID AND sfA.TIP = 'A'
        LEFT JOIN stk_fiyat sfS ON s.ID = sfS.STOKID AND sfS.TIP = 'S'
        LEFT JOIN kdv ON s.KDVID = kdv.ID
        LEFT JOIN stk_urun_miktar sum ON s.ID = sum.URUN_ID
        WHERE s.ID = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    $urun_sorgu_bitis = microtime(true);
    $performans['urun_bilgileri'] = $urun_sorgu_bitis - $urun_sorgu_baslangic;
    
    // Debug - SQL sorgusunu göster
    // Devre dışı bırakıldı
    // echo "<div style='background:#ffffee; padding:10px; margin-bottom:10px; border:1px solid #ddd;'>";
    // echo "Debug: SQL sorgusu çalıştırıldı<br>";
    // echo "Bulunan kayıt sayısı: " . $stmt->rowCount() . "<br>";
    // echo "</div>";
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Ürün bulunamadı.";
        header('Location: index.php');
        exit;
    }
    
    $urun = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Debug - ürün bilgilerini göster
    // Devre dışı bırakıldı
    // echo "<div style='background:#eeeeff; padding:10px; margin-bottom:10px; border:1px solid #ddd;'>";
    // echo "Debug: Ürün bilgileri:<br><pre>";
    // print_r($urun);
    // echo "</pre></div>";
    
    // Kategori adını getir - Eğer kategori tablosu varsa
    if (!empty($urun['main_category_id'])) {
        try {
            $stmt = $db->prepare("SELECT ID, KOD FROM grup WHERE ID = :cat_id");
            $stmt->bindParam(':cat_id', $urun['main_category_id']);
            $stmt->execute();
            $category = $stmt->fetch(PDO::FETCH_ASSOC);
            if ($category) {
                $urun['category_name'] = $category['KOD'];
            }
        } catch (PDOException $e) {
            echo "<div class='alert alert-danger'>Kategori bilgisi alınırken hata: " . $e->getMessage() . "</div>";
        }
    }
    
    // OZELGRUP değerlerini çek
    $grup_ids = [
        'main_category' => $urun['main_category_id'] ?? null,
        'sub_category' => $urun['sub_category_id'] ?? null,
        'shelf_code' => $urun['shelf_code_id'] ?? null,
        'brand' => $urun['brand_id'] ?? null, 
        'vehicle_brand' => $urun['vehicle_brand_id'] ?? null,
        'vehicle_model' => $urun['vehicle_model_id'] ?? null,
        'chassis' => $urun['chassis_id'] ?? null,
        'engine' => $urun['engine_id'] ?? null,
        'supplier' => $urun['supplier_id'] ?? null
    ];
    
    // Debug - gruplar ID'lerini göster
    // Devre dışı bırakıldı
    // echo "<div style='background:#eeffff; padding:10px; margin-bottom:10px; border:1px solid #ddd;'>";
    // echo "Debug: Grup ID'leri:<br><pre>";
    // print_r($grup_ids);
    // echo "</pre></div>";
    
    // Tek sorguda tüm grup değerlerini çek
    $grup_idleri = array_filter(array_values($grup_ids));
    if (!empty($grup_idleri)) {
        try {
            $grup_sorgu_baslangic = microtime(true);
            
            $placeholders = implode(',', array_fill(0, count($grup_idleri), '?'));
            $stmt = $db->prepare("SELECT ID, KOD FROM grup WHERE ID IN ($placeholders)");
            $stmt->execute($grup_idleri);
            $gruplar = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $grup_sorgu_bitis = microtime(true);
            $performans['grup_bilgileri'] = $grup_sorgu_bitis - $grup_sorgu_baslangic;
            
            // Debug - bulunan grupları göster
            // Devre dışı bırakıldı
            // echo "<div style='background:#eeffff; padding:10px; margin-bottom:10px; border:1px solid #ddd;'>";
            // echo "Debug: Bulunan gruplar (" . count($gruplar) . "):<br><pre>";
            // print_r($gruplar);
            // echo "</pre></div>";
            
            // Grup ID'leri ve kodları eşleştir
            $grup_kodlari = [];
            foreach ($gruplar as $grup) {
                $grup_kodlari[$grup['ID']] = [
                    'kod' => $grup['KOD'],
                    'adi' => $grup['KOD']
                ];
            }
            
            // Debug - grup kodları eşleşmesini göster
            // Devre dışı bırakıldı
            // echo "<div style='background:#eeffff; padding:10px; margin-bottom:10px; border:1px solid #ddd;'>";
            // echo "Debug: Eşleşen grup kodları:<br><pre>";
            // print_r($grup_kodlari);
            // echo "</pre></div>";
            
            // Grup değerlerini ürün dizisine ekle
            foreach ($grup_ids as $key => $id_value) {
                if (!empty($id_value) && isset($grup_kodlari[$id_value])) {
                    $urun[$key] = $grup_kodlari[$id_value]['adi']; // ADI değerini kullan
                    $urun[$key.'_code'] = $grup_kodlari[$id_value]['kod']; // KOD değerini de sakla
                } else {
                    $urun[$key] = '';
                    $urun[$key.'_code'] = '';
                }
            }
        } catch (PDOException $e) {
            echo "<div style='color:red; padding:20px; background:#ffeeee; border:1px solid red;'>";
            echo "<strong>GRUP HATA:</strong> " . $e->getMessage();
            echo "<br><br>SQL: " . $stmt->queryString;
            echo "</div>";
            // Hata olursa grup değerlerini boş ata
            foreach ($grup_ids as $key => $id_value) {
                $urun[$key] = '';
                $urun[$key.'_code'] = '';
            }
        }
    }
    
    // Durum değerini düzenle
    $urun['status'] = ($urun['status'] == 1) ? 'active' : 'passive';
    
    // Ekleyen ve güncelleyen kullanıcı bilgilerini ayrıca al - ama başka sorgularda gerekli değilse almayalım
    $urun['created_user_name'] = '';
    $urun['created_user_surname'] = '';
    $urun['updated_user_name'] = '';
    $urun['updated_user_surname'] = '';
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
    error_log("Ürün detay hatası: " . $e->getMessage() . " - SQL: " . $stmt->queryString);
    header('Location: index.php');
    exit;
}

// Sayfa başlığı
$pageTitle = "Ürün Detayı: " . $urun['name'];

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Ürün Detayı</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Ürün Listesine Dön
            </a>
            <a href="urun_duzenle.php?id=<?php echo $id; ?>" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-edit"></i> Düzenle
            </a>
            <a href="stok_hareketleri.php?urun_id=<?php echo $id; ?>" class="btn btn-sm btn-outline-info">
                <i class="fas fa-exchange-alt"></i> Stok Hareketleri
            </a>
        </div>
    </div>
</div>

<!-- Ürün Bilgileri -->
<div class="row mb-4">
    <div class="col-md-6">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Ürün Bilgileri</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 35%">Stok Kodu:</th>
                        <td><?php echo htmlspecialchars($urun['code']); ?></td>
                    </tr>
                    <tr>
                        <th>Ürün Adı:</th>
                        <td><?php echo displayHtml($urun['name']); ?></td>
                    </tr>
                    <tr>
                        <th>Kategori:</th>
                        <td>
                          <?php 
                            if (!empty($urun['main_category'])) {
                              echo htmlspecialchars($urun['main_category']);
                              if (!empty($urun['sub_category'])) {
                                echo ' / ' . htmlspecialchars($urun['sub_category']);
                              }
                            } else {
                              echo '-';
                            } 
                          ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Marka:</th>
                        <td><?php echo htmlspecialchars($urun['brand'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td><?php echo htmlspecialchars($urun['vehicle_model'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Barkod:</th>
                        <td><?php echo htmlspecialchars($urun['code'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Birim:</th>
                        <td><?php echo htmlspecialchars($urun['unit'] ?? 'Adet'); ?></td>
                    </tr>
                    <tr>
                        <th>Durum:</th>
                        <td>
                            <?php if ($urun['status'] == 'active'): ?>
                                <span class="badge bg-success">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger">Pasif</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Açıklama:</th>
                        <td><?php echo nl2br(htmlspecialchars($urun['description'] ?? '-')); ?></td>
                    </tr>
                </table>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <div class="card shadow h-100">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Stok ve Fiyat Bilgileri</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless">
                    <tr>
                        <th style="width: 35%">Alış Fiyatı:</th>
                        <td>₺<?php echo number_format($urun['purchase_price'] ?? 0, 2, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <th>Satış Fiyatı:</th>
                        <td>₺<?php echo number_format($urun['sale_price'] ?? 0, 2, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <th>KDV Oranı:</th>
                        <td>%<?php echo $urun['tax_rate'] ?? 0; ?></td>
                    </tr>
                    <tr>
                        <th>Mevcut Stok:</th>
                        <td class="<?php echo (($urun['current_stock'] ?? 0) <= ($urun['min_stock'] ?? 0)) ? 'text-danger fw-bold' : ''; ?>">
                            <?php echo number_format($urun['current_stock'] ?? 0, 2, ',', '.'); ?> <?php echo htmlspecialchars($urun['unit'] ?? 'Adet'); ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Stok:</th>
                        <td><?php echo number_format($urun['min_stock'] ?? 0, 2, ',', '.'); ?> <?php echo htmlspecialchars($urun['unit'] ?? 'Adet'); ?></td>
                    </tr>
                    <tr>
                        <th>Ekleme Tarihi:</th>
                        <td>-</td>
                    </tr>
                    <tr>
                        <th>Ekleyen:</th>
                        <td>-</td>
                    </tr>
                    <?php if (!empty($urun['updated_at'])): ?>
                    <tr>
                        <th>Son Güncelleme:</th>
                        <td><?php echo date('d.m.Y H:i', strtotime($urun['updated_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Güncelleyen:</th>
                        <td><?php echo htmlspecialchars(($urun['updated_user_name'] ?? '') . ' ' . ($urun['updated_user_surname'] ?? '')); ?></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Araç ve Parça Bilgileri Kartı -->
<div class="row mb-4">
    <div class="col-md-12">
        <div class="card shadow">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Araç ve Parça Bilgileri</h6>
            </div>
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th style="width: 35%">OEM No:</th>
                                <td>
                                    <?php 
                                    if (!empty($urun['oem_no'])) {
                                        $oem_numbers = preg_split('/\r\n|\r|\n/', $urun['oem_no']);
                                        foreach ($oem_numbers as $oem) {
                                            echo htmlspecialchars(trim($oem)) . '<br>';
                                        }
                                    } else {
                                        echo '-';
                                    }
                                    ?>
                                </td>
                            </tr>
                            <tr>
                                <th>Çapraz Referans:</th>
                                <td><?php echo !empty($urun['cross_reference']) ? nl2br(htmlspecialchars($urun['cross_reference'])) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Ürün Ölçüleri:</th>
                                <td><?php echo !empty($urun['dimensions']) ? htmlspecialchars($urun['dimensions']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Raf Kodu:</th>
                                <td><?php echo !empty($urun['shelf_code']) ? htmlspecialchars($urun['shelf_code']) . ' (' . htmlspecialchars($urun['shelf_code_code']) . ')' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Üretici/Marka:</th>
                                <td><?php echo !empty($urun['brand']) ? htmlspecialchars($urun['brand']) . ' (' . htmlspecialchars($urun['brand_code']) . ')' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Tedarikçi:</th>
                                <td><?php echo !empty($urun['supplier']) ? htmlspecialchars($urun['supplier']) . ' (' . htmlspecialchars($urun['supplier_code']) . ')' : '-'; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th style="width: 35%">Araç Markası:</th>
                                <td><?php echo !empty($urun['vehicle_brand']) ? htmlspecialchars($urun['vehicle_brand']) . ' (' . htmlspecialchars($urun['vehicle_brand_code']) . ')' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Araç Modeli:</th>
                                <td><?php echo !empty($urun['vehicle_model']) ? htmlspecialchars($urun['vehicle_model']) . ' (' . htmlspecialchars($urun['vehicle_model_code']) . ')' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Model Yıl Aralığı:</th>
                                <td><?php echo !empty($urun['year_range']) ? htmlspecialchars($urun['year_range']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Kasa Kodu:</th>
                                <td><?php echo !empty($urun['chassis']) ? htmlspecialchars($urun['chassis']) . ' (' . htmlspecialchars($urun['chassis_code']) . ')' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Motor Kodu:</th>
                                <td><?php echo !empty($urun['engine']) ? htmlspecialchars($urun['engine']) . ' (' . htmlspecialchars($urun['engine_code']) . ')' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Ana Kategori:</th>
                                <td><?php echo !empty($urun['main_category']) ? htmlspecialchars($urun['main_category']) . ' (' . htmlspecialchars($urun['main_category_code']) . ')' : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Alt Kategori:</th>
                                <td><?php echo !empty($urun['sub_category']) ? htmlspecialchars($urun['sub_category']) . ' (' . htmlspecialchars($urun['sub_category_code']) . ')' : '-'; ?></td>
                            </tr>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Muadil Ürünler -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Muadil Ürünler</h6>
    </div>
    <div class="card-body">
        <?php
        try {
            $muadil_sorgu_baslangic = microtime(true);
            
            // Ürünün bulunduğu muadil grup ID'lerini bul
            $stmt = $db->prepare("
                SELECT ag.id, ag.group_name
                FROM alternative_groups ag
                JOIN product_alternatives pa ON ag.id = pa.alternative_group_id
                WHERE pa.product_id = ?
                LIMIT 10
            ");
            $stmt->execute([$id]);
            $alternative_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($alternative_groups)) {
                // Yeni sisteme göre grup bulunamadıysa, eski sistemdeki OEM numaralarına göre kontrol et
                $oem_numbers = !empty($urun['oem_no']) ? preg_split('/\r\n|\r|\n/', $urun['oem_no'], -1, PREG_SPLIT_NO_EMPTY) : [];
                
                if (empty($oem_numbers)) {
                    echo '<div class="alert alert-info">Bu ürün için OEM numarası tanımlanmamış veya muadil ürün grubu bulunmuyor.</div>';
                } else {
                    // Daha hafif bir sorgu ile OEM numaralarına göre muadil bul
                    $oem_array = [];
                    foreach ($oem_numbers as $oem) {
                        $trimmed = trim($oem);
                        if (!empty($trimmed)) {
                            $oem_array[] = $trimmed;
                        }
                    }
                    
                    if (!empty($oem_array)) {
                        // OEM numaraları için direkt arama yapalım
                        // Tam eşleşme için LIKE yerine direkt eşitlik kullanarak daha hızlı sorgu
                        $oem_sorgu_baslangic = microtime(true);

                        // OEM numaralarını direkt WHERE IN sorgusu ile aratalım
                        $oem_placeholders = implode(',', array_fill(0, min(count($oem_array), 10), '?'));
                        $sql = "SELECT s.ID as id,
                                s.ADI as name, 
                                s.KOD as code, 
                                g.KOD as unit,
                                sfS.FIYAT as sale_price,
                                sum.MIKTAR as current_stock,
                                s.OZELALAN1 as oem_no
                                FROM stok s 
                                LEFT JOIN stk_birim sb ON s.ID = sb.STOKID
                                LEFT JOIN grup g ON sb.BIRIMID = g.ID
                                LEFT JOIN stk_fiyat sfS ON s.ID = sfS.STOKID AND sfS.TIP = 'S'
                                LEFT JOIN stk_urun_miktar sum ON s.ID = sum.URUN_ID
                                WHERE s.ID != ? 
                                AND s.OZELALAN1 IS NOT NULL
                                ORDER BY s.ID
                                LIMIT 50";
                        
                        $stmt = $db->prepare($sql);
                        $stmt->bindValue(1, $id);
                        $stmt->execute();
                        $tum_muadil_urunler = $stmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // PHP tarafında OEM eşleşmelerini kontrol edelim
                        $muadil_urunler = [];
                        
                        foreach ($tum_muadil_urunler as $muadil) {
                            if (empty($muadil['oem_no'])) continue;
                            
                            $muadil_oem_numbers = preg_split('/\r\n|\r|\n/', $muadil['oem_no'], -1, PREG_SPLIT_NO_EMPTY);
                            $eslesme_var = false;
                            
                            foreach ($muadil_oem_numbers as $moem) {
                                $moem_trim = trim($moem);
                                if (empty($moem_trim)) continue;
                                
                                foreach ($oem_array as $own_oem) {
                                    if (strcasecmp($own_oem, $moem_trim) === 0 || 
                                        (strlen($own_oem) > 4 && strlen($moem_trim) > 4 && 
                                         (stripos($own_oem, $moem_trim) !== false || 
                                          stripos($moem_trim, $own_oem) !== false))) {
                                        $eslesme_var = true;
                                        break 2;
                                    }
                                }
                            }
                            
                            if ($eslesme_var) {
                                $muadil_urunler[] = $muadil;
                                // En fazla 20 eşleşme ile sınırlayalım
                                if (count($muadil_urunler) >= 20) break;
                            }
                        }
                        
                        $oem_sorgu_bitis = microtime(true);
                        $performans['oem_sorgu'] = $oem_sorgu_bitis - $oem_sorgu_baslangic;
                        
                        if (count($muadil_urunler) > 0) {
                            echo '<div class="alert alert-warning mb-3">Bu ürünün muadil ürünleri OEM numaralarına göre bulundu.</div>';
                            
                            echo '<div class="table-responsive">';
                            echo '<table class="table table-bordered table-hover" width="100%" cellspacing="0">';
                            echo '<thead>';
                            echo '<tr>';
                            echo '<th>Stok Kodu</th>';
                            echo '<th>Ürün Adı</th>';
                            echo '<th>OEM No</th>';
                            echo '<th>Stok</th>';
                            echo '<th>Satış Fiyatı</th>';
                            echo '<th>İşlemler</th>';
                            echo '</tr>';
                            echo '</thead>';
                            echo '<tbody>';
                            
                            foreach ($muadil_urunler as $muadil) {
                                echo '<tr>';
                                echo '<td>' . htmlspecialchars($muadil['code']) . '</td>';
                                echo '<td><a href="urun_detay.php?id=' . $muadil['id'] . '">' . htmlspecialchars($muadil['name']) . '</a></td>';
                                echo '<td>';
                                $muadil_oem_numbers = !empty($muadil['oem_no']) ? preg_split('/\r\n|\r|\n/', $muadil['oem_no'], -1, PREG_SPLIT_NO_EMPTY) : [];
                                foreach ($muadil_oem_numbers as $moem) {
                                    $moem_trim = trim($moem);
                                    if (empty($moem_trim)) continue;
                                    
                                    // OEM numarası eşleşiyorsa vurgula
                                    $is_match = false;
                                    foreach ($oem_array as $own_oem) {
                                        if (strcasecmp($own_oem, $moem_trim) === 0 || 
                                            stripos($own_oem, $moem_trim) !== false || 
                                            stripos($moem_trim, $own_oem) !== false) {
                                            $is_match = true;
                                            break;
                                        }
                                    }
                                    
                                    if ($is_match) {
                                        echo '<span class="badge bg-success">' . htmlspecialchars($moem_trim) . '</span><br>';
                                    } else {
                                        echo htmlspecialchars($moem_trim) . '<br>';
                                    }
                                }
                                echo '</td>';
                                echo '<td class="text-end">' . number_format($muadil['current_stock'] ?? 0, 2, ',', '.') . ' ' . htmlspecialchars($muadil['unit'] ?? 'Adet') . '</td>';
                                echo '<td class="text-end">₺' . number_format($muadil['sale_price'] ?? 0, 2, ',', '.') . '</td>';
                                echo '<td class="text-center">';
                                echo '<div class="btn-group">';
                                echo '<a href="urun_detay.php?id=' . $muadil['id'] . '" class="btn btn-sm btn-info" title="Detay"><i class="fas fa-eye"></i></a>';
                                echo '<a href="urun_duzenle.php?id=' . $muadil['id'] . '" class="btn btn-sm btn-primary" title="Düzenle"><i class="fas fa-edit"></i></a>';
                                echo '</div>';
                                echo '</td>';
                                echo '</tr>';
                            }
                            
                            echo '</tbody>';
                            echo '</table>';
                            echo '</div>';
                        } else {
                            echo '<div class="alert alert-info">Bu ürün için muadil ürün bulunamadı.</div>';
                        }
                    } else {
                        echo '<div class="alert alert-info">Geçerli OEM numarası bulunamadı.</div>';
                    }
                }
            } else {
                // Kalan kod aynı kalsın
                // ... existing code ...
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Veritabanı hatası: ' . $e->getMessage() . '</div>';
            echo '<div style="color:red; padding:10px; margin-bottom:10px; border:1px solid red;">';
            echo 'SQL: ' . $stmt->queryString . '<br>';
            echo 'Hata Detayı: ' . $e->getTraceAsString();
            echo '</div>';
        }
        ?>
    </div>
</div>

<!-- Son Stok Hareketleri -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Son Stok Hareketleri</h6>
        <a href="stok_hareketleri.php?urun_id=<?php echo $id; ?>" class="btn btn-sm btn-info">
            <i class="fas fa-list"></i> Tüm Hareketleri Görüntüle
        </a>
    </div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-bordered table-hover" width="100%" cellspacing="0">
                <thead>
                    <tr>
                        <th>Tarih</th>
                        <th>Hareket Tipi</th>
                        <th>Miktar</th>
                        <th>Birim Fiyat</th>
                        <th>Toplam Tutar</th>
                        <th>Referans</th>
                        <th>Kullanıcı</th>
                        <th>Açıklama</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Son 10 stok hareketini getir
                    try {
                        $hareketler_sorgu_baslangic = microtime(true);
                        
                        $stmt = $db->prepare("
                            SELECT sh.TARIH, sh.HAREKET_TIPI, sh.MIKTAR, sh.BIRIM_FIYAT, 
                                   sh.REFERANS_TIP, sh.REFERANS_NO, sh.ACIKLAMA,
                                   u.name as user_name, u.surname as user_surname 
                            FROM STK_FIS_HAR sh
                            LEFT JOIN users u ON sh.KULLANICI_ID = u.id
                            WHERE sh.URUN_ID = :product_id
                            ORDER BY sh.TARIH DESC
                            LIMIT 5
                        ");
                        $stmt->bindParam(':product_id', $id);
                        $stmt->execute();
                        
                        $hareketler = $stmt->fetchAll();
                        
                        $hareketler_sorgu_bitis = microtime(true);
                        $performans['hareketler_sorgu'] = $hareketler_sorgu_bitis - $hareketler_sorgu_baslangic;
                        
                        if (count($hareketler) > 0) {
                            foreach ($hareketler as $hareket) {
                                $toplam_tutar = $hareket['MIKTAR'] * $hareket['BIRIM_FIYAT'];
                                $hareket_tipi_text = $hareket['HAREKET_TIPI'] == 'giris' ? 
                                    '<span class="badge bg-success">Giriş</span>' : 
                                    '<span class="badge bg-danger">Çıkış</span>';
                                
                                echo '<tr>';
                                echo '<td>' . date('d.m.Y H:i', strtotime($hareket['TARIH'])) . '</td>';
                                echo '<td>' . $hareket_tipi_text . '</td>';
                                echo '<td>' . number_format($hareket['MIKTAR'], 2, ',', '.') . ' ' . htmlspecialchars($urun['unit'] ?? 'Adet') . '</td>';
                                echo '<td>₺' . number_format($hareket['BIRIM_FIYAT'], 2, ',', '.') . '</td>';
                                echo '<td>₺' . number_format($toplam_tutar, 2, ',', '.') . '</td>';
                                
                                // Referans
                                $referans_tipler = [
                                    'alis_fatura' => 'Alış Faturası',
                                    'satis_fatura' => 'Satış Faturası',
                                    'stok_sayim' => 'Stok Sayımı',
                                    'stok_transfer' => 'Stok Transferi',
                                    'diger' => 'Diğer'
                                ];
                                $referans_tip_text = isset($referans_tipler[$hareket['REFERANS_TIP']]) ? 
                                    $referans_tipler[$hareket['REFERANS_TIP']] : $hareket['REFERANS_TIP'];
                                
                                echo '<td>' . $referans_tip_text . ' ' . $hareket['REFERANS_NO'] . '</td>';
                                echo '<td>' . htmlspecialchars($hareket['user_name'] . ' ' . $hareket['user_surname']) . '</td>';
                                echo '<td>' . htmlspecialchars($hareket['ACIKLAMA']) . '</td>';
                                echo '</tr>';
                            }
                        } else {
                            echo '<tr><td colspan="8" class="text-center">Henüz stok hareketi bulunmuyor.</td></tr>';
                        }
                    } catch (PDOException $e) {
                        echo '<tr><td colspan="8" class="text-center text-danger">Veritabanı hatası: ' . $e->getMessage() . '</td></tr>';
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Stok Hareketi Ekleme Butonu -->
<div class="text-center mb-4">
    <a href="urun_duzenle.php?id=<?php echo $id; ?>" class="btn btn-success">
        <i class="fas fa-plus"></i> Stok Hareketi Ekle
    </a>
</div>

<?php
// Sayfa yükleme süresini hesapla ve göster
$bitis_zamani = microtime(true);
$toplam_sure = $bitis_zamani - $baslangic_zamani;

// Performans bilgilerini göster
echo "<div class='alert alert-info text-center'>";
echo "<strong>Sayfa yükleme süresi:</strong> " . round($toplam_sure, 4) . " saniye<br><br>";
echo "<strong>Sorgu Süreleri:</strong><br>";
foreach ($performans as $islem => $sure) {
    echo "$islem: " . round($sure, 4) . " saniye<br>";
}

// Muadil sorgu toplam süresini hesapla
$muadil_sorgu_bitis = microtime(true);
$performans['muadil_sorgu_toplam'] = $muadil_sorgu_bitis - $muadil_sorgu_baslangic;
echo "Muadil sorgu toplam: " . round($performans['muadil_sorgu_toplam'], 4) . " saniye<br>";

// PHP işleme süreleri
echo "<br><strong>PHP İşleme Süresi:</strong> " . round($toplam_sure - array_sum($performans), 4) . " saniye<br>";

echo "</div>";

// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 