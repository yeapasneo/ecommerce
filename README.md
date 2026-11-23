# TechNest: computer and gadget e-commerce store

A full e-commerce store for computers, components and gadgets, with a complete admin panel. It is built as one app with **Laravel 13 + Inertia.js + React 19 + PostgreSQL** and styled with Tailwind CSS v4.

## Features

### Storefront
- **Home page:** sliders, side banners, featured categories, featured products, new arrivals and brands.
- **Mega menu:** nested categories up to three levels, plus a mobile drawer menu.
- **Category, brand and search listings** with filters:
  - price range, availability and brand;
  - per-category **specification filters** (for example RAM, Socket, Display Size);
  - sorting and page size.
- **Search suggestions** as you type in the header.
- **Product page:**
  - image gallery, key features, price and discount, stock status, product code, brand and warranty;
  - tabs for **specifications, description, questions and reviews**;
  - related products.
- **Compare:** up to 3 products side by side, specification by specification.
- **Cart:** guest or logged-in. A guest cart is merged into the account on login or registration.
- **Coupons:** fixed or percentage, with minimum order, maximum discount, usage limit and validity dates.
- **Checkout:**
  - customer information, payment method (Cash on Delivery, bKash, card) and delivery method (home, express, store pickup);
  - delivery charge worked out live: inside or outside Dhaka, express delivery, and a free-delivery threshold;
  - stock is checked and reduced inside a database transaction.
- **PC Builder:** pick a CPU, motherboard, RAM, storage, GPU, PSU, casing, cooler and peripherals. The total is shown as you go, required parts are enforced, the whole build can be added to the cart, builds can be saved to the account or printed.
- **Order tracking** by order number and phone, with a status timeline.
- **Customer account:**
  - dashboard, order history and details, cancelling a pending order (its stock is returned);
  - wishlist, saved PC builds, profile and password change.
- **Other pages:** offers, brands, contact form, newsletter sign-up, and pages such as About and Terms.
- **Authentication:** register, login and forgot/reset password.

### Admin panel (`/admin`)
- **Dashboard:** revenue, orders, products and customers; a 30-day revenue chart; orders by status; recent orders; top sellers; low stock.
- **Products:** full create, edit and delete:
  - main image and gallery uploads;
  - key features, grouped specifications, SEO fields, stock and stock status, featured and visible flags;
  - search and filters.
- **Categories** (nested, filter attributes, featured, sort order) and **Brands** (logo).
- **Orders:**
  - filter by status, payment status, date or search;
  - update status and payment status with comments;
  - automatic restock on cancel, history timeline, printable invoice.
- **Customers:** order count and total spent, block or unblock, grant or remove admin.
- **Coupons, Banners and Sliders, Offers, Pages, PC Builder parts.**
- **Moderation:** approve reviews, answer product questions, read contact messages and see newsletter subscribers.
- **Settings:** store name, contact details, delivery charges, notice and social links.

## Requirements
- PHP 8.3+ with `pdo_pgsql`
- Composer
- Node 20+
- PostgreSQL 14+

## Setup

```bash
git clone https://github.com/yeapasneo/ecommerce.git
cd ecommerce
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Edit `.env` and set your PostgreSQL credentials (`DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`). Then run:

```bash
createdb ecommerce            # or create the database any way you like
php artisan migrate --seed    # creates tables plus demo catalog, orders and content
php artisan storage:link
npm run build                 # or `npm run dev` while developing
php artisan serve
```

Open http://localhost:8000.

### Demo accounts (created by the seeder)

| Role     | Email                    | Password   |
|----------|--------------------------|------------|
| Admin    | admin@technest.test      | `password` |
| Customer | customer@technest.test   | `password` |

Change these passwords, or remove these accounts, before going live.

### Demo data
The seeder creates about 110 products in about 45 categories and 30 brands, with specifications. It also creates generated SVG product and banner images, two coupons (`WELCOME500` and `SAVE5`), offers, pages, reviews, questions and sample orders.

## Imported catalog

`php artisan db:seed` loads `database/data/catalog.json.gz` when it exists (about 16,700 real products, 298 categories and 504 brands). Without that file it falls back to a small demo catalog. A full fresh seed takes about 80 seconds.

The file is built by a Python crawler that collects only facts: names, product codes, prices, stock status, short key features, spec tables, categories and brands. It does not copy product photos or description text. Every product gets a generated placeholder image instead; upload real images from the admin panel.

To refresh or finish the crawl (it resumes from `scripts/crawler/.cache`):

```bash
pip install requests beautifulsoup4
python3 scripts/crawler/crawl.py --out database/data/catalog.json.gz
php artisan migrate:fresh --seed
```

Use `--no-fetch` to rebuild the file from the cache without network requests, and `--limit N` for a quick partial crawl.

## Tests

The feature tests run against a PostgreSQL database named `ecommerce_test` (see `phpunit.xml`):

```bash
createdb ecommerce_test
php artisan test
```

## Notes
- The bKash and card payment options record the order as unpaid. No payment gateway is connected yet; staff mark the order paid in the admin panel. A gateway (for example SSLCommerz or the bKash API) would plug into `CheckoutController@store`.
- Password reset emails use the mail driver in `.env` (`log` by default).
- Product descriptions and pages accept HTML from admins.

## Project structure
- `app/Http/Controllers`: storefront controllers.
- `app/Http/Controllers/Admin`: admin panel controllers.
- `app/Services`: `CartService`, `ProductListing` (filters and facets) and `OrderService` (status changes and restocking).
- `resources/js/Pages`: Inertia React pages. `Admin/*` holds the admin panel.
- `resources/js/Components`: shared UI, including the generic `CrudPage` used by the simple admin resources.
- `database/seeders`: the demo catalog and content.
