-- Veritabanını oluştur
CREATE DATABASE IF NOT EXISTS `erp_sistem` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE `erp_sistem`;

-- Kullanıcılar tablosu
CREATE TABLE IF NOT EXISTS `users` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `username` varchar(50) NOT NULL,
    `password` varchar(255) NOT NULL,
    `full_name` varchar(100) NOT NULL,
    `email` varchar(100) NOT NULL,
    `role` varchar(50) NOT NULL,
    `status` enum('active','inactive') NOT NULL DEFAULT 'active',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    `last_login` timestamp NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `username` (`username`),
    UNIQUE KEY `email` (`email`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Kullanıcı izinleri tablosu
CREATE TABLE IF NOT EXISTS `user_permissions` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `user_id` int(11) NOT NULL,
    `module_name` varchar(50) NOT NULL,
    `permission` varchar(50) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `user_id` (`user_id`),
    CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Müşteriler tablosu
CREATE TABLE IF NOT EXISTS customers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    tax_number VARCHAR(20),
    tax_office VARCHAR(100),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP NULL,
    updated_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Tedarikçiler tablosu
CREATE TABLE IF NOT EXISTS suppliers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(20) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    tax_number VARCHAR(20),
    tax_office VARCHAR(100),
    address TEXT,
    phone VARCHAR(20),
    email VARCHAR(100),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP NULL,
    updated_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Ürünler tablosu
CREATE TABLE IF NOT EXISTS products (
    id INT AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    barcode VARCHAR(50),
    name VARCHAR(100) NOT NULL,
    category_id INT,
    brand VARCHAR(100),
    unit VARCHAR(20) NOT NULL,
    purchase_price DECIMAL(15,2) NOT NULL,
    sale_price DECIMAL(15,2) NOT NULL,
    min_stock INT NOT NULL DEFAULT 0,
    current_stock INT NOT NULL DEFAULT 0,
    image_path VARCHAR(255),
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP NULL,
    updated_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Ürün kategorileri tablosu
CREATE TABLE IF NOT EXISTS product_categories (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    description TEXT,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP NULL,
    updated_by INT NULL,
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Stok hareketleri tablosu
CREATE TABLE IF NOT EXISTS stock_movements (
    id INT AUTO_INCREMENT PRIMARY KEY,
    product_id INT NOT NULL,
    movement_type ENUM('in', 'out') NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    reference_type VARCHAR(50) NOT NULL,
    reference_id INT,
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    FOREIGN KEY (product_id) REFERENCES products(id),
    FOREIGN KEY (created_by) REFERENCES users(id)
);

-- Çek/Senet tablosu
CREATE TABLE IF NOT EXISTS `cek_senet` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `islem_no` varchar(20) NOT NULL,
    `tip` enum('cek','senet') NOT NULL,
    `tutar` decimal(15,2) NOT NULL,
    `tarih` date NOT NULL,
    `vade_tarihi` date NOT NULL,
    `durum` enum('beklemede','tahsil_edildi','odendi','iptal') NOT NULL DEFAULT 'beklemede',
    `aciklama` text,
    `banka` varchar(100) DEFAULT NULL,
    `sube` varchar(100) DEFAULT NULL,
    `hesap_no` varchar(50) DEFAULT NULL,
    `cek_no` varchar(50) DEFAULT NULL,
    `keside_eden` varchar(100) DEFAULT NULL,
    `alici` varchar(100) DEFAULT NULL,
    `kullanici_id` int(11) NOT NULL,
    `son_islem_yapan` int(11) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `islem_no` (`islem_no`),
    KEY `kullanici_id` (`kullanici_id`),
    KEY `son_islem_yapan` (`son_islem_yapan`),
    CONSTRAINT `cek_senet_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `users` (`id`),
    CONSTRAINT `cek_senet_ibfk_2` FOREIGN KEY (`son_islem_yapan`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Muhasebe kayıtları tablosu
CREATE TABLE IF NOT EXISTS `muhasebe_kayitlari` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `islem_no` varchar(20) NOT NULL,
    `islem_tipi` varchar(50) NOT NULL,
    `tarih` date NOT NULL,
    `tutar` decimal(15,2) NOT NULL,
    `borc_alacak` enum('borc','alacak') NOT NULL,
    `aciklama` text,
    `referans_id` int(11) DEFAULT NULL,
    `referans_tipi` varchar(50) DEFAULT NULL,
    `kullanici_id` int(11) NOT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    KEY `kullanici_id` (`kullanici_id`),
    CONSTRAINT `muhasebe_kayitlari_ibfk_1` FOREIGN KEY (`kullanici_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Şirket bilgileri tablosu
CREATE TABLE IF NOT EXISTS `company_settings` (
    `id` int(11) NOT NULL DEFAULT 1,
    `name` varchar(100) NOT NULL,
    `tax_office` varchar(100) DEFAULT NULL,
    `tax_number` varchar(20) DEFAULT NULL,
    `address` text,
    `phone` varchar(20) DEFAULT NULL,
    `email` varchar(100) DEFAULT NULL,
    `website` varchar(100) DEFAULT NULL,
    `logo` varchar(255) DEFAULT NULL,
    `founded_year` varchar(4) DEFAULT NULL,
    `bank_account` varchar(100) DEFAULT NULL,
    `bank_iban` varchar(50) DEFAULT NULL,
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Satış faturaları tablosu
CREATE TABLE IF NOT EXISTS sales_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    customer_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) NOT NULL,
    grand_total DECIMAL(15,2) NOT NULL,
    status ENUM('draft', 'issued', 'cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP NULL,
    updated_by INT NULL,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Satış fatura detayları tablosu
CREATE TABLE IF NOT EXISTS sales_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,
    tax_amount DECIMAL(15,2) NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    FOREIGN KEY (invoice_id) REFERENCES sales_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Alış faturaları tablosu
CREATE TABLE IF NOT EXISTS purchase_invoices (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_no VARCHAR(20) NOT NULL UNIQUE,
    supplier_id INT NOT NULL,
    invoice_date DATE NOT NULL,
    due_date DATE NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    tax_amount DECIMAL(15,2) NOT NULL,
    grand_total DECIMAL(15,2) NOT NULL,
    status ENUM('draft', 'issued', 'cancelled') NOT NULL DEFAULT 'draft',
    notes TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by INT NOT NULL,
    updated_at TIMESTAMP NULL,
    updated_by INT NULL,
    FOREIGN KEY (supplier_id) REFERENCES suppliers(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    FOREIGN KEY (updated_by) REFERENCES users(id)
);

-- Alış fatura detayları tablosu
CREATE TABLE IF NOT EXISTS purchase_invoice_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    invoice_id INT NOT NULL,
    product_id INT NOT NULL,
    quantity INT NOT NULL,
    unit_price DECIMAL(15,2) NOT NULL,
    tax_rate DECIMAL(5,2) NOT NULL,
    tax_amount DECIMAL(15,2) NOT NULL,
    total_amount DECIMAL(15,2) NOT NULL,
    description TEXT,
    FOREIGN KEY (invoice_id) REFERENCES purchase_invoices(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id)
);

-- Sistem ayarları tablosu
CREATE TABLE IF NOT EXISTS `system_settings` (
    `id` int(11) NOT NULL AUTO_INCREMENT,
    `setting_key` varchar(100) NOT NULL,
    `setting_value` text,
    `setting_group` varchar(50) DEFAULT 'general',
    `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
    `updated_at` timestamp NULL DEFAULT NULL ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (`id`),
    UNIQUE KEY `setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Örnek kullanıcı ekle (şifre: admin123)
INSERT INTO `users` (`username`, `password`, `full_name`, `email`, `role`, `status`) VALUES
('admin', '$2y$10$VUMxV6SUYG9NK5QSLNwQ3.r3Vdndw7VN0nhSGpKJ/jYKnYIf0JLaW', 'Sistem Yöneticisi', 'admin@example.com', 'admin', 'active');

-- Örnek yetkiler ekle
INSERT INTO `user_permissions` (`user_id`, `module_name`, `permission`) VALUES
(1, 'dashboard', 'tam_yetki'),
(1, 'kullanicilar', 'tam_yetki'),
(1, 'muhasebe', 'tam_yetki'),
(1, 'stok', 'tam_yetki'),
(1, 'satis', 'tam_yetki'),
(1, 'satinalma', 'tam_yetki'),
(1, 'raporlar', 'tam_yetki'),
(1, 'ayarlar', 'tam_yetki');

-- Örnek şirket bilgileri
INSERT INTO `company_settings` (`name`, `tax_office`, `tax_number`, `address`, `phone`, `email`, `website`) VALUES
('ABC Şirketi', 'Merkez', '1234567890', 'Örnek Adres, İstanbul', '0212 123 45 67', 'info@example.com', 'www.example.com');

-- Örnek sistem ayarları
INSERT INTO `system_settings` (`setting_key`, `setting_value`, `setting_group`) VALUES
('site_title', 'ERP Sistem', 'general'),
('timezone', 'Europe/Istanbul', 'general'),
('date_format', 'd.m.Y', 'general'),
('currency_symbol', '₺', 'general'),
('records_per_page', '10', 'general'),
('theme', 'default', 'general'),
('mail_host', '', 'mail'),
('mail_port', '587', 'mail'),
('mail_username', '', 'mail'),
('mail_password', '', 'mail'),
('mail_from_name', 'ERP Sistem', 'mail'),
('mail_from_address', '', 'mail'),
('invoice_prefix', 'INV', 'invoice'),
('invoice_next_number', '1', 'invoice'),
('backup_enabled', '1', 'backup'),
('backup_frequency', 'daily', 'backup'),
('maintenance_mode', '0', 'general'); 