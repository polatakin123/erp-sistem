<?php
/**
 * Header Dosyası
 * 
 * Bu dosya sayfanın üst kısmını ve navigasyon menüsünü içerir.
 */

// Ana dizin yolunu belirle
$rootPath = '';
if (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) {
    $rootPath = '../../';
} else {
    $rootPath = '';
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo isset($pageTitle) ? $pageTitle . ' - ERP Sistem' : 'ERP Sistem'; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Font Awesome -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    
    <!-- DataTables -->
    <link href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css" rel="stylesheet">
    
    <!-- Bootstrap Datepicker -->
    <link href="https://cdnjs.cloudflare.com/ajax/libs/bootstrap-datepicker/1.9.0/css/bootstrap-datepicker.min.css" rel="stylesheet">
    
    <!-- Select2 -->
    <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/select2-bootstrap-5-theme@1.3.0/dist/select2-bootstrap-5-theme.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link href="<?php echo $rootPath; ?>assets/css/style.css" rel="stylesheet">
    
    <?php if (isset($customCSS)): ?>
    <!-- Page Specific CSS -->
    <?php echo $customCSS; ?>
    <?php endif; ?>
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    
    <!-- Bootstrap JS - Doğru sırada yüklenmesi önemli -->
    <script src="https://cdn.jsdelivr.net/npm/@popperjs/core@2.11.6/dist/umd/popper.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0-alpha1/dist/js/bootstrap.min.js"></script>
    
    <style>
        /* Navbar ve içerik stillerini doğrudan header'da tanımlıyoruz */
        body, html {
            width: 100%;
            height: 100%;
            margin: 0;
            padding: 0;
            overflow-x: hidden;
            font-size: .875rem;
        }
        
        /* Sabit header */
        .navbar {
            padding: 0.5rem 1rem;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
            background-color: #fff;
        }
        
        .navbar-brand {
            font-weight: bold;
            font-size: 1.5rem;
            padding: 0.5rem 1rem;
        }
        
        /* Ana içerik alanı */
        main {
            margin-top: 56px; /* Navbar yüksekliği */
            padding: 1rem;
        }
        
        /* Dropdown menü stilleri */
        .navbar-nav .dropdown-menu {
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            margin-top: 0;
            display: none;
        }
        
        /* Hover ile dropdown menüyü göster */
        .nav-item.dropdown:hover .dropdown-menu {
            display: block;
        }
        
        .nav-link {
            padding: 0.5rem 1rem;
            display: flex;
            align-items: center;
        }
        
        .nav-link i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .dropdown-item i {
            margin-right: 0.5rem;
            width: 20px;
            text-align: center;
        }
        
        .navbar-light .navbar-nav .nav-link {
            color: rgba(0, 0, 0, 0.7);
        }
        
        .navbar-light .navbar-nav .nav-link.active {
            color: #2470dc;
            background-color: rgba(36, 112, 220, 0.1);
        }
        
        /* Dropdown menüler için özel stiller */
        .dropdown-menu {
            border: none;
            box-shadow: 0 0.5rem 1rem rgba(0, 0, 0, 0.15);
            border-radius: 0.5rem;
        }
        
        .dropdown-item {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }
        
        .dropdown-item:hover, .dropdown-item:focus {
            background-color: rgba(36, 112, 220, 0.1);
            color: #2470dc;
        }
        
        /* Navbar ikinci satır */
        .navbar-second-row {
            top: 56px;
            position: fixed;
            width: 100%;
            z-index: 1020;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.1);
            padding: 0.5rem 1rem;
        }
        
        /* Bildirim badge */
        .badge {
            position: absolute;
            top: 0;
            right: 0.2rem;
            font-size: 0.6rem;
        }
        
        .nav-link.has-badge {
            position: relative;
        }
        
        /* Mobil menü ayarlamaları */
        @media (max-width: 991.98px) {
            .navbar-collapse {
                max-height: calc(100vh - 56px);
                overflow-y: auto;
            }
            
            .navbar-toggler {
                border: none;
                padding: 0.25rem;
            }
            
            .navbar-toggler:focus {
                box-shadow: none;
            }
            
            .dropdown-menu {
                border: none;
                box-shadow: none;
                padding-left: 1.5rem;
            }
            
            /* Mobilde hover yerine tıklama ile çalışsın */
            .nav-item.dropdown:hover .dropdown-menu {
                display: none;
            }
            
            .nav-item.dropdown.show .dropdown-menu {
                display: block;
            }
            
            .nav-item.dropdown {
                display: block;
                width: 100%;
            }
            
            .navbar-second-row .container-fluid {
                flex-direction: column;
                align-items: stretch;
            }
            
            .navbar-second-row form {
                margin-bottom: 0.5rem;
                width: 100%;
            }
        }
    </style>

<script>
    // Sayfa yüklendiğinde çalışacak kodlar
    document.addEventListener("DOMContentLoaded", function() {
        // Mobil cihazlar için
        if (window.innerWidth < 992) {
            // Dropdown toggle öğeleri
            document.querySelectorAll('.dropdown-toggle').forEach(function(element) {
                element.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var parent = this.parentElement;
                    var dropdown = parent.querySelector('.dropdown-menu');
                    
                    // Tüm dropdown menüleri kapat
                    document.querySelectorAll('.nav-item.dropdown').forEach(function(item) {
                        if (item !== parent) {
                            item.classList.remove('show');
                            var menu = item.querySelector('.dropdown-menu');
                            if (menu) menu.classList.remove('show');
                        }
                    });
                    
                    // Bu dropdown menüyü toggle et
                    parent.classList.toggle('show');
                    dropdown.classList.toggle('show');
                });
            });
        }
        
        // Tıklama ile sadece mobilde çalışsın
        if (window.innerWidth < 992) {
            // Sayfanın herhangi bir yerine tıklandığında açık menüleri kapat
            document.addEventListener('click', function(e) {
                if (!e.target.closest('.dropdown-menu') && !e.target.classList.contains('dropdown-toggle')) {
                    document.querySelectorAll('.nav-item.dropdown.show').forEach(function(dropdown) {
                        dropdown.classList.remove('show');
                        var menu = dropdown.querySelector('.dropdown-menu');
                        if (menu) menu.classList.remove('show');
                    });
                }
            });
        }
        
        // Bildirimler butonuna tıklandığında modalı aç
        document.getElementById("bildirimler").addEventListener("click", function(e) {
            e.preventDefault();
            var bildirimlerModal = new bootstrap.Modal(document.getElementById('bildirimlerModal'));
            bildirimlerModal.show();
        });
        
        // Mesajlar butonuna tıklandığında modalı aç
        document.getElementById("mesajlar").addEventListener("click", function(e) {
            e.preventDefault();
            var mesajlarModal = new bootstrap.Modal(document.getElementById('mesajlarModal'));
            mesajlarModal.show();
        });
    });
</script>
</head>
<body>
    <!-- Sabit Header -->
    <nav class="navbar navbar-expand-lg navbar-light bg-light">
        <div class="container-fluid">
            <a class="navbar-brand" href="<?php echo $rootPath; ?>">
                <i class="fas fa-box-open"></i> ERP Sistem
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarCollapse" aria-controls="navbarCollapse" aria-expanded="false" aria-label="Toggle navigation">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarCollapse">
                <ul class="navbar-nav me-auto mb-2 mb-lg-0">
                    <li class="nav-item">
                        <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], 'index.php') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>index.php">
                            <i class="fas fa-tachometer-alt"></i> Gösterge Paneli
                        </a>
                    </li>
                    
                    <!-- Stok Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="stokDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-boxes"></i> Stok
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="stokDropdown">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/stok/index.php"><i class="fas fa-list"></i> Stok Listesi</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/stok/urun_ekle.php"><i class="fas fa-plus"></i> Yeni Ürün Ekle</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/stok/urun_arama.php"><i class="fas fa-search"></i> Ürün Arama</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/stok/stok_hareketi_ekle.php"><i class="fas fa-exchange-alt"></i> Stok Hareketi Ekle</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/stok/stok_raporlari.php"><i class="fas fa-chart-bar"></i> Stok Raporları</a></li>
                        </ul>
                    </li>
                    
                    <!-- Cari Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="cariDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-users"></i> Cari
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="cariDropdown">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/cari/index.php"><i class="fas fa-list"></i> Cari Listesi</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/cari/cari_ekle.php"><i class="fas fa-user-plus"></i> Yeni Cari Ekle</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/cari/cari_arama.php"><i class="fas fa-search"></i> Cari Arama</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/cari/cari_hareketleri.php"><i class="fas fa-exchange-alt"></i> Cari Hareketleri</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/cari/cari_raporlari.php"><i class="fas fa-chart-bar"></i> Cari Raporları</a></li>
                        </ul>
                    </li>
                    
                    <!-- İrsaliye Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-truck"></i> İrsaliye
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/irsaliye/irsaliye_ozet.php">
                                <i class="fas fa-chart-bar"></i> İrsaliye Özet
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/irsaliye/irsaliye_listesi.php">
                                <i class="fas fa-list"></i> İrsaliye Listesi
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/irsaliye/alis_irsaliyesi.php">
                                <i class="fas fa-arrow-circle-down"></i> Alış İrsaliyesi
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/irsaliye/yurtici_satis_irsaliyesi.php">
                                <i class="fas fa-arrow-circle-up"></i> Yurt İçi Satış İrsaliyesi
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/irsaliye/yurtdisi_satis_irsaliyesi.php">
                                <i class="fas fa-globe"></i> Yurt Dışı Satış İrsaliyesi
                            </a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/irsaliye/irsaliye_hareketleri.php">
                                <i class="fas fa-exchange-alt"></i> İrsaliye Hareketleri
                            </a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/irsaliye/toplu_faturala.php">
                                <i class="fas fa-file-invoice"></i> Toplu Faturala
                            </a></li>
                        </ul>
                    </li>
                    
                    <!-- Fatura Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="faturaDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-file-invoice"></i> Fatura
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="faturaDropdown">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/fatura/fatura_listesi.php"><i class="fas fa-list"></i> Fatura Listesi</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/fatura/fatura_ekle.php"><i class="fas fa-plus"></i> Yeni Fatura Ekle</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/fatura/fatura_hareketleri.php"><i class="fas fa-exchange-alt"></i> Fatura Hareketleri</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/fatura/fatura_raporlari.php"><i class="fas fa-chart-bar"></i> Fatura Raporları</a></li>
                        </ul>
                    </li>
                    
                    <!-- Muhasebe Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="muhasebeDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-calculator"></i> Muhasebe
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="muhasebeDropdown">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/muhasebe/kasa_listesi.php"><i class="fas fa-cash-register"></i> Kasa İşlemleri</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/muhasebe/banka_listesi.php"><i class="fas fa-university"></i> Banka İşlemleri</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/muhasebe/rapor.php"><i class="fas fa-chart-line"></i> Muhasebe Raporu</a></li>
                        </ul>
                    </li>
                    
                    <!-- Raporlar Menüsü -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="raporlarDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-chart-bar"></i> Raporlar
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="raporlarDropdown">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/raporlar/satis_raporu.php"><i class="fas fa-chart-line"></i> Satış Raporu</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/raporlar/stok_raporu.php"><i class="fas fa-chart-pie"></i> Stok Raporu</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/raporlar/cari_raporu.php"><i class="fas fa-users"></i> Cari Raporu</a></li>
                        </ul>
                    </li>
                    
                    <!-- Sistem Menüleri -->
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="sistemDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-cog"></i> Sistem
                        </a>
                        <ul class="dropdown-menu" aria-labelledby="sistemDropdown">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/kullanici/index.php"><i class="fas fa-users"></i> Kullanıcı Yönetimi</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/ayarlar/index.php"><i class="fas fa-cog"></i> Ayarlar</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/yedekleme/index.php"><i class="fas fa-database"></i> Yedekleme</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/yardim/index.php"><i class="fas fa-question-circle"></i> Yardım</a></li>
                        </ul>
                    </li>
                </ul>
                
                <!-- Kullanıcı Dropdown -->
                <div class="navbar-nav">
                    <!-- Bildirimler ve Mesajlar -->
                    <div class="d-flex me-2">
                        <a class="nav-link" href="#" id="bildirimler">
                            <i class="fas fa-bell"></i>
                            <span class="badge rounded-pill bg-danger">3</span>
                        </a>
                        <a class="nav-link" href="#" id="mesajlar">
                            <i class="fas fa-envelope"></i>
                            <span class="badge rounded-pill bg-primary">2</span>
                        </a>
                    </div>
                    
                    <div class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                            <i class="fas fa-user-circle"></i> <?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Kullanıcı'; ?>
                        </a>
                        <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/ayarlar/kullanici_profili.php"><i class="fas fa-user fa-fw"></i> Profil</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/ayarlar/sirket_bilgileri.php"><i class="fas fa-building fa-fw"></i> Şirket Bilgileri</a></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/ayarlar/sistem_ayarlari.php"><i class="fas fa-cog fa-fw"></i> Sistem Ayarları</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="<?php echo $rootPath; ?>logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Çıkış Yap</a></li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
    </nav>
    
    <!-- Sabit Hızlı Ürün Arama Formu -->
    <nav class="navbar navbar-light bg-light border-top navbar-expand-lg navbar-second-row">
        <div class="container-fluid">
            <form action="<?php echo $rootPath; ?>modules/stok/urun_arama.php" method="get" class="d-flex flex-grow-1">
                <input type="hidden" name="arama_modu" value="hizli">
                <div class="input-group">
                    <span class="input-group-text bg-transparent border-end-0">
                        <i class="fas fa-search text-muted"></i>
                    </span>
                    <input class="form-control border-start-0" type="text" placeholder="Hızlı ürün arama..." name="arama" aria-label="Ara">
                    <button class="btn btn-primary" type="submit">
                        Ara
                    </button>
                </div>
            </form>
        </div>
    </nav>
    
    <!-- Ana Konteyner -->
    <div class="container-fluid px-0" style="margin-top: 110px;">
        <!-- Sidebar'ı kaldırdık -->
        <!-- Ana İçerik Alanı -->
        <main class="w-100">
    
    <!-- Bildirimler Modal -->
    <div class="modal fade" id="bildirimlerModal" tabindex="-1" aria-labelledby="bildirimlerModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="bildirimlerModalLabel">Bildirimler</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Stok Uyarısı</h5>
                                <small>3 saat önce</small>
                            </div>
                            <p class="mb-1">3 ürünün stok seviyesi kritik seviyenin altına düştü.</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Fatura Hatırlatma</h5>
                                <small>1 gün önce</small>
                            </div>
                            <p class="mb-1">2 fatura ödeme tarihi yaklaşıyor.</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Yeni Sipariş</h5>
                                <small>2 gün önce</small>
                            </div>
                            <p class="mb-1">ABC Otomotiv'den yeni bir sipariş geldi.</p>
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary">Tümünü Gör</button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Mesajlar Modal -->
    <div class="modal fade" id="mesajlarModal" tabindex="-1" aria-labelledby="mesajlarModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="mesajlarModalLabel">Mesajlar</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div class="list-group">
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Ahmet Yılmaz</h5>
                                <small>1 saat önce</small>
                            </div>
                            <p class="mb-1">Merhaba, son siparişimizin durumu hakkında bilgi alabilir miyim?</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Mehmet Öz</h5>
                                <small>3 saat önce</small>
                            </div>
                            <p class="mb-1">Fiyat teklifinizi aldık, teşekkürler.</p>
                        </a>
                        <a href="#" class="list-group-item list-group-item-action">
                            <div class="d-flex w-100 justify-content-between">
                                <h5 class="mb-1">Ayşe Demir</h5>
                                <small>1 gün önce</small>
                            </div>
                            <p class="mb-1">Toplantımızı Cuma günü saat 14:00'e erteleyebilir miyiz?</p>
                        </a>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Kapat</button>
                    <button type="button" class="btn btn-primary">Tümünü Gör</button>
                </div>
            </div>
        </div>
    </div>
    
    <script>
    $(document).ready(function() {
        // Dropdown menüler için
        $('.dropdown-toggle').dropdown();
        
        // Dropdown menüleri tıklanabilir hale getir
        $('.nav-item.dropdown').on('click', function(e) {
            e.stopPropagation();
            $('.dropdown-menu', this).toggleClass('show');
        });
        
        // Dropdown menü içindeki öğelere tıklandığında dropdownu kapatma
        $('.dropdown-item').on('click', function() {
            $(this).closest('.dropdown-menu').removeClass('show');
        });
        
        // Sayfa yüklendiğinde kullanıcı modüllerine göre aktif sınıfı ekle
        var currentPath = window.location.pathname;
        
        $('.nav-link.dropdown-toggle').each(function() {
            var menuPath = $(this).attr('data-path');
            if (menuPath && currentPath.includes(menuPath)) {
                $(this).addClass('active');
            }
        });
    });
    </script>
</body>
</html> 