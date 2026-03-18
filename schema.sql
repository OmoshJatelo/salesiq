-- =============================================================
-- SalesIQ E-Commerce  |  Fresh standalone database
-- =============================================================
-- mysql -u root -p salesiq_db < schema.sql
-- =============================================================

CREATE DATABASE IF NOT EXISTS salesiq_db
    CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE salesiq_db;

-- 1. users
CREATE TABLE users (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(100) NOT NULL,
    email         VARCHAR(150) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    phone         VARCHAR(20)  NULL,
    role          ENUM('admin','staff','customer') NOT NULL DEFAULT 'customer',
    theme_pref    ENUM('light','dark') NOT NULL DEFAULT 'light',
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

-- 2. categories
CREATE TABLE categories (
    id   INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(80) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- 3. products
CREATE TABLE products (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name          VARCHAR(200) NOT NULL,
    description   TEXT,
    cost_price    DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    selling_price DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    stock         INT UNSIGNED  NOT NULL DEFAULT 0,
    reorder_level INT UNSIGNED  NOT NULL DEFAULT 10,
    image_url     VARCHAR(500)  NULL,
    is_active     TINYINT(1)    NOT NULL DEFAULT 1,
    category_id   INT UNSIGNED  NOT NULL,
    created_by    INT UNSIGNED  NOT NULL,
    created_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at    TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_prod_cat  FOREIGN KEY (category_id) REFERENCES categories(id),
    CONSTRAINT fk_prod_user FOREIGN KEY (created_by)  REFERENCES users(id)
) ENGINE=InnoDB;

-- 4. orders
CREATE TABLE orders (
    id             INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    customer_id    INT UNSIGNED NOT NULL,
    status         ENUM('pending','paid','processing','shipped','delivered','cancelled','refunded')
                   NOT NULL DEFAULT 'pending',
    payment_method ENUM('mpesa','cash') NOT NULL DEFAULT 'mpesa',
    subtotal       DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    delivery_fee   DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    total          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
    mpesa_phone    VARCHAR(20)   NULL,
    mpesa_code     VARCHAR(30)   NULL,
    notes          VARCHAR(500)  NULL,
    created_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at     TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_order_customer FOREIGN KEY (customer_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- 5. order_items
CREATE TABLE order_items (
    id           INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id     INT UNSIGNED  NOT NULL,
    product_id   INT UNSIGNED  NOT NULL,
    product_name VARCHAR(200)  NOT NULL,   -- snapshot so name changes don't break history
    quantity     INT UNSIGNED  NOT NULL,
    unit_price   DECIMAL(10,2) NOT NULL,   -- selling price at time of sale
    cost_price   DECIMAL(10,2) NOT NULL,   -- cost snapshot for profit calc
    CONSTRAINT fk_oi_order   FOREIGN KEY (order_id)   REFERENCES orders(id),
    CONSTRAINT fk_oi_product FOREIGN KEY (product_id) REFERENCES products(id)
) ENGINE=InnoDB;

-- 6. audit_log
CREATE TABLE audit_log (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id    INT UNSIGNED NOT NULL,
    action     ENUM('CREATE','UPDATE','DELETE') NOT NULL,
    entity     VARCHAR(50)  NOT NULL,
    entity_id  INT UNSIGNED NOT NULL,
    details    TEXT,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_audit_user FOREIGN KEY (user_id) REFERENCES users(id)
) ENGINE=InnoDB;

-- ── Seed data ──────────────────────────────────────────────────────────────

INSERT INTO categories (name) VALUES
    ('Electronics'), ('Clothing'), ('Home & Garden'), ('Sports'), ('Books');

-- Admin user  (password = admin123)
-- Replace the hash below: php -r "echo password_hash('admin123', PASSWORD_BCRYPT);"
INSERT INTO users (name, email, password_hash, role) VALUES
    ('Admin', 'admin@salesiq.co.ke',
     '$2y$10$ReplaceThisWithARealBcryptHashGeneratedByPHP00000000000',
     'admin');

-- Sample products (created_by = 1 = the admin above)
INSERT INTO products (name, description, cost_price, selling_price, stock, reorder_level, category_id, created_by) VALUES
    ('Wireless Headphones',  'Noise-cancelling Bluetooth, 30 hr battery.',       45.00,  89.99,  45, 10, 1, 1),
    ('Running Shoes',        'Lightweight trainers, cushioned sole, sizes 38-46.', 70.00, 129.50, 30,  8, 4, 1),
    ('Desk Lamp',            'LED, 3 brightness modes, USB charging port.',       15.00,  34.00,  60, 15, 3, 1),
    ('Python Cookbook',      "O'Reilly 3rd edition, advanced recipes.",           18.00,  42.75,  20,  5, 5, 1),
    ('Cotton T-Shirt',       'Organic cotton, sizes XS–XXL, 5 colours.',           8.00,  19.99, 100, 20, 2, 1),
    ('Mechanical Keyboard',  'TKL, Cherry MX switches, RGB, aluminium frame.',   55.00, 110.00,  25,  5, 1, 1),
    ('Yoga Mat',             'Non-slip, 6 mm thick, 183 × 61 cm + carry strap.', 12.00,  28.00,  40, 10, 4, 1),
    ('Novel: Dust',          'Yvonne Adhiambo Owuor. Award-winning. Paperback.',   6.00,  15.00,  50, 10, 5, 1);
