<?php
/**
 * ERP Sistem - Veri Aktarımı İşlemi
 * 
 * Bu dosya seçilen tabloların verilerini hedef tablolara aktarmak için kullanılır.
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

// Veritabanı bağlantı bilgileri kontrolü
if (!isset($_SESSION['source_db'])) {
    $_SESSION['error_message'] = "Veritabanı bağlantı bilgisi bulunamadı. Lütfen önce bağlantı kurun.";
    header('Location: index.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// POST verilerini kontrol et
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['mapping']) || empty($_POST['mapping'])) {
    $_SESSION['error_message'] = "Veri eşleştirmesi bulunamadı. Lütfen tekrar deneyin.";
    header('Location: tablo_secimi.php');
    exit;
}

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
    
    // Her bir tablo için veri aktarımı yap
    foreach ($mapping as $table => $config) {
        if (!isset($config['source_columns']) || !isset($config['target_columns']) || !isset($config['target_table'])) {
            $results[$table] = [
                'status' => 'error',
                'message' => 'Eksik yapılandırma bilgileri',
                'inserted' => 0,
                'errors' => []
            ];
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
            continue;
        }
        
        $exists = isset($config['exists']) && $config['exists'] == '1';
        $truncate = isset($config['truncate']) && $config['truncate'] == '1';
        
        // Sadece aktarılacak alanları filtrele (boş olmayanlar)
        $filtered_source_columns = [];
        $filtered_target_columns = [];
        
        for ($i = 0; $i < count($source_columns); $i++) {
            if (!empty($target_columns[$i])) {
                $target_col = $target_columns[$i];
                
                // Otomatik oluşturulan alanlar için işlem yap
                if (strpos($target_col, 'AUTO:') === 0) {
                    $target_col = substr($target_col, 5);  // "AUTO:" kısmını çıkar
                }
                
                $filtered_source_columns[] = $source_columns[$i];
                $filtered_target_columns[] = $target_col;
            }
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
                } else {
                    // Tablo yok, oluştur
                    // Tablo adının güvenliğini kontrol et
                    if (!preg_match('/^[a-zA-Z0-9_]+$/', $target_table)) {
                        throw new PDOException("Geçersiz tablo adı formatı: " . $target_table);
                    }
                    
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
                }
            } catch (PDOException $e) {
                $results[$table] = [
                    'status' => 'error',
                    'message' => 'Tablo oluşturma hatası: ' . $e->getMessage(),
                    'sql' => isset($create_sql) ? $create_sql : 'SQL yok',
                    'inserted' => 0,
                    'errors' => []
                ];
                continue;
            }
        }
        
        // Mevcut tabloyu temizle (eğer isteniyorsa)
        if ($exists && $truncate) {
            try {
                $db->exec("TRUNCATE TABLE `$target_table`");
            } catch (PDOException $e) {
                // Truncate başarısız olursa, DELETE kullan
                try {
                    $db->exec("DELETE FROM `$target_table`");
                } catch (PDOException $e2) {
                    $results[$table] = [
                        'status' => 'error',
                        'message' => 'Tablo temizleme hatası: ' . $e2->getMessage(),
                        'inserted' => 0,
                        'errors' => []
                    ];
                    continue;
                }
            }
        }
        
        // Hedef tablo sütunlarını ve yer tutucuları hazırla
        $target_column_names = '`' . implode('`, `', $filtered_target_columns) . '`';
        $placeholders = rtrim(str_repeat('?, ', count($filtered_target_columns)), ', ');
        $insert_sql = "INSERT INTO `$target_table` ($target_column_names) VALUES ($placeholders)";
        $insert_stmt = $db->prepare($insert_sql);
        
        // Veri aktarımı değişkenlerini başlat
        $inserted = 0;
        $errors = [];
        $batch_size = 100; // Her seferde işlenecek satır sayısı
        $memory_limit = 419430400; // 400 MB olarak belirle (PHP varsayılan limit 512MB)
        
        // İşlem başlat
        $db->beginTransaction();
        
        try {
            if ($is_mssql) {
                $source_column_names = implode(', ', $filtered_source_columns); // MSSQL için isimlendirme
                
                // SQL Server için büyük tablolarda sayfalama yaparak okuma
                $offset = 0;
                $fetch_size = 500; // Her seferde çekilecek satır sayısı
                $hasMoreRows = true;
                
                while ($hasMoreRows) {
                    $pagingQuery = "SELECT $source_column_names FROM [$table] ORDER BY (SELECT NULL) OFFSET $offset ROWS FETCH NEXT $fetch_size ROWS ONLY";
                    $stmt = $sourceDb->prepare($pagingQuery);
                    $stmt->execute();
                    
                    $rowCount = 0;
                    
                    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                        $rowCount++;
                        try {
                            $values = [];
                            foreach ($filtered_source_columns as $index => $column) {
                                $values[] = $row[$column] ?? null;
                            }
                            
                            $insert_stmt->execute($values);
                            $inserted++;
                            
                            // Her 100 satırda bir transaction'ı tamamla ve bellek kontrolü yap
                            if ($inserted % $batch_size === 0) {
                                $db->commit();
                                $db->beginTransaction();
                                
                                // Bellek kullanımını kontrol et ve gerekirse temizlik yap
                                if (memory_get_usage(true) > $memory_limit) {
                                    gc_collect_cycles(); // Bellek temizliği yap
                                }
                            }
                        } catch (PDOException $e) {
                            $errors[] = [
                                'message' => $e->getMessage(),
                                'row_sample' => substr(json_encode(array_slice($row, 0, 3)), 0, 100) . '...'
                            ];
                        }
                    }
                    
                    // Tüm sayfayı işledik, sonraki sayfaya geç veya döngüden çık
                    if ($rowCount < $fetch_size) {
                        $hasMoreRows = false; // Daha fazla satır yok
                    } else {
                        $offset += $fetch_size; // Sonraki sayfa için offset'i güncelle
                    }
                }
            } else {
                // MySQL için normal sorgu çalıştır
                $source_column_names = '`' . implode('`, `', $filtered_source_columns) . '`';
                $stmt = $sourceDb->prepare("SELECT $source_column_names FROM `$table`");
                
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
                        
                        // Her 100 satırda bir transaction'ı tamamla ve bellek kontrolü yap
                        if ($inserted % $batch_size === 0) {
                            $db->commit();
                            $db->beginTransaction();
                            
                            // Bellek kullanımını kontrol et ve gerekirse temizlik yap
                            if (memory_get_usage(true) > $memory_limit) {
                                gc_collect_cycles(); // Bellek temizliği yap
                            }
                        }
                    } catch (PDOException $e) {
                        $errors[] = [
                            'message' => $e->getMessage(),
                            'row_sample' => substr(json_encode(array_slice($row, 0, 3)), 0, 100) . '...'
                        ];
                    }
                }
            }
            
            // Son transaction'ı tamamla
            $db->commit();
            
            $results[$table] = [
                'status' => 'success',
                'message' => "Veri aktarımı tamamlandı: $inserted satır aktarıldı.",
                'inserted' => $inserted,
                'errors' => $errors
            ];
            
        } catch (PDOException $e) {
            // Hatada işlemi geri al
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            
            $results[$table] = [
                'status' => 'error',
                'message' => 'Veri aktarım hatası: ' . $e->getMessage(),
                'inserted' => 0,
                'errors' => []
            ];
        }
    }
    
    // Üst kısmı dahil et
    include_once '../../includes/header.php';
    ?>
    
    <!-- Sayfa Başlığı -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Veri Aktarım Sonuçları</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-home"></i> Ana Sayfa
                </a>
            </div>
        </div>
    </div>
    
    <!-- Sonuçlar -->
    <div class="row">
        <div class="col-md-12">
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Aktarım Özeti</h6>
                </div>
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-bordered">
                            <thead>
                                <tr>
                                    <th>Tablo</th>
                                    <th>Hedef Tablo</th>
                                    <th>Durum</th>
                                    <th>Aktarılan Satır</th>
                                    <th>Hata</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($results as $table => $result): ?>
                                <tr class="<?php echo $result['status'] == 'success' ? 'table-success' : 'table-danger'; ?>">
                                    <td><?php echo htmlspecialchars($table); ?></td>
                                    <td><?php echo isset($mapping[$table]['target_table']) ? htmlspecialchars($mapping[$table]['target_table']) : '<span class="text-danger">Hedef tablo belirtilmemiş</span>'; ?></td>
                                    <td>
                                        <?php if ($result['status'] == 'success'): ?>
                                            <span class="badge bg-success">Başarılı</span>
                                        <?php else: ?>
                                            <span class="badge bg-danger">Hata</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo number_format($result['inserted'], 0, ',', '.'); ?></td>
                                    <td>
                                        <?php if ($result['status'] == 'error'): ?>
                                            <?php echo htmlspecialchars($result['message']); ?>
                                        <?php elseif (!empty($result['errors'])): ?>
                                            <?php echo count($result['errors']); ?> satırda hata
                                            <button type="button" class="btn btn-sm btn-outline-danger view-errors" data-table="<?php echo htmlspecialchars($table); ?>">
                                                Detaylar
                                            </button>
                                        <?php else: ?>
                                            -
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Hatalar için Modal -->
    <div class="modal fade" id="errorsModal" tabindex="-1" aria-labelledby="errorsModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="errorsModalLabel">Hata Detayları</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="errorDetails"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Hata detayları görüntüleme
        const errorButtons = document.querySelectorAll('.view-errors');
        const errorModal = new bootstrap.Modal(document.getElementById('errorsModal'));
        const errorDetails = document.getElementById('errorDetails');
        
        errorButtons.forEach(button => {
            button.addEventListener('click', function() {
                const table = this.getAttribute('data-table');
                let errorHtml = '<div class="alert alert-danger">';
                
                <?php foreach ($results as $table => $result): ?>
                if (table === '<?php echo $table; ?>' && <?php echo !empty($result['errors']) ? 'true' : 'false'; ?>) {
                    errorHtml += '<h5><?php echo htmlspecialchars($table); ?> Tablosundaki Hatalar:</h5>';
                    errorHtml += '<ul>';
                    
                    <?php foreach ($result['errors'] as $error): ?>
                    errorHtml += '<li><strong>Hata:</strong> <?php echo htmlspecialchars($error['message']); ?></li>';
                    <?php endforeach; ?>
                    
                    errorHtml += '</ul>';
                }
                <?php endforeach; ?>
                
                errorHtml += '</div>';
                errorDetails.innerHTML = errorHtml;
                errorModal.show();
            });
        });
    });
    </script>
    
    <?php
    // Alt kısmı dahil et
    include_once '../../includes/footer.php';
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası: " . $e->getMessage();
    header('Location: tablo_secimi.php');
    exit;
}
?> 