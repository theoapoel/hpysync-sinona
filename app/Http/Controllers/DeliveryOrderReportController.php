<?php

namespace App\Http\Controllers;

use App\Models\DeliveryOrder;
use App\Services\ErpNextService;
use Carbon\Carbon;
use Illuminate\Http\Request;

/**
 * Laporan penjualan Delivery Order — sumber lokal (tabel delivery_orders), tanpa
 * memanggil ERP saat listing supaya cepat. Verifikasi Sales Invoice & Delivery Note
 * ke ERP HPY dilakukan on-demand per order lewat checkErp() (AJAX).
 */
class DeliveryOrderReportController extends Controller
{
    public function index(Request $request)
    {
        // Rentang tanggal berdasarkan tanggal pengiriman (delivery_date). Default: bulan berjalan.
        // Batas rentang tanggal (Pengaturan Toko): bila 'today', semua role selain
        // admin dikunci ke tanggal hari ini — rentang dari request diabaikan.
        $dateLocked = \App\Models\Setting::reportDateLocked();

        if ($dateLocked) {
            $from = Carbon::today()->startOfDay();
            $to = Carbon::today()->endOfDay();
        } else {
            $from = $request->filled('date_from')
                ? Carbon::parse($request->date_from)->startOfDay()
                : Carbon::today()->startOfMonth();
            $to = $request->filled('date_to')
                ? Carbon::parse($request->date_to)->endOfDay()
                : Carbon::today()->endOfMonth();
        }

        $status = $request->input('status', '');       // '', draft, confirmed, delivering, completed
        $payment = $request->input('payment', '');      // '', unpaid, partial, paid

        $orders = DeliveryOrder::with(['customer', 'payments', 'shipments'])
            ->whereBetween('delivery_date', [$from->toDateString(), $to->toDateString()])
            ->when($status !== '', fn ($q) => $q->where('status', $status))
            ->when($payment !== '', fn ($q) => $q->where('payment_status', $payment))
            ->orderByDesc('delivery_date')
            ->orderByDesc('id')
            ->get();

        // Ringkasan dihitung dari koleksi lokal.
        $totalSales = $orders->sum(fn ($o) => (float) $o->total);
        $totalPaid = $orders->sum(fn ($o) => $o->totalPaid());
        $totalOutstanding = $orders->sum(fn ($o) => $o->outstanding());
        $count = $orders->count();

        // Berapa order yang invoice HPY-nya sudah terbit (referensi lokal terisi).
        $withInvoice = $orders->filter(fn ($o) => ! empty($o->erp_sales_invoice))->count();

        return view('reports.delivery-order', compact(
            'orders', 'from', 'to', 'status', 'payment', 'dateLocked',
            'totalSales', 'totalPaid', 'totalOutstanding', 'count', 'withInvoice'
        ));
    }

    /**
     * Verifikasi ke ERP HPY: status Sales Invoice + Delivery Note untuk satu DO.
     */
    public function checkErp(DeliveryOrder $deliveryOrder)
    {
        $deliveryOrder->loadMissing('shipments', 'payments');

        $erp = new ErpNextService;
        $result = $erp->fetchDeliveryOrderErpStatus($deliveryOrder);

        if (! $result['success']) {
            return response()->json(['success' => false, 'error' => $result['error']], 422);
        }

        return response()->json([
            'success' => true,
            'order_no' => $deliveryOrder->order_no,
            'erp_sales_order' => $deliveryOrder->erp_sales_order,
            'sales_invoice' => $result['sales_invoice'],
            'delivery_notes' => $result['delivery_notes'],
            'payment_entries' => $result['payment_entries'],
        ]);
    }
}
