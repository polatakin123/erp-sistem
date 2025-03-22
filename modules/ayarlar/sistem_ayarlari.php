<?php
/**
 * ERP Sistem - Sistem Ayarları Sayfası
 * 
 * Bu dosya sistem genelindeki ayarları düzenlemeyi sağlar.
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

// Sistem ayarları varsayılan değerler
$settings = [
    'site_title' => 'ERP Sistem',
    'timezone' => 'Europe/Istanbul',
    'date_format' => 'd.m.Y',
    'currency_symbol' => '₺',
    'records_per_page' => '10',
    'theme' => 'default',
    'mail_host' => '',
    'mail_port' => '587',
    'mail_username' => '',
    'mail_password' => '',
    'mail_from_name' => '',
    'mail_from_address' => '',
    'invoice_prefix' => 'INV',
    'invoice_next_number' => '1',
    'backup_enabled' => '1',
    'backup_frequency' => 'daily',
    'maintenance_mode' => '0'
];

// Başarı ve hata mesajları
$successMessage = '';
$errorMessage = '';

// Veritabanından sistem ayarlarını al
try {
    $stmt = $db->prepare("SELECT * FROM system_settings");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
            $settings[$row['setting_key']] = $row['setting_value'];
        }
    }
} catch (PDOException $e) {
    // Tablo yoksa oluştur
    try {
        $db->exec("CREATE TABLE IF NOT EXISTS `system_settings` (
            `id` int(11) NOT NULL AUTO_INCREMENT,
            `setting_key` varchar(100) NOT NULL,
            `setting_value` text,
            `setting_group` varchar(50) DEFAULT 'general',
            `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `setting_key` (`setting_key`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");
        
        // Varsayılan ayarları ekle
        $db->beginTransaction();
        
        $insertStmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) VALUES (:key, :value, :group)");
        
        foreach ($settings as $key => $value) {
            $group = 'general';
            
            if (strpos($key, 'mail_') === 0) {
                $group = 'mail';
            } elseif (strpos($key, 'invoice_') === 0) {
                $group = 'invoice';
            } elseif (strpos($key, 'backup_') === 0) {
                $group = 'backup';
            }
            
            $insertStmt->bindParam(':key', $key);
            $insertStmt->bindParam(':value', $value);
            $insertStmt->bindParam(':group', $group);
            $insertStmt->execute();
        }
        
        $db->commit();
    } catch (PDOException $e2) {
        $errorMessage = 'Veritabanı hatası: ' . $e2->getMessage();
        if ($db->inTransaction()) {
            $db->rollBack();
        }
    }
}

// Form gönderildiyse
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Gönderilen değerleri al
    $newSettings = [
        'site_title' => trim($_POST['site_title'] ?? ''),
        'timezone' => trim($_POST['timezone'] ?? ''),
        'date_format' => trim($_POST['date_format'] ?? ''),
        'currency_symbol' => trim($_POST['currency_symbol'] ?? ''),
        'records_per_page' => intval($_POST['records_per_page'] ?? 10),
        'theme' => trim($_POST['theme'] ?? ''),
        'mail_host' => trim($_POST['mail_host'] ?? ''),
        'mail_port' => trim($_POST['mail_port'] ?? ''),
        'mail_username' => trim($_POST['mail_username'] ?? ''),
        'mail_password' => trim($_POST['mail_password'] ?? ''),
        'mail_from_name' => trim($_POST['mail_from_name'] ?? ''),
        'mail_from_address' => trim($_POST['mail_from_address'] ?? ''),
        'invoice_prefix' => trim($_POST['invoice_prefix'] ?? ''),
        'invoice_next_number' => intval($_POST['invoice_next_number'] ?? 1),
        'backup_enabled' => isset($_POST['backup_enabled']) ? '1' : '0',
        'backup_frequency' => trim($_POST['backup_frequency'] ?? ''),
        'maintenance_mode' => isset($_POST['maintenance_mode']) ? '1' : '0'
    ];
    
    // Validasyon
    $errors = [];
    
    if (empty($newSettings['site_title'])) {
        $errors[] = 'Site başlığı boş olamaz.';
    }
    
    if (empty($newSettings['timezone'])) {
        $errors[] = 'Zaman dilimi boş olamaz.';
    }
    
    if (!empty($newSettings['mail_from_address']) && !filter_var($newSettings['mail_from_address'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Geçerli bir e-posta adresi giriniz.';
    }
    
    if ($newSettings['records_per_page'] < 1) {
        $errors[] = 'Sayfa başına kayıt sayısı en az 1 olmalıdır.';
    }
    
    // Hata yoksa güncelleme işlemini gerçekleştir
    if (empty($errors)) {
        try {
            $db->beginTransaction();
            
            // Her bir ayarı güncelle
            $updateStmt = $db->prepare("INSERT INTO system_settings (setting_key, setting_value, setting_group) 
                    VALUES (:key, :value, :group) 
                    ON DUPLICATE KEY UPDATE 
                    setting_value = :value, updated_at = NOW()");
            
            foreach ($newSettings as $key => $value) {
                $group = 'general';
                
                if (strpos($key, 'mail_') === 0) {
                    $group = 'mail';
                } elseif (strpos($key, 'invoice_') === 0) {
                    $group = 'invoice';
                } elseif (strpos($key, 'backup_') === 0) {
                    $group = 'backup';
                }
                
                $updateStmt->bindParam(':key', $key);
                $updateStmt->bindParam(':value', $value);
                $updateStmt->bindParam(':group', $group);
                $updateStmt->execute();
            }
            
            $db->commit();
            $successMessage = 'Sistem ayarları başarıyla güncellendi.';
            
            // Güncellenmiş ayarları göster
            $settings = $newSettings;
        } catch (PDOException $e) {
            $db->rollBack();
            $errorMessage = 'Veritabanı hatası: ' . $e->getMessage();
        }
    } else {
        $errorMessage = implode('<br>', $errors);
    }
}

// Zaman dilimleri listesi
$timezones = DateTimeZone::listIdentifiers();

// Tarih formatları
$dateFormats = [
    'd.m.Y' => date('d.m.Y'),
    'd/m/Y' => date('d/m/Y'),
    'Y-m-d' => date('Y-m-d'),
    'd.m.Y H:i' => date('d.m.Y H:i'),
    'd/m/Y H:i' => date('d/m/Y H:i'),
    'Y-m-d H:i' => date('Y-m-d H:i')
];

// Para birimleri
$currencySymbols = [
    '₺' => 'Türk Lirası (₺)',
    '$' => 'Dolar ($)',
    '€' => 'Euro (€)',
    '£' => 'Sterlin (£)'
];

// Temalar
$themes = [
    'default' => 'Varsayılan',
    'dark' => 'Koyu Tema',
    'light' => 'Açık Tema',
    'blue' => 'Mavi Tema'
];

// Yedekleme sıklıkları
$backupFrequencies = [
    'daily' => 'Günlük',
    'weekly' => 'Haftalık',
    'monthly' => 'Aylık'
];

// Sayfa başlığı
$pageTitle = 'Sistem Ayarları';

// Üst kısım
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="d-sm-flex align-items-center justify-content-between mb-4">
        <h1 class="h3 mb-0 text-gray-800">Sistem Ayarları</h1>
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
            <h6 class="m-0 font-weight-bold text-primary">Sistem Ayarlarını Düzenle</h6>
        </div>
        <div class="card-body">
            <form method="POST" action="<?php echo htmlspecialchars($_SERVER['PHP_SELF']); ?>">
                <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="general-tab" data-bs-toggle="tab" data-bs-target="#general" type="button" role="tab" aria-controls="general" aria-selected="true">Genel</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="email-tab" data-bs-toggle="tab" data-bs-target="#email" type="button" role="tab" aria-controls="email" aria-selected="false">E-posta</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="invoice-tab" data-bs-toggle="tab" data-bs-target="#invoice" type="button" role="tab" aria-controls="invoice" aria-selected="false">Fatura</button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="backup-tab" data-bs-toggle="tab" data-bs-target="#backup" type="button" role="tab" aria-controls="backup" aria-selected="false">Yedekleme</button>
                    </li>
                </ul>
                
                <div class="tab-content" id="settingsTabsContent">
                    <!-- Genel Ayarlar -->
                    <div class="tab-pane fade show active" id="general" role="tabpanel" aria-labelledby="general-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="site_title" class="form-label">Site Başlığı *</label>
                                    <input type="text" class="form-control" id="site_title" name="site_title" value="<?php echo htmlspecialchars($settings['site_title']); ?>" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="timezone" class="form-label">Zaman Dilimi *</label>
                                    <select class="form-select" id="timezone" name="timezone" required>
                                        <?php foreach ($timezones as $tz): ?>
                                            <option value="<?php echo $tz; ?>" <?php echo ($settings['timezone'] == $tz) ? 'selected' : ''; ?>>
                                                <?php echo $tz; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="date_format" class="form-label">Tarih Formatı *</label>
                                    <select class="form-select" id="date_format" name="date_format" required>
                                        <?php foreach ($dateFormats as $format => $example): ?>
                                            <option value="<?php echo $format; ?>" <?php echo ($settings['date_format'] == $format) ? 'selected' : ''; ?>>
                                                <?php echo $example . ' (' . $format . ')'; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="currency_symbol" class="form-label">Para Birimi *</label>
                                    <select class="form-select" id="currency_symbol" name="currency_symbol" required>
                                        <?php foreach ($currencySymbols as $symbol => $name): ?>
                                            <option value="<?php echo $symbol; ?>" <?php echo ($settings['currency_symbol'] == $symbol) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="records_per_page" class="form-label">Sayfa Başına Kayıt Sayısı *</label>
                                    <input type="number" class="form-control" id="records_per_page" name="records_per_page" value="<?php echo htmlspecialchars($settings['records_per_page']); ?>" min="1" required>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="theme" class="form-label">Tema</label>
                                    <select class="form-select" id="theme" name="theme">
                                        <?php foreach ($themes as $value => $name): ?>
                                            <option value="<?php echo $value; ?>" <?php echo ($settings['theme'] == $value) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="maintenance_mode" name="maintenance_mode" <?php echo ($settings['maintenance_mode'] == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="maintenance_mode">Bakım Modu</label>
                                    <div class="form-text">Bakım modu aktif olduğunda sadece yöneticiler sisteme erişebilir.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- E-posta Ayarları -->
                    <div class="tab-pane fade" id="email" role="tabpanel" aria-labelledby="email-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mail_host" class="form-label">SMTP Sunucusu</label>
                                    <input type="text" class="form-control" id="mail_host" name="mail_host" value="<?php echo htmlspecialchars($settings['mail_host']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_port" class="form-label">SMTP Port</label>
                                    <input type="text" class="form-control" id="mail_port" name="mail_port" value="<?php echo htmlspecialchars($settings['mail_port']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_username" class="form-label">SMTP Kullanıcı Adı</label>
                                    <input type="text" class="form-control" id="mail_username" name="mail_username" value="<?php echo htmlspecialchars($settings['mail_username']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_password" class="form-label">SMTP Şifre</label>
                                    <input type="password" class="form-control" id="mail_password" name="mail_password" value="<?php echo htmlspecialchars($settings['mail_password']); ?>">
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="mail_from_name" class="form-label">Gönderen Adı</label>
                                    <input type="text" class="form-control" id="mail_from_name" name="mail_from_name" value="<?php echo htmlspecialchars($settings['mail_from_name']); ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label for="mail_from_address" class="form-label">Gönderen E-posta</label>
                                    <input type="email" class="form-control" id="mail_from_address" name="mail_from_address" value="<?php echo htmlspecialchars($settings['mail_from_address']); ?>">
                                </div>
                                
                                <div class="mt-4">
                                    <button type="button" class="btn btn-secondary" id="testEmail">
                                        <i class="fas fa-paper-plane me-1"></i> Test E-postası Gönder
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Fatura Ayarları -->
                    <div class="tab-pane fade" id="invoice" role="tabpanel" aria-labelledby="invoice-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="invoice_prefix" class="form-label">Fatura Ön Eki</label>
                                    <input type="text" class="form-control" id="invoice_prefix" name="invoice_prefix" value="<?php echo htmlspecialchars($settings['invoice_prefix']); ?>">
                                    <div class="form-text">Fatura numaralarının başına eklenecek ön ek. Örnek: INV-001</div>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="invoice_next_number" class="form-label">Sonraki Fatura Numarası</label>
                                    <input type="number" class="form-control" id="invoice_next_number" name="invoice_next_number" value="<?php echo htmlspecialchars($settings['invoice_next_number']); ?>" min="1">
                                    <div class="form-text">Oluşturulacak bir sonraki fatura için kullanılacak numara.</div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Yedekleme Ayarları -->
                    <div class="tab-pane fade" id="backup" role="tabpanel" aria-labelledby="backup-tab">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3 form-check">
                                    <input type="checkbox" class="form-check-input" id="backup_enabled" name="backup_enabled" <?php echo ($settings['backup_enabled'] == '1') ? 'checked' : ''; ?>>
                                    <label class="form-check-label" for="backup_enabled">Otomatik Yedekleme</label>
                                    <div class="form-text">Otomatik yedekleme özelliğini aktif eder.</div>
                                </div>
                                
                                <div class="mb-3">
                                    <label for="backup_frequency" class="form-label">Yedekleme Sıklığı</label>
                                    <select class="form-select" id="backup_frequency" name="backup_frequency">
                                        <?php foreach ($backupFrequencies as $value => $name): ?>
                                            <option value="<?php echo $value; ?>" <?php echo ($settings['backup_frequency'] == $value) ? 'selected' : ''; ?>>
                                                <?php echo $name; ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="col-md-6">
                                <div class="card">
                                    <div class="card-body">
                                        <h5 class="card-title">Manuel Yedekleme</h5>
                                        <p class="card-text">Veritabanının anlık bir yedeğini almak için aşağıdaki butona tıklayın.</p>
                                        <button type="button" class="btn btn-primary" id="manualBackup">
                                            <i class="fas fa-download me-1"></i> Yedek Al
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <hr>
                
                <div class="d-flex justify-content-end">
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save me-1"></i> Ayarları Kaydet
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Test e-postası gönderme
    document.getElementById('testEmail').addEventListener('click', function() {
        // AJAX ile e-posta gönderme işlemi
        alert('Bu özellik henüz aktif değil.');
    });
    
    // Manuel yedek alma
    document.getElementById('manualBackup').addEventListener('click', function() {
        // AJAX ile yedek alma işlemi
        alert('Bu özellik henüz aktif değil.');
    });
});
</script>

<?php
// Alt kısım
include '../../includes/footer.php';
?> 