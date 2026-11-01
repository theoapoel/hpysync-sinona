<?php
namespace App\Http\Controllers;
use App\Models\Customer;
use Illuminate\Http\Request;

class CustomerController extends Controller {
    public function index(Request $request) {
        $query = Customer::query();
        if ($request->search) $query->search($request->search);
        $customers = $query->latest()->paginate(20);
        return view('customers.index', compact('customers'));
    }

    public function store(Request $request) {
        $data = $request->validate([
            'name'=>'required|string|max:255',
            'email'=>'nullable|email',
            'phone'=>'nullable|string',
            'address'=>'nullable|string',
        ]);
        $data['code'] = Customer::generateCode();
        $customer = Customer::create($data);
        if ($request->expectsJson()) return response()->json(['success'=>true,'customer'=>$customer]);
        return redirect()->route('customers.index')->with('success','Customer berhasil ditambahkan!');
    }

    public function update(Request $request, Customer $customer) {
        $data = $request->validate([
            'name'=>'required|string',
            'email'=>'nullable|email',
            'phone'=>'nullable|string',
            'address'=>'nullable|string',
        ]);
        $customer->update($data);
        return redirect()->route('customers.index')->with('success','Customer berhasil diperbarui!');
    }

    public function destroy(Customer $customer) {
        $customer->delete();
        return response()->json(['success'=>true]);
    }

    public function search(Request $request) {
        $customers = Customer::where('is_active',true)->search($request->q ?? '')->limit(10)
            ->get(['id','code','name','phone','email','loyalty_points']);
        return response()->json($customers);
    }
}
