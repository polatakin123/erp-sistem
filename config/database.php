<?php
/**
 * Veritabanı Bağlantı Ayarları
 * 
 * Bu dosya veritabanı bağlantı bilgilerini içerir.
 */

// Veritabanı bağlantı bilgileri
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'erp_sistem');

// PDO bağlantısı oluşturma
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8";
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
    
} catch (PDOException $e) {
    // Hata durumunda
    die("Veritabanı bağlantı hatası: " . $e->getMessage());
} 