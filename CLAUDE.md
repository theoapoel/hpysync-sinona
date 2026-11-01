# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project

**Mitra POS HPY** — Laravel 11 Point-of-Sale system with ERPNext/HPY integration. PHP 8.2+, MySQL, Blade + Vite.

## Commands

```bash
# Dependencies
composer install
npm install

# Dev server (XAMPP already handles Apache; use artisan serve for standalone)
php artisan serve

# Frontend assets
npm run dev      # watch mode
npm run build    # production build

# Database
php artisan migrate
php artisan db:seed                 # creates demo users, products, ~300 transactions
php artisan migrate:fresh --seed    # full reset

# Cache (clear after .env or config changes)
php artisan cache:clear && php artisan config:clear && php artisan route:clear

# Linting (PSR-12 via Laravel Pint)
./vendor/bin/pint --test   # check only
./vendor/bin/pint           # auto-fix

# Tests
./vendor/bin/phpunit
./vendor/bin/phpunit tests/Feature/SomeTest.php   # single file
```

## Architecture

### Middleware Layers

Routes use two stacking middlewares defined in `app/Http/Middleware/`:

- `role:admin` / `role:admin,manager` — checks `users.role` column directly
- `permission:module_name` — checks `role_permissions` table via `RolePermission::can()`

All configurable permission modules are defined in `RolePermission::modules()`:
`dashboard`, `pos`, `transactions`, `products`, `customers`, `stock_transfer`, `stock`, `delivery`, `kitchen`, `stock_request`, `rekap_order`, `sync`

Admin-only routes (users, roles, settings, warehouses, backup) bypass the permission system — they only use `role:admin`.

Permissions are cached per-role for 300 seconds under the key `role_permissions_{role}`. Call `RolePermission::clearCache($role)` after any permission change.

### Configuration Store

`app/Models/Setting.php` is a key-value store for runtime configuration (ERPNext credentials, store name, default warehouse, etc.). These override `.env` at runtime. Pattern: `Setting::get('key', $default)` / `Setting::set('key', $value)`.

### ERPNext Integration

All ERPNext API calls go through `app/Services/ErpNextService.php` (Guzzle HTTP). Credentials are read from the `Setting` model at runtime. Sync state is tracked via `erp_sync_status` (`pending` / `synced` / `failed`) on the relevant table and audited in `erp_sync_logs`. Syncs run synchronously per request — there is no background queue.

### Transaction Flow (POS)

Checkout in `PosController::checkout()` runs inside a DB transaction: validates cart, deducts stock (if `products.track_stock = true`), writes `Transaction` + `TransactionItems`, marks `erp_sync_status = pending`. Invoice numbers: `INV-YYYYMMDD-XXXX`. Cancellation (admin/manager only) restores stock and soft-deletes the transaction.

### Delivery Order Flow

`DeliveryOrder` statuses: `draft` → `confirmed` → (soft-deleted on cancel). Kitchen statuses: `pending` → `preparing` → `ready`. Document numbers: `DO-YYYYMMDD-XXXX`. Each order has `DeliveryOrderItem` rows and `DeliveryShipment` rows (JSON items). Delivery Notes (`DeliveryNoteController`) operate on shipments, not orders.

The Rekap Order report (`RekapOrderController`) aggregates items across confirmed Delivery Orders (filtered by `delivery_date`) and submitted Stock Requests (filtered by `needed_date`), merging by `item_code`/`product_sku`.

### Stock Request Flow (Permintaan FG)

`StockRequest` statuses: `draft` → `submitted` → (cancelled). Kitchen statuses: `requested` → `preparing` → `done`. Document numbers: `FG-YYYYMMDD-XXXX`. Syncs to ERPNext as a Material Request.

### Stock Transfer Flow

`StockTransfer` has `type` (outgoing/incoming), `status` (draft/submitted/cancelled), and `local_status` (draft/sent/received). Document numbers: `STO-*` for outgoing, `STI-*` for incoming. Receiving loads items from the ERPNext source entry. The surat jalan print view (`stock-transfer.surat-jalan`) is a standalone HTML page (no app layout) opened in a new tab. A report detail view (`StockTransferReportController`) aggregates all transfers with their line items.

### Kitchen Monitor

`KitchenController` polls both `DeliveryOrder` (kitchen_status) and `StockRequest` (kitchen_status) in a single view. The poll endpoint returns JSON for live refresh.

### Multi-Warehouse

`Warehouse` models map 1:1 to ERPNext warehouses. One carries `is_default = true` for POS; one may carry `is_transit = true` for in-transit stock.

### Coupon System

`Coupon` records are pulled from ERPNext. Applied at POS checkout; discount stored in `transactions.coupon_discount`. Auto-sync toggle available in settings.

### Demo Credentials (after seeding)

| Role    | Email                  | Password  | PIN    |
|---------|------------------------|-----------|--------|
| Admin   | admin@larapos.com      | password  | 123456 |
| Manager | manager@larapos.com    | password  | 111222 |
| Cashier | kasir@larapos.com      | password  | 654321 |

Cashiers land directly on the POS page after login; role `dapur` lands on Kitchen Monitor.

## Adding a New Permission Module

1. Add entry to `RolePermission::modules()` in [app/Models/RolePermission.php](app/Models/RolePermission.php)
2. Add `->middleware('permission:new_module')` to the relevant route group in [routes/web.php](routes/web.php)
3. Add `$canNewModule = $u->hasPermission('new_module')` and the nav item to [resources/views/layouts/app.blade.php](resources/views/layouts/app.blade.php)

The permission matrix UI at `resources/views/permissions/index.blade.php` reads `RolePermission::modules()` dynamically — no changes needed there.
