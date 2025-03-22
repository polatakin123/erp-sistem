<?php
/**
 * Çek/Senet İşlemleri Sayfası
 * 
 * Bu sayfa çek ve senet işlemlerinin yönetimi için kullanılır.
 * İşlemlerin listelenmesi, eklenmesi ve yönetilmesi sağlanır.
 */

session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasPermission($_SESSION['user_id'], 'muhasebe_cek_senet')) {
    header('Location: /erp_sistem/login.php');
    exit;
}

// Filtreleme parametreleri
$filtre_tip = isset($_GET['tip']) ? $_GET['tip'] : '';
$filtre_durum = isset($_GET['durum']) ? $_GET['durum'] : '';
$filtre_tarih_baslangic = isset($_GET['tarih_baslangic']) ? $_GET['tarih_baslangic'] : '';
$filtre_tarih_bitis = isset($_GET['tarih_bitis']) ? $_GET['tarih_bitis'] : '';
$filtre_tutar_min = isset($_GET['tutar_min']) ? $_GET['tutar_min'] : '';
$filtre_tutar_max = isset($_GET['tutar_max']) ? $_GET['tutar_max'] : '';

// SQL sorgusu oluştur
$sql = "SELECT cs.*, 
        CASE 
            WHEN cs.tip = 'cek' THEN 'Çek'
            ELSE 'Senet'
        END as islem_tipi,
        CASE 
            WHEN cs.durum = 'beklemede' THEN 'Beklemede'
            WHEN cs.durum = 'tahsil_edildi' THEN 'Tahsil Edildi'
            WHEN cs.durum = 'odendi' THEN 'Ödendi'
            WHEN cs.durum = 'iptal' THEN 'İptal'
            ELSE 'Bilinmiyor'
        END as durum_text
        FROM cek_senet cs
        WHERE 1=1";

$params = array();

if ($filtre_tip) {
    $sql .= " AND cs.tip = ?";
    $params[] = $filtre_tip;
}

if ($filtre_durum) {
    $sql .= " AND cs.durum = ?";
    $params[] = $filtre_durum;
}

if ($filtre_tarih_baslangic) {
    $sql .= " AND cs.tarih >= ?";
    $params[] = $filtre_tarih_baslangic;
}

if ($filtre_tarih_bitis) {
    $sql .= " AND cs.tarih <= ?";
    $params[] = $filtre_tarih_bitis;
}

if ($filtre_tutar_min) {
    $sql .= " AND cs.tutar >= ?";
    $params[] = $filtre_tutar_min;
}

if ($filtre_tutar_max) {
    $sql .= " AND cs.tutar <= ?";
    $params[] = $filtre_tutar_max;
}

$sql .= " ORDER BY cs.tarih DESC";

// Sorguyu çalıştır
$stmt = $db->prepare($sql);
$stmt->execute($params);
$cek_senetler = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Özet bilgileri hesapla
$toplam_bekleyen = 0;
$toplam_tahsil_edilen = 0;
$toplam_odenen = 0;
$toplam_iptal = 0;

foreach ($cek_senetler as $islem) {
    switch ($islem['durum']) {
        case 'beklemede':
            $toplam_bekleyen += $islem['tutar'];
            break;
        case 'tahsil_edildi':
            $toplam_tahsil_edilen += $islem['tutar'];
            break;
        case 'odendi':
            $toplam_odenen += $islem['tutar'];
            break;
        case 'iptal':
            $toplam_iptal += $islem['tutar'];
            break;
    }
}

// Sayfa başlığı
$page_title = "Çek/Senet İşlemleri";

// Header'ı dahil et
include '../../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <!-- Sidebar -->
        <?php include '../../includes/sidebar.php'; ?>

        <!-- Ana içerik -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                <h1 class="h2">Çek/Senet İşlemleri</h1>
                <div class="btn-toolbar mb-2 mb-md-0">
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#yeniIslemModal">
                        <i class="fas fa-plus"></i> Yeni İşlem
                    </button>
                </div>
            </div>

            <!-- Özet kartları -->
            <div class="row mb-4">
                <div class="col-md-3">
                    <div class="card text-white bg-warning">
                        <div class="card-body">
                            <h5 class="card-title">Bekleyen</h5>
                            <p class="card-text h3"><?php echo number_format($toplam_bekleyen, 2, ',', '.'); ?> ₺</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-success">
                        <div class="card-body">
                            <h5 class="card-title">Tahsil Edilen</h5>
                            <p class="card-text h3"><?php echo number_format($toplam_tahsil_edilen, 2, ',', '.'); ?> ₺</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-info">
                        <div class="card-body">
                            <h5 class="card-title">Ödenen</h5>
                            <p class="card-text h3"><?php echo number_format($toplam_odenen, 2, ',', '.'); ?> ₺</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-3">
                    <div class="card text-white bg-danger">
                        <div class="card-body">
                            <h5 class="card-title">İptal</h5>
                            <p class="card-text h3"><?php echo number_format($toplam_iptal, 2, ',', '.'); ?> ₺</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Filtre formu -->
            <div class="card mb-4">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-2">
                            <label class="form-label">İşlem Tipi</label>
                            <select name="tip" class="form-select">
                                <option value="">Tümü</option>
                                <option value="cek" <?php echo $filtre_tip == 'cek' ? 'selected' : ''; ?>>Çek</option>
                                <option value="senet" <?php echo $filtre_tip == 'senet' ? 'selected' : ''; ?>>Senet</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Durum</label>
                            <select name="durum" class="form-select">
                                <option value="">Tümü</option>
                                <option value="beklemede" <?php echo $filtre_durum == 'beklemede' ? 'selected' : ''; ?>>Beklemede</option>
                                <option value="tahsil_edildi" <?php echo $filtre_durum == 'tahsil_edildi' ? 'selected' : ''; ?>>Tahsil Edildi</option>
                                <option value="odendi" <?php echo $filtre_durum == 'odendi' ? 'selected' : ''; ?>>Ödendi</option>
                                <option value="iptal" <?php echo $filtre_durum == 'iptal' ? 'selected' : ''; ?>>İptal</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tarih Başlangıç</label>
                            <input type="date" name="tarih_baslangic" class="form-control" value="<?php echo $filtre_tarih_baslangic; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Tarih Bitiş</label>
                            <input type="date" name="tarih_bitis" class="form-control" value="<?php echo $filtre_tarih_bitis; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Min. Tutar</label>
                            <input type="number" name="tutar_min" class="form-control" value="<?php echo $filtre_tutar_min; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Max. Tutar</label>
                            <input type="number" name="tutar_max" class="form-control" value="<?php echo $filtre_tutar_max; ?>">
                        </div>
                        <div class="col-12">
                            <button type="submit" class="btn btn-primary">Filtrele</button>
                            <a href="cek_senet.php" class="btn btn-secondary">Temizle</a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- İşlem listesi -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-striped table-hover" id="islemlerTable">
                            <thead>
                                <tr>
                                    <th>İşlem No</th>
                                    <th>Tip</th>
                                    <th>Tarih</th>
                                    <th>Vade Tarihi</th>
                                    <th>Tutar</th>
                                    <th>Durum</th>
                                    <th>İşlemler</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($cek_senetler as $islem): ?>
                                <tr>
                                    <td><?php echo $islem['islem_no']; ?></td>
                                    <td><?php echo $islem['islem_tipi']; ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($islem['tarih'])); ?></td>
                                    <td><?php echo date('d.m.Y', strtotime($islem['vade_tarihi'])); ?></td>
                                    <td><?php echo number_format($islem['tutar'], 2, ',', '.'); ?> ₺</td>
                                    <td>
                                        <span class="badge bg-<?php 
                                            echo $islem['durum'] == 'beklemede' ? 'warning' : 
                                                ($islem['durum'] == 'tahsil_edildi' ? 'success' : 
                                                ($islem['durum'] == 'odendi' ? 'info' : 'danger')); 
                                        ?>">
                                            <?php echo $islem['durum_text']; ?>
                                        </span>
                                    </td>
                                    <td>
                                        <button type="button" class="btn btn-sm btn-info" onclick="islemDetay(<?php echo $islem['id']; ?>)">
                                            <i class="fas fa-eye"></i>
                                        </button>
                                        <?php if ($islem['durum'] == 'beklemede'): ?>
                                        <button type="button" class="btn btn-sm btn-success" onclick="durumGuncelle(<?php echo $islem['id']; ?>, 'tahsil_edildi')">
                                            <i class="fas fa-check"></i>
                                        </button>
                                        <button type="button" class="btn btn-sm btn-danger" onclick="durumGuncelle(<?php echo $islem['id']; ?>, 'iptal')">
                                            <i class="fas fa-times"></i>
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Yeni İşlem Modal -->
<div class="modal fade" id="yeniIslemModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Yeni Çek/Senet İşlemi</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form id="yeniIslemForm" action="cek_senet_islem.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">İşlem Tipi</label>
                        <select name="tip" class="form-select" required>
                            <option value="">Seçiniz</option>
                            <option value="cek">Çek</option>
                            <option value="senet">Senet</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tutar</label>
                        <input type="number" name="tutar" class="form-control" step="0.01" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Tarih</label>
                        <input type="date" name="tarih" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Vade Tarihi</label>
                        <input type="date" name="vade_tarihi" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Referans No</label>
                        <input type="text" name="referans_no" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Açıklama</label>
                        <textarea name="aciklama" class="form-control" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                    <button type="submit" class="btn btn-primary">Kaydet</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- İşlem Detay Modal -->
<div class="modal fade" id="islemDetayModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">İşlem Detayı</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="islemDetayIcerik">
                <!-- Detay bilgileri AJAX ile yüklenecek -->
            </div>
        </div>
    </div>
</div>

<script>
$(document).ready(function() {
    // DataTable başlat
    $('#islemlerTable').DataTable({
        "language": {
            "url": "//cdn.datatables.net/plug-ins/1.10.24/i18n/Turkish.json"
        }
    });

    // Form gönderimi
    $('#yeniIslemForm').on('submit', function(e) {
        e.preventDefault();
        
        $.ajax({
            url: $(this).attr('action'),
            type: 'POST',
            data: $(this).serialize(),
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Bir hata oluştu!');
            }
        });
    });
});

// İşlem detayı görüntüleme
function islemDetay(id) {
    $.ajax({
        url: 'cek_senet_detay.php',
        type: 'GET',
        data: { id: id },
        success: function(response) {
            $('#islemDetayIcerik').html(response);
            $('#islemDetayModal').modal('show');
        }
    });
}

// Durum güncelleme
function durumGuncelle(id, yeniDurum) {
    if (confirm('İşlem durumunu güncellemek istediğinize emin misiniz?')) {
        $.ajax({
            url: 'cek_senet_durum_guncelle.php',
            type: 'POST',
            data: {
                id: id,
                durum: yeniDurum
            },
            success: function(response) {
                if (response.success) {
                    location.reload();
                } else {
                    alert(response.message);
                }
            },
            error: function() {
                alert('Bir hata oluştu!');
            }
        });
    }
}
</script>

<?php include '../../includes/footer.php'; ?> 