<?php
/**
 * ERP Sistem - Cari Hesaplar Aktarım Sayfası
 * 
 * Bu dosya dış kaynaklardan cari hesap verilerini aktarır.
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
$pageTitle = "Veri Aktarımı - Cari Hesaplar Aktarımı";

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
            $cariTipi = isset($_POST['cari_tipi']) ? clean($_POST['cari_tipi']) : 'customer';
            
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
                    
                    // Hedef tablo belirleme
                    $targetTable = $cariTipi == 'customer' ? 'customers' : 'suppliers';
                    
                    // Her kayıt için işlem yap
                    foreach ($sourceData as $record) {
                        // Hedef tabloya eklenecek verileri hazırla
                        $cariData = [];
                        
                        foreach ($fieldMappings as $targetField => $sourceField) {
                            if (!empty($sourceField) && isset($record[$sourceField])) {
                                $cariData[$targetField] = $record[$sourceField];
                            }
                        }
                        
                        // Zorunlu alanları kontrol et
                        if (empty($cariData['code']) || empty($cariData['name'])) {
                            $errorCount++;
                            continue;
                        }
                        
                        // Varsayılan değerleri ekle
                        if (!isset($cariData['status'])) $cariData['status'] = 'active';
                        
                        // Oluşturma tarihini ve kullanıcı bilgisini ekle
                        $cariData['created_at'] = date('Y-m-d H:i:s');
                        $cariData['created_by'] = $_SESSION['user_id'];
                        
                        // Aynı cari kodu var mı kontrol et
                        $checkStmt = $db->prepare("SELECT COUNT(*) FROM $targetTable WHERE code = :code");
                        $checkStmt->bindParam(':code', $cariData['code']);
                        $checkStmt->execute();
                        
                        if ($checkStmt->fetchColumn() > 0) {
                            // Bu cari kodu zaten var, güncelleme yap
                            $updateFields = [];
                            $updateParams = [];
                            
                            foreach ($cariData as $field => $value) {
                                if ($field != 'code' && $field != 'created_at' && $field != 'created_by') {
                                    $updateFields[] = "`$field` = :$field";
                                    $updateParams[":$field"] = $value;
                                }
                            }
                            
                            // Güncelleme alanları varsa güncelle
                            if (!empty($updateFields)) {
                                $updateParams[':code'] = $cariData['code'];
                                $updateParams[':updated_at'] = date('Y-m-d H:i:s');
                                $updateParams[':updated_by'] = $_SESSION['user_id'];
                                
                                $updateSql = "UPDATE $targetTable SET " . implode(', ', $updateFields) . ", 
                                          updated_at = :updated_at, updated_by = :updated_by 
                                          WHERE code = :code";
                                
                                $updateStmt = $db->prepare($updateSql);
                                $updateStmt->execute($updateParams);
                                
                                $importedCount++;
                            }
                        } else {
                            // Yeni kayıt ekle
                            $insertFields = array_keys($cariData);
                            $insertPlaceholders = array_map(function($field) {
                                return ":$field";
                            }, $insertFields);
                            
                            $insertSql = "INSERT INTO $targetTable (`" . implode('`, `', $insertFields) . "`) 
                                     VALUES (" . implode(', ', $insertPlaceholders) . ")";
                            
                            $insertStmt = $db->prepare($insertSql);
                            
                            foreach ($cariData as $field => $value) {
                                $insertStmt->bindValue(":$field", $value);
                            }
                            
                            $insertStmt->execute();
                            
                            $importedCount++;
                        }
                    }
                    
                    // İşlemi tamamla
                    $db->commit();
                    
                    $success[] = "Veri aktarımı tamamlandı. $importedCount cari hesap başarıyla aktarıldı." . 
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
            
            // Cari hesaplar için tablo seçilmiş mi?
            $cariTable = '';
            $cariType = '';
            
            foreach ($data_types as $table => $type) {
                if ($type == 'customers' || $type == 'suppliers') {
                    $cariTable = $table;
                    $cariType = $type;
                    break;
                }
            }
            
            if (empty($cariTable)) {
                $errors[] = "Lütfen önce cari hesaplar için bir tablo seçin.";
                $sourceColumns = [];
            } else {
                // Seçilen tablonun sütunlarını al
                $stmt = $sourceDb->query("SHOW COLUMNS FROM `$cariTable`");
                $sourceColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Hedef tablonun sütunlarını al (müşteri veya tedarikçi)
                $targetTable = $cariType == 'customers' ? 'customers' : 'suppliers';
                
                try {
                    $stmt = $db->query("SHOW COLUMNS FROM $targetTable");
                    $targetColumns = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (PDOException $e) {
                    // Tablo yoksa varsayılan alanlar
                    $targetColumns = ['id', 'code', 'name', 'tax_number', 'tax_office', 'phone', 'email', 'address', 'city', 'district', 'contact_person', 'notes', 'status'];
                }
                
                // Örnek veri al
                $stmt = $sourceDb->query("SELECT * FROM `$cariTable` LIMIT 1");
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
    <h1 class="h2">Cari Hesaplar Aktarımı</h1>
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
    if (!empty($cariTable) && !empty($sourceColumns) && !empty($targetColumns)):
?>

<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Cari Hesap Veri Eşleştirme</h6>
    </div>
    <div class="card-body">
        <div class="mb-3">
            <label for="cari_tipi" class="form-label">Cari Hesap Tipi</label>
            <select name="cari_tipi" id="cari_tipi" class="form-select" disabled>
                <option value="customer" <?php echo $cariType == 'customers' ? 'selected' : ''; ?>>Müşteri</option>
                <option value="supplier" <?php echo $cariType == 'suppliers' ? 'selected' : ''; ?>>Tedarikçi</option>
            </select>
            <small class="form-text text-muted">Bu değer, tablo seçim ekranında yaptığınız seçime göre belirlenmiştir.</small>
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
            <input type="hidden" name="source_table" value="<?php echo htmlspecialchars($cariTable); ?>">
            <input type="hidden" name="import_data" value="1">
            <input type="hidden" name="cari_tipi" value="<?php echo $cariType == 'customers' ? 'customer' : 'supplier'; ?>">
            
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
                        $primaryFields = ['code', 'name', 'tax_number', 'tax_office', 'phone', 'email', 'address', 'city'];
                        
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
                                    'code' => 'Cari Kod (Zorunlu, benzersiz olmalı)',
                                    'name' => 'Cari Ad/Ünvan (Zorunlu)',
                                    'tax_number' => 'Vergi/TC Kimlik No',
                                    'tax_office' => 'Vergi Dairesi',
                                    'phone' => 'Telefon',
                                    'email' => 'E-posta',
                                    'address' => 'Adres',
                                    'city' => 'İl'
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
                                    'district' => 'İlçe',
                                    'postal_code' => 'Posta Kodu',
                                    'country' => 'Ülke',
                                    'contact_person' => 'İlgili Kişi',
                                    'notes' => 'Notlar',
                                    'status' => 'Durum (active/passive)',
                                    'credit_limit' => 'Kredi Limiti',
                                    'website' => 'Web Sitesi',
                                    'mobile_phone' => 'Cep Telefonu',
                                    'fax' => 'Faks',
                                    'discount_rate' => 'İskonto Oranı'
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
                <strong>Uyarı:</strong> Veri aktarımı öncesinde veritabanı yedeği almanız önerilir. Aktarım sırasında mevcut cari kodlar ile eşleşen kayıtlar güncellenecektir.
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