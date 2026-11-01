<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use Illuminate\Http\Request;

class CouponController extends Controller
{
    public function index(Request $request)
    {
        $search = $request->get('search');
        $coupons = Coupon::when($search, fn($q) => $q->where('code', 'like', "%$search%")
            ->orWhere('description', 'like', "%$search%"))
            ->orderByDesc('id')
            ->paginate(20);

        return view('coupons.index', compact('coupons', 'search'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'code'           => 'required|string|max:50|unique:coupons,code',
            'description'    => 'nullable|string|max:255',
            'discount_type'  => 'required|in:fixed,percent',
            'discount_value' => 'required|numeric|min:0.01',
            'min_purchase'   => 'nullable|numeric|min:0',
            'max_uses'       => 'nullable|integer|min:1',
            'valid_from'     => 'nullable|date',
            'valid_until'    => 'nullable|date|after_or_equal:valid_from',
            'is_active'      => 'boolean',
        ]);

        $data['code']        = strtoupper($data['code']);
        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['is_active']   = $request->boolean('is_active', true);

        Coupon::create($data);

        return redirect()->route('coupons.index')->with('success', 'Kupon berhasil ditambahkan.');
    }

    public function update(Request $request, Coupon $coupon)
    {
        $data = $request->validate([
            'code'           => 'required|string|max:50|unique:coupons,code,' . $coupon->id,
            'description'    => 'nullable|string|max:255',
            'discount_type'  => 'required|in:fixed,percent',
            'discount_value' => 'required|numeric|min:0.01',
            'min_purchase'   => 'nullable|numeric|min:0',
            'max_uses'       => 'nullable|integer|min:1',
            'valid_from'     => 'nullable|date',
            'valid_until'    => 'nullable|date|after_or_equal:valid_from',
            'is_active'      => 'boolean',
        ]);

        $data['code']        = strtoupper($data['code']);
        $data['min_purchase'] = $data['min_purchase'] ?? 0;
        $data['is_active']   = $request->boolean('is_active', false);

        $coupon->update($data);

        return redirect()->route('coupons.index')->with('success', 'Kupon berhasil diperbarui.');
    }

    public function destroy(Coupon $coupon)
    {
        $coupon->delete();
        return redirect()->route('coupons.index')->with('success', 'Kupon berhasil dihapus.');
    }
}
