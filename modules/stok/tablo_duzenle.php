<?php
/**
 * ERP Sistem - Tablo Düzenleme
 * 
 * Bu dosya veritabanı tablolarını düzenlemeye yarar.
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

// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sayfa başlığı
$pageTitle = "Tablo Düzenleme";

// Sonuç mesajları
$islemSonucu = '';
$hata = '';

// İşlem yapıldıysa
if (isset($_POST['islem'])) {
    try {
        // Model sütunu ekle
        if ($_POST['islem'] == 'model_ekle') {
            // Önce sütunun var olup olmadığını kontrol et
            $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'model'");
            $stmt->execute();
            $column_exists = $stmt->fetchColumn();
            
            if (!$column_exists) {
                // Sütun yoksa ekle
                $db->exec("ALTER TABLE products ADD COLUMN model VARCHAR(100) DEFAULT NULL AFTER brand");
                $islemSonucu = "Model sütunu başarıyla eklendi!";
            } else {
                $islemSonucu = "Model sütunu zaten mevcut.";
            }
        }
        
        // Description sütunu ekle
        if ($_POST['islem'] == 'description_ekle') {
            // Önce sütunun var olup olmadığını kontrol et
            $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'description'");
            $stmt->execute();
            $column_exists = $stmt->fetchColumn();
            
            if (!$column_exists) {
                // Sütun yoksa ekle
                $db->exec("ALTER TABLE products ADD COLUMN description TEXT AFTER model");
                $islemSonucu = "Description sütunu başarıyla eklendi!";
            } else {
                $islemSonucu = "Description sütunu zaten mevcut.";
            }
        }
        
        // Tax Rate sütunu ekle
        if ($_POST['islem'] == 'tax_rate_ekle') {
            // Önce sütunun var olup olmadığını kontrol et
            $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'tax_rate'");
            $stmt->execute();
            $column_exists = $stmt->fetchColumn();
            
            if (!$column_exists) {
                // Sütun yoksa ekle
                $db->exec("ALTER TABLE products ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER sale_price");
                $islemSonucu = "Tax Rate sütunu başarıyla eklendi!";
            } else {
                $islemSonucu = "Tax Rate sütunu zaten mevcut.";
            }
        }
        
        // Reference No sütunu ekle
        if ($_POST['islem'] == 'reference_no_ekle') {
            // Önce sütunun var olup olmadığını kontrol et
            $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'reference_no'");
            $stmt->execute();
            $column_exists = $stmt->fetchColumn();
            
            if (!$column_exists) {
                // Sütun yoksa ekle
                $db->exec("ALTER TABLE stock_movements ADD COLUMN reference_no VARCHAR(50) DEFAULT NULL AFTER reference_type");
                $islemSonucu = "Reference No sütunu başarıyla eklendi!";
            } else {
                $islemSonucu = "Reference No sütunu zaten mevcut.";
            }
        }
        
        // User ID sütunu ekle
        if ($_POST['islem'] == 'user_id_ekle') {
            // Önce sütunun var olup olmadığını kontrol et
            $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'user_id'");
            $stmt->execute();
            $column_exists = $stmt->fetchColumn();
            
            if (!$column_exists) {
                // Sütun yoksa ekle
                $db->exec("ALTER TABLE stock_movements ADD COLUMN user_id INT(11) DEFAULT NULL AFTER description");
                $islemSonucu = "User ID sütunu başarıyla eklendi!";
            } else {
                $islemSonucu = "User ID sütunu zaten mevcut.";
            }
        }
        
        // Created By sütunu ekle
        if ($_POST['islem'] == 'created_by_ekle') {
            // Önce sütunun var olup olmadığını kontrol et
            $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'created_by'");
            $stmt->execute();
            $column_exists = $stmt->fetchColumn();
            
            if (!$column_exists) {
                // Foreign key kontrolünü geçici olarak kapat
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                // Sütun yoksa ekle
                $db->exec("ALTER TABLE stock_movements ADD COLUMN created_by INT(11) DEFAULT NULL AFTER user_id");
                
                // Eğer user_id sütunu varsa, değerleri created_by'a kopyala
                $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'user_id'");
                $stmt->execute();
                if ($stmt->fetchColumn()) {
                    $db->exec("UPDATE stock_movements SET created_by = user_id WHERE user_id IS NOT NULL");
                }
                
                // Foreign key kontrolünü tekrar aç
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                $islemSonucu = "Created By sütunu başarıyla eklendi!";
            } else {
                $islemSonucu = "Created By sütunu zaten mevcut.";
            }
        }
        
        // Stock Movements tablosunu güncelle
        if ($_POST['islem'] == 'stock_movements_guncelle') {
            // Foreign key kontrolünü geçici olarak kapat
            $db->exec("SET FOREIGN_KEY_CHECKS = 0");
            
            // Mevcut kısıtlamaları listele ve kaldır
            $stmt = $db->query("
                SELECT CONSTRAINT_NAME
                FROM information_schema.TABLE_CONSTRAINTS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = 'stock_movements'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
            ");
            
            $constraints = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            foreach ($constraints as $constraint) {
                $db->exec("ALTER TABLE stock_movements DROP FOREIGN KEY {$constraint}");
            }
            
            // Tabloyu güncelle
            $db->exec("ALTER TABLE stock_movements 
                MODIFY created_by INT(11) DEFAULT NULL,
                ADD COLUMN IF NOT EXISTS user_id INT(11) DEFAULT NULL AFTER description,
                ADD COLUMN IF NOT EXISTS created_by INT(11) DEFAULT NULL AFTER user_id
            ");
            
            // Eğer user_id sütunu varsa, değerleri created_by'a kopyala
            $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'user_id'");
            $stmt->execute();
            if ($stmt->fetchColumn()) {
                $db->exec("UPDATE stock_movements SET created_by = user_id WHERE user_id IS NOT NULL");
            }
            
            // Yabancı anahtar kontrollerini tekrar aç
            $db->exec("SET FOREIGN_KEY_CHECKS = 1");
            
            $islemSonucu = "Stock Movements tablosu başarıyla güncellendi.";
        }
        
        // Birden çok sütunu toplu ekle
        if ($_POST['islem'] == 'tum_sutunlari_ekle') {
            $eklemeSonuclari = [];
            
            // Model sütunu
            $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'model'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                $db->exec("ALTER TABLE products ADD COLUMN model VARCHAR(100) DEFAULT NULL AFTER brand");
                $eklemeSonuclari[] = "Model sütunu eklendi.";
            }
            
            // Description sütunu
            $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'description'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                $db->exec("ALTER TABLE products ADD COLUMN description TEXT AFTER model");
                $eklemeSonuclari[] = "Description sütunu eklendi.";
            }
            
            // Tax Rate sütunu
            $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'tax_rate'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                $db->exec("ALTER TABLE products ADD COLUMN tax_rate DECIMAL(5,2) NOT NULL DEFAULT 0.00 AFTER sale_price");
                $eklemeSonuclari[] = "Tax Rate sütunu eklendi.";
            }
            
            // Reference No sütunu
            $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'reference_no'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                $db->exec("ALTER TABLE stock_movements ADD COLUMN reference_no VARCHAR(50) DEFAULT NULL AFTER reference_type");
                $eklemeSonuclari[] = "Reference No sütunu eklendi.";
            }
            
            // User ID sütunu
            $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'user_id'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                $db->exec("ALTER TABLE stock_movements ADD COLUMN user_id INT(11) DEFAULT NULL AFTER description");
                $eklemeSonuclari[] = "User ID sütunu eklendi.";
            }
            
            // Created By sütunu
            $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'created_by'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                // Foreign key kontrolünü geçici olarak kapat
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                $db->exec("ALTER TABLE stock_movements ADD COLUMN created_by INT(11) DEFAULT NULL AFTER user_id");
                
                // Eğer user_id sütunu varsa, değerleri created_by'a kopyala
                $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'user_id'");
                $stmt->execute();
                if ($stmt->fetchColumn()) {
                    $db->exec("UPDATE stock_movements SET created_by = user_id WHERE user_id IS NOT NULL");
                }
                
                // Foreign key kontrolünü tekrar aç
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                $eklemeSonuclari[] = "Created By sütunu eklendi.";
            }
            
            // Parent ID sütunu
            $stmt = $db->prepare("SHOW COLUMNS FROM product_categories LIKE 'parent_id'");
            $stmt->execute();
            if (!$stmt->fetchColumn()) {
                $db->exec("ALTER TABLE product_categories ADD COLUMN parent_id INT DEFAULT NULL AFTER id");
                $eklemeSonuclari[] = "Parent ID sütunu eklendi.";
            }
            
            if (count($eklemeSonuclari) > 0) {
                $islemSonucu = "Aşağıdaki sütunlar başarıyla eklendi:<br>" . implode("<br>", $eklemeSonuclari);
            } else {
                $islemSonucu = "Tüm sütunlar zaten mevcut.";
            }
        }
        
        // Parent ID sütunu ekle
        if ($_POST['islem'] == 'parent_id_ekle') {
            // Önce sütunun var olup olmadığını kontrol et
            $stmt = $db->prepare("SHOW COLUMNS FROM product_categories LIKE 'parent_id'");
            $stmt->execute();
            $column_exists = $stmt->fetchColumn();
            
            if (!$column_exists) {
                // Sütun yoksa ekle
                $db->exec("ALTER TABLE product_categories ADD COLUMN parent_id INT DEFAULT NULL AFTER id");
                $islemSonucu = "Parent ID sütunu başarıyla eklendi!";
            } else {
                $islemSonucu = "Parent ID sütunu zaten mevcut.";
            }
        }
    } catch (PDOException $e) {
        $hata = "Veritabanı hatası: " . $e->getMessage();
    } catch (Exception $e) {
        $hata = "Genel hata: " . $e->getMessage();
    }
}

// Tablonun durumunu kontrol et
$modelSutunuVar = false;
$descriptionSutunuVar = false;
$taxRateSutunuVar = false;
$referenceNoSutunuVar = false;
$userIdSutunuVar = false;
$createdBySutunuVar = false;
$parentIDSutunuVar = false;
try {
    $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'model'");
    $stmt->execute();
    $modelSutunuVar = (bool) $stmt->fetchColumn();
    
    $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'description'");
    $stmt->execute();
    $descriptionSutunuVar = (bool) $stmt->fetchColumn();
    
    $stmt = $db->prepare("SHOW COLUMNS FROM products LIKE 'tax_rate'");
    $stmt->execute();
    $taxRateSutunuVar = (bool) $stmt->fetchColumn();
    
    $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'reference_no'");
    $stmt->execute();
    $referenceNoSutunuVar = (bool) $stmt->fetchColumn();
    
    $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'user_id'");
    $stmt->execute();
    $userIdSutunuVar = (bool) $stmt->fetchColumn();
    
    $stmt = $db->prepare("SHOW COLUMNS FROM stock_movements LIKE 'created_by'");
    $stmt->execute();
    $createdBySutunuVar = (bool) $stmt->fetchColumn();
    
    $stmt = $db->prepare("SHOW COLUMNS FROM product_categories LIKE 'parent_id'");
    $stmt->execute();
    $parentIDSutunuVar = (bool) $stmt->fetchColumn();
} catch (PDOException $e) {
    $hata = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Tablo Düzenleme</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="index.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-arrow-left"></i> Geri Dön
        </a>
    </div>
</div>

<?php if (!empty($islemSonucu)): ?>
<div class="alert alert-success alert-dismissible fade show" role="alert">
    <strong>Başarılı!</strong> <?php echo $islemSonucu; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<?php if (!empty($hata)): ?>
<div class="alert alert-danger alert-dismissible fade show" role="alert">
    <strong>Hata!</strong> <?php echo $hata; ?>
    <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
</div>
<?php endif; ?>

<!-- Ana İçerik -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Tablo Yapısı Düzenleme</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-info">
            <p>Bu sayfadan veritabanı tablolarınızın yapısını düzenleyebilirsiniz.</p>
        </div>
        
        <h5 class="mb-3">Products Tablosu</h5>
        
        <?php if ($modelSutunuVar): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Model sütunu tabloda bulunuyor.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Model sütunu tabloda bulunamadı!
        </div>
        
        <form action="" method="post">
            <input type="hidden" name="islem" value="model_ekle">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Model Sütununu Ekle
            </button>
        </form>
        <?php endif; ?>
        
        <?php if ($descriptionSutunuVar): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Description sütunu tabloda bulunuyor.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Description sütunu tabloda bulunamadı!
        </div>
        
        <form action="" method="post" class="mt-2">
            <input type="hidden" name="islem" value="description_ekle">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Description Sütununu Ekle
            </button>
        </form>
        <?php endif; ?>
        
        <?php if ($taxRateSutunuVar): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Tax Rate sütunu tabloda bulunuyor.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Tax Rate sütunu tabloda bulunamadı!
        </div>
        
        <form action="" method="post" class="mt-2">
            <input type="hidden" name="islem" value="tax_rate_ekle">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Tax Rate Sütununu Ekle
            </button>
        </form>
        <?php endif; ?>
        
        <hr>
        
        <h5 class="mb-3">Stock Movements Tablosu</h5>
        
        <?php if ($referenceNoSutunuVar): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Reference No sütunu tabloda bulunuyor.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Reference No sütunu tabloda bulunamadı!
        </div>
        
        <form action="" method="post" class="mt-2">
            <input type="hidden" name="islem" value="reference_no_ekle">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Reference No Sütununu Ekle
            </button>
        </form>
        <?php endif; ?>
        
        <?php if ($userIdSutunuVar): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> User ID sütunu tabloda bulunuyor.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> User ID sütunu tabloda bulunamadı!
        </div>
        
        <form action="" method="post" class="mt-2">
            <input type="hidden" name="islem" value="user_id_ekle">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> User ID Sütununu Ekle
            </button>
        </form>
        <?php endif; ?>
        
        <?php if ($createdBySutunuVar): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Created By sütunu tabloda bulunuyor.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Created By sütunu tabloda bulunamadı!
        </div>
        
        <form action="" method="post" class="mt-2">
            <input type="hidden" name="islem" value="created_by_ekle">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Created By Sütununu Ekle
            </button>
        </form>
        <?php endif; ?>
        
        <form action="" method="post" class="mt-2">
            <input type="hidden" name="islem" value="stock_movements_guncelle">
            <button type="submit" class="btn btn-warning">
                <i class="fas fa-sync-alt"></i> Stock Movements Tablosunu Güncelle/Düzelt
            </button>
        </form>
        
        <hr>
        
        <div class="mb-4">
            <h5>Hızlı İşlem</h5>
            <p>Tüm eksik sütunları tek seferde eklemek için aşağıdaki butonu kullanabilirsiniz:</p>
            
            <form action="" method="post">
                <input type="hidden" name="islem" value="tum_sutunlari_ekle">
                <button type="submit" class="btn btn-success">
                    <i class="fas fa-magic"></i> Tüm Eksik Sütunları Otomatik Ekle
                </button>
            </form>
        </div>
        
        <hr>
        
        <h5 class="mb-3">Product Categories Tablosu</h5>
        
        <?php if ($parentIDSutunuVar): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> Parent ID sütunu tabloda bulunuyor.
        </div>
        <?php else: ?>
        <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle"></i> Parent ID sütunu tabloda bulunamadı!
        </div>
        
        <form action="" method="post">
            <input type="hidden" name="islem" value="parent_id_ekle">
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-plus-circle"></i> Parent ID Sütununu Ekle
            </button>
        </form>
        <?php endif; ?>
        
        <hr>
        
        <h5 class="mb-3">Products Tablo Yapısı</h5>
        
        <?php
        try {
            // Tablo yapısını getir
            $stmt = $db->query("SHOW COLUMNS FROM products");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($columns) > 0) {
                echo '<table class="table table-bordered table-striped">';
                echo '<thead><tr><th>Alan Adı</th><th>Tür</th><th>Null</th><th>Anahtar</th><th>Varsayılan</th><th>Ekstra</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($columns as $column) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($column['Extra']) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-warning">Tablo yapısı bulunamadı veya tablo boş.</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Tablo yapısı sorgulanırken hata oluştu: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <h5 class="mb-3 mt-4">Stock Movements Tablo Yapısı</h5>
        
        <?php
        try {
            // Tablo yapısını getir
            $stmt = $db->query("SHOW COLUMNS FROM stock_movements");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($columns) > 0) {
                echo '<table class="table table-bordered table-striped">';
                echo '<thead><tr><th>Alan Adı</th><th>Tür</th><th>Null</th><th>Anahtar</th><th>Varsayılan</th><th>Ekstra</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($columns as $column) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($column['Extra']) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-warning">Tablo yapısı bulunamadı veya tablo boş.</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Tablo yapısı sorgulanırken hata oluştu: ' . $e->getMessage() . '</div>';
        }
        ?>
        
        <h5 class="mb-3 mt-4">Product Categories Tablo Yapısı</h5>
        
        <?php
        try {
            // Tablo yapısını getir
            $stmt = $db->query("SHOW COLUMNS FROM product_categories");
            $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            if (count($columns) > 0) {
                echo '<table class="table table-bordered table-striped">';
                echo '<thead><tr><th>Alan Adı</th><th>Tür</th><th>Null</th><th>Anahtar</th><th>Varsayılan</th><th>Ekstra</th></tr></thead>';
                echo '<tbody>';
                
                foreach ($columns as $column) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars($column['Field']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Type']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Null']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Key']) . '</td>';
                    echo '<td>' . htmlspecialchars($column['Default'] ?? 'NULL') . '</td>';
                    echo '<td>' . htmlspecialchars($column['Extra']) . '</td>';
                    echo '</tr>';
                }
                
                echo '</tbody></table>';
            } else {
                echo '<div class="alert alert-warning">Tablo yapısı bulunamadı veya tablo boş.</div>';
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Tablo yapısı sorgulanırken hata oluştu: ' . $e->getMessage() . '</div>';
        }
        ?>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 