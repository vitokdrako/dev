<?php namespace Farforrent\Catalog\Models;

use Model;

class RentalPayment extends Model
{
    use \October\Rain\Database\Traits\Validation;

    protected $table = 'farforrent_payments';
    protected $guarded = ['id'];
    
    protected $casts = [
        'amount' => 'decimal:2'
    ];
    
    public $rules = [
        'order_id' => 'required|exists:farforrent_orders,id',
        'type' => 'required|in:prepaid,deposit_hold,deposit_release,damage_charge,cleaning_fee,refund',
        'amount' => 'required|numeric|min:0',
        'method' => 'required|in:cash,card,online,bank_transfer'
    ];
    
    // Relationships
    public $belongsTo = [
        'order' => [RentalOrder::class, 'key' => 'order_id']
    ];
    
    public function getTypeLabel()
    {
        return match($this->type) {
            'prepaid' => 'Передплата',
            'deposit_hold' => 'Утримання застави',
            'deposit_release' => 'Повернення застави', 
            'damage_charge' => 'Плата за пошкодження',
            'cleaning_fee' => 'Плата за чистку',
            'refund' => 'Повернення коштів',
            default => $this->type
        };
    }
    
    public function getMethodLabel()
    {
        return match($this->method) {
            'cash' => 'Каса',
            'card' => 'Картка',
            'online' => 'Онлайн платіж',
            'bank_transfer' => 'Банківський переказ',
            default => $this->method
        };
    }
    
    public function getStatusColor()
    {
        return match($this->status) {
            'completed' => 'green',
            'pending' => 'orange',
            'failed' => 'red',
            'refunded' => 'blue',
            default => 'gray'
        };
    }
    
    // Static methods for quick payments
    public static function createPrepayment($orderId, $amount, $method = 'cash')
    {
        return static::create([
            'order_id' => $orderId,
            'type' => 'prepaid',
            'amount' => $amount,
            'method' => $method,
            'status' => 'completed',
            'description' => 'Передплата за замовлення'
        ]);
    }
    
    public static function createDepositHold($orderId, $amount)
    {
        return static::create([
            'order_id' => $orderId,
            'type' => 'deposit_hold',
            'amount' => $amount,
            'method' => 'hold',
            'status' => 'completed',
            'description' => 'Утримання застави'
        ]);
    }
    
    public static function createDamageCharge($orderId, $amount, $description = '')
    {
        return static::create([
            'order_id' => $orderId,
            'type' => 'damage_charge',
            'amount' => $amount,
            'method' => 'deposit_withhold',
            'status' => 'completed',
            'description' => $description ?: 'Плата за пошкодження'
        ]);
    }
}