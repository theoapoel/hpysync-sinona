<?php
namespace App\Http\Controllers;
use App\Models\Setting;
use App\Models\Transaction;
use Illuminate\Http\Request;

class TransactionController extends Controller {
    public function index(Request $request) {
        $query = Transaction::with(['user','customer'])->latest();
        // Cakupan laporan (Pengaturan Toko): 'all' = semua transaksi toko,
        // 'user' = kasir non-manager hanya melihat transaksinya sendiri.
        $user = auth()->user();
        if (Setting::reportScopedByUser() && $user && ! $user->isManager()) {
            $query->where('user_id', $user->id);
        }
        if ($request->search) {
            $query->where('invoice_no','LIKE',"%{$request->search}%");
        }
        if ($request->erp_invoice) {
            $query->where('erp_pos_invoice','LIKE',"%{$request->erp_invoice}%");
        }
        // Batas rentang tanggal (Pengaturan Toko): bila 'today', semua role selain
        // admin dikunci ke tanggal hari ini — filter dari request diabaikan.
        $dateLocked = Setting::reportDateLocked();
        if ($dateLocked) {
            $query->whereDate('created_at', today());
        } else {
            if ($request->date_from) $query->whereDate('created_at','>=',$request->date_from);
            if ($request->date_to) $query->whereDate('created_at','<=',$request->date_to);
        }
        if ($request->status) $query->where('status',$request->status);
        if ($request->payment_method) $query->where('payment_method',$request->payment_method);
        // Ringkasan seluruh hasil filter (bukan hanya halaman yang tampil).
        // Nilai uang dihitung dari transaksi selesai saja agar yang dibatalkan
        // tidak menggelembungkan total.
        $summary = (clone $query)->without(['user','customer'])->where('status','completed')
            ->selectRaw('COALESCE(SUM(total),0) as total_amount')
            ->reorder()->first();

        $transactions = $query->paginate(20)->withQueryString();

        // Keadaan invoice di ERP HPY untuk baris yang tampil saja (1 request per
        // halaman). Tujuannya menangkap invoice yang dibatalkan/dihapus di ERP
        // tetapi di sini masih tercatat selesai — pembatalan di ERP tidak mengubah
        // data lokal. Kalau ERP tidak bisa dihubungi, halaman tetap tampil dan
        // kolomnya diberi tanda "tidak terperiksa", bukan disimpulkan aman.
        $erpNames = collect($transactions->items())->pluck('erp_pos_invoice')->filter()->all();
        $erpCheck = $erpNames
            ? (new \App\Services\ErpNextService)->fetchPosInvoiceStates($erpNames)
            : ['success' => true, 'data' => []];
        $erpStates = $erpCheck['data'];
        $erpCheckFailed = ! $erpCheck['success'];

        // Pilihan tipe bayar = metode dari POS Profile ERP + metode yang pernah
        // dipakai di transaksi lama (agar data lama tetap bisa difilter).
        $paymentMethods = collect(pos_payment_methods())
            ->pluck('mode_of_payment')
            ->merge(Transaction::select('payment_method')->distinct()->pluck('payment_method'))
            ->map(fn ($m) => trim((string) $m))
            ->filter()
            // ERP menyimpan "Cash" sedangkan transaksi lama "CASH" — samakan agar
            // tidak muncul dua kali di dropdown (perbandingan DB sendiri case-insensitive).
            ->unique(fn ($m) => mb_strtoupper($m))
            ->sort(fn ($a, $b) => strcasecmp($a, $b))
            ->values();

        return view('transactions.index', compact('transactions','paymentMethods','summary','erpStates','erpCheckFailed','dateLocked'));
    }

    public function show(Transaction $transaction) {
        $transaction->load('items.product','customer','user');
        return view('transactions.show', compact('transaction'));
    }

    /**
     * Role di ERP HPY yang boleh membatalkan transaksi lokal.
     */
    private const ERP_CANCEL_ROLE = 'Accounts Manager';

    /**
     * Pemeriksaan sebelum konfirmasi: apakah user yang menekan tombol Batalkan
     * memegang role Accounts Manager di ERP HPY. Dipanggil dari UI supaya user
     * tidak sempat mengonfirmasi pembatalan yang toh akan ditolak.
     *
     * Ini hanya untuk pengalaman pemakaian — cancel() memeriksa ulang sendiri,
     * karena hasil pemeriksaan di sisi klien tidak bisa dipercaya.
     */
    public function cancelCheck(Transaction $transaction) {
        if ($transaction->status !== 'completed') {
            return response()->json(['allowed'=>false,'error'=>'Transaksi tidak bisa dibatalkan'],422);
        }

        $check = $this->erpCancelPermission();

        return response()->json([
            'allowed'         => $check['has_role'],
            'error'           => $check['has_role'] ? null : $this->erpCancelDenialMessage($check),
            'erp_pos_invoice' => $transaction->erp_pos_invoice,
        ]);
    }

    public function cancel(Transaction $transaction) {
        if ($transaction->status !== 'completed') {
            return response()->json(['success'=>false,'error'=>'Transaksi tidak bisa dibatalkan'],422);
        }

        // Kewenangan membatalkan ditentukan oleh role di ERP HPY, bukan role lokal —
        // pembatalan transaksi menyentuh pembukuan, jadi yang berlaku adalah daftar
        // wewenang di ERP. Ditolak juga bila ERP tidak bisa dihubungi (fail-closed).
        $check = $this->erpCancelPermission();
        if (! $check['has_role']) {
            return response()->json([
                'success' => false,
                'error'   => $this->erpCancelDenialMessage($check),
            ], 403);
        }

        // Restore stock
        foreach ($transaction->items as $item) {
            if ($item->product && $item->product->track_stock) {
                $item->product->increment('stock', $item->quantity);
            }
        }
        $transaction->update(['status'=>'cancelled']);

        // Pembatalan ini hanya berlaku di sini. Invoice yang sudah tersinkron tetap
        // hidup di ERP HPY dan masih terhitung sebagai penjualan sampai dibatalkan
        // manual di sana — user harus diberi tahu, bukan dibiarkan mengira selesai.
        return response()->json([
            'success'         => true,
            'erp_pos_invoice' => $transaction->erp_pos_invoice,
            'warning'         => $transaction->erp_pos_invoice
                ? "Transaksi dibatalkan di sini saja. Pastikan POS Invoice {$transaction->erp_pos_invoice} juga dibatalkan di ERP HPY, kalau tidak penjualannya tetap terhitung di sana."
                : null,
        ]);
    }

    private function erpCancelPermission(): array {
        return (new \App\Services\ErpNextService)
            ->userHasErpRole(auth()->user()?->email, self::ERP_CANCEL_ROLE);
    }

    private function erpCancelDenialMessage(array $check): string {
        if (! $check['success']) {
            return 'Tidak bisa memastikan wewenang Anda di ERP HPY ('.($check['error'] ?? 'gagal menghubungi ERP').'). Pembatalan dihentikan demi keamanan.';
        }

        return $check['error']
            ?? 'Pembatalan transaksi hanya untuk pemegang role '.self::ERP_CANCEL_ROLE.' di ERP HPY.';
    }
}
