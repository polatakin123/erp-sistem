<?php
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

// Ürün bilgilerini al
try {
    $stmt = $db->prepare("
        SELECT p.*, pc.name as category_name
        FROM products p
        LEFT JOIN product_categories pc ON p.category_id = pc.id
        WHERE p.id = :id
    ");
    $stmt->bindParam(':id', $id);
    $stmt->execute();
    
    if ($stmt->rowCount() == 0) {
        $_SESSION['error'] = "Ürün bulunamadı.";
        header('Location: index.php');
        exit;
    }
    
    $urun = $stmt->fetch();
    
    // Ekleyen ve güncelleyen kullanıcı bilgilerini ayrıca al
    if (!empty($urun['created_by'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $urun['created_by']);
        $stmt->execute();
        $created_user = $stmt->fetch();
        
        if ($created_user) {
            $urun['created_user_name'] = $created_user['name'] ?? '';
            $urun['created_user_surname'] = $created_user['surname'] ?? '';
        }
    }
    
    if (!empty($urun['updated_by'])) {
        $stmt = $db->prepare("SELECT * FROM users WHERE id = :user_id");
        $stmt->bindParam(':user_id', $urun['updated_by']);
        $stmt->execute();
        $updated_user = $stmt->fetch();
        
        if ($updated_user) {
            $urun['updated_user_name'] = $updated_user['name'] ?? '';
            $urun['updated_user_surname'] = $updated_user['surname'] ?? '';
        }
    }
    
} catch (PDOException $e) {
    $_SESSION['error'] = "Veritabanı hatası: " . $e->getMessage();
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
                        <td><?php echo htmlspecialchars($urun['category_name'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Marka:</th>
                        <td><?php echo htmlspecialchars($urun['brand'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Model:</th>
                        <td><?php echo htmlspecialchars($urun['model'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Barkod:</th>
                        <td><?php echo htmlspecialchars($urun['barcode'] ?? '-'); ?></td>
                    </tr>
                    <tr>
                        <th>Birim:</th>
                        <td><?php echo htmlspecialchars($urun['unit']); ?></td>
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
                        <td>₺<?php echo number_format($urun['purchase_price'], 2, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <th>Satış Fiyatı:</th>
                        <td>₺<?php echo number_format($urun['sale_price'], 2, ',', '.'); ?></td>
                    </tr>
                    <tr>
                        <th>KDV Oranı:</th>
                        <td>%<?php echo $urun['tax_rate']; ?></td>
                    </tr>
                    <tr>
                        <th>Mevcut Stok:</th>
                        <td class="<?php echo ($urun['current_stock'] <= $urun['min_stock']) ? 'text-danger fw-bold' : ''; ?>">
                            <?php echo number_format($urun['current_stock'], 2, ',', '.'); ?> <?php echo $urun['unit']; ?>
                        </td>
                    </tr>
                    <tr>
                        <th>Minimum Stok:</th>
                        <td><?php echo number_format($urun['min_stock'], 2, ',', '.'); ?> <?php echo $urun['unit']; ?></td>
                    </tr>
                    <tr>
                        <th>Ekleme Tarihi:</th>
                        <td><?php echo date('d.m.Y H:i', strtotime($urun['created_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Ekleyen:</th>
                        <td><?php echo htmlspecialchars($urun['created_user_name'] . ' ' . $urun['created_user_surname']); ?></td>
                    </tr>
                    <?php if (!empty($urun['updated_at'])): ?>
                    <tr>
                        <th>Son Güncelleme:</th>
                        <td><?php echo date('d.m.Y H:i', strtotime($urun['updated_at'])); ?></td>
                    </tr>
                    <tr>
                        <th>Güncelleyen:</th>
                        <td><?php echo htmlspecialchars($urun['updated_user_name'] . ' ' . $urun['updated_user_surname']); ?></td>
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
                                <td><?php echo !empty($urun['shelf_code']) ? htmlspecialchars($urun['shelf_code']) : '-'; ?></td>
                            </tr>
                        </table>
                    </div>
                    <div class="col-md-6">
                        <table class="table table-borderless">
                            <tr>
                                <th style="width: 35%">Araç Markası:</th>
                                <td><?php echo !empty($urun['vehicle_brand']) ? htmlspecialchars($urun['vehicle_brand']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Araç Modeli:</th>
                                <td><?php echo !empty($urun['vehicle_model']) ? htmlspecialchars($urun['vehicle_model']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Ana Kategori:</th>
                                <td><?php echo !empty($urun['main_category']) ? htmlspecialchars($urun['main_category']) : '-'; ?></td>
                            </tr>
                            <tr>
                                <th>Alt Kategori:</th>
                                <td><?php echo !empty($urun['sub_category']) ? htmlspecialchars($urun['sub_category']) : '-'; ?></td>
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
            // Ürünün bulunduğu muadil grup ID'lerini bul
            $stmt = $db->prepare("
                SELECT ag.id, ag.group_name
                FROM alternative_groups ag
                JOIN product_alternatives pa ON ag.id = pa.alternative_group_id
                WHERE pa.product_id = ?
            ");
            $stmt->execute([$id]);
            $alternative_groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (empty($alternative_groups)) {
                // Yeni sisteme göre grup bulunamadıysa, eski sistemdeki OEM numaralarına göre kontrol et
                $oem_numbers = !empty($urun['oem_no']) ? preg_split('/\r\n|\r|\n/', $urun['oem_no']) : [];
                
                if (empty($oem_numbers)) {
                    echo '<div class="alert alert-info">Bu ürün için OEM numarası tanımlanmamış veya muadil ürün grubu bulunmuyor.</div>';
                } else {
                    // Eski algoritma ile muadil bul (geçici çözüm)
                    include 'legacy_alternative_products.php';
                }
            } else {
                // Her bir muadil grup için ürünleri getir
                $all_alternative_products = [];
                
                foreach ($alternative_groups as $group) {
                    $stmt = $db->prepare("
                        SELECT p.*, c.name as category_name
                        FROM products p
                        LEFT JOIN product_categories c ON p.category_id = c.id
                        JOIN product_alternatives pa ON p.id = pa.product_id
                        WHERE pa.alternative_group_id = ? AND p.id != ?
                        ORDER BY p.name
                    ");
                    
                    $stmt->execute([$group['id'], $id]);
                    $group_products = $stmt->fetchAll();
                    
                    if (!empty($group_products)) {
                        foreach ($group_products as $product) {
                            // Tekrarlanan ürünleri engelle
                            if (!isset($all_alternative_products[$product['id']])) {
                                $all_alternative_products[$product['id']] = $product;
                                $all_alternative_products[$product['id']]['group_name'] = $group['group_name'];
                            }
                        }
                    }
                }
                
                if (empty($all_alternative_products)) {
                    echo '<div class="alert alert-info">Bu ürün bir muadil grubuna ait ancak başka muadil ürün bulunamadı.</div>';
                } else {
                    // OEM numaralarını ürün bazında al
                    $product_oems = [];
                    foreach (array_keys($all_alternative_products) as $product_id) {
                        $stmt = $db->prepare("SELECT oem_no FROM oem_numbers WHERE product_id = ?");
                        $stmt->execute([$product_id]);
                        $oems = $stmt->fetchAll(PDO::FETCH_COLUMN);
                        $product_oems[$product_id] = $oems;
                    }
                    
                    // Mevcut ürünün OEM numaralarını al
                    $stmt = $db->prepare("SELECT oem_no FROM oem_numbers WHERE product_id = ?");
                    $stmt->execute([$id]);
                    $current_product_oems = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    
                    // Muadil ürünleri tablosu
                    echo '<div class="table-responsive">';
                    echo '<table class="table table-bordered table-hover" width="100%" cellspacing="0">';
                    echo '<thead>';
                    echo '<tr>';
                    echo '<th>Stok Kodu</th>';
                    echo '<th>Ürün Adı</th>';
                    echo '<th>Marka/Model</th>';
                    echo '<th>OEM No</th>';
                    echo '<th>Muadil Grup</th>';
                    echo '<th>Stok</th>';
                    echo '<th>Satış Fiyatı</th>';
                    echo '<th>İşlemler</th>';
                    echo '</tr>';
                    echo '</thead>';
                    echo '<tbody>';
                    
                    foreach ($all_alternative_products as $product) {
                        echo '<tr>';
                        echo '<td>' . htmlspecialchars($product['code']) . '</td>';
                        echo '<td><a href="urun_detay.php?id=' . $product['id'] . '">' . htmlspecialchars($product['name']) . '</a></td>';
                        echo '<td>';
                        echo htmlspecialchars($product['brand'] ?? '');
                        if (!empty($product['model'])) {
                            echo ' / ' . htmlspecialchars($product['model']);
                        }
                        echo '</td>';
                        echo '<td>';
                        
                        if (isset($product_oems[$product['id']])) {
                            foreach ($product_oems[$product['id']] as $oem) {
                                $is_match = in_array($oem, $current_product_oems);
                                if ($is_match) {
                                    echo '<span class="badge bg-success">' . htmlspecialchars($oem) . '</span><br>';
                                } else {
                                    echo htmlspecialchars($oem) . '<br>';
                                }
                            }
                        } else {
                            // Eğer yeni tabloda yoksa eski veriden göster
                            $product_oem_numbers = !empty($product['oem_no']) ? preg_split('/\r\n|\r|\n/', $product['oem_no']) : [];
                            foreach ($product_oem_numbers as $moem) {
                                $moem_trim = trim($moem);
                                if (empty($moem_trim)) continue;
                                echo htmlspecialchars($moem_trim) . '<br>';
                            }
                        }
                        
                        echo '</td>';
                        echo '<td>' . htmlspecialchars($product['group_name']) . '</td>';
                        echo '<td class="text-end">' . number_format($product['current_stock'], 2, ',', '.') . ' ' . htmlspecialchars($product['unit']) . '</td>';
                        echo '<td class="text-end">₺' . number_format($product['sale_price'], 2, ',', '.') . '</td>';
                        echo '<td class="text-center">';
                        echo '<div class="btn-group">';
                        echo '<a href="urun_detay.php?id=' . $product['id'] . '" class="btn btn-sm btn-info" title="Detay"><i class="fas fa-eye"></i></a>';
                        echo '<a href="urun_duzenle.php?id=' . $product['id'] . '" class="btn btn-sm btn-primary" title="Düzenle"><i class="fas fa-edit"></i></a>';
                        echo '</div>';
                        echo '</td>';
                        echo '</tr>';
                    }
                    
                    echo '</tbody>';
                    echo '</table>';
                    echo '</div>';
                }
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Veritabanı hatası: ' . $e->getMessage() . '</div>';
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
                        $stmt = $db->prepare("
                            SELECT sm.*, u.name as user_name, u.surname as user_surname 
                            FROM stock_movements sm
                            LEFT JOIN users u ON sm.user_id = u.id
                            WHERE sm.product_id = :product_id
                            ORDER BY sm.created_at DESC
                            LIMIT 10
                        ");
                        $stmt->bindParam(':product_id', $id);
                        $stmt->execute();
                        
                        $hareketler = $stmt->fetchAll();
                        
                        if (count($hareketler) > 0) {
                            foreach ($hareketler as $hareket) {
                                $toplam_tutar = $hareket['quantity'] * $hareket['unit_price'];
                                $hareket_tipi_text = $hareket['movement_type'] == 'giris' ? 
                                    '<span class="badge bg-success">Giriş</span>' : 
                                    '<span class="badge bg-danger">Çıkış</span>';
                                
                                echo '<tr>';
                                echo '<td>' . date('d.m.Y H:i', strtotime($hareket['created_at'])) . '</td>';
                                echo '<td>' . $hareket_tipi_text . '</td>';
                                echo '<td>' . number_format($hareket['quantity'], 2, ',', '.') . ' ' . $urun['unit'] . '</td>';
                                echo '<td>₺' . number_format($hareket['unit_price'], 2, ',', '.') . '</td>';
                                echo '<td>₺' . number_format($toplam_tutar, 2, ',', '.') . '</td>';
                                
                                // Referans
                                $referans_tipler = [
                                    'alis_fatura' => 'Alış Faturası',
                                    'satis_fatura' => 'Satış Faturası',
                                    'stok_sayim' => 'Stok Sayımı',
                                    'stok_transfer' => 'Stok Transferi',
                                    'diger' => 'Diğer'
                                ];
                                $referans_tip_text = isset($referans_tipler[$hareket['reference_type']]) ? 
                                    $referans_tipler[$hareket['reference_type']] : $hareket['reference_type'];
                                
                                echo '<td>' . $referans_tip_text . ' ' . $hareket['reference_no'] . '</td>';
                                echo '<td>' . htmlspecialchars($hareket['user_name'] . ' ' . $hareket['user_surname']) . '</td>';
                                echo '<td>' . htmlspecialchars($hareket['description']) . '</td>';
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
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 