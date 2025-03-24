<?php
/**
 * Geçici Giriş Sayfası
 * 
 * Bu dosya, admin kullanıcısını sisteme geçici olarak giriş yaptırır.
 * Gerçek uygulamada kullanıcı adı ve şifre kontrolü yapılmalıdır.
 */

require_once 'auth.php';

// Oturumu başlat
session_start();

// Geçici admin kullanıcısını oluştur ve giriş yaptır
login(1, 'admin', 'admin', 'Sistem Yöneticisi');

// Ana sayfaya yönlendir
echo "Giriş yapıldı. <a href='../index.php'>Ana Sayfa</a>'ya yönlendiriliyorsunuz...";
header("Refresh: 2; URL=../index.php");
?> 