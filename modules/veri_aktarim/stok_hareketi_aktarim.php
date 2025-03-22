<?php
/**
 * ERP Sistem - Stok Hareketleri Aktarım Sayfası
 * 
 * Bu dosya dış kaynaklardan stok hareketi verilerini aktarır.
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
$pageTitle = "Veri Aktarımı - Stok Hareketleri Aktarımı";

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
    
    // Ürün ve cari listelerini al (eşleştirme için)
    $products = [];
    try {
        $stmt = $db->query("SELECT id, code, name FROM products ORDER BY name");
        while ($row = $stmt->fetch()) {
            $products[$row['id']] = $row;
            $productsByCode[$row['code']] = $row;
        }
    } catch (PDOException $e) {
        $errors[] = "Ürün listesi alınamadı: " . $e->getMessage();
    }
    
    $customers = [];
    $customersByCode = [];
    try {
        $stmt = $db->query("SELECT id, code, name FROM customers ORDER BY name");
        while ($row = $stmt->fetch()) {
            $customers[$row['id']] = $row;
            $customersByCode[$row['code']] = $row;
        }
    } catch (PDOException $e) {
        // Tablo yok veya erişim hatası
    }
    
    $suppliers = [];
    $suppliersByCode = [];
    try {
        $stmt = $db->query("SELECT id, code, name FROM suppliers ORDER BY name");
        while ($row = $stmt->fetch()) {
            $suppliers[$row['id']] = $row;
            $suppliersByCode[$row['code']] = $row;
        }
    } catch (PDOException $e) {
        // Tablo yok veya erişim hatası
    }
    
    // Veritabanı bağlantısı başarılı
    
    // POST isteğinde tablo seçimi yapıldı mı?
    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        
        // Veri aktarımı yapılacak mı?
        if (isset($_POST['import_data']) && $_POST['import_data'] == 1) {
            // Veri aktarımını başlat
            
            $sourceTable = isset($_POST['source_table']) ? clean($_POST['source_table']) : '';
            $fieldMappings = isset($_POST['field_mapping']) ? $_POST['field_mapping'] : [];
            $hareketTipi = isset($_POST['hareket_tipi']) ? clean($_POST['hareket_tipi']) : 'all';
            
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
                    
                    // Hedef tablo
                    $targetTable = 'stock_movements';
                    
                    // Her kayıt için işlem yap
                    foreach ($sourceData as $record) {
                        // Hedef tabloya eklenecek verileri hazırla
                        $hareketData = [];
                        
                        foreach ($fieldMappings as $targetField => $sourceField) {
                            if (!empty($sourceField) && isset($record[$sourceField])) {
                                $hareketData[$targetField] = $record[$sourceField];
                            }
                        }
                        
                        // Hareket tipi kontrolü - sadece belirli tipte hareketler aktarılacaksa
                        if ($hareketTipi != 'all' && isset($hareketData['type'])) {
                            if ($hareketData['type'] != $hareketTipi) {
                                continue; // Bu hareket tipi aktarılmayacak
                            }
                        }
                        
                        // Zorunlu alanları kontrol et
                        if (empty($hareketData['product_id']) || empty($hareketData['quantity']) || 
                            empty($hareketData['date']) || empty($hareketData['type'])) {
                            $errorCount++;
                            continue;
                        }
                        
                        // Ürün kodu varsa ID'ye çevir
                        if (isset($hareketData['product_code']) && !empty($hareketData['product_code'])) {
                            if (isset($productsByCode[$hareketData['product_code']])) {
                                $hareketData['product_id'] = $productsByCode[$hareketData['product_code']]['id'];
                            } else {
                                $errorCount++;
                                continue; // Ürün bulunamadı
                            }
                        }
                        
                        // Cari kodu varsa ID'ye çevir
                        if (isset($hareketData['customer_code']) && !empty($hareketData['customer_code'])) {
                            if (isset($customersByCode[$hareketData['customer_code']])) {
                                $hareketData['customer_id'] = $customersByCode[$hareketData['customer_code']]['id'];
                            }
                        }
                        
                        if (isset($hareketData['supplier_code']) && !empty($hareketData['supplier_code'])) {
                            if (isset($suppliersByCode[$hareketData['supplier_code']])) {
                                $hareketData['supplier_id'] = $suppliersByCode[$hareketData['supplier_code']]['id'];
                            }
                        }
                        
                        // Varsayılan değerleri ekle
                        if (!isset($hareketData['status'])) $hareketData['status'] = 'completed';
                        
                        // Oluşturma tarihini ve kullanıcı bilgisini ekle
                        $hareketData['created_at'] = date('Y-m-d H:i:s');
                        $hareketData['created_by'] = $_SESSION['user_id'];
                        
                        // Yeni kayıt ekle
                        $insertFields = array_keys($hareketData);
                        $insertPlaceholders = array_map(function($field) {
                            return ":$field";
                        }, $insertFields);
                        
                        $insertSql = "INSERT INTO $targetTable (`" . implode('`, `', $insertFields) . "`) 
                                      VALUES (" . implode(', ', $insertPlaceholders) . ")";
                        
                        $insertStmt = $db->prepare($insertSql);
                        
                        foreach ($hareketData as $field => $value) {
                            $insertStmt->bindValue(":$field", $value);
                        }
                        
                        $insertStmt->execute();
                        
                        $importedCount++;
                    }
                    
                    // İşlemi tamamla
                    $db->commit();
                    
                    $success[] = "Veri aktarımı tamamlandı. $importedCount stok hareketi başarıyla aktarıldı." . 
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
            
            // Stok hareketleri için tablo seçilmiş mi?
            $hareketTable = '';
            
            foreach ($data_types as $table => $type) {
                if ($type == 'stock_movements') {
                    $hareketTable = $table;
                    break;
                }
            }
            
            if (empty($hareketTable)) {
                $errors[] = "Lütfen önce stok hareketleri için bir tablo seçin.";
                $sourceColumns = [];
            } else {
                // Seçilen tablonun sütunlarını al
                $stmt = $sourceDb->query("SHOW COLUMNS FROM `$hareketTable`");
                $sourceColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Hedef tablonun sütunlarını al
                try {
                    $stmt = $db->query("SHOW COLUMNS FROM stock_movements");
                    $targetColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (PDOException $e) {
                    // Tablo yoksa varsayılan alanlar
                    $targetColumns = ['id', 'date', 'product_id', 'quantity', 'type', 'reference_no', 'unit_price', 'customer_id', 'supplier_id', 'notes', 'status'];
                }
                
                // Örnek veri al
                $stmt = $sourceDb->query("SELECT * FROM `$hareketTable` LIMIT 1");
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
    <h1 class="h2">Stok Hareketleri Aktarımı</h1>
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
    if (!empty($hareketTable) && !empty($sourceColumns) && !empty($targetColumns)):
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Stok Hareketi Veri Eşleştirme</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label for="hareket_tipi" class="form-label">Aktarılacak Hareket Tipleri</label>
            <select name="hareket_tipi" id="hareket_tipi" class="form-select">
                <option value="all">Tüm Hareketler</option>
                <option value="in">Sadece Giriş Hareketleri</option>
                <option value="out">Sadece Çıkış Hareketleri</option>
                <option value="transfer">Sadece Transfer Hareketleri</option>
                <option value="count">Sadece Sayım Hareketleri</option>
            </select>
        </div>
        
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
            <input type="hidden" name="source_table" value="<?php echo htmlspecialchars($hareketTable); ?>">
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
                        $primaryFields = ['date', 'product_id', 'product_code', 'quantity', 'type', 'reference_no', 'unit_price'];
                        
                        foreach ($primaryFields as $field): 
                            // Bu alan hedef tabloda var mı veya ilişkili alan mı?
                            if (in_array($field, $targetColumns) || $field == 'product_code'):
                        ?>
                        <tr class="table-primary">
                            <td>
                                <strong><?php echo htmlspecialchars($field); ?></strong>
                                <?php if (in_array($field, ['date', 'product_id', 'quantity', 'type']) || $field == 'product_code'): ?>
                                <span class="badge bg-danger">Zorunlu</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select name="field_mapping[<?php echo htmlspecialchars($field); ?>]" class="form-select" <?php echo in_array($field, ['date', 'product_id', 'quantity', 'type']) || $field == 'product_code' ? 'required' : ''; ?>>
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
                                    'date' => 'Hareket Tarihi (Zorunlu, yyyy-mm-dd formatında)',
                                    'product_id' => 'Ürün ID (Zorunlu, yerel ürün tablosu ID değeri)',
                                    'product_code' => 'Ürün Kodu (Zorunlu, eğer ürün ID yoksa)',
                                    'quantity' => 'Miktar (Zorunlu, giriş için pozitif, çıkış için negatif)',
                                    'type' => 'Hareket Tipi (Zorunlu, in=Giriş, out=Çıkış, transfer=Transfer, count=Sayım)',
                                    'reference_no' => 'Referans No (Belge No, Fatura No vb.)',
                                    'unit_price' => 'Birim Fiyat'
                                ];
                                echo isset($descriptions[$field]) ? $descriptions[$field] : '';
                                ?>
                            </td>
                        </tr>
                        <?php 
                            endif;
                        endforeach; 
                        
                        // Cari hesap alanları
                        $cariFields = ['customer_id', 'customer_code', 'supplier_id', 'supplier_code'];
                        
                        foreach ($cariFields as $field): 
                            // Bu alan hedef tabloda var mı veya ilişkili alan mı?
                            if (in_array($field, $targetColumns) || $field == 'customer_code' || $field == 'supplier_code'):
                        ?>
                        <tr class="table-success">
                            <td><strong><?php echo htmlspecialchars($field); ?></strong></td>
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
                                    'customer_id' => 'Müşteri ID (yerel müşteri tablosu ID değeri)',
                                    'customer_code' => 'Müşteri Kodu (eğer müşteri ID yoksa)',
                                    'supplier_id' => 'Tedarikçi ID (yerel tedarikçi tablosu ID değeri)',
                                    'supplier_code' => 'Tedarikçi Kodu (eğer tedarikçi ID yoksa)'
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
                            if (!in_array($field, $primaryFields) && !in_array($field, $cariFields) && $field != 'id' && 
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
                                    'notes' => 'Notlar',
                                    'status' => 'Durum (completed/pending/cancelled)',
                                    'location_from' => 'Kaynak Depo/Lokasyon',
                                    'location_to' => 'Hedef Depo/Lokasyon',
                                    'serial_number' => 'Seri Numarası',
                                    'batch_number' => 'Parti Numarası',
                                    'expiry_date' => 'Son Kullanma Tarihi'
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
                <strong>Uyarı:</strong> Veri aktarımı öncesinde veritabanı yedeği almanız önerilir. Aktarım sonrası stok miktarları değişecektir.
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