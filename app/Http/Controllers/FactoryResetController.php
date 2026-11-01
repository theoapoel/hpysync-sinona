<?php

namespace App\Http\Controllers;

use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class FactoryResetController extends Controller
{
    public function __construct(private ErpNextService $erp) {}

    public function index()
    {
        return view('factory-reset.index', [
            'backupCreatedAt' => Session::get('backup_created_at'),
        ]);
    }

    // ----------------------------------------------------------
    // Jumlah data yang akan dihapus (untuk preview di UI)
    // ----------------------------------------------------------
    public function counts()
    {
        return response()->json([
            'success'         => true,
            'transactions'    => DB::table('transactions')->count(),
            'products'        => DB::table('products')->count(),
            'customers'       => DB::table('customers')->count(),
            'transfers'       => DB::table('stock_transfers')->count(),
            'delivery_orders' => DB::table('delivery_orders')->count(),
            'stock_requests'  => DB::table('stock_requests')->count(),
            'logs'            => DB::table('erp_sync_logs')->count(),
        ]);
    }

    // ----------------------------------------------------------
    // STEP 1: Verifikasi kredensial ERPNext System Manager
    // ----------------------------------------------------------
    public function verify(Request $request)
    {
        $request->validate([
            'erp_username' => 'required|string',
            'erp_password' => 'required|string',
        ]);

        $result = $this->erp->verifySystemManager(
            $request->erp_username,
            $request->erp_password
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']]);
        }

        // Simpan token verifikasi di session (berlaku 10 menit)
        $token = hash('sha256', $result['username'] . now()->timestamp . config('app.key'));
        Session::put('factory_reset_token', $token);
        Session::put('factory_reset_verified_at', now()->timestamp);
        Session::put('factory_reset_user', $result['fullname']);

        return response()->json([
            'success'  => true,
            'token'    => $token,
            'fullname' => $result['fullname'],
        ]);
    }

    // ----------------------------------------------------------
    // STEP 2: Eksekusi factory reset
    // ----------------------------------------------------------
    public function execute(Request $request)
    {
        $request->validate([
            'token'       => 'required|string',
            'confirm_text'=> 'required|string',
        ]);

        // Backup wajib dibuat terlebih dahulu
        if (!Session::has('backup_created_at')) {
            return response()->json(['success' => false, 'error' => 'Backup data harus dibuat terlebih dahulu sebelum factory reset.']);
        }

        // Validasi token session
        $sessionToken    = Session::get('factory_reset_token');
        $verifiedAt      = Session::get('factory_reset_verified_at');
        $verifiedUser    = Session::get('factory_reset_user', 'Unknown');

        if (!$sessionToken || $request->token !== $sessionToken) {
            return response()->json(['success' => false, 'error' => 'Token tidak valid. Lakukan verifikasi ulang.']);
        }

        // Token berlaku maksimal 10 menit
        if (now()->timestamp - $verifiedAt > 600) {
            Session::forget(['factory_reset_token', 'factory_reset_verified_at', 'factory_reset_user']);
            return response()->json(['success' => false, 'error' => 'Sesi verifikasi sudah kadaluarsa. Silakan verifikasi ulang.']);
        }

        // Konfirmasi teks
        if (strtoupper(trim($request->confirm_text)) !== 'RESET') {
            return response()->json(['success' => false, 'error' => 'Ketik RESET (huruf kapital) untuk konfirmasi.']);
        }

        // Eksekusi reset
        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            $tables = [
                'delivery_order_payments',
                'delivery_shipments',
                'delivery_order_items',
                'delivery_orders',
                'stock_request_items',
                'stock_requests',
                'stock_opname_items',
                'stock_opnames',
                'product_stocks',
                'stock_transfer_items',
                'stock_transfers',
                'transaction_items',
                'transactions',
                'cash_registers',
                'erp_sync_logs',
                'customers',
                'products',
                'categories',
            ];

            foreach ($tables as $table) {
                DB::table($table)->truncate();
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            // Bersihkan cache settings agar tidak ada data stale
            foreach (['erpnext_url','erpnext_api_key','erpnext_api_secret','erpnext_company','erpnext_pos_profile'] as $key) {
                \Illuminate\Support\Facades\Cache::forget("setting_{$key}");
            }

            Session::forget(['factory_reset_token', 'factory_reset_verified_at', 'factory_reset_user', 'backup_created_at']);

            Log::warning("FACTORY RESET dieksekusi oleh ERPNext user: {$verifiedUser}, local user: " . auth()->user()->name . " (ID: " . auth()->id() . ")");

            return response()->json(['success' => true]);

        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Log::error("Factory reset gagal: " . $e->getMessage());
            return response()->json(['success' => false, 'error' => 'Reset gagal: ' . $e->getMessage()]);
        }
    }
}
