<?php
/**
 * ERP Sistem - Stok Modülü Veritabanı Optimizasyonu (Güvenli)
 * 
 * Bu dosya MySQL komut satırı aracılığıyla indeksleri oluşturur.
 * PHP zaman aşımı sorunlarına karşı güvenlidir.
 */

require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Oturum kontrolü
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Sadece admin yetkisi olanlar bu betiği çalıştırabilir
if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] != 'admin') {
    echo "Bu işlemi yapmak için yetkiniz bulunmamaktadır.";
    exit;
}

// HTML başlık
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stok Modülü Veritabanı Optimizasyonu (Güvenli)</title>
    <link rel="stylesheet" href="../../assets/css/bootstrap.min.css">
    <style>
        body { padding: 20px; }
        .text-success { color: green; }
        .text-danger { color: red; }
        .console {
            background-color: #000;
            color: #00ff00;
            padding: 15px;
            border-radius: 5px;
            font-family: monospace;
            white-space: pre-wrap;
            max-height: 400px;
            overflow-y: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Stok Modülü Veritabanı Optimizasyonu (Güvenli)</h1>
        
        <div class="alert alert-warning">
            <strong>Dikkat!</strong> Bu işlem veritabanınızda indeksler oluşturacak. 
            Büyük tablolarda bu işlem uzun sürebilir.
        </div>
        
        <div class="card mb-4">
            <div class="card-header">
                <h5>MySQL Komut Satırı İle Optimizasyon (Önerilen)</h5>
            </div>
            <div class="card-body">
                <p>Bu yöntem en güvenli ve hızlı yöntemdir. Aşağıdaki komutu MySQL komut satırında çalıştırın:</p>
                
                <div class="console">
# Windows için:
cd C:\xampp\mysql\bin
mysql -u root -p veritabani_adi < "C:\xampp\htdocs\projeler\erp_sistem\modules\stok\indeksler.sql"

# Linux için:
cd /var/www/html/projeler/erp_sistem/modules/stok
mysql -u root -p veritabani_adi < indeksler.sql
                </div>
                
                <p class="mt-3">Veya <code>indeksler.sql</code> dosyasını phpMyAdmin aracılığıyla içe aktarın.</p>
                
                <div class="mt-3">
                    <a href="indeksler.sql" class="btn btn-info" download>SQL Dosyasını İndir</a>
                    <a href="http://localhost/phpmyadmin" target="_blank" class="btn btn-primary">phpMyAdmin'i Aç</a>
                </div>
            </div>
        </div>
        
        <div class="card">
            <div class="card-header">
                <h5>Tek Tek İndeksleme</h5>
            </div>
            <div class="card-body">
                <p>Bu yöntem daha yavaş olabilir ve zaman aşımı hatası alabilirsiniz.</p>
                
                <div class="row g-3">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Stok Kodu (KOD) İndeksi</h6>
                                <p>stok tablosunda KOD alanı için indeks oluşturur.</p>
                                <a href="stok_optimizasyon.php?islem=stok_kod" class="btn btn-sm btn-outline-primary">Oluştur</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Stok Adı (ADI) İndeksi</h6>
                                <p>stok tablosunda ADI alanı için indeks oluşturur.</p>
                                <a href="stok_optimizasyon.php?islem=stok_adi" class="btn btn-sm btn-outline-primary">Oluştur</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Ürün Miktar İndeksi</h6>
                                <p>STK_URUN_MIKTAR tablosunda birleşik indeks oluşturur.</p>
                                <a href="stok_optimizasyon.php?islem=urun_miktar" class="btn btn-sm btn-outline-primary">Oluştur</a>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-body">
                                <h6 class="card-title">Fiyat İndeksi</h6>
                                <p>stk_fiyat tablosunda birleşik indeks oluşturur.</p>
                                <a href="stok_optimizasyon.php?islem=stk_fiyat" class="btn btn-sm btn-outline-primary">Oluştur</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="mt-4">
            <a href="urun_arama.php" class="btn btn-primary">Ürün Arama Sayfasına Dön</a>
        </div>
    </div>
</body>
</html> 