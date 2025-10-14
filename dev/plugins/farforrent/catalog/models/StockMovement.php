<?php namespace Farforrent\Catalog\Models;

use Model;

class StockMovement extends Model
{
    public $table = 'farforrent_catalog_stock_movements';
    protected $fillable = ['product_id','order_id','type','qty','user_id'];
}
