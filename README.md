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

`php artisan db:seed` loads `database/data/catalog.json.gz` when it exists (the full reference catalog: categories, brands, products, key features and spec tables). Without that file it falls back to a small demo catalog.

- **Product photos** are in `public/images/products/<slug>.webp` (500×500 WebP), downloaded by the crawler from the reference store. They belong to that store or the brands, so get permission before using them on a public site.
- **Brand logos** are in `public/images/brands/<key>.svg`. The reference store publishes no brand logos, so `scripts/crawler/brand_logos.py` uses [Simple Icons](https://simpleicons.org) (CC0) for the brands it covers and draws a text wordmark for the rest.
- A product or brand without a file gets a generated placeholder. Upload real images any time from the admin panel.

To refresh the crawl (it resumes from `scripts/crawler/.cache`):

```bash
pip install requests beautifulsoup4 pillow
python3 scripts/crawler/crawl.py --refresh-listing --images public/images/products --out database/data/catalog.json.gz
npm pack simple-icons && tar xzf simple-icons-*.tgz
python3 scripts/crawler/brand_logos.py --icons package --out public/images/brands
php artisan migrate:fresh --seed
```

Use `--no-fetch` to skip fetching product pages and `--limit N` for a quick partial crawl.

## SEO

- Every storefront page sends its title, description, canonical URL, robots, Open Graph / Twitter tags and JSON-LD in the first HTML response, so crawlers and link previews don't need JavaScript. The same tags are updated on client-side navigation (`app/Support/Seo.php`, `resources/js/Components/Seo.jsx`).
- Structured data: Organization and WebSite search box (home), BreadcrumbList (categories, brands, products) and Product with offer, availability, brand and ratings.
- Filtered/sorted listings, search, cart, checkout and account pages are `noindex` and canonicalise to the clean URL.
- `/sitemap.xml` is an index of page, category, brand and product sitemaps (5,000 products per file, with images). `/robots.txt` is generated and points to it.
- Products, categories, brands and pages have their own meta title, description and keywords in the admin. The product form shows a Google preview, length counters, an SEO checklist and can generate the fields from the product.
- **Settings → SEO**: homepage title and description, default share image, Google/Bing/Facebook verification codes, and a switch that hides a staging site from search engines.

## Tracking

**Settings → Tracking** takes a Google Tag Manager container ID, a GA4 measurement ID and a Meta Pixel ID. They load on the storefront only and report page views on every navigation plus `view_item`, `add_to_cart`, `begin_checkout` and `purchase` (GA4 e-commerce format in the dataLayer; ViewContent, AddToCart, InitiateCheckout and Purchase for the Pixel).

## Payments

**Settings → Payments** has cash on delivery plus bKash (Tokenized Checkout), Nagad, SSLCommerz and EPS, each with a sandbox switch. Secrets are stored encrypted and never sent to the browser. A gateway appears at checkout only when it is switched on and all its credentials are filled in.

Flow: the order is created, the customer is sent to the gateway, and the gateway returns to `/payment/{gateway}/{result}/{order}`. The order is marked paid only after the payment is confirmed server-to-server with the gateway (amount and reference are checked). SSLCommerz also calls the IPN URL shown in the settings. If a payment fails or is cancelled, the order page offers "Pay with…" buttons to try again. Code: `app/Payments`, `app/Http/Controllers/PaymentController.php`.

Test every gateway with its sandbox credentials before going live. The Nagad and EPS integrations follow their published API docs but haven't been run against their sandboxes yet.

## Couriers

**Settings → Couriers** connects Steadfast and Pathao. Each has a **Test connection** button (Steadfast shows your balance, Pathao lists your pickup stores) and a status webhook URL plus token to paste into the courier's merchant panel.

- Book a parcel from an order's page (**Courier → Send to Steadfast / Pathao**). Name, phone, address and cash to collect are prefilled (nothing to collect when the order was paid online). For Pathao, pick the city, zone and optional area from Pathao's own lists; the order's city is preselected.
- Booking moves the order to Shipped. Courier updates arrive by webhook (or **Refresh status**) and are added to the order history. When a parcel is delivered the order becomes Delivered and cash-on-delivery orders are marked paid; switch this off with "Update orders from the courier".
- Customers see the courier and a **Track parcel** link on the order tracking and account pages.
- API secrets and passwords are stored encrypted. Code: `app/Couriers`, `app/Http/Controllers/Admin/CourierController.php`, `routes/webhooks.php`.

Pathao was tested end to end against its public sandbox. Steadfast has no sandbox, so book one real parcel first to confirm the setup.

## Running the shop

- **Create Order** (Orders → Create Order): staff orders from phone calls, Facebook or the shop counter. Search products, edit prices, set a discount (percent or amount), delivery and payment. Typing a returning customer's phone fills in their details. Walk-in sales need no customer details.
- **Notifications** (Settings → Notifications): SMS through BulkSMSBD, Alpha SMS or SSL Wireless, and email through SMTP. Choose which order updates go out (placed, confirmed, shipped, delivered, cancelled, payment received) and edit the SMS text with placeholders like `{name}`, `{order}`, `{tracking_url}`. A new-order alert can go to the shop's email. Every message is logged on the order page. Messages are sent by the queue worker.
- **Checkout verification**: optional 6-digit SMS code before a customer can place an order.
- **Fraud check**: each order shows the customer's delivered vs. cancelled record in this shop, and across couriers with a BD Courier API key. Mark a number as fraud from the order page or **Blocked Numbers**; checkout then refuses cash on delivery (or all orders) for it.
- **System Status** (Settings → System Status): a go-live checklist (production mode, HTTPS, demo password, queue worker, scheduler, backups, email, SMS, sandbox modes) with how to fix each item, plus database backups to download.
- **Scheduled jobs** (`routes/console.php`): courier status sync every 30 minutes, nightly database backup at 02:30, weekly clean-ups. Needs one cron entry.

Deployment steps, Nginx and Supervisor configs are in **[DEPLOY.md](DEPLOY.md)** and `deploy/`.

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
