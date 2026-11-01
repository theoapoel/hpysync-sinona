<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('delivery_order_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery_order_id')->constrained()->cascadeOnDelete();
            $table->enum('payment_method', ['cash', 'transfer', 'qris', 'card'])->default('cash');
            $table->decimal('amount', 15, 2);
            $table->date('payment_date');
            $table->string('reference_no')->nullable();
            $table->text('notes')->nullable();
            $table->string('erp_payment_entry')->nullable();
            $table->enum('erp_sync_status', ['none', 'synced', 'failed'])->default('none');
            $table->text('erp_sync_error')->nullable();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamps();
        });

        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->enum('payment_status', ['unpaid', 'partial', 'paid'])->default('unpaid')->after('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('delivery_orders', function (Blueprint $table) {
            $table->dropColumn('payment_status');
        });
        Schema::dropIfExists('delivery_order_payments');
    }
};
