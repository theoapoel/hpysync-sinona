<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_prices', function (Blueprint $table) {
            $table->id();
            $table->string('erp_item_code');
            $table->string('platform'); // gofood, grabfood, shopeefood
            $table->decimal('price', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['erp_item_code', 'platform']);
            $table->index('platform');
        });

        // Migrate existing JSON data from settings table
        $platforms = ['gofood', 'grabfood', 'shopeefood'];
        foreach ($platforms as $platform) {
            $json = DB::table('settings')
                ->where('key', 'delivery_prices_' . $platform)
                ->value('value');

            if (!$json) continue;

            $prices = json_decode($json, true);
            if (!is_array($prices)) continue;

            $rows = [];
            $now  = now();
            foreach ($prices as $itemCode => $price) {
                $rows[] = [
                    'erp_item_code' => $itemCode,
                    'platform'      => $platform,
                    'price'         => (float) $price,
                    'created_at'    => $now,
                    'updated_at'    => $now,
                ];
            }

            if (!empty($rows)) {
                DB::table('delivery_prices')->insert($rows);
            }

            // Remove stale JSON setting
            DB::table('settings')->where('key', 'delivery_prices_' . $platform)->delete();
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_prices');
    }
};
