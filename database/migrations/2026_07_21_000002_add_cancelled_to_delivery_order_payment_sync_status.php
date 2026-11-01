<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Pembatalan Delivery Order ikut membatalkan Payment Entry di ERP HPY, jadi
     * statusnya butuh nilai 'cancelled'. Tanpa ini update-nya kena "Data truncated"
     * dan status lokal tertinggal 'synced' padahal dokumen ERP sudah dibatalkan.
     */
    public function up(): void
    {
        // SQLite tak punya tipe ENUM (kolomnya sudah TEXT), jadi tak perlu diubah.
        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE delivery_order_payments MODIFY erp_sync_status ENUM('none','synced','failed','cancelled') NOT NULL DEFAULT 'none'");
    }

    public function down(): void
    {
        DB::statement("UPDATE delivery_order_payments SET erp_sync_status = 'failed' WHERE erp_sync_status = 'cancelled'");

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        DB::statement("ALTER TABLE delivery_order_payments MODIFY erp_sync_status ENUM('none','synced','failed') NOT NULL DEFAULT 'none'");
    }
};
