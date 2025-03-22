<?php
/**
 * ERP Sistem - Veri Aktarım Sayfası
 * 
 * Bu dosya dış kaynaklardan veri aktarım işlemlerini gerçekleştirir.
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
$pageTitle = "Veri Aktarımı";

// Hata ve başarı mesajları
$errors = [];
$success = [];
$warnings = [];

// Hata veya başarı mesajları kontrolü
if (isset($_SESSION['error_message'])) {
    $errors[] = $_SESSION['error_message'];
    unset($_SESSION['error_message']);
}

if (isset($_SESSION['success_message'])) {
    $success[] = $_SESSION['success_message'];
    unset($_SESSION['success_message']);
}

if (isset($_SESSION['warning_message'])) {
    $warnings[] = $_SESSION['warning_message'];
    unset($_SESSION['warning_message']);
}

// Varsayılan veritabanı türü
$is_mssql = false;

// MSSQL için karakter seti ayarlamak
// MSSQL için desteklenen karakter setleri
$valid_mssql_charsets = array('UTF-8', 'Cyrillic_General_CI_AS', 'Turkish_CI_AS', 'Latin1_General_CI_AS');

// MSSQL için otomatik charset belirleme (sunucu adından kontrol et)
if (isset($_POST['db_host']) && strpos($_POST['db_host'], '\\') !== false) {
    $is_mssql = true;
    if (!empty($_POST['db_charset'])) {
        if ($_POST['db_charset'] == 'utf8mb4') {
            $db_charset = 'UTF-8';
        } elseif ($_POST['db_charset'] == 'latin5') {
            $db_charset = 'Turkish_CI_AS';
        }
    }
}

// Form değerlerini al (önce cookie kontrolü yap)
$form_data = isset($_SESSION['form_data']) ? $_SESSION['form_data'] : [
    'db_host' => isset($_COOKIE['db_host']) ? $_COOKIE['db_host'] : 'localhost',
    'db_name' => isset($_COOKIE['db_name']) ? $_COOKIE['db_name'] : '',
    'db_user' => isset($_COOKIE['db_user']) ? $_COOKIE['db_user'] : '',
    'db_charset' => 'utf8mb4'
];

// Mevcut tabloları al
try {
    $tables = [];
    $stmt = $db->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
} catch (PDOException $e) {
    $errors[] = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Veri Aktarımı</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="../index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Ana Sayfa'ya Dön
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

if (!empty($warnings)) {
    echo '<div class="alert alert-warning">';
    foreach ($warnings as $message) {
        echo '<p><i class="fas fa-exclamation-triangle"></i> ' . $message . '</p>';
    }
    echo '</div>';
}
?>

<div class="row">
    <!-- Veritabanı Bağlantı Formu -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Kaynak Veritabanı Bağlantısı</h6>
            </div>
            <div class="card-body">
                <form action="kontrol_baglanti.php" method="post">
                    <div class="mb-3">
                        <label for="db_host" class="form-label">Veritabanı Sunucu</label>
                        <input type="text" class="form-control" id="db_host" name="db_host" value="<?php echo htmlspecialchars($form_data['db_host']); ?>" required>
                        <div class="form-text text-muted">
                            MySQL için: localhost veya IP adresi<br>
                            MSSQL için: SERVER\INSTANCE formatında yazınız
                        </div>
                    </div>
                    <div class="mb-3">
                        <label for="db_name" class="form-label">Veritabanı Adı</label>
                        <input type="text" class="form-control" id="db_name" name="db_name" value="<?php echo htmlspecialchars($form_data['db_name']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_user" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="db_user" name="db_user" value="<?php echo htmlspecialchars($form_data['db_user']); ?>" required>
                    </div>
                    <div class="mb-3">
                        <label for="db_pass" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="db_pass" name="db_pass">
                    </div>
                    <div class="mb-3">
                        <label for="db_charset" class="form-label">Karakter Seti</label>
                        <input type="text" class="form-control" id="db_charset" name="db_charset" value="<?php echo htmlspecialchars($form_data['db_charset']); ?>">
                        <div class="form-text text-muted">
                            MySQL için: utf8mb4 (varsayılan)<br>
                            MSSQL için: UTF-8
                        </div>
                    </div>
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" id="remember_connection" name="remember_connection" value="1">
                        <label class="form-check-label" for="remember_connection">Bu bağlantı bilgilerini hatırla</label>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-database"></i> Bağlantıyı Test Et
                    </button>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Veri Aktarım Seçenekleri -->
    <div class="col-md-6">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Veri Aktarım Seçenekleri</h6>
            </div>
            <div class="card-body">
                <p>Dış veritabanından aşağıdaki tablolara veri aktarabilirsiniz:</p>
                <div class="list-group">
                    <a href="stok_aktarim.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-boxes me-2"></i> Stok Verileri Aktarımı
                        <span class="badge bg-info float-end">Products</span>
                    </a>
                    <a href="cari_aktarim.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-users me-2"></i> Cari Hesaplar Aktarımı
                        <span class="badge bg-info float-end">Customers & Suppliers</span>
                    </a>
                    <a href="stok_hareketi_aktarim.php" class="list-group-item list-group-item-action">
                        <i class="fas fa-exchange-alt me-2"></i> Stok Hareketleri Aktarımı
                        <span class="badge bg-info float-end">Stock Movements</span>
                    </a>
                </div>
                <div class="alert alert-warning mt-3">
                    <i class="fas fa-exclamation-triangle"></i> Dikkat: Veri aktarımı yapmadan önce veritabanı yedeği almanız önerilir.
                </div>
            </div>
        </div>
        
        <!-- Hızlı Yardım -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Yardım</h6>
            </div>
            <div class="card-body">
                <p>Veri aktarımı işlemi için:</p>
                <ol>
                    <li>Önce kaynak veritabanı bağlantı bilgilerini girin.</li>
                    <li>Bağlantıyı test edin.</li>
                    <li>Aktarmak istediğiniz veri türünü seçin.</li>
                    <li>Alan eşleştirmesi yapın.</li>
                    <li>Aktarımı başlatın.</li>
                </ol>
            </div>
        </div>
    </div>
</div>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?>

<?php
// Form bilgilerini hafızaya almak için
if (isset($_POST['remember_connection']) && $_POST['remember_connection'] == '1') {
    // Bağlantı bilgilerini cookie olarak saklayalım (30 gün)
    $db_host = $_POST['db_host'];
    $db_name = $_POST['db_name'];
    $db_user = $_POST['db_user'];
    setcookie('db_host', $db_host, time() + (86400 * 30), "/");
    setcookie('db_name', $db_name, time() + (86400 * 30), "/");
    setcookie('db_user', $db_user, time() + (86400 * 30), "/");
    // Güvenlik nedeniyle şifreyi saklamıyoruz
}
?> 