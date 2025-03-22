-- ERP Sistem Veritabanı Şeması

-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS erp_sistem CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Veritabanını seç
USE erp_sistem;

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS kullanicilar (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kullanıcı yetkileri tablosu
CREATE TABLE IF NOT EXISTS kullanici_yetkileri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT NOT NULL,
    yetki_adi VARCHAR(50) NOT NULL,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE CASCADE,
    UNIQUE KEY kullanici_yetki (kullanici_id, yetki_adi)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cariler Tablosu (Müşteri ve Tedarikçileri Birleştirir)
CREATE TABLE IF NOT EXISTS cariler (
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
    olusturan_id INT NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleyen_id INT,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT,
    FOREIGN KEY (guncelleyen_id) REFERENCES kullanicilar(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cari İletişim Kişileri Tablosu
CREATE TABLE IF NOT EXISTS cari_iletisim_kisileri (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Cari Hesap Hareketleri Tablosu
CREATE TABLE IF NOT EXISTS cari_hareketler (
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
    kullanici_id INT NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cari_id) REFERENCES cariler(id) ON DELETE CASCADE,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kategoriler tablosu
CREATE TABLE IF NOT EXISTS kategoriler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ust_kategori_id INT,
    ad VARCHAR(100) NOT NULL,
    aciklama TEXT,
    durum TINYINT(1) NOT NULL DEFAULT 1,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (ust_kategori_id) REFERENCES kategoriler(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Markalar tablosu
CREATE TABLE IF NOT EXISTS markalar (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(100) NOT NULL,
    aciklama TEXT,
    durum TINYINT(1) NOT NULL DEFAULT 1,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Birimler tablosu
CREATE TABLE IF NOT EXISTS birimler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ad VARCHAR(50) NOT NULL,
    kisaltma VARCHAR(10) NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ürünler tablosu
CREATE TABLE IF NOT EXISTS urunler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kategori_id INT,
    marka_id INT,
    birim_id INT NOT NULL,
    stok_kodu VARCHAR(50) NOT NULL UNIQUE,
    barkod VARCHAR(50),
    ad VARCHAR(255) NOT NULL,
    aciklama TEXT,
    resim VARCHAR(255),
    alis_fiyati DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    satis_fiyati DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    kdv_orani INT NOT NULL DEFAULT 18,
    stok_miktari INT NOT NULL DEFAULT 0,
    min_stok_miktari INT NOT NULL DEFAULT 5,
    raf_no VARCHAR(50),
    durum TINYINT(1) NOT NULL DEFAULT 1,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (kategori_id) REFERENCES kategoriler(id) ON DELETE SET NULL,
    FOREIGN KEY (marka_id) REFERENCES markalar(id) ON DELETE SET NULL,
    FOREIGN KEY (birim_id) REFERENCES birimler(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ürün eşdeğerleri tablosu
CREATE TABLE IF NOT EXISTS urun_esdegerleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    urun_id INT NOT NULL,
    esdeger_urun_id INT NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE CASCADE,
    FOREIGN KEY (esdeger_urun_id) REFERENCES urunler(id) ON DELETE CASCADE,
    UNIQUE KEY urun_esdeger (urun_id, esdeger_urun_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Müşteriler tablosu
CREATE TABLE IF NOT EXISTS musteriler (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Tedarikçiler tablosu
CREATE TABLE IF NOT EXISTS tedarikciler (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satış faturaları tablosu
CREATE TABLE IF NOT EXISTS satis_faturalari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fatura_no VARCHAR(50) NOT NULL UNIQUE,
    musteri_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    fatura_tarihi DATE NOT NULL,
    vade_tarihi DATE,
    aciklama TEXT,
    alt_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    kdv_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    indirim_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    genel_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    odeme_durumu ENUM('odenmedi', 'kismi_odendi', 'odendi') NOT NULL DEFAULT 'odenmedi',
    fatura_tipi ENUM('normal', 'iade') NOT NULL DEFAULT 'normal',
    e_fatura_mi TINYINT(1) NOT NULL DEFAULT 0,
    e_fatura_no VARCHAR(50),
    iptal_edildi TINYINT(1) NOT NULL DEFAULT 0,
    iptal_tarihi DATETIME,
    iptal_aciklama TEXT,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE RESTRICT,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Satış fatura detayları tablosu
CREATE TABLE IF NOT EXISTS satis_fatura_detaylari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fatura_id INT NOT NULL,
    urun_id INT NOT NULL,
    miktar INT NOT NULL,
    birim_fiyat DECIMAL(10, 2) NOT NULL,
    kdv_orani INT NOT NULL,
    kdv_tutar DECIMAL(10, 2) NOT NULL,
    indirim_orani INT NOT NULL DEFAULT 0,
    indirim_tutar DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    net_tutar DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (fatura_id) REFERENCES satis_faturalari(id) ON DELETE CASCADE,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alış faturaları tablosu
CREATE TABLE IF NOT EXISTS alis_faturalari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fatura_no VARCHAR(50) NOT NULL UNIQUE,
    tedarikci_id INT NOT NULL,
    kullanici_id INT NOT NULL,
    fatura_tarihi DATE NOT NULL,
    vade_tarihi DATE,
    aciklama TEXT,
    alt_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    kdv_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    indirim_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    genel_toplam DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    odeme_durumu ENUM('odenmedi', 'kismi_odendi', 'odendi') NOT NULL DEFAULT 'odenmedi',
    fatura_tipi ENUM('normal', 'iade') NOT NULL DEFAULT 'normal',
    iptal_edildi TINYINT(1) NOT NULL DEFAULT 0,
    iptal_tarihi DATETIME,
    iptal_aciklama TEXT,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (tedarikci_id) REFERENCES tedarikciler(id) ON DELETE RESTRICT,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Alış fatura detayları tablosu
CREATE TABLE IF NOT EXISTS alis_fatura_detaylari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    fatura_id INT NOT NULL,
    urun_id INT NOT NULL,
    miktar INT NOT NULL,
    birim_fiyat DECIMAL(10, 2) NOT NULL,
    kdv_orani INT NOT NULL,
    kdv_tutar DECIMAL(10, 2) NOT NULL,
    indirim_orani INT NOT NULL DEFAULT 0,
    indirim_tutar DECIMAL(10, 2) NOT NULL DEFAULT 0.00,
    net_tutar DECIMAL(10, 2) NOT NULL,
    FOREIGN KEY (fatura_id) REFERENCES alis_faturalari(id) ON DELETE CASCADE,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Stok hareketleri tablosu
CREATE TABLE IF NOT EXISTS stok_hareketleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    urun_id INT NOT NULL,
    hareket_tipi ENUM('giris', 'cikis') NOT NULL,
    miktar INT NOT NULL,
    birim_fiyat DECIMAL(10, 2) NOT NULL,
    toplam_tutar DECIMAL(10, 2) NOT NULL,
    referans_no VARCHAR(50),
    referans_tip ENUM('alis_fatura', 'satis_fatura', 'stok_sayim', 'stok_transfer', 'diger') NOT NULL,
    aciklama TEXT,
    kullanici_id INT NOT NULL,
    islem_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (urun_id) REFERENCES urunler(id) ON DELETE RESTRICT,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Ödemeler tablosu
CREATE TABLE IF NOT EXISTS odemeler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    odeme_no VARCHAR(50) NOT NULL UNIQUE,
    odeme_tipi ENUM('tahsilat', 'odeme') NOT NULL,
    referans_no VARCHAR(50),
    referans_tip ENUM('satis_fatura', 'alis_fatura', 'diger') NOT NULL,
    musteri_id INT,
    tedarikci_id INT,
    odeme_yontemi ENUM('nakit', 'kredi_karti', 'banka_havalesi', 'cek', 'senet') NOT NULL,
    tutar DECIMAL(10, 2) NOT NULL,
    odeme_tarihi DATE NOT NULL,
    aciklama TEXT,
    kullanici_id INT NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE RESTRICT,
    FOREIGN KEY (tedarikci_id) REFERENCES tedarikciler(id) ON DELETE RESTRICT,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Çekler tablosu
CREATE TABLE IF NOT EXISTS cekler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    cek_no VARCHAR(50) NOT NULL UNIQUE,
    banka_adi VARCHAR(100) NOT NULL,
    sube_adi VARCHAR(100),
    hesap_no VARCHAR(50),
    cek_tipi ENUM('alinan', 'verilen') NOT NULL,
    musteri_id INT,
    tedarikci_id INT,
    tutar DECIMAL(10, 2) NOT NULL,
    kesim_tarihi DATE NOT NULL,
    vade_tarihi DATE NOT NULL,
    odeme_durumu ENUM('beklemede', 'tahsil_edildi', 'odendi', 'karsilik_yok') NOT NULL DEFAULT 'beklemede',
    islem_tarihi DATE,
    aciklama TEXT,
    kullanici_id INT NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE RESTRICT,
    FOREIGN KEY (tedarikci_id) REFERENCES tedarikciler(id) ON DELETE RESTRICT,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Senetler tablosu
CREATE TABLE IF NOT EXISTS senetler (
    id INT AUTO_INCREMENT PRIMARY KEY,
    senet_no VARCHAR(50) NOT NULL UNIQUE,
    senet_tipi ENUM('alinan', 'verilen') NOT NULL,
    musteri_id INT,
    tedarikci_id INT,
    tutar DECIMAL(10, 2) NOT NULL,
    kesim_tarihi DATE NOT NULL,
    vade_tarihi DATE NOT NULL,
    odeme_durumu ENUM('beklemede', 'tahsil_edildi', 'odendi', 'protestolu') NOT NULL DEFAULT 'beklemede',
    islem_tarihi DATE,
    aciklama TEXT,
    kullanici_id INT NOT NULL,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (musteri_id) REFERENCES musteriler(id) ON DELETE RESTRICT,
    FOREIGN KEY (tedarikci_id) REFERENCES tedarikciler(id) ON DELETE RESTRICT,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Kasa hareketleri tablosu
CREATE TABLE IF NOT EXISTS kasa_hareketleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    hareket_tipi ENUM('giris', 'cikis') NOT NULL,
    tutar DECIMAL(10, 2) NOT NULL,
    aciklama TEXT,
    referans_no VARCHAR(50),
    referans_tip ENUM('tahsilat', 'odeme', 'diger') NOT NULL,
    kullanici_id INT NOT NULL,
    islem_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sistem ayarları tablosu
CREATE TABLE IF NOT EXISTS sistem_ayarlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    ayar_adi VARCHAR(100) NOT NULL UNIQUE,
    ayar_degeri TEXT,
    aciklama TEXT,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Sistem log tablosu
CREATE TABLE IF NOT EXISTS sistem_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    kullanici_id INT,
    islem_tipi VARCHAR(50) NOT NULL,
    islem_detay TEXT,
    ip_adresi VARCHAR(50),
    islem_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (kullanici_id) REFERENCES kullanicilar(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- E-Fatura ayarları tablosu
CREATE TABLE IF NOT EXISTS e_fatura_ayarlari (
    id INT AUTO_INCREMENT PRIMARY KEY,
    entegrator VARCHAR(100) NOT NULL,
    kullanici_adi VARCHAR(100) NOT NULL,
    sifre VARCHAR(255) NOT NULL,
    api_anahtari VARCHAR(255),
    test_modu TINYINT(1) NOT NULL DEFAULT 1,
    aktif TINYINT(1) NOT NULL DEFAULT 0,
    olusturma_tarihi DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    guncelleme_tarihi DATETIME ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Varsayılan admin kullanıcısı oluştur (şifre: admin123)
INSERT INTO kullanicilar (kullanici_adi, sifre, ad, soyad, email, telefon, rol, durum)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'Admin', 'Kullanıcı', 'admin@example.com', '5551234567', 'admin', 1);

-- Admin kullanıcısına tüm yetkileri ekle
INSERT INTO kullanici_yetkileri (kullanici_id, yetki_adi) VALUES 
(1, 'stok_goruntule'),
(1, 'stok_ekle'),
(1, 'stok_duzenle'),
(1, 'stok_sil'),
(1, 'fatura_goruntule'),
(1, 'fatura_ekle'),
(1, 'fatura_duzenle'),
(1, 'fatura_sil'),
(1, 'musteri_goruntule'),
(1, 'musteri_ekle'),
(1, 'musteri_duzenle'),
(1, 'musteri_sil'),
(1, 'tedarikci_goruntule'),
(1, 'tedarikci_ekle'),
(1, 'tedarikci_duzenle'),
(1, 'tedarikci_sil'),
(1, 'rapor_goruntule'),
(1, 'kullanici_goruntule'),
(1, 'kullanici_ekle'),
(1, 'kullanici_duzenle'),
(1, 'kullanici_sil'),
(1, 'ayarlar_goruntule'),
(1, 'ayarlar_duzenle');

-- Varsayılan birimler
INSERT INTO birimler (ad, kisaltma) VALUES 
('Adet', 'AD'),
('Kutu', 'KT'),
('Çift', 'ÇF'),
('Kilogram', 'KG'),
('Litre', 'LT'),
('Metre', 'MT');

-- Varsayılan sistem ayarları
INSERT INTO sistem_ayarlari (ayar_adi, ayar_degeri, aciklama) VALUES
('firma_adi', 'ERP Sistem', 'Firma Adı'),
('firma_telefon', '0212 123 45 67', 'Firma Telefon'),
('firma_email', 'info@erpsistem.com', 'Firma E-posta'),
('firma_adres', 'Örnek Mah. Örnek Cad. No:123 İstanbul', 'Firma Adres'),
('firma_vergi_dairesi', 'Örnek Vergi Dairesi', 'Firma Vergi Dairesi'),
('firma_vergi_no', '1234567890', 'Firma Vergi No'),
('para_birimi', 'TL', 'Para Birimi'),
('kdv_orani', '18', 'Varsayılan KDV Oranı'),
('stok_uyari', '1', 'Stok Uyarılarını Göster (1: Evet, 0: Hayır)'),
('fatura_no_format', 'FTR-{YIL}{AY}{GUN}-{NO}', 'Fatura No Formatı'),
('satis_fatura_on_ek', 'SF', 'Satış Fatura Ön Eki'),
('alis_fatura_on_ek', 'AF', 'Alış Fatura Ön Eki'); 