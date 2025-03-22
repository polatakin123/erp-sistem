<?php
/**
 * Genel Yardımcı Fonksiyonlar
 * 
 * Bu dosya sistemde kullanılacak genel yardımcı fonksiyonları içerir.
 */

// XSS saldırılarına karşı koruma
function clean($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Sunucu adları için özel karakterleri (özellikle \) koruyan fonksiyon
function cleanServerName($data) {
    $data = trim($data);
    // stripslashes fonksiyonunu kullanmıyoruz çünkü \ karakterini kaldırıyor
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Özel karakterleri koruyarak ürün adlarını işleme fonksiyonu (>, < gibi karakterleri korur)
function cleanProductName($data) {
    $data = trim($data);
    $data = stripslashes($data);
    
    // "&gt;" gibi zaten encode edilmiş karakterleri decode et
    $data = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
    
    // Script ve tehlikeli HTML etiketlerini filtrele ama > ve < gibi karakterleri koru
    $data = str_replace(['<script', '</script>'], ['&lt;script', '&lt;/script&gt;'], $data);
    
    return $data;
}

// XSS saldırılarına karşı koruma (HTML olarak kaydetme)
function cleanRaw($data) {
    $data = trim($data);
    $data = stripslashes($data);
    return $data;
}

// HTML olarak kaydedilmiş verileri güvenli şekilde görüntüleme
function displayHtml($data) {
    // Önce html_entity_decode ile &gt; gibi karakterleri düzgün işle
    $decoded = html_entity_decode($data, ENT_QUOTES, 'UTF-8');
    // Sonra tekrar htmlspecialchars ile güvenli bir şekilde çık
    return htmlspecialchars($decoded, ENT_QUOTES, 'UTF-8');
}

// Tarih formatını düzenleme (MySQL -> TR)
function formatDateTR($date) {
    if ($date == '0000-00-00' || $date == '') {
        return '';
    }
    $newDate = new DateTime($date);
    return $newDate->format('d.m.Y');
}

// Tarih formatını düzenleme (TR -> MySQL)
function formatDateSQL($date) {
    if (empty($date)) {
        return null;
    }
    $date = str_replace('.', '-', $date);
    $newDate = new DateTime($date);
    return $newDate->format('Y-m-d');
}

// Para formatını düzenleme
function formatMoney($amount) {
    return number_format($amount, 2, ',', '.');
}

// Sayfa yönlendirme
function redirect($url) {
    header("Location: $url");
    exit();
}

// Oturum kontrolü
function checkLogin() {
    if (!isset($_SESSION['user_id'])) {
        redirect('login.php');
    }
}

// Hata mesajı oluşturma
function errorMessage($message) {
    return '<div class="alert alert-danger">' . $message . '</div>';
}

// Başarı mesajı oluşturma
function successMessage($message) {
    return '<div class="alert alert-success">' . $message . '</div>';
}

// Bilgi mesajı oluşturma
function infoMessage($message) {
    return '<div class="alert alert-info">' . $message . '</div>';
}

// Uyarı mesajı oluşturma
function warningMessage($message) {
    return '<div class="alert alert-warning">' . $message . '</div>';
}

// Kullanıcı yetkisini kontrol etme
function checkPermission($permission) {
    if (!isset($_SESSION['permissions']) || !in_array($permission, $_SESSION['permissions'])) {
        return false;
    }
    return true;
}

// Rastgele şifre oluşturma
function generatePassword($length = 8) {
    $chars = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789!@#$%^&*()_+';
    $password = '';
    for ($i = 0; $i < $length; $i++) {
        $password .= $chars[rand(0, strlen($chars) - 1)];
    }
    return $password;
}

// Dosya yükleme
function uploadFile($file, $destination, $allowedTypes = ['jpg', 'jpeg', 'png', 'pdf'], $maxSize = 5242880) {
    // Dosya türünü kontrol et
    $fileType = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($fileType, $allowedTypes)) {
        return ['success' => false, 'message' => 'Geçersiz dosya türü. İzin verilen türler: ' . implode(', ', $allowedTypes)];
    }
    
    // Dosya boyutunu kontrol et
    if ($file['size'] > $maxSize) {
        return ['success' => false, 'message' => 'Dosya boyutu çok büyük. Maksimum boyut: ' . ($maxSize / 1048576) . 'MB'];
    }
    
    // Benzersiz dosya adı oluştur
    $fileName = uniqid() . '.' . $fileType;
    $targetFile = $destination . $fileName;
    
    // Dosyayı yükle
    if (move_uploaded_file($file['tmp_name'], $targetFile)) {
        return ['success' => true, 'file_name' => $fileName, 'file_path' => $targetFile];
    } else {
        return ['success' => false, 'message' => 'Dosya yüklenirken bir hata oluştu.'];
    }
} 