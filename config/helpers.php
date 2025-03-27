<?php
/**
 * ERP Sistem yardımcı fonksiyonlar dosyası
 */

require_once __DIR__ . '/db.php'; // Veritabanı bağlantısını dahil ediyoruz

/**
 * Ürünün birim bilgisini getirir
 * 
 * @param int $birimId Birim ID
 * @return string Birim kodu, bulunamazsa boş string döner
 */
function getBirimKodu($birimId) {
    global $db;
    
    if (empty($birimId)) {
        return '';
    }
    
    try {
        $query = "SELECT KOD FROM stk_birim WHERE ID = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$birimId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['KOD'] : '';
    } catch (Exception $e) {
        error_log('Birim kodu alınamadı: ' . $e->getMessage());
        return '';
    }
}

/**
 * Ürünün GRUP tablosundan birim bilgisini getirir
 * 
 * @param int $birimId Birim ID
 * @return string Birim kodu, bulunamazsa boş string döner
 */
function getGrupBirimKodu($birimId) {
    global $db;
    
    if (empty($birimId)) {
        return '';
    }
    
    try {
        $query = "SELECT KOD FROM grup WHERE ID = ?";
        $stmt = $db->prepare($query);
        $stmt->execute([$birimId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $result ? $result['KOD'] : '';
    } catch (Exception $e) {
        error_log('Grup birim kodu alınamadı: ' . $e->getMessage());
        return '';
    }
}

/**
 * Ürünün birim bilgisini getiren genel fonksiyon
 * Önce stk_birim tablosundan arar, bulamazsa grup tablosundan arar
 * 
 * @param int $birimId Birim ID
 * @param string $table Tabloyu zorlamak için 'stk_birim' veya 'grup' değeri verilebilir
 * @return string Birim kodu, bulunamazsa boş string döner
 */
function getBirim($birimId, $table = null) {
    if (empty($birimId)) {
        return '';
    }
    
    try {
        if ($table === 'stk_birim') {
            return getBirimKodu($birimId);
        } elseif ($table === 'grup') {
            return getGrupBirimKodu($birimId);
        }
        
        // Önce stk_birim'den dene
        $birim = getBirimKodu($birimId);
        
        // Bulunamazsa grup tablosundan dene
        if (empty($birim)) {
            $birim = getGrupBirimKodu($birimId);
        }
        
        return $birim;
    } catch (Exception $e) {
        error_log('Birim bilgisi alınamadı: ' . $e->getMessage());
        return '';
    }
} 