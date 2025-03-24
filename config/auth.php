<?php
/**
 * Kimlik Doğrulama İşlevleri
 * 
 * Bu dosya sistemin kimlik doğrulama, oturum yönetimi ve yetkilendirme 
 * işlevlerini içerir.
 */

// Oturum başlatma
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

/**
 * Kullanıcının giriş yapıp yapmadığını kontrol eder
 * @return bool Kullanıcı giriş yapmışsa true, aksi halde false
 */
function isLoggedIn() {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Kullanıcının yönetici yetkisi olup olmadığını kontrol eder
 * @return bool Kullanıcı yönetici ise true, aksi halde false
 */
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] == 'admin';
}

/**
 * Kullanıcı giriş işlemi
 * @param int $user_id Kullanıcı ID'si
 * @param string $username Kullanıcı adı
 * @param string $role Kullanıcı rolü (admin, user vb.)
 * @param string $ad_soyad Kullanıcının adı soyadı
 * @return bool İşlem başarılı ise true
 */
function login($user_id, $username, $role, $ad_soyad) {
    $_SESSION['user_id'] = $user_id;
    $_SESSION['username'] = $username;
    $_SESSION['user_role'] = $role;
    $_SESSION['ad_soyad'] = $ad_soyad;
    $_SESSION['login_time'] = time();
    return true;
}

/**
 * Kullanıcı çıkış işlemi
 * @return bool İşlem başarılı ise true
 */
function logout() {
    // Tüm session değişkenlerini temizle
    $_SESSION = array();
    
    // Session çerezini yok et
    if (ini_get("session.use_cookies")) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $params["path"], $params["domain"],
            $params["secure"], $params["httponly"]
        );
    }
    
    // Session'ı yok et
    session_destroy();
    return true;
}

/**
 * Kullanıcının belirli bir yetkiye sahip olup olmadığını kontrol eder
 * @param string $permission Kontrol edilecek yetki
 * @return bool Yetkiye sahipse true, aksi halde false
 */
function hasPermission($permission) {
    if (!isLoggedIn()) {
        return false;
    }
    
    // Yönetici her yetkiye sahiptir
    if (isAdmin()) {
        return true;
    }
    
    // Kullanıcının yetkilerini veritabanından kontrol et
    // Bu örnek basit bir yetki kontrolü içindir
    // Gerçek uygulamada veritabanından kontrol yapılmalıdır
    if (isset($_SESSION['permissions']) && is_array($_SESSION['permissions'])) {
        return in_array($permission, $_SESSION['permissions']);
    }
    
    return false;
}

/**
 * Yetki gerektiren sayfalar için kontrol
 * @param string $permission Gerekli yetki
 * @param string $redirectUrl Yetki yoksa yönlendirilecek URL
 * @return bool Yetkiye sahipse true, değilse yönlendirme yapar
 */
function requirePermission($permission, $redirectUrl = '/login.php') {
    if (!hasPermission($permission)) {
        $_SESSION['error'] = 'Bu sayfaya erişim yetkiniz bulunmamaktadır.';
        header('Location: ' . $redirectUrl);
        exit;
    }
    return true;
} 