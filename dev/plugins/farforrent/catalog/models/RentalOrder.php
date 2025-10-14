<?php namespace Farforrent\Catalog\Models;

use Model;
use Carbon\Carbon;

class RentalOrder extends Model
{
    use \October\Rain\Database\Traits\Validation;

    protected $table = 'farforrent_orders';
    protected $guarded = ['id'];
    
    protected $dates = [
        'issue_date',
        'return_date', 
        'issued_at',
        'returned_at',
        'created_at',
        'updated_at'
    ];
    
    protected $casts = [
        'issue_date' => 'date',
        'return_date' => 'date',
        'subtotal' => 'decimal:2',
        'deposit_total' => 'decimal:2',
        'prepaid_amount' => 'decimal:2',
        'discount_percent' => 'decimal:2',
        'discount_amount' => 'decimal:2', 
        'damage_amount' => 'decimal:2',
        'cleaning_amount' => 'decimal:2',
        'total_amount' => 'decimal:2'
    ];
    
    public $rules = [
        'number' => 'required|unique:farforrent_orders',
        'customer_name' => 'required',
        'customer_phone' => 'required', 
        'issue_date' => 'required|date',
        'return_date' => 'required|date|after:issue_date'
    ];
    
    // Relationships
    public $hasMany = [
        'items' => [RentalOrderItem::class, 'key' => 'order_id'],
        'payments' => [RentalPayment::class, 'key' => 'order_id'],
        'damages' => [RentalDamage::class, 'key' => 'order_id']
    ];
    
    // Scopes
    public function scopeByStatus($query, $status)
    {
        return $query->where('status', $status);
    }
    
    public function scopeForToday($query)
    {
        return $query->whereDate('issue_date', Carbon::today())
                    ->orWhereDate('return_date', Carbon::today());
    }
    
    // Mutators
    public function beforeCreate()
    {
        if (empty($this->number)) {
            $this->number = $this->generateOrderNumber();
        }
        $this->calculateTotals();
    }
    
    public function beforeUpdate()
    {
        $this->calculateTotals();
    }
    
    // Methods
    public function generateOrderNumber()
    {
        $year = date('Y');
        $lastOrder = static::whereYear('created_at', $year)
            ->orderBy('id', 'desc')
            ->first();
            
        $sequence = $lastOrder ? (intval(substr($lastOrder->number, -4)) + 1) : 1;
        
        return $year . str_pad($sequence, 4, '0', STR_PAD_LEFT);
    }
    
    public function calculateTotals()
    {
        // Calculate rental days
        if ($this->issue_date && $this->return_date) {
            $this->rental_days = $this->issue_date->diffInDays($this->return_date) + 1;
        }
        
        // Calculate totals from items
        if ($this->items) {
            $this->subtotal = $this->items->sum('item_total');
            $this->deposit_total = $this->items->sum('deposit_total');
        }
        
        // Apply discount
        if ($this->discount_percent > 0) {
            $this->discount_amount = $this->subtotal * ($this->discount_percent / 100);
        }
        
        // Calculate final total
        $this->total_amount = $this->subtotal - $this->discount_amount + $this->damage_amount + $this->cleaning_amount;
    }
    
    public function getAmountDue()
    {
        return $this->total_amount - $this->prepaid_amount;
    }
    
    public function getStatusColor()
    {
        return match($this->status) {
            'new' => 'purple',
            'reserved' => 'blue',
            'picked' => 'orange', 
            'issued', 'on_rent' => 'green',
            'returned' => 'cyan',
            'closed' => 'gray',
            'cancelled' => 'red',
            default => 'gray'
        };
    }
    
    public function getStatusLabel()
    {
        return match($this->status) {
            'new' => 'Нове замовлення',
            'reserved' => 'Заброньовано',
            'picked' => 'Зібрано',
            'issued', 'on_rent' => 'Видача',
            'returned' => 'Повернення', 
            'closed' => 'Закрито',
            'cancelled' => 'Скасовано',
            default => 'Невідомо'
        };
    }
    
    // State machine methods
    public function canReserve()
    {
        return in_array($this->status, ['new']);
    }
    
    public function canIssue()
    {
        return in_array($this->status, ['reserved', 'picked']);
    }
    
    public function canReturn()
    {
        return in_array($this->status, ['issued', 'on_rent']);
    }
    
    public function reserve()
    {
        if ($this->canReserve()) {
            $this->status = 'reserved';
            $this->save();
            
            // Reserve inventory for each item
            foreach ($this->items as $item) {
                $item->status = 'reserved';
                $item->save();
            }
            
            return true;
        }
        return false;
    }
    
    public function issue()
    {
        if ($this->canIssue()) {
            $this->status = 'issued';
            $this->issued_at = now();
            $this->save();
            
            foreach ($this->items as $item) {
                $item->status = 'issued';
                $item->save();
            }
            
            return true;
        }
        return false;
    }
    
    public function returnOrder($damageData = [])
    {
        if ($this->canReturn()) {
            $this->status = 'returned';
            $this->returned_at = now();
            
            // Process damages if any
            if (!empty($damageData)) {
                $this->processDamages($damageData);
            }
            
            $this->save();
            
            foreach ($this->items as $item) {
                $item->status = 'returned';
                $item->save();
            }
            
            return true;
        }
        return false;
    }
    
    private function processDamages($damageData)
    {
        foreach ($damageData as $damage) {
            RentalDamage::create(array_merge($damage, [
                'order_id' => $this->id
            ]));
        }
        
        // Recalculate totals with damage costs
        $this->damage_amount = $this->damages->sum('repair_cost');
        $this->cleaning_amount = $this->damages->sum('cleaning_cost');
    }
}