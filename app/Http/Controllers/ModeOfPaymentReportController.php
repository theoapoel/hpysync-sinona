<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use App\Services\ErpNextService;
use Illuminate\Http\Request;

class ModeOfPaymentReportController extends Controller
{
    public function index()
    {
        $posProfile = Setting::get('erpnext_pos_profile', '');

        // Kasir (non-manager) hanya melihat transaksinya sendiri — bila pengaturan
        // toko "Cakupan Laporan Transaksi" diset per kasir.
        $user = auth()->user();
        $scopedToUser = Setting::reportScopedByUser() && $user && ! $user->isManager();
        $scopedUserName = $scopedToUser ? $user->name : null;

        $dateLocked = Setting::reportDateLocked();

        return view('reports.mode-of-payment', compact('posProfile', 'scopedToUser', 'scopedUserName', 'dateLocked'));
    }

    public function fetch(Request $request)
    {
        $request->validate([
            'date_from' => 'required|date',
            'date_to' => 'required|date|after_or_equal:date_from',
            'pos_profile' => 'nullable|string|max:255',
        ]);

        $erp = new ErpNextService;

        // Kasir (non-manager) dibatasi ke invoice miliknya sendiri
        // (owner POS Invoice = email ERP User kasir), hanya bila cakupan laporan
        // diset per kasir. Default 'all' → semua invoice toko.
        $user = auth()->user();
        $owner = (Setting::reportScopedByUser() && $user && ! $user->isManager()) ? $user->email : '';

        // Sumber tunggal: report POS Register — satu baris per transaksi, sudah
        // membawa metode bayarnya (split payment jadi mode gabungan "BCA QR, CASH").
        // Batas rentang tanggal (Pengaturan Toko): bila 'today', semua role selain
        // admin dikunci ke tanggal hari ini — rentang dari request diabaikan.
        $dateFrom = $request->date_from;
        $dateTo = $request->date_to;
        if (Setting::reportDateLocked()) {
            $dateFrom = $dateTo = today()->toDateString();
        }

        $result = $erp->fetchPosRegister(
            $dateFrom,
            $dateTo,
            $request->input('pos_profile', ''),
            $owner
        );

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 422);
        }

        // Nilai transaksi diambil dari grand_total, bukan paid_amount — lihat catatan
        // di ErpNextService::fetchPosRegister().
        $matrix = [];      // tanggal => mode => ['count'=>int,'total'=>float]
        $byCashier = [];   // owner   => mode => ['count'=>int,'total'=>float]
        $modeTotals = [];  // mode    => total (untuk urutan kolom)
        $txPerDate = [];

        foreach ($result['data'] as $row) {
            $date = $row['posting_date'] ?? null;
            if ($date === null) {
                continue;
            }

            $mode = $row['mode_of_payment'] ?: 'Tanpa Metode';
            $amount = (float) ($row['grand_total'] ?? 0);
            $cashier = $row['owner'] ?? 'Lainnya';

            $matrix[$date][$mode]['count'] = ($matrix[$date][$mode]['count'] ?? 0) + 1;
            $matrix[$date][$mode]['total'] = ($matrix[$date][$mode]['total'] ?? 0) + $amount;

            $byCashier[$cashier][$mode]['count'] = ($byCashier[$cashier][$mode]['count'] ?? 0) + 1;
            $byCashier[$cashier][$mode]['total'] = ($byCashier[$cashier][$mode]['total'] ?? 0) + $amount;

            $modeTotals[$mode] = ($modeTotals[$mode] ?? 0) + $amount;
            $txPerDate[$date] = ($txPerDate[$date] ?? 0) + 1;
        }

        arsort($modeTotals);
        $modes = array_keys($modeTotals);
        ksort($matrix);
        ksort($byCashier);

        // Bangun baris tabel per tanggal + total kolom
        $rows = [];
        $modeTotals = array_fill_keys($modes, ['count' => 0, 'total' => 0.0]);
        $grandCount = 0;
        $grandTotal = 0.0;

        foreach ($matrix as $date => $byMode) {
            $cells = [];
            $rowTotal = 0.0;
            $rowCount = 0;
            foreach ($modes as $mode) {
                $cell = $byMode[$mode] ?? ['count' => 0, 'total' => 0.0];
                $cells[$mode] = $cell;
                $rowTotal += $cell['total'];
                $rowCount += $cell['count'];
                $modeTotals[$mode]['count'] += $cell['count'];
                $modeTotals[$mode]['total'] += $cell['total'];
            }
            $rows[] = [
                'date' => $date,
                'tx_count' => $txPerDate[$date] ?? 0,
                'cells' => $cells,
                'total' => $rowTotal,
                'count' => $rowCount,
            ];
            $grandCount += $rowCount;
            $grandTotal += $rowTotal;
        }

        // Rekap per kasir (owner POS Invoice = email ERP User). Petakan email → nama lokal.
        $owners = array_keys($byCashier);
        $ownerNames = \App\Models\User::whereIn('email', $owners)->pluck('name', 'email')->all();

        $cashierRows = [];
        foreach ($byCashier as $owner => $byMode) {
            $cells = [];
            $rowTotal = 0.0;
            $rowCount = 0;
            foreach ($modes as $mode) {
                $cell = $byMode[$mode] ?? ['count' => 0, 'total' => 0.0];
                $cells[$mode] = $cell;
                $rowTotal += $cell['total'];
                $rowCount += $cell['count'];
            }
            $cashierRows[] = [
                'cashier' => $ownerNames[$owner] ?? $owner,
                'email' => $owner,
                'cells' => $cells,
                'total' => $rowTotal,
                'count' => $rowCount,
            ];
        }
        // Urutkan kasir dari total terbesar
        usort($cashierRows, fn ($a, $b) => $b['total'] <=> $a['total']);

        return response()->json([
            'success' => true,
            // POS Register mengembalikan seluruh baris rentang tanggal (tanpa paging).
            'truncated' => false,
            'modes' => $modes,
            'rows' => $rows,
            'cashier_rows' => $cashierRows,
            'totals' => [
                'per_mode' => $modeTotals,
                'grand_count' => $grandCount,
                'grand_total' => $grandTotal,
            ],
        ]);
    }
}
