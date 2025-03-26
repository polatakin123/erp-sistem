<?php
/**
 * EAN-13 barkod oluşturma script'i
 * Ajax istekleri için kullanılır
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    exit('Yetkisiz Erişim');
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';
require_once 'functions.php';

// EAN-13 oluştur (veritabanı bağlantısını göndererek)
$ean13 = generateEAN13('9670000', $db);

// Sadece EAN-13 kodunu döndür
echo $ean13; 