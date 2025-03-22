<?php
/**
 * ERP Sistem - Stok Modülü Test Sayfası
 * 
 * Bu script stok modülünün kurulumunu ve test edilmesini sağlar.
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

// Hata raporlamayı aç
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

// Sayfa başlığı
$pageTitle = "Stok Modülü Test";

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container">
    <div class="row mt-4">
        <div class="col-md-12">
            <div class="alert alert-info">
                <h4 class="alert-heading">Stok Modülü Test Sayfası</h4>
                <p>Bu sayfa üzerinden stok modülünü test etmek için gerekli adımları sırasıyla gerçekleştirebilirsiniz.</p>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Test Adımları</h6>
                </div>
                <div class="card-body">
                    <div class="list-group">
                        <a href="tablo_olustur.php" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">1. Tabloları Oluştur</h5>
                            </div>
                            <p class="mb-1">Stok modülü için gerekli olan veritabanı tablolarını kontrol eder ve yoksa oluşturur.</p>
                            <small class="text-muted">product_categories, products, stock_movements tablolarını oluşturur.</small>
                        </a>
                        <a href="kategori_ekle.php" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">2. Kategorileri Ekle</h5>
                            </div>
                            <p class="mb-1">Veritabanına örnek ürün kategorileri ekler.</p>
                            <small class="text-muted">Elektronik, Gıda, Kırtasiye gibi temel kategorileri ekler.</small>
                        </a>
                        <a href="urun_test_verileri.php" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">3. Test Ürünlerini Ekle</h5>
                            </div>
                            <p class="mb-1">Veritabanına 20 adet örnek test ürünü ekler.</p>
                            <small class="text-muted">Farklı kategorilerden çeşitli ürünler ekler.</small>
                        </a>
                        <a href="index.php" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">4. Stok Modülünü Aç</h5>
                            </div>
                            <p class="mb-1">Stok modülünün ana sayfasını açar.</p>
                            <small class="text-muted">Eklenen test ürünlerini görüntüleyebilirsiniz.</small>
                        </a>
                    </div>
                </div>
            </div>
            
            <div class="alert alert-warning">
                <h4 class="alert-heading">Dikkat!</h4>
                <p>Bu işlemler sırasında veritabanınızda değişiklikler yapılacaktır. Önemli verileriniz varsa yedek almanız önerilir.</p>
            </div>
            
            <div class="card shadow mb-4">
                <div class="card-header py-3">
                    <h6 class="m-0 font-weight-bold text-primary">Otomatik Kurulum</h6>
                </div>
                <div class="card-body">
                    <p>Tüm kurulum adımlarını otomatik olarak çalıştırmak için aşağıdaki butona tıklayabilirsiniz:</p>
                    <div class="text-center mb-4">
                        <a href="javascript:void(0);" class="btn btn-primary" id="autoRunButton">
                            <i class="fas fa-cogs"></i> Tüm Adımları Otomatik Çalıştır
                        </a>
                    </div>
                    <div id="autoRunResults" class="d-none">
                        <div class="alert alert-info">
                            <h5>İşlem Sonuçları</h5>
                            <div id="processResults"></div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    document.addEventListener('DOMContentLoaded', function() {
        // Otomatik çalıştırma düğmesi için event listener
        document.getElementById('autoRunButton').addEventListener('click', function() {
            const resultsContainer = document.getElementById('autoRunResults');
            const processResults = document.getElementById('processResults');
            
            resultsContainer.classList.remove('d-none');
            processResults.innerHTML = '<p>İşlem başlatılıyor...</p>';
            
            // İşlemleri sırayla çalıştır
            runProcess('tablo_olustur.php', 'Tablolar oluşturuluyor...', function() {
                runProcess('kategori_ekle.php', 'Kategoriler ekleniyor...', function() {
                    runProcess('urun_test_verileri.php', 'Test ürünleri ekleniyor...', function() {
                        processResults.innerHTML += '<p class="text-success">Tüm işlemler tamamlandı! <a href="index.php" class="btn btn-sm btn-success">Stok Modülüne Git</a></p>';
                    });
                });
            });
            
            function runProcess(url, message, callback) {
                processResults.innerHTML += '<p>' + message + '</p>';
                
                fetch(url, { method: 'GET' })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('İşlem sırasında hata oluştu!');
                        }
                        processResults.innerHTML += '<p class="text-success">' + url + ' başarıyla çalıştırıldı.</p>';
                        if (callback) callback();
                    })
                    .catch(error => {
                        processResults.innerHTML += '<p class="text-danger">Hata: ' + error.message + '</p>';
                    });
            }
        });
    });
</script>

<?php
// Alt kısmı dahil et
include_once '../../includes/footer.php';
?> 