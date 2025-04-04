<?php
/**
 * Stok Modülü Yardımcı Fonksiyonları
 * Bu dosya, stok modülünde kullanılan ortak fonksiyonları içerir.
 */

/**
 * OEM numaralarını işler ve muadil ürün gruplarını günceller
 * 
 * @param PDO $db Veritabanı bağlantısı
 * @param int $product_id İşlem yapılacak ürün ID
 * @param string $oem_no OEM numaraları (birden fazla satır olabilir)
 * @param bool $clear_existing Mevcut OEM numaralarını temizle
 * @return array İşlem sonucu bilgilerini içeren dizi
 */
function processOEMNumbers($db, $product_id, $oem_no, $clear_existing = false) {
    try {
        $result = [
            'success' => true,
            'message' => '',
            'imported_count' => 0,
            'groups_updated' => 0
        ];
        
        // Eski OEM numaralarını temizle (eğer isteniyorsa)
        if ($clear_existing) {
            $stmt = $db->prepare("DELETE FROM oem_numbers WHERE product_id = ?");
            $stmt->execute([$product_id]);
        }
        
        // OEM numarası yoksa işlem yapma
        if (empty($oem_no)) {
            return $result;
        }
        
        // OEM numaralarını satırlara ayır
        $oem_numbers = preg_split('/\r\n|\r|\n/', $oem_no);
        $groups_updated = [];
        
        foreach ($oem_numbers as $oem_line) {
            $oem_trim = trim($oem_line);
            if (empty($oem_trim)) continue;
            
            // Bu OEM numarasını tabloya ekle
            $stmt = $db->prepare("INSERT IGNORE INTO oem_numbers (product_id, oem_no) VALUES (?, ?)");
            $stmt->execute([$product_id, $oem_trim]);
            
            if ($stmt->rowCount() > 0) {
                $result['imported_count']++;
            }
            
            // Bu OEM numarasına sahip başka ürünler var mı kontrol et (muadil grup için)
            $stmt = $db->prepare("SELECT DISTINCT product_id FROM oem_numbers WHERE oem_no = ? AND product_id != ?");
            $stmt->execute([$oem_trim, $product_id]);
            $related_products = $stmt->fetchAll(PDO::FETCH_COLUMN);
            
            if (!empty($related_products)) {
                // Bu ürünün dahil olduğu mevcut bir alternatif grup var mı kontrol et
                $stmt = $db->prepare("SELECT alternative_group_id FROM product_alternatives WHERE product_id = ?");
                $stmt->execute([$product_id]);
                $existing_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                
                // Related products'ın dahil olduğu grupları bul
                $related_groups = [];
                foreach ($related_products as $rel_pid) {
                    $stmt = $db->prepare("SELECT alternative_group_id FROM product_alternatives WHERE product_id = ?");
                    $stmt->execute([$rel_pid]);
                    $rel_groups = $stmt->fetchAll(PDO::FETCH_COLUMN);
                    $related_groups = array_merge($related_groups, $rel_groups);
                }
                $related_groups = array_unique($related_groups);
                
                // Mevcut bir grup varsa ona ekle, yoksa yeni oluştur
                $target_group = null;
                
                // Önce mevcut grupları kontrol et
                $common_groups = array_intersect($existing_groups, $related_groups);
                if (!empty($common_groups)) {
                    // Ortak bir grup varsa onu kullan
                    $target_group = reset($common_groups);
                } elseif (!empty($related_groups)) {
                    // İlişkili ürünlerin bir grubu varsa ona ekle
                    $target_group = reset($related_groups);
                } elseif (!empty($existing_groups)) {
                    // Bu ürünün bir grubu varsa ona ekle
                    $target_group = reset($existing_groups);
                }
                
                // Hiç grup yoksa yeni oluştur
                if ($target_group === null) {
                    $stmt = $db->prepare("INSERT INTO alternative_groups (group_name) VALUES (?)");
                    $group_name = "Muadil Grup " . $oem_trim;
                    $stmt->execute([$group_name]);
                    $target_group = $db->lastInsertId();
                }
                
                // Bu ürünü gruba ekle
                $stmt = $db->prepare("INSERT IGNORE INTO product_alternatives (product_id, alternative_group_id) VALUES (?, ?)");
                $stmt->execute([$product_id, $target_group]);
                
                // İlişkili ürünleri de aynı gruba ekle
                foreach ($related_products as $rel_pid) {
                    $stmt = $db->prepare("INSERT IGNORE INTO product_alternatives (product_id, alternative_group_id) VALUES (?, ?)");
                    $stmt->execute([$rel_pid, $target_group]);
                }
                
                // İşlenen grup ID'lerini takip et
                if (!in_array($target_group, $groups_updated)) {
                    $groups_updated[] = $target_group;
                }
            }
        }
        
        $result['groups_updated'] = count($groups_updated);
        $result['message'] = "Toplam {$result['imported_count']} OEM numarası işlendi ve {$result['groups_updated']} muadil grup güncellendi.";
        
        return $result;
    } catch (Exception $e) {
        return [
            'success' => false,
            'message' => "OEM verileri işlenirken hata: " . $e->getMessage(),
            'imported_count' => 0,
            'groups_updated' => 0
        ];
    }
}

/**
 * EAN-13 barkod oluşturur
 * 
 * @param string $prefix Barkodu başlangıç rakamları (ülke kodu vs.)
 * @return string EAN-13 formatında oluşturulan barkod
 */
function generateEAN13($prefix = '9670000', $db = null) {
    // EAN-13 formatı toplam 13 haneden oluşur
    // İlk 12 hane + 1 kontrol hanesi
    
    // Prefix zaten belirtilmiş (ülke/firma kodu gibi)
    $code = $prefix;
    
    // Son kullanılan seri numarasını bul
    $last_number = 0;
    
    if ($db) {
        try {
            $stmt = $db->prepare("SELECT MAX(KOD) as max_code FROM stok WHERE KOD LIKE ?");
            $stmt->execute([$prefix . '%']);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result && $result['max_code']) {
                // Prefix'i kaldır ve sıra numarasını al
                $last_code = $result['max_code'];
                $sequence_part = substr($last_code, strlen($prefix), -1); // Son kontrolsüz rakamı da çıkar
                $last_number = (int)$sequence_part;
            }
        } catch (Exception $e) {
            // Hata durumunda 0'dan başla
            $last_number = 0;
        }
    }
    
    // Sıradaki numarayı oluştur
    $next_number = $last_number + 1;
    
    // Kalan haneleri sıfırla doldur ve sıra numarasını ekle
    $sequence_length = 12 - strlen($prefix);
    $sequence_str = str_pad($next_number, $sequence_length, '0', STR_PAD_LEFT);
    
    // Tam kodu oluştur (prefix + sıra numarası)
    $code = $prefix . $sequence_str;
    
    // Kontrol hanesi hesaplama (EAN-13 algoritması)
    $sum = 0;
    for ($i = 0; $i < 12; $i++) {
        $sum += $code[$i] * ($i % 2 == 0 ? 1 : 3);
    }
    $checksum = (10 - ($sum % 10)) % 10;
    
    // Son kodu döndür (12 hane + kontrol hanesi)
    return $code . $checksum;
} 