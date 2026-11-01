<?php

namespace App\Http\Controllers;

use App\Services\ErpNextService;
use Illuminate\Http\Request;

class OnlineReportController extends Controller
{
    public function index()
    {
        $posProfile = \App\Models\Setting::get('erpnext_pos_profile', '');
        $dateLocked = \App\Models\Setting::reportDateLocked();
        return view('reports.online', compact('posProfile', 'dateLocked'));
    }

    public function fetch(Request $request)
    {
        $request->validate([
            'date_from'   => 'required|date',
            'date_to'     => 'required|date|after_or_equal:date_from',
            'pos_profile' => 'nullable|string|max:255',
        ]);

        // Batas rentang tanggal (Pengaturan Toko): bila 'today', semua role selain
        // admin dikunci ke tanggal hari ini — rentang dari request diabaikan.
        $dateFrom = $request->date_from;
        $dateTo   = $request->date_to;
        if (\App\Models\Setting::reportDateLocked()) {
            $dateFrom = $dateTo = today()->toDateString();
        }

        $erp    = new ErpNextService();
        $result = $erp->fetchPosInvoices(
            $dateFrom,
            $dateTo,
            $request->input('pos_profile', '')
        );

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 422);
        }

        $invoices = collect($result['data']);

        // Cocokkan nomor invoice HPY dengan transaksi lokal (erp_pos_invoice → invoice_no)
        $localMap = \App\Models\Transaction::whereIn('erp_pos_invoice', $invoices->pluck('name')->filter()->all())
            ->pluck('invoice_no', 'erp_pos_invoice');

        // Metode bayar per transaksi diambil dari report POS Register (split payment
        // muncul sebagai mode gabungan, mis. "BCA QR, CASH").
        $register = $erp->fetchPosRegister(
            $dateFrom,
            $dateTo,
            $request->input('pos_profile', '')
        );

        if (!$register['success']) {
            return response()->json(['success' => false, 'error' => $register['error']], 422);
        }

        $modeByInvoice = collect($register['data'])
            ->pluck('mode_of_payment', 'pos_invoice')
            ->all();

        $invoices = $invoices->map(function ($inv) use ($localMap, $modeByInvoice) {
            $inv['local_invoice']    = $localMap[$inv['name']] ?? null;
            $inv['mode_of_payment']  = $modeByInvoice[$inv['name']] ?? null;
            return $inv;
        });

        // Statistik & chart dihitung dari POS Register, bukan dari $invoices: daftar
        // invoice dibatasi 1.000 baris, sedangkan POS Register mengembalikan seluruh
        // rentang. Kalau dihitung dari $invoices, kartu total akan lebih kecil daripada
        // rincian metode bayar di halaman yang sama.
        $registerRows = collect($register['data']);

        $totalSales = $registerRows->sum(fn($r) => (float) $r['grand_total']);
        $totalCount = $registerRows->count();
        $avgPerTx   = $totalCount > 0 ? round($totalSales / $totalCount) : 0;

        // Agregasi per hari untuk chart
        $dailyData = $registerRows
            ->groupBy('posting_date')
            ->map(fn($group) => [
                'count' => $group->count(),
                'total' => $group->sum(fn($r) => (float) $r['grand_total']),
            ])
            ->sortKeys();

        // Agregasi per metode pembayaran, dari POS Register. Nilainya pakai grand_total
        // (bukan paid_amount) — lihat catatan di ErpNextService::fetchPosRegister().
        $paymentData = $registerRows
            ->groupBy(fn($r) => $r['mode_of_payment'] ?: 'Tanpa Metode')
            ->map(fn($g, $mode) => [
                'mode_of_payment' => $mode,
                'count'           => $g->count(),
                'total'           => $g->sum(fn($r) => (float) $r['grand_total']),
            ])
            ->sortByDesc('total')
            ->values()
            ->all();

        return response()->json([
            'success'   => true,
            'invoices'  => $invoices->values()->all(),
            'truncated' => $result['truncated'],
            'stats'     => [
                'total_sales' => $totalSales,
                'total_count' => $totalCount,
                'avg_per_tx'  => $avgPerTx,
                'daily_data'  => $dailyData,
                'payment_data' => $paymentData,
            ],
        ]);
    }

    public function detail(string $name)
    {
        $erp    = new ErpNextService();
        $result = $erp->fetchPosInvoiceDetail($name);

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 422);
        }

        return response()->json(['success' => true, 'data' => $result['data']]);
    }
}
