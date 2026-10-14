## Digital Boarding Pass System

A Laravel 13 regional-airport boarding pass system with session authentication and role-based access for passengers, airport staff, and administrators. The current modules provide administrator flight and booking management, passenger flight search and booking, transactional seat inventory, and passenger booking cancellation. Check-in and boarding passes are not included yet.

## Requirements

- PHP 8.3 or newer with `pdo_mysql`, `pdo_sqlite`, `sqlite3`, `mbstring`, `openssl`, and `fileinfo`
- Composer 2
- MySQL 8 or compatible MariaDB
- Node.js 20 or newer and npm

## Installation

1. Install PHP and JavaScript dependencies:

   ```bash
   composer install
   npm install
   ```

2. Create a MySQL database and a dedicated application user (replace the sample password):

   ```sql
   CREATE DATABASE digital_boarding_pass CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   CREATE USER 'boarding_app'@'127.0.0.1' IDENTIFIED BY 'replace-with-a-strong-password';
   GRANT ALL PRIVILEGES ON digital_boarding_pass.* TO 'boarding_app'@'127.0.0.1';
   FLUSH PRIVILEGES;
   ```

3. Configure the local environment and generate the encryption key:

   ```bash
   cp .env.example .env
   php artisan key:generate
   ```

   Set `DB_USERNAME=boarding_app` and `DB_PASSWORD` in `.env` to the MySQL account you created. The default database name is `digital_boarding_pass`.

4. Create the tables and seed a local administrator:

   ```bash
   php artisan migrate --seed
   ```

   All three local test accounts use password `password`:

   | Role | Email |
   | --- | --- |
   | Passenger | `passenger@example.com` |
   | Airport staff | `staff@example.com` |
   | Administrator | `admin@example.com` |

   These are development-only credentials. Change or remove them before deploying the application. Public registration always creates an active passenger; staff and administrator access must be provisioned separately.

5. Build frontend assets:

   ```bash
   npm run build
   ```

## Development

After configuring MySQL credentials in `.env`, start the Laravel server:

```bash
php artisan serve
```

Open <http://127.0.0.1:8000>. During frontend development, run Vite in another terminal:

```bash
npm run dev
```

Run the test suite (feature tests use in-memory SQLite):

```bash
php artisan test
```

In a local setup without MySQL credentials, the seeded SQLite database can be used directly:

```bash
touch database/database.sqlite
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan migrate --seed
DB_CONNECTION=sqlite DB_DATABASE=database/database.sqlite php artisan serve
```

Password reset emails use Laravel's log mailer by default. In local development, open `storage/logs/laravel.log` to retrieve the reset link.

Laravel's `web` middleware provides sessions and CSRF protection. Registration, login, password reset, flight creation/search, and booking creation validate requests server-side; passwords are hashed by the `User` model. Login is rate-limited. The `passenger`, `staff`, and `admin` middleware protect `/dashboard`, `/flights` and `/bookings`, `/staff`, and `/admin` respectively; accounts with inactive status are denied access. Administrators manage flights at `/admin/flights` and bookings at `/admin/bookings`; passengers search available flights at `/flights` and manage their bookings at `/bookings`. Booking creation and cancellation lock seat inventory in database transactions.