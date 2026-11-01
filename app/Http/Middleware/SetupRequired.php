<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetupRequired
{
    // Route yang boleh diakses meski belum setup
    private const EXCEPT = [
        'setup',
        'setup/test-connection',
        'login',
        'logout',
        'up',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        foreach (self::EXCEPT as $path) {
            if ($request->is($path)) {
                return $next($request);
            }
        }

        // Cek DB saja — env tidak dihitung sebagai "sudah dikonfigurasi"
        $url = Setting::where('key', 'erpnext_url')->value('value');

        if (empty($url)) {
            return redirect()->route('setup.index')
                ->with('info', 'Selesaikan konfigurasi awal sebelum menggunakan aplikasi.');
        }

        return $next($request);
    }
}
