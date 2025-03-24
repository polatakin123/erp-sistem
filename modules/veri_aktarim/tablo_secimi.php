<?php
/**
 * ERP Sistem - Tablo Seçimi Sayfası
 * 
 * Bu dosya kaynak veritabanından alınacak tabloları seçmek için kullanılır.
 */

// Hata ayıklama kodları (gizli)
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

// Veritabanı bağlantı bilgileri kontrolü
if (!isset($_SESSION['source_db'])) {
    $_SESSION['error_message'] = "Veritabanı bağlantı bilgisi bulunamadı. Lütfen önce bağlantı kurun.";
    header('Location: index.php');
    exit;
}

// Yükleniyor sayfası göster
echo '<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tablo Bilgileri Yükleniyor...</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .loading {
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            height: 100vh;
            text-align: center;
        }
        .spinner-border {
            width: 5rem;
            height: 5rem;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="loading">
        <div class="spinner-border text-primary" role="status">
            <span class="visually-hidden">Yükleniyor...</span>
        </div>
        <h2>Tablo Bilgileri Yükleniyor</h2>
        <p>Veritabanı tabloları sorgulanıyor, lütfen bekleyin...</p>
    </div>
</body>
</html>';
// Yükleme ekranını göndermek için çıktı tamponunu temizle
flush();

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
    $filtered_tables = [];
    $other_tables = [];
    $table_counts = []; // Tablo kayıt sayılarını saklayacak dizi
    
    if ($is_mssql) {
        // MSSQL bağlantısı (PDO kullanarak)
        $dsn = "sqlsrv:Server=" . $source_db['host'] . ";Database=" . $source_db['name'];
        $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass']);
        $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        try {
            // Tabloları ve kayıt sayılarını tek sorguda al (performanslı)
            if ($hide_empty_tables) {
                // Sadece kaydı olan tabloları al - daha hızlı bir sorgu
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
                            i.index_id <= 1 AND
                            p.rows > 0
                        GROUP BY 
                            t.NAME, p.Rows
                        ORDER BY 
                            t.NAME";
                
                $stmt = $sourceDb->query($sql);
                
                // Sonuçları alıyoruz
                $tables = [];
                while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                    $tables[] = $row['TableName'];
                    // Tablo sayısını sakla
                    $table_counts[$row['TableName']] = [
                        'count' => $row['RecordCount'],
                        'error' => false
                    ];
                }
            } else {
                // Tüm tabloları al - kayıt sayısına bakma
                $stmt = $sourceDb->query("SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_TYPE = 'BASE TABLE' ORDER BY TABLE_NAME");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Her tablo için kayıt sayısı varsayılan 0 olsun
                foreach ($tables as $table) {
                    $table_counts[$table] = [
                        'count' => 0,
                        'error' => false
                    ];
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
        
        // Kayıt sayılarıyla birlikte tabloları tek sorguda al
        if ($hide_empty_tables) {
            $sql = "SELECT 
                        table_name, 
                        table_rows
                    FROM 
                        information_schema.tables
                    WHERE 
                        table_schema = ?
                        AND table_rows > 0
                    ORDER BY 
                        table_name";
            
            $stmt = $sourceDb->prepare($sql);
            $stmt->execute([$source_db['name']]);
            
            $tables = [];
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $tables[] = $row['table_name'];
                $table_counts[$row['table_name']] = [
                    'count' => $row['table_rows'],
                    'error' => false
                ];
            }
        } else {
            // Tüm tabloları al - kayıt sayısına bakma
            $stmt = $sourceDb->query("SHOW TABLES");
            $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            // Her tablo için kayıt sayısı varsayılan 0 olsun
            foreach ($tables as $table) {
                $table_counts[$table] = [
                    'count' => 0,
                    'error' => false
                ];
            }
        }
    }
    
    // Tablolari filtrele ve grupla
    foreach ($tables as $table) {
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
    
    // Daha önce aktarılan tabloların bilgilerini al
    $aktarim_kayitlari = [];
    try {
        // Tablo varlığını kontrol et
        $checkTableSql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = 'tablo_aktarim_kayitlari'";
        $tableExists = $db->query($checkTableSql)->fetchColumn() > 0;
        
        if ($tableExists) {
            // Her kaynak tablo için son aktarım bilgilerini al
            $aktarimSql = "SELECT kaynak_tablo, hedef_tablo, aktarilan_kayit_sayisi, 
                           DATE_FORMAT(aktarim_tarihi, '%d.%m.%Y %H:%i') as aktarim_tarihi,
                           aktaran_kullanici
                           FROM tablo_aktarim_kayitlari 
                           WHERE (kaynak_tablo, aktarim_tarihi) IN (
                               SELECT kaynak_tablo, MAX(aktarim_tarihi) 
                               FROM tablo_aktarim_kayitlari 
                               GROUP BY kaynak_tablo
                           )";
            
            $stmt = $db->query($aktarimSql);
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $aktarim_kayitlari[$row['kaynak_tablo']] = $row;
            }
        }
    } catch (PDOException $e) {
        // Kayıt tablosu olmayabilir, bu durumda sessiz bir şekilde devam et
    }
    
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
                <i class="fas fa-arrow-left"></i> Geri
            </a>
        </div>
    </div>
</div>

<!-- Uyarı ve Hata Mesajları -->
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
    <?php foreach ($success as $success_msg): ?>
        <div class="alert alert-success">
            <i class="fas fa-check-circle"></i> <?php echo $success_msg; ?>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<div class="alert alert-warning">
    <i class="fas fa-exclamation-triangle"></i> <strong>Uyarı:</strong> Çok fazla tablo ve alan seçildiğinde PHP'nin bellek limiti veya input değişkenleri limiti aşılabilir. Performans sorunları yaşamamak için bir defada en fazla 10-15 tablo seçmeniz önerilir.
</div>

<!-- Filtreleme Seçenekleri -->
<div class="card mb-4">
    <div class="card-header">
        <i class="fas fa-filter"></i> Filtreleme Seçenekleri
    </div>
    <div class="card-body">
        <form method="get" action="<?php echo $_SERVER['PHP_SELF']; ?>" class="row g-3">
            <div class="col-md-6">
                <label for="filter" class="form-label">Tablo Adı ile Filtrele:</label>
                <input type="text" class="form-control" id="filter" name="filter" value="<?php echo htmlspecialchars($filter); ?>" placeholder="Tablo adı yazın...">
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="priority" name="priority" value="1" <?php echo $show_only_priority ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="priority">
                        Sadece öncelikli tabloları göster
                    </label>
                </div>
            </div>
            <div class="col-md-3">
                <label class="form-label">&nbsp;</label>
                <div class="form-check mt-2">
                    <input class="form-check-input" type="checkbox" id="hide_empty" name="hide_empty" value="1" <?php echo $hide_empty_tables ? 'checked' : ''; ?>>
                    <label class="form-check-label" for="hide_empty">
                        Boş tabloları gizle
                    </label>
                </div>
            </div>
            <div class="col-12">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-search"></i> Filtrele
                </button>
                <a href="<?php echo $_SERVER['PHP_SELF']; ?>" class="btn btn-secondary">
                    <i class="fas fa-sync-alt"></i> Filtreleri Temizle
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Tablo Seçim Formu -->
<form method="post" action="eslesme_ayarla.php" id="tableSelectionForm">
    <div class="card mb-4">
        <div class="card-header d-flex justify-content-between align-items-center">
            <div><i class="fas fa-table"></i> Kaynak Tabloları</div>
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" id="selectAll">
                <label class="form-check-label" for="selectAll">Tümünü Seç/Kaldır</label>
            </div>
        </div>
        <div class="card-body">
            <?php if (count($filtered_tables) > 0): ?>
                <div class="table-responsive">
                    <table class="table table-bordered table-hover">
                        <thead class="table-light">
                            <tr>
                                <th width="5%">Seç</th>
                                <th width="30%">Kaynak Tablo</th>
                                <th width="15%">Kayıt Sayısı</th>
                                <th width="30%">Hedef Tablo</th>
                                <th width="20%">Son Aktarım</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($filtered_tables as $table): ?>
                            <tr>
                                <td class="text-center">
                                    <div class="form-check">
                                        <input class="form-check-input table-checkbox" type="checkbox" name="source_tables[]" value="<?php echo htmlspecialchars($table); ?>" id="table_<?php echo htmlspecialchars($table); ?>">
                                        <label class="form-check-label" for="table_<?php echo htmlspecialchars($table); ?>"></label>
                                    </div>
                                </td>
                                <td>
                                    <label for="table_<?php echo htmlspecialchars($table); ?>"><?php echo htmlspecialchars($table); ?></label>
                                </td>
                                <td class="text-end">
                                    <?php if (isset($table_counts[$table])): ?>
                                        <?php if ($table_counts[$table]['error']): ?>
                                            <span class="text-danger">Hata</span>
                                        <?php else: ?>
                                            <?php echo number_format($table_counts[$table]['count'], 0, ',', '.'); ?>
                                        <?php endif; ?>
                                    <?php else: ?>
                                        <span class="text-muted">-</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <select name="target_tables[<?php echo htmlspecialchars($table); ?>]" class="form-select">
                                        <option value="">Kaynak Tablo Adını Kullan</option>
                                        <?php foreach ($target_tables as $target_table): ?>
                                            <option value="<?php echo htmlspecialchars($target_table); ?>" <?php echo $target_table == $table ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($target_table); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <?php if (isset($aktarim_kayitlari[$table])): ?>
                                        <span class="badge bg-success" title="Aktaran: <?php echo htmlspecialchars($aktarim_kayitlari[$table]['aktaran_kullanici']); ?>&#10;Kayıt Sayısı: <?php echo number_format($aktarim_kayitlari[$table]['aktarilan_kayit_sayisi'], 0, ',', '.'); ?>">
                                            <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($aktarim_kayitlari[$table]['aktarim_tarihi']); ?>
                                        </span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">
                                            <i class="fas fa-times-circle"></i> Aktarılmamış
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="alert alert-info">
                    <i class="fas fa-info-circle"></i> Filtreleme kriterlerine uygun tablo bulunamadı.
                </div>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
        <button type="submit" class="btn btn-primary" id="nextButton" disabled>
            <i class="fas fa-arrow-right"></i> İleri: Alan Eşleştirme
        </button>
    </div>
</form>

<script>
// Tümünü seç/kaldır işlevi
document.getElementById('selectAll').addEventListener('change', function() {
    const checkboxes = document.getElementsByClassName('table-checkbox');
    for (let i = 0; i < checkboxes.length; i++) {
        checkboxes[i].checked = this.checked;
    }
    toggleNextButton();
});

// Checkbox değişiminde İleri butonunu etkinleştir/devre dışı bırak
const tableCheckboxes = document.getElementsByClassName('table-checkbox');
for (let i = 0; i < tableCheckboxes.length; i++) {
    tableCheckboxes[i].addEventListener('change', toggleNextButton);
}

// İleri butonunu etkinleştir/devre dışı bırak
function toggleNextButton() {
    const checkboxes = document.getElementsByClassName('table-checkbox');
    let anyChecked = false;
    
    for (let i = 0; i < checkboxes.length; i++) {
        if (checkboxes[i].checked) {
            anyChecked = true;
            break;
        }
    }
    
    document.getElementById('nextButton').disabled = !anyChecked;
}

// Sayfa yüklendiğinde de kontrol et
document.addEventListener('DOMContentLoaded', toggleNextButton);
</script>

<?php include_once '../../includes/footer.php'; ?> 