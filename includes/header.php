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
    
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
</head>
<body>
    <!-- Üst Menü -->
    <header class="navbar navbar-dark sticky-top bg-dark flex-md-nowrap p-0 shadow">
        <a class="navbar-brand col-md-auto me-0 px-3" href="<?php echo $rootPath; ?>index.php">ERP Sistem</a>
        <button class="navbar-toggler position-absolute d-md-none collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebarMenu" aria-controls="sidebarMenu" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>
        <form action="<?php echo $rootPath; ?>modules/stok/urun_arama.php" method="get" style="flex-grow: 1; max-width: 500px;">
            <input type="hidden" name="arama_modu" value="hizli">
            <div class="input-group">
                <input class="form-control form-control-dark border-0" type="text" placeholder="Hızlı Ürün Ara (stok kodu, ürün adı, marka, model)" name="arama" aria-label="Ara">
                <button class="btn btn-dark border-0" type="submit">
                    <i class="fas fa-search"></i>
                </button>
            </div>
        </form>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="#" id="bildirimler">
                    <i class="fas fa-bell"></i>
                    <span class="badge rounded-pill bg-danger">3</span>
                </a>
            </div>
        </div>
        <div class="navbar-nav">
            <div class="nav-item text-nowrap">
                <a class="nav-link px-3" href="#" id="mesajlar">
                    <i class="fas fa-envelope"></i>
                    <span class="badge rounded-pill bg-warning">5</span>
                </a>
            </div>
        </div>
        <div class="navbar-nav">
            <div class="nav-item dropdown">
                <a class="nav-link dropdown-toggle px-3" href="#" id="userDropdown" role="button" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="fas fa-user-circle"></i> <?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Kullanıcı'; ?>
                </a>
                <ul class="dropdown-menu dropdown-menu-end" aria-labelledby="userDropdown">
                    <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/ayarlar/kullanici_profili.php"><i class="fas fa-user fa-fw"></i> Profil</a></li>
                    <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/ayarlar/sirket_bilgileri.php"><i class="fas fa-building fa-fw"></i> Şirket Bilgileri</a></li>
                    <li><a class="dropdown-item" href="<?php echo $rootPath; ?>modules/ayarlar/sistem_ayarlari.php"><i class="fas fa-cog fa-fw"></i> Sistem Ayarları</a></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="<?php 
                    // Modül içinde ise veya ana dizinde ise farklı yollar kullan
                    if (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) {
                        // Modül içindeyken aynı dizine logout.php dosyasını gösterir
                        echo 'logout.php';
                    } else {
                        // Ana dizindeyken
                        echo $rootPath . 'logout.php';
                    }
                    ?>"><i class="fas fa-sign-out-alt fa-fw"></i> Çıkış Yap</a></li>
                </ul>
            </div>
        </div>
    </header>
    
    <!-- Ana Konteyner -->
    <div class="container-fluid px-0">
        <div class="row g-0">
            <!-- Sidebar -->
            <?php include $rootPath . 'includes/sidebar.php'; ?>
            
            <!-- Ana İçerik Alanı -->
            <main class="col">
    
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
    
    <!-- Sidebar Toggle Butonu -->
    <div class="sidebar-toggle d-none d-md-flex" id="sidebarToggle">
        <i class="fas fa-chevron-left"></i>
    </div>
    
    <script>
    $(document).ready(function() {
        // Bildirimler butonuna tıklandığında modalı aç
        $("#bildirimler").click(function(e) {
            e.preventDefault();
            $("#bildirimlerModal").modal('show');
        });
        
        // Mesajlar butonuna tıklandığında modalı aç
        $("#mesajlar").click(function(e) {
            e.preventDefault();
            $("#mesajlarModal").modal('show');
        });
        
        // Sidebar Toggle
        $("#sidebarToggle").click(function() {
            $(".sidebar").toggleClass("sidebar-collapsed");
            
            // Toggle ikonu değiştir
            if ($(".sidebar").hasClass("sidebar-collapsed")) {
                $(this).find("i").removeClass("fa-chevron-left").addClass("fa-chevron-right");
            } else {
                $(this).find("i").removeClass("fa-chevron-right").addClass("fa-chevron-left");
            }
            
            // Sidebar durumunu localStorage'a kaydet
            localStorage.setItem("sidebarCollapsed", $(".sidebar").hasClass("sidebar-collapsed"));
        });
        
        // Sayfa yüklendiğinde sidebar'ı otomatik olarak daralt
        $(".sidebar").addClass("sidebar-collapsed");
        $("#sidebarToggle").find("i").removeClass("fa-chevron-left").addClass("fa-chevron-right");
        localStorage.setItem("sidebarCollapsed", "true");
    });
    </script>
</body>
</html> 