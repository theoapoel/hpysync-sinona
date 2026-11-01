<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Session;

class BackupController extends Controller
{
    // Tables that are backed up / restored (same set that factory reset clears)
    private const TABLES = [
        'categories',
        'products',
        'customers',
        'transactions',
        'transaction_items',
        'stock_transfers',
        'stock_transfer_items',
        'delivery_orders',
        'delivery_order_items',
        'delivery_shipments',
        'delivery_order_payments',
        'stock_requests',
        'stock_request_items',
        'product_stocks',
        'stock_opnames',
        'stock_opname_items',
        'erp_sync_logs',
    ];

    public function download()
    {
        ini_set('memory_limit', '512M');
        set_time_limit(300);
        $data = [];
        foreach (self::TABLES as $table) {
            $data[$table] = DB::table($table)->get()->map(fn($r) => (array) $r)->all();
        }

        $backup = [
            'meta' => [
                'app'        => 'LaraPos',
                'version'    => 1,
                'created_at' => now()->toIso8601String(),
                'created_by' => auth()->user()->name,
                'counts'     => array_map('count', $data),
            ],
            'data' => $data,
        ];

        Session::put('backup_created_at', now()->timestamp);

        $filename = 'larapos-backup-' . now()->format('Y-m-d_His') . '.json';

        Log::info("Backup dibuat oleh: " . auth()->user()->name . " (ID: " . auth()->id() . ")");

        return response()->streamDownload(function () use ($backup) {
            echo json_encode($backup, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        }, $filename, ['Content-Type' => 'application/json']);
    }

    public function restorePage()
    {
        return view('backup.restore');
    }

    public function restore(Request $request)
    {
        $request->validate([
            'backup_file'  => 'required|file|max:102400', // 100 MB max
            'confirm_text' => 'required|string',
        ]);

        if (strtoupper(trim($request->confirm_text)) !== 'RESTORE') {
            return back()->with('error', 'Ketik RESTORE (huruf kapital) untuk konfirmasi.');
        }

        $content = file_get_contents($request->file('backup_file')->getPathname());
        $backup  = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE || !isset($backup['meta'], $backup['data'])) {
            return back()->with('error', 'File backup tidak valid atau formatnya tidak dikenali.');
        }

        if (($backup['meta']['app'] ?? '') !== 'LaraPos') {
            return back()->with('error', 'File ini bukan file backup LaraPos.');
        }

        try {
            DB::statement('SET FOREIGN_KEY_CHECKS=0');

            foreach (self::TABLES as $table) {
                DB::table($table)->truncate();

                if (!empty($backup['data'][$table])) {
                    $rows = array_map(fn($r) => (array) $r, $backup['data'][$table]);
                    foreach (array_chunk($rows, 500) as $chunk) {
                        DB::table($table)->insert($chunk);
                    }
                }
            }

            DB::statement('SET FOREIGN_KEY_CHECKS=1');

            Log::info("Restore backup berhasil oleh: " . auth()->user()->name .
                " | Backup: " . ($backup['meta']['created_at'] ?? 'unknown'));

            $backupDate = \Carbon\Carbon::parse($backup['meta']['created_at'])->format('d/m/Y H:i');

            return redirect()->route('dashboard')
                ->with('success', "Restore berhasil! Data dipulihkan dari backup tanggal {$backupDate}.");

        } catch (\Exception $e) {
            DB::statement('SET FOREIGN_KEY_CHECKS=1');
            Log::error("Restore backup gagal: " . $e->getMessage());
            return back()->with('error', 'Restore gagal: ' . $e->getMessage());
        }
    }
}
