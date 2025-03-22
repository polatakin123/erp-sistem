# ERP Sistem

ERP Sistem, küçük ve orta ölçekli işletmeler için tasarlanmış bir Kurumsal Kaynak Planlama (ERP) sistemidir. Bu sistem, işletmelerin günlük operasyonlarını yönetmelerine, muhasebe işlemlerini takip etmelerine ve karar alma süreçlerini iyileştirmelerine yardımcı olur.

## Özellikler

- **Kullanıcı Yönetimi**: Rol tabanlı erişim kontrolü ile güvenli kullanıcı yönetimi
- **Muhasebe Modülü**: Gelir-gider takibi, çek/senet işlemleri
- **Stok Yönetimi**: Ürün envanteri, stok takibi, kritik stok seviyesi uyarıları
- **Satış Yönetimi**: Satış faturası oluşturma, müşteri takibi
- **Satın Alma Yönetimi**: Satın alma faturası oluşturma, tedarikçi takibi
- **Raporlama**: Gelir-gider raporları, stok raporları, satış raporları
- **Ayarlar**: Şirket bilgileri, sistem ayarları, kullanıcı profili yönetimi

## Kurulum

### Gereksinimler

- PHP 7.4 veya üzeri
- MySQL 5.7 veya üzeri
- Web sunucusu (Apache, Nginx, vb.)

### Adımlar

1. Dosyaları web sunucunuza yükleyin
2. Ana sayfaya (index.php) eriştiğinizde otomatik olarak kurulum sayfasına yönlendirileceksiniz
3. Kurulum sayfasında veritabanı bağlantı bilgilerinizi girin:
   - Veritabanı Sunucusu: MySQL sunucunuzun adresi (genellikle `localhost`)
   - Kullanıcı Adı: MySQL kullanıcı adınız (genellikle `root`)
   - Şifre: MySQL şifreniz
   - Veritabanı Adı: Oluşturulacak veritabanının adı (örn. `erp_sistem`)
4. "Veritabanını Yükle" butonuna tıklayın
5. Kurulum tamamlandıktan sonra, aşağıdaki bilgilerle sisteme giriş yapabilirsiniz:
   - Kullanıcı Adı: `admin`
   - Şifre: `admin123`
6. İlk girişten sonra güvenlik için şifrenizi değiştirmeyi unutmayın

## Kullanım

Sisteme giriş yaptıktan sonra, sol taraftaki menüden ilgili modüllere erişebilirsiniz:

- **Ana Sayfa**: Genel durum özeti ve istatistikler
- **Muhasebe**: Gelir-gider kayıtları, çek/senet işlemleri
- **Stok**: Ürün yönetimi, stok işlemleri
- **Satış**: Müşteri yönetimi, satış faturası işlemleri
- **Satın Alma**: Tedarikçi yönetimi, satın alma faturası işlemleri
- **Raporlar**: Çeşitli raporlar ve analizler
- **Ayarlar**: Sistem ayarları, şirket bilgileri, kullanıcı profili

## Güvenlik

Sistem, aşağıdaki güvenlik önlemlerini içerir:

- Şifreler güvenli bir şekilde hash'lenerek saklanır
- Rol tabanlı erişim kontrolü ile yetkilendirme
- Oturum süresi kontrolü ve otomatik oturum kapatma
- SQL enjeksiyon koruması için PDO kullanımı
- XSS saldırılarına karşı giriş doğrulama ve temizleme

## Bakım ve Destek

Sistem ile ilgili herhangi bir sorun, öneri veya hata bildirimi için:
- E-posta: destek@erpsistem.com
- GitHub: [https://github.com/erpsistem/](https://github.com/erpsistem/)

## Lisans

Bu proje [MIT Lisansı](LICENSE) altında lisanslanmıştır.

## Ekran Görüntüleri

![Ana Sayfa](screenshots/ana_sayfa.png)
![Muhasebe Modülü](screenshots/muhasebe.png)
![Stok Yönetimi](screenshots/stok.png)

---

&copy; 2024 ERP Sistem. Tüm hakları saklıdır. 