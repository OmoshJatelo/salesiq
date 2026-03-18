# SalesIQ — E-Commerce System

A PHP/MySQL e-commerce platform with a public storefront, session cart, M-Pesa checkout, and a full admin panel.

---

## Project Structure

```
salesiq/
├── admin/                   ← Admin-only pages (staff & admin roles)
│   ├── dashboard.php        ← Revenue/profit analytics, charts, alerts
│   ├── orders.php           ← All customer orders + status management
│   ├── products.php         ← Product CRUD with margin calculator
│   ├── customers.php        ← Customer list with lifetime value
│   └── categories.php       ← Category management
│
├── backend/
│   ├── includes/
│   │   ├── bootstrap.php    ← Entry point: loads env, session, all modules
│   │   ├── db.php           ← PDO singleton
│   │   ├── env.php          ← .env file loader
│   │   ├── helpers.php      ← esc(), flashSet/Get, paginate(), deliveryFee()
│   │   ├── auth.php         ← Login, register, logout, role helpers
│   │   ├── audit.php        ← logAction(), getRecentAudit()
│   │   ├── shop_header.php  ← Storefront HTML header (nav, cart badge, search)
│   │   ├── shop_footer.php  ← Storefront HTML footer
│   │   ├── admin_header.php ← Admin panel HTML header
│   │   └── admin_footer.php ← Admin panel HTML footer
│   └── php/
│       └── product_form.php ← Shared product create/edit form
│
├── frontend/
│   ├── css/
│   │   ├── shop.css         ← Storefront styles (light + dark mode)
│   │   └── admin.css        ← Admin panel styles (light + dark mode)
│   └── js/
│       └── shop.js          ← Auto-dismiss alerts
│
├── index.php                ← Smart redirect (role-based)
├── shop.php                 ← Public product grid with search + category filter
├── product.php              ← Single product detail + related products
├── cart.php                 ← Session-based cart (add/update/remove)
├── checkout.php             ← M-Pesa checkout with stock locking
├── order_confirmation.php   ← Post-purchase thank-you page
├── my_orders.php            ← Customer order history
├── login.php
├── register.php
├── logout.php
├── settings.php             ← Phone + theme prefs (works for all roles)
├── schema.sql               ← Full database schema + seed data
├── generate_hash.php        ← One-time password hash utility (delete after use)
├── .env.example             ← Copy to .env and fill in credentials
├── .htaccess                ← Blocks direct /backend/ access
└── .gitignore
```

---

## Roles

| Role       | Access                                                   |
|------------|----------------------------------------------------------|
| `customer` | Shop, cart, checkout, my orders, settings                |
| `staff`    | Admin panel: dashboard, orders, products (view/edit)     |
| `admin`    | Everything staff sees + create/delete products, customers, categories |

Customers **never** see admin pages, sales data, cost prices, or profit margins.

---

## Setup

### 1. Database

```bash
mysql -u root -p < schema.sql
```

This creates the `salesiq_db` database with all tables and sample data.

### 2. Environment

```bash
cp .env.example .env
```

Edit `.env` with your database credentials:

```
DB_HOST=localhost
DB_NAME=salesiq_db
DB_USER=your_db_user
DB_PASS=your_db_password
APP_DEBUG=false
```

### 3. Admin password

The seed admin user has a placeholder hash. Generate a real one:

```bash
php generate_hash.php
```

Then run the SQL it outputs, e.g.:
```sql
UPDATE users SET password_hash='$2y$10$...' WHERE email='admin@salesiq.co.ke';
```

**Delete `generate_hash.php` from your server after use.**

### 4. Web server

Point your document root to the `salesiq/` folder.

**Apache** — the included `.htaccess` blocks direct access to `backend/`.

**Nginx** — add this to your server block:
```nginx
location /backend/ { deny all; }
```

### 5. HTTPS

Set `APP_DEBUG=false` in production. Session cookies are marked `Secure` automatically when the request comes over HTTPS.

---

## M-Pesa

The checkout uses a **manual code entry** flow: the customer pays via M-Pesa Pay Bill, then enters their phone number and the confirmation code from the SMS. No STK push or API polling is needed.

To upgrade to Daraja API (STK push), add your keys to `.env`:

```
MPESA_SHORTCODE=174379
MPESA_PASSKEY=...
MPESA_CONSUMER_KEY=...
MPESA_CONSUMER_SECRET=...
MPESA_ENV=sandbox
MPESA_CALLBACK_URL=https://yourdomain.co.ke/mpesa_callback.php
```

---

## Delivery Fee

Configured in `backend/includes/helpers.php`:

```php
function deliveryFee(float $subtotal): float {
    return $subtotal >= 5000 ? 0.0 : 300.0;
}
```

Change the threshold and fee amount here.

---

## Adding Products

Log in as admin → Admin Panel → Products → Add Product.

To bulk-insert via SQL, add rows to the `products` table following the seed data pattern in `schema.sql`. All prices in KES.

---

## Tech Stack

- PHP 8.1+
- MySQL 8+ / MariaDB 10.6+
- Bootstrap 5.3
- Chart.js 4.4 (admin dashboard)
- Sora (Google Font)
- Bootstrap Icons
