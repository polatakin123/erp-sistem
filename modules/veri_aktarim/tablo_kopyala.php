<?php
/**
 * ERP Sistem - Tablo Kopyalama Sayfası
 * 
 * Bu dosya kaynak veritabanından tüm tabloları hedef veritabanına olduğu gibi kopyalamak için kullanılır.
 */

// Hata ayıklama kodları
ini_set('display_errors', 0); // Hata mesajlarını kapatıyoruz
ini_set('display_startup_errors', 0);
error_reporting(0);

// Uzun süren işlemler için zaman aşımı limitlerini artır
ini_set('max_execution_time', 600); // 10 dakika
ini_set('memory_limit', '512M');    // 512 MB bellek limiti

// Çıktı tamponlamasını devre dışı bırak (ilerleme bilgisi için)
ob_implicit_flush(true);
ob_end_flush();

// Oturum başlat
session_start();

// Oturum ve veritabanı bilgilerini kontrol et
// Hata ayıklama bilgilerini kaldırıyoruz
/*
echo "<div style='background: #f8f9fa; border: 1px solid #ddd; padding: 10px; margin-bottom: 10px; font-family: monospace;'>";
echo "<h4>Hata Ayıklama Bilgileri:</h4>";
echo "Oturum durumu: " . (session_status() == PHP_SESSION_ACTIVE ? "Aktif" : "Aktif değil") . "<br>";
echo "user_id kontrolü: " . (isset($_SESSION['user_id']) ? "Var (ID: {$_SESSION['user_id']})" : "Yok") . "<br>";
echo "source_db kontrolü: " . (isset($_SESSION['source_db']) ? "Var" : "Yok") . "<br>";

if (isset($_SESSION['source_db'])) {
    echo "<pre>";
    print_r($_SESSION['source_db']);
    echo "</pre>";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<h4>POST Verileri:</h4>";
    echo "<pre>";
    print_r($_POST);
    echo "</pre>";
}
echo "</div>";
*/

// Önceki tüm mesajları temizle
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
unset($_SESSION['warning_message']);

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Veritabanı bağlantı bilgileri kontrolü
if (!isset($_SESSION['source_db'])) {
    $_SESSION['error_message'] = "Veritabanı bağlantı bilgisi bulunamadı. Lütfen önce bağlantı kurun.";
    header('Location: index.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Tablo Kopyalama";

// Veritabanı bağlantı bilgilerini al
$source_db = $_SESSION['source_db'];
$is_mssql = $source_db['is_mssql'];

// Hata ve başarı mesajları için diziler
$errors = [];
$success = [];
$warnings = [];

// İşlem başladıysa sayfa başlığını göster
if (isset($_POST['start_copy'])) {
    ?>
    <!DOCTYPE html>
    <html lang="tr">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Tablo Kopyalama - İlerleme</title>
        <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
        <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
        <style>
            .progress-table {
                width: 100%;
                border-collapse: collapse;
                margin-bottom: 20px;
            }
            .progress-table th, .progress-table td {
                padding: 8px;
                border: 1px solid #ddd;
            }
            .progress-table th {
                background-color: #f2f2f2;
                text-align: left;
            }
            .status-success {
                color: green;
            }
            .status-error {
                color: red;
            }
            .status-pending {
                color: orange;
            }
            .progress-container {
                margin: 20px 0;
            }
            #log-container {
                height: 300px;
                overflow-y: auto;
                background-color: #f8f9fa;
                padding: 10px;
                border: 1px solid #ddd;
                border-radius: 4px;
                font-family: monospace;
                margin-bottom: 20px;
            }
        </style>
    </head>
    <body>
        <div class="container mt-4">
            <h1>Tablo Kopyalama İşlemi</h1>
            <div class="alert alert-info">
                <i class="fas fa-info-circle"></i> Tablo kopyalama işlemi devam ediyor. Lütfen sayfayı kapatmayın.
            </div>
            
            <div class="progress-container">
                <h4>Genel İlerleme: <span id="progress-percent">0%</span></h4>
                <div class="progress">
                    <div id="progress-bar" class="progress-bar progress-bar-striped progress-bar-animated" role="progressbar" style="width: 0%"></div>
                </div>
            </div>
            
            <div id="log-container">
                <strong>İşlem kayıtları:</strong><br>
            </div>
            
            <table class="progress-table" id="progress-table">
                <thead>
                    <tr>
                        <th width="5%">#</th>
                        <th width="30%">Tablo Adı</th>
                        <th width="15%">Kayıt Sayısı</th>
                        <th width="15%">Durum</th>
                        <th width="35%">Bilgi</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Tablo satırları burada JavaScript ile doldurulacak -->
                </tbody>
            </table>
            
            <div id="summary-container"></div>
            
            <div class="mt-3">
                <a href="index.php" class="btn btn-primary" id="back-button" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Ana Sayfaya Dön
                </a>
            </div>
        </div>
        
        <script>
            // Logları eklemek için fonksiyon
            function addLog(message) {
                const logContainer = document.getElementById('log-container');
                const logEntry = document.createElement('div');
                logEntry.innerHTML = message;
                logContainer.appendChild(logEntry);
                logContainer.scrollTop = logContainer.scrollHeight;
            }
            
            // Tablo satırı eklemek için fonksiyon
            function addTableRow(index, tableName, recordCount) {
                const table = document.getElementById('progress-table').getElementsByTagName('tbody')[0];
                const row = table.insertRow();
                
                const cell1 = row.insertCell(0);
                const cell2 = row.insertCell(1);
                const cell3 = row.insertCell(2);
                const cell4 = row.insertCell(3);
                const cell5 = row.insertCell(4);
                
                cell1.textContent = index;
                cell2.textContent = tableName;
                cell3.textContent = recordCount;
                cell4.innerHTML = '<span class="status-pending">Bekliyor...</span>';
                cell5.textContent = '';
                
                row.id = 'table-row-' + tableName.replace(/[^a-zA-Z0-9]/g, '_');
                return row;
            }
            
            // Tablo durumunu güncellemek için fonksiyon
            function updateTableStatus(tableName, status, message) {
                const rowId = 'table-row-' + tableName.replace(/[^a-zA-Z0-9]/g, '_');
                const row = document.getElementById(rowId);
                
                if (row) {
                    const statusCell = row.cells[3];
                    const messageCell = row.cells[4];
                    
                    if (status === 'success') {
                        statusCell.innerHTML = '<span class="status-success">Başarılı</span>';
                    } else if (status === 'error') {
                        statusCell.innerHTML = '<span class="status-error">Hata</span>';
                    } else if (status === 'processing') {
                        statusCell.innerHTML = '<span class="status-pending">İşleniyor...</span>';
                    }
                    
                    messageCell.textContent = message || '';
                }
            }
            
            // İlerlemeyi güncellemek için fonksiyon
            function updateProgress(current, total) {
                const percent = Math.round((current / total) * 100);
                document.getElementById('progress-bar').style.width = percent + '%';
                document.getElementById('progress-percent').textContent = percent + '%';
                
                if (current === total) {
                    document.getElementById('back-button').style.display = 'inline-block';
                }
            }
            
            // Özet bilgilerini güncellemek için fonksiyon
            function updateSummary(copied, failed) {
                const summaryContainer = document.getElementById('summary-container');
                summaryContainer.innerHTML = `
                    <div class="alert alert-${failed > 0 ? 'warning' : 'success'}">
                        <strong>İşlem tamamlandı!</strong><br>
                        ${copied} tablo başarıyla kopyalandı.<br>
                        ${failed} tablo kopyalanırken hata oluştu.
                    </div>
                `;
            }
        </script>
    </body>
    </html>
    <?php
    // Tarayıcıya doğrudan göndermek için çıktı tamponunu boşalt
    flush();
}

// Kaynak veritabanına bağlan
try {
    if ($is_mssql) {
        // MSSQL bağlantısı (PDO kullanarak)
        $dsn = "sqlsrv:Server=" . $source_db['host'] . ";Database=" . $source_db['name'];
        $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass']);
        $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        try {
            // Tabloları al
            $tables = [];
            
            // Yöntem 1: sys.tables sorgusu
            try {
                $stmt = $sourceDb->query("SELECT name FROM sys.tables ORDER BY name");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            } catch (PDOException $e) {
                // Hata olursa diğer yöntemleri deneyelim
                $tables = [];
            }
            
            // Yöntem 2: INFORMATION_SCHEMA.TABLES sorgusu (tablolar bulunamadıysa)
            if (count($tables) === 0) {
                try {
                    $stmt = $sourceDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (PDOException $e) {
                    // Hata olursa diğer yöntemleri deneyelim
                    $tables = [];
                }
            }
            
            // Diğer alternatif yöntemler...
            if (count($tables) === 0) {
                // Farklı sorgular denenebilir...
                $warnings[] = "MSSQL tablolar alınamadı. Lütfen manuel olarak tablolarınızı kontrol edin.";
            }
            
        } catch (PDOException $e) {
            $errors[] = "MSSQL Sorgu hatası: " . $e->getMessage();
        }
    } else {
        // MySQL bağlantısı
        $dsn = "mysql:host=" . $source_db['host'] . ";dbname=" . $source_db['name'];
        if (!empty($source_db['charset'])) {
            $dsn .= ";charset=" . $source_db['charset'];
        }
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass'], $options);
        
        // Veritabanındaki tabloları al
        $stmt = $sourceDb->query("SHOW TABLES");
        $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }
    
    // Kayıt sayısı olan tabloları filtrele
    $tables_with_records = [];
    
    foreach ($tables as $table) {
        try {
            // Tablo kayıt sayısını kontrol et
            if ($is_mssql) {
                $tableName = str_replace("'", "''", $table);
                $countSql = "SELECT COUNT(*) AS total FROM [$tableName]";
                $countStmt = $sourceDb->prepare($countSql);
                $countStmt->execute();
            } else {
                $countStmt = $sourceDb->prepare("SELECT COUNT(*) FROM `" . $table . "`");
                $countStmt->execute();
            }
            
            $count = $countStmt->fetchColumn();
            
            // Sadece kayıt içeren tabloları listeye ekle
            if ($count > 0) {
                $tables_with_records[] = [
                    'name' => $table,
                    'count' => $count
                ];
            }
        } catch (PDOException $e) {
            $warnings[] = "Tablo '$table' kayıt sayısı kontrol edilirken hata: " . $e->getMessage();
        }
    }
    
    // İşlem başlatıldıysa
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start_copy'])) {
        try {
            // İlk bilgilendirme
            echo "<script>
                addLog('<strong>Kopyalama işlemi başlatılıyor...</strong>');
                addLog('Toplam " . count($tables_with_records) . " tablo kopyalanacak.');
            </script>";
            flush();
            
            // Tablo satırlarını ekle
            $tableIndex = 1;
            foreach ($tables_with_records as $table_info) {
                echo "<script>
                    addTableRow(" . $tableIndex . ", '" . addslashes($table_info['name']) . "', " . $table_info['count'] . ");
                </script>";
                flush();
                $tableIndex++;
            }
            
            // Hedef veritabanına tabloları kopyalama işlemi
            $copied_tables = 0;
            $skipped_tables = 0;
            $failed_tables = 0;
            $total_tables = count($tables_with_records);
            
            // Tüm tabloları kopyala
            $tableIndex = 0;
            foreach ($tables_with_records as $table_info) {
                $tableIndex++;
                $table = $table_info['name'];
                $count = $table_info['count'];
                
                // İşleniyor durumunu güncelle
                echo "<script>
                    updateTableStatus('" . addslashes($table) . "', 'processing', 'İşlem başlatıldı...');
                    addLog('Tablo işleniyor: " . addslashes($table) . " (" . $count . " kayıt)');
                </script>";
                flush();
                
                try {
                    // 1. Tablo yapısını al
                    echo "<script>addLog('Tablo yapısı alınıyor: " . addslashes($table) . "');</script>";
                    flush();
                    
                    if ($is_mssql) {
                        // MSSQL tablo yapısını almak daha karmaşık, basit bir yaklaşım kullanıyoruz
                        $tableName = str_replace("'", "''", $table);
                        $columnSql = "SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE 
                                       FROM INFORMATION_SCHEMA.COLUMNS 
                                       WHERE TABLE_NAME = '$tableName'";
                        $columnStmt = $sourceDb->query($columnSql);
                        $columns = $columnStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        // Tablo oluşturma SQL'i
                        $createSql = "CREATE TABLE IF NOT EXISTS `$table` (";
                        foreach ($columns as $column) {
                            $columnName = $column['COLUMN_NAME'];
                            $dataType = $column['DATA_TYPE'];
                            $maxLength = $column['CHARACTER_MAXIMUM_LENGTH'];
                            $isNullable = $column['IS_NULLABLE'] === 'YES' ? 'NULL' : 'NOT NULL';
                            
                            // MySQL veri tipine çevir (basit bir örnek)
                            switch (strtolower($dataType)) {
                                case 'nvarchar':
                                case 'varchar':
                                    $maxLength = $maxLength > 0 ? $maxLength : 255;
                                    $mysqlType = "VARCHAR($maxLength)";
                                    break;
                                case 'int':
                                    $mysqlType = "INT";
                                    break;
                                case 'decimal':
                                    $mysqlType = "DECIMAL(18,2)"; // Varsayılan
                                    break;
                                case 'datetime':
                                    $mysqlType = "DATETIME";
                                    break;
                                default:
                                    $mysqlType = "VARCHAR(255)"; // Varsayılan
                            }
                            
                            $createSql .= "`$columnName` $mysqlType $isNullable, ";
                        }
                        $createSql = rtrim($createSql, ", ") . ")";
                    } else {
                        // MySQL tablo yapısını al
                        $createStmt = $sourceDb->query("SHOW CREATE TABLE `$table`");
                        $createRow = $createStmt->fetch(PDO::FETCH_ASSOC);
                        $createSql = isset($createRow['Create Table']) ? $createRow['Create Table'] : $createRow[1];
                    }
                    
                    // 2. Tablodaki verileri al
                    echo "<script>addLog('Tablo verileri alınıyor: " . addslashes($table) . "');</script>";
                    flush();
                    
                    if ($is_mssql) {
                        $tableName = str_replace("'", "''", $table);
                        $dataSql = "SELECT * FROM [$tableName]";
                    } else {
                        $dataSql = "SELECT * FROM `$table`";
                    }
                    $dataStmt = $sourceDb->query($dataSql);
                    $rows = $dataStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 3. Hedef veritabanında tabloyu oluştur (eğer yoksa)
                    echo "<script>addLog('Hedef veritabanında tablo oluşturuluyor: " . addslashes($table) . "');</script>";
                    flush();
                    
                    // Önce mevcut tabloyu sil (isteğe bağlı)
                    $db->exec("DROP TABLE IF EXISTS `$table`");
                    
                    // Tabloyu oluştur
                    $db->exec($createSql);
                    
                    // 4. Verileri ekle
                    if (count($rows) > 0) {
                        echo "<script>addLog('Veriler hedef tabloya ekleniyor: " . addslashes($table) . " (" . count($rows) . " kayıt)');</script>";
                        flush();
                        
                        // İlk satırdan sütun isimlerini al
                        $columns = array_keys($rows[0]);
                        $columnStr = "`" . implode("`, `", $columns) . "`";
                        $placeholders = ":" . implode(", :", $columns);
                        
                        // INSERT sorgusu hazırla
                        $insertSql = "INSERT INTO `$table` ($columnStr) VALUES ($placeholders)";
                        $insertStmt = $db->prepare($insertSql);
                        
                        // Her satırı ekle
                        $insertedRows = 0;
                        $totalRows = count($rows);
                        
                        foreach ($rows as $index => $row) {
                            try {
                                $insertStmt->execute($row);
                                $insertedRows++;
                                
                                // Her 100 satırda bir ilerleme bilgisi
                                if ($insertedRows % 100 === 0 || $insertedRows === $totalRows) {
                                    $percent = round(($insertedRows / $totalRows) * 100);
                                    echo "<script>
                                        updateTableStatus('" . addslashes($table) . "', 'processing', 'Veri ekleniyor... %" . $percent . " (" . $insertedRows . "/" . $totalRows . ")');
                                    </script>";
                                    flush();
                                }
                            } catch (PDOException $e) {
                                // Satır eklenirken hata oldu, devam et
                                $warnings[] = "Tablo '$table' için veri eklenirken hata: " . $e->getMessage();
                                echo "<script>addLog('Uyarı: " . addslashes($table) . " tablosunda satır ekleme hatası: " . addslashes($e->getMessage()) . "');</script>";
                                flush();
                            }
                        }
                    }
                    
                    // Başarıyla tamamlandı mı kontrol et
                    $verifyCountSql = "SELECT COUNT(*) FROM `$table`";
                    $verifyStmt = $db->query($verifyCountSql);
                    $verifyCount = $verifyStmt->fetchColumn();
                    
                    if ($verifyCount > 0) {
                        $copied_tables++;
                        $success[] = "Tablo '$table' başarıyla kopyalandı. ($count kayıt)";
                        
                        echo "<script>
                            updateTableStatus('" . addslashes($table) . "', 'success', 'Başarıyla kopyalandı. Toplam $verifyCount kayıt.');
                            addLog('✅ Tablo başarıyla kopyalandı: " . addslashes($table) . " ($verifyCount kayıt)');
                            updateProgress($tableIndex, $total_tables);
                        </script>";
                        flush();
                    } else {
                        $failed_tables++;
                        $errors[] = "Tablo '$table' kopyalandı ancak veri eklenemedi.";
                        
                        echo "<script>
                            updateTableStatus('" . addslashes($table) . "', 'error', 'Tablo oluşturuldu ancak veri eklenemedi.');
                            addLog('❌ Tablo kopyalama hatası: " . addslashes($table) . " (Veri eklenemedi)');
                            updateProgress($tableIndex, $total_tables);
                        </script>";
                        flush();
                    }
                } catch (PDOException $e) {
                    $failed_tables++;
                    $errors[] = "Tablo '$table' kopyalanırken hata: " . $e->getMessage();
                    
                    echo "<script>
                        updateTableStatus('" . addslashes($table) . "', 'error', '" . addslashes($e->getMessage()) . "');
                        addLog('❌ Tablo kopyalama hatası: " . addslashes($table) . " - " . addslashes($e->getMessage()) . "');
                        updateProgress($tableIndex, $total_tables);
                    </script>";
                    flush();
                }
            }
            
            // İşlem tamamlandı
            echo "<script>
                addLog('<strong>İşlem tamamlandı!</strong> $copied_tables tablo başarıyla kopyalandı, $failed_tables tablo kopyalanamadı.');
                updateSummary($copied_tables, $failed_tables);
                updateProgress($total_tables, $total_tables);
            </script>";
            flush();
            
            $success[] = "İşlem tamamlandı. $copied_tables tablo başarıyla kopyalandı, $failed_tables tablo kopyalanamadı.";
            
        } catch (Exception $e) {
            $errors[] = "Kopyalama işlemi sırasında hata: " . $e->getMessage();
            echo "<script>
                addLog('❌ Genel hata: " . addslashes($e->getMessage()) . "');
                document.getElementById('back-button').style.display = 'inline-block';
            </script>";
            flush();
        }
        
        // POST ile gönderildiğinde HTML çıktısını tamamla ve çık
        exit;
    }
    
} catch (PDOException $e) {
    $errors[] = "Veritabanı bağlantı hatası: " . $e->getMessage();
}

// Normal sayfa görünümü (ilk yükleme)
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

            <?php if (count($errors) > 0): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-circle"></i> <?php echo $error; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (count($warnings) > 0): ?>
                <?php foreach ($warnings as $warning): ?>
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> <?php echo $warning; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <?php if (count($success) > 0): ?>
                <?php foreach ($success as $message): ?>
                    <div class="alert alert-success">
                        <i class="fas fa-check-circle"></i> <?php echo $message; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="card mb-4">
                <div class="card-header">
                    <i class="fas fa-database"></i> Tablolar
                </div>
                <div class="card-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i> Bu sayfa, kaynak veritabanındaki tüm tabloları (kayıt içeren) hedef veritabanına olduğu gibi kopyalar. Bu işlem mevcut tabloları yeniden oluşturur, içeriği tamamen değiştirir. Devam etmeden önce yedek almayı unutmayın.
                    </div>
                    
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover">
                            <thead>
                                <tr>
                                    <th>Tablo Adı</th>
                                    <th>Kayıt Sayısı</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($tables_with_records as $table_info): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($table_info['name']); ?></td>
                                    <td><?php echo number_format($table_info['count']); ?></td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <?php if (count($tables_with_records) > 0): ?>
                    <form method="post" target="_blank">
                        <div class="mt-3">
                            <button type="submit" name="start_copy" class="btn btn-primary" onclick="return confirm('Bu işlem mevcut verilerin üzerine yazacaktır. Devam etmek istiyor musunuz?');">
                                <i class="fas fa-copy"></i> Tüm Tabloları Kopyala
                            </button>
                        </div>
                    </form>
                    <?php else: ?>
                    <div class="alert alert-warning mt-3">
                        <i class="fas fa-exclamation-triangle"></i> Kaynak veritabanında kayıt içeren tablo bulunamadı.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?> 