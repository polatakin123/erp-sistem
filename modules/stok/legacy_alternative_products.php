<?php
/**
 * Legacy Alternative Products Script
 * 
 * Bu dosya, yeni muadil ürün sistemi kurulmadan önce OEM numarası eşleşmesine dayalı
 * eski sistemi kullanarak muadil ürünleri bulmak için kullanılır.
 * Yeni tablolar ve sistem tamamen aktif olana kadar geçici çözüm olarak kullanılacaktır.
 */

// Bu ürünün OEM numaralarını PHP tarafında işleyelim
$oem_array = [];
foreach ($oem_numbers as $oem) {
    $trimmed = trim($oem);
    if (!empty($trimmed)) {
        $oem_array[] = $trimmed;
    }
}

if (!empty($oem_array)) {
    // Tüm ürünleri çekelim ve PHP tarafında filtreleme yapalım
    $sql = "SELECT s.*, 
        s.ID as id,
        s.ADI as name, 
        s.KOD as code, 
        s.OZELGRUP4 as brand_id, 
        s.OZELGRUP5 as vehicle_brand_id, 
        s.OZELGRUP6 as vehicle_model_id, 
        s.DURUM as status, 
        s.ACIKLAMA as description, 
        g.KOD as unit,
        sfA.FIYAT as purchase_price,
        sfS.FIYAT as sale_price,
        kdv.ORAN as tax_rate,
        sum.MIKTAR as current_stock,
        s.MIN_STOK as min_stock,
        s.OZELALAN1 as oem_no,
        s.OZELGRUP1 as main_category_id,
        s.OZELGRUP2 as sub_category_id
        FROM stok s 
        LEFT JOIN stk_birim sb ON s.ID = sb.STOKID
        LEFT JOIN grup g ON sb.BIRIMID = g.ID
        LEFT JOIN stk_fiyat sfA ON s.ID = sfA.STOKID AND sfA.TIP = 'A'
        LEFT JOIN stk_fiyat sfS ON s.ID = sfS.STOKID AND sfS.TIP = 'S'
        LEFT JOIN kdv ON s.KDVID = kdv.ID
        LEFT JOIN stk_urun_miktar sum ON s.ID = sum.URUN_ID
        WHERE s.ID != :current_id 
        AND s.OZELALAN1 IS NOT NULL
        LIMIT 500"; // Sorguyu 500 ürünle sınırlayalım
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':current_id', $id);
    $stmt->execute();
    $all_products = $stmt->fetchAll();
    
    // Kategori adlarını al
    $kategori_ids = array_column(array_filter($all_products, function($p) { return !empty($p['main_category_id']); }), 'main_category_id');
    $kategori_map = [];
    
    if (!empty($kategori_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($kategori_ids), '?'));
            $stmt = $db->prepare("SELECT ID, KOD FROM grup WHERE ID IN ($placeholders)");
            $stmt->execute($kategori_ids);
            $kategoriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($kategoriler as $kat) {
                $kategori_map[$kat['ID']] = $kat['KOD'];
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Kategorileri alırken hata: ' . $e->getMessage() . '</div>';
        }
    }
    
    // Alt kategori adlarını al
    $alt_kategori_ids = array_column(array_filter($all_products, function($p) { return !empty($p['sub_category_id']); }), 'sub_category_id');
    $alt_kategori_map = [];
    
    if (!empty($alt_kategori_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($alt_kategori_ids), '?'));
            $stmt = $db->prepare("SELECT ID, KOD FROM grup WHERE ID IN ($placeholders)");
            $stmt->execute($alt_kategori_ids);
            $alt_kategoriler = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($alt_kategoriler as $kat) {
                $alt_kategori_map[$kat['ID']] = $kat['KOD'];
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Alt kategorileri alırken hata: ' . $e->getMessage() . '</div>';
        }
    }
    
    // Marka bilgilerini al
    $brand_ids = array_column(array_filter($all_products, function($p) { return !empty($p['brand_id']); }), 'brand_id');
    $brand_map = [];
    
    if (!empty($brand_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($brand_ids), '?'));
            $stmt = $db->prepare("SELECT ID, KOD FROM grup WHERE ID IN ($placeholders)");
            $stmt->execute($brand_ids);
            $brands = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($brands as $brand) {
                $brand_map[$brand['ID']] = $brand['KOD'];
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Markaları alırken hata: ' . $e->getMessage() . '</div>';
        }
    }
    
    // Model bilgilerini al
    $model_ids = array_column(array_filter($all_products, function($p) { return !empty($p['vehicle_model_id']); }), 'vehicle_model_id');
    $model_map = [];
    
    if (!empty($model_ids)) {
        try {
            $placeholders = implode(',', array_fill(0, count($model_ids), '?'));
            $stmt = $db->prepare("SELECT ID, KOD FROM grup WHERE ID IN ($placeholders)");
            $stmt->execute($model_ids);
            $models = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            foreach ($models as $model) {
                $model_map[$model['ID']] = $model['KOD'];
            }
        } catch (PDOException $e) {
            echo '<div class="alert alert-danger">Modelleri alırken hata: ' . $e->getMessage() . '</div>';
        }
    }
    
    // Bulunan ürünleri önce filtreleyelim
    $muadil_urunler = [];
    
    foreach ($all_products as $product) {
        if (empty($product['oem_no'])) continue;
        
        // Bu ürünün OEM numaralarını da ayıralım
        $product_oem_numbers = preg_split('/\r\n|\r|\n/', $product['oem_no'], -1, PREG_SPLIT_NO_EMPTY);
        $product_oem_array = [];
        
        foreach ($product_oem_numbers as $p_oem) {
            $p_trimmed = trim($p_oem);
            if (!empty($p_trimmed)) {
                $product_oem_array[] = $p_trimmed;
            }
        }
        
        // Her iki ürünün OEM numaralarını karşılaştıralım
        $is_match = false;
        
        foreach ($oem_array as $own_oem) {
            if (empty($own_oem)) continue;
            
            foreach ($product_oem_array as $product_oem) {
                if (empty($product_oem)) continue;
                
                // Tamamen aynı OEM numarası
                if (strcasecmp($own_oem, $product_oem) === 0) {
                    $is_match = true;
                    break 2;
                }
                // OEM numaraları birbirini içeriyor ve uzunluk uygun
                if ((strlen($own_oem) > 4 && strlen($product_oem) > 4) && 
                    (stripos($own_oem, $product_oem) !== false || stripos($product_oem, $own_oem) !== false)) {
                    $is_match = true;
                    break 2;
                }
            }
        }
        
        if ($is_match) {
            $muadil_urunler[] = $product;
            // Eşleşen ürün sayısı 20'yi geçerse döngüden çık
            if (count($muadil_urunler) >= 20) break;
        }
    }
    
    if (count($muadil_urunler) > 0) {
        echo '<div class="alert alert-warning mb-3">Bu ürünün muadil ürünleri eski sistem ile bulundu. Lütfen ürünü düzenleyerek yeni sisteme geçiriniz.</div>';
        
        echo '<div class="table-responsive">';
        echo '<table class="table table-bordered table-hover" width="100%" cellspacing="0">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Stok Kodu</th>';
        echo '<th>Ürün Adı</th>';
        echo '<th>Marka/Model</th>';
        echo '<th>OEM No</th>';
        echo '<th>Stok</th>';
        echo '<th>Satış Fiyatı</th>';
        echo '<th>İşlemler</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($muadil_urunler as $muadil) {
            echo '<tr>';
            echo '<td>' . htmlspecialchars($muadil['code']) . '</td>';
            echo '<td><a href="urun_detay.php?id=' . $muadil['id'] . '">' . displayHtml($muadil['name']) . '</a></td>';
            echo '<td>';
            if (!empty($muadil['brand_id']) && isset($brand_map[$muadil['brand_id']])) {
                echo htmlspecialchars($brand_map[$muadil['brand_id']]);
            }
            if (!empty($muadil['vehicle_model_id']) && isset($model_map[$muadil['vehicle_model_id']])) {
                echo ' / ' . htmlspecialchars($model_map[$muadil['vehicle_model_id']]);
            }
            echo '</td>';
            echo '<td>';
            $muadil_oem_numbers = !empty($muadil['oem_no']) ? preg_split('/\r\n|\r|\n/', $muadil['oem_no']) : [];
            foreach ($muadil_oem_numbers as $moem) {
                $moem_trim = trim($moem);
                if (empty($moem_trim)) continue;
                
                // OEM numarası eşleşiyorsa vurgula
                $is_match = false;
                foreach ($oem_array as $own_oem) {
                    // Tam eşleşme veya içerik eşleşmesi
                    if (strcasecmp($own_oem, $moem_trim) === 0 || 
                        stripos($own_oem, $moem_trim) !== false || 
                        stripos($moem_trim, $own_oem) !== false) {
                        $is_match = true;
                        break;
                    }
                }
                
                if ($is_match) {
                    echo '<span class="badge bg-success">' . htmlspecialchars($moem_trim) . '</span><br>';
                } else {
                    echo htmlspecialchars($moem_trim) . '<br>';
                }
            }
            echo '</td>';
            echo '<td class="text-end">' . number_format($muadil['current_stock'] ?? 0, 2, ',', '.') . ' ' . htmlspecialchars($muadil['unit'] ?? 'Adet') . '</td>';
            echo '<td class="text-end">₺' . number_format($muadil['sale_price'] ?? 0, 2, ',', '.') . '</td>';
            echo '<td class="text-center">';
            echo '<div class="btn-group">';
            echo '<a href="urun_detay.php?id=' . $muadil['id'] . '" class="btn btn-sm btn-info" title="Detay"><i class="fas fa-eye"></i></a>';
            echo '<a href="urun_duzenle.php?id=' . $muadil['id'] . '" class="btn btn-sm btn-primary" title="Düzenle"><i class="fas fa-edit"></i></a>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-info">Bu ürün için muadil ürün bulunamadı.</div>';
    }
} else {
    echo '<div class="alert alert-info">Geçerli OEM numarası bulunamadı.</div>';
} 