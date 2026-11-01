<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\Category;
use App\Models\ItemCategory;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class ProductController extends Controller
{
    public function index(Request $request)
    {
        $query = Product::with(['category', 'itemCategory']);
        if ($request->search) $query->search($request->search);
        if ($request->category_id) $query->where('category_id', $request->category_id);
        if ($request->item_category_id) $query->where('item_category_id', $request->item_category_id);
        if ($request->status !== null) $query->where('is_active', $request->status);
        $products = $query->latest()->paginate(20);
        $categories = Category::all();
        $itemCategories = ItemCategory::active()->orderBy('name')->get();
        return view('products.index', compact('products', 'categories', 'itemCategories'));
    }

    public function create()
    {
        $categories = Category::where('is_active', true)->get();
        $itemCategories = ItemCategory::active()->orderBy('name')->get();
        return view('products.form', compact('categories', 'itemCategories'));
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products',
            'barcode' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'item_category_id' => 'nullable|exists:item_categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
        ]);
        Product::create($data);
        return redirect()->route('products.index')->with('success', 'Produk berhasil ditambahkan!');
    }

    public function edit(Product $product)
    {
        $categories = Category::where('is_active', true)->get();
        $itemCategories = ItemCategory::active()->orderBy('name')->get();
        return view('products.form', compact('product', 'categories', 'itemCategories'));
    }

    public function update(Request $request, Product $product)
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'sku' => 'required|string|unique:products,sku,' . $product->id,
            'barcode' => 'nullable|string',
            'category_id' => 'nullable|exists:categories,id',
            'item_category_id' => 'nullable|exists:item_categories,id',
            'price' => 'required|numeric|min:0',
            'cost_price' => 'nullable|numeric|min:0',
            'stock' => 'required|integer|min:0',
            'min_stock' => 'nullable|integer|min:0',
            'unit' => 'nullable|string',
            'tax_rate' => 'nullable|numeric|min:0|max:100',
            'description' => 'nullable|string',
            'is_active' => 'boolean',
            'track_stock' => 'boolean',
        ]);
        $product->update($data);
        return redirect()->route('products.index')->with('success', 'Produk berhasil diperbarui!');
    }

    public function destroy(Product $product)
    {
        $product->delete();
        return response()->json(['success' => true]);
    }
}
