<?php

namespace App\Http\Controllers;

use App\Models\Warehouse;
use App\Services\ErpNextService;
use Illuminate\Http\Request;

class WarehouseController extends Controller
{
    public function __construct(private ErpNextService $erp) {}

    public function index(Request $request)
    {
        $query = Warehouse::query();

        if ($search = $request->get('search')) {
            $query->where(fn($q) =>
                $q->where('name', 'like', "%$search%")
                  ->orWhere('warehouse_name', 'like', "%$search%")
                  ->orWhere('warehouse_type', 'like', "%$search%")
            );
        }

        if ($request->get('show') === 'active') {
            $query->where('is_active', true);
        } elseif ($request->get('show') === 'leaf') {
            $query->where('is_group', false);
        }

        $warehouses = $query->orderByDesc('is_active')
                            ->orderByDesc('is_default')
                            ->orderByDesc('is_transit')
                            ->orderBy('name')
                            ->paginate(50)->withQueryString();

        $stats = [
            'total'   => Warehouse::count(),
            'active'  => Warehouse::where('is_active', true)->count(),
            'default' => Warehouse::where('is_default', true)->value('warehouse_name')
                         ?: Warehouse::where('is_default', true)->value('name'),
            'transit' => Warehouse::where('is_transit', true)->value('warehouse_name')
                         ?: Warehouse::where('is_transit', true)->value('name'),
            'last_pulled' => Warehouse::max('erp_last_pulled'),
        ];

        return view('warehouses.index', compact('warehouses', 'stats'));
    }

    /** Pull all warehouses from ERPNext and upsert to local DB */
    public function pull()
    {
        $result = $this->erp->getWarehouses();

        if (!$result['success']) {
            return response()->json(['success' => false, 'error' => $result['error'] ?? 'Koneksi ERP HPY gagal.'], 500);
        }

        $now     = now();
        $count   = 0;

        foreach ($result['data'] as $w) {
            Warehouse::updateOrCreate(
                ['name' => $w['name']],
                [
                    'warehouse_name'  => $w['warehouse_name']  ?? $w['name'],
                    'company'         => $w['company']         ?? null,
                    'warehouse_type'  => $w['warehouse_type']  ?? null,
                    'parent_warehouse'=> $w['parent_warehouse'] ?? null,
                    'is_group'        => (bool) ($w['is_group'] ?? false),
                    'erp_last_pulled' => $now,
                ]
            );
            $count++;
        }

        return response()->json([
            'success' => true,
            'message' => "{$count} warehouse berhasil dipull dari ERP HPY.",
            'count'   => $count,
        ]);
    }

    /** Toggle active/inactive */
    public function toggle(Warehouse $warehouse)
    {
        $newActive = !$warehouse->is_active;
        $data = ['is_active' => $newActive];

        // Deactivating: remove default/transit flags
        if (!$newActive) {
            $data['is_default'] = false;
            $data['is_transit'] = false;
        }

        $warehouse->update($data);

        return response()->json([
            'success'   => true,
            'is_active' => $warehouse->is_active,
            'message'   => $warehouse->is_active
                ? "\"{$warehouse->display_name}\" diaktifkan."
                : "\"{$warehouse->display_name}\" dinonaktifkan.",
        ]);
    }

    /** Set as default warehouse for POS transactions */
    public function setDefault(Warehouse $warehouse)
    {
        if (!$warehouse->is_active) {
            return response()->json(['success' => false, 'error' => 'Aktifkan warehouse ini terlebih dahulu.']);
        }
        if ($warehouse->is_group) {
            return response()->json(['success' => false, 'error' => 'Warehouse grup tidak bisa dijadikan default.']);
        }

        Warehouse::where('is_default', true)->update(['is_default' => false]);
        $warehouse->update(['is_default' => true]);

        return response()->json([
            'success' => true,
            'name'    => $warehouse->name,
            'message' => "\"{$warehouse->display_name}\" dijadikan warehouse default transaksi.",
        ]);
    }

    /** Set as in-transit warehouse for stock transfers */
    public function setTransit(Warehouse $warehouse)
    {
        if (!$warehouse->is_active) {
            return response()->json(['success' => false, 'error' => 'Aktifkan warehouse ini terlebih dahulu.']);
        }
        if ($warehouse->is_group) {
            return response()->json(['success' => false, 'error' => 'Warehouse grup tidak bisa dijadikan transit.']);
        }

        Warehouse::where('is_transit', true)->update(['is_transit' => false]);
        $warehouse->update(['is_transit' => true]);

        return response()->json([
            'success' => true,
            'name'    => $warehouse->name,
            'message' => "\"{$warehouse->display_name}\" dijadikan warehouse transit.",
        ]);
    }

    /** Clear default/transit flag */
    public function clearFlag(Request $request, Warehouse $warehouse)
    {
        $flag = $request->input('flag'); // 'is_default' or 'is_transit'
        if (!in_array($flag, ['is_default', 'is_transit'])) {
            return response()->json(['success' => false, 'error' => 'Flag tidak valid.']);
        }
        $warehouse->update([$flag => false]);
        return response()->json(['success' => true]);
    }
}
