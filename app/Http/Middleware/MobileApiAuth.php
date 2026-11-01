<?php

namespace App\Http\Middleware;

use App\Models\Setting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MobileApiAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $key = Setting::get('mobile_api_key', '');
        $secret = Setting::get('mobile_api_secret', '');

        if ($key === '' || $secret === '') {
            return response()->json([
                'success' => false,
                'message' => 'API tablet belum diaktifkan. Atur API Key & Secret di menu Pengaturan.',
            ], 503);
        }

        $reqKey = (string) $request->header('X-API-KEY', '');
        $reqSecret = (string) $request->header('X-API-SECRET', '');

        if (! hash_equals($key, $reqKey) || ! hash_equals($secret, $reqSecret)) {
            return response()->json([
                'success' => false,
                'message' => 'API Key atau Secret tidak valid.',
            ], 401);
        }

        return $next($request);
    }
}
