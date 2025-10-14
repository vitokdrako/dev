<?php namespace Farforrent\Catalog\Http\Controllers\Api;

use Farforrent\Catalog\Models\Category;
use Farforrent\Catalog\Models\Subcategory;
use Illuminate\Http\Request;

class DictController
{
    public function categories()
    {
        return response()->json(
            Category::orderBy('name_ua')->get(['id','name_ua'])
        );
    }

    public function subcategories(Request $r)
    {
        $cid = (int) $r->query('category_id');
        $q = Subcategory::query();
        if ($cid) $q->where('category_id', $cid);
        return response()->json($q->orderBy('name_ua')->get(['id','name_ua','category_id']));
    }
}
