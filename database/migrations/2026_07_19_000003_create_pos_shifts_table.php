<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        // Sesi kasir (shift): buka → jualan → tutup. 1 shift = 1 POS Opening Entry
        // + 1 POS Closing Entry di ERP HPY. Kasir bisa buka-tutup berkali-kali/hari.
        Schema::create('pos_shifts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users');
            $table->string('pos_profile', 140)->nullable();
            $table->string('status', 20)->default('open');   // open | closed

            $table->timestamp('opened_at');
            $table->timestamp('closed_at')->nullable();

            $table->decimal('opening_cash', 14, 2)->default(0);      // modal kas awal
            $table->decimal('expected_cash', 14, 2)->nullable();     // kas seharusnya (opening + tunai bersih)
            $table->decimal('counted_cash', 14, 2)->nullable();      // kas hasil hitung fisik
            $table->decimal('cash_difference', 14, 2)->nullable();   // counted - expected

            $table->decimal('total_sales', 14, 2)->nullable();       // grand total penjualan shift
            $table->unsignedInteger('invoice_count')->default(0);
            $table->json('payment_breakdown')->nullable();           // snapshot per metode: expected/counted

            $table->string('erp_opening_entry', 100)->nullable();
            $table->string('erp_closing_entry', 100)->nullable();
            $table->string('erp_sync_status', 20)->default('pending'); // pending | synced | failed
            $table->text('erp_sync_error')->nullable();

            $table->timestamps();
            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_shifts');
    }
};
