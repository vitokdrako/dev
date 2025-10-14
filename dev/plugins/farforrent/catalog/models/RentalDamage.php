<?php namespace Farforrent\Catalog\Models;

use Model;

class RentalDamage extends Model
{
    use \October\Rain\Database\Traits\Validation;

    protected $table = 'farforrent_damages';
    protected $guarded = ['id'];
    
    protected $casts = [
        'repair_cost' => 'decimal:2',
        'cleaning_cost' => 'decimal:2',
        'photos' => 'json'
    ];
    
    public $rules = [
        'order_id' => 'required|exists:farforrent_orders,id',
        'order_item_id' => 'required|exists:farforrent_order_items,id',
        'damage_type' => 'required|in:broken,dirty,lost,chipped,scratched',
        'severity' => 'required|in:minor,major,total',
        'quantity_damaged' => 'required|integer|min:1'
    ];
    
    // Relationships
    public $belongsTo = [
        'order' => [RentalOrder::class, 'key' => 'order_id'],
        'orderItem' => [RentalOrderItem::class, 'key' => 'order_item_id']
    ];
    
    public function getDamageTypeLabel()
    {
        return match($this->damage_type) {
            'broken' => 'Зламано',
            'dirty' => 'Забруднено',
            'lost' => 'Втрачено',
            'chipped' => 'Скол',
            'scratched' => 'Подряпано',
            default => $this->damage_type
        };
    }
    
    public function getSeverityLabel()
    {
        return match($this->severity) {
            'minor' => 'Незначне',
            'major' => 'Суттєве',
            'total' => 'Повне знищення',
            default => $this->severity
        };
    }
    
    public function getTotalCost()
    {
        return $this->repair_cost + $this->cleaning_cost;
    }
    
    public function getSeverityColor()
    {
        return match($this->severity) {
            'minor' => 'yellow',
            'major' => 'orange',
            'total' => 'red',
            default => 'gray'
        };
    }
    
    public function getResolutionLabel()
    {
        return match($this->resolution) {
            'pending' => 'Очікує рішення',
            'repair' => 'Під ремонт',
            'replace' => 'Потребує заміна',
            'charge' => 'Списано з депозита',
            default => $this->resolution
        };
    }
    
    // Auto-calculate costs based on damage type and severity  
    public function beforeSave()
    {
        if (empty($this->repair_cost) && empty($this->cleaning_cost)) {
            $this->autoCalculateCosts();
        }
    }
    
    private function autoCalculateCosts()
    {
        $basePrice = $this->orderItem->deposit_per_unit ?? 100;
        
        // Calculate repair cost based on damage type and severity
        $repairMultiplier = match($this->damage_type) {
            'broken' => 0.8,
            'lost' => 1.0,
            'chipped' => 0.3,
            'scratched' => 0.2,
            'dirty' => 0.0,
            default => 0.1
        };
        
        $severityMultiplier = match($this->severity) {
            'minor' => 0.5,
            'major' => 0.8, 
            'total' => 1.0,
            default => 0.5
        };
        
        if ($this->damage_type === 'dirty') {
            $this->cleaning_cost = $basePrice * 0.1; // 10% за чистку
        } else {
            $this->repair_cost = $basePrice * $repairMultiplier * $severityMultiplier;
        }
    }
}