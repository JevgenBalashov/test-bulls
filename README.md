# Google Sheets to MySQL sync

Imports data from several Google Sheets files into MySQL tables once a day.

Every file may have its own column order and its own extra columns. Columns are
matched **by header name**, never by position, so adding a column to a sheet or
moving it around does not break the import. The whole mapping lives in the
database and is edited through the web interface.

- One tab can be written to several tables at the same time.
- Sheet headers and table columns can be named differently.
- When the mapping names a column the target table does not have, the importer
  runs `ALTER TABLE` and starts filling it on the same run.
- Rows are written with `upsert`, so re-running an import never creates
  duplicates.

Built with Laravel 12, MySQL 8, Inertia and Vue 3.

## Contents

- [Trying it out](#trying-it-out)
- [Requirements](#requirements)
- [Install on Ubuntu](#install-on-ubuntu)
- [Install for local development](#install-for-local-development)
- [Google API access](#google-api-access)
- [How the configuration works](#how-the-configuration-works)
- [Using the interface](#using-the-interface)
- [Console commands](#console-commands)
- [Scheduling and queue](#scheduling-and-queue)
- [How the table is extended](#how-the-table-is-extended)
- [Tests](#tests)

## Trying it out

Start to finish this takes about ten minutes, most of it waiting for Composer.

**1. Install the project**

```bash
git clone git@github.com:JevgenBalashov/test-bulls.git
cd test-bulls
composer install
npm install && npm run build

cp .env.example .env
php artisan key:generate
```

**2. Create the database**

```sql
CREATE DATABASE sheets_sync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Put your credentials in `.env` (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`),
then:

```bash
php artisan migrate --seed
```

The seeder creates the login and a ready-made configuration pointing at two
example spreadsheets that are shared for reading, so there is nothing to fill in
by hand.

**3. Add a Google key**

The importer needs a service account of your own. It takes a few minutes and is
described step by step in [Google API access](#google-api-access). The short
version: create a project, enable the Sheets API, create a service account,
download its JSON key and save it as
`storage/app/google/credentials.json`.

**4. Run the import**

```bash
php artisan sheets:import --sync
```

Expected output:

```
Sales Report EU .................. success, 7 rows written
Sales Report US .................. partial, 3 rows written
```

`partial` is intentional: one row in the second sheet holds `n/a` where a price
is expected. That row is skipped and the reason is recorded, while the rest of
the sheet is imported.

**5. Open the interface**

```bash
php artisan serve
```

Go to http://127.0.0.1:8000 and sign in with `admin@example.com` / `password`.

### What to look at

| Requirement | Where to see it |
| --- | --- |
| Different column order and extra columns | The two example sheets use different header names and order, and both land in `sales`. Compare them on the source screens. |
| List of files, tabs and columns is configurable | **Sources** screen, and the detail screen behind any source. Everything is editable there. |
| Matching between file and table | **Source detail**, the column table under each target table. |
| One file into several tables | Source **Sales Report EU**: its single tab feeds both `sales` and `products`. |
| New column triggers `ALTER TABLE` | Add a column to a mapping and it is marked **will be added**. Run the import and it appears in the database, listed in the run journal. |
| Daily automation | `php artisan schedule:list` shows `0 3 * * *`. |
| Import history | **Import runs** screen, with rows read, written and skipped per table. |

A quick way to see the `ALTER TABLE` behaviour: on **Sales Report EU** add the
column `region` of type `string` to the `sales` mapping, press **Import now**,
and watch it show up both in the journal and in the table.

To follow the raw data instead:

```bash
php artisan sheets:read <spreadsheet-id> 'Daily Sales'
```

prints the sheet exactly the way the importer sees it.

```bash
php artisan test
```

runs the suite, which needs neither MySQL nor Google access.

## Requirements

- PHP 8.2 or newer with `mysql`, `mbstring`, `xml`, `curl`, `zip`, `bcmath`, `intl`
- MySQL 8 or newer
- Node 20 or newer
- Composer 2

## Install on Ubuntu

The repository ships a provisioning script for a fresh server. It installs PHP,
MySQL, Node, Composer and nginx, creates the database, builds the assets and
registers both the queue worker and the scheduler.

```bash
git clone git@github.com:JevgenBalashov/test-bulls.git /var/www/sheets-sync
cd /var/www/sheets-sync
sudo bash scripts/setup.sh
```

The script is safe to run again on an already configured server.

Everything is overridable through environment variables:

```bash
sudo APP_URL=https://sheets.example.com \
     SERVER_NAME=sheets.example.com \
     DB_PASSWORD=choose-your-own \
     bash scripts/setup.sh
```

| Variable | Default | Meaning |
| --- | --- | --- |
| `APP_DIR` | directory of the repository | Where the application lives |
| `APP_USER` | `www-data` | User that owns the files and runs the worker |
| `APP_URL` | `http://localhost` | Value written to `.env` |
| `SERVER_NAME` | `localhost` | `server_name` of the nginx site |
| `DB_DATABASE` | `sheets_sync` | Database name |
| `DB_USERNAME` | `sheets_sync` | Database user |
| `DB_PASSWORD` | generated | Printed once at the end if generated |
| `PHP_VERSION` | `8.3` | Installed from the `ondrej/php` repository |
| `NODE_MAJOR` | `20` | Installed from NodeSource |
| `INSTALL_NGINX` | `yes` | Set to `no` to keep your own web server |
| `SCHEDULE_TIME` | `* * * * *` | Cron expression for `schedule:run` |
| `SITE_NAME` | `sheets-sync` | Names the nginx site, the systemd unit and the cron file |
| `CACHE_DIR` | `/var/cache/sheets-sync` | Composer and npm cache used during the install |

When it finishes the application is reachable, and the only thing left is the
Google service account key described below.

Sign in with `admin@example.com` / `password` and change the password right away.

## Install for local development

```bash
git clone git@github.com:JevgenBalashov/test-bulls.git
cd test-bulls

composer install
npm install

cp .env.example .env
php artisan key:generate
```

Create the database and point `.env` at it:

```sql
CREATE DATABASE sheets_sync CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sheets_sync
DB_USERNAME=root
DB_PASSWORD=
```

Then migrate and start the dev servers:

```bash
php artisan migrate --seed
npm run dev
php artisan serve
```

`--seed` creates the admin user and an example configuration with two sources,
so the interface is not empty on the first visit. The example sources carry
placeholder spreadsheet ids; replace them with your own before importing.

A queue worker is needed for imports triggered from the interface:

```bash
php artisan queue:work
```

## Google API access

The importer authenticates as a **service account**. There is no interactive
consent screen, which is what makes an unattended daily import possible.

### 1. Create a project

Open the [Google Cloud console](https://console.cloud.google.com/), click the
project selector in the top bar and create a new project.

### 2. Enable the Sheets API

Go to **APIs & Services -> Library**, search for **Google Sheets API** and press
**Enable**. Without this step every request fails with `403 SERVICE_DISABLED`.

### 3. Create the service account

Go to **APIs & Services -> Credentials -> Create credentials -> Service
account**. Give it a name such as `sheets-importer` and confirm. No project
roles are required: the account reads documents that are shared with it
directly, not through project permissions.

### 4. Download the key

Open the service account, switch to the **Keys** tab and choose
**Add key -> Create new key -> JSON**. The file downloads once and cannot be
downloaded again.

Put it in the application:

```bash
mkdir -p storage/app/google
cp ~/Downloads/your-project-0a1b2c3d4e5f.json storage/app/google/credentials.json
chmod 640 storage/app/google/credentials.json
```

On a server also hand it to the application user:

```bash
sudo chown www-data:www-data storage/app/google/credentials.json
```

The path is configurable, and the directory is ignored by git, so the key can
never be committed by accident:

```dotenv
GOOGLE_CREDENTIALS_PATH=storage/app/google/credentials.json
```

### 5. Share every spreadsheet with the service account

This is the step that is easiest to miss. Open the JSON key and copy the
`client_email` value, which looks like:

```
sheets-importer@your-project.iam.gserviceaccount.com
```

Open each spreadsheet, press **Share**, paste that address and give it
**Viewer** access. A document that is not shared returns `404` even though the
key is valid.

### 6. Find the spreadsheet id

It is the part of the document url between `/d/` and `/edit`:

```
https://docs.google.com/spreadsheets/d/1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms/edit
                                        ^------------- spreadsheet id -------------^
```

### 7. Check the connection

```bash
php artisan sheets:read 1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms 'Daily Sales'
```

The command prints the detected headers and the first rows exactly the way the
importer sees them. It is the fastest way to tell a sharing problem from a
mapping problem.

The application only ever requests the `spreadsheets.readonly` scope, so it
cannot modify your documents.

## How the configuration works

Four tables describe what to import and where to put it:

```
sheet_sources            one Google Sheets file
  |
  +- sheet_tabs          one tab inside that file
       |
       +- sheet_mappings            one target database table
            |
            +- sheet_mapping_columns     sheet header -> table column
```

A tab may have several mappings, which is how one file is written into several
tables with different structures.

`sheet_mapping_columns` holds the actual matching:

| Field | Meaning |
| --- | --- |
| `source_column` | Header text in the sheet |
| `target_column` | Column name in the database table |
| `data_type` | `string`, `text`, `integer`, `decimal`, `boolean`, `date`, `datetime` |
| `is_nullable` | Used when the table is created |
| `is_unique_key` | Part of the key that `upsert` matches rows on |

The example from the task is expressed like this: a sheet with
`[date, product_name, price, amount]` is written to `sales` as
`date -> date`, `product_name -> name`, `price -> product_price`,
`amount -> amount`, while the same tab also feeds `products` with
`product_name -> name` and `price -> last_price`.

Values are normalised on the way in, so `1 234,56`, `1,234.56` and `49.90 EUR`
all become proper decimals. A cell that cannot be converted does not abort the
run: the row is skipped, counted, and the reason is written to the import
journal.

## Using the interface

**Sources** lists the spreadsheets. From here you add a file, pause it, or start
an import by hand.

**Source detail** is where the mapping is built: tabs, the target tables of each
tab, and the columns of each table.

The **Load headers from Google** button reads the real header row of the tab and
shows it as chips. Clicking a chip fills the sheet header field, which removes
the usual typing mistakes.

Columns that the target table does not have yet are marked **will be added**,
and tables that do not exist yet are marked **table not created yet**. Both
disappear after the next import creates them.

**Import runs** is the journal. Each run lists its mappings with the number of
rows read, written and skipped, the columns that were added by `ALTER TABLE`,
and the error message when something failed.

## Console commands

Preview a tab without importing:

```bash
php artisan sheets:read <spreadsheet-id> <tab> [--header=1] [--start=2] [--limit=10]
```

Import every active source. By default the work is pushed to the queue:

```bash
php artisan sheets:import
```

Import immediately in the current process, which is the useful mode while
setting a mapping up:

```bash
php artisan sheets:import --sync
```

Limit the run to one source, by database id or by spreadsheet id:

```bash
php artisan sheets:import --sync --source=1
php artisan sheets:import --sync --source=1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms
```

## Scheduling and queue

`sheets:import` is scheduled daily at 03:00 and cannot overlap itself:

```php
Schedule::command('sheets:import')
    ->dailyAt('03:00')
    ->withoutOverlapping();
```

The scheduler only queues the jobs, so the cron entry returns immediately. The
actual reading and writing happens in the queue worker, which `scripts/setup.sh`
installs as `sheets-sync-worker`:

```bash
sudo systemctl status sheets-sync-worker
sudo journalctl -u sheets-sync-worker -f
```

The cron entry that drives the scheduler lives in `/etc/cron.d/sheets-sync`.

If a worker is killed mid-run, the job marks the unfinished run as failed, so
nothing stays stuck in the journal.

## How the table is extended

Before writing, the importer compares the configured columns with the real
table:

- the table does not exist yet, so it is created with the mapped columns plus
  `id`, `imported_at` and timestamps;
- the table exists but lacks a column, so `ALTER TABLE ... ADD COLUMN` is run
  and the column is filled on the same pass;
- a unique index is created for the columns marked as keys, which is what makes
  `upsert` idempotent.

Columns are never dropped or retyped, so existing data cannot be lost by editing
the configuration.

Columns added to a table that already has rows are always created nullable,
because the rows that are already stored have nothing to put there. The
`is_nullable` flag is therefore only applied when the table is created.

Table and column names are validated against `^[a-zA-Z_][a-zA-Z0-9_]*$` before
they reach the schema builder, and the application's own tables are rejected as
targets, so a mapping cannot be pointed at `users` or `migrations`.

## Tests

```bash
php artisan test
```

The suite runs on an in-memory SQLite database and needs no Google access: the
Sheets client is replaced with a stub, so the whole import pipeline is covered
without network calls.
