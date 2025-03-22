<?php
/**
 * ERP Sistem - Ürün Arama Sayfası
 * 
 * Bu dosya ürünleri detaylı arama ve filtreleme işlemlerini gerçekleştirir.
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

// Sayfa başlığı
$pageTitle = "Ürün Arama";

// Arama parametresini al
$arama = isset($_GET['arama']) ? clean($_GET['arama']) : '';

// Detaylı arama için parametreleri al
$stok_kodu = isset($_GET['stok_kodu']) ? clean($_GET['stok_kodu']) : '';
$urun_adi = isset($_GET['urun_adi']) ? clean($_GET['urun_adi']) : '';
$kategori_id = isset($_GET['kategori_id']) ? (int)$_GET['kategori_id'] : 0;
$marka = isset($_GET['marka']) ? clean($_GET['marka']) : '';
$model = isset($_GET['model']) ? clean($_GET['model']) : '';
$min_stok = isset($_GET['min_stok']) ? (float)$_GET['min_stok'] : null;
$max_stok = isset($_GET['max_stok']) ? (float)$_GET['max_stok'] : null;
$min_fiyat = isset($_GET['min_fiyat']) ? (float)$_GET['min_fiyat'] : null;
$max_fiyat = isset($_GET['max_fiyat']) ? (float)$_GET['max_fiyat'] : null;
$durum = isset($_GET['durum']) ? clean($_GET['durum']) : '';

// Arama modunu belirle (hızlı/detaylı)
$arama_modu = isset($_GET['arama_modu']) ? $_GET['arama_modu'] : 'hizli';

// Ürünleri filtrele
$urunler = [];
$filtreSorgusu = false;

// Hızlı arama
if ($arama_modu == 'hizli' && !empty($arama)) {
    $filtreSorgusu = true;
    
    try {
        // Arama kelimelerini boşluklara göre ayır
        $arama_terimleri = explode(' ', trim($arama));
        
        // Ana sorgu - arama kriterine uyan tüm ürünleri bul
        $sql = "
        WITH matched_products AS (
            SELECT p.*, c.name as category_name, 0 as is_alternative
            FROM products p 
            LEFT JOIN product_categories c ON p.category_id = c.id 
            WHERE 1=1";
        
        $params = [];
        $param_index = 1;
        
        // Her bir arama terimi için koşul ekle
        foreach ($arama_terimleri as $index => $terim) {
            if (strlen($terim) < 2) continue; // Çok kısa terimleri atla
            
            $sql .= " AND (
                p.code LIKE :arama{$param_index} OR 
                p.name LIKE :arama" . ($param_index + 1) . " OR 
                p.description LIKE :arama" . ($param_index + 2) . " OR 
                p.brand LIKE :arama" . ($param_index + 3) . " OR 
                p.model LIKE :arama" . ($param_index + 4) . " OR 
                c.name LIKE :arama" . ($param_index + 5) . " OR
                p.oem_no LIKE :arama" . ($param_index + 6) . " OR
                p.cross_reference LIKE :arama" . ($param_index + 7) . " OR
                p.dimensions LIKE :arama" . ($param_index + 8) . " OR
                p.shelf_code LIKE :arama" . ($param_index + 9) . " OR
                p.vehicle_brand LIKE :arama" . ($param_index + 10) . " OR
                p.vehicle_model LIKE :arama" . ($param_index + 11) . " OR
                p.main_category LIKE :arama" . ($param_index + 12) . " OR
                p.sub_category LIKE :arama" . ($param_index + 13) . " OR
                EXISTS (SELECT 1 FROM oem_numbers WHERE product_id = p.id AND oem_no LIKE :arama" . ($param_index + 14) . ")
            )";
            
            $arama_param = '%' . $terim . '%';
            for ($i = 0; $i < 15; $i++) {
                $params[':arama' . ($param_index + $i)] = $arama_param;
            }
            
            $param_index += 15;
        }
        
        $sql .= "),

        -- İlk eşleşen ürünleri ve alternatif gruplarını belirleme
        initial_matches AS (
            SELECT 
                mp.id,
                (SELECT pa.alternative_group_id 
                 FROM product_alternatives pa 
                 WHERE pa.product_id = mp.id 
                 LIMIT 1) as group_id
            FROM matched_products mp
        ),
        
        -- Tüm ilgili muadil grup ID'lerini toplama
        all_related_groups AS (
            SELECT DISTINCT alternative_group_id as group_id
            FROM product_alternatives
            WHERE alternative_group_id IN (
                SELECT im.group_id FROM initial_matches im WHERE im.group_id IS NOT NULL
            )
        ),
        
        -- Muadil gruplardaki tüm ürünleri alma
        all_alternative_products AS (
            SELECT DISTINCT p.id
            FROM products p
            JOIN product_alternatives pa ON p.id = pa.product_id
            WHERE pa.alternative_group_id IN (SELECT group_id FROM all_related_groups)
        ),
        
        -- Genişletilmiş ürün listesi (arama sonucu + tüm muadil ürünler)
        extended_product_list AS (
            SELECT id FROM matched_products
            UNION
            SELECT id FROM all_alternative_products
        ),
        
        -- Muadil ürün gruplarını belirle
        product_group_info AS (
            SELECT 
                p.id,
                p.code,
                p.name,
                p.category_id,
                p.unit,
                p.current_stock,
                p.sale_price,
                p.status,
                p.brand,
                p.model,
                p.description,
                p.oem_no,
                p.cross_reference,
                p.dimensions,
                p.shelf_code,
                p.vehicle_brand,
                p.vehicle_model,
                p.main_category,
                p.sub_category,
                c.name as category_name,
                COALESCE(ag.id, 0) as group_id,
                CASE 
                    WHEN EXISTS (SELECT 1 FROM matched_products mp WHERE mp.id = p.id) THEN 1
                    ELSE 0 
                END as direct_match,
                CASE
                    WHEN p.code = '" . addslashes($arama) . "' THEN 1
                    WHEN p.name = '" . addslashes($arama) . "' THEN 2
                    WHEN p.name = 'Klasör' THEN 2
                    WHEN p.name LIKE 'Klasör %' THEN 2
                    WHEN p.name LIKE '% Klasör' THEN 3
                    WHEN p.name LIKE '%" . addslashes($arama) . " %' OR p.name LIKE '% " . addslashes($arama) . " %' THEN 3
                    WHEN p.name LIKE '%Klasör%' THEN 3
                    WHEN p.name LIKE '%" . addslashes($arama) . "%' THEN 4
                    WHEN EXISTS (SELECT 1 FROM matched_products mp WHERE mp.id = p.id) THEN 5
                    ELSE 10
                END as match_priority,
                CASE
                    WHEN EXISTS (SELECT 1 FROM matched_products mp WHERE mp.id = p.id) THEN 0
                    ELSE 1
                END as is_muadil,
                COALESCE(
                    (SELECT MIN(p2.id) 
                     FROM product_alternatives pa2 
                     JOIN products p2 ON pa2.product_id = p2.id 
                     WHERE pa2.alternative_group_id = ag.id
                     AND EXISTS (SELECT 1 FROM matched_products mp WHERE mp.id = p2.id)
                     ORDER BY 
                        CASE 
                            WHEN p2.name LIKE '%" . addslashes($arama) . "%' THEN 1
                            ELSE 2
                        END,
                        p2.id
                    ), 
                    COALESCE(
                        (SELECT MIN(p3.id) 
                         FROM product_alternatives pa3
                         JOIN products p3 ON pa3.product_id = p3.id 
                         WHERE pa3.alternative_group_id = ag.id
                         ORDER BY p3.id),
                        p.id
                    )
                ) as primary_product_id
            FROM products p
            LEFT JOIN product_categories c ON p.category_id = c.id
            LEFT JOIN product_alternatives pa ON p.id = pa.product_id
            LEFT JOIN alternative_groups ag ON pa.alternative_group_id = ag.id
            WHERE p.id IN (SELECT id FROM extended_product_list)
        ),
        
        -- Her gruptan bir ürün seç (ilk eşleşen) ve diğerlerini muadil olarak işaretle
        final_results AS (
            SELECT 
                pg.id,
                pg.code,
                pg.name,
                pg.category_id,
                pg.unit,
                pg.current_stock,
                pg.sale_price,
                pg.status,
                pg.brand,
                pg.model,
                pg.description,
                pg.oem_no,
                pg.cross_reference,
                pg.dimensions,
                pg.shelf_code,
                pg.vehicle_brand,
                pg.vehicle_model,
                pg.main_category,
                pg.sub_category,
                pg.category_name,
                pg.group_id,
                pg.direct_match,
                pg.match_priority,
                pg.is_muadil,
                CASE 
                    WHEN pg.id = pg.primary_product_id THEN 0
                    WHEN pg.group_id > 0 THEN 1
                    ELSE 0
                END as is_alternative,
                pg.primary_product_id
            FROM product_group_info pg
            WHERE 
                pg.id = pg.primary_product_id  -- Ana ürünleri al
                OR (pg.group_id > 0 AND pg.id <> pg.primary_product_id) -- Muadil ürünleri al
        )
        
        SELECT * FROM final_results
        ";
        
        // Sıralama için arama terimleriyle tam eşleşenlerin önce gelmesini sağlayan CASE ifadeleri
        $primary_sort_cases = [];
        foreach ($arama_terimleri as $index => $terim) {
            if (strlen($terim) < 2) continue;
            
            $primary_sort_cases[] = "CASE 
                WHEN name = '" . addslashes($terim) . "' THEN 1
                WHEN code = '" . addslashes($terim) . "' THEN 1
                WHEN name = 'Klasör' THEN 1
                WHEN name LIKE 'Klasör %' THEN 2
                WHEN name LIKE '% Klasör' THEN 2
                WHEN name LIKE '%Klasör%' THEN 2
                WHEN name LIKE '" . addslashes($terim) . " %' THEN 2
                WHEN name LIKE '% " . addslashes($terim) . "' THEN 2
                WHEN name LIKE '%" . addslashes($terim) . "%' THEN 3
                ELSE 4
            END";
        }
        
        // Sıralama kriterlerini ekle
        if (!empty($primary_sort_cases)) {
            $sql .= " ORDER BY is_muadil ASC, match_priority, " . implode(" + ", $primary_sort_cases);
        } else {
            $sql .= " ORDER BY is_muadil ASC, match_priority";
        }
        
        // Önce ana ürünler (is_alternative=0), sonra group_id'ye göre sırala, 
        // böylece aynı gruptaki ürünler bir arada gösterilir
        $sql .= ", group_id, name ASC";
        
        $stmt = $db->prepare($sql);
        
        // Parametreleri bağla
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        
        $stmt->execute();
        $urunler = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
    }
}
// Detaylı arama
elseif ($arama_modu == 'detayli' && ($_SERVER['REQUEST_METHOD'] == 'GET' && (
    !empty($stok_kodu) || 
    !empty($urun_adi) || 
    !empty($kategori_id) || 
    !empty($marka) || 
    !empty($model) || 
    $min_stok !== null || 
    $max_stok !== null || 
    $min_fiyat !== null || 
    $max_fiyat !== null || 
    !empty($durum)
))) {
    $filtreSorgusu = true;
    
    try {
        // SQL sorgusu oluştur
        $sql = "SELECT p.*, c.name as category_name 
                FROM products p 
                LEFT JOIN product_categories c ON p.category_id = c.id 
                WHERE 1=1";
        $params = [];
        
        // Filtreleri ekle
        if (!empty($stok_kodu)) {
            $sql .= " AND p.code LIKE :stok_kodu";
            $params[':stok_kodu'] = '%' . $stok_kodu . '%';
        }
        
        if (!empty($urun_adi)) {
            $sql .= " AND p.name LIKE :urun_adi";
            $params[':urun_adi'] = '%' . $urun_adi . '%';
        }
        
        if (!empty($kategori_id)) {
            $sql .= " AND p.category_id = :kategori_id";
            $params[':kategori_id'] = $kategori_id;
        }
        
        if (!empty($marka)) {
            $sql .= " AND p.brand LIKE :marka";
            $params[':marka'] = '%' . $marka . '%';
        }
        
        if (!empty($model)) {
            $sql .= " AND p.model LIKE :model";
            $params[':model'] = '%' . $model . '%';
        }
        
        if ($min_stok !== null) {
            $sql .= " AND p.current_stock >= :min_stok";
            $params[':min_stok'] = $min_stok;
        }
        
        if ($max_stok !== null) {
            $sql .= " AND p.current_stock <= :max_stok";
            $params[':max_stok'] = $max_stok;
        }
        
        if ($min_fiyat !== null) {
            $sql .= " AND p.sale_price >= :min_fiyat";
            $params[':min_fiyat'] = $min_fiyat;
        }
        
        if ($max_fiyat !== null) {
            $sql .= " AND p.sale_price <= :max_fiyat";
            $params[':max_fiyat'] = $max_fiyat;
        }
        
        if (!empty($durum)) {
            $sql .= " AND p.status = :durum";
            $params[':durum'] = $durum;
        }
        
        // Sıralama ekle
        $sql .= " ORDER BY p.id DESC";
        
        // Sorguyu hazırla ve çalıştır
        $stmt = $db->prepare($sql);
        foreach ($params as $key => $value) {
            $stmt->bindValue($key, $value);
        }
        $stmt->execute();
        
        $urunler = $stmt->fetchAll();
        
    } catch (PDOException $e) {
        $error = "Veritabanı hatası: " . $e->getMessage();
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-2 pb-1 mb-2 border-bottom">
    <h1 class="h3">Ürün Arama</h1>
    <div class="btn-toolbar mb-0 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Stok Yönetimine Dön
            </a>
            <a href="urun_ekle.php" class="btn btn-sm btn-outline-primary">
                <i class="fas fa-plus"></i> Yeni Ürün Ekle
            </a>
        </div>
    </div>
</div>

<?php
// Hata mesajı varsa göster
if (isset($error)) {
    echo '<div class="alert alert-danger">' . $error . '</div>';
}
?>

<!-- Arama Formları -->
<div class="card shadow mb-3">
    <div class="card-header py-2">
        <ul class="nav nav-tabs card-header-tabs" id="arama-tabs" role="tablist">
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($arama_modu == 'hizli') ? 'active' : ''; ?>" 
                        id="hizli-arama-tab" data-bs-toggle="tab" data-bs-target="#hizli-arama"
                        type="button" role="tab" aria-controls="hizli-arama" aria-selected="<?php echo ($arama_modu == 'hizli') ? 'true' : 'false'; ?>">
                    <i class="fas fa-bolt"></i> Hızlı Arama
                </button>
            </li>
            <li class="nav-item" role="presentation">
                <button class="nav-link <?php echo ($arama_modu == 'detayli') ? 'active' : ''; ?>" 
                        id="detayli-arama-tab" data-bs-toggle="tab" data-bs-target="#detayli-arama"
                        type="button" role="tab" aria-controls="detayli-arama" aria-selected="<?php echo ($arama_modu == 'detayli') ? 'true' : 'false'; ?>">
                    <i class="fas fa-search-plus"></i> Detaylı Arama
                </button>
            </li>
        </ul>
    </div>
    <div class="card-body py-2">
        <div class="tab-content" id="arama-tabs-content">
            <!-- Hızlı Arama Formu -->
            <div class="tab-pane fade <?php echo ($arama_modu == 'hizli') ? 'show active' : ''; ?>" id="hizli-arama" role="tabpanel" aria-labelledby="hizli-arama-tab">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="mb-0">
                    <input type="hidden" name="arama_modu" value="hizli">
                    <div class="input-group">
                        <input type="text" class="form-control" 
                               placeholder="Stok kodu, ürün adı, açıklama, marka, model veya kategori..." 
                               name="arama" value="<?php echo htmlspecialchars($arama); ?>" autofocus>
                        <button class="btn btn-primary" type="submit">
                            <i class="fas fa-search"></i> Ara
                        </button>
                        <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?arama_modu=hizli" class="btn btn-secondary">
                            <i class="fas fa-eraser"></i>
                        </a>
                    </div>
                    <small class="form-text text-muted mt-1">
                        <i class="fas fa-info-circle"></i> Stok kodu, ürün adı, açıklama, marka, model veya kategoride eşleşme arar.
                    </small>
                </form>
            </div>
            
            <!-- Detaylı Arama Formu -->
            <div class="tab-pane fade <?php echo ($arama_modu == 'detayli') ? 'show active' : ''; ?>" id="detayli-arama" role="tabpanel" aria-labelledby="detayli-arama-tab">
                <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="get" class="row g-2">
                    <input type="hidden" name="arama_modu" value="detayli">
                    
                    <!-- Temel Bilgiler -->
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="stok_kodu" name="stok_kodu" value="<?php echo htmlspecialchars($stok_kodu); ?>" placeholder="Stok Kodu">
                    </div>
                    <div class="col-md-4">
                        <input type="text" class="form-control form-control-sm" id="urun_adi" name="urun_adi" value="<?php echo htmlspecialchars($urun_adi); ?>" placeholder="Ürün Adı">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select form-select-sm select2" id="kategori_id" name="kategori_id">
                            <option value="">Kategori</option>
                            <?php
                            // Kategorileri listele
                            try {
                                $stmt = $db->query("SELECT * FROM product_categories WHERE status = 'active' ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    $selected = ($kategori_id == $row['id']) ? 'selected' : '';
                                    echo '<option value="' . $row['id'] . '" ' . $selected . '>' . htmlspecialchars($row['name']) . '</option>';
                                }
                            } catch (PDOException $e) {
                                // Hata durumunda
                            }
                            ?>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="marka" name="marka" value="<?php echo htmlspecialchars($marka); ?>" placeholder="Marka">
                    </div>
                    <div class="col-md-2">
                        <input type="text" class="form-control form-control-sm" id="model" name="model" value="<?php echo htmlspecialchars($model); ?>" placeholder="Model">
                    </div>
                    
                    <!-- İkinci Satır -->
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" id="min_stok" name="min_stok" value="<?php echo $min_stok !== null ? htmlspecialchars($min_stok) : ''; ?>" placeholder="Min. Stok Miktarı">
                    </div>
                    <div class="col-md-2">
                        <input type="number" class="form-control form-control-sm" id="max_stok" name="max_stok" value="<?php echo $max_stok !== null ? htmlspecialchars($max_stok) : ''; ?>" placeholder="Max. Stok Miktarı">
                    </div>
                    
                    <div class="col-md-2">
                        <input type="number" step="0.01" class="form-control form-control-sm" id="min_fiyat" name="min_fiyat" value="<?php echo $min_fiyat !== null ? htmlspecialchars($min_fiyat) : ''; ?>" placeholder="Min. Fiyat (₺)">
                    </div>
                    <div class="col-md-2">
                        <input type="number" step="0.01" class="form-control form-control-sm" id="max_fiyat" name="max_fiyat" value="<?php echo $max_fiyat !== null ? htmlspecialchars($max_fiyat) : ''; ?>" placeholder="Max. Fiyat (₺)">
                    </div>
                    
                    <div class="col-md-2">
                        <select class="form-select form-select-sm" id="durum" name="durum">
                            <option value="">Durum</option>
                            <option value="active" <?php echo ($durum == 'active') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="passive" <?php echo ($durum == 'passive') ? 'selected' : ''; ?>>Pasif</option>
                        </select>
                    </div>
                    
                    <!-- Butonlar -->
                    <div class="col-md-2">
                        <div class="d-grid gap-2 d-md-block">
                            <button type="submit" class="btn btn-primary btn-sm">
                                <i class="fas fa-search"></i> Ara
                            </button>
                            <a href="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>?arama_modu=detayli" class="btn btn-secondary btn-sm">
                                <i class="fas fa-eraser"></i>
                            </a>
                        </div>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php if ($filtreSorgusu): ?>
<!-- Arama Sonuçları -->
<div class="card shadow mb-4">
    <div class="card-header py-2 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Sonuçlar (<?php echo count($urunler); ?> Ürün)</h6>
        <?php if (count($urunler) > 0): ?>
        <div>
            <button type="button" class="btn btn-sm btn-success" onclick="exportSearchResults()">
                <i class="fas fa-file-excel"></i> Excel
            </button>
        </div>
        <?php endif; ?>
    </div>
    <div class="card-body">
        <?php if (count($urunler) > 0): ?>
            <div class="table-responsive">
                <?php
                // Ürünleri gruplarına göre düzenle
                $groupedProducts = [];
                foreach ($urunler as $urun) {
                    $groupId = isset($urun['group_id']) && $urun['group_id'] > 0 ? $urun['group_id'] : 'single_' . $urun['id'];
                    if (!isset($groupedProducts[$groupId])) {
                        $groupedProducts[$groupId] = [];
                    }
                    $groupedProducts[$groupId][] = $urun;
                }
                ?>
                
                <style>
                    .product-group {
                        border: 1px solid #ddd;
                        border-radius: 4px;
                        margin-bottom: 10px;
                        overflow: hidden;
                    }
                    .product-group-title {
                        background-color: #f8f9fa;
                        padding: 5px 10px;
                        font-weight: bold;
                        border-bottom: 1px solid #ddd;
                        font-size: 0.9rem;
                    }
                    .product-group-content {
                        padding: 0;
                    }
                    .product-group-content table {
                        margin-bottom: 0;
                    }
                    .product-group .main-product {
                        background-color: #e6f2ff !important;
                    }
                    .product-group .alternative-product {
                        background-color: #fff3cd !important;
                    }
                    .product-group table th {
                        position: sticky;
                        top: 0;
                        background-color: #fff;
                        z-index: 10;
                        padding: 6px;
                        font-size: 0.85rem;
                    }
                    .product-group table td {
                        padding: 5px 6px;
                        font-size: 0.85rem;
                    }
                    .btn-sm {
                        padding: 0.2rem 0.4rem;
                        font-size: 0.75rem;
                    }
                    .table-compact {
                        margin-bottom: 0;
                    }
                </style>
                
                <?php foreach ($groupedProducts as $groupId => $products): ?>
                    <div class="product-group">
                        <?php
                        // İlk ürünün ana ürün olup olmadığını kontrol et
                        $hasMainProduct = false;
                        $mainProductName = '';
                        
                        foreach ($products as $product) {
                            if (isset($product['is_muadil']) && $product['is_muadil'] == 0) {
                                $hasMainProduct = true;
                                // HTML özel karakterlerini düzgün escape et
                                $mainProductName = $product['name'];
                                break;
                            }
                        }
                        ?>
                        
                        <?php if (count($products) > 1 || (strpos($groupId, 'single_') === false)): ?>
                        <div class="product-group-title">
                            <?php if ($hasMainProduct): ?>
                                <i class="fas fa-minus"></i> <?php echo html_entity_decode(htmlspecialchars_decode($mainProductName)); ?> ve Muadil Ürünleri
                            <?php else: ?>
                                <i class="fas fa-minus"></i> Muadil Ürün Grubu
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                        
                        <div class="product-group-content">
                            <table class="table table-bordered table-hover table-compact" width="100%" cellspacing="0">
                                <thead>
                                    <tr>
                                        <th>Stok Kodu</th>
                                        <th>Ürün Adı</th>
                                        <th>Kategori</th>
                                        <th>Marka/Model</th>
                                        <th class="text-end">Stok</th>
                                        <th class="text-end">Satış Fiyatı</th>
                                        <th>Durum</th>
                                        <th class="text-center">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($products as $urun): 
                                        // Ürünün sınıfını belirle (ana ürün veya muadil)
                                        $row_class = '';
                                        $badge_text = '';
                                        
                                        if (isset($urun['is_muadil']) && $urun['is_muadil'] == 0) {
                                            $row_class = 'main-product';
                                        } else if (isset($urun['is_muadil']) && $urun['is_muadil'] == 1) {
                                            $row_class = 'alternative-product';
                                            $badge_text = '<span class="badge bg-warning text-dark ms-1">Muadil</span>';
                                        } else if (isset($urun['is_alternative']) && $urun['is_alternative'] == 1) {
                                            $row_class = 'alternative-product';
                                            $badge_text = '<span class="badge bg-warning text-dark ms-1">Muadil</span>';
                                        }
                                    ?>
                                        <tr class="<?php echo $row_class; ?>">
                                            <td><?php echo htmlspecialchars($urun['code']); ?></td>
                                            <td>
                                                <a href="urun_detay.php?id=<?php echo $urun['id']; ?>">
                                                    <?php echo html_entity_decode(htmlspecialchars_decode($urun['name'])); ?>
                                                </a>
                                                <?php echo $badge_text; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($urun['category_name'] ?? ''); ?></td>
                                            <td>
                                                <?php if (!empty($urun['brand'])): ?>
                                                    <?php echo htmlspecialchars($urun['brand']); ?>
                                                    <?php if (!empty($urun['model'])): ?>
                                                        / <?php echo htmlspecialchars($urun['model']); ?>
                                                    <?php endif; ?>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-end"><?php echo number_format($urun['current_stock'], 2, ',', '.'); ?> <?php echo htmlspecialchars($urun['unit']); ?></td>
                                            <td class="text-end">₺<?php echo number_format($urun['sale_price'], 2, ',', '.'); ?></td>
                                            <td>
                                                <?php if ($urun['status'] == 'active'): ?>
                                                    <span class="badge bg-success">Aktif</span>
                                                <?php elseif ($urun['status'] == 'passive'): ?>
                                                    <span class="badge bg-danger">Pasif</span>
                                                <?php endif; ?>
                                            </td>
                                            <td class="text-center">
                                                <div class="btn-group">
                                                    <a href="urun_detay.php?id=<?php echo $urun['id']; ?>" class="btn btn-sm btn-info" title="Detay"><i class="fas fa-eye"></i></a>
                                                    <a href="urun_duzenle.php?id=<?php echo $urun['id']; ?>" class="btn btn-sm btn-primary" title="Düzenle"><i class="fas fa-edit"></i></a>
                                                    <a href="stok_hareketi_ekle.php?urun_id=<?php echo $urun['id']; ?>" class="btn btn-sm btn-success" title="Stok Hareketi Ekle"><i class="fas fa-plus"></i></a>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php else: ?>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Arama kriterlerinize uygun ürün bulunamadı.
            </div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Tab değiştiğinde form modunu ayarla
        const tabLinks = document.querySelectorAll('button[data-bs-toggle="tab"]');
        tabLinks.forEach(function(tabLink) {
            tabLink.addEventListener('shown.bs.tab', function (event) {
                const targetId = event.target.getAttribute('id');
                if (targetId === 'hizli-arama-tab') {
                    document.querySelectorAll('input[name="arama_modu"]').forEach(input => input.value = 'hizli');
                } else if (targetId === 'detayli-arama-tab') {
                    document.querySelectorAll('input[name="arama_modu"]').forEach(input => input.value = 'detayli');
                }
            });
        });
        
        // Hızlı arama inputu üzerinde enter tuşuna basıldığında form gönderme
        const hizliAramaInput = document.querySelector('#hizli-arama input[name="arama"]');
        if (hizliAramaInput) {
            hizliAramaInput.addEventListener('keypress', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.closest('form').submit();
                }
            });
        }
        
        // Grup başlıklarının tıklanabilir olması için
        const groupTitles = document.querySelectorAll('.product-group-title');
        groupTitles.forEach(function(title) {
            title.addEventListener('click', function() {
                const content = this.nextElementSibling;
                if (content.style.display === 'none') {
                    content.style.display = 'block';
                    this.querySelector('i').classList.remove('fa-plus');
                    this.querySelector('i').classList.add('fa-minus');
                } else {
                    content.style.display = 'none';
                    this.querySelector('i').classList.remove('fa-minus');
                    this.querySelector('i').classList.add('fa-plus');
                }
            });
            
            // İkon değiştir
            const icon = title.querySelector('i');
            if (icon && icon.classList.contains('fa-layer-group')) {
                icon.classList.remove('fa-layer-group');
                icon.classList.add('fa-minus');
            }
            
            // Tıklanabilir olduğunu göster
            title.style.cursor = 'pointer';
        });
    });
    
    // Excel'e aktarma fonksiyonu - Tüm arama sonuçlarını toplar
    function exportSearchResults() {
        // Tüm grupları birleştirerek bir tablo oluştur
        let combinedTable = document.createElement('table');
        combinedTable.id = 'combined_search_results';
        combinedTable.style.display = 'none';
        document.body.appendChild(combinedTable);
        
        // Başlık satırını ekle
        let headerRow = document.createElement('tr');
        ['Stok Kodu', 'Ürün Adı', 'Kategori', 'Marka/Model', 'Stok', 'Satış Fiyatı', 'Durum'].forEach(header => {
            let th = document.createElement('th');
            th.textContent = header;
            headerRow.appendChild(th);
        });
        
        let thead = document.createElement('thead');
        thead.appendChild(headerRow);
        combinedTable.appendChild(thead);
        
        // Tüm gruplardaki verileri bir tbody'e ekle
        let tbody = document.createElement('tbody');
        
        const productGroups = document.querySelectorAll('.product-group');
        productGroups.forEach(group => {
            const rows = group.querySelectorAll('tbody tr');
            rows.forEach(row => {
                let newRow = document.createElement('tr');
                
                // İşlem sütununu hariç tut (son sütun)
                const cells = row.querySelectorAll('td:not(:last-child)');
                cells.forEach(cell => {
                    let newCell = document.createElement('td');
                    newCell.innerHTML = cell.innerHTML.replace(/<\/?a[^>]*>/g, ''); // Linkleri kaldır
                    newRow.appendChild(newCell);
                });
                
                tbody.appendChild(newRow);
            });
        });
        
        combinedTable.appendChild(tbody);
        
        // Excel'e aktar
        exportTableToExcel('combined_search_results', 'urun_arama_sonuclari');
        
        // Geçici tabloyu temizle
        document.body.removeChild(combinedTable);
    }
    
    // Excel dönüştürme temel fonksiyonu
    function exportTableToExcel(tableID, filename = '') {
        var downloadLink;
        var dataType = 'application/vnd.ms-excel';
        var tableSelect = document.getElementById(tableID);
        var tableHTML = tableSelect.outerHTML.replace(/ /g, '%20');
        
        // Dosya adını oluştur
        filename = filename ? filename + '.xls' : 'excel_data.xls';
        
        // Link oluştur ve indir
        downloadLink = document.createElement("a");
        document.body.appendChild(downloadLink);
        
        if(navigator.msSaveOrOpenBlob) {
            var blob = new Blob(['\ufeff', tableHTML], {
                type: dataType
            });
            navigator.msSaveOrOpenBlob(blob, filename);
        } else {
            // Base64 formatına dönüştür
            downloadLink.href = 'data:' + dataType + ', ' + tableHTML;
            downloadLink.download = filename;
            downloadLink.click();
        }
    }
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 