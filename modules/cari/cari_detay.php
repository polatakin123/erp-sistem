<?php
/**
 * ERP Sistem - Cari Detay Sayfası
 * 
 * Bu dosya, seçilen carinin detaylı bilgilerini görüntüler
 */

// Hata raporlama ayarları
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$debug_mode = true; // Debug modu açık

// Oturum başlat
session_start();

// Test için otomatik oturum ataması
if (!isset($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 1; // Test için kullanıcı ID'si tanımlıyoruz
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// Sayfa başlığı
$pageTitle = "Cari Detay";

// ID kontrolü
if (!isset($_GET['id']) || empty($_GET['id'])) {
    header('Location: index.php');
    exit;
}

$cari_id = (int)$_GET['id'];

try {
    // Cari bilgilerini al
    $stmt = $db->prepare("SELECT * FROM cari WHERE ID = ?");
    $stmt->execute([$cari_id]);
    $cari = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$cari) {
        $error = "Cari bulunamadı!";
    }

    // Cari iletişim bilgilerini al (varsa)
    try {
        $stmt = $db->prepare("SELECT * FROM cari_iletisimler WHERE cari_id = ? ORDER BY id DESC");
        $stmt->execute([$cari_id]);
        $iletisimler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // İletişim tablosu yoksa veya başka bir hata oluştuysa boş bir dizi tanımla
        $iletisimler = [];
    }

    // Cari hareketlerini al
    try {
        $stmt = $db->prepare("
            SELECT * FROM cari_hareket 
            WHERE cari_id = ? 
            ORDER BY tarih DESC, id DESC
            LIMIT 20
        ");
        $stmt->execute([$cari_id]);
        $hareketler = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Hareket tablosu yoksa veya başka bir hata oluştuysa boş bir dizi tanımla
        $hareketler = [];
    }

    // Cari borç/alacak toplamları
    try {
        $stmt = $db->prepare("
            SELECT 
                SUM(CASE WHEN islem_tipi = 'borc' THEN tutar ELSE 0 END) as toplam_borc,
                SUM(CASE WHEN islem_tipi = 'alacak' THEN tutar ELSE 0 END) as toplam_alacak,
                SUM(CASE WHEN islem_tipi = 'borc' THEN tutar ELSE -tutar END) as bakiye
            FROM cari_hareket
            WHERE cari_id = ?
        ");
        $stmt->execute([$cari_id]);
        $ozet = $stmt->fetch(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        // Özet bilgileri yoksa varsayılan değerler oluştur
        $ozet = [
            'toplam_borc' => 0,
            'toplam_alacak' => 0,
            'bakiye' => 0
        ];
    }
    
} catch (PDOException $e) {
    $error = "Veritabanı hatası: " . $e->getMessage();
}

// Üst kısmı dahil et
include_once '../../includes/header.php';
?>

<div class="container-fluid">
    <!-- Sayfa İçeriği -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Cari Detay</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="index.php" class="btn btn-sm btn-outline-secondary">Cari Listesi</a>
                <a href="cari_duzenle.php?id=<?php echo $cari_id; ?>" class="btn btn-sm btn-outline-primary">Düzenle</a>
                <a href="cari_ekstre.php?id=<?php echo $cari_id; ?>" class="btn btn-sm btn-outline-info">Ekstre</a>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?php echo $error; ?></div>
    <?php elseif (isset($cari)): ?>
        <!-- Cari Detay Kartı -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3 d-flex justify-content-between align-items-center">
                        <h6 class="m-0 font-weight-bold text-primary">Temel Bilgiler</h6>
                        <span class="badge <?php echo $cari['DURUM'] == 1 ? 'bg-success' : 'bg-danger'; ?>">
                            <?php echo $cari['DURUM'] == 1 ? 'Aktif' : 'Pasif'; ?>
                        </span>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Cari Kodu:</div>
                            <div class="col-md-8"><?php echo htmlspecialchars($cari['KOD']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Firma Ünvanı:</div>
                            <div class="col-md-8"><?php echo htmlspecialchars($cari['ADI']); ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Cari Tipi:</div>
                            <div class="col-md-8">
                                <?php 
                                    switch ($cari['TIP']) {
                                        case 'musteri':
                                            echo '<span class="badge bg-primary">Müşteri</span>';
                                            break;
                                        case 'tedarikci':
                                            echo '<span class="badge bg-success">Tedarikçi</span>';
                                            break;
                                        case 'her_ikisi':
                                            echo '<span class="badge bg-info">Müşteri/Tedarikçi</span>';
                                            break;
                                        default:
                                            echo 'Belirtilmemiş';
                                    }
                                ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Yetkili:</div>
                            <div class="col-md-8">
                                <?php 
                                    $yetkili = [];
                                    if (!empty($cari['YETKILI_ADI'])) $yetkili[] = $cari['YETKILI_ADI'];
                                    if (!empty($cari['YETKILI_SOYADI'])) $yetkili[] = $cari['YETKILI_SOYADI'];
                                    echo !empty($yetkili) ? htmlspecialchars(implode(' ', $yetkili)) : 'Belirtilmemiş';
                                ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Telefon:</div>
                            <div class="col-md-8"><?php echo !empty($cari['TELEFON']) ? htmlspecialchars($cari['TELEFON']) : 'Belirtilmemiş'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Cep Telefonu:</div>
                            <div class="col-md-8"><?php echo !empty($cari['CEPNO']) ? htmlspecialchars($cari['CEPNO']) : 'Belirtilmemiş'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">E-posta:</div>
                            <div class="col-md-8"><?php echo !empty($cari['EMAIL']) ? htmlspecialchars($cari['EMAIL']) : 'Belirtilmemiş'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Web Sitesi:</div>
                            <div class="col-md-8">
                                <?php if (!empty($cari['WEB'])): ?>
                                    <a href="<?php echo htmlspecialchars($cari['WEB']); ?>" target="_blank"><?php echo htmlspecialchars($cari['WEB']); ?></a>
                                <?php else: ?>
                                    Belirtilmemiş
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-md-6">
                <div class="card shadow mb-4">
                    <div class="card-header py-3">
                        <h6 class="m-0 font-weight-bold text-primary">Adres ve Finans Bilgileri</h6>
                    </div>
                    <div class="card-body">
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Adres:</div>
                            <div class="col-md-8"><?php echo !empty($cari['ADRES']) ? nl2br(htmlspecialchars($cari['ADRES'])) : 'Belirtilmemiş'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">İl/İlçe:</div>
                            <div class="col-md-8">
                                <?php 
                                    $konum = [];
                                    if (!empty($cari['IL'])) $konum[] = $cari['IL'];
                                    if (!empty($cari['ILCE'])) $konum[] = $cari['ILCE'];
                                    echo !empty($konum) ? htmlspecialchars(implode('/', $konum)) : 'Belirtilmemiş';
                                ?>
                            </div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Posta Kodu:</div>
                            <div class="col-md-8"><?php echo !empty($cari['POSTA_KODU']) ? htmlspecialchars($cari['POSTA_KODU']) : 'Belirtilmemiş'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Risk Limiti:</div>
                            <div class="col-md-8"><?php echo !empty($cari['LIMITTL']) ? number_format($cari['LIMITTL'], 2, ',', '.') . ' ₺' : '0,00 ₺'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Ödeme Vadesi:</div>
                            <div class="col-md-8"><?php echo !empty($cari['VADE']) ? $cari['VADE'] . ' gün' : '0 gün'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Notlar:</div>
                            <div class="col-md-8"><?php echo !empty($cari['NOTLAR']) ? nl2br(htmlspecialchars($cari['NOTLAR'])) : 'Belirtilmemiş'; ?></div>
                        </div>
                        <div class="row mb-3">
                            <div class="col-md-4 font-weight-bold">Bakiye:</div>
                            <div class="col-md-8 <?php echo $cari['BAKIYE'] > 0 ? 'text-danger' : ($cari['BAKIYE'] < 0 ? 'text-success' : ''); ?>">
                                <?php 
                                    if (isset($cari['BAKIYE'])) {
                                        echo number_format(abs($cari['BAKIYE']), 2, ',', '.') . ' ₺ ';
                                        echo $cari['BAKIYE'] > 0 ? '(Borçlu)' : ($cari['BAKIYE'] < 0 ? '(Alacaklı)' : '(Nötr)');
                                    } else {
                                        echo '0,00 ₺ (Nötr)';
                                    }
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Cari Hareket Listesi -->
        <?php if (isset($hareketler) && count($hareketler) > 0): ?>
        <div class="card shadow mb-4">
            <div class="card-header py-3 d-flex justify-content-between align-items-center">
                <h6 class="m-0 font-weight-bold text-primary">Son Hareketler</h6>
                <a href="cari_ekstre.php?id=<?php echo $cari_id; ?>" class="btn btn-sm btn-outline-primary">Tüm Hareketler</a>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-bordered" width="100%" cellspacing="0">
                        <thead>
                            <tr>
                                <th>Tarih</th>
                                <th>İşlem No</th>
                                <th>Açıklama</th>
                                <th>İşlem Tipi</th>
                                <th class="text-end">Tutar</th>
                                <th class="text-end">Bakiye</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php
                            $guncel_bakiye = 0;
                            foreach ($hareketler as $hareket): 
                                if ($hareket['islem_tipi'] == 'borc') {
                                    $guncel_bakiye += $hareket['tutar'];
                                } else {
                                    $guncel_bakiye -= $hareket['tutar'];
                                }
                            ?>
                                <tr>
                                    <td><?php echo date('d.m.Y', strtotime($hareket['tarih'])); ?></td>
                                    <td><?php echo htmlspecialchars($hareket['islem_no']); ?></td>
                                    <td><?php echo htmlspecialchars($hareket['aciklama']); ?></td>
                                    <td>
                                        <?php if ($hareket['islem_tipi'] == 'borc'): ?>
                                            <span class="badge bg-danger">Borç</span>
                                        <?php else: ?>
                                            <span class="badge bg-success">Alacak</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end"><?php echo number_format($hareket['tutar'], 2, ',', '.'); ?> ₺</td>
                                    <td class="text-end <?php echo $guncel_bakiye > 0 ? 'text-danger' : ($guncel_bakiye < 0 ? 'text-success' : ''); ?>">
                                        <?php echo number_format(abs($guncel_bakiye), 2, ',', '.'); ?> ₺
                                        <?php echo $guncel_bakiye > 0 ? '(B)' : ($guncel_bakiye < 0 ? '(A)' : ''); ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
        <?php endif; ?>

    <?php endif; ?>
</div>

<?php include_once '../../includes/footer.php'; ?> 