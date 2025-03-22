<?php
/**
 * ERP Sistem - Stok Verileri Aktarım Sayfası
 * 
 * Bu dosya dış kaynaklardan stok verilerini aktarır.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Kaynak veritabanı bilgileri kontrolü
if (!isset($_SESSION['source_db'])) {
    $_SESSION['error_message'] = "Önce veritabanı bağlantısı yapmalısınız.";
    header('Location: index.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Veri Aktarımı - Stok Aktarımı";

// Hata ve başarı mesajları
$errors = [];
$success = [];

if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success[] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

// Kaynak veritabanına bağlan
try {
    $source_db = $_SESSION['source_db'];
    $dsn = "mysql:host={$source_db['host']};dbname={$source_db['name']};charset={$source_db['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass'], $options);
    
    // Veritabanı bağlantısı başarılı
    
    // POST isteğinde tablo seçimi yapıldı mı?
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        // Veri aktarımı yapılacak mı?
        if (isset($_POST['import_data']) && $_POST['import_data'] == 1) {
            // Veri aktarımını başlat
            
            $sourceTable = isset($_POST['source_table']) ? clean($_POST['source_table']) : '';
            $fieldMappings = isset($_POST['field_mapping']) ? $_POST['field_mapping'] : [];
            
            if (empty($sourceTable) || empty($fieldMappings)) {
                $errors[] = "Lütfen tüm gerekli alanları doldurun.";
            } else {
                // Veri aktarımını gerçekleştir
                try {
                    // Veritabanı işlemini başlat
                    $db->beginTransaction();
                    
                    // Kaynak tablodan verileri al
                    $stmt = $sourceDb->query("SELECT * FROM `$sourceTable`");
                    $sourceData = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $importedCount = 0;
                    $errorCount = 0;
                    
                    // Her kayıt için işlem yap
                    foreach ($sourceData as $record) {
                        // Hedef tabloya eklenecek verileri hazırla
                        $productData = [];
                        
                        foreach ($fieldMappings as $targetField => $sourceField) {
                            if (!empty($sourceField) && isset($record[$sourceField])) {
                                // ">" karakterini düzgün işlemek için cleanProductName kullan
                                if ($targetField == 'name') {
                                    $productData[$targetField] = cleanProductName($record[$sourceField]);
                                } else {
                                    $productData[$targetField] = $record[$sourceField];
                                }
                            }
                        }
                        
                        // Zorunlu alanları kontrol et
                        if (empty($productData['code']) || empty($productData['name'])) {
                            $errorCount++;
                            continue;
                        }
                        
                        // Varsayılan değerleri ekle
                        if (!isset($productData['unit'])) $productData['unit'] = 'Adet';
                        if (!isset($productData['status'])) $productData['status'] = 'active';
                        
                        // Oluşturma tarihini ve kullanıcı bilgisini ekle
                        $productData['created_at'] = date('Y-m-d H:i:s');
                        $productData['created_by'] = $_SESSION['user_id'];
                        
                        // Aynı stok kodu var mı kontrol et
                        $checkStmt = $db->prepare("SELECT COUNT(*) FROM products WHERE code = :code");
                        $checkStmt->bindParam(':code', $productData['code']);
                        $checkStmt->execute();
                        
                        if ($checkStmt->fetchColumn() > 0) {
                            // Bu stok kodu zaten var, güncelleme yap
                            $updateFields = [];
                            $updateParams = [];
                            
                            foreach ($productData as $field => $value) {
                                if ($field != 'code' && $field != 'created_at' && $field != 'created_by') {
                                    $updateFields[] = "`$field` = :$field";
                                    $updateParams[":$field"] = $value;
                                }
                            }
                            
                            // Güncelleme alanları varsa güncelle
                            if (!empty($updateFields)) {
                                $updateParams[':code'] = $productData['code'];
                                $updateParams[':updated_at'] = date('Y-m-d H:i:s');
                                $updateParams[':updated_by'] = $_SESSION['user_id'];
                                
                                $updateSql = "UPDATE products SET " . implode(', ', $updateFields) . ", 
                                          updated_at = :updated_at, updated_by = :updated_by 
                                          WHERE code = :code";
                                
                                $updateStmt = $db->prepare($updateSql);
                                $updateStmt->execute($updateParams);
                                
                                $importedCount++;
                            }
                        } else {
                            // Yeni kayıt ekle
                            $insertFields = array_keys($productData);
                            $insertPlaceholders = array_map(function($field) {
                                return ":$field";
                            }, $insertFields);
                            
                            $insertSql = "INSERT INTO products (`" . implode('`, `', $insertFields) . "`) 
                                     VALUES (" . implode(', ', $insertPlaceholders) . ")";
                            
                            $insertStmt = $db->prepare($insertSql);
                            
                            foreach ($productData as $field => $value) {
                                $insertStmt->bindValue(":$field", $value);
                            }
                            
                            $insertStmt->execute();
                            
                            $importedCount++;
                        }
                    }
                    
                    // İşlemi tamamla
                    $db->commit();
                    
                    $success[] = "Veri aktarımı tamamlandı. $importedCount kayıt başarıyla aktarıldı." . 
                            ($errorCount > 0 ? " $errorCount kayıt hatalı veya eksik olduğu için atlandı." : "");
                    
                } catch (PDOException $e) {
                    // Hata durumunda işlemi geri al
                    $db->rollback();
                    $errors[] = "Veri aktarımı sırasında hata oluştu: " . $e->getMessage();
                }
            }
            
        } else {
            // Tablo seçimi ve alan eşleştirmesi
            $data_types = isset($_POST['data_type']) ? $_POST['data_type'] : [];
            
            // Stok bilgileri için tablo seçilmiş mi?
            $productTable = '';
            foreach ($data_types as $table => $type) {
                if ($type == 'products') {
                    $productTable = $table;
                    break;
                }
            }
            
            if (empty($productTable)) {
                $errors[] = "Lütfen önce stok bilgileri için bir tablo seçin.";
                $sourceColumns = [];
            } else {
                // Seçilen tablonun sütunlarını al
                $stmt = $sourceDb->query("SHOW COLUMNS FROM `$productTable`");
                $sourceColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Hedef tablonun sütunlarını al
                $stmt = $db->query("SHOW COLUMNS FROM products");
                $targetColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Örnek veri al
                $stmt = $sourceDb->query("SELECT * FROM `$productTable` LIMIT 1");
                $sampleData = $stmt->fetch(PDO::FETCH_ASSOC);
            }
        }
    } else {
        // POST isteği yapılmadı, tabloları göster
        header('Location: tablo_secimi.php');
        exit;
    }
    
} catch (PDOException $e) {
    $errors[] = "Veritabanı bağlantı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Stok Verileri Aktarımı</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="tablo_secimi.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Tablo Seçimine Dön
            </a>
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-home"></i> Ana Sayfa
            </a>
        </div>
    </div>
</div>

<?php
// Hata ve başarı mesajları
if (!empty($errors)) {
    echo '<div class="alert alert-danger">';
    foreach ($errors as $error) {
        echo '<p>' . $error . '</p>';
    }
    echo '</div>';
}

if (!empty($success)) {
    echo '<div class="alert alert-success">';
    foreach ($success as $message) {
        echo '<p>' . $message . '</p>';
    }
    echo '</div>';
}

// Veri aktarımı tamamlandıysa
if (!empty($success) && strpos($success[0], 'Veri aktarımı tamamlandı') !== false) {
    echo '<div class="text-center mb-4">';
    echo '<a href="index.php" class="btn btn-primary">Veri Aktarım Ana Sayfasına Dön</a>';
    echo '</div>';
} else {
    // Alan eşleştirme formu
    if (!empty($productTable) && !empty($sourceColumns) && !empty($targetColumns)):
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Alan Eşleştirme</h6>
    </div>
    <div class="card-body">
        <p>Lütfen kaynak tablodaki alanları, hedef tablodaki karşılıklarıyla eşleştirin:</p>
        
        <?php if (!empty($sampleData)): ?>
        <div class="alert alert-info">
            <h6>Örnek Veri:</h6>
            <table class="table table-sm table-bordered">
                <tr>
                    <?php foreach ($sampleData as $field => $value): ?>
                    <th><?php echo htmlspecialchars($field); ?></th>
                    <?php endforeach; ?>
                </tr>
                <tr>
                    <?php foreach ($sampleData as $value): ?>
                    <td><?php echo htmlspecialchars(substr($value, 0, 50)) . (strlen($value) > 50 ? '...' : ''); ?></td>
                    <?php endforeach; ?>
                </tr>
            </table>
        </div>
        <?php endif; ?>
        
        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="post">
            <input type="hidden" name="source_table" value="<?php echo htmlspecialchars($productTable); ?>">
            <input type="hidden" name="import_data" value="1">
            
            <div class="table-responsive">
                <table class="table table-bordered">
                    <thead>
                        <tr>
                            <th>Hedef Alan (ERP Sistem)</th>
                            <th>Kaynak Alan</th>
                            <th>Açıklama</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        // Önemli alanlar önce
                        $primaryFields = ['code', 'name', 'unit', 'barcode', 'brand', 'model', 'purchase_price', 'sale_price', 'current_stock'];
                        
                        foreach ($primaryFields as $field): 
                            // Bu alan hedef tabloda var mı?
                            if (in_array($field, $targetColumns)):
                        ?>
                        <tr class="table-primary">
                            <td>
                                <strong><?php echo htmlspecialchars($field); ?></strong>
                                <?php if (in_array($field, ['code', 'name'])): ?>
                                <span class="badge bg-danger">Zorunlu</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select name="field_mapping[<?php echo htmlspecialchars($field); ?>]" class="form-select" <?php echo in_array($field, ['code', 'name']) ? 'required' : ''; ?>>
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($sourceColumns as $sourceColumn): ?>
                                    <option value="<?php echo htmlspecialchars($sourceColumn); ?>" <?php 
                                        // Benzer alanları otomatik eşle
                                        echo (strtolower($sourceColumn) == strtolower($field) || 
                                              strpos(strtolower($sourceColumn), strtolower($field)) !== false) ? 'selected' : ''; 
                                    ?>>
                                        <?php echo htmlspecialchars($sourceColumn); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php
                                $descriptions = [
                                    'code' => 'Stok Kodu (Zorunlu, benzersiz olmalı)',
                                    'name' => 'Ürün Adı (Zorunlu)',
                                    'unit' => 'Birim (Adet, Kg, Lt, vb.)',
                                    'barcode' => 'Barkod',
                                    'brand' => 'Marka',
                                    'model' => 'Model',
                                    'purchase_price' => 'Alış Fiyatı',
                                    'sale_price' => 'Satış Fiyatı',
                                    'current_stock' => 'Mevcut Stok Miktarı'
                                ];
                                echo isset($descriptions[$field]) ? $descriptions[$field] : '';
                                ?>
                            </td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        
                        // Diğer alanlar
                        foreach ($targetColumns as $field): 
                            if (!in_array($field, $primaryFields) && $field != 'id' && 
                                !in_array($field, ['created_at', 'updated_at', 'created_by', 'updated_by'])):
                        ?>
                        <tr>
                            <td><?php echo htmlspecialchars($field); ?></td>
                            <td>
                                <select name="field_mapping[<?php echo htmlspecialchars($field); ?>]" class="form-select">
                                    <option value="">Seçiniz</option>
                                    <?php foreach ($sourceColumns as $sourceColumn): ?>
                                    <option value="<?php echo htmlspecialchars($sourceColumn); ?>" <?php 
                                        echo (strtolower($sourceColumn) == strtolower($field) || 
                                              strpos(strtolower($sourceColumn), strtolower($field)) !== false) ? 'selected' : ''; 
                                    ?>>
                                        <?php echo htmlspecialchars($sourceColumn); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td>
                                <?php
                                $descriptions = [
                                    'category_id' => 'Kategori ID',
                                    'description' => 'Açıklama',
                                    'tax_rate' => 'KDV Oranı',
                                    'min_stock' => 'Minimum Stok Seviyesi',
                                    'status' => 'Durum (active/passive)',
                                    'oem_no' => 'OEM Numarası',
                                    'cross_reference' => 'Çapraz Referans',
                                    'dimensions' => 'Ürün Ölçüleri',
                                    'shelf_code' => 'Raf Kodu',
                                    'vehicle_brand' => 'Araç Markası',
                                    'vehicle_model' => 'Araç Modeli',
                                    'main_category' => 'Ana Kategori',
                                    'sub_category' => 'Alt Kategori'
                                ];
                                echo isset($descriptions[$field]) ? $descriptions[$field] : '';
                                ?>
                            </td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </tbody>
                </table>
            </div>
            
            <div class="alert alert-warning">
                <strong>Uyarı:</strong> Veri aktarımı öncesinde veritabanı yedeği almanız önerilir. Aktarım sırasında mevcut stok kodları ile eşleşen ürünler güncellenecektir.
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-database"></i> Veri Aktarımını Başlat
                </button>
                <a href="tablo_secimi.php" class="btn btn-secondary">
                    <i class="fas fa-times"></i> İptal
                </a>
            </div>
        </form>
    </div>
</div>

<?php 
    endif;
}
?>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 