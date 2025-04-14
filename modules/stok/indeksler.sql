-- Stok modülü indekslerini oluşturan SQL betiği
-- Doğrudan MySQL komut satırından çalıştırılabilir

-- Stok tablosu indeksleri
CREATE INDEX IF NOT EXISTS idx_stok_kod ON stok (KOD);
-- Eğer IF NOT EXISTS desteklenmiyorsa, aşağıdaki sorguyu kullanın:
-- CREATE INDEX idx_stok_kod ON stok (KOD);

CREATE INDEX IF NOT EXISTS idx_stok_adi ON stok (ADI);
-- Alternatif: CREATE INDEX idx_stok_adi ON stok (ADI);

-- STK_URUN_MIKTAR tablosu indeksleri
CREATE INDEX IF NOT EXISTS idx_stk_urun_miktar ON STK_URUN_MIKTAR (URUN_ID, MIKTAR);
-- Alternatif: CREATE INDEX idx_stk_urun_miktar ON STK_URUN_MIKTAR (URUN_ID, MIKTAR);

-- STK_FIYAT tablosu indeksleri
CREATE INDEX IF NOT EXISTS idx_stk_fiyat ON stk_fiyat (STOKID, TIP);
-- Alternatif: CREATE INDEX idx_stk_fiyat ON stk_fiyat (STOKID, TIP);

-- İstatistikleri güncelle
ANALYZE TABLE stok;
ANALYZE TABLE STK_URUN_MIKTAR;
ANALYZE TABLE stk_fiyat; 