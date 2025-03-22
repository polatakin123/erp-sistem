<?php
/**
 * ERP Sistem - Veritabanı Yükleme Aracı
 * 
 * Bu dosya veritabanı şemasını ve örnek verileri yükler.
 */

// Hata mesajları
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Başarı ve hata mesajları
$messages = [];
$errors = [];

// Form verilerini al
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $host = $_POST['host'] ?? 'localhost';
    $username = $_POST['username'] ?? 'root';
    $password = $_POST['password'] ?? '';
    $dbName = $_POST['dbname'] ?? 'erp_sistem';

    try {
        // Veritabanı bağlantısı - veritabanı seçmeden
        $pdo = new PDO("mysql:host=$host", $username, $password);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // SQL dosyasını oku
        $sqlFile = file_get_contents(__DIR__ . '/db.sql');
        
        // SQL ifadelerini böl
        $sqlStatements = explode(';', $sqlFile);
        
        // Her bir SQL ifadesini çalıştır
        foreach ($sqlStatements as $statement) {
            $statement = trim($statement);
            if (!empty($statement)) {
                $pdo->exec($statement);
            }
        }
        
        $messages[] = "Veritabanı başarıyla oluşturuldu ve örnek veriler yüklendi.";
        
        // db.php dosyasını oluştur veya güncelle
        $dbConfig = "<?php
/**
 * ERP Sistem - Veritabanı Bağlantı Ayarları
 */

// Veritabanı bağlantı ayarları
define('DB_HOST', '$host');
define('DB_NAME', '$dbName');
define('DB_USER', '$username');
define('DB_PASS', '$password');

// PDO bağlantısı oluştur
try {
    \$db = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS);
    \$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    \$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    \$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
} catch (PDOException \$e) {
    die('Veritabanı bağlantı hatası: ' . \$e->getMessage());
}
";
        
        file_put_contents(__DIR__ . '/db.php', $dbConfig);
        $messages[] = "Veritabanı bağlantı dosyası başarıyla oluşturuldu.";
        
    } catch (PDOException $e) {
        $errors[] = "Hata: " . $e->getMessage();
    }
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Sistem - Veritabanı Yükleme Aracı</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            padding: 40px 0;
        }
        .container {
            max-width: 800px;
        }
        .card {
            border: none;
            border-radius: 10px;
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.1);
        }
        .card-header {
            background-color: #4e73df;
            color: white;
            border-radius: 10px 10px 0 0 !important;
            padding: 15px 20px;
        }
        .btn-primary {
            background-color: #4e73df;
            border-color: #4e73df;
        }
        .btn-primary:hover {
            background-color: #2e59d9;
            border-color: #2e59d9;
        }
        .alert {
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="card-header">
                <h3 class="m-0">ERP Sistem - Veritabanı Yükleme Aracı</h3>
            </div>
            <div class="card-body">
                
                <?php if (!empty($messages)): ?>
                    <?php foreach ($messages as $message): ?>
                        <div class="alert alert-success"><?php echo $message; ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <?php if (!empty($errors)): ?>
                    <?php foreach ($errors as $error): ?>
                        <div class="alert alert-danger"><?php echo $error; ?></div>
                    <?php endforeach; ?>
                <?php endif; ?>
                
                <p class="lead">Bu araç, ERP Sistem için veritabanı şemasını ve örnek verileri yükler.</p>
                
                <div class="alert alert-info">
                    <h5>Yükleme Öncesi Kontrol Edilmesi Gerekenler:</h5>
                    <ul class="mb-0">
                        <li>MySQL veya MariaDB sunucusunun çalıştığından emin olun.</li>
                        <li>Veritabanı kullanıcı adı ve şifresinin doğru olduğundan emin olun.</li>
                        <li>Eğer daha önce aynı isimde bir veritabanı varsa, içerisindeki veriler silinecektir.</li>
                    </ul>
                </div>
                
                <form method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" class="mt-4">
                    <div class="mb-3">
                        <label for="host" class="form-label">Veritabanı Sunucusu</label>
                        <input type="text" class="form-control" id="host" name="host" value="<?php echo $host; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="username" class="form-label">Kullanıcı Adı</label>
                        <input type="text" class="form-control" id="username" name="username" value="<?php echo $username; ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label for="password" class="form-label">Şifre</label>
                        <input type="password" class="form-control" id="password" name="password" value="<?php echo $password; ?>">
                    </div>
                    
                    <div class="mb-3">
                        <label for="dbname" class="form-label">Veritabanı Adı</label>
                        <input type="text" class="form-control" id="dbname" name="dbname" value="<?php echo $dbName; ?>" required>
                    </div>
                    
                    <div class="d-grid gap-2">
                        <button type="submit" class="btn btn-primary btn-lg">Veritabanını Yükle</button>
                    </div>
                </form>
                
                <?php if (!empty($messages)): ?>
                    <div class="alert alert-success mt-4">
                        <p><strong>Kurulum tamamlandı!</strong></p>
                        <p>Sisteme giriş yapmak için aşağıdaki bilgileri kullanabilirsiniz:</p>
                        <ul>
                            <li><strong>Kullanıcı Adı:</strong> admin</li>
                            <li><strong>Şifre:</strong> admin123</li>
                        </ul>
                        <p><a href="../login.php" class="btn btn-success">Giriş Sayfasına Git</a></p>
                    </div>
                <?php endif; ?>
            </div>
            <div class="card-footer text-center text-muted py-3">
                &copy; <?php echo date('Y'); ?> ERP Sistem - Tüm Hakları Saklıdır
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 