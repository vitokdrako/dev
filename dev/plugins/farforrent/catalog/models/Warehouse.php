<?php

namespace Farforrent\Rental\Models;

use Model;
use October\Rain\Database\Traits\Validation;

class Warehouse extends Model
{
    use Validation;

    public $table = 'farforrent_rental_warehouses';

    protected $fillable = [
        'name', 'code', 'address', 'contact_person', 'phone', 'email',
        'is_main', 'is_active'
    ];

    protected $rules = [
        'name' => 'required|max:255',
        'code' => 'required|unique:farforrent_rental_warehouses',
        'is_main' => 'boolean',
        'is_active' => 'boolean'
    ];

    public $attributes = [
        'is_main' => false,
        'is_active' => true
    ];

    public $hasMany = [
        'inventoryItems' => [InventoryItem::class],
        'stockMovesFrom' => [StockMove::class, 'key' => 'from_warehouse_id'],
        'stockMovesTo' => [StockMove::class, 'key' => 'to_warehouse_id']
    ];

    public static function getDefault()
    {
        return static::where('is_main', true)->first() ?: static::first();
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
}