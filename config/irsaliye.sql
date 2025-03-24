-- İrsaliyeler tablosu
CREATE TABLE IF NOT EXISTS irsaliyeler (
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
    FOREIGN KEY (olusturan_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (onaylayan_id) REFERENCES kullanicilar(id),
    FOREIGN KEY (iptal_eden_id) REFERENCES kullanicilar(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- İrsaliye kalemleri tablosu
CREATE TABLE IF NOT EXISTS irsaliye_kalemleri (
    id INT AUTO_INCREMENT PRIMARY KEY,
    irsaliye_id INT NOT NULL,
    urun_id INT NOT NULL,
    miktar DECIMAL(15,3) NOT NULL,
    birim_fiyat DECIMAL(15,2) NOT NULL,
    toplam_tutar DECIMAL(15,2) NOT NULL,
    FOREIGN KEY (irsaliye_id) REFERENCES irsaliyeler(id) ON DELETE CASCADE,
    FOREIGN KEY (urun_id) REFERENCES urunler(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci; 