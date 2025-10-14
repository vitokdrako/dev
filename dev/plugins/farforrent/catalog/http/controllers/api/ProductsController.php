<?php namespace Farforrent\Catalog\Http\Controllers\Api;

use Illuminate\Routing\Controller;
use Illuminate\Http\Request;
use Farforrent\Catalog\Models\Product;
use Farforrent\Catalog\Models\Category;

class ProductsController extends Controller
{
    /**
     * Пошук товарів з фільтрацією
     */
    public function search(Request $request)
    {
        $query = Product::query();
        
        // Тільки активні товари
        $query->where('is_active', 1);
        
        // Пошук по назві або SKU
        if ($search = $request->input('q')) {
            $query->where(function($q) use ($search) {
                $q->where('name', 'like', '%' . $search . '%')
                  ->orWhere('sku', 'like', '%' . $search . '%')
                  ->orWhere('description', 'like', '%' . $search . '%');
            });
        }
        
        // Фільтр по категорії
        if ($categoryId = $request->input('category')) {
            $query->where('category_id', $categoryId);
        }
        
        // Фільтр по підкатегорії
        if ($subcategoryId = $request->input('subcategory')) {
            $query->where('subcategory_id', $subcategoryId);
        }
        
        // Обмеження кількості результатів
        $limit = min($request->input('limit', 50), 100);
        
        $products = $query->with(['category', 'subcategory'])
            ->select([
                'id', 'name', 'sku', 'price', 'image',
                'category_id', 'subcategory_id', 'description', 'sort_order'
            ])
            ->orderBy('sort_order')
            ->orderBy('name')
            ->limit($limit)
            ->get();
        
        // Додати розрахункові поля
        $products = $products->map(function($product) {
            // Депозит = 50% від ціни (або ваша логіка)
            $product->deposit = round($product->price * 0.5, 2);
            $product->price_per_day = $product->price; // Ціна за добу
            return $product;
        });
        
        return response()->json($products, 200, [], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Отримання одного товару
     */
    public function show($id)
    {
        $product = Product::with(['category', 'subcategory'])
            ->findOrFail($id);
        
        // Депозит = 50% від ціни
        $product->deposit = round($product->price * 0.5, 2);
        $product->price_per_day = $product->price;
        
        return response()->json($product, 200, [], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Lookup товару по SKU
     */
    public function lookup(Request $request)
    {
        $sku = $request->input('sku');
        
        if (!$sku) {
            return response()->json(['error' => 'SKU is required'], 400);
        }
        
        $product = Product::with(['category', 'subcategory'])
            ->where('sku', $sku)
            ->where('is_active', 1)
            ->first();
        
        if (!$product) {
            return response()->json(['error' => 'Product not found'], 404);
        }
        
        // Депозит = 50% від ціни
        $product->deposit = round($product->price * 0.5, 2);
        $product->price_per_day = $product->price;
        
        return response()->json($product, 200, [], JSON_UNESCAPED_UNICODE);
    }
    
    /**
     * Отримання категорій
     */
    public function categories()
    {
        $categories = Category::orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'sort_order']);
        
        return response()->json($categories, 200, [], JSON_UNESCAPED_UNICODE);
    }
}
