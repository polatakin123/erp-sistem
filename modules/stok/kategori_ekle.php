<?php
/**
 * ERP Sistem - Kategori Ekleme Sayfası
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
$pageTitle = "Kategori Ekle";

// İşlem sonucu
$islemSonucu = '';
$hata = '';

// Kategori tablosunu kontrol et ve yoksa oluştur
try {
    $db->exec("
        CREATE TABLE IF NOT EXISTS product_categories (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            parent_id INT(11) DEFAULT NULL,
            name VARCHAR(255) NOT NULL,
            description TEXT,
            image VARCHAR(255) DEFAULT NULL,
            status ENUM('active', 'passive') DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            created_by INT(11) DEFAULT NULL,
            updated_by INT(11) DEFAULT NULL,
            FOREIGN KEY (created_by) REFERENCES users(id),
            FOREIGN KEY (updated_by) REFERENCES users(id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
    ");
} catch (PDOException $e) {
    $hata = "Tablo oluşturma hatası: " . $e->getMessage();
}

// Örnek kategorileri ekle
if (isset($_POST['ornek_ekle'])) {
    try {
        $ornekKategoriler = [
            ['name' => 'Elektronik', 'description' => 'Elektronik ürünler', 'parent_id' => null],
            ['name' => 'Gıda', 'description' => 'Gıda ürünleri', 'parent_id' => null],
            ['name' => 'Kırtasiye', 'description' => 'Kırtasiye ürünleri', 'parent_id' => null],
            ['name' => 'Bilgisayar', 'description' => 'Bilgisayar ürünleri', 'parent_id' => 1],
            ['name' => 'Telefon', 'description' => 'Telefon ürünleri', 'parent_id' => 1],
            ['name' => 'Bakliyat', 'description' => 'Bakliyat ürünleri', 'parent_id' => 2],
            ['name' => 'İçecek', 'description' => 'İçecek ürünleri', 'parent_id' => 2]
        ];
        
        // Tabloda veri var mı kontrol et
        $stmt = $db->query("SELECT COUNT(*) FROM product_categories");
        $kategoriSayisi = $stmt->fetchColumn();
        
        if ($kategoriSayisi > 0) {
            $islemSonucu = "Tabloda zaten " . $kategoriSayisi . " kategori bulunuyor.";
        } else {
            $db->beginTransaction();
            
            $stmt = $db->prepare("
                INSERT INTO product_categories (
                    name, 
                    description, 
                    parent_id, 
                    status,
                    created_by,
                    updated_by
                ) VALUES (
                    :name, 
                    :description, 
                    :parent_id, 
                    'active',
                    :created_by,
                    :updated_by
                )
            ");
            
            $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
            
            foreach ($ornekKategoriler as $kategori) {
                $stmt->bindParam(':name', $kategori['name']);
                $stmt->bindParam(':description', $kategori['description']);
                $stmt->bindParam(':parent_id', $kategori['parent_id']);
                $stmt->bindParam(':created_by', $user_id);
                $stmt->bindParam(':updated_by', $user_id);
                $stmt->execute();
            }
            
            $db->commit();
            $islemSonucu = "Örnek kategoriler başarıyla eklendi.";
        }
    } catch (PDOException $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        $hata = "Kategori ekleme hatası: " . $e->getMessage();
    }
}

// Normal kategori ekleme
if (isset($_POST['submit'])) {
    try {
        $name = isset($_POST['name']) ? clean($_POST['name']) : '';
        $description = isset($_POST['description']) ? clean($_POST['description']) : '';
        $parent_id = isset($_POST['parent_id']) && !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $status = isset($_POST['status']) ? clean($_POST['status']) : 'active';
        $user_id = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null;
        
        if (empty($name)) {
            $hata = "Kategori adı boş olamaz!";
        } else {
            $stmt = $db->prepare("
                INSERT INTO product_categories (
                    name, 
                    description, 
                    parent_id, 
                    status,
                    created_by,
                    updated_by
                ) VALUES (
                    :name, 
                    :description, 
                    :parent_id, 
                    :status,
                    :created_by,
                    :updated_by
                )
            ");
            
            $stmt->bindParam(':name', $name);
            $stmt->bindParam(':description', $description);
            $stmt->bindParam(':parent_id', $parent_id);
            $stmt->bindParam(':status', $status);
            $stmt->bindParam(':created_by', $user_id);
            $stmt->bindParam(':updated_by', $user_id);
            
            $stmt->execute();
            
            $islemSonucu = "Kategori başarıyla eklendi.";
            
            // Formu temizle
            $_POST = array();
        }
    } catch (PDOException $e) {
        $hata = "Kategori ekleme hatası: " . $e->getMessage();
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Kategori Ekle</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <a href="kategoriler.php" class="btn btn-sm btn-outline-secondary">
            <i class="fas fa-list"></i> Kategorileri Listele
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

<div class="row">
    <div class="col-md-6">
        <!-- Kategori Ekle Formu -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Yeni Kategori Ekle</h6>
            </div>
            <div class="card-body">
                <form method="post">
                    <div class="mb-3">
                        <label for="name" class="form-label">Kategori Adı <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" value="<?php echo isset($_POST['name']) ? htmlspecialchars($_POST['name']) : ''; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="parent_id" class="form-label">Üst Kategori</label>
                        <select class="form-select" id="parent_id" name="parent_id">
                            <option value="">Ana Kategori</option>
                            <?php
                            try {
                                $stmt = $db->query("SELECT id, name FROM product_categories WHERE status = 'active' ORDER BY name");
                                while ($row = $stmt->fetch()) {
                                    $selected = (isset($_POST['parent_id']) && $_POST['parent_id'] == $row['id']) ? 'selected' : '';
                                    echo '<option value="' . $row['id'] . '" ' . $selected . '>' . htmlspecialchars($row['name']) . '</option>';
                                }
                            } catch (PDOException $e) {
                                echo '<option value="">Kategoriler yüklenemedi</option>';
                            }
                            ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label for="description" class="form-label">Açıklama</label>
                        <textarea class="form-control" id="description" name="description" rows="3"><?php echo isset($_POST['description']) ? htmlspecialchars($_POST['description']) : ''; ?></textarea>
                    </div>
                    
                    <div class="mb-3">
                        <label for="status" class="form-label">Durum</label>
                        <select class="form-select" id="status" name="status">
                            <option value="active" <?php echo (isset($_POST['status']) && $_POST['status'] == 'active') ? 'selected' : ''; ?>>Aktif</option>
                            <option value="passive" <?php echo (isset($_POST['status']) && $_POST['status'] == 'passive') ? 'selected' : ''; ?>>Pasif</option>
                        </select>
                    </div>
                    
                    <button type="submit" name="submit" class="btn btn-primary">
                        <i class="fas fa-save"></i> Kaydet
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <div class="col-md-6">
        <!-- Örnek Kategoriler -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Örnek Kategoriler</h6>
            </div>
            <div class="card-body">
                <p>Aşağıdaki butona tıklayarak örnek kategoriler ekleyebilirsiniz.</p>
                <form method="post">
                    <button type="submit" name="ornek_ekle" class="btn btn-success">
                        <i class="fas fa-plus-circle"></i> Örnek Kategorileri Ekle
                    </button>
                </form>
                
                <hr>
                
                <h6>Mevcut Kategoriler</h6>
                <?php
                try {
                    $stmt = $db->query("SELECT c.*, p.name as parent_name FROM product_categories c LEFT JOIN product_categories p ON c.parent_id = p.id ORDER BY c.name");
                    $kategoriler = $stmt->fetchAll();
                    
                    if (count($kategoriler) > 0) {
                        echo '<table class="table table-bordered table-striped">';
                        echo '<thead><tr><th>ID</th><th>Kategori Adı</th><th>Üst Kategori</th><th>Durum</th></tr></thead>';
                        echo '<tbody>';
                        
                        foreach ($kategoriler as $kat) {
                            echo '<tr>';
                            echo '<td>' . $kat['id'] . '</td>';
                            echo '<td>' . htmlspecialchars($kat['name']) . '</td>';
                            echo '<td>' . htmlspecialchars($kat['parent_name'] ?? '-') . '</td>';
                            echo '<td>' . ($kat['status'] == 'active' ? '<span class="badge bg-success">Aktif</span>' : '<span class="badge bg-danger">Pasif</span>') . '</td>';
                            echo '</tr>';
                        }
                        
                        echo '</tbody></table>';
                    } else {
                        echo '<div class="alert alert-info">Henüz kategori bulunmuyor.</div>';
                    }
                } catch (PDOException $e) {
                    echo '<div class="alert alert-danger">Kategoriler listelenirken hata oluştu: ' . $e->getMessage() . '</div>';
                }
                ?>
            </div>
        </div>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 