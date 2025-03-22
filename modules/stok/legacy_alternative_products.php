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
    $sql = "SELECT p.*, c.name as category_name 
           FROM products p 
           LEFT JOIN product_categories c ON p.category_id = c.id 
           WHERE p.id != :current_id 
           AND p.oem_no IS NOT NULL";
    
    $stmt = $db->prepare($sql);
    $stmt->bindParam(':current_id', $id);
    $stmt->execute();
    $all_products = $stmt->fetchAll();
    
    // Bulunan ürünleri önce filtreleyelim
    $muadil_urunler = [];
    
    foreach ($all_products as $product) {
        if (empty($product['oem_no'])) continue;
        
        // Bu ürünün OEM numaralarını da ayıralım
        $product_oem_numbers = preg_split('/\r\n|\r|\n/', $product['oem_no']);
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
            foreach ($product_oem_array as $product_oem) {
                // Tamamen aynı OEM numarası
                if (strcasecmp($own_oem, $product_oem) === 0) {
                    $is_match = true;
                    break 2;
                }
                // OEM numaraları birbirini içeriyor
                if (stripos($own_oem, $product_oem) !== false || stripos($product_oem, $own_oem) !== false) {
                    $is_match = true;
                    break 2;
                }
            }
        }
        
        if ($is_match) {
            $muadil_urunler[] = $product;
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
            echo htmlspecialchars($muadil['brand'] ?? '');
            if (!empty($muadil['model'])) {
                echo ' / ' . htmlspecialchars($muadil['model']);
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
            echo '<td class="text-end">' . number_format($muadil['current_stock'], 2, ',', '.') . ' ' . htmlspecialchars($muadil['unit']) . '</td>';
            echo '<td class="text-end">₺' . number_format($muadil['sale_price'], 2, ',', '.') . '</td>';
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