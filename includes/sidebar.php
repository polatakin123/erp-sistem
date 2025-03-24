<?php
/**
 * Yan Menü (Sidebar)
 * 
 * Bu dosya sistemin yan menüsünü içerir.
 */

// Ana dizin yolunu belirle
$rootPath = '';
if (strpos($_SERVER['PHP_SELF'], '/modules/') !== false) {
    $rootPath = '../../';
} else {
    $rootPath = '';
}
?>
<nav id="sidebarMenu" class="col-md-3 col-lg-2 d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3">
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" aria-current="page" href="<?php echo $rootPath; ?>index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    Gösterge Paneli
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/stok/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/stok/index.php">
                    <i class="fas fa-boxes"></i>
                    Stok Yönetimi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/cari/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/cari/index.php">
                    <i class="fas fa-address-book"></i>
                    Cari Yönetimi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/irsaliye/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/irsaliye/irsaliye_listesi.php">
                    <i class="fas fa-truck"></i>
                    İrsaliye İşlemleri
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/muhasebe/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/muhasebe/index.php">
                    <i class="fas fa-calculator"></i>
                    Muhasebe
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/fatura/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/fatura/index.php">
                    <i class="fas fa-file-invoice"></i>
                    Fatura İşlemleri
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/raporlar/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/raporlar/index.php">
                    <i class="fas fa-chart-bar"></i>
                    Raporlar
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-4 mb-1 text-muted">
            <span>Sistem</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/kullanici/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/kullanici/index.php">
                    <i class="fas fa-users"></i>
                    Kullanıcı Yönetimi
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $rootPath; ?>modules/ayarlar/index.php">
                    <i class="fas fa-cog"></i>
                    Ayarlar
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $rootPath; ?>modules/yedekleme/index.php">
                    <i class="fas fa-database"></i>
                    Yedekleme
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $rootPath; ?>modules/yardim/index.php">
                    <i class="fas fa-question-circle"></i>
                    Yardım
                </a>
            </li>
            <li class="nav-item mt-3">
                <a class="nav-link bg-danger text-white" href="<?php echo $rootPath; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    Çıkış Yap
                </a>
            </li>
        </ul>
    </div>
</nav> 