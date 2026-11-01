<?php

namespace App\Http\Controllers;

use App\Models\PosShift;
use App\Models\Setting;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class PosShiftController extends Controller
{
    private function tz(): string
    {
        return Setting::get('timezone', 'Asia/Jakarta') ?: 'Asia/Jakarta';
    }

    /** Respons standar saat fitur Buka/Tutup Kasir dimatikan. */
    private function disabledResponse()
    {
        return response()->json([
            'success' => false,
            'feature_disabled' => true,
            'has_open_shift' => false,
            'shift' => null,
            'error' => 'Fitur Buka/Tutup Kasir sedang dinonaktifkan.',
        ], 200);
    }

    /** Status shift kasir yang sedang login (untuk POS mengetahui perlu buka kasir atau tidak). */
    public function current()
    {
        if (! PosShift::featureEnabled()) {
            return $this->disabledResponse();
        }

        $shift = PosShift::openFor(auth()->id());

        // Shift yang dibuka saat internet mati: coba susulkan ke ERP begitu online lagi.
        if ($shift && ! $shift->erp_opening_entry) {
            $this->backfillOpeningEntry($shift);
        }

        return response()->json([
            'has_open_shift' => (bool) $shift,
            'shift' => $shift ? [
                'id' => $shift->id,
                'opened_at' => $shift->opened_at->toIso8601String(),
                'opening_cash' => (float) $shift->opening_cash,
                'erp_opening_entry' => $shift->erp_opening_entry,
                'offline' => ! $shift->erp_opening_entry,
            ] : null,
        ]);
    }

    /**
     * Buka Kasir → buat POS Opening Entry di ERP + shift lokal.
     * Bila internet/ERP tidak bisa dijangkau, shift tetap dibuka secara lokal
     * (erp_opening_entry kosong, status pending) dan disusulkan ke ERP nanti.
     */
    public function open(Request $request)
    {
        if (! PosShift::featureEnabled()) {
            return $this->disabledResponse();
        }

        $request->validate([
            'opening_cash' => 'required|numeric|min:0',
        ]);

        $user = auth()->user();

        if (PosShift::openFor($user->id)) {
            return response()->json(['success' => false, 'error' => 'Masih ada shift terbuka. Tutup dulu sebelum membuka lagi.'], 422);
        }
        if (! $user->email) {
            return response()->json(['success' => false, 'error' => 'Akun kasir tidak punya email (harus sama dengan ERP User).'], 422);
        }

        $erp = new ErpNextService;
        $openingCash = (float) $request->opening_cash;
        $openingName = null;
        $offlineError = null;

        // Cek koneksi sekali di depan (±3 detik) supaya saat internet mati kasir
        // tidak menunggu tiap panggilan ERP timeout satu per satu.
        if (! $erp->quickPing()) {
            $offlineError = 'ERP HPY tidak dapat dijangkau (internet mati).';
        } else {
            // Bila kasir masih punya opening entry Open di ERP (mis. sisa dari sesi lain), pakai itu.
            $openingName = $erp->findOpenPosOpeningEntry($user->email);
            if (! $openingName) {
                $result = $erp->createPosOpeningEntry($user->email, $openingCash);
                if ($result['success']) {
                    $openingName = $result['docname'];
                } elseif ($this->isOffline($result)) {
                    $offlineError = $result['error'] ?? 'ERP HPY tidak dapat dijangkau';
                } else {
                    return response()->json(['success' => false, 'error' => 'Gagal buka kasir di ERP HPY: '.($result['error'] ?? 'Unknown')], 422);
                }
            }
        }

        $shift = PosShift::create([
            'user_id' => $user->id,
            'pos_profile' => Setting::get('erpnext_pos_profile', ''),
            'status' => 'open',
            'opened_at' => now(),
            'opening_cash' => $openingCash,
            'erp_opening_entry' => $openingName,
            'erp_sync_status' => $openingName ? 'synced' : 'pending',
            'erp_sync_error' => $offlineError,
        ]);

        return response()->json([
            'success' => true,
            'shift_id' => $shift->id,
            'erp_opening_entry' => $openingName,
            'offline' => ! $openingName,
            'message' => $openingName
                ? 'Kasir dibuka.'
                : 'Kasir dibuka OFFLINE — buka kasir di ERP HPY akan disusulkan otomatis saat internet kembali.',
        ]);
    }

    /** Kegagalan ERP yang berupa masalah jaringan/konfigurasi tak terjangkau (bukan penolakan data). */
    private function isOffline(array $result): bool
    {
        if ($result['network_error'] ?? false) {
            return true;
        }
        $err = strtolower((string) ($result['error'] ?? ''));

        foreach (['network', 'unreachable', 'timed out', 'timeout', 'could not resolve', 'connection refused', 'curl'] as $needle) {
            if (str_contains($err, $needle)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Susulkan POS Opening Entry untuk shift yang dibuka saat offline.
     * Mengisi $shift->erp_opening_entry bila berhasil.
     */
    private function backfillOpeningEntry(PosShift $shift): bool
    {
        $email = $shift->user->email ?? null;
        if (! $email) {
            return false;
        }

        $erp = new ErpNextService;
        if (! $erp->quickPing()) {   // masih offline → jangan buang waktu menunggu timeout
            return false;
        }

        $name = $erp->findOpenPosOpeningEntry($email);
        if (! $name) {
            $result = $erp->createPosOpeningEntry(
                $email,
                (float) $shift->opening_cash,
                $shift->opened_at->setTimezone($this->tz())->format('Y-m-d H:i:s')
            );
            if (! $result['success']) {
                $shift->update(['erp_sync_error' => $result['error'] ?? 'Gagal menyusulkan buka kasir.']);

                return false;
            }
            $name = $result['docname'];
        }

        $shift->update([
            'erp_opening_entry' => $name,
            'erp_sync_status' => 'synced',
            'erp_sync_error' => null,
        ]);

        return true;
    }

    /** Pratinjau rekonsiliasi sebelum tutup: expected per metode + ringkasan penjualan. */
    public function reconcile()
    {
        if (! PosShift::featureEnabled()) {
            return $this->disabledResponse();
        }

        $shift = PosShift::openFor(auth()->id());
        if (! $shift) {
            return response()->json(['success' => false, 'error' => 'Tidak ada shift terbuka.'], 422);
        }

        if (! $shift->erp_opening_entry) {
            $this->backfillOpeningEntry($shift);
        }

        $erp = new ErpNextService;
        $recon = $this->buildRecon($erp, $shift);
        if (! $recon['success']) {
            return response()->json($recon, 422);
        }

        return response()->json([
            'success' => true,
            'modes' => $recon['modes'],
            'totals' => $recon['totals'],
        ]);
    }

    /** Tutup Kasir → buat POS Closing Entry (dengan hitung kas fisik) + tutup shift lokal. */
    public function close(Request $request)
    {
        if (! PosShift::featureEnabled()) {
            return $this->disabledResponse();
        }

        $request->validate([
            'counted' => 'required|array',       // { "CASH": 500000, "BCA QR": 100000, ... }
            'counted.*' => 'numeric|min:0',
        ]);

        $shift = PosShift::openFor(auth()->id());
        if (! $shift) {
            return response()->json(['success' => false, 'error' => 'Tidak ada shift terbuka.'], 422);
        }

        // Shift yang dibuka offline: opening entry harus ada dulu sebelum bisa ditutup di ERP.
        if (! $shift->erp_opening_entry && ! $this->backfillOpeningEntry($shift)) {
            return response()->json([
                'success' => false,
                'error' => 'Kasir ini dibuka saat offline dan belum tercatat di ERP HPY. Tutup kasir butuh koneksi internet — coba lagi setelah internet tersambung.',
            ], 422);
        }
        $shift->refresh();

        $user = auth()->user();
        $erp = new ErpNextService;

        $recon = $this->buildRecon($erp, $shift);
        if (! $recon['success']) {
            return response()->json($recon, 422);
        }

        $counted = $request->counted;
        $result = $erp->createPosClosingEntry(
            $shift->erp_opening_entry,
            $user->email,
            $shift->opened_at->setTimezone($this->tz())->format('Y-m-d H:i:s'),
            $recon,
            $counted
        );

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => 'Gagal tutup kasir di ERP: '.($result['error'] ?? 'Unknown')], 422);
        }

        // Snapshot per metode (expected vs counted) untuk struk & audit.
        $breakdown = [];
        $cashExpected = 0.0;
        $cashCounted = 0.0;
        foreach ($recon['modes'] as $m) {
            $mode = $m['mode_of_payment'];
            $cnt = (float) ($counted[$mode] ?? $m['expected_amount']);
            $breakdown[] = [
                'mode' => $mode,
                'is_cash' => $m['is_cash'],
                'expected' => $m['expected_amount'],
                'counted' => $cnt,
                'difference' => round($cnt - $m['expected_amount'], 2),
            ];
            if ($m['is_cash']) {
                $cashExpected += $m['expected_amount'];
                $cashCounted += $cnt;
            }
        }

        $shift->update([
            'status' => 'closed',
            'closed_at' => now(),
            'expected_cash' => round($cashExpected, 2),
            'counted_cash' => round($cashCounted, 2),
            'cash_difference' => round($cashCounted - $cashExpected, 2),
            'total_sales' => $recon['totals']['grand_total'],
            'invoice_count' => $recon['totals']['invoice_count'],
            'payment_breakdown' => $breakdown,
            'erp_closing_entry' => $result['docname'],
            'erp_sync_status' => 'synced',
        ]);

        return response()->json([
            'success' => true,
            'shift_id' => $shift->id,
            'erp_closing_entry' => $result['docname'],
            'print_url' => route('pos-shift.receipt', $shift),
        ]);
    }

    /** Struk tutup kasir (X/Z report). */
    public function receipt(PosShift $shift)
    {
        abort_unless($shift->user_id === auth()->id() || auth()->user()->isManager(), 403);
        $shift->load('user');

        return view('pos.shift-receipt', ['shift' => $shift]);
    }

    /** Bangun rekonsiliasi untuk shift (rentang tanggal lokal opened_at → hari ini). */
    private function buildRecon(ErpNextService $erp, PosShift $shift): array
    {
        $from = $shift->opened_at->setTimezone($this->tz())->format('Y-m-d');
        $to = Carbon::now($this->tz())->format('Y-m-d');

        return $erp->getShiftReconciliation(
            $shift->user->email,
            $from,
            $to,
            (float) $shift->opening_cash
        );
    }
}
