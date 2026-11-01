<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use GuzzleHttp\Client;
use Illuminate\Http\Request;

class SetupController extends Controller
{
    public function index()
    {
        // Pre-fill dari DB jika ada, fallback ke .env supaya user tidak ketik ulang
        return view('setup.index', [
            'url'     => Setting::where('key', 'erpnext_url')->value('value')
                         ?? env('ERPNEXT_URL', ''),
            'company' => Setting::where('key', 'erpnext_company')->value('value')
                         ?? env('ERPNEXT_COMPANY', ''),
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'erpnext_url'     => 'required|url',
            'erpnext_company' => 'required|string|max:200',
        ], [
            'erpnext_url.required' => 'URL ERP HPY wajib diisi.',
            'erpnext_url.url'      => 'Format URL tidak valid. Contoh: http://erp.perusahaan.com',
            'erpnext_company.required' => 'Nama company wajib diisi.',
        ]);

        Setting::set('erpnext_url',     rtrim($request->erpnext_url, '/'), 'erpnext');
        Setting::set('erpnext_company', $request->erpnext_company, 'erpnext');

        return redirect()->route('login')
            ->with('setup_done', 'Konfigurasi berhasil disimpan. Silakan login menggunakan akun ERP HPY.');
    }

    public function testConnection(Request $request)
    {
        $request->validate(['url' => 'required|url']);

        $url = rtrim($request->url, '/');

        try {
            $client = new Client([
                'base_uri'        => $url,
                'timeout'         => 10,
                'verify'          => false,
                'http_errors'     => false,
                'allow_redirects' => true,
            ]);

            $resp   = $client->get('/api/method/frappe.utils.ping');
            $status = $resp->getStatusCode();
            $body   = json_decode($resp->getBody()->getContents(), true);

            if ($status === 200 && isset($body['message'])) {
                return response()->json(['success' => true, 'message' => 'Berhasil terhubung ke ERP HPY.']);
            }

            // Frappe pasti return JSON; jika kita mendapat HTML berarti bukan Frappe
            if ($status >= 200 && $status < 500) {
                return response()->json(['success' => true, 'message' => 'Server merespons (status ' . $status . ').']);
            }

            return response()->json(['success' => false, 'error' => 'Server merespons dengan status ' . $status . '.']);

        } catch (\Exception $e) {
            return response()->json(['success' => false, 'error' => 'Tidak dapat menjangkau server: ' . $e->getMessage()]);
        }
    }
}
