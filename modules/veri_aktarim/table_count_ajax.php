<?php
/**
 * ERP Sistem - Tablo Kayıt Sayısı Hesaplama (AJAX)
 * 
 * Bu dosya AJAX isteği ile tablo kayıt sayısını hesaplar.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    echo '<span class="text-danger">Oturum hatası</span>';
    exit;
}

// Veritabanı bağlantı bilgileri kontrolü
if (!isset($_SESSION['source_db'])) {
    echo '<span class="text-danger">Bağlantı bilgisi bulunamadı</span>';
    exit;
}

// Tablo adını al
if (!isset($_GET['table']) || empty($_GET['table'])) {
    echo '<span class="text-danger">Tablo adı belirtilmedi</span>';
    exit;
}

$table = $_GET['table'];

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

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
?> 