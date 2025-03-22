<?php
/**
 * ERP Sistem - Ana Sayfa
 * 
 * Bu dosya sistemin ana sayfasını (dashboard) içerir.
 */

// Oturum ve güvenlik kontrolü
session_start();
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

// Veritabanı bağlantısı
try {
    require_once 'config/db.php';
} catch (Exception $e) {
    // Veritabanı hatası, kurulum sayfasına yönlendir
    header('Location: config/db_installer.php');
    exit;
}

// Şirket bilgilerini al
try {
    $stmt = $db->prepare("SELECT * FROM company_settings WHERE id = 1");
    $stmt->execute();
    
    if ($stmt->rowCount() > 0) {
        $company = $stmt->fetch(PDO::FETCH_ASSOC);
    } else {
        $company = ['name' => 'ERP Sistem'];
    }
} catch (PDOException $e) {
    $company = ['name' => 'ERP Sistem'];
}

// Dashboard özet verileri
$totalProduct = 0;
$monthlySales = 0;
$pendingOrders = 0;
$criticalStock = 0;
$recentTransactions = [];

try {
    // Toplam ürün sayısı
    $stmtProduct = $db->prepare("SELECT COUNT(*) AS total FROM products");
    $stmtProduct->execute();
    $totalProduct = $stmtProduct->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Aylık satış tutarı
    $stmtSales = $db->prepare("SELECT SUM(tutar) AS total FROM muhasebe_kayitlari 
                                WHERE islem_tipi = 'satis' 
                                AND MONTH(tarih) = MONTH(CURRENT_DATE()) 
                                AND YEAR(tarih) = YEAR(CURRENT_DATE())");
    $stmtSales->execute();
    $monthlySales = $stmtSales->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Bekleyen siparişler
    $stmtOrders = $db->prepare("SELECT COUNT(*) AS total FROM orders WHERE status = 'pending'");
    $stmtOrders->execute();
    $pendingOrders = $stmtOrders->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Kritik stok seviyesindeki ürünler
    $stmtCritical = $db->prepare("SELECT COUNT(*) AS total FROM products WHERE stock_quantity <= min_stock_level");
    $stmtCritical->execute();
    $criticalStock = $stmtCritical->fetch(PDO::FETCH_ASSOC)['total'] ?? 0;
    
    // Son işlemler
    $stmtTransactions = $db->prepare("SELECT * FROM muhasebe_kayitlari ORDER BY created_at DESC LIMIT 5");
    $stmtTransactions->execute();
    $recentTransactions = $stmtTransactions->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Hata durumunda işlem yapma
}

// Sayfa başlığı
$pageTitle = 'Ana Sayfa';

// Üst kısım
include 'includes/header.php';
?>

<!-- Sayfa Başlığı -->
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800">Ana Sayfa</h1>
    <div class="btn-group">
        <button id="sharePanel" class="d-none d-sm-inline-block btn btn-sm btn-primary shadow-sm me-2">
            <i class="fas fa-share fa-sm text-white-50 me-1"></i> Paneli Paylaş
        </button>
        <button id="exportData" class="d-none d-sm-inline-block btn btn-sm btn-success shadow-sm me-2">
            <i class="fas fa-download fa-sm text-white-50 me-1"></i> Veri Dışa Aktar
        </button>
        <div class="dropdown d-inline-block">
            <button class="btn btn-sm btn-info dropdown-toggle shadow-sm" type="button" id="dateRangeDropdown" data-bs-toggle="dropdown" aria-expanded="false">
                <i class="fas fa-calendar fa-sm text-white-50 me-1"></i> Tarih Aralığı
            </button>
            <ul class="dropdown-menu" aria-labelledby="dateRangeDropdown">
                <li><a class="dropdown-item" href="#" data-range="today">Bugün</a></li>
                <li><a class="dropdown-item" href="#" data-range="week">Bu Hafta</a></li>
                <li><a class="dropdown-item" href="#" data-range="month">Bu Ay</a></li>
                <li><a class="dropdown-item" href="#" data-range="year">Bu Yıl</a></li>
                <li><hr class="dropdown-divider"></li>
                <li><a class="dropdown-item" href="#" data-range="custom">Özel Aralık</a></li>
            </ul>
        </div>
    </div>
</div>

<!-- Özet Kartları -->
<div class="row">
    <!-- Toplam Stok Kartı -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-primary shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-primary text-uppercase mb-1">
                            Toplam Stok
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($totalProduct, 0, ',', '.'); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-box fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Aylık Gelir Kartı -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-success shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-success text-uppercase mb-1">
                            Aylık Gelir
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($monthlySales, 2, ',', '.') . ' ₺'; ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-money-bill-wave fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Bekleyen Siparişler Kartı -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-info shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-info text-uppercase mb-1">
                            Bekleyen Siparişler
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($pendingOrders, 0, ',', '.'); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-clipboard-list fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Kritik Stok Uyarıları Kartı -->
    <div class="col-xl-3 col-md-6 mb-4">
        <div class="card border-left-warning shadow h-100 py-2">
            <div class="card-body">
                <div class="row no-gutters align-items-center">
                    <div class="col mr-2">
                        <div class="text-xs font-weight-bold text-warning text-uppercase mb-1">
                            Kritik Stok Uyarıları
                        </div>
                        <div class="h5 mb-0 font-weight-bold text-gray-800"><?php echo number_format($criticalStock, 0, ',', '.'); ?></div>
                    </div>
                    <div class="col-auto">
                        <i class="fas fa-exclamation-triangle fa-2x text-gray-300"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Ana İçerik -->
<div class="row">
    <!-- Gelir Grafiği -->
    <div class="col-xl-8 col-lg-7">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Gelir Özeti</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">İşlemler:</div>
                        <a class="dropdown-item" href="#">Detayları Görüntüle</a>
                        <a class="dropdown-item" href="#">Raporu İndir</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#">Grafiği Yazdır</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-area">
                    <canvas id="incomeChart"></canvas>
                </div>
            </div>
        </div>
    </div>

    <!-- Kategori Dağılımı -->
    <div class="col-xl-4 col-lg-5">
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex flex-row align-items-center justify-content-between">
                <h6 class="m-0 font-weight-bold text-primary">Kategori Dağılımı</h6>
                <div class="dropdown no-arrow">
                    <a class="dropdown-toggle" href="#" role="button" id="dropdownMenuLink" data-bs-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
                        <i class="fas fa-ellipsis-v fa-sm fa-fw text-gray-400"></i>
                    </a>
                    <div class="dropdown-menu dropdown-menu-right shadow animated--fade-in" aria-labelledby="dropdownMenuLink">
                        <div class="dropdown-header">İşlemler:</div>
                        <a class="dropdown-item" href="#">Detayları Görüntüle</a>
                        <a class="dropdown-item" href="#">Raporu İndir</a>
                        <div class="dropdown-divider"></div>
                        <a class="dropdown-item" href="#">Grafiği Yazdır</a>
                    </div>
                </div>
            </div>
            <div class="card-body">
                <div class="chart-pie pt-4 pb-2">
                    <canvas id="categoryChart"></canvas>
                </div>
                <div class="mt-4 text-center small">
                    <span class="mr-2">
                        <i class="fas fa-circle text-primary"></i> Elektronik
                    </span>
                    <span class="mr-2">
                        <i class="fas fa-circle text-success"></i> Giyim
                    </span>
                    <span class="mr-2">
                        <i class="fas fa-circle text-info"></i> Gıda
                    </span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Son İşlemler ve Bildirimler -->
<div class="row">
    <!-- Son İşlemler -->
    <div class="col-lg-6 mb-4">
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Son İşlemler</h6>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>İşlem No</th>
                                <th>Tarih</th>
                                <th>Tip</th>
                                <th>Tutar</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($recentTransactions)): ?>
                                <tr>
                                    <td colspan="4" class="text-center">İşlem bulunamadı.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($recentTransactions as $transaction): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($transaction['islem_no']); ?></td>
                                        <td><?php echo date('d.m.Y', strtotime($transaction['tarih'])); ?></td>
                                        <td>
                                            <?php if ($transaction['islem_tipi'] == 'satis'): ?>
                                                <span class="badge bg-success">Satış</span>
                                            <?php elseif ($transaction['islem_tipi'] == 'alis'): ?>
                                                <span class="badge bg-danger">Alış</span>
                                            <?php elseif ($transaction['islem_tipi'] == 'odeme'): ?>
                                                <span class="badge bg-warning">Ödeme</span>
                                            <?php elseif ($transaction['islem_tipi'] == 'tahsilat'): ?>
                                                <span class="badge bg-info">Tahsilat</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary"><?php echo ucfirst($transaction['islem_tipi']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-end">
                                            <?php if ($transaction['borc_alacak'] == 'alacak'): ?>
                                                <span class="text-success">+<?php echo number_format($transaction['tutar'], 2, ',', '.') . ' ₺'; ?></span>
                                            <?php else: ?>
                                                <span class="text-danger">-<?php echo number_format($transaction['tutar'], 2, ',', '.') . ' ₺'; ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
                <div class="text-center mt-3">
                    <a href="modules/muhasebe/index.php" class="btn btn-primary btn-sm">
                        Tüm İşlemleri Görüntüle
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bildirimler ve Yapılacaklar -->
    <div class="col-lg-6 mb-4">
        <!-- Bildirimler -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Bildirimler</h6>
            </div>
            <div class="card-body">
                <div class="list-group">
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><i class="fas fa-exclamation-circle text-danger me-2"></i> Kritik stok seviyesi</div>
                            <small class="text-muted">3 ürün kritik stok seviyesinin altında</small>
                        </div>
                        <span class="badge bg-danger rounded-pill">Yeni</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><i class="fas fa-file-invoice text-primary me-2"></i> Yeni fatura</div>
                            <small class="text-muted">2 yeni satış faturası oluşturuldu</small>
                        </div>
                        <span class="badge bg-primary rounded-pill">Bugün</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><i class="fas fa-money-check text-success me-2"></i> Tahsilat hatırlatması</div>
                            <small class="text-muted">Yarın vadesi dolacak 2 çek bulunuyor</small>
                        </div>
                        <span class="badge bg-warning rounded-pill">Önemli</span>
                    </a>
                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                        <div>
                            <div class="fw-bold"><i class="fas fa-truck text-info me-2"></i> Sipariş durumu</div>
                            <small class="text-muted">5 sipariş teslim edildi</small>
                        </div>
                        <span class="badge bg-success rounded-pill">Tamamlandı</span>
                    </a>
                </div>
                <div class="text-center mt-3">
                    <a href="#" class="btn btn-primary btn-sm">
                        Tüm Bildirimleri Görüntüle
                    </a>
                </div>
            </div>
        </div>

        <!-- Yapılacaklar -->
        <div class="card shadow mb-4">
            <div class="card-header py-3">
                <h6 class="m-0 font-weight-bold text-primary">Yapılacaklar</h6>
            </div>
            <div class="card-body">
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="" id="task1">
                    <label class="form-check-label" for="task1">
                        <span class="text-primary fw-bold">Stok sayımı yapılacak</span> - 25.06.2024
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="" id="task2" checked>
                    <label class="form-check-label text-decoration-line-through" for="task2">
                        <span class="fw-bold">Fatura ödemeleri kontrol edilecek</span> - 20.06.2024
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="" id="task3">
                    <label class="form-check-label" for="task3">
                        <span class="text-danger fw-bold">Vergi ödemeleri yapılacak</span> - 28.06.2024
                    </label>
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" value="" id="task4">
                    <label class="form-check-label" for="task4">
                        <span class="fw-bold">Yeni ürün girişleri yapılacak</span> - 30.06.2024
                    </label>
                </div>

                <div class="input-group mt-3">
                    <input type="text" class="form-control" placeholder="Yeni görev ekle">
                    <button class="btn btn-primary" type="button">Ekle</button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Grafikler için JavaScript -->
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Gelir grafiği
    const incomeCtx = document.getElementById('incomeChart').getContext('2d');
    const incomeChart = new Chart(incomeCtx, {
        type: 'line',
        data: {
            labels: ['Oca', 'Şub', 'Mar', 'Nis', 'May', 'Haz', 'Tem', 'Ağu', 'Eyl', 'Eki', 'Kas', 'Ara'],
            datasets: [{
                label: 'Gelir (₺)',
                lineTension: 0.3,
                backgroundColor: "rgba(78, 115, 223, 0.05)",
                borderColor: "rgba(78, 115, 223, 1)",
                pointRadius: 3,
                pointBackgroundColor: "rgba(78, 115, 223, 1)",
                pointBorderColor: "rgba(78, 115, 223, 1)",
                pointHoverRadius: 3,
                pointHoverBackgroundColor: "rgba(78, 115, 223, 1)",
                pointHoverBorderColor: "rgba(78, 115, 223, 1)",
                pointHitRadius: 10,
                pointBorderWidth: 2,
                data: [0, 10000, 5000, 15000, 10000, 20000, 15000, 25000, 20000, 30000, 25000, 40000],
            }],
        },
        options: {
            maintainAspectRatio: false,
            layout: {
                padding: {
                    left: 10,
                    right: 25,
                    top: 25,
                    bottom: 0
                }
            },
            scales: {
                x: {
                    grid: {
                        display: false,
                        drawBorder: false
                    }
                },
                y: {
                    ticks: {
                        maxTicksLimit: 5,
                        padding: 10,
                        callback: function(value, index, values) {
                            return value.toLocaleString('tr-TR') + ' ₺';
                        }
                    },
                    grid: {
                        color: "rgb(234, 236, 244)",
                        zeroLineColor: "rgb(234, 236, 244)",
                        drawBorder: false,
                        borderDash: [2],
                        zeroLineBorderDash: [2]
                    }
                },
            },
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    titleMarginBottom: 10,
                    titleColor: '#6e707e',
                    titleFontSize: 14,
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    padding: 15,
                    displayColors: false,
                    callbacks: {
                        label: function(context) {
                            let label = context.dataset.label || '';
                            let value = context.parsed.y;
                            return label + ': ' + value.toLocaleString('tr-TR') + ' ₺';
                        }
                    }
                }
            }
        }
    });

    // Kategori dağılımı grafiği
    const categoryCtx = document.getElementById('categoryChart').getContext('2d');
    const categoryChart = new Chart(categoryCtx, {
        type: 'doughnut',
        data: {
            labels: ["Elektronik", "Giyim", "Gıda"],
            datasets: [{
                data: [55, 30, 15],
                backgroundColor: ['#4e73df', '#1cc88a', '#36b9cc'],
                hoverBackgroundColor: ['#2e59d9', '#17a673', '#2c9faf'],
                hoverBorderColor: "rgba(234, 236, 244, 1)",
            }],
        },
        options: {
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                },
                tooltip: {
                    backgroundColor: "rgb(255,255,255)",
                    bodyColor: "#858796",
                    borderColor: '#dddfeb',
                    borderWidth: 1,
                    padding: 15,
                    displayColors: false
                }
            },
            cutout: '70%',
        },
    });

    // Buton işlevleri
    document.getElementById('sharePanel').addEventListener('click', function() {
        alert('Panel paylaşımı özelliği yakında aktif olacaktır.');
    });

    document.getElementById('exportData').addEventListener('click', function() {
        alert('Veri dışa aktarma özelliği yakında aktif olacaktır.');
    });

    const dateLinks = document.querySelectorAll('[data-range]');
    dateLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const range = this.getAttribute('data-range');
            
            if (range === 'custom') {
                alert('Özel tarih aralığı seçimi yakında aktif olacaktır.');
            } else {
                // Tarih aralığına göre grafiği güncelle
                alert(this.textContent + ' için veriler yükleniyor...');
            }
        });
    });
});
</script>

<?php
// Alt kısım
include 'includes/footer.php';
?> 