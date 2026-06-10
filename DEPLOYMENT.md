# Deployment Guide — MPSI Marketplace

Panduan deploy ke VPS dengan **CyberPanel** menggunakan 2 subdomain terpisah:
- `mpsi.contoh.com` → Frontend SvelteKit (Node SSR)
- `api.mpsi.contoh.com` → Backend Laravel 11

> Asumsi: VPS Ubuntu 22.04 + CyberPanel sudah terinstall, domain `contoh.com` sudah diarahkan ke IP VPS via A record.

---

## 1. Persiapan Server (sekali saja)

SSH ke VPS sebagai root:

```bash
ssh root@<IP_VPS>
```

Install Node.js 20 LTS (kalau belum ada):

```bash
curl -fsSL https://deb.nodesource.com/setup_20.x | bash -
apt install -y nodejs
node -v   # harus v20.x
npm -v
```

Install PM2 untuk Node process manager:

```bash
npm install -g pm2
```

Cek PHP 8.2 dan Composer (CyberPanel sudah include):

```bash
php -v
composer -V
# kalau composer belum ada:
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
chmod +x /usr/local/bin/composer
```

---

## 2. Buat Subdomain di CyberPanel

### 2.1 Subdomain Backend (`api.mpsi.contoh.com`)

1. Login CyberPanel di `https://<IP_VPS>:8090`
2. **Websites → Create Website**
3. Pilih package, isi:
   - Domain: `api.mpsi.contoh.com`
   - Email: email admin Anda
   - PHP: 8.2
   - SSL: ✓ (centang issue SSL)
   - DKIM: opsional
4. **Create Website**
5. Tunggu CyberPanel issue SSL + setup vhost (~1 menit)

### 2.2 Subdomain Frontend (`mpsi.contoh.com`)

Ulangi langkah di atas dengan domain `mpsi.contoh.com`. PHP-nya tidak akan dipakai (kita pakai Node), tapi tetap pilih 8.2 untuk kepraktisan.

### 2.3 Buat Database MySQL

1. **Databases → Create Database**
2. Website: `api.mpsi.contoh.com`
3. Database Name: `mpsi` (atau apapun)
4. User: `mpsi_user`
5. Password: generate strong password — **simpan**
6. **Create**

---

## 3. Deploy Backend (Laravel)

### 3.1 Upload kode

SSH ke VPS, pindah ke direktori site backend:

```bash
cd /home/api.mpsi.contoh.com/public_html
```

Pull kode dari git (kalau pakai git) atau upload via SFTP/FTP. Contoh dengan git:

```bash
# Hapus dulu file default cyberpanel
rm -rf *

# Clone repo (asumsi sudah punya repo di GitHub/GitLab)
git clone https://github.com/USERNAME/portalsi-marketplace.git temp
mv temp/backend/* temp/backend/.* . 2>/dev/null
rm -rf temp
```

Atau **upload manual** isi folder `backend/` saja ke `/home/api.mpsi.contoh.com/public_html/`.

### 3.2 Install dependencies

```bash
cd /home/api.mpsi.contoh.com/public_html
composer install --no-dev --optimize-autoloader
```

### 3.3 Konfigurasi `.env`

```bash
cp .env.example .env
nano .env
```

Edit isi penting:

```env
APP_NAME=MPSI
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://api.mpsi.contoh.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=mpsi
DB_USERNAME=mpsi_user
DB_PASSWORD=<password_yang_disimpan_tadi>

# Frontend URL — penting untuk email links
FRONTEND_URL=https://mpsi.contoh.com

# Brevo (kalau sudah punya)
BREVO_API_KEY=xkeysib-...
BREVO_SENDER_EMAIL=noreply@contoh.com
BREVO_SENDER_NAME=MPSI

# Tripay production
TRIPAY_MODE=production
TRIPAY_API_KEY=...
TRIPAY_PRIVATE_KEY=...
TRIPAY_MERCHANT_CODE=T...
```

Generate APP_KEY:

```bash
php artisan key:generate
```

### 3.4 Migrate + seed

```bash
php artisan migrate --force
php artisan db:seed --class=SettingsSeeder --force
# Optional: seed demo data
# php artisan db:seed --force
```

### 3.5 Cache config & permission

```bash
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Storage & cache permissions
chown -R cyberpanel:cyberpanel storage bootstrap/cache
chmod -R 775 storage bootstrap/cache
```

### 3.6 Point document root ke `public/`

Di CyberPanel:

1. **Websites → List Websites → Manage `api.mpsi.contoh.com`**
2. **vHost Conf** (atau **Rewrite Rules**)
3. Cari baris `DocumentRoot` di vhost dan ubah ke:
   ```
   DocumentRoot /home/api.mpsi.contoh.com/public_html/public
   ```
4. **Save & Restart**

Alternatif (tanpa edit vhost): buat `.htaccess` di `/home/api.mpsi.contoh.com/public_html/.htaccess`:

```apache
<IfModule mod_rewrite.c>
  RewriteEngine On
  RewriteRule ^(.*)$ public/$1 [L]
</IfModule>
```

### 3.7 Test backend

Buka di browser: `https://api.mpsi.contoh.com/api/settings/public`

Harus return JSON. Kalau error 500, cek log:
```bash
tail -50 /home/api.mpsi.contoh.com/public_html/storage/logs/laravel.log
```

---

## 4. Deploy Frontend (SvelteKit)

### 4.1 Build di local (lebih cepat) atau di server

**Opsi A: Build di local**, upload hasil build.

Di komputer lokal Anda:

```bash
cd frontend
echo "PUBLIC_API_URL=https://api.mpsi.contoh.com/api" > .env.production
npm install
npm run build
```

Ini menghasilkan folder `build/` yang berisi Node SSR app.

Upload via SCP/SFTP:
- `build/` → `/home/mpsi.contoh.com/public_html/build/`
- `package.json` → `/home/mpsi.contoh.com/public_html/package.json`
- `package-lock.json` → `/home/mpsi.contoh.com/public_html/package-lock.json`

### 4.2 Atau build di server

```bash
cd /home/mpsi.contoh.com/public_html
rm -rf *
git clone https://github.com/USERNAME/portalsi-marketplace.git temp
mv temp/frontend/* temp/frontend/.* . 2>/dev/null
rm -rf temp

# Setup env
echo "PUBLIC_API_URL=https://api.mpsi.contoh.com/api" > .env.production

# Install + build
npm install
npm run build
```

### 4.3 Install adapter-node (kalau belum)

Cek `frontend/svelte.config.js`. Pastikan pakai `@sveltejs/adapter-node`:

```bash
cd /home/mpsi.contoh.com/public_html
npm install --save-dev @sveltejs/adapter-node
```

Edit `svelte.config.js`:

```js
import adapter from '@sveltejs/adapter-node';
import { vitePreprocess } from '@sveltejs/vite-plugin-svelte';

export default {
  preprocess: vitePreprocess(),
  kit: {
    adapter: adapter({ out: 'build' })
  }
};
```

Build ulang:

```bash
npm run build
```

### 4.4 Install production deps only

```bash
cd /home/mpsi.contoh.com/public_html
npm ci --omit=dev
```

### 4.5 Start Node server via PM2

```bash
cd /home/mpsi.contoh.com/public_html
PORT=3000 HOST=127.0.0.1 pm2 start build/index.js --name mpsi-frontend
pm2 save
pm2 startup
# Jalankan baris perintah yang muncul (untuk auto-start setelah reboot)
```

### 4.6 Reverse proxy: Nginx → Node 3000

CyberPanel pakai OpenLiteSpeed atau Apache. Untuk reverse proxy ke Node, edit vhost.

**Untuk OpenLiteSpeed** (default CyberPanel):

1. CyberPanel → **Websites → mpsi.contoh.com → vHost Conf**
2. Tambahkan context proxy:

```
context / {
  type                    proxy
  handler                 nodeJsProxy
  addDefaultCharset       off
}

extprocessor nodeJsProxy {
  type                    proxy
  address                 127.0.0.1:3000
  maxConns                100
  pcKeepAliveTimeout      60
  initTimeout             60
  retryTimeout            0
  respBuffer              0
}
```

3. **Save & Restart**

**Untuk Apache**, edit `.htaccess` di document root:

```apache
RewriteEngine On
RewriteRule ^(.*)$ http://127.0.0.1:3000/$1 [P,L]
ProxyPassReverse / http://127.0.0.1:3000/
```

Dan pastikan modul Apache `mod_proxy` + `mod_proxy_http` aktif.

### 4.7 Test frontend

Buka `https://mpsi.contoh.com`. Harus tampil landing page MPSI.

---

## 5. SSL (HTTPS)

CyberPanel sudah otomatis issue SSL Let's Encrypt saat create website kalau Anda centang. Verifikasi:

1. CyberPanel → **SSL → Manage SSL**
2. Pilih `api.mpsi.contoh.com` → **Issue SSL** kalau belum
3. Ulangi untuk `mpsi.contoh.com`

Sertifikat auto-renew 80 hari sebelum expired.

---

## 6. Konfigurasi CORS (Backend → Frontend cross-origin)

Edit `backend/config/cors.php`:

```php
return [
    'paths' => ['api/*', 'sanctum/csrf-cookie'],
    'allowed_methods' => ['*'],
    'allowed_origins' => [
        'https://mpsi.contoh.com',
        'http://localhost:5173', // untuk dev
    ],
    'allowed_origins_patterns' => [],
    'allowed_headers' => ['*'],
    'exposed_headers' => [],
    'max_age' => 0,
    'supports_credentials' => true,
];
```

Restart cache:

```bash
cd /home/api.mpsi.contoh.com/public_html
php artisan config:clear
php artisan config:cache
```

---

## 7. Konfigurasi Tripay Callback URL

Login dashboard Tripay → **Merchant → Konfigurasi**:

- Callback URL: `https://api.mpsi.contoh.com/api/tripay/callback`
- Return URL: `https://mpsi.contoh.com/orders` (atau order detail)

---

## 8. Cron Job Laravel

Tambah ke crontab:

```bash
crontab -e
```

Append:

```
* * * * * cd /home/api.mpsi.contoh.com/public_html && php artisan schedule:run >> /dev/null 2>&1
```

---

## 9. Maintenance & Update

### Update kode backend

```bash
cd /home/api.mpsi.contoh.com/public_html
git pull
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan config:cache
php artisan route:cache
```

### Update kode frontend

```bash
cd /home/mpsi.contoh.com/public_html
git pull
npm ci
npm run build
pm2 restart mpsi-frontend
```

### Cek log

- Laravel: `tail -f /home/api.mpsi.contoh.com/public_html/storage/logs/laravel.log`
- Node: `pm2 logs mpsi-frontend`
- OpenLiteSpeed: `tail -f /usr/local/lsws/logs/error.log`

---

## 10. Backup

### Database otomatis

CyberPanel → **Backup → Schedule Backup** → pilih daily ke local atau Google Drive/SFTP.

### Manual mysqldump

```bash
mysqldump -u mpsi_user -p mpsi > /root/backup/mpsi-$(date +%Y%m%d).sql
```

### File backup

```bash
tar czf /root/backup/mpsi-files-$(date +%Y%m%d).tar.gz \
  /home/api.mpsi.contoh.com/public_html \
  /home/mpsi.contoh.com/public_html
```

---

## 11. Hardening (Production checklist)

- [ ] `APP_DEBUG=false` di backend `.env`
- [ ] DB password generated, 24+ char
- [ ] Firewall: hanya port 22, 80, 443, 8090 (CyberPanel) terbuka
- [ ] Disable SSH password login, pakai SSH key
- [ ] Cron untuk SSL auto-renew (CyberPanel handle)
- [ ] PM2 startup script aktif (sudah dilakukan di 4.5)
- [ ] Brevo API key set di admin settings (atau .env)
- [ ] Tripay production mode + credentials valid
- [ ] CORS allowed_origins hanya domain frontend Anda
- [ ] Logo & branding sudah dicustom via admin Tampilan
- [ ] Test flow lengkap: register → verify email → buka toko → admin approve → order → payment → ship → done

---

## 12. Troubleshooting

| Masalah | Solusi |
|---|---|
| Frontend 502 Bad Gateway | PM2 stopped. `pm2 restart mpsi-frontend`. Cek log. |
| Frontend "PUBLIC_API_URL undefined" | Build ulang dengan `.env.production` benar. |
| CORS error di browser console | Tambah origin frontend ke `config/cors.php`, `config:cache`. |
| API 500 di production | `tail -f storage/logs/laravel.log`. Cek `.env` & permission `storage/`. |
| SSL not valid | Re-issue dari CyberPanel SSL menu. |
| Tripay callback gagal | Cek log Laravel, verifikasi signature di TripayService. |
| Email tidak terkirim | Cek Brevo API key di admin, dan log Laravel `Brevo:disabled` atau `Brevo:error`. |
| PM2 hilang setelah reboot | `pm2 startup` lalu jalankan command yang muncul, `pm2 save`. |
| Database error "table doesn't exist" | `php artisan migrate --force`. |

---

## 13. Performance Tuning

### Backend

```bash
# Composer dump-autoload optimized
composer dump-autoload --optimize --classmap-authoritative

# Octave/OpCache (php.ini)
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0  # set 0 di production
```

### Frontend

```bash
# Cluster mode untuk lebih banyak CPU
pm2 delete mpsi-frontend
PORT=3000 HOST=127.0.0.1 pm2 start build/index.js --name mpsi-frontend -i max
pm2 save
```

### MySQL

Di `/etc/mysql/mysql.conf.d/mysqld.cnf`:

```
innodb_buffer_pool_size = 1G  # sesuaikan dengan RAM VPS
max_connections = 200
```

Restart MySQL:
```bash
systemctl restart mysql
```

---

Selesai! Aplikasi MPSI Anda siap melayani pembeli & penjual di production. 🚀
