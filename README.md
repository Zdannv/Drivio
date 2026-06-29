<p align="center"><a href="#" target="_blank"><img src="./public/icon_kndi.svg" width="400" alt="Laravel Logo"></a></p>

## About Project and task management system

## Start running on local using docker & sail

```bash
cp .env.example .env
docker run --rm \
  -u "$(id -u):$(id -g)" \
  -v "$(pwd):/var/www/html" \
  -w /var/www/html \
  laravelsail/php83-composer:latest \
  composer install --ignore-platform-reqs

## Sebelum menjalankan perintah-perintah di bawah, sangat disarankan untuk membuat alias agar Anda cukup mengetikkan `sail` (sebagai ganti dari `./vendor/bin/sail`). Jalankan perintah ini di terminal Anda:

```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'

sail up -d  
sail npm i
sail artisan key:generate
sail artisan migrate
sail artisan db:seed
sail npm run dev
```
# Start Reverb websocket server
./vendor/bin/sail artisan reverb:start
