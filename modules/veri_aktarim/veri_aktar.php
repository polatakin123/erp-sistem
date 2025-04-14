<?php
/**
 * ERP Sistem - Veri Aktarımı İşlemi
 * 
 * Bu dosya seçilen tabloların verilerini hedef tablolara aktarmak için kullanılır.
 */

// Hata ayarları
ini_set('display_errors', 0);
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

// POST verilerini kontrol et
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['mapping']) || empty($_POST['mapping'])) {
    $_SESSION['error_message'] = "Veri eşleştirmesi bulunamadı. Lütfen tekrar deneyin.";
    header('Location: tablo_secimi.php');
    exit;
}

// İlerleme sayfasını göster
echo "<!DOCTYPE html>
<html lang=\"tr\">
<head>
    <meta charset=\"UTF-8\">
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1.0\">
    <title>Veri Aktarımı İşlemi</title>
    <link href=\"https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css\" rel=\"stylesheet\">
    <link href=\"https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css\" rel=\"stylesheet\">
    <style>
        .progress-container {
            margin-bottom: 20px;
        }
        .table-progress {
            margin-top: 30px;
        }
        .log-container {
            height: 200px;
            overflow-y: auto;
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            border-radius: 0.25rem;
            padding: 10px;
            margin-bottom: 20px;
            font-family: monospace;
        }
        .success {
            color: green;
        }
        .error {
            color: red;
        }
        .pending {
            color: orange;
        }
    </style>
</head>
<body>
<div class=\"container mt-4\">
    <h1>Veri Aktarım İşlemi</h1>
    <div class=\"alert alert-info\">
        <i class=\"fas fa-info-circle\"></i> Veri aktarımı başlatıldı. Bu işlem verilerin miktarına göre biraz zaman alabilir. Lütfen sayfayı kapatmayın.
    </div>
    
    <div class=\"progress-container\">
        <h4>Genel İlerleme: <span id=\"overall-progress\">0%</span></h4>
        <div class=\"progress\">
            <div id=\"overall-progress-bar\" class=\"progress-bar progress-bar-striped progress-bar-animated\" role=\"progressbar\" style=\"width: 0%\" aria-valuenow=\"0\" aria-valuemin=\"0\" aria-valuemax=\"100\"></div>
        </div>
    </div>
    
    <div class=\"log-container\" id=\"log\">
        <div><strong>İşlem kayıtları:</strong></div>
    </div>
    
    <div class=\"table-progress\" id=\"table-progress\">
        <!-- Her tablo için ilerleme burada gösterilecek -->
    </div>
    
    <div class=\"mt-3\" id=\"result-actions\" style=\"display: none;\">
        <a href=\"index.php\" class=\"btn btn-primary\">
            <i class=\"fas fa-home\"></i> Ana Sayfaya Dön
        </a>
    </div>
</div>
<script>
// Log ekleme fonksiyonu
function addLog(message, status = \"\") {
    const logElement = document.getElementById(\"log\");
    const logEntry = document.createElement(\"div\");
    
    if (status === \"success\") {
        logEntry.className = \"success\";
    } else if (status === \"error\") {
        logEntry.className = \"error\";
    } else if (status === \"pending\") {
        logEntry.className = \"pending\";
    }
    
    logEntry.innerHTML = message;
    logElement.appendChild(logEntry);
    logElement.scrollTop = logElement.scrollHeight;
}

// İlerleme çubuğu ekleme fonksiyonu
function addTableProgress(tableName, total) {
    const tableProgress = document.getElementById(\"table-progress\");
    const container = document.createElement(\"div\");
    container.className = \"mb-3\";
    container.id = \"progress-\" + tableName.replace(/[^a-zA-Z0-9]/g, \"_\");
    
    const title = document.createElement(\"h5\");
    title.innerText = \"Tablo: \" + tableName;
    
    const status = document.createElement(\"div\");
    status.className = \"text-muted small mb-1\";
    status.id = \"status-\" + tableName.replace(/[^a-zA-Z0-9]/g, \"_\");
    status.innerText = \"Aktarım başlatılıyor...\";
    
    const progressContainer = document.createElement(\"div\");
    progressContainer.className = \"progress\";
    
    const progressBar = document.createElement(\"div\");
    progressBar.className = \"progress-bar\";
    progressBar.id = \"bar-\" + tableName.replace(/[^a-zA-Z0-9]/g, \"_\");
    progressBar.role = \"progressbar\";
    progressBar.style.width = \"0%\";
    progressBar.setAttribute(\"aria-valuenow\", \"0\");
    progressBar.setAttribute(\"aria-valuemin\", \"0\");
    progressBar.setAttribute(\"aria-valuemax\", \"100\");
    
    progressContainer.appendChild(progressBar);
    container.appendChild(title);
    container.appendChild(status);
    container.appendChild(progressContainer);
    tableProgress.appendChild(container);
}

// Tablo ilerleme durumunu güncelleme fonksiyonu
function updateTableProgress(tableName, current, total, status = \"\") {
    const percentage = Math.round((current / total) * 100);
    const progressBarId = \"bar-\" + tableName.replace(/[^a-zA-Z0-9]/g, \"_\");
    const statusId = \"status-\" + tableName.replace(/[^a-zA-Z0-9]/g, \"_\");
    
    const progressBar = document.getElementById(progressBarId);
    const statusElement = document.getElementById(statusId);
    
    if (progressBar) {
        progressBar.style.width = percentage + \"%\";
        progressBar.setAttribute(\"aria-valuenow\", percentage);
        
        if (status === \"success\") {
            progressBar.className = \"progress-bar bg-success\";
        } else if (status === \"error\") {
            progressBar.className = \"progress-bar bg-danger\";
        } else {
            progressBar.className = \"progress-bar\";
        }
    }
    
    if (statusElement) {
        if (status === \"success\") {
            statusElement.innerText = \"Tamamlandı: \" + current + \" / \" + total + \" kayıt (\" + percentage + \"%)\";
            statusElement.className = \"text-success small mb-1\";
        } else if (status === \"error\") {
            statusElement.innerText = \"Hata: \" + status;
            statusElement.className = \"text-danger small mb-1\";
        } else {
            statusElement.innerText = \"İşleniyor: \" + current + \" / \" + total + \" kayıt (\" + percentage + \"%)\";
            statusElement.className = \"text-muted small mb-1\";
        }
    }
}

// Genel ilerleme durumunu güncelleme fonksiyonu
function updateOverallProgress(current, total) {
    const percentage = Math.round((current / total) * 100);
    const progressBar = document.getElementById(\"overall-progress-bar\");
    const progressText = document.getElementById(\"overall-progress\");
    
    if (progressBar && progressText) {
        progressBar.style.width = percentage + \"%\";
        progressBar.setAttribute(\"aria-valuenow\", percentage);
        progressText.innerText = percentage + \"%\";
        
        if (percentage === 100) {
            document.getElementById(\"result-actions\").style.display = \"block\";
        }
    }
}

// Sayfa yüklendi başlangıç mesajını göster
addLog(\"Veri aktarımı başlatılıyor...\");
</script>";
flush();

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Eşleştirme verilerini al
$mapping = $_POST['mapping'];

// Sayfa başlığı
$pageTitle = "Veri Aktarımı";

// Veritabanı bağlantı bilgilerini al
$source_db = $_SESSION['source_db'];
$is_mssql = $source_db['is_mssql'];

// Kaynak veritabanına bağlan
try {
    if ($is_mssql) {
        // MSSQL bağlantısı (PDO kullanarak)
        $dsn = "sqlsrv:Server=" . $source_db['host'] . ";Database=" . $source_db['name'];
        $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass']);
        $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
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
    }
    
    // Sonuçları saklamak için dizi
    $results = [];
    
    // Toplam tablo sayısını ve mevcut tablo sayacını takip et
    $totalTables = count($mapping);
    $currentTable = 0;
    
    echo "<script>addLog('Toplam " . $totalTables . " tablo işlenecek.')</script>";
    flush();
    
    // Her bir tablo için veri aktarımı yap
    foreach ($mapping as $table => $config) {
        $currentTable++;
        
        if (!isset($config['source_columns']) || !isset($config['target_columns']) || !isset($config['target_table'])) {
            $results[$table] = [
                'status' => 'error',
                'message' => 'Eksik yapılandırma bilgileri',
                'inserted' => 0,
                'errors' => []
            ];
            echo "<script>addLog('Tablo " . $table . ": Eksik yapılandırma bilgileri', 'error');</script>";
            flush();
            continue;
        }
        
        $source_columns = $config['source_columns'];
        $target_columns = $config['target_columns'];
        $target_table = $config['target_table'];
        
        // Hedef tablo adının boş olup olmadığını kontrol et
        if (empty($target_table) || trim($target_table) === '') {
            $results[$table] = [
                'status' => 'error',
                'message' => 'Hedef tablo adı boş olamaz. Lütfen geçerli bir tablo adı belirtin.',
                'inserted' => 0,
                'errors' => []
            ];
            echo "<script>addLog('Tablo " . $table . ": Hedef tablo adı boş olamaz', 'error');</script>";
            flush();
            continue;
        }
        
        $exists = isset($config['exists']) && $config['exists'] == '1';
        $truncate = isset($config['truncate']) && $config['truncate'] == '1';
        
        // Sadece aktarılacak alanları filtrele (boş olmayanlar)
        $filtered_source_columns = [];
        $filtered_target_columns = [];
        $used_target_columns = []; // Mükerrer hedef sütunlarını kontrol etmek için

        for ($i = 0; $i < count($source_columns); $i++) {
            if (!empty($target_columns[$i])) {
                $target_col = $target_columns[$i];
                
                // Otomatik oluşturulan alanlar için işlem yap
                if (strpos($target_col, 'AUTO:') === 0) {
                    $target_col = substr($target_col, 5);  // "AUTO:" kısmını çıkar
                }
                
                // Hedef sütun adı daha önce kullanılmışsa atla ve hata kaydı oluştur
                if (in_array($target_col, $used_target_columns)) {
                    echo "<script>addLog('UYARI: " . addslashes($target_col) . " sütunu birden fazla kez belirtilmiş. İlk eşleştirme kullanılacak.', 'error');</script>";
                    flush();
                    continue;
                }
                
                $filtered_source_columns[] = $source_columns[$i];
                $filtered_target_columns[] = $target_col;
                $used_target_columns[] = $target_col; // Kullanılan hedef sütunu kaydet
            }
        }
        
        // Önce kaynak tablodan kayıt sayısını al (ilerleme çubuğu için)
        try {
            if ($is_mssql) {
                $countSql = "SELECT COUNT(*) AS total FROM [$table]";
            } else {
                $countSql = "SELECT COUNT(*) AS total FROM `$table`";
            }
            $countStmt = $sourceDb->query($countSql);
            $totalRecords = (int)$countStmt->fetchColumn();
            
            echo "<script>addLog('Tablo " . $table . ": Toplam " . $totalRecords . " kayıt aktarılacak.');</script>";
            echo "<script>addTableProgress('" . $table . "', " . $totalRecords . ");</script>";
            flush();
        } catch (PDOException $e) {
            echo "<script>addLog('Tablo " . $table . ": Kayıt sayısı hesaplanamadı - " . addslashes($e->getMessage()) . "', 'error');</script>";
            $totalRecords = 0; // Bilinmiyorsa 0 olarak belirle
            flush();
        }
        
        // Tablo daha önce yoksa oluştur
        if (!$exists) {
            try {
                // Bir kez daha tablo varlığını kontrol edelim
                $table_exists_check = $db->prepare("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = ?");
                $table_exists_check->execute([$target_table]);
                
                if ($table_exists_check->fetchColumn() > 0) {
                    // Tablo zaten mevcut, exists değerini güncelle
                    $exists = true;
                    echo "<script>addLog('Tablo " . $target_table . " zaten mevcut, üzerine yazılacak.', 'pending');</script>";
                    flush();
                } else {
                    // Tablo yok, oluştur
                    // Tablo adının güvenliğini kontrol et
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $target_table)) {
                        throw new PDOException("Geçersiz tablo adı formatı: " . $target_table);
                    }
                    
                    echo "<script>addLog('Tablo " . $target_table . " oluşturuluyor...', 'pending');</script>";
                    flush();
                    
                    // Kaynak tablodaki sütun bilgilerini al
                    if ($is_mssql) {
                        $stmt = $sourceDb->prepare("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH, IS_NULLABLE 
                                                 FROM INFORMATION_SCHEMA.COLUMNS 
                                                 WHERE TABLE_NAME = ? 
                                                 ORDER BY ORDINAL_POSITION");
                        $stmt->execute([$table]);
                    } else {
                        $stmt = $sourceDb->prepare("SHOW COLUMNS FROM `$table`");
                        $stmt->execute();
                    }
                    
                    $columns_info = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // MySQL tablo oluşturma SQL'i
                    $create_sql = "CREATE TABLE `$target_table` (\n";
                    $column_defs = [];
                    $primary_keys = isset($config['primary_key']) ? $config['primary_key'] : [];
                    
                    foreach ($columns_info as $col) {
                        $column_name = $is_mssql ? $col['COLUMN_NAME'] : $col['Field'];
                        
                        // Bu kolonu aktarıma dahil etmek için kontrol et
                        if (!in_array($column_name, $filtered_source_columns)) {
                            continue;
                        }
                        
                        // Hedef kolon adını bul
                        $target_column_name = $filtered_target_columns[array_search($column_name, $filtered_source_columns)];
                        
                        if ($is_mssql) {
                            // MSSQL'den MySQL'e veri tipi dönüşümü
                            $data_type = strtolower($col['DATA_TYPE']);
                            $length = $col['CHARACTER_MAXIMUM_LENGTH'];
                            $nullable = $col['IS_NULLABLE'] == 'YES' ? 'NULL' : 'NOT NULL';
                            
                            // Veri tipi dönüşümleri
                            switch ($data_type) {
                                case 'nvarchar':
                                case 'varchar':
                                    if ($length == -1) {
                                        $mysql_type = "TEXT";
                                    } else {
                                        $mysql_type = "VARCHAR($length)";
                                    }
                                    break;
                                case 'nchar':
                                case 'char':
                                    $mysql_type = "CHAR($length)";
                                    break;
                                case 'int':
                                case 'bigint':
                                    $mysql_type = "INT";
                                    break;
                                case 'decimal':
                                case 'numeric':
                                    $mysql_type = "DECIMAL(18,2)";
                                    break;
                                case 'datetime':
                                case 'datetime2':
                                    $mysql_type = "DATETIME";
                                    break;
                                case 'date':
                                    $mysql_type = "DATE";
                                    break;
                                case 'bit':
                                    $mysql_type = "TINYINT(1)";
                                    break;
                                case 'uniqueidentifier':
                                    $mysql_type = "VARCHAR(36)";
                                    break;
                                case 'text':
                                case 'ntext':
                                    $mysql_type = "TEXT";
                                    break;
                                case 'image':
                                    $mysql_type = "BLOB";
                                    break;
                                default:
                                    $mysql_type = "VARCHAR(255)";
                            }
                            
                            $column_def = "`$target_column_name` $mysql_type $nullable";
                            
                        } else {
                            // MySQL'den MySQL'e doğrudan tipi kullan
                            $column_def = "`$target_column_name` " . $col['Type'];
                            
                            if ($col['Null'] == 'NO') {
                                $column_def .= " NOT NULL";
                            }
                            
                            if ($col['Default'] !== null) {
                                $column_def .= " DEFAULT '" . $col['Default'] . "'";
                            }
                            
                            if ($col['Extra'] == 'auto_increment') {
                                $column_def .= " AUTO_INCREMENT";
                            }
                        }
                        
                        $column_defs[] = $column_def;
                    }
                    
                    // Primary key ekle
                    if (!empty($primary_keys)) {
                        // Primary key kolonlarının hedef kolon adlarını bul
                        $target_primary_keys = [];
                        foreach ($primary_keys as $pk) {
                            $idx = array_search($pk, $filtered_source_columns);
                            if ($idx !== false) {
                                $target_primary_keys[] = $filtered_target_columns[$idx];
                            }
                        }
                        
                        if (!empty($target_primary_keys)) {
                            $column_defs[] = "PRIMARY KEY (`" . implode("`, `", $target_primary_keys) . "`)";
                        }
                    }
                    
                    $create_sql .= implode(",\n", $column_defs);
                    $create_sql .= "\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
                    
                    // Tabloyu oluştur
                    $db->exec($create_sql);
                    
                    $exists = true;  // Artık tablo var
                    echo "<script>addLog('Tablo " . $target_table . " başarıyla oluşturuldu.', 'success');</script>";
                    flush();
                }
            } catch (PDOException $e) {
                $results[$table] = [
                    'status' => 'error',
                    'message' => 'Tablo oluşturma hatası: ' . $e->getMessage(),
                    'sql' => isset($create_sql) ? $create_sql : 'SQL yok',
                    'inserted' => 0,
                    'errors' => []
                ];
                echo "<script>addLog('Tablo " . $target_table . " oluşturma hatası: " . addslashes($e->getMessage()) . "', 'error');</script>";
                echo "<script>updateTableProgress('" . $table . "', 0, " . $totalRecords . ", 'error');</script>";
                echo "<script>updateOverallProgress(" . $currentTable . ", " . $totalTables . ");</script>";
                flush();
                continue;
            }
        }
        
        // Mevcut tabloyu temizle (eğer isteniyorsa)
        if ($exists && $truncate) {
            try {
                echo "<script>addLog('Tablo " . $target_table . " temizleniyor...', 'pending');</script>";
                flush();
                
                $db->exec("TRUNCATE TABLE `$target_table`");
                echo "<script>addLog('Tablo " . $target_table . " temizlendi.', 'success');</script>";
                flush();
            } catch (PDOException $e) {
                // Truncate başarısız olursa, DELETE kullan
                try {
                    echo "<script>addLog('TRUNCATE başarısız, DELETE kullanılıyor...', 'pending');</script>";
                    flush();
                    
                    $db->exec("DELETE FROM `$target_table`");
                    echo "<script>addLog('Tablo " . $target_table . " temizlendi (DELETE ile).', 'success');</script>";
                    flush();
                } catch (PDOException $e2) {
                    $results[$table] = [
                        'status' => 'error',
                        'message' => 'Tablo temizleme hatası: ' . $e2->getMessage(),
                        'inserted' => 0,
                        'errors' => []
                    ];
                    echo "<script>addLog('Tablo " . $target_table . " temizleme hatası: " . addslashes($e2->getMessage()) . "', 'error');</script>";
                    echo "<script>updateTableProgress('" . $table . "', 0, " . $totalRecords . ", 'error');</script>";
                    echo "<script>updateOverallProgress(" . $currentTable . ", " . $totalTables . ");</script>";
                    flush();
                    continue;
                }
            }
        }
        
        // Hedef tablo sütunlarını ve yer tutucuları hazırla
        $target_column_names = '`' . implode('`, `', $filtered_target_columns) . '`';
        $placeholders = rtrim(str_repeat('?, ', count($filtered_target_columns)), ', ');
        $insert_sql = "INSERT INTO `$target_table` ($target_column_names) VALUES ($placeholders)";
        $insert_stmt = $db->prepare($insert_sql);
        
        // Sadece yeni kayıtları aktarmak için değişkenler
        $where_clause = "";
        $last_id_column = null;
        $last_id_value = null;
        $incremental_update = false;
        
        // Eğer tabloda temizleme yapılmadıysa ve birincil anahtar varsa sadece yeni kayıtları aktarabiliriz
        if ($exists && !$truncate && isset($config['primary_key']) && !empty($config['primary_key'])) {
            try {
                // Birincil anahtar kolonları
                $primary_keys = $config['primary_key'];
                
                // Hedef tablodaki birincil anahtar sütunu
                $primary_key = $primary_keys[0]; // Şimdilik ilk birincil anahtarı kullanalım
                
                // Hedef kolon adını bul
                $target_primary_key = null;
                foreach ($filtered_source_columns as $index => $source_col) {
                    if ($source_col == $primary_key) {
                        $target_primary_key = $filtered_target_columns[$index];
                        break;
                    }
                }
                
                if ($target_primary_key) {
                    // Hedef tablodaki en son ID'yi bul
                    $last_id_query = $db->prepare("SELECT MAX(`$target_primary_key`) as last_id FROM `$target_table`");
                    $last_id_query->execute();
                    $last_id_result = $last_id_query->fetch(PDO::FETCH_ASSOC);
                    
                    if ($last_id_result && !is_null($last_id_result['last_id'])) {
                        $last_id_value = $last_id_result['last_id'];
                        $last_id_column = $primary_key;
                        $incremental_update = true;
                        
                        echo "<script>addLog('Son kayıt ID: " . $last_id_value . " bulundu. Sadece yeni kayıtlar aktarılacak.', 'success');</script>";
                        flush();
                        
                        // Kaynak veritabanından sadece yeni kayıtları çekmek için WHERE koşulu
                        $where_clause = " WHERE [$primary_key] > '$last_id_value'";
                        if (!$is_mssql) {
                            $where_clause = " WHERE `$primary_key` > '$last_id_value'";
                        }
                        
                        // Sadece yeni kayıtların sayısını hesapla
                        try {
                            if ($is_mssql) {
                                $newCountSql = "SELECT COUNT(*) AS total FROM [$table]$where_clause";
                            } else {
                                $newCountSql = "SELECT COUNT(*) AS total FROM `$table`$where_clause";
                            }
                            $newCountStmt = $sourceDb->query($newCountSql);
                            $totalRecords = (int)$newCountStmt->fetchColumn();
                            
                            if ($totalRecords == 0) {
                                echo "<script>addLog('Tablo " . $table . ": Aktarılacak yeni kayıt bulunmadı.', 'success');</script>";
                                echo "<script>updateTableProgress('" . $table . "', 0, 0, 'success');</script>";
                                echo "<script>updateOverallProgress(" . $currentTable . ", " . $totalTables . ");</script>";
                                flush();
                                continue; // Sonraki tabloya geç
                            }
                            
                            echo "<script>addLog('Tablo " . $table . ": Toplam " . $totalRecords . " yeni kayıt aktarılacak.');</script>";
                            echo "<script>addTableProgress('" . $table . "', " . $totalRecords . ");</script>";
                            flush();
                        } catch (PDOException $e) {
                            echo "<script>addLog('Tablo " . $table . ": Yeni kayıt sayısı hesaplanamadı - " . addslashes($e->getMessage()) . "', 'error');</script>";
                            flush();
                        }
                    }
                }
            } catch (PDOException $e) {
                echo "<script>addLog('Son kayıt kontrolü yapılamadı: " . addslashes($e->getMessage()) . "', 'error');</script>";
                flush();
            }
        }
        
        // Veri aktarımı değişkenlerini başlat
        $inserted = 0;
        $errors = [];
        $batch_size = 50; // Her seferde işlenecek satır sayısı
        
        // İşlem başlat
        $db->beginTransaction();
        
        try {
            echo "<script>addLog('Tablo " . $table . " veri aktarımı başlatılıyor...', 'pending');</script>";
            flush();
            
            if ($is_mssql) {
                // MSSQL için veri aktarımı
                // SQL Server için büyük tablolarda sayfalama yaparak okuma
                $offset = 0;
                $fetch_size = 200; // Her seferde çekilecek satır sayısı
                $hasMoreRows = true;
                
                // MSSQL için dinamik sorgu oluştur
                $source_column_names_arr = [];
                foreach ($filtered_source_columns as $col) {
                    $source_column_names_arr[] = "[$col]";
                }
                $source_column_names = implode(', ', $source_column_names_arr);
                
                while ($hasMoreRows) {
                    // SQL Server 2012+ için OFFSET-FETCH kullanımı
                    $pagingQuery = "SELECT $source_column_names FROM [$table]$where_clause ORDER BY (SELECT NULL) OFFSET $offset ROWS FETCH NEXT $fetch_size ROWS ONLY";
                    $stmt = $sourceDb->prepare($pagingQuery);
                    $stmt->execute();
                    
                    $rowCount = 0;
                    $batchInserted = 0;
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $rowCount++;
                        try {
                            $values = [];
                            foreach ($filtered_source_columns as $column) {
                                $values[] = $row[$column] ?? null;
                            }
                            
                            $insert_stmt->execute($values);
                            $inserted++;
                            $batchInserted++;
                            
                            // Her 50 satırda bir transaction'ı tamamla ve ilerleme göster
                            if ($inserted % $batch_size === 0) {
                                $db->commit();
                                $db->beginTransaction();
                                
                                // İlerleme durumunu güncelle
                                echo "<script>updateTableProgress('" . $table . "', " . $inserted . ", " . $totalRecords . ");</script>";
                                flush();
                            }
                        } catch (PDOException $e) {
                            $errors[] = [
                                'message' => $e->getMessage(),
                                'row_sample' => substr(json_encode(array_slice($row, 0, 3)), 0, 100) . '...'
                            ];
                            
                            echo "<script>addLog('Kayıt ekleme hatası: " . addslashes($e->getMessage()) . "', 'error');</script>";
                            flush();
                        }
                    }
                    
                    // Tüm sayfayı işledik, sonraki sayfaya geç veya döngüden çık
                    if ($rowCount < $fetch_size) {
                        $hasMoreRows = false; // Daha fazla satır yok
                    } else {
                        $offset += $fetch_size; // Sonraki sayfa için offset'i güncelle
                        
                        // İşlem durumunu güncelle
                        echo "<script>updateTableProgress('" . $table . "', " . $inserted . ", " . $totalRecords . ");</script>";
                        echo "<script>addLog('Sayfalama: Şu ana kadar " . $inserted . " kayıt aktarıldı...');</script>";
                        flush();
                    }
                }
            } else {
                // MySQL için veri aktarımı
                $source_column_names = '`' . implode('`, `', $filtered_source_columns) . '`';
                $query = "SELECT $source_column_names FROM `$table`";
                
                // Eğer where koşulu varsa ekle
                if (!empty($where_clause)) {
                    $query .= $where_clause;
                }
                
                $stmt = $sourceDb->prepare($query);
                
                // Sorguyu yürüt
                $stmt->execute();
                
                // Sorgu sonuçlarını işle
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    try {
                        $values = [];
                        foreach ($filtered_source_columns as $column) {
                            $values[] = $row[$column] ?? null;
                        }
                        
                        $insert_stmt->execute($values);
                        $inserted++;
                        
                        // Her 50 satırda bir transaction'ı tamamla ve ilerleme göster
                        if ($inserted % $batch_size === 0) {
                            $db->commit();
                            $db->beginTransaction();
                            
                            // İlerleme durumunu güncelle
                            echo "<script>updateTableProgress('" . $table . "', " . $inserted . ", " . $totalRecords . ");</script>";
                            flush();
                        }
                    } catch (PDOException $e) {
                        $errors[] = [
                            'message' => $e->getMessage(),
                            'row_sample' => substr(json_encode(array_slice($row, 0, 3)), 0, 100) . '...'
                        ];
                        
                        echo "<script>addLog('Kayıt ekleme hatası: " . addslashes($e->getMessage()) . "', 'error');</script>";
                        flush();
                    }
                }
            }
            
            // Son transaction'ı tamamla
            if ($db->inTransaction()) {
                $db->commit();
            }
            
            $results[$table] = [
                'status' => 'success',
                'message' => "Veri aktarımı tamamlandı: $inserted satır aktarıldı.",
                'inserted' => $inserted,
                'errors' => $errors
            ];
            
            echo "<script>addLog('Tablo " . $table . ": Veri aktarımı tamamlandı. Toplam " . $inserted . " kayıt aktarıldı.', 'success');</script>";
            echo "<script>updateTableProgress('" . $table . "', " . $inserted . ", " . $totalRecords . ", 'success');</script>";
            echo "<script>updateOverallProgress(" . $currentTable . ", " . $totalTables . ");</script>";
            flush();
            
            // Aktarım tarihini kaydet
            try {
                // tablo_aktarim_kayitlari tablosunun varlığını kontrol et
                $checkTableSql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'tablo_aktarim_kayitlari'";
                $tableExists = $db->query($checkTableSql)->fetchColumn() > 0;
                
                // Tablo yoksa oluştur
                if (!$tableExists) {
                    $createTableSql = "CREATE TABLE `tablo_aktarim_kayitlari` (
                        `id` int(11) NOT NULL AUTO_INCREMENT,
                        `kaynak_tablo` varchar(100) NOT NULL,
                        `hedef_tablo` varchar(100) NOT NULL,
                        `aktarilan_kayit_sayisi` int(11) NOT NULL,
                        `aktarim_tarihi` datetime NOT NULL,
                        `aktaran_kullanici` varchar(50) NOT NULL,
                        PRIMARY KEY (`id`),
                        KEY `kaynak_tablo` (`kaynak_tablo`),
                        KEY `hedef_tablo` (`hedef_tablo`)
                    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;";
                    
                    $db->exec($createTableSql);
                }
                
                // Aktarım kaydını ekle
                $insertSql = "INSERT INTO `tablo_aktarim_kayitlari` 
                    (`kaynak_tablo`, `hedef_tablo`, `aktarilan_kayit_sayisi`, `aktarim_tarihi`, `aktaran_kullanici`) 
                    VALUES (?, ?, ?, NOW(), ?)";
                
                $stmt = $db->prepare($insertSql);
                $stmt->execute([
                    $table,
                    $target_table,
                    $inserted,
                    isset($_SESSION['user_name']) ? $_SESSION['user_name'] : 'Bilinmeyen'
                ]);
                
                echo "<script>addLog('Aktarım bilgileri kaydedildi.', 'success');</script>";
                flush();
            } catch (PDOException $e) {
                echo "<script>addLog('Aktarım bilgileri kaydedilemedi: " . addslashes($e->getMessage()) . "', 'error');</script>";
                flush();
            }
            
        } catch (PDOException $e) {
            // Hatada işlemi geri al
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            $results[$table] = [
                'status' => 'error',
                'message' => 'Veri aktarım hatası: ' . $e->getMessage(),
                'inserted' => $inserted,
                'errors' => [$e->getMessage()]
            ];
            
            echo "<script>addLog('Tablo " . $table . ": Veri aktarım genel hatası: " . addslashes($e->getMessage()) . "', 'error');</script>";
            echo "<script>updateTableProgress('" . $table . "', " . $inserted . ", " . $totalRecords . ", 'error');</script>";
            echo "<script>updateOverallProgress(" . $currentTable . ", " . $totalTables . ");</script>";
            flush();
        }
    }
    
    // Tüm işlemler tamamlandı
    echo "<script>
        addLog('Tüm işlemler tamamlandı. " . $totalTables . " tablo işlendi.', 'success');
        document.getElementById('result-actions').style.display = 'block';
    </script>";
    flush();
    
    exit; // Sayfayı sonlandır
    
} catch (PDOException $e) {
    echo "<script>
        addLog('Veritabanı bağlantı hatası: " . addslashes($e->getMessage()) . "', 'error');
        document.getElementById('result-actions').style.display = 'block';
    </script>";
    flush();
    exit;
}
?> 