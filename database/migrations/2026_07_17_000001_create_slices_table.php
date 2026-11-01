<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('slices', function (Blueprint $table) {
            $table->id();
            $table->string('slice_no', 40)->unique();
            $table->foreignId('created_by')->constrained('users');
            $table->string('status', 20)->default('draft');           // draft, submitted, cancelled
            $table->string('erp_stock_entry', 100)->nullable();        // Repack Stock Entry name
            $table->string('erp_sync_status', 20)->default('pending'); // pending, synced, failed
            $table->text('erp_sync_error')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('slices');
    }
};
