<?php
/**
 * ERP Sistem - Kullanıcı Profili Sayfası
 * 
 * Bu dosya kullanıcının kendi profilini düzenlemesini sağlar.
 */

// Oturum ve güvenlik kontrolü
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Veritabanı bağlantısı
require_once '../../config/db.php';

// Başarı ve hata mesajları
$successMessage = '';
$errorMessage = '';

// Kullanıcı bilgilerini al
try {
    $stmt = $db->prepare("SELECT * FROM users WHERE id = :id");
    $stmt->bindParam(':id', $_SESSION['user_id']);
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        header('Location: ../../logout.php');
        exit;
    }
} catch (PDOException $e) {
    $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
}

// Form gönderildiyse
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Hangi form gönderildi kontrolü
    if (isset($_POST['update_profile'])) {
        // Profil güncelleme formu
        $fullName = trim($_POST['full_name'] ?? '');
        $email = trim($_POST['email'] ?? '');
        
        // Validasyon
        $errors = [];
        
        if (empty($fullName)) {
            $errors[] = 'Ad Soyad alanı boş olamaz.';
        }
        
        if (empty($email)) {
            $errors[] = 'E-posta alanı boş olamaz.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'Geçerli bir e-posta adresi giriniz.';
        }
        
        // E-posta adresi başka bir kullanıcı tarafından kullanılıyor mu?
        if ($email != $user['email']) {
            try {
                $checkEmail = $db->prepare("SELECT id FROM users WHERE email = :email AND id != :id");
                $checkEmail->bindParam(':email', $email);
                $checkEmail->bindParam(':id', $_SESSION['user_id']);
                $checkEmail->execute();
                
                if ($checkEmail->rowCount() > 0) {
                    $errors[] = 'Bu e-posta adresi başka bir kullanıcı tarafından kullanılıyor.';
                }
            } catch (PDOException $e) {
                $errors[] = 'Veritabanı hatası: ' . $e->getMessage();
            }
        }
        
        // Hata yoksa güncelleme işlemini gerçekleştir
        if (empty($errors)) {
            try {
                $updateStmt = $db->prepare("UPDATE users SET full_name = :full_name, email = :email, updated_at = NOW() WHERE id = :id");
                $updateStmt->bindParam(':full_name', $fullName);
                $updateStmt->bindParam(':email', $email);
                $updateStmt->bindParam(':id', $_SESSION['user_id']);
                
                if ($updateStmt->execute()) {
                    $successMessage = 'Profil bilgileriniz başarıyla güncellendi.';
                    
                    // Session bilgilerini güncelle
                    $_SESSION['full_name'] = $fullName;
                    $_SESSION['email'] = $email;
                    
                    // Güncel verileri göster
                    $user['full_name'] = $fullName;
                    $user['email'] = $email;
                } else {
                    $errorMessage = 'Profil güncellenirken bir hata oluştu.';
                }
            } catch (PDOException $e) {
                $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
            }
        } else {
            $errorMessage = implode('<br>', $errors);
        }
    } elseif (isset($_POST['change_password'])) {
        // Şifre değiştirme formu
        $currentPassword = $_POST['current_password'] ?? '';
        $newPassword = $_POST['new_password'] ?? '';
        $confirmPassword = $_POST['confirm_password'] ?? '';
        
        // Validasyon
        $errors = [];
        
        if (empty($currentPassword)) {
            $errors[] = 'Mevcut şifre boş olamaz.';
        }
        
        if (empty($newPassword)) {
            $errors[] = 'Yeni şifre boş olamaz.';
        } elseif (strlen($newPassword) < 8) {
            $errors[] = 'Yeni şifre en az 8 karakter olmalıdır.';
        }
        
        if ($newPassword != $confirmPassword) {
            $errors[] = 'Yeni şifre ve şifre tekrarı eşleşmiyor.';
        }
        
        // Mevcut şifre doğru mu?
        if (!password_verify($currentPassword, $user['password'])) {
            $errors[] = 'Mevcut şifreniz yanlış.';
        }
        
        // Hata yoksa şifre değiştirme işlemini gerçekleştir
        if (empty($errors)) {
            try {
                // Yeni şifreyi hash'le
                $hashedPassword = password_hash($newPassword, PASSWORD_DEFAULT);
                
                $updateStmt = $db->prepare("UPDATE users SET password = :password, updated_at = NOW() WHERE id = :id");
                $updateStmt->bindParam(':password', $hashedPassword);
                $updateStmt->bindParam(':id', $_SESSION['user_id']);
                
                if ($updateStmt->execute()) {
                    $successMessage = 'Şifreniz başarıyla değiştirildi.';
                } else {
                    $errorMessage = 'Şifre değiştirilirken bir hata oluştu.';
                }
            } catch (PDOException $e) {
                $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
            }
        } else {
            $errorMessage = implode('<br>', $errors);
        }
    }
}

// Sayfa başlığı
$pageTitle = 'Kullanıcı Profili';

// Üst kısım
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Kullanıcı Profili</h1>
    </div>

    <?php if (!empty($successMessage)): ?>
        <div class="alert alert-success">
            <?php echo $successMessage; ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errorMessage)): ?>
        <div class="alert alert-danger">
            <?php echo $errorMessage; ?>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-xl-4">
            <!-- Kullanıcı Bilgileri Kartı -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Kullanıcı Bilgileri</h6>
                </div>
                <div class="card-body">
                    <div class="text-center mb-4">
                        <img class="img-fluid rounded-circle mb-3" style="width: 150px; height: 150px; object-fit: cover;" 
                             src="https://ui-avatars.com/api/?name=<?php echo urlencode($user['full_name']); ?>&background=random&color=fff&size=150">
                        <h5 class="mb-1"><?php echo htmlspecialchars($user['full_name']); ?></h5>
                        <p class="text-muted"><?php echo htmlspecialchars($user['role']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Kullanıcı Adı:</h6>
                        <p><?php echo htmlspecialchars($user['username']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">E-posta:</h6>
                        <p><?php echo htmlspecialchars($user['email']); ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Son Giriş:</h6>
                        <p><?php echo $user['last_login'] ? date('d.m.Y H:i', strtotime($user['last_login'])) : 'Bilgi yok'; ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <h6 class="font-weight-bold">Hesap Durumu:</h6>
                        <p>
                            <?php if ($user['status'] == 'active'): ?>
                                <span class="badge bg-success text-white">Aktif</span>
                            <?php else: ?>
                                <span class="badge bg-danger text-white">Pasif</span>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="col-xl-8">
            <!-- Profil Güncelleme Kartı -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Profil Bilgilerini Güncelle</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" disabled>
                            <div class="form-text">Kullanıcı adı değiştirilemez.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Ad Soyad *</label>
                            <input type="text" class="form-control" id="full_name" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta *</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="role" class="form-label">Rol</label>
                            <input type="text" class="form-control" id="role" value="<?php echo htmlspecialchars($user['role']); ?>" disabled>
                            <div class="form-text">Rol değiştirme işlemi için yönetici ile iletişime geçin.</div>
                        </div>
                        
                        <input type="hidden" name="update_profile" value="1">
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save me-1"></i> Profili Güncelle
                            </button>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Şifre Değiştirme Kartı -->
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Şifre Değiştir</h6>
                </div>
                <div class="card-body">
                    <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                        <div class="mb-3">
                            <label for="current_password" class="form-label">Mevcut Şifre *</label>
                            <input type="password" class="form-control" id="current_password" name="current_password" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="new_password" class="form-label">Yeni Şifre *</label>
                            <input type="password" class="form-control" id="new_password" name="new_password" required minlength="8">
                            <div class="form-text">Şifreniz en az 8 karakter olmalıdır.</div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="confirm_password" class="form-label">Yeni Şifre (Tekrar) *</label>
                            <input type="password" class="form-control" id="confirm_password" name="confirm_password" required minlength="8">
                        </div>
                        
                        <input type="hidden" name="change_password" value="1">
                        
                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-key me-1"></i> Şifreyi Değiştir
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<?php
// Alt kısım
include '../../includes/footer.php';
?> 