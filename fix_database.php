<?php
/**
 * ERP Sistem - Veritabanı Düzeltme
 * 
 * Bu dosya veritabanı ve gerekli tabloları oluşturur.
 */

// Veritabanı bağlantı bilgileri - config/db.php dosyasındaki ile aynı olmalı
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');

try {
    // PDO bağlantısı oluştur (veritabanı adı olmadan)
    $db = new PDO(
        "mysql:host=" . DB_HOST . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        )
    );
    
    // Mevcut veritabanı yapısını kontrol et
    $db->exec("DROP DATABASE IF EXISTS erp_sistem");
    echo "Eski veritabanı silindi.<br>";
    
    // Veritabanını oluştur
    $db->exec("CREATE DATABASE IF NOT EXISTS erp_sistem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Veritabanı oluşturuldu.<br>";
    
    // Veritabanını seç
    $db->exec("USE erp_sistem");
    
    // Kullanıcılar tablosu (login.php ve diğer dosyalarla uyumlu olacak şekilde)
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INT AUTO_INCREMENT PRIMARY KEY,
        username VARCHAR(50) NOT NULL UNIQUE,
        password VARCHAR(255) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'yonetici', 'muhasebe', 'satis', 'depo') NOT NULL DEFAULT 'admin',
        last_login DATETIME
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Users tablosu oluşturuldu.<br>";
    
    // Kullanıcılar tablosu - Türkçe versiyon
    $db->exec("CREATE TABLE IF NOT EXISTS kullanicilar (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_adi VARCHAR(50) NOT NULL UNIQUE,
        sifre VARCHAR(255) NOT NULL,
        ad VARCHAR(50) NOT NULL,
        soyad VARCHAR(50) NOT NULL,
        email VARCHAR(100) NOT NULL UNIQUE,
        telefon VARCHAR(20),
        rol ENUM('admin', 'yonetici', 'muhasebe', 'satis', 'depo') NOT NULL DEFAULT 'satis',
        durum TINYINT(1) NOT NULL DEFAULT 1,
        son_giris DATETIME,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Kullanicilar tablosu oluşturuldu.<br>";
    
    // Kullanıcı yetkileri tablosu - İngilizce isimle
    $db->exec("CREATE TABLE IF NOT EXISTS user_permissions (
        id INT AUTO_INCREMENT PRIMARY KEY,
        user_id INT NOT NULL,
        module_name VARCHAR(50) NOT NULL,
        permission VARCHAR(50) NOT NULL DEFAULT 'read',
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        UNIQUE KEY user_permission (user_id, module_name)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "User_permissions tablosu oluşturuldu.<br>";
    
    // Kullanıcı yetkileri tablosu - Türkçe isimle
    $db->exec("CREATE TABLE IF NOT EXISTS kullanici_yetkileri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kullanici_id INT NOT NULL,
        yetki_adi VARCHAR(50) NOT NULL,
        FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
        UNIQUE KEY kullanici_yetki (kullanici_id, yetki_adi)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Kullanici_yetkileri tablosu oluşturuldu.<br>";
    
    // Cariler Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS cariler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cari_kodu VARCHAR(50) NOT NULL UNIQUE,
        cari_tipi ENUM('musteri', 'tedarikci', 'her_ikisi') NOT NULL DEFAULT 'musteri',
        firma_unvani VARCHAR(255) NOT NULL,
        yetkili_ad VARCHAR(100),
        yetkili_soyad VARCHAR(100),
        vergi_dairesi VARCHAR(100),
        vergi_no VARCHAR(20),
        tc_kimlik_no VARCHAR(11),
        adres TEXT,
        il VARCHAR(50),
        ilce VARCHAR(50),
        posta_kodu VARCHAR(20),
        telefon VARCHAR(20),
        cep_telefon VARCHAR(20),
        faks VARCHAR(20),
        email VARCHAR(100),
        web_sitesi VARCHAR(255),
        banka_adi VARCHAR(100),
        sube_adi VARCHAR(100),
        hesap_no VARCHAR(50),
        iban VARCHAR(50),
        risk_limiti DECIMAL(15,2) DEFAULT 0.00,
        odeme_vade_suresi INT DEFAULT 0,
        iskonto_orani DECIMAL(5,2) DEFAULT 0.00,
        bakiye DECIMAL(15,2) DEFAULT 0.00,
        kategori VARCHAR(100),
        aciklama TEXT,
        durum TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Cariler tablosu oluşturuldu.<br>";
    
    // Cari İletişim Kişileri Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS cari_iletisim_kisileri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cari_id INT NOT NULL,
        ad VARCHAR(50) NOT NULL,
        soyad VARCHAR(50) NOT NULL,
        unvan VARCHAR(100),
        telefon VARCHAR(20),
        email VARCHAR(100),
        aciklama TEXT,
        durum TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (cari_id) REFERENCES cariler(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Cari_iletisim_kisileri tablosu oluşturuldu.<br>";
    
    // Cari Hesap Hareketleri Tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS cari_hareketler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        cari_id INT NOT NULL,
        islem_turu ENUM('alis_fatura', 'satis_fatura', 'tahsilat', 'odeme', 'cek', 'senet', 'iade', 'diger') NOT NULL,
        islem_no VARCHAR(50) NOT NULL,
        belge_no VARCHAR(50),
        aciklama TEXT,
        borc_tutari DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        alacak_tutari DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        bakiye DECIMAL(15,2) NOT NULL DEFAULT 0.00,
        vade_tarihi DATE,
        islem_tarihi DATETIME NOT NULL,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (cari_id) REFERENCES cariler(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Cari_hareketler tablosu oluşturuldu.<br>";
    
    // Müşteriler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS musteriler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        musteri_kodu VARCHAR(50) NOT NULL UNIQUE,
        ad VARCHAR(100) NOT NULL,
        soyad VARCHAR(100),
        firma_adi VARCHAR(255),
        vergi_dairesi VARCHAR(100),
        vergi_no VARCHAR(20),
        tc_kimlik_no VARCHAR(11),
        adres TEXT,
        il VARCHAR(50),
        ilce VARCHAR(50),
        telefon VARCHAR(20),
        email VARCHAR(100),
        web_sitesi VARCHAR(255),
        musteri_tipi ENUM('bireysel', 'kurumsal') NOT NULL DEFAULT 'bireysel',
        aciklama TEXT,
        durum TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Musteriler tablosu oluşturuldu.<br>";
    
    // Tedarikçiler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS tedarikciler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        tedarikci_kodu VARCHAR(50) NOT NULL UNIQUE,
        firma_adi VARCHAR(255) NOT NULL,
        yetkili_ad VARCHAR(100),
        yetkili_soyad VARCHAR(100),
        vergi_dairesi VARCHAR(100),
        vergi_no VARCHAR(20),
        adres TEXT,
        il VARCHAR(50),
        ilce VARCHAR(50),
        telefon VARCHAR(20),
        email VARCHAR(100),
        web_sitesi VARCHAR(255),
        aciklama TEXT,
        durum TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Tedarikciler tablosu oluşturuldu.<br>";
    
    // Ürün kategorileri tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS product_categories (
        id INT AUTO_INCREMENT PRIMARY KEY,
        name VARCHAR(100) NOT NULL,
        parent_id INT DEFAULT NULL,
        description TEXT,
        status ENUM('active', 'passive') NOT NULL DEFAULT 'active',
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
        created_by INT,
        updated_by INT,
        FOREIGN KEY (parent_id) REFERENCES product_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Product_categories tablosu oluşturuldu.<br>";
    
    // Kategoriler tablosu (Türkçe karşılık)
    $db->exec("CREATE TABLE IF NOT EXISTS kategoriler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        ust_kategori_id INT,
        ad VARCHAR(100) NOT NULL,
        aciklama TEXT,
        durum TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (ust_kategori_id) REFERENCES kategoriler(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Kategoriler tablosu oluşturuldu.<br>";
    
    // Ürünler tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS products (
        id INT AUTO_INCREMENT PRIMARY KEY,
        code VARCHAR(50) NOT NULL UNIQUE,
        name VARCHAR(255) NOT NULL,
        category_id INT,
        unit VARCHAR(20) NOT NULL DEFAULT 'adet',
        barcode VARCHAR(50),
        brand VARCHAR(100),
        model VARCHAR(100),
        description TEXT,
        purchase_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        sale_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        tax_rate DECIMAL(5,2) NOT NULL DEFAULT 18.00,
        stock_quantity INT NOT NULL DEFAULT 0,
        current_stock DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        min_stock INT NOT NULL DEFAULT 5,
        min_stock_level INT NOT NULL DEFAULT 5,
        location VARCHAR(50),
        status ENUM('active', 'passive') NOT NULL DEFAULT 'active',
        oem_no VARCHAR(100) DEFAULT NULL,
        cross_reference TEXT DEFAULT NULL,
        dimensions VARCHAR(100) DEFAULT NULL,
        shelf_code VARCHAR(50) DEFAULT NULL,
        vehicle_brand VARCHAR(100) DEFAULT NULL,
        vehicle_model VARCHAR(100) DEFAULT NULL,
        main_category VARCHAR(100) DEFAULT NULL,
        sub_category VARCHAR(100) DEFAULT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME ON UPDATE CURRENT_TIMESTAMP,
        created_by INT,
        updated_by INT,
        FOREIGN KEY (category_id) REFERENCES product_categories(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Products tablosu oluşturuldu.<br>";
    
    // Ürünler tablosu (Türkçe karşılık)
    $db->exec("CREATE TABLE IF NOT EXISTS urunler (
        id INT AUTO_INCREMENT PRIMARY KEY,
        kategori_id INT,
        marka_id INT,
        birim_id INT,
        stok_kodu VARCHAR(50) NOT NULL UNIQUE,
        barkod VARCHAR(50),
        ad VARCHAR(255) NOT NULL,
        aciklama TEXT,
        resim VARCHAR(255),
        alis_fiyati DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        satis_fiyati DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
        kdv_orani INT NOT NULL DEFAULT 18,
        stok_miktari INT NOT NULL DEFAULT 0,
        mevcut_stok DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        min_stok_miktari INT NOT NULL DEFAULT 5,
        raf_no VARCHAR(50),
        durum TINYINT(1) NOT NULL DEFAULT 1,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Urunler tablosu oluşturuldu.<br>";
    
    // Stok hareketleri tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS stock_movements (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        movement_type ENUM('giris', 'cikis', 'transfer', 'adjustment', 'reservation') NOT NULL,
        quantity INT NOT NULL,
        unit_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        reference_id INT,
        reference_type VARCHAR(50),
        reference_no VARCHAR(50) DEFAULT NULL,
        description TEXT,
        movement_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        user_id INT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Stock_movements tablosu oluşturuldu.<br>";
    
    // Ürün alternatif bağlantıları tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS product_alternatives (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        alternative_id INT NOT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        created_by INT,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (alternative_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY product_alternative (product_id, alternative_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Product_alternatives tablosu oluşturuldu.<br>";
    
    // Muadil ürün grupları tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS alternative_groups (
        id INT AUTO_INCREMENT PRIMARY KEY,
        group_name VARCHAR(100),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Alternative_groups tablosu oluşturuldu.<br>";
    
    // Ürün-Muadil grup ilişki tablosu (yeni)
    $db->exec("CREATE TABLE IF NOT EXISTS product_alternative_groups (
        product_id INT NOT NULL,
        alternative_group_id INT NOT NULL,
        PRIMARY KEY (product_id, alternative_group_id),
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        FOREIGN KEY (alternative_group_id) REFERENCES alternative_groups(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Product_alternative_groups tablosu oluşturuldu.<br>";
    
    // OEM numaraları tablosu
    $db->exec("CREATE TABLE IF NOT EXISTS oem_numbers (
        id INT AUTO_INCREMENT PRIMARY KEY,
        product_id INT NOT NULL,
        oem_no VARCHAR(100) NOT NULL,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE CASCADE,
        UNIQUE KEY product_oem_unique (product_id, oem_no),
        INDEX (oem_no)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "OEM_numbers tablosu oluşturuldu.<br>";
    
    // Stok hareketleri tablosu (Türkçe)
    $db->exec("CREATE TABLE IF NOT EXISTS stok_hareketleri (
        id INT AUTO_INCREMENT PRIMARY KEY,
        urun_id INT NOT NULL,
        hareket_turu ENUM('giris', 'cikis', 'transfer', 'duzenleme', 'rezervasyon') NOT NULL,
        miktar INT NOT NULL,
        birim_fiyat DECIMAL(10,2) NOT NULL DEFAULT 0.00,
        referans_id INT,
        referans_turu VARCHAR(50),
        referans_no VARCHAR(50) DEFAULT NULL,
        aciklama TEXT,
        hareket_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        kullanici_id INT,
        olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE CASCADE
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    echo "Stok_hareketleri tablosu oluşturuldu.<br>";
    
    // Admin kullanıcısını oluştur
    $username = 'admin';
    $password = password_hash('123456', PASSWORD_DEFAULT);
    $email = 'admin@example.com';
    $fullName = 'Sistem Yöneticisi';
    $role = 'admin';
    
    // Kullanıcıyı ekle - İngilizce tabloya
    $stmt = $db->prepare("INSERT INTO users (username, password, email, full_name, role) VALUES (:username, :password, :email, :full_name, :role)");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':email', $email);
    $stmt->bindParam(':full_name', $fullName);
    $stmt->bindParam(':role', $role);
    $stmt->execute();
    
    $userId = $db->lastInsertId();
    echo "Admin kullanıcısı oluşturuldu (İngilizce tablo).<br>";
    
    // Kullanıcıyı ekle - Türkçe tabloya
    $stmt = $db->prepare("INSERT INTO kullanicilar (kullanici_adi, sifre, ad, soyad, email, rol) VALUES (:username, :password, 'Sistem', 'Yöneticisi', :email, 'admin')");
    $stmt->bindParam(':username', $username);
    $stmt->bindParam(':password', $password);
    $stmt->bindParam(':email', $email);
    $stmt->execute();
    
    $kullaniciId = $db->lastInsertId();
    echo "Admin kullanıcısı oluşturuldu (Türkçe tablo).<br>";
    
    // Kullanıcı yetkilerini ekle - İngilizce tabloya
    $permissions = [
        'muhasebe_cek_senet',
        'muhasebe_tahsilat',
        'muhasebe_odeme',
        'stok_urun',
        'stok_hareket',
        'fatura_satis',
        'fatura_alis'
    ];
    
    $stmt = $db->prepare("INSERT INTO user_permissions (user_id, module_name, permission) VALUES (:user_id, :module_name, 'full')");
    
    foreach ($permissions as $permission) {
        $stmt->bindParam(':user_id', $userId);
        $stmt->bindParam(':module_name', $permission);
        $stmt->execute();
    }
    
    // Kullanıcı yetkilerini ekle - Türkçe tabloya
    $stmt = $db->prepare("INSERT INTO kullanici_yetkileri (kullanici_id, yetki_adi) VALUES (:kullanici_id, :yetki_adi)");
    
    foreach ($permissions as $permission) {
        $stmt->bindParam(':kullanici_id', $kullaniciId);
        $stmt->bindParam(':yetki_adi', $permission);
        $stmt->execute();
    }
    
    // Test cari oluştur
    $cariler = [
        ['M001', 'musteri', 'ABC Elektronik Ltd. Şti.', 'Mehmet', 'Yılmaz', 'İstanbul VD', '12345678901', '', 'İstanbul', 'Kadıköy', '+90 212 555 11 22', 'info@abcelektronik.com'],
        ['M002', 'musteri', 'XYZ Bilgisayar A.Ş.', 'Ayşe', 'Kaya', 'Ankara VD', '23456789012', '', 'Ankara', 'Çankaya', '+90 312 444 33 44', 'info@xyzbilgisayar.com'],
        ['T001', 'tedarikci', 'Teknik Malzeme San. Tic.', 'Ali', 'Demir', 'İzmir VD', '34567890123', '', 'İzmir', 'Konak', '+90 232 333 55 66', 'info@teknikmalzeme.com'],
        ['T002', 'tedarikci', 'Global Supply Inc.', 'Ahmet', 'Şahin', 'Bursa VD', '45678901234', '', 'Bursa', 'Nilüfer', '+90 224 222 77 88', 'info@globalsupply.com'],
        ['B001', 'her_ikisi', 'Mega Ticaret A.Ş.', 'Zeynep', 'Öztürk', 'Antalya VD', '56789012345', '', 'Antalya', 'Muratpaşa', '+90 242 111 99 00', 'info@megaticaret.com']
    ];
    
    $cari_idleri = [];
    foreach ($cariler as $cari) {
        $db->exec("INSERT INTO cariler (cari_kodu, cari_tipi, firma_unvani, yetkili_ad, yetkili_soyad, vergi_dairesi, vergi_no, tc_kimlik_no, il, ilce, telefon, email) 
        VALUES ('{$cari[0]}', '{$cari[1]}', '{$cari[2]}', '{$cari[3]}', '{$cari[4]}', '{$cari[5]}', '{$cari[6]}', '{$cari[7]}', '{$cari[8]}', '{$cari[9]}', '{$cari[10]}', '{$cari[11]}')");
        $cari_idleri[] = $db->lastInsertId();
    }
    echo count($cariler) . " adet test cari oluşturuldu.<br>";
    
    // Test kategori oluştur - İngilizce
    $kategoriler = [
        ['Elektronik', 'Elektronik ürünler kategorisi'],
        ['Beyaz Eşya', 'Beyaz eşya ürünleri kategorisi'],
        ['Bilgisayar', 'Bilgisayar ve çevre birimleri'],
        ['Otomotiv', 'Otomotiv yedek parçaları'],
        ['Mobilya', 'Ev ve ofis mobilyaları']
    ];
    
    $kategori_idleri = [];
    foreach ($kategoriler as $kategori) {
        $stmt = $db->prepare("INSERT INTO product_categories (name, description) VALUES (?, ?)");
        $stmt->execute([$kategori[0], $kategori[1]]);
        $kategori_idleri[] = $db->lastInsertId();
    }
    echo count($kategoriler) . " adet test kategori oluşturuldu (İngilizce).<br>";
    
    // Test kategori oluştur - Türkçe
    $kategoriler_tr = [
        ['Elektronik', 'Elektronik ürünler kategorisi'],
        ['Beyaz Eşya', 'Beyaz eşya ürünleri kategorisi'],
        ['Bilgisayar', 'Bilgisayar ve çevre birimleri'],
        ['Otomotiv', 'Otomotiv yedek parçaları'],
        ['Mobilya', 'Ev ve ofis mobilyaları']
    ];
    
    foreach ($kategoriler_tr as $kategori) {
        $db->exec("INSERT INTO kategoriler (ad, aciklama) VALUES ('{$kategori[0]}', '{$kategori[1]}')");
    }
    echo count($kategoriler_tr) . " adet test kategori oluşturuldu (Türkçe).<br>";
    
    // Test ürünler oluştur - İngilizce
    $urunler = [
        ['E001', 'Samsung 55" Smart TV', $kategori_idleri[0], 'adet', 'Samsung', 'UE55TU8000', '4K Ultra HD Smart LED TV', 3500.00, 4200.00, 18.00, 15],
        ['E002', 'iPhone 13 Pro', $kategori_idleri[0], 'adet', 'Apple', 'iPhone 13 Pro', '128GB Grafit', 12000.00, 13999.00, 18.00, 20],
        ['B001', 'Arçelik Buzdolabı', $kategori_idleri[1], 'adet', 'Arçelik', '2876 NFK', 'No-Frost Kombi Buzdolabı', 4500.00, 5499.00, 18.00, 8],
        ['B002', 'Bosch Çamaşır Makinesi', $kategori_idleri[1], 'adet', 'Bosch', 'WAT24480TR', '9 kg 1200 Devir', 3800.00, 4599.00, 18.00, 6],
        ['C001', 'Lenovo Thinkpad', $kategori_idleri[2], 'adet', 'Lenovo', 'X1 Carbon', '14" Intel i7 16GB RAM 512GB SSD', 10000.00, 12500.00, 18.00, 12],
        ['O001', 'Valeo Debriyaj Seti', $kategori_idleri[3], 'adet', 'Valeo', 'VL-826033', 'Ford Focus 1.6 TDCI Debriyaj Seti', 850.00, 1200.00, 18.00, 25],
        ['M001', 'Çalışma Masası', $kategori_idleri[4], 'adet', 'Seray', 'S-120', '120x60 Çalışma Masası', 450.00, 599.00, 18.00, 10]
    ];
    
    $urun_idleri = [];
    foreach ($urunler as $urun) {
        $stmt = $db->prepare("INSERT INTO products (code, name, category_id, unit, brand, model, description, purchase_price, sale_price, tax_rate, stock_quantity, current_stock, min_stock, created_by) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 5, ?)");
        $stmt->execute([$urun[0], $urun[1], $urun[2], $urun[3], $urun[4], $urun[5], $urun[6], $urun[7], $urun[8], $urun[9], $urun[10], $urun[10], $userId]);
        $urun_idleri[] = $db->lastInsertId();
    }
    echo count($urunler) . " adet test ürün oluşturuldu (İngilizce).<br>";
    
    // Test ürünler oluştur - Türkçe
    $urunler_tr = [
        ['ET001', 'Samsung 55" Smart TV', 'Samsung', 3500.00, 4200.00, 15],
        ['ET002', 'iPhone 13 Pro', 'Apple', 12000.00, 13999.00, 20],
        ['BT001', 'Arçelik Buzdolabı', 'Arçelik', 4500.00, 5499.00, 8],
        ['BT002', 'Bosch Çamaşır Makinesi', 'Bosch', 3800.00, 4599.00, 6],
        ['CT001', 'Lenovo Thinkpad', 'Lenovo', 10000.00, 12500.00, 12]
    ];
    
    $urun_tr_idleri = [];
    foreach ($urunler_tr as $urun) {
        $db->exec("INSERT INTO urunler (stok_kodu, ad, marka_id, alis_fiyati, satis_fiyati, stok_miktari, mevcut_stok) 
        VALUES ('{$urun[0]}', '{$urun[1]}', 1, {$urun[3]}, {$urun[4]}, {$urun[5]}, {$urun[5]})");
        $urun_tr_idleri[] = $db->lastInsertId();
    }
    echo count($urunler_tr) . " adet test ürün oluşturuldu (Türkçe).<br>";
    
    // Test stok hareketleri oluştur - İngilizce
    $hareketler = [
        // [ürün_id, hareket_tipi, miktar, birim_fiyat, referans_tipi, referans_no, açıklama]
        [$urun_idleri[0], 'giris', 15, 3500.00, 'alis_fatura', 'ALF-2023001', 'Açılış stok girişi'],
        [$urun_idleri[1], 'giris', 20, 12000.00, 'alis_fatura', 'ALF-2023002', 'Tedarikçiden alım'],
        [$urun_idleri[2], 'giris', 8, 4500.00, 'alis_fatura', 'ALF-2023003', 'Stok girişi'],
        [$urun_idleri[3], 'giris', 6, 3800.00, 'stok_sayim', 'SS-2023001', 'Stok sayım düzeltmesi'],
        [$urun_idleri[0], 'cikis', 2, 4200.00, 'satis_fatura', 'STF-2023001', 'Müşteri satışı'],
        [$urun_idleri[1], 'cikis', 3, 13999.00, 'satis_fatura', 'STF-2023002', 'Müşteri satışı'],
        [$urun_idleri[2], 'cikis', 1, 5499.00, 'satis_fatura', 'STF-2023003', 'Showroom teşhir'],
        [$urun_idleri[4], 'giris', 12, 10000.00, 'alis_fatura', 'ALF-2023004', 'Tedarikçiden alım'],
        [$urun_idleri[5], 'giris', 25, 850.00, 'alis_fatura', 'ALF-2023005', 'Stok girişi'],
        [$urun_idleri[6], 'giris', 10, 450.00, 'alis_fatura', 'ALF-2023006', 'Stok girişi']
    ];
    
    foreach ($hareketler as $hareket) {
        $stmt = $db->prepare("INSERT INTO stock_movements (product_id, movement_type, quantity, unit_price, reference_type, reference_no, description, user_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$hareket[0], $hareket[1], $hareket[2], $hareket[3], $hareket[4], $hareket[5], $hareket[6], $userId]);
    }
    echo count($hareketler) . " adet test stok hareketi oluşturuldu (İngilizce).<br>";
    
    // Test stok hareketleri oluştur - Türkçe
    $hareketler_tr = [
        // [ürün_id, hareket_tipi, miktar, birim_fiyat, referans_tipi, referans_no, açıklama]
        [$urun_tr_idleri[0], 'giris', 15, 3500.00, 'alis_fatura', 'ALF-2023001', 'Açılış stok girişi'],
        [$urun_tr_idleri[1], 'giris', 20, 12000.00, 'alis_fatura', 'ALF-2023002', 'Tedarikçiden alım'],
        [$urun_tr_idleri[2], 'giris', 8, 4500.00, 'alis_fatura', 'ALF-2023003', 'Stok girişi'],
        [$urun_tr_idleri[0], 'cikis', 2, 4200.00, 'satis_fatura', 'STF-2023001', 'Müşteri satışı'],
        [$urun_tr_idleri[1], 'cikis', 3, 13999.00, 'satis_fatura', 'STF-2023002', 'Müşteri satışı']
    ];
    
    foreach ($hareketler_tr as $hareket) {
        $stmt = $db->prepare("INSERT INTO stok_hareketleri (urun_id, hareket_turu, miktar, birim_fiyat, referans_turu, referans_no, aciklama, kullanici_id) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$hareket[0], $hareket[1], $hareket[2], $hareket[3], $hareket[4], $hareket[5], $hareket[6], $kullaniciId]);
    }
    echo count($hareketler_tr) . " adet test stok hareketi oluşturuldu (Türkçe).<br>";
    
    echo "Kullanıcı yetkileri eklendi.<br>";
    echo "<hr>";
    echo "İşlem tamamlandı. Şimdi <a href='login.php'>login.php</a> sayfasına giderek giriş yapabilirsiniz.<br>";
    echo "Kullanıcı adı: $username<br>";
    echo "Şifre: 123456<br>";
    
    // İngilizce -> Türkçe veri aktarımı
    echo "<h2>İngilizce Tablolardan Türkçe Tablolara Veri Aktarımı</h2>";

    try {
        // Veritabanı bağlantısını kontrol et
        if (!$db) {
            throw new Exception("Veritabanı bağlantısı kurulamadı.");
        }

        // USERS -> KULLANICILAR
        echo "<h3>Users -> Kullanicilar</h3>";
        try {
            // Önce kullanıcılar tablosunu kontrol et
            $checkUsersStmt = $db->query("SELECT COUNT(*) FROM users");
            $usersCount = $checkUsersStmt->fetchColumn();

            if ($usersCount > 0) {
                // Kullanıcılar tablosu varsa ve veri içeriyorsa, aktarım yap
                $stmt = $db->query("SELECT * FROM users");
                $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($users as $user) {
                    // Kullanıcı zaten var mı diye kontrol et
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM kullanicilar WHERE kullanici_adi = :username");
                    $checkStmt->bindParam(':username', $user['username']);
                    $checkStmt->execute();
                    
                    if ($checkStmt->fetchColumn() == 0) {
                        // Kullanıcı yoksa ekle
                        $insertStmt = $db->prepare("INSERT INTO kullanicilar (
                            id, kullanici_adi, sifre, ad, soyad, email, yetki, durum, son_giris, olusturma_tarihi
                        ) VALUES (
                            :id, :username, :password, :name, :surname, :email, :role, :status, :last_login, :created_at
                        )");
                        
                        $name = $user['name'] ?? (isset($user['full_name']) ? explode(' ', $user['full_name'])[0] : '');
                        $surname = $user['surname'] ?? (isset($user['full_name']) && count(explode(' ', $user['full_name'])) > 1 ? explode(' ', $user['full_name'])[1] : '');
                        
                        $insertStmt->bindParam(':id', $user['id']);
                        $insertStmt->bindParam(':username', $user['username']);
                        $insertStmt->bindParam(':password', $user['password']);
                        $insertStmt->bindParam(':name', $name);
                        $insertStmt->bindParam(':surname', $surname);
                        $insertStmt->bindParam(':email', $user['email']);
                        $insertStmt->bindParam(':role', $user['role']);
                        $insertStmt->bindParam(':status', $user['status'] ?? 1);
                        $insertStmt->bindParam(':last_login', $user['last_login'] ?? null);
                        $insertStmt->bindParam(':created_at', $user['created_at'] ?? date('Y-m-d H:i:s'));
                        
                        $insertStmt->execute();
                        echo "Kullanıcı aktarıldı: " . $user['username'] . "<br>";
                    } else {
                        echo "Kullanıcı zaten var: " . $user['username'] . "<br>";
                    }
                }
            } else {
                echo "Users tablosunda aktarılacak veri bulunamadı.<br>";
            }
        } catch (PDOException $e) {
            echo "Kullanıcı aktarım hatası: " . $e->getMessage() . "<br>";
        }

        // PRODUCTS -> URUNLER
        echo "<h3>Products -> Urunler</h3>";
        try {
            // Önce ürünler tablosunu kontrol et
            $checkProductsStmt = $db->query("SELECT COUNT(*) FROM products");
            $productsCount = $checkProductsStmt->fetchColumn();

            if ($productsCount > 0) {
                // Ürünler tablosu varsa ve veri içeriyorsa, aktarım yap
                $stmt = $db->query("SELECT * FROM products");
                $products = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($products as $product) {
                    // Ürün zaten var mı diye kontrol et
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM urunler WHERE stok_kodu = :code");
                    $checkStmt->bindParam(':code', $product['code']);
                    $checkStmt->execute();
                    
                    if ($checkStmt->fetchColumn() == 0) {
                        // Ürün yoksa ekle
                        $insertStmt = $db->prepare("INSERT INTO urunler (
                            id, stok_kodu, ad, aciklama, kategori_id, marka_id, birim, mevcut_stok, alis_fiyati, 
                            satis_fiyati, kdv_orani, ozel_kod1, ozel_kod2, durum, olusturma_tarihi
                        ) VALUES (
                            :id, :code, :name, :description, :category_id, :brand_id, :unit, :current_stock, :purchase_price, 
                            :sale_price, :tax_rate, :special_code1, :special_code2, :status, :created_at
                        )");
                        
                        $insertStmt->bindParam(':id', $product['id']);
                        $insertStmt->bindParam(':code', $product['code']);
                        $insertStmt->bindParam(':name', $product['name']);
                        $insertStmt->bindParam(':description', $product['description'] ?? '');
                        $insertStmt->bindParam(':category_id', $product['category_id'] ?? null);
                        $insertStmt->bindParam(':brand_id', $product['brand_id'] ?? null);
                        $insertStmt->bindParam(':unit', $product['unit'] ?? 'adet');
                        $insertStmt->bindParam(':current_stock', $product['current_stock'] ?? 0);
                        $insertStmt->bindParam(':purchase_price', $product['purchase_price'] ?? 0);
                        $insertStmt->bindParam(':sale_price', $product['sale_price'] ?? 0);
                        $insertStmt->bindParam(':tax_rate', $product['tax_rate'] ?? 18);
                        $insertStmt->bindParam(':special_code1', $product['special_code1'] ?? '');
                        $insertStmt->bindParam(':special_code2', $product['special_code2'] ?? '');
                        $insertStmt->bindParam(':status', $product['status'] ?? 1);
                        $insertStmt->bindParam(':created_at', $product['created_at'] ?? date('Y-m-d H:i:s'));
                        
                        $insertStmt->execute();
                        echo "Ürün aktarıldı: " . $product['code'] . " - " . $product['name'] . "<br>";
                    } else {
                        echo "Ürün zaten var: " . $product['code'] . " - " . $product['name'] . "<br>";
                    }
                }
            } else {
                echo "Products tablosunda aktarılacak veri bulunamadı.<br>";
            }
        } catch (PDOException $e) {
            echo "Ürün aktarım hatası: " . $e->getMessage() . "<br>";
        }

        // PRODUCT_CATEGORIES -> KATEGORILER
        echo "<h3>Product Categories -> Kategoriler</h3>";
        try {
            // Önce kategoriler tablosunu kontrol et
            $checkCategoriesStmt = $db->query("SELECT COUNT(*) FROM product_categories");
            $categoriesCount = $checkCategoriesStmt->fetchColumn();

            if ($categoriesCount > 0) {
                // Kategoriler tablosu varsa ve veri içeriyorsa, aktarım yap
                $stmt = $db->query("SELECT * FROM product_categories");
                $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($categories as $category) {
                    // Kategori zaten var mı diye kontrol et
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM kategoriler WHERE kod = :code");
                    $checkStmt->bindParam(':code', $category['code']);
                    $checkStmt->execute();
                    
                    if ($checkStmt->fetchColumn() == 0) {
                        // Kategori yoksa ekle
                        $insertStmt = $db->prepare("INSERT INTO kategoriler (
                            id, kod, ad, aciklama, ust_kategori_id, durum, olusturma_tarihi
                        ) VALUES (
                            :id, :code, :name, :description, :parent_id, :status, :created_at
                        )");
                        
                        $insertStmt->bindParam(':id', $category['id']);
                        $insertStmt->bindParam(':code', $category['code']);
                        $insertStmt->bindParam(':name', $category['name']);
                        $insertStmt->bindParam(':description', $category['description'] ?? '');
                        $insertStmt->bindParam(':parent_id', $category['parent_id'] ?? null);
                        $insertStmt->bindParam(':status', $category['status'] ?? 1);
                        $insertStmt->bindParam(':created_at', $category['created_at'] ?? date('Y-m-d H:i:s'));
                        
                        $insertStmt->execute();
                        echo "Kategori aktarıldı: " . $category['code'] . " - " . $category['name'] . "<br>";
                    } else {
                        echo "Kategori zaten var: " . $category['code'] . " - " . $category['name'] . "<br>";
                    }
                }
            } else {
                echo "Product_categories tablosunda aktarılacak veri bulunamadı.<br>";
            }
        } catch (PDOException $e) {
            echo "Kategori aktarım hatası: " . $e->getMessage() . "<br>";
        }

        // STOCK_MOVEMENTS -> STOK_HAREKETLERI
        echo "<h3>Stock Movements -> Stok Hareketleri</h3>";
        try {
            // Önce stok hareketleri tablosunu kontrol et
            $checkMovementsStmt = $db->query("SELECT COUNT(*) FROM stock_movements");
            $movementsCount = $checkMovementsStmt->fetchColumn();

            if ($movementsCount > 0) {
                // Stok hareketleri tablosu varsa ve veri içeriyorsa, aktarım yap
                $stmt = $db->query("SELECT sm.*, p.code as product_code, p.name as product_name 
                                   FROM stock_movements sm 
                                   LEFT JOIN products p ON sm.product_id = p.id");
                $movements = $stmt->fetchAll(PDO::FETCH_ASSOC);

                foreach ($movements as $movement) {
                    // Hareket zaten var mı diye kontrol et (ürün ve tarih kombinasyonu)
                    $checkStmt = $db->prepare("SELECT COUNT(*) FROM stok_hareketleri 
                                             WHERE urun_id = :product_id 
                                             AND DATE_FORMAT(hareket_tarihi, '%Y-%m-%d %H:%i:%s') = :movement_date");
                    $movementDate = isset($movement['created_at']) ? $movement['created_at'] : (isset($movement['movement_date']) ? $movement['movement_date'] : date('Y-m-d H:i:s'));
                    
                    $checkStmt->bindParam(':product_id', $movement['product_id']);
                    $checkStmt->bindParam(':movement_date', $movementDate);
                    $checkStmt->execute();
                    
                    if ($checkStmt->fetchColumn() == 0) {
                        // Hareket yoksa ekle
                        $insertStmt = $db->prepare("INSERT INTO stok_hareketleri (
                            urun_id, hareket_turu, miktar, birim_fiyat, toplam_tutar, referans_no, referans_turu, 
                            aciklama, kullanici_id, hareket_tarihi
                        ) VALUES (
                            :product_id, :movement_type, :quantity, :unit_price, :total_amount, :reference_no, :reference_type,
                            :description, :user_id, :movement_date
                        )");
                        
                        $totalAmount = $movement['quantity'] * $movement['unit_price'];
                        
                        $insertStmt->bindParam(':product_id', $movement['product_id']);
                        $insertStmt->bindParam(':movement_type', $movement['movement_type']);
                        $insertStmt->bindParam(':quantity', $movement['quantity']);
                        $insertStmt->bindParam(':unit_price', $movement['unit_price']);
                        $insertStmt->bindParam(':total_amount', $totalAmount);
                        $insertStmt->bindParam(':reference_no', $movement['reference_no'] ?? '');
                        $insertStmt->bindParam(':reference_type', $movement['reference_type'] ?? 'diger');
                        $insertStmt->bindParam(':description', $movement['description'] ?? '');
                        $insertStmt->bindParam(':user_id', $movement['user_id'] ?? 1);
                        $insertStmt->bindParam(':movement_date', $movementDate);
                        
                        $insertStmt->execute();
                        echo "Stok hareketi aktarıldı: " . $movement['product_code'] . " - " . $movement['product_name'] . " (" . $movement['movement_type'] . ")<br>";
                    } else {
                        echo "Stok hareketi zaten var: " . $movement['product_code'] . " - " . $movement['product_name'] . " (" . $movement['movement_type'] . ")<br>";
                    }
                }
            } else {
                echo "Stock_movements tablosunda aktarılacak veri bulunamadı.<br>";
            }
        } catch (PDOException $e) {
            echo "Stok hareketi aktarım hatası: " . $e->getMessage() . "<br>";
        }

        // Diğer tablo aktarımları buraya eklenebilir
        
        echo "<h2>Veri Aktarımı Tamamlandı!</h2>";
        echo "<p>İngilizce tablolardaki veriler başarıyla Türkçe tablolara aktarılmıştır.</p>";
        
        // İngilizce tabloları kaldırmak için sorgu oluştur
        echo "<h2>İngilizce Tabloları Kaldırma</h2>";
        echo "<p>Aşağıdaki butona tıklayarak İngilizce tabloları veritabanından kaldırabilirsiniz:</p>";
        echo '<form method="post">';
        echo '<input type="hidden" name="remove_english_tables" value="1">';
        echo '<button type="submit" class="btn btn-danger">İngilizce Tabloları Kaldır</button>';
        echo '</form>';
        
        // İngilizce tabloları kaldır
        if (isset($_POST['remove_english_tables']) && $_POST['remove_english_tables'] == 1) {
            try {
                // Yabancı anahtar kontrolünü geçici olarak devre dışı bırak
                $db->exec("SET FOREIGN_KEY_CHECKS = 0");
                
                $englishTables = [
                    'users', 'user_permissions', 'products', 'product_categories', 'stock_movements',
                    'product_alternatives', 'alternative_groups', 'product_alternative_groups', 'oem_numbers',
                    'customers', 'suppliers', 'sales_invoices', 'sales_invoice_items', 'purchase_invoices', 
                    'purchase_invoice_items', 'system_settings'
                ];
                
                foreach ($englishTables as $table) {
                    $db->exec("DROP TABLE IF EXISTS $table");
                    echo "Tablo kaldırıldı: $table<br>";
                }
                
                // Yabancı anahtar kontrolünü tekrar etkinleştir
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                
                echo "<div class='alert alert-success mt-3'>İngilizce tablolar başarıyla kaldırıldı.</div>";
            } catch (PDOException $e) {
                // Hata durumunda yabancı anahtar kontrolünü tekrar etkinleştirmeyi unutma
                $db->exec("SET FOREIGN_KEY_CHECKS = 1");
                echo "<div class='alert alert-danger mt-3'>Tablolar kaldırılırken hata oluştu: " . $e->getMessage() . "</div>";
            }
        }
        
    } catch (Exception $e) {
        echo "<div class='alert alert-danger'>Hata: " . $e->getMessage() . "</div>";
    }
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}
?> 