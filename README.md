# MUZORA.PK

MUZORA.PK is a Laravel 12 / PHP storefront for catalog browsing, customer accounts, carts, wishlists, checkout, manual payment review, coupons, reviews, and store settings. It uses Blade, Vite, Tailwind CSS 4, and a MySQL-compatible database. The existing storefront and admin presentation are retained.

## Requirements

- PHP 8.2 or later, with the PHP extensions required by Laravel and PDO MySQL enabled
- Composer 2
- MySQL 8 or a compatible MySQL/MariaDB server
- Node.js and npm (for building the frontend assets)
- For running the automated test suite: PDO SQLite / SQLite support

The checked-in `composer.lock` and `package-lock.json` pin dependency versions. PHP, Composer, and the Laravel runtime have not been verified in every environment; check platform requirements after installing PHP and Composer.

## Fresh local installation

### Windows (PowerShell)

From the project directory:

```powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
npm ci
```

Create a MySQL database and a least-privilege database user (for example, using MySQL Workbench). Set `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, and `DB_PASSWORD` in `.env`. The example values target a local MySQL server and database named `muzora`; change them to match your machine. Keep `.env` private and do not commit it.

Then run:

```powershell
php artisan migrate
php artisan storage:link
php artisan admin:create
npm run build
php artisan serve
```

Open <http://127.0.0.1:8000>. `admin:create` securely prompts for the administrator's name, email, and confirmed password; it enforces a 12-character mixed-case, numeric, and symbol password. There is no public admin registration.

### macOS / Linux / other PHP environments

```bash
composer install
cp .env.example .env
php artisan key:generate
```

Create the MySQL database/user, set the `DB_*` values in `.env`, then run:

```bash
npm ci
php artisan migrate
php artisan storage:link
php artisan admin:create
npm run build
php artisan serve
```

Open <http://127.0.0.1:8000>. To use Vite's hot-reload development server, run `npm run dev` in another terminal. For a LAN or hosted preview, configure the dev server's host/origin as appropriate for that environment.

## Initial store configuration

1. Sign in at `/admin/login` using the account created with `php artisan admin:create`.
2. Configure the store's general information and shipping fee, free-shipping threshold, and minimum order amount under **Admin → Settings**. Money inputs are PKR; internal totals use integer paisa.
3. Create and activate the required manual payment methods under **Admin → Payment methods**. The supported methods are Bank Alfalah, Meezan Bank, EasyPaisa, and JazzCash. There is no online payment gateway. Enter genuine account/payment instructions only in the protected application database/admin interface, not in source code, `.env.example`, or Git.
4. Configure coupons and review the moderation queue in the admin area. Customer reviews are tied to eligible orders and require moderation before publication.
5. Configure `MAIL_*` in `.env` or the production environment if customer password-reset emails should be delivered. The example mailer writes to the application log and is not a production mail service.

Shipping fallback values in `.env.example` are `SHIPPING_STANDARD_FEE_MINOR=25000` and `SHIPPING_FREE_THRESHOLD_MINOR=299900` (PKR 250.00 and PKR 2,999.00). Set and verify real business values before launch; the admin shipping settings are stored in the database after they are saved.

**Fresh database limitation:** migrations and the database seeder do not create product, category, brand, image, or payment-method records. The admin catalog pages currently provide read-only product/category/brand listings; this repository does not include catalog creation or product-image upload screens. Populate and verify the catalog through an approved provisioning process before opening the storefront to customers. Payment methods must be configured by an administrator before checkout can be used.

## Tests

The PHPUnit configuration uses an in-memory SQLite database. With Composer dependencies and the required PHP SQLite extension installed, run:

```bash
php artisan test
```

This command has not been run as part of documenting setup unless separately reported by the audit; successful dependency installation and a passing test run should be confirmed in the target environment.

## Production deployment

- Deploy the Laravel project with the web server's **document root set to the project's `public/` directory**. Do not expose the repository root, `.env`, `vendor`, or private storage to the web.
- Use HTTPS and set `APP_ENV=production`, `APP_DEBUG=false`, the correct HTTPS `APP_URL`, and a unique `APP_KEY`. Generate the key once with `php artisan key:generate` in the deployment environment, store it securely, and do not rotate it casually.
- Set production database credentials, `FILESYSTEM_DISK`, shipping defaults, and any `MAIL_*` values using the hosting environment/secret manager. Do not use the example `root` database account or empty password in production.
- Install production PHP dependencies and build frontend assets during deployment/build preparation:

  ```bash
  composer install --no-dev --optimize-autoloader
  npm ci
  npm run build
  ```

- Apply schema changes with `php artisan migrate --force`, create an administrator with `php artisan admin:create`, and create the public storage link with `php artisan storage:link` where supported by the host. Confirm `public/build` is present in the deployed release.
- Give the PHP/web-server user write access only where required, notably `storage/` and `bootstrap/cache/`. Ensure payment-proof files remain on the private filesystem disk and are never exposed through a public web path. Back up the database and private proof storage using access-controlled backups.
- After deploy, verify HTTPS, login, checkout totals, payment-proof upload/download authorization, mail delivery (if enabled), and database/storage backups in the actual hosting environment.

## Implemented flows and current limits

- Server-rendered home, shop, and product pages with database-backed catalog queries, search/filter/sort, and pagination.
- Separate customer and administrator authentication; admin routes require an authenticated active administrator.
- Database-backed guest/customer carts and customer wishlists; authenticated checkout recalculates prices, stock, discounts, and shipping server-side and records order snapshots transactionally.
- Four manual payment methods only. Payment-proof uploads are limited to JPEG/PNG/WebP images up to 5 MB, use generated storage paths on a private disk, and are downloaded through customer ownership or active-admin-authorized routes.
- Coupon validation and usage recording, order-linked customer reviews with admin moderation, and database-backed general/shipping settings are implemented in the application. These behaviors are covered by feature tests, but a test suite passing is not claimed until it has been executed.
- No online gateway, courier integration, or catalog/product-image management UI is included. No sample products, credentials, payment details, or demo admin account are seeded.
