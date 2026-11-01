<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('stock_requests', function (Blueprint $table) {
            $table->id();
            $table->string('request_no', 40)->unique();
            $table->foreignId('requested_by')->constrained('users');
            $table->string('status', 20)->default('draft');          // draft, submitted, cancelled
            $table->string('kitchen_status', 20)->nullable();         // requested, preparing, done
            $table->timestamp('kitchen_started_at')->nullable();
            $table->timestamp('kitchen_done_at')->nullable();
            $table->string('erp_material_request', 100)->nullable();
            $table->string('erp_sync_status', 20)->default('pending'); // pending, synced, failed
            $table->text('erp_sync_error')->nullable();
            $table->date('needed_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('stock_requests');
    }
};
