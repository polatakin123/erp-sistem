<?php
/**
 * ERP Sistem - Giriş Sayfası
 * 
 * Bu dosya kullanıcı giriş işlemlerini yönetir.
 */

// Oturum başlat
session_start();

// Kullanıcı zaten giriş yapmışsa ana sayfaya yönlendir
if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

// Veritabanı bağlantısı
require_once 'config/db.php';

// Hata mesajı
$error = '';

// Giriş formu gönderildiyse
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Kullanıcı adı ve şifre boş olmamalı
    if (empty($username) || empty($password)) {
        $error = 'Kullanıcı adı ve şifre gereklidir.';
    } else {
        try {
            // Kullanıcıyı veritabanında ara
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->bindParam(':username', $username);
            $stmt->execute();
            
            if ($stmt->rowCount() > 0) {
                $user = $stmt->fetch(PDO::FETCH_ASSOC);
                
                // Şifreyi doğrula
                if (password_verify($password, $user['password'])) {
                    // Oturum değişkenlerini ayarla
                    $_SESSION['user_id'] = $user['id'];
                    $_SESSION['username'] = $user['username'];
                    $_SESSION['full_name'] = $user['full_name'];
                    $_SESSION['email'] = $user['email'];
                    $_SESSION['role'] = $user['role'];
                    $_SESSION['last_login'] = isset($user['last_login']) ? $user['last_login'] : null;
                    
                    // Kullanıcı izinlerini al
                    $permissions = [];
                    
                    // Önce user_permissions tablosunun var olup olmadığını kontrol et
                    $checkTableStmt = $db->query("SHOW TABLES LIKE 'user_permissions'");
                    if ($checkTableStmt->rowCount() > 0) {
                        // Tablo varsa, kullanıcı izinlerini çek
                        try {
                            $permStmt = $db->prepare("SELECT module_name, permission FROM user_permissions WHERE user_id = :user_id");
                            $permStmt->bindParam(':user_id', $user['id']);
                            $permStmt->execute();
                            
                            while ($perm = $permStmt->fetch(PDO::FETCH_ASSOC)) {
                                $permissions[$perm['module_name']] = $perm['permission'];
                            }
                        } catch (PDOException $permError) {
                            // İzinleri alırken bir hata oluştu, boş izinlerle devam et
                            error_log("Kullanıcı izinleri alınırken hata: " . $permError->getMessage());
                        }
                    }
                    
                    $_SESSION['permissions'] = $permissions;
                    
                    // Son giriş zamanını güncelle
                    $updateStmt = $db->prepare("UPDATE users SET last_login = NOW() WHERE id = :id");
                    $updateStmt->bindParam(':id', $user['id']);
                    $updateStmt->execute();
                    
                    // Ana sayfaya yönlendir
                    header('Location: index.php');
                    exit;
                } else {
                    $error = 'Geçersiz kullanıcı adı veya şifre.';
                }
            } else {
                $error = 'Geçersiz kullanıcı adı veya şifre.';
            }
        } catch (PDOException $e) {
            $error = 'Veritabanı hatası: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ERP Sistem - Giriş</title>
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <!-- Custom CSS -->
    <link href="assets/css/style.css" rel="stylesheet">
    <style>
        body {
            background-color: #f8f9fa;
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            max-width: 400px;
            width: 100%;
            padding: 20px;
            background-color: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .login-logo {
            text-align: center;
            margin-bottom: 30px;
            height: 80px; /* Sabit yükseklik */
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-logo img, .login-logo svg {
            max-height: 80px;
            max-width: 100%;
        }
        .login-title {
            text-align: center;
            margin-bottom: 20px;
            color: #343a40;
        }
        .login-form .form-control {
            height: 45px;
        }
        .login-form .btn {
            height: 45px;
        }
        .login-footer {
            text-align: center;
            margin-top: 20px;
            font-size: 14px;
            color: #6c757d;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-logo">
            <!-- SVG logo placeholder başlangıçta gösterilir -->
            <svg id="logoPlaceholder" width="200" height="80" viewBox="0 0 200 80" xmlns="http://www.w3.org/2000/svg">
                <rect width="100%" height="100%" fill="#f8f9fa" />
                <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="24" fill="#6c757d" text-anchor="middle" dominant-baseline="middle">ERP Sistem</text>
            </svg>
            <!-- Gerçek logo gizli başlar, yüklenirse gösterilir -->
            <img id="logoImg" src="assets/img/logo.png" alt="ERP Sistem Logo" style="display: none;">
        </div>
        <h4 class="login-title">ERP Sistem Giriş</h4>
        
        <?php if (!empty($error)): ?>
            <div class="alert alert-danger" role="alert">
                <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <form class="login-form" method="post" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
            <div class="mb-3">
                <label for="username" class="form-label">Kullanıcı Adı</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-user"></i></span>
                    <input type="text" class="form-control" id="username" name="username" placeholder="Kullanıcı adınızı girin" required autofocus>
                </div>
            </div>
            <div class="mb-3">
                <label for="password" class="form-label">Şifre</label>
                <div class="input-group">
                    <span class="input-group-text"><i class="fas fa-lock"></i></span>
                    <input type="password" class="form-control" id="password" name="password" placeholder="Şifrenizi girin" required>
                    <button class="btn btn-outline-secondary" type="button" id="togglePassword">
                        <i class="fas fa-eye"></i>
                    </button>
                </div>
            </div>
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="rememberMe" name="remember">
                <label class="form-check-label" for="rememberMe">Beni hatırla</label>
            </div>
            <div class="d-grid gap-2">
                <button type="submit" class="btn btn-primary">
                    <i class="fas fa-sign-in-alt me-2"></i> Giriş Yap
                </button>
            </div>
        </form>
        
        <div class="login-footer">
            <p>&copy; <?php echo date('Y'); ?> ERP Sistem. Tüm hakları saklıdır.</p>
        </div>
    </div>

    <!-- Bootstrap & jQuery JS -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Logo yüklenmesini önceden kontrol et
        window.addEventListener('DOMContentLoaded', function() {
            const logoImg = document.getElementById('logoImg');
            const logoPlaceholder = document.getElementById('logoPlaceholder');
            
            // Logo yüklendiğinde
            logoImg.onload = function() {
                logoPlaceholder.style.display = 'none';
                logoImg.style.display = 'block';
            };
            
            // Logo yüklenemediğinde placeholder görünür kalır
            logoImg.onerror = function() {
                console.log('Logo dosyası bulunamadı, varsayılan logo kullanılıyor.');
                logoImg.style.display = 'none';
                logoPlaceholder.style.display = 'block';
            };
            
            // Şifre göster/gizle
            document.getElementById('togglePassword').addEventListener('click', function() {
                const passwordInput = document.getElementById('password');
                const icon = this.querySelector('i');
                
                if (passwordInput.type === 'password') {
                    passwordInput.type = 'text';
                    icon.classList.remove('fa-eye');
                    icon.classList.add('fa-eye-slash');
                } else {
                    passwordInput.type = 'password';
                    icon.classList.remove('fa-eye-slash');
                    icon.classList.add('fa-eye');
                }
            });
        });
    </script>
</body>
</html> 