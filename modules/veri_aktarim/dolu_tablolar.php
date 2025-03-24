<?php
/**
 * ERP Sistem - Dolu Tabloları Listeleme Sayfası
 * 
 * Bu dosya kaynak veritabanındaki kayıt içeren (dolu) tabloları listeler.
 */

// Oturum başlat
session_start();

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
$pageTitle = "Kaynak Veritabanı Dolu Tablolar";

// Veritabanı bağlantı bilgilerini al
$source_db = $_SESSION['source_db'];
$is_mssql = $source_db['is_mssql'];

// Hata ve başarı mesajları için diziler
$errors = [];
$success = [];
$warnings = [];

// Sayfa HTML başlangıcı
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
            
            <div class="card mb-4">
                <div class="card-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <i class="fas fa-database"></i> Kaynak Veritabanı: <strong><?php echo htmlspecialchars($source_db['name']); ?></strong>
                        </div>
                    </div>
                </div>
                <div class="card-body">
                    <?php
                    // Kaynak veritabanına bağlan
                    try {
                        if ($is_mssql) {
                            // MSSQL bağlantısı
                            $dsn = "sqlsrv:Server=" . $source_db['host'] . ";Database=" . $source_db['name'];
                            $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass']);
                            $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                            
                            // MSSQL için tabloları al - daha performanslı bir yöntem
                            $tables = [];
                            try {
                                // Bu sorgu, her tablonun row_count değerini sorgulayan daha performanslı bir MSSQL sorgusu
                                $sql = "SELECT 
                                            t.NAME AS TableName,
                                            p.rows AS RecordCount
                                        FROM 
                                            sys.tables t
                                        INNER JOIN      
                                            sys.indexes i ON t.OBJECT_ID = i.object_id
                                        INNER JOIN 
                                            sys.partitions p ON i.object_id = p.OBJECT_ID AND i.index_id = p.index_id
                                        WHERE 
                                            t.is_ms_shipped = 0 AND
                                            i.OBJECT_ID > 255 AND
                                            i.index_id <= 1
                                        GROUP BY 
                                            t.NAME, p.Rows
                                        ORDER BY 
                                            p.rows DESC, t.NAME";
                                
                                $stmt = $sourceDb->query($sql);
                                
                                // Sonuçları alıyoruz
                                $tables_with_records = [];
                                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                                    if ($row['RecordCount'] > 0) {
                                        $tables_with_records[] = [
                                            'name' => $row['TableName'],
                                            'count' => $row['RecordCount']
                                        ];
                                    }
                                }
                                
                                echo '<div class="alert alert-success">' . count($tables_with_records) . ' dolu tablo bulundu.</div>';
                                
                                if (count($tables_with_records) > 0) {
                                    echo '<div class="mb-3">
                                        <input type="text" class="form-control" id="tableSearch" placeholder="Tablo adı ile ara..." onkeyup="filterTables()">
                                    </div>';
                                    
                                    echo '<div class="table-responsive">
                                        <table class="table table-bordered table-hover" id="tablesTable">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th width="60%">Tablo Adı</th>
                                                    <th width="20%">Kayıt Sayısı</th>
                                                    <th width="15%">İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>';
                                    
                                    $i = 1;
                                    foreach ($tables_with_records as $table_info) {
                                        echo '<tr>
                                            <td>' . $i++ . '</td>
                                            <td>' . htmlspecialchars($table_info['name']) . '</td>
                                            <td class="text-end">' . number_format($table_info['count'], 0, ',', '.') . '</td>
                                            <td class="text-center">
                                                <form method="post" action="eslesme_ayarla.php" style="display: inline-block">
                                                    <input type="hidden" name="source_tables[]" value="' . htmlspecialchars($table_info['name']) . '">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-exchange-alt"></i> Aktar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>';
                                    }
                                    
                                    echo '</tbody>
                                        </table>
                                    </div>';
                                    
                                    echo '<div class="mt-3">
                                        <a href="tablo_secimi.php" class="btn btn-success">
                                            <i class="fas fa-list-check"></i> Özel Seçim İle Tablo Aktarımı
                                        </a>
                                        <a href="tablo_kopyala.php" class="btn btn-danger">
                                            <i class="fas fa-copy"></i> Tüm Tabloları Kopyala
                                        </a>
                                    </div>';
                                } else {
                                    echo '<div class="alert alert-warning">Kaynak veritabanında kayıt içeren tablo bulunamadı.</div>';
                                }
                            } catch (PDOException $e) {
                                echo '<div class="alert alert-warning">Tablo bilgileri alınamadı: ' . $e->getMessage() . '</div>';
                                
                                // Alternatif yöntem - basit bir kontrol
                                echo '<div class="alert alert-info">Alternatif yöntem deneniyor...</div>';
                                
                                $tables = [];
                                $stmt = $sourceDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
                                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                                
                                // İlk 10 tabloyu kontrol edelim
                                $limit = 10;
                                $tables_with_records = [];
                                $processed = 0;
                                
                                foreach ($tables as $table) {
                                    if ($processed >= $limit) break;
                                    
                                    try {
                                        // TOP 1 sorgusu COUNT(*)'dan daha hızlıdır
                                        $tableName = str_replace("'", "''", $table);
                                        $sql = "SELECT TOP 1 * FROM [$tableName]";
                                        $stmt = $sourceDb->prepare($sql);
                                        $stmt->execute();
                                        
                                        // Eğer kayıt varsa
                                        if ($stmt->fetch()) {
                                            $tables_with_records[] = [
                                                'name' => $table,
                                                'count' => '??' // Kesin sayı bilinmiyor
                                            ];
                                        }
                                    } catch (PDOException $e) {
                                        // Bu tabloyu atla
                                        continue;
                                    }
                                    
                                    $processed++;
                                }
                                
                                // Sonuçları göster
                                echo '<div class="alert alert-warning">Alternatif yöntemle ' . count($tables_with_records) . ' dolu tablo bulundu. Not: Bu sadece ilk ' . $limit . ' tablo içindir ve kayıt sayıları gösterilmemektedir.</div>';
                                
                                if (count($tables_with_records) > 0) {
                                    echo '<div class="table-responsive">
                                        <table class="table table-bordered table-hover">
                                            <thead class="table-light">
                                                <tr>
                                                    <th width="5%">#</th>
                                                    <th width="75%">Tablo Adı</th>
                                                    <th width="20%">İşlemler</th>
                                                </tr>
                                            </thead>
                                            <tbody>';
                                    
                                    $i = 1;
                                    foreach ($tables_with_records as $table_info) {
                                        echo '<tr>
                                            <td>' . $i++ . '</td>
                                            <td>' . htmlspecialchars($table_info['name']) . '</td>
                                            <td class="text-center">
                                                <form method="post" action="eslesme_ayarla.php" style="display: inline-block">
                                                    <input type="hidden" name="source_tables[]" value="' . htmlspecialchars($table_info['name']) . '">
                                                    <button type="submit" class="btn btn-sm btn-primary">
                                                        <i class="fas fa-exchange-alt"></i> Aktar
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>';
                                    }
                                    
                                    echo '</tbody>
                                        </table>
                                    </div>';
                                }
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
                            
                            // MySQL için daha performanslı bir sorgu
                            $sql = "SELECT 
                                        table_name, 
                                        table_rows
                                    FROM 
                                        information_schema.tables
                                    WHERE 
                                        table_schema = ?
                                        AND table_rows > 0
                                    ORDER BY 
                                        table_rows DESC";
                            
                            $stmt = $sourceDb->prepare($sql);
                            $stmt->execute([$source_db['name']]);
                            
                            $tables_with_records = [];
                            while ($row = $stmt->fetch()) {
                                $tables_with_records[] = [
                                    'name' => $row['table_name'],
                                    'count' => $row['table_rows']
                                ];
                            }
                            
                            echo '<div class="alert alert-success">' . count($tables_with_records) . ' dolu tablo bulundu.</div>';
                            
                            if (count($tables_with_records) > 0) {
                                echo '<div class="mb-3">
                                    <input type="text" class="form-control" id="tableSearch" placeholder="Tablo adı ile ara..." onkeyup="filterTables()">
                                </div>';
                                
                                echo '<div class="table-responsive">
                                    <table class="table table-bordered table-hover" id="tablesTable">
                                        <thead class="table-light">
                                            <tr>
                                                <th width="5%">#</th>
                                                <th width="60%">Tablo Adı</th>
                                                <th width="20%">Kayıt Sayısı</th>
                                                <th width="15%">İşlemler</th>
                                            </tr>
                                        </thead>
                                        <tbody>';
                                
                                $i = 1;
                                foreach ($tables_with_records as $table_info) {
                                    echo '<tr>
                                        <td>' . $i++ . '</td>
                                        <td>' . htmlspecialchars($table_info['name']) . '</td>
                                        <td class="text-end">' . number_format($table_info['count'], 0, ',', '.') . '</td>
                                        <td class="text-center">
                                            <form method="post" action="eslesme_ayarla.php" style="display: inline-block">
                                                <input type="hidden" name="source_tables[]" value="' . htmlspecialchars($table_info['name']) . '">
                                                <button type="submit" class="btn btn-sm btn-primary">
                                                    <i class="fas fa-exchange-alt"></i> Aktar
                                                </button>
                                            </form>
                                        </td>
                                    </tr>';
                                }
                                
                                echo '</tbody>
                                    </table>
                                </div>';
                                
                                echo '<div class="mt-3">
                                    <a href="tablo_secimi.php" class="btn btn-success">
                                        <i class="fas fa-list-check"></i> Özel Seçim İle Tablo Aktarımı
                                    </a>
                                    <a href="tablo_kopyala.php" class="btn btn-danger">
                                        <i class="fas fa-copy"></i> Tüm Tabloları Kopyala
                                    </a>
                                </div>';
                            } else {
                                echo '<div class="alert alert-warning">Kaynak veritabanında kayıt içeren tablo bulunamadı.</div>';
                            }
                        }
                    } catch (PDOException $e) {
                        echo '<div class="alert alert-danger">Veritabanı bağlantı hatası: ' . $e->getMessage() . '</div>';
                    }
                    ?>
                </div>
            </div>
        </main>
    </div>
</div>

<?php include '../../includes/footer.php'; ?>

<script>
function filterTables() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("tableSearch");
    filter = input.value.toUpperCase();
    table = document.getElementById("tablesTable");
    tr = table.getElementsByTagName("tr");
    
    for (i = 0; i < tr.length; i++) {
        td = tr[i].getElementsByTagName("td")[1]; // Tablo adı sütunu
        if (td) {
            txtValue = td.textContent || td.innerText;
            if (txtValue.toUpperCase().indexOf(filter) > -1) {
                tr[i].style.display = "";
            } else {
                tr[i].style.display = "none";
            }
        }
    }
}
</script> 