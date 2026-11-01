<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_opnames', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->constrained('users');
            $table->date('opname_date');
            $table->enum('status', ['draft', 'submitted', 'cancelled'])->default('draft');
            $table->text('notes')->nullable();
            $table->enum('erp_sync_status', ['pending', 'synced', 'failed'])->nullable();
            $table->text('erp_sync_error')->nullable();
            $table->string('erp_entry_issue')->nullable();
            $table->string('erp_entry_receipt')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_opnames');
    }
};
