<?php
/**
 * ERP Sistem - Tablo Seçimi Sayfası
 * 
 * Bu dosya kaynak veritabanından alınacak tabloları seçmek için kullanılır.
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

// Sayfa başlığı
$pageTitle = "Tablo Seçimi";

// Veritabanı bağlantı bilgilerini al
$source_db = $_SESSION['source_db'];
$is_mssql = $source_db['is_mssql'];

// Öncelikli tablolar - bunlar her zaman listenin başında görünecek
$priority_tables = ['STOK', 'CARI', 'STK_FIS_HAR', 'FATURA', 'SIPARIS', 'IRSALIYE', 'URUN', 'MUSTERI', 'TEDARIKCI'];

// Filtre değerini al (arama/filtreleme için)
$filter = isset($_GET['filter']) ? clean($_GET['filter']) : '';
$show_only_priority = isset($_GET['priority']) && $_GET['priority'] == '1';
$hide_empty_tables = isset($_GET['hide_empty']) && $_GET['hide_empty'] == '1';

// Kaynak veritabanına bağlan
try {
    if ($is_mssql) {
        // MSSQL bağlantısı (PDO kullanarak)
        $dsn = "sqlsrv:Server=" . $source_db['host'] . ";Database=" . $source_db['name'];
        $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass']);
        $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        try {
            // Farklı sorgu yöntemleri deneyelim
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
            
            // Yöntem 3: sp_tables stored procedure (tablolar hala bulunamadıysa)
            if (count($tables) === 0) {
                try {
                    $stmt = $sourceDb->query("EXEC sp_tables @table_type = \"'TABLE'\"");
                    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    foreach ($result as $row) {
                        if (isset($row['TABLE_NAME'])) {
                            $tables[] = $row['TABLE_NAME'];
                        }
                    }
                } catch (PDOException $e) {
                    // Hata olursa son yöntemi deneyelim
                    $tables = [];
                }
            }
            
            // Yöntem 4: Tüm kullanıcı tablolarını almanın başka bir yolu
            if (count($tables) === 0) {
                try {
                    $stmt = $sourceDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_CATALOG = DB_NAME() AND TABLE_TYPE = 'BASE TABLE'");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (PDOException $e) {
                    // Hata devam ederse uyarı ekle
                    $_SESSION['warning_message'] = "Tabloları almakta sorun yaşandı: " . $e->getMessage();
                }
            }
            
            // Sonuçta bir tablo listesi elde edemediyse son bir yöntem deneyelim
            if (count($tables) === 0) {
                try {
                    // Veritabanındaki tüm objeleri al ve filtrele
                    $stmt = $sourceDb->query("SELECT name FROM sysobjects WHERE xtype = 'U' ORDER BY name");
                    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                } catch (PDOException $e) {
                    // Uyarı ekle
                    $_SESSION['warning_message'] = "MSSQL tablolar alınamadı. MSSQL sürümünüz ile uyumluluk sorunu olabilir.";
                }
            }
            
            // Hala tablo yoksa hata mesajı ekle
            if (count($tables) === 0) {
                $_SESSION['warning_message'] = "MSSQL veritabanında tablo bulunamadı veya tablo listesine erişim izni yok.";
            }
            
        } catch (PDOException $e) {
            $_SESSION['error_message'] = "MSSQL Sorgu hatası: " . $e->getMessage();
            header('Location: index.php');
            exit;
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
    
    // Filtreleme ve sıralama yap
    $filtered_tables = [];
    $other_tables = [];
    $table_counts = []; // Tablo kayıt sayılarını saklayacak dizi
    
    // Tablolari filtrele ve grupla
    foreach ($tables as $table) {
        // Kayıt sayısını hesapla (0 kayıtlı tabloları filtrelemek için)
        $count = 0;
        $count_error = false;
        
        if ($hide_empty_tables) {
            try {
                if ($is_mssql) {
                    try {
                        // Sorguyu çift tırnak içinde korumalıyız
                        $tableName = str_replace("'", "''", $table);
                        $countSql = "SELECT COUNT(*) AS total FROM [$tableName]";
                        $countStmt = $sourceDb->prepare($countSql);
                        $countStmt->execute();
                        $count = $countStmt->fetchColumn();
                    } catch (PDOException $e) {
                        try {
                            // Alternatif sorgu yöntemi
                            $tableName = str_replace("'", "''", $table);
                            $countSql = "SELECT COUNT_BIG(*) AS total FROM [$tableName]";
                            $countStmt = $sourceDb->prepare($countSql);
                            $countStmt->execute();
                            $count = $countStmt->fetchColumn();
                        } catch (PDOException $e2) {
                            $count_error = true;
                        }
                    }
                } else {
                    // MySQL için güvenli sorgu
                    $countStmt = $sourceDb->prepare("SELECT COUNT(*) FROM `" . $table . "`");
                    $countStmt->execute();
                    $count = $countStmt->fetchColumn();
                }
            } catch (PDOException $e) {
                $count_error = true;
            }
            
            // Kayıt sayısı 0 ise ve boş tabloları gizle seçili ise, bu tabloyu atla
            if ($count == 0 && !$count_error) {
                continue;
            }
        }
        
        // Tablo sayısını sakla
        $table_counts[$table] = [
            'count' => $count,
            'error' => $count_error
        ];
        
        // Filtreleme yap (eğer filtre varsa)
        if (!empty($filter) && stripos($table, $filter) === false) {
            continue;
        }
        
        // Öncelikli tablolar ayrı bir diziye ekle
        if (in_array(strtoupper($table), $priority_tables)) {
            $filtered_tables[] = $table;
        } else {
            // Öncelikli olmayanları başka bir diziye ekle
            if (!$show_only_priority) {
                $other_tables[] = $table;
            }
        }
    }
    
    // Öncelikli tabloları alfabetik olarak sırala
    sort($filtered_tables);
    
    // Diğer tabloları ekle (eğer sadece öncelikli gösterilmiyorsa)
    if (!$show_only_priority) {
        sort($other_tables);
        $filtered_tables = array_merge($filtered_tables, $other_tables);
    }
    
    // Hedef veritabanındaki tabloları al (mevcut ERP veritabanı)
    $stmt = $db->query("SHOW TABLES");
    $target_tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası: " . $e->getMessage();
    header('Location: index.php');
    exit;
}

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

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
    <h1 class="h2">Tablo Seçimi</h1>
    <div class="btn-toolbar mb-2 mb-md-0">
        <div class="btn-group me-2">
            <a href="index.php" class="btn btn-sm btn-outline-secondary">
                <i class="fas fa-arrow-left"></i> Geri Dön
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

<!-- Veritabanı Bilgileri -->
<div class="card shadow mb-4">
    <div class="card-header py-3">
        <h6 class="m-0 font-weight-bold text-primary">Kaynak Veritabanı Bilgileri</h6>
    </div>
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <p><strong>Sunucu:</strong> <?php echo htmlspecialchars($source_db['host']); ?></p>
                <p><strong>Veritabanı:</strong> <?php echo htmlspecialchars($source_db['name']); ?></p>
            </div>
            <div class="col-md-6">
                <p><strong>Kullanıcı:</strong> <?php echo htmlspecialchars($source_db['user']); ?></p>
                <p><strong>Toplam Tablo Sayısı:</strong> <?php echo count($tables); ?></p>
                <p><strong>Filtrelenmiş Tablo Sayısı:</strong> <?php echo count($filtered_tables); ?></p>
                <p><strong>Veritabanı Türü:</strong> <?php echo $is_mssql ? 'Microsoft SQL Server' : 'MySQL'; ?></p>
            </div>
        </div>
    </div>
</div>

<!-- Tablo Seçimi -->
<div class="card shadow mb-4">
    <div class="card-header py-3 d-flex justify-content-between align-items-center">
        <h6 class="m-0 font-weight-bold text-primary">Aktarılacak Tabloları Seçin</h6>
        <div>
            <a href="tablo_secimi.php?priority=1<?php echo $hide_empty_tables ? '&hide_empty=1' : ''; ?>" class="btn btn-sm <?php echo $show_only_priority ? 'btn-primary' : 'btn-outline-primary'; ?>">
                <i class="fas fa-filter"></i> Sadece Önemli Tablolar
            </a>
            <a href="tablo_secimi.php<?php echo $hide_empty_tables ? '?hide_empty=1' : ''; ?>" class="btn btn-sm <?php echo !$show_only_priority ? 'btn-primary' : 'btn-outline-primary'; ?>">
                <i class="fas fa-list"></i> Tüm Tablolar
            </a>
            <a href="tablo_secimi.php?<?php echo $show_only_priority ? 'priority=1&' : ''; ?>hide_empty=<?php echo $hide_empty_tables ? '0' : '1'; ?>" class="btn btn-sm <?php echo $hide_empty_tables ? 'btn-warning' : 'btn-outline-warning'; ?>">
                <i class="fas fa-ban"></i> <?php echo $hide_empty_tables ? 'Boş Tabloları Göster' : 'Boş Tabloları Gizle'; ?>
            </a>
        </div>
    </div>
    <div class="card-body">
        <form action="eslesme_ayarla.php" method="post" id="tableSelectionForm">
            <div class="row mb-3">
                <div class="col-md-8">
                    <input type="text" class="form-control" id="searchTable" placeholder="Tablo adı ile ara..." onkeyup="filterTables()">
                </div>
                <div class="col-md-4">
                    <div class="d-flex gap-2">
                        <button type="button" class="btn btn-secondary" onclick="selectAllTables()">Tümünü Seç</button>
                        <button type="button" class="btn btn-secondary" onclick="deselectAllTables()">Tümünü Kaldır</button>
                        <button type="button" class="btn btn-info" onclick="selectCommonTables()">Önemli Tabloları Seç</button>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <i class="fas fa-info-circle"></i> Performans nedeniyle sadece önemli tablolarda kayıt sayısı otomatik hesaplanmaktadır. Diğer tablolarda kayıt sayısını görmek için "Hesapla" butonuna tıklayabilirsiniz.
            </div>
            
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <strong>Uyarı:</strong> Çok fazla tablo seçmek performans sorunlarına ve sistem hatalarına neden olabilir. Lütfen bir seferde en fazla 10-15 tablo seçin. Çok fazla alan içeren büyük tablolar için daha da az sayıda tablo seçmeniz önerilir.
            </div>
            
            <div class="table-responsive">
                <table class="table table-bordered" id="tablesTable">
                    <thead>
                        <tr>
                            <th width="5%">Seç</th>
                            <th>Tablo Adı</th>
                            <th width="15%">Kayıt Sayısı</th>
                            <th width="15%">Hedef Tablo</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $common_table_names = ['STOK', 'CARI', 'STK_FIS_HAR', 'FATURA', 'SIPARIS', 'IRSALIYE', 'URUN', 'MUSTERI', 'TEDARIKCI'];
                        
                        foreach ($filtered_tables as $table): 
                            $is_common_table = in_array(strtoupper($table), $common_table_names);
                        ?>
                        <tr class="<?php echo $is_common_table ? 'table-info' : ''; ?>" data-is-common="<?php echo $is_common_table ? '1' : '0'; ?>">
                            <td>
                                <div class="form-check">
                                    <input class="form-check-input" type="checkbox" name="source_tables[]" value="<?php echo htmlspecialchars($table); ?>" id="table_<?php echo htmlspecialchars($table); ?>" <?php echo $is_common_table ? 'checked' : ''; ?>>
                                </div>
                            </td>
                            <td><?php echo htmlspecialchars($table); ?></td>
                            <td>
                                <?php
                                // Halihazırda hesaplanmış tablo sayılarını kullan
                                if (isset($table_counts[$table])) {
                                    if ($table_counts[$table]['error']) {
                                        echo '<span class="text-warning">Hesaplanamadı</span>';
                                    } else {
                                        echo number_format($table_counts[$table]['count'], 0, ',', '.');
                                    }
                                } else {
                                    // Önemli tablolarda hemen hesapla, diğerlerinde hesaplama butonu göster
                                    if ($is_common_table) {
                                        try {
                                            if ($is_mssql) {
                                                // SQL Server'da farklı tablolarda farklı sorgu yöntemleri deneyeceğiz
                                                try {
                                                    // Sorguyu çift tırnak içinde korumalıyız
                                                    $tableName = str_replace("'", "''", $table);
                                                    $countSql = "SELECT COUNT(*) AS total FROM [$tableName]";
                                                    $countStmt = $sourceDb->prepare($countSql);
                                                    $countStmt->execute();
                                                    $count = $countStmt->fetchColumn();
                                                    echo number_format($count, 0, ',', '.');
                                                } catch (PDOException $e) {
                                                    // Alternatif sorgu yöntemi
                                                    try {
                                                        $tableName = str_replace("'", "''", $table);
                                                        $countSql = "SELECT COUNT_BIG(*) AS total FROM [$tableName]";
                                                        $countStmt = $sourceDb->prepare($countSql);
                                                        $countStmt->execute();
                                                        $count = $countStmt->fetchColumn();
                                                        echo number_format($count, 0, ',', '.');
                                                    } catch (PDOException $e2) {
                                                        echo '<span class="text-warning" title="' . htmlspecialchars($e2->getMessage()) . '">Hesaplanamadı</span>';
                                                    }
                                                }
                                            } else {
                                                // MySQL için güvenli sorgu
                                                $countStmt = $sourceDb->prepare("SELECT COUNT(*) FROM `" . $table . "`");
                                                $countStmt->execute();
                                                $count = $countStmt->fetchColumn();
                                                echo number_format($count, 0, ',', '.');
                                            }
                                        } catch (PDOException $e) {
                                            echo '<span class="text-danger" title="' . htmlspecialchars($e->getMessage()) . '">Hata</span>';
                                        }
                                    } else {
                                        // Önemli olmayan tablolarda hesaplama butonu göster
                                        echo '<button type="button" class="btn btn-sm btn-outline-secondary calculate-count" data-table="' . htmlspecialchars($table) . '">Hesapla</button>';
                                    }
                                }
                                ?>
                            </td>
                            <td>
                                <select class="form-select form-select-sm" name="target_tables[<?php echo htmlspecialchars($table); ?>]">
                                    <option value="">Kaynak Tablo Adını Kullan</option>
                                    <?php foreach ($target_tables as $t_table): ?>
                                    <option value="<?php echo htmlspecialchars($t_table); ?>" <?php echo (strtolower($table) == strtolower($t_table)) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($t_table); ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            
            <div class="mt-3">
                <button type="submit" class="btn btn-primary" onclick="return validateTableSelection()">
                    <i class="fas fa-arrow-right"></i> İleri: Alan Eşleştirme
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Tablo filtreleme ve seçim JavaScript kodu -->
<script>
function filterTables() {
    var input, filter, table, tr, td, i, txtValue;
    input = document.getElementById("searchTable");
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

function selectAllTables() {
    var checkboxes = document.querySelectorAll('input[name="source_tables[]"]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = true;
    }
}

function deselectAllTables() {
    var checkboxes = document.querySelectorAll('input[name="source_tables[]"]');
    for (var i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = false;
    }
}

function selectCommonTables() {
    var rows = document.querySelectorAll('tr[data-is-common="1"]');
    for (var i = 0; i < rows.length; i++) {
        var checkbox = rows[i].querySelector('input[type="checkbox"]');
        if (checkbox) {
            checkbox.checked = true;
        }
    }
}

// Sayfa yüklendiğinde önemli tabloları işaretle
document.addEventListener('DOMContentLoaded', function() {
    selectCommonTables();
    
    // Hesapla butonlarına tıklama olayı ekle
    document.querySelectorAll('.calculate-count').forEach(function(button) {
        button.addEventListener('click', function() {
            const table = this.getAttribute('data-table');
            const button = this;
            const cell = button.parentElement;
            
            // Yükleniyor göster
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Hesaplanıyor...';
            
            // AJAX isteği gönder
            fetch('table_count_ajax.php?table=' + encodeURIComponent(table))
                .then(response => response.text())
                .then(data => {
                    // Sonucu göster
                    cell.innerHTML = data;
                })
                .catch(error => {
                    // Hata durumunda
                    cell.innerHTML = '<span class="text-danger">Hata: ' + error.message + '</span>';
                });
        });
    });
});

// Form gönderilmeden önce seçilen tablo sayısını kontrol et
function validateTableSelection() {
    const selectedTables = document.querySelectorAll('input[name="source_tables[]"]:checked');
    
    if (selectedTables.length === 0) {
        alert('Lütfen en az bir tablo seçin.');
        return false;
    }
    
    if (selectedTables.length > 15) {
        return confirm('DİKKAT: ' + selectedTables.length + ' tablo seçtiniz. Bu kadar çok tablo seçmek sistem kaynaklarını zorlayabilir ve hataya neden olabilir. Yine de devam etmek istiyor musunuz?\n\nDaha güvenli bir aktarım için daha az tablo seçerek birden fazla aktarım yapmanız önerilir.');
    }
    
    return true;
}
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 