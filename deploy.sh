#!/bin/bash

# ==========================================================
# 2T1 HUB - OTOMATİK KURULUM VE GÜNCELLEME SCRİPTİ
# ==========================================================
# Bu script mevcut /var/www/2t1.online klasörünü tamamen siler,
# Windows'tan (veya zip'ten) atılan yeni dosyaları oraya kopyalar
# ve izinleri/nginx ayarlarını yapar.

echo "[+] 2T1 Hub Kurulumu Basliyor..."

# 1. Eski klasörü ve dosyaları tamamen SİL
echo "[+] Eski /var/www/2t1.online siliniyor..."
rm -rf /var/www/2t1.online

# 2. Yeni klasörü oluştur
echo "[+] Yeni klasor olusturuluyor..."
mkdir -p /var/www/2t1.online

# 3. Dosyaları kopyala (Bu scriptin bulunduğu dizindeki her şeyi atar)
# zip veya SFTP ile atılan mevcut klasördeki dosyaları hedefe taşır.
echo "[+] Dosyalar kopyalaniyor..."
cp -R * /var/www/2t1.online/

# Deploy scriptinin kendisini sunucudan sil (gerek yok)
rm -f /var/www/2t1.online/deploy.sh
rm -f /var/www/2t1.online/nginx.conf

# 4. Gerekli klasörleri oluştur (data, logs, scripts)
echo "[+] Data ve Log klasorleri ayarlaniyor..."
mkdir -p /var/www/2t1.online/data
mkdir -p /var/www/2t1.online/logs
mkdir -p /var/www/2t1.online/scripts

# 5. Dosya İzinleri
echo "[+] Izinler (www-data) ayarlaniyor..."
chown -R www-data:www-data /var/www/2t1.online
chmod -R 755 /var/www/2t1.online
# Data, Log ve Scripts klasörlerine yazma yetkisi
chmod -R 777 /var/www/2t1.online/data
chmod -R 777 /var/www/2t1.online/logs
chmod -R 777 /var/www/2t1.online/scripts

# 6. Nginx Ayarı (Eğer nginx.conf varsa)
if [ -f "./nginx.conf" ]; then
    echo "[+] Nginx yapilandirmasi kuruluyor..."
    cp ./nginx.conf /etc/nginx/sites-available/2t1.online
    ln -sf /etc/nginx/sites-available/2t1.online /etc/nginx/sites-enabled/
    
    echo "[+] Nginx yeniden baslatiliyor..."
    systemctl restart nginx
else
    echo "[-] nginx.conf bulunamadi, Nginx adimi atlandi."
fi

# 7. Discord Bot Kurulumu (Gerekirse)
# Kullanici tarafindan kaldirildi.

echo "=========================================================="
echo "✅ Kurulum Tamamlandi! Web sitesi ve Bot artik aktif."
echo "=========================================================="
