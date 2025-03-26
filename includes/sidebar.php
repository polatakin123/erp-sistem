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
<nav id="sidebarMenu" class="col-auto d-md-block bg-light sidebar collapse">
    <div class="position-sticky pt-3 sidebar-content">
        <div class="px-2 mb-3">
            <div class="text-center mb-2">
                <img src="<?php echo $rootPath; ?>assets/img/logo.png" alt="Logo" class="img-fluid" style="max-width: 120px;">
            </div>
            <div class="user-info text-center mb-2">
                <h6 class="mb-1 small"><?php echo isset($_SESSION['full_name']) ? $_SESSION['full_name'] : 'Kullanıcı'; ?></h6>
                <small class="text-muted"><?php echo isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'Kullanıcı'; ?></small>
            </div>
        </div>
        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo basename($_SERVER['PHP_SELF']) == 'index.php' ? 'active' : ''; ?>" aria-current="page" href="<?php echo $rootPath; ?>index.php">
                    <i class="fas fa-tachometer-alt"></i>
                    <span>Gösterge Paneli</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/stok/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/stok/index.php">
                    <i class="fas fa-boxes"></i>
                    <span>Stok Yönetimi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/cari/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/cari/index.php">
                    <i class="fas fa-address-book"></i>
                    <span>Cari Yönetimi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/irsaliye/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/irsaliye/irsaliye_listesi.php">
                    <i class="fas fa-truck"></i>
                    <span>İrsaliye İşlemleri</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/muhasebe/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/muhasebe/index.php">
                    <i class="fas fa-calculator"></i>
                    <span>Muhasebe</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/fatura/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/fatura/index.php">
                    <i class="fas fa-file-invoice"></i>
                    <span>Fatura İşlemleri</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/raporlar/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/raporlar/index.php">
                    <i class="fas fa-chart-bar"></i>
                    <span>Raporlar</span>
                </a>
            </li>
        </ul>

        <h6 class="sidebar-heading d-flex justify-content-between align-items-center px-3 mt-3 mb-1 text-muted">
            <span>Sistem</span>
        </h6>
        <ul class="nav flex-column mb-2">
            <li class="nav-item">
                <a class="nav-link <?php echo strpos($_SERVER['PHP_SELF'], '/modules/kullanici/') !== false ? 'active' : ''; ?>" href="<?php echo $rootPath; ?>modules/kullanici/index.php">
                    <i class="fas fa-users"></i>
                    <span>Kullanıcı Yönetimi</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $rootPath; ?>modules/ayarlar/index.php">
                    <i class="fas fa-cog"></i>
                    <span>Ayarlar</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $rootPath; ?>modules/yedekleme/index.php">
                    <i class="fas fa-database"></i>
                    <span>Yedekleme</span>
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="<?php echo $rootPath; ?>modules/yardim/index.php">
                    <i class="fas fa-question-circle"></i>
                    <span>Yardım</span>
                </a>
            </li>
            <li class="nav-item mt-2">
                <a class="nav-link bg-danger text-white" href="<?php echo $rootPath; ?>logout.php">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Çıkış Yap</span>
                </a>
            </li>
        </ul>
    </div>
</nav> 