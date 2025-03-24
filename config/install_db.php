<?php
/**
 * Veritabanı Kurulum Dosyası
 * 
 * Bu dosya, uygulamanın ihtiyaç duyduğu veritabanı tablolarını oluşturur.
 */

// Veritabanı bağlantısını yükle
require_once 'db.php';

// Hata raporlamasını etkinleştir
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

echo '<h1>Veritabanı Kurulumu</h1>';

try {
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Kullanıcılar tablosu
    $sql = "CREATE TABLE IF NOT EXISTS kullanicilar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
        sifre VARCHAR(255) NOT NULL,
        ad_soyad VARCHAR(100) NOT NULL,
        email VARCHAR(100) NOT NULL,
        rol ENUM('admin', 'kullanici') NOT NULL DEFAULT 'kullanici',
        aktif TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo '<p>Kullanıcılar tablosu oluşturuldu.</p>';
    
    // Admin kullanıcısı ekle (şifre: admin123)
    $kullanici_kontrol = $db->query("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = 'admin'")->fetchColumn();
    if($kullanici_kontrol == 0) {
        $sql = "INSERT INTO kullanicilar (kullanici_adi, sifre, ad_soyad, email, rol) 
                VALUES ('admin', '".password_hash('admin123', PASSWORD_DEFAULT)."', 'Sistem Yöneticisi', 'admin@erpsistem.com', 'admin')";
        $db->exec($sql);
        echo '<p>Admin kullanıcısı oluşturuldu. (Kullanıcı adı: admin, Şifre: admin123)</p>';
    } else {
        echo '<p>Admin kullanıcısı zaten mevcut.</p>';
    }
    
    // Cariler tablosu
    $sql = "CREATE TABLE IF NOT EXISTS cariler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        unvan VARCHAR(255) NOT NULL,
        vergi_no VARCHAR(20),
        adres TEXT,
        telefon VARCHAR(20),
        email VARCHAR(100),
        yetkili_kisi VARCHAR(100),
        borc DECIMAL(15,2) NOT NULL DEFAULT 0,
        alacak DECIMAL(15,2) NOT NULL DEFAULT 0,
        bakiye DECIMAL(15,2) NOT NULL DEFAULT 0,
        olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo '<p>Cariler tablosu oluşturuldu.</p>';
    
    // Ürünler tablosu
    $sql = "CREATE TABLE IF NOT EXISTS urunler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kod VARCHAR(50) NOT NULL UNIQUE,
        stok_kodu VARCHAR(50) NOT NULL,
        ad VARCHAR(255) NOT NULL,
        kategori VARCHAR(100),
        birim VARCHAR(20) NOT NULL,
        fiyat DECIMAL(15,2) NOT NULL DEFAULT 0,
        kdv_orani INT NOT NULL DEFAULT 18,
        aciklama TEXT,
        olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo '<p>Ürünler tablosu oluşturuldu.</p>';
    
    // Stok tablosu
    $sql = "CREATE TABLE IF NOT EXISTS stok (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stok_kodu VARCHAR(50) NOT NULL UNIQUE,
        miktar DECIMAL(15,3) NOT NULL DEFAULT 0,
        rezerve_miktar DECIMAL(15,3) NOT NULL DEFAULT 0,
        kritik_seviye DECIMAL(15,3),
        son_guncelleme TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo '<p>Stok tablosu oluşturuldu.</p>';
    
    // Stok hareketleri tablosu
    $sql = "CREATE TABLE IF NOT EXISTS stok_hareketleri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        stok_kodu VARCHAR(50) NOT NULL,
        hareket_tipi ENUM('Giris', 'Cikis') NOT NULL,
        miktar DECIMAL(15,3) NOT NULL,
        birim_fiyat DECIMAL(15,2) NOT NULL,
        toplam_tutar DECIMAL(15,2) NOT NULL,
        referans_id INT,
        referans_tipi VARCHAR(50),
        aciklama TEXT,
        hareket_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (stok_kodu) REFERENCES stok(stok_kodu)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo '<p>Stok hareketleri tablosu oluşturuldu.</p>';
    
    // İrsaliyeler tablosu
    $sql = "CREATE TABLE IF NOT EXISTS irsaliyeler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        irsaliye_no VARCHAR(20) NOT NULL UNIQUE,
        tarih DATE NOT NULL,
        cari_id INT NOT NULL,
        toplam_tutar DECIMAL(15,2) NOT NULL DEFAULT 0,
        durum ENUM('Beklemede', 'Onaylandı', 'İptal') NOT NULL DEFAULT 'Beklemede',
        olusturan_id INT NOT NULL,
        olusturma_tarihi TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        onaylayan_id INT,
        onay_tarihi TIMESTAMP NULL,
        iptal_eden_id INT,
        iptal_tarihi TIMESTAMP NULL,
        FOREIGN KEY (cari_id) REFERENCES cariler(id),
        FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo '<p>İrsaliyeler tablosu oluşturuldu.</p>';
    
    // İrsaliye kalemleri tablosu
    $sql = "CREATE TABLE IF NOT EXISTS irsaliye_kalemleri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        irsaliye_id INT NOT NULL,
        urun_id INT NOT NULL,
        miktar DECIMAL(15,3) NOT NULL,
        birim_fiyat DECIMAL(15,2) NOT NULL,
        toplam_tutar DECIMAL(15,2) NOT NULL,
        FOREIGN KEY (irsaliye_id) REFERENCES irsaliyeler(id) ON DELETE CASCADE,
        FOREIGN KEY (urun_id) REFERENCES urunler(id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";
    $db->exec($sql);
    echo '<p>İrsaliye kalemleri tablosu oluşturuldu.</p>';
    
    // Örnek veri ekleme

    // Örnek müşteri ekleme
    $cari_kontrol = $db->query("SELECT COUNT(*) FROM cariler")->fetchColumn();
    if($cari_kontrol == 0) {
        $sql = "INSERT INTO cariler (unvan, vergi_no, adres, telefon, email, yetkili_kisi) 
                VALUES 
                ('ABC Ltd. Şti.', '1234567890', 'Örnek Mah. Test Cad. No:1 İstanbul', '0212 123 4567', 'info@abcltd.com', 'Ahmet Yılmaz'),
                ('XYZ A.Ş.', '0987654321', 'Deneme Sok. No:5 Ankara', '0312 987 6543', 'iletisim@xyzas.com', 'Ayşe Demir')";
        $db->exec($sql);
        echo '<p>Örnek müşteriler eklendi.</p>';
    }
    
    // Örnek ürün ve stok ekleme
    $urun_kontrol = $db->query("SELECT COUNT(*) FROM urunler")->fetchColumn();
    if($urun_kontrol == 0) {
        try {
            $db->beginTransaction();
            
            // Ürün 1
            $sql = "INSERT INTO urunler (kod, stok_kodu, ad, kategori, birim, fiyat, kdv_orani, aciklama) 
                    VALUES ('ÜRÜN-001', 'STK001', 'Laptop Bilgisayar', 'Elektronik', 'Adet', 5000.00, 18, 'Yüksek performanslı dizüstü bilgisayar')";
            $db->exec($sql);
            
            $sql = "INSERT INTO stok (stok_kodu, miktar, kritik_seviye) VALUES ('STK001', 10.000, 3.000)";
            $db->exec($sql);
            
            // Ürün 2
            $sql = "INSERT INTO urunler (kod, stok_kodu, ad, kategori, birim, fiyat, kdv_orani, aciklama) 
                    VALUES ('ÜRÜN-002', 'STK002', 'Akıllı Telefon', 'Elektronik', 'Adet', 3000.00, 18, '64GB Hafızalı akıllı telefon')";
            $db->exec($sql);
            
            $sql = "INSERT INTO stok (stok_kodu, miktar, kritik_seviye) VALUES ('STK002', 20.000, 5.000)";
            $db->exec($sql);
            
            // Ürün 3
            $sql = "INSERT INTO urunler (kod, stok_kodu, ad, kategori, birim, fiyat, kdv_orani, aciklama) 
                    VALUES ('ÜRÜN-003', 'STK003', 'Ofis Koltuğu', 'Mobilya', 'Adet', 800.00, 18, 'Ergonomik ofis sandalyesi')";
            $db->exec($sql);
            
            $sql = "INSERT INTO stok (stok_kodu, miktar, kritik_seviye) VALUES ('STK003', 15.000, 4.000)";
            $db->exec($sql);
            
            $db->commit();
            echo '<p>Örnek ürünler ve stok bilgileri eklendi.</p>';
        } catch (Exception $e) {
            $db->rollBack();
            echo '<p>Hata: ' . $e->getMessage() . '</p>';
        }
    }
    
    echo '<h2>Veritabanı kurulumu tamamlandı!</h2>';
    echo '<p><a href="/login.php">Giriş sayfasına gitmek için tıklayın</a></p>';
    echo '<p><a href="/config/temp_login.php">Geçici olarak giriş yapmak için tıklayın</a></p>';
    
} catch(PDOException $e) {
    echo '<h2>Veritabanı kurulumu sırasında bir hata oluştu!</h2>';
    echo '<p>Hata: ' . $e->getMessage() . '</p>';
} 