<?php
require_once '../../config/db.php';
require_once '../../config/auth.php';

// Oturum kontrolü
if (!isLoggedIn()) {
    header('Location: /login.php');
    exit;
}

// ID kontrolü
if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
    header('Location: irsaliye_listesi.php');
    exit;
}

$irsaliye_id = $_GET['id'];

// İrsaliye durumunu kontrol et
$query = "SELECT ID, IPTAL, FATURALANDI 
          FROM stk_fis 
          WHERE ID = ? AND TIP IN ('İrsaliye', 'Irsaliye', 'IRSALIYE', '20')";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);
$irsaliye = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$irsaliye || $irsaliye['IPTAL'] == 1 || $irsaliye['FATURALANDI'] == 1) {
    header('Location: irsaliye_listesi.php');
    exit;
}

// İrsaliyeyi iptal et
$query = "UPDATE stk_fis SET IPTAL = 1 WHERE ID = ?";
$stmt = $db->prepare($query);
$stmt->execute([$irsaliye_id]);

// Yönlendir
header('Location: irsaliye_detay.php?id=' . $irsaliye_id);
exit; 