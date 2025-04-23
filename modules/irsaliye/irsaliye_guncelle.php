<?php

// irsaliye kalemlerini sil ve yeniden kaydet
$delete_query = "DELETE FROM stk_fis_har WHERE STKFISID = :irsaliye_id";
$delete_stmt = $db->prepare($delete_query);
$delete_stmt->execute(['irsaliye_id' => $irsaliye_id]);

// irsaliye kalemlerini yeniden kaydet
$query = "INSERT INTO stk_fis_har (
            SIRANO, BOLUMID, FISTIP, STKFISID, FISTAR, ISLEMTIPI,
            KARTTIPI, KARTID, MIKTAR, BIRIMID, FIYAT, TUTAR,
            KDVORANI, KDVTUTARI, CARIID, DEPOID, SUBEID, FATSIRANO
        ) VALUES (
            :sirano, 1, :fistip, :irsaliye_id, :tarih, :islemtipi,
            'S', :urun_id, :miktar, :birim_id, :birim_fiyat, :toplam_tutar,
            :kdv_orani, :kdv_tutari, :cari_id, :depo_id, :sube_id, :fatsirano
        )";
$stmt = $db->prepare($query);

foreach ($_POST['urun_id'] as $key => $urun_id) {
    if (!empty($urun_id)) {
        // Ürün birim ID'sini alalım
        try {
            $stmt_birim = $db->prepare("SELECT sb.BIRIMID FROM stok_birim sb WHERE sb.STOKID = ? LIMIT 1");
            $stmt_birim->execute([$urun_id]);
            $birim_id = $stmt_birim->fetchColumn();
            
            // Eğer birim bulunamadıysa varsayılan değer ata
            if (!$birim_id) {
                $birim_id = 3; // Varsayılan birim ID
            }
        } catch (Exception $e) {
            // Hata olursa varsayılan değer kullan
            $birim_id = 3; // Varsayılan birim ID
            error_log("Birim ID bulunamadı: " . $e->getMessage());
        }

        // Sıra numarası
        $sirano = $key + 1;
        
        // KDV oranı ve tutarı hesapla
        $kdv_orani = isset($_POST['kdv_orani'][$key]) ? floatval($_POST['kdv_orani'][$key]) : 0;
        $tutar = floatval($_POST['kalem_toplam'][$key]);
        $kdv_tutari = isset($_POST['kalem_kdv_tutari'][$key]) ? floatval($_POST['kalem_kdv_tutari'][$key]) : 0;
        
        // Fiş tipi ve işlem tipi belirle
        $fistip = $irsaliye_tipi == 'alis' ? '10' : '20';
        $islemtipi = $irsaliye_tipi == 'alis' ? 'Giriş' : 'Çıkış';
        
        // Debug için kalem parametrelerini kaydet
        $kalem_params = [
            'sirano' => $sirano,
            'fistip' => $fistip,
            'irsaliye_id' => $irsaliye_id,
            'tarih' => $_POST['tarih'],
            'islemtipi' => $islemtipi,
            'urun_id' => $urun_id,
            'miktar' => $_POST['miktar'][$key],
            'birim_id' => $birim_id,
            'birim_fiyat' => $_POST['birim_fiyat'][$key],
            'toplam_tutar' => $tutar,
            'kdv_orani' => $kdv_orani,
            'kdv_tutari' => $kdv_tutari,
            'cari_id' => $_POST['cari_id'],
            'depo_id' => $_POST['depo_id'],
            'sube_id' => $_POST['sube_id'] ?? 1,
            'fatsirano' => $sirano // FATSIRANO, SIRANO ile aynı değeri alacak
        ];
        
        $debug_info['kalem_'.$key] = $kalem_params;
        
        try {
            $stmt->execute($kalem_params);
        } catch (PDOException $e) {
            $debug_info['kalem_error_'.$key] = $e->getMessage();
            throw $e;
        }
    }
} 