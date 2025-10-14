<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

use Farforrent\Catalog\Http\Controllers\Api\{
    CardsController,
    OrdersController,
    CalendarController,
    FinanceController,
    DamageController,
    TasksController,
    DictController,
    ProductsController
};

Route::group([
    'prefix'     => 'api/manager',
    'middleware' => ['web'],
], function () {

    // Глобальний патерн для {id}
    Route::pattern('id', '\d+');

    /* ---------- Health / Diagnostics ---------- */

    // Пінг API
    Route::get('ping', function () {
        return response()->json(['ok' => true, 'ts' => now()->toDateTimeString()]);
    });

    // Перевірка конекту до OpenCart
    Route::get('db-ping', function () {
        try {
            $table = 'order'; // зазвичай це oc_order
            $one = DB::connection('opencart')->table($table)->select('order_id')->limit(1)->get();
            return response()->json(['ok' => true, 'table' => $table, 'sample' => $one]);
        } catch (\Throwable $e) {
            return response()->json(['ok' => false, 'error' => $e->getMessage()], 500);
        }
    });

    /* ---------- Orders ---------- */

    // Картки на день (issue/return/new)
    Route::get('orders/today',  'Farforrent\Catalog\Http\Controllers\Api\OrdersController@today');
    // Аліас для сумісності зі старим фронтом (today2)
    Route::get('orders/today2', 'Farforrent\Catalog\Http\Controllers\Api\OrdersController@today');

    // Деталі/CRUD
    Route::get ('orders/{id}',  'Farforrent\Catalog\Http\Controllers\Api\OrdersController@show');
    Route::post('orders',       'Farforrent\Catalog\Http\Controllers\Api\OrdersController@store');
    Route::put ('orders/{id}',  'Farforrent\Catalog\Http\Controllers\Api\OrdersController@update');

    // Операції з ордером
    Route::post('orders/{id}/issue',   'Farforrent\Catalog\Http\Controllers\Api\OrdersController@issue');
    Route::post('orders/{id}/settle',  'Farforrent\Catalog\Http\Controllers\Api\OrdersController@settle');
    Route::post('orders/{id}/close',   'Farforrent\Catalog\Http\Controllers\Api\OrdersController@close');

    // Швидка зміна статусу (використовується фронтом)
    Route::post('order/{id}/status',   'Farforrent\Catalog\Http\Controllers\Api\OrdersController@quickStatus');

    /* ---------- Finance ---------- */

    Route::get('finance/ledger',   'Farforrent\Catalog\Http\Controllers\Api\FinanceController@ledger');
    Route::get('finance/summary',  'Farforrent\Catalog\Http\Controllers\Api\FinanceController@summary');

    /* ---------- Damage ---------- */

    Route::get ('damage',          'Farforrent\Catalog\Http\Controllers\Api\DamageController@index');
    Route::post('damage',          'Farforrent\Catalog\Http\Controllers\Api\DamageController@store');

    /* ---------- Tasks ---------- */

    Route::get ('tasks',           'Farforrent\Catalog\Http\Controllers\Api\TasksController@index');
    Route::post('tasks',           'Farforrent\Catalog\Http\Controllers\Api\TasksController@store');
    Route::put ('tasks/{id}',      'Farforrent\Catalog\Http\Controllers\Api\TasksController@update');

    /* ---------- Calendar (не чіпав) ---------- */

    Route::get('calendar', 'Farforrent\Catalog\Http\Controllers\Api\CalendarController@calendar');

    /* ---------- Cards / Dicts / Catalog ---------- */

    // Генерація наступного SKU, лукап продуктів і створення картки
    Route::get ('sku/next',        'Farforrent\Catalog\Http\Controllers\Api\CardsController@nextSku');
    Route::get ('product/lookup',  'Farforrent\Catalog\Http\Controllers\Api\CardsController@lookup');
    Route::post('cards',           'Farforrent\Catalog\Http\Controllers\Api\CardsController@store');

    // Словники (короткі списки для селектів)
    Route::get('dict/categories',    'Farforrent\Catalog\Http\Controllers\Api\CardsController@dictCategories');
    Route::get('dict/subcategories', 'Farforrent\Catalog\Http\Controllers\Api\CardsController@dictSubcategories');

    // Категорії
    Route::get   ('categories',       'Farforrent\Catalog\Http\Controllers\Api\CardsController@categoriesList');
    Route::post  ('categories/save',  'Farforrent\Catalog\Http\Controllers\Api\CardsController@categorySave');
    Route::delete('categories/{id}',  'Farforrent\Catalog\Http\Controllers\Api\CardsController@categoryDelete');

    // Підкатегорії
    Route::get   ('subcategories',       'Farforrent\Catalog\Http\Controllers\Api\CardsController@subcategoriesList'); // ?category_id=ID
    Route::post  ('subcategories/save',  'Farforrent\Catalog\Http\Controllers\Api\CardsController@subcategorySave');
    Route::delete('subcategories/{id}',  'Farforrent\Catalog\Http\Controllers\Api\CardsController@subcategoryDelete');

    // Універсальний лукап
    Route::get('lookup', 'Farforrent\Catalog\Http\Controllers\Api\CardsController@lookup');
    
    /* ---------- Products (New) ---------- */
    
    // Пошук товарів
    Route::get('products/search', 'Farforrent\Catalog\Http\Controllers\Api\ProductsController@search');
    
    // Отримання одного товару
    Route::get('products/{id}', 'Farforrent\Catalog\Http\Controllers\Api\ProductsController@show');
    
    // Lookup по SKU
    Route::get('product/lookup', 'Farforrent\Catalog\Http\Controllers\Api\ProductsController@lookup');
    
    // Список категорій
    Route::get('categories', 'Farforrent\Catalog\Http\Controllers\Api\ProductsController@categories');
});
