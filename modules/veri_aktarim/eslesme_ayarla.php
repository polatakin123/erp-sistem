<?php
/**
 * ERP Sistem - Veri Aktarımı - Alan Eşleştirme Sayfası
 * 
 * Bu dosya, kaynak ve hedef tablolar arasında alan eşleştirmesi yapılması için kullanılır.
 */

// Oturum başlat
session_start();

// Önceki tüm mesajları temizle
unset($_SESSION['error_message']);
unset($_SESSION['success_message']);
unset($_SESSION['warning_message']);

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: ../../login.php');
    exit;
}

// Veritabanı bağlantı bilgileri kontrolü
if (!isset($_SESSION['source_db'])) {
    $_SESSION['error_message'] = "Veritabanı bağlantı bilgisi bulunamadı. Lütfen önce bağlantı kurun.";
    header('Location: index.php');
    exit;
}

// Gerekli dosyaları dahil et
require_once '../../config/db.php';
require_once '../../includes/functions.php';

// POST verilerini kontrol et
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || 
    !isset($_POST['source_tables']) || empty($_POST['source_tables'])) {
    $_SESSION['error_message'] = "Kaynak tablo seçimi yapılmadı. Lütfen tekrar deneyin.";
    header('Location: tablo_secimi.php');
    exit;
}

// Kaynak ve hedef tabloları al
$source_tables = $_POST['source_tables'];
$target_tables = isset($_POST['target_tables']) ? $_POST['target_tables'] : [];

// Şimdi her kaynak tablo için bir hedef tablo olduğundan emin olalım
// İlişkisel diziden normal diziye dönüştürelim
$processed_target_tables = [];
foreach ($source_tables as $source_table) {
    // Eğer bu kaynak tablo için bir hedef tablo belirtilmişse kullan, yoksa aynı adı kullan
    $processed_target_tables[] = isset($target_tables[$source_table]) && !empty($target_tables[$source_table]) ? $target_tables[$source_table] : $source_table;
}

// İkisi de aynı sayıda değilse hata ver
if (count($source_tables) != count($processed_target_tables)) {
    $_SESSION['error_message'] = "Kaynak ve hedef tablo sayıları eşleşmiyor. Lütfen tekrar deneyin.";
    header('Location: tablo_secimi.php');
    exit;
}

// Sayfa başlığı
$pageTitle = "Alan Eşleştirme";

// Veritabanı bağlantı bilgilerini al
$source_db = $_SESSION['source_db'];
$is_mssql = $source_db['is_mssql'];

// Kaynak veritabanına bağlan
try {
    if ($is_mssql) {
        // MSSQL bağlantısı (PDO kullanarak)
        $dsn = "sqlsrv:Server=" . $source_db['host'] . ";Database=" . $source_db['name'];
        $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass']);
        $sourceDb->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    } else {
        // MySQL bağlantısı
        $dsn = "mysql:host=" . $source_db['host'] . ";dbname=" . $source_db['name'];
        if (!empty($source_db['charset'])) {
            $dsn .= ";charset=" . $source_db['charset'];
        }
        
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ];
        
        $sourceDb = new PDO($dsn, $source_db['user'], $source_db['pass'], $options);
    }
    
    // Tablo bilgilerini saklamak için dizi
    $tables_info = [];
    
    // Her bir tablo çifti için bilgileri al
    for ($i = 0; $i < count($source_tables); $i++) {
        $source_table = $source_tables[$i];
        $target_table = $processed_target_tables[$i];
        
        // Kaynak tablodaki sütunları al
        if ($is_mssql) {
            $stmt = $sourceDb->prepare("SELECT COLUMN_NAME, DATA_TYPE, CHARACTER_MAXIMUM_LENGTH 
                                     FROM INFORMATION_SCHEMA.COLUMNS 
                                     WHERE TABLE_NAME = ? 
                                     ORDER BY ORDINAL_POSITION");
            $stmt->execute([$source_table]);
            $source_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sütun adlarını düzenle
            foreach ($source_columns as &$col) {
                $col['name'] = $col['COLUMN_NAME'];
                $col['type'] = $col['DATA_TYPE'];
                $col['length'] = $col['CHARACTER_MAXIMUM_LENGTH'];
            }
        } else {
            $stmt = $sourceDb->prepare("SHOW COLUMNS FROM `$source_table`");
            $stmt->execute();
            $source_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Sütun adlarını düzenle
            foreach ($source_columns as &$col) {
                $col['name'] = $col['Field'];
                $col['type'] = preg_replace('/\(.*\)/', '', $col['Type']);
                preg_match('/\((\d+)\)/', $col['Type'], $matches);
                $col['length'] = isset($matches[1]) ? $matches[1] : null;
            }
        }
        
        // Hedef tablodaki sütunları al (eğer varsa)
        try {
            $target_exists = false;
            $target_columns = [];
            
            $stmt = $db->prepare("SHOW TABLES LIKE ?");
            $stmt->execute([$target_table]);
            
            if ($stmt->rowCount() > 0) {
                $target_exists = true;
                
                $stmt = $db->prepare("SHOW COLUMNS FROM `$target_table`");
                $stmt->execute();
                $target_columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Sütun adlarını düzenle
                foreach ($target_columns as &$col) {
                    $col['name'] = $col['Field'];
                    $col['type'] = preg_replace('/\(.*\)/', '', $col['Type']);
                    preg_match('/\((\d+)\)/', $col['Type'], $matches);
                    $col['length'] = isset($matches[1]) ? $matches[1] : null;
                    $col['is_primary'] = $col['Key'] == 'PRI';
                }
            }
        } catch (PDOException $e) {
            $target_exists = false;
            $target_columns = [];
        }
        
        // Otomatik eşleştirme için sütun adlarının benzerliğini hesapla
        $suggested_mappings = [];
        
        foreach ($source_columns as $s_col) {
            $source_col_name = strtolower($s_col['name']);
            $best_match = null;
            $best_match_score = 0;
            
            // Hedef tablo varsa sütunlarla karşılaştır
            if ($target_exists) {
                foreach ($target_columns as $t_col) {
                    $target_col_name = strtolower($t_col['name']);
                    
                    // Tam eşleşme
                    if ($source_col_name == $target_col_name) {
                        $best_match = $t_col['name'];
                        $best_match_score = 100;
                        break;
                    }
                    
                    // Benzerlik skoru
                    $score = 0;
                    
                    // Prefix/Suffix uyumsuzluklarını kontrol et
                    if (strpos($source_col_name, $target_col_name) === 0 || 
                        strpos($target_col_name, $source_col_name) === 0) {
                        $score = 80;
                    } 
                    // Levenshtein mesafesine göre benzerlik skoru
                    else {
                        $lev = levenshtein($source_col_name, $target_col_name);
                        $max_len = max(strlen($source_col_name), strlen($target_col_name));
                        $score = (1 - $lev / $max_len) * 70;
                    }
                    
                    if ($score > $best_match_score) {
                        $best_match = $t_col['name'];
                        $best_match_score = $score;
                    }
                }
            }
            
            // Eğer benzerlik skoru yeterince yüksekse öneri olarak ekle
            if ($best_match_score >= 70) {
                $suggested_mappings[$s_col['name']] = $best_match;
            } else {
                $suggested_mappings[$s_col['name']] = '';
            }
        }
        
        // Tablo bilgilerini kaydet
        $tables_info[] = [
            'source_table' => $source_table,
            'target_table' => $target_table,
            'source_columns' => $source_columns,
            'target_columns' => $target_columns,
            'target_exists' => $target_exists,
            'suggested_mappings' => $suggested_mappings
        ];
    }
    
    // Üst kısmı dahil et
    include_once '../../includes/header.php';
    ?>
    
    <!-- Sayfa Başlığı -->
    <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
        <h1 class="h2">Alan Eşleştirme</h1>
        <div class="btn-toolbar mb-2 mb-md-0">
            <div class="btn-group me-2">
                <a href="tablo_secimi.php" class="btn btn-sm btn-outline-secondary">
                    <i class="fas fa-arrow-left"></i> Tablo Seçimi
                </a>
            </div>
        </div>
    </div>
    
    <!-- Eşleştirme Formu -->
    <form action="veri_aktar.php" method="post" id="mappingForm">
        <div class="accordion" id="accordionMapping">
            <?php foreach ($tables_info as $index => $table): ?>
            <div class="accordion-item mb-3">
                <h2 class="accordion-header" id="heading<?php echo $index; ?>">
                    <button class="accordion-button <?php echo $index > 0 ? 'collapsed' : ''; ?>" type="button" data-bs-toggle="collapse" data-bs-target="#collapse<?php echo $index; ?>" aria-expanded="<?php echo $index == 0 ? 'true' : 'false'; ?>" aria-controls="collapse<?php echo $index; ?>">
                        <strong><?php echo htmlspecialchars($table['source_table']); ?></strong> &rarr; <strong><?php echo htmlspecialchars($table['target_table']); ?></strong>
                        <?php if ($table['target_exists']): ?>
                            <span class="badge bg-success ms-2">Hedef Tablo Mevcut</span>
                        <?php else: ?>
                            <span class="badge bg-warning ms-2">Hedef Tablo Oluşturulacak</span>
                        <?php endif; ?>
                    </button>
                </h2>
                
                <div id="collapse<?php echo $index; ?>" class="accordion-collapse collapse <?php echo $index == 0 ? 'show' : ''; ?>" aria-labelledby="heading<?php echo $index; ?>" data-bs-parent="#accordionMapping">
                    <div class="accordion-body">
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> <strong><?php echo htmlspecialchars($table['source_table']); ?></strong> tablosunun alanlarını <strong><?php echo htmlspecialchars($table['target_table']); ?></strong> tablosunun alanlarıyla eşleştirin.
                            <?php if (!$table['target_exists']): ?>
                                <br>Hedef tablo mevcut değil, aktarım sırasında otomatik oluşturulacak.
                            <?php endif; ?>
                        </div>
                        
                        <div class="table-responsive">
                            <table class="table table-bordered table-hover">
                                <thead class="table-light">
                                    <tr>
                                        <th>Kaynak Alan</th>
                                        <th>Veri Tipi</th>
                                        <th>Hedef Alan</th>
                                        <th>Birincil Anahtar</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($table['source_columns'] as $col): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($col['name']); ?></td>
                                        <td>
                                            <?php 
                                            $type_display = htmlspecialchars($col['type']);
                                            if (!empty($col['length']) && $col['length'] != -1) {
                                                $type_display .= '(' . $col['length'] . ')';
                                            }
                                            echo $type_display;
                                            ?>
                                        </td>
                                        <td>
                                            <input type="hidden" name="mapping[<?php echo htmlspecialchars($table['source_table']); ?>][source_columns][]" value="<?php echo htmlspecialchars($col['name']); ?>">
                                            
                                            <?php if ($table['target_exists']): ?>
                                                <select name="mapping[<?php echo htmlspecialchars($table['source_table']); ?>][target_columns][]" class="form-select">
                                                    <option value="">-- Alan Seçiniz --</option>
                                                    <?php foreach ($table['target_columns'] as $t_col): ?>
                                                        <option value="<?php echo htmlspecialchars($t_col['name']); ?>" <?php echo isset($table['suggested_mappings'][$col['name']]) && $table['suggested_mappings'][$col['name']] == $t_col['name'] ? 'selected' : ''; ?>>
                                                            <?php echo htmlspecialchars($t_col['name']); ?> (<?php echo htmlspecialchars($t_col['type']); ?>)
                                                        </option>
                                                    <?php endforeach; ?>
                                                    <option value="AUTO:<?php echo htmlspecialchars($col['name']); ?>" <?php echo !isset($table['suggested_mappings'][$col['name']]) || empty($table['suggested_mappings'][$col['name']]) ? 'selected' : ''; ?>>
                                                        Otomatik: <?php echo htmlspecialchars($col['name']); ?>
                                                    </option>
                                                </select>
                                            <?php else: ?>
                                                <div class="input-group">
                                                    <span class="input-group-text"><i class="fas fa-magic"></i></span>
                                                    <input type="text" name="mapping[<?php echo htmlspecialchars($table['source_table']); ?>][target_columns][]" class="form-control" value="<?php echo htmlspecialchars($col['name']); ?>" placeholder="Alan adı girin">
                                                </div>
                                            <?php endif; ?>
                                        </td>
                                        <td class="text-center">
                                            <div class="form-check form-check-inline">
                                                <input class="form-check-input" type="checkbox" name="mapping[<?php echo htmlspecialchars($table['source_table']); ?>][primary_key][]" value="<?php echo htmlspecialchars($col['name']); ?>" 
                                                <?php 
                                                // Hedef tablo varsa ve bu alan birincil anahtar ise
                                                if ($table['target_exists'] && isset($table['suggested_mappings'][$col['name']])) {
                                                    foreach ($table['target_columns'] as $t_col) {
                                                        if ($t_col['name'] == $table['suggested_mappings'][$col['name']] && $t_col['is_primary']) {
                                                            echo 'checked';
                                                            break;
                                                        }
                                                    }
                                                // ID ve benzeri alanlar için otomatik seçim
                                                } else if (strtolower($col['name']) == 'id' || 
                                                           strtolower($col['name']) == $table['source_table'] . '_id' || 
                                                           strtolower($col['name']) == 'kod' || 
                                                           strtolower($col['name']) == $table['source_table'] . '_kod') {
                                                    echo 'checked';
                                                }
                                                ?>>
                                                <label class="form-check-label visually-hidden">Birincil Anahtar</label>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <input type="hidden" name="mapping[<?php echo htmlspecialchars($table['source_table']); ?>][target_table]" value="<?php echo htmlspecialchars($table['target_table']); ?>">
                        <input type="hidden" name="mapping[<?php echo htmlspecialchars($table['source_table']); ?>][exists]" value="<?php echo $table['target_exists'] ? '1' : '0'; ?>">
                        
                        <?php if ($table['target_exists']): ?>
                        <div class="form-check mt-3">
                            <input class="form-check-input" type="checkbox" id="truncate<?php echo $index; ?>" name="mapping[<?php echo htmlspecialchars($table['source_table']); ?>][truncate]" value="1">
                            <label class="form-check-label text-danger" for="truncate<?php echo $index; ?>">
                                <i class="fas fa-exclamation-triangle"></i> Aktarımdan önce hedef tabloyu temizle (tüm veriler silinecek)
                            </label>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <div class="d-grid gap-2 d-md-flex justify-content-md-end mb-4">
            <a href="tablo_secimi.php" class="btn btn-outline-secondary me-md-2">
                <i class="fas fa-arrow-left"></i> Geri: Tablo Seçimi
            </a>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-exchange-alt"></i> Veri Aktarımını Başlat
            </button>
        </div>
    </form>
    
    <script>
    // Eşleştirme formu gönderilmeden önce kontrol
    document.getElementById('mappingForm').addEventListener('submit', function(event) {
        let isValid = true;
        const mappings = document.querySelectorAll('select[name$="[target_columns][]"], input[type="text"][name$="[target_columns][]"]');
        
        // En az bir alan eşleştirilmiş mi kontrol et
        let hasMapping = false;
        for (let i = 0; i < mappings.length; i++) {
            if (mappings[i].value !== '') {
                hasMapping = true;
                break;
            }
        }
        
        if (!hasMapping) {
            alert('Lütfen en az bir alanı eşleştirin.');
            event.preventDefault();
            return false;
        }
        
        // Hedef tablo adları kontrolü
        const targetTableInputs = document.querySelectorAll('input[name$="[target_table]"]');
        for (let i = 0; i < targetTableInputs.length; i++) {
            if (targetTableInputs[i].value.trim() === '') {
                alert('Hedef tablo adı boş olamaz. Lütfen tüm hedef tablo adlarını kontrol edin.');
                event.preventDefault();
                return false;
            }
        }
        
        // Truncate onayı
        const truncateCheckboxes = document.querySelectorAll('input[name$="[truncate]"]');
        for (let i = 0; i < truncateCheckboxes.length; i++) {
            if (truncateCheckboxes[i].checked) {
                if (!confirm('DİKKAT: Bazı hedef tablolardaki TÜM VERİLER SİLİNECEK! Devam etmek istediğinize emin misiniz?')) {
                    event.preventDefault();
                    return false;
                }
                break;
            }
        }
        
        return true;
    });
    </script>
    
    <?php
    // Alt kısmı dahil et
    include_once '../../includes/footer.php';
    
} catch (PDOException $e) {
    $_SESSION['error_message'] = "Veritabanı bağlantı hatası: " . $e->getMessage();
    header('Location: tablo_secimi.php');
    exit;
}
?> 