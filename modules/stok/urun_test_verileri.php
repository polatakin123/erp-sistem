<?php
/**
 * ERP Sistem - Test Verileri Ekleme Scripti
 * 
 * Bu script veritabanına 20 adet test ürünü ekler
 */

// Oturum başlat
session_start();

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Test Verileri Ekle";

// Üst kısmı dahil et
include_once '../../includes/header.php';

// Kategoriler için bir dizi oluşturalım (varsayılan olarak veritabanında olduğunu varsayıyoruz)
$kategoriler = [1, 2, 3]; // Örnek kategori ID'leri, veritabanınıza göre değişebilir

// Durumlar
$durumlar = ['active', 'passive'];

// Birimler
$birimler = ['Adet', 'Kg', 'Lt', 'Mt', 'Paket', 'Kutu', 'Çift'];

// Markalar
$markalar = ['Samsung', 'Apple', 'LG', 'Sony', 'Philips', 'Beko', 'Arçelik', 'Vestel', 'Bosch', 'Siemens'];

// Test ürünleri
$urunler = [
    // Elektronik ürünler
    ['code' => 'ELK001', 'name' => 'LED TV 55 inç', 'category_id' => 1, 'brand' => 'Samsung', 'model' => 'UHD-55TU8000', 'barcode' => '8801456987451', 'unit' => 'Adet', 'purchase_price' => 3500, 'sale_price' => 4200, 'tax_rate' => 18, 'current_stock' => 15, 'min_stock' => 5, 'description' => '55 inç Ultra HD Smart LED TV', 'status' => 'active'],
    ['code' => 'ELK002', 'name' => 'Buzdolabı No-Frost', 'category_id' => 1, 'brand' => 'Arçelik', 'model' => 'NF-584', 'barcode' => '7845963214521', 'unit' => 'Adet', 'purchase_price' => 4200, 'sale_price' => 4999, 'tax_rate' => 18, 'current_stock' => 8, 'min_stock' => 3, 'description' => 'No-Frost Teknolojili Buzdolabı', 'status' => 'active'],
    ['code' => 'ELK003', 'name' => 'Çamaşır Makinesi 9kg', 'category_id' => 1, 'brand' => 'Bosch', 'model' => 'WAT24480TR', 'barcode' => '4242356789012', 'unit' => 'Adet', 'purchase_price' => 3800, 'sale_price' => 4599, 'tax_rate' => 18, 'current_stock' => 6, 'min_stock' => 2, 'description' => '9 kg 1200 Devir Çamaşır Makinesi', 'status' => 'active'],
    ['code' => 'ELK004', 'name' => 'Laptop Pro', 'category_id' => 1, 'brand' => 'Apple', 'model' => 'MacBook Pro 13', 'barcode' => '6123456789014', 'unit' => 'Adet', 'purchase_price' => 9500, 'sale_price' => 11999, 'tax_rate' => 18, 'current_stock' => 12, 'min_stock' => 5, 'description' => '13 inç MacBook Pro M1 çip 8GB RAM 256GB SSD', 'status' => 'active'],
    ['code' => 'ELK005', 'name' => 'Akıllı Telefon', 'category_id' => 1, 'brand' => 'Samsung', 'model' => 'Galaxy S21', 'barcode' => '8801236547896', 'unit' => 'Adet', 'purchase_price' => 5800, 'sale_price' => 6499, 'tax_rate' => 18, 'current_stock' => 25, 'min_stock' => 10, 'description' => 'Samsung Galaxy S21 5G 128GB', 'status' => 'active'],
    ['code' => 'ELK006', 'name' => 'Mikrodalga Fırın', 'category_id' => 1, 'brand' => 'Vestel', 'model' => 'MD-20', 'barcode' => '7845962145632', 'unit' => 'Adet', 'purchase_price' => 850, 'sale_price' => 999, 'tax_rate' => 18, 'current_stock' => 20, 'min_stock' => 7, 'description' => '20 Lt Dijital Mikrodalga Fırın', 'status' => 'active'],
    ['code' => 'ELK007', 'name' => 'Bluetooth Kulaklık', 'category_id' => 1, 'brand' => 'Sony', 'model' => 'WH-1000XM4', 'barcode' => '4547896541230', 'unit' => 'Adet', 'purchase_price' => 1800, 'sale_price' => 2299, 'tax_rate' => 18, 'current_stock' => 30, 'min_stock' => 8, 'description' => 'Gürültü Önleyici Bluetooth Kulaklık', 'status' => 'active'],
    
    // Gıda ürünleri
    ['code' => 'GID001', 'name' => 'Zeytinyağı 1 Lt', 'category_id' => 2, 'brand' => 'Komili', 'model' => 'Naturel Sızma', 'barcode' => '8690123456789', 'unit' => 'Lt', 'purchase_price' => 85, 'sale_price' => 110, 'tax_rate' => 8, 'current_stock' => 50, 'min_stock' => 15, 'description' => 'Naturel Sızma Zeytinyağı 1 Lt', 'status' => 'active'],
    ['code' => 'GID002', 'name' => 'Çay 1 Kg', 'category_id' => 2, 'brand' => 'Çaykur', 'model' => 'Tiryaki', 'barcode' => '8690123789456', 'unit' => 'Kg', 'purchase_price' => 60, 'sale_price' => 75, 'tax_rate' => 8, 'current_stock' => 100, 'min_stock' => 30, 'description' => 'Tiryaki Siyah Çay 1 Kg', 'status' => 'active'],
    ['code' => 'GID003', 'name' => 'Kahve 250gr', 'category_id' => 2, 'brand' => 'Nescafe', 'model' => 'Gold', 'barcode' => '8690456123789', 'unit' => 'Adet', 'purchase_price' => 45, 'sale_price' => 60, 'tax_rate' => 8, 'current_stock' => 75, 'min_stock' => 25, 'description' => 'Gold Kavanoz Çözünebilir Kahve 250gr', 'status' => 'active'],
    ['code' => 'GID004', 'name' => 'Un 5 Kg', 'category_id' => 2, 'brand' => 'Söke', 'model' => 'Genel Amaçlı', 'barcode' => '8690789456123', 'unit' => 'Paket', 'purchase_price' => 25, 'sale_price' => 32, 'tax_rate' => 8, 'current_stock' => 60, 'min_stock' => 20, 'description' => 'Genel Amaçlı Un 5 Kg', 'status' => 'active'],
    ['code' => 'GID005', 'name' => 'Makarna 500gr', 'category_id' => 2, 'brand' => 'Filiz', 'model' => 'Burgu', 'barcode' => '8690456789123', 'unit' => 'Adet', 'purchase_price' => 4.5, 'sale_price' => 7.5, 'tax_rate' => 8, 'current_stock' => 150, 'min_stock' => 50, 'description' => 'Burgu Makarna 500gr', 'status' => 'active'],
    ['code' => 'GID006', 'name' => 'Pirinç 1 Kg', 'category_id' => 2, 'brand' => 'Reis', 'model' => 'Baldo', 'barcode' => '8690123789456', 'unit' => 'Kg', 'purchase_price' => 20, 'sale_price' => 28, 'tax_rate' => 8, 'current_stock' => 80, 'min_stock' => 25, 'description' => 'Baldo Pirinç 1 Kg', 'status' => 'active'],
    
    // Kırtasiye ürünleri
    ['code' => 'KIR001', 'name' => 'A4 Fotokopi Kağıdı', 'category_id' => 3, 'brand' => 'Navigator', 'model' => 'Universal', 'barcode' => '8697412589634', 'unit' => 'Paket', 'purchase_price' => 45, 'sale_price' => 55, 'tax_rate' => 18, 'current_stock' => 100, 'min_stock' => 30, 'description' => '80gr A4 Fotokopi Kağıdı 500 Yaprak', 'status' => 'active'],
    ['code' => 'KIR002', 'name' => 'Kurşun Kalem', 'category_id' => 3, 'brand' => 'Faber-Castell', 'model' => '2B', 'barcode' => '8697412345678', 'unit' => 'Adet', 'purchase_price' => 1.5, 'sale_price' => 3, 'tax_rate' => 18, 'current_stock' => 200, 'min_stock' => 50, 'description' => '2B Kurşun Kalem', 'status' => 'active'],
    ['code' => 'KIR003', 'name' => 'Tükenmez Kalem', 'category_id' => 3, 'brand' => 'Rotring', 'model' => 'Tikky', 'barcode' => '8697489612345', 'unit' => 'Adet', 'purchase_price' => 8, 'sale_price' => 12, 'tax_rate' => 18, 'current_stock' => 150, 'min_stock' => 50, 'description' => 'Mavi Tükenmez Kalem', 'status' => 'active'],
    ['code' => 'KIR004', 'name' => 'Defter 100 Yaprak', 'category_id' => 3, 'brand' => 'Mopak', 'model' => 'Spiralli', 'barcode' => '8697489654321', 'unit' => 'Adet', 'purchase_price' => 12, 'sale_price' => 18, 'tax_rate' => 18, 'current_stock' => 80, 'min_stock' => 25, 'description' => '100 Yaprak Spiralli Kareli Defter', 'status' => 'active'],
    ['code' => 'KIR005', 'name' => 'Yapıştırıcı', 'category_id' => 3, 'brand' => 'Pritt', 'model' => 'Stick', 'barcode' => '8697456123789', 'unit' => 'Adet', 'purchase_price' => 7, 'sale_price' => 11, 'tax_rate' => 18, 'current_stock' => 120, 'min_stock' => 40, 'description' => 'Stick Yapıştırıcı 20gr', 'status' => 'active'],
    ['code' => 'KIR006', 'name' => 'Klasör', 'category_id' => 3, 'brand' => 'Leitz', 'model' => 'Geniş', 'barcode' => '8697123456789', 'unit' => 'Adet', 'purchase_price' => 18, 'sale_price' => 25, 'tax_rate' => 18, 'current_stock' => 60, 'min_stock' => 15, 'description' => 'Geniş Karton Klasör', 'status' => 'active'],
];

// Ekleme işlemi başlat
try {
    // Başarıyla eklenen ve hata alan ürünleri takip et
    $eklenmiş = 0;
    $hatalı = 0;
    $hataMesajları = [];
    
    // Veritabanı işlemini başlat
    $db->beginTransaction();
    
    // Her ürün için ekleme işlemi yap
    foreach ($urunler as $urun) {
        // Önce ürünün daha önce eklenip eklenmediğini kontrol et
        $stmt = $db->prepare("SELECT COUNT(*) FROM products WHERE code = :code");
        $stmt->bindParam(':code', $urun['code']);
        $stmt->execute();
        
        if ($stmt->fetchColumn() > 0) {
            $hataMesajları[] = $urun['code'] . " kodlu ürün zaten mevcut, atlandı.";
            $hatalı++;
            continue;
        }
        
        // Ürünü ekle
        $sql = "INSERT INTO products (
            code, 
            name, 
            category_id, 
            unit, 
            barcode, 
            brand, 
            model, 
            description, 
            purchase_price, 
            sale_price, 
            tax_rate, 
            current_stock, 
            min_stock, 
            status, 
            created_at, 
            created_by
        ) VALUES (
            :code, 
            :name, 
            :category_id, 
            :unit, 
            :barcode, 
            :brand, 
            :model, 
            :description, 
            :purchase_price, 
            :sale_price, 
            :tax_rate, 
            :current_stock, 
            :min_stock, 
            :status, 
            NOW(), 
            :created_by
        )";
        
        $stmt = $db->prepare($sql);
        $stmt->bindParam(':code', $urun['code']);
        $stmt->bindParam(':name', $urun['name']);
        $stmt->bindParam(':category_id', $urun['category_id']);
        $stmt->bindParam(':unit', $urun['unit']);
        $stmt->bindParam(':barcode', $urun['barcode']);
        $stmt->bindParam(':brand', $urun['brand']);
        $stmt->bindParam(':model', $urun['model']);
        $stmt->bindParam(':description', $urun['description']);
        $stmt->bindParam(':purchase_price', $urun['purchase_price']);
        $stmt->bindParam(':sale_price', $urun['sale_price']);
        $stmt->bindParam(':tax_rate', $urun['tax_rate']);
        $stmt->bindParam(':current_stock', $urun['current_stock']);
        $stmt->bindParam(':min_stock', $urun['min_stock']);
        $stmt->bindParam(':status', $urun['status']);
        $stmt->bindParam(':created_by', $_SESSION['user_id']);
        $stmt->execute();
        
        $product_id = $db->lastInsertId();
        
        // Stok hareketi ekle (başlangıç stoku)
        if ($urun['current_stock'] > 0) {
            $sql = "INSERT INTO stock_movements (
                product_id, 
                movement_type, 
                quantity, 
                unit_price, 
                reference_type, 
                reference_no, 
                description, 
                user_id, 
                created_at
            ) VALUES (
                :product_id, 
                'giris', 
                :quantity, 
                :unit_price, 
                'stok_sayim', 
                'TESTDATA', 
                'Test verisi - İlk stok girişi', 
                :user_id, 
                NOW()
            )";
            
            $stmt = $db->prepare($sql);
            $stmt->bindParam(':product_id', $product_id);
            $stmt->bindParam(':quantity', $urun['current_stock']);
            $stmt->bindParam(':unit_price', $urun['purchase_price']);
            $stmt->bindParam(':user_id', $_SESSION['user_id']);
            $stmt->execute();
        }
        
        $eklenmiş++;
    }
    
    // İşlemi tamamla
    $db->commit();
    
    // Sonuçları göster
    echo '<div class="container">';
    echo '<div class="row mt-4">';
    echo '<div class="col-md-12">';
    
    echo '<div class="alert alert-success" role="alert">';
    echo "<strong>İşlem tamamlandı!</strong> Toplam $eklenmiş ürün başarıyla eklendi.";
    echo '</div>';
    
    if ($hatalı > 0) {
        echo '<div class="alert alert-warning" role="alert">';
        echo "<strong>Dikkat!</strong> $hatalı ürün eklenemedi:";
        echo '<ul>';
        foreach ($hataMesajları as $mesaj) {
            echo "<li>$mesaj</li>";
        }
        echo '</ul>';
        echo '</div>';
    }
    
    echo '<div class="card shadow mb-4">';
    echo '<div class="card-header py-3">';
    echo '<h6 class="m-0 font-weight-bold text-primary">Eklenen Örnek Ürünler</h6>';
    echo '</div>';
    echo '<div class="card-body">';
    echo '<div class="table-responsive">';
    echo '<table class="table table-bordered" width="100%" cellspacing="0">';
    echo '<thead><tr><th>Kod</th><th>Ürün Adı</th><th>Kategori</th><th>Marka</th><th>Fiyat</th><th>Stok</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($urunler as $urun) {
        echo '<tr>';
        echo '<td>' . $urun['code'] . '</td>';
        echo '<td>' . $urun['name'] . '</td>';
        echo '<td>' . $urun['category_id'] . '</td>';
        echo '<td>' . $urun['brand'] . '</td>';
        echo '<td>₺' . number_format($urun['sale_price'], 2, ',', '.') . '</td>';
        echo '<td>' . $urun['current_stock'] . ' ' . $urun['unit'] . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
    echo '</div>'; // table-responsive
    echo '</div>'; // card-body
    echo '</div>'; // card
    
    echo '<div class="text-center mb-4">';
    echo '<a href="index.php" class="btn btn-primary"><i class="fas fa-list"></i> Ürün Listesine Git</a>';
    echo '</div>';
    
    echo '</div>'; // col
    echo '</div>'; // row
    echo '</div>'; // container
    
} catch (PDOException $e) {
    // Hata durumunda işlemi geri al
    $db->rollBack();
    
    echo '<div class="container">';
    echo '<div class="row mt-4">';
    echo '<div class="col-md-12">';
    echo '<div class="alert alert-danger" role="alert">';
    echo "<strong>Hata!</strong> Veritabanı hatası: " . $e->getMessage();
    echo '</div>';
    echo '<div class="text-center mb-4">';
    echo '<a href="index.php" class="btn btn-primary"><i class="fas fa-list"></i> Ürün Listesine Git</a>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
    echo '</div>';
}

// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 