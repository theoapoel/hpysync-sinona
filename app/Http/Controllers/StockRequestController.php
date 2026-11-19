<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\StockRequest;
use App\Models\StockRequestItem;
use App\Services\ErpNextService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StockRequestController extends Controller
{
    public function index(Request $request)
    {
        $query = StockRequest::with('requester', 'deliveryOrder')
            ->orderByDesc('id');

        if ($request->status) {
            $query->where('status', $request->status);
        }
        if ($request->kitchen_status) {
            $query->where('kitchen_status', $request->kitchen_status);
        }
        if ($request->date_from) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->date_to) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }
        if ($request->search) {
            $query->where('request_no', 'like', '%' . $request->search . '%');
        }

        $requests = $query->paginate(20)->withQueryString();
        return view('stock-requests.index', compact('requests'));
    }

    public function create()
    {
        $products = Product::where('is_active', true)->inItemGroups('stock_request_item_groups')->orderBy('name')
            ->get(['id', 'name', 'sku', 'erp_item_code', 'unit']);
        return view('stock-requests.create', compact('products'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'needed_date'        => 'nullable|date',
            'needed_time'        => 'nullable|date_format:H:i',
            'notes'              => 'nullable|string|max:1000',
            'items'              => 'required|array|min:1',
            'items.*.item_name'  => 'required|string|max:200',
            'items.*.qty'        => 'required|numeric|min:0.01',
            'items.*.uom'        => 'required|string|max:30',
        ]);

        $stockRequest = DB::transaction(function () use ($request) {
            $sr = StockRequest::create([
                'request_no'   => StockRequest::generateRequestNo(),
                'requested_by' => auth()->id(),
                'status'       => 'draft',
                'needed_date'  => $request->needed_date,
                'needed_time'  => $request->needed_time,
                'notes'        => $request->notes,
            ]);

            foreach ($request->items as $row) {
                $productId = $row['product_id'] ? (int)$row['product_id'] : null;
                $product   = $productId ? Product::find($productId) : null;
                StockRequestItem::create([
                    'stock_request_id' => $sr->id,
                    'product_id'       => $productId,
                    'item_name'        => $row['item_name'],
                    'item_code'        => $product?->erp_item_code ?? $product?->sku ?? null,
                    'qty'              => $row['qty'],
                    'uom'              => $row['uom'],
                    'notes'            => $row['notes'] ?? null,
                ]);
            }

            return $sr;
        });

        return redirect()->route('stock-requests.show', $stockRequest)
            ->with('success', 'Permintaan FG ' . $stockRequest->request_no . ' berhasil dibuat.');
    }

    public function show(StockRequest $stockRequest)
    {
        $stockRequest->load('requester', 'items.product', 'deliveryOrder.customer');
        return view('stock-requests.show', ['req' => $stockRequest]);
    }

    public function submit(StockRequest $stockRequest)
    {
        if ($stockRequest->status !== 'draft') {
            return back()->with('error', 'Hanya draft yang bisa diajukan.');
        }

        $stockRequest->update([
            'status'         => 'submitted',
            'kitchen_status' => 'requested',
        ]);

        // Auto-sync to ERPNext Material Request
        $syncMsg = '';
        try {
            $erp = new ErpNextService();
            if ($erp->isConfigured()) {
                $stockRequest->load('items.product');
                $result  = $erp->createMaterialRequest($stockRequest);
                $syncMsg = $result['success']
                    ? ' MR ERP: ' . ($result['docname'] ?? 'synced') . '.'
                    : ' (Sync ERP gagal: ' . ($result['error'] ?? '') . ')';
            }
        } catch (\Exception $e) {
            // silent — sync failure must not block submission
        }

        return back()->with('success', 'Permintaan FG diajukan dan masuk antrian dapur.' . $syncMsg);
    }

    public function cancel(StockRequest $stockRequest)
    {
        if ($stockRequest->status === 'cancelled') {
            return back()->with('error', 'Sudah dibatalkan.');
        }
        // Sudah "Selesai" berarti Material Request-nya sudah disahkan dan stoknya
        // sudah/akan terbentuk di ERP HPY — membatalkan di sini tidak menariknya lagi.
        if ($stockRequest->kitchen_status === 'done') {
            return back()->with('error', 'Permintaan sudah selesai diproduksi, tidak bisa dibatalkan.');
        }

        // Material Request yang masih Draft ikut dihapus supaya tidak tertinggal
        // di ERP HPY lalu disahkan orang lain tanpa sengaja.
        $note = '';
        if ($stockRequest->erp_material_request) {
            try {
                $erp = new ErpNextService();
                if ($erp->isConfigured()) {
                    $result = $erp->deleteDraftMaterialRequest($stockRequest);
                    if (! $result['success'] && ! empty($result['submitted'])) {
                        return back()->with('error', 'Tidak dibatalkan: ' . ($result['error'] ?? '') . ' Produksinya sudah berjalan.');
                    }
                    if (! $result['success']) {
                        $note = ' (Material Request ' . $stockRequest->erp_material_request . ' belum terhapus di ERP HPY: ' . ($result['error'] ?? '') . ')';
                    }
                }
            } catch (\Exception $e) {
                $note = ' (Material Request ' . $stockRequest->erp_material_request . ' belum terhapus di ERP HPY.)';
            }
        }

        $stockRequest->update(['status' => 'cancelled', 'kitchen_status' => null]);
        return back()->with('success', 'Permintaan FG dibatalkan.' . $note);
    }

    public function syncErp(StockRequest $stockRequest)
    {
        if ($stockRequest->erp_sync_status === 'synced') {
            return response()->json(['success' => false, 'error' => 'Sudah pernah disync.']);
        }
        $stockRequest->load('items.product');
        $erp    = new ErpNextService();
        $result = $erp->createMaterialRequest($stockRequest);

        // Kalau dapur sudah menekan "Selesai" sebelum Material Request sempat
        // terbuat, sahkan sekarang — kalau tidak MR tertinggal Draft dan stoknya
        // tidak pernah terbentuk.
        if (($result['success'] ?? false) && $stockRequest->kitchen_status === 'done') {
            $stockRequest->refresh();
            $submit = $erp->submitMaterialRequest($stockRequest);
            if (! ($submit['success'] ?? false)) {
                $result = ['success' => false, 'error' => 'Material Request ' . ($result['docname'] ?? '') . ' dibuat tapi gagal disahkan: ' . ($submit['error'] ?? '')];
            }
        }

        return response()->json($result);
    }

    public function updateKitchenStatus(Request $request, StockRequest $stockRequest)
    {
        $request->validate([
            'kitchen_status' => 'required|in:requested,preparing,done',
        ]);

        $newStatus = $request->kitchen_status;
        $data = ['kitchen_status' => $newStatus];

        if ($newStatus === 'preparing' && !$stockRequest->kitchen_started_at) {
            $data['kitchen_started_at'] = now();
        } elseif ($newStatus === 'done' && !$stockRequest->kitchen_done_at) {
            $data['kitchen_done_at'] = now();
        }

        $stockRequest->update($data);

        // Saat selesai: submit Material Request di ERP (docstatus=1).
        // Bila MR belum sempat terbuat (mis. ERP tak terjangkau saat diajukan),
        // buat dulu — kalau tidak "Selesai" tidak membentuk stok apa pun.
        if ($newStatus === 'done') {
            try {
                $erp = new ErpNextService();
                if ($erp->isConfigured()) {
                    if (! $stockRequest->erp_material_request) {
                        $stockRequest->load('items.product');
                        $erp->createMaterialRequest($stockRequest);
                        $stockRequest->refresh();
                    }
                    if ($stockRequest->erp_material_request) {
                        $erp->submitMaterialRequest($stockRequest);
                    }
                }
            } catch (\Exception $e) {
                // silent — jangan block perubahan status kitchen
            }
        }

        return response()->json(['success' => true, 'kitchen_status' => $newStatus]);
    }
}
