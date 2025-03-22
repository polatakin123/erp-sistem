<?php
/**
 * ERP Sistem - Tablo Önizleme Sayfası
 * 
 * Bu dosya, seçilen tablonun önizlemesini gösterir.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    echo "Yetkilendirme hatası!";
    exit;
}

// Kaynak veritabanı bilgileri kontrolü
if (!isset($_SESSION['source_db'])) {
    echo "Veritabanı bağlantısı eksik!";
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Tablo adını al
$table = isset($_GET['table']) ? clean($_GET['table']) : '';

if (empty($table)) {
    echo "Tablo adı belirtilmedi!";
    exit;
}

// Kaynak veritabanına bağlan
try {
    $source_db = $_SESSION['source_db'];
    $dsn = "mysql:host={$source_db['host']};dbname={$source_db['name']};charset={$source_db['charset']}";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass'], $options);
    
    // Tablo sütunlarını al
    $columns = [];
    $stmt = $sourceDb->query("SHOW COLUMNS FROM `$table`");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // İlk 10 kaydı al
    $stmt = $sourceDb->query("SELECT * FROM `$table` LIMIT 10");
    $records = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Veri sayısını al
    $stmt = $sourceDb->query("SELECT COUNT(*) FROM `$table`");
    $totalCount = $stmt->fetchColumn();
    
    // HTML çıktısı oluştur
    echo '<div class="mb-3">';
    echo '<h5>' . htmlspecialchars($table) . ' Tablosu</h5>';
    echo '<p>Toplam Kayıt Sayısı: <strong>' . number_format($totalCount, 0, ',', '.') . '</strong></p>';
    echo '</div>';
    
    if (!empty($records)) {
        echo '<div class="table-responsive">';
        echo '<table class="table table-sm table-bordered table-hover">';
        
        // Tablo başlıkları
        echo '<thead class="table-light">';
        echo '<tr>';
        foreach ($columns as $column) {
            echo '<th>' . htmlspecialchars($column['Field']) . '</th>';
        }
        echo '</tr>';
        echo '</thead>';
        
        // Tablo içeriği
        echo '<tbody>';
        foreach ($records as $record) {
            echo '<tr>';
            foreach ($record as $value) {
                if (is_null($value)) {
                    echo '<td><em class="text-muted">NULL</em></td>';
                } else {
                    // Çok uzun değerleri kısalt
                    $displayValue = (strlen($value) > 100) ? substr($value, 0, 100) . '...' : $value;
                    echo '<td>' . htmlspecialchars($displayValue) . '</td>';
                }
            }
            echo '</tr>';
        }
        echo '</tbody>';
        
        echo '</table>';
        echo '</div>';
        
        if ($totalCount > 10) {
            echo '<div class="alert alert-info mt-3">';
            echo 'Tabloda toplam ' . number_format($totalCount, 0, ',', '.') . ' kayıt var. Sadece ilk 10 kayıt gösteriliyor.';
            echo '</div>';
        }
    } else {
        echo '<div class="alert alert-warning">';
        echo 'Bu tabloda kayıt bulunamadı.';
        echo '</div>';
    }
    
} catch (PDOException $e) {
    echo '<div class="alert alert-danger">';
    echo 'Veritabanı hatası: ' . $e->getMessage();
    echo '</div>';
}
?> 