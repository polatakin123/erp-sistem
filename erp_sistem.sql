-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Anamakine: 127.0.0.1
-- Üretim Zamanı: 26 Mar 2025, 16:02:24
-- Sunucu sürümü: 10.4.22-MariaDB
-- PHP Sürümü: 8.0.3

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Veritabanı: `erp_sistem`
--

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `alternative_groups`
--

CREATE TABLE `alternative_groups` (
  `id` int(11) NOT NULL,
  `group_name` varchar(100) DEFAULT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cari`
--

CREATE TABLE `cari` (
  `ID` int(11) NOT NULL,
  `KOD` varchar(20) NOT NULL,
  `ADI` varchar(400) NOT NULL,
  `DURUM` tinyint(1) NOT NULL,
  `TIP` varchar(255) DEFAULT NULL,
  `ALIS_OT_PLANID` int(11) NOT NULL,
  `SATIS_OT_PLANID` int(11) NOT NULL,
  `ISKONTO` decimal(18,2) DEFAULT NULL,
  `ALISKURYERIID` int(11) NOT NULL,
  `SATISKURYERIID` int(11) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `ALIS_GRUPID` int(11) NOT NULL,
  `SATIS_GRUPID` int(11) NOT NULL,
  `MUTABAKAT_TARIHI` datetime DEFAULT NULL,
  `USEIRSADRES` tinyint(1) NOT NULL,
  `ACIK_HES_LIMIT` decimal(18,2) NOT NULL,
  `ACIK_HES_LIMIT_TIPI` varchar(255) DEFAULT NULL,
  `CEK_SENET_LIMIT` decimal(18,2) NOT NULL,
  `CEK_SENET_LIMIT_TIPI` varchar(255) DEFAULT NULL,
  `MUHHES_ALIS` int(11) DEFAULT NULL,
  `MUHHES_SATIS` int(11) DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELGRUP3` int(11) DEFAULT NULL,
  `OZELGRUP4` int(11) DEFAULT NULL,
  `OZELGRUP5` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `OZELALAN3` varchar(40) DEFAULT NULL,
  `OZELALAN4` varchar(40) DEFAULT NULL,
  `OZELALAN5` varchar(40) DEFAULT NULL,
  `NOTLAR` text DEFAULT NULL,
  `PLASIYERID` int(11) DEFAULT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `ISKMATRISID` int(11) DEFAULT NULL,
  `VADEGUNSAYISI` varchar(255) DEFAULT NULL,
  `ERP_FASONDEPOID` int(11) DEFAULT NULL,
  `MUSTAHSIL_BAGKUR_KESINTISI` tinyint(1) DEFAULT NULL,
  `BAGKUR_NUMARASI` varchar(20) DEFAULT NULL,
  `SGK_NUMARASI` varchar(20) DEFAULT NULL,
  `ISKONTO2` decimal(18,2) DEFAULT NULL,
  `WEBDEGOSTER` tinyint(1) DEFAULT NULL,
  `B2BKULLANICIADI` varchar(100) DEFAULT NULL,
  `B2BKULLANICISIFRESI` varchar(40) DEFAULT NULL,
  `EFATSENARYO` int(11) DEFAULT NULL,
  `EFATKULLAN` tinyint(1) DEFAULT NULL,
  `EFATTARIH` datetime DEFAULT NULL,
  `EFATALIAS` text DEFAULT NULL,
  `HS_GONDERILDI` tinyint(1) DEFAULT NULL,
  `EIRSALIYEKULLAN` tinyint(1) DEFAULT NULL,
  `EIRSALIYETARIH` datetime DEFAULT NULL,
  `MUTABAKAT_DURUMU` varchar(255) NOT NULL,
  `MUTABAKAT_GONDERIM` varchar(255) NOT NULL,
  `MUTABAKAT_MAIL` varchar(200) DEFAULT NULL,
  `MUTABAKAT_GSM` varchar(30) DEFAULT NULL,
  `MUTABAKAT_BAKIYE_MAIL` varchar(200) DEFAULT NULL,
  `EARSIVGONDERIM` varchar(255) NOT NULL,
  `EIRSALIAS` text DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cariler`
--

CREATE TABLE `cariler` (
  `id` int(11) NOT NULL,
  `cari_kodu` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `firma_unvani` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `yetkili_ad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `yetkili_soyad` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `telefon` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cep_telefonu` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `fax` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `adres` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `il` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `ilce` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `posta_kodu` varchar(10) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vergi_dairesi` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `vergi_no` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `cari_tipi` enum('musteri','tedarikci','her_ikisi') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'musteri',
  `bakiye` decimal(15,2) NOT NULL DEFAULT 0.00,
  `kredi_limiti` decimal(15,2) NOT NULL DEFAULT 0.00,
  `odeme_vade` int(11) NOT NULL DEFAULT 0,
  `durum` tinyint(1) NOT NULL DEFAULT 1,
  `web_sitesi` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `notlar` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cari_fis`
--

CREATE TABLE `cari_fis` (
  `ID` int(11) NOT NULL,
  `OWN_BOLUMID` int(11) NOT NULL,
  `OWN_FISTIP` varchar(255) NOT NULL,
  `OWNERID` int(11) DEFAULT NULL,
  `BOLUMID` int(11) NOT NULL,
  `FISTIP` varchar(255) NOT NULL,
  `FISNO` varchar(15) NOT NULL,
  `FISTAR` datetime NOT NULL,
  `KARTID` int(11) NOT NULL,
  `ADI` varchar(400) DEFAULT NULL,
  `TUTAR` decimal(18,2) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `KUR_YEREL` decimal(18,2) DEFAULT NULL,
  `YEREL_TUTAR` decimal(18,2) NOT NULL,
  `KUR_RAPOR` decimal(18,2) DEFAULT NULL,
  `CI_KARTID` int(11) NOT NULL,
  `CI_ADI` varchar(400) DEFAULT NULL,
  `CI_TUTAR` decimal(18,2) NOT NULL,
  `CI_DOVIZID` int(11) NOT NULL,
  `CI_KUR_YEREL` decimal(18,2) DEFAULT NULL,
  `CI_YEREL_TUTAR` decimal(18,2) NOT NULL,
  `POSAKTARFISID` int(11) DEFAULT NULL,
  `MUHFISID` int(11) DEFAULT NULL,
  `BASIMYAPILDI` tinyint(1) NOT NULL,
  `KDVORANI` decimal(18,2) NOT NULL,
  `KDV_MATRAHI` decimal(18,2) NOT NULL,
  `KDV_TUTARI` decimal(18,2) NOT NULL,
  `KKISLEMTAR` datetime DEFAULT NULL,
  `KKVADE` datetime DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `HAREKET_TIPI` varchar(255) DEFAULT NULL,
  `HAREKET_ADEDI` varchar(255) DEFAULT NULL,
  `FAIZ_TIPI` varchar(255) DEFAULT NULL,
  `YILDA_GUN` varchar(255) DEFAULT NULL,
  `VF_YILLIK_ORANI` decimal(18,2) DEFAULT NULL,
  `ORT_VADE` datetime DEFAULT NULL,
  `ORT_GUN` varchar(255) DEFAULT NULL,
  `VF_TOPLAMI` decimal(18,2) NOT NULL,
  `VF_TAHSIL_EDILEN` decimal(18,2) NOT NULL,
  `VF_TAHSIL_EDILEN_YT` decimal(18,2) NOT NULL,
  `SUBRECID` int(11) DEFAULT NULL,
  `IZAH` varchar(200) DEFAULT NULL,
  `NOTLAR` text DEFAULT NULL,
  `PLASIYERID` int(11) DEFAULT NULL,
  `PCPOSTAHSILID` int(11) DEFAULT NULL,
  `PCPOSTAHSILTUTAR` decimal(18,2) DEFAULT NULL,
  `SUBEID` int(11) NOT NULL,
  `CI_SUBEID` int(11) DEFAULT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `GENELGRUP1` int(11) DEFAULT NULL,
  `GENELGRUP2` int(11) DEFAULT NULL,
  `GENELGRUP3` int(11) DEFAULT NULL,
  `GENELGRUP4` int(11) DEFAULT NULL,
  `GENELGRUP5` int(11) DEFAULT NULL,
  `EVRAK_NO` varchar(20) DEFAULT NULL,
  `CI_HES_GEC_TUTAR` decimal(18,2) NOT NULL,
  `FISSAAT` datetime DEFAULT NULL,
  `HS_KASAID` int(11) DEFAULT NULL,
  `HS_KASIYERID` int(11) DEFAULT NULL,
  `BKRD_ANA_PARA` decimal(18,2) NOT NULL,
  `BKRD_FAIZ_TUTAR` decimal(18,2) NOT NULL,
  `BKRD_BSMV_TUTAR` decimal(18,2) NOT NULL,
  `BKRD_KKDF_TUTAR` decimal(18,2) NOT NULL,
  `BKRD_ERKEN_FAIZ_TUTAR` decimal(18,2) NOT NULL,
  `BKRD_GEC_FAIZ_TUTAR` decimal(18,2) NOT NULL,
  `TYPEID` varchar(20) NOT NULL,
  `FATURAID` int(11) DEFAULT NULL,
  `CARICOKLUID` int(11) DEFAULT NULL,
  `CARIFISBRDID` int(11) DEFAULT NULL,
  `VF_ERKEN_ODENEN` decimal(18,2) NOT NULL,
  `VF_ERKEN_ODENEN_YT` decimal(18,2) NOT NULL,
  `HS_VARDIYA_UUID` varchar(36) DEFAULT NULL,
  `HS_ENTEGRASYON_UUID` varchar(36) DEFAULT NULL,
  `HS_FIS_UUID` varchar(36) NOT NULL,
  `IADE_EDILEN_TUTAR` decimal(18,2) DEFAULT NULL,
  `IADE_KKFISID` int(11) DEFAULT NULL,
  `FOREIGNDOCUMENTNO` varchar(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cari_hareket`
--

CREATE TABLE `cari_hareket` (
  `ID` int(11) NOT NULL,
  `OWN_BOLUMID` int(11) NOT NULL,
  `OWNERID` int(11) NOT NULL,
  `FISTIPI` varchar(255) DEFAULT NULL,
  `CARIID` int(11) NOT NULL,
  `CARIADI` varchar(400) DEFAULT NULL,
  `EVRAKNO` varchar(20) NOT NULL,
  `ISLEMTIPI` varchar(255) NOT NULL,
  `ISLEMTARIHI` datetime NOT NULL,
  `TUTAR` decimal(18,2) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `KUR_YEREL` decimal(18,2) NOT NULL,
  `YEREL_TUTAR` decimal(18,2) NOT NULL,
  `KUR_RAPOR` decimal(18,2) NOT NULL,
  `IZAH` varchar(75) DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELGRUP3` int(11) DEFAULT NULL,
  `OZELGRUP4` int(11) DEFAULT NULL,
  `OZELGRUP5` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `OZELALAN3` varchar(40) DEFAULT NULL,
  `OZELALAN4` varchar(40) DEFAULT NULL,
  `OZELALAN5` varchar(40) DEFAULT NULL,
  `SUBEID` int(11) NOT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `GENELGRUP1` int(11) DEFAULT NULL,
  `GENELGRUP2` int(11) DEFAULT NULL,
  `GENELGRUP3` int(11) DEFAULT NULL,
  `GENELGRUP4` int(11) DEFAULT NULL,
  `GENELGRUP5` int(11) DEFAULT NULL,
  `ISLEMSAATI` datetime DEFAULT NULL,
  `FATURAID` int(11) DEFAULT NULL,
  `CARIFISID` int(11) DEFAULT NULL,
  `KASAFISID` int(11) DEFAULT NULL,
  `BANKAFISID` int(11) DEFAULT NULL,
  `CSBORDROID` int(11) DEFAULT NULL,
  `TOPLUFISID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cari_iletisimler`
--

CREATE TABLE `cari_iletisimler` (
  `id` int(11) NOT NULL,
  `cari_id` int(11) NOT NULL,
  `iletisim_turu` enum('telefon','email','adres','diger') COLLATE utf8mb4_unicode_ci NOT NULL,
  `iletisim_bilgisi` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `aciklama` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `cek_senet`
--

CREATE TABLE `cek_senet` (
  `id` int(11) NOT NULL,
  `islem_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tip` enum('cek','senet') COLLATE utf8mb4_unicode_ci NOT NULL,
  `tutar` decimal(15,2) NOT NULL,
  `tarih` date NOT NULL,
  `vade_tarihi` date NOT NULL,
  `referans_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `aciklama` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `durum` enum('beklemede','tahsil_edildi','odendi','iptal') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'beklemede',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `customers`
--

CREATE TABLE `customers` (
  `id` int(11) NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_office` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `earsiv`
--

CREATE TABLE `earsiv` (
  `ID` int(11) NOT NULL,
  `FATFISID` int(11) DEFAULT NULL,
  `FATTIP` varchar(255) DEFAULT NULL,
  `FISTAR` datetime DEFAULT NULL,
  `FISSAAT` datetime DEFAULT NULL,
  `CARIID` int(11) DEFAULT NULL,
  `CARIADI` varchar(400) DEFAULT NULL,
  `FISNO` varchar(20) DEFAULT NULL,
  `EARSIV_IPTAL` tinyint(1) DEFAULT NULL,
  `EARSIV_GONDERIM` varchar(255) DEFAULT NULL,
  `EARSIV_TIP` varchar(255) DEFAULT NULL,
  `KAYITTIP` varchar(255) DEFAULT NULL,
  `SMSDURUM` varchar(255) DEFAULT NULL,
  `SMSTIMERID` varchar(255) DEFAULT NULL,
  `MAILDURUM` tinyint(1) DEFAULT NULL,
  `YAZDIRILDI` tinyint(1) DEFAULT NULL,
  `TASLAK` varchar(255) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `efat_grup`
--

CREATE TABLE `efat_grup` (
  `ID` int(11) NOT NULL,
  `KOD` varchar(20) NOT NULL,
  `ADI` varchar(150) DEFAULT NULL,
  `TIP` int(11) NOT NULL,
  `KISAAD` varchar(40) DEFAULT NULL,
  `OZELILETISIMVERGISI` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `fatura`
--

CREATE TABLE `fatura` (
  `ID` int(11) NOT NULL,
  `TIP` varchar(255) NOT NULL,
  `FISNO` varchar(20) DEFAULT NULL,
  `FISTAR` datetime NOT NULL,
  `FISSAAT` datetime DEFAULT NULL,
  `SEVKTAR` datetime DEFAULT NULL,
  `SEVKSAAT` datetime DEFAULT NULL,
  `KDVDAHIL` tinyint(1) NOT NULL,
  `KDVDAHILISK` tinyint(1) NOT NULL,
  `CARIID` int(11) NOT NULL,
  `VADESI` datetime DEFAULT NULL,
  `ODEMETIP` varchar(255) NOT NULL,
  `OTPLANID` int(11) NOT NULL,
  `DEPOID` int(11) NOT NULL,
  `TEVKIFATID` int(11) NOT NULL,
  `STOKTOPLAM` decimal(18,2) NOT NULL,
  `HIZMETTOPLAM` decimal(18,2) NOT NULL,
  `KALEMISKTOPLAM` decimal(18,2) NOT NULL,
  `KALEMKDVTOPLAM` decimal(18,2) NOT NULL,
  `ISKORAN1` decimal(18,2) NOT NULL,
  `ISKTUTAR1` decimal(18,2) NOT NULL,
  `ISKORAN2` decimal(18,2) NOT NULL,
  `ISKTUTAR2` decimal(18,2) NOT NULL,
  `ARATOPLAM` decimal(18,2) NOT NULL,
  `FISKDVTUTARI` decimal(18,2) NOT NULL,
  `YUVARLAMA` decimal(18,2) NOT NULL,
  `GENELTOPLAM` decimal(18,2) NOT NULL,
  `KUR_RAPOR` decimal(18,2) DEFAULT NULL,
  `GENELTOPLAM_RAPOR` decimal(18,2) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `KUR_VALUE` decimal(18,2) DEFAULT NULL,
  `GENELTOPLAM_CARI` decimal(18,2) NOT NULL,
  `CARIADI` varchar(400) DEFAULT NULL,
  `ADRESKODU` varchar(20) DEFAULT NULL,
  `ADRES1` varchar(200) DEFAULT NULL,
  `ADRES2` varchar(200) DEFAULT NULL,
  `SEMT` varchar(200) DEFAULT NULL,
  `ILCE` varchar(200) DEFAULT NULL,
  `SEHIR` varchar(40) DEFAULT NULL,
  `ULKE` varchar(40) DEFAULT NULL,
  `POSTAKODU` varchar(10) DEFAULT NULL,
  `BOLGEKODU` varchar(40) DEFAULT NULL,
  `TELEFON1` varchar(30) DEFAULT NULL,
  `TELEFON2` varchar(30) DEFAULT NULL,
  `FAX` varchar(30) DEFAULT NULL,
  `GSM` varchar(30) DEFAULT NULL,
  `MAIL` varchar(200) DEFAULT NULL,
  `URL` varchar(200) DEFAULT NULL,
  `ENLEM` varchar(10) DEFAULT NULL,
  `BOYLAM` varchar(10) DEFAULT NULL,
  `VERGINO` varchar(20) DEFAULT NULL,
  `VERGIDAIRESI` varchar(20) DEFAULT NULL,
  `BASILDI` tinyint(1) NOT NULL,
  `DOVIZLIHARCOUNT` varchar(255) DEFAULT NULL,
  `MUHFISID` int(11) DEFAULT NULL,
  `KASAFISID` int(11) DEFAULT NULL,
  `IPTAL` tinyint(1) NOT NULL,
  `STOPAJ` decimal(18,2) DEFAULT NULL,
  `STOPAJTUTAR` decimal(18,2) NOT NULL,
  `BAGKUR` decimal(18,2) DEFAULT NULL,
  `BAGKURTUTAR` decimal(18,2) NOT NULL,
  `KOMISYONKDV` decimal(18,2) DEFAULT NULL,
  `KOMISYONKDVTUTAR` decimal(18,2) NOT NULL,
  `KOMISYON` decimal(18,2) DEFAULT NULL,
  `KOMISYONTUTAR` decimal(18,2) NOT NULL,
  `BORSA` decimal(18,2) DEFAULT NULL,
  `BORSATUTAR` decimal(18,2) NOT NULL,
  `KESINTI1` decimal(18,2) DEFAULT NULL,
  `KESINTI1TUTAR` decimal(18,2) NOT NULL,
  `KESINTI2` decimal(18,2) DEFAULT NULL,
  `KESINTI2TUTAR` decimal(18,2) NOT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELGRUP3` int(11) DEFAULT NULL,
  `OZELGRUP4` int(11) DEFAULT NULL,
  `OZELGRUP5` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `OZELALAN3` varchar(40) DEFAULT NULL,
  `OZELALAN4` varchar(40) DEFAULT NULL,
  `OZELALAN5` varchar(40) DEFAULT NULL,
  `NOTLAR` text DEFAULT NULL,
  `PLASIYERID` int(11) DEFAULT NULL,
  `POSKASAID` int(11) DEFAULT NULL,
  `POSKASIYERID` int(11) DEFAULT NULL,
  `OWN_BOLUMID` int(11) DEFAULT NULL,
  `OWN_FISTIP` varchar(255) DEFAULT NULL,
  `OWNERID` int(11) DEFAULT NULL,
  `EK_MALIYET_ACIKLAMA` varchar(100) DEFAULT NULL,
  `EK_MALIYET_TUTAR_YEREL` decimal(18,2) NOT NULL,
  `EK_MALIYET_TUTAR_CARI` decimal(18,2) NOT NULL,
  `ONODEMELISIPARIS` tinyint(1) NOT NULL,
  `OTVTOPLAMI` decimal(18,2) NOT NULL,
  `USERID` int(11) DEFAULT NULL,
  `SUBEID` int(11) NOT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `GENELGRUP1` int(11) DEFAULT NULL,
  `GENELGRUP2` int(11) DEFAULT NULL,
  `GENELGRUP3` int(11) DEFAULT NULL,
  `GENELGRUP4` int(11) DEFAULT NULL,
  `GENELGRUP5` int(11) DEFAULT NULL,
  `MALIYET_ISLEM` varchar(255) DEFAULT NULL,
  `HIZLISATIS_ONAY` tinyint(1) DEFAULT NULL,
  `EFATURA` varchar(255) DEFAULT NULL,
  `EFATURA_ZARFID` int(11) DEFAULT NULL,
  `EFATURA_ACIKLAMA` varchar(150) DEFAULT NULL,
  `EFATURA_UUID` varchar(100) DEFAULT NULL,
  `ISKDVZTUTAR1` decimal(18,2) NOT NULL,
  `ISKDVZTUTAR2` decimal(18,2) NOT NULL,
  `EFATURA_IADEIZAH` varchar(255) DEFAULT NULL,
  `EARSIV_TIP` varchar(255) DEFAULT NULL,
  `EARSIV_GONDERIM` varchar(255) DEFAULT NULL,
  `EARSIV_INTWEB` varchar(50) DEFAULT NULL,
  `EARSIV_INTODEME` varchar(255) DEFAULT NULL,
  `EARSIV_INTODEMETEXT` varchar(50) DEFAULT NULL,
  `EARSIV_INTTARIH` datetime DEFAULT NULL,
  `EARSIV_INTKARGOID` int(11) DEFAULT NULL,
  `PERAKENDEFIS` int(11) DEFAULT NULL,
  `OKCBASILDI` tinyint(1) DEFAULT NULL,
  `OKCFISNO` varchar(20) DEFAULT NULL,
  `OKCZNO` varchar(20) DEFAULT NULL,
  `EFATURA_TIP` varchar(255) DEFAULT NULL,
  `KAMPANYAID` int(11) NOT NULL,
  `EFAT_TESLIMSART` varchar(10) DEFAULT NULL,
  `EFAT_GONSEKLI` varchar(30) DEFAULT NULL,
  `EFAT_TESLIMILCE` varchar(20) DEFAULT NULL,
  `EFAT_TESLIMSEHIR` varchar(40) DEFAULT NULL,
  `EFAT_TESLIMULKE` varchar(40) DEFAULT NULL,
  `EFAT_UYRUK` varchar(40) DEFAULT NULL,
  `EFAT_PASAPORTNO` varchar(20) DEFAULT NULL,
  `EFAT_PASAPORTTARIH` datetime DEFAULT NULL,
  `EFAT_BANKANO` varchar(20) DEFAULT NULL,
  `EFAT_BANKAADI` varchar(40) DEFAULT NULL,
  `EFAT_BANKASUBE` varchar(40) DEFAULT NULL,
  `EFAT_DOVIZID` int(11) DEFAULT NULL,
  `EFAT_ARACICARIID` int(11) DEFAULT NULL,
  `HS_KASAID` int(11) DEFAULT NULL,
  `HS_KASIYERID` int(11) DEFAULT NULL,
  `KAMPANYA_KALEMISKTOPLAM` decimal(18,2) NOT NULL,
  `KAMPANYA_ISKTUTAR` decimal(18,2) NOT NULL,
  `PUAN_KAZANILAN` decimal(18,2) DEFAULT NULL,
  `PUAN_KULLANILAN` decimal(18,2) DEFAULT NULL,
  `EMUSTAHSIL` tinyint(1) DEFAULT NULL,
  `HESAPLAMA` int(11) DEFAULT NULL,
  `NETTUTAR` decimal(18,2) DEFAULT NULL,
  `STOPAJTUR` int(11) DEFAULT NULL,
  `ESGKDONEMBASTARIH` datetime DEFAULT NULL,
  `ESGKDONEMBITTARIH` datetime DEFAULT NULL,
  `ESGKDOSYANO` varchar(40) DEFAULT NULL,
  `NAVLUN_TIP` varchar(255) DEFAULT NULL,
  `NAVLUN_TUTAR` decimal(18,2) DEFAULT NULL,
  `NAVLUN_DVZ_TUTAR` decimal(18,2) DEFAULT NULL,
  `SIGORTA_TUTAR` decimal(18,2) DEFAULT NULL,
  `SIGORTA_DVZ_TUTAR` decimal(18,2) DEFAULT NULL,
  `ELEKTRONIK_BELGE` tinyint(1) NOT NULL,
  `EFAT_MATRAH_TUTAR` decimal(18,2) DEFAULT NULL,
  `EFAT_MATRAH_KDVOR` decimal(18,2) DEFAULT NULL,
  `EFAT_MATRAH_KDVTUTAR` decimal(18,2) DEFAULT NULL,
  `TEVKIFATUYGULANDI` tinyint(1) DEFAULT NULL,
  `EBELGE_GIB_IMZA` int(11) NOT NULL,
  `HS_VARDIYA_UUID` varchar(36) DEFAULT NULL,
  `HS_ENTEGRASYON_UUID` varchar(36) DEFAULT NULL,
  `HS_FIS_UUID` varchar(36) NOT NULL,
  `KONAKLAMAKDVTOPLAM` decimal(18,2) DEFAULT NULL,
  `VERGIKODID` int(11) DEFAULT NULL,
  `OIVORAN` decimal(18,2) DEFAULT NULL,
  `OIVTUTAR` decimal(18,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `grup`
--

CREATE TABLE `grup` (
  `ID` int(11) NOT NULL,
  `OWNERID` int(11) NOT NULL,
  `TIP` varchar(255) NOT NULL,
  `KOD` varchar(20) NOT NULL,
  `IZAH` varchar(40) NOT NULL,
  `IZAH_2` varchar(250) DEFAULT NULL,
  `EFAT_GRUPID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kasa`
--

CREATE TABLE `kasa` (
  `ID` int(11) NOT NULL,
  `KOD` varchar(20) NOT NULL,
  `ADI` varchar(60) DEFAULT NULL,
  `DOVIZID` int(11) NOT NULL,
  `ONAY_TARIHI` datetime DEFAULT NULL,
  `ONAYTARKONTROLTIPI` varchar(255) DEFAULT NULL,
  `TERSBAKIYE` varchar(255) NOT NULL,
  `DURUM` tinyint(1) NOT NULL,
  `MUHHES_KASA` int(11) DEFAULT NULL,
  `MINTUTAR` decimal(18,2) NOT NULL,
  `MAXTUTAR` decimal(18,2) NOT NULL,
  `MINKONTROLTIPI` varchar(255) DEFAULT NULL,
  `MAXKONTROLTIPI` varchar(255) DEFAULT NULL,
  `USEALLUSER` tinyint(1) NOT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `NOTLAR` text DEFAULT NULL,
  `SUBEID` int(11) NOT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `MUHHES_KUR_BORC` int(11) DEFAULT NULL,
  `MUHHES_KUR_ALACAK` int(11) DEFAULT NULL,
  `HS_GONDERILDI` tinyint(1) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kasa_hareket`
--

CREATE TABLE `kasa_hareket` (
  `ID` int(11) NOT NULL,
  `OWN_BOLUMID` int(11) NOT NULL,
  `OWNERID` int(11) NOT NULL,
  `FISTIPI` varchar(255) DEFAULT NULL,
  `KASAID` int(11) NOT NULL,
  `EVRAKNO` varchar(15) NOT NULL,
  `ISLEMTIPI` varchar(255) NOT NULL,
  `ISLEMTARIHI` datetime NOT NULL,
  `TUTAR` decimal(18,2) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `KUR_YEREL` decimal(18,2) NOT NULL,
  `YEREL_TUTAR` decimal(18,2) NOT NULL,
  `KUR_RAPOR` decimal(18,2) NOT NULL,
  `IZAH` varchar(75) DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `SUBEID` int(11) NOT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `GENELGRUP1` int(11) DEFAULT NULL,
  `GENELGRUP2` int(11) DEFAULT NULL,
  `GENELGRUP3` int(11) DEFAULT NULL,
  `GENELGRUP4` int(11) DEFAULT NULL,
  `GENELGRUP5` int(11) DEFAULT NULL,
  `ISLEMSAATI` datetime DEFAULT NULL,
  `TOPLUFISID` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `kdv`
--

CREATE TABLE `kdv` (
  `ID` int(11) NOT NULL,
  `ADI` varchar(40) NOT NULL,
  `ORAN` decimal(18,2) NOT NULL,
  `MUHHES_ALIS` int(11) DEFAULT NULL,
  `MUHHES_ALISIADE` int(11) DEFAULT NULL,
  `MUHHES_SATIS` int(11) DEFAULT NULL,
  `MUHHES_SATISIADE` int(11) DEFAULT NULL,
  `MUHHES_ALISFIYATFARKI` int(11) DEFAULT NULL,
  `MUHHES_SATISFIYATFARKI` int(11) DEFAULT NULL,
  `MUHHES_POSZRAPOR` int(11) DEFAULT NULL,
  `SATIR_ALIS_HESAPID` int(11) NOT NULL,
  `SATIR_ALIS_IADE_HESAPID` int(11) NOT NULL,
  `GENEL_ALIS_HESAPID` int(11) NOT NULL,
  `GENEL_ALIS_IADE_HESAPID` int(11) NOT NULL,
  `SATIR_SATIS_HESAPID` int(11) NOT NULL,
  `SATIR_SATIS_IADE_HESAPID` int(11) NOT NULL,
  `GENEL_SATIS_HESAPID` int(11) NOT NULL,
  `GENEL_SATIS_IADE_HESAPID` int(11) NOT NULL,
  `MUHHES_ALIS_TECIL` int(11) DEFAULT NULL,
  `MUHHES_SATIS_TECIL` int(11) DEFAULT NULL,
  `MUHHES_BEYANKDV_ALIS` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `muhasebe_kayitlari`
--

CREATE TABLE `muhasebe_kayitlari` (
  `id` int(11) NOT NULL,
  `tarih` date NOT NULL,
  `aciklama` text COLLATE utf8mb4_unicode_ci NOT NULL,
  `tutar` decimal(15,2) NOT NULL,
  `islem_tipi` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `referans_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `oem_numbers`
--

CREATE TABLE `oem_numbers` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `oem_no` varchar(100) NOT NULL,
  `created_at` datetime DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `product_alternatives`
--

CREATE TABLE `product_alternatives` (
  `product_id` int(11) NOT NULL,
  `alternative_group_id` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `product_categories`
--

CREATE TABLE `product_categories` (
  `id` int(11) NOT NULL,
  `parent_id` int(11) DEFAULT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `purchase_invoices`
--

CREATE TABLE `purchase_invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `supplier_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `grand_total` decimal(15,2) NOT NULL,
  `status` enum('draft','issued','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `purchase_invoice_items`
--

CREATE TABLE `purchase_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sales_invoices`
--

CREATE TABLE `sales_invoices` (
  `id` int(11) NOT NULL,
  `invoice_no` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `customer_id` int(11) NOT NULL,
  `invoice_date` date NOT NULL,
  `due_date` date NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `grand_total` decimal(15,2) NOT NULL,
  `status` enum('draft','issued','cancelled') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'draft',
  `notes` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sales_invoice_items`
--

CREATE TABLE `sales_invoice_items` (
  `id` int(11) NOT NULL,
  `invoice_id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `tax_rate` decimal(5,2) NOT NULL,
  `tax_amount` decimal(15,2) NOT NULL,
  `total_amount` decimal(15,2) NOT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `siparis`
--

CREATE TABLE `siparis` (
  `ID` int(11) NOT NULL,
  `TIP` varchar(255) NOT NULL,
  `FISNO` varchar(15) NOT NULL,
  `FISTAR` datetime NOT NULL,
  `FISSAAT` datetime DEFAULT NULL,
  `KDVDAHIL` tinyint(1) NOT NULL,
  `KDVDAHILISK` tinyint(1) NOT NULL,
  `CARIID` int(11) NOT NULL,
  `DOVIZUSETIP` varchar(255) NOT NULL,
  `VADESI` datetime DEFAULT NULL,
  `OTPLANID` int(11) NOT NULL,
  `DEPOID` int(11) NOT NULL,
  `STOKTOPLAM` decimal(18,2) NOT NULL,
  `HIZMETTOPLAM` decimal(18,2) NOT NULL,
  `KALEMISKTOPLAM` decimal(18,2) NOT NULL,
  `KALEMKDVTOPLAM` decimal(18,2) NOT NULL,
  `ISKORAN1` decimal(18,2) NOT NULL,
  `ISKTUTAR1` decimal(18,2) NOT NULL,
  `ISKORAN2` decimal(18,2) NOT NULL,
  `ISKTUTAR2` decimal(18,2) NOT NULL,
  `ARATOPLAM` decimal(18,2) NOT NULL,
  `FISKDVTUTARI` decimal(18,2) NOT NULL,
  `GENELTOPLAM` decimal(18,2) NOT NULL,
  `KUR_RAPOR` decimal(18,2) NOT NULL,
  `GENELTOPLAM_RAPOR` decimal(18,2) NOT NULL,
  `CARIADI` varchar(400) DEFAULT NULL,
  `ADRESKODU` varchar(20) DEFAULT NULL,
  `ADRES1` varchar(200) DEFAULT NULL,
  `ADRES2` varchar(200) DEFAULT NULL,
  `SEMT` varchar(200) DEFAULT NULL,
  `ILCE` varchar(200) DEFAULT NULL,
  `SEHIR` varchar(40) DEFAULT NULL,
  `ULKE` varchar(40) DEFAULT NULL,
  `POSTAKODU` varchar(10) DEFAULT NULL,
  `BOLGEKODU` varchar(40) DEFAULT NULL,
  `TELEFON1` varchar(30) DEFAULT NULL,
  `TELEFON2` varchar(30) DEFAULT NULL,
  `FAX` varchar(30) DEFAULT NULL,
  `GSM` varchar(30) DEFAULT NULL,
  `MAIL` varchar(200) DEFAULT NULL,
  `URL` varchar(200) DEFAULT NULL,
  `ENLEM` varchar(10) DEFAULT NULL,
  `BOYLAM` varchar(10) DEFAULT NULL,
  `VERGINO` varchar(20) DEFAULT NULL,
  `VERGIDAIRESI` varchar(20) DEFAULT NULL,
  `BASILDI` tinyint(1) NOT NULL,
  `DOVIZLIHARCOUNT` varchar(255) DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELGRUP3` int(11) DEFAULT NULL,
  `OZELGRUP4` int(11) DEFAULT NULL,
  `OZELGRUP5` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `OZELALAN3` varchar(40) DEFAULT NULL,
  `OZELALAN4` varchar(40) DEFAULT NULL,
  `OZELALAN5` varchar(40) DEFAULT NULL,
  `NOTLAR` text DEFAULT NULL,
  `PLASIYERID` int(11) DEFAULT NULL,
  `DOVIZID` int(11) NOT NULL,
  `KUR_VALUE` decimal(18,2) NOT NULL,
  `GENELTOPLAM_CARI` decimal(18,2) NOT NULL,
  `ONODEMELISIPARIS` tinyint(1) NOT NULL,
  `IRSALIYEID` int(11) NOT NULL,
  `FATURAID` int(11) NOT NULL,
  `OTVTOPLAMI` decimal(18,2) NOT NULL,
  `SUBEID` int(11) NOT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `GENELGRUP1` int(11) DEFAULT NULL,
  `GENELGRUP2` int(11) DEFAULT NULL,
  `GENELGRUP3` int(11) DEFAULT NULL,
  `GENELGRUP4` int(11) DEFAULT NULL,
  `GENELGRUP5` int(11) DEFAULT NULL,
  `ODURUMU` varchar(255) DEFAULT NULL,
  `WEBONAYLI` tinyint(1) DEFAULT NULL,
  `PLAN_TESLIM_TARIHI` datetime DEFAULT NULL,
  `ISKDVZTUTAR1` decimal(18,2) NOT NULL,
  `ISKDVZTUTAR2` decimal(18,2) NOT NULL,
  `EARSIV_TIP` varchar(255) DEFAULT NULL,
  `EARSIV_GONDERIM` varchar(255) DEFAULT NULL,
  `EARSIV_INTWEB` varchar(50) DEFAULT NULL,
  `EARSIV_INTODEME` varchar(255) DEFAULT NULL,
  `EARSIV_INTODEMETEXT` varchar(50) DEFAULT NULL,
  `EARSIV_INTTARIH` datetime DEFAULT NULL,
  `EARSIV_INTKARGOID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `sip_har`
--

CREATE TABLE `sip_har` (
  `ID` int(11) NOT NULL,
  `SIRANO` varchar(255) NOT NULL,
  `FISTIP` varchar(255) NOT NULL,
  `FISID` int(11) NOT NULL,
  `FISTAR` datetime NOT NULL,
  `KARTTIPI` varchar(1) NOT NULL,
  `KARTID` int(11) NOT NULL,
  `BARKODID` int(11) NOT NULL,
  `MIKTAR` decimal(18,2) NOT NULL,
  `BIRIMID` int(11) NOT NULL,
  `BIRIM1KATSAYI` varchar(255) NOT NULL,
  `BIRIM2KATSAYI` varchar(255) NOT NULL,
  `BIRIM3KATSAYI` varchar(255) NOT NULL,
  `FIYAT` decimal(18,2) NOT NULL,
  `DFIYAT` decimal(18,2) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `KUR_YEREL` decimal(18,2) NOT NULL,
  `KUR_RAPOR` decimal(18,2) NOT NULL,
  `TUTAR` decimal(18,2) NOT NULL,
  `DTUTAR` decimal(18,2) NOT NULL,
  `KDVORANI` decimal(18,2) NOT NULL,
  `KDVMATRAHI` decimal(18,2) NOT NULL,
  `KDVTUTARI` decimal(18,2) NOT NULL,
  `ISKONTO1` decimal(18,2) DEFAULT NULL,
  `ISKONTO2` decimal(18,2) DEFAULT NULL,
  `ISKONTO3` decimal(18,2) DEFAULT NULL,
  `ISKONTO4` decimal(18,2) DEFAULT NULL,
  `ISKONTO5` decimal(18,2) DEFAULT NULL,
  `ISKONTO1TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO2TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO3TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO4TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO5TUTAR` decimal(18,2) NOT NULL,
  `FISISK1TUTAR` decimal(18,2) NOT NULL,
  `FISISK2TUTAR` decimal(18,2) NOT NULL,
  `OTPLANID` int(11) NOT NULL,
  `VADE_TARIHI` datetime DEFAULT NULL,
  `DEPOID` int(11) NOT NULL,
  `TESLIM_ED_MIKTAR` decimal(18,2) NOT NULL,
  `TESLIMEDILDI` tinyint(1) NOT NULL,
  `PLAN_TESLIM_TAR` datetime DEFAULT NULL,
  `RESERVE` tinyint(1) NOT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `ACIKLAMA` varchar(100) DEFAULT NULL,
  `OWNER_STOK_ID` int(11) DEFAULT NULL,
  `OWNER_TREE_ID` int(11) DEFAULT NULL,
  `OTVID` int(11) DEFAULT NULL,
  `OTVHARID` int(11) DEFAULT NULL,
  `OTVMATRAHI` decimal(18,2) NOT NULL,
  `OTVTUTARI` decimal(18,2) NOT NULL,
  `OTVINDIRIMI` decimal(18,2) NOT NULL,
  `TEKLIFID` int(11) DEFAULT NULL,
  `TEKLIFHARID` int(11) DEFAULT NULL,
  `TEKLIFNO` varchar(15) DEFAULT NULL,
  `TEKLIFTAR` datetime DEFAULT NULL,
  `SUBEID` int(11) NOT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `GENELGRUP1` int(11) DEFAULT NULL,
  `GENELGRUP2` int(11) DEFAULT NULL,
  `GENELGRUP3` int(11) DEFAULT NULL,
  `GENELGRUP4` int(11) DEFAULT NULL,
  `GENELGRUP5` int(11) DEFAULT NULL,
  `DEVIR_TESLIM_ED_MIKTAR` decimal(18,2) DEFAULT NULL,
  `MH_DEGER1` decimal(18,2) NOT NULL,
  `MH_DEGER2` decimal(18,2) NOT NULL,
  `MH_DEGER3` decimal(18,2) NOT NULL,
  `MH_DEGER4` decimal(18,2) NOT NULL,
  `MH_DEGER5` decimal(18,2) NOT NULL,
  `MH_DEGER6` decimal(18,2) NOT NULL,
  `ERP_VARYANTID` int(11) DEFAULT NULL,
  `ERP_ONERIID` int(11) DEFAULT NULL,
  `ADRESID` int(11) DEFAULT NULL,
  `REFERANS_NO` int(11) DEFAULT NULL,
  `OWNER_SIPARIS_ID` int(11) DEFAULT NULL,
  `OWNER_SIP_HAR_ID` int(11) DEFAULT NULL,
  `MH_DEGER7` decimal(18,2) NOT NULL,
  `MH_DEGER8` decimal(18,2) NOT NULL,
  `MH_DEGER9` decimal(18,2) NOT NULL,
  `MH_DEGER10` decimal(18,2) NOT NULL,
  `OZELALAN3` varchar(40) DEFAULT NULL,
  `OZELALAN4` varchar(40) DEFAULT NULL,
  `OZELALAN5` varchar(40) DEFAULT NULL,
  `OZELGRUP3` int(11) DEFAULT NULL,
  `OZELGRUP4` int(11) DEFAULT NULL,
  `OZELGRUP5` int(11) DEFAULT NULL,
  `CARIID` int(11) DEFAULT NULL,
  `TEVKIFATID` int(11) DEFAULT NULL,
  `KUR_CARI` decimal(18,2) DEFAULT NULL,
  `CARIDOVIZID` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stk_birim`
--

CREATE TABLE `stk_birim` (
  `ID` int(11) NOT NULL,
  `STOKID` int(11) NOT NULL,
  `SIRANO` varchar(255) NOT NULL,
  `BIRIMID` int(11) NOT NULL,
  `KATSAYI` varchar(255) NOT NULL,
  `NETAGIRLIK_KG` decimal(18,2) DEFAULT NULL,
  `DARA_KG` decimal(18,2) DEFAULT NULL,
  `EN_MM` decimal(18,2) DEFAULT NULL,
  `BOY_MM` decimal(18,2) DEFAULT NULL,
  `YUKSEKLIK_MM` decimal(18,2) DEFAULT NULL,
  `DEPOZITO` decimal(18,2) DEFAULT NULL,
  `ALAN_M2` decimal(18,2) DEFAULT NULL,
  `BRUTHACIM` decimal(18,2) DEFAULT NULL,
  `NETHACIM` decimal(18,2) DEFAULT NULL,
  `VARSAYILAN` tinyint(1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stk_fis_har`
--

CREATE TABLE `stk_fis_har` (
  `ID` int(11) NOT NULL,
  `SIRANO` varchar(255) NOT NULL,
  `FATSIRANO` varchar(255) NOT NULL,
  `BOLUMID` int(11) NOT NULL,
  `FISTIP` varchar(255) NOT NULL,
  `STKFISID` int(11) NOT NULL,
  `FISTAR` datetime DEFAULT NULL,
  `ISLEMTIPI` varchar(255) NOT NULL,
  `KARTTIPI` varchar(1) NOT NULL,
  `KARTID` int(11) NOT NULL,
  `BARKODID` int(11) NOT NULL,
  `MIKTAR` decimal(18,2) NOT NULL,
  `BIRIMID` int(11) NOT NULL,
  `BIRIM1KATSAYI` varchar(255) NOT NULL,
  `BIRIM2KATSAYI` varchar(255) NOT NULL,
  `BIRIM3KATSAYI` varchar(255) NOT NULL,
  `FIYAT` decimal(18,2) NOT NULL,
  `DFIYAT` decimal(18,2) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `KUR_YEREL` decimal(18,2) NOT NULL,
  `KUR_RAPOR` decimal(18,2) NOT NULL,
  `KUR_CARI` decimal(18,2) NOT NULL,
  `CARIDOVIZID` int(11) DEFAULT NULL,
  `TUTAR` decimal(18,2) NOT NULL,
  `DTUTAR` decimal(18,2) NOT NULL,
  `KDVORANI` decimal(18,2) NOT NULL,
  `KDVMATRAHI` decimal(18,2) NOT NULL,
  `KDVTUTARI` decimal(18,2) NOT NULL,
  `ISKONTO1` decimal(18,2) DEFAULT NULL,
  `ISKONTO2` decimal(18,2) DEFAULT NULL,
  `ISKONTO3` decimal(18,2) DEFAULT NULL,
  `ISKONTO4` decimal(18,2) DEFAULT NULL,
  `ISKONTO5` decimal(18,2) DEFAULT NULL,
  `ISKONTO1TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO2TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO3TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO4TUTAR` decimal(18,2) NOT NULL,
  `ISKONTO5TUTAR` decimal(18,2) NOT NULL,
  `FISISK1TUTAR` decimal(18,2) NOT NULL,
  `FISISK2TUTAR` decimal(18,2) NOT NULL,
  `CARIID` int(11) NOT NULL,
  `OTPLANID` int(11) DEFAULT NULL,
  `VADE_TARIHI` datetime DEFAULT NULL,
  `DEPOID` int(11) NOT NULL,
  `CIKIS_DEPOID` int(11) DEFAULT NULL,
  `CIKISHARID` int(11) DEFAULT NULL,
  `ODEMETIP` varchar(255) DEFAULT NULL,
  `FATFISID` int(11) DEFAULT NULL,
  `FATFISTIP` int(11) DEFAULT NULL,
  `SIPFISID` int(11) DEFAULT NULL,
  `SIPHARID` int(11) DEFAULT NULL,
  `IPTAL` tinyint(1) NOT NULL,
  `USETRG` tinyint(1) NOT NULL,
  `SIPNO` varchar(15) DEFAULT NULL,
  `SIPTAR` datetime DEFAULT NULL,
  `STKFISNO` varchar(20) DEFAULT NULL,
  `STKFISTAR` datetime DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `ACIKLAMA` varchar(100) DEFAULT NULL,
  `STOPAJTUTAR` decimal(18,2) NOT NULL,
  `BAGKURTUTAR` decimal(18,2) NOT NULL,
  `KOMISYONKDVTUTAR` decimal(18,2) NOT NULL,
  `KOMISYONTUTAR` decimal(18,2) NOT NULL,
  `BORSATUTAR` decimal(18,2) NOT NULL,
  `KESINTI1TUTAR` decimal(18,2) NOT NULL,
  `KESINTI2TUTAR` decimal(18,2) NOT NULL,
  `OWNER_STOK_ID` int(11) DEFAULT NULL,
  `OWNER_TREE_ID` int(11) DEFAULT NULL,
  `OTVID` int(11) DEFAULT NULL,
  `OTVHARID` int(11) DEFAULT NULL,
  `OTVMATRAHI` decimal(18,2) NOT NULL,
  `OTVTUTARI` decimal(18,2) NOT NULL,
  `OTVINDIRIMI` decimal(18,2) NOT NULL,
  `PLASIYERID` int(11) DEFAULT NULL,
  `TEKLIFID` int(11) DEFAULT NULL,
  `TEKLIFHARID` int(11) DEFAULT NULL,
  `TEKLIFNO` varchar(15) DEFAULT NULL,
  `TEKLIFTAR` datetime DEFAULT NULL,
  `FISSAAT` datetime DEFAULT NULL,
  `KDVDAHIL` tinyint(1) NOT NULL,
  `SUBEID` int(11) NOT NULL,
  `CIKIS_SUBEID` int(11) DEFAULT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `ISTIRAKDETAYID` int(11) DEFAULT NULL,
  `GENELGRUP1` int(11) DEFAULT NULL,
  `GENELGRUP2` int(11) DEFAULT NULL,
  `GENELGRUP3` int(11) DEFAULT NULL,
  `GENELGRUP4` int(11) DEFAULT NULL,
  `GENELGRUP5` int(11) DEFAULT NULL,
  `ERP_LOTID` int(11) NOT NULL,
  `ERP_FIREID` int(11) DEFAULT NULL,
  `ERP_URETIM_FISID` int(11) DEFAULT NULL,
  `ERP_URETIM_HARID` int(11) DEFAULT NULL,
  `SIPONODEMELI` tinyint(1) DEFAULT NULL,
  `REAL_STOK_ID` int(11) DEFAULT NULL,
  `OWN_BOLUMID` int(11) DEFAULT NULL,
  `OWN_FISTIP` int(11) DEFAULT NULL,
  `OWNERID` int(11) DEFAULT NULL,
  `SUBOWNERID` int(11) DEFAULT NULL,
  `ERP_LOT_CIKIS_GIRIS_HARID` int(11) DEFAULT NULL,
  `ERP_LOT_GIRIS_CIKIS_MIKTAR` decimal(18,2) DEFAULT NULL,
  `MALIYET_ISLEM` varchar(255) DEFAULT NULL,
  `BAGLI_FISNO` varchar(30) DEFAULT NULL,
  `ERP_SEVK_EMRI_ID` int(11) NOT NULL,
  `ERP_SEVK_EMRI_DETAYID` int(11) NOT NULL,
  `MH_DEGER1` decimal(18,2) NOT NULL,
  `MH_DEGER2` decimal(18,2) NOT NULL,
  `MH_DEGER3` decimal(18,2) NOT NULL,
  `MH_DEGER4` decimal(18,2) NOT NULL,
  `MH_DEGER5` decimal(18,2) NOT NULL,
  `MH_DEGER6` decimal(18,2) NOT NULL,
  `ERP_YERID1` int(11) NOT NULL,
  `ERP_YERID2` int(11) NOT NULL,
  `ERP_YERID3` int(11) NOT NULL,
  `ERP_BOLUMID` int(11) DEFAULT NULL,
  `ERP_FISID` int(11) DEFAULT NULL,
  `ERP_HARID` int(11) DEFAULT NULL,
  `ERP_VARYANTID` int(11) NOT NULL,
  `MH_DEGER7` decimal(18,2) NOT NULL,
  `MH_DEGER8` decimal(18,2) NOT NULL,
  `MH_DEGER9` decimal(18,2) NOT NULL,
  `MH_DEGER10` decimal(18,2) NOT NULL,
  `EFATURA_ACIKLAMA` varchar(150) DEFAULT NULL,
  `OZELALAN3` varchar(40) DEFAULT NULL,
  `OZELALAN4` varchar(40) DEFAULT NULL,
  `OZELALAN5` varchar(40) DEFAULT NULL,
  `OZELGRUP3` int(11) DEFAULT NULL,
  `OZELGRUP4` int(11) DEFAULT NULL,
  `OZELGRUP5` int(11) DEFAULT NULL,
  `ADISYON_MASAID` int(11) NOT NULL,
  `FATFISNO` varchar(20) DEFAULT NULL,
  `IADETIP` varchar(255) NOT NULL,
  `IADEID` int(11) NOT NULL,
  `IADEFISID` int(11) NOT NULL,
  `KAMPANYAID` int(11) NOT NULL,
  `KAMPANYA_TIP` varchar(255) NOT NULL,
  `KAMPANYA_STOKID` int(11) NOT NULL,
  `EFAT_KAPCINS` varchar(255) DEFAULT NULL,
  `EFAT_KAPNO` varchar(20) DEFAULT NULL,
  `EFAT_KAPADET` varchar(20) DEFAULT NULL,
  `KAMPANYA_ISKTUTAR` decimal(18,2) NOT NULL,
  `KAMPANYA_FISISKTUTAR` decimal(18,2) NOT NULL,
  `NAVLUN_FIYAT` decimal(18,2) DEFAULT NULL,
  `NAVLUN_TUTAR` decimal(18,2) DEFAULT NULL,
  `TEVKIFATID` int(11) DEFAULT NULL,
  `ERP_HATALI_STOK` tinyint(1) NOT NULL,
  `KONAKLAMAORANI` decimal(18,2) DEFAULT NULL,
  `KONAKLAMATUTARI` decimal(18,2) DEFAULT NULL,
  `YANITTESLIMMIKTAR` decimal(18,2) DEFAULT NULL,
  `YANITEKSIKMIKTAR` decimal(18,2) DEFAULT NULL,
  `YANITREDMIKTAR` decimal(18,2) DEFAULT NULL,
  `YANITFAZLAMIKTAR` decimal(18,2) DEFAULT NULL,
  `YANITREDKOD` varchar(200) DEFAULT NULL,
  `YANITREDACIKLAMA` text DEFAULT NULL,
  `EIRSALIYESIRANO` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stk_fiyat`
--

CREATE TABLE `stk_fiyat` (
  `ID` int(11) NOT NULL,
  `BOLUMID` int(11) NOT NULL,
  `STOKID` int(11) NOT NULL,
  `TIP` varchar(1) NOT NULL,
  `ADIID` int(11) NOT NULL,
  `CARIID` int(11) NOT NULL,
  `GRUPID` int(11) NOT NULL,
  `DEFFIYAT` tinyint(1) NOT NULL,
  `URTFIYAT` tinyint(1) NOT NULL,
  `BIRIMID` int(11) NOT NULL,
  `FIYAT` decimal(18,2) NOT NULL,
  `DOVIZID` int(11) NOT NULL,
  `OTPLANID` int(11) NOT NULL,
  `KDVDAHIL` tinyint(1) NOT NULL,
  `ILKTARIH` datetime DEFAULT NULL,
  `SONTARIH` datetime DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `KASAFIYATNO` varchar(255) NOT NULL,
  `SONGUNCELTAR` datetime DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `ISKONTO1` decimal(18,2) DEFAULT NULL,
  `ISKONTO2` decimal(18,2) DEFAULT NULL,
  `ISKONTO3` decimal(18,2) DEFAULT NULL,
  `ISKONTO4` decimal(18,2) DEFAULT NULL,
  `ISKONTO5` decimal(18,2) DEFAULT NULL,
  `SATSOZLESMEID` int(11) DEFAULT NULL,
  `SUBEID` int(11) NOT NULL,
  `OZELISKONTO` tinyint(1) DEFAULT NULL,
  `KOMBINIDETAY` tinyint(1) DEFAULT NULL,
  `VARYANT_KOSULU` varchar(200) DEFAULT NULL,
  `SONGUNCELSAAT` datetime DEFAULT NULL,
  `BIRIM1NETFIYAT` decimal(18,2) DEFAULT NULL,
  `BIRIM2NETFIYAT` decimal(18,2) DEFAULT NULL,
  `BIRIM3NETFIYAT` decimal(18,2) DEFAULT NULL,
  `NET_FIYAT` decimal(18,2) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stk_urun_miktar`
--

CREATE TABLE `stk_urun_miktar` (
  `ID` int(11) NOT NULL,
  `URUN_ID` int(11) NOT NULL,
  `MIKTAR` decimal(10,2) NOT NULL DEFAULT 0.00,
  `SON_GUNCELLEME` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stock_movements`
--

CREATE TABLE `stock_movements` (
  `id` int(11) NOT NULL,
  `product_id` int(11) NOT NULL,
  `movement_type` enum('in','out') COLLATE utf8mb4_unicode_ci NOT NULL,
  `quantity` int(11) NOT NULL,
  `unit_price` decimal(15,2) NOT NULL,
  `reference_type` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `reference_no` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `reference_id` int(11) DEFAULT NULL,
  `description` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_id` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `stok`
--

CREATE TABLE `stok` (
  `ID` int(11) NOT NULL,
  `KARTTIPI` varchar(1) NOT NULL,
  `KOD` varchar(100) NOT NULL,
  `ADI` varchar(150) NOT NULL,
  `DURUM` tinyint(1) NOT NULL,
  `KDVIDPERAKENDE` int(11) NOT NULL,
  `OWNER_STOK_ID` int(11) DEFAULT NULL,
  `DEPOUSEDEF` tinyint(1) NOT NULL,
  `KARORAN` decimal(18,2) DEFAULT NULL,
  `TIP` varchar(255) DEFAULT NULL,
  `KDVID` int(11) NOT NULL,
  `SERINOUSETIP` varchar(255) DEFAULT NULL,
  `GRN_BASTIP` varchar(255) DEFAULT NULL,
  `GRN_AY` varchar(255) DEFAULT NULL,
  `SERINOBAS` varchar(5) DEFAULT NULL,
  `MUHHES_YI_ALIS` int(11) DEFAULT NULL,
  `MUHHES_YI_ALISIADE` int(11) DEFAULT NULL,
  `MUHHES_YI_SATIS` int(11) DEFAULT NULL,
  `MUHHES_YI_SATISIADE` int(11) DEFAULT NULL,
  `MUHHES_YD_SATIS` int(11) DEFAULT NULL,
  `MUHHES_YD_SATISIADE` int(11) DEFAULT NULL,
  `MUHHES_FFARK_ALIS` int(11) DEFAULT NULL,
  `MUHHES_FFARK_SATIS` int(11) DEFAULT NULL,
  `DEPOBSIZUYAR` tinyint(1) NOT NULL,
  `DEPOID` int(11) DEFAULT NULL,
  `ACIKLAMA` varchar(100) DEFAULT NULL,
  `OZELGRUP1` int(11) DEFAULT NULL,
  `OZELGRUP2` int(11) DEFAULT NULL,
  `OZELGRUP3` int(11) DEFAULT NULL,
  `OZELGRUP4` int(11) DEFAULT NULL,
  `OZELGRUP5` int(11) DEFAULT NULL,
  `OZELGRUP6` int(11) DEFAULT NULL,
  `OZELGRUP7` int(11) DEFAULT NULL,
  `OZELGRUP8` int(11) DEFAULT NULL,
  `OZELGRUP9` int(11) DEFAULT NULL,
  `OZELGRUP10` int(11) DEFAULT NULL,
  `OZELALAN1` varchar(40) DEFAULT NULL,
  `OZELALAN2` varchar(40) DEFAULT NULL,
  `OZELALAN3` varchar(40) DEFAULT NULL,
  `OZELALAN4` varchar(40) DEFAULT NULL,
  `OZELALAN5` varchar(40) DEFAULT NULL,
  `OZELALAN6` varchar(40) DEFAULT NULL,
  `OZELALAN7` varchar(40) DEFAULT NULL,
  `OZELALAN8` varchar(40) DEFAULT NULL,
  `OZELALAN9` varchar(40) DEFAULT NULL,
  `OZELALAN10` varchar(40) DEFAULT NULL,
  `NOTLAR` text DEFAULT NULL,
  `DEPARTMANID` int(11) DEFAULT NULL,
  `PLESORAN` decimal(18,2) DEFAULT NULL,
  `OTVID` int(11) DEFAULT NULL,
  `BRK_TIP` varchar(255) DEFAULT NULL,
  `BRK_UZUNLUK` varchar(255) DEFAULT NULL,
  `BRK_VARSAYILAN` varchar(20) DEFAULT NULL,
  `BRK_BASDEGER` int(11) DEFAULT NULL,
  `MUHHES_SAIR_GIRISLER` int(11) DEFAULT NULL,
  `MUHHES_SAIR_CIKISLAR` int(11) DEFAULT NULL,
  `MUHHES_FIRE` int(11) DEFAULT NULL,
  `MUHHES_SARF` int(11) DEFAULT NULL,
  `MUHHES_URETIMDEN_GIRIS` int(11) DEFAULT NULL,
  `YETKIKODID` int(11) DEFAULT NULL,
  `USEGRUPFIYAT` tinyint(1) DEFAULT NULL,
  `ERP_DIGER_ADI` varchar(100) DEFAULT NULL,
  `ERP_LOTTAKIBI` tinyint(1) NOT NULL,
  `ERP_OPERASYON` tinyint(1) DEFAULT NULL,
  `ERP_MUHHES_FASON_FIRE` int(11) DEFAULT NULL,
  `ERP_MUHHES_FASON_SARF` int(11) DEFAULT NULL,
  `ERP_MUHHES_FASON_URETIMDEN_GIRIS` int(11) DEFAULT NULL,
  `ERP_MUHHES_FASON_CIKIS` int(11) DEFAULT NULL,
  `ERP_LOTCIKIS` varchar(255) DEFAULT NULL,
  `MUHHES_TEVKIFAT_ALIS` int(11) DEFAULT NULL,
  `MUHHES_TEVKIFAT_SATIS` int(11) DEFAULT NULL,
  `DEGISKEN_KATSAYILI` tinyint(1) DEFAULT NULL,
  `WEBDEGOSTER` tinyint(1) DEFAULT NULL,
  `DEGKAT_TAKIP` tinyint(1) DEFAULT NULL,
  `DEGKAT_TAKIP_UZUNLUK` varchar(255) DEFAULT NULL,
  `DEGKAT_TAKIP_BASLANGIC` int(11) DEFAULT NULL,
  `DEGKAT_TAKIP_ONKOD` varchar(100) DEFAULT NULL,
  `ERP_AGIRLIK_BIRIM` varchar(255) DEFAULT NULL,
  `ERP_YERTAKIBI` tinyint(1) NOT NULL,
  `ERP_YERID1` int(11) DEFAULT NULL,
  `ERP_YERID2` int(11) DEFAULT NULL,
  `ERP_YERID3` int(11) DEFAULT NULL,
  `ERP_AMBALAJ` tinyint(1) NOT NULL,
  `ERP_EN` decimal(18,2) DEFAULT NULL,
  `ERP_BOY` decimal(18,2) DEFAULT NULL,
  `ERP_DERINLIK` decimal(18,2) DEFAULT NULL,
  `ERP_ALAN` decimal(18,2) DEFAULT NULL,
  `ERP_HACIM` decimal(18,2) DEFAULT NULL,
  `ERP_EN_BIRIM` varchar(255) DEFAULT NULL,
  `ERP_BOY_BIRIM` varchar(255) DEFAULT NULL,
  `ERP_DERINLIK_BIRIM` varchar(255) DEFAULT NULL,
  `ERP_ALAN_BIRIM` varchar(255) DEFAULT NULL,
  `ERP_HACIM_BIRIM` varchar(255) DEFAULT NULL,
  `ERP_AGIRLIK` decimal(18,2) DEFAULT NULL,
  `ERP_VARYANTLI` tinyint(1) NOT NULL,
  `HS_TARTILABILIR` tinyint(1) DEFAULT NULL,
  `MUHHES_URETIMDEN_SEVK` int(11) DEFAULT NULL,
  `ERP_VARYANT_ADI_DESENI` varchar(100) DEFAULT NULL,
  `YAZDIRMA_GRUPID` int(11) NOT NULL,
  `EFAT_GTIP` varchar(12) DEFAULT NULL,
  `YERLI_URETIM` tinyint(1) NOT NULL,
  `HS_GONDERILDI` tinyint(1) DEFAULT NULL,
  `HS_BIRIM_KATSAYISINDAN_FIYAT_HESAPLA` tinyint(1) NOT NULL,
  `TEVKIFATID` int(11) DEFAULT NULL,
  `SABIT_KIYMET_DEPARTMANID` int(11) DEFAULT NULL,
  `SABIT_KIYMET_SERINO` varchar(40) DEFAULT NULL,
  `SABIT_KIYMET_PLAKANO` varchar(40) DEFAULT NULL,
  `SABIT_KIYMET_HURDADEGERI` decimal(18,2) DEFAULT NULL,
  `SABIT_KIYMET_FAYDALIOMUR` varchar(255) DEFAULT NULL,
  `SABIT_KIYMET_BITISTARIHI` datetime DEFAULT NULL,
  `SABIT_KIYMET_HESAPLAMASEKLI` varchar(255) DEFAULT NULL,
  `SABIT_KIYMET_UYGULAMASURESI` varchar(255) DEFAULT NULL,
  `SABIT_KIYMET_AMORTISMANDONEM` varchar(255) DEFAULT NULL,
  `SABIT_KIYMET_NORM_AMORTORAN` decimal(18,2) DEFAULT NULL,
  `SABIT_KIYMET_AZALAN_AMORTORAN` decimal(18,2) DEFAULT NULL,
  `MUHHES_SABIT_KIYMET_KAR` int(11) DEFAULT NULL,
  `MUHHES_SABIT_KIYMET_ZARAR` int(11) DEFAULT NULL,
  `MUHHES_SABIT_KIYMET_AMORT_GIDER` int(11) DEFAULT NULL,
  `MUHHES_SABIT_KIYMET_BIRIK_AMORT` int(11) DEFAULT NULL,
  `KONAKLAMA_VERGISI` tinyint(1) DEFAULT NULL,
  `MUHHES_ENFLASYONFARKI` int(11) DEFAULT NULL,
  `MUHHES_ENFLASYONDUZELTME` int(11) DEFAULT NULL,
  `EBELGEKULLANIMTIPI` varchar(255) DEFAULT NULL,
  `MIN_STOK` decimal(10,2) NOT NULL DEFAULT 0.00,
  `EKLEYEN_ID` int(11) NOT NULL DEFAULT -1,
  `EKLENME_TARIHI` datetime DEFAULT current_timestamp(),
  `GUNCELLEYEN_ID` int(11) NOT NULL DEFAULT -1,
  `GUNCELLEME_TARIHI` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `suppliers`
--

CREATE TABLE `suppliers` (
  `id` int(11) NOT NULL,
  `code` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `tax_number` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `tax_office` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `address` text COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `phone` varchar(20) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `created_by` int(11) NOT NULL,
  `updated_at` timestamp NULL DEFAULT NULL,
  `updated_by` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `tablo_aktarim_kayitlari`
--

CREATE TABLE `tablo_aktarim_kayitlari` (
  `id` int(11) NOT NULL,
  `kaynak_tablo` varchar(100) NOT NULL,
  `hedef_tablo` varchar(100) NOT NULL,
  `aktarilan_kayit_sayisi` int(11) NOT NULL,
  `aktarim_tarihi` datetime NOT NULL,
  `aktaran_kullanici` varchar(50) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `password` varchar(255) COLLATE utf8mb4_unicode_ci NOT NULL,
  `email` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `full_name` varchar(100) COLLATE utf8mb4_unicode_ci NOT NULL,
  `role` varchar(20) COLLATE utf8mb4_unicode_ci NOT NULL,
  `status` enum('active','inactive') COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT 'active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  `last_login` timestamp NULL DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- --------------------------------------------------------

--
-- Tablo için tablo yapısı `user_permissions`
--

CREATE TABLE `user_permissions` (
  `id` int(11) NOT NULL,
  `user_id` int(11) NOT NULL,
  `module_name` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `permission` varchar(50) COLLATE utf8mb4_unicode_ci NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

--
-- Dökümü yapılmış tablolar için indeksler
--

--
-- Tablo için indeksler `alternative_groups`
--
ALTER TABLE `alternative_groups`
  ADD PRIMARY KEY (`id`);

--
-- Tablo için indeksler `cari`
--
ALTER TABLE `cari`
  ADD PRIMARY KEY (`ID`,`KOD`);

--
-- Tablo için indeksler `cariler`
--
ALTER TABLE `cariler`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `unique_cari_kodu` (`cari_kodu`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Tablo için indeksler `cari_fis`
--
ALTER TABLE `cari_fis`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `cari_hareket`
--
ALTER TABLE `cari_hareket`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `cari_iletisimler`
--
ALTER TABLE `cari_iletisimler`
  ADD PRIMARY KEY (`id`),
  ADD KEY `cari_id` (`cari_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `cek_senet`
--
ALTER TABLE `cek_senet`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `islem_no` (`islem_no`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Tablo için indeksler `customers`
--
ALTER TABLE `customers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Tablo için indeksler `earsiv`
--
ALTER TABLE `earsiv`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `efat_grup`
--
ALTER TABLE `efat_grup`
  ADD PRIMARY KEY (`ID`,`KOD`);

--
-- Tablo için indeksler `fatura`
--
ALTER TABLE `fatura`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `grup`
--
ALTER TABLE `grup`
  ADD PRIMARY KEY (`ID`,`KOD`);

--
-- Tablo için indeksler `kasa`
--
ALTER TABLE `kasa`
  ADD PRIMARY KEY (`ID`,`KOD`);

--
-- Tablo için indeksler `kasa_hareket`
--
ALTER TABLE `kasa_hareket`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `kdv`
--
ALTER TABLE `kdv`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `muhasebe_kayitlari`
--
ALTER TABLE `muhasebe_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `oem_numbers`
--
ALTER TABLE `oem_numbers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `product_oem_unique` (`product_id`,`oem_no`),
  ADD KEY `oem_no` (`oem_no`);

--
-- Tablo için indeksler `product_alternatives`
--
ALTER TABLE `product_alternatives`
  ADD PRIMARY KEY (`product_id`,`alternative_group_id`),
  ADD KEY `alternative_group_id` (`alternative_group_id`);

--
-- Tablo için indeksler `product_categories`
--
ALTER TABLE `product_categories`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Tablo için indeksler `purchase_invoices`
--
ALTER TABLE `purchase_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `supplier_id` (`supplier_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Tablo için indeksler `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Tablo için indeksler `sales_invoices`
--
ALTER TABLE `sales_invoices`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `invoice_no` (`invoice_no`),
  ADD KEY `customer_id` (`customer_id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Tablo için indeksler `sales_invoice_items`
--
ALTER TABLE `sales_invoice_items`
  ADD PRIMARY KEY (`id`),
  ADD KEY `invoice_id` (`invoice_id`),
  ADD KEY `product_id` (`product_id`);

--
-- Tablo için indeksler `siparis`
--
ALTER TABLE `siparis`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `sip_har`
--
ALTER TABLE `sip_har`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `stk_birim`
--
ALTER TABLE `stk_birim`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `stk_fis_har`
--
ALTER TABLE `stk_fis_har`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `stk_fiyat`
--
ALTER TABLE `stk_fiyat`
  ADD PRIMARY KEY (`ID`);

--
-- Tablo için indeksler `stk_urun_miktar`
--
ALTER TABLE `stk_urun_miktar`
  ADD PRIMARY KEY (`ID`),
  ADD UNIQUE KEY `URUN_ID` (`URUN_ID`);

--
-- Tablo için indeksler `stock_movements`
--
ALTER TABLE `stock_movements`
  ADD PRIMARY KEY (`id`),
  ADD KEY `product_id` (`product_id`),
  ADD KEY `created_by` (`created_by`);

--
-- Tablo için indeksler `stok`
--
ALTER TABLE `stok`
  ADD PRIMARY KEY (`ID`,`KOD`);

--
-- Tablo için indeksler `suppliers`
--
ALTER TABLE `suppliers`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `code` (`code`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `updated_by` (`updated_by`);

--
-- Tablo için indeksler `tablo_aktarim_kayitlari`
--
ALTER TABLE `tablo_aktarim_kayitlari`
  ADD PRIMARY KEY (`id`),
  ADD KEY `kaynak_tablo` (`kaynak_tablo`),
  ADD KEY `hedef_tablo` (`hedef_tablo`);

--
-- Tablo için indeksler `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`),
  ADD UNIQUE KEY `email` (`email`);

--
-- Tablo için indeksler `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `user_id` (`user_id`);

--
-- Dökümü yapılmış tablolar için AUTO_INCREMENT değeri
--

--
-- Tablo için AUTO_INCREMENT değeri `alternative_groups`
--
ALTER TABLE `alternative_groups`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `cariler`
--
ALTER TABLE `cariler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `cari_iletisimler`
--
ALTER TABLE `cari_iletisimler`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `cek_senet`
--
ALTER TABLE `cek_senet`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `customers`
--
ALTER TABLE `customers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `muhasebe_kayitlari`
--
ALTER TABLE `muhasebe_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `oem_numbers`
--
ALTER TABLE `oem_numbers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `product_categories`
--
ALTER TABLE `product_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `purchase_invoices`
--
ALTER TABLE `purchase_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sales_invoices`
--
ALTER TABLE `sales_invoices`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `sales_invoice_items`
--
ALTER TABLE `sales_invoice_items`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stk_urun_miktar`
--
ALTER TABLE `stk_urun_miktar`
  MODIFY `ID` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `stock_movements`
--
ALTER TABLE `stock_movements`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `suppliers`
--
ALTER TABLE `suppliers`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `tablo_aktarim_kayitlari`
--
ALTER TABLE `tablo_aktarim_kayitlari`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Tablo için AUTO_INCREMENT değeri `user_permissions`
--
ALTER TABLE `user_permissions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- Dökümü yapılmış tablolar için kısıtlamalar
--

--
-- Tablo kısıtlamaları `cariler`
--
ALTER TABLE `cariler`
  ADD CONSTRAINT `cariler_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL,
  ADD CONSTRAINT `cariler_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `cari_iletisimler`
--
ALTER TABLE `cari_iletisimler`
  ADD CONSTRAINT `cari_iletisimler_ibfk_1` FOREIGN KEY (`cari_id`) REFERENCES `cariler` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `cari_iletisimler_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE SET NULL;

--
-- Tablo kısıtlamaları `cek_senet`
--
ALTER TABLE `cek_senet`
  ADD CONSTRAINT `cek_senet_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `cek_senet_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `customers`
--
ALTER TABLE `customers`
  ADD CONSTRAINT `customers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `customers_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `muhasebe_kayitlari`
--
ALTER TABLE `muhasebe_kayitlari`
  ADD CONSTRAINT `muhasebe_kayitlari_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `product_alternatives`
--
ALTER TABLE `product_alternatives`
  ADD CONSTRAINT `product_alternatives_ibfk_1` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `product_alternatives_ibfk_2` FOREIGN KEY (`alternative_group_id`) REFERENCES `alternative_groups` (`id`) ON DELETE CASCADE;

--
-- Tablo kısıtlamaları `purchase_invoices`
--
ALTER TABLE `purchase_invoices`
  ADD CONSTRAINT `purchase_invoices_ibfk_1` FOREIGN KEY (`supplier_id`) REFERENCES `suppliers` (`id`),
  ADD CONSTRAINT `purchase_invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `purchase_invoices_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `purchase_invoice_items`
--
ALTER TABLE `purchase_invoice_items`
  ADD CONSTRAINT `purchase_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `purchase_invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `purchase_invoice_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Tablo kısıtlamaları `sales_invoices`
--
ALTER TABLE `sales_invoices`
  ADD CONSTRAINT `sales_invoices_ibfk_1` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`),
  ADD CONSTRAINT `sales_invoices_ibfk_2` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `sales_invoices_ibfk_3` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `sales_invoice_items`
--
ALTER TABLE `sales_invoice_items`
  ADD CONSTRAINT `sales_invoice_items_ibfk_1` FOREIGN KEY (`invoice_id`) REFERENCES `sales_invoices` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `sales_invoice_items_ibfk_2` FOREIGN KEY (`product_id`) REFERENCES `products` (`id`);

--
-- Tablo kısıtlamaları `suppliers`
--
ALTER TABLE `suppliers`
  ADD CONSTRAINT `suppliers_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`),
  ADD CONSTRAINT `suppliers_ibfk_2` FOREIGN KEY (`updated_by`) REFERENCES `users` (`id`);

--
-- Tablo kısıtlamaları `user_permissions`
--
ALTER TABLE `user_permissions`
  ADD CONSTRAINT `user_permissions_ibfk_1` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) ON DELETE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

--
-- Tablo için tablo yapısı `barkodlar`
--

CREATE TABLE `barkodlar` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `stok_id` int(11) NOT NULL,
  `barkod` varchar(50) NOT NULL,
  `aktif` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`),
  UNIQUE KEY `barkod` (`barkod`),
  KEY `stok_id` (`stok_id`),
  CONSTRAINT `barkodlar_ibfk_1` FOREIGN KEY (`stok_id`) REFERENCES `stok` (`ID`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
