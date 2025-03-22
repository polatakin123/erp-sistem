<?php
/**
 * Cari Modülü - Tablo Oluşturma Sayfası
 * 
 * Bu dosya cariler tablosunu ve ilgili diğer tabloları oluşturur.
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Cariler Tablosu Oluştur";

// Başarı ve hata mesajları için değişkenler
$success_message = "";
$error_message = "";

// Tablo oluşturma işlemi
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['create_tables'])) {
    try {
        // cariler tablosu
        $sql = "CREATE TABLE IF NOT EXISTS cariler (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            cari_kodu VARCHAR(20) NOT NULL,
            firma_unvani VARCHAR(255) NOT NULL,
            yetkili_ad VARCHAR(100),
            yetkili_soyad VARCHAR(100),
            email VARCHAR(100),
            telefon VARCHAR(20),
            cep_telefonu VARCHAR(20),
            fax VARCHAR(20),
            adres TEXT,
            il VARCHAR(50),
            ilce VARCHAR(50),
            posta_kodu VARCHAR(10),
            vergi_dairesi VARCHAR(100),
            vergi_no VARCHAR(20),
            cari_tipi ENUM('musteri', 'tedarikci', 'her_ikisi') NOT NULL DEFAULT 'musteri',
            bakiye DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            kredi_limiti DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            odeme_vade INT(11) NOT NULL DEFAULT 0,
            durum TINYINT(1) NOT NULL DEFAULT 1,
            web_sitesi VARCHAR(255),
            notlar TEXT,
            created_by INT(11),
            updated_by INT(11),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY unique_cari_kodu (cari_kodu),
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        
        // cari_hareketler tablosu
        $sql = "CREATE TABLE IF NOT EXISTS cari_hareketler (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            cari_id INT(11) NOT NULL,
            hareket_turu ENUM('tahsilat', 'odeme', 'borc', 'alacak', 'iade') NOT NULL,
            tutar DECIMAL(15,2) NOT NULL,
            aciklama TEXT,
            belge_no VARCHAR(50),
            belge_tarihi DATE,
            islem_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            created_by INT(11),
            updated_by INT(11),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (cari_id) REFERENCES cariler(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (updated_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        
        // cari_iletisimler tablosu (ek iletişim bilgileri için)
        $sql = "CREATE TABLE IF NOT EXISTS cari_iletisimler (
            id INT(11) NOT NULL AUTO_INCREMENT PRIMARY KEY,
            cari_id INT(11) NOT NULL,
            iletisim_turu ENUM('telefon', 'email', 'adres', 'diger') NOT NULL,
            iletisim_bilgisi VARCHAR(255) NOT NULL,
            aciklama VARCHAR(255),
            created_by INT(11),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (cari_id) REFERENCES cariler(id) ON DELETE CASCADE,
            FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE SET NULL
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
        
        $db->exec($sql);
        
        // Örnek cari verisi ekleyelim - önce kontrol edelim
        try {
            // Mevcut kayıtları kontrol et
            $check = $db->query("SELECT COUNT(*) FROM cariler WHERE cari_kodu IN ('MUS001', 'TED001', 'MUS002', 'HER001')");
            $exists = $check->fetchColumn();
            
            if ($exists == 0) {
                // Kayıtlar yoksa ekle
                $sql = "INSERT INTO cariler 
                    (cari_kodu, firma_unvani, yetkili_ad, yetkili_soyad, email, telefon, il, ilce, cari_tipi, bakiye, durum) 
                    VALUES 
                    ('MUS001', 'ABC Otomotiv Ltd. Şti.', 'Ahmet', 'Yılmaz', 'ahmet@abcoto.com', '0212 555 1234', 'İstanbul', 'Kadıköy', 'musteri', 0.00, 1),
                    ('TED001', 'XYZ Yedek Parça A.Ş.', 'Mehmet', 'Kaya', 'mkaya@xyz.com', '0216 444 5678', 'İstanbul', 'Ümraniye', 'tedarikci', 0.00, 1),
                    ('MUS002', 'Demir Oto Sanayi', 'Ali', 'Demir', 'ali@demiroto.com', '0312 333 9876', 'Ankara', 'Ostim', 'musteri', 2500.00, 1),
                    ('HER001', 'Yıldız Otomotiv', 'Ayşe', 'Yıldız', 'ayse@yildizoto.com', '0232 222 5555', 'İzmir', 'Bornova', 'her_ikisi', -1500.00, 1)";
                    
                $db->exec($sql);
                $success_message = "Cari tabloları başarıyla oluşturuldu ve örnek veriler eklendi.";
            } else {
                $success_message = "Cari tabloları başarıyla oluşturuldu. Örnek veriler zaten mevcut olduğu için tekrar eklenmedi.";
            }
        } catch (PDOException $e) {
            $error_message = "Örnek veri ekleme hatası: " . $e->getMessage();
        }
        
    } catch (PDOException $e) {
        $error_message = "Tablo oluşturma hatası: " . $e->getMessage();
    }
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Sayfa İçeriği -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cariler Tablosu Oluştur</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Cari Listesine Dön</a>
            </div>
        </div>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success"><?php echo $success_message; ?></div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-danger"><?php echo $error_message; ?></div>
    <?php endif; ?>

    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cari Modülü Tabloları</h6>
        </div>
        <div class="card-body">
            <p>Bu sayfayı kullanarak cari modülü için gerekli veritabanı tablolarını oluşturabilirsiniz:</p>
            <ul>
                <li><strong>cariler</strong> - Müşteri ve tedarikçi bilgilerini içeren ana tablo</li>
                <li><strong>cari_hareketler</strong> - Cari hesaplar ile ilgili borç, alacak, tahsilat, ödeme hareketleri</li>
                <li><strong>cari_iletisimler</strong> - Cariler için ek iletişim bilgileri</li>
            </ul>
            
            <form method="post" class="mt-4">
                <div class="alert alert-warning">
                    <i class="fas fa-exclamation-triangle"></i> 
                    <strong>Dikkat:</strong> Bu işlem mevcut cariler tablosunu silmez, ancak tablo yoksa yeni oluşturur ve örnek veriler ekler.
                </div>
                
                <button type="submit" name="create_tables" class="btn btn-primary">
                    <i class="fas fa-database"></i> Tabloları Oluştur
                </button>
            </form>
        </div>
    </div>
    
    <!-- Cari Modülü Açıklaması -->
    <div class="card shadow mb-4">
        <div class="card-header py-3">
            <h6 class="m-0 font-weight-bold text-primary">Cari Modülü Hakkında</h6>
        </div>
        <div class="card-body">
            <p>Cari modülü, işletmenizin müşteri ve tedarikçi bilgilerini yönetmek için kullanılır. Bu modül ile:</p>
            <ul>
                <li>Müşteri ve tedarikçi kayıtlarını tutabilir</li>
                <li>Cari hesapların bakiyelerini takip edebilir</li>
                <li>Tahsilat ve ödeme işlemlerini kaydedebilir</li>
                <li>Borç ve alacak durumlarını görüntüleyebilir</li>
                <li>Müşteri ve tedarikçilere özel ekstre raporları alabilirsiniz</li>
            </ul>
        </div>
    </div>
</div>

<?php include_once '../../includes/footer.php'; ?> 