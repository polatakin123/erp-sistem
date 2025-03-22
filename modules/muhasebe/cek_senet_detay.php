<?php
session_start();
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Oturum ve yetki kontrolü
if (!isset($_SESSION['user_id']) || !hasPermission($_SESSION['user_id'], 'muhasebe_cek_senet')) {
    die('Yetkisiz erişim!');
}

// İşlem ID kontrolü
if (!isset($_GET['id'])) {
    die('İşlem ID gerekli!');
}

$islem_id = $_GET['id'];

// İşlem bilgilerini getir
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
        WHERE cs.id = ?";

$stmt = $db->prepare($sql);
$stmt->execute([$islem_id]);
$islem = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$islem) {
    die('İşlem bulunamadı!');
}
?>

<div class="row">
    <div class="col-md-6">
        <table class="table">
            <tr>
                <th>İşlem No:</th>
                <td><?php echo $islem['islem_no']; ?></td>
            </tr>
            <tr>
                <th>İşlem Tipi:</th>
                <td><?php echo $islem['islem_tipi']; ?></td>
            </tr>
            <tr>
                <th>Tarih:</th>
                <td><?php echo date('d.m.Y', strtotime($islem['tarih'])); ?></td>
            </tr>
            <tr>
                <th>Vade Tarihi:</th>
                <td><?php echo date('d.m.Y', strtotime($islem['vade_tarihi'])); ?></td>
            </tr>
            <tr>
                <th>Tutar:</th>
                <td><?php echo number_format($islem['tutar'], 2, ',', '.'); ?> ₺</td>
            </tr>
            <tr>
                <th>Durum:</th>
                <td>
                    <span class="badge bg-<?php 
                        echo $islem['durum'] == 'beklemede' ? 'warning' : 
                            ($islem['durum'] == 'tahsil_edildi' ? 'success' : 
                            ($islem['durum'] == 'odendi' ? 'info' : 'danger')); 
                    ?>">
                        <?php echo $islem['durum_text']; ?>
                    </span>
                </td>
            </tr>
            <tr>
                <th>Referans No:</th>
                <td><?php echo $islem['referans_no'] ?: '-'; ?></td>
            </tr>
            <tr>
                <th>Açıklama:</th>
                <td><?php echo nl2br($islem['aciklama'] ?: '-'); ?></td>
            </tr>
        </table>
    </div>
    <div class="col-md-6">
        <?php if ($islem['durum'] == 'beklemede'): ?>
        <div class="alert alert-warning">
            <h5>İşlem Durumu: Beklemede</h5>
            <p>Bu işlem için aşağıdaki işlemleri yapabilirsiniz:</p>
            <button type="button" class="btn btn-success" onclick="durumGuncelle(<?php echo $islem['id']; ?>, 'tahsil_edildi')">
                <i class="fas fa-check"></i> Tahsil Edildi Olarak İşaretle
            </button>
            <button type="button" class="btn btn-danger" onclick="durumGuncelle(<?php echo $islem['id']; ?>, 'iptal')">
                <i class="fas fa-times"></i> İptal Et
            </button>
        </div>
        <?php endif; ?>

        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">İşlem Geçmişi</h5>
            </div>
            <div class="card-body">
                <div class="timeline">
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?php echo date('d.m.Y H:i', strtotime($islem['created_at'])); ?>
                        </div>
                        <div class="timeline-content">
                            <h6>İşlem Oluşturuldu</h6>
                            <p>İşlem <?php echo $islem['created_by']; ?> tarafından oluşturuldu.</p>
                        </div>
                    </div>
                    <?php if ($islem['updated_at']): ?>
                    <div class="timeline-item">
                        <div class="timeline-date">
                            <?php echo date('d.m.Y H:i', strtotime($islem['updated_at'])); ?>
                        </div>
                        <div class="timeline-content">
                            <h6>İşlem Güncellendi</h6>
                            <p>İşlem <?php echo $islem['updated_by']; ?> tarafından güncellendi.</p>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.timeline {
    position: relative;
    padding: 20px 0;
}

.timeline-item {
    position: relative;
    padding-left: 30px;
    margin-bottom: 20px;
}

.timeline-item:before {
    content: '';
    position: absolute;
    left: 0;
    top: 0;
    width: 12px;
    height: 12px;
    border-radius: 50%;
    background: #007bff;
}

.timeline-item:after {
    content: '';
    position: absolute;
    left: 5px;
    top: 12px;
    width: 2px;
    height: calc(100% + 8px);
    background: #e9ecef;
}

.timeline-item:last-child:after {
    display: none;
}

.timeline-date {
    font-size: 0.875rem;
    color: #6c757d;
    margin-bottom: 5px;
}

.timeline-content {
    background: #f8f9fa;
    padding: 10px;
    border-radius: 4px;
}

.timeline-content h6 {
    margin: 0 0 5px 0;
    color: #343a40;
}

.timeline-content p {
    margin: 0;
    color: #6c757d;
}
</style> 