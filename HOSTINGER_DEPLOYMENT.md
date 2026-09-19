# CrisisPulse AI on Hostinger

This project is a Laravel 13 application. Deploy it with PHP 8.3 or newer, MySQL, Composer 2, and HTTPS enabled.

## 1. Create hosting resources

In hPanel:

1. Add the domain or subdomain.
2. Create a MySQL database and database user.
3. Enable PHP 8.3 or newer and enable the required extensions: `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`, `tokenizer`, `xml`, `ctype`, `json`, and `bcmath`.
4. Enable SSL and force HTTPS.

Hostinger normally uses `public_html` as the website root. The safest arrangement is to keep the Laravel project outside the public web directory and point the domain document root to the project’s `public` directory. For example:

```text
/home/USERNAME/domains/example.com/crisispulse-ai/       Laravel project
/home/USERNAME/domains/example.com/crisispulse-ai/public  Website document root
```

If your plan does not allow changing the document root, use Hostinger’s Laravel public-directory deployment method and follow the fallback instructions in section 6.

## 2. Upload or clone the project

Recommended SSH deployment:

```bash
cd /home/USERNAME/domains/example.com
git clone https://github.com/Nicanderrr/CrisisPulse.git crisispulse-ai
cd crisispulse-ai
composer2 install --no-dev --prefer-dist --optimize-autoloader
```

Do not upload `.env`, `vendor`, `node_modules`, or local storage contents from the development computer. The repository intentionally excludes these files.

## 3. Build the frontend before uploading, or on the server

The production build can be created locally and committed/uploaded as `public/build` is ignored by Git. If Node.js is available on Hostinger:

```bash
npm ci
npm run build
```

If Node.js is not available, run `npm ci && npm run build` locally, then upload the generated `public/build` directory after the Git checkout. Do not run the development Vite server on Hostinger.

## 4. Configure production `.env`

```bash
cp .env.hostinger.example .env
php artisan key:generate --force
```

Edit `.env` and set:

- `APP_URL` to the real HTTPS domain.
- `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` to the Hostinger MySQL values.
- `OPENAI_API_KEY` to the production OpenAI key.
- `APP_DEBUG=false`.
- `FILESYSTEM_DISK=public`.

Never commit `.env` or expose the OpenAI key in frontend JavaScript.

## 5. Initialize Laravel

Run these commands from the Laravel project root:

```bash
php artisan migrate --force
php artisan db:seed --force
php artisan storage:link
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
chmod -R ug+rwx storage bootstrap/cache
```

The seed creates the initial accounts:

```text
System Admin: admin@gmail.com / password
Staff:        staff@gmail.com / password
```

Change these passwords immediately after the first production login. The uploaded logo, login media, and message evidence are stored on the public disk and require the `storage:link` command.

## 6. Fallback when the domain must use `public_html`

Keep the complete Laravel project in a directory outside the public root, then place only the contents of the project’s `public` directory in `public_html`. Update the copied `public_html/index.php` paths so they point to the real project directory, for example:

```php
require __DIR__.'/../crisispulse-ai/vendor/autoload.php';
$app = require_once __DIR__.'/../crisispulse-ai/bootstrap/app.php';
```

Copy the project’s `public/.htaccess` into `public_html`. Also create the storage link so `public_html/storage` points to the project’s `storage/app/public` directory. Keep `.env`, `app`, `bootstrap`, `config`, `database`, `resources`, `routes`, `storage`, and `vendor` outside `public_html`.

## 7. Verify the deployment

Open these URLs:

```text
https://your-domain.example/up
https://your-domain.example/login
```

Then verify:

1. Login with the seeded admin account.
2. Change the admin password or create a new admin account.
3. Upload a logo from Admin > Brand Settings.
4. Upload login media and verify the login page preview.
5. Create a text analysis and an image/video analysis.
6. Start the Realtime assistant and allow microphone access.

If the application returns a 500 error, inspect `storage/logs/laravel.log` and confirm that `APP_DEBUG=false` remains enabled in production.

## 8. Optional cron job

This application does not currently require a queue worker or scheduled task for its main workflow. If scheduled Laravel tasks are added later, create a Hostinger cron job that runs every minute:

```bash
/usr/bin/php /home/USERNAME/domains/example.com/crisispulse-ai/artisan schedule:run
```

## 9. Updating after a GitHub push

From the project directory:

```bash
git pull origin main
composer2 install --no-dev --prefer-dist --optimize-autoloader
php artisan migrate --force
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

Build and upload `public/build` whenever frontend files change.
