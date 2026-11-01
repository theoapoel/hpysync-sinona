<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kunci idempoten checkout. Kasir yang menekan "Proses Pembayaran" dua kali — atau
 * koneksi yang putus setelah server selesai menyimpan — sebelumnya menghasilkan dua
 * Transaction dengan nomor invoice berbeda, dan keduanya ikut tersync ke ERP HPY
 * sebagai penjualan yang sama-sama sah. Duplikat semacam itu tidak bisa dikenali dari
 * data mana pun sesudahnya, jadi harus dicegah di titik pembuatannya.
 *
 * Unique index-nya yang menjadi penentu: dua request bersamaan tidak bisa dua-duanya
 * lolos, sehingga pengecekan "sudah ada atau belum" tidak punya celah balapan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->string('idempotency_key', 64)->nullable()->unique()->after('invoice_no');
        });
    }

    public function down(): void
    {
        Schema::table('transactions', function (Blueprint $table) {
            $table->dropUnique(['idempotency_key']);
            $table->dropColumn('idempotency_key');
        });
    }
};
