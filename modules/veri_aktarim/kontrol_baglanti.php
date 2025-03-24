<?php
/**
 * ERP Sistem - Veritabanı Bağlantı Kontrolü
 * 
 * Bu dosya dış veritabanı bağlantı testini gerçekleştirir.
 */

// Hata ayıklama kodları
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(0);

// Uzun süren işlemler için zaman aşımı limitlerini artır
ini_set('max_execution_time', 300); // 5 dakika
ini_set('memory_limit', '256M');    // 256 MB bellek limiti

// Oturum başlat
session_start();

// Önceki tüm mesajları temizle
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
unset($_SESSION['warning_message']);

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Hata ayıklama bilgisi
echo "<div style='background: #f8f9fa; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; font-family: monospace;'>";
echo "<h4>Hata Ayıklama Bilgileri - Bağlantı Kontrolü:</h4>";
echo "Oturum durumu: " . (session_status() == PHP_SESSION_ACTIVE ? "Aktif" : "Aktif değil") . "<br>";
echo "user_id kontrolü: " . (isset($_SESSION['user_id']) ? "Var (ID: {$_SESSION['user_id']})" : "Yok") . "<br>";

echo "<h4>POST Verileri:</h4>";
echo "<pre>";
print_r($_POST);
echo "</pre>";
echo "</div>";

// POST verileri kontrolü
if ($_SERVER['REQUEST_METHOD'] != 'POST') {
    header('Location: index.php');
    exit;
}

// Form verilerini al
$db_host = isset($_POST['db_host']) ? cleanServerName($_POST['db_host']) : '';
$db_name = isset($_POST['db_name']) ? clean($_POST['db_name']) : '';
$db_user = isset($_POST['db_user']) ? clean($_POST['db_user']) : '';
$db_pass = isset($_POST['db_pass']) ? $_POST['db_pass'] : '';
$db_charset = isset($_POST['db_charset']) ? clean($_POST['db_charset']) : 'utf8mb4';
$remember_connection = isset($_POST['remember_connection']) && $_POST['remember_connection'] == '1';

// Form verilerini session'a kaydet (hata durumunda geri doldurmak için)
$_SESSION['form_data'] = [
    'db_host' => $db_host,
    'db_name' => $db_name,
    'db_user' => $db_user,
    'db_charset' => $db_charset
];

// Cookie'lere kaydet (eğer kullanıcı istiyorsa)
if ($remember_connection) {
    // 30 gün boyunca hatırla
    $expire = time() + (86400 * 30);
    setcookie('db_host', $db_host, $expire, '/');
    setcookie('db_name', $db_name, $expire, '/');
    setcookie('db_user', $db_user, $expire, '/');
    // Şifreyi güvenlik nedeniyle kaydetmiyoruz
}

// Verileri doğrula
if (empty($db_host) || empty($db_name) || empty($db_user)) {
    $_SESSION['error_message'] = "Lütfen tüm gerekli alanları doldurun.";
    header('Location: index.php');
    exit;
}

// Veritabanı bağlantısını test et
try {
    // SQL Server mı MySQL mi kontrolü
    $is_mssql = strpos($db_host, '\\') !== false;
    
    if ($is_mssql) {
        // MSSQL bağlantısı (PDO kullanarak)
        if (!extension_loaded('sqlsrv')) {
            throw new Exception("MSSQL bağlantısı için 'sqlsrv' PHP eklentisi bulunamadı.");
        }
        
        // SQL Server için PDO DSN - charset parametresi kullanmıyoruz
        $dsn = "sqlsrv:Server=$db_host;Database=$db_name";
        
        // PDO bağlantısı oluştur
        try {
            $sourceDb = new PDO($dsn, $db_user, $db_pass);
            $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
            
            // Veritabanındaki tabloları al
            $stmt = $sourceDb->query("SELECT name FROM sys.tables");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (count($tables) === 0) {
                // Tablo bulunamadıysa, başka bir sorgu deneyin
                $stmt = $sourceDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE'");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            }
        } catch (PDOException $e) {
            throw new Exception("MSSQL PDO hatası: " . $e->getMessage());
        }
    } else {
        // MySQL bağlantısı
        
        // MySQL için desteklenen karakter setlerini kontrol et
        $valid_charsets = ['utf8', 'utf8mb4', 'latin1', 'latin2', 'ascii'];
        if (!in_array(strtolower($db_charset), $valid_charsets)) {
            $db_charset = 'utf8mb4'; // Varsayılan karakter seti kullan
            $_SESSION['warning_message'] = "Belirtilen karakter seti desteklenmiyor. Varsayılan 'utf8mb4' kullanılıyor.";
        }
        
        try {
            $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ];
            
            $sourceDb = new PDO($dsn, $db_user, $db_pass, $options);
            
            // Veritabanındaki tabloları al
            $stmt = $sourceDb->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
        } catch (PDOException $pdoEx) {
            // Karakter seti sorunu varsa, utf8 ile tekrar dene
            if (strpos($pdoEx->getMessage(), 'character set') !== false && $db_charset != 'utf8') {
                $db_charset = 'utf8';
                $dsn = "mysql:host=$db_host;dbname=$db_name;charset=$db_charset";
                $sourceDb = new PDO($dsn, $db_user, $db_pass, $options);
                
                // Veritabanındaki tabloları al
                $stmt = $sourceDb->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                $_SESSION['warning_message'] = "Belirtilen karakter seti desteklenmediği için 'utf8' kullanıldı.";
            } else {
                // Başka bir PDO hatası
                throw $pdoEx;
            }
        }
    }
    
    // Bağlantı başarılı, veritabanı bilgilerini session'a kaydet
    $_SESSION['source_db'] = [
        'host' => $db_host,
        'name' => $db_name,
        'user' => $db_user,
        'pass' => $db_pass,
        'charset' => $is_mssql ? '' : $db_charset, // MSSQL için charset boş bırakılıyor
        'is_mssql' => $is_mssql
    ];
    
    // Hata ayıklama bilgisi
    echo "<div style='background: #d4edda; border: 1px solid #c3e6cb; padding: 10px; margin-bottom: 10px; font-family: monospace;'>";
    echo "<h4>Bağlantı Başarılı!</h4>";
    echo "Oturum veritabanı bilgileri ayarlandı:<br>";
    echo "<pre>";
    print_r($_SESSION['source_db']);
    echo "</pre>";
    echo "</div>";
    
    $_SESSION['success_message'] = "Veritabanı bağlantısı başarılı. " . count($tables) . " tablo bulundu.";
    
    // Form verilerini temizle
    unset($_SESSION['form_data']);
    
    // Sayfa başlığı ve HTML
    $pageTitle = "Veritabanı Bağlantısı Başarılı";
    include '../../includes/header.php';
    ?>

    <div class="container-fluid">
        <div class="row">
            <?php include '../../includes/sidebar.php'; ?>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2"><?php echo $pageTitle; ?></h1>
                    <div class="btn-toolbar mb-2 mb-md-0">
                        <div class="btn-group me-2">
                            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                                <i class="fas fa-arrow-left"></i> Geri
                            </a>
                        </div>
                    </div>
                </div>

                <div class="alert alert-success">
                    <i class="fas fa-check-circle"></i> Veritabanı bağlantısı başarılı. <?php echo count($tables); ?> tablo bulundu.
                </div>

                <div class="card mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Veri Aktarım İşlemi Seçin</h6>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> Lütfen yapmak istediğiniz veri aktarım işlemini seçin.
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Tüm Tabloları Olduğu Gibi Kopyala</h5>
                                        <p class="card-text">Kaynak veritabanındaki tüm tabloları (kayıt içeren) otomatik olarak hedef veritabanına kopyalar.</p>
                                        <a href="tablo_kopyala.php" class="btn btn-danger" target="_blank">
                                            <i class="fas fa-clone"></i> Tüm Tabloları Kopyala
                                        </a>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Özel Veri Aktarımı</h5>
                                        <p class="card-text">İstediğiniz tabloları seçerek ve alan eşleştirmesi yaparak özelleştirilmiş veri aktarımı gerçekleştirin.</p>
                                        <a href="tablo_secimi.php" class="btn btn-primary" target="_blank">
                                            <i class="fas fa-table"></i> Tabloları Seç
                                        </a>
                                    </div>
                                </div>
                            </div>

                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <h5 class="card-title">Dolu Tabloları Listele</h5>
                                        <p class="card-text">Kaynak veritabanındaki kayıt içeren tabloları ve kayıt sayılarını listeler.</p>
                                        <a href="dolu_tablolar.php" class="btn btn-info" target="_blank">
                                            <i class="fas fa-list"></i> Dolu Tabloları Görüntüle
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <?php include '../../includes/footer.php';
    exit;
}

catch (Exception $e) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası: " . $e->getMessage();
    header('Location: index.php');
    exit;
}
?> 