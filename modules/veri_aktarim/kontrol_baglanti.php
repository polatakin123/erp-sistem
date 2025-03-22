<?php
/**
 * ERP Sistem - Veritabanı Bağlantı Kontrolü
 * 
 * Bu dosya dış veritabanı bağlantı testini gerçekleştirir.
 */

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
    
    $_SESSION['success_message'] = "Veritabanı bağlantısı başarılı. " . count($tables) . " tablo bulundu.";
    
    // Form verilerini temizle
    unset($_SESSION['form_data']);
    
    header('Location: tablo_secimi.php');
    exit;
    
} catch (Exception $e) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası: " . $e->getMessage();
    header('Location: index.php');
    exit;
}
?> 