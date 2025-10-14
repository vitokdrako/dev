<?php namespace Farforrent\Catalog\Models;
use Model;
class Order extends Model {
  public $table = 'farforrent_orders';
  protected $guarded = [];
  protected $casts = [
    'is_late'=>'bool','is_locked_final'=>'bool',
    'financial_snapshot'=>'array',
  ];
  public $hasMany = [ 'items'=>[OrderItem::class] , 'payments'=>[Payment::class], 'fees'=>[Fee::class] ];
  public $belongsTo = [ 'customer'=>[Customer::class] ];
}
