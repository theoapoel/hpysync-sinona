-- ============================================================
-- LaraPos — SQL Schema Dump untuk MariaDB / MySQL
-- Gunakan ini sebagai alternatif jika `php artisan migrate` gagal
-- ============================================================

CREATE DATABASE IF NOT EXISTS `larapos`
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE `larapos`;

-- ─── USERS ───────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `users` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(255)    NOT NULL,
    `email`          VARCHAR(255)    NOT NULL UNIQUE,
    `password`       VARCHAR(255)    NOT NULL,
    `role`           VARCHAR(50)     NOT NULL DEFAULT 'cashier',
    `pin`            VARCHAR(6)      DEFAULT NULL,
    `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
    `remember_token` VARCHAR(100)    DEFAULT NULL,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── CATEGORIES ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `categories` (
    `id`             BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `name`           VARCHAR(255)    NOT NULL,
    `slug`           VARCHAR(255)    NOT NULL UNIQUE,
    `color`          VARCHAR(7)      NOT NULL DEFAULT '#4285F4',
    `icon`           VARCHAR(100)    DEFAULT NULL,
    `is_active`      TINYINT(1)      NOT NULL DEFAULT 1,
    `erp_item_group` VARCHAR(255)    DEFAULT NULL,
    `created_at`     TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`     TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── PRODUCTS ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `products` (
    `id`            BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `sku`           VARCHAR(255)    NOT NULL UNIQUE,
    `barcode`       VARCHAR(255)    DEFAULT NULL,
    `name`          VARCHAR(255)    NOT NULL,
    `description`   TEXT            DEFAULT NULL,
    `category_id`   BIGINT UNSIGNED DEFAULT NULL,
    `price`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `cost_price`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `stock`         INT             NOT NULL DEFAULT 0,
    `min_stock`     INT             NOT NULL DEFAULT 0,
    `unit`          VARCHAR(50)     NOT NULL DEFAULT 'pcs',
    `image`         VARCHAR(255)    DEFAULT NULL,
    `is_active`     TINYINT(1)      NOT NULL DEFAULT 1,
    `track_stock`   TINYINT(1)      NOT NULL DEFAULT 1,
    `tax_rate`      DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `erp_item_code` VARCHAR(255)    DEFAULT NULL,
    `erp_last_sync` TIMESTAMP       NULL DEFAULT NULL,
    `created_at`    TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`    TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `products_barcode_index` (`barcode`),
    KEY `products_erp_item_code_index` (`erp_item_code`),
    CONSTRAINT `fk_products_category` FOREIGN KEY (`category_id`)
        REFERENCES `categories` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── CUSTOMERS ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `customers` (
    `id`                BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code`              VARCHAR(255)    NOT NULL UNIQUE,
    `name`              VARCHAR(255)    NOT NULL,
    `email`             VARCHAR(255)    DEFAULT NULL,
    `phone`             VARCHAR(50)     DEFAULT NULL,
    `address`           TEXT            DEFAULT NULL,
    `loyalty_points`    DECIMAL(10,2)   NOT NULL DEFAULT 0.00,
    `total_purchase`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `is_active`         TINYINT(1)      NOT NULL DEFAULT 1,
    `erp_customer_name` VARCHAR(255)    DEFAULT NULL,
    `erp_last_sync`     TIMESTAMP       NULL DEFAULT NULL,
    `created_at`        TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`        TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `customers_erp_customer_name_index` (`erp_customer_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TRANSACTIONS ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transactions` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `invoice_no`       VARCHAR(255)    NOT NULL UNIQUE,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `customer_id`      BIGINT UNSIGNED DEFAULT NULL,
    `status`           VARCHAR(50)     NOT NULL DEFAULT 'completed',
    `subtotal`         DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `discount_amount`  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `discount_percent` DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `tax_amount`       DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `total`            DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `paid_amount`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `change_amount`    DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `payment_method`   VARCHAR(50)     NOT NULL DEFAULT 'cash',
    `payment_details`  JSON            DEFAULT NULL,
    `notes`            TEXT            DEFAULT NULL,
    `erp_pos_invoice`  VARCHAR(255)    DEFAULT NULL,
    `erp_synced_at`    TIMESTAMP       NULL DEFAULT NULL,
    `erp_sync_status`  VARCHAR(50)     NOT NULL DEFAULT 'pending',
    `erp_sync_error`   TEXT            DEFAULT NULL,
    `created_at`       TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
    `deleted_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `transactions_erp_pos_invoice_index` (`erp_pos_invoice`),
    CONSTRAINT `fk_transactions_user`     FOREIGN KEY (`user_id`)     REFERENCES `users`     (`id`),
    CONSTRAINT `fk_transactions_customer` FOREIGN KEY (`customer_id`) REFERENCES `customers` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── TRANSACTION ITEMS ────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `transaction_items` (
    `id`              BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `transaction_id`  BIGINT UNSIGNED NOT NULL,
    `product_id`      BIGINT UNSIGNED NOT NULL,
    `product_name`    VARCHAR(255)    NOT NULL,
    `product_sku`     VARCHAR(255)    NOT NULL,
    `price`           DECIMAL(15,2)   NOT NULL,
    `cost_price`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `quantity`        INT             NOT NULL,
    `discount_amount` DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `tax_rate`        DECIMAL(5,2)    NOT NULL DEFAULT 0.00,
    `tax_amount`      DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `subtotal`        DECIMAL(15,2)   NOT NULL,
    `created_at`      TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`      TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_items_transaction` FOREIGN KEY (`transaction_id`)
        REFERENCES `transactions` (`id`) ON DELETE CASCADE,
    CONSTRAINT `fk_items_product`     FOREIGN KEY (`product_id`)
        REFERENCES `products` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── CASH REGISTERS ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `cash_registers` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `user_id`          BIGINT UNSIGNED NOT NULL,
    `shift_name`       VARCHAR(255)    DEFAULT NULL,
    `opening_balance`  DECIMAL(15,2)   NOT NULL DEFAULT 0.00,
    `closing_balance`  DECIMAL(15,2)   DEFAULT NULL,
    `expected_balance` DECIMAL(15,2)   DEFAULT NULL,
    `difference`       DECIMAL(15,2)   DEFAULT NULL,
    `opened_at`        TIMESTAMP       NOT NULL,
    `closed_at`        TIMESTAMP       NULL DEFAULT NULL,
    `status`           VARCHAR(50)     NOT NULL DEFAULT 'open',
    `notes`            TEXT            DEFAULT NULL,
    `created_at`       TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    CONSTRAINT `fk_cashregisters_user` FOREIGN KEY (`user_id`) REFERENCES `users` (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── ERP SYNC LOGS ────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `erp_sync_logs` (
    `id`               BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `type`             VARCHAR(100)    NOT NULL,
    `reference_id`     BIGINT UNSIGNED NOT NULL,
    `reference_no`     VARCHAR(255)    DEFAULT NULL,
    `status`           VARCHAR(50)     NOT NULL,
    `request_payload`  TEXT            DEFAULT NULL,
    `response_payload` TEXT            DEFAULT NULL,
    `error_message`    TEXT            DEFAULT NULL,
    `erp_docname`      VARCHAR(255)    DEFAULT NULL,
    `created_at`       TIMESTAMP       NULL DEFAULT NULL,
    `updated_at`       TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `erp_sync_logs_type_reference` (`type`, `reference_id`),
    KEY `erp_sync_logs_status_created` (`status`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── SETTINGS ─────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `settings` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `key`        VARCHAR(255)    NOT NULL UNIQUE,
    `value`      TEXT            DEFAULT NULL,
    `group`      VARCHAR(100)    NOT NULL DEFAULT 'general',
    `created_at` TIMESTAMP       NULL DEFAULT NULL,
    `updated_at` TIMESTAMP       NULL DEFAULT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── MIGRATIONS TABLE (Laravel internal) ──────────────────────
CREATE TABLE IF NOT EXISTS `migrations` (
    `id`        INT UNSIGNED    NOT NULL AUTO_INCREMENT,
    `migration` VARCHAR(255)    NOT NULL,
    `batch`     INT             NOT NULL,
    PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─── SESSIONS TABLE ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `sessions` (
    `id`            VARCHAR(255)    NOT NULL,
    `user_id`       BIGINT UNSIGNED DEFAULT NULL,
    `ip_address`    VARCHAR(45)     DEFAULT NULL,
    `user_agent`    TEXT            DEFAULT NULL,
    `payload`       LONGTEXT        NOT NULL,
    `last_activity` INT             NOT NULL,
    PRIMARY KEY (`id`),
    KEY `sessions_user_id_index` (`user_id`),
    KEY `sessions_last_activity_index` (`last_activity`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
