# Contabo 서버 배포 가이드 (Laravel + MySQL)

Ubuntu 22.04 LTS 기준으로 Contabo VPS에 GS Brochure(Laravel 12)를 MySQL과 함께 배포하는 단계입니다.

## 전제 조건

- Contabo VPS (Ubuntu 22.04 LTS 권장)
- 도메인 또는 서버 공인 IP
- SSH 접속 가능

---

## 1. 서버 초기 설정

```bash
sudo apt update && sudo apt upgrade -y
```

**PHP 8.2+**

```bash
sudo apt install -y software-properties-common
sudo add-apt-repository -y ppa:ondrej/php
sudo apt update
sudo apt install -y php8.2-fpm php8.2-mysql php8.2-mbstring php8.2-xml php8.2-curl php8.2-zip php8.2-bcmath
```

**MySQL 8.x**

```bash
sudo apt install -y mysql-server
sudo mysql_secure_installation
```

**Nginx**

```bash
sudo apt install -y nginx
```

**Composer (전역)**

```bash
curl -sS https://getcomposer.org/installer | php
sudo mv composer.phar /usr/local/bin/composer
```

---

## 2. MySQL 데이터베이스 준비

```bash
sudo mysql -u root -p
```

MySQL 셸에서:

```sql
CREATE DATABASE brochure_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'brochure_user'@'localhost' IDENTIFIED BY 'your_strong_password';
GRANT ALL PRIVILEGES ON brochure_db.* TO 'brochure_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

---

## 3. 소스 배포

저장소 루트를 `/var/www/gs-brochure`로 클론한 경우, Laravel 앱은 `laravel/` 폴더에 있습니다. 웹 루트는 **반드시** `laravel/public`로 설정합니다.

```bash
sudo mkdir -p /var/www/gs-brochure
cd /var/www/gs-brochure
sudo git clone https://github.com/Coding0508s/GSBrochure.git .
```

Document root 경로: `/var/www/gs-brochure/laravel/public`

---

## 4. Laravel 환경 설정

```bash
cd /var/www/gs-brochure/laravel
cp .env.example .env
```

Nginx 설정 예시는 `deployment/nginx.conf.example`를 참고하세요.

`.env` 수정 (최소):

- `APP_NAME="GS Brochure"`
- `APP_ENV=production`
- `APP_DEBUG=false`
- `APP_URL=https://yourdomain.com` (또는 `http://서버IP`)
- DB 블록 주석 해제 후 MySQL 정보 입력:
  - `DB_CONNECTION=mysql`
  - `DB_DATABASE=brochure_db`
  - `DB_USERNAME=brochure_user`
  - `DB_PASSWORD=위에서_설정한_비밀번호`
- (선택) `TEAMS_WEBHOOK_URL=...` Teams 알림 사용 시

```bash
php artisan key:generate
composer install --no-dev --optimize-autoloader
```

또는 4~5단계를 한 번에 실행하려면 (`.env` 및 `key:generate` 완료 후):

```bash
cd /var/www/gs-brochure/laravel
chmod +x deployment/deploy.sh
./deployment/deploy.sh
```

(선택) 프론트 빌드: `npm ci && npm run build` — 메인 화면은 CDN + public/js 사용으로 생략 가능.

---

## 5. 마이그레이션 및 저장소

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
```

권한 (Nginx/PHP-FPM 사용자 `www-data` 기준):

```bash
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache
```

---

## 6. Nginx 설정

사이트 설정 파일 예: `/etc/nginx/sites-available/gs-brochure`

```nginx
server {
    listen 80;
    server_name yourdomain.com;
    root /var/www/gs-brochure/laravel/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
        fastcgi_hide_header X-Powered-By;
    }
}
```

활성화 및 재시작:

```bash
sudo ln -s /etc/nginx/sites-available/gs-brochure /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

HTTPS: Let's Encrypt 사용 시 `certbot` 설치 후 `sudo certbot --nginx -d yourdomain.com` 실행. 이후 `.env`의 `APP_URL`을 `https://yourdomain.com`으로 변경.

---

## 7. 스케줄러(Cron)

Laravel 스케줄러로 매일 `completed-requests:clean`이 실행됩니다. Crontab에 추가:

```bash
sudo crontab -u www-data -e
```

한 줄 추가:

```
* * * * * cd /var/www/gs-brochure/laravel && php artisan schedule:run >> /dev/null 2>&1
```

---

## 8. (선택) Queue Worker

`QUEUE_CONNECTION=database`이므로 비동기 작업 사용 시 프로덕션에서 queue worker 실행 권장. systemd 서비스 또는 supervisor로 `php artisan queue:work --tries=3` 상시 실행.

---

## 9. 배포 후 점검

- 브라우저에서 `APP_URL` 접속 → 메인/로그인/브로셔 신청 동작 확인
- 관리자 로그인 → 대시보드, 재고, 입출고, 운송장 입력 확인
- Teams 알림 사용 시 신청/운송장 등록 시 수신 확인
- `storage/logs/laravel.log` 에러 확인

---

## 요약 순서

| 순서 | 작업 |
|------|------|
| 1 | 서버: PHP 8.2+, MySQL, Nginx, Composer 설치 |
| 2 | MySQL: DB·사용자 생성 및 권한 부여 |
| 3 | Git 클론, document root = `laravel/public` |
| 4 | .env 생성 및 MySQL·APP_*·TEAMS 설정, key:generate |
| 5 | composer install --no-dev, migrate --force, storage:link, 권한 |
| 6 | Nginx virtual host 설정, SSL(선택) |
| 7 | Cron에 schedule:run 등록 |
| 8 | (선택) Queue worker, npm build |
