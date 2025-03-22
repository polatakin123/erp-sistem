<?php
/**
 * ERP Sistem - Şirket Bilgileri Sayfası
 * 
 * Bu dosya şirket bilgilerinin görüntülenmesi ve düzenlenmesini sağlar.
 */

// Oturum ve güvenlik kontrolü
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Yetki kontrolü
if (!isset($_SESSION['permissions']['ayarlar']) || $_SESSION['permissions']['ayarlar'] != 'tam_yetki') {
    header('Location: ../../index.php?error=yetkisiz_erisim');
    exit;
}

// Veritabanı bağlantısı
require_once '../../config/db.php';

// Şirket bilgileri varsayılan değerler
$company = [
    'name' => '',
    'tax_office' => '',
    'tax_number' => '',
    'address' => '',
    'phone' => '',
    'email' => '',
    'website' => '',
    'logo' => '',
    'founded_year' => '',
    'bank_account' => '',
    'bank_iban' => ''
];

// Başarı ve hata mesajları
$successMessage = '';
$errorMessage = '';

// Veritabanından şirket bilgilerini al
try {
    $stmt = $db->prepare("SELECT * FROM company_settings WHERE id = 1");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    }
} catch (PDOException $e) {
    $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
}

// Form gönderildiyse
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Form verilerini al
    $companyName = trim($_POST['company_name'] ?? '');
    $taxOffice = trim($_POST['tax_office'] ?? '');
    $taxNumber = trim($_POST['tax_number'] ?? '');
    $address = trim($_POST['address'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $website = trim($_POST['website'] ?? '');
    $foundedYear = trim($_POST['founded_year'] ?? '');
    $bankAccount = trim($_POST['bank_account'] ?? '');
    $bankIban = trim($_POST['bank_iban'] ?? '');
    
    // Validasyon
    $errors = [];
    
    if (empty($companyName)) {
        $errors[] = 'Şirket adı boş olamaz.';
    }
    
    if (empty($taxNumber)) {
        $errors[] = 'Vergi numarası boş olamaz.';
    }
    
    if (empty($address)) {
        $errors[] = 'Adres boş olamaz.';
    }
    
    if (empty($phone)) {
        $errors[] = 'Telefon numarası boş olamaz.';
    }
    
    if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }
    
    if (!empty($website) && !filter_var($website, FILTER_VALIDATE_URL)) {
        $errors[] = 'Geçerli bir website adresi giriniz.';
    }
    
    // Logo yükleme işlemi
    $logoPath = $company['logo']; // Mevcut logo yolunu koru
    
    if (isset($_FILES['logo']) && $_FILES['logo']['size'] > 0) {
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif'];
        $maxSize = 2 * 1024 * 1024; // 2MB
        
        if (!in_array($_FILES['logo']['type'], $allowedTypes)) {
            $errors[] = 'Logo sadece JPEG, PNG veya GIF formatında olabilir.';
        }
        
        if ($_FILES['logo']['size'] > $maxSize) {
            $errors[] = 'Logo boyutu en fazla 2MB olabilir.';
        }
        
        if (empty($errors)) {
            $uploadDir = '../../assets/img/';
            $fileName = 'company_logo_' . time() . '_' . basename($_FILES['logo']['name']);
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($_FILES['logo']['tmp_name'], $uploadPath)) {
                // Eski logoyu sil (varsayılan logo değilse)
                if (!empty($logoPath) && file_exists($logoPath) && basename($logoPath) != 'logo-placeholder.png') {
                    unlink($logoPath);
                }
                
                $logoPath = $uploadPath;
            } else {
                $errors[] = 'Logo yüklenirken bir hata oluştu.';
            }
        }
    }
    
    // Hata yoksa güncelleme işlemini gerçekleştir
    if (empty($errors)) {
        try {
            $sql = "INSERT INTO company_settings 
                    (id, name, tax_office, tax_number, address, phone, email, website, logo, founded_year, bank_account, bank_iban, updated_at) 
                    VALUES (1, :name, :tax_office, :tax_number, :address, :phone, :email, :website, :logo, :founded_year, :bank_account, :bank_iban, NOW())
                    ON DUPLICATE KEY UPDATE
                    name = :name, tax_office = :tax_office, tax_number = :tax_number, address = :address, 
                    phone = :phone, email = :email, website = :website, logo = :logo, founded_year = :founded_year,
                    bank_account = :bank_account, bank_iban = :bank_iban, updated_at = NOW()";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':name', $companyName);
            $stmt->bindParam(':tax_office', $taxOffice);
            $stmt->bindParam(':tax_number', $taxNumber);
            $stmt->bindParam(':address', $address);
            $stmt->bindParam(':phone', $phone);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':website', $website);
            $stmt->bindParam(':logo', $logoPath);
            $stmt->bindParam(':founded_year', $foundedYear);
            $stmt->bindParam(':bank_account', $bankAccount);
            $stmt->bindParam(':bank_iban', $bankIban);
            
            if ($stmt->execute()) {
                $successMessage = 'Şirket bilgileri başarıyla güncellendi.';
                
                // Güncellenmiş verileri hemen göster
                $company = [
                    'name' => $companyName,
                    'tax_office' => $taxOffice,
                    'tax_number' => $taxNumber,
                    'address' => $address,
                    'phone' => $phone,
                    'email' => $email,
                    'website' => $website,
                    'logo' => $logoPath,
                    'founded_year' => $foundedYear,
                    'bank_account' => $bankAccount,
                    'bank_iban' => $bankIban
                ];
            } else {
                $errorMessage = 'Şirket bilgileri güncellenirken bir hata oluştu.';
            }
        } catch (PDOException $e) {
            $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}

// Sayfa başlığı
$pageTitle = 'Şirket Bilgileri';

// Üst kısım
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Şirket Bilgileri</h1>
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

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Şirket Bilgilerini Düzenle</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>" enctype="multipart/form-data">
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="company_name" class="form-label">Şirket Adı *</label>
                            <input type="text" class="form-control" id="company_name" name="company_name" value="<?php echo htmlspecialchars($company['name']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="tax_office" class="form-label">Vergi Dairesi</label>
                            <input type="text" class="form-control" id="tax_office" name="tax_office" value="<?php echo htmlspecialchars($company['tax_office']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="tax_number" class="form-label">Vergi Numarası *</label>
                            <input type="text" class="form-control" id="tax_number" name="tax_number" value="<?php echo htmlspecialchars($company['tax_number']); ?>" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="address" class="form-label">Adres *</label>
                            <textarea class="form-control" id="address" name="address" rows="3" required><?php echo htmlspecialchars($company['address']); ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label for="phone" class="form-label">Telefon *</label>
                            <input type="text" class="form-control phone-mask" id="phone" name="phone" value="<?php echo htmlspecialchars($company['phone']); ?>" required>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email" value="<?php echo htmlspecialchars($company['email']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="website" class="form-label">Web Sitesi</label>
                            <input type="url" class="form-control" id="website" name="website" value="<?php echo htmlspecialchars($company['website']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="founded_year" class="form-label">Kuruluş Yılı</label>
                            <input type="number" class="form-control" id="founded_year" name="founded_year" value="<?php echo htmlspecialchars($company['founded_year']); ?>" min="1900" max="<?php echo date('Y'); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="bank_account" class="form-label">Banka Hesap Adı</label>
                            <input type="text" class="form-control" id="bank_account" name="bank_account" value="<?php echo htmlspecialchars($company['bank_account']); ?>">
                        </div>
                        
                        <div class="mb-3">
                            <label for="bank_iban" class="form-label">IBAN</label>
                            <input type="text" class="form-control" id="bank_iban" name="bank_iban" value="<?php echo htmlspecialchars($company['bank_iban']); ?>">
                        </div>
                    </div>
                </div>
                
                <div class="row">
                    <div class="col-md-6">
                        <div class="mb-3">
                            <label for="logo" class="form-label">Şirket Logosu</label>
                            <?php if (!empty($company['logo'])): ?>
                                <div class="mb-2">
                                    <img src="<?php echo htmlspecialchars($company['logo']); ?>" alt="Şirket Logosu" class="img-thumbnail" style="max-height: 100px;">
                                </div>
                            <?php endif; ?>
                            <input type="file" class="form-control" id="logo" name="logo" accept="image/jpeg, image/png, image/gif">
                            <div class="form-text">Maksimum 2MB, JPEG, PNG veya GIF formatında.</div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
// Alt kısım
include '../../includes/footer.php';
?> 