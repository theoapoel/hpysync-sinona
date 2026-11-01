<?php
namespace App\Http\Controllers;
use App\Models\{Transaction, Product, Customer, Category};
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller {
    public function index() {
        $today = today();
        $stats = [
            'today_sales' => Transaction::whereDate('created_at',$today)->where('status','completed')->sum('total'),
            'today_count' => Transaction::whereDate('created_at',$today)->where('status','completed')->count(),
            'total_products' => Product::active()->count(),
            'low_stock' => Product::active()->where('track_stock',true)->whereColumn('stock','<=','min_stock')->count(),
            'pending_sync' => Transaction::where('erp_sync_status','pending')->where('status','completed')->count(),
            'total_customers' => Customer::where('is_active',true)->count(),
        ];
        $recentTx = Transaction::with(['user','customer'])->latest()->limit(8)->get();
        $topProducts = DB::table('transaction_items')
            ->select('product_name', DB::raw('SUM(quantity) as total_qty'), DB::raw('SUM(subtotal) as total_revenue'))
            ->whereDate('created_at', '>=', now()->subDays(30))
            ->groupBy('product_name')->orderByDesc('total_qty')->limit(5)->get();
        $salesChart = Transaction::where('status','completed')
            ->where('created_at','>=',now()->subDays(7))
            ->selectRaw('DATE(created_at) as date, SUM(total) as total, COUNT(*) as count')
            ->groupBy('date')->orderBy('date')->get();
        return view('dashboard', compact('stats','recentTx','topProducts','salesChart'));
    }
}
