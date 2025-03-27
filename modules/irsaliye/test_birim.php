<?php
require_once '../../config/db.php';
require_once '../../config/helpers.php';

// Test bir birim ID'si
$birim_id = 1;

// getBirim fonksiyonunu test et
echo "getBirim($birim_id) -> " . getBirim($birim_id) . "\n";

// Birim kodu grup tablosundan sorgula
$query = "SELECT KOD FROM grup WHERE ID = ?";
$stmt = $db->prepare($query);
$stmt->execute([$birim_id]);
$result = $stmt->fetch(PDO::FETCH_ASSOC);
echo "grup sorgusu -> " . ($result ? $result['KOD'] : 'Bulunamadı') . "\n";

// stok_birim tablosunu kontrol et
echo "\nstok_birim tablosu yapısı:\n";
$query = "DESCRIBE stk_birim";
$stmt = $db->query($query);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $column) {
    echo $column['Field'] . " - " . $column['Type'] . "\n";
}

// stok_birim tablosundaki verilere bak
echo "\nstok_birim tablosundaki veriler (ilk 5):\n";
$query = "SELECT * FROM stk_birim LIMIT 5";
$stmt = $db->query($query);
$records = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($records as $record) {
    echo "ID: " . $record['ID'] . ", STOKID: " . $record['STOKID'] . ", BIRIMID: " . $record['BIRIMID'] . "\n";
}

// grup tablosunun yapısını kontrol et
echo "\ngrup tablosu yapısı:\n";
$query = "DESCRIBE grup";
$stmt = $db->query($query);
$columns = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($columns as $column) {
    echo $column['Field'] . " - " . $column['Type'] . "\n";
} 