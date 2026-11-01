<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_transfers', function (Blueprint $table) {
            $table->id();
            $table->string('transfer_no')->unique();
            $table->enum('type', ['outgoing', 'incoming']);
            $table->enum('status', ['draft', 'submitted', 'cancelled'])->default('draft');
            $table->string('from_warehouse');
            $table->string('to_warehouse');
            $table->string('in_transit_warehouse')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();

            // ERPNext fields
            $table->string('erp_stock_entry')->nullable();
            $table->string('erp_source_entry')->nullable()->comment('For incoming: reference to the outgoing stock entry');
            $table->enum('erp_sync_status', ['pending', 'synced', 'failed'])->default('pending');
            $table->text('erp_sync_error')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['type', 'status']);
            $table->index(['erp_sync_status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_transfers');
    }
};
