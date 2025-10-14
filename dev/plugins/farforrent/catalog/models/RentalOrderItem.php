<?php namespace Farforrent\Catalog\Models;

use Model;

class RentalOrderItem extends Model
{
    use \October\Rain\Database\Traits\Validation;

    protected $table = 'farforrent_order_items';
    protected $guarded = ['id'];
    
    protected $casts = [
        'rent_price_per_day' => 'decimal:2',
        'deposit_per_unit' => 'decimal:2',
        'item_total' => 'decimal:2',
        'deposit_total' => 'decimal:2'
    ];
    
    public $rules = [
        'order_id' => 'required|exists:farforrent_orders,id',
        'product_id' => 'required',
        'product_name' => 'required',
        'quantity' => 'required|integer|min:1',
        'rent_price_per_day' => 'required|numeric|min:0',
        'deposit_per_unit' => 'required|numeric|min:0'
    ];
    
    // Relationships
    public $belongsTo = [
        'order' => [RentalOrder::class, 'key' => 'order_id'],
    ];
    
    public $hasMany = [
        'damages' => [RentalDamage::class, 'key' => 'order_item_id']
    ];
    
    // Mutators
    public function beforeSave()
    {
        $this->calculateTotals();
    }
    
    public function calculateTotals()
    {
        if ($this->order && $this->order->rental_days) {
            $this->item_total = $this->quantity * $this->rent_price_per_day * $this->order->rental_days;
            $this->deposit_total = $this->quantity * $this->deposit_per_unit;
        }
    }
    
    public function getStatusColor()
    {
        return match($this->status) {
            'reserved' => 'blue',
            'picked' => 'orange',
            'issued' => 'green', 
            'returned' => 'cyan',
            default => 'gray'
        };
    }
    
    public function getDamageTotal()
    {
        return $this->damages->sum(function($damage) {
            return $damage->repair_cost + $damage->cleaning_cost;
        });
    }
    
    public function hasDamages()
    {
        return $this->damages->count() > 0;
    }
}