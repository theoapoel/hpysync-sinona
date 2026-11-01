<?php

use App\Models\Setting;
use Carbon\Carbon;

if (! function_exists('local_tz')) {
    /** Timezone lokal aplikasi (mis. WIB). App menyimpan waktu di UTC. */
    function local_tz(): string
    {
        return Setting::get('timezone', 'Asia/Jakarta') ?: 'Asia/Jakarta';
    }
}

if (! function_exists('local_dt')) {
    /**
     * Konversi datetime (tersimpan UTC di DB) ke timezone lokal lalu format.
     * Aman untuk data lama maupun baru karena selalu dikonversi saat ditampilkan.
     */
    function local_dt($dt, string $format = 'd/m/Y H:i'): string
    {
        if (empty($dt)) {
            return '';
        }

        return Carbon::parse($dt)->setTimezone(local_tz())->format($format);
    }
}

if (! function_exists('pos_payment_methods')) {
    /**
     * Metode pembayaran POS beserta penanda `is_cash`.
     *
     * Field `type` dari POS Profile ERP sering tidak terisi sehingga semua
     * metode jatuh ke default 'General' — termasuk CASH. Karena itu nama
     * metode ikut diperiksa agar tunai tetap dikenali dan kolom "Nominal
     * Bayar" muncul sebagaimana mestinya. Kata "kas" ikut dikenali karena
     * toko sering menamai mode tunainya "KAS STORE", "Kas Kecil", dsb.
     */
    function pos_payment_methods(): array
    {
        $methods = json_decode(Setting::get('pos_payment_methods', '[]'), true) ?: [];

        foreach ($methods as $i => $m) {
            $name = $m['mode_of_payment'] ?? '';
            $methods[$i]['is_cash'] = ($m['type'] ?? '') === 'Cash'
                || (bool) preg_match('/(^|[^a-z])(cash|tunai|kas)([^a-z]|$)/i', $name);
        }

        return array_values($methods);
    }
}
